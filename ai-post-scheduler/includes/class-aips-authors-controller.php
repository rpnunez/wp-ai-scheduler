<?php
/**
 * Authors Controller
 *
 * Handles AJAX requests for author management.
 *
 * @package AI_Post_Scheduler
 * @since 1.8.0
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Authors_Controller
 *
 * Manages AJAX endpoints for author CRUD operations.
 */
class AIPS_Authors_Controller {

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
	 * @var AIPS_Authors_Repository Repository for authors
	 */
	private $repository;
	
	/**
	 * @var AIPS_Author_Topics_Repository Repository for topics
	 */
	private $topics_repository;
	
	/**
	 * @var AIPS_Author_Topic_Logs_Repository Repository for logs
	 */
	private $logs_repository;
	
	/**
	 * @var AIPS_Feedback_Repository Repository for feedback
	 */
	private $feedback_repository;
	
	/**
	 * @var AIPS_Author_Topics_Scheduler Topics scheduler
	 */
	private $topics_scheduler;

	/**
	 * @var AIPS_Notifications Notifications service
	 */
	private $notifications;

	/**
	 * Initialize the controller.
	 */
	public function __construct() {
		$this->repository = new AIPS_Authors_Repository();
		$this->topics_repository = new AIPS_Author_Topics_Repository();
		$this->logs_repository = new AIPS_Author_Topic_Logs_Repository();
		$this->feedback_repository = new AIPS_Feedback_Repository();
		$this->topics_scheduler = new AIPS_Author_Topics_Scheduler();
		$this->notifications = new AIPS_Notifications();
		
		// Register AJAX endpoints
		add_action('wp_ajax_aips_save_author', array($this, 'ajax_save_author'));
		add_action('wp_ajax_aips_delete_author', array($this, 'ajax_delete_author'));
		add_action('wp_ajax_aips_get_author', array($this, 'ajax_get_author'));
		add_action('wp_ajax_aips_get_author_topics', array($this, 'ajax_get_author_topics'));
		add_action('wp_ajax_aips_get_author_posts', array($this, 'ajax_get_author_posts'));
		add_action('wp_ajax_aips_get_author_feedback', array($this, 'ajax_get_author_feedback'));
		add_action('wp_ajax_aips_generate_topics_now', array($this, 'ajax_generate_topics_now'));
		add_action('wp_ajax_aips_get_topic_posts', array($this, 'ajax_get_topic_posts'));
		add_action('wp_ajax_aips_suggest_authors', array($this, 'ajax_suggest_authors'));
	}
	
