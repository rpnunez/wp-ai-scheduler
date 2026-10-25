@AGENTS.md

# Claude Code specifics

`AGENTS.md` (imported above) is the canonical instruction source, including the rules-by-file-type table and the testing protocol. Do not repeat it here. Deeper reference: `docs/AI_AGENT_REFERENCE.md`, `docs/DEVELOPMENT_GUIDELINES.md`, `TESTING.md`, `docs/SETUP.md` (Docker/`make` commands).

## Subagents

The roster is in `AGENTS.md`; `.claude/agents/*.md` are thin wrappers around `.agents/agents/*.md`.

## Skills and workflows

- Skills: `.agents/skills/` (see `AGENTS.md`). `.claude/skills/*/SKILL.md` are stubs pointing there (Claude Code only discovers that folder).
- Saved multi-agent workflows: `.claude/workflows/` (`ajax-security-sweep`, `db-migration-audit`, `generation-pipeline-sweep`, `full-plugin-pr-audit`).
