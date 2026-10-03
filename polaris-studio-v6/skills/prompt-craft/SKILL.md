---
name: prompt-craft
description: Write SD 1.5 prompts and negative prompts that fit a 512px RX 590 generation, including LoRA syntax for Forge.
license: MIT
metadata:
  hermes:
    category: creative
    tags: [prompt, sd15, lora, negative]
version: 6.0.0
---

# Prompt craft

Stay at 512 or 512x768. 768 square is the edge of this card. Anything above 768x768 is rejected by the studio.

## Prompt

Subject, concrete details, light, lens. One sentence of style at the end if needed. Do not stack twenty quality tags. SD 1.5 listens to the first clauses more than the last.

LoRA in Forge / A1111: `<lora:filename:0.8>`. Start at 0.7–0.9. If the picture looks fried, drop to 0.5 before you add more steps.

## Negative

Default: `lowres, blurry, extra fingers, deformed, watermark, text, jpeg artifacts`.

Add anatomy terms only if the failure is anatomy. A huge negative prompt fights the LoRA.

## Settings that fit

- Sampler: Euler a or DPM++ 2M Karras
- Steps: 20–28. More steps will not fix a bad prompt and will heat the card.
- CFG: 6–8. CFG 12 on SD 1.5 burns the image.
- Seed: lock it when comparing a LoRA weight.

Do not enable hires fix on this card unless the user accepts a likely out-of-memory. The UI leaves it off.
