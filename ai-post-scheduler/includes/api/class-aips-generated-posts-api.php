<?php
/**
 * Generated Posts REST API Controller
 *
 * Provides REST API endpoints for the React-based Generated Posts page.
 *
 * @package AI_Post_Scheduler
 * @since 2.1.0
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Generated_Posts_API
 *
 * Handles REST API endpoints for generated posts data.
 */
class AIPS_Generated_Posts_API {
	
	/**
	 * @var string API namespace
	 */
	private $namespace = 'aips/v1';
	
	/**
	 * @var AIPS_History_Repository
	 */
	private $history_repository;
	
	/**
	 * @var AIPS_Template_Repository
	 */
	private $template_repository;
	
	/**
	 * @var AIPS_Generated_Posts_Controller
	 */
	private $controller;
	
	/**
	 * Initialize the API controller
	 */
	public function __construct() {
		$this->history_repository = new AIPS_History_Repository();
		$this->template_repository = new AIPS_Template_Repository();
		$this->controller = new AIPS_Generated_Posts_Controller();
		
		add_action('rest_api_init', array($this, 'register_routes'));
	}
	
	/**
	 * Register REST API routes
	 */
	public function register_routes() {
		// GET /generated-posts - List generated posts with filtering
		register_rest_route($this->namespace, '/generated-posts', array(
			'methods' => WP_REST_Server::READABLE,
			'callback' => array($this, 'get_generated_posts'),
			'permission_callback' => array($this, 'check_permissions'),
			'args' => array(
				'page' => array(
					'default' => 1,
					'sanitize_callback' => 'absint',
				),
				'per_page' => array(
					'default' => 20,
					'sanitize_callback' => 'absint',
				),
				'status' => array(
					'default' => '',
					'sanitize_callback' => 'sanitize_text_field',
				),
				'search' => array(
					'default' => '',
					'sanitize_callback' => 'sanitize_text_field',
				),
				'template_id' => array(
					'default' => 0,
					'sanitize_callback' => 'absint',
				),
			),
		));
		
		// GET /generation-session/:id - Get session details for modal
		register_rest_route($this->namespace, '/generation-session/(?P<id>\d+)', array(
			'methods' => WP_REST_Server::READABLE,
			'callback' => array($this, 'get_generation_session'),
			'permission_callback' => array($this, 'check_permissions'),
			'args' => array(
				'id' => array(
					'validate_callback' => function($param) {
						return is_numeric($param);
					}
				),
			),
		));
		
		// DELETE /generated-posts/:id - Delete a generated post
		register_rest_route($this->namespace, '/generated-posts/(?P<id>\d+)', array(
			'methods' => WP_REST_Server::DELETABLE,
			'callback' => array($this, 'delete_generated_post'),
			'permission_callback' => array($this, 'check_permissions'),
			'args' => array(
				'id' => array(
					'validate_callback' => function($param) {
						return is_numeric($param);
					}
				),
			),
		));
		
		// GET /templates - Get list of templates for filter dropdown
		register_rest_route($this->namespace, '/templates', array(
			'methods' => WP_REST_Server::READABLE,
			'callback' => array($this, 'get_templates'),
			'permission_callback' => array($this, 'check_permissions'),
		));
	}
	
	/**
	 * Check if user has permission to access endpoints
	 *
	 * @return bool
	 */
	public function check_permissions() {
		return current_user_can('manage_options');
	}
	
