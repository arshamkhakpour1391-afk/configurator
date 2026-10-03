---
name: agent-memory
description: Save and recall durable user facts with the memory tool. Use when the user states a preference, a path, a trigger word, or a machine detail that should survive the session.
license: MIT
metadata:
  hermes:
    category: agent
    tags: [memory, fts, preferences]
version: 6.0.0
---

# Memory

Memory is a SQLite FTS5 table, not a scratchpad in the chat. Use it for facts that should still be true tomorrow.

Save:

- GPU is an RX 590 GME, 8GB, and whether VRAM was overridden.
- Base model path on disk.
- Trigger words and dataset ids.
- "Do not use SDXL." "Prefer Persian UI." A forge URL that is not the default.

Do not save:

- Secrets, API keys, or the contents of a whole file.
- A guess. If `model_inspect` was low confidence, save that it was a guess, not the number as fact.
- One-off prompt text.

Search before you ask the user for a path they already gave you. Forget a memory when the user says it is wrong. Do not leave the stale one beside the new one.
