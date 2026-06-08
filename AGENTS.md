# AGENTS.md — wp-ai-scheduler

## Project
WordPress plugin for scheduling and generating AI-written posts through Meow Apps AI Engine. The plugin is admin-driven and supports template/author-topic scheduling, research, review/regeneration flows, sources, internal links, embeddings, telemetry, campaigns, post slices, notifications, and WP-Cron automation.

## Work area
- Plugin app root: `ai-post-scheduler/`.
- Bootstrap reference: `ai-post-scheduler/ai-post-scheduler.php`.
- Business logic: `ai-post-scheduler/includes/`.
- Admin templates: `ai-post-scheduler/templates/admin/`.
- Composer/PHPUnit commands must run from `ai-post-scheduler/`.
- Target PHP 8.2+ and WordPress 5.8+.
- Current plugin version: **2.8.3** (`AIPS_VERSION`).

## Coding conventions
- Use `AIPS_`-prefixed, underscore-separated PHP class names.
- Mirror class names in filenames: `class-aips-my-class.php` for `AIPS_My_Class`.
- Use Composer `vendor/autoload.php` as the primary autoloader; `AIPS_Autoloader` is fallback only.
- Use tabs and `array()` syntax for PHP.
- Add `if (!defined('ABSPATH')) { exit; }` to plugin PHP files.
- Centralize default option values in `AIPS_Config::get_instance()->get_default_options()`.
- Use `AIPS_DateTime` for timestamp handling.

## Boot/runtime shape
`AI_Post_Scheduler::init()` boots only the request context needed:
- `boot_common()`: text domain, DI bindings, optional telemetry, `aips_source_group` taxonomy.
- `boot_cron()`: lazy WP-Cron closures for schedulers, batch processors, embeddings, sources, notifications, reconciler, and cleanup.
- `boot_ajax()`: resolves `AIPS_Ajax_Registry` and instantiates one mapped controller; legacy lazy hooks may remain for unmapped plugin actions.
- `boot_admin()`: admin menu/assets/settings/onboarding/admin bar/notifications/reconciler/history/internal links.
- `boot_frontend()`: admin bar only for users with `manage_options`.

## Architecture rules
- Use `AIPS_Container::get_instance()->make(ClassName::class)` for registered singletons and interface aliases.
- Register every AJAX action in `AIPS_Ajax_Registry::$map` with its controller.
- Controllers register `wp_ajax_*` hooks in constructors and own nonce checks, capability checks, sanitization, and JSON responses.
- Keep SQL/persistence in repositories; avoid direct `$wpdb` in controllers or services when a repository exists.
- Prefer generation context abstractions: `AIPS_Generation_Context`, `AIPS_Template_Context`, `AIPS_Topic_Context`, and `AIPS_Generation_Context_Factory`.
- Use shared/specialized prompt builders rather than ad hoc prompt assembly.
- Use `AIPS_History_Service`, `AIPS_History_Container`, `AIPS_Generation_Logger`, `AIPS_Logger`, and `AIPS_Correlation_Id` for lifecycle logging and tracing.
- Site-wide content strategy settings live in `AIPS_Settings::get_content_strategy_options()`.
- Localization uses `AIPS_Language_Store` and `AIPS_Admin_L10n`.

## Key subsystems
- Scheduling: `AIPS_Unified_Schedule_Service`, `AIPS_Scheduler`, `AIPS_Author_Topics_Scheduler`, `AIPS_Author_Post_Generator`.
- Batch/bulk jobs: `AIPS_Batch_Queue_Service`, `AIPS_Bulk_Batch_Processor`, `AIPS_Bulk_Batch_Job_Store`, `AIPS_Bulk_Generator_Service`, and `includes/job/`.
- Resilience: `AIPS_Resilience_Service::retry_with_backoff()`.
- Cache: `AIPS_Cache`, `AIPS_Cache_Factory`, `AIPS_Cache_Invalidation_Bus`, repository cache traits/config.
- Partial generation recovery: `AIPS_Partial_Generation_Notifications`, `AIPS_Partial_Generation_State_Reconciler`, `AIPS_Component_Regeneration_Service`, `AIPS_Session_To_JSON`.
- Sources: `AIPS_Sources_*` classes and `aips_source_group` taxonomy.
- Embeddings: `AIPS_Embeddings_Service`, `AIPS_Embeddings_Cron`, `AIPS_Post_Embeddings_Repository`.
- Internal links: `AIPS_Internal_Links_Controller`, services/repository, `aips_index_posts_batch` cron.
- Notifications: registry/senders/templates/event handler plus `AIPS_Notifications_Repository`.
- Campaigns, post slices, AI assistance, operations insights, telemetry, taxonomy, onboarding, and diagnostics each have dedicated controllers/repositories/services.

