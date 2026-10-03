---
name: forge-directml
description: Point image generation at Forge, SD.Next, or ComfyUI with DirectML for an RX 590. Use when generation fails or the user asks how to install a backend.
license: MIT
metadata:
  hermes:
    category: creative
    tags: [forge, directml, comfyui, a1111]
version: 6.0.0
---

# Forge / SD.Next on DirectML

This studio does not bundle a diffusion runtime for generation. It talks to a backend you already run, because those projects already handle DirectML on Polaris better than a from-scratch pipeline.

## Forge or SD.Next

1. Install with the DirectML option, not the NVIDIA installer.
2. Leave the API on. Forge exposes `/sdapi/v1/txt2img` the same way A1111 does.
3. Default URL is `http://127.0.0.1:7860`. Change it in Settings if the port differs.
4. Put LoRAs in that install's Lora folder. The prompt tag is `<lora:name:0.8>`.
5. Use an SD 1.5 checkpoint. An SDXL checkpoint will load and then die mid-step on 8GB.

## ComfyUI

Set the backend to Comfy and the checkpoint filename to one that exists on that machine. The graph is a single KSampler. If the checkpoint name is wrong, Comfy returns no image and the studio reports that, rather than inventing a picture.

## Do not

- Run Forge and a training job together. Pause training. The studio returns 409 if you forget.
- Turn on `--medvram` experiments copied from a 4GB NVIDIA guide without checking the log. DirectML flags are not CUDA flags.
