# Abilities integration: follow-up plan

Status of PR #1877 after the background-process refactor, the workflow editing UI,
typed inputs and the live checks, and the plan for what is left, including the
Ability stress tests for **Diagnostics → Stress Test**.

## 1. What changed in this pass

| Area | Result |
|---|---|
| Workflow editing UI | Workflow settings (name, description, status, max steps, max runtime, *Allow destructive abilities*) are editable from the list (**Edit settings**) and from the builder (**Workflow settings** link). The dead `log_payloads` option and the non-functional *Scheduled* trigger were removed; status and settings are validated and bounded server-side. Reactivating is a status change. |
| Typed inputs | The input map has a per-field type (Text, Number, True/false, JSON list/object). References such as `{{steps.a.output.count}}` keep their runtime type. Invalid numbers/booleans/JSON are rejected before the step is saved. |
| Builder safety | Unsaved-changes badge, `beforeunload` guard and a confirmation before *Run Now* (which runs the last **saved** version). |
| Step picker | Abilities that may change data, or are unavailable, are marked; the selected ability's description and declared input fields (type, required) are shown. |
| Background processes | Workflow execution (`ability_workflows`) and post-generation enrichment (`ability_enrichment`) are managed background processes (`AIPS_Managed_Background_Process`). The bespoke `aips_run_ability_workflow` cron chain and the option-based continuation scheduling are gone; generation only queues a post. Pause / stop / the watchdog tick / AI-call metering and the Diagnostics → Background Processes rows come from the shared framework. |
| Live checks | Core abilities and the real WordPress AI plugin abilities were exercised against WordPress 7.1.3 and Google (see §3). |

## 2. Architecture after the refactor

```
Run Now ──► executor->dispatch_run()  ── creates run (queued) ─► AIPS_Ability_Workflows_Process::kick()
                                                                         │ start() or ensure_scheduled()
                                                                         ▼
                                   aips_bg_process_tick (shared cron event, lock, watchdog, heartbeat)
                                                                         ▼
                              process_slice(): for each open run (oldest first)
                                   executor->process_run($run_id)  → { finished | continue(retry_at) | busy }

aips_post_generated ─► enrichment service: enqueue post (post meta) + kick() ─► AIPS_Ability_Enrichment_Process
                              process_slice(): enrich 2 posts per slice (alt text, classification) as the triggering user
```

* One History row per workflow run, reused across slices and closed once.
* Backoff is stored on the parked step run (`error.next_retry_at`); the executor reports `continue(retry_at)` and the process sleeps until the earliest retry.
* A run lock (atomic claim, compare-and-swap takeover, refreshed per step) still guards `process_run()` against concurrent callers; the tick lock already prevents overlapping slices.
* Stopping a process leaves queued runs/posts queued; starting it (or the next `kick()`) resumes them.

## 3. Live verification (this pass)

Run against WordPress 7.1.3 (test library) with the user's installed `ai` 1.3.0 and `ai-provider-for-google` 1.1.1 plugins, and a Google API key from the environment. Only reads the plugins; all writes go to the disposable test database.

| Check | Result |
|---|---|
| Core abilities listed through the adapter and catalog (`core/get-site-info`, `get-environment-info`, `get-user-info`) | Pass: real labels, schemas, `readonly` annotations; no approval needed |
| Input-less invocation (empty payload) | Pass. WordPress 7.1.3 core abilities all declare a schema, so the `null` conversion is proven with a real registered schema-less ability: `validate_input([])` fails, `validate_input(null)` passes, and the adapter succeeds |
| Permission model | Pass: a subscriber is refused `core/get-environment-info`; the adapter does not bypass it |
| Real workflow, two core steps with a condition on step one's output | Pass: both steps completed, History closed |
| `ai/title-generation` through the adapter | Pass: returns `{"title": "..."}` |
| Workflow `ai/title-generation` → `ai/summarization` chaining `{{steps.title.output.title}}` | Pass: both steps completed with real model output |
| `ai/alt-text-generation` on a real PNG | Pass: the ability answered; on a plain red square it returned `is_decorative` and the step correctly skipped (and wrote alt text on a second run) |
| `ai/content-classification` with existing terms | Pass: assigned the relevant existing tag/category, did not assign unrelated ones |
| Enrichment background process end to end (3 ability calls) | Pass once `no_results` was treated as benign (see below) |

Real-world findings fixed because of the live run:

* `ai/content-classification` answers `no_results` when the site has no matching existing term. That is not a failure; it is now reported as `skipped:no_suggestions` and no longer marks the post failed in the process.
* The WordPress test harness unhooks core's ability registration and `init` reads as not fired inside a test; the live tests restore both explicitly (documented in `Test_AIPS_Ability_Live_Core`).

