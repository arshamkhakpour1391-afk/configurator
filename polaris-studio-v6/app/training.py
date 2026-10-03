"""Training supervisor.

The child process does the math. This process only watches. A dead heartbeat,
a stuck step, an OOM exit, or a driver reset resumes from the last atomic
checkpoint instead of leaving a half-finished run that looks like it 'just stopped'.
"""

from __future__ import annotations

import json
import os
import signal
import subprocess
import sys
import threading
import time
from pathlib import Path
from typing import Any

from .train_contract import (
    EXIT_CRASH,
    EXIT_DEPS,
    EXIT_DATA,
    EXIT_HUNG,
    EXIT_NAN,
    EXIT_OK,
    EXIT_OOM,
    EXIT_PAUSED,
    EXIT_STOPPED,
    FATAL_EXITS,
    RECOVERABLE_EXITS,
    SLOW_PHASES,
    apply_recovery,
)
from .util import atomic_write_json, data_dir, read_json


PHASE_TIMEOUTS = {
    "loading": 1200,
    "caching": 2400,
    "saving": 600,
    "starting": 180,
    "queued": 120,
}


def jobs_root() -> Path:
    path = data_dir() / "jobs"
    path.mkdir(parents=True, exist_ok=True)
    return path


def job_dir(job_id: str) -> Path:
    if not job_id or "/" in job_id or "\\" in job_id or job_id.startswith("."):
        raise ValueError("invalid job id")
    return jobs_root() / job_id


def load_job(job_id: str) -> dict[str, Any]:
    payload = read_json(job_dir(job_id) / "job.json", None)
    if not payload:
        raise FileNotFoundError(job_id)
    return payload


def save_job(job: dict[str, Any]) -> None:
    job["updated_at"] = time.strftime("%Y-%m-%dT%H:%M:%SZ", time.gmtime())
    atomic_write_json(job_dir(job["id"]) / "job.json", job)


def list_jobs() -> list[dict[str, Any]]:
    items = []
    for child in sorted(jobs_root().iterdir(), reverse=True):
        if (child / "job.json").is_file():
            items.append(read_json(child / "job.json", {}))
    return items


PRESETS: dict[str, dict[str, Any]] = {
    "rx590-sd15-safe": {
        "label": "RX 590 safe",
        "blurb": "SD 1.5, 512px, rank 8, fp32, batch 1. The preset that should finish.",
        "base_family": "sd15",
        "steps": 800,
        "rank": 8,
        "alpha": 8,
        "lr": 1e-4,
        "resolution": 512,
        "batch_size": 1,
        "grad_accum": 4,
        "checkpoint_every": 25,
        "seed": 42,
        "flip": True,
        "attention_slicing": True,
        "gradient_checkpointing": True,
        "force_fp32": True,
        "cache_latents": True,
        "min_snr_gamma": 5.0,
        "repeats": 8,
    },
    "rx590-sd15-quality": {
        "label": "RX 590 quality",
        "blurb": "Same safe shell, rank 16, more steps. Still fp32 and batch 1.",
        "base_family": "sd15",
        "steps": 1500,
        "rank": 16,
        "alpha": 16,
        "lr": 8e-5,
        "resolution": 512,
        "batch_size": 1,
        "grad_accum": 4,
        "checkpoint_every": 25,
        "seed": 42,
        "flip": True,
        "attention_slicing": True,
        "gradient_checkpointing": True,
        "force_fp32": True,
        "cache_latents": True,
        "min_snr_gamma": 5.0,
        "repeats": 12,
    },
    "rx590-sd15-fast": {
        "label": "RX 590 fast draft",
        "blurb": "Rank 4, 400 steps, 448px. Use it to see if the concept is even there.",
        "base_family": "sd15",
        "steps": 400,
        "rank": 4,
        "alpha": 4,
        "lr": 1e-4,
        "resolution": 448,
        "batch_size": 1,
        "grad_accum": 2,
        "checkpoint_every": 20,
        "seed": 42,
        "flip": True,
        "attention_slicing": True,
        "gradient_checkpointing": True,
        "force_fp32": True,
        "cache_latents": True,
        "min_snr_gamma": 5.0,
        "repeats": 6,
    },
}


def preset_spec(name: str) -> dict[str, Any]:
    if name not in PRESETS:
        raise KeyError(name)
    spec = dict(PRESETS[name])
    spec["preset"] = name
    spec["engine"] = "diffusers"
    return spec


