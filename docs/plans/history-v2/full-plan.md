# History v2: Hierarchical "History of Histories" — Full Implementation Plan

## Overview

Implement a hierarchical history system for the `wp-ai-scheduler` WordPress plugin. Top-level "parent" containers represent batch operations (schedule runs, topic batches, post generation batches). Child containers are the individual per-post or per-author operations nested beneath them.

**Phases covered:**

- **Phase 1** — Data model: add `parent_id` to `aips_history`, repository methods (`get_children`, `get_top_level`, `get_child_summary`), container `create_child()`
- **Phase 2** — `AIPS_History_Operation_Type` constants class
- **Phase 3a** — Wire `schedule_lifecycle` containers as parents to per-post child containers in `AIPS_Schedule_Processor`
- **Phase 3b** — Wrap `ajax_bulk_generate_topics` and `_do_bulk_generate_from_queue` with `TOPIC_GENERATION_BATCH` parent containers; wire `AIPS_Author_Topics_Scheduler::process_topic_generation()` similarly
- **Phase 3e** — Wrap `AIPS_Author_Post_Generator` cron run in a `POST_GENERATION_BATCH` parent
- **Phase 4** — New grouped history page (top-level operations, expandable inline children, filter bar improvements, legacy badge)
- **Phase 5** — History log modal: Summary / Timeline / Raw Logs tabs + severity row colouring
- **Phase 7** — Tests: `test-history-parent-child.php`, `test-history-operation-type.php`

---

## Coding Standards (MANDATORY)

- Use **TABS** for indentation throughout PHP
- Use `array()` syntax, **NOT** `[]`
- Add `if (!defined('ABSPATH')) { exit; }` to every PHP file
- Follow WordPress escaping, sanitization, nonce, and capability patterns
- All class names use `AIPS_` prefix, underscore separated
- File names mirror class names: `class-aips-foo-bar.php`
- Do **NOT** add manual `require_once` for plugin classes (autoloader handles it)
- Use existing `AIPS_Ajax_Response::success()` / `AIPS_Ajax_Response::error()` for AJAX responses

---

## What Exists (Context)

**Plugin root:** `ai-post-scheduler/` inside the repo.

### Key Existing Files

- `ai-post-scheduler/includes/class-aips-history-container.php` — container with `record()`, `complete_success()`, `complete_failure()`, `persist()`
- `ai-post-scheduler/includes/class-aips-history-service.php` — `create($type, $metadata)` singleton service
- `ai-post-scheduler/includes/class-aips-history-repository.php` — full repository; `get_history()` at line 155 currently excludes `schedule_lifecycle` containers via: `$where_clauses[] = "COALESCE(h.creation_method, '') <> 'schedule_lifecycle'"`
- `ai-post-scheduler/includes/class-aips-history-type.php` — type constants (LOG=1, ERROR=2, WARNING=3, INFO=4, AI_REQUEST=5, AI_RESPONSE=6, DEBUG=7, ACTIVITY=8, SESSION_METADATA=9, METRIC=10)
- `ai-post-scheduler/includes/class-aips-history.php` — AJAX controller; `ajax_get_history_logs()` at ~line 140 is the modal endpoint
- `ai-post-scheduler/includes/class-aips-upgrades.php` — version-gated migrations; current plugin version is `2.5.0` (defined in `ai-post-scheduler/ai-post-scheduler.php`)
- `ai-post-scheduler/includes/class-aips-db-manager.php` — schema; `aips_history` table at ~line 84, `aips_history_log` table at ~line 115
- `ai-post-scheduler/includes/class-aips-author-topics-scheduler.php` — `process_topic_generation()` loops over due authors, calls `generate_topics_for_author()` which creates per-author history containers directly
- `ai-post-scheduler/includes/class-aips-author-topics-controller.php` — `_do_bulk_generate_topics()` delegates to `AIPS_Bulk_Generator_Service::run()`; `ajax_bulk_generate_topics()` and `ajax_bulk_generate_from_queue()` both call `_do_bulk_generate_topics()`
- `ai-post-scheduler/includes/class-aips-author-post-generator.php` — creates `topic_post_generation` containers per post
- `ai-post-scheduler/includes/class-aips-schedule-processor.php` — creates `schedule_lifecycle` containers around schedule runs
- `ai-post-scheduler/includes/class-aips-correlation-id.php` — static correlation ID manager
- `ai-post-scheduler/templates/admin/history.php` — history page template
- `ai-post-scheduler/templates/partials/history-row.php` — row partial
- `ai-post-scheduler/assets/js/admin-history.js` — existing JS with `AIPS.History` module
- `ai-post-scheduler/assets/css/admin.css` — main admin CSS

