"""Automatic context-window decisions.

Order of trust, highest first:
  1. GGUF metadata ({arch}.context_length, rope scaling, KV shape)
  2. Hugging Face config.json / tokenizer_config.json
  3. Live server metadata (Ollama /api/show, llama.cpp /props, /v1/models)
  4. A labeled name guess — never silent, never treated as fact

The number that is actually applied is then clamped to the GPU budget.
On an RX 590 GME that clamp is the difference between a model that answers
and a model that "randomly" dies as soon as the prompt gets long.
"""

from __future__ import annotations

import json
import struct
from pathlib import Path
from typing import Any

from .util import align_down


GGUF_MAGIC = 0x46554747
GGUF_MAGIC_SWAPPED = 0x47475546
GGUF_VERSIONS = {1, 2, 3}

GGUF_UINT8, GGUF_INT8, GGUF_UINT16, GGUF_INT16 = 0, 1, 2, 3
GGUF_UINT32, GGUF_INT32, GGUF_FLOAT32, GGUF_BOOL = 4, 5, 6, 7
GGUF_STRING, GGUF_ARRAY, GGUF_UINT64, GGUF_INT64, GGUF_FLOAT64 = 8, 9, 10, 11, 12

LLAMA_FTYPE = {
    0: "F32",
    1: "F16",
    2: "Q4_0",
    3: "Q4_1",
    4: "Q4_1_SOME_F16",
    7: "Q8_0",
    8: "Q5_0",
    9: "Q5_1",
    10: "Q2_K",
    11: "Q3_K_S",
    12: "Q3_K_M",
    13: "Q3_K_L",
    14: "Q4_K_S",
    15: "Q4_K_M",
    16: "Q5_K_S",
    17: "Q5_K_M",
    18: "Q6_K",
    19: "IQ2_XXS",
    20: "IQ2_XS",
    21: "Q2_K_S",
    22: "IQ3_XS",
    23: "IQ3_XXS",
    24: "IQ1_S",
    25: "IQ4_NL",
    26: "IQ3_S",
    27: "IQ3_M",
    28: "IQ2_S",
    29: "IQ2_M",
    30: "IQ4_XS",
    31: "IQ1_M",
    32: "BF16",
}

QUANT_BITS = {
    "Q2_K": 3.1,
    "Q3_K_S": 3.5,
    "Q3_K_M": 3.9,
    "Q3_K_L": 4.3,
    "Q4_0": 4.5,
    "Q4_1": 5.0,
    "Q4_K_S": 4.6,
    "Q4_K_M": 4.85,
    "Q5_0": 5.5,
    "Q5_1": 6.0,
    "Q5_K_S": 5.5,
    "Q5_K_M": 5.7,
    "Q6_K": 6.6,
    "Q8_0": 8.5,
    "F16": 16.0,
    "BF16": 16.0,
    "F32": 32.0,
    "IQ4_NL": 4.5,
    "IQ4_XS": 4.25,
    "IQ3_M": 3.7,
    "IQ2_M": 2.7,
}

