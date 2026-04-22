 Phase 1 — Data model: add parent_id to aips_history, repository methods (get_children, get_top_level, get_child_summary), container create_child()
 Phase 2 — AIPS_History_Operation_Type constants class
 Phase 3a — Wire schedule_lifecycle containers as parents to per-post child containers in AIPS_Schedule_Processor
 Phase 3b — Wrap ajax_bulk_generate_topics and _do_bulk_generate_from_queue with TOPIC_GENERATION_BATCH parent containers; wire AIPS_Author_Topics_Scheduler::process_topic_generation() similarly
 Phase 3e — Wrap AIPS_Author_Post_Generator cron run in a POST_GENERATION_BATCH parent
 Phase 4 — New grouped history page (top-level operations, expandable inline children, filter bar improvements, legacy badge)
 Phase 5 — History log modal: Summary / Timeline / Raw Logs tabs + severity row colouring
 Phase 7 — Tests: test-history-parent-child.php, test-history-operation-type.php

 You are implementing a hierarchical "History of Histories" system for a WordPress plugin in the repository at /home/runner/work/wp-ai-scheduler/wp-ai-scheduler. The plugin lives in the ai-post-scheduler/ subdirectory.

CODING STANDARDS (MANDATORY)
----------------------------

*   Use TABS for indentation throughout PHP
*   Use `array()` syntax, NOT `[]`
*   Add `if (!defined('ABSPATH')) { exit; }` to every PHP file
*   Follow WordPress escaping, sanitization, nonce, and capability patterns
*   All class names use `AIPS_` prefix, underscore separated
*   File names mirror class names: `class-aips-foo-bar.php`
*   Do NOT add manual `require_once` for plugin classes (autoloader handles it)
*   Use existing `AIPS_Ajax_Response::success()` / `AIPS_Ajax_Response::error()` for AJAX responses

WHAT EXISTS (already read and understood)
-----------------------------------------

### Key existing files (do NOT re-read unless needed):

*   `ai-post-scheduler/includes/class-aips-history-container.php` — container with `record()`, `complete_success()`, `complete_failure()`, `persist()`
*   `ai-post-scheduler/includes/class-aips-history-service.php` — `create($type, $metadata)` singleton service
*   `ai-post-scheduler/includes/class-aips-history-repository.php` — full repository; `get_history()` at line 155 currently excludes `schedule_lifecycle` containers via: `$where_clauses[] = "COALESCE(h.creation_method, '') <> 'schedule_lifecycle'"`;
*   `ai-post-scheduler/includes/class-aips-history-type.php` — type constants (LOG=1, ERROR=2, WARNING=3, INFO=4, AI\_REQUEST=5, AI\_RESPONSE=6, DEBUG=7, ACTIVITY=8, SESSION\_METADATA=9, METRIC=10)
*   `ai-post-scheduler/includes/class-aips-history.php` — AJAX controller; `ajax_get_history_logs()` at ~line 140 is the modal endpoint
*   `ai-post-scheduler/includes/class-aips-upgrades.php` — version-gated migrations; current plugin version is `2.5.0` (defined in `ai-post-scheduler/ai-post-scheduler.php`)
*   `ai-post-scheduler/includes/class-aips-db-manager.php` — schema; `aips_history` table at ~line 84, `aips_history_log` table at ~line 115
*   `ai-post-scheduler/includes/class-aips-author-topics-scheduler.php` — `process_topic_generation()` loops over due authors, calls `generate_topics_for_author()` which creates per-author history containers directly
*   `ai-post-scheduler/includes/class-aips-author-topics-controller.php` — `_do_bulk_generate_topics()` delegates to `AIPS_Bulk_Generator_Service::run()`; `ajax_bulk_generate_topics()` and `ajax_bulk_generate_from_queue()` both call `_do_bulk_generate_topics()`
*   `ai-post-scheduler/includes/class-aips-author-post-generator.php` — creates `topic_post_generation` containers per post
*   `ai-post-scheduler/includes/class-aips-schedule-processor.php` — creates `schedule_lifecycle` containers around schedule runs
*   `ai-post-scheduler/includes/class-aips-correlation-id.php` — static correlation ID manager
*   `ai-post-scheduler/templates/admin/history.php` — history page template
*   `ai-post-scheduler/templates/partials/history-row.php` — row partial
*   `ai-post-scheduler/assets/js/admin-history.js` — existing JS with AIPS.History module
*   `ai-post-scheduler/assets/css/admin.css` — main admin CSS

### DB schema for aips\_history (current):

Code

