---
name: aips-db-schema-change
description: Use when adding or changing a database table, column, or index in the AI Post Scheduler plugin (ai-post-scheduler/) — anything touching AIPS_DB_Manager, AIPS_DB_Migrations, or plugin schema/version bumps.
---

# Database schema change workflow

## Required workflow

1. **Update the schema definition.** Add/modify the table in
   `AIPS_DB_Manager::get_schema()` (`includes/class-aips-db-manager.php`).
   `install_tables()` + WordPress `dbDelta` apply it — there is no separate
   migrations directory to wire up for a brand-new table.
2. **Add migration logic only if altering existing data/columns.** Use
   `AIPS_DB_Migrations::check_and_run()` (`includes/class-aips-db-migrations.php`) as
   the entry point. `class-aips-upgrades.php` is a compatibility alias, not a second
   place to add logic.
3. **Prefer additive, backward-compatible changes.** Don't drop or rename columns
   without a compatibility bridge — existing installs upgrade in place via `dbDelta`,
   there's no forward-only migration runway.
4. **Bump the version.** Update both the `Version:` header and `AIPS_VERSION`
   constant in `ai-post-scheduler/ai-post-scheduler.php`. These two must stay in sync.
5. **Create a repository class for any new table.** Every table gets its own
   `class-aips-*-repository.php` — this is also what the repository-boundary lint
   expects (see `aips-repository-boundary` skill).
6. **Write/extend schema and migration tests.** Reference:
   `tests/Test_AIPS_DB_Schema.php`, `tests/Test_AIPS_DB_Migrations.php`.

## Guardrails

- Target PHP 8.2+ / WP 5.8+, tabs + `array()` syntax, and the
  `if (!defined('ABSPATH')) { exit; }` guard on any new PHP file.
- Don't add SQL to controllers or services — only repositories (and
  `AIPS_DB_Manager`/`AIPS_DB_Migrations` themselves, which are whitelisted for direct
  `$wpdb` use in `config/repository-boundary-whitelist.txt`).
- A schema change almost always warrants the `schema-change` PR label — see
  `aips-pr-prep`.

## Reference files

- `ai-post-scheduler/includes/class-aips-db-manager.php`
- `ai-post-scheduler/includes/class-aips-db-migrations.php`
- `ai-post-scheduler/ai-post-scheduler.php` (`Version:` header, `AIPS_VERSION`)
- `ai-post-scheduler/tests/Test_AIPS_DB_Schema.php`
- `ai-post-scheduler/tests/Test_AIPS_DB_Migrations.php`
- `ai-post-scheduler/config/repository-boundary-whitelist.txt`
