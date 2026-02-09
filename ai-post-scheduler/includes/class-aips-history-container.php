<?php
/**
 * History Container
 *
 * Represents a container for logs related to a specific process.
 * Each History object has a unique UUID and can append various types of logs.
 *
 * @package AI_Post_Scheduler
 * @since 2.0.0
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_History_Container
 *
 * A container for tracking and logging a specific process or activity.
 */
class AIPS_History_Container {
	
	/**
	 * @var string Unique identifier for this history container
	 */
	private $uuid;
	
	/**
	 * @var int|null Database ID once persisted
	 */
	private $history_id;
	
	/**
	 * @var string Type of history (e.g., 'post_generation', 'topic_generation')
	 */
	private $type;
	
	/**
	 * @var array Metadata for this history container
	 */
	private $metadata;
	
	/**
	 * @var AIPS_History_Repository Repository for database operations
	 */
	private $repository;
	
	/**
	 * @var AIPS_Generation_Session|null Optional session tracker
	 */
	private $session;
	
	/**
	 * @var bool Whether this history has been persisted to database
	 */
	private $is_persisted;
	
	/**
	 * Initialize a new History container
	 *
	 * @param AIPS_History_Repository $repository Repository instance
	 * @param string $type Type of history container
	 * @param array $metadata Optional metadata
	 */
	public function __construct($repository, $type, $metadata = array()) {
		$this->uuid = $this->generate_uuid();
		$this->history_id = null;
		$this->type = $type;
		$this->metadata = $metadata;
		$this->repository = $repository;
		$this->session = null;
		$this->is_persisted = false;
		
		// Persist to database immediately
		$this->persist();
	}
	
	/**
	 * Generate a unique UUID for this history container
	 *
	 * @return string UUID
	 */
	private function generate_uuid() {
		// Use WordPress's unique ID generation if available
		if (function_exists('wp_generate_uuid4')) {
			return wp_generate_uuid4();
		}
		
		// Fallback to custom UUID generation
		return sprintf(
			'%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
			mt_rand(0, 0xffff), mt_rand(0, 0xffff),
			mt_rand(0, 0xffff),
			mt_rand(0, 0x0fff) | 0x4000,
			mt_rand(0, 0x3fff) | 0x8000,
			mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
		);
	}
	
	/**
	 * Persist this history container to the database
	 *
	 * @return bool True on success, false on failure
	 */
	private function persist() {
		if ($this->is_persisted) {
			return true;
		}
		
		$data = array_merge(
			array(
				'uuid' => $this->uuid,
				'status' => 'processing',
			),
			$this->metadata
		);
		
		$this->history_id = $this->repository->create($data);
		
		if ($this->history_id) {
			$this->is_persisted = true;
			return true;
		}
		
		return false;
	}
	
	/**
	 * Get the UUID for this history container
	 *
	 * @return string UUID
	 */
	public function get_uuid() {
		return $this->uuid;
	}
	
	/**
	 * Get the database ID for this history container
	 *
	 * @return int|null Database ID or null if not persisted
	 */
	public function get_id() {
		return $this->history_id;
	}
	
	/**
	 * Record a log entry to this history container.
	 *
	 * This is the primary method for appending logs to a history container.
	 *
	 * @param string $log_type Type of log (e.g., 'activity', 'ai_request', 'ai_response', 'error', 'debug')
	 * @param string $message Human-readable message
	 * @param array $context Optional context data. Can include:
	 *                       - 'input': Input data for the operation
	 *                       - 'output': Output/result data  
	 *                       - 'prompt': AI prompts
	 *                       - 'response': AI responses
	 *                       - 'error': Error details
	 *                       - 'options': Configuration options
	 *                       - Any custom keys
	 * @return int|false Log entry ID on success, false on failure
	 */
	public function record($log_type, $message, $context = array()) {
		if (!$this->is_persisted) {
			return false;
		}
		
		// Determine history type ID based on log_type
		$history_type_id = $this->map_log_type_to_history_type($log_type);
		
		// Build details array
		$details = array(
			'message' => $message,
			'timestamp' => current_time('mysql'),
		);
		
		// Handle 'input' key in context
		if (isset($context['input'])) {
			$input = $context['input'];
			$details['input'] = is_array($input) || is_object($input) ? $input : array('value' => $input);
			unset($context['input']);
		}
		
		// Handle 'output' key in context
		if (isset($context['output'])) {
			$output = $context['output'];
			// Base64 encode if it's a large string (likely AI response)
			if (is_string($output) && strlen($output) > 500) {
				$details['output'] = base64_encode($output);
				$details['output_encoded'] = true;
			} else {
				$details['output'] = is_array($output) || is_object($output) ? $output : array('value' => $output);
			}
			unset($context['output']);
		}
		
		// Merge remaining context
		if (!empty($context)) {
			$details['context'] = $context;
		}
		
		// Track AI calls in session if applicable
		if ($this->session && $log_type === 'ai_request') {
			$this->session->log_ai_call();
		}
		
		// Track errors in session if applicable
		if ($this->session && $log_type === 'error') {
			$this->session->add_error();
		}
		
		// Persist to database
		$log_id = $this->repository->add_log_entry(
			$this->history_id,
			$log_type,
			$details,
			$history_type_id
		);
		
		// Write to PHP error log after successful DB insert (when WP_DEBUG enabled)
		// Note: Input/output are in details array, error_log shows remaining context only
		if ($log_id && defined('WP_DEBUG') && WP_DEBUG) {
			$log_entry = sprintf(
				'[AIPS History] [%s] %s',
				strtoupper($log_type),
				$message
			);
			if (!empty($details['context'])) {
				$log_entry .= ' | Context: ' . wp_json_encode($details['context']);
			}
			error_log($log_entry);
		}
		
		return $log_id;
	}
	
