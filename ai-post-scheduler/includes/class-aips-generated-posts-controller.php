<?php
/**
 * Generated Posts Controller
 *
 * Handles the "Generated Posts" admin page showing all posts created by this plugin.
 * Provides detailed session view with AI calls, logs, and activity data.
 *
 * @package AI_Post_Scheduler
 * @since 2.0.0
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Generated_Posts_Controller
 *
 * Manages the Generated Posts admin interface and AJAX endpoints for viewing post generation sessions.
 */
class AIPS_Generated_Posts_Controller {
	/**
	 * Maximum explainability prompt preview length.
	 */
	private const EXPLAINABILITY_PROMPT_PREVIEW_MAX_LENGTH = 800;
	
	/**
	 * @var AIPS_History_Repository Repository for database operations
	 */
	private $history_repository;
	
	/**
	 * @var AIPS_Schedule_Repository Schedule repository for schedule data
	 */
	private $schedule_repository;
	
	/**
	 * @var AIPS_Post_Review_Repository Repository for post review data
	 */
	private $post_review_repository;
	
	/**
	 * @var array Cache for template names to avoid N+1 queries
	 */
	private $template_cache = array();
	
	/**
	 * @var array Cache for author names to avoid N+1 queries
	 */
	private $author_cache = array();
	
	/**
	 * @var array Cache for topic titles to avoid N+1 queries
	 */
	private $topic_cache = array();
	
	/**
	 * Initialize the controller
	 */
	public function __construct() {
		$this->history_repository = new AIPS_History_Repository();
		$this->schedule_repository = new AIPS_Schedule_Repository();
		$this->post_review_repository = new AIPS_Post_Review_Repository();
		
		// Register AJAX handlers
		add_action('wp_ajax_aips_get_post_session', array($this, 'ajax_get_post_session'));
		add_action('wp_ajax_aips_get_session_json', array($this, 'ajax_get_session_json'));
		// AJAX endpoint to download the session JSON as a file
		add_action('wp_ajax_aips_download_session_json', array($this, 'ajax_download_session_json'));
	}
	
	/**
	 * Render the Generated Posts admin page
	 */
	public function render_page() {
		// Use separate pagination parameters for each tab
		$generated_page = isset($_GET['generated_paged']) ? absint($_GET['generated_paged']) : 1;
		$review_page = isset($_GET['review_paged']) ? absint($_GET['review_paged']) : 1;
		$partial_page = isset($_GET['partial_paged']) ? absint($_GET['partial_paged']) : 1;
		$search_query = isset($_GET['s']) ? sanitize_text_field(wp_unslash($_GET['s'])) : '';
		$author_id = isset($_GET['author_id']) ? absint($_GET['author_id']) : 0;
		$template_id = isset($_GET['template_id']) ? absint($_GET['template_id']) : 0;

		// Get completed history entries with post IDs (for Generated Posts tab)
		$history = $this->history_repository->get_history(array(
			'page' => $generated_page,
			'per_page' => 20,
			'status' => 'completed',
			'search' => $search_query,
			'author_id' => $author_id,
			'template_id' => $template_id,
			'fields' => 'list', // Explicitly use lightweight list fields for UI listing
		));
		
		// Get schedule data for each post
		$posts_data = array();
		foreach ($history['items'] as $item) {
			if (!$item->post_id) {
				continue;
			}
			
			$post = get_post($item->post_id);
			if (!$post) {
				continue;
			}
			
			// Get most recent schedule for this template (if exists)
			$schedule = null;
			if ($item->template_id) {
				$schedules = $this->schedule_repository->get_by_template($item->template_id);
				// get_by_template returns multiple schedules, get the first one
				$schedule = !empty($schedules) ? $schedules[0] : null;
			}
			
			// Format source information
			$source = $this->format_source($item);

			// Use a GMT timestamp to avoid timezone skew when formatting relative time.
			$published_timestamp = (int) get_post_time('U', true, $post);
			
			$posts_data[] = array(
				'history_id' => $item->id,
				'post_id' => $item->post_id,
				'title' => $post->post_title,
				'date_generated' => AIPS_DateTime::formatRelativeOrAbsolute($item->created_at, get_option('date_format') . ' ' . get_option('time_format')),
				'date_published' => AIPS_DateTime::formatRelativeOrAbsolute($published_timestamp, get_option('date_format') . ' ' . get_option('time_format')),
				'date_scheduled' => AIPS_DateTime::formatRelativeOrAbsolute($schedule ? $schedule->next_run : null, get_option('date_format') . ' ' . get_option('time_format')),
				'edit_link' => esc_url_raw(get_edit_post_link($item->post_id)),
				'source' => $source,
			);
		}
		
		// Get draft posts for Post Review tab
		$draft_posts = $this->post_review_repository->get_draft_posts(array(
			'page' => $review_page,
			'search' => $search_query,
			'template_id' => $template_id,
		));

		// Pre-format dates for draft posts
		if (!empty($draft_posts['items'])) {
			foreach ($draft_posts['items'] as $item) {
				$item->created_at_formatted = AIPS_DateTime::formatRelativeOrAbsolute($item->created_at, get_option('date_format') . ' ' . get_option('time_format'));
			}
		}

		$partial_generations = $this->history_repository->get_partial_generations(array(
			'page' => $partial_page,
			'per_page' => 20,
			'search' => $search_query,
			'author_id' => $author_id,
			'template_id' => $template_id,
		));

		$partial_posts_data = array();
		foreach ($partial_generations['items'] as $item) {
			if (!$item->post_id) {
				continue;
			}

			$post = get_post($item->post_id);
			if (!$post) {
				continue;
			}

			$partial_posts_data[] = array(
				'history_id' => $item->id,
				'post_id' => $item->post_id,
				'title' => $post->post_title,
			'date_generated' => AIPS_DateTime::formatRelativeOrAbsolute($item->created_at, get_option('date_format') . ' ' . get_option('time_format')),
			'date_updated' => AIPS_DateTime::formatRelativeOrAbsolute($item->post_modified, get_option('date_format') . ' ' . get_option('time_format')),
				'edit_link' => esc_url_raw(get_edit_post_link($item->post_id)),
				'post_status' => $item->post_status,
				'is_currently_incomplete' => ('true' === (string) $item->is_currently_incomplete),
				'source' => $this->format_source($item),
				'missing_components' => $this->get_missing_components($item->component_statuses),
			);
		}
		
		// Pass separate page variables for each tab
		$current_page = $generated_page; // For Generated Posts tab
		$review_current_page = $review_page; // For Pending Review tab
		$partial_current_page = $partial_page; // For Partial Generations tab
		
		// Get templates for filter dropdown
		$template_repository = new AIPS_Template_Repository();
		$templates = $template_repository->get_all();

		// Get authors for filter dropdown
		$authors_repository = new AIPS_Authors_Repository();
		$authors = $authors_repository->get_all();
		
		// Get globally-initialized Post Review handler
		global $aips_post_review_handler;
		$post_review_handler = isset($aips_post_review_handler) ? $aips_post_review_handler : $this->post_review_repository;
		
		// Make controller available to template for formatting
		$controller = $this;
		
		include AIPS_PLUGIN_DIR . 'templates/admin/content.php';
	}

