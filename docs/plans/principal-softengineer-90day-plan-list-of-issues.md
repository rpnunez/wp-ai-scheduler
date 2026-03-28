# Principal Software Engineer 90-Day Plan - Draft Issue Backlog

## Purpose

This document drafts a set of GitHub Issues to manage the 90-day refactor roadmap explicitly. The issues are grouped by track and written so they can be copied into GitHub with minimal editing.

## Suggested Labels

- `architecture`
- `scheduler`
- `generation`
- `frontend`
- `observability`
- `tech-debt`
- `risk-control`
- `phase-1`
- `phase-2`
- `phase-3`
- `high-priority`

## Suggested Milestone Names

- `PSE 90-Day Plan - Phase 1`
- `PSE 90-Day Plan - Phase 2`
- `PSE 90-Day Plan - Phase 3`

## Architecture Track

### Issue 1

**Title**

Architecture: define canonical generation boundary and legacy adapter strategy

**Labels**

- `architecture`
- `tech-debt`
- `phase-1`
- `high-priority`

**Problem**

Generation-related classes still carry inline branching for legacy template-based inputs and newer context-based inputs. This increases complexity, test scope, and refactor risk.

**Scope**

- Inventory current legacy entrypoints.
- Define the canonical internal generation input model.
- Define one edge adapter strategy for legacy callers.
- Record decisions in an ADR or equivalent planning artifact.

**Acceptance Criteria**

- Legacy entrypoints are enumerated.
- Canonical internal input model is documented.
- Adapter ownership and cut lines are explicit.
- Follow-on implementation issues reference this design.

**Risk Controls**

- Do not change runtime behavior in this issue.
- Limit scope to decision-making and boundaries.

### Issue 2

**Title**

Architecture: complete repository migration for high-value controller flows

**Labels**

- `architecture`
- `tech-debt`
- `phase-2`

**Problem**

Some controller flows still mix request handling with persistence behavior, which reduces testability and keeps business logic coupled to storage details.

**Scope**

- Audit high-value controllers.
- Replace remaining direct persistence calls with repository-backed service methods.
- Add tests proving delegation behavior.

**Acceptance Criteria**

- High-value controllers no longer perform direct persistence for targeted flows.
- Repository contracts cover targeted operations.
- Tests assert controller-to-service-to-repository boundaries.

**Risk Controls**

- Prioritize schedule, generation, and author-topic flows first.
- Avoid opportunistic migration of unrelated low-value code.

### Issue 3

**Title**

Architecture: start PSR-4 strangler migration for new refactor modules

**Labels**

- `architecture`
- `tech-debt`
- `phase-3`

**Problem**

The codebase still relies on a flat include structure and custom autoloading. Continuing to place new refactor code there will compound long-term maintenance costs.

**Scope**

- Define the placement rules for new refactor modules.
- Add compatibility bridges as needed.
- Move only new or actively rewritten modules to the new structure.

**Acceptance Criteria**

- At least one new module family uses the new structure.
- Existing runtime remains stable.
- A migration checklist for later waves exists.

**Risk Controls**

- No mass renaming of stable modules in this issue.
- Preserve backward compatibility at entrypoints.

## Scheduler Track

### Issue 4

**Title**

Scheduler: introduce queue-backed generation jobs with idempotency keys

**Labels**

- `scheduler`
- `tech-debt`
- `phase-2`
- `high-priority`

**Problem**

Current cron-driven scheduling risks duplicate work, poor batching, and unbounded execution when multiple schedules are due or cron overlaps.

**Scope**

- Add queue storage or queue abstraction for generation jobs.
- Include idempotency key, lock token, attempts, and scheduled availability.
- Adjust cron flow to enqueue rather than fully execute all work inline.

**Acceptance Criteria**

- Due schedule work is represented as queue jobs.
- Jobs cannot be duplicated for the same schedule occurrence under normal overlap conditions.
- Batch worker size is bounded and configurable.

**Risk Controls**

- Keep feature gated during rollout.
- Add replay and overlap tests before production enablement.

### Issue 5

**Title**

Scheduler: implement worker locking, pacing, and bounded retries

**Labels**

