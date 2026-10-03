"""HTTP API and static UI."""

from __future__ import annotations

import json
import threading
from pathlib import Path
from typing import Any

from fastapi import FastAPI, File, Form, HTTPException, UploadFile
from fastapi.middleware.cors import CORSMiddleware
from fastapi.responses import FileResponse, JSONResponse, StreamingResponse
from fastapi.staticfiles import StaticFiles
from starlette.exceptions import HTTPException as StarletteHTTPException

from . import agent as agent_mod
from . import dataset as dataset_mod
from . import generate as generate_mod
from . import health as health_mod
from . import llm as llm_mod
from . import skills as skills_mod
from . import store
from . import training
from .context import decide_context, inspect_path
from .hardware import detect_hardware
from .util import WEB_DIR, data_dir


class APIError(Exception):
    def __init__(self, message: str, status: int = 400):
        self.message = message
        self.status = status


def create_app() -> FastAPI:
    app = FastAPI(title="Polaris Studio", version="6.0.0")
    app.add_middleware(
        CORSMiddleware,
        allow_origins=["*"],
        allow_methods=["*"],
        allow_headers=["*"],
    )

    @app.exception_handler(APIError)
    async def _api_error(_, exc: APIError):
        return JSONResponse({"error": exc.message}, status_code=exc.status)

    @app.exception_handler(FileNotFoundError)
    async def _missing(_, exc: FileNotFoundError):
        return JSONResponse({"error": str(exc) or "not found"}, status_code=404)

    @app.exception_handler(Exception)
    async def _unexpected(_, exc: Exception):
        if isinstance(exc, APIError):
            return JSONResponse({"error": exc.message}, status_code=exc.status)
        if isinstance(exc, (HTTPException, StarletteHTTPException)):
            detail = getattr(exc, "detail", None) or str(exc)
            return JSONResponse({"error": detail}, status_code=exc.status_code)
        return JSONResponse({"error": f"{type(exc).__name__}: {exc}"}, status_code=500)

    @app.on_event("startup")
    def _startup() -> None:
        store.init_db()
        data_dir()
        settings = store.load_settings()
        if settings.get("keep_awake"):
            health_mod.keep_awake(True)
        try:
            training.resume_interrupted()
        except Exception:
            pass

    @app.get("/api/version")
    def version() -> dict[str, str]:
        return {"name": "Polaris Studio", "version": "6.0.0"}

    @app.get("/api/health")
    def health() -> dict[str, Any]:
        return health_mod.scan(store.load_settings())

    @app.get("/api/hardware")
    def hardware() -> dict[str, Any]:
        settings = store.load_settings()
        return detect_hardware(settings.get("assume_gpu") or "rx590gme", int(settings.get("vram_override_mb") or 0) or None)

    @app.get("/api/settings")
    def get_settings() -> dict[str, Any]:
        settings = store.load_settings()
        # Never send the raw key back in full.
        if settings.get("openai_key"):
            settings = dict(settings)
            settings["openai_key_set"] = True
            settings["openai_key"] = ""
        return settings

    @app.put("/api/settings")
    def put_settings(payload: dict[str, Any]) -> dict[str, Any]:
        current = store.load_settings()
        if payload.get("openai_key") == "" and current.get("openai_key"):
            payload = dict(payload)
            payload.pop("openai_key", None)
        return store.save_settings(payload)

    @app.get("/api/gpu")
    def gpu() -> dict[str, Any]:
        return training.GPU.status()

    @app.get("/api/llm/probe")
    def llm_probe() -> dict[str, Any]:
        return llm_mod.probe_all(store.load_settings())

    @app.get("/api/llm/models")
    def llm_models() -> dict[str, Any]:
        settings = store.load_settings()
        live = llm_mod.list_live_models(settings)
        files = _scan_model_files(settings)
        return {"live": live, "files": files, "decisions": store.list_decisions()}

    @app.post("/api/models/inspect")
    def inspect_model(payload: dict[str, Any]) -> dict[str, Any]:
        settings = store.load_settings()
        ref = (payload.get("ref") or "").strip()
        if not ref:
            raise APIError("ref is required")
        hardware = detect_hardware(settings.get("assume_gpu") or "rx590gme", int(settings.get("vram_override_mb") or 0) or None)
        path = Path(ref).expanduser()
        if path.exists():
            meta = inspect_path(path)
            key = str(path)
        else:
            meta = llm_mod.inspect_live_model(ref, settings)
            key = ref
        pinned = int(payload["pinned_context"]) if payload.get("pinned_context") else None
        decision = decide_context(meta, hardware, settings.get("context_policy") or "safe", pinned_context=pinned)
        decision["model_key"] = key
        store.save_decision(key, decision)
        if payload.get("activate"):
            store.save_settings({"active_model": key})
        return decision

    @app.post("/api/models/activate")
    def activate_model(payload: dict[str, Any]) -> dict[str, Any]:
        key = payload.get("model_key") or ""
        if not key:
            raise APIError("model_key is required")
        settings = store.save_settings({"active_model": key})
        decision = llm_mod.ensure_decision(key, settings, force=bool(payload.get("force")))
        return decision

    @app.get("/api/sessions")
    def sessions(kind: str = "") -> list[dict[str, Any]]:
        return store.list_sessions(kind or None)

    @app.post("/api/sessions")
    def new_session(payload: dict[str, Any]) -> dict[str, Any]:
        return store.create_session(payload.get("kind") or "chat", payload.get("title") or "", payload.get("model") or "")

    @app.get("/api/sessions/{session_id}")
    def session_detail(session_id: str) -> dict[str, Any]:
        session = store.get_session(session_id)
        if not session:
            raise APIError("session not found", 404)
        session["messages"] = store.list_messages(session_id)
        return session

    @app.delete("/api/sessions/{session_id}")
    def session_delete(session_id: str) -> dict[str, bool]:
        store.delete_session(session_id)
        return {"ok": True}

    @app.post("/api/chat")
    def chat(payload: dict[str, Any]):
        return StreamingResponse(_chat_stream(payload, agent=False), media_type="text/event-stream")

    @app.post("/api/agent")
    def agent(payload: dict[str, Any]):
        return StreamingResponse(_chat_stream(payload, agent=True), media_type="text/event-stream")

    @app.get("/api/skills")
    def skills() -> list[dict[str, Any]]:
        return skills_mod.list_skills(include_body=False)

    @app.get("/api/skills/{name}")
    def skill_detail(name: str, reference: str = "") -> dict[str, Any]:
        try:
            return skills_mod.get_skill(name, reference or None)
        except skills_mod.SkillError as exc:
            raise APIError(str(exc), 404) from exc

    @app.post("/api/skills")
    def skill_create(payload: dict[str, Any]) -> dict[str, Any]:
        try:
            return skills_mod.write_skill(
                payload.get("name") or "",
                payload.get("description") or "",
                payload.get("body") or "",
                payload.get("category") or "general",
            )
        except skills_mod.SkillError as exc:
            raise APIError(str(exc)) from exc

    @app.post("/api/skills/{name}/toggle")
    def skill_toggle(name: str, payload: dict[str, Any]) -> dict[str, Any]:
        skills_mod.set_enabled(name, bool(payload.get("enabled", True)))
        return {"ok": True}

    @app.get("/api/memory")
    def memory(q: str = "") -> list[dict[str, Any]]:
        return store.search_memory(q)

    @app.post("/api/memory")
    def memory_add(payload: dict[str, Any]) -> dict[str, Any]:
        if not (payload.get("content") or "").strip():
            raise APIError("content is required")
        return store.add_memory(payload["content"], payload.get("topic") or "", "user")

    @app.delete("/api/memory/{memory_id}")
    def memory_delete(memory_id: int) -> dict[str, bool]:
        store.delete_memory(memory_id)
        return {"ok": True}

    @app.get("/api/todos")
    def todos() -> list[dict[str, Any]]:
        return store.list_todos()

    @app.post("/api/todos")
    def todo_add(payload: dict[str, Any]) -> dict[str, Any]:
        if not (payload.get("title") or "").strip():
            raise APIError("title is required")
        return store.add_todo(payload["title"].strip())

    @app.post("/api/todos/{todo_id}/done")
    def todo_done(todo_id: int) -> dict[str, bool]:
        store.finish_todo(todo_id)
        return {"ok": True}

    @app.delete("/api/todos/{todo_id}")
    def todo_delete(todo_id: int) -> dict[str, bool]:
        store.delete_todo(todo_id)
        return {"ok": True}

    @app.get("/api/datasets")
    def datasets() -> list[dict[str, Any]]:
        return dataset_mod.list_datasets()

    @app.post("/api/datasets")
    def dataset_create(payload: dict[str, Any]) -> dict[str, Any]:
        return dataset_mod.create_dataset(payload.get("name") or "Dataset", payload.get("trigger") or "")

    @app.post("/api/datasets/sample")
    def dataset_sample() -> dict[str, Any]:
        return dataset_mod.create_sample()

    @app.get("/api/datasets/{dataset_id}")
    def dataset_get(dataset_id: str) -> dict[str, Any]:
        return dataset_mod.get_dataset(dataset_id)

    @app.delete("/api/datasets/{dataset_id}")
    def dataset_delete(dataset_id: str) -> dict[str, bool]:
        dataset_mod.delete_dataset(dataset_id)
        return {"ok": True}

    @app.post("/api/datasets/{dataset_id}/upload")
    async def dataset_upload(
        dataset_id: str,
        file: UploadFile = File(...),
        caption: str = Form(""),
    ) -> dict[str, Any]:
        raw = await file.read()
        try:
            return dataset_mod.add_image(dataset_id, raw, file.filename or "image.png", caption)
        except ValueError as exc:
            raise APIError(str(exc)) from exc

    @app.put("/api/datasets/{dataset_id}/caption")
    def dataset_caption(dataset_id: str, payload: dict[str, Any]) -> dict[str, bool]:
        try:
            dataset_mod.update_caption(dataset_id, payload.get("name") or "", payload.get("caption") or "")
        except ValueError as exc:
            raise APIError(str(exc)) from exc
        return {"ok": True}

    @app.post("/api/datasets/{dataset_id}/template")
    def dataset_template(dataset_id: str, payload: dict[str, Any]) -> dict[str, int]:
        count = dataset_mod.apply_template(
            dataset_id,
            payload.get("template") or "{trigger}, {name}",
            only_empty=bool(payload.get("only_empty", True)),
        )
        return {"updated": count}

    @app.delete("/api/datasets/{dataset_id}/images/{name}")
    def dataset_image_delete(dataset_id: str, name: str) -> dict[str, bool]:
        dataset_mod.delete_image(dataset_id, name)
        return {"ok": True}

    @app.get("/api/datasets/{dataset_id}/images/{name}")
    def dataset_image(dataset_id: str, name: str):
        return FileResponse(dataset_mod.image_path(dataset_id, name))

    @app.post("/api/datasets/{dataset_id}/autocaption")
    def dataset_autocaption(dataset_id: str, payload: dict[str, Any] | None = None) -> dict[str, Any]:
        settings = store.load_settings()
        model = (payload or {}).get("model") or settings.get("vision_model") or ""
        if not model:
            raise APIError("Set a vision model name (moondream, llava, qwen2.5vl) in the request or Settings.")
        thread = threading.Thread(target=_autocaption, args=(dataset_id, model, settings), daemon=True)
        thread.start()
        return {"status": "started", "model": model}

    @app.get("/api/train/presets")
    def presets() -> dict[str, Any]:
        return training.PRESETS

    @app.post("/api/train/start")
    def train_start(payload: dict[str, Any]) -> dict[str, Any]:
        settings = store.load_settings()
        preset = payload.get("preset") or "rx590-sd15-safe"
        try:
            spec = training.preset_spec(preset)
        except KeyError as exc:
            raise APIError(f"unknown preset {preset}") from exc
        for key, value in (payload.get("spec") or payload).items():
            if key in ("preset", "spec"):
                continue
            if value is not None and value != "":
                spec[key] = value
        if not spec.get("dataset_id"):
            raise APIError("dataset_id is required")
        if spec.get("engine") != "smoke" and not spec.get("base_model"):
            raise APIError("base_model is required — a local SD 1.5 folder or .safetensors file")
        spec["stall_seconds"] = int(payload.get("stall_seconds") or settings.get("stall_seconds") or 180)
        spec["startup_grace_seconds"] = int(settings.get("startup_grace_seconds") or 900)
        spec["dead_timeout"] = int(settings.get("dead_timeout") or 90)
        spec["max_recoveries"] = int(settings.get("max_recoveries") or 8)
        spec["force_fp32"] = True if spec.get("base_family") == "sd15" else spec.get("force_fp32", True)
        try:
            dataset_mod.scan_dataset(spec["dataset_id"])
        except Exception as exc:  # noqa: BLE001
            raise APIError(str(exc)) from exc
        job = training.create_job(spec, kind="lora")
        training.start_job_thread(job["id"])
        return job

    @app.post("/api/train/drill")
    def train_drill() -> dict[str, Any]:
        settings = store.load_settings()
        spec = {
            "engine": "smoke",
            "steps": 6,
            "crash_at": 3,
            "checkpoint_every": 1,
            "step_sleep": 0.05,
            "stall_seconds": 30,
            "startup_grace_seconds": 10,
            "dead_timeout": 30,
            "max_recoveries": int(settings.get("max_recoveries") or 8),
            "name": "Watchdog drill",
        }
        job = training.create_job(spec, kind="drill")
        training.start_job_thread(job["id"])
        return job

    @app.get("/api/train/jobs")
    def train_jobs() -> list[dict[str, Any]]:
        return training.list_jobs()

    @app.get("/api/train/jobs/{job_id}")
    def train_job(job_id: str) -> dict[str, Any]:
        return training.load_job(job_id)

    @app.get("/api/train/jobs/{job_id}/log")
    def train_log(job_id: str) -> dict[str, str]:
        return {"log": training.read_log(job_id)}

    @app.post("/api/train/jobs/{job_id}/stop")
    def train_stop(job_id: str) -> dict[str, Any]:
        return training.request_stop(job_id, pause=False)

    @app.post("/api/train/jobs/{job_id}/pause")
    def train_pause(job_id: str) -> dict[str, Any]:
        return training.request_stop(job_id, pause=True)

    @app.post("/api/train/jobs/{job_id}/resume")
    def train_resume(job_id: str) -> dict[str, Any]:
        return training.resume_job(job_id)

    @app.get("/api/generate/probe")
    def generate_probe() -> dict[str, Any]:
        return generate_mod.probe(store.load_settings())

    @app.post("/api/generate")
    def generate(payload: dict[str, Any]) -> dict[str, Any]:
        if training.GPU.holder == "train":
            raise APIError("Training holds the GPU. Pause it before generating — running both resets an RX 590.", 409)
        token = "gen"
        if not training.GPU.acquire("generate", token):
            raise APIError("GPU is busy.", 409)
        try:
            return generate_mod.generate(store.load_settings(), payload)
        except generate_mod.GenerateError as exc:
            raise APIError(str(exc)) from exc
        finally:
            training.GPU.release(token)

    @app.get("/api/gallery")
    def gallery() -> list[dict[str, Any]]:
        return generate_mod.list_gallery()

    @app.get("/api/gallery/{image_id}")
    def gallery_image(image_id: str):
        try:
            return FileResponse(generate_mod.gallery_file(image_id))
        except generate_mod.GenerateError as exc:
            raise APIError(str(exc), 404) from exc

    @app.get("/api/tools")
    def tools() -> list[dict[str, Any]]:
        return agent_mod.tool_schemas()

    @app.post("/api/tools/run")
    def tools_run(payload: dict[str, Any]) -> dict[str, Any]:
        name = payload.get("name") or ""
        if name == "delegate_task":
            raise APIError("delegate_task is only available inside an agent turn")
        return agent_mod.run_tool(name, payload.get("arguments") or {}, store.load_settings())

    @app.get("/api/backup")
    def backup():
        import io
        import zipfile

        buffer = io.BytesIO()
        with zipfile.ZipFile(buffer, "w", zipfile.ZIP_DEFLATED) as archive:
            settings = store.load_settings()
            redacted = dict(settings)
            if redacted.get("openai_key"):
                redacted["openai_key"] = "***"
            archive.writestr("settings.json", json.dumps(redacted, indent=2))
            archive.writestr("memories.json", json.dumps(store.search_memory(""), indent=2))
            archive.writestr("skills-index.json", json.dumps(skills_mod.list_skills(False), indent=2))
            user_skills = data_dir() / "skills"
            if user_skills.exists():
                for path in user_skills.rglob("*"):
                    if path.is_file():
                        archive.write(path, Path("skills") / path.relative_to(user_skills))
        buffer.seek(0)
        from fastapi.responses import Response

        return Response(
            buffer.getvalue(),
            media_type="application/zip",
            headers={"Content-Disposition": "attachment; filename=polaris-studio-backup.zip"},
        )

    if WEB_DIR.exists():
        app.mount("/", StaticFiles(directory=str(WEB_DIR), html=True), name="web")
    return app


