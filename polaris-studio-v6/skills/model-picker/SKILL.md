---
name: model-picker
description: Pick a local LLM quant that actually fits an RX 590 GME. Use when the user asks which model to download or why a model will not load.
license: MIT
metadata:
  hermes:
    category: models
    tags: [gguf, quant, q4, vram]
version: 6.0.0
---

# Model picker

Comfortable on 8GB Polaris:

- 7B or 8B at Q4_K_M. This is the default recommendation.
- 3B–4B at Q5_K_M or Q6_K if they want a smaller, cleaner model.
- A vision model only if they need captions: moondream, not a 7B LLaVA, if VRAM is already tight.

Uncomfortable, say so before they download:

- 14B Q4. It does not fit with a useful context. Partial offload on this chip is unstable.
- 70B anything.
- FP16 7B. That is ~14GB of weights before KV.

After they have a file, `model_inspect` the path. Quote `applied_context`, the quant read from `general.file_type`, and the llama.cpp command. If confidence is low, say the file was not read.

Ollama on this card: set `OLLAMA_VULKAN=1` before the server starts. ROCm will not pick up gfx803 correctly, and forcing it is how the desktop resets.
