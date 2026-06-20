# Copilot Instructions for AI Post Scheduler

Use [`../AGENTS.md`](../AGENTS.md) as the canonical repository rule set. Use [`../docs/AI_AGENT_REFERENCE.md`](../docs/AI_AGENT_REFERENCE.md) for long-form architecture details.

## Copilot-specific behavior

- Prefer small, reviewable edits that preserve existing WordPress/plugin patterns.
- When suggesting code, follow the repository rules in `AGENTS.md` and avoid duplicating architecture guidance here.
- For skill-specific review or planning, use the canonical checklists in `.codex/skills/*/SKILL.md`; files in `.github/prompts/skills/` are Copilot prompt wrappers that point back to those checklists.
- Do not propose local unit-test shims that bypass the WordPress test library. If tests cannot run in the current environment, say so and provide the supported command from `AGENTS.md`.
- For state-changing admin or AJAX code, always call out nonce, capability, sanitization, escaping, and response-shape requirements.
- For schema or persistence changes, point reviewers to `AIPS_DB_Manager`, `AIPS_DB_Migrations`, and repository boundaries.
- For generation changes, point reviewers to context abstractions, prompt builders, history/logging, correlation IDs, retry behavior, and partial-generation recovery.
