"""LLM backends. Every request carries the context decision for that model.

Ollama's default num_ctx is a few thousand tokens even when the GGUF says 32k
or 128k. Leaving it alone is why long chats "randomly" fall apart. This module
refuses to send a chat turn without an applied context.
"""

from __future__ import annotations

import json
from typing import Any, Iterator

import httpx

from . import context as context_mod
from . import store
from .hardware import detect_hardware


class LLMError(RuntimeError):
    pass


def _client(timeout: float = 30) -> httpx.Client:
    return httpx.Client(timeout=timeout)


def probe_ollama(url: str) -> dict[str, Any]:
    with _client(4) as client:
        response = client.get(url.rstrip("/") + "/api/tags")
        response.raise_for_status()
        payload = response.json()
    models = [item.get("name") for item in payload.get("models") or [] if item.get("name")]
    return {"ok": True, "backend": "ollama", "models": models}


def probe_llamacpp(url: str) -> dict[str, Any]:
    base = url.rstrip("/")
    with _client(4) as client:
        try:
            response = client.get(base + "/v1/models")
            response.raise_for_status()
            payload = response.json()
            models = [item.get("id") for item in payload.get("data") or [] if item.get("id")]
        except (httpx.HTTPError, json.JSONDecodeError):
            response = client.get(base + "/health")
            response.raise_for_status()
            models = []
    return {"ok": True, "backend": "llamacpp", "models": models or ["loaded"]}


def probe_openai(base: str, key: str) -> dict[str, Any]:
    if not base:
        raise LLMError("OpenAI-compatible base URL is empty")
    headers = {"Authorization": f"Bearer {key}"} if key else {}
    with _client(8) as client:
        response = client.get(base.rstrip("/") + "/models", headers=headers)
        response.raise_for_status()
        payload = response.json()
    models = [item.get("id") for item in payload.get("data") or [] if item.get("id")]
    return {"ok": True, "backend": "openai", "models": models}


def probe_all(settings: dict[str, Any]) -> dict[str, Any]:
    results = {}
    for name, func in (
        ("ollama", lambda: probe_ollama(settings["ollama_url"])),
        ("llamacpp", lambda: probe_llamacpp(settings["llamacpp_url"])),
        ("openai", lambda: probe_openai(settings.get("openai_base") or "", settings.get("openai_key") or "")),
    ):
        try:
            results[name] = func()
        except Exception as exc:  # noqa: BLE001 — probe must never take the UI down
            results[name] = {"ok": False, "backend": name, "error": str(exc), "models": []}
    return results


def list_live_models(settings: dict[str, Any]) -> list[dict[str, str]]:
    found = []
    probes = probe_all(settings)
    for backend, info in probes.items():
        if not info.get("ok"):
            continue
        for model in info.get("models") or []:
            found.append({"backend": backend, "name": model, "key": f"{backend}:{model}"})
    return found


def _hardware(settings: dict[str, Any]) -> dict[str, Any]:
    override = int(settings.get("vram_override_mb") or 0) or None
    return detect_hardware(assume=settings.get("assume_gpu") or "rx590gme", vram_override_mb=override)


def inspect_live_model(model_key: str, settings: dict[str, Any]) -> dict[str, Any]:
    """See the model, then return metadata. model_key is 'backend:name' or a path or a bare name."""
    backend = ""
    name = model_key
    if model_key.startswith("ollama:") or model_key.startswith("llamacpp:") or model_key.startswith("openai:"):
        backend, name = model_key.split(":", 1)
    path_candidate = None
    try:
        from pathlib import Path

        candidate = Path(name).expanduser()
        if candidate.exists():
            path_candidate = candidate
    except OSError:
        path_candidate = None
    if path_candidate is not None:
        return context_mod.inspect_path(path_candidate)
    if backend in ("", "ollama", "auto"):
        try:
            with _client(8) as client:
                response = client.post(
                    settings["ollama_url"].rstrip("/") + "/api/show",
                    json={"name": name},
                )
                if response.status_code == 200:
                    meta = context_mod.meta_from_ollama_show(response.json(), name)
                    meta["id"] = model_key if backend else f"ollama:{name}"
                    return meta
        except (httpx.HTTPError, json.JSONDecodeError, KeyError):
            pass
    if backend in ("", "llamacpp", "auto"):
        try:
            with _client(4) as client:
                props = client.get(settings["llamacpp_url"].rstrip("/") + "/props")
                if props.status_code == 200:
                    payload = props.json()
                    model_path = payload.get("model_path") or payload.get("model")
                    if model_path:
                        try:
                            meta = context_mod.inspect_path(model_path)
                            meta["id"] = model_key or f"llamacpp:{name}"
                            running = (payload.get("default_generation_settings") or {}).get("n_ctx") or payload.get("n_ctx")
                            if running:
                                meta["server_context"] = int(running)
                            return meta
                        except (OSError, context_mod.GGUFError):
                            pass
        except (httpx.HTTPError, json.JSONDecodeError, TypeError, ValueError):
            pass
    meta = context_mod.meta_from_name_only(name)
    meta["id"] = model_key
    return meta