Copy code

    id bigint(20) AUTO_INCREMENT PRIMARY KEY
    uuid varchar(36)
    correlation_id varchar(36)
    post_id bigint(20)
    template_id bigint(20)
    author_id bigint(20)
    topic_id bigint(20)
    creation_method varchar(20)
    status varchar(50) DEFAULT 'pending'
    prompt text
    generated_title varchar(500)
    generated_content longtext
    generation_log longtext
    error_message text
    created_at bigint(20) unsigned NOT NULL DEFAULT 0
    completed_at bigint(20) unsigned NOT NULL DEFAULT 0
    

WHAT TO IMPLEMENT
-----------------

### Phase 1 — Data Model

**1a. Schema: add `parent_id` to `aips_history`**

In `class-aips-db-manager.php`, inside the `CREATE TABLE $table_history` statement, add after `correlation_id`:

Code

Copy code

    parent_id bigint(20) DEFAULT NULL,
    

And add these KEY lines in that table's indexes section:

Code

Copy code

    KEY parent_id (parent_id),
    KEY parent_id_created (parent_id, created_at),
    

**1b. Upgrade migration: `migrate_to_2_6_0()`**

In `class-aips-upgrades.php`, add a new migration method and wire it into `run_upgrade()`:

In `run_upgrade()`, after the existing `2.5.0` block, add:

PHP

Copy code

    if (version_compare($from_version, '2.6.0', '<')) {
        $this->migrate_to_2_6_0();
    }
    

The `migrate_to_2_6_0()` method should:

1.  Check if `aips_history` table exists
2.  Check if `parent_id` column already exists (SHOW COLUMNS guard)
3.  If not, run: `ALTER TABLE {table} ADD COLUMN parent_id bigint(20) DEFAULT NULL AFTER correlation_id`
4.  Check and add `KEY parent_id (parent_id)` index (SHOW INDEX guard)
5.  Check and add `KEY parent_id_created (parent_id, created_at)` index (SHOW INDEX guard)

**1c. Update plugin version to 2.6.0**

In `ai-post-scheduler/ai-post-scheduler.php`, change the `AIPS_VERSION` constant from `'2.5.0'` to `'2.6.0'`. Also update the `Version:` header comment if present. Also update in `CHANGELOG.md` if it exists: add a new `## [2.6.0]` section at the top documenting the hierarchical history feature.

**1d. Repository changes**

In `class-aips-history-repository.php`:

1.  In the `create()` method, add support for `parent_id` in `$data` — persist it when present (follow the pattern used for `correlation_id`)
    
2.  Add method `get_children($parent_id)`:
    

PHP

Copy code

    public function get_children($parent_id) {
        // SELECT all fields from aips_history where parent_id = $parent_id
        // LEFT JOIN aips_templates for template_name
        // ORDER BY created_at ASC
        // Return array of objects
    }
    

3.  Add method `get_top_level($args = array())`: This is the same as `get_history()` but adds `h.parent_id IS NULL` to `$where_clauses`. It should also include the `schedule_lifecycle` containers (do NOT filter them out here). Accept the same args as `get_history()` plus `operation_type` filter. Returns same shape: `array('items' => [], 'total' => int, 'pages' => int, 'current_page' => int)`.
    
4.  Add method `get_child_summary($parent_id)`:
    

PHP

Copy code

    public function get_child_summary($parent_id) {
        // SELECT COUNT(*) as total,
        //   SUM(CASE WHEN status='completed' THEN 1 ELSE 0 END) as completed_count,
        //   SUM(CASE WHEN status='failed' THEN 1 ELSE 0 END) as failed_count,
        //   SUM(CASE WHEN status='processing' THEN 1 ELSE 0 END) as processing_count,
        //   MIN(created_at) as first_created,
        //   MAX(completed_at) as last_completed
        // FROM aips_history WHERE parent_id = $parent_id
        // Return object with these fields (0 if no children)
    }
    

5.  In `get_history()`, the existing filter `"COALESCE(h.creation_method, '') <> 'schedule_lifecycle'"` should remain as-is (for backward compat of the old flat view), but the new `get_top_level()` does NOT include this filter.

**1e. History Container changes**

In `class-aips-history-container.php`:

1.  In the `persist()` method, when building `$data`, also include `parent_id` if it is set in `$this->metadata`:

PHP

Copy code

    if (isset($this->metadata['parent_id']) && $this->metadata['parent_id']) {
        $data['parent_id'] = absint($this->metadata['parent_id']);
    }
    

2.  Add method `create_child($type, $metadata = array())`:

PHP

