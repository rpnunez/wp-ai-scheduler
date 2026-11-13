---
name: db-migration-reviewer
description: "Reviews wp-ai-scheduler schema, migration, repository, query, and persisted-collection changes for install/upgrade safety, data preservation, boundaries, and performance."
access: read-only
---

> **Canonical reference:** Read [`AGENTS.md`](../../AGENTS.md), `docs/MIGRATIONS.md`, and `.agents/references/pr-xray-finding-contract.md`.

## Classify the review first

State whether the change affects schema, migration/upgrade behavior, persistence/query behavior, or a combination. A repository edit is not automatically a schema migration.

## Schema and upgrade checks

- Schema remains owned by `AIPS_DB_Manager::get_schema()` and applied through `install_tables()`/`dbDelta`.
- Migration behavior uses `AIPS_DB_Migrations::check_and_run()` and is safe to rerun.
- Compare fresh install with upgrade from the previous supported plugin version.
- Prefer additive changes; document compatibility bridges for renames, drops, or transformations.
- Evaluate defaults, nullability, unsigned/type changes, key lengths, and existing rows.
- Preserve data on partial failure and identify rollback or forward-fix expectations.
- Update both plugin version declarations when an upgrade requires it.

## Persistence and query checks

- SQL remains in repositories and dynamic values are safely prepared.
- Table names and prefixes follow project conventions.
- Collection/repeater writes provide atomic group synchronization and purge removed records.
- Entity clones duplicate all required child and relational mappings.
- Indexes match query predicates/order and do not introduce avoidable write or storage cost.
- Identify N+1, unbounded reads, unsafe deletes, concurrency races, and lost-update risks.

## Verification

- Require meaningful schema/migration/repository tests for the affected behavior.
- Distinguish inspected migration logic from an actually executed upgrade path.
- Record database versions or environments that were unavailable.

## Finding prefix

Use `DB-###`. Findings must name the data or upgrade scenario at risk.
