# Copilot Instructions for AI Post Scheduler

## Repository Overview

This repository contains a WordPress plugin that schedules and generates AI-written posts using Meow Apps AI Engine. The plugin entry point is `ai-post-scheduler/ai-post-scheduler.php`, and the current plugin version is **2.5.0** (`AIPS_VERSION`).

The plugin lives inside the `ai-post-scheduler/` subdirectory. Treat that folder as the application root for plugin work. All Composer and PHPUnit commands must be run from inside `ai-post-scheduler/`, not from the repository root.

## Technology Stack

- **Language**: PHP 8.2+
- **Platform**: WordPress 5.8+
- **Framework**: WordPress Plugin API
- **Testing**: PHPUnit 10.5 with WordPress PHPUnit helpers/mocks
- **Package Manager**: Composer (run from `ai-post-scheduler/`)
- **AI Integration**: Meow Apps AI Engine (`Meow_MWAI_Core` runtime dependency)

## Critical Constraints (Anti-Duplication)

1. **Check Existing PRs:** Before making any file modifications, you MUST use the GitHub CLI (`gh pr list`) or check the repository's open pull requests.
2. **De-duplication:** When determining what to work on (unless given specific instructions), pull the current open PR list before deciding and choose work that is not already addressed by an open PR to avoid wasted time and resources. If a PR already exists that addresses the same feature, ABORT the task immediately.

## Where to Work

- Work primarily inside `ai-post-scheduler/`
- Use `ai-post-scheduler/ai-post-scheduler.php` as the main bootstrap reference
- Use `ai-post-scheduler/includes/` for PHP classes
- Use `ai-post-scheduler/templates/admin/` for admin presentation templates
- Use `ai-post-scheduler/assets/` for admin CSS/JS

## Runtime Architecture (v2.5.0)

`AI_Post_Scheduler::init()` dispatches to one of four context-specific boot methods. Only subsystems needed for that request type are instantiated.

### `boot_common()` — every request
- Loads text domain, registers DI container bindings, optionally boots `AIPS_Telemetry`, and registers the `aips_source_group` taxonomy.

### `boot_cron()` — WP-Cron requests only
- Registers lazy-resolving closures for cron hooks (schedulers, batch processors, embeddings, sources fetch, notifications, reconciler, export cleanup).
- Key services: `AIPS_Scheduler`, `AIPS_Author_Topics_Scheduler`, `AIPS_Author_Post_Generator`, `AIPS_Bulk_Batch_Processor`, `AIPS_Embeddings_Cron`, `AIPS_Sources_Cron`, `AIPS_Research_Controller`, `AIPS_Notifications`, `AIPS_Partial_Generation_State_Reconciler`.

### `boot_ajax()` — admin AJAX requests only
- Resolves the action in `AIPS_Ajax_Registry`.
- Instantiates exactly one mapped controller for the request.
- Falls back to lazy `wp_ajax_*` hook registration for plugin-owned actions not yet in the registry.

### `boot_admin()` — admin page views only
- `AIPS_Admin_Menu`, `AIPS_Admin_Assets`, `AIPS_Settings`, `AIPS_Onboarding_Wizard`, `AIPS_Admin_Bar`, `AIPS_Notifications`, `AIPS_Partial_Generation_State_Reconciler`, `AIPS_Internal_Links_Controller`.

### `boot_frontend()` — non-admin page loads
- `AIPS_Admin_Bar` only (toolbar visible to users with `manage_options`).

## Core Conventions

- Use `AIPS_`-prefixed, underscore-separated class names.
- File names mirror class names: `class-aips-my-class.php` for `AIPS_My_Class`.
- Composer `vendor/autoload.php` (classmap) is the primary autoloader; `AIPS_Autoloader` is a fallback shim.
- Keep business logic in `includes/` and admin rendering in `templates/admin/`.
- Use tabs and `array()` syntax in PHP to match WordPress/codebase style.
- Add `if (!defined('ABSPATH')) { exit; }` to plugin PHP files.
- Keep default options centralized in `AIPS_Config::get_instance()->get_default_options()`.

## Architecture Patterns

### DI Container
- Use `AIPS_Container` for core singleton bindings and interface aliases.
- Resolve via `AIPS_Container::get_instance()->make(ClassName::class)`.

### AJAX Registry
- `AIPS_Ajax_Registry` is the source of truth for AJAX action → controller routing.
- Add new AJAX actions to `AIPS_Ajax_Registry::$map`.

### Repositories
- Keep SQL/persistence in repositories; avoid direct `$wpdb` use in controllers/services where a repository exists.
- Core repositories include history, schedule, template, authors, author topics/logs, voices, article structures, prompt sections, trending topics, post review, feedback, notifications, sources, sources data, taxonomy, embeddings, internal links, metrics, telemetry, and data-management repositories.

