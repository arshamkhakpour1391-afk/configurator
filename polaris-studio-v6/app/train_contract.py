"""Shared training exit codes and the recovery ladder.

The parent supervisor and the child worker import this module only, so a crash
in the child cannot take the watchdog down with it.
"""

from __future__ import annotations

import copy
from typing import Any


EXIT_OK = 0
EXIT_CRASH = 1
EXIT_OOM = 42
EXIT_NAN = 43
EXIT_HUNG = 44
EXIT_DEPS = 45
EXIT_DATA = 46
EXIT_STOPPED = 47
EXIT_PAUSED = 48

FATAL_EXITS = {EXIT_DEPS, EXIT_DATA}
RECOVERABLE_EXITS = {EXIT_CRASH, EXIT_OOM, EXIT_NAN, EXIT_HUNG}

SLOW_PHASES = {"queued", "loading", "caching", "saving", "starting"}


def classify_runtime_error(message: str) -> int:
    text = (message or "").lower()
    oom_markers = (
        "out of memory",
        "outofmemory",
        "cuda out of memory",
        "cudnn_status_alloc_failed",
        "failed to allocate",
        "not enough memory",
        "memory allocation failure",
    )
    hung_markers = (
        "device removed",
        "device hung",
        "dxgi_error_device",
        "dxgi_error_device_removed",
        "dxgi_error_device_hung",
        "tdr",
        "gpu reset",
        "amdgpu: gpu reset",
        "video scheduler internal error",
    )
    nan_markers = ("nan", "inf", "loss is nan", "non-finite")
    if any(marker in text for marker in oom_markers):
        return EXIT_OOM
    if any(marker in text for marker in hung_markers):
        return EXIT_HUNG
    if any(marker in text for marker in nan_markers):
        return EXIT_NAN
    return EXIT_CRASH


def apply_recovery(spec: dict[str, Any], code: int) -> tuple[dict[str, Any], str]:
    """Return a safer spec and a human reason. One rung per call, never a jump to the floor."""
    updated = copy.deepcopy(spec)
    actions: list[str] = []

    if code == EXIT_NAN:
        lr = float(updated.get("lr") or 1e-4)
        updated["lr"] = max(1e-6, lr * 0.5)
        updated["force_fp32"] = True
        updated["min_snr_gamma"] = updated.get("min_snr_gamma") or 5.0
        actions.append(
            f"NaN loss: learning rate halved to {updated['lr']:.2e} and precision forced to fp32"
        )
        return updated, "; ".join(actions)

    if code not in (EXIT_OOM, EXIT_HUNG, EXIT_CRASH):
        return updated, f"no automatic recovery for exit {code}"

    if not updated.get("attention_slicing", True):
        updated["attention_slicing"] = True
        actions.append("enabled attention slicing to cut the activation peak")
    elif not updated.get("gradient_checkpointing", True):
        updated["gradient_checkpointing"] = True
        actions.append("enabled gradient checkpointing")
    elif int(updated.get("rank") or 8) > 4:
        rank = int(updated.get("rank") or 8)
        updated["rank"] = max(4, rank // 2)
        updated["alpha"] = min(int(updated.get("alpha") or updated["rank"]), updated["rank"])
        actions.append(f"reduced LoRA rank to {updated['rank']} so the adapter fits in VRAM")
    elif int(updated.get("resolution") or 512) > 384:
        current = int(updated["resolution"])
        updated["resolution"] = 384 if current <= 448 else current - 64
        actions.append(f"reduced training resolution to {updated['resolution']}")
    elif int(updated.get("grad_accum") or 1) > 1 and int(updated.get("batch_size") or 1) > 1:
        updated["batch_size"] = 1
        actions.append("forced batch size 1")
    elif float(updated.get("lr") or 1e-4) > 2e-5:
        updated["lr"] = float(updated["lr"]) * 0.5
        actions.append(f"halved learning rate to {updated['lr']:.2e}")
    else:
        actions.append("settings are already at the safe floor; retrying once from the last good checkpoint")

    updated["batch_size"] = 1
    updated["force_fp32"] = True
    if code == EXIT_HUNG:
        actions.append(
            "GPU timeout or driver reset (Windows TDR is the usual cause on RX 590). "
            "Apply scripts/Enable-LongGPUTimeout.reg and reboot if this keeps happening"
        )
    if code == EXIT_OOM:
        actions.append("out of memory — VRAM was released and the run will resume from the last checkpoint")
    return updated, "; ".join(actions)
