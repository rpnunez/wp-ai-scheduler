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
class AIPS_Notifications_Repository implements AIPS_Notifications_Repository_Interface {

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
	 * @var string Full read-receipts table name.
	 */
	private $reads_table;

	/**
	 * Constructor.
	 */
	public function __construct() {
		global $wpdb;
		$this->wpdb       = $wpdb;
		$this->table      = $wpdb->prefix . 'aips_notifications';
		$this->reads_table = $wpdb->prefix . 'aips_notification_reads';
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
			'read_at'    => 0,
			'created_at' => AIPS_DateTime::now()->timestamp(),
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
				'read_at'    => !empty($data['read_at']) ? absint($data['read_at']) : 0,
				'created_at' => !empty($data['created_at']) ? absint($data['created_at']) : AIPS_DateTime::now()->timestamp(),
			),
			array('%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%d')
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
		$cutoff_timestamp = AIPS_DateTime::now()->timestamp() - $window_seconds;

		if ('' === $dedupe_key || $window_seconds < 1) {
			return false;
		}

		$count = $this->wpdb->get_var(
			$this->wpdb->prepare(
				"SELECT COUNT(*) FROM {$this->table} WHERE dedupe_key = %s AND created_at >= %d",
				$dedupe_key,
				$cutoff_timestamp
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
	public function get_unread($limit = 20, $user_id = 0) {
		$limit = absint($limit);
		if ($limit < 1) {
			$limit = 20;
		}

		$resolved_user_id = $this->resolve_user_id($user_id);

		if ($resolved_user_id < 1) {
			return $this->wpdb->get_results(
				$this->wpdb->prepare(
					"SELECT id, type, title, message, url, level, is_read, read_at, created_at FROM {$this->table} WHERE is_read = 0 ORDER BY created_at DESC, id DESC LIMIT %d",
					$limit
				)
			);
		}

		return $this->wpdb->get_results(
			$this->wpdb->prepare(
				"SELECT n.id, n.type, n.title, n.message, n.url, n.level, n.is_read, n.read_at, n.created_at
				FROM {$this->table} n
				LEFT JOIN {$this->reads_table} r
					ON n.id = r.notification_id
					AND r.user_id = %d
				WHERE n.is_read = 0
					AND r.notification_id IS NULL
				ORDER BY n.created_at DESC, n.id DESC
				LIMIT %d",
				$resolved_user_id,
				$limit
			)
		);
	}

	/**
	 * Count unread notifications.
	 *
	 * @return int
	 */
	public function count_unread($user_id = 0) {
		$resolved_user_id = $this->resolve_user_id($user_id);

		if ($resolved_user_id < 1) {
			return (int) $this->wpdb->get_var(
				"SELECT COUNT(*) FROM {$this->table} WHERE is_read = 0"
			);
		}

		return (int) $this->wpdb->get_var(
			$this->wpdb->prepare(
				"SELECT COUNT(*)
				FROM {$this->table} n
				LEFT JOIN {$this->reads_table} r
					ON n.id = r.notification_id
					AND r.user_id = %d
				WHERE n.is_read = 0
					AND r.notification_id IS NULL",
				$resolved_user_id
			)
		);
	}

	/**
	 * Mark a single notification as read.
	 *
	 * @param int $id Notification ID.
	 * @return bool True on success.
	 */
	public function mark_as_read($id, $user_id = 0) {
		$id = absint($id);
		$resolved_user_id = $this->resolve_user_id($user_id);

		if ($id < 1 || $resolved_user_id < 1) {
			return false;
		}

		$existing = $this->wpdb->get_var(
			$this->wpdb->prepare(
				"SELECT 1 FROM {$this->reads_table} WHERE notification_id = %d AND user_id = %d",
				$id,
				$resolved_user_id
			)
		);

		if ($existing) {
			return true;
		}

		$result = $this->wpdb->insert(
			$this->reads_table,
			array(
				'notification_id' => $id,
				'user_id'         => $resolved_user_id,
				'read_at'         => AIPS_DateTime::now()->timestamp(),
			),
			array('%d', '%d', '%d')
		);

		return false !== $result;
	}

	/**
	 * Mark all notifications as read.
	 *
	 * @return bool True on success.
	 */
	public function mark_all_as_read($user_id = 0) {
		$resolved_user_id = $this->resolve_user_id($user_id);

		if ($resolved_user_id < 1) {
			return false;
		}

		$result = $this->wpdb->query(
			$this->wpdb->prepare(
				"INSERT INTO {$this->reads_table} (notification_id, user_id, read_at)
				SELECT n.id, %d, %d
				FROM {$this->table} n
				LEFT JOIN {$this->reads_table} r
					ON n.id = r.notification_id
					AND r.user_id = %d
				WHERE n.is_read = 0
					AND r.notification_id IS NULL",
				$resolved_user_id,
				AIPS_DateTime::now()->timestamp(),
				$resolved_user_id
			)
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
		$cutoff_timestamp = AIPS_DateTime::now()->timestamp() - ($days * DAY_IN_SECONDS);

		return (int) $this->wpdb->query(
			$this->wpdb->prepare(
				"DELETE FROM {$this->table} WHERE is_read = 1 AND created_at < %d",
				$cutoff_timestamp
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
		$cutoff_timestamp = AIPS_DateTime::now()->timestamp() - $seconds;

		if ($seconds < 1) {
			return array();
		}

		$sql = "SELECT type, COUNT(*) AS count FROM {$this->table} WHERE created_at >= %d";
		$params = array($cutoff_timestamp);

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
	 * Resolve the user ID for per-user unread/read operations.
	 *
	 * @param int $user_id Requested user ID.
	 * @return int
	 */
	private function resolve_user_id($user_id) {
		$user_id = absint($user_id);
		if ($user_id > 0) {
			return $user_id;
		}

		if (function_exists('get_current_user_id')) {
			return absint(get_current_user_id());
		}

		return 0;
	}
}
