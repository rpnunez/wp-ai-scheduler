# Principal Software Engineer 90-Day Plan - GitHub Issue Templates

## How To Use

For each template below:

1. Create a new GitHub Issue.
2. Copy `Title`, `Labels`, and `Milestone` into issue metadata.
3. Paste the `Body` block into the issue description.

## Suggested Milestones

- PSE 90-Day Plan - Phase 1
- PSE 90-Day Plan - Phase 2
- PSE 90-Day Plan - Phase 3

## Suggested Labels

- architecture
- scheduler
- generation
- frontend
- observability
- tech-debt
- risk-control
- phase-1
- phase-2
- phase-3
- high-priority

## Recommended Creation Order

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

---

## Issue 1

**Title**
Architecture: define canonical generation boundary and legacy adapter strategy

**Labels**
architecture, tech-debt, phase-1, high-priority

**Milestone**
PSE 90-Day Plan - Phase 1

**Body**
```md
## Summary
Define a canonical internal generation boundary and isolate legacy template-based input handling behind a single adapter.

## Problem
Generation logic still contains inline branching for legacy and context-driven flows, increasing complexity, test scope, and change risk.

## Scope
- [ ] Inventory all legacy generation entrypoints.
- [ ] Define canonical internal generation input model.
- [ ] Define legacy adapter contract and ownership.
- [ ] Capture design in an ADR or equivalent architecture decision doc.

## Acceptance Criteria
- [ ] Legacy entrypoints are fully enumerated.
- [ ] Canonical generation input model is documented and approved.
- [ ] Adapter boundary and ownership are explicit.
- [ ] Follow-on implementation issues reference this design.

## Risk Controls
- Do not change runtime behavior in this issue.
- Keep scope limited to architecture decisions and boundaries.

## Out Of Scope
- Implementation of adapter internals.
- Refactoring prompt builder or generator code paths.
```

---

## Issue 2

**Title**
Architecture: complete repository migration for high-value controller flows

**Labels**
architecture, tech-debt, phase-2

**Milestone**
PSE 90-Day Plan - Phase 2

**Body**
```md
## Summary
Complete repository-backed persistence in high-value controller paths and remove direct persistence logic from those controllers.

## Problem
Some controllers still mix request handling and persistence behavior, reducing testability and increasing coupling.

## Scope
- [ ] Audit high-value controllers (schedule, generation-related, author-topic).
- [ ] Replace direct persistence with repository/service methods.
- [ ] Add/expand tests proving controller delegation behavior.

## Acceptance Criteria
- [ ] Targeted controllers no longer perform direct persistence logic.
- [ ] Repository contracts cover targeted operations.
- [ ] Tests assert controller -> service -> repository boundaries.

## Risk Controls
- Prioritize high-leverage flows first.
- Avoid low-value opportunistic migration in the same issue.

## Out Of Scope
- Full repository migration for the entire plugin.
```

---

## Issue 3

**Title**
Architecture: start PSR-4 strangler migration for new refactor modules

**Labels**
architecture, tech-debt, phase-3

**Milestone**
PSE 90-Day Plan - Phase 3

**Body**
```md
## Summary
Begin PSR-4 strangler migration by placing new refactor modules in the new structure with compatibility bridges.

## Problem
Continuing to place new refactor code in the flat legacy include structure compounds long-term maintenance costs.

## Scope
- [ ] Define placement rules for new refactor modules.
- [ ] Add compatibility bridge strategy where required.
- [ ] Move at least one new module family to the new structure.
- [ ] Publish migration checklist for subsequent waves.

## Acceptance Criteria
- [ ] At least one module family uses the new structure.
- [ ] Runtime behavior remains stable.
- [ ] Migration checklist exists for future waves.

## Risk Controls
- No mass renaming of stable modules.
- Preserve backward compatibility at entrypoints.

## Out Of Scope
- Full plugin-wide PSR-4 migration.
```

---

## Issue 4

**Title**
Scheduler: introduce queue-backed generation jobs with idempotency keys

