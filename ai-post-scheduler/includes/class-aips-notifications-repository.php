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
		$this->table = AIPS_DB_Manager::get_table_name('notifications');
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
		$result = $this->wpdb->insert(
			$this->table,
			array(
				'type'       => sanitize_text_field($type),
				'message'    => sanitize_textarea_field($message),
				'url'        => esc_url_raw($url),
				'is_read'    => 0,
				'created_at' => current_time('mysql', true),
			),
			array('%s', '%s', '%s', '%d', '%s')
		);

		if ($result === false) {
			return false;
		}

		return (int) $this->wpdb->insert_id;
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
			array('is_read' => 1),
			array('id' => absint($id)),
			array('%d'),
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
			array('is_read' => 1),
			array('is_read' => 0),
			array('%d'),
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
}
