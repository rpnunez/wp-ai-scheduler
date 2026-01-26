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
	 * @var AIPS_Generation_Session|null Current generation session
	 */
	private $current_session;
	
	/**
	 * @var int|null Current history entry ID
	 */
	private $history_id;
	
	/**
	 * Initialize the service
	 *
	 * @param AIPS_History_Repository|null $repository Optional repository instance
	 */
	public function __construct($repository = null) {
		$this->repository = $repository ?: new AIPS_History_Repository();
		$this->current_session = null;
		$this->history_id = null;
	}
	
	/**
	 * Start a new generation session
	 *
	 * @param AIPS_Generation_Context|object $context Generation context or template
	 * @param object|null $voice Optional voice object (for backward compatibility)
	 * @return int|false History ID on success, false on failure
	 */
	public function start_generation($context, $voice = null) {
		// Initialize session
		$this->current_session = new AIPS_Generation_Session();
		$this->current_session->start($context, $voice);
		
		// Create history entry
		$template_id = null;
		if ($context instanceof AIPS_Generation_Context) {
			if ($context->get_type() === 'template') {
				$template_id = $context->get_id();
			}
		} elseif (isset($context->id)) {
			$template_id = $context->id;
		}
		
		$this->history_id = $this->repository->create(array(
			'template_id' => $template_id,
			'status' => 'processing',
		));
		
		return $this->history_id;
	}
	
	/**
	 * Get current history ID
	 *
	 * @return int|null
	 */
	public function get_history_id() {
		return $this->history_id;
	}
	
	/**
	 * Set history ID (for cases where history is created externally)
	 *
	 * @param int $history_id History entry ID
	 */
	public function set_history_id($history_id) {
		$this->history_id = $history_id;
	}
	
	/**
	 * Log an entry to the current generation history
	 *
	 * @param string $log_type Type of log entry
	 * @param array $details Log details
	 * @param int $history_type_id History type constant from AIPS_History_Type
	 * @return int|false Log entry ID on success, false on failure
	 */
	public function log($log_type, $details, $history_type_id = null) {
		if (!$this->history_id) {
			return false;
		}
		
		if ($history_type_id === null) {
			$history_type_id = AIPS_History_Type::LOG;
		}
		
		// Add timestamp if not present
		if (!isset($details['timestamp'])) {
			$details['timestamp'] = current_time('mysql');
		}
		
		return $this->repository->add_log_entry(
			$this->history_id,
			$log_type,
			$details,
			$history_type_id
		);
	}
	
	/**
	 * Log an AI request
	 *
	 * @param string $component Component name (title, content, excerpt, etc.)
	 * @param string $prompt AI prompt
	 * @param array $options AI options
	 * @return int|false Log entry ID on success, false on failure
	 */
	public function log_ai_request($component, $prompt, $options = array()) {
		if ($this->current_session) {
			$this->current_session->log_ai_call();
		}
		
		return $this->log(
			$component . '_request',
			array(
				'component' => $component,
				'prompt' => $prompt,
				'options' => $options,
			),
			AIPS_History_Type::AI_REQUEST
		);
	}
	
	/**
	 * Log an AI response
	 *
	 * @param string $component Component name (title, content, excerpt, etc.)
	 * @param string $response AI response
	 * @return int|false Log entry ID on success, false on failure
	 */
	public function log_ai_response($component, $response) {
		return $this->log(
			$component . '_response',
			array(
				'component' => $component,
				'response' => base64_encode($response),
			),
			AIPS_History_Type::AI_RESPONSE
		);
	}
	
	/**
	 * Log an error
	 *
	 * @param string $component Component or context where error occurred
	 * @param string $error_message Error message
	 * @param array $additional_data Optional additional error context
	 * @return int|false Log entry ID on success, false on failure
	 */
	public function log_error($component, $error_message, $additional_data = array()) {
		if ($this->current_session) {
			$this->current_session->add_error();
		}
		
		return $this->log(
			$component . '_error',
			array_merge(
				array(
					'component' => $component,
					'error' => $error_message,
				),
				$additional_data
			),
			AIPS_History_Type::ERROR
		);
	}
	
	/**
	 * Log an activity event
	 *
	 * @param string $event_type Event type (post_published, schedule_failed, etc.)
	 * @param string $event_status Event status (success, failed, draft)
	 * @param string $message Human-readable message
	 * @param array $metadata Optional additional metadata
	 * @return int|false Log entry ID on success, false on failure
	 */
	public function log_activity($event_type, $event_status, $message = '', $metadata = array()) {
		return $this->log(
			'activity_' . $event_type,
			array(
				'event_type' => $event_type,
				'event_status' => $event_status,
				'message' => $message,
				'metadata' => $metadata,
			),
			AIPS_History_Type::ACTIVITY
		);
	}
	
	/**
	 * Complete generation with failure
	 *
	 * Updates session and history to reflect failed state.
	 *
	 * @param string $error_message Error message
	 * @param string $component Optional component where failure occurred
	 * @return bool True on success, false on failure
	 */
	public function complete_with_failure($error_message, $component = 'generation') {
		// Complete session
		if ($this->current_session) {
			$this->current_session->complete(array(
				'success' => false,
				'error' => $error_message,
			));
		}
		
		// Update history
		if ($this->history_id) {
			$this->repository->update($this->history_id, array(
				'status' => 'failed',
				'error_message' => $error_message,
				'completed_at' => current_time('mysql'),
			));
		}
		
		// Log the error
		$this->log_error($component, $error_message);
		
		return true;
	}
	
	/**
	 * Complete generation with success
	 *
	 * Updates session and history to reflect successful state.
	 *
	 * @param int $post_id WordPress post ID
	 * @param string $title Generated title
	 * @param string $content Generated content
	 * @return bool True on success, false on failure
	 */
	public function complete_with_success($post_id, $title, $content) {
		// Complete session
		if ($this->current_session) {
			$this->current_session->complete(array(
				'success' => true,
				'post_id' => $post_id,
				'title' => $title,
			));
		}
		
		// Update history
		if ($this->history_id) {
			$this->repository->update($this->history_id, array(
				'status' => 'completed',
				'post_id' => $post_id,
				'generated_title' => $title,
				'generated_content' => $content,
				'completed_at' => current_time('mysql'),
			));
		}
		
		return true;
	}
	
	/**
	 * Get all history entries with optional filtering
	 *
	 * @param array $filters Optional filters (status, type, date range, etc.)
	 * @return array History entries
	 */
	public function get_history($filters = array()) {
		return $this->repository->get_history($filters);
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
		global $wpdb;
		
		$where_clauses = array("history_type_id = " . AIPS_History_Type::ACTIVITY);
		$where_args = array();
		
		// Event type filter
		if (!empty($filters['event_type'])) {
			$where_clauses[] = "details LIKE %s";
			$where_args[] = '%"event_type":"' . $wpdb->esc_like($filters['event_type']) . '"%';
		}
		
		// Event status filter
		if (!empty($filters['event_status'])) {
			$where_clauses[] = "details LIKE %s";
			$where_args[] = '%"event_status":"' . $wpdb->esc_like($filters['event_status']) . '"%';
		}
		
		// Search filter
		if (!empty($filters['search'])) {
			$search_term = '%' . $wpdb->esc_like($filters['search']) . '%';
			$where_clauses[] = "(log_type LIKE %s OR details LIKE %s)";
			$where_args[] = $search_term;
			$where_args[] = $search_term;
		}
		
		$where_sql = implode(' AND ', $where_clauses);
		$where_args[] = $limit;
		$where_args[] = $offset;
		
		$history_log_table = $wpdb->prefix . 'aips_history_log';
		$history_table = $wpdb->prefix . 'aips_history';
		
		$sql = "SELECT hl.*, h.post_id, h.template_id 
		        FROM {$history_log_table} hl 
		        LEFT JOIN {$history_table} h ON hl.history_id = h.id 
		        WHERE $where_sql 
		        ORDER BY hl.timestamp DESC 
		        LIMIT %d OFFSET %d";
		
		if (empty($where_args)) {
			return $wpdb->get_results($sql);
		}
		
		return $wpdb->get_results($wpdb->prepare($sql, $where_args));
	}
	
	/**
	 * Get current session (for backward compatibility)
	 *
	 * @return AIPS_Generation_Session|null
	 */
	public function get_current_session() {
		return $this->current_session;
	}
}
