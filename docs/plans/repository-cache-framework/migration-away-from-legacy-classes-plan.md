# Migration away from legacy repository cache classes

## Goal

Phase repository caching fully onto the new repository cache framework (`AIPS_Cacheable_Repository`, `AIPS_Repository_Cache_Dependencies`, tag-version invalidation, and explicit operation policies) and retire legacy repository usage of `AIPS_Cache_Invalidation_Bus` and `AIPS_Cache_Policy`.

## Principles

- Freeze new repository usage of legacy cache bus/policy immediately.
- Fix framework blockers before broad rollout.
- Migrate repositories in small, test-backed phases.
- Keep legacy classes temporarily for untouched/non-repository callers during transition.
- Deprecate and remove legacy paths only after migration exit criteria are met.

## Phase 0 — Freeze legacy expansion

1. Enforce: no new repository reads/writes should add `AIPS_Cache_Invalidation_Bus` or `AIPS_Cache_Policy` usage.
2. Add a migration tracking checklist per repository currently using legacy cache invalidation.
3. Require explicit operation IDs for any new repository caching work.

## Phase 1 — Stabilize framework blockers

1. Ensure invalidation bumps tag versions on the same cache instance used for repository reads (named cache instance parity).
2. Ensure `force_refresh` performs read bypass + write-through refresh (not bypass-only).
3. Lock these behaviors with focused PHPUnit tests before additional repository migrations.

## Phase 2 — Repository migration waves

For each repository in scope:

1. Add `use AIPS_Cacheable_Repository;`.
2. Replace manual cache keying + direct cache get/set/has with `cache_read()` using explicit operation IDs.
3. Define `repository_cache_group()` and `repository_cache_policies()`.
4. Add read tags and invalidation domains in `AIPS_Repository_Cache_Dependencies`.
5. Replace `AIPS_Cache_Invalidation_Bus::invalidate(...)` calls with `invalidate_cache_domain(...)` (or `invalidate_cache_tags(...)` when domain mapping is not yet needed).
6. Keep non-cache side effects unchanged (for example transients and operational hooks).
7. Add focused tests for hit/miss, bypass paths, null-caching behavior, and domain invalidation.

Recommended order:

1. `AIPS_Post_Slices_Repository`
2. `AIPS_Prompt_Section_Repository`
3. `AIPS_Article_Structure_Repository`
4. `AIPS_Schedule_Repository` (last due to higher queue/cron sensitivity)

## Phase 3 — Legacy compatibility period

1. Keep `AIPS_Cache_Invalidation_Bus` and `AIPS_Cache_Policy` available for remaining non-migrated/non-repository consumers.
2. Mark repository-facing legacy entry points as deprecated in code comments/changelog notes once migrations start.
3. Track remaining call sites until repository usage reaches zero.

## Phase 4 — Deprecation and removal

1. Exit criteria:
   - All repositories migrated to the new framework.
   - No repository call sites use legacy bus/policy.
   - Migration test suite passes for tag-version invalidation, bypass controls, and domain invalidation.
2. Remove repository-specific legacy pathways.
3. Remove legacy classes only after confirming no remaining consumers.

## Validation checklist (per wave)

- Run repository boundary lint.
- Run targeted PHPUnit tests for touched repositories and cache framework classes.
- Verify invalidation changes key derivation through tag-version changes.
- Verify queue/cron/lock-sensitive reads remain bypassed where required.

## Deliverables

- Repository-by-repository migration checklist.
- Updated dependency-map coverage for migrated repositories.
- PHPUnit coverage proving correctness and safety of each migration wave.
- Final deprecation/removal PR once legacy repository usage is zero.
