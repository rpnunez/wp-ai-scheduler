# Principal Software Engineer 90-Day Plan

## Purpose

This plan defines a concrete 90-day implementation roadmap for the highest-value architectural improvements in AI Post Scheduler. It is designed to reduce complexity, improve throughput and reliability, and create a safer foundation for future features.

The plan is organized around five execution tracks:

1. Architecture
2. Scheduler
3. Generation
4. Frontend
5. Observability

## Target Outcomes By Day 90

- Generation runs use one canonical context-driven execution model internally.
- Scheduling uses queue-backed, idempotent job execution for generation work.
- Partial failures and retries follow explicit generation lifecycle states.
- Admin scheduling UI no longer relies on duplicated legacy modal fallback logic.
- Core workflows emit correlation IDs and measurable operational metrics.
- New work can be added in namespaced, modular code without increasing legacy coupling.

## Scope Boundaries

This roadmap focuses on structural improvements, not broad feature expansion. New user-facing features should only be added when they directly validate the new architecture, such as:

- Batch generation support
- Missed-run recovery
- Partial generation recovery
- Improved schedule editing UX

## Guiding Principles

- Prefer strangler migrations over big-bang rewrites.
- Keep public behavior stable unless a change is explicitly approved.
- Move complexity to explicit boundaries: adapters, handlers, repositories, and state models.
- Every infrastructure change must ship with test coverage and rollback criteria.
- Instrument first, optimize second.

## Workstreams

### Architecture

- Establish a stable modular boundary for generation, scheduling, and admin workflows.
- Introduce new code in PSR-4-style, namespaced modules where practical.
- Isolate legacy compatibility behind adapters.

### Scheduler

- Move from direct cron-triggered generation execution toward queue-backed job orchestration.
- Add idempotency, locking, pacing, and missed-run recovery controls.

### Generation

- Finish the migration to context-first generation.
- Model generation as a lifecycle with explicit states and recovery paths.

### Frontend

- Reduce admin.js centralization by extracting page-specific modules.
- Replace duplicated modal flows with a single state-driven scheduling UI contract.

### Observability

- Add correlation IDs, structured metrics, and operational dashboards for generation and scheduling.
- Make failures diagnosable without reading raw logs or reproducing manually.

## Delivery Plan

## Phase 1: Foundation And Safety Rails

### Window

- Days 1-30

### Milestone 1.1: Architecture Baseline And Cut Lines

**Goals**

- Define what remains legacy, what becomes adapted, and what becomes the new canonical path.
- Establish module boundaries for scheduler, generation, and admin scheduling UI.

**Deliverables**

- One architecture decision record for generation execution boundary.
- One architecture decision record for queue-backed scheduler design.
- Dependency map for current generator, scheduler, prompt builder, session, and schedule UI flows.
- Namespace and folder policy for all new code introduced during this roadmap.

**Acceptance Criteria**

- A written execution boundary exists for `AIPS_Generator`, scheduler processing, and prompt building.
- New code placement rules are documented and agreed before implementation.
- A legacy compatibility inventory exists listing all entrypoints that still accept template-based inputs.

**Risk Controls**

- Do not move existing classes purely for cosmetics in Phase 1.
- Block new legacy-path additions after the compatibility inventory is published.
- Require one named owner for each module boundary decision.

### Milestone 1.2: Observability Baseline

**Goals**

- Measure current performance and failure patterns before invasive refactors.

**Deliverables**

- Baseline metrics for generation duration, AI call duration, image generation failure rate, schedule run success rate, and queue depth surrogate metrics.
- Correlation ID specification spanning schedule run, generation session, AI calls, notifications, and history.
- Lightweight system status additions for scheduler health and recent generation outcomes.

**Acceptance Criteria**

- At least five baseline metrics are captured and reviewable.
- Correlation IDs appear in history records or equivalent diagnostic paths for manual and scheduled runs.
- A failure investigation can trace a run from schedule trigger to post creation attempt.

**Risk Controls**

- Keep instrumentation additive and non-blocking.
- Avoid any metrics implementation that can fail a generation run.
- Sample expensive metrics if needed rather than collecting everything synchronously.

### Milestone 1.3: Frontend Scheduling Contract

**Goals**

- Stop further spread of wizard-versus-legacy modal duplication.

**Deliverables**

- Scheduling UI contract document covering open, edit, preselect, validate, submit, and close actions.
- One extracted JS module for schedule modal coordination and state handling.
- Shared helpers for modal reset, populate, submit state, and error rendering.

**Acceptance Criteria**

- Schedule create and edit use one shared UI controller path.
- No new schedule flow logic is added directly to the large shared admin bootstrap object.
- Manual QA passes for create, edit, cancel, preselect, and validation error flows.

**Risk Controls**

- Keep the legacy modal behind a feature flag or guarded compatibility path until the new controller is stable.
- Preserve existing selectors and localized data contracts in Phase 1.