### DB Schema for `aips_history` (Current)

```sql
id bigint(20) AUTO_INCREMENT PRIMARY KEY
uuid varchar(36)
correlation_id varchar(36)
post_id bigint(20)
template_id bigint(20)
author_id bigint(20)
topic_id bigint(20)
creation_method varchar(20) DEFAULT NULL   -- NOTE: will be extended to varchar(50)
status varchar(50) DEFAULT 'pending'
prompt text
generated_title varchar(500)
generated_content longtext
generation_log longtext
error_message text
created_at bigint(20) unsigned NOT NULL DEFAULT 0
completed_at bigint(20) unsigned NOT NULL DEFAULT 0
```

> **⚠️ CRITICAL:** `creation_method` is currently `varchar(20)`. Operation type strings like `topic_generation_batch` (22 chars) will be silently truncated. **The migration must extend this column to `varchar(50)`.**

---

## Key Files to Read Before Editing

Before making any changes, read these files to understand the exact current content:

- `ai-post-scheduler/ai-post-scheduler.php` (version constant)
- `ai-post-scheduler/includes/class-aips-schedule-processor.php` (lines 400–600 for `execute_schedule_logic()` structure)
- `ai-post-scheduler/templates/admin/history.php`
- `ai-post-scheduler/assets/js/admin-history.js`

---

## Phase 1 — Data Model

### Phase 1a: Schema — Add `parent_id` to `aips_history`

In `class-aips-db-manager.php`, inside the `CREATE TABLE $table_history` statement (around line 87), add after `correlation_id varchar(36) DEFAULT NULL,`:

```sql
parent_id bigint(20) DEFAULT NULL,
```

Also extend `creation_method` to `varchar(50)`:

```sql
creation_method varchar(50) DEFAULT NULL,
```

Add these KEY lines after the existing KEY lines (before the closing `)`):

```sql
KEY parent_id (parent_id),
KEY parent_id_created (parent_id, created_at),
```

---

### Phase 1b: Upgrade Migration — `migrate_to_2_6_0()`

In `class-aips-upgrades.php`:

**1. In `run_upgrade()`,** after the existing `2.5.0` block, add:

```php
if (version_compare($from_version, '2.6.0', '<')) {
    $this->migrate_to_2_6_0();
}
```

**2. Add the `migrate_to_2_6_0()` method** following the exact same guard pattern as `migrate_to_2_3_1()`:

```php
private function migrate_to_2_6_0() {
    global $wpdb;
    $table = $wpdb->prefix . 'aips_history';

    // Guard: table must exist.
    if ($wpdb->get_var("SHOW TABLES LIKE '$table'") !== $table) {
        return;
    }

    // Add parent_id column if not present.
    $column = $wpdb->get_results("SHOW COLUMNS FROM `$table` LIKE 'parent_id'");
    if (empty($column)) {
        $wpdb->query("ALTER TABLE `$table` ADD COLUMN parent_id bigint(20) DEFAULT NULL AFTER correlation_id");
    }

    // Extend creation_method to varchar(50).
    $wpdb->query("ALTER TABLE `$table` MODIFY COLUMN creation_method varchar(50) DEFAULT NULL");

    // Add KEY parent_id if not present.
    $index = $wpdb->get_results("SHOW INDEX FROM `$table` WHERE Key_name = 'parent_id'");
    if (empty($index)) {
        $wpdb->query("ALTER TABLE `$table` ADD KEY parent_id (parent_id)");
    }

    // Add KEY parent_id_created if not present.
    $index2 = $wpdb->get_results("SHOW INDEX FROM `$table` WHERE Key_name = 'parent_id_created'");
    if (empty($index2)) {
        $wpdb->query("ALTER TABLE `$table` ADD KEY parent_id_created (parent_id, created_at)");
    }
}
```

