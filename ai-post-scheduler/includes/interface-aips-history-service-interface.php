<?php
/**
 * History Service Interface
 *
 * Defines the contract for history orchestration operations.
 *
 * @package AI_Post_Scheduler
 * @since 2.3.1
 */

if (!defined('ABSPATH')) {
	exit;
}

interface AIPS_History_Service_Interface {

	/**
	 * Create a new history container.
	 *
	 * @param string $type History type.
	 * @param array  $metadata Optional metadata.
	 * @return AIPS_History_Container
	 */
	public function create($type, $metadata = array());

	/**
	 * Return activity-feed entries.
	 *
	 * @param int   $limit Number of rows.
	 * @param int   $offset Offset.
	 * @param array $filters Optional filters.
	 * @return array
	 */
	public function get_activity_feed($limit = 50, $offset = 0, $filters = array());

	/**
	 * Check if a post has completed history.
	 *
	 * @param int $post_id Post ID.
	 * @return bool
	 */
	public function post_has_history_and_completed($post_id);

	/**
	 * Fetch history by ID.
	 *
	 * @param int $history_id History ID.
	 * @return object|null
	 */
	public function get_by_id($history_id);

	/**
	 * Update a history record.
	 *
	 * @param int   $history_id History ID.
	 * @param array $data Update data.
	 * @return bool
	 */
	public function update_history_record($history_id, $data);

	/**
	 * Find an in-progress history container by context.
	 *
	 * @param string $type Container type label.
	 * @param array  $metadata Lookup metadata (e.g. author_id).
	 * @return AIPS_History_Container|null
	 */
	public function find_incomplete($type, $metadata = array());
}
