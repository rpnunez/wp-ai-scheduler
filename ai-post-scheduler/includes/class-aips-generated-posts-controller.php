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
		$search_query = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
		
		// Get completed history entries with post IDs (for Generated Posts tab)
		$history = $this->history_repository->get_history(array(
			'page' => $generated_page,
			'per_page' => 20,
			'status' => 'completed',
			'search' => $search_query,
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
			
			$posts_data[] = array(
				'history_id' => $item->id,
				'post_id' => $item->post_id,
				'title' => $post->post_title,
				'date_generated' => $item->created_at,
				'date_published' => $post->post_date,
				'date_scheduled' => $schedule ? $schedule->next_run : null,
				'edit_link' => get_edit_post_link($item->post_id),
				'source' => $source,
			);
		}
		
		// Get draft posts for Post Review tab
		$template_id = isset($_GET['template_id']) ? absint($_GET['template_id']) : 0;
		$draft_posts = $this->post_review_repository->get_draft_posts(array(
			'page' => $review_page,
			'search' => $search_query,
			'template_id' => $template_id,
		));
		
		// Pass separate page variables for each tab
		$current_page = $generated_page; // For Generated Posts tab
		$review_current_page = $review_page; // For Pending Review tab
		
		// Get templates for filter dropdown
		$template_repository = new AIPS_Template_Repository();
		$templates = $template_repository->get_all();
		
		// Get globally-initialized Post Review handler
		global $aips_post_review_handler;
		$post_review_handler = isset($aips_post_review_handler) ? $aips_post_review_handler : $this->post_review_repository;
		
		// Make controller available to template for formatting
		$controller = $this;
		
		include AIPS_PLUGIN_DIR . 'templates/admin/generated-posts.php';
	}
	
	/**
	 * AJAX handler to get detailed session data for a post
	 */
	public function ajax_get_post_session() {
		check_ajax_referer('aips_ajax_nonce', 'nonce');
		
		if (!current_user_can('manage_options')) {
			wp_send_json_error(array('message' => __('Permission denied.', 'ai-post-scheduler')));
		}
		
		$history_id = isset($_POST['history_id']) ? absint($_POST['history_id']) : 0;
		
		if (!$history_id) {
			wp_send_json_error(array('message' => __('Invalid history ID.', 'ai-post-scheduler')));
		}
		
		// Get history item with all logs
		$history_item = $this->history_repository->get_by_id($history_id);
		
		if (!$history_item) {
			wp_send_json_error(array('message' => __('History item not found.', 'ai-post-scheduler')));
		}
		
		// Organize logs by type
		$logs = array();
		$ai_calls = array();
		
		foreach ($history_item->log as $log_entry) {
			$details = json_decode($log_entry->details, true);
			
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
		
		wp_send_json_success(array(
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
		));
	}
	
	/**
	 * AJAX handler that returns a downloadable JSON file for a session
	 *
	 * This keeps the existing AJAX endpoint that returns the JSON string for JS consumption,
	 * while providing a dedicated endpoint that sends proper download headers so the browser
	 * will prompt the user to save the JSON to disk.
	 */
	public function ajax_download_session_json() {
		check_ajax_referer('aips_ajax_nonce', 'nonce');
		
		if (!current_user_can('manage_options')) {
			wp_send_json_error(array('message' => __('Permission denied.', 'ai-post-scheduler')));
		}
		
		$history_id = isset($_POST['history_id']) ? absint($_POST['history_id']) : 0;
		
		if (!$history_id) {
			wp_send_json_error(array('message' => __('Invalid history ID.', 'ai-post-scheduler')));
		}
		
		// Get history item to inspect size/complexity
		$history_item = $this->history_repository->get_by_id($history_id);
		if (!$history_item) {
			wp_send_json_error(array('message' => __('History item not found.', 'ai-post-scheduler')));
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
				wp_send_json_error(array('message' => $temp->get_error_message()));
			}
			
			// Read the file and send it directly instead of redirecting
			// This prevents double downloads when form is submitted with target="_blank"
			$filepath = $temp['path'];
			$filename = basename($filepath);
			
			if (!file_exists($filepath)) {
				wp_send_json_error(array('message' => __('Export file not found.', 'ai-post-scheduler')));
			}
			
			$json_string = file_get_contents($filepath);
			if ($json_string === false) {
				wp_send_json_error(array('message' => __('Failed to read export file.', 'ai-post-scheduler')));
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
			wp_send_json_error(array('message' => $json_string->get_error_message()));
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
		check_ajax_referer('aips_ajax_nonce', 'nonce');
		
		if (!current_user_can('manage_options')) {
			wp_send_json_error(array('message' => __('Permission denied.', 'ai-post-scheduler')));
		}
		
		$history_id = isset($_POST['history_id']) ? absint($_POST['history_id']) : 0;
		
		if (!$history_id) {
			wp_send_json_error(array('message' => __('Invalid history ID.', 'ai-post-scheduler')));
		}
		
		// Use the Session To JSON converter
		$converter = new AIPS_Session_To_JSON();
		$json_string = $converter->generate_json_string($history_id, true);
		
		if (is_wp_error($json_string)) {
			wp_send_json_error(array('message' => $json_string->get_error_message()));
		}
		
		wp_send_json_success(array(
			'json' => $json_string,
		));
	}
	
	/**
	 * Format source information for display
	 *
	 * @param object $history_item History item from database
	 * @return string Formatted source string (already escaped)
	 */
	public function format_source($history_item) {
		$source = '';
		
		// Determine the source type
		if (!empty($history_item->template_id)) {
			// Template-based generation
			$template_repository = new AIPS_Template_Repository();
			$template = $template_repository->get_by_id($history_item->template_id);
			
			$source = __('Template', 'ai-post-scheduler');
			if ($template && isset($template->name)) {
				$source .= ': ' . esc_html($template->name);
			}
		} elseif (!empty($history_item->author_id) && !empty($history_item->topic_id)) {
			// Author Topic-based generation
			$authors_repository = new AIPS_Authors_Repository();
			$topics_repository = new AIPS_Author_Topics_Repository();
			
			$author = $authors_repository->get_by_id($history_item->author_id);
			$topic = $topics_repository->get_by_id($history_item->topic_id);
			
			$source = __('Author Topic', 'ai-post-scheduler');
			if ($author && isset($author->name)) {
				$source .= ': ' . esc_html($author->name);
			}
			if ($topic && isset($topic->topic_title)) {
				$source .= ' - ' . esc_html($topic->topic_title);
			}
		} else {
			$source = __('Unknown', 'ai-post-scheduler');
		}
		
		// Add creation method if available
		if (!empty($history_item->creation_method)) {
			$method = $history_item->creation_method === 'manual' 
				? __('Manual', 'ai-post-scheduler') 
				: __('Scheduled', 'ai-post-scheduler');
			$source .= ' (' . esc_html($method) . ')';
		}
		
		return $source;
	}
}