	/**
	 * Map a log type string to a history type ID constant
	 *
	 * @param string $log_type Log type string
	 * @return int History type ID
	 */
	private function map_log_type_to_history_type($log_type) {
		$map = array(
			'activity' => AIPS_History_Type::ACTIVITY,
			'ai_request' => AIPS_History_Type::AI_REQUEST,
			'ai_response' => AIPS_History_Type::AI_RESPONSE,
			'error' => AIPS_History_Type::ERROR,
			'warning' => AIPS_History_Type::WARNING,
			'info' => AIPS_History_Type::INFO,
			'debug' => AIPS_History_Type::DEBUG,
			'log' => AIPS_History_Type::LOG,
		);
		
		return isset($map[$log_type]) ? $map[$log_type] : AIPS_History_Type::LOG;
	}
	
	/**
	 * Log an error with comprehensive context
	 * 
	 * Standardized error logging that captures all relevant context for debugging.
	 * Automatically includes PHP error state, memory usage, and performance metrics.
	 * Note: Automatic context values take precedence and will override any conflicting caller keys.
	 *
	 * @param string $message Human-readable error message
	 * @param array $context Error context (e.g., component, error_code, attempt, input, output)
	 * @param WP_Error|null $wp_error Optional WP_Error object for additional context
	 * @return int|false Log entry ID on success, false on failure
	 */
	public function record_error($message, $context = array(), $wp_error = null) {
		// Build automatic error tracking context
		$auto_context = array(
			'timestamp' => microtime(true),
			'php_error' => error_get_last(),
			'memory_usage' => memory_get_usage(true),
			'memory_peak' => memory_get_peak_usage(true),
		);
		
		// Merge with caller context - automatic values override any conflicting caller keys
		$context = array_merge($context, $auto_context);
		
		// Add WP_Error details if provided
		if ($wp_error && is_wp_error($wp_error)) {
			$context['wp_error_code'] = $wp_error->get_error_code();
			$context['wp_error_message'] = $wp_error->get_error_message();
			$context['wp_error_data'] = $wp_error->get_error_data();
		}
		
		return $this->record('error', $message, $context);
	}
	
	/**
	 * Log a user-initiated action
	 * 
	 * Tracks manual operations triggered by users (vs automated/scheduled operations).
	 * Automatically includes user context.
	 * Note: Automatic context values take precedence and will override any conflicting caller keys.
	 *
	 * @param string $action Action type (e.g., 'manual_generation', 'bulk_delete', 'manual_publish')
	 * @param string $message Human-readable description
	 * @param array $context Data specific to this action (can include input, output, or any custom keys)
	 * @return int|false Log entry ID on success, false on failure
	 */
	public function record_user_action($action, $message, $context = array()) {
		// Build automatic user action context
		$auto_context = array(
			'action_type' => $action,
			'user_id' => get_current_user_id(),
			'user_login' => wp_get_current_user()->user_login,
			'timestamp' => microtime(true),
			'source' => 'manual_ui',
		);
		
		// Merge with caller context - automatic values override any conflicting caller keys
		$context = array_merge($context, $auto_context);
		
		return $this->record('activity', $message, $context);
	}
	
	/**
	 * Complete this history container with success
	 *
	 * @param array $result_data Result data (e.g., post_id, title, content)
	 * @return bool True on success, false on failure
	 */
	public function complete_success($result_data = array()) {
		if (!$this->is_persisted) {
			return false;
		}
		
		// Complete session if exists
		if ($this->session) {
			$this->session->complete(array_merge(
				array('success' => true),
				$result_data
			));
		}
		
		// Update history status
		$update_data = array_merge(
			array(
				'status' => 'completed',
				'completed_at' => current_time('mysql'),
			),
			$result_data
		);
		
		return $this->repository->update($this->history_id, $update_data) !== false;
	}
	
	/**
	 * Complete this history container with failure
	 *
	 * @param string $error_message Error message
	 * @param array $context Optional additional error context
	 * @return bool True on success, false on failure
	 */
	public function complete_failure($error_message, $context = array()) {
		if (!$this->is_persisted) {
			return false;
		}
		
		// Complete session if exists
		if ($this->session) {
			$this->session->complete(array(
				'success' => false,
				'error' => $error_message,
			));
		}
		
		// Log the error
		$this->record('error', $error_message, $context);
		
		// Update history status
		return $this->repository->update($this->history_id, array(
			'status' => 'failed',
			'error_message' => $error_message,
			'completed_at' => current_time('mysql'),
		)) !== false;
	}
	
	/**
	 * Attach a generation session to this history container
	 *
	 * @param AIPS_Generation_Context|object $context Generation context
	 * @param object|null $voice Optional voice object
	 * @return self For method chaining
	 */
	public function with_session($context, $voice = null) {
		$this->session = new AIPS_Generation_Session();
		$this->session->start($context, $voice);
		return $this;
	}
	
	/**
	 * Get the session tracker if attached
	 *
	 * @return AIPS_Generation_Session|null
	 */
	public function get_session() {
		return $this->session;
	}
}
