# Bolt's Journal

## 2024-05-23 - [Initial Setup]
**Learning:** Initialized Bolt's journal for tracking performance learnings.
**Action:** Always check this file before starting optimization tasks.

## 2024-05-23 - [Bulk Insert Optimization]
**Learning:** The "Planner" feature was using N+1 queries to schedule generated topics. By constructing a single INSERT query with multiple VALUES, we reduced database round-trips from N to 1.
**Action:** Always look for loops performing database writes and convert them to bulk operations where possible.

## 2024-05-24 - [Dashboard Stats Caching]
**Learning:** The `get_stats` method performed a full table scan and aggregation on every dashboard load. Implementing transient caching eliminated this overhead for read-heavy workloads.
**Action:** Identify read-heavy dashboard metrics and apply transient caching with invalidation on write.

## 2024-05-25 - N+1 Query Fix in Templates List
**Learning:** The Templates list view was executing a stats query for each row.
**Action:** Implemented methods to pre-fetch all necessary data in two queries before the loop.

## 2024-05-25 - [History List Optimization]
**Learning:** `get_history` was selecting `*` which includes `longtext` columns like `generated_content` and `generation_log`. These were unused in list views.
**Action:** Always check if `SELECT *` is fetching heavy unused columns, especially for list views.

## 2024-05-25 - [Missing Indexes on Sort Columns]
**Learning:** The History view defaults to sorting by `created_at DESC`, but the `aips_history` table lacked an index on `created_at`, causing filesorts on every load.
**Action:** When auditing `AIPS_DB_Manager`, ensure all columns used in default `ORDER BY` clauses are indexed.
## 2026-03-24 - [Optimize Dashboard History Retrieval]
**Learning:** Replacing `SELECT *` with hardcoded columns in a core repository method (like `get_history`) as a default fallback is an anti-pattern in this architecture. It creates high regression risks by starving callers of expected data (like `longtext` fields) and breaks forward compatibility when new columns are added. The safest performance optimization is to update the call sites (like list views or dashboard widgets) to explicitly request a lighter payload (e.g. `fields => 'list'`) when heavy data is unnecessary.
**Action:** When optimizing database queries, prefer passing explicit optimization parameters from the caller rather than blindly altering default fallback behaviors in the underlying repository.
