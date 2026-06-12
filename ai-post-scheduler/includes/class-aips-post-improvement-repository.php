<?php
/**
 * Post Improvement Repository.
 *
 * Data access layer for post improvement schedules, scans, suggestions, and suggestion items.
 * Manages persistence for the existing post improvement workflow including schedule management,
 * scan execution tracking, suggestion creation, and item-level decision tracking.
 *
 * @package AI_Post_Scheduler
 * @since 2.10.0
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Post_Improvement_Repository
 *
 * Handles all database operations for the post improvement feature.
 */
class AIPS_Post_Improvement_Repository {

	/**
	 * Schedule type identifier for existing post scan schedules.
	 *
	 * @var string
	 * @since 2.10.0
	 */
	public const EXISTING_POST_SCAN_SCHEDULE_TYPE = 'existing_post_scan';

	/**
	 * WordPress database object.
	 *
	 * @var wpdb
	 * @since 2.10.0
	 */
	private $wpdb;

	/**
	 * Schedules table name (shared with template schedules).
	 *
	 * @var string
	 * @since 2.10.0
	 */
	private $table_schedules;

	/**
	 * Scan runs table name.
	 *
	 * @var string
	 * @since 2.10.0
	 */
	private $table_runs;

	/**
	 * Suggestions table name (post-level suggestions).
	 *
	 * @var string
	 * @since 2.10.0
	 */
	private $table_suggestions;

	/**
	 * Suggestion items table name (component-level suggestions).
	 *
	 * @var string
	 * @since 2.10.0
	 */
	private $table_items;

	/**
	 * History table name (for plugin-generated post detection).
	 *
	 * @var string
	 * @since 2.10.0
	 */
	private $table_history;

	/**
	 * Initialize repository and set table names.
	 *
	 * @since 2.10.0
	 */
	public function __construct() {
		global $wpdb;

		$this->wpdb              = $wpdb;
		$this->table_schedules   = $wpdb->prefix . 'aips_schedule';
		$this->table_runs        = $wpdb->prefix . 'aips_existing_post_scan_runs';
		$this->table_suggestions = $wpdb->prefix . 'aips_existing_post_suggestions';
		$this->table_items       = $wpdb->prefix . 'aips_existing_post_suggestion_items';
		$this->table_history     = $wpdb->prefix . 'aips_history';
	}

	/**
	 * Create a new scan schedule.
	 *
	 * @param array $data Schedule configuration with keys: title, description, frequency, status,
	 *                    category_filters, include_generated_posts, next_run, last_run.
	 *
	 * @return int The new schedule ID, or 0 on failure.
	 * @since 2.10.0
	 */
	public function create_schedule($data) {
		$now = time();

		// Build run state with category filters and scan configuration
		$state = array(
			'category_filters'        => isset($data['category_filters']) ? array_values(array_map('absint', (array) $data['category_filters'])) : array(),
			'include_generated_posts' => !empty($data['include_generated_posts']) ? 1 : 0,
			'lock_token'              => '',
			'lock_expires_at'         => 0,
			'run_cursor'              => 0,
			'retry_count'             => 0,
			'last_error'              => '',
		);

		// Build schedule row with default values
		$row = array(
			'template_id'            => 0,
			'title'                  => isset($data['title']) ? sanitize_text_field($data['title']) : '',
			'topic'                  => isset($data['description']) ? sanitize_textarea_field($data['description']) : '',
			'frequency'              => isset($data['frequency']) ? sanitize_key($data['frequency']) : 'daily',
			'status'                 => isset($data['status']) ? sanitize_key($data['status']) : 'active',
			'is_active'              => (!isset($data['status']) || 'active' === sanitize_key($data['status'])) ? 1 : 0,
			'schedule_type'          => self::EXISTING_POST_SCAN_SCHEDULE_TYPE,
			'next_run'               => isset($data['next_run']) ? absint($data['next_run']) : 0,
			'last_run'               => isset($data['last_run']) ? absint($data['last_run']) : 0,
			'run_state'              => wp_json_encode($state),
			'created_at'             => $now,
		);

		$inserted = $this->wpdb->insert($this->table_schedules, $row, array('%d','%s','%s','%s','%s','%d','%s','%d','%d','%s','%d'));
		if (false === $inserted) {
			return 0;
		}

		return (int) $this->wpdb->insert_id;
	}