---

### Phase 1c: Update Plugin Version to 2.6.0

In `ai-post-scheduler/ai-post-scheduler.php`:

- Change `AIPS_VERSION` constant from `'2.5.0'` to `'2.6.0'`
- Also update the `Version:` header comment

In `CHANGELOG.md` (check `ai-post-scheduler/CHANGELOG.md` or repo root), add a new `## [2.6.0]` section at the top documenting the hierarchical history feature.

---

### Phase 1d: Repository Changes

In `class-aips-history-repository.php`:

**1. Update `create()` method** — add `parent_id` to `$insert_data`:

```php
'parent_id' => isset($data['parent_id']) && $data['parent_id'] ? absint($data['parent_id']) : null,
```

Add `'%d'` to the `$format` array (making it 14 items total).

**2. Add `get_children($parent_id)`:**

```php
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
```

**3. Add `get_top_level($args = array())`:**

Returns only containers where `parent_id IS NULL`. Does **NOT** filter out `schedule_lifecycle` containers. Supports `operation_type` filter. Returns same shape as `get_history()`.

```php
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
```

**4. Add `get_child_summary($parent_id)`:**

```php
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
```

**5. Preserve `get_history()` as-is** — keep `"COALESCE(h.creation_method, '') <> 'schedule_lifecycle'"` for backward compatibility of the flat view.

---

### Phase 1e: History Container Changes

In `class-aips-history-container.php`:

**1. Update `persist()` method** — after building `$data`, inherit `parent_id` from the ambient correlation context if not already set:

```php
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
```

**2. Add private helper `_is_parent_type($data)`:**

```php
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
```

**3. Add `create_child($type, $metadata = array())` method** after `get_session()`:

```php
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
```

---

## Phase 2 — Operation Type Taxonomy

**Create new file** `ai-post-scheduler/includes/class-aips-history-operation-type.php`:

```php
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
```

---

## Phase 3a — Wire Correlation ID Manager with Parent History ID

### Add to `class-aips-correlation-id.php`

After the existing `private static $current_id = null;`, add:

```php
/** @var int|null Active parent history ID for the current run. */
private static $parent_history_id = null;
```

Add these two methods after `reset()`:

```php
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
```

Update the existing `reset()` method to also clear `parent_history_id`:

```php
public static function reset() {
    self::$current_id        = null;
    self::$parent_history_id = null;
}
```

---

### Wire `schedule_lifecycle` Parent in `class-aips-schedule-processor.php`

In `execute_schedule_logic()`, after getting/creating the `$history` container (via `$this->result_handler->get_or_create_schedule_history(...)`), add:

```php
// Register this lifecycle container as the ambient parent so all
// child generation containers created during this run inherit parent_id.
if ($history && $history->get_id()) {
    AIPS_Correlation_ID::set_parent_history_id($history->get_id());
}
```

At the end of `execute_schedule_logic()` (before the return), reset it:

```php
AIPS_Correlation_ID::set_parent_history_id(null);
```

> **Note:** `process_single_schedule()` already has `finally { AIPS_Correlation_ID::reset(); }` which now also resets `parent_history_id`, so this cleanup is doubly safe.

For robustness, wrap the generation loop in try/finally:

```php
try {
    // ... existing generation loop and result handling ...
} finally {
    AIPS_Correlation_ID::set_parent_history_id(null);
}
```

