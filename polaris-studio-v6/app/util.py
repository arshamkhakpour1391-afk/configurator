"""Small shared helpers. No I/O policy lives anywhere else."""

from __future__ import annotations

import json
import os
import tempfile
from pathlib import Path
from typing import Any


ROOT = Path(__file__).resolve().parents[1]
WEB_DIR = ROOT / "web"
SKILLS_DIR = ROOT / "skills"


def data_dir() -> Path:
    override = os.environ.get("POLARIS_DATA")
    path = Path(override).expanduser() if override else ROOT / "data"
    path.mkdir(parents=True, exist_ok=True)
    return path


def atomic_write_text(path: Path, text: str) -> None:
    path.parent.mkdir(parents=True, exist_ok=True)
    fd, tmp_name = tempfile.mkstemp(prefix=path.name + ".", dir=str(path.parent))
    try:
        with os.fdopen(fd, "w", encoding="utf-8") as handle:
            handle.write(text)
            handle.flush()
            os.fsync(handle.fileno())
        os.replace(tmp_name, path)
    except Exception:
        try:
            os.unlink(tmp_name)
        except OSError:
            pass
        raise


def atomic_write_json(path: Path, payload: Any) -> None:
    atomic_write_text(path, json.dumps(payload, indent=2, ensure_ascii=False))


def read_json(path: Path, default: Any) -> Any:
    try:
        return json.loads(path.read_text(encoding="utf-8"))
    except FileNotFoundError:
        return default
    except json.JSONDecodeError:
        # A crash mid-write used to leave an empty file and take the studio down
        # with it. Keep the broken file for debugging and continue on the default.
        broken = path.with_suffix(path.suffix + ".broken")
        try:
            os.replace(path, broken)
        except OSError:
            pass
        return default


def safe_join(root: Path, *parts: str) -> Path:
    root = root.resolve()
    if any(part in ("", ".", "..") or "/" in part or "\\" in part or part.startswith("..") for part in parts):
        # Allow nested relative paths only through an explicit relative string
        # that we normalize below. Reject absolute and parent segments here when
        # they are passed as separate parts.
        for part in parts:
            if part.startswith(("/", "\\")) or Path(part).is_absolute() or ".." in Path(part).parts:
                raise ValueError("path escapes root")
    candidate = root.joinpath(*parts).resolve()
    if candidate != root and root not in candidate.parents:
        raise ValueError("path escapes root")
    return candidate


def safe_relative(root: Path, relative: str) -> Path:
    relative = (relative or "").replace("\\", "/").lstrip("/")
    if not relative or relative.startswith("../") or "/../" in f"/{relative}/" or relative == "..":
        raise ValueError("path escapes root")
    parts = [part for part in relative.split("/") if part not in ("", ".")]
    if any(part == ".." for part in parts):
        raise ValueError("path escapes root")
    return safe_join(root, *parts)


def clamp(value: int, low: int, high: int) -> int:
    return max(low, min(high, value))


def align_down(value: int, base: int = 256) -> int:
    value = int(value)
    if value < base:
        return base
    return value - (value % base)
