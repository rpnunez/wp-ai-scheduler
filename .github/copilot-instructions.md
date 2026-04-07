# Copilot Instructions for AI Post Scheduler

## Repository Overview

This repository contains a WordPress plugin that schedules and generates AI-written posts using Meow Apps AI Engine. The current plugin entry point is `ai-post-scheduler/ai-post-scheduler.php`, and the current plugin version is **1.7.3**.

The plugin lives inside the `ai-post-scheduler/` subdirectory. Treat that folder as the application root for plugin work. All Composer and PHPUnit commands must be run from inside `ai-post-scheduler/`, not from the repository root.

## Technology Stack

- **Language**: PHP 8.2+
- **Platform**: WordPress 5.8+
- **Framework**: WordPress Plugin API
- **Testing**: PHPUnit 9.6 with WordPress PHPUnit helpers/mocks
- **Package Manager**: Composer (run from `ai-post-scheduler/`)
- **AI Integration**: Meow Apps AI Engine (`Meow_MWAI_Core` runtime dependency)

## Where to Work

- Work primarily inside `ai-post-scheduler/`
- Use `ai-post-scheduler/ai-post-scheduler.php` as the main bootstrap reference
- Use `ai-post-scheduler/includes/` for PHP classes
- Use `ai-post-scheduler/templates/admin/` for admin presentation templates
- Use `ai-post-scheduler/assets/` for admin CSS/JS

## Current Project Structure

```text
wp-ai-scheduler/
├── .github/
│   ├── copilot-instructions.md
│   └── workflows/
├── docs/
│   ├── FEATURE_LIST.md
│   ├── HOOKS.md
│   ├── MIGRATIONS.md
│   └── SETUP.md
├── scripts/
│   └── install-wp-tests.sh
└── ai-post-scheduler/
    ├── ai-post-scheduler.php           # Plugin bootstrap and activation/deactivation
    ├── composer.json
    ├── phpunit.xml
    ├── mcp-bridge.php
    ├── mcp-bridge-schema.json
    ├── includes/
    │   ├── class-aips-autoloader.php
    │   ├── class-aips-settings.php
    │   ├── class-aips-admin-assets.php
    │   ├── class-aips-admin-bar.php
    │   ├── class-aips-db-manager.php
    │   ├── class-aips-upgrades.php
    │   ├── class-aips-generator.php
    │   ├── class-aips-generation-context-factory.php
    │   ├── class-aips-template-context.php
    │   ├── class-aips-topic-context.php
    │   ├── interface-aips-generation-context.php
    │   ├── class-aips-prompt-builder.php
    │   ├── class-aips-prompt-builder-topic.php
    │   ├── class-aips-prompt-builder-authors.php
    │   ├── class-aips-template-processor.php
    │   ├── class-aips-scheduler.php
    │   ├── class-aips-author-topics-scheduler.php
    │   ├── class-aips-author-post-generator.php
    │   ├── class-aips-unified-schedule-service.php
    │   ├── class-aips-generated-posts-controller.php
    │   ├── class-aips-ai-edit-controller.php
    │   ├── class-aips-component-regeneration-service.php
    │   ├── class-aips-history-service.php
    │   ├── class-aips-history-container.php
    │   ├── class-aips-history-repository.php
    │   ├── class-aips-session-to-json.php
    │   ├── class-aips-notifications-repository.php
    │   ├── class-aips-partial-generation-notifications.php
    │   ├── class-aips-partial-generation-state-reconciler.php
    │   ├── class-aips-author-suggestions-service.php
    │   ├── class-aips-site-context.php
    │   ├── class-aips-*-repository.php
    │   ├── class-aips-*-controller.php
    │   └── interface-aips-*.php
    ├── templates/admin/
    │   ├── dashboard.php
    │   ├── templates.php
    │   ├── voices.php
    │   ├── structures.php
    │   ├── authors.php
    │   ├── author-topics.php
    │   ├── research.php
    │   ├── schedule.php
    │   ├── calendar.php
    │   ├── generated-posts.php
    │   ├── history.php
    │   ├── settings.php
    │   ├── system-status.php
    │   ├── seeder.php
    │   ├── dev-tools.php
    │   ├── planner.php
    │   ├── post-review.php
    │   └── sections.php
    ├── assets/css/
    │   ├── admin.css
    │   ├── admin-ai-edit.css
    │   ├── admin-bar.css
    │   ├── authors.css
    │   ├── calendar.css
    │   ├── planner.css
    │   └── research.css
    ├── assets/js/
    │   ├── admin.js
    │   ├── admin-ai-edit.js
    │   ├── admin-bar.js
    │   ├── admin-db.js
    │   ├── admin-dev-tools.js
    │   ├── admin-generated-posts.js
    │   ├── admin-history.js
    │   ├── admin-planner.js
    │   ├── admin-post-review.js
    │   ├── admin-research.js
    │   ├── admin-seeder.js
    │   ├── admin-view-session.js
    │   ├── authors.js
    │   ├── calendar.js
    │   ├── templates.js
    │   └── utilities.js
    └── tests/
```