# Used only when the file itself does not say. Each row is (regex, context, note).
NAME_GUESSES: list[tuple[str, int, str]] = [
    (r"llama[-_.]?3\.2", 131072, "Llama 3.2 family is 128k unless this is a clipped fine-tune"),
    (r"llama[-_.]?3\.1", 131072, "Llama 3.1 family is 128k"),
    (r"llama[-_.]?3\b", 8192, "Llama 3 base context is 8192"),
    (r"llama[-_.]?2", 4096, "Llama 2 base context is 4096"),
    (r"qwen3", 32768, "Qwen3 default used here is 32k; some builds are longer — read the file"),
    (r"qwen2\.5", 32768, "Qwen2.5 default is 32768"),
    (r"qwen2", 32768, "Qwen2 default is 32768"),
    (r"gemma-?3", 131072, "Gemma 3 is often 128k; confirm in the file"),
    (r"gemma-?2", 8192, "Gemma 2 context is 8192"),
    (r"phi-?4", 16384, "Phi-4 default is 16k"),
    (r"phi-?3.*128k", 131072, "Phi-3 128k variant"),
    (r"phi-?3", 4096, "Phi-3 mini/small default is 4096 unless the name says 128k"),
    (r"mistral-?nemo|mistral-?small-?3", 131072, "Mistral NeMo / Small 3 are 128k"),
    (r"mistral", 32768, "Modern Mistral is usually 32k; v0.1 was 8k"),
    (r"mixtral", 32768, "Mixtral context is 32768"),
    (r"deepseek", 131072, "Many DeepSeek distillations are 128k"),
    (r"yi-.*200k", 200000, "Yi 200k variant"),
    (r"\byi\b", 4096, "Yi base is 4096 unless the name says 200k"),
    (r"command-?r", 131072, "Command-R is 128k"),
    (r"starcoder2", 16384, "StarCoder2 is 16k"),
    (r"codestral", 32768, "Codestral is 32k"),
    (r"smollm", 8192, "SmolLM is often 2k–8k"),
    (r"stablelm", 4096, "StableLM default guess is 4096"),
]


class GGUFError(ValueError):
    pass


def _read_exact(handle, size: int) -> bytes:
    data = handle.read(size)
    if len(data) != size:
        raise GGUFError("unexpected end of file while reading GGUF metadata")
    return data


def _read_string(handle, version: int) -> str:
    length = struct.unpack("<I" if version == 1 else "<Q", _read_exact(handle, 4 if version == 1 else 8))[0]
    if length > 64 * 1024 * 1024:
        raise GGUFError("GGUF string length is not plausible")
    return _read_exact(handle, length).decode("utf-8", errors="replace")


def _read_scalar(handle, value_type: int) -> Any:
    if value_type == GGUF_UINT8:
        return struct.unpack("<B", _read_exact(handle, 1))[0]
    if value_type == GGUF_INT8:
        return struct.unpack("<b", _read_exact(handle, 1))[0]
    if value_type == GGUF_UINT16:
        return struct.unpack("<H", _read_exact(handle, 2))[0]
    if value_type == GGUF_INT16:
        return struct.unpack("<h", _read_exact(handle, 2))[0]
    if value_type == GGUF_UINT32:
        return struct.unpack("<I", _read_exact(handle, 4))[0]
    if value_type == GGUF_INT32:
        return struct.unpack("<i", _read_exact(handle, 4))[0]
    if value_type == GGUF_FLOAT32:
        return struct.unpack("<f", _read_exact(handle, 4))[0]
    if value_type == GGUF_BOOL:
        return bool(struct.unpack("<B", _read_exact(handle, 1))[0])
    if value_type == GGUF_UINT64:
        return struct.unpack("<Q", _read_exact(handle, 8))[0]
    if value_type == GGUF_INT64:
        return struct.unpack("<q", _read_exact(handle, 8))[0]
    if value_type == GGUF_FLOAT64:
        return struct.unpack("<d", _read_exact(handle, 8))[0]
    raise GGUFError(f"unsupported GGUF scalar type {value_type}")


def _read_value(handle, version: int, value_type: int, store: bool) -> Any:
    if value_type == GGUF_STRING:
        return _read_string(handle, version)
    if value_type == GGUF_ARRAY:
        elem_type = struct.unpack("<I", _read_exact(handle, 4))[0]
        count = struct.unpack("<I" if version == 1 else "<Q", _read_exact(handle, 4 if version == 1 else 8))[0]
        keep = store and count <= 4096 and elem_type != GGUF_ARRAY
        items = []
        for _ in range(count):
            item = _read_value(handle, version, elem_type, store=keep)
            if keep:
                items.append(item)
        if keep:
            return items
        return {"skipped": int(count), "type": int(elem_type)}
    return _read_scalar(handle, value_type)


