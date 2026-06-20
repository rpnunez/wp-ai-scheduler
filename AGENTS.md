# AGENTS.md — wp-ai-scheduler

## Canonical rules

- This repository contains the AI Post Scheduler WordPress plugin. The plugin app root is `ai-post-scheduler/`.
- Read the long-form architecture reference before non-trivial changes: [`docs/AI_AGENT_REFERENCE.md`](docs/AI_AGENT_REFERENCE.md).
- Bootstrap reference: `ai-post-scheduler/ai-post-scheduler.php`.
- Business logic belongs in `ai-post-scheduler/includes/`; admin rendering belongs in `ai-post-scheduler/templates/admin/`; admin JS/CSS belongs in `ai-post-scheduler/assets/`.
- Run Composer and PHPUnit commands from `ai-post-scheduler/`, not from the repository root.
- Target PHP 8.2+ and WordPress 5.8+.
- Current plugin version: **2.9.1** (`AIPS_VERSION`).

## Required pre-work

- Before modifying files, check open pull requests with `gh pr list` when `gh` is available.
- If an open PR already covers the same feature or file-scope, stop and report the overlap instead of duplicating work.

## Coding rules

- Use `AIPS_`-prefixed, underscore-separated PHP class names and matching filenames (`class-aips-my-class.php` for `AIPS_My_Class`).
- Use Composer `vendor/autoload.php` as the primary autoloader; `AIPS_Autoloader` is fallback only.
- Use tabs and `array()` syntax for PHP.
- Add `if (!defined('ABSPATH')) { exit; }` to plugin PHP files.
- Centralize default option values in `AIPS_Config::get_instance()->get_default_options()`.
- Use `AIPS_DateTime` for timestamp handling.

## Architecture rules

- Resolve registered singletons and aliases through `AIPS_Container::get_instance()->make(ClassName::class)`.
- Register each AJAX action in `AIPS_Ajax_Registry::$map` with exactly one responsible controller.
- Controllers register `wp_ajax_*` hooks in constructors and own nonce checks, capability checks, sanitization, and JSON responses.
- Keep SQL/persistence in repositories; avoid direct `$wpdb` in controllers or services when a repository exists.
- Prefer generation context abstractions and shared/specialized prompt builders.
- Use the shared history, logging, correlation, resilience, cache, queue, cron, source, embedding, internal-link, notification, and diagnostics services described in `docs/AI_AGENT_REFERENCE.md`.

## Security and WordPress hygiene

- Escape output with `esc_html()`, `esc_attr()`, `esc_url()`, or `wp_kses_post()` as appropriate.
- Verify nonces for state-changing actions.
- Check `current_user_can('manage_options')` for admin/AJAX actions.
- Sanitize request data with WordPress helpers.
- Use `AIPS_Ajax_Response` for consistent AJAX JSON responses.
- Handle missing AI Engine dependency gracefully.

## Testing policy

- Supported test workflow: `cd ai-post-scheduler && composer test:setup && composer test`.
- Docker-backed workflow: `cd ai-post-scheduler && bash scripts/run-wp-tests-docker.sh`.
- Do not invent local unit-test shims that bypass the WordPress test library. If supported tests cannot run locally, document the environment limitation and cite the supported command.
