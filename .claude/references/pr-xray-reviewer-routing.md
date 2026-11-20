# PR Xray Reviewer Routing

The coordinator records every reviewer as selected or skipped, with evidence. File patterns are signals, not substitutes for tracing the changed execution path.

| Reviewer | Select when the change affects |
|---|---|
| AJAX controller | `wp_ajax_*`, `AIPS_Ajax_Registry`, controllers, request parsing, permissions, or AJAX response contracts |
| Database migration | Schema, migrations, repositories, SQL, indexes, persisted collections, or upgrade behavior |
| Generation pipeline | Generation contexts, prompt builders, provider execution, regeneration, partial state, generation history, or output processing |
| Background job | WP-Cron, queues, batch slices, managed background processes, retry/cooldown, pause/resume/cancel, locks, or job state |
| Localization | User-facing PHP/JS strings, gettext, localized script data, language stores, or translated templates |
| Bootstrap/dependency | Plugin bootstrap, Composer, autoloading, container bindings, constructors, boot contexts, class moves, interfaces, or optional dependencies |
| QA | Always; assess requirements coverage, tests, error paths, concurrency, and verification evidence |

## Cross-domain rules

- Select multiple reviewers when a change crosses boundaries.
- A repository change does not automatically imply a schema migration; record whether the DB review is schema, migration, or query focused.
- Generation scheduled by cron selects both generation and background-job reviewers.
- Moving a class while preserving behavior selects bootstrap/dependency review and may also require the affected domain reviewer.
- User-facing AJAX output can require AJAX, localization, and QA review.
- If no specialist applies, the coordinator and QA reviewer still cover the change.

## Coverage rule

Every changed file must have at least one reviewer owner or an explicit exclusion containing the reason and risk assessment. Every acceptance criterion must be marked `verified`, `partially-verified`, `not-verified`, or `not-applicable`.
