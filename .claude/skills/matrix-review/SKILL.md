---
name: matrix-review
description: Perform a feature-agnostic, two-phase deep code review of any wp-ai-scheduler PR, branch, or subsystem by building a Tier A/B/C Mechanism Map and a concrete Boundary & Scenario Test Matrix (pausing for user approval), then tracing every scenario across call chains, retired-code/cross-path parity, orphan references, and prior-fix falsification with unified diff patches.
---

# Matrix Review

Canonical cross-agent skill specification: [`.agents/skills/matrix-review/SKILL.md`](../../../.agents/skills/matrix-review/SKILL.md)  
Report template: [`.agents/skills/matrix-review/resources/report_template.md`](../../../.agents/skills/matrix-review/resources/report_template.md)  
Multi-agent workflow: [`.claude/workflows/matrix-review.md`](../../workflows/matrix-review.md)

Use this skill for an execution-traced, scenario-driven code review of any `wp-ai-scheduler` PR, branch, or feature subsystem (supporting both **PR / Branch Diff Mode** and **Feature / Subsystem Audit Mode**).

## Required workflow

Follow [`.agents/skills/matrix-review/SKILL.md`](../../../.agents/skills/matrix-review/SKILL.md) and [`.claude/workflows/matrix-review.md`](../../workflows/matrix-review.md):

1. **Phase 1 — System Discovery & Scenario Matrix Generation (Pause for Approval)**:
   - Sync baseline/commits (and digest all PR comments and prior fix commits in PR mode, or inventory feature files in Subsystem mode).
   - Build the **Feature Mechanism & Caller Map (Tiers A / B / C)** across touched and untouched files.
   - Run the **Universal Dimension & Boundary Value Extractor** (`0/1`, `threshold - 1`, `threshold`, `mid-range`, `> cap / non-divisible remainder`, mode/flag permutations, JS/WP boundary inputs, concurrency/locks, relational sync/cloning, partial errors, and upgrade migrations) to produce a scope-proportional **Test Scenario Matrix** (`10–18` small, `18–30` medium, `25–40+` large).
   - Present the standardized 3-part Phase 1 checkpoint and **STOP to wait for user approval** before beginning Phase 2.

2. **Phase 2 — Multi-Level Execution Tracing & Defect Synthesis (Read-Only)**:
   - Trace every approved matrix scenario and target code path through all **5 Mandatory Lenses**:
     1. Symbolic Execution & Math/Loop/State Boundary Trace
     2. Strict Caller $\leftrightarrow$ Callee Signature & Return-Shape Audit
     3. Retired-Code / Cross-Path Parity Audit
     4. Untouched-Codebase Orphan Reference Sweep (`git grep`)
     5. Prior-Fix Falsification, Adversarial Self-Check & Concrete Unified Diff Patches
   - Keep the repository strictly read-only (`git diff` and `git diff --cached` empty) and write the full report to `.artifacts/matrix-review/<TARGET_SLUG>_CODE_REVIEW_REPORT.md` using [`.agents/skills/matrix-review/resources/report_template.md`](../../../.agents/skills/matrix-review/resources/report_template.md).
   - **STOP and ask the user** which severity tiers, specific defect IDs, or uncovered matrix test scenarios they want applied in Phase 3.

3. **Phase 3 — Remediation & Matrix Test Implementation (Optional — User Approval Required)**:
   - Once approved by the user, apply the selected unified diff patches from Section 2 of the report, add/update PHPUnit tests for the uncovered matrix cases, and verify via `php -l` and `make test-session ARGS="..."` per `TESTING.md`.

## Useful agents
- `.claude/agents/ajax-controller-reviewer.md`
- `.claude/agents/background-job-reviewer.md`
- `.claude/agents/bootstrap-dependency-reviewer.md`
- `.claude/agents/db-migration-reviewer.md`
- `.claude/agents/generation-pipeline-reviewer.md`
- `.claude/agents/l10n-reviewer.md`
- `.claude/agents/qa-reviewer.md`
