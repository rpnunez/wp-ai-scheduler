# DB Changes Skill

Use this skill when implementing schema/table/index changes in the AI Post Scheduler plugin.

## Scope
- WordPress plugin DB schema and upgrade safety.
- Tables managed by `AIPS_DB_Manager` and migrations managed by `AIPS_DB_Migrations`.

## Required workflow
1. **Locate ownership**
   - Confirm the table or schema lives in `ai-post-scheduler/includes/class-aips-db-manager.php`.
   - Confirm migration touchpoints in `ai-post-scheduler/includes/class-aips-db-migrations.php`.
2. **Design for backward compatibility**
   - Prefer additive changes.
   - Never drop/rename columns without a compatibility bridge.
3. **Implement through DB manager/migrations only**
   - Update `AIPS_DB_Manager::get_schema()`.
   - Ensure `AIPS_DB_Manager::install_tables()` + `dbDelta` can apply the change.
   - If needed, add/extend migration logic in `AIPS_DB_Migrations::check_and_run()` flows.
4. **Repository boundary check**
   - Keep SQL in repository classes under `ai-post-scheduler/includes/`.
   - Avoid SQL in controllers and templates.
5. **Validation**
   - Run targeted tests first, then broader suite when practical.
   - Confirm plugin activation/upgrade path remains safe.

## Guardrails
- Target PHP 8.2+ and WP 5.8+.
- Keep WordPress style: tabs, `array()` syntax.
- Add/keep `if (!defined('ABSPATH')) { exit; }` in new PHP files.

## Useful files
- `ai-post-scheduler/includes/class-aips-db-manager.php`
- `ai-post-scheduler/includes/class-aips-db-migrations.php`
- `ai-post-scheduler/tests/Test_AIPS_DB_Schema.php`
- `ai-post-scheduler/tests/Test_AIPS_DB_Migrations.php`
