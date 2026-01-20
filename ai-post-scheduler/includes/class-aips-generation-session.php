<?php
/**
 * Generation Session Tracker
 *
 * Encapsulates runtime tracking data for a single post generation session.
 * This class provides a clear separation between:
 * - Runtime session tracking (ephemeral, in-memory)
 * - Persistent history records (stored in database via AIPS_History_Repository)
 *
 * The session tracker captures detailed information about the generation process,
 * including all AI calls, errors, timing, and results. This data can be serialized
 * and stored in the History database table's `generation_log` JSON field.
 *
 * @package AI_Post_Scheduler
 * @since 1.6.0
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Generation_Session
 *
 * Tracks the lifecycle and details of a single post generation attempt.
 * 
 * Lifecycle:
 * 1. Created at the start of generation
 * 2. Populated with template, voice, AI calls, and errors during generation
 * 3. Completed with final result
 * 4. Serialized to JSON and stored in History database
 * 5. Discarded after generation completes
 *
 * Key Differences from History:
 * - Session: Ephemeral runtime tracker (exists during one request)
 * - History: Persistent database record (exists across all requests)
 * - Session: Contains all detailed diagnostic data
 * - History: Contains summary + serialized session as JSON
 */
class AIPS_Generation_Session {

	/**
	 * @var string|null Timestamp when generation started (MySQL format)
	 */
	private $started_at;

	/**
	 * @var string|null Timestamp when generation completed (MySQL format)
	 */
	private $completed_at;

	/**
	 * @var array|null Template configuration used for generation
	 */
	private $template;

	/**
	 * @var array|null Voice configuration used for generation (optional)
	 */
	private $voice;

	/**
	 * @var array Collection of all AI calls made during generation
	 */
	private $ai_calls;

	/**
	 * @var array Collection of all errors encountered during generation
	 */
	private $errors;

	/**
	 * @var array|null Final result of the generation (success/failure + data)
	 */
	private $result;

	/**
	 * Initialize a new generation session.
	 */
	public function __construct() {
		$this->reset();
	}

	/**
	 * Reset the session to initial state.
	 *
	 * @return void
	 */
	private function reset() {
		$this->started_at = null;
		$this->completed_at = null;
		$this->template = null;
		$this->voice = null;
		$this->ai_calls = array();
		$this->errors = array();
		$this->result = null;
	}

	/**
	 * Start a new generation session.
	 *
	 * @param object      $template Template object used for generation.
	 * @param object|null $voice    Optional voice object used for generation.
	 * @return void
	 */
	public function start($template, $voice = null) {
		$this->reset();
		$this->started_at = current_time('mysql');

		// Store template configuration
		$this->template = array(
			'id' => $template->id,
			'name' => $template->name,
			'prompt_template' => $template->prompt_template,
			'title_prompt' => $template->title_prompt,
			'post_status' => $template->post_status,
			'post_category' => $template->post_category,
			'post_tags' => $template->post_tags,
			'post_author' => $template->post_author,
			'post_quantity' => $template->post_quantity,
			'generate_featured_image' => $template->generate_featured_image,
			'image_prompt' => $template->image_prompt,
		);

		// Store voice configuration if provided
		if ($voice) {
			$this->voice = array(
				'id' => $voice->id,
				'name' => $voice->name,
				'title_prompt' => $voice->title_prompt,
				'content_instructions' => $voice->content_instructions,
				'excerpt_instructions' => $voice->excerpt_instructions,
			);
		}
	}

