## [3.6.6] - 2026-09-04

### Added
- **Content Indexing History:** Added domain filtering, optional verbose embedding logs, and collapsible contiguous activity groups with bulk selection.

### Fixed
- **History Accuracy:** Content indexing persistence failures now remain failed, modal summaries read nested indexing metrics, and grouped rows report in-progress items.

## [3.6.5] - 2026-08-28
- **UX:** Fixed pagination parameter reset on clearing filters and search across Generated Posts tabs.

- **UX:** Fixed pagination parameter reset on clearing filters and search across Generated Posts tabs.
### Added
- **UX:** Fixed pagination parameter reset on clearing filters and search across Generated Posts tabs.
- **Unified Semantic Vector Core**: Introduced `wp_aips_embeddings` (polymorphic store for posts, CPTs, and author topics) and `wp_aips_relationships` (precomputed cosine similarity matrix).
- **Database Migration (`migrate_to_3_6_5`)**: Automated schema upgrade backfilling legacy vectors with post type resolution and dropping legacy tables.
- **Top-Level Content Indexer Suite**: Centralized admin hub under **AI Post Scheduler → Content Indexer** featuring:
  - Interactive SVG force-directed semantic graph visualizer with live similarity threshold controls and node inspection drawer.
  - Progressive chunked backfill scanner with pause/resume controls and multi-CPT coverage counters.
  - Duplicate & cannibalization clustering audit engine.
- **AI-Powered Related Posts Presentation Layer**: Dynamic shortcode (`[aips_related_posts]`), Gutenberg block (`aips/related-posts`), auto-append single post filter, and customizable card grid and list layouts.
- **Decoupled Embeddings Provider**: Independent vector engine configuration (`aips_embeddings_provider`) with auto-discovery of Meow AI Engine custom environments (Percona Server pgvector, OpenAI, Pinecone, Qdrant, Ollama, Chroma) and WP AI Client connector fallback.
- **Vector Dimension Mismatch Guard**: Detection of dimension variance between stored vectors and active environments with one-click guided re-index.
- **Continuous Sync on Publish**: Automatically generates embeddings and updates relationship pairings when posts are published or updated.
- **Prompt Context Injection**: Injects semantically related published articles directly into AI generation prompts across all context and legacy template flows.

## [Unreleased]

- **Accessibility:** Added missing `aria-label` attributes to checkboxes in the Planner and Research admin templates to improve screen reader accessibility.

- **Performance:** Fixed N+1 queries in Generated Posts controller by batching `get_post()` calls using `_prime_post_caches()`.
### Added
- **WordPress AI Connector Routing**: Added Settings > AI controls for using all available WordPress AI connectors or an ordered allowlist, with connector-specific failover and short-lived health cooldowns. Request validation and content-policy failures are surfaced without provider shopping.
- **Prompt Context Digest**: Added bounded beginning/outline/conclusion context for stateless title and excerpt requests, preserving article-wide signal without resending unbounded bodies.