**Labels**
scheduler, tech-debt, phase-2, high-priority

**Milestone**
PSE 90-Day Plan - Phase 2

**Body**
```md
## Summary
Introduce queue-backed generation jobs and idempotency to replace unbounded cron execution patterns.

## Problem
Cron-driven direct scheduling can produce duplicate work, poor batching, and unstable load behavior.

## Scope
- [ ] Add queue storage/abstraction for generation jobs.
- [ ] Include idempotency key, lock token, attempt count, and available-at time.
- [ ] Update cron flow to enqueue and process bounded work.

## Acceptance Criteria
- [ ] Due work is represented as queue jobs.
- [ ] Duplicate active work for same schedule occurrence is prevented under overlap conditions.
- [ ] Worker batch size is bounded and configurable.

## Risk Controls
- Feature-gate rollout.
- Add overlap/replay tests before production enablement.

## Out Of Scope
- Full missed-run recovery policy.
```

---

## Issue 5

**Title**
Scheduler: implement worker locking, pacing, and bounded retries

**Labels**
scheduler, risk-control, phase-2

**Milestone**
PSE 90-Day Plan - Phase 2

**Body**
```md
## Summary
Harden queue processing with lock discipline, pacing controls, and bounded retry behavior.

## Problem
Queue adoption alone will not prevent API spikes, stuck jobs, or duplicate claim attempts.

## Scope
- [ ] Add lock acquisition and expiration semantics.
- [ ] Add pacing controls for worker batch processing.
- [ ] Add bounded retries and terminal failure handling.

## Acceptance Criteria
- [ ] A job cannot be processed by multiple workers concurrently.
- [ ] Retries are bounded and observable.
- [ ] Worker pacing prevents bursty over-execution.

## Risk Controls
- Use conservative defaults.
- Expose pacing/retry limits via config or filters.

## Out Of Scope
- End-user UI for queue tuning.
```

---

## Issue 6

**Title**
Scheduler: add missed-run recovery and safe catch-up processing

**Labels**
scheduler, phase-3

**Milestone**
PSE 90-Day Plan - Phase 3

**Body**
```md
## Summary
Implement bounded missed-run recovery that safely catches up without overwhelming generation infrastructure.

## Problem
Low-traffic WordPress environments can miss cron windows, causing publishing gaps and unsafe manual recovery.

## Scope
- [ ] Detect missed schedule windows.
- [ ] Create bounded catch-up work.
- [ ] Prevent thundering-herd behavior during recovery.

## Acceptance Criteria
- [ ] Missed runs are detected reliably.
- [ ] Catch-up work is bounded and paced.
- [ ] Recovery does not create unsafe backlog spikes.

## Risk Controls
- Add hard limits on catch-up depth.
- Optionally support dry-run verification mode.

## Out Of Scope
- Advanced predictive scheduling.
```

---

## Issue 7

**Title**
Generation: move core execution to context-only internals via legacy adapter

**Labels**
generation, architecture, phase-2, high-priority

**Milestone**
PSE 90-Day Plan - Phase 2

**Body**
```md
## Summary
Move generation internals to one context-driven execution path, keeping legacy support only at the edge adapter.

## Problem
Core generation classes currently support dual execution styles inline, creating branching and duplication.

## Scope
- [ ] Implement legacy-to-context adapter.
- [ ] Update core generation orchestration to consume context input only.
- [ ] Remove duplicated inline dual-path logic from core flow.
- [ ] Add regression coverage for template and topic generation paths.

## Acceptance Criteria
- [ ] Core internals use a single canonical input model.
- [ ] Legacy callers continue to work through adapter conversion.
- [ ] Regression tests confirm behavior compatibility.

## Risk Controls
- Keep adapter coverage high before deleting old branches.
- Preserve output compatibility and user-visible behavior.

## Out Of Scope
- New generation feature expansion unrelated to migration.
```

---

## Issue 8

**Title**
Generation: implement explicit generation lifecycle states and transitions

