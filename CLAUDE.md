# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Canonical references

`AGENTS.md` is the canonical cross-agent instruction source — read it first. This file adds Claude Code-specific orientation and does not duplicate what is already there.

## Claude Code skills and subagents

`.claude/skills/` and `.claude/agents/` encode this plugin's recurring workflows (AJAX endpoints, schema changes, generation pipeline, bulk batch jobs, admin UI/JS, repository-boundary enforcement, PR prep) so they auto-trigger instead of relying on prose recall. See the catalog in `docs/AI_AGENT_REFERENCE.md` — don't duplicate that inventory here.

## Commands

All Composer and PHPUnit commands run from `ai-post-scheduler/`.

```bash
# Full test suite (Docker-backed; handles DB setup automatically)
bash scripts/run-wp-tests-docker.sh

# Directly (when WP test env is already installed in the shell)
cd ai-post-scheduler
composer test
composer test:verbose
composer test:coverage

# Single test file
cd ai-post-scheduler && vendor/bin/phpunit tests/test-template-processor.php

# Skip DB creation (agent/CI environments without create permissions)
export AIPS_WP_TEST_SKIP_DB_CREATE=true
cd ai-post-scheduler && composer test

# Repository boundary lint
cd ai-post-scheduler && composer lint:repository-boundary
```

Docker development environment:

```bash
./start-dev.sh          # First-time provisioning
make up                 # Start services
make down               # Stop (keeps volumes)
make shell              # Bash in WordPress container
make logs               # Follow all container logs
make test               # Run tests via Docker wrapper
make test-coverage      # Coverage via Docker wrapper
make urls               # Print all service URLs
```

Local URLs (Docker): WordPress `http://localhost:8080` · Admin `http://localhost:8080/wp-admin` (admin/admin) · phpMyAdmin `http://localhost:8082`

Performance benchmarks:

```bash
cd ai-post-scheduler
php bin/benchmark.php --wp-core-dir=/tmp/wordpress
php bin/benchmark.php --wp-core-dir=/tmp/wordpress --baseline-file=../.github/performance-baseline.json --fail-on-regression
```

## Architecture

### Request-context boot

`AI_Post_Scheduler::init()` (in `ai-post-scheduler/ai-post-scheduler.php`) is the context-dispatch entry point, registered on the `init` hook. The singleton itself is created on `plugins_loaded` (priority 5) via `aips_init()`. `init()` always calls `boot_common()`, then exactly one context branch:

| Context | Boot method | What it loads |
|---|---|---|
| WP-Cron | `boot_cron()` | Lazy closures for schedulers, batch processors, embeddings, sources, notifications |
| AJAX | `boot_ajax()` | One controller resolved from `AIPS_Ajax_Registry` |
| Admin | `boot_admin()` | Admin menu, assets, settings, onboarding, notifications, reconciler |
| Frontend | `boot_frontend()` | Admin toolbar node only |

This means only the classes required for the current request type are ever instantiated.

### AJAX routing

Every `wp_ajax_*` action must be registered in `AIPS_Ajax_Registry::$map` (`includes/class-aips-ajax-registry.php`) mapping `action_name => ControllerClass`. `boot_ajax()` reads `$_REQUEST['action']`, resolves the controller, and constructs it — the constructor registers the hook, WordPress fires it. Never add ad hoc AJAX registrations outside the registry.

### Dependency injection

`AIPS_Container::get_instance()->make(ClassName::class)` resolves registered singletons and interface aliases. Core bindings are registered in `AI_Post_Scheduler::register_container_bindings()`. Interface aliases (e.g. `AIPS_History_Service_Interface` → `AIPS_History_Service`) are also registered there.

### Layer separation (enforced)

- **Controllers** (`class-aips-*-controller.php`): AJAX hook registration, nonce/capability checks, sanitization, `AIPS_Ajax_Response` JSON responses.
- **Repositories** (`class-aips-*-repository.php`): all `$wpdb` SQL. Never write SQL in controllers, services, or templates.
- **Services** (`class-aips-*-service.php`): business logic and orchestration — no direct DB calls.
- **Templates** (`templates/admin/*.php`): presentation only; no SQL, no heavy logic.

### Settings pattern

Three mandatory steps for any new option:
1. Default in `AIPS_Config::get_default_options()`.
2. Register via `register_setting()` in `AIPS_Settings::register_settings()`, reading default from step 1.
3. Read via `AIPS_Config::get_instance()->get_option('key')`.

### Schema changes

1. Update `AIPS_DB_Manager::get_schema()`.
2. `install_tables()` + `dbDelta` do the rest — no extra wiring.
3. Bump `Version:` header and `AIPS_VERSION` in `ai-post-scheduler.php`.
4. Create a repository class for any new table.

Migrations entry point: `AIPS_DB_Migrations::check_and_run()`.

### Generation pipeline

The core content-generation flow uses context objects rather than ad hoc parameters: `AIPS_Template_Context`, `AIPS_Topic_Context`, and `AIPS_Generation_Context` (built by `AIPS_Generation_Context_Factory`). `AIPS_Generator` drives generation; `AIPS_Generation_Logger` + `AIPS_History_Service` + `AIPS_Correlation_Id` provide observability. Prompt assembly goes through shared prompt builders, never string concatenation in callers.

### Bulk/batch jobs

`AIPS_Bulk_Batch_Processor` dispatches `aips_process_bulk_batch` single-event cron slices. Job types (`author_topic_post`, `planner_post`, `trending_topic_post`) are registered as strategies via `$processor->register(type, callable)` in `boot_cron()`. Job state lives in `AIPS_Bulk_Batch_Job_Store`.

### JavaScript module pattern

Each JS file in `assets/js/` follows the same structure:
```js
(function($) {
  'use strict';
  window.AIPS = window.AIPS || {};
  var AIPS = window.AIPS;

  AIPS.ModuleName = {
    init() { this.bindEvents(); },
    bindEvents() { $(document).on('event', '.selector', this.handler.bind(this)); },
    handler(e) { ... }
  };

  $(document).ready(function() { AIPS.ModuleName.init(); });
})(jQuery);
```

Key JS rules:
- **HTML generation**: always `AIPS.Templates.render(id, data)` (auto-escaped) or `AIPS.Templates.renderRaw(id, data)` (trusted HTML only). Never string concatenation.
- **Toasts**: `AIPS.Utilities.showToast(message, type)` — never `alert()`.
- **Confirm dialogs**: `AIPS.Utilities.confirm(message, heading, buttons)` — never `confirm()`.
- **DOM refresh**: re-fetch via AJAX and re-render with `AIPS.Templates`; never `location.reload()`.

### Template layout structure

Admin pages follow: `div.wrap.aips-wrap` → `div.aips-page-container` → `div.aips-page-header` / `div.aips-content-panel`. Buttons use `aips-btn` with variants (`aips-btn-primary`, `aips-btn-danger`, etc.). Tables use `table.aips-table`. Modals: `.aips-modal` → `.aips-modal-content` → `.aips-modal-header` / `.aips-modal-body` / `.aips-modal-footer`.

## Testing policy

Do not run `composer test` or PHPUnit unless the user explicitly asks or the task requires it. For code changes, prefer static/syntax checks on touched files and note unrun test suites in the response. When tests are needed, use `AIPS_WP_TEST_SKIP_DB_CREATE=true` if DB creation is unavailable.