### Changed
- **Repository Caching Standardization**: Every repository now adopts `AIPS_Cacheable_Repository` with per-repository cache groups and broad-tagged policies, and resolves table names through the shared `AIPS_Repository_Tables::table()` accessor backed by `AIPS_DB_Manager::get_table()` — no repository hardcodes `$wpdb->prefix . 'aips_*'` any more. Generation-adjacent, queue-driving, and externally-joined reads are deliberately left uncached with documented rationale.
- **Embeddings Repository Caching**: `AIPS_Embeddings_Repository` caches single-object lookups, batched post lookups, counts, index statistics, and stored dimensions under the broad `embeddings` tag bumped by every write. The `wp_posts`-joined `count_indexed_for_types()` additionally carries an `embeddings_posts` tag that the new `AIPS_Embeddings_Cache_Invalidator` bumps on `transition_post_status` / `deleted_post`, so trashing, publishing, or deleting posts through native WordPress flows never serves stale index counts. Full-vector similarity reads and the backfill queue cursor stay uncached.
- **Prompt Hardening**: Source and article content are now delimited as reference data with explicit prompt-injection boundaries, metadata generation preserves voice excerpt instructions, and structured metadata schemas reject unexpected properties.
- **Title Regeneration**: Template and topic regeneration now use the same context-aware path and include saved post content when conversational replay is unavailable.
- **Stress Test: Integration (meta field) cases**: The Diagnostics > Stress Test page gained four cases that exercise the Integration generation engine end to end — a single native custom field, a batched multi-field run (the N-fields-to-one-call path), native custom fields written onto a generated **custom post type** post, and (only when ACF is installed and active) an ACF field-group case. Each drives the real `AIPS_Integration_Manager` against the page's isolated AI service and reads the written values back to verify them.
- **Stress Test: Export Results**: An "Export Results" button downloads the full run — provider/model/version snapshot, per-case status, timings, compared values, and the complete AI request/response log — as a single JSON file for sharing and analysis.
- **Meta-field template setup script**: `scripts/create-meta-field-templates.php` (WP-CLI `wp eval-file`) creates two ready-to-run Templates wired to native WordPress custom fields, for exercising integration generation without ACF.

### Changed
- **Cache Read Refactor**: Refactored `AIPS_Cacheable_Repository::cache_read` God method into smaller components to enforce Separation of Concerns.


### Fixed
- **Content Auditor Tab**: Fixed fatal error `Class "AIPS_Author_Repository" not found` in `templates/admin/tab-content-auditor.php` by correcting class name to `AIPS_Authors_Repository`.
- **Short-form AI Responses**: Reserve at least 1200 output tokens for title and excerpt requests so reasoning-capable connector models do not cut off visible responses after spending the smaller configured budget on internal reasoning. The global Max Tokens Limit remains authoritative.
- **WordPress AI Client Detection**: Treat locally registered connectors with configured credentials as available without requiring a successful remote model-catalog request during admin page loads. Live generation now surfaces the AI Client's connector/model error instead of showing a false missing-provider notice.
- **Stress Test Reliability**: Give AIPS-scoped WordPress AI Client requests a 90-second timeout, retry one transient provider failure during interactive stress tests, and provide sufficient structured-output budget for reasoning-capable models.
- **Integration Field Mappings Hardening**: The Integrations save endpoint now validates each submitted field key against its own integration's adapter (instead of assuming every row shares the first row's adapter) and rejects rows referencing an unavailable integration. Native WordPress Custom Field generation now refuses to overwrite WordPress-core-internal or AIPS-owned meta keys (`_wp_*`, `_edit_*`, `_oembed_*`, `_menu_item_*`, `_thumbnail_id`, `_aips*`) even when "Show Advanced Custom Meta Fields" is enabled, and hides those reserved keys from discovery. Single-line integration fields are sanitized as single-line text, and a non-text AI value for a text field is now rejected instead of writing the literal string `Array`. Native-meta discovery also surfaces meta registered site-wide (no post-type subtype), not just per-post-type registrations.

### Security
- **Dev Tools Gating**: Cache Monitor and the Seeder are now disabled by default and properly enforce their feature flags (`aips_cache_monitor_enabled`, `aips_developer_mode`) at every layer — menu, Diagnostics tab, page render, and AJAX handlers — closing gaps where the flag was only checked for UI visibility. The AI scaffold generator ("Dev Tools") AJAX handler now also re-checks `aips_developer_mode`.
- **MCP Bridge**: `mcp-bridge.php` is now disabled by default and can only be enabled by defining `AIPS_MCP_BRIDGE_ENABLED` and a shared-secret `AIPS_MCP_BRIDGE_TOKEN` in `wp-config.php` — it can no longer be turned on from the WordPress admin UI. All HTTP requests must now present a matching `token` field, closing a CSRF gap on this previously cookie-auth-only endpoint. See `docs/MCP_BRIDGE.md`.

## [3.5.1] - 2026-07-25