class GPUGate:
    def __init__(self) -> None:
        self._lock = threading.Lock()
        self.holder: str | None = None
        self.job_id: str | None = None

    def acquire(self, holder: str, job_id: str) -> bool:
        if not self._lock.acquire(blocking=False):
            return False
        self.holder = holder
        self.job_id = job_id
        return True

    def release(self, job_id: str) -> None:
        if self.job_id == job_id:
            self.holder = None
            self.job_id = None
            try:
                self._lock.release()
            except RuntimeError:
                pass

    def status(self) -> dict[str, Any]:
        return {"holder": self.holder, "job_id": self.job_id}


GPU = GPUGate()
_THREADS: dict[str, threading.Thread] = {}
_THREAD_LOCK = threading.Lock()


def _pid_alive(pid: int | None) -> bool:
    if not pid:
        return False
    try:
        os.kill(pid, 0)
    except OSError:
        return False
    else:
        return True


def create_job(spec: dict[str, Any], kind: str = "lora") -> dict[str, Any]:
    from .store import new_id, utcnow

    job_id = new_id()
    folder = jobs_root() / job_id
    folder.mkdir(parents=True)
    (folder / "checkpoints").mkdir()
    (folder / "output").mkdir()
    job = {
        "id": job_id,
        "kind": kind,
        "status": "queued",
        "created_at": utcnow(),
        "updated_at": utcnow(),
        "spec": spec,
        "attempt": 1,
        "recoveries": [],
        "step": int(spec.get("resume_step") or 0),
        "total_steps": int(spec.get("steps") or 1),
        "loss": None,
        "phase": "queued",
        "history": [],
        "error": None,
        "output_dir": str(folder / "output"),
        "pid": None,
        "same_step_crashes": 0,
        "last_crash_step": None,
        "message": "queued",
    }
    atomic_write_json(folder / "job.json", job)
    return job


def _append_history(job: dict[str, Any], step: int, loss: float | None) -> None:
    if loss is None:
        return
    history = job.setdefault("history", [])
    if history and history[-1].get("step") == step:
        history[-1]["loss"] = loss
        return
    history.append({"step": step, "loss": loss})
    if len(history) > 400:
        del history[:-400]
    with (job_dir(job["id"]) / "history.jsonl").open("a", encoding="utf-8") as handle:
        handle.write(json.dumps({"step": step, "loss": loss}) + "\n")


def _read_heartbeat(folder: Path) -> dict[str, Any] | None:
    return read_json(folder / "heartbeat.json", None)


def _kill_process(proc: subprocess.Popen) -> None:
    if proc.poll() is not None:
        return
    try:
        if os.name == "nt":
            proc.terminate()
        else:
            os.killpg(proc.pid, signal.SIGTERM)
    except (OSError, ProcessLookupError):
        proc.terminate()
    try:
        proc.wait(timeout=8)
    except subprocess.TimeoutExpired:
        try:
            if os.name == "nt":
                proc.kill()
            else:
                os.killpg(proc.pid, signal.SIGKILL)
        except (OSError, ProcessLookupError):
            proc.kill()
        proc.wait(timeout=5)


