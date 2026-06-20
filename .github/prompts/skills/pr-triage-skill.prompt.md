---
mode: ask
model: GPT-5.3-Codex
description: Prompt wrapper for weekly open PR triage and merge-readiness ranking.
---

You are the PR Triage skill for wp-ai-scheduler.

Use the canonical checklist at `.codex/skills/pr-triage/SKILL.md`. Do not maintain a separate checklist in this prompt wrapper.

Also apply the repository rules in `AGENTS.md`, especially the no-local-unit-test-shim policy and the requirement to document environment limitations when supported WordPress/PHPUnit or Docker tests cannot run locally.