**Read `class-aips-schedule-processor.php` carefully before editing** to find the exact injection point.

---

## Phase 3b — Author Topics: `TOPIC_GENERATION_BATCH` Parent

### In `class-aips-author-topics-scheduler.php`

Replace the existing `process_topic_generation()` with:

```php
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
```

> **Important:** `AIPS_Correlation_ID::reset()` (called in the inner finally) now clears `parent_history_id` too. The re-set call after reset ensures subsequent authors still receive the correct `parent_id`.

---

### In `class-aips-author-topics-controller.php`

**Refactor `_do_bulk_generate_topics()`** to return a result array instead of calling `AIPS_Ajax_Response` directly (Option C — cleanest approach):

```php
/**
 * Shared bulk generation driver. Returns a result array instead of
 * sending the AJAX response directly so callers can complete the parent
 * history container before responding.
 *
 * @param int[]  $topic_ids Sanitized topic IDs to process.
 * @param array  $options   Options forwarded to AIPS_Bulk_Generator_Service::run().
 * @return array {success: bool, was_limited: bool, message: string, success_count: int, failed_count: int, errors: array}
 */
private function _do_bulk_generate_topics( array $topic_ids, array $options ) {
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
```

**Update `ajax_bulk_generate_from_queue()`:**

```php
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
```

**Update `ajax_bulk_generate_topics()` similarly:**

```php
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
```

---

## Phase 3e — Author Post Generator: `POST_GENERATION_BATCH` Parent

In `class-aips-author-post-generator.php`, in the `process()` method (around line 109), after getting `$due_authors` and confirming it's not empty, replace the generation loop with:

```php
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
```

> **Read the actual file first** to confirm the exact method name and loop structure.

---

## Phase 4 — History Page: Grouped Hierarchical View

### Phase 4a: New AJAX Endpoints in `class-aips-history.php`

**In `__construct()`**, add:

```php
add_action('wp_ajax_aips_get_history_top_level', array($this, 'ajax_get_history_top_level'));
add_action('wp_ajax_aips_get_operation_children', array($this, 'ajax_get_operation_children'));
```

**Add `ajax_get_history_top_level()`:**

```php
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
```

**Add `ajax_get_operation_children()`:**

```php
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
```

**Also update `ajax_get_history_logs()`** — add parent/child metadata to response and fix duration calculation:

```php
// Before AIPS_Ajax_Response::success(), add:
$child_summary = $this->repository->get_child_summary($history_id);
$is_parent     = $child_summary->total > 0;
$operation_label = AIPS_History_Operation_Type::get_label(
    isset($history_item->creation_method) ? $history_item->creation_method : ''
);

// Fix duration calculation (timestamps are bigints, not date strings):
$duration_seconds = null;
if (!empty($history_item->created_at) && !empty($history_item->completed_at)) {
    $start = (int) $history_item->created_at;
    $end   = (int) $history_item->completed_at;
    if ($start > 0 && $end > 0 && $end >= $start) {
        $duration_seconds = $end - $start;
    }
}

// Add to AIPS_Ajax_Response::success() array:
'is_parent'       => $is_parent,
'child_summary'   => $child_summary,
'operation_label' => $operation_label,
```

---

### Phase 4b: New Partial — `templates/partials/history-parent-row.php`

Create this file. Variables available: `$item` (stdObject, top-level row) and `$child_summary` (stdObject, from `get_child_summary()`).

```php
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
		$duration_text = $diff >= 60 ? sprintf('%dm %ds', floor($diff / 60), $diff % 60) : sprintf('%ds', $diff);
	}
} elseif (!empty($item->created_at) && !empty($item->completed_at)) {
	$diff = (int) $item->completed_at - (int) $item->created_at;
	if ($diff > 0) {
		$duration_text = $diff >= 60 ? sprintf('%dm %ds', floor($diff / 60), $diff % 60) : sprintf('%ds', $diff);
	}
}

// Use date_i18n as safe fallback if AIPS_DateTime::from_timestamp() is unavailable.
$formatted_date = !empty($item->created_at)
	? esc_html(date_i18n('M j, Y g:i a', (int) $item->created_at))
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
			<span class="aips-badge <?php echo ($failed > 0) ? 'aips-badge-error' : 'aips-badge-info'; ?>"
			      title="<?php echo esc_attr(sprintf('%d completed, %d failed', $completed, $failed)); ?>">
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
```

