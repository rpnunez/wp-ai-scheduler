# AI Agent Reference

Detailed context for AI agents working on AI Post Scheduler. [AGENTS.md](../AGENTS.md) is the canonical high-level instruction file; this document holds longer inventories that should not be duplicated there or in tool-specific instruction files.

## Runtime boot shape

`AI_Post_Scheduler::init()` boots only the request context needed:

- `boot_common()`: text domain, DI bindings, optional telemetry, and `aips_source_group` taxonomy.
- `boot_cron()`: lazy WP-Cron closures for schedulers, batch processors, embeddings, sources, notifications, reconciler, and cleanup.
- `boot_ajax()`: resolves `AIPS_Ajax_Registry` and instantiates one mapped controller; legacy lazy hooks may remain for unmapped plugin actions.
- `boot_admin()`: admin menu, assets, settings, onboarding, admin bar, notifications, reconciler, history, and internal links.
- `boot_frontend()`: admin bar only for users with `manage_options`.

## Key subsystems

- Scheduling: `AIPS_Unified_Schedule_Service`, `AIPS_Scheduler`, `AIPS_Author_Topics_Scheduler`, `AIPS_Author_Post_Generator`.
- Batch/bulk jobs: `AIPS_Batch_Queue_Service`, `AIPS_Bulk_Batch_Processor`, `AIPS_Bulk_Batch_Job_Store`, `AIPS_Bulk_Generator_Service`, and `includes/job/`.
- Resilience: `AIPS_Resilience_Service::retry_with_backoff()`.
- Cache: `AIPS_Cache`, `AIPS_Cache_Factory`, `AIPS_Cache_Invalidation_Bus`, repository cache traits/config.
- Partial generation recovery: `AIPS_Partial_Generation_Notifications`, `AIPS_Partial_Generation_State_Reconciler`, `AIPS_Component_Regeneration_Service`, `AIPS_Session_To_JSON`.
- Sources: `AIPS_Sources_*` classes and the `aips_source_group` taxonomy.
- Embeddings: `AIPS_Embeddings_Service`, `AIPS_Embeddings_Cron`, `AIPS_Post_Embeddings_Repository`.
- Internal links: `AIPS_Internal_Links_Controller`, services/repository, and the `aips_index_posts_batch` cron.
- Notifications: registry, senders, templates, event handler, plus `AIPS_Notifications_Repository`.
- Campaigns, post slices, AI assistance, operations insights, telemetry, taxonomy, onboarding, and diagnostics each have dedicated controllers, repositories, and services.

## Admin/UI reference

- Admin menu registration lives in `AIPS_Admin_Menu::add_menu_pages()`.
- Settings are split across `AIPS_Settings`, `AIPS_Settings_UI`, and `AIPS_Settings_Ajax`.
- Dashboard is rendered by `AIPS_Dashboard_Controller`.
- System status uses `AIPS_System_Status_Controller`, `AIPS_System_Diagnostics_Service`, and providers in `includes/diagnostics/`.
- Hidden pages include `aips-author-topics`, `aips-campaign-wizard`, and `aips-campaign-detail`.

## Data and schema

- Schema changes go through `AIPS_DB_Manager::get_schema()` and `AIPS_DB_Manager::install_tables()` using `dbDelta`.
- `AIPS_DB_Migrations::check_and_run()` is the migration entry point; `class-aips-upgrades.php` is a compatibility alias.
- There is no standalone migrations directory.
- Plugin tables include history/logs, templates, schedules, voices, structures, prompt sections, trending topics, authors/topics/logs/feedback, campaigns, post slices, notifications, sources/data/groups, taxonomy, embeddings, internal links, cache, telemetry, AI assistance, and bulk batch jobs.

## Cron hooks

Recurring hooks:

- `aips_generate_scheduled_posts`
- `aips_generate_author_topics`
- `aips_generate_author_posts`
- `aips_scheduled_research`
- `aips_notification_rollups`
- `aips_cleanup_export_files`
- `aips_fetch_sources`
- `aips_cleanup_bulk_batch_jobs`
- `aips_cache_monitor_maintenance`

Single-event hooks:

- `aips_process_schedule_batch`
- `aips_process_author_topics_slice`
- `aips_retry_failed_author_slices_topics`
- `aips_process_author_post_slice`
- `aips_retry_failed_author_slices_posts`
- `aips_process_bulk_batch`
- `aips_process_author_embeddings`
- `aips_index_posts_batch`

## Claude Code skills and subagents

`.claude/skills/` and `.claude/agents/` give Claude Code auto-triggering, repo-grounded
knowledge of this plugin's recurring workflows. These complement (don't replace)
`AGENTS.md`/`CLAUDE.md`; see those files for the underlying architecture rules these
implement. Equivalent tooling for other agents lives in `.codex/skills/` and
`.github/agents/` — the two are maintained independently.

Skills (`.claude/skills/<name>/SKILL.md`, auto-trigger by description):

| Skill | Use for |
|---|---|
| `aips-ajax-endpoint` | Adding/reviewing an AJAX action: registry entry, controller, service, repository, test. |
| `aips-db-schema-change` | Schema/table/index changes: `AIPS_DB_Manager`, `AIPS_DB_Migrations`, version bump. |
| `aips-generation-pipeline` | Content-generation flow changes: context objects, `AIPS_Generator`, prompt builders, logging. |
| `aips-bulk-batch-job` | New bulk/batch job types: `AIPS_Bulk_Batch_Processor` strategy registration. |
| `aips-admin-ui-js` | Admin template/JS changes: module pattern, `AIPS.Templates`/`AIPS.Utilities`, layout structure. |
| `aips-repository-boundary` | Understanding/satisfying `composer lint:repository-boundary`. |
| `aips-pr-prep` | Pre-PR checklist: verification steps and risk labels from `.github/pull_request_template.md`. |

Subagents (`.claude/agents/<name>.md`, invoked explicitly or by task match):

| Subagent | Role |
|---|---|
| `aips-qa` | Test planning/verification using this repo's actual test conventions. |
| `aips-architecture-guardian` | Mechanical diff check: repository boundary, AJAX registry completeness, layer separation. |
| `aips-release-prep` | Performance-regression check (`bin/benchmark.php`) and version-bump consistency. |

For roles Claude Code already ships (janitor/simplification, security review, PR
triage, architecture planning), reuse the built-in `simplify` and `security-review`
skills and the `Explore`/`Plan` agents rather than adding new ones.
