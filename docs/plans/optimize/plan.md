# Performance Optimization Plan

_Analyzed from codebase snapshot — April 2026_

This document catalogs concrete, actionable performance optimization targets for the AI Post Scheduler plugin. Each target is self-contained and can be implemented independently. Items are grouped by concern and ordered by priority within each group.

---

## A. Bootstrap / Object Instantiation Overhead

### A1 — 100+ `wp_ajax_*` callbacks registered on every admin page (High)

**Problem:** Every admin page (posts list, Gutenberg, WooCommerce, media, etc.) triggers `is_admin()` and therefore instantiates all ~20 controllers:
`AIPS_Schedule_Controller`, `AIPS_Author_Topics_Controller`, `AIPS_Authors_Controller`, `AIPS_Post_Review`, `AIPS_Research_Controller`, `AIPS_Voices`, `AIPS_History`, `AIPS_Planner`, `AIPS_Structures_Controller`, `AIPS_Prompt_Sections_Controller`, `AIPS_AI_Edit_Controller`, `AIPS_Calendar_Controller`, `AIPS_Sources_Controller`, `AIPS_Taxonomy_Controller`, `AIPS_Data_Management`, `AIPS_Templates_Controller`, `AIPS_DB_Manager`, `AIPS_Settings`, etc.

Collectively these register ~100+ AJAX hooks that have zero chance of firing on a non-AJAX page. The hook registrations themselves are cheap, but the _constructors_ eagerly `new`-up repositories, services, and other dependencies, causing cascading object creation even though no AJAX action will be dispatched.

**Recommendation:** Wrap the entire admin-only controller block in an additional `wp_doing_ajax()` branch or adopt a lazy-init pattern. For non-AJAX admin requests (page views), defer instantiation until the admin menu page is actually being rendered (using the `load-{page}` or `current_screen` hook). For AJAX requests, only instantiate the controller whose action matches `$_REQUEST['action']`.

---

### A2 — `AIPS_Scheduler` instantiated twice in admin context (Medium)

**Problem:** Inside `is_admin()`, `AIPS_Schedule_Controller::__construct()` always creates `new AIPS_Scheduler()`. Then, unconditionally later in `init()`, a second `new AIPS_Scheduler()` is created and bound to the cron hook. `AIPS_Scheduler::__construct()` eagerly creates: `AIPS_Interval_Calculator`, `AIPS_Schedule_Repository`, `AIPS_Template_Repository`, `AIPS_History_Repository`, `AIPS_History_Service`, `AIPS_Template_Type_Selector`, and `AIPS_Schedule_Processor` — so this duplication means 14+ objects are created for nothing.

**Recommendation:** Share the scheduler instance from `AIPS_Schedule_Controller` and bind it to the cron hook, or skip the second instantiation. Alternatively, make the scheduler a singleton.

---

### A3 — `AIPS_Author_Post_Generator` instantiated 3 times in admin (Low)

**Problem:**
1. `AIPS_Author_Topics_Controller::__construct()` → `new AIPS_Author_Post_Generator()`
2. `init()` bootstrap → `new AIPS_Author_Post_Generator()` for the cron hook
3. `AIPS_Authors_Controller::__construct()` → `new AIPS_Author_Topics_Scheduler()` which internally creates `new AIPS_Author_Topics_Generator()`

`AIPS_Author_Post_Generator` creates: `AIPS_Authors_Repository`, `AIPS_Author_Topics_Repository`, `AIPS_Author_Topic_Logs_Repository`, `AIPS_Generator`, `AIPS_Logger`, `AIPS_Interval_Calculator`, `AIPS_Topic_Expansion_Service`, `AIPS_History_Service`, `AIPS_Generation_Execution_Runner`.

**Recommendation:** Re-use the same instance between the `AIPS_Author_Topics_Controller` (which uses it for its AJAX handlers) and the cron binding. A shared static instance or passing it in as a constructor argument would eliminate 2 of the 3 allocations.

---

### A4 — `AIPS_Notifications` instantiated 3+ times per request, spawning 5 child objects each time (Medium)

