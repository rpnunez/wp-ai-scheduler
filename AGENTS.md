# AGENTS.md — wp-ai-scheduler

## Canonical Project Context
- This file is the canonical cross-agent instruction source; all agents, subagents, and automated workflows reference this document.
- **Project**: WordPress plugin for scheduling and generating AI-written posts through Meow Apps AI Engine.
- **Plugin Application Root**: `ai-post-scheduler/`; run Composer, PHPUnit, and plugin scripts from that directory.
- **Bootstrap & Version Source**: `ai-post-scheduler/ai-post-scheduler.php`.
- **Current Plugin Version**: **3.7.19** (`Version:` header and `AIPS_VERSION`).
- **Runtime Targets**: PHP 8.2+ and WordPress 5.8+.

## Key Paths
- **Business Logic & PHP Classes**: `ai-post-scheduler/includes/`
- **Admin Templates**: `ai-post-scheduler/templates/admin/`
- **Admin Assets**: `ai-post-scheduler/assets/`
- **Tests**: `ai-post-scheduler/tests/`
- **Agent Skills, Subagents & Workflows**: `.agents/`
- **Changelog**: `CHANGELOG.md`
- **Deeper Documentation**: [README.md](README.md), [docs/DEVELOPMENT_GUIDELINES.md](docs/DEVELOPMENT_GUIDELINES.md), [docs/AI_AGENT_REFERENCE.md](docs/AI_AGENT_REFERENCE.md), [docs/FEATURE_LIST.md](docs/FEATURE_LIST.md), [docs/HOOKS.md](docs/HOOKS.md), [docs/MIGRATIONS.md](docs/MIGRATIONS.md), [docs/SETUP.md](docs/SETUP.md).

## Agents, Subagents & Skills

All agent resources live under `.agents/`:

### Subagents (`.agents/subagents/`)
| Subagent | Role & Scope |
|---|---|
| `qa-reviewer.md` | Test planning, bug hunting, edge-case analysis, implementation verification. |
| `ajax-controller-reviewer.md` | Nonce verification, capability checks, and sanitization compliance in AJAX controllers. |
| `db-migration-reviewer.md` | Schema changes, table migrations, and `$wpdb` repository encapsulation. |
| `batch-job-reviewer.md` | Bulk batch processor strategies, cron slicing, and job store state handling. |
| `l10n-reviewer.md` | Text domain usage, i18n string escaping, language stores, and admin templates. |
| `feature-slicer.md` | Decomposing feature requests into independently testable, mergeable increments. |
| `generation-pipeline-reviewer.md` | Generation contexts, prompt builders, resilience flows, and tracing. |
| `pr-triage.md` | PR overlap detection, risk classification, and review readiness audits. |
| `codebase-explorer.md` | Read-only explorer for tracing execution paths and identifying small change surfaces. |
| `test-analyst.md` | Selecting focused verification checks and identifying coverage gaps. |
| `wordpress-reviewer.md` | WordPress standards, architecture rules, security, and compatibility reviews. |

### Skills (`.agents/skills/`)
Implementation step-by-step guides:
- `admin-ui-changes` — Admin templates, tab routing, and UI component standards.
- `ajax-controller-changes` — Secure AJAX controllers, registry mapping, and response format.
- `batch-job-changes` — Bulk batch queue strategies, WP-Cron slices, and job stores.
- `db-changes` — Database tables, dbDelta migrations, and repository patterns.
- `feature-slicing` — Structured breakdown of complex feature development.
- `generation-changes` — Content generation contexts, prompt builders, and retry loops.
- `pr-triage` — Pull request auditing, risk classification, and test verification.
- `sql-batch` — Read-only SQL diagnostics on dev/prod environments via `make sql-batch`.

### Multi-Agent Workflows (`.agents/workflows/`)
- `ajax-security-sweep.md` — Audits every AJAX controller for nonce, capability, and sanitization compliance.
- `db-migration-audit.md` — Audits table schemas, repository boundaries, and migrations.
- `generation-pipeline-sweep.md` — Sweeps generation contexts, prompt builders, logging, and retry backoff.
- `full-plugin-pr-audit.md` — Master pre-merge audit fanning out domain reviewers across touched files.

## Development & Test Commands

