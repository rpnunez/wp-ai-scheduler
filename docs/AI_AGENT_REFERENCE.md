# AI Agent Reference — wp-ai-scheduler

This is the long-form architecture reference for AI assistants working in this repository. Keep short, mandatory rules in [`../AGENTS.md`](../AGENTS.md); put durable architectural context here.

## Project overview

AI Post Scheduler is a WordPress plugin for scheduling and generating AI-written posts through Meow Apps AI Engine. It supports template and author-topic scheduling, research, review and regeneration flows, sources, internal links, embeddings, telemetry, campaigns, post slices, notifications, and WP-Cron automation.

- Plugin app root: `ai-post-scheduler/`.
- Bootstrap reference: `ai-post-scheduler/ai-post-scheduler.php`.
- Business logic: `ai-post-scheduler/includes/`.
- Admin templates: `ai-post-scheduler/templates/admin/`.
- Admin assets: `ai-post-scheduler/assets/`.
- Composer and PHPUnit commands run from `ai-post-scheduler/`.
- Target runtime: PHP 8.2+ and WordPress 5.8+.
- Current plugin version: `2.9.1` (`AIPS_VERSION`).
- Runtime AI dependency: Meow Apps AI Engine (`Meow_MWAI_Core`), which must be handled gracefully when missing.

## Boot and runtime shape

`AI_Post_Scheduler::init()` boots only the request context needed for the current request.

### `boot_common()` — every request

- Loads the text domain.
- Registers dependency-injection container bindings.
- Optionally boots telemetry.
- Registers the `aips_source_group` taxonomy.

### `boot_cron()` — WP-Cron requests only

Registers lazy WP-Cron closures for schedulers, batch processors, embeddings, sources, notifications, reconciliation, and cleanup. Important services include:

- `AIPS_Scheduler`
- `AIPS_Author_Topics_Scheduler`
- `AIPS_Author_Post_Generator`
- `AIPS_Bulk_Batch_Processor`
- `AIPS_Embeddings_Cron`
- `AIPS_Sources_Cron`
- `AIPS_Research_Controller`
- `AIPS_Notifications`
- `AIPS_Partial_Generation_State_Reconciler`

### `boot_ajax()` — admin AJAX requests only

- Resolves the current action through `AIPS_Ajax_Registry`.
- Instantiates one mapped controller for the request.
- Legacy lazy `wp_ajax_*` hooks may remain for plugin-owned actions not yet mapped.

### `boot_admin()` — admin page views only

Initializes admin menu, assets, settings, onboarding, admin bar, notifications, reconciler, history, and internal links features.

### `boot_frontend()` — non-admin page loads

Boots only the admin bar for users with `manage_options`.

## Core conventions

- Use `AIPS_`-prefixed, underscore-separated PHP class names.
- Mirror class names in filenames: `class-aips-my-class.php` for `AIPS_My_Class`.
- Use Composer `vendor/autoload.php` as the primary autoloader; `AIPS_Autoloader` is a fallback shim only.
- Keep business logic in `includes/` and rendering in `templates/admin/`.
- Use tabs and `array()` syntax for PHP.
- Add `if (!defined('ABSPATH')) { exit; }` to plugin PHP files.
- Centralize default option values in `AIPS_Config::get_instance()->get_default_options()`.
- Use `AIPS_DateTime` for timestamp handling.

## Architecture patterns

### Dependency injection

- Use `AIPS_Container` for core singleton bindings and interface aliases.
- Resolve registered dependencies with `AIPS_Container::get_instance()->make(ClassName::class)`.

### AJAX controllers

- `AIPS_Ajax_Registry::$map` is the source of truth for action-to-controller routing.
- Controllers register `wp_ajax_*` hooks in constructors.
- Controllers own nonce checks, capability checks, sanitization, validation, and JSON responses.
- Use `AIPS_Ajax_Response` for consistent JSON payloads.
- Keep SQL out of controllers.

### Repositories and persistence

- Keep SQL and persistence in repositories.
- Avoid direct `$wpdb` in controllers or services when a repository exists.
- Core repository areas include history, schedules, templates, authors, author topics/logs, voices, article structures, prompt sections, trending topics, review/feedback, notifications, sources, taxonomy, embeddings, internal links, metrics, telemetry, data management, and bulk batch jobs.

### Generation and prompting

- Prefer generation context abstractions: `AIPS_Generation_Context`, `AIPS_Template_Context`, `AIPS_Topic_Context`, and `AIPS_Generation_Context_Factory`.
- Use shared and specialized prompt builders instead of ad hoc prompt assembly.
- Keep prompt composition isolated from transport/execution code.
- Use `AIPS_History_Service`, `AIPS_History_Container`, and `AIPS_Generation_Logger` for lifecycle logging.
- Use `AIPS_Logger` and `AIPS_Correlation_Id` for tracing and diagnostics.

### Reliability and infrastructure

