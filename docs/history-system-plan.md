# History System Audit & Plan

Audit of where History containers and logs are (and are not) created, and the plan to close the gaps.
Findings come from reading the code; nothing here was verified by running the plugin.

Background: each History container (`wp_aips_history`) has a `creation_method` (defaults to the container type) and ordered log rows. The History modal's **Overview** tab shows "Triggered By" and "Trigger Type"; the **Timeline** tab shows `Trigger Source` and `Trigger Method` as the first two rows. These come from `AIPS_Generation_Trigger` (`includes/class-aips-generation-trigger.php`), which also classifies `creation_method` values as manual / automatic / unknown.

## Corrections to the first trigger-info change

| # | Problem | Where |
|---|---|---|
| C1 | Cron bulk strategies were missed. `planner_post` calls legacy `generate_post()` so it records as `manual`; `author_topic_post` goes through `generate_now()` which hardcodes `manual`. | `ai-post-scheduler.php` (bulk strategies), `AIPS_Author_Post_Generator::generate_now` |
| C2 | `trending_topic_post` passes creation method `cron`, which the classifier does not know, so it shows "Unknown". | `ai-post-scheduler.php` |
| C3 | `bulk_batch_slice`, `schedule_lifecycle`, `template_lifecycle` are not classified. | `AIPS_Generation_Trigger` |
| C4 | Manual "Generate Topics Now" creates no container at all (`generate_now()` calls the generator directly), so trigger entries only exist on the cron path. | `AIPS_Author_Topics_Scheduler::generate_now`, `AIPS_Authors_Controller::ajax_generate_topics_now` |

## Findings

### (a) Events with no History container

| Area | Gap |
|---|---|
| Authors | Create / update / delete author; `ajax_generate_topics_now`. |
| Schedules | Delete and bulk delete (repository only); reset circuit; resume batch; bulk toggle. |
| Templates | Only the slicing notice is logged on save. Create / update / delete / clone / test are not. |
| Campaigns | Only pause / resume / archive / restore are logged. Create & finalise, duplicate, delete, draft save, AI generate are not. `CAMPAIGN_CREATED` / `CAMPAIGN_UPDATED` are defined but never emitted. |
| Research | `run_scheduled_research` (cron) writes only to the file logger. `ajax_research_topics`, gap analysis, `generate_topics_from_gap`, `research_from_sources` have no container. |
| Sources | `fetch_source_now`, the sources cron, and source / group CRUD. |
| Content indexer / auditor | Bulk reindex, clear index, cannibalization audit, gap ideas, commit gap topics, save pillar. Only per-post indexing has containers. |
| GSC / link index | `AIPS_GSC_Keywords_Service`, `AIPS_Link_Index_Service`, GSC controller. |
| Config CRUD (Phase 4) | Voices, article structures, prompt sections, post slices, affiliate links, redirects, silos, link rules, integrations, taxonomy settings, Settings changes. |
| Dev / stress (Phase 4) | Stress test, seeder. |

### (b) Containers that exist but need more logs

- **Component regeneration / AI edit** append to the post's original container via `resolve_existing()` with no record of who triggered the regeneration or why.
- **`schedule_lifecycle` containers** have no trigger entries. Scheduled author runs show author + topic but not the author schedule/slice that fired them.
- **Bulk-batch job and slice containers** have no trigger entries; per-item post containers are not linked to the job ID or the starting user.
- **Large-batch dispatch / job dispatcher** (`AIPS_Batch_Queue_Service`, `AIPS_Job_Dispatcher`) reference history; what is recorded has not been reviewed.
- **Post deleted / published events** are written as string literals in many places.
- **Author slice scheduler** records failures only.
- **Notification rollup cron** has no container.
- Several `AIPS_History_Event_Type` constants are never used (`SCHEDULE_CREATED`, `SCHEDULE_ENABLED`, `EMBEDDING_*`, ...). New events should go through `AIPS_History_Event_Recorder`.

### (c) Missing event types

Config CRUD, Generate Topics Now, scheduled research, Settings changes, AI-assistance usage. The telemetry table records AI calls but not who triggered them.

## Plan

**Phase 0 — fix the earlier misses** (C1–C4)
1. Classify `cron`, `bulk_batch_slice`, `schedule_lifecycle`, `template_lifecycle`.
2. Label `planner_post` / `trending_topic_post` / `author_topic_post` as bulk + automatic.
3. Make `generate_now` create a container and pass a real creation method.
4. Tests.

