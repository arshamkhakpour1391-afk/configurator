#!/usr/bin/env python3
"""Launch Polaris Studio. Creates a local venv on first run if needed."""

from __future__ import annotations

import os
import subprocess
import sys
import threading
import webbrowser
from pathlib import Path


ROOT = Path(__file__).resolve().parent


def venv_python() -> Path | None:
    if os.name == "nt":
        candidate = ROOT / ".venv" / "Scripts" / "python.exe"
    else:
        candidate = ROOT / ".venv" / "bin" / "python"
    return candidate if candidate.exists() else None


def ensure_venv() -> None:
    if "--in-venv" in sys.argv or os.environ.get("POLARIS_NO_VENV") == "1":
        return
    if sys.prefix != sys.base_prefix:
        return
    py = venv_python()
    if py is None:
        print("Creating .venv …")
        subprocess.check_call([sys.executable, "-m", "venv", str(ROOT / ".venv")])
        py = venv_python()
    assert py is not None
    subprocess.check_call([str(py), "-m", "pip", "install", "-q", "-r", str(ROOT / "requirements.txt")])
    os.execv(str(py), [str(py), str(ROOT / "start.py"), "--in-venv", *sys.argv[1:]])


def main() -> None:
    ensure_venv()
    os.chdir(ROOT)
    host = os.environ.get("POLARIS_HOST", "127.0.0.1")
    port = int(os.environ.get("POLARIS_PORT", "8787"))
    url = f"http://{host if host != '0.0.0.0' else '127.0.0.1'}:{port}"
    print(f"Polaris Studio v6  →  {url}")
    if os.environ.get("POLARIS_NO_BROWSER") != "1":
        threading.Timer(1.2, lambda: webbrowser.open(url)).start()
    import uvicorn

    uvicorn.run("app.server:app", host=host, port=port, reload=False)


if __name__ == "__main__":
    main()
