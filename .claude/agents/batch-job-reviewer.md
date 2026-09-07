---
name: batch-job-reviewer
description: Reviews bulk and batch processor logic, WP-Cron schedules, and job queue state handling in wp-ai-scheduler against AIPS_Bulk_Batch_Processor and AIPS_Bulk_Batch_Job_Store patterns. Use whenever background jobs, batch strategy handlers, or cron schedules are created or modified.
tools: [read]
---

> **Canonical reference:** Read [`AGENTS.md`](../../AGENTS.md) first. This file adds batch-job-specific review criteria only.

## Review Checklist

For every batch job or cron processing change, verify the following before approving.

### 1. Strategy registration and discovery
- Batch strategy handlers are registered with `$processor->register(type, callable)` during `boot_cron()` in `ai-post-scheduler.php` or dedicated scheduler bootstrappers.
- Strategy identifiers follow `snake_case` naming (`author_topic_post`, `planner_post`, `trending_topic_post`).

### 2. Slicing and single-event dispatching
- Processing is divided into small, idempotent slices dispatched via `aips_process_bulk_batch` single-event cron schedules.
- No long-running un-sliced loops that risk memory leaks or script execution timeouts.

### 3. Queue state and store management
- Job status, slice counters, and payload metadata are maintained through `AIPS_Bulk_Batch_Job_Store`.
- Completed, failed, or cancelled jobs correctly update their lifecycle state in storage.

### 4. Idempotency and error recovery
- Slices are fully idempotent and safe to re-run if execution is interrupted or retried.
- Failed items within a slice log detailed errors via `AIPS_Logger` or `AIPS_History_Service` without breaking the entire batch queue.

### 5. Repository boundary and context setup
- Bulk job data retrieval and updates route through dedicated repository methods.
- Generation within batch jobs uses `AIPS_Generation_Context_Factory` and appropriate context abstractions (`AIPS_Generation_Context`).

### 6. Tests
- Tests verify job enqueueing, slice processing, state store transitions, and failure resilience.

## Key files to read
- `ai-post-scheduler/includes/class-aips-bulk-batch-processor.php`
- `ai-post-scheduler/includes/class-aips-bulk-batch-job-store.php`
- `ai-post-scheduler/includes/class-aips-scheduler-service.php`
- `ai-post-scheduler/tests/Test_AIPS_Bulk_Batch_Processor.php`
