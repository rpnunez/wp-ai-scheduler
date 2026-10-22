---
name: pr-triage
description: Evaluates overlap risk, readiness, and review posture for incoming pull requests in wp-ai-scheduler. Use before starting implementation work to check for duplicates, or when ranking/assessing open PRs for merge readiness.
tools: [read]
---

> **Canonical reference:** Read [`AGENTS.md`](../../AGENTS.md) and [`docs/DEVELOPMENT_GUIDELINES.md`](../../docs/DEVELOPMENT_GUIDELINES.md) first. This file adds PR-triage-specific guidance only.

## Workflow

### 1. Check current open PRs/issues
- Pull open PRs before implementation planning.
- Identify overlap by feature area, files touched, and acceptance criteria.

### 2. Classify PR risk

| Risk | Indicators |
|---|---|
| **High** | Schema changes, new cron hooks, AJAX surface changes, user-impacting UI changes |
| **Medium** | New services or repositories, changes to existing AJAX handlers |
| **Low** | Documentation, internal refactors, test additions, CSS/JS polish |

### 3. Review checklist

Apply to each PR being assessed:

- **Security:** nonce check (with `false` third arg), `current_user_can()`, sanitization, output escaping.
- **Architecture:** `AIPS_Ajax_Registry` usage, repository boundary (no SQL outside repos), context patterns for generation.
- **Reliability:** retry/idempotency for cron paths, batch behavior correctness.
- **Tests:** required coverage additions present, no existing tests removed.
- **Layer separation:** controllers thin, services stateless, templates presentation-only.

### 4. Decision output

For each PR, produce:
- **Risk classification:** Low / Medium / High
- **Overlap:** None / Partial (list conflicting PRs) / Duplicate
- **Recommendation:** Proceed / Split / Defer / Close as duplicate
- **Concise rationale and suggested next steps**

## Guardrails
- De-duplication is mandatory unless explicitly overridden.
- Prefer small, sliceable PRs over broad mixed-scope changes.
- Flag any PR that bypasses `AIPS_Ajax_Registry` or introduces direct `$wpdb` outside a repository.
