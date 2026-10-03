"""Image generation clients.

Nothing here invents an image. If Forge, ComfyUI, or a local diffusers
pipeline is not reachable, the call fails with the setup step that is missing.
"""

from __future__ import annotations

import base64
import json
import uuid
from pathlib import Path
from typing import Any

import httpx

from .util import atomic_write_json, data_dir


class GenerateError(RuntimeError):
    pass


def gallery_dir() -> Path:
    path = data_dir() / "gallery"
    path.mkdir(parents=True, exist_ok=True)
    return path


def probe_forge(url: str) -> dict[str, Any]:
    with httpx.Client(timeout=4) as client:
        response = client.get(url.rstrip("/") + "/sdapi/v1/sd-models")
        response.raise_for_status()
        models = response.json()
    names = []
    if isinstance(models, list):
        names = [item.get("title") or item.get("model_name") for item in models if isinstance(item, dict)]
    return {"ok": True, "backend": "forge", "models": [name for name in names if name]}


def probe_comfy(url: str) -> dict[str, Any]:
    with httpx.Client(timeout=4) as client:
        response = client.get(url.rstrip("/") + "/system_stats")
        response.raise_for_status()
    return {"ok": True, "backend": "comfy", "models": []}


def probe(settings: dict[str, Any]) -> dict[str, Any]:
    results = {}
    for name, func in (
        ("forge", lambda: probe_forge(settings.get("forge_url") or "")),
        ("comfy", lambda: probe_comfy(settings.get("comfy_url") or "")),
    ):
        try:
            results[name] = func()
        except Exception as exc:  # noqa: BLE001
            results[name] = {"ok": False, "backend": name, "error": str(exc), "models": []}
    results["diffusers"] = {
        "ok": bool(settings.get("diffusers_model")),
        "backend": "diffusers",
        "error": "" if settings.get("diffusers_model") else "Set a local SD 1.5 path in Settings to generate without Forge.",
        "models": [settings.get("diffusers_model")] if settings.get("diffusers_model") else [],
    }
    return results


def _save_png(raw: bytes, meta: dict[str, Any]) -> dict[str, Any]:
    image_id = uuid.uuid4().hex[:12]
    folder = gallery_dir()
    (folder / f"{image_id}.png").write_bytes(raw)
    atomic_write_json(folder / f"{image_id}.json", meta)
    from .store import utcnow

    meta = dict(meta)
    meta["id"] = image_id
    meta["created_at"] = utcnow()
    atomic_write_json(folder / f"{image_id}.json", meta)
    try:
        from .store import connect, init_db

        init_db()
        with connect() as conn:
            conn.execute(
                "INSERT OR REPLACE INTO gallery (id, prompt, meta, created_at) VALUES (?, ?, ?, ?)",
                (image_id, meta.get("prompt") or "", json.dumps(meta), meta["created_at"]),
            )
    except Exception:
        pass
    return meta


def list_gallery(limit: int = 60) -> list[dict[str, Any]]:
    items = []
    files = sorted(gallery_dir().glob("*.json"), key=lambda path: path.stat().st_mtime, reverse=True)
    for path in files[:limit]:
        try:
            items.append(json.loads(path.read_text(encoding="utf-8")))
        except json.JSONDecodeError:
            continue
    return items


def gallery_file(image_id: str) -> Path:
    if not image_id or "/" in image_id or ".." in image_id:
        raise GenerateError("bad id")
    path = gallery_dir() / f"{image_id}.png"
    if not path.is_file():
        raise GenerateError("image not found")
    return path


def _forge_payload(params: dict[str, Any]) -> dict[str, Any]:
    prompt = params.get("prompt") or ""
    lora = (params.get("lora") or "").strip()
    if lora:
        weight = float(params.get("lora_weight") or 0.8)
        prompt = f"{prompt} <lora:{lora}:{weight}>"
    seed = params.get("seed")
    if seed is None or seed == "" or int(seed) < 0:
        seed = -1
    return {
        "prompt": prompt,
        "negative_prompt": params.get("negative") or "",
        "steps": int(params.get("steps") or 22),
        "width": int(params.get("width") or 512),
        "height": int(params.get("height") or 512),
        "cfg_scale": float(params.get("cfg") or 7),
        "sampler_name": params.get("sampler") or "Euler a",
        "seed": int(seed),
        "batch_size": 1,
        "n_iter": 1,
        "save_images": False,
    }