def read_gguf_metadata(path: str | Path, max_pairs: int = 4096) -> dict[str, Any]:
    """Read GGUF header metadata. Does not map tensor weights."""
    file_path = Path(path)
    with file_path.open("rb") as handle:
        magic = struct.unpack("<I", _read_exact(handle, 4))[0]
        if magic == GGUF_MAGIC_SWAPPED:
            raise GGUFError("big-endian GGUF is not supported")
        if magic != GGUF_MAGIC:
            raise GGUFError("not a GGUF file (bad magic)")
        version = struct.unpack("<I", _read_exact(handle, 4))[0]
        if version not in GGUF_VERSIONS:
            raise GGUFError(f"unsupported GGUF version {version}")
        width = "<I" if version == 1 else "<Q"
        width_n = 4 if version == 1 else 8
        tensor_count = struct.unpack(width, _read_exact(handle, width_n))[0]
        kv_count = struct.unpack(width, _read_exact(handle, width_n))[0]
        if kv_count > max_pairs:
            raise GGUFError(f"refusing to scan {kv_count} metadata pairs")
        metadata: dict[str, Any] = {}
        for _ in range(kv_count):
            key = _read_string(handle, version)
            value_type = struct.unpack("<I", _read_exact(handle, 4))[0]
            # Tokenizer vocab arrays are huge. Skip storing them, but still walk them
            # so the next key stays aligned.
            store = not key.endswith("tokens") and "merges" not in key
            metadata[key] = _read_value(handle, version, value_type, store=store)
        metadata["__gguf_version"] = version
        metadata["__tensor_count"] = tensor_count
        metadata["__file_size"] = file_path.stat().st_size
    return metadata


def _as_int(value: Any) -> int | None:
    """Context-window sized integers. Rejects the 1e30 tokenizer sentinel and tiny counts."""
    if isinstance(value, bool):
        return None
    if isinstance(value, int) and 128 <= value <= 2_000_000:
        return value
    if isinstance(value, float) and value.is_integer() and 128 <= value <= 2_000_000:
        return int(value)
    return None


def _small_int(value: Any) -> int | None:
    """Layer / head counts. These are small and must not go through the context filter."""
    if isinstance(value, bool):
        return None
    if isinstance(value, int) and 0 < value <= 1_000_000:
        return value
    if isinstance(value, float) and value.is_integer() and 0 < value <= 1_000_000:
        return int(value)
    return None


def _first_int(mapping: dict[str, Any], keys: list[str]) -> tuple[int | None, str | None]:
    for key in keys:
        found = _as_int(mapping.get(key))
        if found:
            return found, key
    return None, None


def guess_from_name(name: str) -> dict[str, Any]:
    import re

    lowered = (name or "").lower().replace(" ", "")
    context = None
    note = None
    for pattern, ctx, why in NAME_GUESSES:
        if re.search(pattern, lowered):
            context = ctx
            note = why
            break
    params = None
    match = re.search(r"(\d+(?:\.\d+)?)b", lowered)
    if match:
        params = float(match.group(1))
    quant = None
    for label in sorted(QUANT_BITS, key=len, reverse=True):
        if label.lower().replace("_", "") in lowered.replace("_", "") or label.lower() in lowered:
            quant = label
            break
    return {
        "native_context": context,
        "note": note,
        "parameter_billions": params,
        "quant": quant,
        "confidence": "low" if context else "none",
    }


