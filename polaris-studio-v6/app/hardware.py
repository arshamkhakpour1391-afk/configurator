"""GPU and machine detection, with an explicit RX 590 GME profile.

Windows reports many 8GB Polaris cards as 4GB because Win32_VideoController.AdapterRAM
is a 32-bit field. Treating that number as truth is how context windows and batch
sizes get set wrong. Known 8GB cards are corrected and the correction is labeled.
"""

from __future__ import annotations

import os
import platform
import re
import shutil
import subprocess
from typing import Any


PROFILES: dict[str, dict[str, Any]] = {
    "rx590gme": {
        "id": "rx590gme",
        "name": "Radeon RX 590 GME",
        "vram_mb": 8192,
        "arch": "Polaris 20 (GCN 4.0, 14nm)",
        "polaris": True,
        "rocm": False,
        "directml": True,
        "vulkan": True,
        "shaders": 2304,
        "bus": "256-bit GDDR5",
        "notes": [
            "China-market Polaris 20 card sold as RX 590 GME. 8GB GDDR5, 2304 shaders. It is not RDNA and it is not on the ROCm support list.",
            "Do not force ROCm with HSA_OVERRIDE_GFX_VERSION. On gfx803 that override is a common cause of random GPU resets.",
            "LLMs: llama.cpp built with Vulkan, or Ollama's Vulkan backend. Q4_K_M 7B/8B is the comfortable class.",
            "Image training: SD 1.5 LoRA at 512px. SDXL does not fit in 8GB without offload that randomly dies.",
            "DirectML fp16 on Polaris produces NaNs and silent stops. This studio forces fp32 on that path.",
        ],
    },
    "rx590": {
        "id": "rx590",
        "name": "Radeon RX 590",
        "vram_mb": 8192,
        "arch": "Polaris 30 (GCN 4.0, 12nm)",
        "polaris": True,
        "rocm": False,
        "directml": True,
        "vulkan": True,
        "shaders": 2304,
        "bus": "256-bit GDDR5",
        "notes": ["Same practical limits as the RX 590 GME: Vulkan for LLMs, DirectML fp32 for SD 1.5, no ROCm."],
    },
    "rx580-8": {
        "id": "rx580-8",
        "name": "Radeon RX 580 8GB",
        "vram_mb": 8192,
        "arch": "Polaris 20",
        "polaris": True,
        "rocm": False,
        "directml": True,
        "vulkan": True,
        "shaders": 2304,
        "bus": "256-bit GDDR5",
        "notes": ["Treat like the RX 590 GME for VRAM budgeting."],
    },
    "polaris-ambiguous": {
        "id": "polaris-ambiguous",
        "name": "Polaris (RX 470/570/580/590 family)",
        "vram_mb": 8192,
        "arch": "Polaris / GCN 4.0",
        "polaris": True,
        "rocm": False,
        "directml": True,
        "vulkan": True,
        "shaders": None,
        "bus": "GDDR5",
        "notes": [
            "PCI ID 67DF covers several cards. 4GB and 8GB boards share it. Defaulting to 8GB because this studio was built for an RX 590 GME — change VRAM in Settings if yours is 4GB.",
        ],
    },
    "cpu": {
        "id": "cpu",
        "name": "CPU only",
        "vram_mb": 0,
        "arch": "none",
        "polaris": False,
        "rocm": False,
        "directml": False,
        "vulkan": False,
        "shaders": None,
        "bus": "",
        "notes": ["No GPU was detected. Training and generation will be extremely slow if they run at all."],
    },
}


def _ram_mb() -> int:
    try:
        if os.path.exists("/proc/meminfo"):
            with open("/proc/meminfo", encoding="utf-8") as handle:
                for line in handle:
                    if line.startswith("MemTotal:"):
                        return int(line.split()[1]) // 1024
        # POSIX fallback
        pages = os.sysconf("SC_PHYS_PAGES")
        size = os.sysconf("SC_PAGE_SIZE")
        return int(pages * size / (1024 * 1024))
    except (OSError, ValueError, AttributeError):
        return 0