	/**
	 * Convert stored component status JSON into a list of failed component labels.
	 *
	 * @param string|null $component_statuses_json Stored component status JSON.
	 * @return array List of missing component labels.
	 */
	public function get_missing_components($component_statuses_json) {
		$labels = array(
			'post_title' => __('Title', 'ai-post-scheduler'),
			'post_excerpt' => __('Excerpt', 'ai-post-scheduler'),
			'post_content' => __('Content', 'ai-post-scheduler'),
			'featured_image' => __('Featured Image', 'ai-post-scheduler'),
		);

		$decoded = json_decode((string) $component_statuses_json, true);
		if (!is_array($decoded)) {
			return array();
		}

		$missing = array();
		foreach ($labels as $key => $label) {
			if (array_key_exists($key, $decoded) && !$decoded[$key]) {
				$missing[] = $label;
			}
		}

		return $missing;
	}

	/**
	 * Get a display label for a WordPress post status.
	 *
	 * @param string $post_status Post status slug.
	 * @return string
	 */
	public function format_post_status($post_status) {
		$status_object = get_post_status_object($post_status);
		if ($status_object && !empty($status_object->label)) {
			return $status_object->label;
		}

		return ucfirst(str_replace('_', ' ', (string) $post_status));
	}
	
	/**
	 * AJAX handler to get detailed session data for a post
	 */
	public function ajax_get_post_session() {
		if ( ! check_ajax_referer('aips_ajax_nonce', 'nonce', false) ) {
			AIPS_Ajax_Response::error(__('Invalid nonce.', 'ai-post-scheduler'));
		}
		
		if (!current_user_can('manage_options')) {
			AIPS_Ajax_Response::permission_denied();
		}
		
		$history_id = isset($_POST['history_id']) ? absint($_POST['history_id']) : 0;
		
		if (!$history_id) {
			AIPS_Ajax_Response::error(__('Invalid history ID.', 'ai-post-scheduler'));
		}
		
		// Get history item with all logs
		$history_item = $this->history_repository->get_by_id($history_id);
		
		if (!$history_item) {
			AIPS_Ajax_Response::error(__('History item not found.', 'ai-post-scheduler'));
		}
		
		// Organize logs by type
		$logs = array();
		$ai_calls = array();
		$explainability_entries = array();
		
		foreach ($history_item->log as $log_entry) {
			$details = json_decode($log_entry->details, true);
			if (!is_array($details)) {
				$details = array();
			}
			
			$explainability_entries[] = array(
				'type_id' => isset($log_entry->history_type_id) ? (int) $log_entry->history_type_id : AIPS_History_Type::LOG,
				'log_type' => (string) $log_entry->log_type,
				'timestamp' => (string) $log_entry->timestamp,
				'details' => $details,
			);
			
			// Categorize based on history_type_id
			$type_id = isset($log_entry->history_type_id) ? (int) $log_entry->history_type_id : AIPS_History_Type::LOG;
			
			switch ($type_id) {
				case AIPS_History_Type::AI_REQUEST:
				case AIPS_History_Type::AI_RESPONSE:
					// Group AI requests and responses together by component
					$component_type = isset($details['context']['component']) ? $details['context']['component'] : 'unknown';
					
					if (!isset($ai_calls[$component_type])) {
						$ai_calls[$component_type] = array(
							'type' => $component_type,
							'label' => ucfirst(str_replace('_', ' ', $component_type)),
							'request' => null,
							'response' => null,
						);
					}
					
					if ($type_id === AIPS_History_Type::AI_REQUEST) {
						$ai_calls[$component_type]['request'] = $details;
					} else {
						// Decode base64-encoded AI output if flagged
						if (isset($details['output']) && !empty($details['output_encoded'])) {
							$details['output'] = base64_decode($details['output']);
						}
						$ai_calls[$component_type]['response'] = $details;
					}
					break;
					
				case AIPS_History_Type::ERROR:
				case AIPS_History_Type::WARNING:
				case AIPS_History_Type::LOG:
				case AIPS_History_Type::INFO:
				case AIPS_History_Type::DEBUG:
					$logs[] = array(
						'type' => AIPS_History_Type::get_label($type_id),
						'type_id' => $type_id,
						'timestamp' => $log_entry->timestamp,
						'log_type' => $log_entry->log_type,
						'details' => $details,
					);
					break;
			}
		}
		
		// Convert ai_calls to indexed array for easier JS iteration
		$ai_calls = array_values($ai_calls);
		
		// Collect regenerations / revisions per component
		$component_revisions = array();
		if ($history_item->post_id) {
			$components = array('title', 'excerpt', 'content', 'featured_image');
			foreach ($components as $component) {
				$component_revisions[$component] = $this->history_repository->get_component_revisions(
					absint($history_item->post_id),
					$component,
					20
				);
			}
		}
		
		AIPS_Ajax_Response::success(array(
			'history' => array(
				'id' => $history_item->id,
				'status' => $history_item->status,
				'created_at' => $history_item->created_at,
				'completed_at' => $history_item->completed_at,
				'generated_title' => $history_item->generated_title,
				'post_id' => $history_item->post_id,
			),
			'logs' => $logs,
			'ai_calls' => $ai_calls,
			'component_revisions' => $component_revisions,
			'explainability' => $this->build_explainability_payload($history_item, $explainability_entries, $ai_calls, $component_revisions),
		));
	}
	
