## 2024-05-25 - Schedule Initialization Logic Flaw
**Learning:** Using `calculate_next_run(interval, start_time)` for *initial* schedule creation is incorrect because it calculates `start_time + interval`, effectively skipping the first run.
**Action:** When creating schedules, always treat `start_time` as the explicit *first* execution time (`next_run`), rather than a base for calculation. Only use `calculate_next_run` for subsequent recurring executions.

## 2026-01-24 - Optimistic Locking with WPDB
**Learning:** `wpdb->update` returns 0 rows affected if the `WHERE` clause doesn't match OR if the data being updated is identical to existing data. For optimistic locking (preventing race conditions), you must explicitly check `$wpdb->rows_affected > 0` after an update query that includes the version/timestamp in the `WHERE` clause. Relying solely on `false` return value is insufficient as it only indicates SQL errors, not race condition failures.
**Action:** Always implement `update_conditional($id, $new_data, $old_version)` methods in Repositories that return boolean based on `rows_affected > 0` for concurrency-sensitive operations.
