# Matrix Review Workflow

Perform a two-phase, scenario-matrix and execution-traced deep review of a pull request, branch, or feature subsystem without modifying tracked repository files.

Canonical specification: [`.agents/skills/matrix-review/SKILL.md`](../../.agents/skills/matrix-review/SKILL.md)  
Report template: [`.agents/skills/matrix-review/resources/report_template.md`](../../.agents/skills/matrix-review/resources/report_template.md)

## Phase 1 — System Discovery & Test Matrix Generation (Approval Gate)

1. Read `AGENTS.md`, `TESTING.md`, and `.agents/skills/matrix-review/SKILL.md`.
2. Determine the target mode:
   - **PR / Branch Diff Mode**: Resolve the target PR/branch, intended base branch, exact `<base>...<head>` commit SHAs, PR description, linked issue(s), commit history, and all prior review comments (human, Cursor, Claude, Copilot).
   - **Feature / Subsystem Audit Mode**: Record the current branch and `HEAD` SHA and inventory all files belonging to the target feature subsystem.
3. Map the feature's mechanisms across the codebase into **Tier A** (primary/new paths), **Tier B** (hybrid/partially wired callers or unused registrations), and **Tier C** (untouched dependents & future unification candidates).
4. Extract quantitative thresholds, caps, modes, flags, JS/WP boundary inputs, date/time boundaries, relational sync/cloning rules, and error/lifecycle states to construct a scope-proportional **Test Scenario Matrix** (`10–18` cases for 1–5 files, `18–30` for 6–20 files, `25–40+` for 20+ files or full subsystems).
5. Present the 3-part Phase 1 Checkpoint (**Baseline & Prior Comment Digestion**, **Tier A/B/C Mechanism Map**, and **Master Test Scenario Matrix**) to the user and **STOP for explicit user approval**.

## Phase 2 — Parallel Multi-Level Execution Tracing (Read-Only)

Once the user approves the matrix (or invokes `/teamwork` with the approved matrix):

1. **Fan Out Parallel Explorers / Domain Reviewers**:
   Partition the approved matrix groups, Tier A/B/C mechanisms, and PR comment claims across parallel read-only workstreams. Every workstream applies the **5 Mandatory Lenses** defined in `.agents/skills/matrix-review/SKILL.md`:
   - **Lens 1**: Symbolic Execution & Math/Loop/State Boundary Trace (substitute exact matrix inputs into loops/formulas/templates).
   - **Lens 2**: Strict Caller $\leftrightarrow$ Callee Signature & Return-Shape Audit (open target classes/JS files to verify method/action/L10n key existence, argument order, and return shapes across all branches).
   - **Lens 3**: Retired-Code / Cross-Path Parity Table (`git diff <base>...<head>` line-by-line audit in PR mode, or cross-entry-point parity audit in Subsystem mode, checking for dropped guards, security/escaping checks, error handling, state writes, cache resets, or user-context restoration).
   - **Lens 4**: Untouched-Codebase Orphan Reference Sweep (`git grep` across all PHP, JS, template, and test files for dropped tables, removed methods, retired hooks, or renamed options).
   - **Lens 5**: Prior-Fix Falsification, Adversarial Self-Check & Unified Diff Patch Synthesis (probe edge cases around prior fix commits, filter false positives via magic methods/inheritance/DI bindings, and draft `diff --git` patches for every confirmed defect).

2. **Adversarial Verification & Challenger Pass**:
   - Independently verify every candidate defect and matrix verdict against the source files (`file:line`).
   - Downgrade or record under Section 2.5 ("Falsified / Downgraded Candidate Hypotheses") any claim proven safe by magic methods, parent classes, or container bindings.
   - Verify that every suggested unified diff patch is syntactically sound and fixes the root cause without introducing regressions.

3. **Report Synthesis & Read-Only Gate**:
   - Write the complete report to `.artifacts/matrix-review/<TARGET_SLUG>_CODE_REVIEW_REPORT.md` following `.agents/skills/matrix-review/resources/report_template.md`.
   - Verify `git status --short`, `git diff`, and `git diff --cached` confirm zero tracked files were modified and no untracked artifacts were left in the repository root.
   - Return the executive summary, PR comment verification verdicts (if applicable), severity-ranked defects (`Critical`, `High`, `Medium`, `Low`), and all matrix scenario verdicts to the user.
   - **STOP and ask the user** which severity tiers (`Critical`, `High`, `Medium`, `Low`), specific defect IDs, or uncovered matrix test cases they would like to apply/implement in Phase 3.

## Phase 3 — Remediation & Matrix Test Implementation (Optional — Approval Gate)

Only execute Phase 3 after the user explicitly selects and approves fixes or tests from the Phase 2 report:
1. Apply the approved unified diff patches from Section 2 of `.artifacts/matrix-review/<TARGET_SLUG>_CODE_REVIEW_REPORT.md` following `AGENTS.md`.
2. Add or update PHPUnit tests in `ai-post-scheduler/tests/` for the fixed defects and approved matrix coverage gaps.
3. Run `php -l` on all modified PHP files and run targeted PHPUnit suites in one invocation via `make test-session ARGS="..."` per `TESTING.md`.
