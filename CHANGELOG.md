# Changelog

All notable changes to this project will be documented in this file.

## [1.7.0] - 2024-06-01

### Added
- **Topic Queue System:** Replaced messy bulk schedules with a "Queue" system. Topics are added to a queue and processed by a single schedule runner.
- **Posting Matrix:** Advanced scheduling configuration modal supporting custom intervals (Specific Times, Days of Week, Day of Month).
- **Calendar View:** New default view for Schedules showing Past (Published), Pending (Review), and Future (Projected) posts in a monthly grid.
- **Topic Generator:** Enhanced "Planner" with an AJAX-powered "Generate & Append" workflow for brainstorming topics.
- **Review Workflow:** Added "Require Review" option to Templates/Schedules, setting generated posts to "Pending" status for manual approval.

### Changed
- **Scheduler Logic:** Updated `AIPS_Scheduler` to respect `post_quantity` for batch generation (removed hardcoded limit of 1).
- **Planner UI:** Complete overhaul of the Planner interface to support the new Queue and Matrix workflow.
- **Database:** Added `aips_schedule_queue` table and updated `aips_schedule` with `advanced_rules`.

### Fixed
- **Batch Processing:** Fixed a bug where recurring schedules ignored the template's post quantity setting.

## [Unreleased]

### Added
- 2024-05-27: Added bulk deletion functionality to the Generation History page (Select All/Individual checkboxes, Delete Selected button).

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
