# Changelog

All notable changes to this project will be documented in this file.

## [Unreleased]

### Added
- [2026-02-04] Added "Clone" functionality for Voices, allowing users to easily duplicate existing voice configurations.
- [2026-01-17 08:24:50] Added Developer Mode and Dev Tools page for generating template scaffolds (Voices, Structures, Templates) using AI.
- [2026-01-20 10:00:00] Added client-side search functionality to the Planner topic list, allowing users to filter brainstormed topics before scheduling.
- 2025-12-25: Added client-side search functionality to the Prompt Sections admin page and "Copy to Clipboard" button for section keys.

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
