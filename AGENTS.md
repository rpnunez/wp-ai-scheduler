# AGENTS.md — wp-ai-scheduler

## Project goal
Build and maintain a WordPress plugin that schedules and generates AI-written posts using the Meow Apps AI Engine plugin as the AI backend. The plugin is admin-driven and supports template scheduling, author/topic workflows, research, review flows, regeneration, and reliable WordPress cron automation.

## Where to work
- The plugin lives in `ai-post-scheduler/`; treat that directory as the app root.
- Run Composer and PHPUnit from `ai-post-scheduler/`, not the repository root.
- Target PHP 8.2+ and WordPress 5.8+.
- Use `ai-post-scheduler/ai-post-scheduler.php` as the bootstrap reference.

## Current runtime shape
- Admin bootstrap happens in `AI_Post_Scheduler::init()`.
- Admin-only classes are instantiated there for settings, templates, voices, history, schedules, research, authors, generated posts, AI edit, calendar, structures, prompt sections, seeding, and data management.
- Always-loaded runtime services include:
  - `AIPS_Scheduler`
  - `AIPS_Author_Topics_Scheduler`
  - `AIPS_Author_Post_Generator`
  - `AIPS_Post_Review_Notifications`
  - `AIPS_Partial_Generation_Notifications`
  - `AIPS_Partial_Generation_State_Reconciler`
  - `AIPS_Admin_Bar`

## Core conventions
- Use `AIPS_`-prefixed, underscore-separated PHP class names.
- Rely on the plugin autoloader; avoid new manual `require_once` calls for normal plugin classes.
- Keep admin rendering in `ai-post-scheduler/templates/admin/`.
- Keep business logic in `ai-post-scheduler/includes/`.
- Use tabs and `array()` syntax in PHP to match the codebase and WordPress style.

## Architecture patterns

### Repositories
- Put persistence and SQL in repository classes.
- Prefer repository methods over direct `$wpdb` usage in feature code.
- Current repositories include history, schedule, template, authors, author topics, author topic logs, voices, article structures, prompt sections, trending topics, post review, feedback, and notifications.

### Controllers
- Register `wp_ajax_*` hooks in controller/handler constructors.
- Keep nonce checks, capability checks, sanitization, and JSON response formatting in controllers.
- Do not put SQL in controllers.
- Instantiate hook-owning classes once during bootstrap; do not add new render-time re-instantiation patterns.
- Some older render callbacks still re-instantiate classes such as generated posts/history handlers; treat that as legacy, not precedent.

### Generation context
- Prefer the context-based generation architecture for new generation or regeneration work.
- Key types:
  - `AIPS_Generation_Context`
  - `AIPS_Template_Context`
  - `AIPS_Topic_Context`
  - `AIPS_Generation_Context_Factory`
- Use this abstraction instead of building new flows around raw template objects where possible.

### Prompt assembly
- `AIPS_Prompt_Builder` is the shared/base prompt builder.
- `AIPS_Prompt_Builder_Topic` handles author-topic prompt composition.
- `AIPS_Prompt_Builder_Authors` handles author suggestion prompts.
- `AIPS_Template_Processor` supports built-in variables and AI variables.

### History and observability
- Use `AIPS_History_Service` and `AIPS_History_Container` for meaningful operations.
- Prefer structured lifecycle events for AI requests, retries, failures, automation runs, and user actions.

### Site context
- Site-wide content strategy settings are defined centrally in `AIPS_Settings::get_content_strategy_options()`.
- `AIPS_Site_Context` reads that registry dynamically.
- If you add a site-wide content strategy setting, update the registry there.

### Partial generation recovery
- Use the existing recovery flow for incomplete generations:
  - `AIPS_Partial_Generation_Notifications`
  - `AIPS_Partial_Generation_State_Reconciler`
  - `AIPS_Component_Regeneration_Service`
  - `AIPS_Session_To_JSON`

### Unified scheduling
- The schedule experience aggregates multiple schedule types through `AIPS_Unified_Schedule_Service`.
- It normalizes template schedules, author topic generation schedules, and author post generation schedules.

## Admin/UI notes
- Admin menu registration lives in `AIPS_Settings::add_menu_pages()`.
- Key active pages include dashboard, templates, voices, structures, authors, research, schedule, calendar, generated posts, history, settings, system status, seeder, and optional dev tools.
- `aips-author-topics` is a hidden page linked from the Authors experience.
- Some templates exist without current submenu registration, including prompt sections, planner, and post-review-specific UI.

## Data access and upgrades
- Schema changes go through `AIPS_DB_Manager::get_schema()` and `dbDelta` via `AIPS_DB_Manager::install_tables()`.
- There is no standalone migrations directory.
- Current plugin tables include history, history log, templates, schedule, voices, article structures, prompt sections, trending topics, authors, author topics, author topic logs, topic feedback, and notifications.

## Security and WordPress hygiene
- Escape output appropriately with `esc_html()`, `esc_attr()`, `esc_url()`, and `wp_kses_post()`.
- Sanitize all request data with WordPress helpers.
- Verify nonces for state-changing actions.
- Check `current_user_can('manage_options')` for admin/AJAX actions.
- Handle missing AI Engine dependency gracefully.

## Useful docs
- `.github/copilot-instructions.md` for the fuller repository guide.
- `README.md` and `docs/` for feature and setup documentation.
- `ai-post-scheduler/CHANGELOG.md` for plugin release history.
- `docs/DEVELOPMENT_GUIDELINES.md` for project-specific coding and architectural guidelines that all developers and AI agents must follow.