def _scan_model_files(settings: dict[str, Any]) -> list[dict[str, Any]]:
    roots = [data_dir() / "models"]
    for raw in settings.get("model_dirs") or []:
        roots.append(Path(raw).expanduser())
    home = Path.home()
    roots.extend([home / "models", home / "Models"])
    found = []
    seen = set()
    for root in roots:
        if not root.exists() or not root.is_dir():
            continue
        for path in root.rglob("*"):
            if len(found) >= 200:
                return found
            if not path.is_file():
                continue
            if path.suffix.lower() not in {".gguf", ".json"} and path.name != "config.json":
                continue
            if path.suffix.lower() == ".json" and path.name not in {"config.json"}:
                continue
            key = str(path.resolve())
            if key in seen:
                continue
            seen.add(key)
            found.append({"path": key, "name": path.name, "bytes": path.stat().st_size})
    return found


def _sse(payload: dict[str, Any]) -> str:
    return f"data: {json.dumps(payload, ensure_ascii=False)}\n\n"


def _chat_stream(payload: dict[str, Any], agent: bool):
    settings = store.load_settings()
    content = (payload.get("content") or "").strip()
    if not content:
        yield _sse({"type": "error", "message": "empty message"})
        return
    session_id = payload.get("session_id")
    if not session_id:
        session = store.create_session("agent" if agent else "chat")
        session_id = session["id"]
    model_key = payload.get("model") or settings.get("active_model") or ""
    store.add_message(session_id, "user", content)
    yield _sse({"type": "session", "session_id": session_id})
    if agent:
        chunks: list[str] = []
        trace: list[dict[str, Any]] = []
        try:
            for event in agent_mod.run_agent(content, settings, model_key):
                yield _sse(event)
                if event.get("type") == "token":
                    chunks.append(event.get("text") or "")
                elif event.get("type") in {"tool", "tool_result", "context", "error"}:
                    trace.append(event)
                if event.get("type") == "done" and event.get("text") and not chunks:
                    chunks.append(event["text"])
        except Exception as exc:  # noqa: BLE001
            yield _sse({"type": "error", "message": str(exc)})
            return
        store.add_message(session_id, "assistant", "".join(chunks), {"trace": trace})
        return
    history = store.list_messages(session_id)
    messages = [{"role": item["role"], "content": item["content"]} for item in history if item["role"] in {"user", "assistant"}]
    chunks = []
    context_event = None
    try:
        for event in llm_mod.stream_chat(messages, model_key, settings):
            yield _sse(event)
            if event.get("type") == "token":
                chunks.append(event.get("text") or "")
            if event.get("type") == "context":
                context_event = event
    except llm_mod.LLMError as exc:
        yield _sse({"type": "error", "message": str(exc)})
        return
    store.add_message(session_id, "assistant", "".join(chunks), {"context": context_event})
    yield _sse({"type": "done"})