---

### Phase 4c: New Partial — `templates/partials/history-child-row.php`

```php
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
		$duration_text = $diff >= 60 ? sprintf('%dm %ds', floor($diff / 60), $diff % 60) : sprintf('%ds', $diff);
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
```

---

### Phase 4d: Update `templates/admin/history.php`

**Read the file first**, then make these minimal additions:

1. Add a **View Mode toggle** above the filter bar with two buttons: "Operations View" (default) and "All Items". Use `data-view-mode` attribute and class `aips-view-mode-btn`.
2. Add an **Operation Type** `<select>` filter to the filter bar (visible only in operations mode), populated with `AIPS_History_Operation_Type::get_all_types()` plus an "All Types" option.
3. Add a second `<thead>` and `<tbody id="aips-history-operations-tbody">` for the operations view with columns: Operation | Children | Status | Triggered | Duration | Actions.
4. Keep the existing `<tbody id="aips-history-tbody">` for the flat "All Items" view.
5. The operations tbody is shown by default; the flat tbody is hidden by default.
6. Add `<script type="text/html">` templates at the bottom for JS rendering:
   - `aips-tmpl-history-parent-row`
   - `aips-tmpl-history-children-container`
   - `aips-tmpl-history-child-row`

---

### Phase 4e: Update `assets/js/admin-history.js`

**Read the file first**, then add to the `AIPS.History` module:

1. `currentViewMode = 'operations'` property (default)
2. `switchViewMode(mode)` — toggles UI between 'operations' and 'all', updates `.aips-view-mode-btn` active states, calls appropriate load method
3. `loadOperationsView(page)` — AJAX to `aips_get_history_top_level` with current filters, renders items into `#aips-history-operations-tbody`
4. `loadAllView(page)` — existing reload logic, targets `#aips-history-tbody`
5. `toggleOperationChildren(id)` — if children row already exists, toggle it off; otherwise AJAX to `aips_get_operation_children`, insert a `<tr class="aips-children-row">` immediately after the parent row with a nested table inside
6. In `bindEvents()`, add handlers for:
   - `.aips-view-mode-btn` click → `switchViewMode()`
   - `.aips-view-operation-children` click → `toggleOperationChildren()`
   - Operation Type filter change → `loadOperationsView(1)`
7. In `init()`, default to operations view, call `loadOperationsView(1)`

For timestamp formatting client-side, use `new Date(timestamp * 1000).toLocaleString()` or equivalent.

---

## Phase 5 — History Log Modal: Tabs + Summary

### Phase 5a: Update `ajax_get_history_logs()`

Already covered in Phase 4a above. Add `is_parent`, `child_summary`, and `operation_label` to the response, and fix the duration calculation to use numeric timestamps.

### Phase 5b/5c: Update History Log Modal

In `templates/admin/history.php`, replace the current single-content modal body with a tabbed structure using `<script type="text/html">` templates:

**`aips-tmpl-history-modal-tabs`:**
```html
<div class="aips-history-modal-tabs">
  <button class="aips-tab-link active" data-tab="summary">Summary</button>
  <button class="aips-tab-link" data-tab="timeline">Timeline</button>
  <button class="aips-tab-link" data-tab="raw-logs">Raw Logs</button>
</div>
<div class="aips-tab-content" id="aips-history-tab-summary">{{summaryHtml}}</div>
<div class="aips-tab-content" id="aips-history-tab-timeline" style="display:none;">{{timelineHtml}}</div>
<div class="aips-tab-content" id="aips-history-tab-raw-logs" style="display:none;">{{rawLogsHtml}}</div>
```

