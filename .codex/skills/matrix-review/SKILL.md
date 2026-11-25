# Matrix Review Skill

Canonical skill definition: [`.agents/skills/matrix-review/SKILL.md`](../../../.agents/skills/matrix-review/SKILL.md)  
Report template: [`.agents/skills/matrix-review/resources/report_template.md`](../../../.agents/skills/matrix-review/resources/report_template.md)

Read and follow [`.agents/skills/matrix-review/SKILL.md`](../../../.agents/skills/matrix-review/SKILL.md) without duplicating or diverging from its two-phase workflow (supporting both **PR / Branch Diff Mode** and **Feature / Subsystem Audit Mode**):
1. **Phase 1**: Sync baseline, digest prior PR comments (when applicable), build the Tier A/B/C Feature Mechanism Map, generate the scope-proportional Boundary & Scenario Test Matrix (`10–18` small, `18–30` medium, `25–40+` large), and **pause for explicit user approval**.
2. **Phase 2**: Trace every approved scenario and target code path through all 5 Mandatory Execution-Trace Lenses (Symbolic Execution, Caller-Callee Contracts, Retired-Code/Cross-Path Parity, Orphan Reference Sweep, and Prior-Fix Falsification / Adversarial Self-Check with Unified Diff Patches) in strictly read-only mode, saving the full report to `.artifacts/matrix-review/<TARGET_SLUG>_CODE_REVIEW_REPORT.md`.
3. **Phase 3 (Optional — Approval Required)**: After delivering the report, ask the user which severity tiers, defect IDs, or uncovered matrix test cases to apply, and implement approved fixes/tests verified via `php -l` and `make test-session` per `TESTING.md`.