Copy code

    /**
     * Create a child history container parented to this container.
     *
     * @param string $type     History type for the child.
     * @param array  $metadata Optional metadata for the child.
     * @return AIPS_History_Container
     */
    public function create_child($type, $metadata = array()) {
        $metadata['parent_id'] = $this->history_id;
        // Inherit correlation_id from parent
        if ($this->correlation_id && !isset($metadata['correlation_id'])) {
            $metadata['correlation_id'] = $this->correlation_id;
        }
        return new self($this->repository, $type, $metadata);
    }
    

* * *

### Phase 2 — Operation Type Taxonomy

Create new file `ai-post-scheduler/includes/class-aips-history-operation-type.php`:

PHP

Copy code

    <?php
    if (!defined('ABSPATH')) {
        exit;
    }
    
    /**
     * Class AIPS_History_Operation_Type
     *
     * Defines operation type constants for the hierarchical history system.
     * Used to categorize top-level and child history containers.
     *
     * @package AI_Post_Scheduler
     * @since 2.6.0
     */
    class AIPS_History_Operation_Type {
    
        /** A cron-triggered schedule execution (parent of per-post generations). */
        const SCHEDULE_RUN = 'schedule_run';
    
        /** A batch of topics generated for one or more authors. */
        const TOPIC_GENERATION_BATCH = 'topic_generation_batch';
    
        /** A batch of posts generated from approved topics. */
        const POST_GENERATION_BATCH = 'post_generation_batch';
    
        /** A single post generation (typically a child of SCHEDULE_RUN or POST_GENERATION_BATCH). */
        const POST_GENERATION = 'post_generation';
    
        /** A single author's topic generation (child of TOPIC_GENERATION_BATCH). */
        const TOPIC_GENERATION = 'topic_generation';
    
        /** Re-generating a single post component (title/excerpt/content/image). */
        const COMPONENT_REGENERATION = 'component_regeneration';
    
        /** System-level events: notifications, cleanup, embeddings, etc. */
        const SYSTEM = 'system';
    
        /**
         * Get a human-readable label for an operation type string.
         *
         * @param string $type Operation type constant.
         * @return string
         */
        public static function get_label($type) {
            $labels = array(
                self::SCHEDULE_RUN            => __('Schedule Run', 'ai-post-scheduler'),
                self::TOPIC_GENERATION_BATCH  => __('Generate Topics', 'ai-post-scheduler'),
                self::POST_GENERATION_BATCH   => __('Post Generation Batch', 'ai-post-scheduler'),
                self::POST_GENERATION         => __('Post Generation', 'ai-post-scheduler'),
                self::TOPIC_GENERATION        => __('Topic Generation', 'ai-post-scheduler'),
                self::COMPONENT_REGENERATION  => __('Component Regeneration', 'ai-post-scheduler'),
                self::SYSTEM                  => __('System', 'ai-post-scheduler'),
            );
            return isset($labels[$type]) ? $labels[$type] : ucwords(str_replace('_', ' ', $type));
        }
    
        /**
         * Get all operation types as type => label pairs.
         *
         * @return array
         */
        public static function get_all_types() {
            return array(
                self::SCHEDULE_RUN            => self::get_label(self::SCHEDULE_RUN),
                self::TOPIC_GENERATION_BATCH  => self::get_label(self::TOPIC_GENERATION_BATCH),
                self::POST_GENERATION_BATCH   => self::get_label(self::POST_GENERATION_BATCH),
                self::POST_GENERATION         => self::get_label(self::POST_GENERATION),
                self::TOPIC_GENERATION        => self::get_label(self::TOPIC_GENERATION),
                self::COMPONENT_REGENERATION  => self::get_label(self::COMPONENT_REGENERATION),
                self::SYSTEM                  => self::get_label(self::SYSTEM),
            );
        }
    
        /**
         * Whether a given operation type is a parent-level (top-level) type.
         *
         * @param string $type Operation type.
         * @return bool
         */
        public static function is_parent_type($type) {
            return in_array($type, array(
                self::SCHEDULE_RUN,
                self::TOPIC_GENERATION_BATCH,
                self::POST_GENERATION_BATCH,
            ), true);
        }
    }
    

* * *

### Phase 3a — Wire schedule\_lifecycle containers as parents

Read `class-aips-schedule-processor.php` carefully to understand how `schedule_lifecycle` history containers are created and how individual post-generation containers are spawned downstream (through `$this->runner`, `$this->result_handler`).

The goal: when a `schedule_lifecycle` container is created, its ID needs to be passed down so each individual post generation container gets `parent_id` set to the lifecycle container's ID.

Look at how `AIPS_Schedule_Result_Handler` and `AIPS_Generation_Execution_Runner` create their per-post history containers. The most surgical approach is:

1.  When the `schedule_lifecycle` container is created in `AIPS_Schedule_Processor::execute_schedule_logic()`, store it and pass its ID as `correlation context` by setting it on the correlation ID manager metadata OR pass it through the options/args chain.

