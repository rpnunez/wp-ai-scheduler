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