**Labels**
generation, observability, phase-2

**Milestone**
PSE 90-Day Plan - Phase 2

**Body**
```md
## Summary
Add explicit generation lifecycle states and transition rules for deterministic retries and recovery.

## Problem
Generation progress and failure modes are insufficiently explicit, making diagnosis and recovery difficult.

## Scope
- [ ] Define lifecycle states and transition rules.
- [ ] Persist state, timestamps, and retry metadata.
- [ ] Expose states via history/status diagnostics.

## Acceptance Criteria
- [ ] Lifecycle states are explicit and test-covered.
- [ ] Partial failures are distinguishable from terminal failures.
- [ ] Recovery logic can target the failed stage.

## Risk Controls
- Start with append-only transition history where practical.
- Avoid tight coupling between early state model and UI assumptions.

## Out Of Scope
- Full workflow visualization UI.
```

---

## Issue 9

**Title**
Generation: support partial recovery for content/image split failures

**Labels**
generation, phase-3

**Milestone**
PSE 90-Day Plan - Phase 3

**Body**
```md
## Summary
Implement recoverable partial generation behavior for content/image split failures.

## Problem
Late-stage failures can discard successful generation outputs that should be recoverable.

## Scope
- [ ] Separate content completion from image completion outcomes.
- [ ] Mark recoverable failures explicitly.
- [ ] Add targeted recovery actions for recoverable states.

## Acceptance Criteria
- [ ] Content can survive image-stage failures when configured.
- [ ] Recoverable failures are clearly visible to operators.
- [ ] Recovery actions are bounded and safe.

## Risk Controls
- Gate partial persistence behind explicit policy.
- Add cleanup rules for partial assets/metadata.

## Out Of Scope
- Broad post-editing workflow redesign.
```

---

## Issue 10

**Title**
Frontend: extract schedule modal controller from admin.js into page-level module

**Labels**
frontend, tech-debt, phase-1, high-priority

**Milestone**
PSE 90-Day Plan - Phase 1

**Body**
```md
## Summary
Extract schedule modal logic from the shared admin bootstrap into a dedicated page-level controller.

## Problem
Schedule create/edit flows rely on duplicated modal reset/fallback logic in admin.js, creating fragile behavior and high change risk.

## Scope
- [ ] Extract open/edit/populate/submit/close logic into dedicated module.
- [ ] Add shared state helpers for reset and error handling.
- [ ] Preserve existing selectors and localized payload contracts.

## Acceptance Criteria
- [ ] Schedule create/edit flows use one controller path.
- [ ] Duplicated modal reset logic in shared flow is removed.
- [ ] Manual QA passes for create/edit/cancel/preselect/validation cases.

## Risk Controls
- Keep compatibility path until stabilized.
- Avoid broad UI redesign in same issue.

## Out Of Scope
- Non-schedule admin page refactors.
```

---

## Issue 11

**Title**
Frontend: standardize admin action state handling for loading, error, and success states

**Labels**
frontend, phase-2

**Milestone**
PSE 90-Day Plan - Phase 2

**Body**
```md
## Summary
Standardize admin action-state handling for loading, error, and success across targeted pages.

## Problem
Inconsistent action-state patterns create duplicate logic and uneven UX feedback.

## Scope
- [ ] Define reusable action-state conventions.
- [ ] Introduce shared helpers/primitives for loading, error, and success.
- [ ] Apply to scheduling and at least one additional admin area.

## Acceptance Criteria
- [ ] At least two admin areas share one action-state pattern.
- [ ] Loading and error behavior is consistent in targeted areas.
- [ ] Duplicated close/reset logic is reduced.

## Risk Controls
- Keep helper surface minimal.
- Avoid introducing a frontend framework in this roadmap.

## Out Of Scope
- Full admin UI redesign.
```

---

## Issue 12

**Title**
Frontend: plan and execute second admin page extraction after scheduling stabilization

**Labels**
frontend, phase-3

**Milestone**
PSE 90-Day Plan - Phase 3

