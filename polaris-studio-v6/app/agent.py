"""Hermes-style agent loop.

Skills are disclosed progressively: the prompt only sees name + description.
The body is loaded by skill_view, reference files by a second call. Tools match
the Hermes names where that makes a skill written for Hermes readable here
(skill_view, memory, terminal, web_extract, todo, process, clarify).
"""

from __future__ import annotations

import json
import re
import shlex
import subprocess
from pathlib import Path
from typing import Any, Iterator

from . import dataset as dataset_mod
from . import generate as generate_mod
from . import llm as llm_mod
from . import skills as skills_mod
from . import store
from . import training
from .context import decide_context, inspect_path
from .hardware import detect_hardware
from .util import data_dir, safe_relative


TOOL_DENY = re.compile(
    r"(\brm\s+-rf\s+/|\bmkfs\b|\bdd\s+if=|:\(\)\s*\{|\bshutdown\b|\breboot\b|"
    r"\bformat\s+[a-z]:|Remove-Item\s+-Recurse\s+-Force\s+[A-Za-z]:\\|"
    r"\bdel\s+/[sq].*[A-Za-z]:\\Windows)",
    re.I,
)


def parse_tool_calls(text: str) -> list[dict[str, Any]]:
    calls = []
    if not text:
        return calls
    cursor = 0
    needle = "<tool_call>"
    while True:
        start = text.find(needle, cursor)
        if start < 0:
            break
        index = start + len(needle)
        while index < len(text) and text[index].isspace():
            index += 1
        if index >= len(text) or text[index] != "{":
            cursor = start + len(needle)
            continue
        try:
            obj, offset = json.JSONDecoder().raw_decode(text[index:])
        except json.JSONDecodeError:
            cursor = start + len(needle)
            continue
        if isinstance(obj, dict) and obj.get("name"):
            arguments = obj.get("arguments") or {}
            if isinstance(arguments, str):
                try:
                    arguments = json.loads(arguments)
                except json.JSONDecodeError:
                    arguments = {"raw": arguments}
            calls.append({"name": obj["name"], "arguments": arguments})
        cursor = index + offset
    return calls


def tool_schemas() -> list[dict[str, Any]]:
    def schema(name: str, description: str, properties: dict, required: list[str] | None = None) -> dict[str, Any]:
        return {
            "type": "function",
            "function": {
                "name": name,
                "description": description,
                "parameters": {
                    "type": "object",
                    "properties": properties,
                    "required": required or [],
                },
            },
        }

    return [
        schema("skill_view", "Load a skill body, or one reference file inside it. Do this before following a skill.", {"name": {"type": "string"}, "reference": {"type": "string"}}, ["name"]),
        schema("skill_manage", "Create a user skill. action=create.", {"action": {"type": "string"}, "name": {"type": "string"}, "description": {"type": "string"}, "body": {"type": "string"}, "category": {"type": "string"}}, ["action", "name", "description", "body"]),
        schema("memory", "Durable facts. action is add, search, or forget.", {"action": {"type": "string"}, "content": {"type": "string"}, "query": {"type": "string"}, "topic": {"type": "string"}, "id": {"type": "integer"}}, ["action"]),
        schema("todo", "Track multi-step work. action is add, list, or done.", {"action": {"type": "string"}, "title": {"type": "string"}, "id": {"type": "integer"}}, ["action"]),
        schema("read_file", "Read a file inside the studio workspace.", {"path": {"type": "string"}}, ["path"]),
        schema("write_file", "Write a file inside the studio workspace.", {"path": {"type": "string"}, "content": {"type": "string"}}, ["path", "content"]),
        schema("search_files", "Find a text snippet under the studio workspace.", {"query": {"type": "string"}}, ["query"]),
        schema("terminal", "Run a command in the studio workspace. Disabled unless Settings allow it.", {"command": {"type": "string"}, "timeout": {"type": "integer"}}, ["command"]),
        schema("web_extract", "Fetch a URL and return plain text.", {"url": {"type": "string"}, "max_chars": {"type": "integer"}}, ["url"]),
        schema("process", "Background jobs. action is list, poll, log, or stop.", {"action": {"type": "string"}, "id": {"type": "string"}}, ["action"]),
        schema("model_inspect", "Read a model file or name and compute the context window for this GPU.", {"ref": {"type": "string"}}, ["ref"]),
        schema("hardware_report", "Current GPU profile and RX 590 advice.", {}),
        schema("dataset_scan", "Validate a dataset.", {"id": {"type": "string"}}, ["id"]),
        schema("train_control", "Start, status, pause, resume, or stop training. action=start needs a spec.", {"action": {"type": "string"}, "id": {"type": "string"}, "spec": {"type": "object"}}, ["action"]),
        schema("image_generate", "Generate one image on the configured Forge/Comfy backend.", {"prompt": {"type": "string"}, "negative": {"type": "string"}, "width": {"type": "integer"}, "height": {"type": "integer"}, "steps": {"type": "integer"}, "seed": {"type": "integer"}}, ["prompt"]),
        schema("clarify", "Ask the user one blocking question and stop.", {"question": {"type": "string"}}, ["question"]),
        schema("delegate_task", "Run a short nested agent on a subtask and return its answer. No further delegation.", {"goal": {"type": "string"}}, ["goal"]),
    ]


