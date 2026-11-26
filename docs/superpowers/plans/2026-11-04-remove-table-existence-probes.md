# Remove Table Existence Probes Implementation Plan

> **For agentic workers:** Use superpowers:executing-plans to implement this plan task-by-task after user review. Steps use checkbox syntax for tracking.

**Goal:** Remove plugin-owned table-existence preflight queries, retire obsolete migrations, and rely on the schema declared by `AIPS_DB_Manager` for normal operation.

**Architecture:** Installation, upgrades, and explicit repair establish the current schema. Retain only migrations 3.7.16 through 3.7.20, applied after the schema, and delete the older migration code. Repositories execute their actual operations directly; bootstrap timing receives explicit handling rather than replacement existence queries.

**Tech Stack:** PHP 8.2+, WordPress 5.8+, wpdb, dbDelta, PHPUnit through the repository's Docker test protocol.

**Spec:** The user's requests in this chat: explain and plan removal of all table-existence checks; delete the retired aips_post_embeddings migration; audit migrations against the current schema; retain roughly the last five migrations. The user states the only deployed site is their production DevStackTips website. Implementation was approved in this chat; see the execution record below for verification limits.

## Design and scope

`prepare()` formats SQL; the surrounding `get_var()` executes `SHOW TABLES LIKE` and retrieves the matching name. Comparisons with the requested name implement an existence check. Existing uses skip work on absent tables, trigger repair, and support historical migrations and early activation logging.

The pre-implementation audit found 37 executable `SHOW TABLES LIKE` expressions in 11 production files:

| File under ai-post-scheduler/includes/ | Count | Responsibility |
| --- | ---: | --- |
| class-aips-db-migrations.php | 24 | Upgrade gate, structural migrations, backfills, retired embeddings table |
| class-aips-data-management-repository.php | 2 | Export table filtering and an existence helper |
| class-aips-db-prune-repository.php | 2 | Embeddings and relationships cleanup prerequisites |
| class-aips-embeddings-repository.php | 2 | Embeddings/topics availability and memoization |
| class-aips-cache-index.php | 1 | Cache-index readiness before installation |
| class-aips-date-time-db-repair.php | 1 | Shared helper used by multiple repair operations |
| class-aips-db-manager.php | 1 | Backup table filtering |
| class-aips-link-clicks-repository.php | 1 | Repository availability |
| class-aips-link-index-repository.php | 1 | Repository availability |
| class-aips-redirects-repository.php | 1 | Repository availability |
| class-aips-relationships-repository.php | 1 | Repository availability |

Two additional bare `SHOW TABLES` queries serve diagnostics in `class-aips-system-info-provider.php` and `diagnostics/class-aips-system-diagnostics-environment-provider.php`. Include those existence preflights in this cleanup, while retaining the actual column/index inspection that diagnostics requires.

Three executable test queries also use `SHOW TABLES LIKE`: two schema assertions in `Test_AIPS_DB_Schema.php` and a setup guard in `Test_AIPS_Sources.php`. Convert these to direct operation/schema assertions and unconditional cleanup of the declared table. SQL examples in `Test_AIPS_Sql_Batch.php` exercise the SQL validator; they are data, not table-existence preflights, and remain valid fixtures.

**Approach:** Remove the probes and obsolete control flow together. Deleting only SQL would strand callers; replacing checks with another discovery query would retain the overhead. A centralized existence cache would also conflict with the requested contract. These alternatives are rejected in favor of direct operations on declared tables.

## Migration audit and retention decision

Static audit of all 24 migration methods, their dispatch gates, the current 35-table schema, the shared datetime map, and the History repository methods used by the retained migrations:

| Migrations | Targets and findings | Decision |
| --- | --- | --- |
| 2.3.1 | Current notifications table; composite indexes already declared in the schema | Delete migration; retain schema indexes |
| 2.4.0 | Current Sources tables, but includes the retired sources_data.word_count to char_count rename | Delete migration and retired-column logic |
| 2.4.1 | Current sources_data columns; historical content-hash backfill and source_id unique-index replacement | Delete completed historical transformation |
| 2.5.0 | Historical datetime conversion via the shared map, which still contains the retired aips_post_embeddings table | Delete migration; remove the stale map entry used by explicit repair too |
| 2.8.2 | Current campaigns/templates/schedule/history tables; historical campaign mapping backfill | Delete completed backfill |
| 2.8.3 | Current scheduling/author/source objects through repair services and private helpers | Delete migration and its five private helper methods; keep the independently used explicit repair service |
| 2.9.1 | Current templates.post_category column; historical integer to JSON/text conversion | Delete completed transformation |
| 3.0.1 | Current WordPress posts/postmeta and plugin history tables; legacy metadata-key conversion | Delete completed conversion |
| 3.1.0 | Reads and drops the retired history_log.log_type column | Delete migration and retired-column logic |
| 3.4.0 | Current history.post_type backfill | Delete migration; retain repository backfill_post_type(), still used by 3.7.17 |
| 3.4.2 | Creates history_id_log_type on current history_id/history_type_id columns; index name is absent from the current schema | Delete migration; do not recreate the retired index |
| 3.5.0 | Current history_log.event_type/event_status/details columns; historical JSON backfill | Delete completed backfill |
| 3.6.5 | Reads and drops retired aips_post_embeddings, copying to current aips_embeddings | Delete the entire migration and dispatcher call, including both table checks; no replacement read, fallback, table creation, or DROP |
| 3.6.7 | Current embeddings.embedding column; historical JSON to binary/MEDIUMBLOB conversion | Delete completed conversion and its migration-only pacing/filter code |
| 3.7.8 | Current history_log table; drops retired history_id/history_id_type indexes covered by current history_type_timestamp | Delete completed index cleanup |
| 3.7.12, 3.7.13 | Current History objects through repository cleanup/status helpers | Delete migration wrappers; retain repository methods still used outside migrations |
| 3.7.14 | Current authors.post_generation_lock and author_topics.claimed_at columns/indexes, already in schema | Delete migration; preserve schema definitions |
| 3.7.15 | Current History post_type backfill | Delete wrapper; 3.7.17 already calls the same repository backfill |
| 3.7.16 | History status repair, using current history columns and WordPress postmeta through the repository | Retain |
| 3.7.17 | Current history.record_kind classification, post_type backfill, and status repair | Retain; does not require the deleted 3.7.15 wrapper |
| 3.7.18 | Current authors.topic_auto_approval_mode/min_score/min_relevance columns plus WordPress options | Retain; min_score is still declared, not a retired column |
| 3.7.19 | Current Sources next_fetch_at and last_fetch_attempt_at/last_fetch_status/last_fetch_error columns | Retain |
| 3.7.20 | Current history_log.history_type_id/timestamp columns and history_type_ts index, already declared in schema | Retain; align release version and make failure handling truthful |

**Retention boundary:** Keep exactly five migration methods, 3.7.16–3.7.20; delete the other 19 methods and their gates. Treat 3.7.15 as the supported existing-installation baseline. Fresh installations create the current schema directly. Historical conversions below this baseline are deliberately unsupported; do not add a compatibility loader or run those conversions elsewhere. This is a policy based on the user's deployment context, not confirmation of the live site's stored DB version or completion history.

**Related stale metadata:** The shared `AIPS_DB_Manager::get_datetime_column_map()` contains `aips_post_embeddings => indexed_at`. All other mapped table/column pairs match the current schema in this static comparison. Remove that one retired entry so explicit datetime repair cannot query it after table guards disappear. Keep the shared conversion helper because explicit repair still calls it.

**Release mismatch:** The plugin header and `AIPS_VERSION` currently say 3.7.19, but a 3.7.20 migration is dispatched during an upgrade. It can therefore run while the stored version is stamped back to 3.7.19, and same-version installations skip it. Include a scoped release/version update to 3.7.20 in this change, unless the integration branch already supplies a higher version; never downgrade it or renumber the retained migrations.

**Retry details:** `install_tables()` currently writes the final version before data migrations finish. Retained migrations 3.7.16/17 can return failure without restoring it; 3.7.18 does not report SQL failure, and 3.7.20 returns true even if ADD INDEX fails. The revised lifecycle must acknowledge completion only after all eligible retained steps succeed.

## Global constraints

- Runtime targets: PHP 8.2+ and WordPress 5.8+.
- Keep schema definitions in `AIPS_DB_Manager::get_schema()` and table names in its canonical table map.
- Preserve stored data, the relative order of the five retained migrations, SQL identifier validation, feature settings, argument validation, and normal operation return contracts.
- Retain `SHOW COLUMNS`, `SHOW INDEX`, and `SHOW CREATE TABLE` where their purpose is schema inspection/export. Retain information_schema queries that obtain size, row-count, engine, or overhead statistics.
- Delete the pre-dbDelta historical transformations. All five retained migrations consume current-schema objects and run after successful schema application.
- Do not add replacement preflight probes using SELECT, information_schema, DESCRIBE, or SHOW TABLE STATUS.
- Limit version changes to aligning the retained 3.7.20 migration with the release version. No unrelated schema, AJAX routing, or UI changes.