Run PHPUnit ONLY as specified in the "Agent test protocol" in [TESTING.md](TESTING.md): `make test-session ARGS="tests/Test_A.php tests/Test_B.php"` (all files in one invocation; it stops the containers it started). Do not use PowerShell/`bash.exe -lc` wrappers, `scripts/run-docker-test.sh` directly, `&&`/`;` chains or `grep`/`head`/`tail` pipes, and do not run `./start-dev.sh` just to test. Run `make composer-install` once for dev dependencies. Clean up: stop any containers you started (never `make down`/`-v`), delete scratch files, and report what you started/stopped.

```bash
# Tests in the web container, against a separate wp_tests DB
make test-session ARGS="tests/Test_AIPS_DB_Migrations.php"   # single file, stops what it started (preferred)
make test ARGS="tests/Test_AIPS_DB_Migrations.php"           # same, leaves the stack up
make test                                            # full test suite
make test ARGS="--fresh"                             # recreate wp_tests DB first
make test-coverage                                   # HTML report in ai-post-scheduler/coverage/

# Alternative Docker test runner script
bash scripts/run-docker-test.sh tests/Test_AIPS_DB_Migrations.php

# CI-parity host runner (needs host PHP + Composer)
make test-ci                                         # = bash scripts/run-wp-tests-docker.sh

# Directly from plugin root (when a WP test env is configured on host)
cd ai-post-scheduler
composer test
composer test:verbose
composer test:coverage

# Skip DB creation (for restricted host/CI environments)
export AIPS_WP_TEST_SKIP_DB_CREATE=true
cd ai-post-scheduler && composer test

# Repository boundary linter (enforces no raw SQL outside repositories)
cd ai-post-scheduler && composer lint:repository-boundary
```

### Docker Development Environment

```bash
./start-dev.sh          # First-time provisioning (or: make start-dev). Prompts for GOOGLE_API_KEY;
                        # --import-sql dump.sql seeds DB; logs to .artifacts/start-dev-*.log
make start              # Start existing containers
make stop               # Stop (preserves containers and database volumes)
make down               # Remove containers (preserves database volumes)
make shell              # Interactive bash shell inside WordPress web container
make logs               # Follow all container logs
make test               # Run PHPUnit inside web container
make test-coverage      # Run PHPUnit coverage
make urls               # Display local service URLs
```

**Local URLs (Docker, default ports)**:
- WordPress: `http://localhost:8080`
- WP Admin: `http://localhost:8080/wp-admin` (`admin` / `admin`)
- phpMyAdmin: `http://localhost:8082`

### Performance Benchmarks

```bash
cd ai-post-scheduler
php bin/benchmark.php --wp-core-dir=/tmp/wordpress
php bin/benchmark.php --wp-core-dir=/tmp/wordpress --baseline-file=../.github/performance-baseline.json --fail-on-regression
```

### Database Diagnostics
Do not hand-roll `docker exec` queries or ask users to run manual SQL: write queries to `.artifacts/sql-queries/*.sql` and run `make sql-batch QUERIES=...` (read-only, secrets redacted, results in `.artifacts/sql-results/`; see `.agents/skills/sql-batch/SKILL.md`).

## Architecture & System Patterns

### 1. Request-Context Boot
`AI_Post_Scheduler::init()` (in `ai-post-scheduler/ai-post-scheduler.php`) is the context-dispatch entry point, registered on the `init` hook. The singleton boots on `plugins_loaded` (priority 5). It inspects the request context and initializes only required subsystems:
- `boot_common()`: Post types, taxonomies, container registrations, text domain, background processes.
- `boot_cron()`: Background cron handlers, single-event queue processor, schedule listeners.
- `boot_ajax()`: AJAX controllers via `AIPS_Ajax_Registry`.
- `boot_admin()`: Admin menus, asset loader, notices, screen handlers.
- `boot_frontend()`: Minimal frontend hooks (if applicable).

### 2. Dependency Injection & Container
- Use `AIPS_Container::get_instance()->make(ClassName::class)` for registered singletons and interface aliases.
- Shared singletons and interface implementations are registered in `AI_Post_Scheduler::register_container_bindings()` (e.g. `AIPS_History_Service_Interface` → `AIPS_History_Service`).

### 3. Layer Separation (Strictly Enforced)
- **Controllers** (`class-aips-*-controller.php`): Register `wp_ajax_*` actions, perform nonce & capability verification, sanitize inputs, and return structured responses via `AIPS_Ajax_Response`. No business logic or direct SQL.
- **Repositories** (`class-aips-*-repository.php`): Encapsulate all `$wpdb` access and database queries. Never write SQL in controllers, services, or templates.
- **Services** (`class-aips-*-service.php`): Contain business workflows, orchestration, entity lifecycles, and transactions. Never call `$wpdb` directly.
- **Templates** (`templates/admin/*.php`): Presentation only. No SQL, no raw database fetches, no heavy calculations.

