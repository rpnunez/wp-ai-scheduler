## SQL Extraction Backlog (Repository Boundary Enforcement)

This backlog is derived from `docs/feature-report-feature-profiles.md` findings and focuses on non-repository classes still using direct `wpdb` access.

| Priority | Class | Current method(s) using SQL | Extract to repository method(s) |
|---|---|---|---|
| P1 | `AIPS_Scheduler` | constructor table setup + schedule fetch/update helpers that query schedule/template tables | `AIPS_Schedule_Repository::get_due_schedules()`, `::mark_schedule_result()`, `::count_due_schedules()` |
| P1 | `AIPS_Schedule_Entry` | schedule state reads/writes in entry runtime paths | `AIPS_Schedule_Repository::get_entry_state()`, `::update_entry_state()` |
| P1 | `AIPS_Post_Manager` | duplicate-check and post lookup helpers using posts table | `AIPS_Post_Repository::find_existing_generated_post()`, `::get_post_id_by_title_or_meta()` |
| P2 | `AIPS_Templates` | template table bootstrap/list operations | `AIPS_Template_Repository::get_all()`, `::find_by_*()` (expand existing repo where needed) |
| P2 | `AIPS_Bulk_Batch_Job_Store` | job insert/update/select/cleanup queries | Move to `AIPS_Bulk_Batch_Jobs_Repository` (or rename current store to repository and inject) |
| P2 | `AIPS_Cache_DB_Driver` | cache row CRUD/cleanup SQL | Isolate as `AIPS_Cache_Repository` and keep driver interface DB-agnostic |
| P3 | `AIPS_Data_Management_Import_MySQL` | import-time table existence/read/insert checks | `AIPS_Data_Management_Repository::table_exists()`, `::insert_rows()`, `::truncate_table()` |
| P3 | `AIPS_DB_Migrations` | migration-time schema introspection/ALTER statements | `AIPS_DB_Schema_Repository::column_exists()`, `::run_alter_statement()` |
| P3 | `AIPS_Date_Time_DB_Repair` | datetime-repair scans/updates | `AIPS_History_Repository::find_invalid_datetimes()`, `::repair_invalid_datetimes()` |
| P3 | `AIPS_Telemetry` | request-level query counters and direct DB object inspection | keep read-only query metrics in `AIPS_Telemetry`; move persistence SQL to `AIPS_Telemetry_Repository` only |

### Prioritization rationale

Start with classes that combine **direct SQL + high coupling** (`AIPS_Scheduler`, `AIPS_Schedule_Entry`, `AIPS_Post_Manager`) to reduce change-surface and improve testability fastest.