Running the live checks yourself:

```bash
# Core abilities only (no network)
cd ai-post-scheduler && vendor/bin/phpunit --configuration phpunit.xml tests/Test_AIPS_Ability_Live_Core.php

# Real AI abilities and provider (calls Google; a few cents)
AIPS_LIVE_PLUGINS_DIR=/path/to/wp-content/plugins GOOGLE_API_KEY=... \
  vendor/bin/phpunit --configuration phpunit.xml tests/Test_AIPS_Ability_Live_AI.php
```

The AI group skips itself unless both variables are set.

## 4. Plan for the remaining gaps

| # | Gap | Plan | Size |
|---|---|---|---|
| G1 (done) | **Runs stuck without a live process.** The watchdog covers a slice that dies, but a run can still sit `queued`/`running` if its process was stopped, or the run's workflow vanished while the process is idle. | (a) Diagnostics → Background Processes already shows the process; add the open-run count to the row message. (b) Add `AIPS_Ability_Workflow_Repository::get_stale_runs( $older_than )` and, in the daily `aips_cleanup_bulk_batch_jobs` hook, fail runs `running` with no step activity for `aips_ability_workflow_stale_after` (default 6 h) and close their History row. (c) `kick()` on every admin page load of the Workflows list when open runs exist and the process is idle (cheap, uses the heartbeat). | S |
| G2 (done) | **Live checks are manual.** | Keep the `abilities-live-*` test groups opt-in. Add a documented `make test-live-abilities` target that forwards `GOOGLE_API_KEY` and `AIPS_LIVE_PLUGINS_DIR`; add a scheduled (weekly) GitHub Actions job using a repository secret and the two plugins from wordpress.org, allowed to fail without blocking merges. Extend the live group with the remaining `ai/*` abilities and `yoast-seo/*` once their schemas are read (see G8). | M |
| G3 (done) | **Step picker is not fully schema-driven.** Hints are shown, but nothing validates `input_map` against the ability's `input_schema`. | Validator (with a catalog): reject unknown keys when `additionalProperties` is false, flag missing required fields, and type-check literal (non-token) values against `type`/`enum`/`minimum`. In the builder, add *Add required fields* (pre-fills the table from the schema) and render `enum` as a select. Tokens are never type-checked at save time. | M |
| G4 (done) | **Hard-coded English** in `AIPS_Ability_Workflow_Document_Validator` messages, executor History messages, and PHP `sprintf` strings without `__()`. | Wrap every user-visible string in `__()` with translators comments, keep error *codes* untranslated, run the `l10n-reviewer` agent, and add a test that scans these classes for untranslated `sprintf('`/`'Step "` patterns (modelled on the existing creation-method scan tests). | S |
| G5 (done) | **Atomic `save_steps` is only tested through the savepoint path** because the WordPress harness keeps a transaction open. | Add a `@group no-transaction` test class that commits the harness transaction in `setUp`, runs `save_steps` with a sabotaged second insert through the `query` filter, asserts the previous steps are restored by the real `START TRANSACTION`/`ROLLBACK`, and deletes everything it created in `tearDown`. | S |
| G6 (done) | **Run and step payloads are never pruned.** Step inputs and outputs (longtext) accumulate. | Settings: `aips_ability_workflow_retention_days` (default 30, 0 = keep). Extend the daily cleanup to delete terminal runs and their step runs older than that (repository methods, batched), cap each stored snapshot at 256 KB with a `_truncated` marker, and add a `aips_ability_workflow_redact_payload` filter applied before persisting. Document that payloads are stored. | M |
| G7 (done) | **PR hygiene.** | Update the PR description (done with each push), keep this plan, `docs/PR_1877_REVIEW_PLAN.md` was deleted before merge so it does not land on main, and add a CHANGELOG line for each shipped slice. | XS |
| G8 (done, unverified against real Yoast) | **Yoast quality-gate step.** | Read `yoast-seo/get-seo-scores` / `get-readability-scores` schemas from a live site, then add `seo_scores` and `readability_scores` enrichment steps that **record** scores (post meta + History) and optionally **flag** posts below `aips_ability_enrichment_min_seo_score` through the existing Pending Review flow. No automatic regeneration. | M |
| G9 (done) | **Enrichment edge cases.** A post queued between a slice's final empty check and the run completing waits for the next `kick()`. | The next generation or heartbeat `kick()` restarts it; add a Heartbeat re-kick when the queue is non-empty and no run is open, with a test. | XS |

