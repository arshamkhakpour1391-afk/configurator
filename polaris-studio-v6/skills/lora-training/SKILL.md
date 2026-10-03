---
name: lora-training
description: Train an SD 1.5 LoRA on an RX 590 GME without the run dying. Use for image training, fine-tunes, trigger words, and preset choice.
license: MIT
metadata:
  hermes:
    category: training
    tags: [lora, sd15, dataset, rx590]
version: 6.0.0
---

# LoRA training

## Before start

1. `dataset_scan` the dataset. Missing captions and unreadable files are refused on purpose.
2. 15–40 images is enough. More repeats, not more resolution.
3. Base model must be SD 1.5 (a diffusers folder or a `.safetensors` around 2–4GB). If the loader says SDXL, stop. Do not override it.
4. Preset `rx590-sd15-safe` unless the user has already finished one run. Quality preset only after a safe run completed.
5. Trigger word in every caption, short, unique, not a real common word.

## What the watchdog does

The trainer is a child process. Heartbeats are written every step. Checkpoints are atomic. If the child dies, the parent resumes from the last checkpoint and walks one rung down: attention slicing, checkpointing, lower rank, lower resolution, lower LR. It will not loop the same crashing step forever.

Load `references/presets.md` if the user wants the numbers.

## While it runs

- `process` action `poll` for step, loss, and recovery count.
- Do not start image generation. The GPU lock will refuse, and that refusal is correct.
- A flat loss for a few hundred steps is a caption or LR problem, not a reason to kill the run.
- NaN loss halves the LR and forces fp32. That is expected on DirectML, not a bug to "fix" by turning fp16 back on.

## After

The LoRA is in the job output folder. In Forge, put it in the Lora folder and prompt `<lora:name:0.8>`.
