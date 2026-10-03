"""Training child process. Exits with a contract code the supervisor understands.

Engines:
  smoke     — no GPU, used by the watchdog drill and the tests
  diffusers — real SD 1.5 LoRA when torch/diffusers/peft are installed
"""

from __future__ import annotations

import argparse
import json
import random
import sys
import time
import traceback
from pathlib import Path

from .train_contract import (
    EXIT_CRASH,
    EXIT_DATA,
    EXIT_DEPS,
    EXIT_NAN,
    EXIT_OK,
    EXIT_OOM,
    EXIT_PAUSED,
    EXIT_STOPPED,
    classify_runtime_error,
)
from .util import atomic_write_json, read_json


def write_heartbeat(folder: Path, **payload) -> None:
    payload["ts"] = time.time()
    atomic_write_json(folder / "heartbeat.json", payload)


def write_error(folder: Path, message: str) -> None:
    (folder / "error.txt").write_text(message, encoding="utf-8")


def stop_requested(folder: Path) -> str | None:
    if (folder / "stop.flag").exists():
        return "stop"
    if (folder / "pause.flag").exists():
        return "pause"
    return None


def save_resume(folder: Path, step: int, extra: dict | None = None) -> None:
    payload = {"step": step, "saved_at": time.time()}
    if extra:
        payload.update(extra)
    atomic_write_json(folder / "resume.json", payload)
    marker = folder / "checkpoints" / f"step_{step:06d}.json"
    atomic_write_json(marker, payload)


def run_smoke(folder: Path, spec: dict) -> int:
    total = int(spec.get("steps") or 6)
    start = int(spec.get("resume_step") or 0)
    attempt = int(spec.get("attempt") or 1)
    # attempt is also stored on the job, copied into spec by the supervisor via resume.
    sleep_s = float(spec.get("step_sleep") or 0.05)
    checkpoint_every = max(1, int(spec.get("checkpoint_every") or 1))
    crash_at = spec.get("crash_at")
    freeze_at = spec.get("freeze_at")
    spin_at = spec.get("spin_at")
    write_heartbeat(folder, phase="loading", step=start, loss=None, message="smoke engine loading")
    time.sleep(min(sleep_s, 0.2))
    for step in range(start + 1, total + 1):
        requested = stop_requested(folder)
        if requested:
            save_resume(folder, step - 1)
            return EXIT_STOPPED if requested == "stop" else EXIT_PAUSED
        if freeze_at and step == int(freeze_at) and attempt == 1:
            # Deadlock: stop writing heartbeats. The supervisor must notice.
            time.sleep(float(spec.get("freeze_seconds") or 5))
            return EXIT_CRASH
        if spin_at and step == int(spin_at) and attempt == 1:
            deadline = time.time() + float(spec.get("spin_seconds") or 5)
            while time.time() < deadline:
                write_heartbeat(folder, phase="training", step=step - 1, loss=1.0, message="spinning without progress")
                time.sleep(0.2)
                if stop_requested(folder):
                    return EXIT_STOPPED
            return EXIT_CRASH
        loss = round(1.0 / step, 5)
        if spec.get("nan_at") and step == int(spec["nan_at"]) and attempt == 1:
            save_resume(folder, step - 1)
            write_error(folder, "loss is nan")
            return EXIT_NAN
        write_heartbeat(folder, phase="training", step=step, loss=loss, message=f"step {step}/{total}")
        if step % checkpoint_every == 0 or step == total:
            save_resume(folder, step, {"loss": loss})
        if crash_at and step == int(crash_at) and attempt == 1:
            write_error(folder, "injected out of memory")
            return EXIT_OOM
        time.sleep(sleep_s)
    save_resume(folder, total, {"loss": round(1.0 / total, 5), "done": True})
    (folder / "output" / "smoke.txt").write_text("drill completed\n", encoding="utf-8")
    write_heartbeat(folder, phase="saving", step=total, loss=round(1.0 / total, 5), message="done")
    return EXIT_OK


def _pick_device(force_cpu: bool = False):
    import torch

    if force_cpu:
        return torch.device("cpu"), "cpu"
    try:
        import torch_directml

        return torch_directml.device(), "directml"
    except Exception:
        pass
    if torch.cuda.is_available():
        return torch.device("cuda"), "cuda"
    if getattr(torch.backends, "mps", None) and torch.backends.mps.is_available():
        return torch.device("mps"), "mps"
    return torch.device("cpu"), "cpu"


