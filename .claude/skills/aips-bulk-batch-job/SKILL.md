---
name: aips-bulk-batch-job
description: Use when adding a new bulk/batch job type or changing how batches are processed in the AI Post Scheduler plugin (ai-post-scheduler/) — anything touching AIPS_Bulk_Batch_Processor, AIPS_Bulk_Batch_Job_Store, or the aips_process_bulk_batch cron hook.
---

# Bulk/batch job workflow

Bulk work runs as single-event cron slices dispatched by one processor, with job
types registered as pluggable strategies rather than separate cron hooks per type.

## Required workflow

1. **Register a new job type as a strategy.** In `boot_cron()`
   (`ai-post-scheduler.php`), call
   `AIPS_Bulk_Batch_Processor::instance()->register(string $job_type, callable $handler)`.
   The three existing types — `author_topic_post`, `planner_post`,
   `trending_topic_post` — are the reference implementations to model a new one on.
2. **Don't add a new cron hook per job type.** All bulk work is dispatched through
   the single `aips_process_bulk_batch` single-event hook; the processor slices work
   and re-schedules itself. Adding a parallel hook duplicates the batching/retry
   machinery.
3. **Persist job state through the store, not ad hoc options/tables.** Use
   `AIPS_Bulk_Batch_Job_Store` for progress/status — don't invent a second state
   mechanism for a new job type.
4. **Keep the handler idempotent and resumable.** Since work is processed in slices
   across multiple cron ticks, a handler must tolerate being invoked again on a
   partially-completed batch.

## Guardrails

- A bulk/batch change almost always warrants the `cron` PR label — see `aips-pr-prep`.
- SQL for job state still belongs in a repository, not directly in the processor or
  handler callable — see `aips-repository-boundary`.

## Reference files

- `ai-post-scheduler/includes/class-aips-bulk-batch-processor.php`
- `ai-post-scheduler/includes/class-aips-bulk-batch-job-store.php`
- `ai-post-scheduler/ai-post-scheduler.php` (`boot_cron()`, existing `register()` calls)
- `ai-post-scheduler/includes/job/`
