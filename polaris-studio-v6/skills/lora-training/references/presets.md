# Presets

| Preset | Steps | Rank | Resolution | Notes |
| --- | --- | --- | --- | --- |
| rx590-sd15-safe | 800 | 8 | 512 | Default. fp32, batch 1, grad accum 4, checkpoint every 25 |
| rx590-sd15-quality | 1500 | 16 | 512 | Only after a safe run finished |
| rx590-sd15-fast | 400 | 4 | 448 | Draft to see if the concept is learnable |

Effective batch is `batch_size * grad_accum`. On this card batch size stays 1. Accumulation is how you get a larger batch without a VRAM spike.

Learning rate 1e-4 with min-SNR gamma 5. If faces melt, drop to 5e-5. If nothing is learned after 400 steps, the captions are the problem, not the LR.

Repeats: safe preset uses 8. Epochs shown in the UI are `steps / (images * repeats)`.
