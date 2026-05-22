# Agent Workflow

This document defines how Codex, Copilot, Gemini, and other agents should work in this repository.

## 1) Intake labels

Use and maintain these labels:

- `agent-ready`
- `needs-spec`
- `duplicate-risk`
- `schema-change`
- `admin-ui`
- `ajax-registry`
- `cron`
- `generation-pipeline`
- `security-sensitive`
- `needs-browser-test`

Workflow: `.github/workflows/standardize-agent-labels.yml` standardizes these labels.

## 2) Agent-ready issue format

Use `.github/ISSUE_TEMPLATE/agent-ready-feature.yml` for implementation-ready feature work.

Required sections:
- Goal
- In scope
- Acceptance criteria
- Verification plan
- Risk labels

If details are missing, replace `agent-ready` with `needs-spec`.

## 3) PR risk checklist

All PRs use `.github/pull_request_template.md`.

The checklist requires:
- verification notes
- risk labels aligned to touched lanes
- duplicate-risk confirmation

## 4) Lane-specific instructions

Path-specific Copilot instructions:

- `.github/instructions/db-changes.instructions.md`
- `.github/instructions/admin-ui.instructions.md`
- `.github/instructions/ajax-controllers.instructions.md`
- `.github/instructions/cron-generation.instructions.md`

## 5) Codex skills

Codex skill prompts:

- `.github/prompts/skills/db-changes-skill.prompt.md`
- `.github/prompts/skills/admin-ui-skill.prompt.md`
- `.github/prompts/skills/ajax-controllers-skill.prompt.md`
- `.github/prompts/skills/generation-pipeline-skill.prompt.md`
- `.github/prompts/skills/pr-triage-skill.prompt.md`
- `.github/prompts/skills/feature-slicing-skill.prompt.md`

## 6) Weekly automations

- Open PR triage: `.github/workflows/pr-triage-daily.yml` (weekly schedule)
- Docs drift report: `.github/workflows/docs-drift-weekly.yml`

Prompt references:

- `.github/prompts/pr-triage-daily.prompt.md`
- `.github/prompts/docs-drift-weekly.prompt.md`

## 7) CI risk guards

Workflow: `.github/workflows/ci-pr.yml`

`Risk Guardrails` now checks:
- lane label presence based on changed paths
- schema/version guard:
  - schema-impacting changes require `schema-change`
  - schema-impacting changes require a plugin version bump
  - plugin header `Version:` and `AIPS_VERSION` must match

This keeps risky changes reviewable and easier to merge safely.
