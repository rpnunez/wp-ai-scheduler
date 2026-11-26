---
name: matrix-review
description: Perform a feature-agnostic, two-phase deep code review of any wp-ai-scheduler PR, branch, or subsystem by first building a Tier A/B/C Mechanism Map and a concrete Boundary & Scenario Test Matrix (pausing for user approval), and then tracing every scenario across call chains, retired-code parity, orphan references, and prior-fix falsification with unified diff fix patches.
---

# Matrix Review

Use this skill for an in-depth, execution-traced code review of any `wp-ai-scheduler` pull request, branch, or feature subsystem (e.g., Admin UI/JS/Templates, AJAX controllers, AI prompt & generation pipelines, internal linking/silos, database schema/migrations, background jobs/cron, sources, or notifications).

Unlike static diff reviews that inspect changed hunks in isolation, `matrix-review` operates in **two human-gated phases**:
1. **Phase 1 (System Discovery & Test Matrix Generation — Approval Required)**: Maps how the target feature is triggered, wired, and consumed across the entire codebase (Tiers A, B, and C) and constructs a scope-proportional scenario test matrix across boundary values, configurations, failure modes, and lifecycle transitions—then **pauses for user approval**.
2. **Phase 2 (Multi-Level Execution Tracing & Defect Synthesis — Read-Only)**: Hand-traces every approved matrix scenario through the actual call chains and math/branch logic (directly or via `/teamwork`), audits retired-code parity and untouched-codebase orphan references, falsifies prior PR fix claims and candidate false positives, and delivers a severity-ranked report (saved to `.artifacts/matrix-review/`) with concrete unified diff patches.

Read `AGENTS.md` and `TESTING.md` before starting.

---

## Supported Review Target Modes

`matrix-review` supports two target modes depending on what the user asks to review:
1. **PR / Branch Diff Mode**: Reviewing a PR or branch against its intended base branch (`<base>...<head>`, typically `main...HEAD`). Includes PR comment/fix-commit verification and a Retired/Refactored Code Parity Audit against `<base>`.
2. **Feature / Subsystem Audit Mode**: Reviewing an existing feature or subsystem on the current branch without a PR diff (e.g., *"Run matrix-review on Internal Links"* or *"Audit the Template cloning and repeater sync flow"*). Skips PR comment digestion, replaces the `<base>...<head>` diff parity check with a **Cross-Entry-Point Consistency & Contract Parity Audit** across all callers of the subsystem, and assigns an **Overall Subsystem Health Verdict** (`HEALTHY`, `NEEDS_HARDENING`, or `BROKEN_CRITICAL_PATHS`).

---

## Phase 1: System Discovery & Scenario Matrix Generation (Approval Required)

Do **not** perform the line-by-line defect review yet in Phase 1. Complete the following four steps and stop for user approval:

### 1. Sync Baseline & Digest Prior Context
- **In PR / Branch Diff Mode**:
  - Resolve the target PR/branch, intended base branch (typically `main`), and exact base and head commit SHAs (`git fetch origin`).
  - Read the PR body, linked issue(s), commit history, and all prior human or AI review comments (e.g., Cursor, Claude, Copilot).
  - Summarize every prior review finding and claimed fix commit as a checklist of hypotheses to verify in Phase 2.
- **In Feature / Subsystem Audit Mode**:
  - Record the current branch and HEAD commit SHA, and inventory all files (controllers, services, repositories, templates, JS assets, tests) belonging to the target feature.

### 2. Build the Feature Mechanism & Caller Map (Tiers A / B / C)
Inventory every way the target feature or refactored subsystem is triggered, processed, and stored across the repository (including files **not** touched by the diff):
- **Tier A — Primary / New Architecture Path**:
  - All entry points (AJAX actions in `AIPS_Ajax_Registry::$map`, admin pages/templates, `assets/js/` modules, WordPress hooks, cron events, or service calls), controllers, services, repositories, and data stores directly implemented, refactored, or owned by the feature.
- **Tier B — Hybrid / Partially Wired Callers (Split-Brain Candidates)**:
  - Secondary callers, fallback flows, or registered constants/actions/hooks/options/handlers/L10n keys that exist in a registry, template, or interface but are never invoked/dispatched, or callers that mix new storage/services with legacy execution paths.
