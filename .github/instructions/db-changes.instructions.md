---
applyTo: "ai-post-scheduler/includes/class-aips-db-*.php,ai-post-scheduler/ai-post-scheduler.php"
---

Lane: **DB changes** (`schema-change`, `security-sensitive`)

- Keep schema definitions and DB upgrades inside DB manager/migration classes only.
- If schema changes, update both plugin header `Version:` and `AIPS_VERSION` in `ai-post-scheduler.php`.
- Keep migrations backward-compatible and idempotent.
- Add/update tests covering repository/query behavior and migration-safe paths.
- Include rollback/compatibility notes in PR summary and apply `schema-change` label.