def _center_square(image, resolution: int):
    from PIL import Image

    image = image.convert("RGB")
    width, height = image.size
    side = min(width, height)
    left = (width - side) // 2
    top = (height - side) // 2
    image = image.crop((left, top, left + side, top + side))
    return image.resize((resolution, resolution), Image.Resampling.LANCZOS)


def run_diffusers(folder: Path, spec: dict) -> int:
    try:
        import torch
        import torch.nn.functional as F
        from diffusers import AutoencoderKL, DDPMScheduler, UNet2DConditionModel
        from diffusers import StableDiffusionPipeline
        from peft import LoraConfig, get_peft_model_state_dict, set_peft_model_state_dict
        from transformers import CLIPTextModel, CLIPTokenizer
    except ImportError as exc:
        write_error(
            folder,
            "Training dependencies are not installed. From the studio folder run:\n"
            "  pip install -r requirements-train.txt\n"
            "On Windows RX 590, install torch-directml first, then the requirements file.\n"
            f"Import error: {exc}",
        )
        return EXIT_DEPS

    from .dataset import iter_training_examples

    dataset_id = spec.get("dataset_id")
    if not dataset_id:
        write_error(folder, "no dataset selected")
        return EXIT_DATA
    try:
        examples = iter_training_examples(dataset_id)
    except Exception as exc:  # noqa: BLE001
        write_error(folder, str(exc))
        return EXIT_DATA
    if len(examples) < 1:
        write_error(folder, "dataset is empty")
        return EXIT_DATA

    base = spec.get("base_model") or ""
    if not base:
        write_error(folder, "base model path is empty. Point it at a local SD 1.5 diffusers folder or a .safetensors file.")
        return EXIT_DATA

    device, device_name = _pick_device(force_cpu=bool(spec.get("force_cpu")))
    force_fp32 = bool(spec.get("force_fp32")) or device_name in {"directml", "cpu"}
    dtype = torch.float32 if force_fp32 else torch.float16
    if device_name == "directml" and not force_fp32:
        dtype = torch.float32
    resolution = int(spec.get("resolution") or 512)
    rank = int(spec.get("rank") or 8)
    alpha = int(spec.get("alpha") or rank)
    lr = float(spec.get("lr") or 1e-4)
    seed = int(spec.get("seed") or 42)
    repeats = max(1, int(spec.get("repeats") or 1))
    total = int(spec.get("steps") or 800)
    start = int(spec.get("resume_step") or 0)
    accum = max(1, int(spec.get("grad_accum") or 1))
    checkpoint_every = max(1, int(spec.get("checkpoint_every") or 25))
    trigger = (spec.get("trigger") or "").strip()
    min_snr = spec.get("min_snr_gamma")

    write_heartbeat(folder, phase="loading", step=start, loss=None, message=f"loading {base} on {device_name} ({dtype})")
    try:
        path = Path(base)
        if path.is_file() and path.suffix.lower() in {".safetensors", ".ckpt"}:
            pipe = StableDiffusionPipeline.from_single_file(str(path), torch_dtype=dtype)
        else:
            pipe = StableDiffusionPipeline.from_pretrained(base, torch_dtype=dtype)
    except Exception as exc:  # noqa: BLE001
        write_error(folder, f"could not load base model: {exc}")
        return EXIT_DATA
    class_name = pipe.__class__.__name__
    if "XL" in class_name:
        write_error(
            folder,
            "This file is SDXL. An RX 590 GME does not have the VRAM to train SDXL LoRA without a random death. "
            "Use an SD 1.5 checkpoint (v1-5, realisticVision, dreamshaper 7/8) instead.",
        )
        return EXIT_DATA

    tokenizer: CLIPTokenizer = pipe.tokenizer
    text_encoder: CLIPTextModel = pipe.text_encoder
    vae: AutoencoderKL = pipe.vae
    unet: UNet2DConditionModel = pipe.unet
    scheduler: DDPMScheduler = pipe.scheduler
    text_encoder.to(device)
    vae.to(device)
    text_encoder.eval()
    vae.eval()

    # Cache on CPU so the training loop only holds the UNet. This is the difference
    # between fitting in 8GB and dying on the first step.
    cached = []
    write_heartbeat(folder, phase="caching", step=start, loss=None, message="caching latents and text embeddings")
    try:
        from PIL import Image

        for index, example in enumerate(examples):
            if stop_requested(folder):
                return EXIT_STOPPED
            caption = example["caption"]
            if trigger and trigger not in caption:
                caption = f"{trigger}, {caption}"
            image = _center_square(Image.open(example["path"]), resolution)
            tensor = torch.tensor(list(image.getdata()), dtype=torch.float32)
            tensor = tensor.view(resolution, resolution, 3).permute(2, 0, 1) / 127.5 - 1.0
            tensor = tensor.unsqueeze(0).to(device=device, dtype=dtype)
            with torch.no_grad():
                latent = vae.encode(tensor).latent_dist.sample() * vae.config.scaling_factor
                tokens = tokenizer(
                    caption,
                    padding="max_length",
                    truncation=True,
                    max_length=tokenizer.model_max_length,
                    return_tensors="pt",
                )
                hidden = text_encoder(tokens.input_ids.to(device))[0]
            cached.append((latent.detach().to("cpu"), hidden.detach().to("cpu")))
            write_heartbeat(
                folder,
                phase="caching",
                step=start,
                loss=None,
                message=f"cached {index + 1}/{len(examples)}",
            )
    except RuntimeError as exc:
        code = classify_runtime_error(str(exc))
        write_error(folder, str(exc))
        return code
    except Exception as exc:  # noqa: BLE001
        write_error(folder, f"failed while caching: {exc}")
        return EXIT_DATA

    del vae
    del text_encoder
    del pipe
    try:
        if device.type == "cuda":
            torch.cuda.empty_cache()
    except Exception:
        pass

    unet.to(device)
    if spec.get("attention_slicing", True):
        try:
            unet.set_attention_slice("max")
        except Exception:
            pass
    if spec.get("gradient_checkpointing", True):
        try:
            unet.enable_gradient_checkpointing()
        except Exception:
            pass
    unet.add_adapter(
        LoraConfig(
            r=rank,
            lora_alpha=alpha,
            init_lora_weights="gaussian",
            target_modules=["to_k", "to_q", "to_v", "to_out.0"],
        )
    )
    resume_file = folder / "checkpoints" / "lora_resume.pt"
    if start and resume_file.exists():
        state = torch.load(resume_file, map_location="cpu")
        set_peft_model_state_dict(unet, state["lora"])
    trainable = [param for param in unet.parameters() if param.requires_grad]
    if not trainable:
        write_error(folder, "LoRA attached but no trainable parameters were found")
        return EXIT_DEPS
    optimizer = torch.optim.AdamW(trainable, lr=lr, weight_decay=1e-2)
    if start and resume_file.exists():
        state = torch.load(resume_file, map_location="cpu")
        if state.get("optimizer"):
            try:
                optimizer.load_state_dict(state["optimizer"])
            except Exception:
                pass

    rng = random.Random(seed)
    expanded = cached * repeats
    write_heartbeat(folder, phase="training", step=start, loss=None, message="training")
    optimizer.zero_grad(set_to_none=True)
    running = None
    try:
        for step in range(start + 1, total + 1):
            requested = stop_requested(folder)
            if requested:
                _save_lora(folder, unet, optimizer, step - 1, get_peft_model_state_dict)
                return EXIT_STOPPED if requested == "stop" else EXIT_PAUSED
            latent_cpu, hidden_cpu = expanded[rng.randrange(len(expanded))]
            if spec.get("flip") and rng.random() < 0.5:
                latent_cpu = torch.flip(latent_cpu, dims=[-1])
            latents = latent_cpu.to(device=device, dtype=dtype)
            hidden = hidden_cpu.to(device=device, dtype=dtype)
            noise = torch.randn_like(latents)
            timesteps = torch.randint(0, int(scheduler.config.num_train_timesteps), (1,), device="cpu").to(device)
            noisy = scheduler.add_noise(latents, noise, timesteps)
            pred = unet(noisy, timesteps, hidden).sample
            target = noise
            if getattr(scheduler.config, "prediction_type", "epsilon") == "v_prediction":
                target = scheduler.get_velocity(latents, noise, timesteps)
            loss = F.mse_loss(pred.float(), target.float(), reduction="none")
            loss = loss.mean(dim=list(range(1, loss.ndim)))
            if min_snr:
                snr = _snr(timesteps, scheduler)
                gamma = float(min_snr)
                weights = torch.minimum(snr, torch.full_like(snr, gamma)) / snr.clamp(min=1e-6)
                loss = (loss * weights).mean()
            else:
                loss = loss.mean()
            if not torch.isfinite(loss):
                write_error(folder, "loss is nan")
                _save_lora(folder, unet, optimizer, step - 1, get_peft_model_state_dict)
                return EXIT_NAN
            (loss / accum).backward()
            if step % accum == 0:
                torch.nn.utils.clip_grad_norm_(trainable, 1.0)
                optimizer.step()
                optimizer.zero_grad(set_to_none=True)
            value = float(loss.detach().cpu())
            running = value if running is None else running * 0.9 + value * 0.1
            write_heartbeat(folder, phase="training", step=step, loss=round(running, 6), message=f"step {step}/{total}")
            if step % checkpoint_every == 0:
                write_heartbeat(folder, phase="saving", step=step, loss=round(running, 6), message="checkpoint")
                _save_lora(folder, unet, optimizer, step, get_peft_model_state_dict)
                write_heartbeat(folder, phase="training", step=step, loss=round(running, 6), message=f"step {step}/{total}")
    except RuntimeError as exc:
        code = classify_runtime_error(str(exc))
        write_error(folder, traceback.format_exc())
        try:
            _save_lora(folder, unet, optimizer, start, get_peft_model_state_dict)
        except Exception:
            pass
        return code
    write_heartbeat(folder, phase="saving", step=total, loss=running, message="saving final LoRA")
    _save_lora(folder, unet, optimizer, total, get_peft_model_state_dict, final=True)
    return EXIT_OK