## Bootstrap and Runtime Architecture

`AI_Post_Scheduler::init()` is the central bootstrap point. The current runtime split is:

### Admin-only classes instantiated during init
- `AIPS_DB_Manager`
- `AIPS_Settings`
- `AIPS_Admin_Assets`
- `AIPS_Voices`
- `AIPS_Templates`
- `AIPS_Templates_Controller`
- `AIPS_History`
- `AIPS_Post_Review` (stored globally as `$aips_post_review_handler`)
- `AIPS_Planner`
- `AIPS_Schedule_Controller`
- `AIPS_Generated_Posts_Controller`
- `AIPS_Research_Controller`
- `AIPS_Seeder_Admin`
- `AIPS_Data_Management`
- `AIPS_Structures_Controller`
- `AIPS_Prompt_Sections_Controller`
- `AIPS_Authors_Controller`
- `AIPS_Author_Topics_Controller`
- `AIPS_AI_Edit_Controller`
- `AIPS_Calendar_Controller`
- `AIPS_Dev_Tools` when `aips_developer_mode` is enabled

### Always-loaded schedulers/services
- `AIPS_Scheduler`
- `AIPS_Author_Topics_Scheduler`
- `AIPS_Author_Post_Generator`
- `AIPS_Post_Review_Notifications`
- `AIPS_Partial_Generation_Notifications`
- `AIPS_Partial_Generation_State_Reconciler`
- `AIPS_Admin_Bar`

## Development Setup

### Prerequisites
- PHP 8.2 or higher
- Composer 2.x
- MySQL for full WordPress integration-style testing
- WordPress test library if you want the broader suite used in CI

### Installation
```bash
cd ai-post-scheduler
composer install
```

### Running Tests
```bash
cd ai-post-scheduler
composer test
composer test:verbose
composer test:coverage

vendor/bin/phpunit tests/test-template-processor.php
```

### WordPress Test Library Setup
```bash
scripts/install-wp-tests.sh <db_name> <db_user> <db_pass> <db_host> latest
export WP_TESTS_DIR=/tmp/wordpress-tests-lib
export WP_CORE_DIR=/tmp/wordpress
cd ai-post-scheduler && composer test
```

### Important Notes
- Tests use `tests/bootstrap.php` and WordPress mocks for most runs
- Meow Apps AI Engine is required at runtime but mocked in tests
- There is **no** standalone migrations directory
- Database upgrades go through `AIPS_DB_Manager::install_tables()` and `dbDelta`

## Coding Standards

### Class Naming and Loading
- All plugin classes use the `AIPS_` prefix
- Use underscore-separated class names such as `AIPS_History_Repository`
- File names mirror class names, for example `class-aips-history-repository.php`
- The production autoloader is `AIPS_Autoloader`
- Do not add manual `require_once` calls for normal plugin classes beyond bootstrap/helper exceptions already used by the plugin

### Code Style
- Use **tabs** for indentation
- Use `array()` notation rather than `[]`
- Use WordPress-style braces and formatting
- Add `if (!defined('ABSPATH')) { exit; }` to plugin PHP files
- Follow WordPress sanitization, escaping, capability, and nonce patterns

## Architecture Patterns

### Repository Pattern
Use repositories for plugin persistence logic.