	/**
	 * AJAX handler for saving an author.
	 */
	public function ajax_save_author() {
		if ( ! check_ajax_referer('aips_ajax_nonce', 'nonce', false) ) {
            AIPS_Ajax_Response::error(__('Invalid nonce.', 'ai-post-scheduler'));
        }
		
		if (!current_user_can('manage_options')) {
			AIPS_Ajax_Response::permission_denied();
		}
		
		// Sanitize and validate input
		$author_id = isset($_POST['author_id']) ? absint($_POST['author_id']) : 0;
		$name = isset($_POST['name']) ? sanitize_text_field(wp_unslash($_POST['name'])) : '';
		$field_niche = isset($_POST['field_niche']) ? sanitize_text_field(wp_unslash($_POST['field_niche'])) : '';
		
		if (empty($name) || empty($field_niche)) {
			AIPS_Ajax_Response::error(__('Name and Field/Niche are required.', 'ai-post-scheduler'));
		}
		
		// Build author data
		$data = array(
			'name' => $name,
			'field_niche' => $field_niche,
			'description' => isset($_POST['description']) ? sanitize_textarea_field(wp_unslash($_POST['description'])) : '',
			'keywords' => isset($_POST['keywords']) ? sanitize_text_field(wp_unslash($_POST['keywords'])) : '',
			'details' => isset($_POST['details']) ? sanitize_textarea_field(wp_unslash($_POST['details'])) : '',
			'article_structure_id' => !empty($_POST['article_structure_id']) ? absint($_POST['article_structure_id']) : null,
			'topic_generation_prompt' => isset($_POST['topic_generation_prompt']) ? sanitize_textarea_field(wp_unslash($_POST['topic_generation_prompt'])) : '',
			'topic_generation_frequency' => isset($_POST['topic_generation_frequency']) ? sanitize_text_field(wp_unslash($_POST['topic_generation_frequency'])) : 'weekly',
			'topic_generation_quantity' => isset($_POST['topic_generation_quantity']) ? absint($_POST['topic_generation_quantity']) : 5,
			'post_generation_frequency' => isset($_POST['post_generation_frequency']) ? sanitize_text_field(wp_unslash($_POST['post_generation_frequency'])) : 'daily',
			'post_status' => isset($_POST['post_status']) ? sanitize_text_field(wp_unslash($_POST['post_status'])) : 'draft',
			'post_category' => isset($_POST['post_category']) ? absint($_POST['post_category']) : null,
			'post_tags' => isset($_POST['post_tags']) ? sanitize_text_field(wp_unslash($_POST['post_tags'])) : '',
			'post_author' => isset($_POST['post_author']) ? absint($_POST['post_author']) : get_current_user_id(),
			'generate_featured_image' => isset($_POST['generate_featured_image']) ? 1 : 0,
			'featured_image_source' => isset($_POST['featured_image_source']) ? sanitize_text_field(wp_unslash($_POST['featured_image_source'])) : 'ai_prompt',
			'voice_tone' => isset($_POST['voice_tone']) ? sanitize_text_field(wp_unslash($_POST['voice_tone'])) : '',
			'writing_style' => isset($_POST['writing_style']) ? sanitize_text_field(wp_unslash($_POST['writing_style'])) : '',
			// New expanded author profile fields
			'target_audience' => isset($_POST['target_audience']) ? sanitize_text_field(wp_unslash($_POST['target_audience'])) : '',
			'expertise_level' => isset($_POST['expertise_level']) ? sanitize_text_field(wp_unslash($_POST['expertise_level'])) : '',
			'content_goals' => isset($_POST['content_goals']) ? sanitize_textarea_field(wp_unslash($_POST['content_goals'])) : '',
			'excluded_topics' => isset($_POST['excluded_topics']) ? sanitize_textarea_field(wp_unslash($_POST['excluded_topics'])) : '',
			'preferred_content_length' => isset($_POST['preferred_content_length']) ? sanitize_text_field(wp_unslash($_POST['preferred_content_length'])) : '',
			'language' => isset($_POST['language']) ? sanitize_text_field(wp_unslash($_POST['language'])) : 'en',
			'max_posts_per_topic' => isset($_POST['max_posts_per_topic']) ? max(1, absint($_POST['max_posts_per_topic'])) : 1,
			// Source group fields
			'include_sources' => isset($_POST['include_sources']) ? 1 : 0,
			'source_group_ids' => isset($_POST['source_group_ids']) && is_array($_POST['source_group_ids'])
				? wp_json_encode(array_map('absint', $_POST['source_group_ids']))
				: wp_json_encode(array()),
			'is_active' => isset($_POST['is_active']) ? 1 : 0
		);
		
		// Set initial run times to now so first execution is not skipped
		if (!$author_id) {
			$now = current_time('mysql');
			$data['topic_generation_next_run'] = $now;
			$data['post_generation_next_run'] = $now;
		}
		
		// Save or update
		if ($author_id) {
			$result = $this->repository->update($author_id, $data);
			$id = $author_id;
		} else {
			$id = $this->repository->create($data);
			$result = $id !== false;
		}
		
		if ($result) {
			AIPS_Ajax_Response::success(array(
				'message' => __('Author saved successfully.', 'ai-post-scheduler'),
				'author_id' => $id
			));
		} else {
			AIPS_Ajax_Response::error(__('Failed to save author.', 'ai-post-scheduler'));
		}
	}
	
	/**
	 * AJAX handler for deleting an author.
	 */
	public function ajax_delete_author() {
		if ( ! check_ajax_referer('aips_ajax_nonce', 'nonce', false) ) {
            AIPS_Ajax_Response::error(__('Invalid nonce.', 'ai-post-scheduler'));
        }
		
		if (!current_user_can('manage_options')) {
			AIPS_Ajax_Response::permission_denied();
		}
		
		$author_id = isset($_POST['author_id']) ? absint($_POST['author_id']) : 0;
		
		if (!$author_id) {
			AIPS_Ajax_Response::error(__('Invalid author ID.', 'ai-post-scheduler'));
		}
		
		// Delete child records first to avoid orphaned records

		// Get all topic IDs for this author via repository
		$topics    = $this->topics_repository->get_by_author($author_id);
		$topic_ids = array_map(function ($t) { return (int) $t->id; }, $topics);

		// Delete logs for these topics via repository
		if (!empty($topic_ids)) {
			$this->logs_repository->delete_by_topic_ids($topic_ids);
		}

		// Delete topics via repository
		$this->topics_repository->delete_by_author($author_id);
		
		// Delete author
		$result = $this->repository->delete($author_id);
		
		if ($result) {
			AIPS_Ajax_Response::success(array(), __('Author deleted successfully.', 'ai-post-scheduler'));
		} else {
			AIPS_Ajax_Response::error(__('Failed to delete author.', 'ai-post-scheduler'));
		}
	}
	
