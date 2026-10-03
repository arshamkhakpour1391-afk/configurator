"""Settings, sessions, memory, and cached context decisions."""

from __future__ import annotations

import json
import sqlite3
import uuid
from datetime import datetime, timezone
from typing import Any

from .util import atomic_write_json, data_dir, read_json


def utcnow() -> str:
    return datetime.now(timezone.utc).replace(microsecond=0).isoformat()


DEFAULT_SETTINGS: dict[str, Any] = {
    "language": "en",
    "llm_backend": "auto",
    "ollama_url": "http://127.0.0.1:11434",
    "llamacpp_url": "http://127.0.0.1:8080",
    "openai_base": "",
    "openai_key": "",
    "openai_model": "",
    "image_backend": "forge",
    "forge_url": "http://127.0.0.1:7860",
    "comfy_url": "http://127.0.0.1:8188",
    "diffusers_model": "",
    "model_dirs": [],
    "context_policy": "safe",
    "assume_gpu": "rx590gme",
    "vram_override_mb": 0,
    "stall_seconds": 180,
    "startup_grace_seconds": 900,
    "dead_timeout": 90,
    "max_recoveries": 8,
    "terminal_enabled": False,
    "native_tool_calls": False,
    "agent_max_iters": 8,
    "active_model": "",
    "first_run": True,
    "vision_model": "",
    "keep_awake": True,
}


def settings_path():
    return data_dir() / "settings.json"


def load_settings() -> dict[str, Any]:
    stored = read_json(settings_path(), {})
    if not isinstance(stored, dict):
        stored = {}
    merged = dict(DEFAULT_SETTINGS)
    merged.update({key: value for key, value in stored.items() if key in DEFAULT_SETTINGS or True})
    # Keep unknown keys so a newer build does not wipe user fields, but always
    # re-introduce defaults that a partial file is missing.
    for key, value in DEFAULT_SETTINGS.items():
        merged.setdefault(key, value)
    return merged


def save_settings(settings: dict[str, Any]) -> dict[str, Any]:
    current = load_settings()
    current.update(settings or {})
    current["stall_seconds"] = int(max(30, min(3600, int(current.get("stall_seconds") or 180))))
    current["startup_grace_seconds"] = int(max(60, min(7200, int(current.get("startup_grace_seconds") or 900))))
    current["dead_timeout"] = int(max(20, min(600, int(current.get("dead_timeout") or 90))))
    current["max_recoveries"] = int(max(1, min(20, int(current.get("max_recoveries") or 8))))
    current["agent_max_iters"] = int(max(1, min(16, int(current.get("agent_max_iters") or 8))))
    if current.get("context_policy") not in ("safe", "max_fit", "native"):
        current["context_policy"] = "safe"
    if not isinstance(current.get("model_dirs"), list):
        current["model_dirs"] = []
    atomic_write_json(settings_path(), current)
    return current


def connect() -> sqlite3.Connection:
    path = data_dir() / "studio.db"
    conn = sqlite3.connect(path, timeout=30)
    conn.row_factory = sqlite3.Row
    conn.execute("PRAGMA journal_mode=WAL")
    conn.execute("PRAGMA foreign_keys=ON")
    return conn


def init_db() -> None:
    with connect() as conn:
        conn.executescript(
            """
            CREATE TABLE IF NOT EXISTS sessions (
              id TEXT PRIMARY KEY,
              title TEXT NOT NULL,
              kind TEXT NOT NULL,
              model TEXT,
              created_at TEXT NOT NULL,
              updated_at TEXT NOT NULL
            );
            CREATE TABLE IF NOT EXISTS messages (
              id INTEGER PRIMARY KEY AUTOINCREMENT,
              session_id TEXT NOT NULL,
              role TEXT NOT NULL,
              content TEXT NOT NULL,
              meta TEXT,
              created_at TEXT NOT NULL
            );
            CREATE TABLE IF NOT EXISTS memories (
              id INTEGER PRIMARY KEY AUTOINCREMENT,
              topic TEXT,
              content TEXT NOT NULL,
              source TEXT,
              created_at TEXT NOT NULL
            );
            CREATE VIRTUAL TABLE IF NOT EXISTS memories_fts USING fts5(
              topic, content, content='memories', content_rowid='id'
            );
            CREATE TABLE IF NOT EXISTS todos (
              id INTEGER PRIMARY KEY AUTOINCREMENT,
              title TEXT NOT NULL,
              done INTEGER NOT NULL DEFAULT 0,
              created_at TEXT NOT NULL
            );
            CREATE TABLE IF NOT EXISTS decisions (
              model_key TEXT PRIMARY KEY,
              payload TEXT NOT NULL,
              updated_at TEXT NOT NULL
            );
            CREATE TABLE IF NOT EXISTS gallery (
              id TEXT PRIMARY KEY,
              prompt TEXT,
              meta TEXT,
              created_at TEXT NOT NULL
            );
            """
        )


