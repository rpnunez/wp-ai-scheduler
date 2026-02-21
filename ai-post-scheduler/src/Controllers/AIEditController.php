<?php
namespace AIPS\Controllers;

/**
 * AI Edit Controller
 *
 * Controller for the AI Edit feature that allows regeneration of individual
 * post components via modal interface.
 *
 * @package AI_Post_Scheduler
 * @since 2.0.0
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIEditController
 *
 * Handles AJAX endpoints for fetching post components, regenerating them,
 * and saving changes back to WordPress posts.
 */
class AIEditController {
	
	/**
	 * @var AIPS_Component_Regeneration_Service Regeneration service
	 */
	private $service;
	
	/**
	 * Constructor
	 */
	public function __construct() {
		$this->service = new \AIPS_Component_Regeneration_Service();
		
		// Register AJAX endpoints
		add_action('wp_ajax_aips_get_post_components', array($this, 'ajax_get_post_components'));
		add_action('wp_ajax_aips_regenerate_component', array($this, 'ajax_regenerate_component'));
		add_action('wp_ajax_aips_save_post_components', array($this, 'ajax_save_post_components'));
		add_action('wp_ajax_aips_get_component_revisions', array($this, 'ajax_get_component_revisions'));
		add_action('wp_ajax_aips_restore_component_revision', array($this, 'ajax_restore_component_revision'));
	}
	
	/**
	 * AJAX handler: Get post components
	 *
	 * Fetches all components of a post along with its generation context.
	 */
	public function ajax_get_post_components() {
		check_ajax_referer('aips_ajax_nonce', 'nonce');
		
		if (!current_user_can('edit_posts')) {
			wp_send_json_error(array('message' => __('Permission denied.', 'ai-post-scheduler')));
		}
		
		$post_id = isset($_POST['post_id']) ? absint($_POST['post_id']) : 0;
		$history_id = isset($_POST['history_id']) ? absint($_POST['history_id']) : 0;
		
		if (!$post_id || !$history_id) {
			wp_send_json_error(array('message' => __('Invalid request.', 'ai-post-scheduler')));
		}
		
		// Check if user can edit this post
		if (!current_user_can('edit_post', $post_id)) {
			wp_send_json_error(array('message' => __('You do not have permission to edit this post.', 'ai-post-scheduler')));
		}
		
		// Get the post
		$post = get_post($post_id);
		if (!$post) {
			wp_send_json_error(array('message' => __('Post not found.', 'ai-post-scheduler')));
		}
		
		// Get generation context
		$context = $this->service->get_generation_context($history_id);
		if (is_wp_error($context)) {
			wp_send_json_error(array('message' => $context->get_error_message()));
		}

		// Ensure the history context belongs to the requested post
		if (isset($context['post_id']) && absint($context['post_id']) !== $post_id) {
			wp_send_json_error(array('message' => __('Invalid history context for this post.', 'ai-post-scheduler')));
		}
		
		// Get featured image
		$featured_image_id = get_post_thumbnail_id($post_id);
		$featured_image_url = $featured_image_id ? wp_get_attachment_url($featured_image_id) : '';
		
		// Build context display based on context type
		$context_display = array(
			'type' => $context['context_type'],
			'name' => $context['context_name'],
		);
		
		// Add specific details based on context type
		if ($context['context_type'] === 'template') {
			$generation_context = $context['generation_context'];
			$context_display['template_name'] = $generation_context->get_name();
			$context_display['author_name'] = __('N/A', 'ai-post-scheduler'); // Templates don't have authors
			$context_display['topic_title'] = $generation_context->get_topic() ? $generation_context->get_topic() : __('N/A', 'ai-post-scheduler');
		} elseif ($context['context_type'] === 'topic') {
			$generation_context = $context['generation_context'];
			$author = $generation_context->get_author();
			$topic = $generation_context->get_topic_object();
			$context_display['template_name'] = __('N/A', 'ai-post-scheduler'); // Topic contexts don't use templates
			$context_display['author_name'] = $author->name;
			$context_display['topic_title'] = $topic->topic_title;
		}
		
		// Build response
		$response = array(
			'context' => $context_display,
			'components' => array(
				'title' => array(
					'value' => $post->post_title,
				),
				'excerpt' => array(
					'value' => $post->post_excerpt,
				),
				'content' => array(
					'value' => $post->post_content,
				),
				'featured_image' => array(
					'attachment_id' => $featured_image_id,
					'url' => $featured_image_url,
				),
			),
		);
		
		wp_send_json_success($response);
	}
	
