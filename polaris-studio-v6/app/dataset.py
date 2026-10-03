"""Image datasets for LoRA training.

Images are resized on the way into the trainer, never pushed to the VAE at
camera resolution. Corrupt files are rejected here so they cannot kill a run
twenty minutes in.
"""

from __future__ import annotations

import hashlib
import io
import json
import re
from pathlib import Path
from typing import Any

from .util import atomic_write_json, data_dir, read_json, safe_join

IMAGE_EXTS = {".png", ".jpg", ".jpeg", ".webp", ".bmp"}


def datasets_root() -> Path:
    path = data_dir() / "datasets"
    path.mkdir(parents=True, exist_ok=True)
    return path


def _meta_path(dataset_id: str) -> Path:
    return safe_join(datasets_root(), dataset_id, "meta.json")


def _images_dir(dataset_id: str) -> Path:
    path = safe_join(datasets_root(), dataset_id, "images")
    path.mkdir(parents=True, exist_ok=True)
    return path


def _valid_id(dataset_id: str) -> str:
    if not re.fullmatch(r"[a-f0-9]{6,32}", dataset_id or ""):
        raise ValueError("invalid dataset id")
    return dataset_id


def list_datasets() -> list[dict[str, Any]]:
    items = []
    for child in sorted(datasets_root().iterdir()):
        meta_path = child / "meta.json"
        if not meta_path.is_file():
            continue
        meta = read_json(meta_path, {})
        images = scan_dataset(child.name)
        meta["image_count"] = images["image_count"]
        meta["issues"] = images["issues"]
        items.append(meta)
    return items


def create_dataset(name: str, trigger: str = "") -> dict[str, Any]:
    from .store import new_id

    dataset_id = new_id()
    folder = datasets_root() / dataset_id
    (folder / "images").mkdir(parents=True)
    meta = {
        "id": dataset_id,
        "name": (name or "Untitled dataset").strip()[:80],
        "trigger": (trigger or "").strip()[:80],
        "created_at": __import__("datetime").datetime.now(__import__("datetime").timezone.utc).isoformat(),
        "autocaption": {"status": "idle", "done": 0, "total": 0, "error": ""},
    }
    atomic_write_json(folder / "meta.json", meta)
    return meta


def get_dataset(dataset_id: str) -> dict[str, Any]:
    dataset_id = _valid_id(dataset_id)
    meta = read_json(_meta_path(dataset_id), None)
    if not meta:
        raise FileNotFoundError(dataset_id)
    scanned = scan_dataset(dataset_id)
    meta.update(scanned)
    return meta


def delete_dataset(dataset_id: str) -> None:
    dataset_id = _valid_id(dataset_id)
    folder = safe_join(datasets_root(), dataset_id)
    if not folder.exists():
        return
    import shutil

    shutil.rmtree(folder)


def _open_image(raw: bytes):
    from PIL import Image, ImageOps, UnidentifiedImageError

    try:
        image = Image.open(io.BytesIO(raw))
        image.verify()
        image = Image.open(io.BytesIO(raw))
        image = ImageOps.exif_transpose(image)
        image.load()
    except (UnidentifiedImageError, OSError) as exc:
        raise ValueError(f"unreadable image: {exc}") from exc
    if image.width < 64 or image.height < 64:
        raise ValueError("image is smaller than 64px")
    if image.width > 12000 or image.height > 12000:
        raise ValueError("image is unreasonably large")
    return image.convert("RGB")


def add_image(dataset_id: str, raw: bytes, original_name: str, caption: str = "") -> dict[str, Any]:
    dataset_id = _valid_id(dataset_id)
    if len(raw) > 40 * 1024 * 1024:
        raise ValueError("image is over 40MB")
    image = _open_image(raw)
    folder = _images_dir(dataset_id)
    existing = [path.stem for path in folder.glob("img_*") if path.suffix.lower() in IMAGE_EXTS]
    number = 1
    for stem in existing:
        match = re.fullmatch(r"img_(\d+)", stem)
        if match:
            number = max(number, int(match.group(1)) + 1)
    stem = f"img_{number:04d}"
    dest = folder / f"{stem}.png"
    image.save(dest, format="PNG", optimize=True)
    text = caption.strip()
    if not text:
        meta = read_json(_meta_path(dataset_id), {})
        trigger = (meta.get("trigger") or "").strip()
        pretty = Path(original_name).stem.replace("_", " ").replace("-", " ")
        text = f"{trigger}, {pretty}".strip(", ")
    (folder / f"{stem}.txt").write_text(text + "\n", encoding="utf-8")
    (folder / f"{stem}.json").write_text(
        json.dumps({"original": Path(original_name).name, "sha256": hashlib.sha256(raw).hexdigest()}, indent=2),
        encoding="utf-8",
    )
    return {"name": dest.name, "caption": text, "width": image.width, "height": image.height}


def update_caption(dataset_id: str, image_name: str, caption: str) -> None:
    dataset_id = _valid_id(dataset_id)
    if not re.fullmatch(r"img_\d{4}\.(png|jpe?g|webp|bmp)", image_name, re.I) and Path(image_name).suffix.lower() not in IMAGE_EXTS:
        raise ValueError("invalid image name")
    path = safe_join(_images_dir(dataset_id), image_name)
    if not path.is_file():
        raise FileNotFoundError(image_name)
    sidecar = path.with_suffix(".txt")
    sidecar.write_text((caption or "").strip() + "\n", encoding="utf-8")


