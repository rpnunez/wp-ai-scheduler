<?php
/**
 * Notifications Repository Interface
 *
 * Defines the contract for notification persistence operations.
 *
 * @package AI_Post_Scheduler
 * @since 2.3.1
 */

if (!defined('ABSPATH')) {
	exit;
}

interface AIPS_Notifications_Repository_Interface {

	/**
	 * Create a notification.
	 *
	 * @param string $type Type slug.
	 * @param string $message Human-readable message.
	 * @param string $url Optional action URL.
	 * @return int|false
	 */
	public function create($type, $message, $url = '');

	/**
	 * Create a notification using the rich payload format.
	 *
	 * @param array $data Notification payload.
	 * @return int|false
	 */
	public function create_notification(array $data);

	/**
	 * Fetch unread notifications.
	 *
	 * @param int $limit Max rows.
	 * @return array
	 */
	public function get_unread($limit = 20);

	/**
	 * Count unread notifications.
	 *
	 * @return int
	 */
	public function count_unread();

	/**
	 * Mark one notification as read.
	 *
	 * @param int $id Notification ID.
	 * @return bool
	 */
	public function mark_as_read($id);

	/**
	 * Mark all notifications as read.
	 *
	 * @return int|false
	 */
	public function mark_all_as_read();

	/**
	 * Check whether a dedupe key was sent recently.
	 *
	 * @param string $dedupe_key Dedupe key.
	 * @param int    $window_seconds Lookback window in seconds.
	 * @return bool
	 */
	public function was_recently_sent($dedupe_key, $window_seconds = 3600);

	/**
	 * Get per-type counts for a time window.
	 *
	 * @param int   $seconds Window in seconds.
	 * @param array $types Optional type filters.
	 * @return array
	 */
	public function get_type_counts_for_window($seconds, array $types = array());
}