	/**
	 * AJAX handler for getting an author.
	 */
	public function ajax_get_author() {
		if ( ! check_ajax_referer('aips_ajax_nonce', 'nonce', false) ) {
            AIPS_Ajax_Response::error(__('Invalid nonce.', 'ai-post-scheduler'));
        }
		
		if (!current_user_can('manage_options')) {
			AIPS_Ajax_Response::permission_denied();
		}
		
		$author_id = isset($_POST['author_id']) ? absint($_POST['author_id']) : 0;
		
		if (!$author_id) {
			AIPS_Ajax_Response::error(__('Invalid author ID.', 'ai-post-scheduler'));
		}
		
		$author = $this->repository->get_by_id($author_id);
		
		if ($author) {
			AIPS_Ajax_Response::success(array('author' => $author));
		} else {
			AIPS_Ajax_Response::error(__('Author not found.', 'ai-post-scheduler'));
		}
	}
	
	/**
	 * AJAX handler for getting author topics.
	 */
	public function ajax_get_author_topics() {
		if ( ! check_ajax_referer('aips_ajax_nonce', 'nonce', false) ) {
            AIPS_Ajax_Response::error(__('Invalid nonce.', 'ai-post-scheduler'));
        }
		
		if (!current_user_can('manage_options')) {
			AIPS_Ajax_Response::permission_denied();
		}
		
		$author_id = isset($_POST['author_id']) ? absint($_POST['author_id']) : 0;
		$status = isset($_POST['status']) ? sanitize_text_field(wp_unslash($_POST['status'])) : null;
		
		if (!$author_id) {
			AIPS_Ajax_Response::error(__('Invalid author ID.', 'ai-post-scheduler'));
		}
		
		// For the special "posts_generated" tab, we need to consider all topics
		// for this author, then filter by whether they have generated posts.
		// For other tabs, we continue to filter by status at the database level
		// for efficiency, and then further refine by post_count where needed.
		if ('posts_generated' === $status) {
			$topics = $this->topics_repository->get_by_author($author_id, null);
		} else {
			$topics = $this->topics_repository->get_by_author($author_id, $status);
		}
		$status_counts = $this->topics_repository->get_status_counts($author_id);
		// Augment status counts with posts_generated using the same logic as the
		// Posts Generated stat card on the Author Topics page.
		$posts_generated_count = $this->logs_repository->count_generated_posts_by_author($author_id);
		$status_counts['posts_generated'] = (int) $posts_generated_count;
		$topic_ids = array();
		foreach ($topics as $topic) {
			$topic_ids[] = (int) $topic->id;
		}
		$latest_feedback_by_topic = $this->feedback_repository->get_latest_by_topics($topic_ids);
		
		// Add post count and latest feedback summary to each topic.
		foreach ($topics as &$topic) {
			$logs = $this->logs_repository->get_by_topic($topic->id);
			$post_count = 0;
			foreach ($logs as $log) {
				if ($log->action === 'post_generated' && $log->post_id) {
					$post_count++;
				}
			}
			$topic->post_count = $post_count;
			
			$topic->last_feedback = null;
			if (isset($latest_feedback_by_topic[(int) $topic->id])) {
				$feedback = $latest_feedback_by_topic[(int) $topic->id];
				$topic->last_feedback = array(
					'action' => $feedback->action,
					'reason_category' => $feedback->reason_category,
					'reason' => $feedback->reason,
					'created_at' => $feedback->created_at,
				);
			}
			
			$topic->potential_duplicate = false;
			$topic->duplicate_match = '';
			if (!empty($topic->metadata)) {
				$metadata = json_decode($topic->metadata, true);
				if (is_array($metadata) && !empty($metadata['potential_duplicate'])) {
					$topic->potential_duplicate = true;
					$topic->duplicate_match = isset($metadata['duplicate_match']) ? (string) $metadata['duplicate_match'] : '';
				}
			}
		}
		unset($topic);
		
		// Refine the topic collection based on the active tab semantics:
		// - "approved" tab: only approved topics that have NO generated posts yet.
		// - "rejected" tab: only rejected topics that have NO generated posts yet.
		// - "posts_generated" tab: any topics (regardless of current status)
		//   that have one or more generated posts associated with them.
		if ('approved' === $status) {
			$topics = array_values(array_filter($topics, function ($topic) {
				return ('approved' === $topic->status && (int) $topic->post_count === 0);
			}));
		} elseif ('rejected' === $status) {
			$topics = array_values(array_filter($topics, function ($topic) {
				return ('rejected' === $topic->status && (int) $topic->post_count === 0);
			}));
		} elseif ('posts_generated' === $status) {
			$topics = array_values(array_filter($topics, function ($topic) {
				return (int) $topic->post_count > 0;
			}));
		}
		
		AIPS_Ajax_Response::success(array(
			'topics' => $topics,
			'status_counts' => $status_counts
		));
	}

