---
name: skill-authoring
description: Write a new Agent Skill in SKILL.md format. Use when the user wants to teach the agent a repeatable procedure.
license: MIT
metadata:
  hermes:
    category: agent
    tags: [skills, skill.md, agentskills]
version: 6.0.0
---

# Skill authoring

Skills follow the agentskills.io layout, the same one Hermes Agent loads.

```
skill-name/
  SKILL.md
  references/   optional, loaded only when asked
  scripts/      optional
```

Frontmatter:

- `name`: lowercase, hyphens, must match the folder, max 64 characters.
- `description`: what it does and when to use it, max 1024 characters. This is the only part in the prompt until `skill_view`.
- Optional `metadata.hermes.tags` and `category`.

Body: steps, a table of failures, and what not to do. Keep it under 500 lines. Put long tables in `references/` and tell the agent to load them.

Create skills with `skill_manage` action `create`. Do not overwrite a bundled skill; write a new name. Bundled skills can be disabled, not deleted, from the Skills page.
