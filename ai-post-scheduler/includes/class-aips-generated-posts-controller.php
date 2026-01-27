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
	 * Initialize the controller
	 */
	public function __construct() {
		$this->history_repository = new AIPS_History_Repository();
		$this->schedule_repository = new AIPS_Schedule_Repository();
		
		// Register AJAX handlers
		add_action('wp_ajax_aips_get_post_session', array($this, 'ajax_get_post_session'));
	}
	
	/**
	 * Render the Generated Posts admin page
	 */
	public function render_page() {
		$current_page = isset($_GET['paged']) ? absint($_GET['paged']) : 1;
		$search_query = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
		
		// Get completed history entries with post IDs
		$history = $this->history_repository->get_history(array(
			'page' => $current_page,
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
			
			$posts_data[] = array(
				'history_id' => $item->id,
				'post_id' => $item->post_id,
				'title' => $post->post_title,
				'date_generated' => $item->created_at,
				'date_published' => $post->post_date,
				'date_scheduled' => $schedule ? $schedule->next_run : null,
				'edit_link' => get_edit_post_link($item->post_id),
			);
		}
		
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
					// Group AI requests and responses together
					$component_type = isset($details['type']) ? $details['type'] : 'unknown';
					
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
}
