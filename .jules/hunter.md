## 2024-05-25 - Schedule Initialization Logic Flaw
**Learning:** Using `calculate_next_run(interval, start_time)` for *initial* schedule creation is incorrect because it calculates `start_time + interval`, effectively skipping the first run.
**Action:** When creating schedules, always treat `start_time` as the explicit *first* execution time (`next_run`), rather than a base for calculation. Only use `calculate_next_run` for subsequent recurring executions.

## 2024-05-26 - Scheduler Race Condition on Concurrent Execution
**Learning:** `wpdb->update` returns `true` (via boolean conversion of 0 affected rows) even if the row wasn't actually changed by the current process, leading to a race condition where multiple schedulers could claim the same task.
**Action:** Use optimistic locking (`UPDATE ... WHERE id = %d AND next_run = %s`) and strictly check for `rows_affected > 0` to confirm lock acquisition for critical resources.
