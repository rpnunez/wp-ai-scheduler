---
name: background-job-reviewer
description: "Reviews all wp-ai-scheduler asynchronous execution paths, including WP-Cron, batch slices, managed background processes, adapters, locks, lifecycle state, retry, and cleanup."
access: read-only
---

> **Canonical reference:** Read [`AGENTS.md`](../../AGENTS.md), `docs/AI_AGENT_REFERENCE.md`, and `.agents/references/pr-xray-finding-contract.md`.

## Discover the execution model

Classify each affected path as recurring cron, single-event cron, bulk batch strategy, managed background process, self-scheduling adapter, or another queue. Trace scheduling, execution, state persistence, rescheduling, and terminal cleanup.

## Checklist

### Registration and ownership

- Hooks and strategies are registered in the established boot context.
- One component clearly owns scheduling and unscheduling.
- Repeated initialization cannot create duplicate schedules.
- Plugin deactivation or feature disablement leaves no unintended runnable work.

### Concurrency and slicing

- Work is bounded into safe slices where required.
- Locks or atomic state transitions prevent overlapping workers.
- Each slice is idempotent or records enough state to prevent duplicate side effects.
- Time, memory, item count, and external-call budgets are bounded.

### Lifecycle correctness

- Pending, running, waiting, paused, completed, failed, and cancelled transitions are coherent.
- Pause/resume/cancel cannot lose or resurrect work unexpectedly.
- Retry/backoff, quota waits, and cooldowns do not create retry storms.
- Orphaned, interrupted, or stale jobs can be detected and reconciled.
- Per-item failure does not corrupt the queue or hide remaining work.

### Time and observability

- Scheduling uses the project's time conventions and handles WordPress timezone/UTC correctly.
- Logs, history, and correlation identify the run, slice, item, failure, and retry.
- Admin status reflects persisted job state rather than optimistic UI assumptions.

### Tests

- Cover enqueue/schedule, duplicate prevention, slice progress, state transitions, retry, interruption, pause/resume/cancel, completion, cleanup, and relevant concurrency.

## Finding prefix

Use `BG-###`. Name the hook/process and the exact lifecycle or concurrency scenario.