def _autocaption(dataset_id: str, model: str, settings: dict[str, Any]) -> None:
    import httpx

    from .util import atomic_write_json, read_json

    meta_path = dataset_mod._meta_path(dataset_id)
    meta = read_json(meta_path, {})
    images = dataset_mod.scan_dataset(dataset_id)["images"]
    meta["autocaption"] = {"status": "running", "done": 0, "total": len(images), "error": ""}
    atomic_write_json(meta_path, meta)
    decision = None
    try:
        decision = llm_mod.ensure_decision(model if ":" in model else f"ollama:{model}", settings)
    except Exception:
        decision = None
    options = (decision or {}).get("ollama_options") or {"num_ctx": 2048}
    for index, item in enumerate(images, start=1):
        path = dataset_mod.image_path(dataset_id, item["name"])
        import base64

        b64 = base64.b64encode(path.read_bytes()).decode("ascii")
        prompt = (
            "Write one training caption for this image. One line, concrete nouns, no style words, "
            "no artist names. If a trigger word is known, start with it: "
            + (meta.get("trigger") or "")
        )
        try:
            with httpx.Client(timeout=120) as client:
                response = client.post(
                    settings["ollama_url"].rstrip("/") + "/api/chat",
                    json={
                        "model": model.split(":", 1)[-1],
                        "stream": False,
                        "options": options,
                        "messages": [{"role": "user", "content": prompt, "images": [b64]}],
                    },
                )
                response.raise_for_status()
                caption = ((response.json().get("message") or {}).get("content") or "").strip()
            if caption:
                dataset_mod.update_caption(dataset_id, item["name"], caption.splitlines()[0][:400])
        except Exception as exc:  # noqa: BLE001
            meta = read_json(meta_path, meta)
            meta["autocaption"] = {"status": "error", "done": index - 1, "total": len(images), "error": str(exc)}
            atomic_write_json(meta_path, meta)
            return
        meta = read_json(meta_path, meta)
        meta["autocaption"] = {"status": "running", "done": index, "total": len(images), "error": ""}
        atomic_write_json(meta_path, meta)
    meta = read_json(meta_path, meta)
    meta["autocaption"] = {"status": "done", "done": len(images), "total": len(images), "error": ""}
    atomic_write_json(meta_path, meta)


app = create_app()