Current repository classes include:
- `AIPS_History_Repository`
- `AIPS_Schedule_Repository`
- `AIPS_Template_Repository`
- `AIPS_Authors_Repository`
- `AIPS_Author_Topics_Repository`
- `AIPS_Author_Topic_Logs_Repository`
- `AIPS_Voices_Repository`
- `AIPS_Article_Structure_Repository`
- `AIPS_Prompt_Section_Repository`
- `AIPS_Trending_Topics_Repository`
- `AIPS_Post_Review_Repository`
- `AIPS_Feedback_Repository`
- `AIPS_Notifications_Repository`

Prefer repository methods over direct `$wpdb` usage in feature code.

### Controller Pattern
- AJAX hooks belong in controller/handler constructors
- Keep permission checks, nonce checks, sanitization, and response formatting in controllers
- Keep SQL and persistence out of controllers

Current AJAX/controller-heavy classes include:
- `AIPS_Templates_Controller`
- `AIPS_Schedule_Controller`
- `AIPS_Research_Controller`
- `AIPS_Authors_Controller`
- `AIPS_Author_Topics_Controller`
- `AIPS_AI_Edit_Controller`
- `AIPS_Calendar_Controller`
- `AIPS_Generated_Posts_Controller`
- `AIPS_Prompt_Sections_Controller`
- `AIPS_Structures_Controller`
- `AIPS_History`
- `AIPS_Post_Review`
- `AIPS_DB_Manager`
- `AIPS_Data_Management`
- `AIPS_Admin_Bar`

### Hook Registration Rule
Controllers and AJAX-owning classes should be instantiated once in `AI_Post_Scheduler::init()`.

There are still some legacy render-time re-instantiation patterns in the codebase, notably around:
- `AIPS_Generated_Posts_Controller`
- `AIPS_History`

Treat those as legacy exceptions. Do not copy that pattern into new code.

### Generation Context Pattern
The generation pipeline now supports a context-based architecture in addition to legacy template-centric flows.

Key classes:
- `AIPS_Generation_Context` interface
- `AIPS_Template_Context`
- `AIPS_Topic_Context`
- `AIPS_Generation_Context_Factory`

Use this pattern when adding generation or regeneration features. It is the current abstraction for template-based and topic-based generation sources.

### Prompt Builder Hierarchy
- `AIPS_Prompt_Builder` is the shared/base prompt assembly class
- `AIPS_Prompt_Builder_Topic` handles author-topic prompt composition
- `AIPS_Prompt_Builder_Authors` handles AI author-suggestion prompt composition

### History and Observability Pattern
Use the history system for meaningful operations.

Key classes:
- `AIPS_History_Service`
- `AIPS_History_Container`
- `AIPS_History_Repository`
- `AIPS_Generation_Logger`

For important user actions, AI requests, automation runs, retries, and failures, prefer structured history events over ad-hoc logging.

### Site Context Pattern
Site-wide content strategy settings are centralized.

Key classes:
- `AIPS_Settings::get_content_strategy_options()`
- `AIPS_Site_Context`

If you add a new site-wide content strategy option, update the registry in `AIPS_Settings::get_content_strategy_options()`. `AIPS_Site_Context` reads from that registry dynamically.

### Partial Generation Recovery Pattern
The plugin has explicit support for incomplete generation states and post-generation repair.

Key classes:
- `AIPS_Partial_Generation_Notifications`
- `AIPS_Partial_Generation_State_Reconciler`
- `AIPS_Component_Regeneration_Service`
- `AIPS_Session_To_JSON`

Use these instead of inventing parallel recovery or session-export flows.

### Unified Scheduling Pattern
The Schedules admin experience now aggregates multiple schedule types.

Key class:
- `AIPS_Unified_Schedule_Service`

It normalizes:
- Template schedules
- Author topic generation schedules
- Author post generation schedules

## Security

- Escape output with `esc_html()`, `esc_attr()`, `esc_url()`, `wp_kses_post()` as appropriate
- Verify nonces for state-changing actions
- Check `current_user_can('manage_options')` on admin/AJAX actions
- Sanitize request data with WordPress helpers
- Keep persistence in repositories and use prepared queries there