def workspace_dir() -> Path:
    path = data_dir() / "workspace"
    path.mkdir(parents=True, exist_ok=True)
    return path


def _workspace_file(relative: str) -> Path:
    return safe_relative(workspace_dir(), relative)


def run_tool(name: str, arguments: dict[str, Any], settings: dict[str, Any], depth: int = 0) -> dict[str, Any]:
    arguments = arguments or {}
    try:
        if name == "skill_view":
            skill = skills_mod.get_skill(arguments.get("name") or "", arguments.get("reference"))
            return {
                "name": skill["name"],
                "description": skill["description"],
                "body": skill.get("body") if not arguments.get("reference") else "",
                "reference": skill.get("reference"),
                "reference_text": skill.get("reference_text"),
                "references": skill.get("references"),
            }
        if name == "skill_manage":
            if arguments.get("action") != "create":
                return {"error": "only action=create is supported"}
            skill = skills_mod.write_skill(
                arguments.get("name") or "",
                arguments.get("description") or "",
                arguments.get("body") or "",
                arguments.get("category") or "general",
            )
            return {"created": skill["name"]}
        if name == "memory":
            action = arguments.get("action")
            if action == "add":
                if not arguments.get("content"):
                    return {"error": "content is required"}
                return store.add_memory(arguments["content"], arguments.get("topic") or "", "agent")
            if action == "search":
                return {"matches": store.search_memory(arguments.get("query") or arguments.get("content") or "")}
            if action == "forget":
                store.delete_memory(int(arguments.get("id")))
                return {"ok": True}
            return {"error": "action must be add, search, or forget"}
        if name == "todo":
            action = arguments.get("action")
            if action == "add":
                return store.add_todo(arguments.get("title") or "")
            if action == "list":
                return {"items": store.list_todos()}
            if action == "done":
                store.finish_todo(int(arguments.get("id")))
                return {"ok": True}
            return {"error": "action must be add, list, or done"}
        if name == "read_file":
            path = _workspace_file(arguments.get("path") or "")
            if not path.is_file():
                return {"error": "file not found"}
            text = path.read_text(encoding="utf-8", errors="replace")
            return {"path": arguments.get("path"), "content": text[:20000], "truncated": len(text) > 20000}
        if name == "write_file":
            path = _workspace_file(arguments.get("path") or "")
            path.parent.mkdir(parents=True, exist_ok=True)
            path.write_text(arguments.get("content") or "", encoding="utf-8")
            return {"ok": True, "path": arguments.get("path")}
        if name == "search_files":
            query = (arguments.get("query") or "").strip()
            if not query:
                return {"error": "query is empty"}
            hits = []
            for file_path in workspace_dir().rglob("*"):
                if not file_path.is_file() or file_path.stat().st_size > 1_000_000:
                    continue
                try:
                    text = file_path.read_text(encoding="utf-8", errors="ignore")
                except OSError:
                    continue
                if query.lower() in text.lower():
                    hits.append(str(file_path.relative_to(workspace_dir())))
                if len(hits) >= 30:
                    break
            return {"hits": hits}
        if name == "terminal":
            if not settings.get("terminal_enabled"):
                return {"error": "terminal tool is disabled in Settings"}
            command = arguments.get("command") or ""
            if TOOL_DENY.search(command):
                return {"error": "command blocked by the studio denylist"}
            timeout = max(1, min(60, int(arguments.get("timeout") or 20)))
            try:
                args = shlex.split(command, posix=os_posix())
            except ValueError as exc:
                return {"error": f"could not parse command: {exc}"}
            if not args:
                return {"error": "empty command"}
            try:
                completed = subprocess.run(
                    args,
                    cwd=str(workspace_dir()),
                    capture_output=True,
                    text=True,
                    timeout=timeout,
                    check=False,
                )
            except subprocess.TimeoutExpired:
                return {"error": f"timed out after {timeout}s"}
            except OSError as exc:
                return {"error": str(exc)}
            return {
                "code": completed.returncode,
                "stdout": (completed.stdout or "")[-8000:],
                "stderr": (completed.stderr or "")[-4000:],
            }
        if name == "web_extract":
            return _web_extract(arguments.get("url") or "", int(arguments.get("max_chars") or 8000))
        if name == "process":
            action = arguments.get("action") or "list"
            if action == "list":
                return {"jobs": [{"id": job["id"], "status": job["status"], "step": job.get("step"), "message": job.get("message")} for job in training.list_jobs()[:20]]}
            job_id = arguments.get("id") or ""
            if action == "poll":
                return training.load_job(job_id)
            if action == "log":
                return {"log": training.read_log(job_id)}
            if action == "stop":
                return training.request_stop(job_id, pause=False)
            return {"error": "unknown process action"}
        if name == "model_inspect":
            hardware = detect_hardware(settings.get("assume_gpu") or "rx590gme", int(settings.get("vram_override_mb") or 0) or None)
            ref = arguments.get("ref") or ""
            try:
                meta = inspect_path(ref)
            except OSError:
                meta = llm_mod.inspect_live_model(ref, settings)
            decision = decide_context(meta, hardware, settings.get("context_policy") or "safe")
            store.save_decision(decision["model_key"], decision)
            return decision
        if name == "hardware_report":
            return detect_hardware(settings.get("assume_gpu") or "rx590gme", int(settings.get("vram_override_mb") or 0) or None)
        if name == "dataset_scan":
            return dataset_mod.scan_dataset(arguments.get("id") or "")
        if name == "train_control":
            return _train_control(arguments, settings)
        if name == "image_generate":
            if training.GPU.holder == "train":
                return {"error": "training holds the GPU. Pause it before generating, or the driver will reset."}
            if not training.GPU.acquire("generate", "agent"):
                return {"error": "GPU is busy"}
            try:
                return generate_mod.generate(settings, arguments)
            finally:
                training.GPU.release("agent")
        if name == "clarify":
            return {"clarify": arguments.get("question") or "What should I do next?", "stop": True}
        if name == "delegate_task":
            if depth >= 1:
                return {"error": "delegation depth exceeded"}
            goal = arguments.get("goal") or ""
            answer = _delegate(goal, settings)
            return {"answer": answer}
        return {"error": f"unknown tool {name}"}
    except Exception as exc:  # noqa: BLE001 — tools must return errors, not kill the loop
        return {"error": f"{type(exc).__name__}: {exc}"}


