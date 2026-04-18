# Post-Implementation Performance Roadmap — Next Plan

Direct recommendation
- Ship the architectural pieces into production by migrating call-sites to the new implementations (container, singletons, AJAX registry, boot_* dispatcher, composer autoload, DTOs, identity-map caches, config cache) in small, gated PRs (one Step or a closely related group per PR). Measure before/after with an agreed benchmark suite and add CI checks to prevent regressions. After integration, iterate on advanced caching, persistent object cache support, request-level telemetry, and JS payload reduction.

What I reviewed and why this plan
- All 16 Steps from the optimization plan were implemented (PRs referenced: docs + Step 16 DTO PR). Many structural artifacts now exist in the codebase (container, singletons, boot dispatcher code, registry, DTO classes, identity-map scaffolding, config cache), but these artifacts are not yet fully wired into runtime call paths in many places. The largest wins will come from switching runtime behavior to use these artifacts and validating the gains with metrics and CI.

High-level goals (what success looks like)
1. All refactor artifacts are actively used by runtime code (no “dead” or unused migrations).
2. Per-request allocations and DB queries reduce measurably (target: 20–50% reductions for hotspots).
3. No functionality regressions — preserve existing behavior during migration.
4. Continuous performance checks guard against regressions.

Key metrics to track (baseline + post-change)
- DB queries per request measured for:
  - admin non-AJAX page load (dashboard/settings)
  - frontend page load for a manage_options user (admin-bar path)
  - representative AJAX endpoints (one heavy, one light)
- Peak memory usage per request
- Request wall-time (PHP-only)
- PHP object/new() allocation counts (or approximate via instantiation telemetry)
- JS asset payload bytes for plugin admin pages (authors page and generic plugin page)
- External AI API request counts/latency (for generator flows)

Phased plan (prioritized, small PRs, measurable)

Phase A — Activate foundational infrastructure (small, low-risk PRs)
Objective: Ensure singletons, composer autoload, and config centralization are the default runtime behavior.

A.1 — Enable Composer classmap / classmap autoloading (Step 1)
- Tasks:
  - Add classmap entry for `includes/` in `composer.json` or migrate to PSR-4.
  - Run `composer dump-autoload --optimize` in CI.
  - Make legacy `AIPS_Autoloader` a fallback shim only; add deprecation note.
- Tests:
  - Unit / smoke tests that all classes load under composer autoload.
- Est. effort: 0.5 day

A.2 — Centralize get_option calls through `AIPS_Config` (Step 2)
- Tasks:
  - Replace remaining direct `get_option('aips_*')` calls with `AIPS_Config::get_instance()->get_option()`.
  - Add typed accessor methods for option groups read together.
- Tests:
  - Unit tests asserting `AIPS_Config::get_option` is used and returns defaults.
- Est. effort: 1 day

A.3 — Add per-request config cache (Step 10)
- Tasks:
  - Ensure `AIPS_Config::get_option()` uses an internal `$resolved` array cache for the request.
  - Add unit tests asserting one `get_option()` per key per request.
- Est. effort: 0.5 day

Validation for Phase A
- Run baseline metrics; expect small improvements in class-loading time and fewer repeated `get_option` unserialize calls.

Phase B — Wire singletons, container, and lazy-init (medium complexity)
Objective: Reduce duplicate instantiations and defer work to correct contexts.

B.1 — Ensure `instance()` factories exist where missing (Step 5)
- Tasks:
  - Implement/complete `instance()` static factories for classes in Step 5 list.
  - Use `instance()` defaults for optional constructor args where appropriate.
- Tests:
  - Unit tests that `instance()` returns the same object.
- Est. effort: 1–2 days

B.2 — Register `AIPS_Container` bindings (Step 7)
- Tasks:
  - Ensure the container implementation is present and register core singletons:
    - `AIPS_History_Repository`, `AIPS_History_Service`, `AIPS_Notifications_Repository`, `AIPS_Config`, `AIPS_Template_Repository`, `AIPS_Schedule_Repository`, `AIPS_AI_Service`, `AIPS_Logger`.
  - Add a small bootstrap that registers bindings early in plugin boot (but do not change boot semantics yet).
- Tests:
  - Container unit tests: `make()` returns correct instances; singletons shared.
- Est. effort: 1–2 days

B.3 — Lazy-init admin bar and notifications repository (Steps 4 & 6)
- Tasks:
  - Make `AIPS_Admin_Bar` lazy-resolve its notifications repository (resolve in rendering hooks only).
  - Gate `get_unread()` so `get_unread()` only runs if `count_unread() > 0`.
  - Set TTL on admin bar cache (60s) and invalidate on `ajax_mark_read()` / `ajax_mark_all_read()`.
- Tests:
  - Unit/integration tests asserting no DB read when unread count is zero and cache invalidates on mark-read.
- Est. effort: 1 day

B.4 — Lazy scheduler instantiation and cron closures (Step 6)
- Tasks:
  - Avoid instantiating schedulers on non-cron requests; bind cron hooks to closures that invoke `AIPS_Scheduler::instance()` or `container->make()` at runtime.