- Retry logic lives in `AIPS_Resilience_Service::retry_with_backoff()`.
- Cache services include `AIPS_Cache`, `AIPS_Cache_Factory`, `AIPS_Cache_Invalidation_Bus`, and repository cache traits/config.
- Scheduling is coordinated through `AIPS_Unified_Schedule_Service`, `AIPS_Scheduler`, `AIPS_Author_Topics_Scheduler`, and `AIPS_Author_Post_Generator`.
- Batch and bulk work uses `AIPS_Batch_Queue_Service`, `AIPS_Bulk_Batch_Processor`, `AIPS_Bulk_Batch_Job_Store`, `AIPS_Bulk_Generator_Service`, and classes in `includes/job/`.
- Partial generation recovery uses `AIPS_Partial_Generation_Notifications`, `AIPS_Partial_Generation_State_Reconciler`, `AIPS_Component_Regeneration_Service`, and `AIPS_Session_To_JSON`.

## Subsystems

- Sources: `AIPS_Sources_*` classes and `aips_source_group` taxonomy.
- Embeddings: `AIPS_Embeddings_Service`, `AIPS_Embeddings_Cron`, and `AIPS_Post_Embeddings_Repository`.
- Internal links: `AIPS_Internal_Links_Controller`, related services/repository, and `aips_index_posts_batch` cron.
- Notifications: registry, senders, templates, event handler, and `AIPS_Notifications_Repository`.
- Campaigns, post slices, AI assistance, operations insights, telemetry, taxonomy, onboarding, and diagnostics each have dedicated controllers, repositories, or services.
- Site-wide content strategy settings live in `AIPS_Settings::get_content_strategy_options()`.
- Localization uses `AIPS_Language_Store` and `AIPS_Admin_L10n`.

## Admin/UI notes

- Menu registration lives in `AIPS_Admin_Menu::add_menu_pages()`.
- Settings are split across `AIPS_Settings`, `AIPS_Settings_UI`, and `AIPS_Settings_Ajax`.
- Dashboard rendering is coordinated by `AIPS_Dashboard_Controller`.
- System status uses `AIPS_System_Status_Controller`, `AIPS_System_Diagnostics_Service`, and providers in `includes/diagnostics/`.
- Hidden pages include `aips-author-topics`, `aips-campaign-wizard`, and `aips-campaign-detail`.

## Data and schema

- Schema changes go through `AIPS_DB_Manager::get_schema()` and `AIPS_DB_Manager::install_tables()` using `dbDelta`.
- `AIPS_DB_Migrations::check_and_run()` is the migration entry point.
- `class-aips-upgrades.php` is a compatibility alias.
- There is no standalone migrations directory.
- Plugin tables include history/logs, templates, schedules, voices, structures, prompt sections, trending topics, authors/topics/logs/feedback, campaigns, post slices, notifications, sources/data/groups, taxonomy, embeddings, internal links, cache, telemetry, AI assistance, and bulk batch jobs.

## Cron events

Recurring hooks:

- `aips_generate_scheduled_posts`
- `aips_generate_author_topics`
- `aips_generate_author_posts`
- `aips_scheduled_research`
- `aips_notification_rollups`
- `aips_cleanup_export_files`
- `aips_fetch_sources`
- `aips_cleanup_bulk_batch_jobs`
- `aips_cache_monitor_maintenance`

Single-event hooks:

- `aips_process_schedule_batch`
- `aips_process_author_topics_slice`
- `aips_retry_failed_author_slices_topics`
- `aips_process_author_post_slice`
- `aips_retry_failed_author_slices_posts`
- `aips_process_bulk_batch`
- `aips_process_author_embeddings`
- `aips_index_posts_batch`

## Security and WordPress hygiene

- Escape output with `esc_html()`, `esc_attr()`, `esc_url()`, or `wp_kses_post()` as appropriate.
- Verify nonces for state-changing actions.
- Check `current_user_can('manage_options')` for admin/AJAX actions.
- Sanitize request data with WordPress helpers.
- Use `AIPS_Ajax_Response` for consistent AJAX JSON responses.
- Handle missing AI Engine dependency gracefully.

## Testing and local execution policy

- Tests live in `ai-post-scheduler/tests/` and run in full WordPress test-library mode.
- Preferred full workflow from `ai-post-scheduler/`: `composer test:setup` then `composer test`.
- Docker-backed local workflow: `bash scripts/run-wp-tests-docker.sh`.
- Use `AIPS_WP_TEST_SKIP_DB_CREATE=true` only when the runtime cannot create test databases.
- **No local unit-test emulation policy:** do not invent or run ad hoc local unit-test shims that bypass the WordPress test library. If the local environment cannot run the supported WordPress/PHPUnit or Docker test workflow, document the limitation and provide the exact supported command that should be run in a proper environment.

## Maintained AI surfaces

- Root rules: `AGENTS.md`.
- Long-form architecture: `docs/AI_AGENT_REFERENCE.md`.
- Copilot behavior: `.github/copilot-instructions.md`.
- Canonical skill checklists: `.codex/skills/*/SKILL.md`.
- Copilot prompt pointers: `.github/prompts/skills/*.prompt.md`.
- Maintained agents: `.github/agents/`.
