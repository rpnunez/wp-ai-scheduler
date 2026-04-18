Implement the plan:

## Improving Sources: Scheduled Fetching, Content Storage & Deep Integration

### Context: Current State

The Sources feature today:

*   Stores URLs + labels in `aips_sources` table, organized via `aips_source_group` taxonomy
*   `build_sources_block()` injects a raw list of URLs into AI prompts ("reference and cite these URLs when relevant")
*   Templates can opt-in via `include_sources` / `source_group_ids` fields
*   Authors (`AIPS_Topic_Context`) have `get_include_sources()` hard-coded to return `false` — sources are **not** wired into the author/topic flow at all
*   Research has no awareness of sources
*   No actual content is fetched — AI models receive only URLs they cannot actually read

- - -

## Phase 1 — Data Layer: Schema & Repositories

### 1.1 Extend `aips_sources` Table

Add three new columns to track fetch configuration and state:

*   `fetch_interval varchar(50) DEFAULT NULL` — a frequency key from `AIPS_Interval_Calculator::get_all_interval_displays()` (e.g. `'daily'`, `'hourly'`); `NULL` means "no automatic fetching"
*   `last_fetched_at datetime DEFAULT NULL` — timestamp of the last successful fetch run
*   `next_fetch_at datetime DEFAULT NULL` — pre-computed next fetch time, used by the cron dispatcher

### 1.2 New Table: `aips_sources_data`

Create a new `aips_sources_data` table to store one scraped snapshot per source URL:

| Column | Type | Notes |
| --- | --- | --- |
| `id` | bigint PK |     |
| `source_id` | bigint FK | references `aips_sources.id` |
| `url` | varchar(2083) | the URL actually fetched (may differ from source root) |
| `page_title` | varchar(500) | extracted `<title>` |
| `meta_description` | text | extracted `<meta name="description">` |
| `extracted_text` | longtext | cleaned readable text stripped of HTML/scripts/styles |
| `raw_html` | longtext | original HTML (optional, toggle via setting) |
| `word_count` | int | character count of `extracted_text` |
| `fetch_status` | varchar(20) | `pending` / `success` / `failed` |
| `http_status` | int | HTTP response code |
| `error_message` | text | last fetch error if any |
| `fetched_at` | datetime | when the last fetch completed |
| `created_at` / `updated_at` | datetime | standard row timestamps |

Unique key on `(source_id, url(191))` — one row per source URL, updated on re-fetch.

Add `aips_sources_data` to `AIPS_DB_Manager::$tables` and `get_schema()`. Schema changes flow through `dbDelta` + `AIPS_Upgrades`.

### 1.3 New `AIPS_Sources_Data_Repository`

Owns all SQL for `aips_sources_data`:

*   `upsert($source_id, $data)` — insert or update a fetch record
*   `get_by_source_id($source_id)` — retrieve the cached snapshot for a source
*   `get_extracted_texts_by_source_ids(array $ids)` — bulk load text for multiple sources
*   `delete_by_source_id($source_id)` — used when a source is deleted
*   `mark_fetch_failed($source_id, $error, $http_status)` — record failure without touching `extracted_text`

### 1.4 Update `AIPS_Sources_Repository`

*   Add `fetch_interval`, `last_fetched_at`, `next_fetch_at` to `create()` / `update()` / `get_by_id()` / `get_all()`
*   Add `get_due_for_fetch()` — returns sources where `is_active = 1 AND fetch_interval IS NOT NULL AND (next_fetch_at IS NULL OR next_fetch_at <= NOW())`
*   Add `set_fetch_schedule($id, $interval_key)` — sets `fetch_interval` + computes `next_fetch_at`
*   Add `update_after_fetch($id, $success)` — updates `last_fetched_at` and computes the next `next_fetch_at` via `AIPS_Interval_Calculator`

- - -

## Phase 2 — Fetching Service & Cron

### 2.1 `AIPS_Sources_Fetcher` (new service class)

Responsible for actually retrieving and parsing a source URL:

*   `fetch($source)` — makes a `wp_remote_get()` call (respects WordPress HTTP API; configurable timeout/user-agent)
*   HTML cleaning pipeline: strip `<script>`, `<style>`, `<nav>`, `<footer>`, `<aside>` tags; extract readable body text; sanitize; trim to a configurable max character limit (default ~5,000 chars)
*   Extracts `page_title` and `meta_description` from the HTML
*   Calls `AIPS_Sources_Data_Repository::upsert()` with the result
*   Calls `AIPS_Sources_Repository::update_after_fetch()` to advance the schedule
*   Logs the fetch attempt (start, success/fail, duration) via `AIPS_History_Service` or `AIPS_Logger`
*   Returns a result object (success flag, word count, error)

### 2.2 `AIPS_Sources_Cron` (new scheduler class)

Manages WP-CRON for the fetch loop:

*   Registers a WP-CRON hook `aips_fetch_sources` (registered once in `boot_cron()` / plugin init, like `AIPS_Scheduler`)
*   `schedule()` — ensures a recurring WP-CRON event exists (at minimum `every_6_hours` or configurable)
*   `run()` — the cron callback; queries `AIPS_Sources_Repository::get_due_for_fetch()`; loops and calls `AIPS_Sources_Fetcher::fetch()` for each; respects a per-run limit to prevent timeouts (e.g. max 10 sources per cron run)
*   Register the class in `boot_cron()` alongside existing schedulers; add `instance()` singleton

### 2.3 Bootstrap Wiring

*   Add `AIPS_Sources_Cron::instance()` to the always-loaded runtime services in `boot_cron()`
*   Hook `aips_sources_delete` (or piggyback the delete AJAX) to call `AIPS_Sources_Data_Repository::delete_by_source_id()` for cleanup

- - -

## Phase 3 — Enhanced Prompt Injection

### 3.1 Update `AIPS_Prompt_Builder::build_sources_block()`

Currently only lists URLs. Change it to fetch real content from `AIPS_Sources_Data_Repository`:

*   For each source URL in the group, check if `extracted_text` is available in `aips_sources_data`
*   If content exists: include a truncated excerpt (configurable max chars per source, e.g. 800) plus the source URL and title
*   If content does not exist yet (not yet fetched): fall back to just the URL
*   Format as a structured block:

    Code

    Copy code

    ```
    Trusted Sources (use the following content and URLs as factual references):

    --- Source: Example Blog (https://example.com/article) ---
    <extracted text excerpt>

    --- Source: Industry Site (https://industry.com) ---
    [Content not yet fetched — reference this URL where relevant]
    ```

*   Add a `aips_sources_block` filter for customization

### 3.2 Configurable Snippet Length

Add a plugin setting (`aips_source_snippet_max_chars`, default 800) that controls how much extracted text is included per source in prompts. Expose in the Settings page.

- - -

## Phase 4 — Authors Integration

### 4.1 Add `source_group_ids` to `aips_authors` Table

Add a `source_group_ids` JSON column to `aips_authors` (same pattern as templates). Schema updated via `dbDelta`.

### 4.2 Update `AIPS_Authors_Repository` and `AIPS_Authors_Controller`

*   Include `source_group_ids` in author create/update/get operations
*   Add `source_group_ids` to the save/edit AJAX handlers in `AIPS_Authors_Controller` (checkbox group, same UX as Templates)

### 4.3 Update `AIPS_Topic_Context`

*   Change `get_include_sources()` to return `true` when the author has non-empty `source_group_ids`
*   Change `get_source_group_ids()` to decode and return `$this->author->source_group_ids`
*   This automatically routes through the existing `inject_sources_into_content_prompt()` filter without any further changes to the generation pipeline

### 4.4 Authors Admin UI

In the Authors edit form, add a "Source Groups" multi-checkbox section (same pattern as Templates) so admins can select which Source Groups an author's generated content should reference

- - -

## Phase 5 — Research Integration

### 5.1 Update `AIPS_Research_Service::build_research_prompt()`

Accept an optional `$source_context` string parameter. When passed (non-empty), prepend it to the research prompt:

Code

Copy code

```
The following is content from trusted sources in this niche. Use it to identify specific trending angles and gaps:

<source content excerpts>

---

Your task: Identify the top N most trending topics...
```

### 5.2 New `AIPS_Research_Service::research_from_sources()` Method

A companion to `research_trending_topics()` that:

*   Accepts an array of Source Group term IDs
*   Loads extracted text for all active sources in those groups via `AIPS_Sources_Data_Repository`
*   Builds a prompt enriched with real source content
*   Calls the AI to surface topic ideas grounded in the actual source material (not just AI knowledge)