- `scheduler`
- `risk-control`
- `phase-2`

**Problem**

Without a worker discipline, queue adoption alone will not prevent API spikes, stuck work, or repeated duplicate processing attempts.

**Scope**

- Add lock acquisition and expiration rules.
- Add pacing controls for worker batches.
- Add bounded retry behavior and terminal failure handling.

**Acceptance Criteria**

- Only one worker can claim the same job at a time.
- Retry behavior is bounded and observable.
- Worker pacing prevents bursty over-execution.

**Risk Controls**

- Keep conservative defaults.
- Expose batch size and retry limits through config or filters.

### Issue 6

**Title**

Scheduler: add missed-run recovery and safe catch-up processing

**Labels**

- `scheduler`
- `phase-3`

**Problem**

Low-traffic WordPress environments can miss cron windows, causing publishing gaps or forcing unsafe manual catch-up.

**Scope**

- Detect missed schedule windows.
- Create bounded catch-up work.
- Prevent thundering herd behavior when recovering.

**Acceptance Criteria**

- Missed runs are detected.
- Catch-up processing is bounded and paced.
- Recovery does not create unsafe backlog spikes.

**Risk Controls**

- Add hard limits on catch-up depth.
- Start with dry-run verification if needed.

## Generation Track

### Issue 7

**Title**

Generation: move core execution to context-only internals via legacy adapter

**Labels**

- `generation`
- `architecture`
- `phase-2`
- `high-priority`

**Problem**

Core generation classes still support two execution styles inline, increasing branching and duplication.

**Scope**

- Implement a legacy-to-context adapter.
- Update generator internals to consume only contexts.
- Remove duplicated inline path handling from the core flow.

**Acceptance Criteria**

- Generator internals use one canonical input type.
- Legacy callers continue to work through the adapter.
- Regression tests cover template and topic generation flows.

**Risk Controls**

- Keep adapter coverage comprehensive before deleting branches.
- Preserve output compatibility.

### Issue 8

**Title**

Generation: implement explicit generation lifecycle states and transitions

**Labels**

- `generation`
- `observability`
- `phase-2`

**Problem**

Generation failures are hard to reason about because progress and failure modes are not represented as an explicit state model.

**Scope**

- Define lifecycle states and transitions.
- Add storage for current state, timestamps, and retry metadata.
- Expose state in history or status surfaces.

**Acceptance Criteria**

- Generation lifecycle states are explicit and test-covered.
- Partial failures are distinguishable from terminal failures.
- Recovery logic can target the correct failed stage.

**Risk Controls**

- Start with append-only state history if needed.
- Avoid overfitting UI decisions to the first state model iteration.

### Issue 9

**Title**

Generation: support partial recovery for content/image split failures

**Labels**

- `generation`
- `phase-3`

**Problem**

If one step in generation fails late, valuable successful work can be discarded rather than recovered or resumed.

**Scope**

- Separate content completion from image completion.
- Mark recoverable failures explicitly.
- Add targeted recovery actions for recoverable states.

**Acceptance Criteria**

- Content can survive image-stage failure when policy allows.
- Recoverable failures are visible to operators.
- Recovery actions are bounded and safe.

**Risk Controls**

- Gate partial persistence behind explicit policy decisions.
- Add cleanup rules for incomplete assets and metadata.

## Frontend Track

### Issue 10

**Title**

Frontend: extract schedule modal controller from admin.js into page-level module

**Labels**

- `frontend`
- `tech-debt`
- `phase-1`
- `high-priority`

**Problem**

Scheduling flows currently rely on duplicated modal reset and fallback logic in the shared admin bootstrap file, making changes fragile and harder to test.

**Scope**

- Extract schedule modal open, edit, populate, submit, and close logic.
- Create shared state helpers for reset and error handling.
- Preserve existing selectors and localized payloads.

**Acceptance Criteria**

- Schedule create and edit use one controller path.
- Duplicated modal reset logic is removed from the shared flow.
- Manual QA passes for key schedule actions.

**Risk Controls**

- Keep compatibility path until the new module is stable.
- Do not redesign the UI in the same issue.

### Issue 11

**Title**

