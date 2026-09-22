# Batch Job & Cron Processing Skill

Use this skill when adding or modifying bulk batch jobs, WP-Cron handlers, or background task queues in `wp-ai-scheduler`.

## Scope
- Batch job strategies registered with `AIPS_Bulk_Batch_Processor`.
- Background task queues managed via `AIPS_Bulk_Batch_Job_Store`.
- WP-Cron hooks and single-event slice processing routines.

## Required workflow

1. **Register Strategy**
   - Register new batch strategy identifiers in `boot_cron()` within `ai-post-scheduler.php` or dedicated scheduler services.
   - Use `$processor->register('strategy_name', array($instance, 'process_slice'))`.

2. **Implement Slice Handler**
   - Slices must process a fixed batch size (e.g., 5-10 items per slice).
   - Ensure the handler is fully idempotent: if re-run on the same slice, it yields consistent results without duplicating records.
   - Return clean batch execution status (completed, pending, or failed).

3. **Manage Queue & Store State**
   - Read and persist slice metrics using `AIPS_Bulk_Batch_Job_Store`.
   - Log errors per-item via `AIPS_Logger` or `AIPS_History_Service` while allowing non-fatal errors to be tracked gracefully.

4. **Schedule Slices Safety**
   - Reschedule remaining slices via `aips_process_bulk_batch` single-event cron timers until job completion.
   - Do not use blocking loops or un-sliced loops.

5. **Validation**
   - Add PHPUnit tests covering enqueueing, slice processing, error handling, and queue completion state transitions.

## Guardrails
- Never execute database queries via direct `$wpdb` in job strategy handlers; use repository methods.
- Ensure context generation inside batch jobs utilizes `AIPS_Generation_Context_Factory`.

## Useful files
- `ai-post-scheduler/includes/class-aips-bulk-batch-processor.php`
- `ai-post-scheduler/includes/class-aips-bulk-batch-job-store.php`
- `ai-post-scheduler/includes/class-aips-scheduler-service.php`
- `ai-post-scheduler/tests/Test_AIPS_Bulk_Batch_Processor.php`

## Useful agents
- `.claude/agents/batch-job-reviewer.md` — post-implementation review