**Done since the last update:** G9 (managed ability processes now start a run on Heartbeat, and when the workflow list opens, if items are waiting and none is open; paused runs stay paused), G1 (runs record `created_at`; the daily cleanup fails runs open with no activity for 6 hours, filter `aips_ability_workflow_stale_after`, skipped while paused or while a worker holds the run, and closes their History row) and G5 (`@group no-transaction` tests run the real `START TRANSACTION` / `ROLLBACK` path with autocommit on). Schema: `aips_ability_workflow_runs.created_at`, version 3.7.21.

New finding from the live run:

| # | Gap | Plan | Size |
|---|---|---|---|
| G10 (done) | **Every `ai/*` ability needs "Allow destructive abilities".** The WordPress AI plugin registers its abilities without `readonly`/`destructive` annotations (the live discovery case reports 5 of 8 abilities with none), so the conservative gate treats them as potentially destructive and a workflow can only use them with the all-or-nothing setting enabled. | Replace the all-or-nothing flag with a per-workflow list of **approved abilities** (`settings.approved_abilities`, chosen in the builder from the ones the workflow actually uses). Keep *Allow destructive abilities* for the rare "anything goes" case. The gate lets an unannotated ability run when it is in the workflow approved list, never one annotated `destructive: true`. Add a Stress Test case for the new gate matrix. | M |

**Done in the latest round:** G10 (per-workflow `approved_abilities`, a checkbox on the step row, never covers a destructive-declared ability), G4 (validator messages translated; `Test_AIPS_Ability_Strings_Localized` fails on any untranslated sentence in the ability classes), G3 (step input checked against `input_schema`: required, forbidden, type, enum; builder *Add Required Fields* and enum suggestions) and G6 (256 KB snapshot cap, `aips_ability_workflow_redact_payload`, retention setting and daily pruning).

**Latest round:** G2 (`make test-live-abilities`, `scripts/run-live-abilities.sh`, weekly `live-abilities.yml`; the AI group downloads the `ai` and `ai-provider-for-google` plugins into the container), G8 (steps `seo_score` and `readability` with the two threshold settings; written against assumed Yoast shapes because Yoast was not installable here, so read the real schemas on a site with Yoast and extend `AIPS_Ability_Enrichment_Service::extract_score()` and the live group if they differ), Stress Test slices C and D (`lock_and_takeover`, `workflow_concurrency`, `enrichment_queue`, `process_soak`, `large_payload`). The soak drives the executor and enrichment service directly, not the managed processes, which would also advance real queued work. Export/diff support for the new metrics needed no change: results are exported as they are. These last changes were written without running the tests, as requested.

Nothing is left in this plan except verifying G8 against real Yoast.

## 5. Plan: Ability stress tests (Diagnostics → Stress Test)

### 5.1 How the Stress Test tab works today

* `AIPS_Stress_Test_Service::get_cases()` returns case definitions (`id`, `label`, `description`, `group`, `creates`). Groups today: `components`, `pipeline`, `integrations`.
* `run( $case_id )` dispatches to a `case_*` method and returns a normalized result (status, duration, AI calls, details); `AIPS_Stress_Test_Controller` exposes `aips_stress_test_run|cleanup|status|save_run|get_history|get_run|diff_runs`; `assets/js/admin-stress-test.js` queues the selected cases.
* Test data is private and marked (`_aips_stress_test` post meta, `aips_stress_cpt`), counted by `count_test_data()` and removed by `cleanup_test_data()`.
* Runs can be saved to history and diffed.

### 5.2 New group: `abilities`

Shown only when the Abilities API is available (`function_exists( 'wp_get_abilities' )`) and, for cases that create workflows or run processes, only when **Ability Workflows** / **Ability Enrichment** are enabled (otherwise the case is listed disabled with the reason). Cases never call destructive abilities: any case that needs a failing, slow, unannotated or destructive ability injects a **fixture provider** through the `aips_ability_provider` filter for the duration of the case, so the site's real abilities are never altered.