**Problem:** `new AIPS_Notifications()` appears in:
- `AIPS_Author_Topics_Scheduler::__construct()`
- `AIPS_Authors_Controller::__construct()`
- `AI_Post_Scheduler::init()`

Each instantiation creates: `AIPS_Notifications_Repository`, `AIPS_Notification_Templates`, `AIPS_History_Service` (which creates `AIPS_History_Repository`), `AIPS_Notifications_Event_Handler` (guarded by `static $hooks_registered` so hooks are only added once), and `AIPS_Notification_Senders`. The static hook guard is good but all the objects are still re-allocated each time.

**Recommendation:** Introduce a static singleton or a shared instance passed through constructor injection so `AIPS_Notifications` is only constructed once per request.

---

### A5 — `AIPS_History_Service` / `AIPS_History_Repository` duplicated across ~10 classes (Low)

**Problem:** `AIPS_History_Service` wrapping `AIPS_History_Repository` is independently instantiated in: `AIPS_Scheduler`, `AIPS_Author_Topics_Scheduler`, `AIPS_Author_Post_Generator`, `AIPS_Embeddings_Cron`, `AIPS_Notifications`, `AIPS_Authors_Controller > AIPS_Notifications`, `AIPS_Author_Topics_Controller`, `AIPS_Post_Review`, `AIPS_Research_Controller`, and more. These are effectively stateless service wrappers. Each one calls `new AIPS_History_Repository()` which resolves `global $wpdb` — cheap individually, but adds up.

**Recommendation:** A static `AIPS_History_Service::instance()` factory (or pass a shared instance through DI). Many controllers already accept it as a constructor argument; ensure the default falls back to a shared static instance rather than `new AIPS_History_Repository()` each time.

---

### A6 — Cron scheduler objects fully initialized on every non-cron request (Medium)

**Problem:** `AIPS_Scheduler`, `AIPS_Author_Topics_Scheduler`, `AIPS_Author_Post_Generator`, and `AIPS_Embeddings_Cron` are instantiated on **every** page load (frontend and admin) even though they only need to run during WP-Cron. Their constructors eagerly create all repositories and services. On a high-traffic site, this means dozens of objects allocated per visitor request, none of which do useful work outside of cron.

**Recommendation:** Wrap scheduler instantiation in `if (wp_doing_cron())` for frontend contexts. For admin contexts, schedulers can also be deferred to their cron hook callbacks (pass the handler lazily using a closure or a static factory method). The `add_action('aips_generate_scheduled_posts', ...)` binding does not require the object to be constructed immediately — it only needs to be constructed when WordPress actually fires the cron event.

Example pattern:
```php
add_action('aips_generate_scheduled_posts', function() {
    (new AIPS_Scheduler())->process();
});
```

---

## B. Database Queries on Every Page Load

### B1 — Admin bar: `get_unread(20)` runs unconditionally even when count is 0 (High)

**Problem:** In `AIPS_Admin_Bar::add_toolbar_node()`, `count_unread()` is fetched (and cached). Then `$this->repository->get_unread(20)` runs unconditionally — a full `SELECT *` against `aips_notifications` (including a `longtext` meta column) that fetches up to 20 rows, even when the cached count says 0. This means a `SELECT *` hits the table on every admin and frontend page load where the user has `manage_options`.

**File:** `ai-post-scheduler/includes/class-aips-admin-bar.php`

**Recommendation:** Gate `get_unread()` with `if ($unread_count > 0)`. When count is 0, pass an empty array directly to the rendering logic.

---

### B2 — Admin bar: `count_unread()` object cache has no TTL and no invalidation (High)

**Problem:** `wp_cache_set($cache_key, $unread_count, 'aips_admin_bar')` is called without a TTL argument (defaults to 0 = no expiration in this request only). Without a persistent object cache (Redis/Memcached), the cache is effectively per-request and the `COUNT(*)` query runs on every page load.

Even with a persistent cache, there is no invalidation when notifications are marked as read — the count badge can stay stale until the cache naturally expires.

