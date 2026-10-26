---
name: sql-batch
description: "Use this skill whenever you need data from a database to diagnose a bug, verify a hypothesis, or audit state in `wp-ai-scheduler` (dev or the user's production site), instead of asking the user to paste queries into phpMyAdmin and screenshot the results."
---

# SQL Batch Skill

Use this skill whenever you need data from a database to diagnose a bug, verify a hypothesis, or audit state in `wp-ai-scheduler` (dev or the user's production site), instead of asking the user to paste queries into phpMyAdmin and screenshot the results.

## Scope
- Tool: `ai-post-scheduler/bin/sql-batch.php` (reads credentials from `wp-config.php`), driven on dev by `make sql-batch` / `scripts/sql-batch.sh`.
- Runner guarantees: CLI only, read-only statements only (`SELECT`, `WITH`, `SHOW`, `DESCRIBE`, `EXPLAIN`), read-only transaction, 60 s timeout, 10,000-row cap, secrets redacted (`[REDACTED]`), output never inside the WordPress directory.

## Required workflow
1. **Write the queries to a file**, never inline in a shell command: `.artifacts/sql-queries/<yyyymmdd>-<topic>.sql` (gitignored).
   ```sql
   -- name: stuck_batch_jobs
   -- purpose: Are there bulk jobs stuck in "running" for over an hour? (bug #123)
   SELECT id, status, updated_at FROM {prefix}aips_bulk_jobs WHERE status = 'running' AND updated_at < NOW() - INTERVAL 1 HOUR;
   ```
   - One statement per query, terminated with `;`. Give every query a `-- name:` (letters, digits, `_`, `-`) and a `-- purpose:` (it is shown in the report, so say what the answer decides).
   - Use `{prefix}` for the table prefix, never a hard-coded `wp_`.
   - Prefer targeted queries (`COUNT(*)`, `GROUP BY`, `LIMIT`, explicit column lists) over `SELECT *`; select only the columns you need so personal data and long text stay out of the report. Look up real table/column names in `AIPS_DB_Manager::get_schema()` first.
2. **Validate on dev first**: `make sql-batch QUERIES=.artifacts/sql-queries/<file>.sql`. This catches syntax errors and wrong column names before the user spends a production run. Read `.artifacts/sql-results/<run>/REPORT.md`; fix any `ERROR`/`REFUSED` entries. Dev data may be empty, which is fine here: you are validating the SQL, not the answer.
3. **Give the user the production command** (the file may need to be uploaded or pasted via a heredoc; see "SQL diagnostics" in `TESTING.md`):
   ```bash
   php /home/USER/sites/SITE/public_html/wp-content/plugins/ai-post-scheduler/bin/sql-batch.php ~/queries.sql
   ```
   Tell them it prints `RESULTS_ZIP=...`, to download that zip, and then to run `make sql-batch-unpack ZIP=<path>` (or just tell you where they saved it and run it yourself).
4. **Read the results** from `.artifacts/sql-results/<run>/`: `REPORT.md` first (environment fingerprint, per-query status, first 25 rows), then `queries/NN_name.json` for full rows. Check the fingerprint (site URL, plugin version/commit) matches what you expect, and check `truncated` / redaction counts before drawing conclusions.

## Guardrails
- Redaction is best effort (sensitive columns even when aliased, keys inside serialized/JSON values, value columns of queries filtering on secret-looking option/meta names). Do not rely on it: never `SELECT` secrets you do not need, and treat results as sensitive.
- Never run ad-hoc SQL through `docker exec`, `wp db query`, `mysql`, or the dev phpMyAdmin; the runner is the one sanctioned path because it is consistent and safe.
- Never write `UPDATE`/`DELETE`/`INSERT`/`DROP`/etc. It will be refused. If data must change, write a normal migration or a proper plugin fix and explain it to the user instead.
- Do not pass `--no-redact` or `--yes` on your own initiative, and never point the runner at a non-dev database from this machine.
- Results can contain production data: keep them under `.artifacts/` (gitignored), never commit them, and do not paste them into issues or PRs without redacting further.
- If a query is refused wrongly (a false positive of the keyword filter), rewrite it (e.g. avoid a column alias that is a SQL keyword) rather than trying to bypass the filter.