	/**
	 * Update an existing scan schedule.
	 *
	 * Supports partial updates - only provided fields are updated.
	 * Updates both top-level fields and run_state JSON fields.
	 *
	 * @param int   $id   Schedule ID.
	 * @param array $data Fields to update.
	 *
	 * @return bool True on success, false on failure.
	 * @since 2.10.0
	 */
	public function update_schedule($id, $data) {
		$id = absint($id);
		if (!$id) {
			return false;
		}

		$existing = $this->get_schedule_by_id($id);
		if (!$existing) {
			return false;
		}

		$fields = array();
		$formats = array();

		// Map of simple top-level fields
		$map = array(
			'title'     => '%s',
			'topic'     => '%s',
			'frequency' => '%s',
			'status'    => '%s',
			'next_run'  => '%d',
			'last_run'  => '%d',
		);

		// Process simple top-level fields
		foreach ($map as $key => $format) {
			if ('topic' === $key && !array_key_exists('description', $data)) {
				continue;
			}
			if ('topic' !== $key && !array_key_exists($key, $data)) {
				continue;
			}

			$value = 'topic' === $key ? $data['description'] : $data[$key];

			if ('%d' === $format) {
				$value = absint($value);
			} elseif (in_array($key, array('status', 'frequency'), true)) {
				$value = sanitize_key((string) $value);
			} else {
				$value = 'topic' === $key ? sanitize_textarea_field((string) $value) : sanitize_text_field((string) $value);
			}

			$fields[$key] = $value;
			$formats[] = $format;
		}

		// Sync is_active with status
		if (array_key_exists('status', $data)) {
			$fields['is_active'] = ('active' === sanitize_key((string) $data['status'])) ? 1 : 0;
			$formats[] = '%d';
		}

		// Handle run_state updates
		$state = $this->decode_schedule_state(isset($existing->run_state) ? $existing->run_state : '');

		if (array_key_exists('category_filters', $data)) {
			$state['category_filters'] = array_values(array_map('absint', (array) $data['category_filters']));
		}
		if (array_key_exists('include_generated_posts', $data)) {
			$state['include_generated_posts'] = !empty($data['include_generated_posts']) ? 1 : 0;
		}
		if (array_key_exists('lock_token', $data)) {
			$state['lock_token'] = sanitize_text_field((string) $data['lock_token']);
		}
		if (array_key_exists('lock_expires_at', $data)) {
			$state['lock_expires_at'] = absint($data['lock_expires_at']);
		}
		if (array_key_exists('run_cursor', $data)) {
			$state['run_cursor'] = absint($data['run_cursor']);
		}
		if (array_key_exists('retry_count', $data)) {
			$state['retry_count'] = absint($data['retry_count']);
		}
		if (array_key_exists('last_error', $data)) {
			$state['last_error'] = sanitize_textarea_field((string) $data['last_error']);
		}

		if (!empty($state)) {
			$fields['run_state'] = wp_json_encode($state);
			$formats[] = '%s';
		}

		if (empty($fields)) {
			return false;
		}

		$result = $this->wpdb->update($this->table_schedules, $fields, array('id' => $id, 'schedule_type' => self::EXISTING_POST_SCAN_SCHEDULE_TYPE), $formats, array('%d', '%s'));
		return false !== $result;
	}

	/**
	 * Get a schedule by ID.
	 *
	 * @param int $id Schedule ID.
	 *
	 * @return object|null Hydrated schedule object or null if not found.
	 * @since 2.10.0
	 */
	public function get_schedule_by_id($id) {
		$id = absint($id);
		if (!$id) {
			return null;
		}

		$schedule = $this->wpdb->get_row($this->wpdb->prepare("SELECT * FROM {$this->table_schedules} WHERE id = %d AND schedule_type = %s", $id, self::EXISTING_POST_SCAN_SCHEDULE_TYPE));
		if (!$schedule) {
			return null;
		}

		return $this->hydrate_schedule($schedule);
	}