def delete_image(dataset_id: str, image_name: str) -> None:
    dataset_id = _valid_id(dataset_id)
    path = safe_join(_images_dir(dataset_id), image_name)
    if path.suffix.lower() not in IMAGE_EXTS:
        raise ValueError("not an image")
    if path.is_file():
        path.unlink()
    for suffix in (".txt", ".json"):
        sidecar = path.with_suffix(suffix)
        if sidecar.exists():
            sidecar.unlink()


def image_path(dataset_id: str, image_name: str) -> Path:
    dataset_id = _valid_id(dataset_id)
    path = safe_join(_images_dir(dataset_id), image_name)
    if path.suffix.lower() not in IMAGE_EXTS or not path.is_file():
        raise FileNotFoundError(image_name)
    return path


def scan_dataset(dataset_id: str) -> dict[str, Any]:
    dataset_id = _valid_id(dataset_id)
    folder = _images_dir(dataset_id)
    images = []
    issues = []
    hashes: dict[str, str] = {}
    for path in sorted(folder.iterdir()):
        if path.suffix.lower() not in IMAGE_EXTS:
            continue
        caption_path = path.with_suffix(".txt")
        caption = caption_path.read_text(encoding="utf-8").strip() if caption_path.exists() else ""
        item = {"name": path.name, "caption": caption, "bytes": path.stat().st_size}
        try:
            from PIL import Image

            with Image.open(path) as image:
                item["width"] = image.width
                item["height"] = image.height
                item["mode"] = image.mode
                if image.width < 256 or image.height < 256:
                    issues.append({"level": "warn", "name": path.name, "message": "smaller than 256px; it will be upscaled and look soft"})
        except Exception as exc:  # noqa: BLE001
            issues.append({"level": "error", "name": path.name, "message": f"unreadable: {exc}"})
            item["error"] = str(exc)
        if not caption:
            issues.append({"level": "error", "name": path.name, "message": "missing caption"})
        digest = hashlib.sha256(path.read_bytes()).hexdigest()
        if digest in hashes:
            issues.append({"level": "warn", "name": path.name, "message": f"duplicate of {hashes[digest]}"})
        else:
            hashes[digest] = path.name
        images.append(item)
    if not images:
        issues.append({"level": "error", "name": "", "message": "dataset has no images"})
    return {"id": dataset_id, "images": images, "image_count": len(images), "issues": issues}


def apply_template(dataset_id: str, template: str, only_empty: bool = True) -> int:
    dataset_id = _valid_id(dataset_id)
    meta = read_json(_meta_path(dataset_id), {})
    count = 0
    for item in scan_dataset(dataset_id)["images"]:
        if only_empty and item.get("caption"):
            continue
        caption = template.replace("{trigger}", meta.get("trigger") or "").replace("{name}", Path(item["name"]).stem)
        update_caption(dataset_id, item["name"], caption.strip(" ,"))
        count += 1
    return count


def create_sample(name: str = "Sample shapes") -> dict[str, Any]:
    """A tiny labeled dataset so the UI and the watchdog can be tried with no photos.

    These are flat colors, not a concept. Training on them only proves the pipeline.
    """
    from PIL import Image, ImageDraw

    dataset = create_dataset(name, trigger="polaris sample")
    colors = [("#c4553a", "a red square on a warm gray background"), ("#d6e38a", "a lime circle on a dark background"), ("#e39a56", "a copper triangle on charcoal"), ("#8fd0c4", "a teal diamond on a dark field")]
    for index, (color, caption) in enumerate(colors, start=1):
        image = Image.new("RGB", (512, 512), "#1a1e18")
        draw = ImageDraw.Draw(image)
        if index == 1:
            draw.rectangle((156, 156, 356, 356), fill=color)
        elif index == 2:
            draw.ellipse((140, 140, 372, 372), fill=color)
        elif index == 3:
            draw.polygon([(256, 120), (390, 380), (122, 380)], fill=color)
        else:
            draw.polygon([(256, 110), (390, 256), (256, 402), (122, 256)], fill=color)
        buffer = io.BytesIO()
        image.save(buffer, format="PNG")
        add_image(dataset["id"], buffer.getvalue(), f"shape{index}.png", f"polaris sample, {caption}")
    return get_dataset(dataset["id"])


def iter_training_examples(dataset_id: str) -> list[dict[str, Any]]:
    scanned = scan_dataset(dataset_id)
    blocking = [issue for issue in scanned["issues"] if issue["level"] == "error" and issue.get("name")]
    if blocking:
        raise ValueError("dataset has unreadable images or missing captions: " + ", ".join(issue["name"] for issue in blocking[:8]))
    if scanned["image_count"] < 1:
        raise ValueError("dataset is empty")
    examples = []
    folder = _images_dir(dataset_id)
    for item in scanned["images"]:
        examples.append({"path": str(folder / item["name"]), "caption": item["caption"], "name": item["name"]})
    return examples
