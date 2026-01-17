# Changelog

All notable changes to this project will be documented in this file.

## [Unreleased]

### Stability
- [2026-01-17 00:23:25] Improved schedule processing resilience by wrapping the execution loop in a try-catch block to prevent a single failure from halting the entire batch.

### Utility
- [2026-01-17 00:23:25] Added `aips_schedule_batch_size` filter to allow developers to configure the number of schedules processed per batch (default: 5).

### Architecture
- [2026-01-17 00:23:25] Refactored default data seeding logic into a dedicated `AIPS_Default_Data_Seeder` class, decoupling it from `AIPS_DB_Manager`.

### Performance
- [2026-01-17 00:23:25] Added database index on `created_at` column in `aips_history` table to optimize sorting and filtering performance.

### Fixed
- 2024-05-28: Fixed infinite loop in schedule processing where failed "One Time" schedules were incorrectly rescheduled for the next day. They are now deactivated upon failure.

### Added
- 2024-05-27: Added bulk deletion functionality to the Generation History page (Select All/Individual checkboxes, Delete Selected button).
- 2026-01-06: Added configurable featured image sources (AI prompt, Unsplash keywords, or Media Library selection) for templates.

### Fixed
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
