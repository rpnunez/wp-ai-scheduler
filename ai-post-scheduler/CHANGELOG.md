[Output for brevity]

# Performance
- [2026-01-17 14:10:30] Optimized schedule batch processing by reducing redundant cache invalidations. Now invalidates stats cache once per batch instead of for every schedule update, significantly reducing database writes.

## [feature/ai-variables-template-13000089435359407644] - 2026-01-17
### Added
- [2025-05-30] Added support for "AI Variables" (e.g., `{#FrameworkName#}`) in templates. These variables are auto-defined by AI during generation and applied consistently across Title, Content, and Image prompts.

## [improvements-batch-1-17780416000157498747] - 2026-01-17
### Stability
- [2026-01-17 00:23:25] Improved schedule processing resilience by wrapping the execution loop in a try-catch block to prevent a single failure from halting the entire batch.
### Utility
- [2026-01-17 00:23:25] Added `aips_schedule_batch_size` filter to allow developers to configure the number of schedules processed per batch (default: 5).
### Architecture
- [2026-01-17 00:23:25] Refactored default data seeding logic into a dedicated `AIPS_Default_Data_Seeder` class, decoupling it from `AIPS_DB_Manager`.
### Performance
- [2026-01-17 00:23:25] Added database index on `created_at` column in `aips_history` table to optimize sorting and filtering performance.

## [atlas-refactor-settings-6580061729689372704] - 2026-01-15
### Added
- Refactor: Split `AIPS_Settings` into `AIPS_Admin_UI` and `AIPS_Settings_Manager` to improve separation of concerns.
- Security: Mask Unsplash Access Key in settings.
- Fix: Ensure `clickToConfirm` translation is available.

## [wizard-sections-search-10066793606948552100] - 2026-01-15
### Added
- 2024-05-28: Added client-side search functionality to the Prompt Sections page for easier management.

## [bolt-wizard-run-now-and-db-optimization-15710079448893206975] - 2026-01-14
### Added
- 2026-01-16: Added "Run Now" button to the Schedules list to trigger immediate execution of a specific schedule.
### Performance
- 2026-01-16: Added composite index `(is_active, next_run)` to `aips_schedule` and index `(is_active)` to `aips_templates` to optimize scheduler polling queries.
### UX
- 2026-01-16: Improved copy button feedback in Admin UI to preserve icons on standard buttons and show a checkmark on small icon-only buttons.

## [hunter-wizard-bolt-improvements-2119560824589976172] - 2026-01-14
### Stability
- 2024-05-29: Fixed race condition in schedule locking using atomic updates (Hunter).
### Added
- 2024-05-29: Added "Soft Confirm" UX pattern to all delete actions in the admin interface (Wizard).
### Performance
- 2024-05-29: Added database index on `(is_active, next_run)` to optimize scheduler polling query (Bolt).

## [improvements-hunter-wizard-bolt-9503700019691523454] - 2026-01-12
### Added
- 2026-05-24: Added "Clear All" functionality to the Trending Topics Research tab for easier data management.
- 2026-05-24: Added database index on `created_at` column in `aips_history` table to improve performance of history logs sorting.
### Security
- 2026-05-24: Enhanced input validation for schedule toggling and improved output sanitization for template testing.

## [bolt-frontend-debounce-4692804471865172902] - 2026-01-10
### Performance
- 2024-05-26: Implemented debouncing for all admin search inputs (Templates, Schedules, Voices, History) to reduce AJAX calls and DOM reflows.

## [feature/structure-search-15819586701892765601] - 2026-01-09
### Added
- 2024-05-28: Added client-side search and filtering for Article Structures in the admin interface.