**`aips-tmpl-history-summary-cards`** — for parent containers, show stat cards (total, completed, failed, processing).

**`aips-tmpl-history-timeline-item`:**
```html
<div class="aips-timeline-item {{statusClass}}">
  <div class="aips-timeline-marker"></div>
  <div class="aips-timeline-content">
    <strong>{{title}}</strong>
    <span class="aips-badge {{statusClass}}">{{status}}</span>
    <span class="aips-meta-text">{{timestamp}}</span>
    <button class="aips-btn aips-btn-sm aips-btn-ghost aips-view-history-logs" data-id="{{id}}">View Logs</button>
  </div>
</div>
```

In `admin-history.js`, update modal rendering to:
1. Build three sections: summary (stat cards if parent), timeline (children list), raw logs (existing table)
2. Handle tab switching within the modal
3. Add `aips-log-row-error` class to rows where `history_type_id === 2` (ERROR)
4. Add `aips-log-row-warning` class to rows where `history_type_id === 3` (WARNING)
5. Add "Errors only" filter button

### Phase 5d: Update `assets/css/admin.css`

Append at the end of the file:

```css
/* ---- History v2: Hierarchical view ---- */
.aips-history-modal-tabs { display: flex; gap: 4px; border-bottom: 1px solid #ddd; margin-bottom: 12px; }
.aips-tab-link { background: none; border: none; padding: 8px 14px; cursor: pointer; color: #666; }
.aips-tab-link.active { border-bottom: 2px solid #2271b1; color: #2271b1; font-weight: 600; }

.aips-history-summary-cards { display: flex; gap: 12px; flex-wrap: wrap; margin-bottom: 16px; }
.aips-summary-card { background: #f9f9f9; border: 1px solid #e0e0e0; border-radius: 4px; padding: 12px 20px; text-align: center; min-width: 90px; }
.aips-summary-card-value { display: block; font-size: 1.8em; font-weight: 700; line-height: 1.1; }
.aips-summary-card-label { display: block; font-size: 0.8em; color: #666; margin-top: 2px; }

.aips-timeline-item { display: flex; gap: 10px; padding: 8px 0; border-left: 2px solid #ddd; margin-left: 12px; padding-left: 14px; }
.aips-timeline-marker { width: 10px; height: 10px; border-radius: 50%; background: #aaa; margin-left: -19px; margin-top: 4px; flex-shrink: 0; }
.aips-timeline-item.aips-timeline-completed { border-left-color: #46b450; }
.aips-timeline-item.aips-timeline-completed .aips-timeline-marker { background: #46b450; }
.aips-timeline-item.aips-timeline-failed { border-left-color: #dc3232; }
.aips-timeline-item.aips-timeline-failed .aips-timeline-marker { background: #dc3232; }
.aips-timeline-content { flex: 1; }

.aips-log-row-error td { background: #fef2f2; }
.aips-log-row-warning td { background: #fffbeb; }

.aips-children-row td { padding-top: 0; padding-bottom: 0; }
.aips-view-mode-btn.active { background: #2271b1; color: #fff; border-color: #2271b1; }
.aips-history-parent-row { background: #f9f9f9; }
.cell-indent { width: 30px; padding-left: 30px !important; }
```

---

## Phase 7 — Tests

### Create `ai-post-scheduler/tests/test-history-parent-child.php`

