You are implementing a hierarchical "History of Histories" system for a WordPress plugin. The plugin lives at /home/runner/work/wp-ai-scheduler/wp-ai-scheduler/ai-post-scheduler/.

CODING STANDARDS (MANDATORY)
----------------------------

*   Use TABS for indentation in PHP
*   Use `array()` syntax, NOT `[]`
*   Add `if (!defined('ABSPATH')) { exit; }` to every PHP file
*   All class names use `AIPS_` prefix, underscore separated
*   File names mirror class names: `class-aips-foo-bar.php`
*   Use existing `AIPS_Ajax_Response::success()` / `AIPS_Ajax_Response::error()` for AJAX responses
*   WordPress escaping, sanitization, nonce, capability patterns throughout

WHAT YOU NEED TO IMPLEMENT
--------------------------

### Phase 1a: Schema - add `parent_id` to `aips_history`

In `/home/runner/work/wp-ai-scheduler/wp-ai-scheduler/ai-post-scheduler/includes/class-aips-db-manager.php`, in the `CREATE TABLE $table_history` statement (around line 87), add after `correlation_id varchar(36) DEFAULT NULL,`:

Code

Copy code

    parent_id bigint(20) DEFAULT NULL,
    

And add these KEY lines after the existing KEY lines (before the closing `)`):

Code

Copy code

    KEY parent_id (parent_id),
    KEY parent_id_created (parent_id, created_at),
    

### Phase 1b: Upgrade migration `migrate_to_2_6_0()`

In `/home/runner/work/wp-ai-scheduler/wp-ai-scheduler/ai-post-scheduler/includes/class-aips-upgrades.php`:

1.  In `run_upgrade()`, after the existing `2.5.0` block (line 38), add:

PHP

Copy code

    if (version_compare($from_version, '2.6.0', '<')) {
        $this->migrate_to_2_6_0();
    }
    

2.  Add a new `migrate_to_2_6_0()` method that:
    *   Checks if `aips_history` table exists
    *   Checks if `parent_id` column already exists (SHOW COLUMNS guard)
    *   If not, runs: `ALTER TABLE {table} ADD COLUMN parent_id bigint(20) DEFAULT NULL AFTER correlation_id`
    *   Checks and adds `KEY parent_id (parent_id)` index (SHOW INDEX guard)
    *   Checks and adds `KEY parent_id_created (parent_id, created_at)` index (SHOW INDEX guard)
    *   Follow the exact same pattern as `migrate_to_2_3_1()` for guard patterns

### Phase 1c: Update plugin version to 2.6.0

In `/home/runner/work/wp-ai-scheduler/wp-ai-scheduler/ai-post-scheduler/ai-post-scheduler.php`:

*   Change `AIPS_VERSION` constant from `'2.5.0'` to `'2.6.0'`
*   Also update the `Version:` header comment if present

In CHANGELOG.md (in the root of the repo at /home/runner/work/wp-ai-scheduler/wp-ai-scheduler/CHANGELOG.md OR at /home/runner/work/wp-ai-scheduler/wp-ai-scheduler/ai-post-scheduler/CHANGELOG.md — check which exists), add a new `## [2.6.0]` section at the top.

### Phase 1d: Repository changes

In `/home/runner/work/wp-ai-scheduler/wp-ai-scheduler/ai-post-scheduler/includes/class-aips-history-repository.php`:

1.  In the `create()` method (around line 881), after the existing `$insert_data` assignments and before `$format = array(...)`, add support for `parent_id`:

PHP

Copy code

    if (isset($data['parent_id']) && $data['parent_id']) {
        $insert_data['parent_id'] = absint($data['parent_id']);
        $format[] = '%d';
    }
    

Wait - the format array is defined after. So add parent\_id to insert\_data along with the others, and add `'%d'` to the format array. Here's the exact change: in `$insert_data`, add:

PHP

Copy code

    'parent_id' => isset($data['parent_id']) && $data['parent_id'] ? absint($data['parent_id']) : null,
    

And in `$format`, add `'%d'` at the end (so format becomes 14 items). Actually looking carefully at the create() method:

PHP

Copy code

    $insert_data = array(
        'uuid' => ...,
        'correlation_id' => ...,
        'template_id' => ...,
        'author_id' => ...,
        'topic_id' => ...,
        'creation_method' => ...,
        'status' => ...,
        'prompt' => ...,
        'generated_title' => ...,
        'generated_content' => ...,
        'error_message' => ...,
        'post_id' => ...,
        'created_at' => ...,
    );
    
    $format = array('%s', '%s', '%d', '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d');
    

Add `'parent_id' => isset($data['parent_id']) && $data['parent_id'] ? absint($data['parent_id']) : null,` to $insert\_data, and `'%d'` to $format.

2.  Add method `get_children($parent_id)`:

PHP

Copy code

    /**
     * Get all child history containers for a given parent.
     *
     * @param int $parent_id Parent history ID.
     * @return array Array of history objects ordered by created_at ASC.
     */
    public function get_children($parent_id) {
        $parent_id = absint($parent_id);
        if (!$parent_id) {
            return array();
        }
        $templates_table = $this->wpdb->prefix . 'aips_templates';
        return $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT h.id, h.uuid, h.correlation_id, h.parent_id, h.post_id, h.template_id, h.status,
                    h.generated_title, h.error_message, h.created_at, h.completed_at,
                    h.author_id, h.topic_id, h.creation_method, t.name as template_name
             FROM {$this->table_name} h
             LEFT JOIN {$templates_table} t ON h.template_id = t.id
             WHERE h.parent_id = %d
             ORDER BY h.created_at ASC",
            $parent_id
        ));
    }
    

3.  Add method `get_top_level($args = array())`: This is like `get_history()` but:

*   Adds `h.parent_id IS NULL` to `$where_clauses` (instead of filtering out `schedule_lifecycle`)
*   Does NOT filter out `schedule_lifecycle` containers (the existing `get_history()` does, this one does NOT)
*   Adds support for `operation_type` filter in `$args` (filters by `h.creation_method = $args['operation_type']`)
*   Returns same shape: `array('items' => [], 'total' => int, 'pages' => int, 'current_page' => int)`
*   Uses 'list' fields by default (same field list as `get_history()` 'list' mode)

PHP