def meta_from_gguf(metadata: dict[str, Any], file_size: int | None = None, name: str = "") -> dict[str, Any]:
    arch = str(metadata.get("general.architecture") or "").strip()
    keys = [f"{arch}.context_length"] if arch else []
    keys += [key for key in metadata if key.endswith(".context_length") and key not in keys]
    native, source_key = _first_int(metadata, keys)
    warnings: list[str] = []
    rope_factor = None
    rope_original = None
    if arch:
        rope_factor = metadata.get(f"{arch}.rope.scaling.factor") or metadata.get(f"{arch}.rope.freq_scale")
        rope_original = _as_int(metadata.get(f"{arch}.rope.scaling.original_context_length")) or _as_int(
            metadata.get(f"{arch}.rope.scaling.orig_ctx_len")
        )
    try:
        rope_factor_f = float(rope_factor) if rope_factor not in (None, 0, 1) else None
    except (TypeError, ValueError):
        rope_factor_f = None
    if rope_factor_f and rope_original and (native is None or native <= rope_original):
        extended = int(rope_original * rope_factor_f)
        if _as_int(extended):
            native = extended
            source_key = f"{source_key or arch + '.context_length'} * rope.scaling.factor"
    elif rope_factor_f and rope_original and native and native > rope_original:
        warnings.append("Rope scaling is present but context_length is already the extended value, so it was not multiplied again.")

    layers = _small_int(metadata.get(f"{arch}.block_count")) if arch else None
    if layers is None:
        for key, value in metadata.items():
            if key.endswith(".block_count"):
                layers = _small_int(value)
                if layers:
                    break
    heads = _small_int(metadata.get(f"{arch}.attention.head_count")) if arch else None
    kv_heads = _small_int(metadata.get(f"{arch}.attention.head_count_kv")) if arch else None
    embedding = _small_int(metadata.get(f"{arch}.embedding_length")) if arch else None
    head_dim = _small_int(metadata.get(f"{arch}.attention.key_length")) if arch else None
    if head_dim is None and embedding and heads:
        if embedding % heads == 0:
            head_dim = embedding // heads
    sliding = None
    if arch:
        sliding = _as_int(metadata.get(f"{arch}.attention.sliding_window")) or _as_int(
            metadata.get(f"{arch}.sliding_window")
        )
    ftype = metadata.get("general.file_type")
    quant = LLAMA_FTYPE.get(int(ftype)) if isinstance(ftype, int) else None
    display = str(metadata.get("general.name") or name or arch or "gguf")
    size_label = metadata.get("general.size_label")
    params = None
    if isinstance(size_label, str):
        guessed = guess_from_name(size_label)
        params = guessed.get("parameter_billions")
    if params is None:
        params = guess_from_name(display).get("parameter_billions") or guess_from_name(name).get("parameter_billions")
    return {
        "id": name or display,
        "display_name": display,
        "native_context": native,
        "native_source": f"gguf:{source_key}" if source_key else "",
        "confidence": "high" if native else "low",
        "architecture": arch or None,
        "layers": layers,
        "heads": heads,
        "kv_heads": kv_heads or heads,
        "head_dim": head_dim,
        "sliding_window": sliding,
        "rope_factor": rope_factor_f,
        "quant": quant or guess_from_name(name).get("quant"),
        "model_bytes": file_size if file_size is not None else metadata.get("__file_size"),
        "parameter_billions": params,
        "warnings": warnings,
        "path": name,
    }


