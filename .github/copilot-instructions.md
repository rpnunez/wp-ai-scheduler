# Copilot Instructions for AI Post Scheduler

## Repository Overview

This is a WordPress plugin (version 1.7.0) that schedules AI-generated posts using Meow Apps AI Engine. The plugin provides a complete admin interface for creating templates, managing schedules, authors, voices, article structures, and automatically generating blog content with AI.

The plugin lives entirely inside the `ai-post-scheduler/` subdirectory. All composer and phpunit commands must be run from inside that subdirectory (`cd ai-post-scheduler`).

## Technology Stack

- **Language**: PHP 8.2 (minimum required)
- **Platform**: WordPress 5.8+
- **Framework**: WordPress Plugin API
- **Testing**: PHPUnit 9.6 with WordPress PHPUnit library
- **Package Manager**: Composer (run from `ai-post-scheduler/`)
- **AI Integration**: Meow Apps AI Engine plugin (`Meow_MWAI_Core` class, required dependency)

## Project Structure

```
wp-ai-scheduler/                 # Repository root
├── .github/
│   ├── copilot-instructions.md  # This file
│   └── workflows/               # GitHub Actions CI workflows
├── docs/                        # Developer documentation
│   ├── FEATURE_LIST.md          # Full feature inventory
│   ├── HOOKS.md                 # All action/filter hooks
│   ├── MIGRATIONS.md            # DB migration notes
│   └── SETUP.md                 # Post-clone setup guide
├── scripts/
│   └── install-wp-tests.sh      # WordPress test library installer
├── ai-post-scheduler/           # Plugin directory (work here)
│   ├── ai-post-scheduler.php    # Entry point, plugin header, main class
│   ├── composer.json            # PHP dependencies (PHP ≥8.2)
│   ├── phpunit.xml              # PHPUnit config (bootstrap: tests/bootstrap.php)
│   ├── mcp-bridge.php           # MCP/JSON-RPC bridge for AI tool integration
│   ├── includes/                # All PHP classes (AIPS_* prefix)
│   │   ├── class-aips-autoloader.php         # PSR-style autoloader for AIPS_* classes
│   │   ├── class-aips-settings.php           # Admin menu + settings (add_menu_pages here)
│   │   ├── class-aips-admin-assets.php       # Script/style enqueueing
│   │   ├── class-aips-db-manager.php         # DB schema install/upgrade via dbDelta
│   │   ├── class-aips-upgrades.php           # Version upgrade runner
│   │   ├── class-aips-config.php             # Singleton config/feature flags
│   │   ├── class-aips-generator.php          # AI content generation pipeline
│   │   ├── class-aips-scheduler.php          # WordPress cron hooks
│   │   ├── class-aips-ai-service.php         # Interface to Meow Apps AI Engine
│   │   ├── class-aips-image-service.php      # Image generation (AI + Unsplash)
│   │   ├── class-aips-template-processor.php # Template variable processing
│   │   ├── class-aips-prompt-builder.php     # Prompt assembly for generation
│   │   ├── class-aips-post-creator.php       # WordPress post insertion
│   │   ├── class-aips-history-service.php    # Unified history logging
│   │   ├── class-aips-logger.php             # General purpose logger
│   │   ├── class-aips-resilience-service.php # Retry + circuit breaker logic
│   │   ├── class-aips-research-service.php   # Trending topics research
│   │   ├── class-aips-embeddings-service.php # Semantic similarity for topic dedup
│   │   ├── class-aips-content-auditor.php    # Content quality auditing
│   │   ├── class-aips-*-repository.php       # DB layer (use these, not $wpdb directly)
│   │   ├── class-aips-*-controller.php       # AJAX endpoint controllers
│   │   └── interface-aips-*.php              # Interfaces
│   ├── templates/admin/         # PHP templates for admin pages
│   ├── assets/
│   │   ├── css/                 # admin.css, authors.css, calendar.css,
│   │   │                        # planner.css, research.css, admin-ai-edit.css
│   │   └── js/                  # utilities.js, admin.js, authors.js,
│   │                            # calendar.js, admin-planner.js, admin-research.js,
│   │                            # admin-activity.js, admin-ai-edit.js, admin-db.js,
│   │                            # admin-dev-tools.js, admin-generated-posts.js,
│   │                            # admin-post-review.js, admin-seeder.js, admin-view-session.js
│   ├── tests/                   # PHPUnit tests (60+ test files)
│   │   ├── bootstrap.php        # WordPress mock environment for tests
│   │   └── test-*.php           # Test files
│   └── vendor/                  # Composer dependencies (not committed)
```