	/**
	 * Build a structured explainability payload for the View Session modal.
	 *
	 * @param object $history_item         History item.
	 * @param array  $entries              Parsed log entries.
	 * @param array  $ai_calls             AI request/response calls grouped by component.
	 * @param array  $component_revisions  Regeneration history grouped by component.
	 * @return array
	 */
	private function build_explainability_payload($history_item, $entries, $ai_calls, $component_revisions) {
		$redaction_count = 0;
		$redacted_ai_calls = $this->redact_sensitive_data($ai_calls, $redaction_count);
		$redacted_revisions = $this->redact_sensitive_data($component_revisions, $redaction_count);
		$redacted_entries = $this->redact_sensitive_data($entries, $redaction_count);
		
		$timeline = array();
		$sources_considered = array();
		$sources_used = array();
		$validation_checks = array();
		$transformations = array();
		
		foreach ($redacted_entries as $entry) {
			$timeline[] = array(
				'stage' => $this->map_log_to_timeline_stage($entry),
				'timestamp' => isset($entry['timestamp']) ? $entry['timestamp'] : '',
				'log_type' => isset($entry['log_type']) ? $entry['log_type'] : '',
				'summary' => $this->build_entry_summary($entry),
			);
			
			$entry_sources = $this->extract_sources_from_entry($entry);
			if (!empty($entry_sources)) {
				$sources_considered = array_merge($sources_considered, $entry_sources);
				if ($this->entry_indicates_source_usage($entry)) {
					$sources_used = array_merge($sources_used, $entry_sources);
				}
			}
			
			$check = $this->extract_validation_check($entry);
			if (!empty($check)) {
				$validation_checks[] = $check;
			}
			
			$transform = $this->extract_transformation($entry);
			if (!empty($transform)) {
				$transformations[] = $transform;
			}
		}
		
		$sources_considered = $this->unique_multidimensional($sources_considered);
		$sources_used = $this->unique_multidimensional($sources_used);
		
		$attempt_count = 1;
		$retry_count = 0;
		foreach ($redacted_revisions as $component_revisions_list) {
			if (is_array($component_revisions_list)) {
				$retry_count += count($component_revisions_list);
			}
		}
		if ($retry_count > 0) {
			$attempt_count += $retry_count;
		}
		
		$model_runs = array();
		foreach ($redacted_ai_calls as $call) {
			$request = isset($call['request']) && is_array($call['request']) ? $call['request'] : array();
			$response = isset($call['response']) && is_array($call['response']) ? $call['response'] : array();
			$model_runs[] = array(
				'step' => isset($call['type']) ? $call['type'] : 'unknown',
				'provider' => isset($request['provider']) ? $request['provider'] : (isset($request['context']['provider']) ? $request['context']['provider'] : ''),
				'model' => isset($request['model']) ? $request['model'] : (isset($request['context']['model']) ? $request['context']['model'] : ''),
				'status' => !empty($response) ? 'completed' : 'requested',
				'has_request' => !empty($request),
				'has_response' => !empty($response),
				'source_ref' => 'ai_calls',
			);
		}
		
		$component_revision_counts = array();
		foreach ($redacted_revisions as $component_key => $component_revisions_list) {
			$component_revision_counts[$component_key] = is_array($component_revisions_list) ? count($component_revisions_list) : 0;
		}
		
		$used_urls = array();
		foreach ($sources_used as $used_source) {
			if (!empty($used_source['url'])) {
				$used_urls[(string) $used_source['url']] = true;
			}
		}
		$sources_excluded = array();
		foreach ($sources_considered as $source_row) {
			$url = isset($source_row['url']) ? (string) $source_row['url'] : '';
			if ('' === $url || !isset($used_urls[$url])) {
				$sources_excluded[] = $source_row;
			}
		}
		
		return array(
			'schema_version' => '1.0.0',
			'generation' => array(
				'history_id' => isset($history_item->id) ? (int) $history_item->id : 0,
				'status' => isset($history_item->status) ? (string) $history_item->status : '',
				'post_id' => isset($history_item->post_id) ? (int) $history_item->post_id : 0,
				'created_at' => isset($history_item->created_at) ? $history_item->created_at : '',
				'completed_at' => isset($history_item->completed_at) ? $history_item->completed_at : '',
				'correlation_id' => isset($history_item->uuid) ? (string) $history_item->uuid : '',
			),
			'trigger' => array(
				'origin' => $this->derive_trigger_origin($history_item),
				'creation_method' => isset($history_item->creation_method) ? (string) $history_item->creation_method : '',
				'template_id' => isset($history_item->template_id) ? (int) $history_item->template_id : 0,
				'author_id' => isset($history_item->author_id) ? (int) $history_item->author_id : 0,
				'topic_id' => isset($history_item->topic_id) ? (int) $history_item->topic_id : 0,
			),
			'context_snapshot' => array(
				'generated_title' => isset($history_item->generated_title) ? (string) $history_item->generated_title : '',
				'error_message' => isset($history_item->error_message) ? (string) $history_item->error_message : '',
				'template_id' => isset($history_item->template_id) ? (int) $history_item->template_id : 0,
				'author_id' => isset($history_item->author_id) ? (int) $history_item->author_id : 0,
				'topic_id' => isset($history_item->topic_id) ? (int) $history_item->topic_id : 0,
			),
			'prompt_components' => $this->build_prompt_components($redacted_ai_calls),
			'sources' => array(
				'considered' => $sources_considered,
				'used' => $sources_used,
				'excluded' => $sources_excluded,
			),
			'model_runs' => $model_runs,
			'validation_checks' => $validation_checks,
			'transformations' => $transformations,
			'attempts' => array(
				'total_attempts' => $attempt_count,
				'retries_or_regenerations' => $retry_count,
				'component_revision_counts' => $component_revision_counts,
				'component_revisions_ref' => 'component_revisions',
			),
			'timeline' => $timeline,
			'final_outcome' => array(
				'status' => isset($history_item->status) ? (string) $history_item->status : '',
				'post_id' => isset($history_item->post_id) ? (int) $history_item->post_id : 0,
				'post_edit_link' => !empty($history_item->post_id) ? esc_url_raw(get_edit_post_link((int) $history_item->post_id, 'raw')) : '',
			),
			'redactions' => array(
				'count' => $redaction_count,
				'note' => $redaction_count > 0 ? __('Some sensitive fields were redacted for safety.', 'ai-post-scheduler') : '',
			),
			'warnings' => $this->build_explainability_warnings($redacted_entries, $sources_used, $validation_checks, $retry_count),
		);
	}
	
