Roll out the repository cache framework by repository category, not all at once.

## Objective

- [ ] Migrate all repository caching to the new framework:
    - `AIPS_Cacheable_Repository`
    - `AIPS_Repository_Cache_Dependencies`
    - tag-version invalidation
    - explicit operation policies
- [ ] Retire repository usage of:
    - `AIPS_Cache_Invalidation_Bus`
    - `AIPS_Cache_Policy`

## Guardrails

- [ ] Do not add new repository usages of legacy cache bus/policy
- [ ] Require explicit operation IDs for new repository caching work
- [ ] Migrate in small, test-backed phases
- [ ] Keep legacy classes temporarily for non-repository or untouched callers
- [ ] Remove legacy repository paths only after migration exit criteria are met

## Phase 0 — Freeze legacy expansion

- [x] Enforce no new repository reads/writes using legacy cache bus/policy
- [x] Add migration tracking checklist for repositories still using legacy invalidation
- [x] Freeze expansion of repository-level legacy cache patterns

## Phase 1 — Stabilize framework blockers

- [x] Fix cache instance parity so invalidation bumps tag versions on the same cache instance used by repository reads
- [x] Fix `force_refresh` to do read bypass + write-through refresh
- [x] Add focused PHPUnit coverage for:
    - [x] named cache instance parity
    - [x] `force_refresh` refresh behavior
    - [x] tag-version invalidation correctness

## Phase 2 — Repository rollout

For each repository migrated:

- [ ] Add `use AIPS_Cacheable_Repository;`
- [ ] Replace manual cache keying/direct cache calls with `cache_read()` and explicit operation IDs
- [ ] Define `repository_cache_group()`
- [ ] Define `repository_cache_policies()`
- [ ] Add read tags and invalidation domains in `AIPS_Repository_Cache_Dependencies`
- [ ] Replace `AIPS_Cache_Invalidation_Bus::invalidate(...)` with:
    - [ ] `invalidate_cache_domain(...)`, or
    - [ ] `invalidate_cache_tags(...)` where domain mapping is not yet needed
- [ ] Preserve non-cache side effects
- [ ] Add focused tests for:
    - [ ] cache hit/miss
    - [ ] bypass paths
    - [ ] null caching
    - [ ] domain invalidation

### Apply caching only to read methods that are:

- [ ] deterministic for the provided arguments
- [ ] not lock-sensitive
- [ ] not responsible for claiming work
- [ ] invalidated by a known domain event

### Recommended rollout order

1. Authors and author topics
2. Dashboard/count repositories
3. Templates, voices, structures, prompt sections
4. Schedule read models
5. Sources and source data
6. Internal links
7. History and telemetry
8. Job/queue repositories only where reads are demonstrably safe

### Low risk

- [ ] `AIPS_Template_Repository`
- [ ] `AIPS_Voices_Repository`
- [ ] `AIPS_Article_Structure_Repository`
- [ ] `AIPS_Prompt_Section_Repository`

### Medium risk

- [ ] `AIPS_Schedule_Repository`
- [ ] `AIPS_Sources_Repository`
- [ ] `AIPS_Sources_Data_Repository`
- [ ] `AIPS_Internal_Links_Repository`
- [ ] `AIPS_Taxonomy_Repository`

### High risk

- [ ] `AIPS_History_Repository`
- [ ] `AIPS_Telemetry_Repository`
- [ ] job and batch queue classes
- [ ] due/claim/retry generation queries

## Phase 3 — Legacy compatibility window

- [ ] Keep legacy classes available for remaining non-repository consumers
- [ ] Mark repository-facing legacy entry points as deprecated once migration begins
- [ ] Track remaining repository call sites until usage reaches zero

## Phase 4 — Deprecation and removal

### Exit criteria

- [ ] All repositories migrated to the new framework
- [ ] No repository call sites remain on legacy bus/policy
- [ ] Migration test suite passes for:
    - [ ] tag-version invalidation
    - [ ] bypass controls
    - [ ] domain invalidation

### Removal tasks

- [ ] Remove repository-specific legacy pathways
- [ ] Remove legacy classes only after confirming no remaining consumers

## Validation per migration wave

- [ ] Run repository boundary lint
- [ ] Run targeted PHPUnit tests for touched repositories and cache framework classes
- [ ] Verify invalidation changes key derivation through tag-version changes
- [ ] Verify queue/cron/lock-sensitive reads remain bypassed where required

## Deliverables

- [ ] Repository-by-repository migration checklist
- [ ] Updated dependency-map coverage for migrated repositories
- [ ] PHPUnit coverage for each migration wave
- [ ] Final deprecation/removal PR after repository legacy usage reaches zero