def _run_attempt(job: dict[str, Any], python: str) -> int:
    folder = job_dir(job["id"])
    for flag in ("stop.flag", "pause.flag"):
        # Flags are requests for the current attempt. A resume clears them first.
        pass
    env = os.environ.copy()
    env["PYTHONPATH"] = str(Path(__file__).resolve().parents[1]) + os.pathsep + env.get("PYTHONPATH", "")
    env["PYTHONUNBUFFERED"] = "1"
    log_path = folder / "worker.log"
    # Rotate so a long recovery loop cannot fill the disk and kill the next attempt.
    if log_path.exists() and log_path.stat().st_size > 2_000_000:
        previous = folder / "worker.previous.log"
        try:
            os.replace(log_path, previous)
        except OSError:
            pass
    creationflags = 0
    popen_kwargs: dict[str, Any] = {}
    if os.name == "nt":
        creationflags = getattr(subprocess, "CREATE_NEW_PROCESS_GROUP", 0)
        popen_kwargs["creationflags"] = creationflags
    else:
        popen_kwargs["start_new_session"] = True
    with log_path.open("a", encoding="utf-8") as log:
        log.write(f"\n--- attempt {job['attempt']} ---\n")
        proc = subprocess.Popen(
            [python, "-m", "app.worker", "--job", str(folder)],
            cwd=str(Path(__file__).resolve().parents[1]),
            env=env,
            stdout=log,
            stderr=subprocess.STDOUT,
            **popen_kwargs,
        )
    job["pid"] = proc.pid
    job["status"] = "running"
    job["phase"] = "starting"
    job["message"] = f"attempt {job['attempt']} started"
    save_job(job)
    (folder / "supervisor.pid").write_text(str(os.getpid()), encoding="utf-8")
    spec = job["spec"]
    dead_timeout = float(spec.get("dead_timeout") or 90)
    stall = float(spec.get("stall_seconds") or 180)
    grace = float(spec.get("startup_grace_seconds") or 900)
    last_progress = time.time()
    last_step = job.get("step") or 0
    last_phase = "starting"
    while proc.poll() is None:
        time.sleep(0.25)
        if (folder / "stop.flag").exists() or (folder / "pause.flag").exists():
            _kill_process(proc)
            break
        heartbeat = _read_heartbeat(folder)
        now = time.time()
        if not heartbeat:
            if now - last_progress > grace:
                _kill_process(proc)
                job["message"] = "no heartbeat during startup"
                save_job(job)
                return EXIT_HUNG
            continue
        phase = heartbeat.get("phase") or "training"
        step = int(heartbeat.get("step") or 0)
        if step != last_step or phase != last_phase:
            last_step = step
            last_phase = phase
            last_progress = now
        job["phase"] = phase
        job["step"] = step
        job["loss"] = heartbeat.get("loss")
        job["message"] = heartbeat.get("message") or phase
        if heartbeat.get("loss") is not None and phase == "training":
            _append_history(job, step, heartbeat.get("loss"))
        save_job(job)
        if now - float(heartbeat.get("ts") or now) > dead_timeout:
            _kill_process(proc)
            job["message"] = "worker stopped writing heartbeats"
            save_job(job)
            return EXIT_HUNG
        if phase in SLOW_PHASES or phase in PHASE_TIMEOUTS:
            limit = float(spec.get(f"{phase}_timeout") or PHASE_TIMEOUTS.get(phase, grace))
        else:
            limit = stall if step else grace
        if now - last_progress > limit:
            _kill_process(proc)
            job["message"] = f"no progress in {phase} for {int(limit)}s"
            save_job(job)
            return EXIT_HUNG
    code = proc.returncode if proc.returncode is not None else EXIT_CRASH
    if (folder / "pause.flag").exists():
        return EXIT_PAUSED
    if (folder / "stop.flag").exists():
        return EXIT_STOPPED
    error_path = folder / "error.txt"
    if error_path.exists() and code not in (EXIT_OK, EXIT_PAUSED, EXIT_STOPPED):
        job["error"] = error_path.read_text(encoding="utf-8")[-2000:]
        save_job(job)
    return code


def _checkpoint_step(folder: Path) -> int:
    best = 0
    ckpt = folder / "checkpoints"
    if not ckpt.exists():
        return 0
    for path in ckpt.glob("step_*"):
        try:
            best = max(best, int(path.name.split("_")[1]))
        except (IndexError, ValueError):
            continue
    state = read_json(folder / "resume.json", None)
    if state and state.get("step"):
        best = max(best, int(state["step"]))
    return best


