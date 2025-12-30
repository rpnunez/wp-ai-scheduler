# Changelog

All notable changes to this project will be documented in this file.

## [Unreleased]

### Fixed
- 2024-05-25: Fixed schedule drift issue where missed crons would reset execution time to `current_time` instead of preserving the original minute/second phase. Implemented catch-up logic in `AIPS_Interval_Calculator`.
- 2024-05-24: Fixed PHPUnit test compatibility issues by adding `: void` return type to `setUp()` and `tearDown()` methods in test classes, ensuring tests run correctly in limited mode without the WordPress test library.

### Performance
- 2024-05-24: Implemented transient caching for History statistics (`AIPS_History_Repository::get_stats`) to reduce database load on dashboard and history pages.

### Fixed
- 2024-05-23: Improved log reading performance by replacing O(N) `SplFileObject` seek with O(1) `fseek` tail reading, preventing potential crashes on large log files.
- 2024-05-22: Removed redundant HTTP response code check in `AIPS_Generator::generate_and_upload_featured_image` to improve code quality and maintainability.
### Added
- [2025-12-21 01:48:42] Added search functionality to the Generation History page to filter posts by title.
- [2024-05-22 10:00:00] Refactored Scheduler: Extracted AJAX handlers to `AIPS_Schedule_Controller`, enhanced `AIPS_Scheduler` with better topic and next_run support, and updated `AIPS_Planner` to use the Scheduler service instead of direct SQL.
- [2024-05-22 10:00:00] Made generated topic titles editable in the Planner before scheduling.
