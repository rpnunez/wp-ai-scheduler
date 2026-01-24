# Change Log

All notable changes to this project will be documented in this file.

## [wizard-authors-search] - 2025-12-27
### Added
- Added client-side search functionality to the Authors list admin page.
- 
## [wizard-activity-search] - 2025-01-02
### Added
- Added server-side search functionality to the Activity Log page, allowing users to search by message and metadata.

## [wizard-clone-template] - 2025-01-01
### Added
- Added "Clone Template" functionality to allow users to easily duplicate templates with a single click.
- Added `ajax_clone_template` to `AIPS_Templates_Controller` to handle secure duplication.

## [wizard-sections-search-copy] - 2025-12-25
### Added
- Added client-side search functionality to the Prompt Sections admin page.
- Added "Copy to Clipboard" button for Prompt Section keys with visual feedback.

## [1.6.0] - 2025-12-24
### Added - Trending Topics Research Feature
- **NEW FEATURE:** AI-powered Trending Topics Research system
- Added `AIPS_Research_Service` class for AI-based trend discovery and analysis
- Added `AIPS_Trending_Topics_Repository` class for persistent topic storage
- Added `AIPS_Research_Controller` class for research workflow orchestration
- Added new database table `wp_aips_trending_topics` for storing research results
- Added "Trending Topics" admin page with filterable library interface
- Added bulk scheduling capability for trending topics
- Added topic relevance scoring system (1-100 scale)
- Added topic freshness analysis based on temporal and seasonal indicators
- Added keyword extraction and tagging for topics
- Added research statistics dashboard
- Added automated scheduled research via WordPress cron (`aips_scheduled_research`)
- Added 41 comprehensive test cases for research functionality
- Added AJAX endpoints for research operations:
  - `aips_research_topics` - Execute new research
  - `aips_get_trending_topics` - Retrieve stored topics
  - `aips_delete_trending_topic` - Remove topics
  - `aips_schedule_trending_topics` - Bulk schedule topics
- Added events for extensibility:
  - `aips_trending_topic_scheduled` - Fired when topic scheduled
  - `aips_scheduled_research_completed` - Fired after automated research

### Enhanced
- Enhanced AI Engine integration to support trend analysis prompts
- Enhanced scheduling system to support bulk operations from trending topics
- Updated plugin version to 1.6.0
- Updated documentation with Trending Topics feature information

### Technical
- Implemented Service Layer pattern for research logic
- Implemented Repository pattern for data persistence
- Implemented Controller pattern for workflow orchestration
- Added database migration system for version 1.6.0
- Added comprehensive DocBlocks for all new classes and methods
- Maintained 100% backward compatibility with existing features

## [bolt-optimize-planner-bulk-insert] - 2024-05-23
### Improved
- Optimized the Planner's "Schedule All" feature by replacing N+1 database `INSERT` queries with a single bulk `INSERT` statement, significantly reducing database load during bulk scheduling.

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
## [sentinel-secure-urls-template-controller] - 2024-05-23
### Security
- [2024-05-23] Fixed a potential XSS vulnerability in `AIPS_Templates_Controller::ajax_get_template_posts` by escaping URLs using `esc_url()`.

## [sentinel-prevent-directory-listing] - 2024-05-24
### Security
- [2024-05-24] Added empty `index.php` files to all plugin subdirectories to prevent directory listing and information disclosure.