## Key Classes and Purposes

| Class | File | Purpose |
|-------|------|---------|
| `AI_Post_Scheduler` | `ai-post-scheduler.php` | Main plugin singleton and bootstrap |
| `AIPS_Settings` | `includes/class-aips-settings.php` | Admin menu registration, settings registration, admin page rendering |
| `AIPS_Admin_Assets` | `includes/class-aips-admin-assets.php` | Admin CSS/JS enqueueing and localization |
| `AIPS_Admin_Bar` | `includes/class-aips-admin-bar.php` | Toolbar quick links + notification dropdown on admin and frontend |
| `AIPS_DB_Manager` | `includes/class-aips-db-manager.php` | Schema definition, install/repair/reinstall helpers |
| `AIPS_Upgrades` | `includes/class-aips-upgrades.php` | Version-based DB upgrade runner |
| `AIPS_Generator` | `includes/class-aips-generator.php` | Core AI generation pipeline |
| `AIPS_Scheduler` | `includes/class-aips-scheduler.php` | Template-schedule cron processing |
| `AIPS_Author_Topics_Scheduler` | `includes/class-aips-author-topics-scheduler.php` | Cron-driven author topic generation |
| `AIPS_Author_Post_Generator` | `includes/class-aips-author-post-generator.php` | Cron/manual generation of posts from approved author topics |
| `AIPS_Unified_Schedule_Service` | `includes/class-aips-unified-schedule-service.php` | Unified schedule view across template/author schedule types |
| `AIPS_Template_Processor` | `includes/class-aips-template-processor.php` | System variable replacement and AI-variable support |
| `AIPS_Prompt_Builder` | `includes/class-aips-prompt-builder.php` | Shared prompt assembly logic |
| `AIPS_Generation_Context_Factory` | `includes/class-aips-generation-context-factory.php` | Reconstructs generation contexts for regeneration flows |
| `AIPS_Template_Context` | `includes/class-aips-template-context.php` | Wraps template-based generation configuration |
| `AIPS_Topic_Context` | `includes/class-aips-topic-context.php` | Wraps author/topic-based generation configuration |
| `AIPS_Generated_Posts_Controller` | `includes/class-aips-generated-posts-controller.php` | Generated Posts page, pending review tab, partial generation tab, session JSON endpoints |
| `AIPS_AI_Edit_Controller` | `includes/class-aips-ai-edit-controller.php` | Component regeneration and revision AJAX endpoints |
| `AIPS_Component_Regeneration_Service` | `includes/class-aips-component-regeneration-service.php` | Regenerates title/content/image components from prior context |
| `AIPS_History_Service` | `includes/class-aips-history-service.php` | Unified history container creation and activity recording |
| `AIPS_Session_To_JSON` | `includes/class-aips-session-to-json.php` | Exports generation sessions to JSON and cleans old export files |
| `AIPS_Author_Suggestions_Service` | `includes/class-aips-author-suggestions-service.php` | AI-generated author profile suggestions based on site context |
| `AIPS_Site_Context` | `includes/class-aips-site-context.php` | Reads site-wide content strategy configuration |
| `AIPS_Notifications_Repository` | `includes/class-aips-notifications-repository.php` | CRUD-like access for admin toolbar notifications |

## Database Tables

All plugin tables use the WordPress prefix. `AIPS_DB_Manager::$tables` is the current source of truth.

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
| `aips_authors` | Author personas and author-level generation settings |
| `aips_author_topics` | Generated author topics and approval workflow |
| `aips_author_topic_logs` | Topic-level history and post linkage |
| `aips_topic_feedback` | Approval/rejection feedback metadata |
| `aips_notifications` | Admin toolbar/system notifications |

### Author Table Notes
`aips_authors` currently includes newer strategy/profile columns such as:
- `target_audience`
- `expertise_level`
- `content_goals`
- `excluded_topics`
- `preferred_content_length`
- `language`
- `max_posts_per_topic`

### Adding a Database Table
1. Add the table slug to `AIPS_DB_Manager::$tables`
2. Add the `CREATE TABLE` statement in `AIPS_DB_Manager::get_schema()`
3. Let `AIPS_Upgrades::check_and_run()` and `AIPS_DB_Manager::install_tables()` apply it through `dbDelta`
4. Create a corresponding repository class in `includes/`
5. Add PHPUnit coverage for the repository/behavior

