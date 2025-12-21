## 2024-05-22 - [SQL Column Collision in SELECT * Joins]
**Learning:** `SELECT s.*, t.*` in a JOIN query causes data corruption in the returned object when both tables share column names (like `id`). The last table's columns overwrite the previous ones. In this case, Template ID overwrote Schedule ID, causing updates to run against the wrong record.
**Action:** Always be explicit with selected columns (`SELECT s.id as schedule_id, ...`) or carefully order `SELECT *` (`SELECT t.*, s.*`) so the primary entity's ID takes precedence. Avoid `SELECT *` on joins when possible.