## Development Setup

### Prerequisites
- PHP 8.2 or higher
- Composer 2.x
- MySQL (for full WordPress integration tests)
- WordPress test library (installed via `scripts/install-wp-tests.sh`)

### Installation
```bash
# From the plugin directory
cd ai-post-scheduler
composer install
```

### Running Tests
```bash
# All commands run from ai-post-scheduler/
cd ai-post-scheduler
composer test                # Run all tests
composer test:verbose        # Run with verbose output
composer test:coverage       # Generate HTML coverage report in coverage/

# Run a specific test file
vendor/bin/phpunit tests/test-template-processor.php
```

### WordPress Integration Tests (Full Suite with DB)
The CI uses a WordPress test library. To install it locally:
```bash
# From repository root
scripts/install-wp-tests.sh <db_name> <db_user> <db_pass> <db_host> latest
export WP_TESTS_DIR=/tmp/wordpress-tests-lib
export WP_CORE_DIR=/tmp/wordpress
cd ai-post-scheduler && composer test
```

### Important Notes
- Tests run without a full WordPress installation; `tests/bootstrap.php` provides WordPress function mocks
- The plugin requires Meow Apps AI Engine (`Meow_MWAI_Core` class) at runtime; tests mock AI calls
- **All composer/phpunit commands must be run from inside `ai-post-scheduler/`**, not the repo root
- There is **no** `migrations/` directory; DB schema upgrades go through `AIPS_DB_Manager::install_tables()` (uses `dbDelta`)

## Coding Standards

### Class Naming
- All classes use the `AIPS_` prefix (AI Post Scheduler)
- Use underscores: `class AIPS_History_Repository`
- File names mirror class names: `class-aips-history-repository.php`
- Autoloader (`AIPS_Autoloader`) maps `AIPS_Foo_Bar` → `includes/class-aips-foo-bar.php` automatically
- The autoloader is the **only** class loader in production; no Composer autoload is used at runtime

### Code Style
- Use **tabs** for indentation (WordPress standard)
- Opening braces on the same line as methods and control structures
- Use `array()` notation (not `[]`) to follow WordPress coding style conventions
- Always add `if (!defined('ABSPATH')) { exit; }` at the top of every PHP file
- Follow WordPress PHP coding standards

### Architecture Patterns

#### Repository Pattern (Database Access)
**Always use repositories instead of `$wpdb` directly.**
- `AIPS_History_Repository` — generation history records
- `AIPS_Schedule_Repository` — scheduled post configurations
- `AIPS_Template_Repository` — prompt templates
- `AIPS_Authors_Repository` — author personas
- `AIPS_Author_Topics_Repository` — per-author topic queue
- `AIPS_Author_Topic_Logs_Repository` — topic generation logs
- `AIPS_Voices_Repository` — writing voices/styles
- `AIPS_Article_Structure_Repository` — article structure templates
- `AIPS_Prompt_Section_Repository` — reusable prompt sections
- `AIPS_Trending_Topics_Repository` — researched trending topics
- `AIPS_Post_Review_Repository` — post review workflow
- `AIPS_Feedback_Repository` — topic feedback storage

#### Controller Pattern (AJAX Endpoints)
- Controllers register AJAX hooks in their constructors via `add_action('wp_ajax_...')`.
- **Guideline**: Controllers SHOULD be instantiated once in `AI_Post_Scheduler::init()`. Avoid instantiating a controller again inside a render callback (e.g., in `add_submenu_page`), as this can cause duplicate AJAX hook registrations and inconsistent state. Store the controller reference after initial instantiation and pass it to the render callback via `use` in a closure, or use static render methods on the controller class. There are a few existing legacy exceptions (e.g., in `AIPS_Settings::render_dashboard_page()` and `render_generated_posts_page()`); treat these as tech debt and do not copy this pattern in new code.
- All AJAX handlers call `check_ajax_referer('aips_ajax_nonce', 'nonce')` and `current_user_can('manage_options')`.

#### Admin Menu (AIPS_Settings)
- **All admin menu pages are registered in `AIPS_Settings::add_menu_pages()`** (not a separate admin menu class).
- The main menu slug is `ai-post-scheduler`; sub-pages use slugs like `aips-schedule`, `aips-templates`, `aips-voices`, `aips-authors`, etc.

