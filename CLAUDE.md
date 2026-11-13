@AGENTS.md

# Claude Code specifics

`AGENTS.md` (imported above) is the canonical instruction source, including the rules-by-file-type table and the testing protocol. Do not repeat it here. Deeper reference: `docs/AI_AGENT_REFERENCE.md`, `docs/DEVELOPMENT_GUIDELINES.md`, `TESTING.md`, `docs/SETUP.md` (Docker/`make` commands).

## Subagents, skills, rules and workflows

- `.claude/agents/*.md`, `.claude/skills/*/SKILL.md` and `.claude/rules/*.md` are generated pointers to `.agents/agents/`, `.agents/skills/` and `.agents/rules/` (Claude Code only discovers the `.claude` folders). Edit the canonical file and run `make sync-agents`; see `AGENTS.md`.
- Saved multi-agent workflows: `.claude/workflows/` (`ajax-security-sweep`, `db-migration-audit`, `generation-pipeline-sweep`, `pr-portfolio-triage`, `pr-xray`).
- Shared PR Xray contracts and report templates: `.agents/references/`, `.agents/templates/`.
