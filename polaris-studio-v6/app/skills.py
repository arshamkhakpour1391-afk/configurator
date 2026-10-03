"""Agent Skills loader.

Compatible with the agentskills.io SKILL.md layout used by Hermes Agent:
YAML frontmatter (name, description, optional metadata) plus a markdown body.
Only the name and description are injected into the prompt. The body and
reference files are loaded when the agent calls skill_view.
"""

from __future__ import annotations

import re
from pathlib import Path
from typing import Any

from .util import SKILLS_DIR, data_dir, safe_relative


NAME_RE = re.compile(r"^[a-z0-9]+(?:-[a-z0-9]+)*$")


class SkillError(ValueError):
    pass


def _split_frontmatter(text: str) -> tuple[dict[str, Any], str]:
    if not text.startswith("---"):
        raise SkillError("SKILL.md must start with YAML frontmatter")
    end = text.find("\n---", 3)
    if end < 0:
        raise SkillError("SKILL.md frontmatter is not closed")
    raw = text[3:end].strip()
    body = text[end + 4 :].lstrip("\n")
    try:
        import yaml
    except ImportError as exc:
        raise SkillError("PyYAML is required to read skills") from exc
    loaded = yaml.safe_load(raw) or {}
    if not isinstance(loaded, dict):
        raise SkillError("skill frontmatter must be a mapping")
    return loaded, body


def _validate(meta: dict[str, Any], folder: str) -> None:
    name = str(meta.get("name") or "")
    description = str(meta.get("description") or "").strip()
    if not NAME_RE.match(name) or len(name) > 64:
        raise SkillError(f"invalid skill name: {name!r}")
    if name != folder:
        raise SkillError(f"skill name {name!r} must match folder {folder!r}")
    if not description or len(description) > 1024:
        raise SkillError(f"{name}: description must be 1–1024 characters")


def user_skills_dir() -> Path:
    path = data_dir() / "skills"
    path.mkdir(parents=True, exist_ok=True)
    return path


def _iter_skill_dirs() -> list[tuple[Path, str]]:
    found: list[tuple[Path, str]] = []
    for root, origin in ((SKILLS_DIR, "bundled"), (user_skills_dir(), "user")):
        if not root.exists():
            continue
        for child in sorted(root.iterdir()):
            if child.is_dir() and (child / "SKILL.md").exists():
                found.append((child, origin))
    return found


def load_skill(directory: Path, origin: str) -> dict[str, Any]:
    text = (directory / "SKILL.md").read_text(encoding="utf-8")
    meta, body = _split_frontmatter(text)
    _validate(meta, directory.name)
    hermes = {}
    metadata = meta.get("metadata")
    if isinstance(metadata, dict):
        nested = metadata.get("hermes")
        hermes = nested if isinstance(nested, dict) else metadata
    references = []
    ref_dir = directory / "references"
    if ref_dir.is_dir():
        references = sorted(path.name for path in ref_dir.iterdir() if path.is_file())
    scripts = []
    script_dir = directory / "scripts"
    if script_dir.is_dir():
        scripts = sorted(path.name for path in script_dir.iterdir() if path.is_file())
    return {
        "name": meta["name"],
        "description": str(meta["description"]).strip(),
        "license": meta.get("license") or "",
        "compatibility": meta.get("compatibility") or "",
        "allowed_tools": meta.get("allowed-tools") or "",
        "category": (hermes.get("category") if isinstance(hermes, dict) else None) or meta.get("category") or "general",
        "tags": hermes.get("tags") if isinstance(hermes, dict) and isinstance(hermes.get("tags"), list) else [],
        "version": str(meta.get("version") or (hermes.get("version") if isinstance(hermes, dict) else "") or ""),
        "origin": origin,
        "enabled": True,
        "body": body,
        "references": references,
        "scripts": scripts,
        "path": str(directory),
    }


def disabled_names() -> set[str]:
    path = data_dir() / "disabled-skills.txt"
    if not path.exists():
        return set()
    return {line.strip() for line in path.read_text(encoding="utf-8").splitlines() if line.strip()}


def set_enabled(name: str, enabled: bool) -> None:
    current = disabled_names()
    if enabled:
        current.discard(name)
    else:
        current.add(name)
    text = "\n".join(sorted(current))
    path = data_dir() / "disabled-skills.txt"
    path.write_text(text + ("\n" if text else ""), encoding="utf-8")


def list_skills(include_body: bool = False) -> list[dict[str, Any]]:
    disabled = disabled_names()
    items = []
    # User skills override bundled ones with the same name.
    by_name: dict[str, dict[str, Any]] = {}
    for directory, origin in _iter_skill_dirs():
        try:
            skill = load_skill(directory, origin)
        except SkillError as exc:
            items.append({"name": directory.name, "error": str(exc), "origin": origin, "enabled": False})
            continue
        skill["enabled"] = skill["name"] not in disabled
        by_name[skill["name"]] = skill
    ordered = list(by_name.values()) + [item for item in items if "error" in item]
    if not include_body:
        for skill in ordered:
            skill.pop("body", None)
    return ordered


def get_skill(name: str, reference: str | None = None) -> dict[str, Any]:
    if not NAME_RE.match(name or ""):
        raise SkillError("invalid skill name")
    matches = [item for item in list_skills(include_body=True) if item.get("name") == name and "error" not in item]
    if not matches:
        raise SkillError(f"skill not found: {name}")
    skill = matches[-1]
    if reference:
        root = Path(skill["path"])
        file_path = safe_relative(root, reference)
        if not file_path.is_file():
            raise SkillError(f"reference not found: {reference}")
        skill = dict(skill)
        skill["reference"] = reference
        skill["reference_text"] = file_path.read_text(encoding="utf-8")[:20000]
    return skill


def write_skill(name: str, description: str, body: str, category: str = "general", tags: list[str] | None = None) -> dict[str, Any]:
    if not NAME_RE.match(name or ""):
        raise SkillError("name must be lowercase letters, numbers, and single hyphens")
    description = (description or "").strip()
    if not description or len(description) > 1024:
        raise SkillError("description must be 1–1024 characters and say when to use the skill")
    folder = user_skills_dir() / name
    folder.mkdir(parents=True, exist_ok=True)
    tag_text = ""
    if tags:
        tag_text = "\n  tags: [" + ", ".join(str(tag) for tag in tags) + "]"
    content = (
        "---\n"
        f"name: {name}\n"
        f"description: {json_escape_yaml(description)}\n"
        "license: MIT\n"
        "metadata:\n"
        "  hermes:\n"
        f"    category: {category or 'general'}"
        f"{tag_text}\n"
        "version: 6.0.0\n"
        "---\n\n"
        + (body or "").strip()
        + "\n"
    )
    (folder / "SKILL.md").write_text(content, encoding="utf-8")
    return get_skill(name)


def json_escape_yaml(value: str) -> str:
    # Quote if the description would break YAML.
    if any(token in value for token in (":", "#", "\n", "'", '"')):
        escaped = value.replace("'", "''")
        return f"'{escaped}'"
    return value


def skill_index_text() -> str:
    lines = []
    for skill in list_skills(include_body=False):
        if skill.get("error") or not skill.get("enabled", True):
            continue
        lines.append(f"- {skill['name']}: {skill.get('description', '')}")
    return "\n".join(lines)