def parse_lspci(text: str) -> list[dict[str, Any]]:
    gpus = []
    for line in text.splitlines():
        if not re.search(r"VGA|3D|Display", line, re.I):
            continue
        device = line.split(": ", 1)[-1].strip()
        match = re.search(r"\[([0-9a-fA-F]{4}):([0-9a-fA-F]{4})\]", line)
        gpus.append(
            {
                "name": device,
                "vendor_id": match.group(1).lower() if match else "",
                "device_id": match.group(2).lower() if match else "",
                "source": "lspci",
                "vram_mb": None,
            }
        )
    return gpus


def _run(cmd: list[str], timeout: float = 4) -> str:
    try:
        completed = subprocess.run(cmd, capture_output=True, text=True, timeout=timeout, check=False)
    except (OSError, subprocess.TimeoutExpired):
        return ""
    return (completed.stdout or "") + "\n" + (completed.stderr or "")


def _linux_gpus() -> list[dict[str, Any]]:
    text = _run(["lspci", "-nn"])
    if text.strip():
        return parse_lspci(text)
    gpus = []
    drm = "/sys/class/drm"
    if not os.path.isdir(drm):
        return gpus
    for entry in sorted(os.listdir(drm)):
        if not entry.startswith("card") or "-" in entry:
            continue
        device = os.path.join(drm, entry, "device")
        try:
            vendor = open(os.path.join(device, "vendor"), encoding="utf-8").read().strip()
            dev = open(os.path.join(device, "device"), encoding="utf-8").read().strip()
        except OSError:
            continue
        gpus.append(
            {
                "name": f"PCI {vendor}:{dev}",
                "vendor_id": vendor.lower().replace("0x", ""),
                "device_id": dev.lower().replace("0x", ""),
                "source": "sysfs",
                "vram_mb": None,
            }
        )
    return gpus


def _windows_gpus() -> list[dict[str, Any]]:
    # AdapterRAM is uint32 and wraps past 4GB. Callers must correct known cards.
    script = (
        "Get-CimInstance Win32_VideoController | "
        "Select-Object Name,AdapterRAM,PNPDeviceID | ConvertTo-Json -Compress"
    )
    raw = _run(["powershell", "-NoProfile", "-Command", script], timeout=8)
    gpus = []
    if not raw.strip():
        return gpus
    import json

    try:
        payload = json.loads(raw.strip().splitlines()[-1])
    except json.JSONDecodeError:
        return gpus
    rows = payload if isinstance(payload, list) else [payload]
    for row in rows:
        name = str(row.get("Name") or "Unknown GPU")
        ram = row.get("AdapterRAM")
        vram = None
        try:
            if ram:
                vram = int(int(ram) / (1024 * 1024))
        except (TypeError, ValueError):
            vram = None
        pnp = str(row.get("PNPDeviceID") or "")
        match = re.search(r"VEN_([0-9A-Fa-f]{4}).*DEV_([0-9A-Fa-f]{4})", pnp)
        gpus.append(
            {
                "name": name,
                "vendor_id": match.group(1).lower() if match else "",
                "device_id": match.group(2).lower() if match else "",
                "source": "wmi",
                "vram_mb": vram,
                "vram_reported_mb": vram,
            }
        )
    return gpus


def detect_gpus() -> list[dict[str, Any]]:
    system = platform.system().lower()
    if system == "windows":
        found = _windows_gpus()
    elif system == "linux":
        found = _linux_gpus()
    else:
        found = parse_lspci(_run(["system_profiler", "SPDisplaysDataType"], timeout=8))
    if shutil.which("nvidia-smi"):
        text = _run(["nvidia-smi", "--query-gpu=name,memory.total", "--format=csv,noheader"])
        for line in text.splitlines():
            if "," not in line:
                continue
            name, mem = [part.strip() for part in line.split(",", 1)]
            digits = re.search(r"(\d+)", mem)
            found.append(
                {
                    "name": name,
                    "vendor_id": "10de",
                    "device_id": "",
                    "source": "nvidia-smi",
                    "vram_mb": int(digits.group(1)) if digits else None,
                }
            )
    return found