**File:** `ai-post-scheduler/includes/class-aips-admin-bar.php`

**Recommendation:** Set a short explicit TTL (e.g. 30–60 seconds):
```php
wp_cache_set($cache_key, $unread_count, 'aips_admin_bar', 60);
```
Also call `wp_cache_delete($cache_key, 'aips_admin_bar')` inside `ajax_mark_read()` and `ajax_mark_all_read()` so the badge updates immediately after user interaction.

---

### B3 — Notifications table missing composite indexes for the hottest queries (Medium)

**Problem:** The `aips_notifications` schema has single-column indexes on `is_read` and `created_at` separately. The two most-executed queries are:

- `get_unread()`: `SELECT * WHERE is_read = 0 ORDER BY created_at DESC LIMIT 20` — uses the `is_read` key but then requires a filesort on `created_at`.
- `was_recently_sent()`: `SELECT COUNT(*) WHERE dedupe_key = %s AND created_at >= DATE_SUB(...)` — uses the `dedupe_key` key but then applies an unindexed range filter on `created_at`.

**File:** `ai-post-scheduler/includes/class-aips-db-manager.php`

**Recommendation:** Add two composite indexes:
- `KEY is_read_created_at (is_read, created_at)` — covers `get_unread` and `count_unread` with a single index scan
- `KEY dedupe_key_created_at (dedupe_key, created_at)` — covers `was_recently_sent` with a range scan on the right side

These should be added to `AIPS_DB_Manager::get_schema()` and applied via a versioned `AIPS_Upgrades` migration.

---

### B4 — `AIPS_Partial_Generation_State_Reconciler`: 3 `get_post_meta()` calls on every `save_post` for non-AIPS posts (Medium)

**Problem:** `on_save_post()` fires on every save of a `post`-type post. The early-out check reads three separate post meta keys to determine whether AIPS generation meta exists:

```php
$has_generation_meta = '' !== (string) get_post_meta($post_id, 'aips_post_generation_component_statuses', true)
    || '' !== (string) get_post_meta($post_id, 'aips_post_generation_incomplete', true)
    || '' !== (string) get_post_meta($post_id, 'aips_post_generation_had_partial', true);
```

For ordinary posts without any AIPS metadata, WordPress won't have the meta cached at `save_post` time (no preceding read), so these can be 3 separate DB queries. Short-circuit evaluation means only 1–2 fire when a value is found, but on posts with no AIPS data the first two return empty and all 3 execute.

**File:** `ai-post-scheduler/includes/class-aips-partial-generation-state-reconciler.php`

**Recommendation:** Replace with a single `metadata_exists($post_id, 'aips_post_generation_component_statuses')` as the fast-path check (fastest because it only needs to confirm existence), then fall through to the full 3-key check only when it returns true. Alternatively, prime the meta cache by calling `update_meta_cache('post', array($post_id))` before the check, so all 3 reads are a single DB query instead of three.

---

### B5 — `AIPS_Admin_Bar::__construct()` creates `AIPS_Notifications_Repository` unconditionally (Low)

**Problem:** The `AIPS_Admin_Bar` constructor runs on every request (not just admin) and always allocates `new AIPS_Notifications_Repository()`. The actual capability check (`current_user_can('manage_options')`) happens later inside `add_toolbar_node()` and `enqueue_assets()`. For anonymous visitors and non-admin authenticated users, the repository object is created but never used.

**File:** `ai-post-scheduler/includes/class-aips-admin-bar.php`

**Recommendation:** Lazy-initialize the repository inside the methods that use it, or add a `current_user_can('manage_options')` guard in the constructor before creating the repository.

---

## C. Frontend / Non-Admin Request Overhead

### C1 — `register_taxonomy('aips_source_group')` fires on every frontend page load (Medium)

**Problem:** The `aips_source_group` taxonomy is registered unconditionally on `init` for all contexts (frontend + admin). It is `public => false`, `show_ui => false`, `show_in_rest => false`, `rewrite => false`. Nothing on the frontend reads or queries this taxonomy. Registering it on the frontend adds unnecessary work to every visitor request.