	/**
	 * Build prompt component records from AI call data.
	 *
	 * @param array $ai_calls Redacted AI calls.
	 * @return array
	 */
	private function build_prompt_components($ai_calls) {
		$components = array();
		
		foreach ($ai_calls as $call) {
			$request = isset($call['request']) && is_array($call['request']) ? $call['request'] : array();
			$prompt_preview = '';
			
			if (isset($request['prompt']) && is_string($request['prompt'])) {
				$prompt_preview = $request['prompt'];
			} elseif (isset($request['messages']) && is_array($request['messages'])) {
				$prompt_preview = wp_json_encode($request['messages']);
			}
			
			$prompt_preview = trim((string) $prompt_preview);
			$prompt_length = function_exists('mb_strlen')
				? mb_strlen($prompt_preview)
				: strlen($prompt_preview);
			if ($prompt_length > self::EXPLAINABILITY_PROMPT_PREVIEW_MAX_LENGTH) {
				$prompt_preview = function_exists('mb_substr')
					? mb_substr($prompt_preview, 0, self::EXPLAINABILITY_PROMPT_PREVIEW_MAX_LENGTH)
					: substr($prompt_preview, 0, self::EXPLAINABILITY_PROMPT_PREVIEW_MAX_LENGTH);
				$prompt_preview .= '...';
			}
			
			$components[] = array(
				'name' => isset($call['type']) ? (string) $call['type'] : 'unknown',
				'label' => isset($call['label']) ? (string) $call['label'] : ucfirst(str_replace('_', ' ', isset($call['type']) ? (string) $call['type'] : 'unknown')),
				'included' => !empty($request),
				'prompt_preview' => $prompt_preview,
				'prompt_length' => $prompt_length,
				'prompt_preview_length' => function_exists('mb_strlen') ? mb_strlen($prompt_preview) : strlen($prompt_preview),
				'source' => isset($request['context']['source']) ? (string) $request['context']['source'] : 'ai_request',
			);
		}
		
		return $components;
	}
	
