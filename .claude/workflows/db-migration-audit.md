# Database and Persistence Audit Workflow

Audit plugin-wide schema, migrations, repositories, queries, and persisted collection behavior. This is a subsystem sweep; use PR Xray for one change set.

## Phase 1 — Inventory

1. Map tables and indexes from `AIPS_DB_Manager::get_schema()`.
2. Map migration/upgrade routines from `AIPS_DB_Migrations`.
3. Enumerate repositories and persisted collection/clone operations.
4. Search non-repository code for direct SQL and classify legitimate infrastructure exceptions separately.

## Phase 2 — Parallel review

Use `.claude/agents/db-migration-reviewer.md` to review schema, upgrade, and persistence/query concerns as separate categories. Include preparation, prefixes, index fit, data preservation, atomic synchronization, clone completeness, N+1 risk, and tests.

Every reviewer returns the shared PR Xray finding envelope.

## Phase 3 — Compatibility cross-check

- Compare fresh installation with upgrade expectations.
- Check migration idempotency and partial-failure behavior.
- Reconcile schema inventory with repository ownership.
- Identify tables or queries without tests or documented lifecycle behavior.

## Phase 4 — Report

Use the shared severity/confidence model. Produce table and repository inventories, schema/upgrade health, boundary compliance, performance risks, adjudicated findings, and prioritized remediation. Use the PR Xray report assembler only when artifacts are requested.

## References

- `AGENTS.md`
- `docs/MIGRATIONS.md`
- `.claude/agents/db-migration-reviewer.md`
- `.claude/references/pr-xray-finding-contract.md`