**Body**
```md
## Summary
Execute a second page-level extraction to validate frontend modularization beyond scheduling.

## Problem
Without proving the pattern on another page, admin.js remains too central and future work risks regression.

## Scope
- [ ] Select one page family (history, planner, or generated posts).
- [ ] Extract page logic into dedicated module.
- [ ] Reuse established action-state and initialization conventions.

## Acceptance Criteria
- [ ] One additional page family is modularized successfully.
- [ ] Shared initialization contract remains stable.
- [ ] Regression smoke tests pass.

## Risk Controls
- Choose least-coupled page after scheduling.
- Keep extraction incremental.

## Out Of Scope
- Multi-page extraction wave.
```

---

## Issue 13

**Title**
Observability: add correlation IDs across scheduler, generation, and notifications

**Labels**
observability, phase-1, high-priority

**Milestone**
PSE 90-Day Plan - Phase 1

**Body**
```md
## Summary
Introduce correlation IDs to trace a run end-to-end across scheduling, generation, history, and notifications.

## Problem
Run-level diagnostics are fragmented across disconnected records, increasing mean time to diagnose failures.

## Scope
- [ ] Define correlation ID generation and propagation rules.
- [ ] Thread IDs through schedule runs, generation sessions, history events, and notifications.
- [ ] Expose IDs in operator-usable diagnostics.

## Acceptance Criteria
- [ ] One run is traceable end-to-end using a single ID.
- [ ] History/diagnostic records include the ID.
- [ ] Operators can diagnose flows without guesswork.

## Risk Controls
- Keep implementation lightweight and additive.
- Avoid exposing sensitive internals in user-facing surfaces.

## Out Of Scope
- Full distributed tracing platform integration.
```

---

## Issue 14

**Title**
Observability: publish baseline metrics for scheduler and generation reliability

**Labels**
observability, phase-1

**Milestone**
PSE 90-Day Plan - Phase 1

**Body**
```md
## Summary
Establish baseline reliability and performance metrics for scheduler and generation workflows.

## Problem
Without baseline metrics, optimization and risk decisions are difficult to justify and verify.

## Scope
- [ ] Define key metrics (duration, failure rate, retry counts, backlog indicators).
- [ ] Collect and expose summary metrics.
- [ ] Provide pre/post comparison basis for refactor outcomes.

## Acceptance Criteria
- [ ] Baseline metrics exist and are reviewable.
- [ ] Reliability and performance trends can be compared over time.
- [ ] Follow-on issues can use baseline as success benchmark.

## Risk Controls
- Prefer summary metrics over heavy instrumentation.
- Keep collection non-blocking and low overhead.

## Out Of Scope
- Advanced analytics dashboards.
```

---

## Issue 15

**Title**
Observability: extend system status with queue health and operator runbook

**Labels**
observability, scheduler, phase-3

**Milestone**
PSE 90-Day Plan - Phase 3

**Body**
```md
## Summary
Add queue-health visibility and a practical operator runbook for queue and generation incident handling.

## Problem
Queue-backed scheduling requires clear admin-facing health diagnostics and repeatable troubleshooting procedures.

## Scope
- [ ] Add queue health indicators to system status.
- [ ] Add backlog, retry saturation, and stuck-job signals.
- [ ] Write short operator runbook for investigation and recovery.

## Acceptance Criteria
- [ ] Queue health is visible and understandable in admin/system status.
- [ ] Operators can identify stuck/failing work quickly.
- [ ] Runbook can be followed by non-authors.

## Risk Controls
- Avoid raw low-level output without summarization.
- Validate runbook with at least one dry-run exercise.

## Out Of Scope
- Full on-call alerting platform rollout.
```

---

## Definition Of Done Across Issue Set

- Every temporary compatibility bridge has a removal follow-up issue.
- Every queue or state transition change includes overlap/regression tests.
- Every operator-facing behavior change includes diagnostic visibility.
- No issue mixes deep refactor work with unrelated feature expansion.
