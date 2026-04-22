**Database Schema (class-aips-db-manager.php)**
-----------------------------------------------

**Date/Time Columns Found:**

*   `aips_history`: `created_at DATETIME DEFAULT CURRENT_TIMESTAMP`, `completed_at DATETIME DEFAULT NULL` (lines 102-103)
*   `aips_history_log`: `timestamp DATETIME DEFAULT CURRENT_TIMESTAMP` (line 122)
*   `aips_templates`: `created_at DATETIME DEFAULT CURRENT_TIMESTAMP`, `updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP` (lines 149-150)
*   `aips_schedule`: `next_run DATETIME NOT NULL`, `last_run DATETIME DEFAULT NULL`, `created_at DATETIME DEFAULT CURRENT_TIMESTAMP` (lines 162-163, 171)
*   `aips_voices`: `created_at DATETIME DEFAULT CURRENT_TIMESTAMP` (line 190)
*   `aips_article_structures`: `created_at DATETIME DEFAULT CURRENT_TIMESTAMP`, `updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP` (lines 200-201)
*   `aips_prompt_sections`: `created_at DATETIME DEFAULT CURRENT_TIMESTAMP`, `updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP` (lines 213-214)
*   `aips_trending_topics`: `researched_at DATETIME NOT NULL` (line 228)
*   `aips_authors`: Multiple datetime fields including `topic_generation_next_run`, `topic_generation_last_run`, `post_generation_next_run`, `post_generation_last_run`, `created_at`, `updated_at` (lines 247-273)
*   `aips_author_topics`: `generated_at DATETIME DEFAULT CURRENT_TIMESTAMP`, `reviewed_at DATETIME DEFAULT NULL` (lines 289-290)
*   `aips_author_topic_logs`: `created_at DATETIME DEFAULT CURRENT_TIMESTAMP` (line 308)
*   `aips_topic_feedback`: `created_at DATETIME DEFAULT CURRENT_TIMESTAMP` (line 325)
*   `aips_notifications`: `read_at DATETIME DEFAULT NULL`, `created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP` (lines 345-346)
*   `aips_sources`: `last_fetched_at DATETIME DEFAULT NULL`, `next_fetch_at DATETIME DEFAULT NULL`, `created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP`, `updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP` (lines 364-367)
*   `aips_sources_data`: `fetched_at DATETIME DEFAULT NULL`, `created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP`, `updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP` (lines 399-401)
*   `aips_taxonomy`: `created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP`, `updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP` (lines 418-419)
*   `aips_post_embeddings`: `indexed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP` (line 432)
*   `aips_internal_links`: `created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP`, `updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP` (lines 445-446)
*   `aips_cache`: `expires_at DATETIME DEFAULT NULL`, `updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP` (lines 460-461)
*   `aips_telemetry`: `inserted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP` (line 484)

**Version-Based Upgrade System (class-aips-upgrades.php)**
----------------------------------------------------------

**Upgrade Versions Implemented:**

1.  **Version 2.3.1** (lines 25-26, 75-100): Adds composite indexes to `aips_notifications` table:
    
    *   `is_read_created_at` index for faster unread queries
    *   `dedupe_key_created_at` index for deduplication checks
2.  **Version 2.4.0** (lines 29-30, 115-154): Adds scheduled-fetch support to `aips_sources`:
    
    *   `fetch_interval VARCHAR(50)`
    *   `last_fetched_at DATETIME`
    *   `next_fetch_at DATETIME`
    *   Also renames `word_count` → `char_count` on `aips_sources_data`
3.  **Version 2.4.1** (lines 33-34, 172-235): Converts `aips_sources_data` to growing archive model:
    
    *   Adds `content_hash VARCHAR(64)` for SHA-256 deduplication
    *   Adds `num_used INT` for round-robin selection
    *   Drops old single-row-per-source UNIQUE constraint
    *   Adds new `source_content_hash` UNIQUE composite key
    *   Adds `num_used` index for efficient ordering

**Key Pattern:** All migrations use `SHOW TABLES`/`SHOW COLUMNS`/`SHOW INDEX` checks before ALTERing, making them idempotent and safe for fresh installs where dbDelta has already applied the schema (lines 80-81, 120-121, etc.).

**Interval Calculator (class-aips-interval-calculator.php)**
------------------------------------------------------------

Provides scheduling math with support for:

*   Fixed intervals: hourly, every\_4\_hours, every\_6\_hours, every\_12\_hours, daily, weekly, bi\_weekly
*   Calendar intervals: monthly, day-specific (every\_monday, every\_tuesday, etc.)
*   Calculates next run times while preserving schedule phase (prevents drift)
*   Catch-up logic for past-due schedules (lines 135-166)
*   Day-specific calculation preserving time component (lines 254-273)

**Schedule Processor (class-aips-schedule-processor.php)**
----------------------------------------------------------

**Key Features:**

*   **Claim-first locking strategy** (lines 177-188): Updates `next_run` BEFORE generation to prevent concurrent duplicates
*   **Resumable batch progress** (lines 361-429): Stores progress in `batch_progress` JSON, recovers from mid-batch crashes
*   Uses post IDs as authoritative completion indicator (lines 402-413)
*   History tracking for schedule lifecycle events
*   Post-execution cleanup: deletes one-time schedules on success, deactivates on failure (lines 562-607)

**Scheduler (class-aips-scheduler.php)**
----------------------------------------

Top-level orchestrator that:

*   Manages singleton instance
*   Coordinates `AIPS_Schedule_Processor` for execution
*   Provides schedule CRUD operations with history tracking
*   Integrates with WP-Cron via `AIPS_Cron_Generation_Handler` interface
*   Protects against schedule timeline resets (lines 180-196): if a schedule is already running in the future and proposed start\_time is in the past, preserves the future timeline

**Telemetry Controller (class-aips-telemetry-controller.php)**
--------------------------------------------------------------

Handles telemetry admin page rendering and AJAX:

*   Stores performance metrics in `aips_telemetry` table
*   Tracks queries, cache hits/misses, memory usage, elapsed time
*   Date range filtering with 30-day default window
*   Daily rollup aggregation for chart display (lines 98-99)
*   Payload includes detailed event arrays (lines 143-147)