	/**
	 * Recursively redact sensitive values from arrays/strings.
	 *
	 * @param mixed $value           Value to redact.
	 * @param int   $redaction_count Redaction count (by reference).
	 * @param string $parent_key     Parent key context.
	 * @return mixed
	 */
	private function redact_sensitive_data($value, &$redaction_count, $parent_key = '') {
		if (is_array($value)) {
			$clean = array();
			foreach ($value as $key => $item) {
				$key_name = is_string($key) ? $key : $parent_key;
				if ($this->is_sensitive_key($key_name)) {
					$redaction_count++;
					$clean[$key] = '[REDACTED]';
					continue;
				}
				$clean[$key] = $this->redact_sensitive_data($item, $redaction_count, (string) $key_name);
			}
			return $clean;
		}
		
		if (is_string($value)) {
			if ($this->is_sensitive_key($parent_key) || $this->contains_sensitive_token($value)) {
				$redaction_count++;
				return '[REDACTED]';
			}
		}
		
		return $value;
	}
	
	/**
	 * Check whether key name is considered sensitive.
	 *
	 * @param string $key Key name.
	 * @return bool
	 */
	private function is_sensitive_key($key) {
		$key = strtolower((string) $key);
		return preg_match('/(password|token|secret|api[_-]?key|authorization|cookie|nonce|bearer|private[_-]?key|client[_-]?secret|access[_-]?token|refresh[_-]?token)/', $key) === 1;
	}
	
	/**
	 * Check whether a string appears to contain credential-like content.
	 *
	 * @param string $value Candidate value.
	 * @return bool
	 */
	private function contains_sensitive_token($value) {
		$value = (string) $value;
		return preg_match('/(sk-[a-z0-9]{16,}|akia[a-z0-9]{16}|aiza[a-z0-9\-_]{20,}|gh[pousr]_[a-z0-9]{20,}|bearer\s+[a-z0-9\-_\.]{16,}|-----begin[^-]*private key-----)/i', $value) === 1;
	}
	
	/**
	 * Derive a human-readable trigger origin.
	 *
	 * @param object $history_item History item.
	 * @return string
	 */
	private function derive_trigger_origin($history_item) {
		if (!empty($history_item->author_id) && !empty($history_item->topic_id)) {
			return 'author_topic';
		}
		if (!empty($history_item->template_id) && isset($history_item->creation_method) && 'scheduled' === $history_item->creation_method) {
			return 'scheduled_post';
		}
		if (!empty($history_item->template_id) && isset($history_item->creation_method) && 'manual' === $history_item->creation_method) {
			return 'manual_generation';
		}
		return 'unknown';
	}
	