#### Service Classes (Business Logic)
- `AIPS_AI_Service` — wraps Meow Apps AI Engine calls
- `AIPS_Image_Service` — generates/fetches featured images
- `AIPS_History_Service` — unified logging across generation pipeline
- `AIPS_Research_Service` — trending topic research via AI
- `AIPS_Resilience_Service` — retry logic and circuit breaker
- `AIPS_Embeddings_Service` — semantic deduplication of topics
- `AIPS_Content_Auditor` — post-generation quality checks
- `AIPS_Topic_Penalty_Service` — penalizes over-used topics

#### Event System
Use native WordPress hooks with the `aips_` prefix:
```php
do_action('aips_post_generation_started', $template_id, $topic);
do_action('aips_post_generated', $post_id, $template, $history_id);
do_action('aips_post_generation_failed', $template_id, $error_message, $topic);
do_action('aips_schedule_execution_started', $schedule_id);
do_action('aips_schedule_execution_completed', $schedule_id, $post_id);
do_action('aips_schedule_execution_failed', $schedule_id, $error_message);
do_action('aips_trending_topic_scheduled', $schedule_data);
do_action('aips_planner_topics_generated', $topics, $niche);
```
See `docs/HOOKS.md` for the full list of actions and filters.

#### Configuration Singleton
```php
$config = AIPS_Config::get_instance();
```
Use this for feature flags and default option values.

### Security
- Always escape output: `esc_html()`, `esc_attr()`, `esc_url()`
- Verify nonces: `check_ajax_referer('aips_ajax_nonce', 'nonce')`
- Check capabilities: `current_user_can('manage_options')`
- Sanitize input: `sanitize_text_field()`, `sanitize_textarea_field()`, `absint()`
- All DB queries go through repositories which use `$wpdb->prepare()`

## Key Classes and Their Purposes

| Class | File | Purpose |
|-------|------|---------|
| `AI_Post_Scheduler` | `ai-post-scheduler.php` | Main plugin singleton; bootstraps all classes |
| `AIPS_Settings` | `class-aips-settings.php` | Admin menu registration + settings page |
| `AIPS_Admin_Assets` | `class-aips-admin-assets.php` | Script/style enqueueing for admin pages |
| `AIPS_DB_Manager` | `class-aips-db-manager.php` | DB schema definition + install/repair via dbDelta |
| `AIPS_Upgrades` | `class-aips-upgrades.php` | Runs `AIPS_DB_Manager::install_tables()` on version change |
| `AIPS_Generator` | `class-aips-generator.php` | Core AI generation pipeline (title, content, image, post creation) |
| `AIPS_Scheduler` | `class-aips-scheduler.php` | WordPress cron hooks for scheduled generation |
| `AIPS_Schedule_Controller` | `class-aips-schedule-controller.php` | AJAX: save/delete/toggle/run schedules |
| `AIPS_Template_Processor` | `class-aips-template-processor.php` | Replaces `{{variables}}` in prompt templates |
| `AIPS_Prompt_Builder` | `class-aips-prompt-builder.php` | Assembles final prompt strings for the AI |
| `AIPS_Post_Creator` | `class-aips-post-creator.php` | Inserts generated content as WordPress posts |
| `AIPS_AI_Service` | `class-aips-ai-service.php` | Calls Meow Apps AI Engine for text/image generation |
| `AIPS_Config` | `class-aips-config.php` | Singleton: default options + feature flags |
| `AIPS_Autoloader` | `class-aips-autoloader.php` | `spl_autoload_register` for all AIPS_* classes |

## Database Tables

All tables use the WordPress table prefix (e.g., `wp_`). Schema managed by `AIPS_DB_Manager::get_schema()` + `dbDelta`.

| Table | Purpose |
|-------|---------|
| `aips_history` | Generation history records |
| `aips_history_log` | Detailed generation step logs |
| `aips_templates` | Prompt templates |
| `aips_schedule` | Scheduled post configurations |
| `aips_voices` | Writing voices/styles |
| `aips_article_structures` | Article structure templates |
| `aips_prompt_sections` | Reusable prompt sections |
| `aips_trending_topics` | Researched trending topics |
| `aips_authors` | Author personas |
| `aips_author_topics` | Per-author topic queue |
| `aips_author_topic_logs` | Author topic generation logs |
| `aips_topic_feedback` | Topic feedback (thumbs up/down) |