```php
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
        $this->service    = new AIPS_History_Service($this->repository);
    }

    public function tearDown(): void {
        global $wpdb;
        $wpdb->query("DELETE FROM {$wpdb->prefix}aips_history WHERE generated_title LIKE 'TEST_%'");
        parent::tearDown();
    }

    /** Test that create() persists parent_id when provided */
    public function test_create_persists_parent_id() { /* ... */ }

    /** Test create_child() sets parent_id to parent's ID */
    public function test_create_child_sets_parent_id() { /* ... */ }

    /** Test create_child() inherits correlation_id from parent */
    public function test_create_child_inherits_correlation_id() { /* ... */ }

    /** Test get_top_level() excludes rows with parent_id set */
    public function test_get_top_level_excludes_children() { /* ... */ }

    /** Test get_top_level() includes schedule_lifecycle containers */
    public function test_get_top_level_includes_schedule_lifecycle() { /* ... */ }

    /** Test get_children() returns only direct children ordered by created_at ASC */
    public function test_get_children_returns_direct_children() { /* ... */ }

    /** Test get_children() returns empty array for leaf/childless container */
    public function test_get_children_returns_empty_for_leaf() { /* ... */ }

    /** Test get_child_summary() returns correct aggregates */
    public function test_get_child_summary_correct_aggregates() { /* ... */ }

    /** Test get_child_summary() returns zeroes for container with no children */
    public function test_get_child_summary_returns_zeroes_for_no_children() { /* ... */ }
}
```

Use `$wpdb->insert()` to create test fixtures directly. Look at existing test files (e.g., `tests/test-history-repository.php`) for exact pattern. Use `'TEST_'` prefix in `generated_title` for cleanup.

---

### Create `ai-post-scheduler/tests/test-history-operation-type.php`

```php
<?php
class Test_History_Operation_Type extends WP_UnitTestCase {
    /** Test all constants are non-empty strings */
    public function test_constants_are_non_empty_strings() { /* ... */ }

    /** Test get_label returns non-empty string for all types */
    public function test_get_label_returns_label_for_all_types() { /* ... */ }

    /** Test get_all_types returns array with all 7 types */
    public function test_get_all_types_returns_all_types() { /* ... */ }

    /** Test is_parent_type returns true for SCHEDULE_RUN, TOPIC_GENERATION_BATCH, POST_GENERATION_BATCH */
    public function test_is_parent_type_for_parent_types() { /* ... */ }

    /** Test is_parent_type returns false for child types */
    public function test_is_parent_type_false_for_child_types() { /* ... */ }

    /** Test get_label returns a fallback (not empty) for unknown type */
    public function test_get_label_fallback_for_unknown_type() { /* ... */ }
}
```

---

## Implementation Order

Execute changes in this sequence:

1. Create `class-aips-history-operation-type.php` (new file — no dependencies)
2. Update `ai-post-scheduler.php` (version bump to 2.6.0)
3. Update `class-aips-db-manager.php` (schema: add `parent_id`, extend `creation_method`)
4. Update `class-aips-upgrades.php` (add `migrate_to_2_6_0()`)
5. Update `class-aips-correlation-id.php` (add `set_parent_history_id` / `get_parent_history_id`, update `reset()`)
6. Update `class-aips-history-repository.php` (add `get_children`, `get_top_level`, `get_child_summary`; update `create()`)
7. Update `class-aips-history-container.php` (update `persist()`, add `_is_parent_type()`, add `create_child()`)
8. Update `class-aips-history.php` (new AJAX handlers, update `ajax_get_history_logs()`)
9. Update `class-aips-author-topics-scheduler.php` (batch parent in `process_topic_generation()`)
10. Update `class-aips-author-topics-controller.php` (refactor `_do_bulk_generate_topics()`, wrap AJAX handlers)
11. Update `class-aips-author-post-generator.php` (batch parent in `process()`)
12. Update `class-aips-schedule-processor.php` (set `parent_history_id` in `execute_schedule_logic()`)
13. Create `templates/partials/history-parent-row.php`
14. Create `templates/partials/history-child-row.php`
15. Update `templates/admin/history.php`
16. Update `assets/js/admin-history.js`
17. Update `assets/css/admin.css`
18. Update `CHANGELOG.md`
19. Create test files

---

## Deliverables Summary

### Files to Create

