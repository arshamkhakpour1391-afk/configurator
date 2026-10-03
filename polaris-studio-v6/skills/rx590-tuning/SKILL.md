---
name: rx590-tuning
description: Tune an AMD RX 590 or RX 590 GME for local LLMs and SD 1.5. Use when the card resets, runs hot, or a setting looks copied from an NVIDIA guide.
license: MIT
metadata:
  hermes:
    category: hardware
    tags: [amd, polaris, rx590, vulkan, directml]
version: 6.0.0
---

# RX 590 GME tuning

The RX 590 GME is a Polaris 20 card: 8GB GDDR5, 2304 shaders, 256-bit bus. It is not RDNA. Guides written for a 6700 XT or an RTX card will crash it.

## Do this

1. Call `hardware_report` before changing settings.
2. LLMs: Vulkan. `OLLAMA_VULKAN=1` for Ollama, or a llama.cpp build with Vulkan. Do not set `HSA_OVERRIDE_GFX_VERSION`. That override is how Polaris jobs die with a GPU reset and no Python error.
3. Image training: DirectML on Windows, fp32, 512px, batch 1, rank 8–16, SD 1.5 only. SDXL does not fit.
4. Context is not "whatever the model card says". Call `model_inspect` and use `applied_context`. On this card a 7B Q4_K_M usually lands around 8k, not 32k or 128k.
5. Close hardware-accelerated browsers while training. Chrome holds hundreds of MB of this VRAM.
6. Fan curve up before power limit up. Thermal throttle on Polaris becomes a driver reset.
7. Windows pagefile 32GB. A commit-charge kill looks random.

## Do not

- fp16 training on DirectML. It NaNs, then the run "stops".
- DataLoader workers. This studio forces 0. Do not add them back.
- Two GPU jobs at once. Generation during training resets the card. Pause training first.
- Partial GPU offload as the default. It is unstable here. Use a smaller quant that fits entirely.

## Related

Load `crash-recovery` if a run already died. Load `context-window` before changing `num_ctx`.