	/**
	 * Get all schedules, optionally filtered by status.
	 *
	 * @param string $status Optional status filter (active, paused, etc.).
	 *
	 * @return array Array of hydrated schedule objects.
	 * @since 2.10.0
	 */
	public function get_schedules($status = '') {
		if (!empty($status)) {
			$schedules = $this->wpdb->get_results($this->wpdb->prepare("SELECT * FROM {$this->table_schedules} WHERE schedule_type = %s AND status = %s ORDER BY next_run ASC, id DESC", self::EXISTING_POST_SCAN_SCHEDULE_TYPE, sanitize_key($status)));
			return array_map(array($this, 'hydrate_schedule'), $schedules);
		}

		$schedules = $this->wpdb->get_results($this->wpdb->prepare("SELECT * FROM {$this->table_schedules} WHERE schedule_type = %s ORDER BY next_run ASC, id DESC", self::EXISTING_POST_SCAN_SCHEDULE_TYPE));
		return array_map(array($this, 'hydrate_schedule'), $schedules);
	}

	/**
	 * Get schedules that are due to run now and not currently locked.
	 *
	 * Uses JSON_EXTRACT to check lock status within run_state field.
	 * Only returns active schedules with next_run <= $now and either no lock or expired lock.
	 *
	 * @param int|null $now   Optional timestamp to check against, defaults to current time.
	 * @param int      $limit Maximum number of schedules to return.
	 *
	 * @return array Array of hydrated schedule objects.
	 * @since 2.10.0
	 */
	public function get_due_schedules($now = null, $limit = 10) {
		$now = null === $now ? time() : absint($now);
		$limit = max(1, absint($limit));

		// Complex SQL to check lock expiration inside JSON field
		$schedules = $this->wpdb->get_results($this->wpdb->prepare(
			"SELECT * FROM {$this->table_schedules}
			WHERE schedule_type = %s
			AND status = %s
			AND next_run > 0
			AND next_run <= %d
			AND (
				COALESCE(CAST(JSON_UNQUOTE(JSON_EXTRACT(CASE WHEN run_state IS NULL OR run_state = '' THEN '{}' ELSE run_state END, '$.lock_expires_at')) AS UNSIGNED), 0) = 0
				OR COALESCE(CAST(JSON_UNQUOTE(JSON_EXTRACT(CASE WHEN run_state IS NULL OR run_state = '' THEN '{}' ELSE run_state END, '$.lock_expires_at')) AS UNSIGNED), 0) < %d
			)
			ORDER BY next_run ASC
			LIMIT %d",
			self::EXISTING_POST_SCAN_SCHEDULE_TYPE, 'active', $now, $now, $limit
		));

		return array_map(array($this, 'hydrate_schedule'), $schedules);
	}

	/**
	 * Acquire a schedule lock to prevent concurrent execution.
	 *
	 * @param int    $schedule_id Schedule ID.
	 * @param string $token       Unique lock token.
	 * @param int    $ttl         Lock time-to-live in seconds (default 300).
	 *
	 * @return bool True if lock acquired, false if schedule not found or already locked.
	 * @since 2.10.0
	 */
	public function acquire_schedule_lock($schedule_id, $token, $ttl = 300) {
		$schedule_id = absint($schedule_id);
		$token = sanitize_text_field($token);
		$ttl = max(30, absint($ttl));

		$schedule = $this->get_schedule_by_id($schedule_id);
		if (!$schedule) {
			return false;
		}

		$now = time();

		// Check if lock is already held and not expired
		if (!empty($schedule->lock_expires_at) && (int) $schedule->lock_expires_at >= $now) {
			return false;
		}

		return $this->update_schedule($schedule_id, array(
			'lock_token'      => $token,
			'lock_expires_at' => $now + $ttl,
		));
	}

	/**
	 * Release a schedule lock.
	 *
	 * @param int    $schedule_id Schedule ID.
	 * @param string $token       Optional lock token for verification.
	 *
	 * @return bool True on success, false on failure.
	 * @since 2.10.0
	 */
	public function release_schedule_lock($schedule_id, $token = '') {
		$schedule_id = absint($schedule_id);
		if ($schedule_id <= 0) {
			return false;
		}

		// Verify token if provided
		if (!empty($token)) {
			$schedule = $this->get_schedule_by_id($schedule_id);
			if (!$schedule || !isset($schedule->lock_token) || !hash_equals((string) $schedule->lock_token, $token)) {
				return false;
			}
		}

		return $this->update_schedule($schedule_id, array(
			'lock_token'      => '',
			'lock_expires_at' => 0,
		));
	}