	/**
	 * AJAX handler for getting author generated posts.
	 */
	public function ajax_get_author_posts() {
		if ( ! check_ajax_referer('aips_ajax_nonce', 'nonce', false) ) {
            AIPS_Ajax_Response::error(__('Invalid nonce.', 'ai-post-scheduler'));
        }
		
		if (!current_user_can('manage_options')) {
			AIPS_Ajax_Response::permission_denied();
		}
		
		$author_id = isset($_POST['author_id']) ? absint($_POST['author_id']) : 0;
		
		if (!$author_id) {
			AIPS_Ajax_Response::error(__('Invalid author ID.', 'ai-post-scheduler'));
		}
		
		$posts = $this->logs_repository->get_generated_posts_by_author($author_id);
		
		// Enrich with WordPress post data
		foreach ($posts as &$post) {
			if ($post->post_id) {
				$wp_post = get_post($post->post_id);
				if ($wp_post) {
					$post->post_title = $wp_post->post_title;
					$post->post_status = $wp_post->post_status;
					$post->post_url = esc_url_raw(get_permalink($wp_post->ID));
					$post->edit_url = esc_url_raw(get_edit_post_link($wp_post->ID, 'raw'));
				}
			}
		}
		
		AIPS_Ajax_Response::success(array('posts' => $posts));
	}
	
	/**
	 * AJAX handler for manually generating topics now.
	 */
	public function ajax_generate_topics_now() {
		if ( ! check_ajax_referer('aips_ajax_nonce', 'nonce', false) ) {
            AIPS_Ajax_Response::error(__('Invalid nonce.', 'ai-post-scheduler'));
        }
		
		if (!current_user_can('manage_options')) {
			AIPS_Ajax_Response::permission_denied();
		}
		
		$author_id = isset($_POST['author_id']) ? absint($_POST['author_id']) : 0;
		
		if (!$author_id) {
			AIPS_Ajax_Response::error(__('Invalid author ID.', 'ai-post-scheduler'));
		}
		
		$result = $this->topics_scheduler->generate_now($author_id);

		if (is_wp_error($result)) {
			AIPS_Ajax_Response::error(array('message' => $result->get_error_message()));
		}

		// Create admin bar notification for manual topic generation
		$author = $this->repository->get_by_id($author_id);
		if ($author && is_array($result)) {
			$this->notifications->author_topics_generated($author->name, count($result), $author_id);
		}

		AIPS_Ajax_Response::success(array(
			'message' => __('Topics generated successfully.', 'ai-post-scheduler'),
			'topics' => $result
		));
	}
	
	/**
	 * AJAX handler for getting author feedback.
	 */
	public function ajax_get_author_feedback() {
		if ( ! check_ajax_referer('aips_ajax_nonce', 'nonce', false) ) {
            AIPS_Ajax_Response::error(__('Invalid nonce.', 'ai-post-scheduler'));
        }
		
		if (!current_user_can('manage_options')) {
			AIPS_Ajax_Response::permission_denied();
		}
		
		$author_id = isset($_POST['author_id']) ? absint($_POST['author_id']) : 0;
		
		if (!$author_id) {
			AIPS_Ajax_Response::error(__('Invalid author ID.', 'ai-post-scheduler'));
		}
		
		$feedback = $this->feedback_repository->get_by_author($author_id);
		
		// Get user display names
		foreach ($feedback as $item) {
			if ($item->user_id) {
				$user = get_userdata($item->user_id);
				$item->user_name = $user ? $user->display_name : __('Unknown', 'ai-post-scheduler');
			} else {
				$item->user_name = __('System', 'ai-post-scheduler');
			}
		}
		
		AIPS_Ajax_Response::success(array('feedback' => $feedback));
	}
	