Copy code

    /**
     * Get paginated top-level (parent) history containers.
     *
     * Returns only containers where parent_id IS NULL, which includes
     * schedule_lifecycle containers and batch containers.
     *
     * @param array $args Same args as get_history(), plus 'operation_type'.
     * @return array Same shape as get_history().
     */
    public function get_top_level($args = array()) {
        $defaults = array(
            'per_page'       => 20,
            'page'           => 1,
            'status'         => '',
            'search'         => '',
            'operation_type' => '',
            'orderby'        => 'created_at',
            'order'          => 'DESC',
        );
        $args = wp_parse_args($args, $defaults);
        $offset = ($args['page'] - 1) * $args['per_page'];
    
        $fields_sql = "h.id, h.uuid, h.correlation_id, h.parent_id, h.post_id, h.template_id, h.status, h.generated_title, h.error_message, h.created_at, h.completed_at, h.author_id, h.topic_id, h.creation_method, t.name as template_name";
    
        $where_clauses = array('1=1', 'h.parent_id IS NULL');
        $where_args    = array();
    
        if (!empty($args['status'])) {
            $where_clauses[] = 'h.status = %s';
            $where_args[]    = $args['status'];
        }
    
        if (!empty($args['operation_type'])) {
            $where_clauses[] = 'h.creation_method = %s';
            $where_args[]    = sanitize_text_field($args['operation_type']);
        }
    
        if (!empty($args['search'])) {
            $where_clauses[] = 'h.generated_title LIKE %s';
            $where_args[]    = '%' . $this->wpdb->esc_like($args['search']) . '%';
        }
    
        $where_sql = implode(' AND ', $where_clauses);
        $orderby   = in_array($args['orderby'], array('created_at', 'completed_at', 'status'), true) ? $args['orderby'] : 'created_at';
        $order     = strtoupper($args['order']) === 'ASC' ? 'ASC' : 'DESC';
        $templates_table = $this->wpdb->prefix . 'aips_templates';
    
        $query_args   = $where_args;
        $query_args[] = $args['per_page'];
        $query_args[] = $offset;
    
        $results = $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT $fields_sql
             FROM {$this->table_name} h
             LEFT JOIN {$templates_table} t ON h.template_id = t.id
             WHERE $where_sql
             ORDER BY h.$orderby $order
             LIMIT %d OFFSET %d",
            $query_args
        ));
    
        if (!empty($where_args)) {
            $total = $this->wpdb->get_var($this->wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->table_name} h WHERE $where_sql",
                $where_args
            ));
        } else {
            $total = $this->wpdb->get_var("SELECT COUNT(*) FROM {$this->table_name} h WHERE $where_sql");
        }
    
        return array(
            'items'        => $results,
            'total'        => (int) $total,
            'pages'        => (int) ceil($total / $args['per_page']),
            'current_page' => $args['page'],
        );
    }
    

4.  Add method `get_child_summary($parent_id)`:

PHP

Copy code

    /**
     * Get aggregate stats for all children of a parent history container.
     *
     * @param int $parent_id Parent history ID.
     * @return object {total, completed_count, failed_count, processing_count, first_created, last_completed}
     */
    public function get_child_summary($parent_id) {
        $parent_id = absint($parent_id);
        $empty = (object) array(
            'total'             => 0,
            'completed_count'   => 0,
            'failed_count'      => 0,
            'processing_count'  => 0,
            'first_created'     => 0,
            'last_completed'    => 0,
        );
        if (!$parent_id) {
            return $empty;
        }
        $result = $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT
                COUNT(*) as total,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_count,
                SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed_count,
                SUM(CASE WHEN status = 'processing' THEN 1 ELSE 0 END) as processing_count,
                MIN(created_at) as first_created,
                MAX(completed_at) as last_completed
             FROM {$this->table_name}
             WHERE parent_id = %d",
            $parent_id
        ));
        if (!$result) {
            return $empty;
        }
        return (object) array(
            'total'             => (int) $result->total,
            'completed_count'   => (int) $result->completed_count,
            'failed_count'      => (int) $result->failed_count,
            'processing_count'  => (int) $result->processing_count,
            'first_created'     => (int) $result->first_created,
            'last_completed'    => (int) $result->last_completed,
        );
    }
    

5.  The existing `get_history()` keeps `"COALESCE(h.creation_method, '') <> 'schedule_lifecycle'"` as-is for backward compat.

### Phase 1e: History Container changes

In `/home/runner/work/wp-ai-scheduler/wp-ai-scheduler/ai-post-scheduler/includes/class-aips-history-container.php`:

1.  In the `persist()` method (around line 191), in the `$data` array building, the current code is:

PHP

Copy code

    $data = array_merge(
        array(
            'uuid' => $this->uuid,
            'correlation_id' => $this->correlation_id,
            'status' => 'processing',
        ),
        $this->metadata
    );
    

Change to:

PHP

Copy code

    $data = array_merge(
        array(
            'uuid'           => $this->uuid,
            'correlation_id' => $this->correlation_id,
            'status'         => 'processing',
        ),
        $this->metadata
    );
    
    // Inherit parent_id from static correlation manager if not already set.
    if (!isset($data['parent_id']) || !$data['parent_id']) {
        $ambient_parent = AIPS_Correlation_ID::get_parent_history_id();
        if ($ambient_parent && !$this->_is_parent_type($data)) {
            $data['parent_id'] = $ambient_parent;
        }
    }
    

Also add a private helper method `_is_parent_type($data)`:

PHP

Copy code

    /**
     * Check if the given data represents a parent-level history container
     * that should not itself be nested under another parent.
     *
     * @param array $data Container data including creation_method.
     * @return bool
     */
    private function _is_parent_type($data) {
        $parent_types = array(
            'schedule_lifecycle',
            AIPS_History_Operation_Type::SCHEDULE_RUN,
            AIPS_History_Operation_Type::TOPIC_GENERATION_BATCH,
            AIPS_History_Operation_Type::POST_GENERATION_BATCH,
        );
        $method = isset($data['creation_method']) ? $data['creation_method'] : '';
        if (in_array($method, $parent_types, true)) {
            return true;
        }
        // Also check $this->type
        if (in_array($this->type, $parent_types, true)) {
            return true;
        }
        return false;
    }
    

2.  Add method `create_child($type, $metadata = array())` after the `get_session()` method:

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
        if ($this->correlation_id && !isset($metadata['correlation_id'])) {
            $metadata['correlation_id'] = $this->correlation_id;
        }
        return new self($this->repository, $type, $metadata);
    }
    

### Phase 2: Operation Type Taxonomy

Create new file `/home/runner/work/wp-ai-scheduler/wp-ai-scheduler/ai-post-scheduler/includes/class-aips-history-operation-type.php`:

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
    

### Phase 3a: Wire correlation ID manager with parent history ID

In `/home/runner/work/wp-ai-scheduler/wp-ai-scheduler/ai-post-scheduler/includes/class-aips-correlation-id.php`:

Add after the existing `private static $current_id = null;`:

PHP

Copy code

    /** @var int|null Active parent history ID for the current run. */
    private static $parent_history_id = null;
    