## Review focus

1. Fresh activation logs before installation; cache-index writes must not issue queries against an uncreated table.
2. The final database version must not advance on a failed retained migration, including after successful schema application.
3. No executable migration or repair metadata may reference retired tables/columns/indexes; delete the retired embeddings migration outright.
4. Cleanup joins must retain relationship-based references and must not delete valid embeddings after availability branches disappear.
5. Export and diagnostics must retain their response structure and distinguish operation failure from valid empty data where they currently expose failure.

## Task 1: Prune migrations and establish installation behavior

**Files:** `ai-post-scheduler/ai-post-scheduler.php`, `ai-post-scheduler/includes/class-aips-db-manager.php`, `class-aips-db-migrations.php`, `class-aips-cache-index.php`, and narrowly scoped error propagation in `class-aips-history-repository.php` if needed; tests in `Test_AIPS_DB_Migrations.php`, `Test_AIPS_DB_Schema.php`, `Test_AIPS_AI_Errors_Diagnostics.php`, `Test_AIPS_Date_Time_DB_Repair.php`, and a new `Test_AIPS_Cache_Index.php`. Update `docs/MIGRATIONS.md`, lifecycle comments in the bootstrap/DB classes, `AGENTS.md` version context, and `ai-post-scheduler/CHANGELOG.md` with the retained window and release alignment.

**Interfaces:** Keep `AIPS_DB_Migrations::check_and_run()` as the lifecycle entrypoint. Extend `AIPS_DB_Manager::install_tables($record_version = true)` with an optional boolean; existing direct callers retain their version-recording contract, while the migration runner passes false and records the version only after all work succeeds. Retain exactly `migrate_to_3_7_16()` through `migrate_to_3_7_20()` and make each return bool indicating success. Remove `critical_tables_exist()` and the cache-index existence memo. If bootstrap needs a guard, use an explicit installation-in-progress state covering the first pre-install log through successful schema creation, restored in `finally`; do not create a persistent readiness cache.