	/**
	 * Delete a schedule by ID.
	 *
	 * @param int $id Schedule ID.
	 *
	 * @return bool True on success, false on failure.
	 * @since 2.10.0
	 */
	public function delete_schedule($id) {
		return false !== $this->wpdb->delete($this->table_schedules, array('id' => absint($id), 'schedule_type' => self::EXISTING_POST_SCAN_SCHEDULE_TYPE), array('%d', '%s'));
	}

	/**
	 * Create a new scan run record.
	 *
	 * @param int    $schedule_id Schedule ID this run belongs to.
	 * @param string $status      Initial run status (default 'running').
	 * @param string $started_by  Who triggered the run: 'cron' or 'manual'.
	 *
	 * @return int The new run ID.
	 * @since 2.10.0
	 */
	public function create_run($schedule_id, $status = 'running', $started_by = 'cron') {
		$now = time();

		$this->wpdb->insert($this->table_runs, array(
			'schedule_id'                => absint($schedule_id),
			'status'                     => sanitize_key($status),
			'posts_scanned'              => 0,
			'suggestions_created'        => 0,
			'suggestions_applied'        => 0,
			'posts_skipped_unchanged'    => 0,
			'failures_count'             => 0,
			'error_summary'              => '',
			'ai_trace'                   => '',
			'started_by'                 => sanitize_key($started_by),
			'started_at'                 => $now,
			'completed_at'               => 0,
			'created_at'                 => $now,
			'updated_at'                 => $now,
		), array('%d','%s','%d','%d','%d','%d','%d','%s','%s','%s','%d','%d','%d','%d'));

		return (int) $this->wpdb->insert_id;
	}

	/**
	 * Update an existing run record with metrics or status.
	 *
	 * @param int   $run_id Run ID.
	 * @param array $data   Fields to update (status, metrics, error_summary, completed_at, etc.).
	 *
	 * @return bool True on success, false on failure.
	 * @since 2.10.0
	 */
	public function update_run($run_id, $data) {
		$run_id = absint($run_id);
		if (!$run_id) {
			return false;
		}

		$fields = array('updated_at' => time());
		$formats = array('%d');

		foreach (array('status' => '%s','posts_scanned' => '%d','suggestions_created' => '%d','suggestions_applied' => '%d','posts_skipped_unchanged' => '%d','failures_count' => '%d','error_summary' => '%s','ai_trace' => '%s','completed_at' => '%d') as $key => $format) {
			if (!array_key_exists($key, $data)) {
				continue;
			}

			$value = $data[$key];

			if ('%d' === $format) {
				$value = absint($value);
			} elseif ('status' === $key) {
				$value = sanitize_key((string) $value);
			} else {
				$value = sanitize_textarea_field((string) $value);
			}

			$fields[$key] = $value;
			$formats[] = $format;
		}

		return false !== $this->wpdb->update($this->table_runs, $fields, array('id' => $run_id), $formats, array('%d'));
	}

	/**
	 * Get recent run history for a schedule.
	 *
	 * @param int $schedule_id Schedule ID.
	 * @param int $limit       Maximum number of runs to return (default 20).
	 *
	 * @return array Array of run objects ordered by started_at DESC.
	 * @since 2.10.0
	 */
	public function get_recent_runs_by_schedule($schedule_id, $limit = 20) {
		return $this->wpdb->get_results($this->wpdb->prepare(
			"SELECT * FROM {$this->table_runs} WHERE schedule_id = %d ORDER BY started_at DESC LIMIT %d",
			absint($schedule_id), max(1, absint($limit))
		));
	}

	/**
	 * Get the latest pending or reviewed suggestion for a post.
	 *
	 * @param int $post_id Post ID.
	 *
	 * @return object|null Suggestion object or null if none found.
	 * @since 2.10.0
	 */
	public function get_latest_pending_suggestion_for_post($post_id) {
		return $this->wpdb->get_row($this->wpdb->prepare(
			"SELECT * FROM {$this->table_suggestions} WHERE post_id = %d AND status IN ('pending','reviewed') ORDER BY created_at DESC LIMIT 1",
			absint($post_id)
		));
	}