Read the actual code to find the clearest injection point. The simplest approach that requires the fewest changes:

In `AIPS_Schedule_Processor::execute_schedule_logic()`, after creating the `schedule_lifecycle` container, set a new PHP static variable on a helper class or add a method to `AIPS_Correlation_ID` to store a `parent_history_id`:

**Add to `class-aips-correlation-id.php`**:

PHP

Copy code

    /** @var int|null Active parent history ID for the current run. */
    private static $parent_history_id = null;
    
    public static function set_parent_history_id($id) {
        self::$parent_history_id = $id ? absint($id) : null;
    }
    
    public static function get_parent_history_id() {
        return self::$parent_history_id;
    }
    

And in `AIPS_Correlation_ID::reset()`, also reset: `self::$parent_history_id = null;`

Then in `AIPS_History_Container::persist()` (or in `AIPS_History_Service::create()`), when creating a NEW container with no explicit `parent_id` in metadata, check `AIPS_Correlation_ID::get_parent_history_id()` and use it IF the new container is not itself a parent-type operation. BUT — this would be too aggressive. Instead, make it explicit:

**Better approach**: In `class-aips-schedule-processor.php`, after the lifecycle container is created:

PHP

Copy code

    AIPS_Correlation_ID::set_parent_history_id($history_container->get_id());
    

And in the `finally` or cleanup block: `AIPS_Correlation_ID::set_parent_history_id(null);`

Then in `AIPS_History_Container::persist()`, before building `$data`, if `parent_id` is not already in `$this->metadata` AND the current container's `$type` is NOT a parent-level type (i.e., not `schedule_lifecycle`, `schedule_run`, `topic_generation_batch`, `post_generation_batch`), automatically pull `AIPS_Correlation_ID::get_parent_history_id()` as the `parent_id`.

The check for "not a parent type" should use `AIPS_History_Operation_Type::is_parent_type()` plus the legacy type strings (`schedule_lifecycle`).

Read the actual schedule processor file carefully and make the minimal change to wire this up.

* * *

### Phase 3b — Author topics: TOPIC\_GENERATION\_BATCH parent

**In `class-aips-author-topics-scheduler.php`**, in `process_topic_generation()`:

Before the loop over `$due_authors`, create a batch parent container:

PHP

Copy code

    $batch_parent = $this->history_service->create(AIPS_History_Operation_Type::TOPIC_GENERATION_BATCH, array(
        'creation_method' => AIPS_History_Operation_Type::TOPIC_GENERATION_BATCH,
    ));
    $batch_parent->record('activity', sprintf(
        __('Scheduled topic generation started for %d author(s).', 'ai-post-scheduler'),
        count($due_authors)
    ));
    

Set this as the parent on the correlation ID manager:

PHP

Copy code

    AIPS_Correlation_ID::set_parent_history_id($batch_parent->get_id());
    

In the `finally` block after the loop, call `AIPS_Correlation_ID::set_parent_history_id(null)`.

Complete the batch parent after the loop:

PHP

Copy code

    $batch_parent->complete_success(array(
        'generated_title' => sprintf(
            __('Scheduled: %d author(s) processed', 'ai-post-scheduler'),
            count($due_authors)
        ),
    ));
    

The per-author containers created in `generate_topics_for_author()` will automatically get `parent_id` set via the `AIPS_Correlation_ID::get_parent_history_id()` mechanism from Phase 3a.

**In `class-aips-author-topics-controller.php`**, in `ajax_bulk_generate_topics()` and `ajax_bulk_generate_from_queue()` (both delegate to `_do_bulk_generate_topics()`):

Before calling `_do_bulk_generate_topics()`, create a parent container and pass its ID through `AIPS_Correlation_ID::set_parent_history_id()`. After `_do_bulk_generate_topics()` returns, complete the parent and reset.

The cleanest approach: add an optional `$parent_container` parameter to `_do_bulk_generate_topics()`, OR wrap the AJAX handlers. Since `ajax_bulk_generate_topics()` and `ajax_bulk_generate_from_queue()` both call `_do_bulk_generate_topics()`, wrap each one:

In `ajax_bulk_generate_topics()`, before the `_do_bulk_generate_topics()` call:

PHP

Copy code

    $batch_history = $this->history_service->create(AIPS_History_Operation_Type::TOPIC_GENERATION_BATCH, array(
        'creation_method' => AIPS_History_Operation_Type::TOPIC_GENERATION_BATCH,
        'user_id'         => get_current_user_id(),
    ));
    $batch_history->record('activity', sprintf(
        __('User triggered bulk topic generation for %d topic(s).', 'ai-post-scheduler'),
        count($topic_ids)
    ));
    AIPS_Correlation_ID::set_parent_history_id($batch_history->get_id());
    