def _snr(timesteps, scheduler):
    import torch

    alphas = scheduler.alphas_cumprod.to(timesteps.device)
    alpha = alphas[timesteps].clamp(min=1e-6, max=1 - 1e-6)
    return alpha / (1 - alpha)


def _save_lora(folder: Path, unet, optimizer, step: int, get_peft_model_state_dict, final: bool = False) -> None:
    import torch

    lora = get_peft_model_state_dict(unet)
    cpu_lora = {key: value.detach().cpu() for key, value in lora.items()}
    payload = {"step": step, "lora": cpu_lora, "optimizer": optimizer.state_dict()}
    target = folder / "checkpoints" / "lora_resume.pt"
    temporary = folder / "checkpoints" / "lora_resume.pt.tmp"
    torch.save(payload, temporary)
    temporary.replace(target)
    save_resume(folder, step)
    if final:
        out = folder / "output" / "pytorch_lora_weights.pt"
        torch.save(cpu_lora, out)
        try:
            from diffusers.utils import convert_state_dict_to_diffusers
            from diffusers import StableDiffusionPipeline

            converted = convert_state_dict_to_diffusers(cpu_lora)
            StableDiffusionPipeline.save_lora_weights(
                str(folder / "output"),
                unet_lora_layers=converted,
                safe_serialization=True,
            )
        except Exception as exc:  # noqa: BLE001
            (folder / "output" / "export_note.txt").write_text(
                "Saved pytorch_lora_weights.pt. safetensors export failed:\n" + str(exc),
                encoding="utf-8",
            )


def main(argv: list[str] | None = None) -> int:
    parser = argparse.ArgumentParser()
    parser.add_argument("--job", required=True)
    args = parser.parse_args(argv)
    folder = Path(args.job)
    job = read_json(folder / "job.json", {})
    spec = dict(job.get("spec") or {})
    spec["attempt"] = job.get("attempt") or spec.get("attempt") or 1
    spec["resume_step"] = spec.get("resume_step") or job.get("step") or 0
    engine = spec.get("engine") or "diffusers"
    try:
        if engine == "smoke":
            return run_smoke(folder, spec)
        if engine == "diffusers":
            return run_diffusers(folder, spec)
        write_error(folder, f"unknown engine {engine}")
        return EXIT_DEPS
    except Exception as exc:  # noqa: BLE001
        write_error(folder, traceback.format_exc())
        code = classify_runtime_error(str(exc))
        return code if code != EXIT_CRASH else EXIT_CRASH


if __name__ == "__main__":
    sys.exit(main())
