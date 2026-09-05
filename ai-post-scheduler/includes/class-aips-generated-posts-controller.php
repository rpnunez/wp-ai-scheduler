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
	 * @var array Cache for campaign objects to avoid N+1 queries
	 */
	private $campaign_cache = array();

	/**
	 * @var array Cache for template names to avoid N+1 queries
	 */
	private $template_cache = array();
	
	/**
	 * @var array Cache for author names to avoid N+1 queries
	 */
	/**
	 * @var AIPS_Generated_Content_Repository
	 */
	private $generated_content_repository;

	/**
	 * @var array Cache for author objects to avoid N+1 queries
	 */
	private $author_cache = array();
	
	/**
	 * @var array Cache for topic titles to avoid N+1 queries
	 */
	private $topic_cache = array();

	/**
	 * @var array Active filter and pagination state for Generated Posts
	 */
	private $active_state = array();
	
	/**
	 * Initialize the controller
	 */
	public function __construct() {
		$this->generated_content_repository = new AIPS_Generated_Content_Repository();
		$this->history_repository = new AIPS_History_Repository();
		$this->schedule_repository = new AIPS_Schedule_Repository();
		$this->post_review_repository = new AIPS_Post_Review_Repository();
		
		// Register AJAX handlers
		add_action('wp_ajax_aips_get_post_session', array($this, 'ajax_get_post_session'));
		add_action('wp_ajax_aips_get_session_json', array($this, 'ajax_get_session_json'));
		add_action('wp_ajax_aips_download_session_json', array($this, 'ajax_download_session_json'));
		add_action('wp_ajax_aips_update_post_status', array($this, 'ajax_update_post_status'));
		add_action('wp_ajax_aips_bulk_generated_posts_action', array($this, 'ajax_bulk_generated_posts_action'));
	}
	
	/**
	 * Render the Generated Posts admin page
	 */
	public function render_page() {
		// Use pagination and filter parameters
		$generated_page = isset($_GET['generated_paged']) ? absint($_GET['generated_paged']) : 1;
		$per_page = isset($_GET['per_page']) ? max(10, min(100, absint($_GET['per_page']))) : 20;
		$search_query = isset($_GET['s']) ? sanitize_text_field(wp_unslash($_GET['s'])) : '';
		$author_id = isset($_GET['author_id']) ? absint($_GET['author_id']) : 0;
		$template_id = isset($_GET['template_id']) ? absint($_GET['template_id']) : 0;
		$campaign_id = isset($_GET['campaign_id']) ? absint($_GET['campaign_id']) : 0;
		$post_status = isset($_GET['post_status']) ? sanitize_key($_GET['post_status']) : '';
		$post_type_filter = isset($_GET['post_type']) ? sanitize_key(wp_unslash($_GET['post_type'])) : '';
		$group_by = isset($_GET['group_by']) ? sanitize_key($_GET['group_by']) : 'campaign';
		$view_mode = isset($_GET['view_mode']) ? sanitize_key($_GET['view_mode']) : 'grouped';

		// Validate group_by and view_mode
		if (!in_array($group_by, array('status', 'campaign', 'template', 'author', 'date', 'none'), true)) {
			$group_by = 'campaign';
		}
		if (!in_array($view_mode, array('grouped', 'table', 'cards'), true)) {
			$view_mode = 'grouped';
		}

		$this->active_state = array(
			'author_id'   => $author_id,
			'template_id' => $template_id,
			'campaign_id' => $campaign_id,
			'post_status' => $post_status,
			'post_type'   => $post_type_filter,
			'group_by'    => $group_by,
			'view_mode'   => $view_mode,
			'per_page'    => $per_page,
			's'           => $search_query,
		);

		// Fetch KPI summary statistics across all content states
		$kpis = $this->generated_content_repository->get_content_kpis();

		// Get unified content entries (completed, draft review, partials)
		$content_result = $this->generated_content_repository->get_unified_content(array(
			'page'        => $generated_page,
			'per_page'    => $per_page,
			'search'      => $search_query,
			'author_id'   => $author_id,
			'template_id' => $template_id,
			'campaign_id' => $campaign_id,
			'status'      => $post_status,
			'post_type'   => $post_type_filter,
		));
		
		// Hoist date/time format lookups outside of loops to prevent N+1 query overhead
		$date_format = get_option('date_format');
		$time_format = get_option('time_format');
		$datetime_format = $date_format . ' ' . $time_format;

		// Get rich post and schedule data for each post
		$posts_data = array();
		$grouped_posts = array();

		foreach ($content_result['items'] as $item) {
			$post_id = !empty($item->post_id) ? (int) $item->post_id : (!empty($item->wp_post_id) ? (int) $item->wp_post_id : 0);
			$post = $post_id ? get_post($post_id) : null;

			// Incomplete check
			$is_incomplete = (!empty($item->is_currently_incomplete) || (isset($item->history_status) && $item->history_status === 'failed'));
			$missing_components = $this->get_missing_components(isset($item->component_statuses) ? $item->component_statuses : null);

			// Timestamps & dates
			$created_at = !empty($item->created_at) ? (int) $item->created_at : 0;
			$date_generated = AIPS_DateTime::formatRelativeOrAbsolute($created_at, $datetime_format);
			
			$raw_status = $post ? $post->post_status : (!empty($item->post_status) ? $item->post_status : ($is_incomplete ? 'incomplete' : 'draft'));
			$is_pending_review = ($raw_status === 'draft' || $raw_status === 'pending') && !$is_incomplete;

			// Source format
			$source = $this->format_source($item);

			// Featured image thumbnail
			$thumb_url = '';
			$thumb_medium_url = '';
			if ($post) {
				$thumb_id = get_post_thumbnail_id($post->ID);
				$thumb_url = $thumb_id ? wp_get_attachment_image_url($thumb_id, 'thumbnail') : '';
				$thumb_medium_url = $thumb_id ? wp_get_attachment_image_url($thumb_id, 'medium') : '';
			}

			// Word count and reading time
			$clean_content = $post ? wp_strip_all_tags($post->post_content) : (!empty($item->post_content) ? wp_strip_all_tags($item->post_content) : '');
			$word_count = str_word_count($clean_content);
			$reading_time = max(1, (int) ceil($word_count / 200));

			// Author info
			$item_author_name = '';
			$item_author_avatar = '';
			if (!empty($item->author_id)) {
				if (!isset($this->author_cache[$item->author_id])) {
					$authors_repo = new AIPS_Authors_Repository();
					$this->author_cache[$item->author_id] = $authors_repo->get_by_id($item->author_id);
				}
				$author_obj = $this->author_cache[$item->author_id];
				if ($author_obj) {
					$item_author_name = $author_obj->name;
					$item_author_avatar = !empty($author_obj->avatar_url) ? $author_obj->avatar_url : '';
				}
			}
			if (empty($item_author_name) && $post) {
				$wp_author = get_userdata($post->post_author);
				$item_author_name = $wp_author ? $wp_author->display_name : __('AI Scheduler', 'ai-post-scheduler');
				$item_author_avatar = get_avatar_url($post->post_author, array('size' => 48));
			}

			// Campaign info
			$item_campaign_id = !empty($item->campaign_id) ? (int) $item->campaign_id : 0;
			$item_campaign_name = '';
			if ($item_campaign_id > 0) {
				if (!isset($this->campaign_cache[$item_campaign_id])) {
					$campaigns_found = AIPS_Campaigns_Repository::instance()->get_campaigns(null, $item_campaign_id);
					$this->campaign_cache[$item_campaign_id] = !empty($campaigns_found) ? $campaigns_found[0] : null;
				}
				$campaign_obj = $this->campaign_cache[$item_campaign_id];
				if ($campaign_obj && isset($campaign_obj->name)) {
					$item_campaign_name = $campaign_obj->name;
				}
			}

			// Template info
			$item_template_id = !empty($item->template_id) ? (int) $item->template_id : 0;
			$item_template_name = '';
			if ($item_template_id > 0) {
				if (!isset($this->template_cache[$item_template_id])) {
					$template_repo = new AIPS_Template_Repository();
					$this->template_cache[$item_template_id] = $template_repo->get_by_id($item_template_id);
				}
				$template_obj = $this->template_cache[$item_template_id];
				if ($template_obj && isset($template_obj->name)) {
					$item_template_name = $template_obj->name;
				}
			}

			// Topic info
			$item_topic_id = !empty($item->topic_id) ? (int) $item->topic_id : 0;
			$item_topic_title = '';
			if ($item_topic_id > 0) {
				if (!isset($this->topic_cache[$item_topic_id])) {
					$topics_repo = new AIPS_Author_Topics_Repository();
					$this->topic_cache[$item_topic_id] = $topics_repo->get_by_id($item_topic_id);
				}
				$topic_obj = $this->topic_cache[$item_topic_id];
				if ($topic_obj && isset($topic_obj->topic_title)) {
					$item_topic_title = $topic_obj->topic_title;
				}
			}

			// AI telemetry
			$duration_seconds = (!empty($item->completed_at) && !empty($item->created_at) && $item->completed_at >= $item->created_at)
				? round((float) ($item->completed_at - $item->created_at), 1)
				: null;

			$post_title = $post ? ($post->post_title ? $post->post_title : __('(No Title)', 'ai-post-scheduler')) : (!empty($item->generated_title) ? $item->generated_title : __('(Incomplete Post)', 'ai-post-scheduler'));

			$post_item = array(
				'history_id'          => $item->history_id,
				'post_id'             => $post_id,
				'title'               => $post_title,
				'post_status'         => $raw_status,
				'post_status_label'   => $is_incomplete ? __('Incomplete', 'ai-post-scheduler') : $this->format_post_status($raw_status),
				'is_incomplete'       => $is_incomplete,
				'is_pending_review'   => $is_pending_review,
				'missing_components'  => $missing_components,
				'permalink'           => $post_id ? get_permalink($post_id) : '',
				'edit_link'           => $post_id ? esc_url_raw(get_edit_post_link($post_id)) : '',
				'thumb_url'           => $thumb_url,
				'thumb_medium_url'    => $thumb_medium_url,
				'word_count'          => $word_count,
				'reading_time'        => $reading_time,
				'author_name'         => $item_author_name,
				'author_avatar'       => $item_author_avatar,
				'campaign_id'         => $item_campaign_id,
				'campaign_name'       => $item_campaign_name,
				'template_id'         => $item_template_id,
				'template_name'       => $item_template_name,
				'topic_id'            => $item_topic_id,
				'topic_title'         => $item_topic_title,
				'duration_seconds'    => $duration_seconds,
				'ai_call_count'       => 0,
				'date_generated'      => $date_generated,
				'date_published'      => ($post && $post->post_status === 'publish') ? AIPS_DateTime::formatRelativeOrAbsolute((int) get_post_time('U', true, $post), $datetime_format) : '',
				'created_at_raw'      => $created_at,
				'source'              => $source,
			);

			$posts_data[] = $post_item;

			// Determine group key and label
			$group_key = 'all';
			$group_title = __('All Posts', 'ai-post-scheduler');
			$group_icon = 'dashicons-admin-post';
			$group_meta = '';

			if ($group_by === 'status') {
				if ($is_incomplete) {
					$group_key = 'status_incomplete';
					$group_title = __('Incomplete / Missing Components', 'ai-post-scheduler');
					$group_icon = 'dashicons-warning';
				} elseif ($is_pending_review) {
					$group_key = 'status_review';
					$group_title = __('Pending Review', 'ai-post-scheduler');
					$group_icon = 'dashicons-edit-page';
				} elseif ($raw_status === 'future') {
					$group_key = 'status_future';
					$group_title = __('Scheduled / Queued', 'ai-post-scheduler');
					$group_icon = 'dashicons-calendar-alt';
				} elseif ($raw_status === 'publish') {
					$group_key = 'status_publish';
					$group_title = __('Published', 'ai-post-scheduler');
					$group_icon = 'dashicons-yes-alt';
				} else {
					$group_key = 'status_' . sanitize_key($raw_status);
					$group_title = $this->format_post_status($raw_status);
					$group_icon = 'dashicons-admin-post';
				}
			} elseif ($group_by === 'campaign') {
				if ($item_campaign_id > 0 && !empty($item_campaign_name)) {
					$group_key = 'campaign_' . $item_campaign_id;
					$group_title = $item_campaign_name;
					$group_icon = 'dashicons-megaphone';
				} else {
					$group_key = 'campaign_standalone';
					$group_title = __('Standalone / No Campaign', 'ai-post-scheduler');
					$group_icon = 'dashicons-tag';
				}
			} elseif ($group_by === 'template') {
				if ($item_template_id > 0 && !empty($item_template_name)) {
					$group_key = 'template_' . $item_template_id;
					$group_title = $item_template_name;
					$group_icon = 'dashicons-layout';
				} else {
					$group_key = 'template_none';
					$group_title = __('No Template', 'ai-post-scheduler');
					$group_icon = 'dashicons-media-document';
				}
			} elseif ($group_by === 'author') {
				$group_key = 'author_' . sanitize_title($item_author_name);
				$group_title = $item_author_name;
				$group_icon = 'dashicons-admin-users';
			} elseif ($group_by === 'date') {
				$date_label = $this->get_date_group_label($created_at);
				$group_key = 'date_' . sanitize_title($date_label);
				$group_title = $date_label;
				$group_icon = 'dashicons-calendar-alt';
			}

			if (!isset($grouped_posts[$group_key])) {
				$grouped_posts[$group_key] = array(
					'key'   => $group_key,
					'title' => $group_title,
					'icon'  => $group_icon,
					'meta'  => $group_meta,
					'posts' => array(),
				);
			}

			$grouped_posts[$group_key]['posts'][] = $post_item;
		}

		$current_page = $generated_page;
		$active_status = $post_status;
		$current_group_by = $group_by;
		$current_view_mode = $view_mode;
		$current_per_page = $per_page;

		// Calculate pagination bounds for unified content view
		$pagination = array(
			'current' => (int) $generated_page,
			'pages'   => (int) (isset($content_result['pages']) ? $content_result['pages'] : 1),
			'start'   => max(1, (int) $generated_page - 3),
			'end'     => min((int) (isset($content_result['pages']) ? $content_result['pages'] : 1), (int) $generated_page + 3),
			'total'   => (int) (isset($content_result['total']) ? $content_result['total'] : 0),
		);
		
		// Get templates for filter dropdown
		$template_repository = new AIPS_Template_Repository();
		$templates = $template_repository->get_all();
		$campaigns = AIPS_Campaigns_Repository::instance()->get_campaign_filter_options();

		// Get authors for filter dropdown
		$authors_repository = new AIPS_Authors_Repository();
		$authors = $authors_repository->get_all();

		// Get selectable post types for filter dropdown
		$selectable_post_types = AIPS_Utilities::get_selectable_post_types();

		// Get globally-initialized Post Review handler
		global $aips_post_review_handler;
		$post_review_handler = isset($aips_post_review_handler) ? $aips_post_review_handler : $this->post_review_repository;
		
		// Make controller available to template for formatting
		$controller = $this;
		
		include AIPS_PLUGIN_DIR . 'templates/admin/content.php';
	}

	/**
	 * Build a paginated URL for the Generated Posts tab preserving all active filters with optional overrides.
	 *
	 * @param int   $page_number Target page number.
	 * @param array $overrides   Optional argument overrides.
	 * @return string
	 */
	public function build_generated_posts_page_url($page_number, $overrides = array()) {
		$base_url = AIPS_Admin_Menu_Helper::get_page_url('generated_posts');
		$state = array_merge($this->active_state, $overrides);

		return add_query_arg(array_filter(array(
			'generated_paged' => absint($page_number),
			'author_id'       => !empty($state['author_id']) ? $state['author_id'] : false,
			'template_id'     => !empty($state['template_id']) ? $state['template_id'] : false,
			'campaign_id'     => !empty($state['campaign_id']) ? $state['campaign_id'] : false,
			'post_status'     => !empty($state['post_status']) ? $state['post_status'] : false,
			'post_type'       => !empty($state['post_type']) ? $state['post_type'] : false,
			'group_by'        => (!empty($state['group_by']) && $state['group_by'] !== 'campaign') ? $state['group_by'] : false,
			'view_mode'       => (!empty($state['view_mode']) && $state['view_mode'] !== 'grouped') ? $state['view_mode'] : false,
			'per_page'        => (!empty($state['per_page']) && (int) $state['per_page'] !== 20) ? $state['per_page'] : false,
			's'               => !empty($state['s']) ? $state['s'] : false,
		)), $base_url);
	}


	/**
	 * Helper to group dates into clean readable buckets

	 *
	 * @param int $timestamp Unix timestamp.
	 * @return string
	 */
	public function get_date_group_label($timestamp) {
		if (!$timestamp) {
			return __('Unknown Date', 'ai-post-scheduler');
		}

		$now = current_time('timestamp');
		$diff = $now - (int) $timestamp;
		$post_date_str = date_i18n('Y-m-d', $timestamp);
		$today_str = date_i18n('Y-m-d', $now);
		$yesterday_str = date_i18n('Y-m-d', $now - DAY_IN_SECONDS);

		if ($post_date_str === $today_str) {
			return __('Today', 'ai-post-scheduler');
		} elseif ($post_date_str === $yesterday_str) {
			return __('Yesterday', 'ai-post-scheduler');
		} elseif ($diff > 0 && $diff < 7 * DAY_IN_SECONDS) {
			return __('This Week', 'ai-post-scheduler');
		} elseif ($diff > 0 && $diff < 30 * DAY_IN_SECONDS) {
			return __('This Month', 'ai-post-scheduler');
		} else {
			return date_i18n('F Y', $timestamp);
		}
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
						'log_type' => isset($details['log_subtype']) ? (string) $details['log_subtype'] : '',
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
		$timestamp = AIPS_DateTime::now()->toDisplay('Ymd-His');
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

	/**
	 * AJAX handler to quickly update the post status (Draft, Publish, Trash, etc.)
	 */
	public function ajax_update_post_status() {
		if ( ! check_ajax_referer('aips_ajax_nonce', 'nonce', false) ) {
			AIPS_Ajax_Response::error(__('Invalid nonce.', 'ai-post-scheduler'));
		}
		
		if (!current_user_can('manage_options')) {
			AIPS_Ajax_Response::permission_denied();
		}
		
		$post_id = isset($_POST['post_id']) ? absint($_POST['post_id']) : 0;
		$new_status = isset($_POST['new_status']) ? sanitize_key($_POST['new_status']) : '';
		
		$allowed_statuses = array('publish', 'draft', 'trash', 'pending', 'future', 'private');
		if (!$post_id || !in_array($new_status, $allowed_statuses, true)) {
			AIPS_Ajax_Response::error(__('Invalid post ID or status.', 'ai-post-scheduler'));
		}
		
		$post = get_post($post_id);
		if (!$post) {
			AIPS_Ajax_Response::error(__('Post not found.', 'ai-post-scheduler'));
		}
		
		if ($new_status === 'trash') {
			$result = wp_trash_post($post_id);
		} else {
			$result = wp_update_post(array(
				'ID'          => $post_id,
				'post_status' => $new_status,
			), true);
		}
		
		if (is_wp_error($result)) {
			AIPS_Ajax_Response::error($result->get_error_message());
		}
		
		$status_label = $this->format_post_status($new_status);
		
		AIPS_Ajax_Response::success(array(
			'post_id'      => $post_id,
			'new_status'   => $new_status,
			'status_label' => $status_label,
			'message'      => sprintf(__('Post status updated to %s.', 'ai-post-scheduler'), $status_label),
		));
	}

	/**
	 * AJAX handler for bulk actions on generated posts
	 */
	public function ajax_bulk_generated_posts_action() {
		if ( ! check_ajax_referer('aips_ajax_nonce', 'nonce', false) ) {
			AIPS_Ajax_Response::error(__('Invalid nonce.', 'ai-post-scheduler'));
		}
		
		if (!current_user_can('manage_options')) {
			AIPS_Ajax_Response::permission_denied();
		}
		
		$post_ids = isset($_POST['post_ids']) ? array_filter(array_map('absint', (array) $_POST['post_ids'])) : array();
		$bulk_action = isset($_POST['bulk_action']) ? sanitize_key($_POST['bulk_action']) : '';
		
		if (empty($post_ids)) {
			AIPS_Ajax_Response::error(__('No posts selected.', 'ai-post-scheduler'));
		}
		
		$affected = 0;
		switch ($bulk_action) {
			case 'trash':
				foreach ($post_ids as $pid) {
					if (wp_trash_post($pid)) {
						$affected++;
					}
				}
				$message = sprintf(__('%d post(s) moved to trash.', 'ai-post-scheduler'), $affected);
				break;
				
			case 'publish':
				foreach ($post_ids as $pid) {
					if (wp_publish_post($pid) || wp_update_post(array('ID' => $pid, 'post_status' => 'publish'))) {
						$affected++;
					}
				}
				$message = sprintf(__('%d post(s) published.', 'ai-post-scheduler'), $affected);
				break;
				
			case 'draft':
				foreach ($post_ids as $pid) {
					if (wp_update_post(array('ID' => $pid, 'post_status' => 'draft'))) {
						$affected++;
					}
				}
				$message = sprintf(__('%d post(s) set to draft.', 'ai-post-scheduler'), $affected);
				break;
				
			default:
				AIPS_Ajax_Response::error(__('Invalid bulk action.', 'ai-post-scheduler'));
				return;
		}
		
		AIPS_Ajax_Response::success(array(
			'affected' => $affected,
			'message'  => $message,
		));
	}
}

