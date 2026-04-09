<?php
if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Notifications_Repository
 *
 * Handles database operations for system-wide admin notifications.
 *
 * @package AI_Post_Scheduler
 * @since 1.8.0
 */
class AIPS_Notifications_Repository {

	/**
	 * @var self|null Singleton instance.
	 */
	private static $instance = null;

	/**
	 * Get the shared singleton instance.
	 *
	 * @return self
	 */
	public static function instance(): self {
		if ( self::$instance === null ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * @var wpdb WordPress database object.
	 */
	private $wpdb;

	/**
	 * @var string Full table name.
	 */
	private $table;

	/**
	 * Constructor.
	 */
	public function __construct() {
		global $wpdb;
		$this->wpdb  = $wpdb;
		$this->table = $wpdb->prefix . 'aips_notifications';
	}

	/**
	 * Create a new notification.
	 *
	 * @param string $type    Notification type (e.g. 'author_topics_generated').
	 * @param string $message Human-readable message.
	 * @param string $url     Optional URL for the action link.
	 * @return int|false Inserted row ID or false on failure.
	 */
	public function create($type, $message, $url = '') {
		return $this->create_notification(array(
			'type'    => $type,
			'message' => $message,
			'url'     => $url,
		));
	}

	/**
	 * Create a rich notification record.
	 *
	 * @param array $data Notification record fields.
	 * @return int|false Inserted row ID or false on failure.
	 */
	public function create_notification(array $data) {
		$defaults = array(
			'type'       => '',
			'title'      => '',
			'message'    => '',
			'url'        => '',
			'level'      => 'info',
			'meta'       => null,
			'dedupe_key' => '',
			'is_read'    => 0,
			'read_at'    => null,
			'created_at' => current_time('mysql', true),
		);

		$data = wp_parse_args($data, $defaults);

		$meta_json = null;
		if (null !== $data['meta']) {
			$meta_json = is_string($data['meta']) ? $data['meta'] : wp_json_encode($data['meta']);
		}

		$result = $this->wpdb->insert(
			$this->table,
			array(
				'type'       => sanitize_text_field($data['type']),
				'title'      => sanitize_text_field($data['title']),
				'message'    => sanitize_textarea_field($data['message']),
				'url'        => esc_url_raw($data['url']),
				'level'      => sanitize_key($data['level']),
				'meta'       => $meta_json,
				'dedupe_key' => sanitize_text_field($data['dedupe_key']),
				'is_read'    => absint($data['is_read']) ? 1 : 0,
				'read_at'    => !empty($data['read_at']) ? $data['read_at'] : null,
				'created_at' => !empty($data['created_at']) ? $data['created_at'] : current_time('mysql', true),
			),
			array('%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s')
		);

		if ($result === false) {
			return false;
		}

		return (int) $this->wpdb->insert_id;
	}

	/**
	 * Check whether a dedupe key was sent recently.
	 *
	 * @param string $dedupe_key     Dedupe key.
	 * @param int    $window_seconds Time window in seconds.
	 * @return bool
	 */
	public function was_recently_sent($dedupe_key, $window_seconds = 3600) {
		$dedupe_key = sanitize_text_field($dedupe_key);
		$window_seconds = absint($window_seconds);

		if ('' === $dedupe_key || $window_seconds < 1) {
			return false;
		}

		$count = $this->wpdb->get_var(
			$this->wpdb->prepare(
				"SELECT COUNT(*) FROM {$this->table} WHERE dedupe_key = %s AND created_at >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL %d SECOND)",
				$dedupe_key,
				$window_seconds
			)
		);

		return ((int) $count) > 0;
	}

	/**
	 * Get all unread notifications.
	 *
	 * @param int $limit Maximum number to return. Default 20.
	 * @return array Array of notification objects.
	 */
	public function get_unread($limit = 20) {
		$limit = absint($limit);
		if ($limit < 1) {
			$limit = 20;
		}

		return $this->wpdb->get_results(
			$this->wpdb->prepare(
				"SELECT * FROM {$this->table} WHERE is_read = 0 ORDER BY created_at DESC LIMIT %d",
				$limit
			)
		);
	}

	/**
	 * Count unread notifications.
	 *
	 * @return int
	 */
	public function count_unread() {
		return (int) $this->wpdb->get_var(
			"SELECT COUNT(*) FROM {$this->table} WHERE is_read = 0"
		);
	}

	/**
	 * Mark a single notification as read.
	 *
	 * @param int $id Notification ID.
	 * @return bool True on success.
	 */
	public function mark_as_read($id) {
		$result = $this->wpdb->update(
			$this->table,
			array(
				'is_read' => 1,
				'read_at' => current_time('mysql', true),
			),
			array('id' => absint($id)),
			array('%d', '%s'),
			array('%d')
		);

		return $result !== false;
	}

	/**
	 * Mark all notifications as read.
	 *
	 * @return bool True on success.
	 */
	public function mark_all_as_read() {
		$result = $this->wpdb->update(
			$this->table,
			array(
				'is_read' => 1,
				'read_at' => current_time('mysql', true),
			),
			array('is_read' => 0),
			array('%d', '%s'),
			array('%d')
		);

		return $result !== false;
	}

	/**
	 * Delete old read notifications (older than a given number of days).
	 *
	 * @param int $days Number of days. Default 30.
	 * @return int Number of rows deleted.
	 */
	public function cleanup_old($days = 30) {
		$days = absint($days);
		if ($days < 1) {
			$days = 30;
		}

		return (int) $this->wpdb->query(
			$this->wpdb->prepare(
				"DELETE FROM {$this->table} WHERE is_read = 1 AND created_at < DATE_SUB(UTC_TIMESTAMP(), INTERVAL %d DAY)",
				$days
			)
		);
	}

	/**
	 * Return notification counts grouped by type over a recent time window.
	 *
	 * @param int   $seconds Time window in seconds.
	 * @param array $types   Optional list of type slugs to include.
	 * @return array<string, int>
	 */
	public function get_type_counts_for_window($seconds, array $types = array()) {
		$seconds = absint($seconds);

		if ($seconds < 1) {
			return array();
		}

		$sql = "SELECT type, COUNT(*) AS count FROM {$this->table} WHERE created_at >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL %d SECOND)";
		$params = array($seconds);

		if (!empty($types)) {
			$types = array_values(array_filter(array_map('sanitize_key', $types)));
			if (!empty($types)) {
				$placeholders = implode(',', array_fill(0, count($types), '%s'));
				$sql .= " AND type IN ({$placeholders})";
				$params = array_merge($params, $types);
			}
		}

		$sql .= ' GROUP BY type';

		$rows = $this->wpdb->get_results($this->wpdb->prepare($sql, $params));
		$counts = array();

		if (empty($rows)) {
			return $counts;
		}

		foreach ($rows as $row) {
			$counts[sanitize_key($row->type)] = (int) $row->count;
		}

		return $counts;
	}

	/**
	 * Get paginated notifications with filtering.
	 *
	 * @param array $args {
	 *     Query arguments.
	 *     @type int    $page     Current page number. Default 1.
	 *     @type int    $per_page Items per page. Default 20.
	 *     @type string $level    Filter by level (info/warning/error). Default ''.
	 *     @type string $type     Filter by type slug. Default ''.
	 *     @type int    $is_read  Filter by read status. -1 = all, 0 = unread, 1 = read. Default -1.
	 *     @type string $search   Search string. Default ''.
	 *     @type string $orderby  Column to order by. Default 'created_at'.
	 *     @type string $order    Sort direction ASC/DESC. Default 'DESC'.
	 * }
	 * @return array{items: array, total: int, pages: int}
	 */
	public function get_paginated(array $args = array()) {
		$defaults = array(
			'page'     => 1,
			'per_page' => 20,
			'level'    => '',
			'type'     => '',
			'is_read'  => -1,
			'search'   => '',
			'orderby'  => 'created_at',
			'order'    => 'DESC',
		);
		$args = wp_parse_args($args, $defaults);

		$page     = max(1, absint($args['page']));
		$per_page = max(1, absint($args['per_page']));
		$offset   = ($page - 1) * $per_page;

		$allowed_orderby = array('id', 'type', 'level', 'title', 'created_at', 'is_read');
		$orderby = in_array($args['orderby'], $allowed_orderby, true) ? $args['orderby'] : 'created_at';
		$order   = strtoupper($args['order']) === 'ASC' ? 'ASC' : 'DESC';
		$order_by_sql = $orderby === 'id'
			? "id {$order}"
			: "{$orderby} {$order}, id {$order}";

		$where  = array('1=1');
		$params = array();

		if ('' !== $args['level']) {
			$where[]  = 'level = %s';
			$params[] = sanitize_key($args['level']);
		}

		if ('' !== $args['type']) {
			$where[]  = 'type = %s';
			$params[] = sanitize_text_field($args['type']);
		}

		if ((int) $args['is_read'] !== -1) {
			$where[]  = 'is_read = %d';
			$params[] = absint($args['is_read']) ? 1 : 0;
		}

		if ('' !== $args['search']) {
			$like     = '%' . $this->wpdb->esc_like(sanitize_text_field($args['search'])) . '%';
			$where[]  = '(title LIKE %s OR message LIKE %s OR type LIKE %s)';
			$params[] = $like;
			$params[] = $like;
			$params[] = $like;
		}

		$where_sql = implode(' AND ', $where);

		$count_sql = "SELECT COUNT(*) FROM {$this->table} WHERE {$where_sql}";
		if (empty($params)) {
			$total = (int) $this->wpdb->get_var($count_sql);
		} else {
			$total = (int) $this->wpdb->get_var($this->wpdb->prepare($count_sql, $params));
		}

		$items_sql    = "SELECT * FROM {$this->table} WHERE {$where_sql} ORDER BY {$order_by_sql} LIMIT %d OFFSET %d";
		$items_params = array_merge($params, array($per_page, $offset));
		$items        = $this->wpdb->get_results($this->wpdb->prepare($items_sql, $items_params));

		return array(
			'items' => is_array($items) ? $items : array(),
			'total' => $total,
			'pages' => $per_page > 0 ? (int) ceil($total / $per_page) : 1,
		);
	}

	/**
	 * Mark a single notification as unread.
	 *
	 * @param int $id Notification ID.
	 * @return bool True on success.
	 */
	public function mark_as_unread($id) {
		$result = $this->wpdb->update(
			$this->table,
			array('is_read' => 0, 'read_at' => null),
			array('id' => absint($id)),
			array('%d', '%s'),
			array('%d')
		);
		return $result !== false;
	}

	/**
	 * Delete a single notification.
	 *
	 * @param int $id Notification ID.
	 * @return bool True on success.
	 */
	public function delete_notification($id) {
		$result = $this->wpdb->delete(
			$this->table,
			array('id' => absint($id)),
			array('%d')
		);
		return $result !== false;
	}

	/**
	 * Bulk mark notifications as read.
	 *
	 * @param int[] $ids Array of notification IDs.
	 * @return bool True on success.
	 */
	public function bulk_mark_as_read(array $ids) {
		if (empty($ids)) {
			return false;
		}
		$ids          = array_map('absint', $ids);
		$placeholders = implode(',', array_fill(0, count($ids), '%d'));
		$result       = $this->wpdb->query(
			$this->wpdb->prepare(
				"UPDATE {$this->table} SET is_read = 1, read_at = %s WHERE id IN ({$placeholders})",
				array_merge(array(current_time('mysql', true)), $ids)
			)
		);
		return $result !== false;
	}

	/**
	 * Bulk mark notifications as unread.
	 *
	 * @param int[] $ids Array of notification IDs.
	 * @return bool True on success.
	 */
	public function bulk_mark_as_unread(array $ids) {
		if (empty($ids)) {
			return false;
		}
		$ids          = array_map('absint', $ids);
		$placeholders = implode(',', array_fill(0, count($ids), '%d'));
		$result       = $this->wpdb->query(
			$this->wpdb->prepare(
				"UPDATE {$this->table} SET is_read = 0, read_at = null WHERE id IN ({$placeholders})",
				$ids
			)
		);
		return $result !== false;
	}

	/**
	 * Bulk delete notifications.
	 *
	 * @param int[] $ids Array of notification IDs.
	 * @return bool True on success.
	 */
	public function bulk_delete(array $ids) {
		if (empty($ids)) {
			return false;
		}
		$ids          = array_map('absint', $ids);
		$placeholders = implode(',', array_fill(0, count($ids), '%d'));
		$result       = $this->wpdb->query(
			$this->wpdb->prepare(
				"DELETE FROM {$this->table} WHERE id IN ({$placeholders})",
				$ids
			)
		);
		return $result !== false;
	}

	/**
	 * Get summary counts: total, unread, errors, warnings.
	 *
	 * @return array{total: int, unread: int, errors: int, warnings: int}
	 */
	public function get_summary_counts() {
		$row = $this->wpdb->get_row(
			"SELECT
				COUNT(*) AS total,
				SUM(CASE WHEN is_read = 0 THEN 1 ELSE 0 END) AS unread,
				SUM(CASE WHEN level = 'error' THEN 1 ELSE 0 END) AS errors,
				SUM(CASE WHEN level = 'warning' THEN 1 ELSE 0 END) AS warnings
			FROM {$this->table}"
		);
		return array(
			'total'    => $row ? (int) $row->total : 0,
			'unread'   => $row ? (int) $row->unread : 0,
			'errors'   => $row ? (int) $row->errors : 0,
			'warnings' => $row ? (int) $row->warnings : 0,
		);
	}
}