def os_posix() -> bool:
    import os

    return os.name != "nt"


def _web_extract(url: str, max_chars: int) -> dict[str, Any]:
    import httpx

    if not url.startswith(("http://", "https://")):
        return {"error": "only http(s) URLs"}
    max_chars = max(500, min(20000, max_chars))
    try:
        with httpx.Client(timeout=20, follow_redirects=True) as client:
            response = client.get(url, headers={"User-Agent": "PolarisStudio/6"})
            response.raise_for_status()
            text = response.text
    except Exception as exc:  # noqa: BLE001
        return {"error": str(exc)}
    text = re.sub(r"(?is)<script.*?>.*?</script>", " ", text)
    text = re.sub(r"(?is)<style.*?>.*?</style>", " ", text)
    text = re.sub(r"<[^>]+>", " ", text)
    text = re.sub(r"\s+", " ", text).strip()
    return {"url": url, "text": text[:max_chars], "truncated": len(text) > max_chars}


def _train_control(arguments: dict[str, Any], settings: dict[str, Any]) -> dict[str, Any]:
    action = arguments.get("action") or "status"
    if action == "status":
        jobs = training.list_jobs()
        return {"jobs": jobs[:10], "gpu": training.GPU.status()}
    if action == "pause":
        return training.request_stop(arguments.get("id") or "", pause=True)
    if action == "stop":
        return training.request_stop(arguments.get("id") or "", pause=False)
    if action == "resume":
        return training.resume_job(arguments.get("id") or "")
    if action == "start":
        spec = dict(arguments.get("spec") or {})
        if not spec.get("dataset_id"):
            return {"error": "spec.dataset_id is required"}
        preset = spec.get("preset") or "rx590-sd15-safe"
        merged = training.preset_spec(preset)
        merged.update({key: value for key, value in spec.items() if value is not None})
        merged["stall_seconds"] = settings.get("stall_seconds")
        merged["startup_grace_seconds"] = settings.get("startup_grace_seconds")
        merged["dead_timeout"] = settings.get("dead_timeout")
        merged["max_recoveries"] = settings.get("max_recoveries")
        job = training.create_job(merged, kind="lora")
        training.start_job_thread(job["id"])
        return {"id": job["id"], "status": "queued"}
    return {"error": "unknown train action"}


