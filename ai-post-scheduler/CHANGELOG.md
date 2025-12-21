# Change Log

All notable changes to this project will be documented in this file.

## [hunter-fix-scheduler-id-collision-15743482046909698209] - 2025-12-21
### Fixed
- Resolved a critical SQL column collision in the scheduler where `SELECT *` joins caused the Template ID to overwrite the Schedule ID, potentially updating the wrong database records.

## [wizard-add-history-search-8755203055436621760] - 2025-12-21
### Added
- [2025-12-21 01:48:42] Added search functionality to the Generation History page to filter posts by title.

## [bug-hunter/fix-logger-performance-12349646298244550070] - 2025-12-20
### Fixed
- 2024-05-23: Improved log reading performance by replacing O(N) `SplFileObject` seek with O(1) `fseek` tail reading, preventing potential crashes on large log files.

## [palette-a11y-fix-9149112686241413741] - 2025-12-20
### Fixed
- 2024-05-23: Improved accessibility by adding labels to voice search inputs and hiding decorative icons from screen readers.

## [bolt-logger-optimization-13857484038144494642] - 2025-12-20
### Improved
- 2024-05-23: Optimized `AIPS_Logger::get_logs` to use tail reading for large log files, improving performance from O(N) to O(1) for files larger than 100KB.

## [1.4.0] - 2025-12-21
- Refactor: Split 'admin.js' into modular files 'admin-planner.js' and 'admin-db.js' for better maintainability.