Frontend: standardize admin action state handling for loading, error, and success states

**Labels**

- `frontend`
- `phase-2`

**Problem**

Admin actions use inconsistent state handling patterns, which leads to duplicate logic and uneven user feedback.

**Scope**

- Define reusable loading and error conventions.
- Add shared helper functions or lightweight UI primitives.
- Apply to at least scheduling and one additional admin page.

**Acceptance Criteria**

- At least two admin areas share the same action-state pattern.
- Error and loading behavior is consistent for targeted pages.
- Duplicated close and reset logic is reduced.

**Risk Controls**

- Keep the helper surface small.
- Avoid introducing a frontend framework during the roadmap.

### Issue 12

**Title**

Frontend: plan and execute second admin page extraction after scheduling stabilization

**Labels**

- `frontend`
- `phase-3`

**Problem**

Even after schedule extraction, the shared admin bootstrap will remain too central unless the pattern is proven on another page.

**Scope**

- Select one page family such as history, planner, or generated posts.
- Extract page-level logic into a dedicated module.
- Reuse state handling patterns established earlier.

**Acceptance Criteria**

- One additional page family is extracted successfully.
- Shared initialization contract remains stable.
- Regression smoke tests pass.

**Risk Controls**

- Choose the least coupled page after scheduling, not the most complex page.

## Observability Track

### Issue 13

**Title**

Observability: add correlation IDs across scheduler, generation, and notifications

**Labels**

- `observability`
- `phase-1`
- `high-priority`

**Problem**

Operators cannot easily trace a generation run end-to-end across scheduling, AI calls, post creation, and notifications.

**Scope**

- Define correlation ID generation and propagation rules.
- Thread IDs through schedule runs, generation sessions, history events, and notifications.
- Display correlation IDs where operators can use them.

**Acceptance Criteria**

- A single run can be traced end-to-end using one ID.
- History entries or equivalent records include the ID.
- Diagnostics no longer rely on guesswork across disconnected records.

**Risk Controls**

- Keep IDs lightweight and additive.
- Avoid exposing sensitive internals in user-facing views.

### Issue 14

**Title**

Observability: publish baseline metrics for scheduler and generation reliability

**Labels**

- `observability`
- `phase-1`

**Problem**

The project lacks a consistent baseline for performance and failure analysis, making optimization decisions harder to justify.

**Scope**

- Define and collect key metrics.
- Surface recent success and failure counts.
- Add enough status visibility to compare pre- and post-refactor behavior.

**Acceptance Criteria**

- Baseline metrics exist for duration, failures, retries, and queue or backlog indicators.
- Metrics are reviewable by maintainers without code inspection.
- Future refactor issues can measure improvement against a known baseline.

**Risk Controls**

- Prefer summary metrics over heavy tracing.
- Keep collection non-blocking.

### Issue 15

**Title**

Observability: extend system status with queue health and operator runbook

**Labels**

- `observability`
- `scheduler`
- `phase-3`

**Problem**

Once queue-backed scheduling is introduced, operators will need admin-visible health indicators and a predictable troubleshooting workflow.

**Scope**

- Add queue health indicators to system status.
- Add backlog, retry saturation, and stuck-job signals.
- Write a short runbook for investigating and recovering from queue issues.

**Acceptance Criteria**

- System status exposes queue health clearly.
- Operators can identify stuck or failing work quickly.
- The runbook is usable by someone who did not author the refactor.

**Risk Controls**

- Avoid exposing raw implementation details without summarization.
- Validate runbook steps with at least one dry-run incident exercise.

## Recommended Execution Order

1. Issue 1
2. Issue 13
3. Issue 14
4. Issue 10
5. Issue 7
6. Issue 8
7. Issue 4
8. Issue 5
9. Issue 2
10. Issue 11
11. Issue 6
12. Issue 9
13. Issue 15
14. Issue 3
15. Issue 12

## Definition Of Done For The Backlog

- Every temporary compatibility bridge has a follow-up removal issue.
- Every queue or state change has regression and overlap tests.
- Every operator-facing behavior change has an admin-visible diagnostic path.
- No issue mixes architectural cleanup with unrelated feature work.