| File | Purpose |
|------|---------|
| `ai-post-scheduler/includes/class-aips-history-operation-type.php` | Operation type constants + helpers |
| `ai-post-scheduler/templates/partials/history-parent-row.php` | Parent row template partial |
| `ai-post-scheduler/templates/partials/history-child-row.php` | Child row template partial |
| `ai-post-scheduler/tests/test-history-parent-child.php` | Repository hierarchy tests |
| `ai-post-scheduler/tests/test-history-operation-type.php` | Operation type class tests |

### Files to Modify

| File | What Changes |
|------|-------------|
| `ai-post-scheduler/ai-post-scheduler.php` | Version bump to 2.6.0 |
| `ai-post-scheduler/includes/class-aips-db-manager.php` | Add `parent_id` column + indexes; extend `creation_method` to `varchar(50)` |
| `ai-post-scheduler/includes/class-aips-upgrades.php` | Add `migrate_to_2_6_0()` |
| `ai-post-scheduler/includes/class-aips-history-repository.php` | Add `get_children()`, `get_top_level()`, `get_child_summary()`; update `create()` |
| `ai-post-scheduler/includes/class-aips-history-container.php` | Update `persist()`; add `_is_parent_type()`; add `create_child()` |
| `ai-post-scheduler/includes/class-aips-correlation-id.php` | Add parent history ID methods; update `reset()` |
| `ai-post-scheduler/includes/class-aips-history.php` | Add AJAX handlers; update `ajax_get_history_logs()` |
| `ai-post-scheduler/includes/class-aips-author-topics-scheduler.php` | Add `TOPIC_GENERATION_BATCH` parent container |
| `ai-post-scheduler/includes/class-aips-author-topics-controller.php` | Refactor `_do_bulk_generate_topics()`; add parent containers |
| `ai-post-scheduler/includes/class-aips-author-post-generator.php` | Add `POST_GENERATION_BATCH` parent container |
| `ai-post-scheduler/includes/class-aips-schedule-processor.php` | Set `parent_history_id` when lifecycle container is created |
| `ai-post-scheduler/templates/admin/history.php` | View mode toggle, operation type filter, operations tbody, JS templates |
| `ai-post-scheduler/assets/js/admin-history.js` | Operations view, `toggleOperationChildren()`, modal tabs, severity colouring |
| `ai-post-scheduler/assets/css/admin.css` | Timeline, card, tab, child-row, parent-row CSS |
| `ai-post-scheduler/CHANGELOG.md` | Add 2.6.0 entry |

---

## Important Implementation Notes

1. **Read files before editing** — use the view tool for any file you need to change to get exact line numbers and avoid breaking existing code.

2. **Do not break existing tests** — `get_history()` is used by existing tests and must not be changed in a breaking way. The flat history view must continue to work.

3. **`varchar(20)` truncation is critical** — `creation_method` must be extended to `varchar(50)` in both the schema definition and the migration. Strings like `topic_generation_batch` (22 chars) will silently truncate without this fix.

4. **`AIPS_Correlation_ID::reset()` now clears both fields** — all scheduler/cron classes that call `reset()` will automatically clear `parent_history_id` too. The per-author and per-post loops re-set `parent_history_id` after each inner `reset()` call.

5. **History page default view** — the default should be `'operations'` (the new hierarchical view). The "All Items" flat view stays available via the toggle.

6. **`AIPS_DateTime::from_timestamp()` availability** — if not available in templates, fall back to `date_i18n('M j, Y g:i a', (int) $item->created_at)`.

7. **`_do_bulk_generate_topics()` refactor** — since `AIPS_Ajax_Response::success/error` calls `wp_die()`, cleanup code after those calls never runs. The refactor to return a result array (Option C) is the only clean approach.

8. **Run tests after implementation:**
   ```bash
   cd ai-post-scheduler && composer install --no-interaction -q
   vendor/bin/phpunit tests/test-history-operation-type.php tests/test-history-parent-child.php 2>&1 | tail -30
   vendor/bin/phpunit 2>&1 | tail -30
   ```
