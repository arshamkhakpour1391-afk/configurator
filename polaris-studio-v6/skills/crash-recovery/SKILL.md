---
name: crash-recovery
description: Diagnose a training or generation run that stopped, reset, or hung on an RX 590. Use when the user says it randomly stopped, the driver timed out, or loss became NaN.
license: MIT
metadata:
  hermes:
    category: training
    tags: [tdr, oom, watchdog, crash]
version: 6.0.0
---

# Crash recovery

Do not tell the user to "just restart". Find which of these it was.

## Read first

1. `process` action `poll` and `log` on the job id.
2. Recovery list on the job. Each entry is one automatic resume and the rung that changed.
3. If there is no job, the process died outside the studio. Then it is TDR, sleep, or an out-of-memory kill of the whole Python process.

## Map the symptom

| What you see | Cause | Fix |
| --- | --- | --- |
| Exit 42, or "out of memory" | VRAM | Watchdog already lowers rank and resolution. Also close the browser. Stay at 512. |
| Exit 43, NaN | DirectML fp16 or LR | Keep fp32. Do not undo the LR cut. |
| Exit 44, no heartbeat, device removed, TDR | Windows GPU timeout or driver reset | Load `references/tdr.md`. Apply the registry file and reboot. Check thermals. |
| Same step crashes 3 times | Bad image or a model that cannot load | `dataset_scan`. Replace the base model if the log dies during "loading". |
| Studio was closed | Process died with the window | Reopen the studio. Interrupted jobs resume from the last checkpoint. |
| Loss frozen, heartbeat fresh | Not a crash | Captions or LR. Do not raise resolution. |

## After a recovery

Tell the user the exact rung that changed (rank, resolution, LR) and the step it resumed from. That is the point of the watchdog: the run did not silently vanish.