	/**
	 * AJAX handler for getting posts associated with a specific topic.
	 */
	public function ajax_get_topic_posts() {
		if ( ! check_ajax_referer('aips_ajax_nonce', 'nonce', false) ) {
            AIPS_Ajax_Response::error(__('Invalid nonce.', 'ai-post-scheduler'));
        }
		
		if (!current_user_can('manage_options')) {
			AIPS_Ajax_Response::permission_denied();
		}
		
		$topic_id = isset($_POST['topic_id']) ? absint($_POST['topic_id']) : 0;
		
		if (!$topic_id) {
			AIPS_Ajax_Response::error(__('Invalid topic ID.', 'ai-post-scheduler'));
		}
		
		// Get the topic details
		$topic = $this->topics_repository->get_by_id($topic_id);
		
		if (!$topic) {
			AIPS_Ajax_Response::error(__('Topic not found.', 'ai-post-scheduler'));
		}
		
		// Get all logs for this topic
		$logs = $this->logs_repository->get_by_topic($topic_id);
		
		$posts = array();
		foreach ($logs as $log) {
			// Only include post_generated logs with valid post IDs.
			if ($log->action === 'post_generated' && $log->post_id) {
				$wp_post = get_post($log->post_id);
				if ($wp_post) {
					$view_url = 'publish' === $wp_post->post_status
						? get_permalink($wp_post->ID)
						: get_preview_post_link($wp_post->ID);

					$posts[] = array(
						'post_id' => $log->post_id,
						'post_title' => $wp_post->post_title,
						'post_status' => $wp_post->post_status,
						'post_excerpt' => wp_strip_all_tags(get_the_excerpt($wp_post->ID)),
						'featured_image_url' => esc_url_raw((string) get_the_post_thumbnail_url($wp_post->ID, 'medium')),
						'date_generated' => $log->created_at,
						'date_published' => $wp_post->post_status === 'publish' ? $wp_post->post_date : null,
						'post_url' => $view_url ? esc_url_raw($view_url) : '',
						'edit_url' => esc_url_raw(get_edit_post_link($wp_post->ID, 'raw'))
					);
				}
			}
		}
		
		AIPS_Ajax_Response::success(array(
			'topic' => $topic,
			'posts' => $posts
		));
	}

	/**
	 * AJAX handler for generating AI-powered author profile suggestions.
	 *
	 * Accepts site context inputs and returns an array of suggested author
	 * profiles that the admin can review and import with one click.
	 */
	public function ajax_suggest_authors() {
		if ( ! check_ajax_referer('aips_ajax_nonce', 'nonce', false) ) {
            AIPS_Ajax_Response::error(__('Invalid nonce.', 'ai-post-scheduler'));
        }

		if (!current_user_can('manage_options')) {
			AIPS_Ajax_Response::permission_denied();
		}

		$site_niche      = isset($_POST['site_niche']) ? sanitize_text_field(wp_unslash($_POST['site_niche'])) : '';
		$target_audience = isset($_POST['target_audience']) ? sanitize_text_field(wp_unslash($_POST['target_audience'])) : '';
		$content_goals   = isset($_POST['content_goals']) ? sanitize_textarea_field(wp_unslash($_POST['content_goals'])) : '';
		$site_url        = isset($_POST['site_url']) ? esc_url_raw(wp_unslash($_POST['site_url'])) : '';
		$count           = isset($_POST['count']) ? absint($_POST['count']) : 3;

		if (empty($site_niche)) {
			AIPS_Ajax_Response::error(__('Site niche is required.', 'ai-post-scheduler'));
		}

		$service = new AIPS_Author_Suggestions_Service();
		$suggestions = $service->suggest_authors(array(
			'site_niche'      => $site_niche,
			'target_audience' => $target_audience,
			'content_goals'   => $content_goals,
			'site_url'        => $site_url,
		), $count);

		if (is_wp_error($suggestions)) {
			AIPS_Ajax_Response::error(array('message' => $suggestions->get_error_message()));
		}

		do_action('aips_author_suggestions_generated', array(
			'count'       => count($suggestions),
			'site_niche'  => $site_niche,
			'user_id'     => get_current_user_id(),
		));

		AIPS_Ajax_Response::success(array(
			'suggestions' => $suggestions,
			'message'     => sprintf(
				/* translators: %d: number of author suggestions generated */
				_n('%d author suggestion generated.', '%d author suggestions generated.', count($suggestions), 'ai-post-scheduler'),
				count($suggestions)
			),
		));
	}
}