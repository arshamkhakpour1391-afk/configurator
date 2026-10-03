"""Unit and API tests for Polaris Studio v6. No GPU required."""

from __future__ import annotations

import io
import json
import os
import struct
import tempfile
import unittest
from pathlib import Path

# Isolate data before any studio import.
os.environ["POLARIS_DATA"] = tempfile.mkdtemp(prefix="polaris-test-")
os.environ["POLARIS_NO_VENV"] = "1"

from app.agent import parse_tool_calls  # noqa: E402
from app.context import (  # noqa: E402
    decide_context,
    meta_from_gguf,
    meta_from_hf_config,
    read_gguf_metadata,
)
from app.hardware import correct_vram, match_profile, parse_lspci  # noqa: E402
from app.llm import trim_messages  # noqa: E402
from app.skills import SkillError, get_skill, list_skills, write_skill  # noqa: E402
from app.train_contract import EXIT_HUNG, EXIT_NAN, EXIT_OOM, apply_recovery  # noqa: E402
from app.training import create_job, run_job  # noqa: E402
from app.util import safe_relative  # noqa: E402


def _pack_string(text: str) -> bytes:
    raw = text.encode("utf-8")
    return struct.pack("<Q", len(raw)) + raw


def _pack_value(value):
    if isinstance(value, str):
        return struct.pack("<I", 8) + _pack_string(value)
    if isinstance(value, bool):
        return struct.pack("<I", 7) + struct.pack("<B", int(value))
    if isinstance(value, int):
        return struct.pack("<I", 4) + struct.pack("<I", value)
    if isinstance(value, float):
        return struct.pack("<I", 6) + struct.pack("<f", value)
    if isinstance(value, list) and value and isinstance(value[0], str):
        body = struct.pack("<I", 8) + struct.pack("<Q", len(value))
        for item in value:
            body += _pack_string(item)
        return struct.pack("<I", 9) + body
    raise TypeError(type(value))


def write_gguf(path: Path, pairs: list[tuple[str, object]]) -> None:
    blob = struct.pack("<I", 0x46554747)
    blob += struct.pack("<I", 3)
    blob += struct.pack("<Q", 0)
    blob += struct.pack("<Q", len(pairs))
    for key, value in pairs:
        blob += _pack_string(key)
        blob += _pack_value(value)
    path.write_bytes(blob)


class ContextTests(unittest.TestCase):
    def test_gguf_roundtrip_with_array_before_context(self):
        path = Path(os.environ["POLARIS_DATA"]) / "tiny.gguf"
        write_gguf(
            path,
            [
                ("tokenizer.ggml.tokens", ["hello", "world", "there", "friend"]),
                ("general.architecture", "qwen2"),
                ("general.name", "Qwen2.5 7B"),
                ("general.file_type", 15),
                ("qwen2.context_length", 32768),
                ("qwen2.block_count", 28),
                ("qwen2.attention.head_count", 28),
                ("qwen2.attention.head_count_kv", 4),
                ("qwen2.attention.key_length", 128),
                ("qwen2.embedding_length", 3584),
            ],
        )
        meta_raw = read_gguf_metadata(path)
        self.assertEqual(meta_raw["qwen2.context_length"], 32768)
        self.assertEqual(meta_raw["general.architecture"], "qwen2")
        self.assertIn("skipped", meta_raw["tokenizer.ggml.tokens"])
        meta = meta_from_gguf(meta_raw, file_size=path.stat().st_size, name=path.name)
        self.assertEqual(meta["native_context"], 32768)
        self.assertEqual(meta["confidence"], "high")
        self.assertEqual(meta["kv_heads"], 4)
        self.assertEqual(meta["quant"], "Q4_K_M")
        hardware = {"vram_mb": 8192, "polaris": True, "os": "windows", "gpu_name": "RX 590 GME", "cpu_count": 8}
        decision = decide_context(meta, hardware, policy="safe")
        self.assertLess(decision["applied_context"], decision["native_context"])
        self.assertEqual(decision["applied_context"] % 256, 0)
        self.assertGreaterEqual(decision["applied_context"], 2048)
        self.assertIn("num_ctx", decision["ollama_options"])
        self.assertEqual(decision["ollama_options"]["num_ctx"], decision["applied_context"])
        self.assertIn("-c " + str(decision["applied_context"]), decision["llama_command"])
        self.assertNotIn("--flash-attn", decision["llama_command"])

    def test_rope_scaling_not_applied_twice(self):
        meta = meta_from_hf_config(
            {
                "model_type": "llama",
                "max_position_embeddings": 131072,
                "rope_scaling": {"factor": 8.0, "original_max_position_embeddings": 8192},
                "num_hidden_layers": 32,
                "num_attention_heads": 32,
                "num_key_value_heads": 8,
                "hidden_size": 4096,
            },
            name="llama-3.1-8b",
        )
        self.assertEqual(meta["native_context"], 131072)
        self.assertTrue(any("not multiplied" in item for item in meta["warnings"]))

    def test_rope_extends_short_context(self):
        meta = meta_from_hf_config(
            {
                "model_type": "llama",
                "max_position_embeddings": 4096,
                "rope_scaling": {"factor": 4.0, "original_max_position_embeddings": 4096},
                "num_hidden_layers": 32,
                "num_attention_heads": 32,
                "num_key_value_heads": 8,
                "hidden_size": 4096,
            }
        )
        self.assertEqual(meta["native_context"], 16384)

    def test_name_guess_is_low_confidence(self):
        from app.context import meta_from_name_only

        meta = meta_from_name_only("Meta-Llama-3.1-8B-Instruct-Q4_K_M.gguf")
        self.assertEqual(meta["confidence"], "low")
        self.assertEqual(meta["native_context"], 131072)
        decision = decide_context(meta, {"vram_mb": 8192, "polaris": True, "os": "linux", "cpu_count": 4, "gpu_name": "RX 590 GME"})
        self.assertEqual(decision["confidence"], "low")
        self.assertTrue(decision["warnings"])

    def test_trim_keeps_system_and_tail(self):
        messages = [{"role": "system", "content": "sys"}] + [
            {"role": "user", "content": "x" * 500} for _ in range(30)
        ]
        trimmed = trim_messages(messages, num_ctx=1024, max_tokens=256)
        self.assertEqual(trimmed[0]["role"], "system")
        self.assertLess(len(trimmed), len(messages))