Add these methods after `reset()`:

PHP

Copy code

    /**
     * Set the active parent history ID for the current execution context.
     *
     * @param int|null $id Parent history container ID, or null to clear.
     * @return void
     */
    public static function set_parent_history_id($id) {
        self::$parent_history_id = $id ? absint($id) : null;
    }
    
    /**
     * Get the active parent history ID.
     *
     * @return int|null
     */
    public static function get_parent_history_id() {
        return self::$parent_history_id;
    }
    

In the existing `reset()` method, add `self::$parent_history_id = null;` so it becomes:

PHP

Copy code

    public static function reset() {
        self::$current_id = null;
        self::$parent_history_id = null;
    }
    

### Phase 3a (schedule processor): Wire schedule\_lifecycle parent

In `/home/runner/work/wp-ai-scheduler/wp-ai-scheduler/ai-post-scheduler/includes/class-aips-schedule-processor.php`, in `execute_schedule_logic()`, the method creates a history container via `$this->result_handler->get_or_create_schedule_history($schedule->schedule_id)` around line 301.

After `$history = $this->result_handler->get_or_create_schedule_history(...)` and after the `if ($history)` block (but still inside the method, before the generation loop), add:

PHP

Copy code

    // Register this lifecycle container as the ambient parent so all
    // child generation containers created during this run inherit parent_id.
    if ($history && $history->get_id()) {
        AIPS_Correlation_ID::set_parent_history_id($history->get_id());
    }
    

And in `execute_schedule_with_lock()`, the `finally` block isn't explicit, but `execute_schedule_logic()` ends naturally. The best place to reset is in `process_single_schedule()` where it already has `finally { AIPS_Correlation_ID::reset(); }`. Since `reset()` now also resets `parent_history_id`, this is already handled.

For `execute_schedule_with_lock()`, look at the runner's run() method — it generates its own correlation ID. We need to also reset parent\_history\_id. Look at where the lock runs via `$this->runner->run()`. The simplest fix is: in `execute_schedule_logic()`, at the very end of the method (after the result handling), add:

PHP

Copy code

    AIPS_Correlation_ID::set_parent_history_id(null);
    

But we need to make sure this happens even on exceptions. Let me check `AIPS_Generation_Execution_Runner::run()` to see if it calls `AIPS_Correlation_ID::reset()`.

Actually, looking at the code: `process_single_schedule()` has `finally { AIPS_Correlation_ID::reset(); }` which now resets `parent_history_id` too. For `execute_schedule_with_lock()`, the runner handles correlation. Since `reset()` clears both, we need to be careful.

The cleanest approach: In `execute_schedule_logic()`, wrap the generation loop in a try/finally that clears `parent_history_id`:

PHP

Copy code

    try {
        // ... existing generation loop and result handling ...
    } finally {
        AIPS_Correlation_ID::set_parent_history_id(null);
    }
    

Actually let's read the actual file to see the complete structure of `execute_schedule_logic()`. Read the file first then make the minimal change.

The key change in `execute_schedule_logic()`:

1.  After creating/getting the `$history` container (line ~301-337), set the parent:

PHP

Copy code

    if ($history && $history->get_id()) {
        AIPS_Correlation_ID::set_parent_history_id($history->get_id());
    }
    

2.  At the end of the method, before the return, reset it:

PHP

Copy code

    AIPS_Correlation_ID::set_parent_history_id(null);
    

This is safe because `execute_schedule_logic()` is always called in a context where the correlation ID will be reset after (either in `process_single_schedule()`'s finally block, or in the runner's cleanup).

### Phase 3b: Author topics scheduler: TOPIC\_GENERATION\_BATCH parent

In `/home/runner/work/wp-ai-scheduler/wp-ai-scheduler/ai-post-scheduler/includes/class-aips-author-topics-scheduler.php`, in `process_topic_generation()`:

Change the method from:

PHP

Copy code

    public function process_topic_generation() {
        $this->logger->log('Starting scheduled topic generation', 'info');
        $due_authors = $this->authors_repository->get_due_for_topic_generation();
        if (empty($due_authors)) {
            $this->logger->log('No authors due for topic generation', 'info');
            return;
        }
        $this->logger->log('Found ' . count($due_authors) . ' authors due for topic generation', 'info');
        foreach ($due_authors as $author) {
            AIPS_Correlation_ID::generate();
            try {
                $this->generate_topics_for_author($author);
            } finally {
                AIPS_Correlation_ID::reset();
            }
        }
        $this->logger->log('Completed scheduled topic generation', 'info');
    }
    

To:

PHP

Copy code

    public function process_topic_generation() {
        $this->logger->log('Starting scheduled topic generation', 'info');
        $due_authors = $this->authors_repository->get_due_for_topic_generation();
        if (empty($due_authors)) {
            $this->logger->log('No authors due for topic generation', 'info');
            return;
        }
        $this->logger->log('Found ' . count($due_authors) . ' authors due for topic generation', 'info');
    
        // Create a batch-level parent container for the whole scheduled run.
        AIPS_Correlation_ID::generate();
        $batch_parent = $this->history_service->create(AIPS_History_Operation_Type::TOPIC_GENERATION_BATCH, array(
            'creation_method' => AIPS_History_Operation_Type::TOPIC_GENERATION_BATCH,
        ));
        $batch_parent->record('activity', sprintf(
            /* translators: %d: number of authors */
            __('Scheduled topic generation started for %d author(s).', 'ai-post-scheduler'),
            count($due_authors)
        ));
        AIPS_Correlation_ID::set_parent_history_id($batch_parent->get_id());
    
        try {
            foreach ($due_authors as $author) {
                AIPS_Correlation_ID::generate();
                try {
                    $this->generate_topics_for_author($author);
                } finally {
                    AIPS_Correlation_ID::reset();
                    // Re-set parent after each per-author reset so subsequent authors still get parent_id.
                    AIPS_Correlation_ID::set_parent_history_id($batch_parent->get_id());
                }
            }
        } finally {
            AIPS_Correlation_ID::set_parent_history_id(null);
        }
    
        $batch_parent->complete_success(array(
            'generated_title' => sprintf(
                /* translators: %d: number of authors */
                __('Scheduled: %d author(s) processed', 'ai-post-scheduler'),
                count($due_authors)
            ),
        ));
        $this->logger->log('Completed scheduled topic generation', 'info');
    }
    

IMPORTANT: The `generate_topics_for_author()` method creates history containers for each author. Those per-author containers should automatically get `parent_id` set to `$batch_parent->get_id()` via the `AIPS_Correlation_ID::get_parent_history_id()` mechanism in `AIPS_History_Container::persist()`. But notice that in `process_topic_generation()`, we call `AIPS_Correlation_ID::generate()` inside the loop which overwrites the correlation ID but NOT the parent\_history\_id. After the inner `AIPS_Correlation_ID::reset()` call, the parent\_history\_id gets reset to null too! That's why we re-set it in the finally block.

