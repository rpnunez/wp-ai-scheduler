# Plan Implementation Order

_Dependency-ordered sequence derived from [`plan.md`](./plan.md) and [`plan-2.md`](./plan-2.md)_

Each item lists what it **requires** (must already be done) and what it **unlocks** (items that become safe to implement next). Items with no requirements can begin immediately.

---

## Step 1 — Add Composer Classmap Autoloading

**Source:** plan-2 §6  
**Requires:** nothing  
**Unlocks:** everything — this is a zero-risk infrastructure step that should be done first so all subsequent code lands in the improved autoloading environment

Replace the custom `AIPS_Autoloader` with a Composer-generated classmap. Run `composer dump-autoload --optimize`. Keep the existing autoloader as a fallback shim during transition.

---

## Step 2 — Centralize All `get_option()` Calls Through `AIPS_Config`

**Source:** plan-2 §10  
**Requires:** Step 1 (Composer autoloading in place)  
**Unlocks:** Step 10 (per-request config cache), Step 14 (split localization objects)

Route all `get_option('aips_*', ...)` calls through `AIPS_Config::get_instance()->get_option()`. Add typed accessor methods for option groups that are read together. This establishes the single point of truth needed before adding caching in Step 10.

---

## Step 3 — Add Composite DB Indexes to `aips_notifications`

**Source:** plan §B3  
**Requires:** nothing (schema-only change, no code dependencies)  
**Unlocks:** Step 4 (the index must exist before the query optimization is meaningful)

Add `KEY is_read_created_at (is_read, created_at)` and `KEY dedupe_key_created_at (dedupe_key, created_at)` to `AIPS_DB_Manager::get_schema()` and apply via a versioned `AIPS_Upgrades` migration.

---

## Step 4 — Fix Admin Bar: Gate `get_unread()` on Count and Add Cache TTL/Invalidation