	/**
	 * AJAX handler: Regenerate component
	 *
	 * Regenerates a single component of a post using AI.
	 */
	public function ajax_regenerate_component() {
		check_ajax_referer('aips_ajax_nonce', 'nonce');
		
		if (!current_user_can('edit_posts')) {
			wp_send_json_error(array('message' => __('Permission denied.', 'ai-post-scheduler')));
		}
		
		$post_id = isset($_POST['post_id']) ? absint($_POST['post_id']) : 0;
		$history_id = isset($_POST['history_id']) ? absint($_POST['history_id']) : 0;
		$component = isset($_POST['component']) ? sanitize_text_field($_POST['component']) : '';
		
		if (!$post_id || !$history_id || !$component) {
			wp_send_json_error(array('message' => __('Invalid request.', 'ai-post-scheduler')));
		}
		
		// Validate component type
		$valid_components = array('title', 'excerpt', 'content', 'featured_image');
		if (!in_array($component, $valid_components)) {
			wp_send_json_error(array('message' => __('Invalid component type.', 'ai-post-scheduler')));
		}
		
		// Check if user can edit this post
		if (!current_user_can('edit_post', $post_id)) {
			wp_send_json_error(array('message' => __('You do not have permission to edit this post.', 'ai-post-scheduler')));
		}
		
		// Get generation context
		$context = $this->service->get_generation_context($history_id);
		if (is_wp_error($context)) {
			wp_send_json_error(array('message' => $context->get_error_message()));
		}
		
		// Ensure the history context belongs to the requested post
		if (isset($context['post_id']) && absint($context['post_id']) !== $post_id) {
			wp_send_json_error(array('message' => __('Invalid history context for this post.', 'ai-post-scheduler')));
		}
		
		// Get current post data for context
		$post = get_post($post_id);
		if (!$post) {
			wp_send_json_error(array('message' => __('Post not found.', 'ai-post-scheduler')));
		}
		
		// Add current post data to context
		$context['post_id'] = $post_id;
		$context['history_id'] = $history_id;
		$context['current_title'] = $post->post_title;
		$context['current_excerpt'] = $post->post_excerpt;
		$context['current_content'] = $post->post_content;
		
		// Regenerate component
		$result = null;
		switch ($component) {
			case 'title':
				$result = $this->service->regenerate_title($context);
				break;
			case 'excerpt':
				$result = $this->service->regenerate_excerpt($context);
				break;
			case 'content':
				$result = $this->service->regenerate_content($context);
				break;
			case 'featured_image':
				$result = $this->service->regenerate_featured_image($context);
				break;
		}
		
		if (is_wp_error($result)) {
			wp_send_json_error(array('message' => $result->get_error_message()));
		}
		
		wp_send_json_success(array('new_value' => $result));
	}
	
	/**
	 * AJAX handler: Save post components
	 *
	 * Persists the changed components to the WordPress post.
	 */
	public function ajax_save_post_components() {
		check_ajax_referer('aips_ajax_nonce', 'nonce');
		
		if (!current_user_can('edit_posts')) {
			wp_send_json_error(array('message' => __('Permission denied.', 'ai-post-scheduler')));
		}
		
		$post_id = isset($_POST['post_id']) ? absint($_POST['post_id']) : 0;
		$components = isset($_POST['components']) ? $_POST['components'] : array();
		
		if (!$post_id || empty($components)) {
			wp_send_json_error(array('message' => __('Invalid request.', 'ai-post-scheduler')));
		}
		
		// Check if user can edit this post
		if (!current_user_can('edit_post', $post_id)) {
			wp_send_json_error(array('message' => __('You do not have permission to edit this post.', 'ai-post-scheduler')));
		}
		
		// Build update array
		$post_data = array('ID' => $post_id);
		$updated_components = array();
		
		if (isset($components['title'])) {
			$post_data['post_title'] = sanitize_text_field($components['title']);
			$updated_components[] = 'title';
		}
		
		if (isset($components['excerpt'])) {
			$post_data['post_excerpt'] = sanitize_textarea_field($components['excerpt']);
			$updated_components[] = 'excerpt';
		}
		
		if (isset($components['content'])) {
			$post_data['post_content'] = wp_kses_post($components['content']);
			$updated_components[] = 'content';
		}
		
		// Update post
		$result = wp_update_post($post_data, true);
		
		if (is_wp_error($result)) {
			wp_send_json_error(array('message' => $result->get_error_message()));
		}
		
		// Update featured image
		if (isset($components['featured_image_id'])) {
			$attachment_id = absint($components['featured_image_id']);
			if ($attachment_id > 0) {
				set_post_thumbnail($post_id, $attachment_id);
				$updated_components[] = 'featured_image';
			} else {
				delete_post_thumbnail($post_id);
			}
		}
		
		wp_send_json_success(array(
			'message' => __('Post updated successfully!', 'ai-post-scheduler'),
			'updated_components' => $updated_components,
		));
	}
	
