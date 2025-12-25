# Bolt's Journal

## 2024-05-23 - [Initial Setup]
**Learning:** Initialized Bolt's journal for tracking performance learnings.
**Action:** Always check this file before starting optimization tasks.

## 2024-05-23 - [Bulk Insert Optimization]
**Learning:** The "Planner" feature was using N+1 queries to schedule generated topics. By constructing a single INSERT query with multiple VALUES, we reduced database round-trips from N to 1.
**Action:** Always look for loops performing database writes and convert them to bulk operations where possible.

## 2024-05-23 - [Bulk Read Optimization for Templates]
**Learning:** The Templates list page was performing N+1 queries to fetch stats for each template (history count and pending schedule). By eager loading all data into maps using two bulk queries, we reduced the complexity from O(N) to O(1) database calls.
**Action:** When rendering lists of items that need related counts or stats, always implement a `get_all_stats` method to fetch data in bulk and map it in memory.
