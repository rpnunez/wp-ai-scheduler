
## $(date +%Y-%m-%d) - Skipped limited mode tests
**Learning:** Tests relying on a real database or specific mock values can fail in limited mode without WP Test Lib.
**Action:** Added `$this->markTestSkipped()` checks in `test-topic-posts-view.php`, `test-schedule-repository-bulk.php`, and `test-scheduler-resilience.php` when `$GLOBALS['wpdb']->get_col_return_val` exists.