### Adding a Database Table
1. Add the table name to `AIPS_DB_Manager::$tables` (no `migrations/` directory exists)
2. Add the `CREATE TABLE` schema to `AIPS_DB_Manager::get_schema()`
3. `AIPS_Upgrades::check_and_run()` calls `AIPS_DB_Manager::install_tables()` on version change, which uses `dbDelta` to apply changes safely
4. Create a corresponding repository class in `includes/`
5. Add tests for the new repository

## Admin Pages and Assets

### Admin Menu (15 pages, all registered in `AIPS_Settings::add_menu_pages()`)
Dashboard, Templates, Voices, Article Structures, Authors, Author Topics (hidden page, `parent_slug` null), Research, Schedule, Calendar, Generated Posts, History, Settings, System Status, Seeder, Dev Tools (hidden unless `aips_developer_mode` option is set)

### Asset Enqueueing (`AIPS_Admin_Assets`)
Assets load only on pages where `$hook` contains `ai-post-scheduler` or `aips-`:
- **Global on all plugin pages**: `aips-admin-style` (admin.css), `aips-utilities-script` (utilities.js), `aips-admin-script` (admin.js)
- **Authors/Author Topics only**: `aips-authors-style` (authors.css), `aips-authors-script` (authors.js)
- Other pages load their own specific scripts inline via `wp_enqueue_script`

### JavaScript Globals
- `window.AIPS.Utilities` — `showToast()`, `confirm()`, `showProgressBar()` etc. (defined in `utilities.js`)
- `aipsAjax` — `{ ajaxUrl, nonce, schedulePageUrl }` (localized on `aips-admin-script`)
- `aipsAdminL10n` — admin string translations (localized on `aips-admin-script`)
- `aipsUtilitiesL10n` — utility string translations (localized on `aips-utilities-script`)
- `aipsAuthorsL10n` — authors page strings (localized on `aips-authors-script`)

## Template Variables

The `AIPS_Template_Processor` replaces `{{variable}}` placeholders in prompts:

| Variable | Value |
|----------|-------|
| `{{date}}` | Current date (e.g., "January 1, 2025") |
| `{{year}}` | Current year |
| `{{month}}` | Current month name |
| `{{day}}` | Current day name |
| `{{time}}` | Current time (H:i) |
| `{{site_name}}` | WordPress site name |
| `{{topic}}` | The topic string for this generation |
| `{{title}}` | Alias for `{{topic}}` |

Custom `{{VariableName}}` not in the above list are treated as **AI Variables** — they are resolved dynamically by the AI before the main prompt is sent (via `process_with_ai_variables()`).

### Adding Template Variables
1. Update `get_variables()` in `class-aips-template-processor.php`
2. Add a test in `tests/test-template-processor.php`
3. Update `docs/HOOKS.md` if the variable is filterable

## Cron Jobs

| Hook | Schedule | Purpose |
|------|----------|---------|
| `aips_generate_scheduled_posts` | hourly | Run active schedule items |
| `aips_generate_author_topics` | hourly | Generate topics for author queues |
| `aips_generate_author_posts` | hourly | Generate posts from author topic queues |
| `aips_scheduled_research` | daily | Fetch trending topics via Research Service |
| `aips_send_review_notifications` | daily | Email review notifications for pending posts |
| `aips_cleanup_export_files` | daily | Delete session export files older than 24 hours |

## Testing Guidelines

### Test Structure
- All tests in `ai-post-scheduler/tests/test-*.php`
- Test classes extend `WP_UnitTestCase`
- `tests/bootstrap.php` provides all WordPress function mocks; no real WP install needed for most tests
- One test file per feature/class

### Test Naming
- Test methods: `test_feature_behavior()`
- Example: `test_template_processor_replaces_date_variable()`

### WordPress Mock Limitations
The `bootstrap.php` mock for `wp_kses_post` only allows: `a`, `strong`, `em`, `p`, `br`, `ul`, `ol`, `li`. Heading tags (`h1`-`h6`), `blockquote`, `pre`, `code`, and other semantic tags are stripped. This means tests that assert HTML content with heading tags will pass in production but appear differently in tests. Any code relying on these tags should be verified against the real `wp_kses_post` behaviour in a full WordPress environment.

### Best Practices
- Test both success and failure cases
- Mock external dependencies (AI Engine calls, HTTP requests)
- Use data providers for multiple input cases
- Clean up state in `tearDown()` (e.g., remove stubs/mocks)
- Inject dependencies via constructor for testability (`AIPS_Generator` accepts all services as constructor args)