	/**
	 * Map log entry to a timeline stage.
	 *
	 * @param array $entry Explainability entry.
	 * @return string
	 */
	private function map_log_to_timeline_stage($entry) {
		$log_type = strtolower(isset($entry['log_type']) ? (string) $entry['log_type'] : '');
		$type_id = isset($entry['type_id']) ? (int) $entry['type_id'] : 0;
		
		if (AIPS_History_Type::AI_REQUEST === $type_id) {
			return 'model_called';
		}
		if (AIPS_History_Type::AI_RESPONSE === $type_id) {
			return 'model_responded';
		}
		if (strpos($log_type, 'source') !== false || strpos($log_type, 'fetch') !== false) {
			return 'sources_fetched';
		}
		if (strpos($log_type, 'prompt') !== false) {
			return 'prompt_built';
		}
		if (strpos($log_type, 'validat') !== false || strpos($log_type, 'check') !== false) {
			return 'validation';
		}
		if (strpos($log_type, 'retry') !== false || strpos($log_type, 'regener') !== false) {
			return 'retry_or_regeneration';
		}
		if (strpos($log_type, 'save') !== false || strpos($log_type, 'post') !== false) {
			return 'final_save';
		}
		return 'activity';
	}
	
	/**
	 * Build a short summary for a timeline entry.
	 *
	 * @param array $entry Explainability entry.
	 * @return string
	 */
	private function build_entry_summary($entry) {
		$details = isset($entry['details']) && is_array($entry['details']) ? $entry['details'] : array();
		if (isset($details['message']) && is_string($details['message']) && '' !== trim($details['message'])) {
			return trim($details['message']);
		}
		if (isset($details['error']) && is_string($details['error']) && '' !== trim($details['error'])) {
			return trim($details['error']);
		}
		return isset($entry['log_type']) ? (string) $entry['log_type'] : '';
	}
	
	/**
	 * Extract source records from a log entry.
	 *
	 * @param array $entry Explainability entry.
	 * @return array
	 */
	private function extract_sources_from_entry($entry) {
		$details = isset($entry['details']) && is_array($entry['details']) ? $entry['details'] : array();
		$found = array();
		$this->collect_sources_recursive($details, $found);
		return $found;
	}
	
	/**
	 * Recursively collect source objects from nested details.
	 *
	 * @param mixed $node  Current node.
	 * @param array $found Collected sources.
	 * @return void
	 */
	private function collect_sources_recursive($node, &$found) {
		if (!is_array($node)) {
			return;
		}
		
		$has_url = isset($node['url']) && is_string($node['url']) && '' !== trim($node['url']);
		if ($has_url) {
			$url = esc_url_raw($node['url']);
			
			if ('' !== $url) {
				$domain = function_exists('wp_parse_url') ? wp_parse_url($url, PHP_URL_HOST) : parse_url($url, PHP_URL_HOST);
				$found[] = array(
					'url' => $url,
					'title' => isset($node['title']) ? (string) $node['title'] : '',
					'domain' => is_string($domain) ? $domain : '',
				);
			}
		}
		
		foreach ($node as $child) {
			if (is_array($child)) {
				$this->collect_sources_recursive($child, $found);
			}
		}
	}
	
	/**
	 * Determine whether log entry indicates that source was used.
	 *
	 * @param array $entry Explainability entry.
	 * @return bool
	 */
	private function entry_indicates_source_usage($entry) {
		$log_type = strtolower(isset($entry['log_type']) ? (string) $entry['log_type'] : '');
		$details = isset($entry['details']) && is_array($entry['details']) ? $entry['details'] : array();
		
		if (strpos($log_type, 'used') !== false || strpos($log_type, 'selected') !== false) {
			return true;
		}
		
		if (isset($details['used']) && true === $details['used']) {
			return true;
		}
		
		return false;
	}
	
	/**
	 * Extract validation check record from an entry when available.
	 *
	 * @param array $entry Explainability entry.
	 * @return array
	 */
	private function extract_validation_check($entry) {
		$log_type = strtolower(isset($entry['log_type']) ? (string) $entry['log_type'] : '');
		if (strpos($log_type, 'validat') === false && strpos($log_type, 'check') === false && strpos($log_type, 'policy') === false && strpos($log_type, 'guard') === false) {
			return array();
		}
		
		$details = isset($entry['details']) && is_array($entry['details']) ? $entry['details'] : array();
		$status = isset($details['status']) ? strtolower((string) $details['status']) : 'info';
		if (!in_array($status, array('passed', 'failed', 'warning', 'skipped'), true)) {
			$status = 'info';
		}
		
		return array(
			'name' => isset($details['check_name']) ? (string) $details['check_name'] : (isset($entry['log_type']) ? (string) $entry['log_type'] : 'validation_check'),
			'status' => $status,
			'reason' => $this->build_entry_summary($entry),
			'timestamp' => isset($entry['timestamp']) ? (string) $entry['timestamp'] : '',
		);
	}
	
