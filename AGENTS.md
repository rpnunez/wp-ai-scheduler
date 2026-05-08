# AGENTS.md — wp-ai-scheduler

## Project goal
Build and maintain a WordPress plugin that schedules and generates AI-written posts using the Meow Apps AI Engine plugin as the AI backend. The plugin is admin-driven and supports template scheduling, author/topic workflows, research, review flows, regeneration, sources, internal linking, embeddings, telemetry, and reliable WordPress cron automation.

## Where to work
- The plugin lives in `ai-post-scheduler/`; treat that directory as the app root.
- Run Composer and PHPUnit from `ai-post-scheduler/`, not the repository root.
- Target PHP 8.2+ and WordPress 5.8+.
- Use `ai-post-scheduler/ai-post-scheduler.php` as the bootstrap reference.
- Current plugin version: **2.5.0** (`AIPS_VERSION`).

## Critical Constraints (Anti-Duplication)
1. **Check Existing PRs:** Before making any file modifications, you MUST use the GitHub CLI (`gh pr list`) or check the repository's open pull requests.
2. **De-duplication:** If a PR already exists that addresses the same feature or modifies the same files, ABORT the task immediately.
3. **Task Tracking:** Always check `TASKS.md` at the root. If the task you are assigned is already listed as "In Progress" or "Completed," do not proceed.

## Current runtime shape

`AI_Post_Scheduler::init()` dispatches to one of four context-specific boot methods. Only subsystems needed for that request type are instantiated.

### `boot_common()` — every request
- Loads text domain, registers DI container bindings, optionally boots `AIPS_Telemetry`, and registers the `aips_source_group` taxonomy.

### `boot_cron()` — WP-Cron requests only
- Registers lazy-resolving closures for all cron hooks (schedulers, batch processors, embeddings, sources fetch, notifications, reconciler, export cleanup).
- Key services involved: `AIPS_Scheduler`, `AIPS_Author_Topics_Scheduler`, `AIPS_Author_Post_Generator`, `AIPS_Bulk_Batch_Processor`, `AIPS_Embeddings_Cron`, `AIPS_Sources_Cron`, `AIPS_Research_Controller`, `AIPS_Notifications`, `AIPS_Partial_Generation_State_Reconciler`.

### `boot_ajax()` — admin AJAX requests only
- Looks up the action in `AIPS_Ajax_Registry`; if found, instantiates exactly one controller.
- Falls back to lazy `wp_ajax_*` hook registration for plugin-owned actions not yet in the registry.

### `boot_admin()` — admin page views only
- `AIPS_Admin_Menu`, `AIPS_Admin_Assets`, `AIPS_Settings`, `AIPS_Onboarding_Wizard`, `AIPS_Admin_Bar`, `AIPS_Notifications`, `AIPS_Partial_Generation_State_Reconciler`, `AIPS_Internal_Links_Controller` (stored in global `$aips_internal_links_controller`).

### `boot_frontend()` — non-admin page loads
- `AIPS_Admin_Bar` only (toolbar visible to users with `manage_options`).

## Core conventions
- Use `AIPS_`-prefixed, underscore-separated PHP class names.
- File names mirror class names: `class-aips-my-class.php` for `AIPS_My_Class`.
- Plugin loads `vendor/autoload.php` (Composer classmap) as the primary autoloader; `AIPS_Autoloader` is registered as a fallback shim.
- Keep admin rendering in `ai-post-scheduler/templates/admin/`.
- Keep business logic in `ai-post-scheduler/includes/`.
- Use tabs and `array()` syntax in PHP to match the codebase and WordPress style.
- Add `if (!defined('ABSPATH')) { exit; }` to all plugin PHP files.
- Centralize default option values in `AIPS_Config::get_instance()->get_default_options()`.

## Architecture patterns

### DI Container
- `AIPS_Container` is a lightweight singleton container used for core service bindings.
- Key singletons registered: `AIPS_Config`, `AIPS_History_Repository`, `AIPS_History_Service`, `AIPS_Notifications_Repository`, `AIPS_Logger`, `AIPS_AI_Service`, `AIPS_Schedule_Repository`, `AIPS_Telemetry_Repository`, `AIPS_Template_Repository`.
- Interface aliases are registered for testability (e.g. `AIPS_History_Service_Interface`, `AIPS_AI_Service_Interface`, `AIPS_Logger_Interface`).
- Use `AIPS_Container::get_instance()->make(ClassName::class)` to resolve registered singletons.