def meta_from_hf_config(config: dict[str, Any], name: str = "", file_size: int | None = None) -> dict[str, Any]:
    src = dict(config)
    text = config.get("text_config")
    if isinstance(text, dict):
        src.update(text)
    warnings: list[str] = []
    if isinstance(text, dict):
        warnings.append("This looks like a multimodal config. Context was read from text_config.")
    native = None
    source_key = None
    for key in (
        "max_position_embeddings",
        "max_sequence_length",
        "n_positions",
        "n_ctx",
        "seq_length",
        "model_max_length",
        "sliding_window",
    ):
        found = _as_int(src.get(key))
        if found:
            native = found
            source_key = key
            break
    rope = src.get("rope_scaling") if isinstance(src.get("rope_scaling"), dict) else {}
    factor = rope.get("factor")
    original = _as_int(rope.get("original_max_position_embeddings"))
    try:
        factor_f = float(factor) if factor not in (None, 0, 1) else None
    except (TypeError, ValueError):
        factor_f = None
    if factor_f and original and (native is None or native <= original):
        native = int(original * factor_f)
        source_key = f"{source_key or 'rope'} * rope_scaling.factor"
    elif factor_f and original and native and native > original:
        warnings.append(
            "Rope scaling is present but context_length is already the extended value, so it was not multiplied again."
        )
    layers = _small_int(src.get("num_hidden_layers") or src.get("n_layer") or src.get("num_layers"))
    heads = _small_int(src.get("num_attention_heads") or src.get("n_head"))
    kv = _small_int(src.get("num_key_value_heads") or src.get("num_kv_heads")) or heads
    hidden = _small_int(src.get("hidden_size") or src.get("n_embd"))
    head_dim = _small_int(src.get("head_dim"))
    if head_dim is None and hidden and heads and hidden % heads == 0:
        head_dim = hidden // heads
    sliding = _as_int(src.get("sliding_window"))
    return {
        "id": name or src.get("_name_or_path") or "hf-config",
        "display_name": name or str(src.get("model_type") or "hf-config"),
        "native_context": native,
        "native_source": f"hf:{source_key}" if source_key else "",
        "confidence": "high" if native and source_key != "sliding_window" else ("medium" if native else "low"),
        "architecture": src.get("model_type"),
        "layers": layers,
        "heads": heads,
        "kv_heads": kv,
        "head_dim": head_dim,
        "sliding_window": sliding,
        "rope_factor": factor_f,
        "quant": None,
        "model_bytes": file_size,
        "parameter_billions": guess_from_name(name).get("parameter_billions"),
        "warnings": warnings,
        "path": name,
    }


def meta_from_ollama_show(payload: dict[str, Any], name: str) -> dict[str, Any]:
    info = payload.get("model_info") or {}
    if not isinstance(info, dict):
        info = {}
    # Ollama uses the same dotted keys as GGUF.
    meta = meta_from_gguf(info, name=name)
    details = payload.get("details") or {}
    if isinstance(details, dict):
        if details.get("quantization_level") and not meta.get("quant"):
            meta["quant"] = str(details["quantization_level"])
        family = details.get("family")
        if family and not meta.get("architecture"):
            meta["architecture"] = family
        param = details.get("parameter_size")
        if param and not meta.get("parameter_billions"):
            guessed = guess_from_name(str(param))
            meta["parameter_billions"] = guessed.get("parameter_billions")
    if meta.get("native_context"):
        meta["native_source"] = meta["native_source"].replace("gguf:", "ollama:")
        meta["confidence"] = "high"
    meta["display_name"] = name
    meta["id"] = name
    return meta


def meta_from_name_only(name: str) -> dict[str, Any]:
    guess = guess_from_name(name)
    warnings = []
    if guess.get("native_context"):
        warnings.append(f"Context was guessed from the name, not read from the file. {guess.get('note')}")
    else:
        warnings.append("No metadata and no recognizable family. Using a conservative default until the file can be read.")
    return {
        "id": name,
        "display_name": name,
        "native_context": guess.get("native_context"),
        "native_source": "name-guess" if guess.get("native_context") else "default",
        "confidence": "low",
        "architecture": None,
        "layers": None,
        "heads": None,
        "kv_heads": None,
        "head_dim": None,
        "sliding_window": None,
        "rope_factor": None,
        "quant": guess.get("quant"),
        "model_bytes": None,
        "parameter_billions": guess.get("parameter_billions"),
        "warnings": warnings,
        "path": "",
    }


def _estimate_bytes(meta: dict[str, Any]) -> int | None:
    if meta.get("model_bytes"):
        return int(meta["model_bytes"])
    params = meta.get("parameter_billions")
    quant = meta.get("quant") or "Q4_K_M"
    bits = QUANT_BITS.get(str(quant), 4.85)
    if not params:
        return None
    return int(params * 1_000_000_000 * bits / 8)