class HardwareTests(unittest.TestCase):
    def test_lspci_and_4gb_wrap(self):
        text = "01:00.0 VGA compatible controller: Advanced Micro Devices, Inc. [AMD/ATI] Ellesmere [Radeon RX 590] [1002:6fdf]"
        gpus = parse_lspci(text)
        self.assertEqual(gpus[0]["device_id"], "6fdf")
        profile = match_profile({"name": "Radeon RX 590 GME", "device_id": "67df"})
        self.assertEqual(profile["id"], "rx590gme")
        vram, warnings = correct_vram(
            {"name": "Radeon RX 590 GME", "vram_mb": 4096},
            profile,
        )
        self.assertEqual(vram, 8192)
        self.assertTrue(warnings)

    def test_path_escape(self):
        root = Path(os.environ["POLARIS_DATA"])
        with self.assertRaises(ValueError):
            safe_relative(root, "../etc/passwd")
        with self.assertRaises(ValueError):
            safe_relative(root, "foo/../../secrets")


class RecoveryTests(unittest.TestCase):
    def test_ladder_is_one_rung(self):
        spec = {"rank": 16, "resolution": 512, "lr": 1e-4, "attention_slicing": True, "gradient_checkpointing": True}
        updated, reason = apply_recovery(spec, EXIT_OOM)
        self.assertEqual(updated["rank"], 8)
        self.assertEqual(updated["resolution"], 512)
        self.assertIn("rank", reason)
        again, _ = apply_recovery(updated, EXIT_OOM)
        self.assertEqual(again["rank"], 4)
        nan_spec, nan_reason = apply_recovery(spec, EXIT_NAN)
        self.assertLess(nan_spec["lr"], spec["lr"])
        self.assertTrue(nan_spec["force_fp32"])
        self.assertIn("NaN", nan_reason)
        hung, hung_reason = apply_recovery({"rank": 4, "resolution": 384, "lr": 1e-5, "attention_slicing": True, "gradient_checkpointing": True}, EXIT_HUNG)
        self.assertIn("TDR", hung_reason)
        self.assertEqual(hung["resolution"], 384)

    def test_watchdog_resumes_after_oom(self):
        spec = {
            "engine": "smoke",
            "steps": 6,
            "crash_at": 3,
            "checkpoint_every": 1,
            "step_sleep": 0.01,
            "stall_seconds": 30,
            "startup_grace_seconds": 10,
            "dead_timeout": 30,
            "max_recoveries": 4,
        }
        job = create_job(spec, kind="drill")
        finished = run_job(job["id"])
        self.assertEqual(finished["status"], "completed", finished.get("error") or finished.get("message"))
        self.assertGreaterEqual(len(finished["recoveries"]), 1)
        self.assertGreaterEqual(finished["step"], 6)
        self.assertTrue((Path(os.environ["POLARIS_DATA"]) / "jobs" / job["id"] / "output" / "smoke.txt").exists())

    def test_watchdog_kills_a_spin_and_resumes(self):
        spec = {
            "engine": "smoke",
            "steps": 4,
            "spin_at": 2,
            "spin_seconds": 3,
            "checkpoint_every": 1,
            "step_sleep": 0.01,
            "stall_seconds": 1,
            "startup_grace_seconds": 5,
            "dead_timeout": 8,
            "max_recoveries": 3,
        }
        job = create_job(spec, kind="drill")
        finished = run_job(job["id"])
        self.assertEqual(finished["status"], "completed", finished.get("error") or finished.get("message"))
        self.assertTrue(any("timeout" in (item.get("reason") or "").lower() or "TDR" in (item.get("reason") or "") or "progress" in (item.get("reason") or "").lower() or "heartbeat" in (item.get("reason") or "").lower() or "retry" in (item.get("reason") or "").lower() for item in finished["recoveries"]))


