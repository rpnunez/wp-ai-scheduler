# AGENTS.md — wp-ai-scheduler

## Project goal
Build and maintain a WordPress plugin that schedules and publishes AI-generated posts using the **Meow Apps AI Engine** plugin as the AI backend. The plugin focuses on an admin-driven workflow (templates + schedules + generation) and reliable automation via WordPress cron.

## Where to work
- The plugin lives in `ai-post-scheduler/` (treat that as the “app root”).
- Run Composer and PHPUnit from `ai-post-scheduler/` (not the repo root).
- Target **PHP 8.2+** and **WordPress 5.8+**.

## Core patterns & conventions
- **Class naming/autoloading**: PHP classes use the `AIPS_` prefix and live under `ai-post-scheduler/includes/`; rely on the plugin’s autoloader (avoid manual `require_once`).
- **Separation of concerns**: keep admin rendering in `ai-post-scheduler/templates/admin/` and place business logic in `includes/` classes/services.
- **Extensibility**: prefer WordPress hooks/actions/filters (generally `aips_*`) around meaningful lifecycle points so other code can extend behavior.

## Data access & upgrades
- **No direct `$wpdb` usage** in feature code; go through repository classes for persistence.
- Schema changes flow through the DB manager’s schema definition and WordPress `dbDelta`-based upgrades (no standalone migrations folder).

## Security & WP hygiene
- Escape output appropriately (`esc_html`, `esc_attr`, `esc_url`) and validate/sanitize input.
- For admin/AJAX endpoints: verify nonces, check capabilities, and avoid leaking sensitive data in responses/logs.

## AI Engine dependency
- The plugin depends on Meow Apps AI Engine (e.g., the `Meow_MWAI_Core` class). Handle missing/disabled dependency gracefully (clear admin messaging; fail safe).

## Useful docs
- `README.md` (repo root) and `docs/` for developer notes and feature details.
- `ai-post-scheduler/CHANGELOG.md` for plugin release history.