## Phase 2: Core Refactor Execution

### Window

- Days 31-60

### Milestone 2.1: Context-Only Generation Core

**Goals**

- Make generation internals context-driven even when legacy callers still exist.

**Deliverables**

- One explicit adapter that converts legacy template inputs into generation contexts.
- Internal generator, prompt builder, and session workflows updated to operate on context objects only.
- Removal of duplicated branching from core execution paths where context and legacy paths are both handled inline.

**Acceptance Criteria**

- Internal generation orchestration accepts one canonical input type.
- Legacy inputs are converted at the edge and never handled in the core flow after adaptation.
- Existing template-based flows continue to work through the adapter.
- Unit tests cover adapter conversion and context-driven execution for template and topic flows.

**Risk Controls**

- Keep adapter behavior covered by regression tests before deleting any dual-path logic.
- Do not change user-facing generation behavior and output format in this milestone.
- Maintain a temporary fallback switch for high-risk production recovery.

### Milestone 2.2: Generation Lifecycle State Model

**Goals**

- Represent generation progress and recovery explicitly.

**Deliverables**

- Generation state model with statuses such as `queued`, `started`, `content_generated`, `title_generated`, `excerpt_generated`, `image_generated`, `post_created`, `completed`, `failed`, and `recoverable_failed`.
- State transition rules for manual runs, scheduled runs, retries, and partial failures.
- Storage strategy for current state and retry metadata.

**Acceptance Criteria**

- Partial failures no longer collapse into generic failures without state context.
- Recovery paths can distinguish between content recovery and image recovery.
- History or status views can show the current or final lifecycle state for a generation run.

**Risk Controls**

- Start by making state transitions append-only before introducing mutation-heavy logic.
- Avoid coupling UI progress rendering to unfinished internal state details.

### Milestone 2.3: Queue-Backed Scheduler v1

**Goals**

- Stop running all scheduling logic directly in cron callbacks.

**Deliverables**

- Queue storage for pending generation jobs with lock token, idempotency key, attempt count, available-at time, and job type.
- Cron hook revised to enqueue due work and process bounded batches rather than fully execute all work inline.
- Locking strategy to prevent duplicate processing on overlapping cron invocations.

**Acceptance Criteria**

- Schedule processing enqueues jobs deterministically and can safely rerun without duplicate post generation.
- A worker can process a bounded batch and leave remaining jobs intact.
- Duplicate cron invocations do not create duplicate active work for the same schedule occurrence.

**Risk Controls**

- Keep queue processing batch size configurable.
- Introduce idempotency keys before enabling multi-post-per-run behavior.
- Add dead-letter or manual inspection criteria for jobs that exceed retry limits.

### Milestone 2.4: Repository And Transaction Boundary Completion

**Goals**

- Finish moving persistence behavior behind explicit repository methods.

**Deliverables**

- Controller audit removing remaining direct persistence behavior from high-value flows.
- Transaction wrapper or coordinated write boundary for multi-step operations.
- Test coverage for repository-backed delete, update, and schedule-selection behaviors.

**Acceptance Criteria**

- Core controllers do not issue direct SQL for business logic paths covered by repositories.
- Multi-write operations either commit as a unit or fail in a recoverable, diagnosable way.
- Tests prove that key controllers delegate to repositories and services instead of writing directly.

**Risk Controls**

- Do not attempt total repository migration across low-value legacy code in the same sprint.
- Limit transaction boundary changes to operations with clear consistency needs.

## Phase 3: Hardening, Rollout, And Optimization

### Window

- Days 61-90

### Milestone 3.1: Scheduler Recovery And Throughput Features

**Goals**

- Use the queue design to support real operational improvements.

**Deliverables**

- Missed-run recovery with bounded catch-up processing.
- Smart pacing controls to prevent API spikes.
- Foundation for multiple posts per run using queue fan-out instead of repeated cron configuration.

**Acceptance Criteria**

- Missed schedules are recovered without a thundering herd effect.
- Throughput limits are enforced per batch or worker cycle.
- Multi-post scheduling can be enabled behind a safe configuration gate.

**Risk Controls**

- Roll out catch-up logic in dry-run or preview mode first if operational risk is high.
- Add explicit limits on total work created from one catch-up window.

### Milestone 3.2: Frontend Decomposition And UX Reliability

**Goals**

- Reduce the admin.js monolith and improve predictable page behavior.

**Deliverables**

- Schedule UI module fully extracted.
- Additional page-level module extraction plan for generated posts, history, and planner.
- Reusable error, loading, and confirmation components for admin actions.

**Acceptance Criteria**

- Scheduling page logic resides outside the central admin.js catch-all where practical.
- Modal state and row updates no longer require duplicated form reset and close logic.
- At least one additional admin area adopts the same module pattern successfully.

**Risk Controls**

- Extract one page family at a time.
- Preserve the existing global initialization contract until all page modules are proven stable.

