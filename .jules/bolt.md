## 2024-05-23 - [N+1 Query in Template Stats]
**Learning:** The "Templates" list view was executing 2 queries per template (history stats + schedule projections). For 50 templates, this meant 100+ DB calls. Pre-fetching all stats in single queries (grouped by template_id) reduced this to 2 queries total.
**Action:** When rendering lists of objects with associated stats, always implement `get_all_*_stats()` methods that group by the parent ID, rather than calling individual `get_stats($id)` methods in the loop.