| Case id | Creates data | What it does | Pass criteria / reported metrics |
|---|---|---|---|
| `ability_discovery` | no | Lists the registry through `AIPS_Ability_Service`/catalog | Provider name; abilities by provider and category; count of abilities needing approval; discovery time; core abilities present |
| `ability_invoke_core` | no | Invokes `core/get-site-info` 20 times, then `core/get-environment-info` once | 0 errors; p50/p95 latency; permission result for the current user |
| `ability_invoke_ai` | no | One real call each to `ai/title-generation` and (if present) `ai/summarization` | Output shape matches the output schema; latency; counted as AI calls; reported *skipped* (not failed) when the ability is not installed |
| `ability_destructive_gate` | no | Fixture abilities with `destructive` true / false / unannotated / read-only run through a one-step workflow, with and without *Allow destructive abilities* | Each combination blocked or allowed exactly as the catalog's `requires_approval` says; error code `ability_workflow_destructive_forbidden` for blocked ones |
| `workflow_two_step` | workflow + run (flagged) | Creates a temporary 2-step workflow (core info → second step that references `{{steps.<key>.output.name}}`), dispatches it and drives the process until finished | Both steps completed; reference resolved with its type; exactly one History row, closed `completed`; total and per-step time |
| `workflow_retry_backoff` | workflow + run | Fixture ability that fails once with a transient code, `retry_policy` attempts 2, backoff 1 s | Step parked `pending` with `next_retry_at`, not retried early, retried once due, run completes; one History row |
| `workflow_cancel` | workflow + run | Three-step workflow; cancels while step 1 runs | Step 2 never runs; run `cancelled`; History `terminated` |
| `workflow_concurrency` | N flagged runs | Queues 10 runs, drives `AIPS_Ability_Workflows_Process` ticks, and simulates a second worker holding one run's lock | All runs terminal; no duplicate step runs; busy run reported `busy` then finished after release; slices needed and runs per second |
| `enrichment_queue` | 5 private posts (+ attachments) | Queues the posts, drives `AIPS_Ability_Enrichment_Process`; real `ai/*` abilities when installed, otherwise a fixture | Queue empty; each post tried once; principal restored; failed vs processed; ability calls per post |
| `process_soak` | flagged runs and posts | Fixture abilities, 100 workflow runs + 50 queued posts through both processes | Both background runs end `completed`; metered AI calls equal the fixture's call count; no process left `running`; watchdog tick present while running and cleared after |
| `lock_and_takeover` | no | Lock contention, expired-lock takeover and the compare-and-swap loser | Exactly one winner in each scenario |
| `large_payload` | workflow + run | Step returning a 1 MB array | Snapshot persisted (or truncated with a marker once G6 lands); later steps can still read the field they need |

All cases return the existing result shape plus `ability_calls` (delta of `AIPS_Ability_Service::get_invocation_count()`), so the AI-call column keeps working.

### 5.3 Data safety and cleanup

* Workflows created by a case are named `[aips-stress] <case>` and carry `settings.stress_test = true`; posts use the existing `_aips_stress_test` meta; queued posts use the standard queue meta.
* `count_test_data()` also counts stress workflows; `cleanup_test_data()` deletes them **with their runs and step runs** (new `AIPS_Ability_Workflow_Repository::delete_workflow_with_runs()`; normal deletion keeps runs for audit) and dequeues leftover enrichment posts.
* Each case wraps its work in `try/finally` so a failure still removes the fixture provider filter, restores the current user and releases locks.
* Cases that call real AI honour the existing per-run budget and report their call count before and after.

### 5.4 Implementation slices

1. **Slice A (no data), done:** `AIPS_Stress_Test_Ability_Cases` (`ability_discovery`, `ability_invoke_core`, `ability_invoke_ai`, `ability_destructive_gate`), wired into `AIPS_Stress_Test_Service` (`get_cases()`, `run()`, a per-case `needs_ai` flag so these cases run without an AIPS AI provider, and `ability_calls` in the result). The executor's gate is now a public `get_approval_error()` that both the executor and the stress case use. Tests: 20 with a fixture provider plus live variants against real core abilities and the real AI abilities. The existing Stress Test table lists cases flat, so the new cases are labelled `Abilities: ...` instead of adding group headings.
2. **Slice B (workflows), done:** `workflow_two_step`, `workflow_retry_backoff`, `workflow_cancel` (fixture abilities, each case removes its workflow, runs and History rows in a `finally`), `delete_workflow_with_runs()`, and leftover count/cleanup in the Stress Test service. The workflows are named `[aips-stress] <case>`; the cleanup button also removes ones a dead request left behind.
3. **Slice C (processes):** `workflow_concurrency`, `enrichment_queue`, `process_soak`, `lock_and_takeover`.
4. **Slice D:** `large_payload` (G6 has landed, so it can now check the truncation marker), export/diff support for the new metrics, docs and CHANGELOG.

Each slice follows the repo conventions: controller untouched except for the group label (cases are data), SQL only in repositories, translatable strings, `Test_AIPS_Stress_Test_Service` extended per case, and the full suite compared against `main`.
