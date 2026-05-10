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
	const EVENT_TYPE_KEY   = 'event_type';
	const EVENT_STATUS_KEY = 'event_status';
	const EVENT_SOURCE_KEY = 'source';

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
		return new AIPS_History_Container($this->repository, $type, self::build_metadata($type, $metadata));
	}

	/**
	 * Build normalized metadata for history containers.
	 *
	 * @param string $history_type Container type.
	 * @param array  $metadata Optional metadata overrides.
	 * @return array
	 */
	public static function build_metadata($history_type, $metadata = array()) {
		if (!is_array($metadata)) {
			$metadata = array();
		}

		$normalized = $metadata;

		if (empty($normalized['creation_method']) && !empty($history_type)) {
			$normalized['creation_method'] = $history_type;
		}

		if (empty($normalized['history_type']) && !empty($history_type)) {
			$normalized['history_type'] = $history_type;
		}

		if (!isset($normalized['user_id']) && function_exists('get_current_user_id')) {
			$user_id = get_current_user_id();
			if ($user_id > 0) {
				$normalized['user_id'] = $user_id;
			}
		}

		return $normalized;
	}

	/**
	 * Get an existing history container when possible, or create one.
	 *
	 * @param string $history_type Container type.
	 * @param array  $metadata Metadata for creation/lookup.
	 * @param int    $history_id Optional existing history ID.
	 * @param int    $post_id Optional post ID context for resolve fallback.
	 * @return AIPS_History_Container
	 */
	public function get_or_create($history_type, $metadata = array(), $history_id = 0, $post_id = 0) {
		$history_id = absint($history_id);
		$post_id    = absint($post_id);

		if ($history_id > 0) {
			$existing = AIPS_History_Container::load_existing($this->repository, $history_id);
			if ($existing) {
				return $existing;
			}
		}

		if ($post_id > 0) {
			$resolved = AIPS_History_Container::resolve_existing($this->repository, $post_id, $history_id);
			if (!is_wp_error($resolved)) {
				return $resolved;
			}
		}

		return $this->create($history_type, $metadata);
	}

	/**
	 * Create a history container and record a single log entry.
	 *
	 * @param string $history_type Container type.
	 * @param array  $metadata Container metadata.
	 * @param string $log_type Log type.
	 * @param string $message Human readable message.
	 * @param mixed  $input Optional input payload.
	 * @param mixed  $output Optional output payload.
	 * @param array  $context Optional context payload.
	 * @return AIPS_History_Container
	 */
	public function create_and_record($history_type, $metadata, $log_type, $message, $input = null, $output = null, $context = array()) {
		$history = $this->create($history_type, $metadata);
		$history->record($log_type, $message, $input, $output, $context);

		return $history;
	}

	/**
	 * Create a history container, record a single entry, and complete success.
	 *
	 * @param string $history_type Container type.
	 * @param array  $metadata Container metadata.
	 * @param string $log_type Log type.
	 * @param string $message Human readable message.
	 * @param mixed  $input Optional input payload.
	 * @param mixed  $output Optional output payload.
	 * @param array  $context Optional context payload.
	 * @param array  $result_data Optional completion payload.
	 * @return AIPS_History_Container
	 */
	public function create_record_and_complete_success($history_type, $metadata, $log_type, $message, $input = null, $output = null, $context = array(), $result_data = array()) {
		$history = $this->create_and_record($history_type, $metadata, $log_type, $message, $input, $output, $context);
		$history->complete_success($result_data);

		return $history;
	}

	/**
	 * Static convenience wrapper for activity-style logs.
	 *
	 * @param string $history_type History container type.
	 * @param string $message Human-readable message.
	 * @param string $event_type Event type key.
	 * @param string $event_status Event status key.
	 * @param array  $metadata Optional container metadata.
	 * @param array  $context Optional log context.
	 * @return AIPS_History_Container
	 */
	public static function log_activity($history_type, $message, $event_type = '', $event_status = '', $metadata = array(), $context = array()) {
		return self::log_event(
			array(
				'history_type' => $history_type,
				'message' => $message,
				'log_type' => 'activity',
				'event_type' => $event_type,
				'event_status' => $event_status,
				'metadata' => $metadata,
				'context' => $context,
			)
		);
	}

	/**
	 * Named-argument event logger to avoid positional arg overload.
	 *
	 * @param array $args Event logging args.
	 * @return AIPS_History_Container
	 */
	public static function log_event($args = array()) {
		$args = wp_parse_args(
			$args,
			array(
				'history_type' => '',
				'message' => '',
				'log_type' => 'activity',
				'event_type' => '',
				'event_status' => '',
				'metadata' => array(),
				'context' => array(),
				'input' => null,
				'output' => null,
				'complete' => '',
				'result_data' => array(),
				'history_id' => 0,
				'post_id' => 0,
			)
		);

		$service = self::instance();
		$event = self::normalize_event_context($args['event_type'], $args['event_status'], $args['context']);
		$metadata = self::build_metadata($args['history_type'], $args['metadata']);
		$history = $service->get_or_create($args['history_type'], $metadata, $args['history_id'], $args['post_id']);

		$history->record(
			$args['log_type'],
			$args['message'],
			$args['input'] !== null ? $args['input'] : array(
				self::EVENT_TYPE_KEY => $event['event_type'],
				self::EVENT_STATUS_KEY => $event['event_status'],
			),
			$args['output'],
			$event['context']
		);

		if ($args['complete'] === 'success') {
			$history->complete_success($args['result_data']);
		} elseif ($args['complete'] === 'failed') {
			$history->complete_failure(
				isset($args['result_data']['error_message']) ? $args['result_data']['error_message'] : '',
				$args['result_data']
			);
		}

		return $history;
	}

	/**
	 * Normalize event type/status and fold into context.
	 *
	 * @param string $event_type Event type.
	 * @param string $event_status Event status.
	 * @param array  $context Optional context.
	 * @return array{event_type:string,event_status:string,context:array}
	 */
	public static function normalize_event_context($event_type = '', $event_status = '', $context = array()) {
		if (!is_array($context)) {
			$context = array();
		}

		$normalized_event_type = !empty($event_type) ? (string) $event_type : (isset($context['event_type']) ? (string) $context['event_type'] : 'activity');
		$normalized_status = !empty($event_status) ? (string) $event_status : (isset($context['event_status']) ? (string) $context['event_status'] : 'success');

		$context[self::EVENT_TYPE_KEY] = $normalized_event_type;
		$context[self::EVENT_STATUS_KEY] = $normalized_status;

		return array(
			'event_type' => $normalized_event_type,
			'event_status' => $normalized_status,
			'context' => $context,
		);
	}

	public static function log_success($history_type, $message, $event_type = '', $metadata = array(), $context = array()) {
		return self::log_activity($history_type, $message, $event_type, 'success', $metadata, $context);
	}

	public static function log_failure($history_type, $message, $event_type = '', $metadata = array(), $context = array()) {
		return self::log_activity($history_type, $message, $event_type, 'failed', $metadata, $context);
	}

	public static function log_warning($history_type, $message, $event_type = 'warning', $metadata = array(), $context = array()) {
		return self::log_event(
			array(
				'history_type' => $history_type,
				'message' => $message,
				'log_type' => 'warning',
				'event_type' => $event_type,
				'event_status' => 'warning',
				'metadata' => $metadata,
				'context' => $context,
			)
		);
	}

	public static function log_user_action($history_type, $action, $message, $metadata = array(), $action_data = array()) {
		$service = self::instance();
		$history = $service->create($history_type, $metadata);
		$history->record_user_action($action, $message, $action_data);

		return $history;
	}

	/**
	 * Record on an existing container only when available.
	 *
	 * @param AIPS_History_Container|null $history History container.
	 * @param string $log_type Log type.
	 * @param string $message Message.
	 * @param mixed $input Optional input.
	 * @param mixed $output Optional output.
	 * @param array $context Optional context.
	 * @return int|false
	 */
	public static function record_on_container($history, $log_type, $message, $input = null, $output = null, $context = array()) {
		if (!$history) {
			return false;
		}

		return $history->record($log_type, $message, $input, $output, $context);
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
			$item_type = isset($item->creation_method) ? (string) $item->creation_method : '';

			if (!empty($type) && !empty($item_type) && $item_type !== $type) {
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