def ensure_decision(model_key: str, settings: dict[str, Any] | None = None, force: bool = False) -> dict[str, Any]:
    settings = settings or store.load_settings()
    hardware = _hardware(settings)
    cached = None if force else store.get_decision(model_key)
    if cached and cached.get("vram_mb") == hardware.get("vram_mb") and cached.get("policy") == (
        "pinned" if cached.get("policy") == "pinned" else settings.get("context_policy")
    ):
        if cached.get("confidence") == "high" or cached.get("policy") == "pinned":
            return cached
    meta = inspect_live_model(model_key, settings)
    pinned = None
    if cached and cached.get("policy") == "pinned":
        pinned = cached.get("applied_context")
    decision = context_mod.decide_context(
        meta,
        hardware,
        policy=settings.get("context_policy") or "safe",
        pinned_context=pinned,
    )
    decision["model_key"] = model_key
    if meta.get("server_context"):
        running = int(meta["server_context"])
        decision["server_context"] = running
        if running < decision["applied_context"]:
            decision["warnings"] = list(decision.get("warnings") or []) + [
                f"llama.cpp is running with -c {running}, but this model should use {decision['applied_context']}. "
                "Context cannot be raised on a running server. Restart it with the command below."
            ]
    store.save_decision(model_key, decision)
    return decision


def trim_messages(messages: list[dict[str, str]], num_ctx: int, max_tokens: int) -> list[dict[str, str]]:
    """Keep the system message and as many recent turns as the applied context can hold.

    Uses 2 characters per token, which is conservative for English and still
    safe-ish for Persian. A too-long prompt is a common 'the model just stopped'
    failure on an 8GB card.
    """
    budget = max(1500, (max(num_ctx, 512) - max_tokens - 256) * 2)
    if not messages:
        return []
    system = [item for item in messages if item.get("role") == "system"][:1]
    rest = [item for item in messages if item.get("role") != "system"]
    kept: list[dict[str, str]] = []
    used = sum(len(item.get("content") or "") for item in system)
    for item in reversed(rest):
        size = len(item.get("content") or "") + 8
        if kept and used + size > budget:
            break
        kept.append(item)
        used += size
    kept.reverse()
    return system + kept


def _resolve_backend(model_key: str, settings: dict[str, Any]) -> tuple[str, str]:
    if model_key.startswith("ollama:"):
        return "ollama", model_key.split(":", 1)[1]
    if model_key.startswith("llamacpp:"):
        return "llamacpp", model_key.split(":", 1)[1]
    if model_key.startswith("openai:"):
        return "openai", model_key.split(":", 1)[1]
    choice = settings.get("llm_backend") or "auto"
    if choice == "auto":
        probes = probe_all(settings)
        for backend in ("ollama", "llamacpp", "openai"):
            if probes.get(backend, {}).get("ok"):
                models = probes[backend].get("models") or []
                if model_key and model_key in models:
                    return backend, model_key
                if models:
                    return backend, models[0] if not model_key else model_key
        raise LLMError("No LLM backend is running. Start Ollama, llama.cpp, or set an OpenAI-compatible URL in Settings.")
    return choice, model_key or settings.get("openai_model") or ""