## Admin Pages and Templates

### Registered Admin Menu Pages
All menu pages are registered in `AIPS_Settings::add_menu_pages()`.

Current slugs:
- `ai-post-scheduler` — Dashboard
- `aips-templates` — Templates
- `aips-voices` — Voices
- `aips-structures` — Article Structures
- `aips-authors` — Authors
- `aips-author-topics` — hidden Author Topics page
- `aips-research` — Research
- `aips-schedule` — Schedule
- `aips-schedule-calendar` — Schedule Calendar
- `aips-generated-posts` — Generated Posts
- `aips-history` — History
- `aips-settings` — Settings
- `aips-status` — System Status
- `aips-seeder` — Seeder
- `aips-dev-tools` — Dev Tools, only when `aips_developer_mode` is enabled

### Important Template Notes
- `templates/admin/post-review.php` exists, but post review is surfaced through the Generated Posts experience rather than a current dedicated submenu entry
- `templates/admin/sections.php` exists, but there is no current submenu registration for prompt sections
- `templates/admin/planner.php` exists and `AIPS_Planner` exposes AJAX endpoints, but there is no current top-level planner submenu

## Assets and Admin Frontend

### Global Assets
On plugin admin pages, `AIPS_Admin_Assets` enqueues:
- `aips-admin-style` from `assets/css/admin.css`
- `aips-utilities-script` from `assets/js/utilities.js`
- `aips-admin-script` from `assets/js/admin.js`

### Page/feature assets currently present
- `assets/js/admin-ai-edit.js`
- `assets/js/admin-bar.js`
- `assets/js/admin-db.js`
- `assets/js/admin-dev-tools.js`
- `assets/js/admin-generated-posts.js`
- `assets/js/admin-history.js`
- `assets/js/admin-planner.js`
- `assets/js/admin-post-review.js`
- `assets/js/admin-research.js`
- `assets/js/admin-seeder.js`
- `assets/js/admin-view-session.js`
- `assets/js/authors.js`
- `assets/js/calendar.js`
- `assets/js/templates.js`

### CSS currently present
- `assets/css/admin.css`
- `assets/css/admin-ai-edit.css`
- `assets/css/admin-bar.css`
- `assets/css/authors.css`
- `assets/css/calendar.css`
- `assets/css/planner.css`
- `assets/css/research.css`

### JavaScript globals/localized objects
- `window.AIPS.Utilities`
- `aipsAjax`
- `aipsAdminL10n`
- `aipsUtilitiesL10n`
- `aipsAuthorsL10n`
- `aipsAuthorContext`
- `aipsAdminBarL10n`

The admin bar assets are also enqueued on the frontend when the toolbar is visible for a user with `manage_options`.

## Template Variables

`AIPS_Template_Processor` currently supports standard variables plus AI-resolved custom variables.

### Built-in system variables
- `{{date}}`
- `{{year}}`
- `{{month}}`
- `{{day}}`
- `{{time}}`
- `{{site_name}}`
- `{{site_description}}`
- `{{random_number}}`
- `{{topic}}`
- `{{title}}`

### AI Variables
Custom placeholders like `{{ProductAngle}}` or `{{FrameworkChoice}}` are treated as AI variables and resolved through `process_with_ai_variables()`.

### Adding Template Variables
1. Update `AIPS_Template_Processor::get_variables()`
2. Add/update PHPUnit coverage in `tests/test-template-processor.php`
3. Update docs if the variable is part of the public extension surface

## Cron Jobs

The plugin currently schedules and clears these hooks during activation/deactivation:

| Hook | Schedule | Purpose |
|------|----------|---------|
| `aips_generate_scheduled_posts` | hourly | Run due template schedules |
| `aips_generate_author_topics` | hourly | Generate topics for due authors |
| `aips_generate_author_posts` | hourly | Generate posts from approved author topics |
| `aips_scheduled_research` | daily | Run research/trending topic collection |
| `aips_send_review_notifications` | daily | Send pending review email notifications |
| `aips_cleanup_export_files` | daily | Delete old session JSON export files |

