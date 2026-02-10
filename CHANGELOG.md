# Changelog

All notable changes to this project will be documented in this file.

## [Unreleased]

### Added
- [2026-02-09] **AI Edit Feature**: New modal interface for regenerating individual post components (title, excerpt, content, featured image) without full post regeneration. Maintains original generation context (template, author, topic) for consistency. Available on both Generated Posts and Pending Review tabs.
  - Backend: `AIPS_AI_Edit_Controller` with 3 AJAX endpoints, `AIPS_Component_Regeneration_Service` for regeneration logic
  - Frontend: Modal UI with change tracking, loading states, and keyboard shortcuts (ESC to close, Ctrl/Cmd+S to save)
  - Security: Nonce verification, capability checks, input sanitization
  - Tests: 21 unit tests with comprehensive coverage

### Refactor
- [2026-05-27] Refactored `AIPS_Scheduler` to extract execution logic into `AIPS_Schedule_Processor`. `AIPS_Scheduler` now orchestrates while `AIPS_Schedule_Processor` handles the business logic of running schedules (locking, generation, logging).
- [2026-05-27] Updated `AIPS_Schedule_Repository` to support `LIMIT` and better joining in `get_due_schedules`.

### Added
- [2026-01-17 08:24:50] Added Developer Mode and Dev Tools page for generating template scaffolds (Voices, Structures, Templates) using AI.
- [2026-01-20 10:00:00] Added client-side search functionality to the Planner topic list, allowing users to filter brainstormed topics before scheduling.
- 2025-12-25: Added client-side search functionality to the Prompt Sections admin page and "Copy to Clipboard" button for section keys.

### Performance
- 2024-05-30: Optimized author topic generation by replacing iterative database inserts with a single bulk INSERT query, reducing database round-trips from N to 1.

### Fixed
- 2024-05-28: Fixed infinite loop in schedule processing where failed "One Time" schedules were incorrectly rescheduled for the next day. They are now deactivated upon failure.

### Added
- 2024-05-27: Added bulk deletion functionality to the Generation History page (Select All/Individual checkboxes, Delete Selected button).
- 2026-01-06: Added configurable featured image sources (AI prompt, Unsplash keywords, or Media Library selection) for templates.

### Fixed
- 2024-05-24: Fixed PHPUnit test compatibility issues by adding `: void` return type to `setUp()` and `tearDown()` methods in test classes, ensuring tests run correctly in limited mode without the WordPress test library.

### Performance
- 2024-05-25: Optimized `AIPS_History_Repository::get_history` to select only necessary columns instead of `SELECT *`, reducing memory usage and database load for large history tables.
- 2024-05-24: Implemented transient caching for History statistics (`AIPS_History_Repository::get_stats`) to reduce database load on dashboard and history pages.

### Fixed
- 2024-05-23: Improved log reading performance by replacing O(N) `SplFileObject` seek with O(1) `fseek` tail reading, preventing potential crashes on large log files.
- 2024-05-22: Removed redundant HTTP response code check in `AIPS_Generator::generate_and_upload_featured_image` to improve code quality and maintainability.
### Added
- [2025-12-21 01:48:42] Added search functionality to the Generation History page to filter posts by title.
- [2024-05-22 10:00:00] Refactored Scheduler: Extracted AJAX handlers to `AIPS_Schedule_Controller`, enhanced `AIPS_Scheduler` with better topic and next_run support, and updated `AIPS_Planner` to use the Scheduler service instead of direct SQL.
- [2024-05-22 10:00:00] Made generated topic titles editable in the Planner before scheduling.
