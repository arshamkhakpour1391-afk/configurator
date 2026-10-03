"""Studio health scan. Every check says what is wrong and what to do, not just a red dot."""

from __future__ import annotations

import os
import platform
import shutil
from typing import Any

from . import dataset as dataset_mod
from . import generate as generate_mod
from . import llm as llm_mod
from . import training
from .hardware import detect_hardware
from .util import data_dir


def _check(name: str, level: str, message: str, fix: str = "") -> dict[str, str]:
    return {"name": name, "level": level, "message": message, "fix": fix}


def disk_free_mb(path) -> int:
    usage = shutil.disk_usage(path)
    return int(usage.free / (1024 * 1024))


def scan(settings: dict[str, Any]) -> dict[str, Any]:
    checks: list[dict[str, str]] = []
    hardware = detect_hardware(
        assume=settings.get("assume_gpu") or "rx590gme",
        vram_override_mb=int(settings.get("vram_override_mb") or 0) or None,
    )
    if hardware.get("assumed"):
        checks.append(
            _check(
                "gpu",
                "warn",
                "No GPU was visible to this process. Recommendations assume an RX 590 GME with 8GB.",
                "On your PC this should detect the card. If it does not, the driver is missing. Install the Adrenalin package AMD lists for RX 590.",
            )
        )
    elif hardware.get("polaris"):
        checks.append(
            _check(
                "gpu",
                "ok",
                f"Detected {hardware.get('gpu_name')} · {hardware.get('vram_mb')}MB.",
                "",
            )
        )
    else:
        checks.append(_check("gpu", "warn", f"Detected {hardware.get('gpu_name')}. Presets are still tuned for 8GB Polaris.", ""))

    for warning in hardware.get("warnings") or []:
        checks.append(_check("vram", "warn", warning, "Confirm the VRAM override in Settings if this is not an 8GB card."))

    ram = hardware.get("ram_mb") or 0
    if ram and ram < 12000:
        checks.append(
            _check(
                "ram",
                "warn",
                f"System RAM is {ram}MB. SD 1.5 LoRA wants 16GB so caching latents does not swap the machine to death.",
                "Close browsers and raise the pagefile. Training can still run, but expect stalls that are actually the disk.",
            )
        )
    elif ram:
        checks.append(_check("ram", "ok", f"System RAM {ram}MB.", ""))

    free = disk_free_mb(data_dir())
    if free < 2048:
        checks.append(_check("disk", "error", f"Only {free}MB free next to the studio data folder.", "Free at least 10GB. A full disk is a classic 'training randomly stopped'."))
    elif free < 10000:
        checks.append(_check("disk", "warn", f"{free}MB free. Checkpoints and a base model will want more.", "Keep 20GB free if you are about to download SD 1.5."))
    else:
        checks.append(_check("disk", "ok", f"{free}MB free.", ""))

    if platform.system().lower() == "windows":
        checks.append(
            _check(
                "tdr",
                "warn",
                "Windows GPU timeout (TDR) is the usual reason a healthy RX 590 job dies with no Python error.",
                "Run scripts/Check-Rx590.ps1. If TdrDelay is missing or under 30, apply scripts/Enable-LongGPUTimeout.reg as administrator and reboot.",
            )
        )
        checks.append(
            _check(
                "pagefile",
                "warn",
                "Pagefile size was not read from this process.",
                "Set a custom pagefile of 32768MB on the fastest disk. Commit-charge failures look like random crashes.",
            )
        )
    else:
        checks.append(
            _check(
                "tdr",
                "ok",
                "This is not Windows, so TDR does not apply. amdgpu can still reset the card if it hangs.",
                "If a run dies, check dmesg for 'amdgpu' and 'GPU reset' before changing training settings.",
            )
        )

    probes = llm_mod.probe_all(settings)
    if any(item.get("ok") for item in probes.values()):
        live = ", ".join(name for name, item in probes.items() if item.get("ok"))
        checks.append(_check("llm", "ok", f"LLM backend up: {live}.", ""))
    else:
        checks.append(
            _check(
                "llm",
                "warn",
                "No LLM is running. Chat and the agent need one. Training does not.",
                "Start Ollama (OLLAMA_VULKAN=1) or llama-server with the command the Models page copies for you.",
            )
        )

    images = generate_mod.probe(settings)
    if any(item.get("ok") for name, item in images.items() if name != "diffusers"):
        checks.append(_check("image-backend", "ok", "An image backend answered.", ""))
    else:
        checks.append(
            _check(
                "image-backend",
                "warn",
                "Forge / ComfyUI is not running. You can still train; you cannot generate yet.",
                "Install SD.Next or Forge with the DirectML flag and leave it on port 7860.",
            )
        )

    missing = []
    for module in ("torch", "diffusers", "peft", "transformers"):
        try:
            __import__(module)
        except ImportError:
            missing.append(module)
    if missing:
        checks.append(
            _check(
                "train-deps",
                "warn",
                "Training libraries missing: " + ", ".join(missing) + ".",
                "pip install -r requirements-train.txt   ·   Windows RX 590: pip install torch-directml first.",
            )
        )
    else:
        checks.append(_check("train-deps", "ok", "torch, diffusers, peft, and transformers import.", ""))
        try:
            import torch_directml  # noqa: F401

            checks.append(_check("directml", "ok", "torch-directml is installed.", ""))
        except Exception:
            if platform.system().lower() == "windows" and hardware.get("polaris"):
                checks.append(
                    _check(
                        "directml",
                        "warn",
                        "torch-directml is not installed. CUDA torch will not see an RX 590.",
                        "pip install torch-directml",
                    )
                )

    for item in dataset_mod.list_datasets():
        errors = [issue for issue in item.get("issues") or [] if issue.get("level") == "error"]
        if errors:
            checks.append(
                _check(
                    f"dataset:{item.get('name')}",
                    "error",
                    f"{item.get('name')} has {len(errors)} blocking issue(s).",
                    "Open the dataset and fix missing captions or unreadable files before you press Train.",
                )
            )

    running = [job for job in training.list_jobs() if job.get("status") in ("running", "recovering")]
    if len(running) > 1:
        checks.append(_check("jobs", "error", "More than one training job thinks it is running.", "Stop all but one. Two trainers on one RX 590 will reset the driver."))
    elif running:
        checks.append(_check("jobs", "ok", f"Training job {running[0].get('id')} is active.", ""))

    stall = int(settings.get("stall_seconds") or 180)
    if stall < 60:
        checks.append(
            _check(
                "watchdog",
                "warn",
                f"Stall timeout is {stall}s. A single SD step on an RX 590 can be slower than that.",
                "Set stall timeout to at least 180 seconds, and leave the startup grace at 900.",
            )
        )
    else:
        checks.append(_check("watchdog", "ok", f"Stall timeout {stall}s, grace {settings.get('startup_grace_seconds')}s.", ""))

    if not settings.get("terminal_enabled"):
        checks.append(_check("terminal", "ok", "Agent terminal tool is off. Turn it on in Settings only if you want it.", ""))
    else:
        checks.append(_check("terminal", "warn", "Agent terminal tool is on. It is sandboxed to the studio workspace, not a full shell.", "Turn it off if you do not need it."))

    score = 100
    for item in checks:
        if item["level"] == "error":
            score -= 20
        elif item["level"] == "warn":
            score -= 6
    score = max(0, score)
    return {"score": score, "checks": checks, "hardware": hardware, "llm": probes, "images": images}


def keep_awake(enable: bool) -> str:
    """Best-effort. Failure is reported, never fatal."""
    system = platform.system().lower()
    if not enable:
        return "disabled"
    if system == "windows":
        try:
            import ctypes

            ES_CONTINUOUS = 0x80000000
            ES_SYSTEM_REQUIRED = 0x00000001
            ctypes.windll.kernel32.SetThreadExecutionState(ES_CONTINUOUS | ES_SYSTEM_REQUIRED)
            return "windows execution state set"
        except Exception as exc:  # noqa: BLE001
            return f"could not set execution state: {exc}"
    # Linux: a systemd inhibitor is nice but not always allowed. Touching a file is useless.
    # We try systemd-inhibit only as a spawned long process if the binary exists — skip, it would leak.
    if os.environ.get("POLARIS_KEEP_AWAKE") == "1" and shutil.which("systemd-inhibit"):
        return "set POLARIS_KEEP_AWAKE and run the studio under systemd-inhibit yourself"
    return "no keep-awake hook on this OS; disable sleep in the OS settings while training"