### AJAX Registry
- `AIPS_Ajax_Registry` is a static map of AJAX action names → controller class names.
- `boot_ajax()` uses this registry to instantiate exactly one controller per AJAX request.
- When adding a new AJAX action, add it to `AIPS_Ajax_Registry::$map` alongside the responsible controller.

### Repositories
- Put persistence and SQL in repository classes.
- Prefer repository methods over direct `$wpdb` usage in feature code.
- Current repositories: `AIPS_History_Repository`, `AIPS_Schedule_Repository`, `AIPS_Template_Repository`, `AIPS_Authors_Repository`, `AIPS_Author_Topics_Repository`, `AIPS_Author_Topic_Logs_Repository`, `AIPS_Voices_Repository`, `AIPS_Article_Structure_Repository`, `AIPS_Prompt_Section_Repository`, `AIPS_Trending_Topics_Repository`, `AIPS_Post_Review_Repository`, `AIPS_Feedback_Repository`, `AIPS_Notifications_Repository`, `AIPS_Sources_Repository`, `AIPS_Sources_Data_Repository`, `AIPS_Taxonomy_Repository`, `AIPS_Post_Embeddings_Repository`, `AIPS_Internal_Links_Repository`, `AIPS_Metrics_Repository`, `AIPS_Telemetry_Repository`, `AIPS_Data_Management_Repository`.

### Controllers
- Register `wp_ajax_*` hooks in controller constructors.
- Keep nonce checks, capability checks, sanitization, and JSON response formatting in controllers.
- Do not put SQL in controllers.
- All controllers must be registered in `AIPS_Ajax_Registry`; instantiate via `boot_ajax()`.
- Do not re-instantiate controllers at render time — this is legacy behavior, not a pattern to copy.

### Generation context
- Prefer the context-based generation architecture for new generation or regeneration work.
- Key types: `AIPS_Generation_Context` (interface), `AIPS_Template_Context`, `AIPS_Topic_Context`, `AIPS_Generation_Context_Factory`.
- Use this abstraction instead of building new flows around raw template objects.

### Prompt assembly
- `AIPS_Prompt_Builder` is the shared/base prompt builder.
- `AIPS_Prompt_Builder_Topic` handles author-topic prompt composition.
- `AIPS_Prompt_Builder_Authors` handles author-suggestion prompts.
- Specialized builders exist for individual post components: `AIPS_Prompt_Builder_Post_Title`, `AIPS_Prompt_Builder_Post_Content`, `AIPS_Prompt_Builder_Post_Excerpt`, `AIPS_Prompt_Builder_Post_Featured_Image`, `AIPS_Prompt_Builder_Taxonomy`, `AIPS_Prompt_Builder_Article_Structure_Section`.
- `AIPS_Template_Processor` supports built-in variables and AI variables.

### History and observability
- Use `AIPS_History_Service` and `AIPS_History_Container` for meaningful operations.
- `AIPS_Generation_Logger` records per-component generation events into a container.
- Prefer structured lifecycle events for AI requests, retries, failures, automation runs, and user actions.
- `AIPS_Logger` / `AIPS_Logger_Interface` is the general-purpose logger (singleton via container).
- `AIPS_Correlation_Id` provides request/run correlation identifiers for tracing across log entries.

### Site context
- `AIPS_Config` is the centralized options registry (`get_option`, `get_default_options`, `has_option`).
- Site-wide content strategy settings are defined in `AIPS_Settings::get_content_strategy_options()`.
- `AIPS_Site_Context` reads that registry dynamically.
- If you add a site-wide content strategy setting, update the registry there.

### Partial generation recovery
- Use the existing recovery flow for incomplete generations:
  - `AIPS_Partial_Generation_Notifications`
  - `AIPS_Partial_Generation_State_Reconciler`
  - `AIPS_Component_Regeneration_Service`
  - `AIPS_Session_To_JSON`

### Unified scheduling
- `AIPS_Unified_Schedule_Service` aggregates template, author-topic, and author-post schedule types.
- The schedule UI and "Run Now" flows go through this service.

### Batch queue and bulk generation
- `AIPS_Batch_Queue_Service` splits large schedule runs into per-item WP-Cron single events.
- `AIPS_Bulk_Batch_Processor` handles async multi-item bulk jobs via `aips_bulk_batch_jobs` table.
- `AIPS_Bulk_Batch_Job_Store` persists bulk-batch job state.
- `AIPS_Bulk_Generator_Service` orchestrates seeder/planner bulk generation flows.
- Batch strategies (`author_topic_post`, `planner_post`, `trending_topic_post`) are registered in `boot_cron()`.
- Job infrastructure lives in `includes/job/`: `AIPS_Job_Scheduler`, `AIPS_Job_Dispatcher`, `AIPS_Batch_Slicer`, `AIPS_Job_Definition`, `AIPS_Slice_Configuration`, `AIPS_Job_Progress_Tracker`, `AIPS_Dispatch_Summary`.