- [ ] Replace tests invoking deleted migration methods with current-schema assertions or retained repository-operation tests. Add fixtures starting at each supported DB version from 3.7.15 through the release version; assert only later retained migrations run, in order, and preserve existing data. Test a fresh installation separately.
- [ ] Delete all 19 migration methods older than 3.7.16 and their dispatcher gates. Remove the migration-only helpers `repair_schedule_last_run_from_run_state()`, `repair_template_schedule_next_runs_from_last_run()`, `repair_author_schedule_next_runs_from_last_run()`, `normalize_timestamp_value()`, and `normalize_run_state_timestamp()`. Keep shared services/repository helpers with surviving callers. Remove retired migration filters and outdated comments/docs rather than creating forwarding stubs.
- [ ] Remove `aips_post_embeddings` from the shared datetime map. Add a test asserting every datetime-map table and column belongs to the canonical current schema; exercise explicit date repair with only current tables installed and assert no query targets the retired table.
- [ ] Assert the five retained migrations run successfully against only current schema objects. Retain 3.7.18's min_score-to-min_relevance copy because both columns exist; do not remove a current column based on a legacy-sounding name.
- [ ] Add tests for fresh activation, same-version no-op, installation failure/retry, and cache writes before/during installation. Assert same-version checks issue no table-discovery query and successful installation permits subsequent cache-index writes in the same request.
- [ ] Remove the per-load critical-table probe. Use version state to dispatch upgrades; activation and explicit repair continue to apply the schema. Accept that deleting a table behind a current DB version no longer triggers repair on every page load.
- [ ] Preserve the distinction between a fresh installation and an existing installation before activation seeds version defaults. Apply the schema before eligible retained migrations. Remove comments promising pre-schema legacy transformations now that those methods are deleted.
- [ ] Remove the two table-existence guards in retained migrations 3.7.19/20; retain actual column/index inspection needed for their structural changes. Missing actual operations must fail rather than being acknowledged as complete.
- [ ] Make migration-runner schema application use `install_tables(false)`. Preserve the original stored version and Config cache throughout any failure, including errors from 3.7.16, each repository step of 3.7.17, 3.7.18's SQL operations, 3.7.19, and 3.7.20's index inspection/ALTER. Capture errors where they occur; a later successful query must not mask an earlier failure. Stamp `AIPS_VERSION` only after all eligible steps succeed. Leave standalone `install_tables()` behavior intact.
- [ ] Inject failures in each retained migration and schema installation, verify the persisted and cached versions remain unchanged, then verify the next call retries successfully. Retain the post_type helper's public return contract while adding explicit failure reporting for migration use if necessary. Do not turn this into a general repository API refactor.
- [ ] Align the plugin header and `AIPS_VERSION` at 3.7.20 (or the integration branch's higher version). Test that upgrading a stored 3.7.19 installation dispatches 3.7.20, while a completed same-version request does no migration work. Verify the index actually exists after success.
- [ ] Remove the cache-index existence query and prevent only pre-install metadata writes through the lifecycle handling above. Retain normal enabled/disabled behavior.

## Task 2: Remove repository and maintenance preflights

**Files:** The seven repository files in the inventory, `class-aips-date-time-db-repair.php`, `class-aips-db-manager.php`, `class-aips-data-management-export-json.php`, `class-aips-data-management-export-mysql.php`.

**Interfaces:** Remove `table_exists()`, `topics_table_exists()`, existence-only cache fields/reset methods, and all internal callers. Exporters consume their validated canonical table map directly instead of `get_existing_tables()`. Update test doubles alongside their interfaces. Keep genuine data/argument guards.

- [ ] Extend operation tests to assert direct reads/writes/deletes return the expected values with zero existence probes: embeddings, relationships, redirects, link index/clicks, backup/export, date repair, and orphan cleanup.
- [ ] Remove repository existence methods, associated early returns, memo fields, and cache-reset calls. Update `Test_AIPS_Similarity_Evaluator_Topics.php`, `Test_AIPS_Embeddings_Repository.php`, and `Test_AIPS_Link_Index_Repository.php` where they exercise the removed API.
- [ ] Remove data-management table filtering and unused existence helpers; update both exporters and `Test_AIPS_Data_Management_JSON.php` doubles. Keep the validated table allowlist and SQL export's `SHOW CREATE TABLE` statements.
- [ ] Run backup and date repair directly against their declared tables. Preserve column/type checks needed for repair and the map cleanup from Task 1; do not retain a reference to the retired embeddings table through a shared helper.
- [ ] Remove prune availability branches and always include the declared relationships table where required. Test orphan deletion alongside post and topic references that must survive.
- [ ] Update stale comments describing tables as optional or missing-table fallbacks. Do not replace the removed API with methods that always return true.

## Task 3: Remove diagnostic preflights and update schema tests

**Files:** `class-aips-system-info-provider.php`, `diagnostics/class-aips-system-diagnostics-environment-provider.php`, `Test_AIPS_DB_Schema.php`, `Test_AIPS_Sources.php`, relevant existing diagnostics tests.

**Interfaces:** Diagnostics enumerate expected tables from `AIPS_DB_Manager`; existing schema-inspection results feed the existing diagnostic response structure. This task does not add a new discovery query.

- [ ] Remove bare `SHOW TABLES` lists and their lookup maps. Execute the schema inspection already required for each expected table; preserve useful error reporting when that actual inspection fails.
- [ ] Replace existence-only schema test assertions with assertions that direct table operations and required columns/indexes succeed. Remove the Sources setup probe before cleanup of its declared table.
- [ ] Verify diagnostics retain table/column status output, exports cover the entire canonical table map, and table-statistics functionality remains intact.

## Task 4: Verification and delivery

- [ ] Run focused PHPUnit files in one invocation from the repository root, following `TESTING.md`. Include new cache-index and any new redirect/click/prune operation tests in the same batch:

  `make test-session ARGS="tests/Test_AIPS_DB_Migrations.php tests/Test_AIPS_DB_Schema.php tests/Test_AIPS_AI_Errors_Diagnostics.php tests/Test_AIPS_Cache_Index.php tests/Test_AIPS_Embeddings_Repository.php tests/Test_AIPS_Relationships_Repository.php tests/Test_AIPS_Link_Index_Repository.php tests/Test_AIPS_Internal_Links.php tests/Test_AIPS_Link_Click_Tracking_Service.php tests/Test_AIPS_Data_Management_JSON.php tests/Test_AIPS_Date_Time_DB_Repair.php tests/Test_AIPS_Sources.php tests/Test_AIPS_Similarity_Evaluator_Topics.php tests/Test_AIPS_System_Diagnostics_Service.php tests/Test_AIPS_System_Status_Diagnostics_Service.php"`

- [ ] Run PHP syntax checks on every touched PHP file and `git diff --check`. Run a final repository search for SQL table-discovery statements and removed helper names; explain retained SQL-validator examples and metadata-statistics queries.
- [ ] Confirm regression tests execute actual operation paths and observe SQL, rather than merely testing that a source string was deleted. Confirm zero plugin-owned existence preflights on normal operations and same-version startup.
- [ ] Search the migration runner, retained helper call paths, datetime map, and tests/docs for retired schema names and deleted migration methods. Confirm exactly five migration methods remain, no production SQL references the retired embeddings table or removed word_count/log_type columns, and tests no longer invoke deleted migrations. Distinguish JSON/response fields named log_type from a removed SQL column; keep unrelated current behavior.
- [ ] Report test results and any blocked checks accurately. Use `make test-session` cleanup behavior, preserve pre-existing containers, and report what was started/stopped. No containers or tests are required for this planning-only step.

## Acceptance criteria

- All 37 production `SHOW TABLES LIKE` queries and both diagnostic discovery preflights are removed.
- No equivalent existence preflight or always-true compatibility helper replaces them.
- All declared tables are used directly during normal plugin operation.
- Exactly migrations 3.7.16 through 3.7.20 remain; the other 19 methods, their gates, and migration-only helpers are deleted.
- No runtime migration or datetime-repair map entry targets a table/column/index absent from the current schema. The retired embeddings migration is removed entirely, with no fallback or replacement.
- Fresh activation, upgrades from the supported 3.7.15 baseline, exports, diagnostics, and relationship-aware cleanup work under the cases above. Old conversions below that baseline are intentionally retired.
- Release version and latest retained migration agree; failed retained steps never acknowledge the final database version and remain retryable.
- Same-version startup no longer discovers missing tables or initiates automatic repair based on table discovery; activation and explicit repair remain available.
- Required focused tests and syntax/static checks pass, or concrete execution blockers are reported without a claim of runtime verification.

## Execution record

- Implemented the repository, maintenance, export, diagnostic, and five-migration cleanup. Release version is 3.7.20. Added `Test_AIPS_Table_Contract.php` and `Test_AIPS_Cache_Index.php` to the focused verification scope.
- Activation now forces the shared migration entrypoint to apply the schema once; it does not subsequently overwrite a failed migration's version through a second standalone installation call.
- Review ruling: retain a request-local failed-installation outcome in the cache index until schema application succeeds. This records an actual failed operation, not discovered table existence, and prevents metadata SQL after an unsuccessful initial installation.
- Restore administrator notifications for automatic upgrade failures. Capture errors between dbDelta statements so a later successful statement cannot mask an earlier schema failure. Export failures produce explicit error output and no destructive restore SQL for that table.
- Initial RED run: 12 tests, 128 assertions, 11 expected failures demonstrating the removed probes, stale datetime entry, early version acknowledgement, and missing 3.7.20 dispatch.
- First implementation batch through `make test-session`: 147 tests, 1209 assertions, 1 error, 11 failures, 1 risky test. Retained migration failure/retry cases passed. Corrected changed export expectations, fixture leakage across DDL commits, and the no-assertions regression test afterward. Other failures include old binary-vector expectations, an uninitialized rate-limiter mock, AI Errors AJAX authorization, and stale diagnostics-step expectations; no untouched-HEAD runtime comparison completed.
- Follow-up verification was interrupted with exit 137 during host resource exhaustion, then Docker returned HTTP 500/502. Final review fixes and additional failure-injection tests were not rerun. The user explicitly chose to finish with this test blocker documented; no final green PHPUnit result is claimed.
- Final static verification passed: PHP syntax on all 26 touched PHP files, exact five-migration inventory, no production table discovery/retired embeddings references/obsolete existence helpers, datetime-map membership against all 35 schema tables, release-version alignment, and `git diff --check`.
- Used an isolated Compose project to preserve pre-existing containers. `make stop` subsequently confirmed the task's web and db services stopped. No user-owned stack or data volumes were deleted. Retained gitignored local `.env` and `docker-compose.override.yml` configuration so a later `make test-session` uses the same isolated project; removed scratch scripts, logs, and the unused baseline snapshot.
- Rerun the existing focused command with `tests/Test_AIPS_Table_Contract.php` included after Docker is healthy. The full suite was not run.
