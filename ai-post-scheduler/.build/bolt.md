## 2026-06-05 - Avoid Redundant Template DB Lookup in Schedule Processor
**Area:** includes/class-aips-schedule-processor.php
**Status:** opened PR
**PR:** ⚡ Bolt: Eliminate redundant template query in schedule execution
**Learning:** Schedule objects fetched by `get_due_schedules` or manual operations are already merged with template properties, meaning explicit re-fetches with `get_by_id` inside the processor logic lead to unnecessary N+1 queries during bulk/batch operations.
**Action:** Always check if required joined properties like `post_quantity` are already present on the domain object before initiating repository fetches in tight loops or processor logic.
