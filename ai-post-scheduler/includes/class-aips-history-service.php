<?php
/**
 * History Service
 *
 * Unified service for logging and tracking all generation activities.
 * Wraps session management and history updates into a clean API.
 *
 * @package AI_Post_Scheduler
 * @since 2.0.0
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_History_Service
 *
 * Provides a unified interface for logging generation activities,
 * managing sessions, and updating history records.
 */
class AIPS_History_Service {
	
	/**
	 * @var AIPS_History_Repository Repository for database operations
	 */
	private $repository;
	
	/**
	 * Initialize the service
	 *
	 * @param AIPS_History_Repository|null $repository Optional repository instance
	 */
	public function __construct($repository = null) {
		$this->repository = $repository ?: new AIPS_History_Repository();
	}
	
	/**
	 * Create a new History container for tracking a specific process.
	 *
	 * @param string $type Type of history container (e.g., 'post_generation', 'topic_generation')
	 * @param array $metadata Optional metadata for the history container
	 * @return AIPS_History_Container History container object
	 */
	public function create($type, $metadata = array()) {
		return new AIPS_History_Container($this->repository, $type, $metadata);
	}
	
	/**
	 * Get activity feed (high-level events)
	 *
	 * Returns only ACTIVITY type entries for display in activity feed.
	 *
	 * @param int $limit Number of items to return
	 * @param int $offset Offset for pagination
	 * @param array $filters Optional filters (event_type, event_status, search)
	 * @return array Activity entries
	 */
	public function get_activity_feed($limit = 50, $offset = 0, $filters = array()) {
		return $this->repository->get_activity_feed($limit, $offset, $filters);
	}
	
	/**
	 * Check if a post has history and is completed.
	 *
	 * @param int $post_id Post ID
	 * @return bool True if post has completed history
	 */
	public function post_has_history_and_completed($post_id) {
		return $this->repository->post_has_history_and_completed($post_id);
	}
	
	/**
	 * Get history item by ID.
	 *
	 * @param int $history_id History ID
	 * @return object|null History item or null if not found
	 */
	public function get_by_id($history_id) {
		return $this->repository->get_by_id($history_id);
	}
	
	/**
	 * Update a history record.
	 *
	 * @param int $history_id History ID
	 * @param array $data Data to update
	 * @return bool Success status
	 */
	public function update_history_record($history_id, $data) {
		return $this->repository->update($history_id, $data);
	}
}