def _delegate(goal: str, settings: dict[str, Any]) -> str:
    chunks = []
    for event in run_agent(goal, settings, model_key=settings.get("active_model") or "", depth=1):
        if event.get("type") == "token":
            chunks.append(event.get("text") or "")
        if event.get("type") == "done":
            break
    return "".join(chunks)[-4000:]


def system_prompt(settings: dict[str, Any]) -> str:
    hardware = detect_hardware(settings.get("assume_gpu") or "rx590gme", int(settings.get("vram_override_mb") or 0) or None)
    memories = store.search_memory("", limit=5)
    memory_text = "\n".join(f"- {item['content']}" for item in memories) or "(none)"
    return f"""You are the agent inside Polaris Studio v6, a local workstation tuned for an AMD RX 590 GME (8GB Polaris).
You have tools. Use them. Do not invent tool results, file contents, or image outputs.

Hardware: {hardware.get('gpu_name')} / {hardware.get('vram_mb')}MB / assumed={hardware.get('assumed')}.
Context windows are computed per model and applied automatically. Do not tell the user to 'just set num_ctx to 128k' on this card.

Skills (load with skill_view before following one):
{skills_mod.skill_index_text()}

Recent memory:
{memory_text}

To call a tool, emit exactly one or more blocks:
<tool_call>
{{"name":"tool_name","arguments":{{}}}}
</tool_call>
After the tool result, continue. If you are done, answer in plain text with no tool call.
If you are blocked on the user, call clarify.
"""


def run_agent(content: str, settings: dict[str, Any], model_key: str, depth: int = 0) -> Iterator[dict[str, Any]]:
    max_iters = 4 if depth else int(settings.get("agent_max_iters") or 8)
    messages = [
        {"role": "system", "content": system_prompt(settings)},
        {"role": "user", "content": content},
    ]
    tools = tool_schemas() if settings.get("native_tool_calls") else None
    for _ in range(max_iters):
        parts: list[str] = []
        tool_calls: list[dict[str, Any]] = []
        try:
            for event in llm_mod.stream_chat(messages, model_key, settings, tools=tools):
                if event.get("type") == "token":
                    parts.append(event.get("text") or "")
                    yield event
                elif event.get("type") == "tool_call":
                    tool_calls.append({"name": event.get("name"), "arguments": event.get("arguments") or {}})
                    yield {"type": "tool", "name": event.get("name"), "arguments": event.get("arguments") or {}}
                elif event.get("type") == "context":
                    yield event
        except llm_mod.LLMError as exc:
            yield {"type": "error", "message": str(exc)}
            return
        text = "".join(parts)
        parsed = parse_tool_calls(text)
        # Native calls win if present; also accept the text protocol.
        if not tool_calls:
            tool_calls = parsed
            for call in parsed:
                yield {"type": "tool", "name": call["name"], "arguments": call["arguments"]}
        if not tool_calls:
            yield {"type": "done", "text": text}
            return
        messages.append({"role": "assistant", "content": text})
        for call in tool_calls[:3]:
            result = run_tool(call.get("name") or "", call.get("arguments") or {}, settings, depth=depth)
            yield {"type": "tool_result", "name": call.get("name"), "result": result}
            messages.append(
                {
                    "role": "user",
                    "content": f"Tool result for {call.get('name')}:\n{json.dumps(result, ensure_ascii=False)[:12000]}",
                }
            )
            if result.get("stop") or result.get("clarify"):
                yield {"type": "done", "text": result.get("clarify") or text}
                return
    yield {"type": "done", "text": "Stopped: iteration budget reached."}