### Milestone 3.3: Observability And Operational Readiness

**Goals**

- Make the new architecture supportable in production.

**Deliverables**

- Queue health indicators in system status.
- Correlation-aware history entries for scheduler and generation workflows.
- Alerting thresholds or review checklist for queue backlog, retry saturation, and partial generation failures.
- Short operator runbook for debugging queue and generation issues.

**Acceptance Criteria**

- Operators can answer: what failed, where it failed, whether it retried, and whether it is recoverable.
- System status exposes enough scheduler and queue information to diagnose stuck work.
- At least one documented operational drill has been executed successfully.

**Risk Controls**

- Do not rely only on verbose logs; expose summary health data in admin.
- Keep runbook steps simple enough for non-authors of the refactor to follow.

### Milestone 3.4: PSR-4 Strangler Start For New Code

**Goals**

- Ensure the refactor does not keep adding to the flat legacy structure.

**Deliverables**

- New roadmap-delivered modules placed in PSR-4-ready or namespaced locations where feasible.
- Compatibility bridge between existing autoloading and new module entrypoints.
- Migration checklist for the next 6-12 months.

**Acceptance Criteria**

- No new large multi-responsibility classes are added under the flat legacy structure for roadmap work.
- At least one new module family proves the migration path is viable.
- Future migrations can proceed incrementally without a whole-plugin rewrite.

**Risk Controls**

- Avoid mass-moving stable classes during the 90-day window.
- Restrict PSR-4 work to new or actively modified areas.

## Milestone Summary Table

| Phase | Milestone | Primary Tracks | Exit Criteria |
| --- | --- | --- | --- |
| 1 | Architecture baseline and cut lines | Architecture, Observability | ADRs complete, boundaries agreed, legacy inventory published |
| 1 | Observability baseline | Observability | Baseline metrics and correlation IDs available |
| 1 | Frontend scheduling contract | Frontend | Shared scheduling UI controller path in place |
| 2 | Context-only generation core | Architecture, Generation | Canonical internal context flow live |
| 2 | Generation lifecycle state model | Generation, Observability | Explicit states and recoverable failure paths implemented |
| 2 | Queue-backed scheduler v1 | Scheduler, Observability | Enqueue plus bounded worker processing with idempotency |
| 2 | Repository and transaction completion | Architecture | Controllers delegate through repositories and services |
| 3 | Scheduler recovery and throughput | Scheduler, Generation | Missed-run recovery and pacing controls validated |
| 3 | Frontend decomposition | Frontend | Scheduling module extracted and stable |
| 3 | Operational readiness | Observability | Queue health, runbook, and diagnostic flow complete |
| 3 | PSR-4 strangler start | Architecture | New modules stop increasing legacy structural debt |

## Test Strategy Across The 90 Days

- Unit tests for adapters, repositories, queue handlers, and state transitions.
- Integration tests for schedule enqueue, worker processing, retry handling, and recovery scenarios.
- Regression tests for manual generation, scheduled generation, generated post creation, and notification hooks.
- Admin UI smoke tests for schedule create, edit, preselect, validation, and recovery after failed submissions.

## Program-Level Acceptance Criteria

- Mean time to diagnose generation failures is reduced materially because every run is traceable.
- Scheduler execution becomes bounded, idempotent, and safe under overlapping cron calls.
- Generation logic becomes simpler because dual-path branching is removed from the core execution path.
- The scheduling UI stops depending on duplicated legacy modal flows.
- The codebase gains a practical migration path toward PSR-4 and modular structure without destabilizing production.

## Key Risks And Controls

### Risk: Refactor breadth exceeds team capacity

**Control**

- Sequence work by business leverage.
- Defer low-value cleanup that does not unlock reliability or maintainability.

### Risk: Scheduler changes create duplicate posts

**Control**

- Enforce idempotency keys before queue fan-out and multi-post-per-run support.
- Add replay-safe tests for overlapping cron invocations.

### Risk: Observability changes add overhead to runtime

**Control**

- Start with summary metrics and correlation IDs.
- Keep detailed tracing opt-in if needed.

### Risk: Frontend extraction breaks admin workflows

**Control**

- Extract one page family at a time.
- Maintain smoke-test scripts for schedule-related workflows before and after each change.

### Risk: Partial migration leaves two architectures permanently alive

**Control**

- Use explicit exit criteria for each temporary compatibility layer.
- Add deprecation tasks for every bridge introduced.

## Deferred Items

These are useful but should not displace the roadmap unless tied to the new architecture:

- Full plugin-wide PSR-4 migration
- Full admin frontend redesign
- Multi-provider AI support beyond the interface and policy seam
- Template inheritance and analytics
- Broad content strategy feature expansion

## Recommended Governance

- Review roadmap progress every two weeks against milestone exit criteria.
- Treat architecture deviations as explicit decisions, not incidental code drift.
- Track all temporary compatibility paths as backlog items with an owner and removal target.
