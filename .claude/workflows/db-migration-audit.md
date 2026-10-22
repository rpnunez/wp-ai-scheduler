# Database Migration Audit Workflow

Fan out review agents to audit database schema definitions, `AIPS_DB_Manager`, `AIPS_DB_Migrations`, and SQL repository boundary isolation across the codebase.

## Purpose

Audit all database structures and migrations in `ai-post-scheduler/` to verify compliance with the DB rules in `AGENTS.md` and `CLAUDE.md`:
1. Schema definitions centralized in `AIPS_DB_Manager::get_schema()`.
2. Table upgrades handled cleanly via `dbDelta` in `install_tables()`.
3. Migration logic defined in `AIPS_DB_Migrations::check_and_run()`.
4. SQL isolated strictly inside repository classes (`class-aips-*-repository.php`).

## Phases

### Phase 1 — Schema & Repository Discovery
1. Read `ai-post-scheduler/includes/class-aips-db-manager.php` to map all defined tables.
2. Read `ai-post-scheduler/includes/class-aips-db-migrations.php` to list all registered migration routines.
3. Enumerate all repository files in `ai-post-scheduler/includes/class-aips-*-repository.php`.

### Phase 2 — Parallel Repository & SQL Audit
For each repository file and non-repository PHP file under `includes/`:
1. Spawn a subagent to verify no raw `$wpdb` direct queries exist outside repository classes.
2. Verify table name variables use `$wpdb->prefix . 'aips_'` conventions.
3. Check parameter binding safety (`$wpdb->prepare` usage on dynamic queries).

### Phase 3 — Backward Compatibility & Version Audit
A dedicated agent checks:
- Migration backward-compatibility (additive schema changes).
- Version header in `ai-post-scheduler.php` and `AIPS_VERSION` constant match DB schema requirements.

### Phase 4 — Consolidated Report
Produce a structured Markdown report:
- Table inventory and schema health status.
- Repository boundary compliance table (Pass/Fail per file).
- Backward compatibility & migration safety findings.
- Prioritized remediation steps.

## Useful references
- `AGENTS.md` — canonical DB rules
- `.claude/agents/db-migration-reviewer.md` — reviewer checklist
- `.claude/skills/db-changes/SKILL.md` — DB implementation guide