	/**
	 * Extract transformation record from an entry when available.
	 *
	 * @param array $entry Explainability entry.
	 * @return array
	 */
	private function extract_transformation($entry) {
		$log_type = strtolower(isset($entry['log_type']) ? (string) $entry['log_type'] : '');
		if (strpos($log_type, 'transform') === false && strpos($log_type, 'normalize') === false && strpos($log_type, 'sanitize') === false && strpos($log_type, 'internal_link') === false && strpos($log_type, 'citation') === false) {
			return array();
		}
		
		return array(
			'stage' => isset($entry['log_type']) ? (string) $entry['log_type'] : 'transformation',
			'summary' => $this->build_entry_summary($entry),
			'timestamp' => isset($entry['timestamp']) ? (string) $entry['timestamp'] : '',
		);
	}
	
	/**
	 * Build explainability-level warnings.
	 *
	 * @param array $entries            Explainability entries.
	 * @param array $sources_used       Sources used list.
	 * @param array $validation_checks  Validation checks list.
	 * @param int   $retry_count        Retry count.
	 * @return array
	 */
	private function build_explainability_warnings($entries, $sources_used, $validation_checks, $retry_count) {
		$warnings = array();
		
		if (empty($entries)) {
			$warnings[] = __('Limited lineage data is available for this generation.', 'ai-post-scheduler');
		}
		
		if (empty($sources_used)) {
			$warnings[] = __('No explicitly used sources were detected in available logs.', 'ai-post-scheduler');
		}
		
		if ($retry_count > 0) {
			$warnings[] = sprintf(
				/* translators: %d: retry/regeneration count */
				__('This generation included %d retry/regeneration attempts.', 'ai-post-scheduler'),
				$retry_count
			);
		}
		
		foreach ($validation_checks as $check) {
			if (isset($check['status']) && 'failed' === $check['status']) {
				$warnings[] = __('At least one validation check failed during generation.', 'ai-post-scheduler');
				break;
			}
		}
		
		return array_values(array_unique($warnings));
	}
	
	/**
	 * Remove duplicate arrays while preserving order.
	 *
	 * @param array $rows Rows to deduplicate.
	 * @return array
	 */
	private function unique_multidimensional($rows) {
		$unique = array();
		$seen = array();
		
		foreach ($rows as $row) {
			$key = wp_json_encode($row);
			if (!isset($seen[$key])) {
				$seen[$key] = true;
				$unique[] = $row;
			}
		}
		
		return $unique;
	}
	
	/**
	 * AJAX handler that returns a downloadable JSON file for a session
	 *
	 * This keeps the existing AJAX endpoint that returns the JSON string for JS consumption,
	 * while providing a dedicated endpoint that sends proper download headers so the browser
	 * will prompt the user to save the JSON to disk.
	 */
	public function ajax_download_session_json() {
		if ( ! check_ajax_referer('aips_ajax_nonce', 'nonce', false) ) {
			AIPS_Ajax_Response::error(__('Invalid nonce.', 'ai-post-scheduler'));
		}
		
		if (!current_user_can('manage_options')) {
			AIPS_Ajax_Response::permission_denied();
		}
		
		$history_id = isset($_POST['history_id']) ? absint($_POST['history_id']) : 0;
		
		if (!$history_id) {
			AIPS_Ajax_Response::error(__('Invalid history ID.', 'ai-post-scheduler'));
		}
		
		// Get history item to inspect size/complexity
		$history_item = $this->history_repository->get_by_id($history_id);
		if (!$history_item) {
			AIPS_Ajax_Response::error(__('History item not found.', 'ai-post-scheduler'));
		}
		
		// Heuristic: if there are many log entries, write to tempfile instead of echoing directly
		$log_count = isset($history_item->log) && is_array($history_item->log) ? count($history_item->log) : 0;
		// Read thresholds from configuration
		$config = AIPS_Config::get_instance();
		$TEMPFILE_LOG_THRESHOLD = (int) $config->get_option('generated_posts_log_threshold_tmpfile', 200);
		
		$converter = new AIPS_Session_To_JSON();
		
		if ($log_count >= $TEMPFILE_LOG_THRESHOLD) {
			$temp = $converter->generate_json_to_tempfile($history_id, true);
			if (is_wp_error($temp)) {
				AIPS_Ajax_Response::error(array('message' => $temp->get_error_message()));
			}
			
			// Read the file and send it directly instead of redirecting
			// This prevents double downloads when form is submitted with target="_blank"
			$filepath = $temp['path'];
			$filename = basename($filepath);
			
			if (!file_exists($filepath)) {
				AIPS_Ajax_Response::error(__('Export file not found.', 'ai-post-scheduler'));
			}
			
			$json_string = file_get_contents($filepath);
			if ($json_string === false) {
				AIPS_Ajax_Response::error(__('Failed to read export file.', 'ai-post-scheduler'));
			}
			
			// Send download headers and the JSON payload
			if (!headers_sent()) {
				header('Content-Description: File Transfer');
				header('Content-Type: application/json; charset=utf-8');
				header('Content-Disposition: attachment; filename="' . $filename . '"');
				header('Content-Transfer-Encoding: binary');
				header('Expires: 0');
				header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
				header('Pragma: public');
				header('Content-Length: ' . strlen($json_string));
			}
			
			echo $json_string;
			exit;
		}
		
		// Small session: generate string and send directly
		$json_string = $converter->generate_json_string($history_id, true);
		
		if (is_wp_error($json_string)) {
			AIPS_Ajax_Response::error(array('message' => $json_string->get_error_message()));
		}
		
		// Build a safe filename including history id and timestamp
		$timestamp = current_time('Ymd-His');
		$filename = sprintf('aips-session-%d-%s.json', $history_id, $timestamp);
		
		// Send download headers and the JSON payload
		if (!headers_sent()) {
			header('Content-Description: File Transfer');
			header('Content-Type: application/json; charset=utf-8');
			header('Content-Disposition: attachment; filename="' . $filename . '"');
			header('Content-Transfer-Encoding: binary');
			header('Expires: 0');
			header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
			header('Pragma: public');
			header('Content-Length: ' . strlen($json_string));
		}
		
		echo $json_string;
		// Terminate immediately to avoid extra output
		exit;
	}