### 4. Settings Pattern
Three mandatory steps for every option:
1. Define default value in `AIPS_Config::get_instance()->get_default_options()`.
2. Register option via `register_setting()` in `AIPS_Settings::register_settings()`, sourcing default from step 1.
3. Access value via `AIPS_Config::get_instance()->get_option('option_key')`.

### 5. Database Schema & Migrations
1. Define table schema in `AIPS_DB_Manager::get_schema()`.
2. `install_tables()` runs via WordPress `dbDelta()` to create or alter tables.
3. Version check and upgrade routines execute in `AIPS_DB_Migrations::check_and_run()`.
4. Bump `Version:` header and `AIPS_VERSION` in `ai-post-scheduler.php`.
5. Create a dedicated repository class for every new table.

### 6. Generation Pipeline
The AI generation pipeline uses immutable context objects rather than loose parameters:
- `AIPS_Template_Context`: Template parameters, prompts, and tone.
- `AIPS_Topic_Context`: Topic title, keywords, research data, and category.
- `AIPS_Generation_Context`: Created by `AIPS_Generation_Context_Factory`, binds template and topic context.
- `AIPS_Generator`: Executes content generation steps.
- Observability: Traced through `AIPS_Generation_Logger`, `AIPS_History_Service`, `AIPS_History_Container`, and `AIPS_Correlation_Id`.
- Prompt Construction: Build prompts through shared prompt builder classes (`AIPS_Article_Prompt_Builder`, etc.), never string concatenation.

### 7. Bulk & Batch Processing
`AIPS_Bulk_Batch_Processor` dispatches `aips_process_bulk_batch` single-event cron slices.
- Job types (`author_topic_post`, `planner_post`, `trending_topic_post`) register strategies via `$processor->register(type, callable)` in `boot_cron()`.
- Queue metrics and slice state persist in `AIPS_Bulk_Batch_Job_Store`.
- Slices process small batches (5–10 items) and reschedule themselves until complete.

### 8. Background Processes
Long-running jobs (indexes, scans, embeddings) register through `AIPS_Background_Process_Interface` and `AIPS_Background_Process_Manager` (hook filter `aips_background_processes`):
- Managed jobs extend `AIPS_Managed_Background_Process` (single-tick cron slices, stored state, quota/cooldown management).
- AI/embeddings jobs extend `AIPS_Embeddings_Background_Process` (`AIPS_Internal_Links_Indexing_Process`, `AIPS_Author_Embeddings_Process`).
- Real-time admin UI status (admin bar pulse, Diagnostics) is driven by `assets/js/background-processes.js` via the Heartbeat API. Never poll independently.

### 9. JavaScript Module Architecture
All admin scripts in `assets/js/` follow this structure:
```javascript
(function($) {
  'use strict';
  window.AIPS = window.AIPS || {};
  var AIPS = window.AIPS;

  AIPS.ModuleName = {
    init: function() { this.bindEvents(); },
    bindEvents: function() { $(document).on('click', '.aips-action-btn', this.handleAction.bind(this)); },
    handleAction: function(e) { /* ... */ }
  };

  $(document).ready(function() { AIPS.ModuleName.init(); });
})(jQuery);
```

**JavaScript Conventions**:
- **HTML Templating**: Render via `AIPS.Templates.render(templateId, data)` (auto-escaped) or `renderRaw()` (trusted markup only). Avoid manual string concatenation.
- **Toasts & Feedback**: `AIPS.Utilities.showToast(message, type)` — do not use `alert()`.
- **Confirmations**: `AIPS.Utilities.confirm(message, heading, buttons)` — do not use `confirm()`.
- **DOM Updates**: Re-fetch data via AJAX and re-render with `AIPS.Templates`; avoid `location.reload()`.

### 10. Admin UI Layout Standards
- Page wrappers follow: `div.wrap.aips-wrap` > `div.aips-page-container` > `div.aips-page-header` / `div.aips-content-panel`.
- Buttons use `aips-btn` with semantic modifiers (`aips-btn-primary`, `aips-btn-danger`, `aips-btn-secondary`).
- Tables use `table.aips-table`.
- Modals follow `.aips-modal` > `.aips-modal-content` > `.aips-modal-header` / `.aips-modal-body` / `.aips-modal-footer`.

