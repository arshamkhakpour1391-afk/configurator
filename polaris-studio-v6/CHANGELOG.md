# Changelog

## 6.0.0

- Training supervisor: heartbeat, progress timeout, atomic checkpoints, resume after OOM / NaN / driver reset / studio restart. Stops looping the same crashing step.
- RX 590 GME profile. Corrects the Windows 4GB `AdapterRAM` wrap. Refuses ROCm override advice. Forces fp32 on DirectML.
- Context engine reads GGUF, Hugging Face config, Ollama `/api/show`, and llama.cpp `/props`, then clamps to VRAM. Applied on every chat request. Name guesses stay labeled.
- Agent skills in the Hermes / agentskills.io `SKILL.md` layout, with progressive disclosure and reference files.
- Tools: skill_view, skill_manage, memory, todo, terminal (off by default), web_extract, process, model_inspect, dataset_scan, train_control, image_generate, clarify, delegate_task.
- Dataset import validates images, applies EXIF orientation, and blocks empty captions.
- Image generation talks to Forge / Comfy and fails clearly when they are down. It will not run beside training.
- Health scan, watchdog drill, EN/FA UI, bundled fonts so the UI works without Google Fonts.
- TDR registry script and PowerShell check.