Wait, looking at the flow more carefully: The inner loop calls `AIPS_Correlation_ID::generate()` then `AIPS_Correlation_ID::reset()` in the finally. `reset()` now resets BOTH `$current_id` and `$parent_history_id`. So we need to re-set `parent_history_id` after each author's `reset()` call.

The code above handles this by re-setting in the finally block.

### Phase 3b: Author topics controller: TOPIC\_GENERATION\_BATCH parent

In `/home/runner/work/wp-ai-scheduler/wp-ai-scheduler/ai-post-scheduler/includes/class-aips-author-topics-controller.php`, refactor `_do_bulk_generate_topics()` to return a result array instead of calling `AIPS_Ajax_Response`:

Change the method signature and body. The current implementation calls `AIPS_Ajax_Response::success()` or `AIPS_Ajax_Response::error()` at the end. Change it to return an array:

PHP

Copy code

    /**
     * Shared bulk generation driver. Returns a result array instead of
     * sending the AJAX response directly so callers can complete the parent
     * history container before responding.
     *
     * @param int[]  $topic_ids Sanitized topic IDs to process.
     * @param array  $options   Options forwarded to AIPS_Bulk_Generator_Service::run().
     * @return array {success: bool, was_limited: bool, message: string, success_count: int, failed_count: int, errors: array}
     */
    private function _do_bulk_generate_topics( array $topic_ids, array $options ): array {
        $post_generator = $this->post_generator;
    
        $result = $this->bulk_generator_service->run(
            $topic_ids,
            function ( $topic_id ) use ( $post_generator ) {
                return $post_generator->generate_now( $topic_id );
            },
            $options
        );
    
        if ( $result->was_limited ) {
            return array(
                'success'       => false,
                'was_limited'   => true,
                'message'       => sprintf(
                    /* translators: 1: selected count, 2: max allowed */
                    __( 'Too many topics selected (%1$d). Please select no more than %2$d at a time for immediate generation.', 'ai-post-scheduler' ),
                    $result->failed_count,
                    $result->max_bulk
                ),
                'success_count' => 0,
                'failed_count'  => $result->failed_count,
                'errors'        => array(),
            );
        }
    
        $message = sprintf(
            /* translators: %d: number of posts */
            __( '%d post(s) generated successfully.', 'ai-post-scheduler' ),
            $result->success_count
        );
        if ( $result->failed_count > 0 ) {
            $message .= ' ' . sprintf(
                /* translators: %d: number of failures */
                __( '%d failed.', 'ai-post-scheduler' ),
                $result->failed_count
            );
        }
    
        return array(
            'success'       => true,
            'was_limited'   => false,
            'message'       => $message,
            'success_count' => $result->success_count,
            'failed_count'  => $result->failed_count,
            'errors'        => $result->errors,
        );
    }
    

Then update `ajax_bulk_generate_from_queue()` to:

PHP

Copy code

    public function ajax_bulk_generate_from_queue() {
        if ( ! check_ajax_referer('aips_ajax_nonce', 'nonce', false) ) {
            AIPS_Ajax_Response::error(__('Invalid nonce.', 'ai-post-scheduler'));
        }
        if (!current_user_can('manage_options')) {
            AIPS_Ajax_Response::permission_denied();
        }
        $topic_ids = isset($_POST['topic_ids']) && is_array($_POST['topic_ids']) ? array_map('absint', $_POST['topic_ids']) : array();
        if (empty($topic_ids)) {
            AIPS_Ajax_Response::error(__('No topics selected.', 'ai-post-scheduler'));
        }
    
        $batch_history = $this->history_service->create(AIPS_History_Operation_Type::TOPIC_GENERATION_BATCH, array(
            'creation_method' => AIPS_History_Operation_Type::TOPIC_GENERATION_BATCH,
            'user_id'         => get_current_user_id(),
        ));
        $batch_history->record('activity', sprintf(
            /* translators: %d: number of topics */
            __('User triggered bulk topic generation for %d topic(s).', 'ai-post-scheduler'),
            count($topic_ids)
        ));
        AIPS_Correlation_ID::set_parent_history_id($batch_history->get_id());
    
        $result = $this->_do_bulk_generate_topics(
            $topic_ids,
            array(
                'history_type' => 'bulk_generation',
                'history_meta' => array( 'topic_count' => count( $topic_ids ) ),
                'trigger_name' => 'ajax_bulk_generate_from_queue',
                'user_action'  => 'bulk_generation',
                'user_message' => sprintf(
                    /* translators: %d: number of topics */
                    __( 'User initiated bulk generation for %d topics', 'ai-post-scheduler' ),
                    count( $topic_ids )
                ),
                'error_formatter' => function ( $topic_id, $msg ) {
                    /* translators: 1: topic ID, 2: error message */
                    return sprintf( __( 'Topic ID %1$d: %2$s', 'ai-post-scheduler' ), $topic_id, $msg );
                },
            )
        );
    
        AIPS_Correlation_ID::set_parent_history_id(null);
    
        if ( $result['was_limited'] ) {
            $batch_history->complete_failure($result['message']);
            AIPS_Ajax_Response::error(array( 'message' => $result['message'] ));
            return;
        }
    
        if ( $result['success'] ) {
            $batch_history->complete_success(array( 'generated_title' => $result['message'] ));
        } else {
            $batch_history->complete_failure($result['message']);
        }
    
        AIPS_Ajax_Response::success(array(
            'message'       => $result['message'],
            'success_count' => $result['success_count'],
            'failed_count'  => $result['failed_count'],
            'errors'        => $result['errors'],
        ));
    }
    

And update `ajax_bulk_generate_topics()` similarly:

PHP

