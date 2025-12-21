# Bolt's Journal

## 2024-05-23 - [Initial Setup]
**Learning:** Initialized Bolt's journal for tracking performance learnings.
**Action:** Always check this file before starting optimization tasks.

## 2024-05-23 - [Bulk Insert Optimization]
**Learning:** The "Planner" feature was using N+1 queries to schedule generated topics. By constructing a single INSERT query with multiple VALUES, we reduced database round-trips from N to 1.
**Action:** Always look for loops performing database writes and convert them to bulk operations where possible.