After `_do_bulk_generate_topics()` returns (it calls `AIPS_Ajax_Response` which exits, so this needs to be restructured slightly — OR you can hook into the fact that `AIPS_Ajax_Response::success/error` calls `wp_send_json` which calls `die()`, meaning code after it doesn't run).

**Important**: Since `_do_bulk_generate_topics()` calls `AIPS_Ajax_Response::success()` or `AIPS_Ajax_Response::error()` at the end (which terminates execution via `wp_die()`), you cannot put cleanup code after `_do_bulk_generate_topics()`. Instead:

Option A: Complete the batch parent inside `_do_bulk_generate_topics()` or by passing it as a parameter. Option B: Use PHP's `register_shutdown_function` to complete it. Option C (simplest): Refactor `_do_bulk_generate_topics()` to return a result object instead of calling `AIPS_Ajax_Response` directly, then the callers can complete the parent before sending the response.

Use Option C — refactor `_do_bulk_generate_topics()` to return a result array instead of calling AIPS\_Ajax\_Response internally:

PHP

Copy code

    private function _do_bulk_generate_topics(array $topic_ids, array $options): array {
        // ... same logic but return instead of AIPS_Ajax_Response
        return array(
            'success' => true/false,
            'was_limited' => bool,
            'message' => string,
            'success_count' => int,
            'failed_count' => int,
            'errors' => array,
        );
    }
    

Then `ajax_bulk_generate_topics()` and `ajax_bulk_generate_from_queue()` do:

PHP

Copy code

    $batch_history = ...; // create parent
    AIPS_Correlation_ID::set_parent_history_id($batch_history->get_id());
    $result = $this->_do_bulk_generate_topics($topic_ids, $options);
    AIPS_Correlation_ID::set_parent_history_id(null);
    if ($result['success']) {
        $batch_history->complete_success(array('generated_title' => $result['message']));
        AIPS_Ajax_Response::success(...);
    } else {
        $batch_history->complete_failure($result['message']);
        AIPS_Ajax_Response::error(...);
    }
    

* * *

### Phase 3e — Author Post Generator: POST\_GENERATION\_BATCH parent

Read `class-aips-author-post-generator.php`. Find the method that runs the cron-based post generation loop (it will be something like `process_post_generation()` or `generate_posts_for_due_authors()`). Before the loop, create a `POST_GENERATION_BATCH` parent container and set it via `AIPS_Correlation_ID::set_parent_history_id()`. The per-post `topic_post_generation` containers created downstream will automatically get `parent_id` via the mechanism established in Phase 3a.

* * *

### Phase 4 — History Page: Grouped Hierarchical View

**4a. New AJAX endpoint: `aips_get_history_top_level`**

In `class-aips-history.php`, add:

*   `add_action('wp_ajax_aips_get_history_top_level', array($this, 'ajax_get_history_top_level'));`
*   `add_action('wp_ajax_aips_get_operation_children', array($this, 'ajax_get_operation_children'));`

Implement `ajax_get_history_top_level()` — nonce+capability check, then call `$this->repository->get_top_level($args)` with status, search, operation\_type, page filters from POST. Prepare items for display (add `formatted_date`). Return items as HTML using a new partial template for parent-level rows, plus pagination HTML and stats.

Implement `ajax_get_operation_children()` — nonce+capability check, accepts `history_id`, calls `$this->repository->get_children($history_id)`, calls `$this->repository->get_child_summary($history_id)`. Prepares items for display. Returns HTML of child rows using a new partial.

Also add a new `get_top_level_page()` method mirroring `render_page()` but using the top-level query.

**4b. New partial: `templates/partials/history-parent-row.php`**

Create a new row partial for parent-level (top-level) operations. It receives `$item` (the history row object) and `$child_summary` (from `get_child_summary()`). Display:

*   Checkbox column
*   **Operation column**: Shows human-readable operation label. Derive from `creation_method` using `AIPS_History_Operation_Type::get_label()`. If `template_name` is set, append " — {template\_name}". If `generated_title` is set, show it as a subtitle.
*   **Children column**: Badge showing count (e.g. "4 items"). If no children, show "—". Show breakdown: "3 completed, 1 failed" as a tooltip or sub-text. If failed > 0, use error badge style.
*   **Status column**: Roll-up status:
    *   If `failed_count > 0 && completed_count > 0`: "Partial" with warning badge
    *   If `failed_count > 0 && completed_count === 0`: "Failed" with error badge
    *   If `processing_count > 0`: "Processing" with info badge
    *   Otherwise: use `$item->status` directly
    *   If it's a legacy row (no children, `parent_id IS NULL`): show regular status badge
*   **Triggered column**: `$item->formatted_date` + badge: "Manual" if `creation_method` contains `manual` or `ajax`; "Scheduled" if `schedule_lifecycle` or `schedule_run`; "Cron" otherwise
*   **Duration column**: If `$child_summary->last_completed > 0 && $item->created_at > 0`, compute diff and show "Xs" or "Xm Ys"
*   **Actions column**: "View Details" button (`aips-view-operation-children`, `data-id`), "View Logs" button (`aips-view-history-logs`, `data-id`)

**4c. New partial: `templates/partials/history-child-row.php`**

A compact child row partial for use inside the expanded children section. Columns:

*   Indent indicator (CSS only)
*   Title/topic (with edit link if `post_id` exists)
*   Status badge
*   Duration (created\_at to completed\_at)
*   "View Logs" button

**4d. Update `templates/admin/history.php`**

Major update to add:

1.  A **View Mode toggle** above the filter bar: two buttons "Operations View" (default, calls `get_top_level`) and "All Items" (calls the existing flat `get_history`). Use `data-view-mode` attribute.
2.  Add **Operation Type** filter `<select>` to the filter bar, populated with `AIPS_History_Operation_Type::get_all_types()` plus an "All Types" option.
3.  The table thead for the operations view: Operation | Children | Status | Triggered | Duration | Actions
4.  The table thead for the flat/all-items view: existing columns (Title/Topic | Template | Status | Created | Actions)
5.  Keep the existing flat tbody and add a second tbody `aips-history-operations-tbody` for the operations view.
6.  Wire JS tab switching so only the active view's tbody and its filters are used.

Add these new `<script type="text/html">` templates at the bottom for the operations view:

*   `aips-tmpl-history-parent-row` — parent row shell
*   `aips-tmpl-history-children-container` — the expandable children wrapper div
*   `aips-tmpl-history-child-row` — individual child row

**4e. Update `assets/js/admin-history.js`**

Add to the `AIPS.History` module:

1.  `currentViewMode` property (default: `'operations'`)
2.  `switchViewMode(mode)` method — toggles between 'operations' and 'all', updates active state of view mode buttons, calls appropriate reload
3.  `loadOperationsView(page)` — fires AJAX to `aips_get_history_top_level` with current filters, replaces `#aips-history-operations-tbody`
4.  `loadAllView(page)` — fires AJAX to the existing `aips_reload_history`, replaces `#aips-history-tbody`
5.  `toggleOperationChildren(id)` — if the children row already exists (toggle off), else fires AJAX to `aips_get_operation_children`, inserts a new `<tr class="aips-children-row">` immediately after the parent row with a nested table inside

In `bindEvents()`, add handlers for:

*   `.aips-view-mode-btn` click → `switchViewMode()`
*   `.aips-view-operation-children` click → `toggleOperationChildren()`
*   Operation Type filter change → reload operations view

On `init()`, default to operations view, call `loadOperationsView(1)`.

* * *

### Phase 5 — History Log Modal: Tabs + Summary

**5a. Update `ajax_get_history_logs()` in `class-aips-history.php`**

Add to the response:

*   `'children'` key: if `$history_item` has children (call `$this->repository->get_child_summary($history_id)`), include `child_summary` object and whether this is a parent
*   `'is_parent'` boolean
*   `'child_summary'` object (from `get_child_summary()`)
*   `'operation_label'` string (from `AIPS_History_Operation_Type::get_label($history_item->creation_method)`)

**5b. Update history log modal in `templates/admin/history.php`**

Replace the current single-content modal body with a tabbed structure. Add three `<script type="text/html">` templates:

`aips-tmpl-history-modal-tabs` — tab nav:

HTML

Copy code

    <div class="aips-history-modal-tabs">
      <button class="aips-tab-link active" data-tab="summary">Summary</button>
      <button class="aips-tab-link" data-tab="timeline">Timeline</button>
      <button class="aips-tab-link" data-tab="raw-logs">Raw Logs</button>
    </div>
    <div class="aips-tab-content" id="aips-history-tab-summary">{{summaryHtml}}</div>
    <div class="aips-tab-content" id="aips-history-tab-timeline" style="display:none;">{{timelineHtml}}</div>
    <div class="aips-tab-content" id="aips-history-tab-raw-logs" style="display:none;">{{rawLogsHtml}}</div>
    

`aips-tmpl-history-summary-cards` — for parent containers, show stat cards:

HTML

Copy code

    <div class="aips-history-summary-cards">
      <div class="aips-summary-card">
        <span class="aips-summary-card-value">{{total}}</span>
        <span class="aips-summary-card-label">Total</span>
      </div>
      ...
    </div>
    

`aips-tmpl-history-timeline-item` — one child item in the timeline:

HTML

Copy code

    <div class="aips-timeline-item {{statusClass}}">
      <div class="aips-timeline-marker"></div>
      <div class="aips-timeline-content">
        <strong>{{title}}</strong>
        <span class="aips-badge {{statusClass}}">{{status}}</span>
        <span class="aips-meta-text">{{timestamp}}</span>
        <button class="aips-btn aips-btn-sm aips-btn-ghost aips-view-history-logs" data-id="{{id}}">View Logs</button>
      </div>
    </div>
    

**5c. Update `admin-history.js`** modal rendering to:

1.  Build three sections: summary (with stat cards if parent), timeline (load children), raw logs (existing table)
2.  Tab switching within the modal
3.  In the raw-logs table, add `aips-log-row-error` class to ERROR rows and `aips-log-row-warning` class to WARNING rows (based on `typeId === 2` and `typeId === 3`)
4.  Add "Errors only" filter button that toggles `data-type-id="2"` rows

**5d. Update `assets/css/admin.css`**

Add CSS for:

*   `.aips-history-modal-tabs` — flex tab bar
*   `.aips-history-summary-cards` — horizontal card grid
*   `.aips-summary-card` — individual stat card with value/label
*   `.aips-timeline-item` — vertical timeline layout with left border marker
*   `.aips-timeline-marker` — circle dot
*   `.aips-timeline-content` — content area
*   `.aips-timeline-item.aips-timeline-failed` — red accent
*   `.aips-timeline-item.aips-timeline-completed` — green accent
*   `.aips-log-row-error td` — light red background tint (`background: #fef2f2`)
*   `.aips-log-row-warning td` — light amber tint (`background: #fffbeb`)
*   `.aips-children-row td` — no top/bottom padding, nested table styling
*   `.aips-view-mode-btn.active` — active state styling
*   `.aips-history-parent-row` — slightly different background (e.g., `#f9f9f9`) to distinguish from flat rows

* * *

### Phase 7 — Tests

**Create `ai-post-scheduler/tests/test-history-parent-child.php`**:

PHP

Copy code

    <?php
    /**
     * Tests for hierarchical history (parent_id, create_child, get_top_level, get_children, get_child_summary)
     */
    class Test_History_Parent_Child extends WP_UnitTestCase {
        private $repository;
        private $service;
    
        public function setUp(): void {
            parent::setUp();
            $this->repository = new AIPS_History_Repository();
            $this->service = new AIPS_History_Service($this->repository);
        }
    
        public function tearDown(): void {
            global $wpdb;
            $wpdb->query("DELETE FROM {$wpdb->prefix}aips_history WHERE generated_title LIKE 'TEST_%'");
            parent::tearDown();
        }
    
        /** Test that create() persists parent_id when provided */
        public function test_create_persists_parent_id() { ... }
    
        /** Test create_child() sets parent_id to parent's ID */
        public function test_create_child_sets_parent_id() { ... }
    
        /** Test create_child() inherits correlation_id from parent */
        public function test_create_child_inherits_correlation_id() { ... }
    
        /** Test get_top_level() excludes rows with parent_id set */
        public function test_get_top_level_excludes_children() { ... }
    
        /** Test get_top_level() includes schedule_lifecycle containers */
        public function test_get_top_level_includes_schedule_lifecycle() { ... }
    
        /** Test get_children() returns only direct children ordered by created_at ASC */
        public function test_get_children_returns_direct_children() { ... }
    
        /** Test get_children() returns empty array for orphaned/childless container */
        public function test_get_children_returns_empty_for_leaf() { ... }
    
        /** Test get_child_summary() returns correct aggregates */
        public function test_get_child_summary_correct_aggregates() { ... }
    
        /** Test get_child_summary() returns zeroes for container with no children */
        public function test_get_child_summary_returns_zeroes_for_no_children() { ... }
    }
    

Implement these tests using the WP\_UnitTestCase pattern visible in `AIPS_History_Repository_Test.php`. Use direct `$wpdb->insert()` to create test fixtures. Wrap test data titles in `'TEST_'` prefix for easy cleanup.

**Create `ai-post-scheduler/tests/test-history-operation-type.php`**:

PHP

Copy code

    <?php
    class Test_History_Operation_Type extends WP_UnitTestCase {
        /** Test all constants are non-empty strings */
        public function test_constants_are_non_empty_strings() { ... }
    
        /** Test get_label returns non-empty string for all types */
        public function test_get_label_returns_label_for_all_types() { ... }
    
        /** Test get_all_types returns array with all 7 types */
        public function test_get_all_types_returns_all_types() { ... }
    
        /** Test is_parent_type returns true for SCHEDULE_RUN, TOPIC_GENERATION_BATCH, POST_GENERATION_BATCH */
        public function test_is_parent_type_for_parent_types() { ... }
    
        /** Test is_parent_type returns false for child types */
        public function test_is_parent_type_false_for_child_types() { ... }
    
        /** Test get_label returns a fallback (not empty) for unknown type */
        public function test_get_label_fallback_for_unknown_type() { ... }
    }
    

* * *

IMPORTANT IMPLEMENTATION NOTES
------------------------------

1.  **Read the actual file content before editing** — use the view tool for any file you need to edit to get exact line numbers and avoid breaking existing code.
    
2.  **Do not break existing tests** — the flat history view must still work. `get_history()` is used by existing tests and must not be changed in a breaking way.
    
3.  **The `_do_bulk_generate_topics()` refactor** — before changing the signature, read the actual current implementation carefully. The refactor to return a result instead of calling AIPS\_Ajax\_Response internally is the cleanest approach, but make sure you read the actual code first.
    
4.  **`AIPS_Correlation_ID::reset()`** — when you add `parent_history_id` to it, make sure the existing `reset()` call in all scheduler/cron classes also resets the parent ID.
    
5.  **History page view mode** — the default should be `'operations'` (the new view). The "All Items" flat view stays available. Both must work correctly after the change.
    
6.  **Run tests after implementation**: From `ai-post-scheduler/`, run `composer install && vendor/bin/phpunit tests/test-history-parent-child.php tests/test-history-operation-type.php` to verify your new tests pass. Also run the full suite to ensure no regressions: `vendor/bin/phpunit 2>&1 | tail -20`.
    
7.  **Version**: The plugin version should be bumped to `2.6.0` in `ai-post-scheduler.php` (the `AIPS_VERSION` constant and the file header `Version:` comment).
    

DELIVERABLES SUMMARY
--------------------

Files to create:

*   `ai-post-scheduler/includes/class-aips-history-operation-type.php`
*   `ai-post-scheduler/templates/partials/history-parent-row.php`
*   `ai-post-scheduler/templates/partials/history-child-row.php`
*   `ai-post-scheduler/tests/test-history-parent-child.php`
*   `ai-post-scheduler/tests/test-history-operation-type.php`

Files to modify:

*   `ai-post-scheduler/ai-post-scheduler.php` — version bump to 2.6.0
*   `ai-post-scheduler/includes/class-aips-db-manager.php` — add parent\_id column + indexes to aips\_history schema
*   `ai-post-scheduler/includes/class-aips-upgrades.php` — add migrate\_to\_2\_6\_0()
*   `ai-post-scheduler/includes/class-aips-history-repository.php` — add get\_children(), get\_top\_level(), get\_child\_summary(); update create() for parent\_id
*   `ai-post-scheduler/includes/class-aips-history-container.php` — add create\_child(); update persist() for parent\_id
*   `ai-post-scheduler/includes/class-aips-correlation-id.php` — add set\_parent\_history\_id() / get\_parent\_history\_id()
*   `ai-post-scheduler/includes/class-aips-history.php` — add ajax\_get\_history\_top\_level(), ajax\_get\_operation\_children(), update ajax\_get\_history\_logs()
*   `ai-post-scheduler/includes/class-aips-author-topics-scheduler.php` — add TOPIC\_GENERATION\_BATCH parent containers
*   `ai-post-scheduler/includes/class-aips-author-topics-controller.php` — refactor \_do\_bulk\_generate\_topics(), add parent containers in ajax handlers
*   `ai-post-scheduler/includes/class-aips-author-post-generator.php` — add POST\_GENERATION\_BATCH parent container in cron run
*   `ai-post-scheduler/includes/class-aips-schedule-processor.php` — set parent\_history\_id on correlation manager when schedule\_lifecycle container is created
*   `ai-post-scheduler/templates/admin/history.php` — add view mode toggle, operation type filter, operations tbody, new HTML templates
*   `ai-post-scheduler/templates/partials/history-row.php` — minor: no changes needed (flat view stays)
*   `ai-post-scheduler/assets/js/admin-history.js` — add operations view, toggleOperationChildren(), modal tabs, severity row colouring
*   `ai-post-scheduler/assets/css/admin.css` — add timeline, card, tab, child-row CSS
*   `ai-post-scheduler/CHANGELOG.md` — add 2.6.0 entry (if file exists)

After all changes are made, run the tests and fix any failures before finishing.