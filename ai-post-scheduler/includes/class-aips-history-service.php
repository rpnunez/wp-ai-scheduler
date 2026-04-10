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
class AIPS_History_Service implements AIPS_History_Service_Interface {

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
	 * @var AIPS_History_Repository_Interface Repository for database operations
	 */
	private $repository;
	
	/**
	 * Initialize the service
	 *
	 * @param AIPS_History_Repository_Interface|null $repository Optional repository instance
	 */
	public function __construct(?AIPS_History_Repository_Interface $repository = null) {
		if ($repository) {
			$this->repository = $repository;
			return;
		}

		$container = AIPS_Container::get_instance();
		if ($container->has(AIPS_History_Repository_Interface::class)) {
			$this->repository = $container->make(AIPS_History_Repository_Interface::class);
			return;
		}

		$this->repository = AIPS_History_Repository::instance();
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

	/**
	 * Find an in-progress history container by type and metadata context.
	 *
	 * @param string $type History type label.
	 * @param array  $metadata Metadata filters.
	 * @return AIPS_History_Container|null
	 */
	public function find_incomplete($type, $metadata = array()) {
		$args = array(
			'per_page' => 20,
			'page' => 1,
			'status' => 'processing',
			'orderby' => 'created_at',
			'order' => 'DESC',
		);

		if (!empty($metadata['author_id'])) {
			$args['author_id'] = absint($metadata['author_id']);
		}

		$history = $this->repository->get_history($args);
		if (empty($history['items']) || !is_array($history['items'])) {
			return null;
		}

		foreach ($history['items'] as $item) {
			if (!empty($type) && isset($item->creation_method) && !empty($item->creation_method) && $item->creation_method !== $type) {
				continue;
			}

			$container = AIPS_History_Container::load_existing($this->repository, $item->id);
			if ($container) {
				return $container;
			}
		}

		return null;
	}
}
