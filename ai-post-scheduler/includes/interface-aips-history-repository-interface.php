<?php
/**
 * History Repository Interface
 *
 * Defines the contract for history persistence and retrieval.
 *
 * @package AI_Post_Scheduler
 * @since 2.3.1
 */

if (!defined('ABSPATH')) {
	exit;
}

interface AIPS_History_Repository_Interface {

	/**
	 * Fetch paginated history rows.
	 *
	 * @param array $args Query arguments.
	 * @return array
	 */
	public function get_history($args = array());

	/**
	 * Fetch paginated activity-feed entries.
	 *
	 * @param int   $limit Number of rows to return.
	 * @param int   $offset Offset for pagination.
	 * @param array $filters Optional feed filters.
	 * @return array
	 */
	public function get_activity_feed($limit = 50, $offset = 0, $filters = array());

	/**
	 * Fetch one history record by ID.
	 *
	 * @param int $id History row ID.
	 * @return object|null
	 */
	public function get_by_id($id);

	/**
	 * Fetch the latest history record linked to a post.
	 *
	 * @param int $post_id Post ID.
	 * @return object|null
	 */
	public function get_by_post_id($post_id);

	/**
	 * Count completed history rows for a schedule.
	 *
	 * @param int|object $schedule Schedule ID or object.
	 * @return int
	 */
	public function count_completed_for_schedule($schedule);

	/**
	 * Invalidate the cached completed-count for a schedule.
	 *
	 * @param int $schedule_id Schedule ID.
	 * @return void
	 */
	public function invalidate_schedule_completed_count_cache($schedule_id);

	/**
	 * Insert a history log entry row.
	 *
	 * @param int          $history_id History record ID.
	 * @param string       $log_type Log type.
	 * @param array|string $details Event details.
	 * @param int|null     $history_type_id Optional history type ID.
	 * @return int|false
	 */
	public function add_log_entry($history_id, $log_type, $details, $history_type_id = null);

	/**
	 * Create a history row.
	 *
	 * @param array $data Insert payload.
	 * @return int|false
	 */
	public function create($data);

	/**
	 * Update a history row.
	 *
	 * @param int   $id History row ID.
	 * @param array $data Update payload.
	 * @return bool
	 */
	public function update($id, $data);

	/**
	 * Fetch logs for a history record.
	 *
	 * @param int   $history_id History ID.
	 * @param array $type_filter Optional type filter.
	 * @param int   $limit Optional limit.
	 * @return array
	 */
	public function get_logs_by_history_id($history_id, $type_filter = array(), $limit = 0);

	/**
	 * Return estimated generation timing stats.
	 *
	 * @param int $limit Number of rows to sample.
	 * @return array
	 */
	public function get_estimated_generation_time($limit = 20);

	/**
	 * Return component revisions for AI edit history.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $component_type Component key.
	 * @param int    $limit Number of revisions.
	 * @return array
	 */
	public function get_component_revisions($post_id, $component_type, $limit = 20);

	/**
	 * Check whether a post has a completed history record.
	 *
	 * @param int $post_id Post ID.
	 * @return bool
	 */
	public function post_has_history_and_completed($post_id);
}