- Tests:
  - Integration test verifying non-cron page loads do not instantiate scheduler.
- Est. effort: 1 day

Validation for Phase B
- Expect large reductions in constructor counts on admin page loads; measurable drop in allocations.

Phase C — Switch runtime routing and boot dispatcher (higher risk; break into small PRs)
Objective: Replace `init()` with `boot_*` methods and use the AJAX registry.

C.1 — Implement `boot_common()`, `boot_ajax()`, `boot_admin()`, `boot_frontend()` (Step 15)
- Tasks:
  - Implement context-aware bootstrap logic.
  - Boot dispatcher behind a feature flag (e.g., `aips_enable_context_boot`) for safe rollout.
  - Ensure `boot_common()` retains taxonomy/textdomain behavior but guards taxonomy on frontend.
- Tests:
  - Integration tests for each context (cron, admin non-AJAX, admin AJAX, frontend).
- Rollout:
  - Merge behind a flag, enable on staging, then flip default after validation.
- Est. effort: 2–3 days

C.2 — Wire `AIPS_Ajax_Registry` and `AIPS_Ajax_Response` (Step 11)
- Tasks:
  - Ensure registry map covers all actions and is used by `boot_ajax()` to resolve a single controller.
  - Replace ad-hoc `wp_send_json_*` usages with `AIPS_Ajax_Response::success()` / `error()`.
  - Migrate controllers incrementally (few controllers per PR).
- Tests:
  - End-to-end tests hitting representative AJAX endpoints and verifying JSON shape.
- Est. effort: 2 days (incremental)

Validation for Phase C
- On an admin AJAX request, only the matched controller should instantiate. Expect significant reduction in per-request instantiations for both AJAX and non-AJAX contexts.

Phase D — Interfaces, identity maps, caching decorators (structural)
Objective: Use interfaces and add identity-map caches and optional persistent caching decorators.

D.1 — Add interfaces and update typehints (Step 12)
- Tasks:
  - Create interfaces for major seams and update constructors to typehint interfaces.
  - Register concrete implementations in container.
  - Maintain BC by accepting `null` and resolving via container when needed.
- Tests:
  - Unit tests verifying classes implement interfaces and constructors accept them.
- Est. effort: 1–2 days

D.2 — Add in-request identity maps (Step 13)
- Tasks:
  - Implement `$cache` and `$all_loaded` within repository singletons: Template, Schedule, Voices repositories.
  - Add unit tests to ensure repeated `get_by_id()` uses cached result.
- Est. effort: 1 day

D.3 — Implement optional persistent caching decorators
- Tasks:
  - Create caching decorator wrappers that use `wp_cache_get()/set()` with conservative TTLs.
  - Register decorator in container conditionally (detect persistent object cache availability).
- Tests:
  - Integration tests verifying decorator reduces DB queries when persistent cache is present.
- Est. effort: 2 days

Validation for Phase D
- Expect repeated-in-request query reductions; generation flows should use cached template/schedule objects.

Phase E — DTO rollout and code modernization (Step 16 + follow-ups)
Objective: Replace ad-hoc arrays with typed DTOs across high-traffic paths.

E.1 — Inventory DTO adoption targets
- Tasks:
  - Map where arrays are returned/consumed for each DTO:
    - `AIPS_Generation_Result`
    - `AIPS_Schedule_Entry`
    - `AIPS_Template_Data`
    - `AIPS_Ajax_Response` (helpers)
  - Prioritize flows: generation -> scheduler -> controllers -> responses.
- Est. effort: 0.5 day

E.2 — Migrate generator flows to `AIPS_Generation_Result`
- Tasks:
  - Change generator APIs to return the DTO; add `toArray()` for compatibility.
  - Update callers to consume DTOs directly.
- Tests:
  - Unit and integration tests for generation success/partial/failure flows.
- Est. effort: 1–3 days (incremental PRs)

E.3 — Migrate repository returns to DTOs
- Tasks:
  - Update repository methods to return typed DTOs for schedule and template rows.
  - Update consumers (scheduler, renderers) accordingly.
- Tests:
  - Unit tests for repository return shapes and consumers.
- Est. effort: 2–4 days

E.4 — Standardize AJAX responses using `AIPS_Ajax_Response`
- Tasks:
  - Migrate controllers to the centralized success/error helpers.
  - Confirm consistent JSON shapes across endpoints.
- Est. effort: 1–2 days

Validation for Phase E
- Expect cleaner, safer code paths and possible minor memory improvements.

Phase F — Asset and JS payload reduction (Step 14 + extras)
Objective: Reduce JS payload size and localization bloat.

F.1 — Split `aipsAdminL10n` into page-scoped objects
- Tasks:
  - Move page-specific strings into smaller localized objects pushed only on relevant pages.
  - Keep a tiny shared object for common strings.
- Tests:
  - Browser smoke tests ensuring required strings exist on each admin page.
- Est. effort: 1 day

F.2 — Scope or lazy-load `admin-embeddings.js`
- Tasks:
  - Enqueue `admin-embeddings.js` only on authors/author-topics pages.
  - Consider dynamic import/code-splitting if build tooling in place.
- Est. effort: 1 day

