---
name: image-caption
description: Caption images for training with a vision model, or say clearly when no vision model is connected. Use when the user asks to auto-caption a dataset.
license: MIT
metadata:
  hermes:
    category: training
    tags: [caption, vision, blip, llava]
version: 6.0.0
---

# Image caption

A text-only model cannot see the photo. Do not invent a caption and call it vision.

1. If Settings has no vision model, say so and offer the filename template `{trigger}, {name}`.
2. Vision models that work through Ollama here: moondream, llava, bakllava, minicpm-v, qwen2.5vl, gemma3. Prefer moondream on an 8GB card.
3. The autocaption request sends `num_ctx` from the model's context decision. Do not raise it to the native maximum.
4. One line. Trigger word first. No "masterpiece", no artist names, no camera brand unless it is actually relevant.
5. Review the grid after. A wrong caption teaches the wrong thing and looks like a failed training run.
