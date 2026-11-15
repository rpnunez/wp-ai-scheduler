<?php
/**
 * Generation Session Tracker
 *
 * Encapsulates runtime tracking data for a single post generation session.
 * This class provides a clear separation between:
 * - Runtime session tracking (ephemeral, in-memory)
 * - Persistent history records (stored in database via AIPS_History_Repository)
 *
 * The session tracker captures information about the generation process,
 * including generation context, AI calls, errors, timing, and results.
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
 * 2. Populated with context, AI calls, and errors during generation
 * 3. Completed with final result
 * 4. Discarded after generation completes
 *
 * Key Differences from History:
 * - Session: Ephemeral runtime tracker (exists during one request)
 * - History: Persistent database record (exists across all requests)
 * - Session: Contains runtime operational metrics (AI calls, errors, duration)
 * - History: Contains persistent record + log entries
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
	 * @var array|null Generation context data (type, id, name, etc.)
	 */
	private $context;

	/**
	 * @var int Counter for AI calls made during generation
	 */
	private $ai_call_count;

	/**
	 * @var int Counter for errors encountered during generation
	 */
	private $error_count;

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
		$this->context = null;
		$this->ai_call_count = 0;
		$this->error_count = 0;
	}

	/**
	 * Start a new generation session.
	 *
	 * Supports both legacy template-based calls and new context-based calls.
	 *
	 * @param object|AIPS_Generation_Context $template_or_context Template object (legacy) or Generation Context.
	 * @param object|null $voice Optional voice object (legacy, ignored if context is provided).
	 * @return void
	 */
	public function start($template_or_context, $voice = null) {
		$this->reset();
		$this->started_at = AIPS_DateTime::now()->toMysql();

		// Check if we're using the context-based approach
		if ($template_or_context instanceof AIPS_Generation_Context) {
			$this->context = $template_or_context->to_array();
		} else {
			// Legacy template-based approach (for backward compatibility)
			$template = $template_or_context;

			$this->context = array(
				'type' => 'template',
				'id'   => isset($template->id) ? $template->id : null,
				'name' => isset($template->name) ? $template->name : '',
			);

			if ($voice && isset($voice->id)) {
				$this->context['voice_id'] = $voice->id;
			}
		}
	}

	/**
	 * Log an AI call made during generation.
	 *
	 * @return void
	 */
	public function log_ai_call() {
		$this->ai_call_count++;
	}

	/**
	 * Add an error to the session.
	 *
	 * @return void
	 */
	public function add_error() {
		$this->error_count++;
	}

	/**
	 * Complete the generation session with result.
	 *
	 * @param array $result Final result data (success/failure + metadata).
	 * @return void
	 */
	public function complete($result = array()) {
		$this->completed_at = AIPS_DateTime::now()->toMysql();
	}

	/**
	 * Get the duration of the generation session in seconds.
	 *
	 * @return int|null Duration in seconds or null if not completed.
	 */
	public function get_duration() {
		if (!$this->started_at || !$this->completed_at) {
			return null;
		}

		$start = AIPS_DateTime::fromMysqlOrNull($this->started_at);
		$end = AIPS_DateTime::fromMysqlOrNull($this->completed_at);

		if (!$start || !$end) {
			return null;
		}

		return $end->timestamp() - $start->timestamp();
	}

	/**
	 * Get the number of AI calls made during the session.
	 *
	 * @return int Number of AI calls.
	 */
	public function get_ai_call_count() {
		return $this->ai_call_count;
	}

	/**
	 * Get the number of errors encountered during the session.
	 *
	 * @return int Number of errors.
	 */
	public function get_error_count() {
		return $this->error_count;
	}

	/**
	 * Get the generation context data.
	 *
	 * @return array|null Context data or null if not started.
	 */
	public function get_context() {
		return $this->context;
	}
}