def generate_forge(settings: dict[str, Any], params: dict[str, Any]) -> dict[str, Any]:
    url = (settings.get("forge_url") or "").rstrip("/") + "/sdapi/v1/txt2img"
    payload = _forge_payload(params)
    try:
        with httpx.Client(timeout=900) as client:
            response = client.post(url, json=payload)
            response.raise_for_status()
            data = response.json()
    except httpx.HTTPError as exc:
        raise GenerateError(
            "Forge / SD.Next / A1111 is not answering at "
            f"{settings.get('forge_url')}. Start it with DirectML, then set the URL in Settings. ({exc})"
        ) from exc
    images = data.get("images") or []
    if not images:
        raise GenerateError("backend returned no images")
    raw = base64.b64decode(images[0].split(",", 1)[-1])
    info = data.get("info")
    return _save_png(raw, {**params, "backend": "forge", "info": info if isinstance(info, str) else ""})


def generate_comfy(settings: dict[str, Any], params: dict[str, Any]) -> dict[str, Any]:
    # A minimal graph. The checkpoint name has to exist on that ComfyUI install.
    ckpt = params.get("checkpoint") or "v1-5-pruned-emaonly.safetensors"
    seed = int(params.get("seed") or 0)
    if seed < 0:
        seed = 0
    graph = {
        "3": {
            "class_type": "KSampler",
            "inputs": {
                "seed": seed,
                "steps": int(params.get("steps") or 22),
                "cfg": float(params.get("cfg") or 7),
                "sampler_name": "euler",
                "scheduler": "normal",
                "denoise": 1,
                "model": ["4", 0],
                "positive": ["6", 0],
                "negative": ["7", 0],
                "latent_image": ["5", 0],
            },
        },
        "4": {"class_type": "CheckpointLoaderSimple", "inputs": {"ckpt_name": ckpt}},
        "5": {
            "class_type": "EmptyLatentImage",
            "inputs": {
                "width": int(params.get("width") or 512),
                "height": int(params.get("height") or 512),
                "batch_size": 1,
            },
        },
        "6": {"class_type": "CLIPTextEncode", "inputs": {"text": params.get("prompt") or "", "clip": ["4", 1]}},
        "7": {"class_type": "CLIPTextEncode", "inputs": {"text": params.get("negative") or "", "clip": ["4", 1]}},
        "8": {"class_type": "VAEDecode", "inputs": {"samples": ["3", 0], "vae": ["4", 2]}},
        "9": {"class_type": "SaveImage", "inputs": {"filename_prefix": "polaris", "images": ["8", 0]}},
    }
    base = (settings.get("comfy_url") or "").rstrip("/")
    try:
        with httpx.Client(timeout=900) as client:
            queued = client.post(base + "/prompt", json={"prompt": graph})
            queued.raise_for_status()
            prompt_id = queued.json().get("prompt_id")
            if not prompt_id:
                raise GenerateError("ComfyUI did not return a prompt id")
            # Poll history. 8GB cards are slow; give it the same budget as the client timeout.
            import time

            deadline = time.time() + 880
            history = None
            while time.time() < deadline:
                response = client.get(base + f"/history/{prompt_id}")
                if response.status_code == 200 and response.json().get(prompt_id):
                    history = response.json()[prompt_id]
                    break
                time.sleep(1.0)
            if not history:
                raise GenerateError("ComfyUI did not finish in time")
            images = (((history.get("outputs") or {}).get("9") or {}).get("images") or [])
            if not images:
                raise GenerateError("ComfyUI finished without an image. Check the checkpoint name.")
            info = images[0]
            view = client.get(
                base + "/view",
                params={"filename": info["filename"], "subfolder": info.get("subfolder") or "", "type": info.get("type") or "output"},
            )
            view.raise_for_status()
            return _save_png(view.content, {**params, "backend": "comfy"})
    except GenerateError:
        raise
    except httpx.HTTPError as exc:
        raise GenerateError(f"ComfyUI request failed: {exc}") from exc


def generate(settings: dict[str, Any], params: dict[str, Any]) -> dict[str, Any]:
    backend = params.get("backend") or settings.get("image_backend") or "forge"
    width = int(params.get("width") or 512)
    height = int(params.get("height") or 512)
    if width * height > 768 * 768:
        raise GenerateError("Resolution is too large for an 8GB Polaris card. Stay at 512, or 512x768 at most.")
    if width % 64 or height % 64:
        raise GenerateError("width and height must be multiples of 64")
    if not (params.get("prompt") or "").strip():
        raise GenerateError("prompt is empty")
    if backend == "forge":
        return generate_forge(settings, params)
    if backend == "comfy":
        return generate_comfy(settings, params)
    if backend == "diffusers":
        raise GenerateError(
            "Built-in diffusers generation is intentionally not a second trainer. "
            "Point Image backend at Forge or ComfyUI, which already run DirectML well on the RX 590. "
            "The training page uses diffusers directly."
        )
    raise GenerateError(f"unknown image backend {backend}")