**File:** `ai-post-scheduler/ai-post-scheduler.php`

**Recommendation:** Wrap the `register_taxonomy()` call in `if (is_admin() || wp_doing_cron())` since there is no frontend use case.

---

## D. Asset Loading

### D1 — `admin-embeddings.js` enqueued on every plugin admin page (Low)

**Problem:** In `AIPS_Admin_Assets::enqueue_admin_assets()`, `aips-admin-embeddings` is enqueued globally for all plugin pages (no page-specific guard). This script is only relevant on the Authors and Author Topics pages.

**File:** `ai-post-scheduler/includes/class-aips-admin-assets.php`

**Recommendation:** Wrap the `admin-embeddings.js` enqueue in the same guard block that gates `authors.css` and `authors.js`:
```php
if (strpos($hook, 'aips-authors') !== false || strpos($hook, 'aips-author-topics') !== false) {
    // enqueue admin-embeddings here
}
```

---

### D2 — `aipsAdminL10n` object: 50+ translation strings pushed to every plugin page (Low)

**Problem:** `wp_localize_script('aips-admin-script', 'aipsAdminL10n', [...])` inlines a very large JavaScript object (50+ strings covering voices, schedules, structures, sections, onboarding wizard, AI variables, etc.) on every plugin admin page. Most of these strings are only relevant to specific pages but load everywhere, increasing every page's inline `<script>` block size.

**File:** `ai-post-scheduler/includes/class-aips-admin-assets.php`

**Recommendation:** Split into smaller page-specific localization objects pushed only on the relevant page. Shared strings (generic errors, confirm labels) can remain in the global object; page-specific ones (voice/schedule/structure-specific copy) should move to their respective page conditionals.

---

## E. AJAX Architecture

### E1 — All ~100 AJAX callbacks registered even for non-AJAX page views (High)

**Problem:** Even on plain admin page views (HTML responses, not AJAX), all `add_action('wp_ajax_*', ...)` registrations occur. While WordPress's `do_action('wp_ajax_*')` never fires during a page view, all the _constructor work_ in every controller runs just to set up AJAX hooks that will never fire — including allocating repositories, services, calculators, and generators.

**Recommendation (complementary to A1):** When `!wp_doing_ajax()`, skip all controller instantiation. Admin UI needs (rendering, settings, assets) can be handled with much lighter classes initialized on `current_screen`. This is the highest-ROI optimization available and would eliminate the majority of unnecessary object allocation on non-AJAX admin page views.

---

## F. Summary Priority Matrix

| Priority | ID | Description | Effort |
|----------|-----|-------------|--------|
| **High** | A1 / E1 | Eliminate ~20 constructor calls + 100+ hook registrations on every non-AJAX admin page | Medium |
| **High** | B1 | Eliminate unconditional `SELECT *` on every admin/frontend page for zero-notification users | Low |
| **High** | B2 | Make notification count cache work across requests; add invalidation on mark-read | Low |
| **Medium** | A2 | Eliminate duplicate scheduler instance + 14 child objects per admin page | Low |
| **Medium** | A6 | Eliminate all scheduler objects on non-cron page loads | Low |
| **Medium** | B3 | Add composite indexes to notifications table for hottest queries | Low |
| **Medium** | A4 | Eliminate 2 redundant `AIPS_Notifications` constructions | Low |
| **Medium** | C1 | Stop taxonomy registration on every frontend request | Trivial |
| **Medium** | B4 | Reduce meta queries on every post save for non-AIPS posts | Low |
| **Low** | A3 | Reduce `AIPS_Author_Post_Generator` duplication | Low |
| **Low** | A5 | Reduce `AIPS_History_Service` duplication via shared static factory | Low |
| **Low** | D1 | Remove unnecessary embeddings script load from non-authors pages | Trivial |
| **Low** | B5 | Lazy-init notifications repository for non-admin users | Low |
| **Low** | D2 | Reduce inline JS localization object size per page | Medium |