def _kv_shape(meta: dict[str, Any]) -> tuple[int, int, int, str]:
    """Return layers, kv_heads, head_dim, confidence note."""
    layers = meta.get("layers")
    kv_heads = meta.get("kv_heads")
    head_dim = meta.get("head_dim")
    if layers and kv_heads and head_dim:
        return int(layers), int(kv_heads), int(head_dim), "metadata"
    params = meta.get("parameter_billions") or 7
    # Modern 7-8B models are usually GQA. Assuming MHA here would clamp context
    # so hard the card looks broken. The assumption is labeled and replaced the
    # moment a GGUF or config is read.
    if params <= 4:
        guess = (22, 4, 128)
    elif params <= 9:
        guess = (32, 8, 128)
    elif params <= 16:
        guess = (40, 8, 128)
    else:
        guess = (48, 8, 128)
    return (
        int(layers or guess[0]),
        int(kv_heads or guess[1]),
        int(head_dim or guess[2]),
        "estimated",
    )


def kv_bytes_per_token(layers: int, kv_heads: int, head_dim: int, bytes_per_elem: int = 2) -> int:
    return 2 * layers * kv_heads * head_dim * bytes_per_elem


def decide_context(
    meta: dict[str, Any],
    hardware: dict[str, Any],
    policy: str = "safe",
    pinned_context: int | None = None,
) -> dict[str, Any]:
    warnings = list(meta.get("warnings") or [])
    notes: list[str] = []
    native = meta.get("native_context")
    if not native:
        native = 4096
        warnings.append("Native context is unknown. 4096 is a placeholder until the model file or server metadata can be read.")
    sliding = meta.get("sliding_window")
    if sliding and sliding < native:
        notes.append(
            f"Attention is windowed at {sliding} tokens. Extra context still costs KV cache on Ollama and llama.cpp, "
            "so the safe budget uses the window unless you pin a larger value."
        )
    layers, kv_heads, head_dim, shape_source = _kv_shape(meta)
    if shape_source != "metadata":
        warnings.append(
            "KV cache shape was estimated (GQA-style). Context may be a bit high or low until the GGUF metadata is read."
        )
    kv_f16 = kv_bytes_per_token(layers, kv_heads, head_dim, 2)
    kv_q8 = kv_bytes_per_token(layers, kv_heads, head_dim, 1)
    model_bytes = _estimate_bytes(meta)
    vram_mb = int(hardware.get("vram_mb") or 0)
    polaris = bool(hardware.get("polaris"))
    system = hardware.get("os") or ""
    if vram_mb <= 0:
        driver_mb = 0
        compute_mb = 0
        usable_bytes = 0
    else:
        driver_mb = 900 if polaris and system == "windows" else 700 if system == "windows" else 500 if polaris else 350
        compute_mb = 700 if (meta.get("parameter_billions") or 7) >= 6 else 400
        margin = int(vram_mb * 0.08)
        usable_bytes = max(0, (vram_mb - driver_mb - compute_mb - margin) * 1024 * 1024)
    weights = model_bytes or 0
    room_for_kv = max(0, usable_bytes - int(weights * 1.06))
    if room_for_kv < 128 * 1024 * 1024 and vram_mb:
        warnings.append("Weights plus driver reserve do not fit in VRAM. The safe plan is CPU offload or a smaller quant, not a long context.")

    def fit_tokens(kv_each: int) -> int:
        if kv_each <= 0 or room_for_kv <= 0:
            return 512 if vram_mb == 0 else 512
        return max(512, room_for_kv // kv_each)

    hard_fit = align_down(min(native, fit_tokens(kv_f16)))
    q8_fit = align_down(min(native, fit_tokens(kv_q8)))
    target = native
    if sliding and sliding < target:
        target = sliding
    if policy == "max_fit":
        applied = hard_fit
        reason = "max_fit uses the full f16 KV budget after driver, compute, and an 8% margin"
    elif policy == "native":
        applied = align_down(min(native, hard_fit))
        reason = "native policy, still clamped so f16 KV cannot exceed VRAM"
    else:
        applied = align_down(int(min(target, hard_fit) * (0.75 if polaris else 0.9)))
        reason = (
            "safe policy keeps a Polaris headroom of 25% under the computed fit so a desktop session or a long prompt does not reset the driver"
            if polaris
            else "safe policy keeps 10% under the computed fit"
        )
    kv_quant = "f16"
    if applied < min(target, 4096) and q8_fit >= min(target, applied * 2) and q8_fit > applied:
        kv_quant = "q8_0"
        if policy == "safe" and polaris:
            applied = align_down(int(min(target, q8_fit) * 0.75))
        elif policy == "max_fit":
            applied = align_down(min(target, q8_fit))
        else:
            applied = align_down(min(native, q8_fit))
        reason += "; KV cache quantized to q8_0 so more of the native window fits"
        notes.append("q8 KV is a quality trade. llama.cpp accepts --cache-type-k q8_0 --cache-type-v q8_0. Ollama uses OLLAMA_KV_CACHE_TYPE=q8_0 on builds that support it.")
    applied = min(applied, native)
    applied = align_down(applied)
    if applied > hard_fit and kv_quant == "f16":
        applied = hard_fit
    pin_warning = None
    if pinned_context:
        pinned = align_down(int(pinned_context))
        pinned = min(pinned, native)
        if kv_quant == "f16" and pinned > hard_fit:
            pin_warning = f"Pinned context {pinned} is above the f16 VRAM fit ({hard_fit}). It was kept, but the card may reset."
            warnings.append(pin_warning)
        applied = pinned
        reason = f"pinned by you to {pinned}"
    if model_bytes is None:
        warnings.append("Model size is unknown, so the VRAM clamp is only as good as the quant/parameter guess.")

    num_thread = max(1, int(hardware.get("cpu_count") or 2) - 1)
    weights_mb = int((model_bytes or 0) / (1024 * 1024))
    kv_each = kv_q8 if kv_quant == "q8_0" else kv_f16
    kv_mb = int(applied * kv_each / (1024 * 1024))
    full_fit = weights_mb + kv_mb + (900 if polaris else 400) < vram_mb * 0.92 if vram_mb else False
    if vram_mb and full_fit:
        num_gpu = 999
        gpu_note = "weights and KV fit together; all layers on GPU"
    elif vram_mb and layers and model_bytes:
        bytes_per_layer = (model_bytes * 0.92) / layers
        room = max(0, vram_mb * 1024 * 1024 - kv_mb * 1024 * 1024 - 1024 * 1024 * 1024)
        partial = int(room / bytes_per_layer) if bytes_per_layer else 0
        partial = max(0, min(layers, partial))
        if polaris:
            num_gpu = 0
            gpu_note = (
                f"partial offload would be about {partial}/{layers} layers, but partial offload on Polaris is unstable. "
                "Safe choice is a smaller quant that fits entirely, otherwise CPU."
            )
            warnings.append(gpu_note)
        else:
            num_gpu = partial
            gpu_note = f"partial offload {partial}/{layers} layers"
    else:
        num_gpu = 999 if vram_mb >= 8192 and (meta.get("parameter_billions") or 7) <= 8 else 0
        gpu_note = "GPU layer count is a fallback because size or layer count is missing"
    num_batch = 256 if polaris or (vram_mb and vram_mb <= 8192) else 512
    max_tokens = min(2048, max(256, applied // 8))
    display = meta.get("display_name") or meta.get("id")
    path = meta.get("path") or ""
    quoted = f'"{path}"' if path else "<model.gguf>"
    cache_flags = ""
    if kv_quant == "q8_0":
        cache_flags = " --cache-type-k q8_0 --cache-type-v q8_0"
    ngl = 99 if num_gpu >= 999 else num_gpu
    llama_command = (
        f"llama-server -m {quoted} -c {applied} -ngl {ngl} -b {num_batch} -t {num_thread}{cache_flags}"
    )
    ollama_options = {
        "num_ctx": applied,
        "num_gpu": 999 if num_gpu >= 999 else num_gpu,
        "num_batch": num_batch,
        "num_thread": num_thread,
        "num_predict": max_tokens,
        "use_mmap": True,
    }
    ollama_env = {}
    if kv_quant == "q8_0":
        ollama_env["OLLAMA_KV_CACHE_TYPE"] = "q8_0"
    if polaris:
        ollama_env["OLLAMA_VULKAN"] = "1"
        notes.append("RX 590 / GME should use the Vulkan backend. ROCm is not a supported path for this chip.")
    budget = {
        "vram_mb": vram_mb,
        "driver_mb": driver_mb if vram_mb else 0,
        "compute_mb": compute_mb if vram_mb else 0,
        "weights_mb": weights_mb,
        "kv_mb": kv_mb,
        "free_mb": max(0, vram_mb - (driver_mb if vram_mb else 0) - (compute_mb if vram_mb else 0) - weights_mb - kv_mb),
    }
    return {
        "model_key": meta.get("id") or display,
        "display_name": display,
        "native_context": native,
        "native_source": meta.get("native_source") or "",
        "confidence": meta.get("confidence") or "low",
        "architecture": meta.get("architecture"),
        "layers": layers,
        "heads": meta.get("heads"),
        "kv_heads": kv_heads,
        "head_dim": head_dim,
        "kv_shape_source": shape_source,
        "sliding_window": sliding,
        "quant": meta.get("quant"),
        "model_bytes": model_bytes,
        "parameter_billions": meta.get("parameter_billions"),
        "vram_mb": vram_mb,
        "gpu_name": hardware.get("gpu_name"),
        "assumed_gpu": bool(hardware.get("assumed")),
        "polaris": polaris,
        "kv_bytes_per_token": kv_each,
        "kv_quant": kv_quant,
        "hard_fit_context": hard_fit,
        "q8_fit_context": q8_fit,
        "recommended_context": applied,
        "applied_context": applied,
        "policy": policy if not pinned_context else "pinned",
        "clamp_reason": reason,
        "num_gpu": 999 if num_gpu >= 999 else num_gpu,
        "num_batch": num_batch,
        "num_thread": num_thread,
        "max_tokens": max_tokens,
        "gpu_note": gpu_note,
        "llama_command": llama_command,
        "ollama_options": ollama_options,
        "ollama_env": ollama_env,
        "budget": budget,
        "warnings": warnings,
        "notes": notes,
        "path": path,
    }


def load_hf_config(path: str | Path) -> dict[str, Any]:
    file_path = Path(path)
    if file_path.is_dir():
        file_path = file_path / "config.json"
    return json.loads(file_path.read_text(encoding="utf-8"))


def inspect_path(path: str | Path) -> dict[str, Any]:
    file_path = Path(path).expanduser()
    if not file_path.exists():
        raise FileNotFoundError(str(file_path))
    if file_path.is_dir() or file_path.name == "config.json":
        return meta_from_hf_config(load_hf_config(file_path), name=str(file_path), file_size=None)
    size = file_path.stat().st_size
    if file_path.suffix.lower() == ".gguf" or file_path.name.lower().endswith(".gguf"):
        metadata = read_gguf_metadata(file_path)
        meta = meta_from_gguf(metadata, file_size=size, name=file_path.name)
        meta["path"] = str(file_path)
        meta["mtime"] = file_path.stat().st_mtime
        return meta
    if file_path.name == "config.json":
        return meta_from_hf_config(load_hf_config(file_path), name=str(file_path))
    # Last resort: maybe a JSON config with a different name.
    try:
        payload = json.loads(file_path.read_text(encoding="utf-8"))
    except (OSError, json.JSONDecodeError):
        payload = None
    if isinstance(payload, dict) and any(key in payload for key in ("max_position_embeddings", "model_type", "n_positions")):
        return meta_from_hf_config(payload, name=file_path.name, file_size=size)
    guessed = meta_from_name_only(file_path.name)
    guessed["path"] = str(file_path)
    guessed["model_bytes"] = size
    guessed["warnings"].append("File is not GGUF or config.json. Only the name and file size were used.")
    return guessed
