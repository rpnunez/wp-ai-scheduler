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
}