	/**
	 * Log an AI call made during generation.
	 *
	 * @param string      $type     Type of AI call (e.g., 'title', 'content', 'excerpt', 'featured_image').
	 * @param string      $prompt   The prompt sent to AI.
	 * @param string|null $response The AI response, if successful.
	 * @param array       $options  Options used for the call.
	 * @param string|null $error    Error message, if call failed.
	 * @return void
	 */
	public function log_ai_call($type, $prompt, $response = null, $options = array(), $error = null) {
		// SECURITY: Redact sensitive options before storing in history
		if (class_exists('AIPS_Logger')) {
			$options = AIPS_Logger::redact_context($options);
		}

		$call_log = array(
			'type' => $type,
			'timestamp' => current_time('mysql'),
			'request' => array(
				'prompt' => $prompt,
				'options' => $options,
			),
			'response' => array(
				'success' => $error === null,
				'content' => $response,
				'error' => $error,
			),
		);

		$this->ai_calls[] = $call_log;

		// If there's an error, also add to errors collection
		if ($error) {
			$this->add_error($type, $error);
		}
	}

	/**
	 * Add an error to the session.
	 *
	 * @param string $type    Type of error (e.g., 'content', 'featured_image').
	 * @param string $message Error message.
	 * @return void
	 */
	public function add_error($type, $message) {
		$this->errors[] = array(
			'type' => $type,
			'timestamp' => current_time('mysql'),
			'message' => $message,
		);
	}

	/**
	 * Complete the generation session with result.
	 *
	 * @param array $result Final result data (success/failure + metadata).
	 * @return void
	 */
	public function complete($result) {
		$this->completed_at = current_time('mysql');
		$this->result = $result;
	}

	/**
	 * Get the session data as an array.
	 *
	 * @return array Session data structure.
	 */
	public function to_array() {
		return array(
			'started_at' => $this->started_at,
			'completed_at' => $this->completed_at,
			'template' => $this->template,
			'voice' => $this->voice,
			'ai_calls' => $this->ai_calls,
			'errors' => $this->errors,
			'result' => $this->result,
		);
	}

	/**
	 * Serialize the session to JSON for storage.
	 *
	 * @return string JSON-encoded session data.
	 */
	public function to_json() {
		return wp_json_encode($this->to_array());
	}

	/**
	 * Get the duration of the generation session in seconds.
	 *
	 * @return float|null Duration in seconds, or null if not completed.
	 */
	public function get_duration() {
		if (!$this->started_at || !$this->completed_at) {
			return null;
		}

		$start = strtotime($this->started_at);
		$end = strtotime($this->completed_at);

		return $end - $start;
	}

	/**
	 * Get the number of AI calls made during the session.
	 *
	 * @return int Number of AI calls.
	 */
	public function get_ai_call_count() {
		return count($this->ai_calls);
	}

	/**
	 * Get the number of errors encountered during the session.
	 *
	 * @return int Number of errors.
	 */
	public function get_error_count() {
		return count($this->errors);
	}

	/**
	 * Check if the generation was successful.
	 *
	 * @return bool True if successful, false if failed or not completed.
	 */
	public function was_successful() {
		if (!$this->result) {
			return false;
		}

		return isset($this->result['success']) && $this->result['success'] === true;
	}

	/**
	 * Get all AI calls made during the session.
	 *
	 * @return array Array of AI call logs.
	 */
	public function get_ai_calls() {
		return $this->ai_calls;
	}

	/**
	 * Get all errors encountered during the session.
	 *
	 * @return array Array of error logs.
	 */
	public function get_errors() {
		return $this->errors;
	}

	/**
	 * Get the template configuration used.
	 *
	 * @return array|null Template configuration or null if not started.
	 */
	public function get_template() {
		return $this->template;
	}

	/**
	 * Get the voice configuration used.
	 *
	 * @return array|null Voice configuration or null if not used.
	 */
	public function get_voice() {
		return $this->voice;
	}

	/**
	 * Get the result of the generation.
	 *
	 * @return array|null Result data or null if not completed.
	 */
	public function get_result() {
		return $this->result;
	}

	/**
	 * Get the start timestamp.
	 *
	 * @return string|null MySQL timestamp or null if not started.
	 */
	public function get_started_at() {
		return $this->started_at;
	}

	/**
	 * Get the completion timestamp.
	 *
	 * @return string|null MySQL timestamp or null if not completed.
	 */
	public function get_completed_at() {
		return $this->completed_at;
	}
}