Copy code

    public function ajax_bulk_generate_topics() {
        if ( ! check_ajax_referer('aips_ajax_nonce', 'nonce', false) ) {
            AIPS_Ajax_Response::error(__('Invalid nonce.', 'ai-post-scheduler'));
        }
        if (!current_user_can('manage_options')) {
            AIPS_Ajax_Response::permission_denied();
        }
        $topic_ids = isset($_POST['topic_ids']) && is_array($_POST['topic_ids']) ? array_map('absint', $_POST['topic_ids']) : array();
        if (empty($topic_ids)) {
            AIPS_Ajax_Response::error(__('No topics selected.', 'ai-post-scheduler'));
        }
    
        $batch_history = $this->history_service->create(AIPS_History_Operation_Type::TOPIC_GENERATION_BATCH, array(
            'creation_method' => AIPS_History_Operation_Type::TOPIC_GENERATION_BATCH,
            'user_id'         => get_current_user_id(),
        ));
        $batch_history->record('activity', sprintf(
            /* translators: %d: number of topics */
            __('User triggered bulk topic generation for %d topic(s).', 'ai-post-scheduler'),
            count($topic_ids)
        ));
        AIPS_Correlation_ID::set_parent_history_id($batch_history->get_id());
    
        $result = $this->_do_bulk_generate_topics(
            $topic_ids,
            array(
                'history_type' => 'bulk_generate',
                'history_meta' => array( 'entity_type' => 'topics', 'entity_count' => count( $topic_ids ) ),
                'trigger_name' => 'ajax_bulk_generate_topics',
                'user_action'  => 'bulk_generate_topics',
                'user_message' => sprintf(
                    /* translators: %d: number of topics */
                    __( 'User initiated bulk generation for %d topics', 'ai-post-scheduler' ),
                    count( $topic_ids )
                ),
                'error_formatter' => function ( $topic_id, $msg ) {
                    /* translators: 1: topic ID, 2: error message */
                    return sprintf( __( 'Topic ID %1$d: %2$s', 'ai-post-scheduler' ), $topic_id, $msg );
                },
            )
        );
    
        AIPS_Correlation_ID::set_parent_history_id(null);
    
        if ( $result['was_limited'] ) {
            $batch_history->complete_failure($result['message']);
            AIPS_Ajax_Response::error(array( 'message' => $result['message'] ));
            return;
        }
    
        if ( $result['success'] ) {
            $batch_history->complete_success(array( 'generated_title' => $result['message'] ));
        } else {
            $batch_history->complete_failure($result['message']);
        }
    
        AIPS_Ajax_Response::success(array(
            'message'       => $result['message'],
            'success_count' => $result['success_count'],
            'failed_count'  => $result['failed_count'],
            'errors'        => $result['errors'],
        ));
    }
    

### Phase 3e: Author Post Generator: POST\_GENERATION\_BATCH parent

In `/home/runner/work/wp-ai-scheduler/wp-ai-scheduler/ai-post-scheduler/includes/class-aips-author-post-generator.php`, in the `process()` method (around line 109):

After getting `$due_authors` and checking it's not empty, add the batch parent container. The current code is:

PHP

Copy code

    foreach ($due_authors as $author) {
        $this->runner->run(
            function() use ($author) {
                $this->generate_post_for_author($author);
            },
            'author_post_generation',
            array('author_id' => $author->id)
        );
    }
    $this->logger->log('Completed scheduled author post generation', 'info');
    

Change to:

PHP

Copy code

    // Create a batch-level parent container for the whole post generation run.
    $batch_parent = $this->history_service->create(AIPS_History_Operation_Type::POST_GENERATION_BATCH, array(
        'creation_method' => AIPS_History_Operation_Type::POST_GENERATION_BATCH,
    ));
    $batch_parent->record('activity', sprintf(
        /* translators: %d: number of authors */
        __('Scheduled post generation started for %d author(s).', 'ai-post-scheduler'),
        count($due_authors)
    ));
    AIPS_Correlation_ID::set_parent_history_id($batch_parent->get_id());
    
    try {
        foreach ($due_authors as $author) {
            $this->runner->run(
                function() use ($author) {
                    $this->generate_post_for_author($author);
                },
                'author_post_generation',
                array('author_id' => $author->id)
            );
            // Re-set parent after runner may have reset correlation ID.
            AIPS_Correlation_ID::set_parent_history_id($batch_parent->get_id());
        }
    } finally {
        AIPS_Correlation_ID::set_parent_history_id(null);
    }
    
    $batch_parent->complete_success(array(
        'generated_title' => sprintf(
            /* translators: %d: number of authors */
            __('Batch: %d author(s) processed', 'ai-post-scheduler'),
            count($due_authors)
        ),
    ));
    $this->logger->log('Completed scheduled author post generation', 'info');
    

### Phase 4: History Page & AJAX endpoints

**4a. New AJAX endpoints in `class-aips-history.php`**

In `/home/runner/work/wp-ai-scheduler/wp-ai-scheduler/ai-post-scheduler/includes/class-aips-history.php`:

In `__construct()`, add:

PHP

Copy code

    add_action('wp_ajax_aips_get_history_top_level', array($this, 'ajax_get_history_top_level'));
    add_action('wp_ajax_aips_get_operation_children', array($this, 'ajax_get_operation_children'));
    

Add these two methods:

PHP

Copy code

    /**
     * AJAX handler to retrieve top-level (parent) history operations.
     *
     * @return void
     */
    public function ajax_get_history_top_level() {
        if ( ! check_ajax_referer('aips_ajax_nonce', 'nonce', false) ) {
            AIPS_Ajax_Response::error(__('Invalid nonce.', 'ai-post-scheduler'));
        }
        if (!current_user_can('manage_options')) {
            AIPS_Ajax_Response::permission_denied();
        }
    
        $page           = isset($_POST['page']) ? absint($_POST['page']) : 1;
        $status         = isset($_POST['status']) ? sanitize_text_field(wp_unslash($_POST['status'])) : '';
        $search         = isset($_POST['search']) ? sanitize_text_field(wp_unslash($_POST['search'])) : '';
        $operation_type = isset($_POST['operation_type']) ? sanitize_text_field(wp_unslash($_POST['operation_type'])) : '';
    
        $result = $this->repository->get_top_level(array(
            'page'           => $page,
            'status'         => $status,
            'search'         => $search,
            'operation_type' => $operation_type,
            'per_page'       => 20,
        ));
    
        $items = array();
        foreach ($result['items'] as $item) {
            $child_summary = $this->repository->get_child_summary($item->id);
            $items[] = array(
                'id'              => (int) $item->id,
                'status'          => $item->status,
                'creation_method' => $item->creation_method,
                'generated_title' => $item->generated_title,
                'template_name'   => isset($item->template_name) ? $item->template_name : '',
                'created_at'      => (int) $item->created_at,
                'completed_at'    => (int) $item->completed_at,
                'operation_label' => AIPS_History_Operation_Type::get_label($item->creation_method),
                'child_summary'   => $child_summary,
            );
        }
    
        AIPS_Ajax_Response::success(array(
            'items'        => $items,
            'total'        => $result['total'],
            'pages'        => $result['pages'],
            'current_page' => $result['current_page'],
        ));
    }
    
    /**
     * AJAX handler to retrieve children of a top-level history operation.
     *
     * @return void
     */
    public function ajax_get_operation_children() {
        if ( ! check_ajax_referer('aips_ajax_nonce', 'nonce', false) ) {
            AIPS_Ajax_Response::error(__('Invalid nonce.', 'ai-post-scheduler'));
        }
        if (!current_user_can('manage_options')) {
            AIPS_Ajax_Response::permission_denied();
        }
    
        $history_id = isset($_POST['history_id']) ? absint($_POST['history_id']) : 0;
        if (!$history_id) {
            AIPS_Ajax_Response::error(__('Invalid history ID.', 'ai-post-scheduler'));
        }
    
        $children      = $this->repository->get_children($history_id);
        $child_summary = $this->repository->get_child_summary($history_id);
    
        $items = array();
        foreach ($children as $child) {
            $duration = null;
            if (!empty($child->created_at) && !empty($child->completed_at)) {
                $duration = (int) $child->completed_at - (int) $child->created_at;
            }
            $post_edit_url = null;
            if (!empty($child->post_id)) {
                $raw_url = get_edit_post_link((int) $child->post_id, 'raw');
                if ($raw_url) {
                    $post_edit_url = esc_url_raw($raw_url);
                }
            }
            $items[] = array(
                'id'              => (int) $child->id,
                'status'          => $child->status,
                'creation_method' => $child->creation_method,
                'generated_title' => $child->generated_title,
                'template_name'   => isset($child->template_name) ? $child->template_name : '',
                'created_at'      => (int) $child->created_at,
                'completed_at'    => (int) $child->completed_at,
                'post_id'         => $child->post_id ? (int) $child->post_id : null,
                'post_edit_url'   => $post_edit_url,
                'duration'        => $duration,
                'operation_label' => AIPS_History_Operation_Type::get_label($child->creation_method),
            );
        }
    
        AIPS_Ajax_Response::success(array(
            'children'      => $items,
            'child_summary' => $child_summary,
            'history_id'    => $history_id,
        ));
    }
    

