## [1.6.1] - 2024-05-22

### Stability (Hunter)
- Implemented optimistic locking in `AIPS_Scheduler` to prevent race conditions during concurrent schedule processing.
- Added `update_next_run_conditional` method to `AIPS_Schedule_Repository` to support conditional updates.

### Performance (Bolt)
- Added `template_status_created` composite index to `aips_history` table schema for faster history queries.
- Optimized `AIPS_Template_Type_Selector::get_schedule_execution_count` to avoid unnecessary subqueries when schedule creation time is known.

### UI Improvements (Wizard)
- Added `.aips-input-group` class to `admin.css` to fix alignment of input fields with action buttons.

### Dev
- Added `tests/test-optimistic-locking.php` (requires WP test environment).