### Controllers
- Register `wp_ajax_*` hooks in constructors.
- Keep nonce/capability checks, sanitization, and response formatting in controllers.
- Keep SQL out of controllers.
- Do not introduce new render-time re-instantiation patterns.

### Generation / Prompting
- Prefer generation context abstraction (`AIPS_Generation_Context`, `AIPS_Template_Context`, `AIPS_Topic_Context`, `AIPS_Generation_Context_Factory`).
- `AIPS_Prompt_Builder` is the shared base; use specialized builders for topics/authors/post components.

### History / Observability
- Use `AIPS_History_Service`, `AIPS_History_Container`, and `AIPS_Generation_Logger` for structured lifecycle logging.
- Use `AIPS_Logger` and `AIPS_Correlation_Id` for tracing and diagnostics.

### Site context / localization / datetime
- Site-wide strategy registry lives in `AIPS_Settings::get_content_strategy_options()`.
- Localization layer uses `AIPS_Language_Store` + `AIPS_Admin_L10n`.
- Standardize timestamp handling with `AIPS_DateTime`.

### Reliability and infra
- Retry logic: `AIPS_Resilience_Service::retry_with_backoff()`.
- Cache layer: `AIPS_Cache` / `AIPS_Cache_Factory` (array/DB/Redis/object-cache/session drivers).
- Unified scheduling: `AIPS_Unified_Schedule_Service`.
- Batch queue + async bulk: `AIPS_Batch_Queue_Service`, `AIPS_Bulk_Batch_Processor`, job classes in `includes/job/`.
- Subsystems: Sources, Embeddings, Internal Links, Taxonomy, Telemetry, Notifications, Onboarding.

## Admin Menu Notes

- Menu registration lives in `AIPS_Admin_Menu::add_menu_pages()`.
- Active pages include dashboard, templates, voices, structures, authors, research, schedule, schedule-calendar, content, history, sources, taxonomy, internal-links, settings, system-status, seeder, telemetry (conditional), dev-tools (conditional).
- `aips-author-topics` is a hidden page accessible by URL.

## Data Access and Upgrades

- Schema changes go through `AIPS_DB_Manager::get_schema()` + `dbDelta` via `AIPS_DB_Manager::install_tables()`.
- `AIPS_Upgrades` was renamed to `AIPS_DB_Migrations`; `class-aips-upgrades.php` is a `class_alias` shim.
- No standalone migrations directory.
- Current tables: history/history_log/templates/schedule/voices/article_structures/prompt_sections/trending_topics/authors/author_topics/author_topic_logs/topic_feedback/notifications/sources/source_group_terms/sources_data/taxonomy/post_embeddings/internal_links/cache/telemetry/bulk_batch_jobs.

## Cron Events

Recurring hooks:
- `aips_generate_scheduled_posts` (hourly)
- `aips_generate_author_topics` (hourly)
- `aips_generate_author_posts` (hourly)
- `aips_scheduled_research` (daily)
- `aips_notification_rollups` (daily)
- `aips_cleanup_export_files` (daily)
- `aips_fetch_sources` (daily)
- `aips_cleanup_bulk_batch_jobs` (daily)

Single-event hooks:
- `aips_process_schedule_batch`
- `aips_process_author_topics_slice`
- `aips_retry_failed_author_slices_topics`
- `aips_process_author_post_slice`
- `aips_retry_failed_author_slices_posts`
- `aips_process_bulk_batch`
- `aips_process_author_embeddings`
- `aips_index_posts_batch`

## Security and WordPress Hygiene

- Escape output with `esc_html()`, `esc_attr()`, `esc_url()`, `wp_kses_post()` as appropriate.
- Verify nonces for state-changing actions.
- Check `current_user_can('manage_options')` for admin/AJAX actions.
- Sanitize request data with WordPress helpers.
- Use `AIPS_Ajax_Response` for consistent AJAX JSON responses.

## Development and Testing

```bash
cd ai-post-scheduler
composer install
composer test
composer test:verbose
composer test:coverage
```

- Tests live in `ai-post-scheduler/tests/` and extend `WP_UnitTestCase`.
- `tests/bootstrap.php` provides WordPress mocks and manually loads include classes for limited-mode runs.
- Runtime requires Meow Apps AI Engine (`Meow_MWAI_Core`) but tests mock this dependency.

## Useful Docs

- `AGENTS.md`
- `README.md`
- `docs/FEATURE_LIST.md`
- `docs/HOOKS.md`
- `docs/MIGRATIONS.md`
- `docs/SETUP.md`
- `docs/DEVELOPMENT_GUIDELINES.md`
- `ai-post-scheduler/CHANGELOG.md`