Also update `ajax_get_history_logs()` to include parent/child metadata. In the existing method, before `AIPS_Ajax_Response::success(...)`, add:

PHP

Copy code

    $child_summary = $this->repository->get_child_summary($history_id);
    $is_parent = $child_summary->total > 0;
    $operation_label = AIPS_History_Operation_Type::get_label(
        isset($history_item->creation_method) ? $history_item->creation_method : ''
    );
    

And in the `AIPS_Ajax_Response::success()` call, add to the array:

PHP

Copy code

    'is_parent'       => $is_parent,
    'child_summary'   => $child_summary,
    'operation_label' => $operation_label,
    

But also fix the duration calculation bug — the current code uses `strtotime($history_item->created_at)` which is wrong since these are now Unix timestamps (bigints). Change to:

PHP

Copy code

    $duration_seconds = null;
    if (!empty($history_item->created_at) && !empty($history_item->completed_at)) {
        $start = (int) $history_item->created_at;
        $end   = (int) $history_item->completed_at;
        if ($start > 0 && $end > 0 && $end >= $start) {
            $duration_seconds = $end - $start;
        }
    }
    

### Phase 4b. New partial: `templates/partials/history-parent-row.php`

Create `/home/runner/work/wp-ai-scheduler/wp-ai-scheduler/ai-post-scheduler/templates/partials/history-parent-row.php`:

PHP

Copy code

    <?php
    /**
     * History parent row partial.
     *
     * Variables available:
     *   $item          - stdObject: the top-level history row
     *   $child_summary - stdObject: aggregate child stats from get_child_summary()
     *
     * @package AI_Post_Scheduler
     * @since 2.6.0
     */
    
    if (!defined('ABSPATH')) {
    	exit;
    }
    
    $op_label   = AIPS_History_Operation_Type::get_label(isset($item->creation_method) ? $item->creation_method : '');
    $total      = isset($child_summary->total) ? (int) $child_summary->total : 0;
    $completed  = isset($child_summary->completed_count) ? (int) $child_summary->completed_count : 0;
    $failed     = isset($child_summary->failed_count) ? (int) $child_summary->failed_count : 0;
    $processing = isset($child_summary->processing_count) ? (int) $child_summary->processing_count : 0;
    
    // Roll-up status
    if ($total > 0) {
    	if ($failed > 0 && $completed === 0) {
    		$rollup_status = 'failed';
    	} elseif ($failed > 0) {
    		$rollup_status = 'partial';
    	} elseif ($processing > 0) {
    		$rollup_status = 'processing';
    	} else {
    		$rollup_status = 'completed';
    	}
    } else {
    	$rollup_status = isset($item->status) ? $item->status : 'pending';
    }
    
    $creation_method = isset($item->creation_method) ? $item->creation_method : '';
    if (strpos($creation_method, 'manual') !== false || strpos($creation_method, 'ajax') !== false || strpos($creation_method, 'bulk') !== false) {
    	$trigger_label = __('Manual', 'ai-post-scheduler');
    } elseif ($creation_method === 'schedule_lifecycle' || $creation_method === AIPS_History_Operation_Type::SCHEDULE_RUN) {
    	$trigger_label = __('Scheduled', 'ai-post-scheduler');
    } else {
    	$trigger_label = __('Cron', 'ai-post-scheduler');
    }
    
    $duration_text = '—';
    if (!empty($item->created_at) && !empty($child_summary->last_completed) && $child_summary->last_completed > 0) {
    	$diff = (int) $child_summary->last_completed - (int) $item->created_at;
    	if ($diff > 0) {
    		if ($diff >= 60) {
    			$duration_text = sprintf('%dm %ds', floor($diff / 60), $diff % 60);
    		} else {
    			$duration_text = sprintf('%ds', $diff);
    		}
    	}
    } elseif (!empty($item->created_at) && !empty($item->completed_at)) {
    	$diff = (int) $item->completed_at - (int) $item->created_at;
    	if ($diff > 0) {
    		if ($diff >= 60) {
    			$duration_text = sprintf('%dm %ds', floor($diff / 60), $diff % 60);
    		} else {
    			$duration_text = sprintf('%ds', $diff);
    		}
    	}
    }
    
    $formatted_date = !empty($item->created_at)
    	? esc_html(AIPS_DateTime::from_timestamp((int) $item->created_at)->format('M j, Y g:i a'))
    	: '—';
    ?>
    <tr class="aips-history-parent-row" data-id="<?php echo esc_attr($item->id); ?>">
    	<td class="check-column"><input type="checkbox" name="history_ids[]" value="<?php echo esc_attr($item->id); ?>"></td>
    	<td class="cell-primary">
    		<strong><?php echo esc_html($op_label); ?></strong>
    		<?php if (!empty($item->template_name)) : ?>
    			<span class="aips-meta-text">— <?php echo esc_html($item->template_name); ?></span>
    		<?php endif; ?>
    		<?php if (!empty($item->generated_title)) : ?>
    			<div class="aips-row-subtitle"><?php echo esc_html($item->generated_title); ?></div>
    		<?php endif; ?>
    	</td>
    	<td class="cell-meta">
    		<?php if ($total > 0) : ?>
    			<span class="aips-badge <?php echo ($failed > 0) ? 'aips-badge-error' : 'aips-badge-info'; ?>" title="<?php echo esc_attr(sprintf('%d completed, %d failed', $completed, $failed)); ?>">
    				<?php echo esc_html(sprintf(_n('%d item', '%d items', $total, 'ai-post-scheduler'), $total)); ?>
    			</span>
    			<div class="aips-meta-text"><?php echo esc_html(sprintf(__('%d completed, %d failed', 'ai-post-scheduler'), $completed, $failed)); ?></div>
    		<?php else : ?>
    			<span class="aips-meta-text">—</span>
    		<?php endif; ?>
    	</td>
    	<td class="cell-meta">
    		<span class="aips-badge aips-badge-status-<?php echo esc_attr($rollup_status); ?>"><?php echo esc_html(ucfirst($rollup_status)); ?></span>
    	</td>
    	<td class="cell-meta">
    		<span class="aips-meta-text"><?php echo $formatted_date; ?></span>
    		<span class="aips-badge aips-badge-secondary"><?php echo esc_html($trigger_label); ?></span>
    	</td>
    	<td class="cell-meta"><?php echo esc_html($duration_text); ?></td>
    	<td class="cell-actions">
    		<?php if ($total > 0) : ?>
    			<button type="button" class="aips-btn aips-btn-sm aips-btn-secondary aips-view-operation-children" data-id="<?php echo esc_attr($item->id); ?>">
    				<?php esc_html_e('View Items', 'ai-post-scheduler'); ?>
    			</button>
    		<?php endif; ?>
    		<button type="button" class="aips-btn aips-btn-sm aips-btn-ghost aips-view-history-logs" data-id="<?php echo esc_attr($item->id); ?>">
    			<?php esc_html_e('View Logs', 'ai-post-scheduler'); ?>
    		</button>
    	</td>
    </tr>
    