	/**
	 * Get generated posts with filtering and pagination
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response
	 */
	public function get_generated_posts($request) {
		$page = $request->get_param('page');
		$per_page = $request->get_param('per_page');
		$status = $request->get_param('status');
		$search = $request->get_param('search');
		$template_id = $request->get_param('template_id');
		
		// Map frontend status to backend status
		$history_status = '';
		if ($status === 'published' || $status === 'draft') {
			// For published/draft, we'll filter by post_status after getting results
			$history_status = 'completed';
		} elseif ($status === 'pending') {
			$history_status = 'completed';
		}
		
		// Get history entries
		$history = $this->history_repository->get_history(array(
			'page' => $page,
			'per_page' => $per_page * 2, // Get more to account for filtering
			'status' => $history_status ?: 'completed',
			'search' => $search,
			'template_id' => $template_id,
		));
		
		// Format posts data
		$posts_data = array();
		foreach ($history['items'] as $item) {
			if (!$item->post_id) {
				continue;
			}
			
			$post = get_post($item->post_id);
			if (!$post) {
				continue;
			}
			
			// Apply post status filter if specified
			if ($status && $status !== 'all') {
				if ($status === 'published' && $post->post_status !== 'publish') {
					continue;
				}
				if ($status === 'draft' && $post->post_status !== 'draft') {
					continue;
				}
				if ($status === 'pending' && $post->post_status !== 'pending') {
					continue;
				}
			}
			
			// Format source information
			$source = $this->controller->format_source($item);
			
			// Get template name
			$template_name = '';
			if ($item->template_id) {
				$template = $this->template_repository->get_by_id($item->template_id);
				$template_name = $template ? $template->name : '';
			}
			
			// Get author
			$author = get_user_by('id', $post->post_author);
			$author_name = $author ? $author->display_name : __('Unknown', 'ai-post-scheduler');
			
			$posts_data[] = array(
				'id' => $item->id,
				'post_id' => $item->post_id,
				'title' => $post->post_title ?: __('(no title)', 'ai-post-scheduler'),
				'template_name' => $template_name,
				'author' => $author_name,
				'status' => $post->post_status,
				'status_label' => $this->get_status_label($post->post_status),
				'date_generated' => $item->created_at,
				'date_generated_formatted' => date_i18n(
					get_option('date_format') . ' ' . get_option('time_format'), 
					strtotime($item->created_at)
				),
				'date_published' => $post->post_date,
				'edit_link' => get_edit_post_link($item->post_id),
				'view_link' => get_permalink($item->post_id),
				'source' => $source,
			);
			
			// Stop if we have enough posts
			if (count($posts_data) >= $per_page) {
				break;
			}
		}
		
		return new WP_REST_Response(array(
			'posts' => $posts_data,
			'total' => $history['total'],
			'pages' => $history['pages'],
			'current_page' => $page,
		), 200);
	}
	
	/**
	 * Get generation session details for modal
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response
	 */
	public function get_generation_session($request) {
		$history_id = absint($request->get_param('id'));
		
		if (!$history_id) {
			return new WP_REST_Response(array(
				'error' => __('Invalid history ID.', 'ai-post-scheduler')
			), 400);
		}
		
		// Get history item with all logs
		$history_item = $this->history_repository->get_by_id($history_id);
		
		if (!$history_item) {
			return new WP_REST_Response(array(
				'error' => __('History item not found.', 'ai-post-scheduler')
			), 404);
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
		
		return new WP_REST_Response(array(
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
		), 200);
	}
	
	/**
	 * Delete a generated post
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response
	 */
	public function delete_generated_post($request) {
		$post_id = absint($request->get_param('id'));
		
		if (!$post_id) {
			return new WP_REST_Response(array(
				'error' => __('Invalid post ID.', 'ai-post-scheduler')
			), 400);
		}
		
		$post = get_post($post_id);
		if (!$post) {
			return new WP_REST_Response(array(
				'error' => __('Post not found.', 'ai-post-scheduler')
			), 404);
		}
		
		// Delete the post
		$result = wp_delete_post($post_id, true);
		
		if (!$result) {
			return new WP_REST_Response(array(
				'error' => __('Failed to delete post.', 'ai-post-scheduler')
			), 500);
		}
		
		return new WP_REST_Response(array(
			'success' => true,
			'message' => __('Post deleted successfully.', 'ai-post-scheduler')
		), 200);
	}
	
	/**
	 * Get list of templates for filter dropdown
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response
	 */
	public function get_templates($request) {
		$templates = $this->template_repository->get_all();
		
		$formatted_templates = array();
		foreach ($templates as $template) {
			$formatted_templates[] = array(
				'id' => $template->id,
				'name' => $template->name,
			);
		}
		
		return new WP_REST_Response(array(
			'templates' => $formatted_templates,
		), 200);
	}
	
	/**
	 * Get human-readable status label
	 *
	 * @param string $status
	 * @return string
	 */
	private function get_status_label($status) {
		$labels = array(
			'publish' => __('Published', 'ai-post-scheduler'),
			'draft' => __('Draft', 'ai-post-scheduler'),
			'pending' => __('Pending Review', 'ai-post-scheduler'),
			'private' => __('Private', 'ai-post-scheduler'),
			'trash' => __('Trash', 'ai-post-scheduler'),
		);
		
		return isset($labels[$status]) ? $labels[$status] : ucfirst($status);
	}
}
