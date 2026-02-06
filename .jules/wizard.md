# Wizard's Journal

## 2025-12-28 - Schedule Bulk Delete
**Learning:** The "Post Schedules" table (`schedule.php`) lacked bulk delete functionality, which is inconsistent with "Activity History" and causes friction for users managing multiple schedules.
**Action:** Implemented bulk delete with "Select All" checkbox, row selection logic in `admin.js`, and a new `delete_bulk` repository method, matching the established pattern from History.
