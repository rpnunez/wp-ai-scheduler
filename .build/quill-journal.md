## 2026-10-12 - Batch Author Topics Status Updates

- **Target:** ai-post-scheduler/includes/class-aips-author-topics-repository.php::AIPS_Author_Topics_Repository::update_status_bulk
- **Lens:** N+1 queries
- **Problem:** Bulk approving and rejecting topics in AIPS_Author_Topics_Controller executed 1 UPDATE query per topic in a loop (N queries for N topics).
- **Fix:** Added batched `AIPS_Author_Topics_Repository::update_status_bulk()` executing a single `WHERE id IN (...)` UPDATE query and invalidating cache tags for affected authors/topics.
- **Before / After:**
  - N=10: 10 queries -> 1 query
  - N=100: 100 queries -> 1 query
  - N=500: 500 queries -> 1 query
- **Cache invalidation:** Invalidates `author_topic` cache domain for all affected author IDs and topic IDs.
- **Backward compatibility:** Preserved original `update_status()` public signature and behavior. Added `update_status_bulk()` alongside.
- **Tests:** AIPS_Author_Topics_Bulk_Actions_Test

## 2026-10-13 - Optimize AIPS_Config get_option Cache Reads With Explicit Defaults

- **Target:** ai-post-scheduler/includes/class-aips-config.php::AIPS_Config::get_option
- **Lens:** Uncached/repeated option reads
- **Problem:** Whenever a caller passed an explicit $default parameter to AIPS_Config::get_option($option_name, $default) (such as aips_related_posts_enabled, aips_publish_linking_mode, etc.), AIPS_Config bypassed its per-request in-memory cache check $this->cache->has($option_name) and triggered a redundant WordPress get_option() database lookup on every call (N queries for N reads).
- **Fix:** Removed the $default === null bypass from the per-request cache check, and cache the registered default when the caller's $default is null or strictly equals it. A `default_resolved` marker ensures a cached registered default is not returned to callers passing a different default. The persistent-cache branch still requires $default === null.
- **Before / After:**
  - Default fallback option reads (aips_related_posts_enabled, default = true):
    - N=10: 10 database lookups -> 1 lookup
    - N=100: 100 database lookups -> 1 lookup
    - N=500: 500 database lookups -> 1 lookup
  - Stored DB option reads (aips_related_posts_enabled, default = true):
    - N=10: 10 database lookups -> 1 lookup
    - N=100: 100 database lookups -> 1 lookup
    - N=500: 500 database lookups -> 1 lookup
- **Cache invalidation:** Hook-based invalidation (updated_option, added_option, deleted_option) and set_option() flush $this->cache as before.
- **Backward compatibility:** Preserved AIPS_Config::get_option() signature, ad-hoc fallback behavior for unregistered options, and return types.
- **Tests:** Test_AIPS_Config: added explicit-default caching and mixed-default tests; also corrected stale expectations (log level follows debug mode, similarity threshold 0.85). Not re-run after review fixes.

## 2026-10-16 - Batch Author Lookups in Retry Failed Slices

- **Target:** ai-post-scheduler/includes/class-aips-author-slice-scheduler-base.php::AIPS_Author_Slice_Scheduler_Base::retry_failed_slices
- **Lens:** Cron/batch hot paths
- **Problem:** Retrying failed author slice cron jobs executed 1 SELECT query per author ID in a loop (N queries for N failed author IDs).
- **Fix:** Added batched `AIPS_Authors_Repository::get_by_ids()` executing a single `WHERE id IN (...)` query and updated `retry_failed_slices()` to call it once.
- **Before / After:**
  - N=10: 10 queries -> 1 query
  - N=100: 100 queries -> 1 query
  - N=500: 512 queries -> 1 query
- **Cache invalidation:** Not applicable (direct ID query on transient retry execution).
- **Backward compatibility:** Preserved original `get_by_id()` public signature and behavior. Added `get_by_ids()` alongside.
- **Tests:** Test_Author_Slice_Scheduler_Retry
