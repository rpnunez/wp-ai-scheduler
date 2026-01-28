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

## 2024-05-25 - Fail-Fast Validation
**Learning:** Validating input length (e.g., topic length) before attempting expensive operations prevents DB errors and saves resources.
**Action:** Added 255-char limit check for `topic` in `AIPS_Schedule_Controller::ajax_run_now`.