### Phase 4c. New partial: `templates/partials/history-child-row.php`

Create `/home/runner/work/wp-ai-scheduler/wp-ai-scheduler/ai-post-scheduler/templates/partials/history-child-row.php`:

PHP

Copy code

    <?php
    /**
     * History child row partial (compact).
     *
     * Variables available:
     *   $item - stdObject: a child history row
     *
     * @package AI_Post_Scheduler
     * @since 2.6.0
     */
    
    if (!defined('ABSPATH')) {
    	exit;
    }
    
    $duration_text = '—';
    if (!empty($item->created_at) && !empty($item->completed_at)) {
    	$diff = (int) $item->completed_at - (int) $item->created_at;
    	if ($diff > 0) {
    		if ($diff >= 60) {
    			$duration_text = sprintf('%dm %ds', floor($diff / 60), $diff % 60);
    		} else {
    			$duration_text = sprintf('%ds', $diff);
    		}
    	}
    }
    ?>
    <tr class="aips-history-child-row">
    	<td class="cell-indent"></td>
    	<td class="cell-primary">
    		<?php if (!empty($item->generated_title)) : ?>
    			<?php if (!empty($item->post_id)) : ?>
    				<a href="<?php echo esc_url(get_edit_post_link($item->post_id)); ?>"><?php echo esc_html($item->generated_title); ?></a>
    			<?php else : ?>
    				<?php echo esc_html($item->generated_title); ?>
    			<?php endif; ?>
    		<?php else : ?>
    			<em><?php echo esc_html(AIPS_History_Operation_Type::get_label($item->creation_method)); ?></em>
    		<?php endif; ?>
    	</td>
    	<td class="cell-meta">
    		<span class="aips-badge aips-badge-status-<?php echo esc_attr($item->status); ?>"><?php echo esc_html(ucfirst($item->status)); ?></span>
    	</td>
    	<td class="cell-meta"><?php echo esc_html($duration_text); ?></td>
    	<td class="cell-actions">
    		<button type="button" class="aips-btn aips-btn-sm aips-btn-ghost aips-view-history-logs" data-id="<?php echo esc_attr($item->id); ?>">
    			<?php esc_html_e('Logs', 'ai-post-scheduler'); ?>
    		</button>
    	</td>
    </tr>
    

### Phase 4d. Update `templates/admin/history.php`

Read the existing file first, then add the operations view mode toggle and new table structures. The key additions are:

1.  Add a "View Mode" toggle above the existing filter bar
2.  Add an "Operation Type" filter to the filter bar (visible only in operations mode)
3.  Add a second tbody `id="aips-history-operations-tbody"` for the operations view
4.  Add JS templates at the bottom

The operations tbody should be shown by default (hidden in all-items mode).

For brevity, read the current history.php to understand its structure before editing. The template should be updated minimally - add the view mode toggle and the second tbody/thead. The existing tbody and thead should remain for the "All Items" view.

### Phase 4e. Update `assets/js/admin-history.js`

Read the current file then add to the `AIPS.History` module:

1.  `currentViewMode = 'operations'` property
2.  `switchViewMode(mode)` method
3.  `loadOperationsView(page)` method — AJAX to `aips_get_history_top_level`, renders items into `#aips-history-operations-tbody`
4.  `loadAllView(page)` method — existing reload logic, targets `#aips-history-tbody`
5.  `toggleOperationChildren(id)` method — AJAX to `aips_get_operation_children`, inserts children row after parent row
6.  Update `bindEvents()` to handle `.aips-view-mode-btn`, `.aips-view-operation-children` clicks
7.  Update `init()` to call `loadOperationsView(1)` by default (instead of or in addition to the flat view load)

For the operations view rendering, build HTML from the returned JSON data (item.operation\_label, item.created\_at, item.child\_summary, etc.) and use `wp_date()` formatting by formatting the timestamp client-side.

### Phase 5a. Update `ajax_get_history_logs()`

Already described in Phase 4a - add `is_parent`, `child_summary`, `operation_label` to response. Fix duration calculation to use numeric timestamps.

### Phase 5b/5c. Update history log modal

Add to the modal in `history.php`:

*   Tab nav (Summary | Timeline | Raw Logs)
*   Summary shows stat cards for parent containers
*   Timeline shows children list

In `admin-history.js`:

*   Build tabbed modal structure
*   Add `aips-log-row-error` class to rows with `history_type_id === 2`
*   Add `aips-log-row-warning` class to rows with `history_type_id === 3`