class SkillTests(unittest.TestCase):
    def test_bundled_and_user_skills(self):
        names = {item["name"] for item in list_skills()}
        self.assertIn("context-window", names)
        self.assertIn("crash-recovery", names)
        skill = get_skill("crash-recovery", "references/tdr.md")
        self.assertIn("TdrDelay", skill["reference_text"])
        with self.assertRaises(SkillError):
            write_skill("Bad Name", "desc", "body")
        created = write_skill("my-recipe", "Use when baking a local LoRA recipe for this card.", "Do the thing.\n")
        self.assertEqual(created["name"], "my-recipe")
        self.assertIn("Do the thing", created["body"])

    def test_tool_parser(self):
        text = 'Sure.\n<tool_call>\n{"name":"model_inspect","arguments":{"ref":"a.gguf"}}\n</tool_call>\n'
        calls = parse_tool_calls(text)
        self.assertEqual(calls[0]["name"], "model_inspect")
        self.assertEqual(calls[0]["arguments"]["ref"], "a.gguf")
        nested = '<tool_call>{"name":"memory","arguments":{"action":"add","content":"use {trigger}"}}</tool_call>'
        self.assertEqual(parse_tool_calls(nested)[0]["arguments"]["content"], "use {trigger}")


class APITests(unittest.TestCase):
    def test_health_and_dataset_and_drill_endpoint(self):
        from fastapi.testclient import TestClient

        from app.server import app

        client = TestClient(app)
        version = client.get("/api/version")
        self.assertEqual(version.status_code, 200)
        self.assertEqual(version.json()["version"], "6.0.0")
        health = client.get("/api/health")
        self.assertEqual(health.status_code, 200)
        self.assertIn("checks", health.json())
        sample = client.post("/api/datasets/sample")
        self.assertEqual(sample.status_code, 200, sample.text)
        dataset_id = sample.json()["id"]
        self.assertGreaterEqual(sample.json()["image_count"], 4)
        bad = client.put(f"/api/datasets/{dataset_id}/caption", json={"name": "../evil.png", "caption": "nope"})
        self.assertGreaterEqual(bad.status_code, 400)
        skills = client.get("/api/skills")
        self.assertGreaterEqual(len(skills.json()), 8)
        settings = client.get("/api/settings")
        self.assertEqual(settings.json()["context_policy"], "safe")
        # Inspect a written GGUF through the API and confirm it is stored.
        gguf = Path(os.environ["POLARIS_DATA"]) / "api.gguf"
        write_gguf(gguf, [("general.architecture", "llama"), ("llama.context_length", 8192), ("llama.block_count", 32), ("llama.attention.head_count", 32), ("llama.attention.head_count_kv", 8), ("llama.attention.key_length", 128)])
        inspected = client.post("/api/models/inspect", json={"ref": str(gguf), "activate": True})
        self.assertEqual(inspected.status_code, 200, inspected.text)
        body = inspected.json()
        self.assertEqual(body["native_context"], 8192)
        self.assertEqual(body["confidence"], "high")
        self.assertEqual(body["ollama_options"]["num_ctx"], body["applied_context"])


if __name__ == "__main__":
    unittest.main()
