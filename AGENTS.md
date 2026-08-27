# AGENTS.md — wp-ai-scheduler

## Canonical project context
- This file is the canonical cross-agent instruction source; keep tool-specific files brief and link here instead of duplicating it.
- Project: WordPress plugin for scheduling and generating AI-written posts through Meow Apps AI Engine.
- Plugin app root: `ai-post-scheduler/`; run Composer, PHPUnit, and plugin scripts from that directory.
- Bootstrap/version source: `ai-post-scheduler/ai-post-scheduler.php`.
- Current plugin version: **2.9.1** (`Version:` header and `AIPS_VERSION`).
- Runtime targets: PHP 8.2+ and WordPress 5.8+.

## Key paths
- Business logic and PHP classes: `ai-post-scheduler/includes/`.
- Admin templates: `ai-post-scheduler/templates/admin/`.
- Admin assets: `ai-post-scheduler/assets/`.
- Tests: `ai-post-scheduler/tests/`.
- Changelog: `ai-post-scheduler/CHANGELOG.md`.
- Deeper docs: [README.md](README.md), [docs/DEVELOPMENT_GUIDELINES.md](docs/DEVELOPMENT_GUIDELINES.md), [docs/AI_AGENT_REFERENCE.md](docs/AI_AGENT_REFERENCE.md), [docs/FEATURE_LIST.md](docs/FEATURE_LIST.md), [docs/HOOKS.md](docs/HOOKS.md), [docs/MIGRATIONS.md](docs/MIGRATIONS.md), [docs/SETUP.md](docs/SETUP.md).

## Coding conventions
- Use `AIPS_`-prefixed, underscore-separated PHP class names.
- Mirror class names in filenames: `class-aips-my-class.php` for `AIPS_My_Class`.
- Use Composer `vendor/autoload.php` as the primary autoloader; `AIPS_Autoloader` is fallback only.
- Use tabs and `array()` syntax for PHP.
- Add `if (!defined('ABSPATH')) { exit; }` to plugin PHP files.
- Centralize default option values in `AIPS_Config::get_instance()->get_default_options()`.
- Use `AIPS_DateTime` for timestamp handling.

## Architecture rules
- `AI_Post_Scheduler::init()` boots only the needed request context: common, cron, AJAX, admin, or frontend.
- Use `AIPS_Container::get_instance()->make(ClassName::class)` for registered singletons and interface aliases.
- Register every AJAX action in `AIPS_Ajax_Registry::$map` with its controller.
- Controllers register `wp_ajax_*` hooks in constructors and own nonce checks, capability checks, sanitization, and JSON responses.
- Keep SQL/persistence in repositories; avoid direct `$wpdb` in controllers or services when a repository exists.
- Prefer `AIPS_Generation_Context`, `AIPS_Template_Context`, `AIPS_Topic_Context`, and `AIPS_Generation_Context_Factory` for generation flows.
- Use shared/specialized prompt builders rather than ad hoc prompt assembly.
- Use `AIPS_History_Service`, `AIPS_History_Container`, `AIPS_Generation_Logger`, `AIPS_Logger`, and `AIPS_Correlation_Id` for lifecycle logging and tracing.
- Site-wide content strategy settings live in `AIPS_Settings::get_content_strategy_options()`.
- Localization uses `AIPS_Language_Store` and `AIPS_Admin_L10n`.

## Security and WordPress hygiene
- Escape output with `esc_html()`, `esc_attr()`, `esc_url()`, or `wp_kses_post()` as appropriate.
- Verify nonces for state-changing actions.
- Check `current_user_can('manage_options')` for admin/AJAX actions.
- Sanitize request data with WordPress helpers.
- Use `AIPS_Ajax_Response` for consistent AJAX JSON responses.
- Handle missing Meow Apps AI Engine dependency gracefully.

## Testing policy
- No local unit tests by default: do not run `composer test`, PHPUnit, or test setup commands unless the user explicitly asks or the task requires it.
- Prefer focused static/syntax checks for touched files; note unrun test suites in the final response.
- When full tests are explicitly needed, run from `ai-post-scheduler/`; use `AIPS_WP_TEST_SKIP_DB_CREATE=true` if DB creation is unavailable.

## Documentation ownership
- Keep this file concise (about 40–60 lines) and high-level.
- Put long subsystem inventories, cron hook lists, table catalogs, and workflow details in `docs/AI_AGENT_REFERENCE.md` or `docs/DEVELOPMENT_GUIDELINES.md`.