### Phase 5d. Update `assets/css/admin.css`

Append CSS for the new UI components at the end of the file:

*   `.aips-history-modal-tabs` — flex tab bar
*   `.aips-history-summary-cards` — horizontal card grid
*   `.aips-summary-card` — individual stat card
*   `.aips-timeline-item` — timeline layout
*   `.aips-timeline-marker` — dot
*   `.aips-timeline-content` — content
*   `.aips-timeline-item.aips-timeline-failed` — red accent
*   `.aips-timeline-item.aips-timeline-completed` — green accent
*   `.aips-log-row-error td` — `background: #fef2f2`
*   `.aips-log-row-warning td` — `background: #fffbeb`
*   `.aips-children-row td` — no top padding, nested table styling
*   `.aips-view-mode-btn.active` — active state
*   `.aips-history-parent-row` — slightly different background `#f9f9f9`
*   `.cell-indent` — 30px padding-left indent column

### Phase 7: Tests

Create `/home/runner/work/wp-ai-scheduler/wp-ai-scheduler/ai-post-scheduler/tests/test-history-parent-child.php`:

Look at the existing test file structure (look at `tests/test-history-repository.php` or similar) to match the pattern. The tests should:

*   Test that `create()` persists parent\_id
*   Test that `create_child()` sets parent\_id
*   Test that `create_child()` inherits correlation\_id
*   Test that `get_top_level()` excludes rows with parent\_id set
*   Test that `get_top_level()` includes schedule\_lifecycle containers
*   Test that `get_children()` returns only direct children ordered by created\_at ASC
*   Test that `get_children()` returns empty for leaf
*   Test that `get_child_summary()` returns correct aggregates
*   Test that `get_child_summary()` returns zeroes for no children

Use direct `$wpdb->insert()` to create test fixtures. Use `'TEST_%'` prefix in generated\_title for cleanup.

Create `/home/runner/work/wp-ai-scheduler/wp-ai-scheduler/ai-post-scheduler/tests/test-history-operation-type.php`:

*   Test all constants are non-empty strings
*   Test get\_label returns label for all types
*   Test get\_all\_types returns array with all 7 types
*   Test is\_parent\_type for parent types
*   Test is\_parent\_type false for child types
*   Test get\_label fallback for unknown type

IMPLEMENTATION STEPS
--------------------

1.  First, read all the files you need to modify to understand the exact current content
2.  Make changes in this order:
    *   Create `class-aips-history-operation-type.php` (new file)
    *   Update `ai-post-scheduler.php` (version bump)
    *   Update `class-aips-db-manager.php` (schema)
    *   Update `class-aips-upgrades.php` (migration)
    *   Update `class-aips-correlation-id.php` (parent history ID)
    *   Update `class-aips-history-repository.php` (new methods, create() update)
    *   Update `class-aips-history-container.php` (create\_child, persist with parent\_id)
    *   Update `class-aips-history.php` (new AJAX handlers)
    *   Update `class-aips-author-topics-scheduler.php` (batch parent)
    *   Update `class-aips-author-topics-controller.php` (refactor \_do\_bulk\_generate\_topics)
    *   Update `class-aips-author-post-generator.php` (batch parent)
    *   Update `class-aips-schedule-processor.php` (set parent\_history\_id)
    *   Create `templates/partials/history-parent-row.php`
    *   Create `templates/partials/history-child-row.php`
    *   Update `templates/admin/history.php`
    *   Update `assets/js/admin-history.js`
    *   Update `assets/css/admin.css`
    *   Update CHANGELOG.md
    *   Create test files
3.  After all changes, run: `cd /home/runner/work/wp-ai-scheduler/wp-ai-scheduler/ai-post-scheduler && composer install --no-interaction -q && vendor/bin/phpunit tests/test-history-operation-type.php tests/test-history-parent-child.php 2>&1 | tail -30`
4.  Then run full test suite: `vendor/bin/phpunit 2>&1 | tail -30`
5.  Fix any failures

KEY FILES TO READ FIRST
-----------------------

Before making changes, read these files to understand exact current content:

*   `/home/runner/work/wp-ai-scheduler/wp-ai-scheduler/ai-post-scheduler/ai-post-scheduler.php` (version)
*   `/home/runner/work/wp-ai-scheduler/wp-ai-scheduler/ai-post-scheduler/includes/class-aips-schedule-processor.php` (lines 400-600 for the result\_handler and execute\_schedule\_logic end)
*   `/home/runner/work/wp-ai-scheduler/wp-ai-scheduler/ai-post-scheduler/templates/admin/history.php`
*   `/home/runner/work/wp-ai-scheduler/wp-ai-scheduler/ai-post-scheduler/assets/js/admin-history.js`

ADDITIONAL NOTES
----------------

1.  When adding `parent_id` to `aips_history` in the `create()` method: the `$format` array currently has 13 items for 13 data fields. Adding `parent_id` makes it 14, so change the format to have 14 items.
    
2.  For the AIPS\_DateTime class usage: looking at the codebase, `AIPS_DateTime::from_timestamp($ts)->format('M j, Y g:i a')` should be the right call for display formatting. Check the actual class API before using.
    
3.  The `AIPS_Correlation_ID::reset()` is called after each author's run in the loop. Since `reset()` now also clears `parent_history_id`, the per-author loops need to re-set `parent_history_id` after reset. This is already handled in the implementations above.
    
4.  In `class-aips-history-container.php`, the `_is_parent_type()` method uses `AIPS_History_Operation_Type` constants — this class must be loaded before `AIPS_History_Container`. Since the autoloader handles class loading, this should be fine as long as the autoloader resolves `AIPS_History_Operation_Type` correctly.
    
5.  Check that `AIPS_DateTime::from_timestamp()` exists - if not, use whatever datetime method the existing codebase uses in templates. The safe fallback is: `date_i18n('M j, Y g:i a', (int) $item->created_at)`.
    
6.  For the `creation_method` field in history: it's currently `varchar(20)`. The new operation type strings like `topic_generation_batch` are 24 chars. Check if this is a problem. Looking at the DB schema: `creation_method varchar(20) DEFAULT NULL`. We need to extend this to `varchar(50)` or use shorter strings. The string `topic_generation_batch` is 22 chars > 20. This will be silently truncated!
    
    Add to `migrate_to_2_6_0()` a change to the `creation_method` column type: After adding `parent_id`, also do:
    
    SQL
    
    Copy code
    
        ALTER TABLE {table} MODIFY COLUMN creation_method varchar(50) DEFAULT NULL
        
    
    And in `class-aips-db-manager.php`, change the schema to: `creation_method varchar(50) DEFAULT NULL`
    
    This is critical for the operation type strings to work correctly!