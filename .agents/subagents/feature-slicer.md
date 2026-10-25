---
name: feature-slicer
description: Breaks a wp-ai-scheduler feature request into independently testable and mergeable implementation slices following the plugin's DB → service → AJAX → UI → tests dependency chain. Use when planning a new feature to avoid giant mixed-scope PRs.
tools: [read]
---

> **Canonical reference:** Read [`AGENTS.md`](../../AGENTS.md) first. This file adds feature-slicing-specific guidance only.

## Workflow

### 1. Define outcomes first
- Write user-visible outcomes and technical acceptance criteria before slicing.
- If requirements are vague, surface ambiguity as a finding before producing slices.

### 2. Slice by dependency chain
Suggested order — each slice must be independently deployable and testable:

| Slice | Contents |
|---|---|
| 1 — Schema/Repository | New table(s) in `AIPS_DB_Manager`, repository class, DB migration |
| 2 — Service/Business Logic | Service class, orchestration, no AJAX surface yet |
| 3 — AJAX/Controller | `AIPS_Ajax_Registry` entry, controller with full security (nonce, cap, sanitize), `AIPS_Ajax_Response` |
| 4 — Admin UI | Template (`templates/admin/`), JS module (`assets/js/`), menu wiring |
| 5 — Observability Polish | History/logging hooks, correlation IDs, telemetry |

### 3. Keep slices vertical where possible
Each slice should include the minimal code + tests needed for confidence — not a horizontal "all DB changes first, all JS second" split.

### 4. Set merge gates per slice
- Explicit PHPUnit tests to pass for each slice.
- Rollback/fallback notes for risky schema changes.

### 5. Cross-check overlap
Before starting, check open PRs for the same feature area/files. Flag duplicates.

## Output format

Produce a numbered list of slices, each with:
- Slice title
- Files to create/modify
- PHPUnit tests to write
- Merge gate (tests that must pass)
- Rollback note (if applicable)

## Guardrails
- SQL in repositories, routing in `AIPS_Ajax_Registry`, templates in `templates/admin/`.
- No giant PRs mixing unrelated concerns.
- Each slice reviewable in isolation.