**Source:** plan §B1, §B2  
**Requires:** Step 3 (indexes in place to make the guarded query fast when it does execute)  
**Unlocks:** Step 5 (these two changes reduce the repository's surface before the singleton wrapper is added)

1. Skip `get_unread()` call when `count_unread()` returns 0.
2. Set explicit 60-second TTL on `wp_cache_set()`.
3. Call `wp_cache_delete()` inside `ajax_mark_read()` and `ajax_mark_all_read()`.

---

## Step 5 — Add Static `instance()` Singletons to Stateless Infrastructure Services

**Source:** plan-2 §2  
**Requires:** Steps 1–2 (clean autoloading; `AIPS_Config` already centralized so it can serve as the reference pattern)  
**Unlocks:** Step 7 (container registration is trivial once the pattern exists), Step 6 (admin bar lazy-init becomes one line once the singleton exists)

Add `static instance()` factory methods to:
- `AIPS_History_Repository`
- `AIPS_History_Service` (default `$repository` argument becomes `AIPS_History_Repository::instance()`)
- `AIPS_Notifications_Repository`
- `AIPS_Logger`
- `AIPS_Interval_Calculator`
- `AIPS_Template_Repository`
- `AIPS_AI_Service`

---

## Step 6 — Fix Admin Bar: Lazy-Init Repository for Non-Admin Users

**Source:** plan §B5  
**Requires:** Step 5 (`AIPS_Notifications_Repository::instance()` available for lazy resolution)  
**Unlocks:** nothing (standalone fix)

Move `new AIPS_Notifications_Repository()` out of `AIPS_Admin_Bar::__construct()` into the methods that use it, resolved via `AIPS_Notifications_Repository::instance()`.

---

## Step 7 — Build `AIPS_Container`

**Source:** plan-2 §1  
**Requires:** Step 5 (existing singletons serve as the first registered bindings; validates the container works correctly before the complex refactors)  
**Unlocks:** Steps 8, 9, 11, 12, 13 (all later architectural items depend on the container being in place)

Implement `class-aips-container.php` with `bind()`, `singleton()`, and `make()`. Register Step 5's singletons immediately as the first bindings:
```php
$c->singleton(AIPS_History_Repository::class, fn() => AIPS_History_Repository::instance());
```

---

## Step 8 — Fix Frontend Taxonomy Registration

**Source:** plan §C1  
**Requires:** nothing (one-line guard, zero dependencies)  
**Unlocks:** nothing (standalone fix)

Wrap `register_taxonomy('aips_source_group', ...)` in `if (is_admin() || wp_doing_cron())`.

_(This step is placed here rather than first because it is trivial and can be batched with any nearby work.)_

---

## Step 9 — Fix `save_post` Meta Query Short-Circuit

**Source:** plan §B4  
**Requires:** nothing (isolated change in `AIPS_Partial_Generation_State_Reconciler`)  
**Unlocks:** nothing (standalone fix)

Replace the three `get_post_meta()` calls with a single `metadata_exists()` fast-path check before falling through to the full check. Can be done in the same PR as Step 8.

---

## Step 10 — Add Per-Request Config Cache to `AIPS_Config`

**Source:** plan-2 §10 (cache layer), plan §D2 (prerequisite for scoped localization)  
**Requires:** Step 2 (all callers already going through `AIPS_Config`) + Step 7 (container in place)  
**Unlocks:** Step 14 (the config cache makes page-scoped option reads cheap enough that splitting localization is worthwhile)

Add a `$resolved` array cache inside `AIPS_Config::get_option()` to avoid repeated `get_option()` + `unserialize` calls for the same key within a single request.

---

## Step 11 — Build `AIPS_Ajax_Registry` and `AIPS_Ajax_Response`

**Source:** plan-2 §7, §9  
**Requires:** Step 7 (container in place to resolve controllers from the registry)  
**Unlocks:** Step 12 (context bootstrap's `boot_ajax()` method depends on the registry)

1. Create `AIPS_Ajax_Registry`: a static map of all ~100 action names → controller class names.
2. Create `AIPS_Ajax_Response`: static `success()` and `error()` helpers with a consistent JSON shape.
3. Migrate all controller handlers from inline `wp_send_json_*` calls to `AIPS_Ajax_Response`.

---

## Step 12 — Add Interfaces for Major Dependency Seams

**Source:** plan-2 §4  
**Requires:** Step 7 (container is the injection point; interfaces without a container are incomplete)  
**Unlocks:** Step 13 (in-request identity map is implemented as a decorator that only makes sense once callers depend on the interface, not the concrete)

Define interfaces:
- `AIPS_History_Repository_Interface`
- `AIPS_History_Service_Interface`
- `AIPS_AI_Service_Interface`
- `AIPS_Logger_Interface`
- `AIPS_Schedule_Repository_Interface`
- `AIPS_Notifications_Repository_Interface`

Update all constructor type hints. Register concrete implementations in the container. No behavioral change at this step.

---

## Step 13 — Add In-Request Identity Map Cache to Repositories

**Source:** plan-2 §8  
**Requires:** Step 12 (callers depend on the interface, so swapping in a caching implementation is safe) + Step 5 (repositories must be singletons for the cache to be shared)  
**Unlocks:** Step 14 (reduces the cost of the extra option reads that scoped localization introduces)

Add `private array $cache = []` and a `$all_loaded` flag to:
- `AIPS_Template_Repository::get_by_id()` / `get_all()`
- `AIPS_Schedule_Repository` (most-read methods)
- `AIPS_Voices_Repository::get_all()`

---

## Step 14 — Scope `admin-embeddings.js` and Split `aipsAdminL10n`

**Source:** plan §D1, §D2  
**Requires:** Step 10 (config cache in place so page-scoped option reads don't add new query cost) + Step 13 (repository caches reduce the per-page overhead before we add per-page localization logic)  
**Unlocks:** nothing (standalone improvements)

1. Guard `admin-embeddings.js` enqueue inside the `aips-authors` / `aips-author-topics` page check in `AIPS_Admin_Assets`.
2. Extract page-specific keys from `aipsAdminL10n` into smaller objects pushed only on the relevant page.

---

## Step 15 — Refactor `init()` into Context-Aware Boot Methods

**Source:** plan-2 §3  
**Requires:** Step 11 (AJAX registry ready for `boot_ajax()`) + Step 7 (container resolves controllers in `boot_admin()`)  
**Unlocks:** Step 16 (typed DTOs are most useful once the context split makes it clear which shape goes where)

Replace the monolithic `AI_Post_Scheduler::init()` with:
- `boot_common()` — taxonomy, text domain
- `boot_cron()` — scheduler/generator hooks only
- `boot_ajax()` — resolves the single controller for `$_REQUEST['action']` from the registry
- `boot_admin()` — registers menu, assets, defers controller init to `load-{page}` hooks
- `boot_frontend()` — admin bar only

---

## Step 16 — Introduce Typed DTOs / Value Objects

**Source:** plan-2 §5  
**Requires:** Step 15 (context split clarifies ownership of each data shape) + Step 12 (interfaces identify the seams where DTOs cross layer boundaries)  
**Unlocks:** nothing; this is the final structural improvement

Introduce:
- `AIPS_Generation_Result` — replaces ad-hoc generation result arrays; named constructors `::success()` / `::failure()`
- `AIPS_Schedule_Entry` — wraps `aips_schedule` DB rows
- `AIPS_Template_Data` — wraps `aips_templates` DB rows

`AIPS_Bulk_Generation_Result` (already uses `public readonly`) is the existing precedent; extend the pattern here.

---

## Dependency Graph

```
Step 1 (Composer autoloading)
 └─► Step 2 (Config centralization)
      └─► Step 10 (Config cache)
               └─► Step 14 (Scoped assets/L10n)

Step 3 (DB indexes)
 └─► Step 4 (Admin bar query + cache fix)

Step 1 ──► Step 5 (Singletons)
                ├─► Step 6 (Admin bar lazy-init)
                └─► Step 7 (DI Container)
                         ├─► Step 11 (AJAX Registry + Response)
                         │        └─► Step 15 (Context bootstrap)
                         │                 └─► Step 16 (Typed DTOs)
                         └─► Step 12 (Interfaces)
                                  └─► Step 13 (Identity map cache)
                                           └─► Step 14 (Scoped assets/L10n)

Steps 8, 9 — standalone, no dependencies, can be done at any time
```

---

## Summary Table

| Step | Item | Requires | Effort |
|------|------|----------|--------|
| 1 | Composer classmap autoloading | — | Trivial |
| 2 | Centralize `get_option()` through `AIPS_Config` | 1 | Low |
| 3 | Add composite DB indexes to `aips_notifications` | — | Low |
| 4 | Fix admin bar query + cache | 3 | Low |
| 5 | Static `instance()` singletons on 7 services | 1, 2 | Low |
| 6 | Admin bar lazy repository init | 5 | Trivial |
| 7 | Build `AIPS_Container` | 5 | Medium |
| 8 | Guard frontend taxonomy registration | — | Trivial |
| 9 | Fix `save_post` meta short-circuit | — | Trivial |
| 10 | Add per-request config cache | 2, 7 | Low |
| 11 | Build `AIPS_Ajax_Registry` + `AIPS_Ajax_Response` | 7 | Medium |
| 12 | Add interfaces for major seams | 7 | Medium |
| 13 | In-request identity map cache in repositories | 5, 12 | Low |
| 14 | Scope embeddings script + split `aipsAdminL10n` | 10, 13 | Low |
| 15 | Context-aware `boot_*()` methods | 7, 11 | Medium |
| 16 | Typed DTOs (`AIPS_Generation_Result`, etc.) | 12, 15 | Medium |