- **Tier C — Untouched Dependents & Adjacent Mechanisms**:
  - Existing services, repositories, admin templates, JS modules, or cron workers outside the primary path that read/write the same database tables, options, post meta keys, transients, or interfaces—plus concrete candidates for future unification.

### 3. Run the Universal Dimension & Boundary Value Extractor
Scan the target subsystem for every quantitative constant, threshold, limit, mode enum, configuration flag, input shape, and lifecycle transition, and build a **grouped Test Scenario Matrix** scaled proportionally to the review scope:
- **Focused / Single-Component Change (1–5 files)**: **10–18 scenarios**
- **Medium Multi-Component Feature (6–20 files)**: **18–30 scenarios**
- **Large Architectural Refactor or Full Subsystem Audit (20+ files)**: **25–40+ scenarios**

Ensure the matrix covers these four universal dimensions:

1. **The 5 Failure-Prone Quantitative Boundaries**:
   - **Min / Empty**: `0` items, `1` item, empty arrays/strings, or `null`/omitted optional request parameters.
   - **Just Below Threshold**: `threshold - 1` (e.g., largest count that takes an inline, single-page, or unbatched branch).
   - **Exact Threshold**: `threshold` (e.g., first count that triggers splitting, pagination, truncation, or queuing).
   - **Multi-Item / Mid-Range**: `threshold < N < max_cap` combined with distinct domain permutations (e.g., different template/topic contexts, entity scopes, post types, frequencies, or UI filter states).
   - **Cap Overflow & Non-Divisible Remainders**: `N > max_cap` where `N % chunk_size != 0` (e.g., testing whether ceiling division, pagination, or slice clamping produces out-of-bounds cursors, negative sizes, or dropped items), SQL `LIMIT` starvation, or rate-limit/quota exhaustion.
2. **Configuration, Mode & Input-Shape Permutations**:
   - Feature flags enabled vs. disabled, global kill-switches (e.g., `"Prevent AI Generation"`), missing dependencies (Meow Apps AI Engine inactive), mode enums (`dry_run` vs. `apply`, `manual` vs. `scheduled`, `hard` vs. `soft`, `missing` vs. `all`).
   - WordPress/JS boundary inputs: boolean strings from jQuery (`"false"` vs. `filter_var($val, FILTER_VALIDATE_BOOLEAN)`), sparse array keys after `array_filter()`, unescaped HTML/quotes in titles or templates (`AIPS.Templates.render()` vs. `renderRaw()`), and calendar boundaries (`Jan 31 + 1 month`, leap day, timezone conversion via `AIPS_DateTime`).
3. **Lifecycle, Concurrency, Relational Sync & Failure Paths**:
   - Concurrent/overlapping requests (atomic DB locks, double-claim prevention, stale lock reclaim, duplicate start prevention).
   - Mid-execution pause, resume, cancel, or time/memory budget yield and subsequent resumption.
   - Partial failures (`WP_Error` or thrown `Throwable` on item $k$ of $N$) vs. terminal failure.
   - Entity lifecycle invariants: atomic group sync purging removed repeater/collection child records, and entity clone actions duplicating all child/relational mappings.
4. **Upgrade, Migration & Backward-Compatibility Paths**:
   - Upgrading from a prior plugin version (`AIPS_DB_Migrations`) with in-flight rows, missing/already-dropped tables, or legacy options/hooks still present.

### 4. Phase 1 Checkpoint Format — STOP and Wait for User Approval
Present the Phase 1 output using this structured 3-part checkpoint:
1. **Baseline & Prior Review Comment Digestion** (commit range and table/list of prior review findings & fix commits to verify, if applicable).
2. **Feature Mechanism & Caller Map (Tiers A / B / C)** (with clickable `file:line` links and any initial architectural disconnects spotted).
3. **Master List of Test Cases** (grouped Markdown tables with columns: `Test ID`, `Scenario`, `Inputs / Conditions`, and `Expected Execution Path & Invariants to Verify`, using clear domain prefixes such as `S-01`, `UI-01`, `AJAX-01`, `GEN-01`, `DB-01`, `B-01`, `M-01`, `J-01`).

**STOP and wait for explicit user approval** (or additions/adjustments to the test cases) before running Phase 2.
*(Exception: Only skip this pause if the user explicitly passed `[skip-matrix-approval]` or asked for a single-turn autonomous review).*

---

## Phase 2: Multi-Level Execution Tracing & Defect Synthesis

