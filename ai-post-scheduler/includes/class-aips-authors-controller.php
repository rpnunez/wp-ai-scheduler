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
	 * @var AIPS_Interval_Calculator Calculator for scheduling intervals
	 */
	private $interval_calculator;
	
	/**
	 * Initialize the controller.
	 */
	public function __construct() {
		$this->repository = new AIPS_Authors_Repository();
		$this->topics_repository = new AIPS_Author_Topics_Repository();
		$this->logs_repository = new AIPS_Author_Topic_Logs_Repository();
		$this->feedback_repository = new AIPS_Feedback_Repository();
		$this->topics_scheduler = new AIPS_Author_Topics_Scheduler();
		$this->interval_calculator = new AIPS_Interval_Calculator();
		
		// Register AJAX endpoints
		add_action('wp_ajax_aips_save_author', array($this, 'ajax_save_author'));
		add_action('wp_ajax_aips_delete_author', array($this, 'ajax_delete_author'));
		add_action('wp_ajax_aips_get_author', array($this, 'ajax_get_author'));
		add_action('wp_ajax_aips_get_author_topics', array($this, 'ajax_get_author_topics'));
		add_action('wp_ajax_aips_get_author_posts', array($this, 'ajax_get_author_posts'));
		add_action('wp_ajax_aips_get_author_feedback', array($this, 'ajax_get_author_feedback'));
		add_action('wp_ajax_aips_generate_topics_now', array($this, 'ajax_generate_topics_now'));
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
			'is_active' => isset($_POST['is_active']) ? 1 : 0
		);
		
		// Calculate initial run times if creating new author
		if (!$author_id) {
			$data['topic_generation_next_run'] = $this->interval_calculator->calculate_next_run($data['topic_generation_frequency']);
			$data['post_generation_next_run'] = $this->interval_calculator->calculate_next_run($data['post_generation_frequency']);
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
		global $wpdb;
		$topics_table = $wpdb->prefix . 'aips_author_topics';
		$logs_table = $wpdb->prefix . 'aips_author_topic_logs';
		
		// Get all topic IDs for this author
		$topic_ids = $wpdb->get_col($wpdb->prepare(
			"SELECT id FROM {$topics_table} WHERE author_id = %d",
			$author_id
		));
		
		// Delete logs for these topics
		if (!empty($topic_ids)) {
			$placeholders = implode(',', array_fill(0, count($topic_ids), '%d'));
			$wpdb->query($wpdb->prepare(
				"DELETE FROM {$logs_table} WHERE author_topic_id IN ({$placeholders})",
				...$topic_ids
			));
		}
		
		// Delete topics
		$wpdb->delete($topics_table, array('author_id' => $author_id), array('%d'));
		
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
					$post->post_url = get_permalink($wp_post->ID);
					$post->edit_url = get_edit_post_link($wp_post->ID, 'raw');
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
}