### Changed
- **Advanced Custom Meta Fields toggle for native WordPress Custom Fields**: protected/internal meta keys (`_`-prefixed) are no longer hard-rejected by the "WordPress Custom Fields" integration. They stay hidden from the fields dropdown by default, but a new "Show Advanced Custom Meta Fields" radio in the Template editor's Integrations panel lets an admin opt in to see and map them, and they can now be saved and written like any other field once selected.

## [3.5.0] - 2026-07-25

### Added
- **Native WordPress Custom Field support**: a new "WordPress Custom Fields" integration lets a Template generate content directly into plain post meta on any post type — native (`post`/`page`) or custom — with no ACF (or any other plugin) required. Fields registered via `register_post_meta()` are auto-discovered; the Template editor's Integrations panel also lets an admin hand-add any other meta key via a growable "+ Add Another Field" repeater, with protected/internal meta keys (`_`-prefixed) rejected both in the picker and on save.

## [3.4.0] - 2026-07-25

### Added
- **Post Type Awareness in History & Content**: `aips_history` now records the post type of every post it generates (backfilled for existing rows via a one-time migration). The History page and the Content page (Generated Posts / Partial Generations / Pending Review tabs) all gained a "Type" column and a post type filter, so a mixed library of `post` and custom-post-type content stays fully navigable.

### Fixed
- **History link hidden on custom post types**: The "View AI History" link/row-action was incorrectly hidden on any post type other than `post`, even when AIPS generated that post. It now shows correctly for any post type.

## [3.3.0] - 2026-07-25

### Added
- **Per-Template Post Type**: Templates can now target any public WordPress post type (native or custom, including CPTs registered by other plugins) via a new "Post Type" selector in the Template editor. The choice is locked once the template is saved — it can't be changed on an existing template — since changing it later would orphan already-generated posts and any Integrations field mappings scoped to the old type. The Categories/Tags fields now hide themselves for post types that don't support those taxonomies, and the Integrations panel's ACF field-group picker is now scoped to the template's actual post type instead of showing every field group on the site.

## [3.2.0] - 2026-07-24

### Added
- **Third-Party Plugin Bridge**: New `AIPS_Integration_Interface` contract, `AIPS_Integration_Registry`, and `AIPS_Integration_Manager` let AIPS generate content directly into fields owned by other plugins. Ships with an Advanced Custom Fields (ACF) adapter — map a template's fields to an ACF field group (with per-field custom prompts) in the Template editor's new "Third-Party Plugin Integrations" panel, and generated posts get those ACF fields populated automatically. Other plugins can register their own adapter via the `aips_integrations_registry` filter without any AIPS core changes.

## [3.0.1] - 2026-07-07

### Added
- **Generated Post Marker**: All plugin-created posts now receive `_aips_generated_post = '1'`, making them easy to identify and filter.

### Changed
- **Private Post Meta Normalization**: Standardized plugin-owned post meta on the private `_aips_*` convention and added a migration to rename legacy partial-generation keys.

## [3.0.0] - 2026-06-21