## Common Tasks

### Adding a New Feature
1. Create `includes/class-aips-feature-name.php` with `AIPS_` prefix
2. Add instantiation in `AI_Post_Scheduler::init()` (inside `is_admin()` block for admin-only features)
3. The autoloader picks up the class automatically — no manual `require_once` needed
4. Write tests in `tests/test-feature-name.php`
5. Use repositories for all DB access
6. Dispatch `aips_*` actions for extensibility
7. Register AJAX hooks in the constructor (instantiate only once!)

### Adding an Admin Page
1. Add a `add_submenu_page()` call in `AIPS_Settings::add_menu_pages()`
2. Create template at `templates/admin/page-name.php`
3. The render callback should use a static method or a global to avoid controller re-instantiation
4. Enqueue page-specific assets in `AIPS_Admin_Assets::enqueue_admin_assets()` gated on `$hook` containing the page slug

### Extending the Generation Pipeline
The `AIPS_Generator` constructor accepts all dependencies as optional args — pass mocks in tests:
```php
$generator = new AIPS_Generator($logger, $ai_service, $template_processor, ...);
```

## CI/CD

### GitHub Actions Workflows
- **`phpunit-tests-wp-build.yml`**: Full PHPUnit suite on PHP 8.2 with MySQL + WordPress test library (primary CI)
- **`phpunit-tests-3-build.yml`**: Alternative PHPUnit build (PHP 8.2)
- **`ci-pr.yml`**: Quick PR checks — composer validate, PHPCS lint (if installed), PHPStan (if installed), unit tests
- **`qodana_code_quality.yml`**: Qodana code quality analysis
- **`copilot-setup-steps.yml`**: Pre-installs Composer deps for Copilot agent sessions

All workflows target **PHP 8.2 only**.

### Test Artifacts
- Coverage HTML report uploaded as artifact (`coverage-report-php8.2`)
- Test result cache uploaded as artifact (`test-results-php8.2`)
- Retention: 7 days

### Known CI Behavior
- New PRs from the Copilot bot may show `action_required` status on first run — this is a GitHub security gate for first-time bot contributors, not a test failure. The workflows themselves pass once approved.

## Dependencies

### Runtime (Required)
- **Meow Apps AI Engine** WordPress plugin (`Meow_MWAI_Core` class must exist)
- PHP ≥8.2

### Development
- `phpunit/phpunit: ^9.6` — testing framework
- `yoast/phpunit-polyfills: ^2.0` — WP polyfills for newer PHPUnit
- `wp-phpunit/wp-phpunit: ^6.6` — WordPress test helpers

## MCP Bridge

The plugin ships `mcp-bridge.php` — a JSON-RPC 2.0 style API bridge that exposes plugin functionality to MCP-compatible AI tools. It is not loaded automatically; it must be called directly or included in a custom MCP server.

## Documentation Files

| File | Location | Content |
|------|----------|---------|
| `FEATURE_LIST.md` | `docs/` | Full inventory of 19 features, 13 DB tables, 15 admin pages |
| `HOOKS.md` | `docs/` | All `aips_*` action and filter hooks with arguments |
| `MIGRATIONS.md` | `docs/` | DB migration history and notes |
| `SETUP.md` | `docs/` | Post-clone setup instructions |
| `CHANGELOG.md` | `ai-post-scheduler/` | Plugin version history |
| `readme.txt` | `ai-post-scheduler/` | WordPress.org plugin readme |

## Important Conventions (Summary)

1. **Never use `$wpdb` directly** — always go through a repository class
2. **Register AJAX hooks once** — controllers must be instantiated only once (in `AI_Post_Scheduler::init()`); re-instantiating them in render callbacks causes duplicate hook registrations
3. **Autoloader handles class loading** — just create the file in `includes/`; no `require_once` needed
4. **No `migrations/` directory** — DB changes go through `AIPS_DB_Manager::get_schema()` + `dbDelta`
5. **Admin menu in `AIPS_Settings`** — there is no separate `AIPS_Admin_Menu` class
6. **All composer/phpunit commands run from `ai-post-scheduler/`**
7. **Escape all output** — use `esc_html()`, `esc_attr()`, `esc_url()`
8. **Verify nonces on every AJAX handler** — use `check_ajax_referer('aips_ajax_nonce', 'nonce')`
9. **PHP 8.2 minimum** — `composer.json` requires `php: >=8.2`; plugin header declares `Requires PHP: 8.2`