def stream_chat(
    messages: list[dict[str, str]],
    model_key: str,
    settings: dict[str, Any] | None = None,
    tools: list[dict[str, Any]] | None = None,
) -> Iterator[dict[str, Any]]:
    settings = settings or store.load_settings()
    if not model_key:
        model_key = settings.get("active_model") or ""
    backend, model_name = _resolve_backend(model_key, settings)
    decision = ensure_decision(model_key or f"{backend}:{model_name}", settings)
    num_ctx = int(decision["applied_context"])
    max_tokens = int(decision["max_tokens"])
    trimmed = trim_messages(messages, num_ctx, max_tokens)
    yield {
        "type": "context",
        "applied_context": num_ctx,
        "native_context": decision.get("native_context"),
        "source": decision.get("native_source"),
        "confidence": decision.get("confidence"),
        "clamp_reason": decision.get("clamp_reason"),
        "warnings": decision.get("warnings") or [],
        "backend": backend,
        "model": model_name,
    }
    if backend == "ollama":
        yield from _stream_ollama(trimmed, model_name, settings, decision, tools)
    elif backend == "llamacpp":
        yield from _stream_openai_compatible(
            trimmed,
            model_name or "loaded",
            settings["llamacpp_url"],
            "",
            decision,
            tools,
        )
    elif backend == "openai":
        yield from _stream_openai_compatible(
            trimmed,
            model_name or settings.get("openai_model") or "",
            settings.get("openai_base") or "",
            settings.get("openai_key") or "",
            decision,
            tools,
        )
    else:
        raise LLMError(f"unknown backend {backend}")


def _stream_ollama(messages, model, settings, decision, tools) -> Iterator[dict[str, Any]]:
    options = dict(decision.get("ollama_options") or {})
    payload: dict[str, Any] = {
        "model": model,
        "messages": messages,
        "stream": True,
        "options": options,
    }
    # Ollama tool calling exists on newer builds. Send it only when asked; the
    # text protocol in the system prompt still works on small local models.
    if tools and settings.get("native_tool_calls"):
        payload["tools"] = tools
    url = settings["ollama_url"].rstrip("/") + "/api/chat"
    try:
        with _client(600) as client:
            with client.stream("POST", url, json=payload) as response:
                if response.status_code >= 400:
                    body = response.read().decode("utf-8", errors="replace")
                    raise LLMError(f"Ollama {response.status_code}: {body[:500]}")
                for line in response.iter_lines():
                    if not line:
                        continue
                    try:
                        item = json.loads(line)
                    except json.JSONDecodeError:
                        continue
                    message = item.get("message") or {}
                    if message.get("content"):
                        yield {"type": "token", "text": message["content"]}
                    if message.get("tool_calls"):
                        for call in message["tool_calls"]:
                            fn = call.get("function") or call
                            yield {
                                "type": "tool_call",
                                "name": fn.get("name"),
                                "arguments": fn.get("arguments") or {},
                            }
                    if item.get("done"):
                        yield {"type": "usage", "eval_count": item.get("eval_count"), "prompt_eval_count": item.get("prompt_eval_count")}
    except httpx.HTTPError as exc:
        raise LLMError(f"Ollama request failed: {exc}") from exc


def _stream_openai_compatible(messages, model, base, key, decision, tools) -> Iterator[dict[str, Any]]:
    if not base:
        raise LLMError("backend URL is empty")
    headers = {"Authorization": f"Bearer {key}"} if key else {}
    payload: dict[str, Any] = {
        "model": model,
        "messages": messages,
        "stream": True,
        "max_tokens": int(decision["max_tokens"]),
        "temperature": 0.7,
    }
    # llama.cpp and vLLM both understand extra body fields differently. num_ctx
    # cannot be changed mid-process on llama.cpp; the warning is emitted by
    # ensure_decision. max_tokens still keeps the reply inside the running window.
    if tools and decision:
        payload["tools"] = tools
        payload["tool_choice"] = "auto"
    url = base.rstrip("/") + "/v1/chat/completions"
    try:
        with _client(600) as client:
            with client.stream("POST", url, json=payload, headers=headers) as response:
                if response.status_code >= 400:
                    body = response.read().decode("utf-8", errors="replace")
                    raise LLMError(f"LLM {response.status_code}: {body[:500]}")
                for line in response.iter_lines():
                    if not line or not line.startswith("data:"):
                        continue
                    data = line[5:].strip()
                    if data == "[DONE]":
                        break
                    try:
                        item = json.loads(data)
                    except json.JSONDecodeError:
                        continue
                    choice = (item.get("choices") or [{}])[0]
                    delta = choice.get("delta") or {}
                    if delta.get("content"):
                        yield {"type": "token", "text": delta["content"]}
                    for call in delta.get("tool_calls") or []:
                        fn = call.get("function") or {}
                        if fn.get("name") or fn.get("arguments"):
                            yield {
                                "type": "tool_call_delta",
                                "index": call.get("index") or 0,
                                "name": fn.get("name") or "",
                                "arguments": fn.get("arguments") or "",
                            }
    except httpx.HTTPError as exc:
        raise LLMError(f"LLM request failed: {exc}") from exc