## [dev-scheduler-optimization-toast-5855757406372943249] - 2026-01-08
### Added
- 2024-05-28: Added Toast Notification system to the admin interface for improved user feedback, replacing native browser alerts.
### Performance
- 2024-05-28: Optimized scheduled post processing by replacing the fixed item limit (5) with a time-bucketed loop (max 20s) to efficiently handle large backlogs.
- 2024-05-28: Added `active_next_run` composite index to the `aips_schedule` table to optimize query performance for due schedules.
### Refactor
- 2024-05-28: Refactored `AIPS_Scheduler` to delegate complex schedule retrieval logic to `AIPS_Schedule_Repository`, removing raw SQL queries from the service layer.
- 2024-05-28: Cleaned up `AIPS_Schedule_Controller` by removing dead fallback code for `toggle_active` operations.

## [wizard-settings-copy-buttons-2442782599479358735] - 2026-01-05
### Added
- 2024-05-29: Added "Copy to Clipboard" buttons to the Template Variables table in Settings for easier prompt construction.

## [feature/wizard-research-copy-16785953591766001513] - 2026-01-05
### Added
- 2024-05-27: Added "Copy Selected" button to Trending Topics research page for easier topic management.

## [copilot/sub-pr-217] - 2026-01-03
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

## [feature-topic-queue-calendar-11945007752136359060] - 2026-01-03
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

## [hunter/fix-schedule-drift-1498366529333512074] - 2025-12-30
### Fixed
- 2024-05-25: Fixed schedule drift issue where missed crons would reset execution time to `current_time` instead of preserving the original minute/second phase. Implemented catch-up logic in `AIPS_Interval_Calculator`.

## [wizard-settings-copy-btn-13674962436856224127] - 2025-12-28
### Added
- 2024-05-25: Added "Copy to Clipboard" buttons for template variables in the Settings page to improve usability.

## [full-mode-tests-5404193756326663210] - 2025-12-27
### Added
- Setup full WordPress environment in GitHub Actions for PHPUnit tests.
- Added MySQL service to `phpunit-tests.yml`.
- Added steps to install WordPress Test Library and activate plugin in CI.
### Changed
- Removed Limited Mode fallback from `tests/bootstrap.php` to ensure tests run against a full WordPress environment.

## [wizard-planner-copy-clear-18398170700848870045] - 2025-12-24
### Added
- 2024-05-24: Added "Copy Selected" and "Clear List" buttons to the Planner interface to improve workflow and data management.

## [bolt-perf-template-stats-11498504781081963158] - 2025-12-24
### Performance
- 2024-05-23: Eliminated N+1 queries on the Templates admin page by eager loading stats for history and pending schedules, reducing page load time for users with many templates.

## [wizard-clear-planner-list-16085545069383698988] - 2025-12-23
### Added
- Added "Clear List" button to Planner to reset topic generation workflow.

## [bolt-perf-templates-n-plus-one-8738878159994991775] - 2025-12-23
### Performance
- 2024-05-23: Eliminated N+1 database queries on the Templates admin page by implementing bulk data fetching for history statistics and schedule projections.

## [copilot/merge-main-into-palette-editable-topics] - 2025-12-21
### Added
- [2024-05-22 14:00:00] Enhanced Planner UI to allow inline editing of AI-generated topics before scheduling.

## [palette-editable-topics-17633147156937485405] - 2025-12-21
### Added
- [2024-05-22 14:00:00] Enhanced Planner UI to allow inline editing of AI-generated topics before scheduling.

## [hunter-fix-scheduler-id-collision-15743482046909698209] - 2025-12-21
### Fixed
- Resolved a critical SQL column collision in the scheduler where `SELECT *` joins caused the Template ID to overwrite the Schedule ID, potentially updating the wrong database records.

## [palette-a11y-fix-9149112686241413741] - 2025-12-20
### Fixed
- 2024-05-23: Improved accessibility by adding labels to voice search inputs and hiding decorative icons from screen readers.

## [bolt-logger-optimization-13857484038144494642] - 2025-12-20
### Improved
- 2024-05-23: Optimized `AIPS_Logger::get_logs` to use tail reading for large log files, improving performance from O(N) to O(1) for files larger than 100KB.

## [wizard-dashboard-link-view-all-schedules-1234567890] - 2024-05-31
### Added
- 2024-05-31: Added "View All Schedules" link to the dashboard's "Upcoming Scheduled Posts" widget for better navigation and consistency.