### Resilience
- `AIPS_Resilience_Service::retry_with_backoff()` is the centralized retry method.
- Both `AIPS_Author_Topics_Scheduler` and `AIPS_Author_Post_Generator` inject and use it for exponential backoff (3 attempts: 1 s, 2 s, 4 s).

### Caching
- `AIPS_Cache` / `AIPS_Cache_Factory` provide a driver-swappable cache layer.
- Drivers: array (in-memory), DB (`aips_cache` table), Redis, WP Object Cache, session.
- Prefer `AIPS_Cache` over raw transients for plugin-internal caching.

### Language / localization
- Admin UI strings are centralized in `AIPS_Language_Store` (language-specific string arrays).
- `AIPS_Admin_L10n::get($key)` reads `aips_language` option and delegates to the store.
- English (`en`) is the default language. Add new languages by implementing `get_{language}($key)`.
- `AIPS_Settings::sanitize_language_option()` is the registered sanitize callback for `aips_language`.

### DateTime
- Use `AIPS_DateTime` for all timestamp handling; do not scatter `strtotime()` calls.
- `AIPS_DateTime::now()`, `::fromMysql()`, `->advance()`, `->timestamp()` are the primary API.
- `AIPS_Date_Time_DB_Repair` handles one-time datetime value repair migrations.

### Telemetry
- `AIPS_Telemetry` boots per-request when `aips_enable_telemetry` is enabled.
- Slow queries and slow requests are captured via constants: `AIPS_TELEMETRY_SLOW_QUERY_MS` (100 ms), `AIPS_TELEMETRY_SLOW_REQUEST_MS` (1500 ms).
- `AIPS_Telemetry_Repository` and `AIPS_Telemetry_Controller` manage storage and AJAX access.

### Sources
- `AIPS_Sources_Repository`, `AIPS_Sources_Data_Repository`, `AIPS_Sources_Fetcher`, `AIPS_Sources_Cron`, `AIPS_Sources_Controller` form the sources subsystem.
- Sources use the `aips_source_group` custom taxonomy (registered in `boot_common()`).
- `aips_fetch_sources` cron hook fires daily.

### Embeddings
- `AIPS_Embeddings_Service`, `AIPS_Embeddings_Cron`, `AIPS_Post_Embeddings_Repository`.
- Embeddings are processed via `aips_process_author_embeddings` single-event cron.

### Internal Links
- `AIPS_Internal_Links_Controller`, `AIPS_Internal_Links_Service`, `AIPS_Internal_Links_Repository`, `AIPS_Internal_Link_Inserter_Service`.
- Post indexing uses `aips_index_posts_batch` single-event cron.
- Controller is instantiated in `boot_admin()` and stored in global `$aips_internal_links_controller`.

### Taxonomy
- `AIPS_Taxonomy_Controller` and `AIPS_Taxonomy_Repository` manage plugin-level taxonomy assignments for generated posts.

### Notifications (enhanced)
- `AIPS_Notifications` is the event handler class (not just a repository wrapper).
- `AIPS_Notification_Registry`, `AIPS_Notification_Senders`, `AIPS_Notification_Template`, `AIPS_Notification_Templates`, `AIPS_Notifications_Event_Handler` form the notification pipeline.
- `AIPS_Notifications_Repository` / `AIPS_Notifications_Repository_Interface` handle persistence.

### Onboarding
- `AIPS_Onboarding_Wizard` handles first-install wizard flow via `aips_onboarding_redirect` transient.

## Admin/UI notes
- Admin menu registration lives in `AIPS_Admin_Menu::add_menu_pages()` (not `AIPS_Settings`).
- Key active pages: dashboard, templates, voices, structures, authors, research, schedule, schedule-calendar, content (generated posts), history, sources, taxonomy, internal-links, settings, system-status, seeder, telemetry (conditional), dev-tools (conditional).
- `aips-author-topics` is a hidden page accessible via URL (linked from the Authors experience).
- Some templates exist without current submenu registration: sections (prompt sections), planner, post-review-specific UI.
- Settings management is split: `AIPS_Settings` (options registration/sanitize), `AIPS_Settings_UI` (render), `AIPS_Settings_Ajax` (AJAX callbacks).
- System status is handled by `AIPS_System_Status_Controller` and `AIPS_System_Diagnostics_Service`.
- Dashboard is rendered by `AIPS_Dashboard_Controller`.