def match_profile(gpu: dict[str, Any] | None) -> dict[str, Any]:
    if not gpu:
        return dict(PROFILES["cpu"])
    name = (gpu.get("name") or "").lower()
    device = (gpu.get("device_id") or "").lower()
    if "590 gme" in name or "590gme" in name:
        return dict(PROFILES["rx590gme"])
    if "590" in name or device == "6fdf":
        return dict(PROFILES["rx590"])
    if "580" in name and ("8g" in name or "8 g" in name):
        return dict(PROFILES["rx580-8"])
    if device == "67df" or "polaris" in name or "ellesmere" in name or "rx 5" in name or "rx5" in name:
        return dict(PROFILES["polaris-ambiguous"])
    profile = {
        "id": "generic",
        "name": gpu.get("name") or "Unknown GPU",
        "vram_mb": gpu.get("vram_mb") or 0,
        "arch": "unknown",
        "polaris": False,
        "rocm": False,
        "directml": platform.system().lower() == "windows",
        "vulkan": True,
        "shaders": None,
        "bus": "",
        "notes": [],
    }
    return profile


def correct_vram(gpu: dict[str, Any], profile: dict[str, Any]) -> tuple[int, list[str]]:
    """Return trusted VRAM in MB and any warnings about the raw reading."""
    warnings: list[str] = []
    reported = gpu.get("vram_mb")
    profile_vram = int(profile.get("vram_mb") or 0)
    name = (gpu.get("name") or "").lower()
    if reported and reported > 0:
        # 8GB cards often show up as 4095/4096 because AdapterRAM is 32-bit.
        looks_capped = reported <= 4096 and profile_vram >= 8192
        name_is_8gb = any(token in name for token in ("590", "8gb", "8 gb", "8g"))
        if looks_capped and (name_is_8gb or profile.get("id") in {"rx590gme", "rx590", "rx580-8"}):
            warnings.append(
                f"The driver reported {reported}MB. That field wraps at 4GB on Windows, "
                f"so the {profile.get('name')} profile is using {profile_vram}MB instead."
            )
            return profile_vram, warnings
        if reported < profile_vram and profile.get("id") == "polaris-ambiguous":
            warnings.append(
                f"Reported VRAM is {reported}MB on an ambiguous Polaris ID. "
                "Leave the 8GB assumption only if this is a 590 GME / 580 8GB; set VRAM to 4096 in Settings for a 4GB card."
            )
            return profile_vram, warnings
        return int(reported), warnings
    if profile_vram:
        warnings.append(f"No trustworthy VRAM reading. Using the {profile.get('name')} profile ({profile_vram}MB).")
        return profile_vram, warnings
    return 0, warnings


def detect_hardware(assume: str = "auto", vram_override_mb: int | None = None) -> dict[str, Any]:
    gpus = detect_gpus()
    chosen = gpus[0] if gpus else None
    assumed = False
    if chosen is None and assume in ("auto", "rx590gme", "", None):
        chosen = {
            "name": "Radeon RX 590 GME",
            "vendor_id": "1002",
            "device_id": "",
            "source": "assumed",
            "vram_mb": 8192,
        }
        assumed = True
    profile = match_profile(chosen if not assumed else {"name": "Radeon RX 590 GME", "device_id": ""})
    if assumed:
        profile = dict(PROFILES["rx590gme"])
    vram, warnings = correct_vram(chosen or {}, profile)
    if vram_override_mb:
        vram = int(vram_override_mb)
        warnings.append(f"VRAM override from settings: {vram}MB.")
    system = platform.system().lower()
    advice = list(profile.get("notes") or [])
    if profile.get("polaris"):
        advice.extend(
            [
                "Close hardware-accelerated browsers while training. Chrome can hold 0.5–1GB of this card and make a healthy run look like a random crash.",
                "On Windows, set a 32GB pagefile. A too-small pagefile kills the trainer with a commit-charge error and no Python traceback.",
                "Raise the GPU timeout (TDR) before the first long run. scripts/Enable-LongGPUTimeout.reg does it; a reboot is required.",
                "Keep the fan curve aggressive. A thermal throttle on Polaris often ends as a driver reset, not a clean pause.",
            ]
        )
    return {
        "os": system,
        "os_release": platform.platform(),
        "cpu_count": os.cpu_count() or 1,
        "ram_mb": _ram_mb(),
        "gpus": gpus,
        "gpu_name": (chosen or {}).get("name") or profile.get("name"),
        "gpu_source": (chosen or {}).get("source") or "none",
        "assumed": assumed,
        "profile": profile,
        "vram_mb": vram,
        "polaris": bool(profile.get("polaris")),
        "warnings": warnings,
        "advice": advice,
        "python": platform.python_version(),
    }