	/**
	 * Create a new suggestion for a post.
	 *
	 * @param array $data Suggestion data with keys: post_id, run_id, schedule_id, status, priority,
	 *                    severity, content_hash, freshness_marker, last_scanned_at, metadata.
	 *
	 * @return int The new suggestion ID.
	 * @since 2.10.0
	 */
	public function create_suggestion($data) {
		$now = time();

		$this->wpdb->insert($this->table_suggestions, array(
			'post_id'                 => absint($data['post_id']),
			'run_id'                  => absint($data['run_id']),
			'schedule_id'             => absint($data['schedule_id']),
			'status'                  => isset($data['status']) ? sanitize_key($data['status']) : 'pending',
			'priority'                => isset($data['priority']) ? sanitize_key($data['priority']) : 'medium',
			'severity'                => isset($data['severity']) ? sanitize_key($data['severity']) : 'medium',
			'content_hash'            => isset($data['content_hash']) ? sanitize_text_field($data['content_hash']) : '',
			'freshness_marker'        => isset($data['freshness_marker']) ? sanitize_text_field($data['freshness_marker']) : '',
			'last_scanned_at'         => isset($data['last_scanned_at']) ? absint($data['last_scanned_at']) : $now,
			'applied_items_count'     => 0,
			'dismissed_items_count'   => 0,
			'metadata'                => isset($data['metadata']) ? wp_json_encode($data['metadata']) : '',
			'created_at'              => $now,
			'updated_at'              => $now,
		), array('%d','%d','%d','%s','%s','%s','%s','%s','%d','%d','%d','%s','%d','%d'));

		return (int) $this->wpdb->insert_id;
	}

	/**
	 * Update an existing suggestion.
	 *
	 * @param int   $suggestion_id Suggestion ID.
	 * @param array $data          Fields to update.
	 *
	 * @return bool True on success, false on failure.
	 * @since 2.10.0
	 */
	public function update_suggestion($suggestion_id, $data) {
		$suggestion_id = absint($suggestion_id);
		if (!$suggestion_id) {
			return false;
		}

		$fields = array('updated_at' => time());
		$formats = array('%d');

		foreach (array('status' => '%s','priority' => '%s','severity' => '%s','content_hash' => '%s','freshness_marker' => '%s','last_scanned_at' => '%d','applied_items_count' => '%d','dismissed_items_count' => '%d','metadata' => '%s') as $key => $format) {
			if (!array_key_exists($key, $data)) {
				continue;
			}

			$value = $data[$key];

			if ('%d' === $format) {
				$value = absint($value);
			} else {
				$value = in_array($key, array('metadata'), true) ? (is_string($value) ? $value : wp_json_encode($value)) : sanitize_text_field((string) $value);
			}

			$fields[$key] = $value;
			$formats[] = $format;
		}

		return false !== $this->wpdb->update($this->table_suggestions, $fields, array('id' => $suggestion_id), $formats, array('%d'));
	}

	/**
	 * Add a suggestion item (component-level suggestion).
	 *
	 * @param array $data Item data with keys: suggestion_id, run_id, post_id, component, item_type,
	 *                    status, original_value, suggested_value, rationale, confidence,
	 *                    diff_payload, audit_meta, decided_by, decided_at.
	 *
	 * @return int The new item ID.
	 * @since 2.10.0
	 */
	public function add_suggestion_item($data) {
		$now = time();

		$this->wpdb->insert($this->table_items, array(
			'suggestion_id'   => absint($data['suggestion_id']),
			'run_id'          => absint($data['run_id']),
			'post_id'         => absint($data['post_id']),
			'component'       => sanitize_key($data['component']),
			'item_type'       => sanitize_key($data['item_type']),
			'status'          => isset($data['status']) ? sanitize_key($data['status']) : 'pending',
			'original_value'  => isset($data['original_value']) ? wp_json_encode($data['original_value']) : '',
			'suggested_value' => isset($data['suggested_value']) ? wp_json_encode($data['suggested_value']) : '',
			'rationale'       => isset($data['rationale']) ? sanitize_textarea_field($data['rationale']) : '',
			'confidence'      => isset($data['confidence']) ? (float) $data['confidence'] : 0,
			'diff_payload'    => isset($data['diff_payload']) ? wp_json_encode($data['diff_payload']) : '',
			'audit_meta'      => isset($data['audit_meta']) ? wp_json_encode($data['audit_meta']) : '',
			'decided_by'      => isset($data['decided_by']) ? absint($data['decided_by']) : 0,
			'decided_at'      => isset($data['decided_at']) ? absint($data['decided_at']) : 0,
			'applied_at'      => 0,
			'created_at'      => $now,
			'updated_at'      => $now,
		), array('%d','%d','%d','%s','%s','%s','%s','%s','%s','%f','%s','%s','%d','%d','%d','%d','%d'));

		return (int) $this->wpdb->insert_id;
	}