Once the user approves the test matrix:
- **If the user invokes `/teamwork`**, hand the approved **PR Comment Summary + Tier A/B/C Mechanism Map + Test Scenario Matrix + Phase 2 Requirements** directly to the `teamwork` subagent.
- **Otherwise**, execute Phase 2 directly (using parallel read-only subagents where helpful).

### Strict Read-Only Rule
- Phase 2 is a **strictly read-only review**: do **not** modify any existing tracked repository source, config, or test files (`git diff` and `git diff --cached` must remain empty).
- Save the generated Markdown review report inside `.artifacts/matrix-review/` (which is gitignored so `git status --short` remains clean), unless the user explicitly requests a different path.
- Never delegate the review verdict to automated linters (`php -l`) alone; every finding and matrix verdict must come from direct manual code inspection and symbolic execution tracing.

### The 5 Mandatory Execution-Trace Lenses

Apply all five lenses across the target code and every scenario in the approved matrix:

#### Lens 1: Symbolic Execution & Math/Loop/State Boundary Trace
- For each scenario in the matrix, trace the exact entry point and step through loops, conditionals, cursor/pagination updates, template rendering, and math formulas using the concrete values from the scenario (e.g., substituting `N = 35, max_slices = 10`, `cursor = 0`, `filter_var("false", FILTER_VALIDATE_BOOLEAN)`, or sparse array `[1 => 42, 3 => 99]`).
- Verify loop termination, off-by-one conditions, final-batch partial yield conditions, and whether state variables (like `$overall_result`, status flags, `cursor_id`, request caches, or `wp_set_current_user()`) leak or get overwritten across iterations.

#### Lens 2: Strict Caller $\leftrightarrow$ Callee Signature & Return-Shape Audit
- Never assume a method called on a collaborator exists or returns what the caller expects. For every cross-class method call, container lookup (`AIPS_Container::get_instance()->make(...)`), interface invocation, and JS $\leftrightarrow$ PHP AJAX contract in the target code:
  1. Open the target class/interface/JS file and confirm the **exact method, action, or L10n key exists** (e.g., catching `get_id()` vs. `get_job_id()`, `stop_backfill()` vs. `cancel_backfill()`, missing `AIPS_Ajax_Registry::$map` entries, or mismatched `$_POST` parameter names).
  2. Verify **argument count, order, and types** against the callee's signature.
  3. Verify **return types and shapes across all callee branches** (`void` vs. `bool`, `null` vs. `object`, `WP_Error`, or an associative summary array mistaken for a list of integer IDs).

#### Lens 3: Retired-Code / Cross-Path Parity Audit
- **In PR / Branch Diff Mode (`<base>` vs. `<head>`)**:
  - Whenever a PR deletes, extracts, or replaces existing classes or methods (`git diff <base>...<head>`), compare the deleted code in `<base>` line-by-line against the new code in `<head>`.
- **In Feature / Subsystem Audit Mode**:
  - Compare parallel entry points of the feature (e.g., manual UI "Run Now" vs. scheduled cron run vs. bulk run; or create vs. update vs. clone) line-by-line for consistency.
- Explicitly check for silently dropped safeguards:
  - Security & WordPress hygiene: `check_ajax_referer(..., 'nonce', false)`, `current_user_can('manage_options')`, output escaping (`esc_html`, `esc_attr`, `esc_url`, `wp_kses_post`), no raw `$wpdb` outside repositories.
  - Feature-flag, kill-switch, or dependency checks (`is_scheduled_ai_generation_prevented()`, graceful handling when Meow Apps AI Engine is missing).
  - Error handling (`is_wp_error()` loop breaks, exception rethrows vs. swallowed exceptions, protecting `once` entities or active state on failure).
  - State persistence (resume cursors, status resets, `AIPS_History_Service` / `AIPS_Logger` lifecycle recording, atomic repeater group sync, relational clone parity).
  - Request hygiene (`AIPS_Cache::reset_request_cache()`, `wp_reset_postdata()` in `finally`, restoring `wp_set_current_user()`).

#### Lens 4: Untouched-Codebase Orphan Reference Sweep (`git grep`)
- For every dropped database table, deleted class, removed method, retired cron/action hook, or renamed option/meta key, run `git grep` across the **entire repository** (`ai-post-scheduler/includes/`, `templates/`, `assets/js/`, `tests/`).
- Flag any untouched production class, template, JS file, or test that still queries a dropped table, calls a removed method, or references a retired hook/key.