## Admin/UI
- Admin menu registration lives in `AIPS_Admin_Menu::add_menu_pages()`.
- Settings are split across `AIPS_Settings`, `AIPS_Settings_UI`, and `AIPS_Settings_Ajax`.
- Dashboard is rendered by `AIPS_Dashboard_Controller`.
- System status uses `AIPS_System_Status_Controller`, `AIPS_System_Diagnostics_Service`, and providers in `includes/diagnostics/`.
- Hidden pages include `aips-author-topics`, `aips-campaign-wizard`, and `aips-campaign-detail`.

## Data and schema
- Schema changes go through `AIPS_DB_Manager::get_schema()` and `AIPS_DB_Manager::install_tables()` using `dbDelta`.
- `AIPS_DB_Migrations::check_and_run()` is the migration entry point; `class-aips-upgrades.php` is a compatibility alias.
- There is no standalone migrations directory.
- Plugin tables include history/logs, templates, schedules, voices, structures, prompt sections, trending topics, authors/topics/logs/feedback, campaigns, post slices, notifications, sources/data/groups, taxonomy, embeddings, internal links, cache, telemetry, AI assistance, and bulk batch jobs.

- Recurring: aips_generate_scheduled_posts, aips_generate_author_topics, aips_generate_author_posts, aips_scheduled_research, aips_notification_rollups, aips_cleanup_export_files, aips_fetch_sources, aips_cleanup_bulk_batch_jobs, aips_cache_monitor_maintenance.
- Recurring: `aips_generate_scheduled_posts`, `aips_generate_author_topics`, `aips_generate_author_posts`, `aips_scheduled_research`, `aips_notification_rollups`, `aips_cleanup_export_files`, `aips_fetch_sources`, `aips_cleanup_bulk_batch_jobs`.
- Single events: `aips_process_schedule_batch`, `aips_process_author_topics_slice`, `aips_retry_failed_author_slices_topics`, `aips_process_author_post_slice`, `aips_retry_failed_author_slices_posts`, `aips_process_bulk_batch`, `aips_process_author_embeddings`, `aips_index_posts_batch`.

## Security and WordPress hygiene
- Escape output with `esc_html()`, `esc_attr()`, `esc_url()`, or `wp_kses_post()` as appropriate.
- Verify nonces for state-changing actions.
- Check `current_user_can('manage_options')` for admin/AJAX actions.
- Sanitize request data with WordPress helpers.
- Use `AIPS_Ajax_Response` for consistent AJAX JSON responses.
- Handle missing AI Engine dependency gracefully.

## Testing
```bash
cd ai-post-scheduler
composer test:setup
composer test
```
- Tests live in `ai-post-scheduler/tests/` and run in full WordPress test-library mode.
- Use `AIPS_WP_TEST_SKIP_DB_CREATE=true` if the runtime cannot create test databases.
- Docker-backed local workflow: `bash scripts/run-wp-tests-docker.sh`.
- Prefer one test file per feature/class and cover success and failure paths.

## Repository references
- Repository skills: `.codex/skills/README.md`.
- Fuller guides: `.github/copilot-instructions.md`, `README.md`, `docs/`, `docs/DEVELOPMENT_GUIDELINES.md`, `ai-post-scheduler/CHANGELOG.md`.