	/**
	 * Get paginated pending suggestions with search support.
	 *
	 * Only returns suggestions that have at least one pending item.
	 *
	 * @param array $args Query args with keys: per_page, page, search, status.
	 *
	 * @return array Associative array with keys: items, total, pages, current_page.
	 * @since 2.10.0
	 */
	public function get_pending_suggestions($args = array()) {
		$defaults = array(
			'per_page' => 20,
			'page'     => 1,
			'search'   => '',
			'status'   => 'pending',
		);

		$args = wp_parse_args($args, $defaults);
		$offset = max(0, ((int) $args['page'] - 1) * (int) $args['per_page']);

		// Build WHERE clause
		$where = array('s.status = %s');
		$params = array(sanitize_key($args['status']));

		if (!empty($args['search'])) {
			$where[] = '(p.post_title LIKE %s OR s.priority LIKE %s OR s.severity LIKE %s)';
			$term = '%' . $this->wpdb->esc_like($args['search']) . '%';
			$params[] = $term;
			$params[] = $term;
			$params[] = $term;
		}

		$where_sql = implode(' AND ', $where);

		// Main query with item count
		$query = "SELECT s.*, p.post_title, p.post_status, COUNT(i.id) AS pending_items
		FROM {$this->table_suggestions} s
		INNER JOIN {$this->wpdb->posts} p ON p.ID = s.post_id
		LEFT JOIN {$this->table_items} i ON i.suggestion_id = s.id AND i.status = 'pending'
		WHERE {$where_sql}
		GROUP BY s.id
		HAVING pending_items > 0
		ORDER BY s.updated_at DESC
		LIMIT %d OFFSET %d";

		$params[] = (int) $args['per_page'];
		$params[] = $offset;
		$items = $this->wpdb->get_results($this->wpdb->prepare($query, $params));

		// Count query for pagination
		$count_query = "SELECT COUNT(DISTINCT s.id)
		FROM {$this->table_suggestions} s
		INNER JOIN {$this->wpdb->posts} p ON p.ID = s.post_id
		INNER JOIN {$this->table_items} i ON i.suggestion_id = s.id AND i.status = 'pending'
		WHERE {$where_sql}";

		$total = (int) $this->wpdb->get_var($this->wpdb->prepare($count_query, array_slice($params, 0, count($params) - 2)));

		return array(
			'items'        => $items,
			'total'        => $total,
			'pages'        => max(1, (int) ceil($total / max(1, (int) $args['per_page']))),
			'current_page' => (int) $args['page'],
		);
	}

	/**
	 * Get detailed suggestion with all its items.
	 *
	 * @param int $suggestion_id Suggestion ID.
	 *
	 * @return array|null Array with keys 'suggestion' and 'items', or null if not found.
	 * @since 2.10.0
	 */
	public function get_suggestion_detail($suggestion_id) {
		$suggestion_id = absint($suggestion_id);
		if (!$suggestion_id) {
			return null;
		}

		$suggestion = $this->wpdb->get_row($this->wpdb->prepare(
			"SELECT s.*, p.post_title, p.post_excerpt, p.post_content FROM {$this->table_suggestions} s INNER JOIN {$this->wpdb->posts} p ON p.ID = s.post_id WHERE s.id = %d",
			$suggestion_id
		));

		if (!$suggestion) {
			return null;
		}

		$items = $this->wpdb->get_results($this->wpdb->prepare("SELECT * FROM {$this->table_items} WHERE suggestion_id = %d ORDER BY component ASC, id ASC", $suggestion_id));

		return array(
			'suggestion' => $suggestion,
			'items'      => $items,
		);
	}

