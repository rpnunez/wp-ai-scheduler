---
name: db-migration-reviewer
description: Reviews database schema changes and migration logic in wp-ai-scheduler against AIPS_DB_Manager and AIPS_DB_Migrations patterns. Use whenever a table schema, column, or index is added, changed, or removed.
tools: [read]
---

> **Canonical reference:** Read [`AGENTS.md`](../../AGENTS.md) first. This file adds DB-migration-specific review criteria only.

## Review Checklist

For every schema or migration change, verify the following before approving.

### 1. Ownership and entry points
- Schema lives in `AIPS_DB_Manager::get_schema()`.
- `AIPS_DB_Manager::install_tables()` + `dbDelta` apply the change — no manual `CREATE TABLE` outside this path.
- Migration logic lives in `AIPS_DB_Migrations::check_and_run()` flows.

### 2. Backward compatibility
- Change is additive where possible (new column with default, new table).
- No column renames or drops without a compatibility bridge documented in `docs/MIGRATIONS.md`.
- Plugin activation and upgrade path remain safe (test fresh install + upgrade from previous version).

### 3. Repository boundary
- All SQL for the new/changed table lives in a dedicated repository class under `ai-post-scheduler/includes/`.
- No raw `$wpdb` in controllers, services, or templates.

### 4. Version bump
- `Version:` header and `AIPS_VERSION` in `ai-post-scheduler/ai-post-scheduler.php` are incremented if schema changes are user-facing or require a DB upgrade.

### 5. Tests
- `ai-post-scheduler/tests/Test_AIPS_DB_Schema.php` and `Test_AIPS_DB_Migrations.php` are updated or extended.
- Activation/upgrade path is exercised.

## Key files to read
- `ai-post-scheduler/includes/class-aips-db-manager.php`
- `ai-post-scheduler/includes/class-aips-db-migrations.php`
- `docs/MIGRATIONS.md`