## File-Type Specific Implementation Instructions

All agents modifying code in this repository must strictly adhere to the following rules based on the file type and subsystem being edited:

### 1. JavaScript Files (`assets/js/*.js`)
- **Module Pattern**: Wrap all scripts in an IIFE receiving jQuery: `(function($) { 'use strict'; ... })(jQuery);`.
- **Global Namespace**: Initialize and reuse `window.AIPS = window.AIPS || {}; var AIPS = window.AIPS;`.
- **Sub-Module Registration**: Define features as named sub-modules on `AIPS` (e.g., `AIPS.ModuleName = { ... }`).
- **Bootstrap & Event Binding**:
  - Provide an `init()` method as the main entry point.
  - Provide a `bindEvents()` method registering DOM listeners to named methods (`this.handler.bind(this)`). Avoid anonymous inline callbacks.
  - Use delegated listeners on `document` (e.g., `$(document).on('click', '.selector', ...)`) for dynamic admin elements.
  - Place a single `$(document).ready(function() { AIPS.ModuleName.init(); });` at the bottom of the file.
- **Documentation**: Add JSDoc comments for all methods, including `@param {Event} e` for event handlers.
- **HTML Generation**: Always use `AIPS.Templates.render(templateId, data)` (auto-escaped) or `renderRaw()` (for trusted markup only). Never assemble markup via manual string concatenation.
- **User Feedback**:
  - Toasts: `AIPS.Utilities.showToast(message, type)` — never use `alert()`.
  - Confirmations: `AIPS.Utilities.confirm(message, heading, buttons)` — never use `confirm()`.
- **DOM Refresh**: Re-fetch updated data via AJAX and re-render with `AIPS.Templates`; never call `location.reload()`.
- **AJAX Submissions**: Sanitize and validate client values before dispatching; use standard `ajaxurl`, nonces, and action hooks.

### 2. Core Business Logic & PHP Classes (`includes/*.php`)
- **WordPress Standards**: Always use native WordPress APIs (actions, filters, capabilities, nonces, sanitization/escaping helpers).
- **Separation of Concerns (Strict)**:
  - **Controllers** (`class-aips-*-controller.php`): Own request/response flow, capability checks (`current_user_can('manage_options')`), nonce checks, input sanitization (`wp_unslash()`), and return responses via `AIPS_Ajax_Response`.
  - **Repositories** (`class-aips-*-repository.php`): Own persistence logic and all `$wpdb` SQL queries.
  - **Services** (`class-aips-*-service.php`): Own business logic, workflows, external API integrations, and transactions. Never call `$wpdb` directly.
  - **Supporting Patterns**: Use Containers, Processors, Managers, and Factories for distinct responsibilities.
- **Strict AJAX Ownership**:
  - Register `wp_ajax_*` hooks inside the respective Controller constructor.
  - Keep callback methods on the same controller (e.g. `ajax_*` handlers). Never register AJAX hooks in repositories, services, or templates.
- **Strict SQL Ownership**:
  - All SQL queries belong exclusively in Repository classes. Never compose raw SQL in Controllers, Services, cron jobs, or templates.
- **Observability & History Logging**:
  - Record structured events for automations, AI generations, and failures via `AIPS_History_Service::get_instance()->create(...)` and `AIPS_History_Container`.
  - Explicitly log complete lifecycles (started, item progress, warning/error, success/failure completion) with correlation IDs.
- **Class & File Naming**: `AIPS_`-prefixed, underscore-separated PHP classes matching filename `class-aips-*.php`.
- **File Guard**: Start every PHP file with `if (!defined('ABSPATH')) { exit; }`.
- **Syntax**: Use tabs for indentation and `array()` syntax. Target PHP 8.2+ and WordPress 5.8+.

### 3. Admin View Templates (`templates/**/*.php`)
- **Presentation Layer Only**: Keep business logic, direct data fetches, and heavy processing out of templates. Data must be prepared in advance by controllers/services/repositories.
- **No Direct SQL**: Never invoke `$wpdb` in templates.
- **Page Wrapper Shell**:
  `div.wrap.aips-wrap` > `div.aips-page-container` > `div.aips-page-header` / `div.aips-content-panel`.
  Reuse layout blocks: `aips-page-header-top`, `aips-page-actions`, `aips-filter-bar`, `aips-panel-toolbar`, `aips-panel-body`.