## Testing Guidelines

- Tests live in `ai-post-scheduler/tests/`
- Test classes extend `WP_UnitTestCase`
- `tests/bootstrap.php` provides WordPress mocks for most test runs
- Prefer one test file per feature/class
- Test success and failure cases
- Inject dependencies where the class already supports it

### WordPress Mock Limitation
The bootstrap mock for `wp_kses_post()` is narrower than real WordPress. Semantic tags like headings, `blockquote`, `pre`, and `code` may be stripped in tests even though production WordPress allows more markup.

## Common Tasks

### Adding a New Feature
1. Create a new `AIPS_` class in `includes/`
2. Let the autoloader resolve it automatically
3. Instantiate it in `AI_Post_Scheduler::init()` when needed
4. Put business logic in services, persistence in repositories, and request handling in controllers
5. Add hooks/history logging where appropriate
6. Add PHPUnit coverage

### Adding an Admin Page
1. Register the page in `AIPS_Settings::add_menu_pages()`
2. Create the template in `templates/admin/`
3. Add page-specific enqueue logic in `AIPS_Admin_Assets::enqueue_admin_assets()` if needed
4. Avoid creating a new hook-owning controller inside the render callback if that controller is already instantiated during bootstrap

### Extending Generation or Regeneration
Prefer the context-based flow:
- `AIPS_Generation_Context`
- `AIPS_Template_Context`
- `AIPS_Topic_Context`
- `AIPS_Generation_Context_Factory`
- `AIPS_Component_Regeneration_Service`

Do not build new generation features around raw template objects if a generation context can be used instead.

## CI/CD

### GitHub Actions Workflows
- `phpunit-tests-wp-build.yml` — main PHPUnit workflow with WordPress/MySQL setup
- `phpunit-tests-3-build.yml` — alternative PHPUnit workflow
- `ci-pr.yml` — PR checks
- `qodana_code_quality.yml` — Qodana analysis
- `copilot-setup-steps.yml` — setup for Copilot agent sessions

All workflows target PHP 8.2.

### Known CI Behavior
New PRs from the Copilot bot can show `action_required` initially due to GitHub security approval. That is not the same as a failing test run.

## Dependencies

### Runtime
- Meow Apps AI Engine plugin (`Meow_MWAI_Core`)
- PHP 8.2+

### Development
- `phpunit/phpunit: ^9.6`
- `yoast/phpunit-polyfills: ^2.0`
- `wp-phpunit/wp-phpunit: ^6.6`

## MCP Bridge

The plugin ships `mcp-bridge.php` and `mcp-bridge-schema.json` for MCP/JSON-RPC style integration. It is not auto-loaded in standard plugin runtime.

## Documentation Files

| File | Location | Content |
|------|----------|---------|
| `FEATURE_LIST.md` | `docs/` | Feature inventory |
| `HOOKS.md` | `docs/` | `aips_*` action/filter reference |
| `MIGRATIONS.md` | `docs/` | DB migration notes/history |
| `SETUP.md` | `docs/` | Local setup guidance |
| `CHANGELOG.md` | `ai-post-scheduler/` | Plugin changelog |
| `readme.txt` | `ai-post-scheduler/` | WordPress readme |

## Important Conventions Summary

1. **Work inside `ai-post-scheduler/`** for plugin code, Composer, and PHPUnit
2. **Use repositories for persistence** and keep SQL out of controllers/templates
3. **Instantiate hook-owning classes once** during bootstrap; do not copy legacy re-instantiation patterns
4. **Use the generation context abstraction** for new generation/regeneration features
5. **Use `AIPS_Settings::get_content_strategy_options()`** as the source of truth for site-wide content strategy settings
6. **Use the history container/service pattern** for important actions, AI requests, and failures
7. **Database schema changes go through `AIPS_DB_Manager::get_schema()` + `dbDelta`**
8. **Admin menu registration lives in `AIPS_Settings`**
9. **Escape output, sanitize input, check capabilities, and verify nonces** everywhere appropriate
10. **Target PHP 8.2+ and WordPress 5.8+**