## Data access and upgrades
- Schema changes go through `AIPS_DB_Manager::get_schema()` and `dbDelta` via `AIPS_DB_Manager::install_tables()`.
- `AIPS_Upgrades` was renamed to `AIPS_DB_Migrations` (`class-aips-db-migrations.php`); `class-aips-upgrades.php` is a `class_alias` shim. All call sites use `AIPS_DB_Migrations::check_and_run()`.
- There is no standalone migrations directory.
- Current plugin tables:

| Table | Purpose |
|-------|---------|
| `aips_history` | Generation history records |
| `aips_history_log` | Structured history log entries |
| `aips_templates` | Prompt templates |
| `aips_schedule` | Template schedule records |
| `aips_voices` | Voice definitions |
| `aips_article_structures` | Article structures |
| `aips_prompt_sections` | Reusable prompt sections |
| `aips_trending_topics` | Research/trending topic results |
| `aips_authors` | Author personas and generation settings |
| `aips_author_topics` | Generated author topics and approval workflow |
| `aips_author_topic_logs` | Topic-level history and post linkage |
| `aips_topic_feedback` | Approval/rejection feedback metadata |
| `aips_notifications` | Admin toolbar/system notifications |
| `aips_sources` | Content sources |
| `aips_source_group_terms` | Source group taxonomy term mappings |
| `aips_sources_data` | Fetched source content |
| `aips_taxonomy` | Plugin taxonomy assignments for posts |
| `aips_post_embeddings` | Post embedding vectors |
| `aips_internal_links` | Internal link index |
| `aips_cache` | DB-backed cache storage |
| `aips_telemetry` | Request/query telemetry records |
| `aips_bulk_batch_jobs` | Async bulk-batch job state |

## Cron events

| Hook | Schedule | Purpose |
|------|----------|---------|
| `aips_generate_scheduled_posts` | hourly | Run due template schedules |
| `aips_generate_author_topics` | hourly | Generate topics for due authors |
| `aips_generate_author_posts` | hourly | Generate posts from approved author topics |
| `aips_scheduled_research` | daily | Automated research/trending topic collection |
| `aips_notification_rollups` | daily | Send notification digest rollups |
| `aips_cleanup_export_files` | daily | Delete old session JSON export files |
| `aips_fetch_sources` | daily | Fetch content for configured sources |
| `aips_cleanup_bulk_batch_jobs` | daily | Delete old completed/failed bulk-batch job rows |
| `aips_process_schedule_batch` | single event | Process one slice of a large template schedule run |
| `aips_process_author_topics_slice` | single event | Process one author's topic generation |
| `aips_retry_failed_author_slices_topics` | single event | Retry failed author-topic-generation slices |
| `aips_process_author_post_slice` | single event | Process one author's post generation |
| `aips_retry_failed_author_slices_posts` | single event | Retry failed author-post-generation slices |
| `aips_process_bulk_batch` | single event | Process one slice of a stored bulk-batch job |
| `aips_process_author_embeddings` | single event | Process author embedding vectors |
| `aips_index_posts_batch` | single event | Index a batch of posts for internal linking |

## Security and WordPress hygiene
- Escape output appropriately with `esc_html()`, `esc_attr()`, `esc_url()`, and `wp_kses_post()`.
- Sanitize all request data with WordPress helpers.
- Verify nonces for state-changing actions.
- Check `current_user_can('manage_options')` for admin/AJAX actions.
- Use `AIPS_Ajax_Response` for consistent, escaped JSON responses.
- Handle missing AI Engine dependency gracefully (dependency check fires on `admin_init`).

## Testing
- Tests live in `ai-post-scheduler/tests/`; run with `composer test` from `ai-post-scheduler/`.
- Test classes extend `WP_UnitTestCase`.
- `tests/bootstrap.php` provides WordPress mocks. New `includes/*.php` classes must be `require_once`'d there for limited-mode runs.
- Prefer one test file per feature/class. Test both success and failure paths.

## Useful docs
- `.github/copilot-instructions.md` for the fuller repository guide.
- `README.md` and `docs/` for feature and setup documentation.
- `ai-post-scheduler/CHANGELOG.md` for plugin release history.
- `docs/DEVELOPMENT_GUIDELINES.md` for project-specific coding and architectural guidelines that all developers and AI agents must follow.