def run_job(job_id: str, python: str | None = None) -> dict[str, Any]:
    python = python or sys.executable
    folder = job_dir(job_id)
    if not GPU.acquire("train", job_id):
        job = load_job(job_id)
        job["status"] = "failed"
        job["error"] = f"GPU is busy ({GPU.holder} {GPU.job_id}). Pause that job first."
        save_job(job)
        return job
    try:
        while True:
            job = load_job(job_id)
            if job["status"] in ("stopped", "failed", "completed", "paused"):
                return job
            code = _run_attempt(job, python)
            job = load_job(job_id)
            step = _checkpoint_step(folder)
            job["step"] = max(int(job.get("step") or 0), step)
            if code == EXIT_OK:
                job["status"] = "completed"
                job["phase"] = "completed"
                job["message"] = "finished"
                job["error"] = None
                save_job(job)
                return job
            if code == EXIT_PAUSED:
                job["status"] = "paused"
                job["phase"] = "paused"
                job["message"] = "paused, checkpoint kept"
                save_job(job)
                return job
            if code == EXIT_STOPPED:
                job["status"] = "stopped"
                job["phase"] = "stopped"
                job["message"] = "stopped by you"
                save_job(job)
                return job
            if code in FATAL_EXITS or code == EXIT_DEPS or code == EXIT_DATA:
                job["status"] = "failed"
                job["phase"] = "failed"
                job["message"] = job.get("error") or f"fatal exit {code}"
                save_job(job)
                return job
            if code not in RECOVERABLE_EXITS and code not in (EXIT_HUNG, EXIT_OOM, EXIT_NAN, EXIT_CRASH):
                code = EXIT_CRASH
            crash_step = job.get("step") or 0
            if job.get("last_crash_step") == crash_step:
                job["same_step_crashes"] = int(job.get("same_step_crashes") or 0) + 1
            else:
                job["last_crash_step"] = crash_step
                job["same_step_crashes"] = 1
            if job["same_step_crashes"] >= 3:
                job["status"] = "failed"
                job["phase"] = "failed"
                job["error"] = (
                    f"Step {crash_step} crashed {job['same_step_crashes']} times. "
                    "Not looping forever. Read worker.log — this is usually a bad image, a driver reset, or a model that does not fit."
                )
                job["message"] = job["error"]
                save_job(job)
                return job
            max_recoveries = int(job["spec"].get("max_recoveries") or 8)
            if len(job.get("recoveries") or []) >= max_recoveries:
                job["status"] = "failed"
                job["phase"] = "failed"
                job["error"] = f"Stopped after {max_recoveries} automatic recoveries. Last error: {job.get('error') or code}"
                job["message"] = job["error"]
                save_job(job)
                return job
            if code == EXIT_CRASH and int(job["spec"].get("_clean_retries") or 0) < 2:
                job["spec"]["_clean_retries"] = int(job["spec"].get("_clean_retries") or 0) + 1
                reason = "transient crash, retrying the same settings from the last checkpoint"
                new_spec = job["spec"]
            else:
                new_spec, reason = apply_recovery(job["spec"], code if code in (EXIT_OOM, EXIT_NAN, EXIT_HUNG, EXIT_CRASH) else EXIT_CRASH)
                job["spec"] = new_spec
            job["spec"]["resume_step"] = step
            job["attempt"] = int(job.get("attempt") or 1) + 1
            job["status"] = "recovering"
            job["phase"] = "recovering"
            job["message"] = reason
            job["recoveries"].append(
                {
                    "at": time.strftime("%Y-%m-%dT%H:%M:%SZ", time.gmtime()),
                    "code": code,
                    "reason": reason,
                    "step": step,
                }
            )
            save_job(job)
            # Clear a stale error so the next attempt can write a fresh one.
            error_path = folder / "error.txt"
            if error_path.exists():
                error_path.unlink()
    finally:
        GPU.release(job_id)


def start_job_thread(job_id: str) -> None:
    with _THREAD_LOCK:
        existing = _THREADS.get(job_id)
        if existing and existing.is_alive():
            return

        def _target() -> None:
            try:
                run_job(job_id)
            except Exception as exc:  # noqa: BLE001 — last-resort so a supervisor bug is visible
                try:
                    job = load_job(job_id)
                    job["status"] = "failed"
                    job["error"] = f"supervisor error: {exc}"
                    save_job(job)
                except Exception:
                    pass
            finally:
                GPU.release(job_id)

        thread = threading.Thread(target=_target, name=f"train-{job_id}", daemon=True)
        _THREADS[job_id] = thread
        thread.start()


def request_stop(job_id: str, pause: bool = False) -> dict[str, Any]:
    folder = job_dir(job_id)
    flag = folder / ("pause.flag" if pause else "stop.flag")
    flag.write_text("1", encoding="utf-8")
    job = load_job(job_id)
    job["message"] = "pause requested" if pause else "stop requested"
    save_job(job)
    return job


def resume_job(job_id: str) -> dict[str, Any]:
    folder = job_dir(job_id)
    for name in ("stop.flag", "pause.flag"):
        path = folder / name
        if path.exists():
            path.unlink()
    job = load_job(job_id)
    if job["status"] not in ("paused", "failed", "stopped"):
        if job["status"] in ("running", "recovering", "queued"):
            return job
    job["status"] = "queued"
    job["phase"] = "queued"
    job["message"] = "resume requested"
    job["error"] = None
    save_job(job)
    start_job_thread(job_id)
    return job


def resume_interrupted() -> list[str]:
    started = []
    for job in list_jobs():
        if job.get("status") in ("running", "recovering"):
            pid = job.get("pid")
            if _pid_alive(pid):
                continue
            job["status"] = "queued"
            job["message"] = "studio restarted during training; resuming from the last checkpoint"
            job["recoveries"] = list(job.get("recoveries") or []) + [
                {
                    "at": time.strftime("%Y-%m-%dT%H:%M:%SZ", time.gmtime()),
                    "code": 0,
                    "reason": job["message"],
                    "step": job.get("step") or 0,
                }
            ]
            save_job(job)
            start_job_thread(job["id"])
            started.append(job["id"])
    return started


def read_log(job_id: str, tail: int = 200) -> str:
    path = job_dir(job_id) / "worker.log"
    if not path.exists():
        return ""
    lines = path.read_text(encoding="utf-8", errors="replace").splitlines()
    return "\n".join(lines[-tail:])