def new_id() -> str:
    return uuid.uuid4().hex[:12]


def create_session(kind: str, title: str = "", model: str = "") -> dict[str, Any]:
    init_db()
    session = {
        "id": new_id(),
        "title": title or ("Agent" if kind == "agent" else "Chat"),
        "kind": kind,
        "model": model,
        "created_at": utcnow(),
        "updated_at": utcnow(),
    }
    with connect() as conn:
        conn.execute(
            "INSERT INTO sessions (id, title, kind, model, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?)",
            (session["id"], session["title"], kind, model, session["created_at"], session["updated_at"]),
        )
    return session


def list_sessions(kind: str | None = None) -> list[dict[str, Any]]:
    init_db()
    with connect() as conn:
        if kind:
            rows = conn.execute(
                "SELECT * FROM sessions WHERE kind = ? ORDER BY updated_at DESC", (kind,)
            ).fetchall()
        else:
            rows = conn.execute("SELECT * FROM sessions ORDER BY updated_at DESC").fetchall()
    return [dict(row) for row in rows]


def get_session(session_id: str) -> dict[str, Any] | None:
    init_db()
    with connect() as conn:
        row = conn.execute("SELECT * FROM sessions WHERE id = ?", (session_id,)).fetchone()
    return dict(row) if row else None


def delete_session(session_id: str) -> None:
    init_db()
    with connect() as conn:
        conn.execute("DELETE FROM messages WHERE session_id = ?", (session_id,))
        conn.execute("DELETE FROM sessions WHERE id = ?", (session_id,))


def add_message(session_id: str, role: str, content: str, meta: dict | None = None) -> dict[str, Any]:
    init_db()
    created = utcnow()
    payload = json.dumps(meta or {}, ensure_ascii=False)
    with connect() as conn:
        cur = conn.execute(
            "INSERT INTO messages (session_id, role, content, meta, created_at) VALUES (?, ?, ?, ?, ?)",
            (session_id, role, content, payload, created),
        )
        conn.execute("UPDATE sessions SET updated_at = ? WHERE id = ?", (created, session_id))
        if role == "user":
            row = conn.execute("SELECT title FROM sessions WHERE id = ?", (session_id,)).fetchone()
            if row and row["title"] in ("Chat", "Agent", "", None):
                title = content.strip().splitlines()[0][:60]
                conn.execute("UPDATE sessions SET title = ? WHERE id = ?", (title, session_id))
        message_id = cur.lastrowid
    return {"id": message_id, "session_id": session_id, "role": role, "content": content, "meta": meta or {}, "created_at": created}


def list_messages(session_id: str) -> list[dict[str, Any]]:
    init_db()
    with connect() as conn:
        rows = conn.execute(
            "SELECT * FROM messages WHERE session_id = ? ORDER BY id ASC", (session_id,)
        ).fetchall()
    items = []
    for row in rows:
        item = dict(row)
        try:
            item["meta"] = json.loads(item.get("meta") or "{}")
        except json.JSONDecodeError:
            item["meta"] = {}
        items.append(item)
    return items