#### Lens 5: Prior-Fix Falsification, Adversarial Self-Check & Unified Diff Patches
- **Prior-Fix Verification**: Verify every item from prior PR review comments and fix commits directly in the code and assign an explicit verdict: **`Verified Fixed`**, **`Partially Fixed`**, or **`Unresolved / Regression`**. Actively probe boundary conditions *around* each prior fix (e.g., if a race condition was fixed on the happy path, check what happens in the `catch (Throwable)` path; if an upgrade migration was added, check what happens when the first legacy table is already absent).
- **Adversarial Self-Falsification Gate (False-Positive Filter)**: Before promoting any candidate finding to `Critical` or `High`, trace inheritance chains, PHP magic methods (`__get`, `__isset`, `__call`), DI container bindings, and WordPress filter defaults to verify the bug is genuinely reachable. Document any falsified or downgraded hypotheses in the report.
- **Concrete Unified Diff Patches**: For **every confirmed defect**, author a concrete, minimal **unified diff patch (`diff --git`)** showing the exact fix in the report.

### Focused Verification (When Requested or Applicable)
- Run fast syntax checks (`php -l`) on touched PHP files when host PHP is available.
- If the user requests running PHPUnit tests, follow `TESTING.md` ("Agent test protocol") strictly: `make test-session ARGS="tests/Test_A.php tests/Test_B.php"` in a single invocation, never run `./start-dev.sh` just to test, and disclose exact executed vs. unavailable suites.

---

## Deliverable Format

1. Save the complete structured review report to `.artifacts/matrix-review/<TARGET_SLUG>_CODE_REVIEW_REPORT.md` (where `<TARGET_SLUG>` identifies the branch, PR, or feature subsystem reviewed, e.g., `retire-legacy-background-callers_CODE_REVIEW_REPORT.md` or `internal-links_CODE_REVIEW_REPORT.md`) following `.agents/skills/matrix-review/resources/report_template.md`.
2. Return a clear, structured summary in the final response containing:
   - Clickable link to the saved `.artifacts/matrix-review/...` report file, Overall Verdict (`APPROVE`, `APPROVE_WITH_NITS`, or `REQUEST_CHANGES` for PRs; `HEALTHY`, `NEEDS_HARDENING`, or `BROKEN_CRITICAL_PATHS` for subsystem audits), and Read-Only integrity confirmation (`git status --short` / `git diff` clean).
   - PR Comment & Commit Verification Summary table (when reviewing a PR).
   - Severity-Ranked Defect Summary (`Critical`, `High`, `Medium`, `Low`) with exact `file:line` citations.
   - All Matrix Scenario Verdicts (`PASS`, `PARTIAL / DEFECT`, `FAIL / DEFECT`, `FAIL / CRITICAL FATAL`) at a glance.
3. **STOP and Prompt for Optional Phase 3 (Remediation & Test Implementation)**:
   - After delivering the Phase 2 report, ask the user which severity tiers (`Critical`, `High`, `Medium`, `Low`), specific defect IDs (`[C-01]`, `[H-01]`, etc.), or uncovered matrix test scenarios they would like to apply/implement next.
   - Do **not** modify any repository files unless and until the user explicitly approves Phase 3.

---

## Phase 3: Remediation & Matrix Test Implementation (Optional — User Approval Required)

When the user approves applying all or a selected subset of the Phase 2 findings:
1. **Apply Approved Fix Patches**:
   - Apply the unified diff patches from Section 2 of the report for the user-selected defects, adhering strictly to `AGENTS.md` conventions (tabs, `array()` syntax, `AIPS_` prefix, DI container usage, repository SQL isolation).
2. **Add / Update PHPUnit Coverage for Matrix Gaps**:
   - When requested or approved by the user, add or update PHPUnit tests in `ai-post-scheduler/tests/` covering the fixed defects and uncovered matrix scenarios identified in Section 5 of the report.
3. **Verify Fixes & Clean Up**:
   - Run `php -l` on all touched PHP files.
   - Run targeted PHPUnit tests following `TESTING.md` ("Agent test protocol"): `make test-session ARGS="tests/Test_A.php tests/Test_B.php"` in a single invocation, stopping any containers started.
   - Report the applied fixes, test execution results, and remaining open items (if any).
