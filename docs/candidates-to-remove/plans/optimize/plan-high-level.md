# Optimization Master Plan — High-Level Summary

_Synthesized from [`plan.md`](./plan.md) (tactical hotspots) and [`plan-2.md`](./plan-2.md) (architectural root causes) — April 2026_

For implementation sequencing, see [`plan-order.md`](./plan-order.md).

---

## What we are solving

The plugin has two distinct but related problem categories:

1. **Tactical hotspots** (`plan.md`) — Specific, measurable inefficiencies: duplicate object construction, unconditional DB queries, missing indexes, over-broad asset loading. Each item can be fixed independently and delivers immediate gains.

2. **Architectural root causes** (`plan-2.md`) — The structural patterns that produce the tactical hotspots. Fixing these prevents entire categories of future issues from emerging, and simultaneously speeds up development by making the codebase easier to extend, test, and reason about.

The two plans are complementary. Tactical fixes deliver quick wins; architectural improvements raise the ceiling so the quick wins don't have to be rediscovered after every new feature.

---

## Tactical Hotspots — Summary ([`plan.md`](./plan.md))

### Bootstrap / Object Instantiation

| ID | Problem | Priority |
|----|---------|----------|
| A1 / E1 | ~20 controllers + 100+ `wp_ajax_*` hooks registered on every non-AJAX admin page | **High** |
| A2 | `AIPS_Scheduler` constructed twice per admin request, spawning 14 child objects | Medium |
| A3 | `AIPS_Author_Post_Generator` constructed 3× per admin request | Low |
| A4 | `AIPS_Notifications` constructed 3× per request, spawning 5 child objects each | Medium |
| A5 | `AIPS_History_Service` / `AIPS_History_Repository` independently instantiated in ~10 classes | Low |
| A6 | All cron scheduler objects initialized on every non-cron page load | Medium |

### Database Queries on Every Page Load

| ID | Problem | Priority |
|----|---------|----------|
| B1 | Admin bar fires `SELECT *` on `aips_notifications` even when unread count is 0 | **High** |
| B2 | Notification count object cache has no TTL and no mark-read invalidation | **High** |
| B3 | `aips_notifications` missing composite indexes `(is_read, created_at)` and `(dedupe_key, created_at)` | Medium |
| B4 | `AIPS_Partial_Generation_State_Reconciler` triggers up to 3 `get_post_meta()` calls on every `save_post` for non-AIPS posts | Medium |
| B5 | `AIPS_Admin_Bar` constructs `AIPS_Notifications_Repository` even for non-admin users | Low |

### Frontend / Asset Loading

| ID | Problem | Priority |
|----|---------|----------|
| C1 | `register_taxonomy('aips_source_group')` runs on every frontend visitor request | Medium |
| D1 | `admin-embeddings.js` enqueued on all plugin pages, not just Authors/Author Topics | Low |
| D2 | `aipsAdminL10n` pushes 50+ strings to every plugin page regardless of relevance | Low |

---

## Architectural Improvements — Summary ([`plan-2.md`](./plan-2.md))

### Core Infrastructure

| # | Pattern | What it enables |
|---|---------|----------------|
| 1 | **DI Container (`AIPS_Container`)** | Singleton + transient scopes; resolves root cause of all A-group hotspots; one registration point per class |
| 2 | **Static `instance()` singletons** | Quick-win predecessor to the container; eliminates N-per-request allocations for stateless services |
| 3 | **Context-aware bootstrap** | Splits `init()` into `boot_cron()` / `boot_ajax()` / `boot_admin()` / `boot_frontend()`; eliminates A1/A6 entirely |
| 6 | **Composer classmap autoloading** | O(1) class resolution; replaces custom `file_exists()` autoloader; enables PSR-4 migration |

### Developer Velocity

| # | Pattern | What it enables |
|---|---------|----------------|
| 4 | **Interface-driven dependencies** | Caching decorators, clean PHPUnit mocks, swappable implementations at the container level |
| 5 | **Typed DTOs / value objects** | `AIPS_Generation_Result`, `AIPS_Schedule_Entry`, `AIPS_Template_Data` — IDE completion, static analysis |
| 9 | **`AIPS_Ajax_Response` standard object** | Consistent JSON shape across all controllers; one-liner error/success responses |
| 10 | **Centralize all `get_option()` through `AIPS_Config`** | Single file to change a key name or default; per-request cache layer |

### AJAX / Routing Architecture

| # | Pattern | What it enables |
|---|---------|----------------|
| 7 | **AJAX action→controller registry** | One controller resolved per AJAX request; single source of truth for all ~100 actions |
| 8 | **In-request repository identity map** | Singleton repositories cache DB reads for the duration of a request; eliminates repeat identical queries |

---

## Impact Matrix

| plan-2 Item | plan.md Hotspots Fixed Structurally |
|-------------|-------------------------------------|
| 1 — DI Container | A2, A3, A4, A5 |
| 2 — Singletons | A4, A5, B5 |
| 3 — Context bootstrap | A1, A6, E1, D2 |
| 4 — Interfaces | Enables future B2/B4 caching decorators |
| 7 — AJAX registry | A1, E1 (direct) |
| 8 — Identity map cache | B1 (template/schedule reads), B4 |
| 10 — Config centralization | B2, D2 |

---

## Recommended Adoption Approach

### Phase 1 — Immediate wins, zero structural risk

1. Add `instance()` singletons to `AIPS_History_Repository`, `AIPS_History_Service`, `AIPS_Notifications_Repository`, `AIPS_Logger` (plan-2 §2)
2. Add composite indexes to `aips_notifications` table (plan §B3)
3. Fix admin bar unconditional `SELECT *` and cache TTL/invalidation (plan §B1, §B2)
4. Stop taxonomy registration on frontend (plan §C1)
5. Fix `save_post` meta query short-circuit (plan §B4)
6. Scope `admin-embeddings.js` to Authors pages only (plan §D1)

### Phase 2 — Core structural refactors

7. Switch to Composer classmap autoloading (plan-2 §6)
8. Build `AIPS_Container`; register Phase 1 singletons inside it (plan-2 §1)
9. Build `AIPS_Ajax_Registry`; map all 100 actions to their controllers (plan-2 §7)
10. Refactor `init()` into context-specific boot methods (plan-2 §3)
11. Introduce `AIPS_Ajax_Response` and migrate all controller handlers (plan-2 §9)

### Phase 3 — Developer-velocity improvements

12. Add interfaces for `AIPS_History_Repository`, `AIPS_History_Service`, `AIPS_AI_Service`, `AIPS_Logger` (plan-2 §4)
13. Add in-request identity map cache to `AIPS_Template_Repository`, `AIPS_Schedule_Repository` (plan-2 §8)
14. Introduce typed DTOs: `AIPS_Generation_Result`, `AIPS_Schedule_Entry` (plan-2 §5)
15. Centralize all `get_option('aips_*')` through `AIPS_Config` (plan-2 §10)
16. Split `aipsAdminL10n` into page-scoped localization objects (plan §D2)

See [`plan-order.md`](./plan-order.md) for the full dependency-ordered implementation sequence.