def add_memory(content: str, topic: str = "", source: str = "user") -> dict[str, Any]:
    init_db()
    created = utcnow()
    with connect() as conn:
        cur = conn.execute(
            "INSERT INTO memories (topic, content, source, created_at) VALUES (?, ?, ?, ?)",
            (topic, content, source, created),
        )
        row_id = cur.lastrowid
        conn.execute(
            "INSERT INTO memories_fts (rowid, topic, content) VALUES (?, ?, ?)",
            (row_id, topic, content),
        )
    return {"id": row_id, "topic": topic, "content": content, "source": source, "created_at": created}


def search_memory(query: str, limit: int = 8) -> list[dict[str, Any]]:
    init_db()
    query = (query or "").strip()
    with connect() as conn:
        if not query:
            rows = conn.execute(
                "SELECT id, topic, content, source, created_at FROM memories ORDER BY id DESC LIMIT ?",
                (limit,),
            ).fetchall()
            return [dict(row) for row in rows]
        # FTS5 query syntax treats some punctuation as operators. Quote the terms.
        safe = " ".join(f'"{part}"' for part in query.replace('"', " ").split() if part)
        if not safe:
            return []
        try:
            rows = conn.execute(
                """
                SELECT memories.id, memories.topic, memories.content, memories.source, memories.created_at
                FROM memories_fts
                JOIN memories ON memories.id = memories_fts.rowid
                WHERE memories_fts MATCH ?
                ORDER BY rank
                LIMIT ?
                """,
                (safe, limit),
            ).fetchall()
        except sqlite3.OperationalError:
            rows = conn.execute(
                "SELECT id, topic, content, source, created_at FROM memories WHERE content LIKE ? LIMIT ?",
                (f"%{query}%", limit),
            ).fetchall()
    return [dict(row) for row in rows]


def delete_memory(memory_id: int) -> None:
    init_db()
    with connect() as conn:
        conn.execute("DELETE FROM memories_fts WHERE rowid = ?", (memory_id,))
        conn.execute("DELETE FROM memories WHERE id = ?", (memory_id,))


def list_todos() -> list[dict[str, Any]]:
    init_db()
    with connect() as conn:
        rows = conn.execute("SELECT * FROM todos ORDER BY done ASC, id DESC").fetchall()
    return [dict(row) for row in rows]


def add_todo(title: str) -> dict[str, Any]:
    init_db()
    created = utcnow()
    with connect() as conn:
        cur = conn.execute("INSERT INTO todos (title, done, created_at) VALUES (?, 0, ?)", (title, created))
        row_id = cur.lastrowid
    return {"id": row_id, "title": title, "done": 0, "created_at": created}


def finish_todo(todo_id: int) -> None:
    init_db()
    with connect() as conn:
        conn.execute("UPDATE todos SET done = 1 WHERE id = ?", (todo_id,))


def delete_todo(todo_id: int) -> None:
    init_db()
    with connect() as conn:
        conn.execute("DELETE FROM todos WHERE id = ?", (todo_id,))


def save_decision(model_key: str, payload: dict[str, Any]) -> None:
    init_db()
    with connect() as conn:
        conn.execute(
            "INSERT INTO decisions (model_key, payload, updated_at) VALUES (?, ?, ?) "
            "ON CONFLICT(model_key) DO UPDATE SET payload = excluded.payload, updated_at = excluded.updated_at",
            (model_key, json.dumps(payload, ensure_ascii=False), utcnow()),
        )


def get_decision(model_key: str) -> dict[str, Any] | None:
    init_db()
    with connect() as conn:
        row = conn.execute("SELECT payload, updated_at FROM decisions WHERE model_key = ?", (model_key,)).fetchone()
    if not row:
        return None
    try:
        payload = json.loads(row["payload"])
    except json.JSONDecodeError:
        return None
    payload["cached_at"] = row["updated_at"]
    return payload


def list_decisions() -> list[dict[str, Any]]:
    init_db()
    with connect() as conn:
        rows = conn.execute("SELECT payload, updated_at FROM decisions ORDER BY updated_at DESC").fetchall()
    items = []
    for row in rows:
        try:
            payload = json.loads(row["payload"])
        except json.JSONDecodeError:
            continue
        payload["cached_at"] = row["updated_at"]
        items.append(payload)
    return items