	/**
	 * Update a suggestion item's status and audit trail.
	 *
	 * @param int    $item_id Item ID.
	 * @param string $status  New status (pending, applied, dismissed, rejected).
	 * @param array  $audit   Audit metadata including user_id, action, timestamp.
	 *
	 * @return bool True on success, false on failure.
	 * @since 2.10.0
	 */
	public function update_item_status($item_id, $status, $audit = array()) {
		$item_id = absint($item_id);
		$status = sanitize_key($status);

		if (!$item_id || empty($status)) {
			return false;
		}

		$fields = array(
			'status'     => $status,
			'updated_at' => time(),
			'audit_meta' => wp_json_encode($audit),
			'decided_by' => isset($audit['user_id']) ? absint($audit['user_id']) : 0,
			'decided_at' => time(),
		);

		$formats = array('%s','%d','%s','%d','%d');

		if ('applied' === $status) {
			$fields['applied_at'] = time();
			$formats[] = '%d';
		}

		return false !== $this->wpdb->update($this->table_items, $fields, array('id' => $item_id), $formats, array('%d'));
	}

	/**
	 * Get multiple items by their IDs.
	 *
	 * @param array $item_ids Array of item IDs.
	 *
	 * @return array Array of item objects.
	 * @since 2.10.0
	 */
	public function get_items_by_ids($item_ids) {
		$item_ids = array_values(array_filter(array_map('absint', (array) $item_ids)));

		if (empty($item_ids)) {
			return array();
		}

		$placeholders = implode(',', array_fill(0, count($item_ids), '%d'));
		$query = "SELECT * FROM {$this->table_items} WHERE id IN ($placeholders)";

		return $this->wpdb->get_results($this->wpdb->prepare($query, ...$item_ids));
	}

	/**
	 * Get IDs of posts generated by this plugin from a list of post IDs.
	 *
	 * Checks both history table and post meta for plugin generation markers.
	 *
	 * @param array $post_ids Array of post IDs to check.
	 *
	 * @return array Array of post IDs that were generated by the plugin.
	 * @since 2.10.0
	 */
	public function get_plugin_generated_post_ids($post_ids) {
		$post_ids = array_values(array_filter(array_map('absint', (array) $post_ids)));
		if (empty($post_ids)) {
			return array();
		}

		// Check history table for posts linked to generation sessions
		$placeholders = implode(',', array_fill(0, count($post_ids), '%d'));
		$history_query = "SELECT DISTINCT post_id FROM {$this->table_history} WHERE post_id IN ($placeholders)";
		$history_ids = $this->wpdb->get_col($this->wpdb->prepare($history_query, ...$post_ids));

		// Check post meta for plugin-specific markers
		$postmeta_table = $this->wpdb->postmeta;
		$meta_keys = array('aips_post_generation_component_statuses', 'aips_post_generation_had_partial', '_aips_trending_topic_id');
		$meta_placeholders = implode(',', array_fill(0, count($meta_keys), '%s'));
		$meta_query = "SELECT DISTINCT post_id FROM {$postmeta_table} WHERE post_id IN ($placeholders) AND meta_key IN ($meta_placeholders)";
		$args = array_merge($post_ids, $meta_keys);
		$meta_ids = $this->wpdb->get_col($this->wpdb->prepare($meta_query, ...$args));

		return array_values(array_unique(array_map('intval', array_merge((array) $history_ids, (array) $meta_ids))));
	}

	/**
	 * Recalculate suggestion status based on item statuses.
	 *
	 * Automatically determines suggestion status from item counts:
	 * - 'applied' if all items are applied
	 * - 'dismissed' if all items are dismissed and none applied
	 * - 'closed' if no items exist
	 * - 'pending' otherwise
	 *
	 * @param int $suggestion_id Suggestion ID.
	 *
	 * @return bool True on success, false on failure.
	 * @since 2.10.0
	 */
	public function recalculate_suggestion_status($suggestion_id) {
		$suggestion_id = absint($suggestion_id);
		if (!$suggestion_id) {
			return false;
		}

		// Aggregate item status counts
		$counts = $this->wpdb->get_row($this->wpdb->prepare(
			"SELECT
			SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) AS pending_count,
			SUM(CASE WHEN status = 'applied' THEN 1 ELSE 0 END) AS applied_count,
			SUM(CASE WHEN status IN ('dismissed','rejected') THEN 1 ELSE 0 END) AS dismissed_count
			FROM {$this->table_items}
			WHERE suggestion_id = %d",
			$suggestion_id
		));

		$pending = isset($counts->pending_count) ? (int) $counts->pending_count : 0;
		$applied = isset($counts->applied_count) ? (int) $counts->applied_count : 0;
		$dismissed = isset($counts->dismissed_count) ? (int) $counts->dismissed_count : 0;