	/**
	 * AJAX handler: Get component revisions
	 *
	 * Fetches revision history for a specific post component.
	 */
	public function ajax_get_component_revisions() {
		check_ajax_referer('aips_ajax_nonce', 'nonce');
		
		if (!current_user_can('edit_posts')) {
			wp_send_json_error(array('message' => __('Permission denied.', 'ai-post-scheduler')));
		}
		
		$post_id = isset($_POST['post_id']) ? absint($_POST['post_id']) : 0;
		$component = isset($_POST['component']) ? sanitize_text_field($_POST['component']) : '';
		
		if (!$post_id || !$component) {
			wp_send_json_error(array('message' => __('Invalid request.', 'ai-post-scheduler')));
		}
		
		// Validate component type
		$valid_components = array('title', 'excerpt', 'content', 'featured_image');
		if (!in_array($component, $valid_components)) {
			wp_send_json_error(array('message' => __('Invalid component type.', 'ai-post-scheduler')));
		}
		
		// Check if user can edit this post
		if (!current_user_can('edit_post', $post_id)) {
			wp_send_json_error(array('message' => __('You do not have permission to access this post.', 'ai-post-scheduler')));
		}
		
		// Get revisions
		$revisions = $this->service->get_component_revisions($post_id, $component, 20);
		
		wp_send_json_success(array(
			'revisions' => $revisions,
			'total' => count($revisions),
		));
	}
	
	/**
	 * AJAX handler: Restore component revision
	 *
	 * Restores a specific revision value for a post component.
	 */
	public function ajax_restore_component_revision() {
		check_ajax_referer('aips_ajax_nonce', 'nonce');
		
		if (!current_user_can('edit_posts')) {
			wp_send_json_error(array('message' => __('Permission denied.', 'ai-post-scheduler')));
		}
		
		$post_id = isset($_POST['post_id']) ? absint($_POST['post_id']) : 0;
		$component = isset($_POST['component']) ? sanitize_text_field($_POST['component']) : '';
		$revision_id = isset($_POST['revision_id']) ? absint($_POST['revision_id']) : 0;
		
		if (!$post_id || !$component || !$revision_id) {
			wp_send_json_error(array('message' => __('Invalid request.', 'ai-post-scheduler')));
		}
		
		// Validate component type
		$valid_components = array('title', 'excerpt', 'content', 'featured_image');
		if (!in_array($component, $valid_components)) {
			wp_send_json_error(array('message' => __('Invalid component type.', 'ai-post-scheduler')));
		}
		
		// Check if user can edit this post
		if (!current_user_can('edit_post', $post_id)) {
			wp_send_json_error(array('message' => __('You do not have permission to edit this post.', 'ai-post-scheduler')));
		}
		
		// Get the revision to restore
		$revisions = $this->service->get_component_revisions($post_id, $component, 100);
		$revision_to_restore = null;
		foreach ($revisions as $rev) {
			if ($rev['id'] == $revision_id) {
				$revision_to_restore = $rev;
				break;
			}
		}
		
		if (!$revision_to_restore) {
			wp_send_json_error(array('message' => __('Revision not found.', 'ai-post-scheduler')));
		}
		
		// Restore the value to the post
		$post_data = array('ID' => $post_id);
		$restored_value = $revision_to_restore['value'];
		
		switch ($component) {
			case 'title':
				$post_data['post_title'] = sanitize_text_field($restored_value);
				break;
			case 'excerpt':
				$post_data['post_excerpt'] = sanitize_textarea_field($restored_value);
				break;
			case 'content':
				$post_data['post_content'] = wp_kses_post($restored_value);
				break;
			case 'featured_image':
				// For featured image, the value is an array with attachment_id
				if (is_array($restored_value) && isset($restored_value['attachment_id'])) {
					set_post_thumbnail($post_id, absint($restored_value['attachment_id']));
					$restored_value = $restored_value['attachment_id'];
				}
				break;
		}
		
		// Update post (unless it's featured image which was already set)
		if ($component !== 'featured_image') {
			$result = wp_update_post($post_data, true);
			
			if (is_wp_error($result)) {
				wp_send_json_error(array('message' => $result->get_error_message()));
			}
		}
		
		wp_send_json_success(array(
			'message' => __('Revision restored successfully!', 'ai-post-scheduler'),
			'component' => $component,
			'value' => $restored_value,
		));
	}
}