Validation for Phase F
- Measure JS bytes per page and parse times; expect tangible reductions.

Phase G — Database and query tuning (Step 3 + ongoing)
Objective: Ensure DB schemas and queries are optimal and migrations applied.

G.1 — Ensure composite indexes exist and migrations applied (Step 3)
- Tasks:
  - Apply idempotent DB migration to add:
    - `KEY is_read_created_at (is_read, created_at)`
    - `KEY dedupe_key_created_at (dedupe_key, created_at)`
  - Use `AIPS_Upgrades` with safe checks.
- Tests:
  - Verify index existence on staging and run `EXPLAIN` for hot queries.
- Est. effort: 0.5 day

G.2 — Sweep query hotspots for improvements
- Tasks:
  - Replace triplet `get_post_meta()` reads with `metadata_exists()` (Step 9).
  - Avoid `SELECT *` when only columns are required.
  - Add LIMITs and index-friendly ORDER BYs.
- Tests:
  - `EXPLAIN` plans; query count/lantency checks.
- Est. effort: 1–2 days

Validation for Phase G
- Expect fewer filesorts and lower query latency on hot paths.

Phase H — Observability, CI, and regression guards
Objective: Prevent regressions and measure impact continuously.

H.1 — Add performance CI job
- Tasks:
  - Add a GitHub Actions job that runs a small benchmark script:
    - Boots WordPress (or uses WP-CLI)
    - Loads a representative admin page, frontend page, and an AJAX endpoint
    - Records `$wpdb->num_queries`, `memory_get_peak_usage()`, and wall time
  - Fail PRs when thresholds are exceeded compared to baseline.
- Est. effort: 1–2 days

H.2 — Add request-level telemetry for staging/dev
- Tasks:
  - Add optional telemetry (only in staging/dev) to log class instantiations, query counts, and significant events (e.g., cache misses).
  - Use it to validate lazy-init and container registration coverage.
- Est. effort: 1 day

H.3 — Load testing and real-world smoke
- Tasks:
  - Run load tests for generation and admin UI concurrency (wrk/ab).
  - Validate AI API rate-limits, background job behavior.
- Est. effort: 1–2 days

Advanced / Future improvements (post-integration)
- Persistent object cache support (Redis/memcached): use `wp_cache` with TTLs for notifications, templates, schedule entries.
- Background workers & job segmentation: use Action Scheduler or background workers for long-running AI tasks; chunk generator jobs.
- Pre-warm caches for heavy admin operations.
- Consider JSON columns or denormalized columns to reduce `unserialize()` costs for frequently-read state.
- Compiled template cache / pre-rendering for repeated templates.
- Circuit-breakers and retry shaping around external AI APIs.

PR & rollout strategy (practical)
- Make small focused PRs (one Step or logical group per PR) containing:
  - Clear scope and migration plan in PR description.
  - Feature flag for risky behavior (boot dispatcher) so you can flip back quickly.
  - Benchmarks: before vs after (`num_queries`, memory, wall time).
  - Backward compatibility tests and smoke tests.
- Suggested PR sequence (priority order):
  1. Composer autoload + CI autoload verification
  2. `AIPS_Config` centralization + per-request config cache
  3. Notifications admin bar fix (count gate + TTL + invalidation)
  4. `instance()` singletons + container registration (registration only)
  5. Lazy-init admin bar + scheduler lazy closures
  6. `boot_*` dispatcher behind flag + `AIPS_Ajax_Registry` wiring
  7. DTO adoption in generator and scheduler flows
  8. Identity map & persistent caching decorators
  9. JS payload scoping and split localization
  10. CI performance job and telemetry

Risks & mitigations
- Risk: Breaking AJAX endpoints during registry migration.
  - Mitigation: Migrate controllers incrementally and test each action with end-to-end tests.
- Risk: Regression after boot refactor.
  - Mitigation: Feature flag for boot dispatcher, smoke tests for each context, quick revert path.
- Risk: Stale data from persistent caches.
  - Mitigation: Conservative TTLs, explicit invalidation on writes, and only enable decorators when persistent cache detected.
- Risk: Increased complexity for contributors.
  - Mitigation: Document container usage and conventions; add examples in README; keep simple fallback defaults for a while.

Concrete next steps (recommended immediate work)
1. Create a small “Phase A” PR that:
   - Adds composer classmap support and runs `composer dump-autoload` in CI.
   - Adds a lightweight performance benchmark script under `scripts/perf-check.php` (measures DB queries and memory for representative requests).
2. Run the benchmark on current `main` and store baselines.
3. Open the PR for `AIPS_Config` centralization (Step 2 + Step 10) with before/after numbers from the benchmark.

Optional help I can provide next
- Draft the Phase A PR (composer.json edits + CI workflow snippet + perf script).
- Or scan the repo for remaining direct `get_option()` calls and prepare a patch replacing them with `AIPS_Config::get_instance()->get_option()` in small batches and prepare PR diffs.

Which would you like me to do next?
- Draft the Phase A PR (composer + CI + perf baseline), or
- Start replacing direct `get_option()` calls with `AIPS_Config` and prepare PRs for Step A.2?