		// Determine new status based on item counts
		$status = 'pending';
		if ($pending <= 0 && $applied > 0) {
			$status = 'applied';
		} elseif ($pending <= 0 && $dismissed > 0 && $applied <= 0) {
			$status = 'dismissed';
		} elseif ($pending <= 0 && $applied <= 0 && $dismissed <= 0) {
			$status = 'closed';
		}

		return $this->update_suggestion($suggestion_id, array(
			'status'                => $status,
			'applied_items_count'   => $applied,
			'dismissed_items_count' => $dismissed,
		));
	}

	/**
	 * Manually mark a suggestion with a specific status.
	 *
	 * @param int    $suggestion_id Suggestion ID.
	 * @param string $status        New status value.
	 *
	 * @return bool True on success, false on failure.
	 * @since 2.10.0
	 */
	public function mark_suggestion_as($suggestion_id, $status) {
		return $this->update_suggestion(absint($suggestion_id), array('status' => sanitize_key($status)));
	}

	/**
	 * Check if a post was generated by this plugin.
	 *
	 * @param int $post_id Post ID to check.
	 *
	 * @return bool True if plugin-generated, false otherwise.
	 * @since 2.10.0
	 */
	public function is_plugin_generated_post($post_id) {
		$post_id = absint($post_id);
		if (!$post_id) {
			return false;
		}

		// Check history table for generation link
		$linked = (int) $this->wpdb->get_var($this->wpdb->prepare(
			"SELECT COUNT(*) FROM {$this->table_history} WHERE post_id = %d",
			$post_id
		));

		if ($linked > 0) {
			return true;
		}

		// Check post meta for plugin generation markers
		$meta_keys = array('aips_post_generation_component_statuses', 'aips_post_generation_had_partial', '_aips_trending_topic_id');
		foreach ($meta_keys as $meta_key) {
			$value = get_post_meta($post_id, $meta_key, true);
			if ('' !== (string) $value) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Decode and sanitize run_state JSON field.
	 *
	 * @param string $run_state JSON-encoded run state.
	 *
	 * @return array Decoded and sanitized state array.
	 * @since 2.10.0
	 */
	private function decode_schedule_state($run_state) {
		$state = json_decode((string) $run_state, true);
		if (!is_array($state)) {
			$state = array();
		}

		// Merge with defaults
		$defaults = array(
			'category_filters'        => array(),
			'include_generated_posts' => 0,
			'lock_token'              => '',
			'lock_expires_at'         => 0,
			'run_cursor'              => 0,
			'retry_count'             => 0,
			'last_error'              => '',
		);

		$state = wp_parse_args($state, $defaults);

		// Sanitize all fields
		$state['category_filters']        = array_values(array_map('absint', (array) $state['category_filters']));
		$state['include_generated_posts'] = !empty($state['include_generated_posts']) ? 1 : 0;
		$state['lock_token']              = sanitize_text_field((string) $state['lock_token']);
		$state['lock_expires_at']         = absint($state['lock_expires_at']);
		$state['run_cursor']              = absint($state['run_cursor']);
		$state['retry_count']             = absint($state['retry_count']);
		$state['last_error']              = sanitize_textarea_field((string) $state['last_error']);

		return $state;
	}

	/**
	 * Hydrate schedule object by expanding run_state JSON into properties.
	 *
	 * @param object $schedule Raw schedule row from database.
	 *
	 * @return object Enhanced schedule object with expanded properties.
	 * @since 2.10.0
	 */
	private function hydrate_schedule($schedule) {
		$schedule = clone $schedule;

		$state = $this->decode_schedule_state(isset($schedule->run_state) ? $schedule->run_state : '');

		// Add convenience properties from run_state
		$schedule->description              = isset($schedule->topic) ? (string) $schedule->topic : '';
		$schedule->category_filters         = wp_json_encode($state['category_filters']);
		$schedule->include_generated_posts  = (int) $state['include_generated_posts'];
		$schedule->lock_token               = (string) $state['lock_token'];
		$schedule->lock_expires_at          = (int) $state['lock_expires_at'];
		$schedule->run_cursor               = (int) $state['run_cursor'];
		$schedule->retry_count              = (int) $state['retry_count'];
		$schedule->last_error               = (string) $state['last_error'];

		return $schedule;
	}
}
