# Change Log

All notable changes to this project will be documented in this file.

## [Unreleased]
### Added
- Added "Copy Selected" and "Clear List" buttons to the Planner interface to improve workflow and data management.

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
