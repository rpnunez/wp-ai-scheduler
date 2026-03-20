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
	 * Initialize the controller.
	 */
	public function __construct() {
		$this->repository = new AIPS_Authors_Repository();
		$this->topics_repository = new AIPS_Author_Topics_Repository();
		$this->logs_repository = new AIPS_Author_Topic_Logs_Repository();
		$this->feedback_repository = new AIPS_Feedback_Repository();
		$this->topics_scheduler = new AIPS_Author_Topics_Scheduler();
		
		// Register AJAX endpoints
		add_action('wp_ajax_aips_save_author', array($this, 'ajax_save_author'));
		add_action('wp_ajax_aips_delete_author', array($this, 'ajax_delete_author'));
		add_action('wp_ajax_aips_get_author', array($this, 'ajax_get_author'));
		add_action('wp_ajax_aips_get_author_topics', array($this, 'ajax_get_author_topics'));
		add_action('wp_ajax_aips_get_author_posts', array($this, 'ajax_get_author_posts'));
		add_action('wp_ajax_aips_get_author_feedback', array($this, 'ajax_get_author_feedback'));
		add_action('wp_ajax_aips_generate_topics_now', array($this, 'ajax_generate_topics_now'));
		add_action('wp_ajax_aips_get_topic_posts', array($this, 'ajax_get_topic_posts'));
	}
	
	/**
	 * AJAX handler for saving an author.
	 */
	public function ajax_save_author() {
		check_ajax_referer('aips_ajax_nonce', 'nonce');
		
		if (!current_user_can('manage_options')) {
			wp_send_json_error(array('message' => __('Permission denied.', 'ai-post-scheduler')));
		}
		
		// Sanitize and validate input
		$author_id = isset($_POST['author_id']) ? absint($_POST['author_id']) : 0;
		$name = isset($_POST['name']) ? sanitize_text_field($_POST['name']) : '';
		$field_niche = isset($_POST['field_niche']) ? sanitize_text_field($_POST['field_niche']) : '';
		
		if (empty($name) || empty($field_niche)) {
			wp_send_json_error(array('message' => __('Name and Field/Niche are required.', 'ai-post-scheduler')));
		}
		
		// Build author data
		$data = array(
			'name' => $name,
			'field_niche' => $field_niche,
			'description' => isset($_POST['description']) ? sanitize_textarea_field($_POST['description']) : '',
			'keywords' => isset($_POST['keywords']) ? sanitize_text_field($_POST['keywords']) : '',
			'details' => isset($_POST['details']) ? sanitize_textarea_field($_POST['details']) : '',
			'article_structure_id' => !empty($_POST['article_structure_id']) ? absint($_POST['article_structure_id']) : null,
			'topic_generation_prompt' => isset($_POST['topic_generation_prompt']) ? sanitize_textarea_field($_POST['topic_generation_prompt']) : '',
			'topic_generation_frequency' => isset($_POST['topic_generation_frequency']) ? sanitize_text_field($_POST['topic_generation_frequency']) : 'weekly',
			'topic_generation_quantity' => isset($_POST['topic_generation_quantity']) ? absint($_POST['topic_generation_quantity']) : 5,
			'post_generation_frequency' => isset($_POST['post_generation_frequency']) ? sanitize_text_field($_POST['post_generation_frequency']) : 'daily',
			'post_status' => isset($_POST['post_status']) ? sanitize_text_field($_POST['post_status']) : 'draft',
			'post_category' => isset($_POST['post_category']) ? absint($_POST['post_category']) : null,
			'post_tags' => isset($_POST['post_tags']) ? sanitize_text_field($_POST['post_tags']) : '',
			'post_author' => isset($_POST['post_author']) ? absint($_POST['post_author']) : get_current_user_id(),
			'generate_featured_image' => isset($_POST['generate_featured_image']) ? 1 : 0,
			'featured_image_source' => isset($_POST['featured_image_source']) ? sanitize_text_field($_POST['featured_image_source']) : 'ai_prompt',
			'voice_tone' => isset($_POST['voice_tone']) ? sanitize_text_field($_POST['voice_tone']) : '',
			'writing_style' => isset($_POST['writing_style']) ? sanitize_text_field($_POST['writing_style']) : '',
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
			wp_send_json_success(array(
				'message' => __('Author saved successfully.', 'ai-post-scheduler'),
				'author_id' => $id
			));
		} else {
			wp_send_json_error(array('message' => __('Failed to save author.', 'ai-post-scheduler')));
		}
	}
	
	/**
	 * AJAX handler for deleting an author.
	 */
	public function ajax_delete_author() {
		check_ajax_referer('aips_ajax_nonce', 'nonce');
		
		if (!current_user_can('manage_options')) {
			wp_send_json_error(array('message' => __('Permission denied.', 'ai-post-scheduler')));
		}
		
		$author_id = isset($_POST['author_id']) ? absint($_POST['author_id']) : 0;
		
		if (!$author_id) {
			wp_send_json_error(array('message' => __('Invalid author ID.', 'ai-post-scheduler')));
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
			wp_send_json_success(array('message' => __('Author deleted successfully.', 'ai-post-scheduler')));
		} else {
			wp_send_json_error(array('message' => __('Failed to delete author.', 'ai-post-scheduler')));
		}
	}
	
	/**
	 * AJAX handler for getting an author.
	 */
	public function ajax_get_author() {
		check_ajax_referer('aips_ajax_nonce', 'nonce');
		
		if (!current_user_can('manage_options')) {
			wp_send_json_error(array('message' => __('Permission denied.', 'ai-post-scheduler')));
		}
		
		$author_id = isset($_POST['author_id']) ? absint($_POST['author_id']) : 0;
		
		if (!$author_id) {
			wp_send_json_error(array('message' => __('Invalid author ID.', 'ai-post-scheduler')));
		}
		
		$author = $this->repository->get_by_id($author_id);
		
		if ($author) {
			wp_send_json_success(array('author' => $author));
		} else {
			wp_send_json_error(array('message' => __('Author not found.', 'ai-post-scheduler')));
		}
	}
	
	/**
	 * AJAX handler for getting author topics.
	 */
	public function ajax_get_author_topics() {
		check_ajax_referer('aips_ajax_nonce', 'nonce');
		
		if (!current_user_can('manage_options')) {
			wp_send_json_error(array('message' => __('Permission denied.', 'ai-post-scheduler')));
		}
		
		$author_id = isset($_POST['author_id']) ? absint($_POST['author_id']) : 0;
		$status = isset($_POST['status']) ? sanitize_text_field($_POST['status']) : null;
		
		if (!$author_id) {
			wp_send_json_error(array('message' => __('Invalid author ID.', 'ai-post-scheduler')));
		}
		
		$topics = $this->topics_repository->get_by_author($author_id, $status);
		$status_counts = $this->topics_repository->get_status_counts($author_id);
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
		
		wp_send_json_success(array(
			'topics' => $topics,
			'status_counts' => $status_counts
		));
	}

	/**
	 * AJAX handler for getting author generated posts.
	 */
	public function ajax_get_author_posts() {
		check_ajax_referer('aips_ajax_nonce', 'nonce');
		
		if (!current_user_can('manage_options')) {
			wp_send_json_error(array('message' => __('Permission denied.', 'ai-post-scheduler')));
		}
		
		$author_id = isset($_POST['author_id']) ? absint($_POST['author_id']) : 0;
		
		if (!$author_id) {
			wp_send_json_error(array('message' => __('Invalid author ID.', 'ai-post-scheduler')));
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
		
		wp_send_json_success(array('posts' => $posts));
	}
	
	/**
	 * AJAX handler for manually generating topics now.
	 */
	public function ajax_generate_topics_now() {
		check_ajax_referer('aips_ajax_nonce', 'nonce');
		
		if (!current_user_can('manage_options')) {
			wp_send_json_error(array('message' => __('Permission denied.', 'ai-post-scheduler')));
		}
		
		$author_id = isset($_POST['author_id']) ? absint($_POST['author_id']) : 0;
		
		if (!$author_id) {
			wp_send_json_error(array('message' => __('Invalid author ID.', 'ai-post-scheduler')));
		}
		
		$result = $this->topics_scheduler->generate_now($author_id);

		if (is_wp_error($result)) {
			wp_send_json_error(array('message' => $result->get_error_message()));
		}

		// Create admin bar notification for manual topic generation
		$author = $this->repository->get_by_id($author_id);
		if ($author && is_array($result)) {
			AIPS_Admin_Bar::notify_author_topics_generated($author->name, count($result), $author_id);
		}

		wp_send_json_success(array(
			'message' => __('Topics generated successfully.', 'ai-post-scheduler'),
			'topics' => $result
		));
	}
	
	/**
	 * AJAX handler for getting author feedback.
	 */
	public function ajax_get_author_feedback() {
		check_ajax_referer('aips_ajax_nonce', 'nonce');
		
		if (!current_user_can('manage_options')) {
			wp_send_json_error(array('message' => __('Permission denied.', 'ai-post-scheduler')));
		}
		
		$author_id = isset($_POST['author_id']) ? absint($_POST['author_id']) : 0;
		
		if (!$author_id) {
			wp_send_json_error(array('message' => __('Invalid author ID.', 'ai-post-scheduler')));
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
		
		wp_send_json_success(array('feedback' => $feedback));
	}
	
	/**
	 * AJAX handler for getting posts associated with a specific topic.
	 */
	public function ajax_get_topic_posts() {
		check_ajax_referer('aips_ajax_nonce', 'nonce');
		
		if (!current_user_can('manage_options')) {
			wp_send_json_error(array('message' => __('Permission denied.', 'ai-post-scheduler')));
		}
		
		$topic_id = isset($_POST['topic_id']) ? absint($_POST['topic_id']) : 0;
		
		if (!$topic_id) {
			wp_send_json_error(array('message' => __('Invalid topic ID.', 'ai-post-scheduler')));
		}
		
		// Get the topic details
		$topic = $this->topics_repository->get_by_id($topic_id);
		
		if (!$topic) {
			wp_send_json_error(array('message' => __('Topic not found.', 'ai-post-scheduler')));
		}
		
		// Get all logs for this topic
		$logs = $this->logs_repository->get_by_topic($topic_id);
		
		$posts = array();
		foreach ($logs as $log) {
			// Only include post_generated logs with valid post IDs
			if ($log->action === 'post_generated' && $log->post_id) {
				$wp_post = get_post($log->post_id);
				if ($wp_post) {
					$posts[] = array(
						'post_id' => $log->post_id,
						'post_title' => $wp_post->post_title,
						'post_status' => $wp_post->post_status,
						'date_generated' => $log->created_at,
						'date_published' => $wp_post->post_status === 'publish' ? $wp_post->post_date : null,
						'post_url' => esc_url_raw(get_permalink($wp_post->ID)),
						'edit_url' => esc_url_raw(get_edit_post_link($wp_post->ID, 'raw'))
					);
				}
			}
		}
		
		wp_send_json_success(array(
			'topic' => $topic,
			'posts' => $posts
		));
	}
}