	/**
	 * AJAX handler to get complete session JSON for debugging/BI purposes
	 */
	public function ajax_get_session_json() {
		if ( ! check_ajax_referer('aips_ajax_nonce', 'nonce', false) ) {
			AIPS_Ajax_Response::error(__('Invalid nonce.', 'ai-post-scheduler'));
		}
		
		if (!current_user_can('manage_options')) {
			AIPS_Ajax_Response::permission_denied();
		}
		
		$history_id = isset($_POST['history_id']) ? absint($_POST['history_id']) : 0;
		
		if (!$history_id) {
			AIPS_Ajax_Response::error(__('Invalid history ID.', 'ai-post-scheduler'));
		}
		
		// Use the Session To JSON converter
		$converter = new AIPS_Session_To_JSON();
		$json_string = $converter->generate_json_string($history_id, true);
		
		if (is_wp_error($json_string)) {
			AIPS_Ajax_Response::error(array('message' => $json_string->get_error_message()));
		}
		
		AIPS_Ajax_Response::success(array(
			'json' => $json_string,
		));
	}
	
	/**
	 * Format source information for display
	 *
	 * @param object $history_item History item from database
	 * @return string Formatted source string (unescaped - caller must escape)
	 */
	public function format_source($history_item) {
		$source = '';
		
		// Determine the source type
		if (!empty($history_item->template_id)) {
			// Template-based generation with caching
			$template_id = $history_item->template_id;
			
			if (!isset($this->template_cache[$template_id])) {
				$template_repository = new AIPS_Template_Repository();
				$this->template_cache[$template_id] = $template_repository->get_by_id($template_id);
			}
			
			$template = $this->template_cache[$template_id];
			$source = __('Template', 'ai-post-scheduler');
			if ($template && isset($template->name)) {
				$source .= ': ' . $template->name;
			}
		} elseif (!empty($history_item->author_id) && !empty($history_item->topic_id)) {
			// Author Topic-based generation with caching
			$author_id = $history_item->author_id;
			$topic_id = $history_item->topic_id;
			
			if (!isset($this->author_cache[$author_id])) {
				$authors_repository = new AIPS_Authors_Repository();
				$this->author_cache[$author_id] = $authors_repository->get_by_id($author_id);
			}
			
			if (!isset($this->topic_cache[$topic_id])) {
				$topics_repository = new AIPS_Author_Topics_Repository();
				$this->topic_cache[$topic_id] = $topics_repository->get_by_id($topic_id);
			}
			
			$author = $this->author_cache[$author_id];
			$topic = $this->topic_cache[$topic_id];
			
			$source = __('Author Topic', 'ai-post-scheduler');
			if ($author && isset($author->name)) {
				$source .= ': ' . $author->name;
			}
			if ($topic && isset($topic->topic_title)) {
				$source .= ' - ' . $topic->topic_title;
			}
		} else {
			$source = __('Unknown', 'ai-post-scheduler');
		}
		
		// Add creation method if available
		if (!empty($history_item->creation_method)) {
			$method = $history_item->creation_method === 'manual' 
				? __('Manual', 'ai-post-scheduler') 
				: __('Scheduled', 'ai-post-scheduler');
			$source .= ' (' . $method . ')';
		}
		
		return $source;
	}
}