- **Component Classes & IDs**: Use `aips-*` classes for visual elements and `data-*` attributes for JavaScript bindings.
- **Data Grids**: Use `table.aips-table` with semantic `thead`, `tbody`, and optional `tfoot`. Use standard column classes (`column-*`, `check-column`, `cell-primary`, `cell-meta`, `cell-actions`). Preserve row `data-*` attributes.
- **Buttons & Icons**: Use `aips-btn` with size (`aips-btn-sm`) and intent modifiers (`aips-btn-primary`, `aips-btn-secondary`, `aips-btn-danger`, `aips-btn-ghost`). Use Dashicons (`span.dashicons dashicons-*`). Include accessible labels (`screen-reader-text` or `aria-label`) for icon-only buttons.
- **Modals**: `.aips-modal` wrapper > `.aips-modal-content` (size modifier `.aips-modal-large`) > `.aips-modal-header`, `.aips-modal-body`, `.aips-modal-footer`. Close control `.aips-modal-close` with accessibility labels.
- **Tabs**: Use `.aips-tab-link` or `.nav-tab` with matching `data-tab` / hash targets and active-state classes aligned with JS handlers.
- **Security & Sanitization**:
  - Escape all output: `esc_html()`, `esc_attr()`, `esc_url()`, or `wp_kses_post()`.
  - Never trust raw `$_GET`, `$_POST`, or `$_REQUEST` values in templates; sanitize with WordPress helpers.
  - Wrap all user-visible strings in i18n functions (`__()`, `_e()`, `esc_html__()`).
  - Never add new one-off inline styles; use utility classes.

### 4. General WordPress Standards & Best Practices (`**/*.php`)
- **Core Extensibility**: Never modify WordPress core; extend strictly through actions and filters.
- **Execution Guard**: Guard direct execution with `defined('ABSPATH') || exit;`.
- **Prefixing**: Use unique prefixes (`AIPS_`, `aips_`) across all functions, classes, hooks, and options to prevent namespace collisions.
- **Asset Enqueuing**: Enqueue all scripts and styles using `wp_enqueue_script` / `wp_enqueue_style`. Never output raw `<script>` or `<style>` tags directly in PHP templates. Enqueue conditionally only on relevant admin screens.
- **Internationalization (i18n)**: All user-facing strings must be wrapped in translation functions (`__()`, `_e()`, `_x()`, `esc_html__()`) using the plugin's text domain (`ai-post-scheduler`).
- **Database Safety**: Always use `$wpdb->prepare()` with dynamic placeholders (`%d`, `%s`, `%f`). Never interpolate variables directly into SQL queries.
- **Performance**: Defer expensive calculations to targeted hooks; never run heavy processing on global `init`. Use transients or object caching for repeat queries.
- **Change Discipline**: Keep changes small, reviewable, and focused. Prefer existing repositories, services, and controllers over inventing parallel patterns.

## Security & WordPress Hygiene
- **Output Escaping**: Escape all output with `esc_html()`, `esc_attr()`, `esc_url()`, or `wp_kses_post()`.
- **Nonce Verification**: Verify nonces with `check_ajax_referer('action', 'nonce', false)`. Always pass `false` as the third parameter to prevent default HTML `wp_die()` outputs and return structured JSON.
- **Capability Checks**: Always verify `current_user_can('manage_options')` for admin/AJAX actions.
- **Input Sanitization**: Always `wp_unslash()` user input before passing to `sanitize_text_field()`, `absint()`, or `sanitize_key()`.
- **AJAX Booleans**: Parse boolean AJAX parameters with `filter_var($val, FILTER_VALIDATE_BOOLEAN)` (jQuery serializes booleans to strings).
- **Standard JSON API Responses**: Use `AIPS_Ajax_Response::success($data)` and `AIPS_Ajax_Response::error($message)`.
- **AI Engine Dependency**: Always handle missing Meow Apps AI Engine dependency gracefully.

## Testing Policy
- **Test Before Merge**: Run PHPUnit for all code changes. Run tests that cover touched code, plus syntax checks on modified files.
- **Docker Test Stack**: Execute via `make test-session ARGS="tests/Test_X.php"` (see the Agent test protocol in `TESTING.md`).
- **Reporting**: Report test and syntax verification results in final responses.