### 5.3 Research UI Updates

In `templates/admin/research.php`:

*   Add a "Use Trusted Sources" toggle / Source Group multi-select
*   When enabled, `AIPS_Research_Controller` loads source content and passes it to `research_from_sources()`
*   Show a status note if selected sources have not been fetched yet (link to Sources page)

- - -

## Phase 6 — Sources Admin UI Updates

### 6.1 Fetch Interval in Add/Edit Source Modal

In `templates/admin/sources.php`, add a "Fetch Interval" `<select>` to the add/edit modal, populated with `AIPS_Interval_Calculator::instance()->get_all_interval_displays()`, plus a "Never (manual only)" option. Save via updated `ajax_save_source()`.

### 6.2 Sources Table: Fetch Status Column

Add a "Content" column to the sources table showing:

*   Last fetched timestamp + word count (if fetched successfully)
*   "Never fetched" badge (with option to trigger)
*   "Failed" badge (with last error shown on hover/tooltip)

### 6.3 "Fetch Now" Manual Trigger

Add a "Fetch Now" icon button per row in the sources table. Wire to a new AJAX endpoint `aips_fetch_source_now` in `AIPS_Sources_Controller` that calls `AIPS_Sources_Fetcher::fetch()` synchronously and returns updated status.

### 6.4 Content Preview in Source Detail

Optionally add a read-only "Fetched Content" expandable section in the edit modal showing the stored `extracted_text` excerpt and `page_title`, so admins can verify what was captured.

- - -

## New Files Summary

| File | Type |
| --- | --- |
| `includes/class-aips-sources-fetcher.php` | New service |
| `includes/class-aips-sources-cron.php` | New cron handler |
| `includes/class-aips-sources-data-repository.php` | New repository |

## Modified Files Summary

| File | Change |
| --- | --- |
| `includes/class-aips-db-manager.php` | Add `aips_sources_data` table; add new columns to `aips_sources` |
| `includes/class-aips-sources-repository.php` | New fields + `get_due_for_fetch()`, `update_after_fetch()` |
| `includes/class-aips-sources-controller.php` | `ajax_fetch_source_now` endpoint |
| `includes/class-aips-prompt-builder.php` | `build_sources_block()` injects extracted text |
| `includes/class-aips-topic-context.php` | `get_include_sources()` / `get_source_group_ids()` from author |
| `includes/class-aips-authors-repository.php` | `source_group_ids` field |
| `includes/class-aips-authors-controller.php` | Save/load `source_group_ids` |
| `includes/class-aips-research-service.php` | Source-context research method |
| `includes/class-aips-research-controller.php` | Route to source-aware research |
| `includes/class-aips-upgrades.php` | Schema migration note for new columns |
| `ai-post-scheduler.php` | Register `AIPS_Sources_Cron` in `boot_cron()` |
| `templates/admin/sources.php` | Fetch interval dropdown, status column, Fetch Now button |
| `templates/admin/authors.php` | Source Groups multi-select |
| `templates/admin/research.php` | Source-aware research toggle |

- - -

## Key Design Decisions

*   **One record per source URL in `aips_sources_data`** — simple upsert pattern, no unbounded history. If you later want versioned snapshots, add a `aips_sources_data_history` table.
*   **Store `raw_html` optionally** — controlled by a setting; most installs will only want `extracted_text` to keep DB size manageable.
*   **WP HTTP API only** (`wp_remote_get`) — no new PHP extensions or Composer dependencies. This works within WordPress hosting constraints, but means JavaScript-rendered pages won't be scraped (acceptable for most reference/news/blog sources).
*   **Per-source cron via `next_fetch_at`** — rather than one event per source (which could bloat WP-CRON), a single `aips_fetch_sources` cron event runs on the tightest needed interval and dispatches only the sources that are due. This follows the same pattern as `AIPS_Scheduler`.
*   **Graceful degradation** — if a source has no fetched content yet, `build_sources_block()` falls back to URL-only injection (current behaviour), so existing installations are not broken.
*   **Author sources follow group pattern** — `source_group_ids` on authors works identically to templates, reusing the same repository method `get_urls_by_group_term_ids()` and the existing `inject_sources_into_content_prompt()` filter.