---
name: dataset-curator
description: Build a small image dataset for LoRA training. Use for captions, duplicates, resolution, trigger words, and the sample dataset.
license: MIT
metadata:
  hermes:
    category: training
    tags: [dataset, captions, images]
version: 6.0.0
---

# Dataset curator

1. 15–40 images, same subject, varied angle and light. Not 200 near-duplicates.
2. `dataset_scan` before training. Errors block the run. Warnings do not.
3. Every image needs a caption. Empty captions are an error because they train the model on pad tokens and then the loss goes NaN.
4. Put the trigger word first. Then concrete nouns: clothing, place, action. No artist names, no "masterpiece", no "8k".
5. The trainer center-crops to a square and resizes to the preset. A 4000px photo will not be sent to the VAE at full size. That used to be an out-of-memory stop.
6. EXIF rotation is applied on import. If the user dropped files straight into the folder, ask them to re-upload so orientation is fixed.
7. Duplicates are warned, not deleted. Ask before removing them.

The sample dataset is four flat shapes. It exists so the watchdog can be demonstrated. Do not tell the user it will learn a person.
