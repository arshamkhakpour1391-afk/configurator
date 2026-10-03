---
name: context-window
description: Set a model's context window from its real metadata and this GPU's VRAM, not from the model card headline. Use whenever a model is selected, a GGUF is added, or the user asks for 32k/128k on an 8GB card.
license: MIT
metadata:
  hermes:
    category: models
    tags: [gguf, num_ctx, kv-cache, ollama]
version: 6.0.0
---

# Context window

## Order of trust

1. GGUF key `{architecture}.context_length`, plus rope scaling if the file did not already apply it.
2. Hugging Face `config.json` (`max_position_embeddings`, rope_scaling).
3. Ollama `/api/show` model_info, which uses the same keys.
4. llama.cpp `/props` model path, then read that GGUF.
5. Name guess. This is low confidence. Say so. Never pretend a guess is a file read.

## What gets applied

`model_inspect` returns `native_context`, `hard_fit_context`, and `applied_context`.

Chat and the agent send `num_ctx = applied_context` on every Ollama request. They also trim the prompt so it fits, and cap `num_predict`.

llama.cpp cannot change context while it is running. If `server_context` is below `applied_context`, tell the user to restart with `llama_command`. Do not claim the running server already has the new window.

## RX 590 math

KV bytes per token = `2 * layers * kv_heads * head_dim * bytes_per_elem`.

Safe policy then keeps 25% under the f16 fit so a desktop session does not reset the driver. q8 KV is recommended only when f16 cannot hold a useful window. It is a quality trade, and it is labeled.

## Do not

- Set num_ctx to the native 128k on this card.
- Multiply rope factor twice. If `context_length` is already the extended value, leave it.
- Ignore a sliding window. Extra tokens still cost KV on Ollama even when attention will not see them.
