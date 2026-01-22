<?php
/**
 * Activity Controller
 *
 * Handles AJAX requests for the Activity page, including retrieving
 * activity feed data and performing actions on posts.
 *
 * @package AI_Post_Scheduler
 * @since 1.7.0
 */

namespace AIPS\Controllers;

use AIPS\Repository\Activity as ActivityRepository;
use AIPS\Repository\Schedule as ScheduleRepository;

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Controller for activity-related AJAX operations.
 */
class Activity {
	/**
	 * @var ActivityRepository Activity repository instance
	 */
	private $activity_repository;

	/**
	 * @var ScheduleRepository Schedule repository instance
	 */
	private $schedule_repository;

	/**
	 * Initialize the controller.
	 */
	public function __construct() {
		$this->activity_repository = new ActivityRepository();
		$this->schedule_repository = new ScheduleRepository();

		// Register AJAX handlers
		add_action('wp_ajax_aips_get_activity', array($this, 'ajax_get_activity'));
		add_action('wp_ajax_aips_get_activity_detail', array($this, 'ajax_get_activity_detail'));
		add_action('wp_ajax_aips_publish_draft', array($this, 'ajax_publish_draft'));
	}

	/**
	 * AJAX handler to get activity feed.
	 */
	public function ajax_get_activity() {
		check_ajax_referer('aips_activity_nonce', 'nonce');

		if (!current_user_can('manage_options')) {
			wp_send_json_error(array('message' => __('Permission denied', 'ai-post-scheduler')));
		}

		$filter = isset($_POST['filter']) ? sanitize_text_field($_POST['filter']) : 'all';
		$limit = isset($_POST['limit']) ? absint($_POST['limit']) : 50;

		$args = array('limit' => $limit);

		switch ($filter) {
			case 'failed':
				$args['event_type'] = 'schedule_failed';
				break;
			case 'drafts':
				$args['event_status'] = 'draft';
				break;
			case 'published':
				$args['event_status'] = 'success';
				$args['event_type'] = 'post_published';
				break;
		}

		$activities = $this->activity_repository->get_recent($args);

		// Format activities for display
		$formatted_activities = array();
		foreach ($activities as $activity) {
			$formatted = $this->format_activity($activity);
			if ($formatted) {
				$formatted_activities[] = $formatted;
			}
		}

		wp_send_json_success(array('activities' => $formatted_activities));
	}

	/**
	 * AJAX handler to get detailed activity information for modal.
	 */
	public function ajax_get_activity_detail() {
		check_ajax_referer('aips_activity_nonce', 'nonce');

		if (!current_user_can('manage_options')) {
			wp_send_json_error(array('message' => __('Permission denied', 'ai-post-scheduler')));
		}

		$post_id = isset($_POST['post_id']) ? absint($_POST['post_id']) : 0;

		if (!$post_id) {
			wp_send_json_error(array('message' => __('Invalid post ID', 'ai-post-scheduler')));
		}

		$post = get_post($post_id);

		if (!$post) {
			wp_send_json_error(array('message' => __('Post not found', 'ai-post-scheduler')));
		}

		$data = array(
			'id' => $post->ID,
			'title' => $post->post_title,
			'content' => $post->post_content,
			'excerpt' => $post->post_excerpt,
			'status' => $post->post_status,
			'author' => get_the_author_meta('display_name', $post->post_author),
			'date' => get_the_date('', $post),
			'edit_url' => get_edit_post_link($post->ID),
			'view_url' => get_permalink($post->ID),
			'categories' => wp_get_post_categories($post->ID, array('fields' => 'names')),
			'tags' => wp_get_post_tags($post->ID, array('fields' => 'names')),
		);

		// Get featured image if exists
		if (has_post_thumbnail($post->ID)) {
			$data['featured_image'] = get_the_post_thumbnail_url($post->ID, 'medium');
		}

		wp_send_json_success($data);
	}

	/**
	 * AJAX handler to publish a draft post.
	 */
	public function ajax_publish_draft() {
		check_ajax_referer('aips_activity_nonce', 'nonce');

		if (!current_user_can('publish_posts')) {
			wp_send_json_error(array('message' => __('Permission denied', 'ai-post-scheduler')));
		}

		$post_id = isset($_POST['post_id']) ? absint($_POST['post_id']) : 0;

		if (!$post_id) {
			wp_send_json_error(array('message' => __('Invalid post ID', 'ai-post-scheduler')));
		}

		$post = get_post($post_id);

		if (!$post) {
			wp_send_json_error(array('message' => __('Post not found', 'ai-post-scheduler')));
		}

		if ($post->post_status === 'publish') {
			wp_send_json_error(array('message' => __('Post is already published', 'ai-post-scheduler')));
		}

		$result = wp_update_post(array(
			'ID' => $post_id,
			'post_status' => 'publish',
		));

		if (is_wp_error($result)) {
			wp_send_json_error(array('message' => $result->get_error_message()));
		}

		// Log the publish activity
		$this->activity_repository->create(array(
			'event_type' => 'post_published',
			'event_status' => 'success',
			'post_id' => $post_id,
			'message' => sprintf(__('Draft post "%s" published manually from Activity page', 'ai-post-scheduler'), $post->post_title),
		));

		wp_send_json_success(array(
			'message' => __('Post published successfully', 'ai-post-scheduler'),
			'view_url' => get_permalink($post_id),
		));
	}

	/**
	 * Format activity for display.
	 *
	 * @param object $activity Activity record from database.
	 * @return array|null Formatted activity data or null if post/schedule not found.
	 */
	private function format_activity($activity) {
		$formatted = array(
			'id' => $activity->id,
			'type' => $activity->event_type,
			'status' => $activity->event_status,
			'message' => $activity->message,
			'date' => $activity->created_at,
			'date_formatted' => human_time_diff(strtotime($activity->created_at), current_time('timestamp')) . ' ' . __('ago', 'ai-post-scheduler'),
		);

		// Add post information if available
		if ($activity->post_id) {
			$post = get_post($activity->post_id);
			if ($post) {
				$formatted['post'] = array(
					'id' => $post->ID,
					'title' => $post->post_title,
					'status' => $post->post_status,
					'edit_url' => get_edit_post_link($post->ID),
					'view_url' => get_permalink($post->ID),
				);
			}
		}

		// Add schedule information if available
		if ($activity->schedule_id) {
			$schedule = $this->schedule_repository->get_by_id($activity->schedule_id);
			if ($schedule) {
				$formatted['schedule'] = array(
					'id' => $schedule->id,
					'name' => $schedule->template_name,
					'frequency' => $schedule->frequency,
					'status' => isset($schedule->status) ? $schedule->status : 'active',
				);
			}
		}

		// Parse metadata if exists
		if (!empty($activity->metadata)) {
			$metadata = json_decode($activity->metadata, true);
			if ($metadata) {
				$formatted['metadata'] = $metadata;
			}
		}

		return $formatted;
	}
}