**Phase 1 — one recording helper**
5. Add a lifecycle helper that creates the container, writes the trigger entries and records the event (event type, subject, user, source).
6. Add missing event-type constants; unit tests.

**Phase 2 — generation-related events**
7. Trigger entries on component regeneration / AI edit.
8. Research runs (manual + scheduled), gap analysis, Generate Topics Now.
9. Pass the author schedule/slice context into `AIPS_Topic_Context`.
10. Bulk-batch: trigger entries on job + slice containers; job ID on each item's container.

**Phase 3 — lifecycle and CRUD events**
11. Authors, schedule delete / bulk / circuit / resume, template create / update / delete / clone, remaining campaign actions.
12. Sources fetch, content-indexer bulk operations, GSC and link-index runs.

**Phase 4 — deferred (keep in mind)**
13. Config-only CRUD (voices, structures, prompt sections, slices, affiliate links, redirects, silos, link rules, integrations) and Settings changes. Optional audit trail; adds many rows, so it needs a retention decision. The Phase 1 helper is designed so these can be added with one call each.

## Status

Implemented on branch `claude/history-modal-trigger-context-b731a0` (PR 2146). Nothing below has been run: no PHP runtime was available, so PHP lint and PHPUnit still need to be run.

| Phase | State |
|---|---|
| 0 | Done. `cron`, `bulk_batch_slice`, `template_lifecycle`, `author_lifecycle`, `source_lifecycle` classified. `schedule_lifecycle` is deliberately left Unknown: that container holds both user edits and cron executions, so `creation_method` cannot say which. `planner_post`, `trending_topic_post`, `author_topic_post` strategies pass their own creation method and a "Bulk job <id>" detail. Manual Generate Topics Now creates a container. |
| 1 | Done. `AIPS_History_Event_Recorder::record_lifecycle()` / `record_simple()` create the container, write Trigger Source + Trigger Method, record the event and close it; they never throw. New lifecycle `creation_method` values live in `AIPS_History_Event_Recorder::lifecycle_creation_methods()` and are hidden from the History list and dashboard counts (same as the existing `*_lifecycle` values). New event types added to `AIPS_History_Event_Type`. |
| 2 | Done: component regeneration trigger entries (once per run); research runs (manual and scheduled, gap analysis, from sources); author post schedule detail; bulk-batch job and slice triggers. |
| 3 | Done: authors create/update/delete; schedule delete, bulk delete, circuit reset; template create/update/delete/clone; campaign create/duplicate/delete/update and the list-page toggle/archive/restore; source fetch (manual and cron); content index clear and cannibalization audit; Search Console sync (manual and cron); link-index backfill start. |
| 4 | Deferred. |

### Not done / follow-ups

- Per-batch content indexing (`ajax_process_batch`) is not given its own container: it is called repeatedly by the progress UI and each post already has its own indexing container.
- `AIPS_Batch_Queue_Service` / `AIPS_Job_Dispatcher` were not reviewed for gaps.
- Schedule resume-batch is covered by the existing `manual_schedule_started` entries.
- Restoring a component revision (`ajax_restore_component_revision`) and capturing manual edits do not write their own trigger entries.
- Notification rollup cron still has no container.

### Code-review fixes

- `planner_post`, `trending_topic_post` and `author_topic_post` are now classified **manual** (a user starts the bulk job; cron only executes the slices). The notification handler classifies creation methods through `AIPS_Generation_Trigger`, so retry / regenerate / bulk-job runs still raise "manual generation completed" as they did before the new creation methods were introduced. Trending-topic bulk runs, which previously used the unrecognised `cron`, now also raise it.
- The History modal no longer reports `*_post` creation methods as "Author topic generation"; new creation methods have group labels.
- Content-indexing trigger entries are only written when verbose indexer history is enabled (two extra log rows per post otherwise). Source fetches from cron are recorded as one container per run.
- `AIPS_History_Event_Recorder::record_entity_change()` replaces three copy-pasted helpers (authors, templates, schedules).
- "Regenerate all components" resolves the History container once and reuses it.
- Scheduled research records runs that saved zero topics; failed saves are recorded as failures.
- Bulk schedule delete keeps each item's type.
- `Test_AIPS_History_Lifecycle_Events::test_every_creation_method_literal_in_the_plugin_is_classified_or_allowlisted` fails when a new `creation_method` literal is added without being classified in `AIPS_Generation_Trigger`.
