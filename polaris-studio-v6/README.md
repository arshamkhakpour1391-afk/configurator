# Polaris Studio v6

Local image training and an agent workstation, tuned for an **AMD Radeon RX 590 GME** (8GB, Polaris). It does not pretend a 128k model fits in 8GB, and it does not let a training run vanish because Windows reset the GPU.

The archive this session was asked to improve was not in the workspace. Nothing from that archive was deleted, because it was not here. This package is the v6 studio: the training watchdog, automatic context, and the Hermes-style skills are built in rather than bolted onto a missing tree.

## Start

Windows:

```bat
start.bat
```

Linux / macOS:

```bash
chmod +x start.sh
./start.sh
```

Opens `http://127.0.0.1:8787`. First launch creates `.venv` and installs the light requirements (FastAPI, not PyTorch). Training libraries are separate, on purpose.

Persian UI: the فا button in the header, or pick فارسی in the first-run sheet.

## What v6 actually fixes

Training that "randomly stops" on this card is usually one of these. Each one is handled, not just documented.

| What it looked like | Cause | What v6 does |
| --- | --- | --- |
| Window flash, process gone, no traceback | Windows TDR (2 second GPU timeout) | `scripts/Enable-LongGPUTimeout.reg` and `scripts/Check-Rx590.ps1`. Watchdog treats a device-removed exit as recoverable. |
| Died on step 40 of a long run | VRAM spike from a full-size photo, fp16 NaN, or a second job | Images are center-cropped on CPU before the VAE. DirectML is forced to fp32. Generation is refused while training holds the GPU. |
| Started over from zero after a crash | No checkpoint, or a half-written one | Checkpoint every 25 steps, atomic rename, resume on studio restart. |
| Hung with the fan still spinning | Driver reset left the process alive | Heartbeat plus a progress timeout. A pulse with no new step is a hang, not "still working". |
| Same step crashed forever | Bad file or a model that cannot load | Three crashes on the same step stop the loop and say so. |
| Chat died once the prompt got long | Ollama default `num_ctx` is a few thousand | Every request sends the computed `num_ctx` for that model. |

The watchdog is a parent process. The trainer is a child. You can prove the parent works without a GPU: Health → **Test the watchdog**. It injects an out-of-memory exit at step 3 and resumes.

## Context, read from the model

Order of trust:

1. GGUF metadata (`{arch}.context_length`, rope scaling, KV heads).
2. Hugging Face `config.json`.
3. Ollama `/api/show` or llama.cpp `/props`, then the GGUF that server loaded.
4. A name guess, labeled low confidence. Never silent.

The applied window is then clamped to this card. Safe policy keeps 25% under the f16 KV fit so a browser on the desktop does not reset the driver. The Models page shows native vs applied, the VRAM bar, and a `llama-server` command you can copy. llama.cpp cannot grow `-c` while it is running; the page says so instead of pretending.

Windows often reports this 8GB card as 4GB because `AdapterRAM` is a 32-bit field. A 590 name is corrected and the correction is labeled. A 4GB RX 580 is not silently upgraded — set the override in the first-run sheet.

## Image training

1. Datasets → upload 15–40 images, or make the sample shapes (those only prove the pipeline).
2. Captions are required. Empty captions used to NaN the loss. A vision model (moondream through Ollama is the one that fits) can fill them; a text model is not asked to pretend it can see.
3. Train → RX 590 safe preset. SD 1.5 only. SDXL is refused.
4. Base model is a local folder or a `.safetensors` file. Do not point it at an SDXL checkpoint.

Install the trainer when you are ready:

```bat
pip install torch-directml
pip install -r requirements-train.txt
```

On Linux, ROCm does not support this chip. Do not set `HSA_OVERRIDE_GFX_VERSION`. Use Vulkan for LLMs. Image training on Linux needs a PyTorch build that can see the card; if it cannot, the worker exits with a dependency error instead of hanging.

## Image generation

The studio does not invent a PNG when Forge is down. Point Settings at Forge, SD.Next, or Automatic1111 with the DirectML install, default `http://127.0.0.1:7860`. LoRA prompt tag is `<lora:name:0.8>`. Stay at 512, or 512×768. Larger than 768² is rejected.

## Agent

Skills use the [Agent Skills](https://agentskills.io/specification) `SKILL.md` layout, the same one Hermes Agent loads. The prompt only sees name and description. `skill_view` loads the body; reference files load on a second call.

Bundled skills: `rx590-tuning`, `lora-training`, `crash-recovery`, `context-window`, `dataset-curator`, `prompt-craft`, `image-caption`, `forge-directml`, `agent-memory`, `skill-authoring`, `model-picker`.

Tools follow Hermes names where a skill written for Hermes should still read: `skill_view`, `memory`, `terminal`, `web_extract`, `todo`, `process`, `clarify`, plus `model_inspect`, `train_control`, `dataset_scan`, `image_generate`. The terminal tool is off until you enable it. It runs without a shell, in `data/workspace`, with a denylist.

Small local models rarely speak native tool calls. The text protocol is the default:

```text
<tool_call>
{"name":"model_inspect","arguments":{"ref":"model.gguf"}}
</tool_call>
```

Turn on native tool calls in Settings only for a model that actually supports them.

## Layout

```text
start.bat / start.sh     launch
app/                     server, context, watchdog, agent
web/                     UI (fonts bundled, no Google Fonts)
skills/                  bundled skills
scripts/                 TDR registry + PowerShell check
docs/RX590.md            hardware notes
requirements.txt         UI and API
requirements-train.txt   torch / diffusers / peft
```

Runtime data lives in `data/` next to the app, or `POLARIS_DATA`.

## Tests

```bash
python -m unittest tests.test_studio -v
```

Covers GGUF parsing (including a tokenizer array before the context key), rope scaling not applied twice, the 4GB Windows misreport, the recovery ladder, a real subprocess resume after an injected OOM, skill loading, and the HTTP API.

## Honesty

- No GPU in this environment means the UI assumes an RX 590 GME until your PC detects one.
- Generation needs Forge or Comfy. The studio will say so.
- Training needs the train requirements and a local SD 1.5 file.
- q8 KV is a quality trade and is labeled when it is recommended.
- Partial GPU offload is not the default on Polaris. It crashes more often than it helps.