### Added
- **Dashboard Redesign**: Complete overhaul of the admin dashboard with a new layout and improved UX. ([#1774](../../pull/1774))
- **Cache Monitor**: Full driver introspection, metadata index, and 8-tab admin UI supporting Array, DB, and WP Object Cache drivers. ([#1707](../../pull/1707))
- **Diagnostics Page**: New consolidated admin page combining status checks, seeder, telemetry, and dev tools. ([#1698](../../pull/1698))
- **Multi-Category Support for Templates**: Templates now support multiple categories (v2.9.1). ([#1728](../../pull/1728))
- **Settings Page AJAX Save**: Settings page now saves without a full page reload via AJAX. ([#1717](../../pull/1717))
- **History Page Timeline Layout**: Refactored History page into an 80/20 layout with a timeline sidebar and heartbeat auto-refresh. ([#1720](../../pull/1720))
- **DST Production Seed Script**: Added DevStackTips production seeder script. ([#1756](../../pull/1756))

### Changed
- **Planner Queue Management**: Staggered bulk-scheduled 'once' topics to improve background queue efficiency.
- **Admin History**: Replaced full page reload with AJAX table reload when retrying failed generations to improve user flow.
- Refactored multiple admin UI actions to update DOM tables dynamically without a full page reload for a smoother user experience.
- **Cache Drivers**: Reduced available cache drivers to Array, WP Object Cache, and Database for simplicity and reliability. ([#1708](../../pull/1708))
- **Sources Flow**: Optimized the NunezScheduler sources flow for better performance. ([#1723](../../pull/1723))
- **Automations Tab**: Restored Automations tab header actions and fixed embedded rendering, submenu mapping, and tab navigation semantics. ([#1731](../../pull/1731), [#1742](../../pull/1742))
- **Admin Modals**: Standardized admin modal structure and button classes across the plugin. ([#1695](../../pull/1695))
- **Author Topics UX**: Refined Author Topics action UX, accessibility, and date formatting. ([#1680](../../pull/1680))
- **Campaign Detail Page**: Enhanced Campaign detail page with additional data and features. ([#1683](../../pull/1683))
- **Edit Author Modal**: Added loading indicator to the Edit Author modal. ([#1678](../../pull/1678))
- **History Lifecycle Entries**: Finalized auxiliary history entries and hidden lifecycle containers from the History view. ([#1726](../../pull/1726))
- **Generated Posts Controller**: Hoisted date and time format options outside of loops for improved performance. ([#1750](../../pull/1750))
- **AI Agent Instructions**: Consolidated and condensed AI agent instructions files. ([#1781](../../pull/1781), [#1741](../../pull/1741))
- **DevStackTips Setup Script**: Hardened idempotency, scheduling, and rollback in the DST setup script. ([#1729](../../pull/1729))
- **WP Test Bootstrap**: Hardened against partial installs and clarified env-var scope in testing documentation. ([#1674](../../pull/1674))
- **Repository-Scale Cache Hardening**: Strengthened caching across all repository layers for stability and performance. ([#1696](../../pull/1696))

### Fixed
- **Cache Index Memory Exhaustion**: Fixed infinite recursion and unsafe `maybe_serialize()` call in `AIPS_Cache_Index` that caused memory exhaustion. ([#1718](../../pull/1718))
- **DB Cache Driver**: Fixed critical issue in the Database cache driver. ([#1705](../../pull/1705))
- **DST Seeder**: Fixed various issues in the DST seeder. ([#1787](../../pull/1787))
- **Test Suite**: Fixed `pipefail` error in test configuration. ([#1753](../../pull/1753))

# Change Log

All notable changes to this project will be documented in this file.

## [2.6.1] - 2026-05-12
### Added - Campaign Wizard & Advanced Features
- **Campaign Wizard**: Multi-step guided campaign creation flow with transaction-safe persistence
  - Per-user draft storage (`aips_campaign_wizard_draft_{user_id}`) with auto-save
  - Five-step wizard: Goal → Template → Defaults → Schedule → Review
  - Template mode selection (create new or use existing)
  - Author persona assignment for topic-based workflows
  - Multi-post-type rules: configure multiple post types per campaign with individual quantities and prompt overrides
  - Advanced scheduling: time windows, day preferences, blackout dates, seasonal end dates
  - Real-time validation and summary review before finalization
- **Campaigns Dashboard**: Dedicated admin page for campaign management
  - Campaigns list with metrics: posts generated, success rate, last/next run
  - Summary statistics: total campaigns, active, archived
  - Quick actions: pause/resume, duplicate, archive
  - AJAX-powered updates with optimistic UI
- **Multi-Post-Type Support**: Single campaigns can target multiple post types
  - Per-type quantity configuration (e.g., "3 blog posts + 2 case studies per week")
  - Optional prompt override per post type
  - JSON-encoded rules stored in `post_type_rules` column
- **Advanced Scheduling Controls**: Fine-grained timing and constraints
  - Time windows: restrict generation to specific hours (HH:MM format)
  - Day preferences: limit posting to selected weekdays (M-Su checkboxes)
  - Blackout dates: skip posting on specified dates (YYYY-MM-DD format)
  - Seasonal campaigns: auto-deactivate after end date (Unix timestamp storage)
- **Database Schema**: Extended `aips_schedule` table with 10 new columns
  - `author_id bigint(20)` - linked author persona
  - `campaign_mode varchar(20)` - template/author/hybrid workflow
  - `post_type_rules longtext` - JSON array of post-type configurations
  - `blackout_dates text` - newline-separated skip dates
  - `time_window_start varchar(5)` - HH:MM start time
  - `time_window_end varchar(5)` - HH:MM end time
  - `day_preferences varchar(255)` - comma-separated day numbers (1-7)
  - `season_end_date bigint(20) unsigned` - Unix timestamp
  - `dynamic_quantity_rules text` - future extensibility
  - `campaign_metadata longtext` - flexible JSON storage
  - Indexed: `author_id`, `campaign_mode`, `season_end_date`

### Added - Operations & System Management
- **Operations Insights Dashboard**: Unified view of system operations
  - Telemetry data export (CSV/JSON) with date range filtering
  - Bulk batch jobs monitoring and export
  - Combined system status operations panel
  - Secure AJAX actions with operation-specific nonces
- **Batch Queue System**: Large-batch processing for scheduled generation
  - Threshold-based queue activation (>10 items triggers batching)
  - `AIPS_Batch_Queue_Service` for slice-based WP-Cron scheduling
  - `AIPS_Bulk_Batch_Processor` for async multi-item jobs
  - Job strategies: `author_topic_post`, `planner_post`, `trending_topic_post`
  - Persistent state in `aips_bulk_batch_jobs` table
  - Progress tracking and automatic cleanup (daily cron)
- **Post Slices**: Sub-post content management with prompt injection
  - `AIPS_Post_Slices_Repository` with bulk operations
  - Integrated with Authors admin page for prompt customization
  - Avoid-titles diversity injection to prevent repetition
  - Per-run uniqueness seed for content variation

### Added - History & Observability
- **Enhanced History Page**: Improved generation tracking and filtering
  - Duration display for completed generations
  - Direct post links from history entries
  - Type filters (manual, scheduled, bulk)
  - Creation method badges
  - Copy session JSON button
  - View Logs modal with structured event timeline
- **Source Content Fetching**: Content-type-aware parsing
  - RSS/Atom feed support with `AIPS_Sources_Fetcher`
  - JSON and HTML content extraction
  - Fallback strategies for mixed/unknown content types
  - Daily fetch cron with persistent storage

### Added - UX & Author Workflows
- **Generate Posts Button**: Authors page quick-action bulk generation
  - Modal with template selection for trending topics
  - Batch job creation for large operations
  - Progress feedback with job status tracking
- **Datetime Standardization**: Unified time handling in cron operations
  - `AIPS_DateTime` consistency across scheduler, topics, and posts
  - Eliminates 1970 UI date bugs from mixed timestamp formats

### Changed
- **Planner Queue Management**: Staggered bulk-scheduled 'once' topics to improve background queue efficiency.
- **Template Post Duplication Fix**: Resolved issue causing duplicate posts from single template runs
- **System Status Operations**: Separated nonces per operation, direct logic execution, configurable batch sizes
- **Performance Tab**: Renamed "Cache" to "Performance" with "Enable Cache System" toggle

### Technical
- Campaign wizard uses AJAX registry pattern for centralized routing
- All advanced scheduling fields properly sanitized and validated
- Supports PHP 7.4+ compatibility (no PHP 8.2-specific syntax)
- Database migrations handle column additions via `dbDelta()`
- Version bump triggers automatic schema updates on activation

## [2.4.1] - 2026-04-15
### Added
- **Telemetry filters and richer request summaries**: the Telemetry admin page now supports AJAX filtering by request type, event category, request method, page slug search, and issue-only rows.
- **Cache observability**: all `AIPS_Cache` operations now emit telemetry events so admins can inspect cache activity, hit/miss trends, and cache-heavy requests.
- **Query diagnostics**: telemetry payloads now summarize slow queries and duplicate queries when `SAVEQUERIES` is available, using `AIPS_TELEMETRY_SLOW_QUERY_MS` and bounded query samples.

### Changed
- **Planner Queue Management**: Staggered bulk-scheduled 'once' topics to improve background queue efficiency.
- **Telemetry schema**: `aips_telemetry` now stores top-level request type, event categories, cache counters, total event count, and query anomaly counters for server-side filtering and faster list rendering.

## [2.3.1] - 2026-04-09
### Performance
- **Composite DB indexes on `aips_notifications`**: Added `KEY is_read_created_at (is_read, created_at)` and `KEY dedupe_key_created_at (dedupe_key, created_at)` to eliminate filesorts on the two hottest queries (`get_unread()` / `count_unread()` and `was_recently_sent()`). Schema updated in `AIPS_DB_Manager::get_schema()` and applied via a versioned `AIPS_Upgrades` migration (`migrate_to_2_3_1`).

## [2.0.1] - 2026-03-28
### Added
- **Observability baseline metrics**: scheduler and generation reliability metrics reviewable from System Status.
  - New `AIPS_Metrics_Repository` class — collects and exposes summary metrics from existing plugin tables (including `aips_history_log` metrics log entries; no schema changes); results are cached in WordPress transients (15-minute TTL, invalidate-able via `invalidate_cache()`).
  - **Generation metrics** (windowed, default 30 days): success rate, failure rate, partial rate, average/p50/p95 generation duration, average AI calls per completed post, image-generation failure rate, scheduled-run success rate, and recent generation outcomes (last 10 records).
  - **Queue-depth surrogates**: active schedule count and approved-topic count from existing tables.
  - New `scheduler health` section in System Status — cron hook registration status, active schedule count, approved topic queue depth, and 30-day schedule success rate.
  - New `generation metrics` section in System Status — 30-day success/failure rates with counts, generation duration percentiles, average AI call count, image failure rate, and an expandable list of the last 10 generation outcomes with status and duration.
  - 20 new PHPUnit tests covering baseline metric shapes, zero-data edge cases, window-day normalization, cache invalidation, and ISO-8601 timestamp format.
- **Correlation IDs**: run-level traceability across all history records and operational notifications.
  - New `AIPS_Correlation_ID` static class (`generate()` / `get()` / `set()` / `reset()`).
  - New `AIPS_Utilities` static helpers class with a shared `generate_uuid()` method (delegates to `wp_generate_uuid4()`, falls back to `random_int()`-based UUID v4). Removes UUID duplication previously spread across `AIPS_History_Container` and `AIPS_Correlation_ID`.
  - `correlation_id varchar(36)` column (indexed) added to `aips_history` table via the existing `dbDelta` upgrade path.
  - `AIPS_History_Repository::get_by_correlation_id()` — retrieves all records for a single run in chronological order.
  - `AIPS_History_Container` auto-inherits the active correlation ID on construction (explicit `correlation_id` in metadata takes precedence); new `get_correlation_id()` accessor.
  - `AIPS_History_Repository::create()` persists `correlation_id`; `get_history()` projects and filters by it.
  - Correlation ID propagated at scheduler/generator entry points:
    - `AIPS_Schedule_Processor` — generated at the start of automated and manual runs; reset in `finally`; included in error notification payloads.
    - `AIPS_Author_Topics_Scheduler` — scoped per-author topic generation run; reset in `finally`.
    - `AIPS_Author_Post_Generator` — scoped per-author post generation run; loop wrapped in `try/catch(\Throwable)/finally`: catch logs via logger and records a structured error entry in a history container; `finally` always resets the ID. Included in exception notification payloads.
  - 16 new PHPUnit tests covering ID lifecycle, container auto-inheritance, explicit override, cross-container sharing within a run, run isolation, and repository payload correctness.

## [refactor-post-generation-flow] - 2026-02-07
### Changed
- **Planner Queue Management**: Staggered bulk-scheduled 'once' topics to improve background queue efficiency.
- **MAJOR**: Refactored post generation to use AI Engine's Chatbot feature for conversational context
  - Content, title, and excerpt now generated in a single conversation with shared context
  - AI "remembers" previously generated components, resulting in better coherence
  - All components closely linked through conversational memory (chatId)

### Added
- Added `generate_with_chatbot()` method to `AIPS_AI_Service` for chatbot-based text generation
- Added `generate_post_components_with_chatbot()` method to `AIPS_Generator` for coordinated generation
- Added `aips_chatbot_id` setting in admin interface (Settings → Chatbot ID)
- Added comprehensive history logging for each chatbot interaction step
- Added chatbot-specific tests to validate conversation continuity
- Added `CHATBOT_GENERATION.md` documentation

### Fixed
- Fixed `AIPS_Generator` constructor to properly initialize `generation_logger` without undefined property
- Updated generator hooks test for compatibility with new chatbot-based architecture

### Technical Details
- Uses `$mwai_core->simpleChatbotQuery()` with chatId for conversation continuity
- Three-step generation: content → title (with content context) → excerpt (with full context)
- Full backward compatibility: template key still provided in hooks for template contexts
- Resilience features maintained (circuit breaker, rate limiting, retries)

## [sentinel-fix-import-sql-injection] - 2025-01-06
### Security
- [2025-01-06] Fixed a Critical SQL Injection vulnerability in `AIPS_Data_Management_Import_MySQL::import` that allowed arbitrary SQL execution (e.g., `DELETE`) by enforcing a strict whitelist of allowed SQL commands (`INSERT`, `DROP`, `CREATE`, `SET`, `LOCK`, `UNLOCK`) and validating target tables.

## [wizard-run-schedule-now] - 2025-01-05
### Added
- Added "Run Schedule Now" capability to `AIPS_Scheduler` and `AIPS_Schedule_Controller`, allowing immediate execution of schedules regardless of their timing or active status.
- Added `run_schedule_now($schedule_id)` method to `AIPS_Scheduler` for manual execution logic.
- Updated `ajax_run_now` in `AIPS_Schedule_Controller` to support `schedule_id` alongside `template_id`.

### Removed
- Removed obsolete `AIPS_Activity_Repository` and `AIPS_Activity_Controller` classes and `ACTIVITY_MIGRATION_TODO.md` file as migration to `AIPS_History_Service` is complete.

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
- 2026-05-23: Improved log reading performance by replacing O(N) `SplFileObject` seek with O(1) `fseek` tail reading, preventing potential crashes on large log files.

## [palette-a11y-fix-9149112686241413741] - 2025-12-20
### Fixed
- 2026-05-23: Improved accessibility by adding labels to voice search inputs and hiding decorative icons from screen readers.

## [bolt-logger-optimization-13857484038144494642] - 2025-12-20
### Improved
- 2026-05-23: Optimized `AIPS_Logger::get_logs` to use tail reading for large log files, improving performance from O(N) to O(1) for files larger than 100KB.

## [1.4.0] - 2025-12-21
- Refactor: Split 'admin.js' into modular files 'admin-planner.js' and 'admin-db.js' for better maintainability.

## [sentinel-secure-urls-template-controller] - 2024-05-23
### Security
- [2026-05-23] Fixed a potential XSS vulnerability in `AIPS_Templates_Controller::ajax_get_template_posts` by escaping URLs using `esc_url()`.

## [sentinel-prevent-directory-listing] - 2024-05-24
### Security
- [2026-05-24] Added empty `index.php` files to all plugin subdirectories to prevent directory listing and information disclosure.
- [2026-05-24] 🛡️ Sentinel: Fixed CRITICAL Complete Data Wipes in History Deletion. Addressed vulnerability in `AIPS_History_Repository::delete_by_status()` and `clear_history()` where passing empty arguments implicitly led to `DELETE FROM table`, erasing all history. Required explicit `all` argument to protect against accidental mass deletions.
