<?php
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
 * Class AIPS_AI_Edit_Controller
 *
 * Handles AJAX endpoints for fetching post components, regenerating them,
 * and saving changes back to WordPress posts.
 */
class AIPS_AI_Edit_Controller {
	
	/**
	 * @var AIPS_Component_Regeneration_Service Regeneration service
	 */
	private $service;

	/**
	 * @var AIPS_History_Repository_Interface
	 */
	private $history_repository;
	
	/**
	 * Constructor
	 *
	 * @param AIPS_Component_Regeneration_Service|null $service            Regeneration service.
	 * @param AIPS_History_Repository_Interface|null   $history_repository History repository.
	 */
	public function __construct($service = null, ?AIPS_History_Repository_Interface $history_repository = null) {
		$container = AIPS_Container::get_instance();
		$this->service            = $service ?: new AIPS_Component_Regeneration_Service();
		$this->history_repository = $history_repository ?: ($container->has(AIPS_History_Repository_Interface::class) ? $container->make(AIPS_History_Repository_Interface::class) : new AIPS_History_Repository());
		
		// Register AJAX endpoints
		add_action('wp_ajax_aips_get_post_components', array($this, 'ajax_get_post_components'));
		add_action('wp_ajax_aips_regenerate_component', array($this, 'ajax_regenerate_component'));
		add_action('wp_ajax_aips_regenerate_all_components', array($this, 'ajax_regenerate_all_components'));
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
		if ( ! check_ajax_referer('aips_ajax_nonce', 'nonce', false) ) {
			AIPS_Ajax_Response::error(__('Invalid nonce.', 'ai-post-scheduler'));
		}
		
		if (!current_user_can('edit_posts')) {
			AIPS_Ajax_Response::permission_denied();
		}
		
		$post_id = isset($_POST['post_id']) ? absint($_POST['post_id']) : 0;
		$history_id = isset($_POST['history_id']) ? absint($_POST['history_id']) : 0;
		
		if (!$post_id || !$history_id) {
			AIPS_Ajax_Response::error(__('Invalid request.', 'ai-post-scheduler'));
		}
		
		// Check if user can edit this post
		if (!current_user_can('edit_post', $post_id)) {
			AIPS_Ajax_Response::error(__('You do not have permission to edit this post.', 'ai-post-scheduler'));
		}
		
		// Get the post
		$post = get_post($post_id);
		if (!$post) {
			AIPS_Ajax_Response::error(__('Post not found.', 'ai-post-scheduler'));
		}
		
		// Get generation context
		$context = $this->service->get_generation_context($history_id);
		if (is_wp_error($context)) {
			$this->log_wp_error($context, __METHOD__);
			AIPS_Ajax_Response::error(__('Failed to retrieve generation context.', 'ai-post-scheduler'));
		}

		// Ensure the history context belongs to the requested post
		if (isset($context['post_id']) && absint($context['post_id']) !== $post_id) {
			AIPS_Ajax_Response::error(__('Invalid history context for this post.', 'ai-post-scheduler'));
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
		
		AIPS_Ajax_Response::success($response);
	}
	
	/**
	 * AJAX handler: Regenerate component
	 *
	 * Regenerates a single component of a post using AI.
	 */
	public function ajax_regenerate_component() {
		if ( ! check_ajax_referer('aips_ajax_nonce', 'nonce', false) ) {
			AIPS_Ajax_Response::error(__('Invalid nonce.', 'ai-post-scheduler'));
		}
		
		if (!current_user_can('edit_posts')) {
			AIPS_Ajax_Response::permission_denied();
		}
		
		$post_id = isset($_POST['post_id']) ? absint($_POST['post_id']) : 0;
		$history_id = isset($_POST['history_id']) ? absint($_POST['history_id']) : 0;
		$component = isset($_POST['component']) ? sanitize_text_field(wp_unslash($_POST['component'])) : '';
		$current_value = isset($_POST['current_value']) ? wp_unslash($_POST['current_value']) : null;
		$current_source = isset($_POST['current_source']) ? sanitize_key(wp_unslash($_POST['current_source'])) : '';
		$current_reason = isset($_POST['current_reason']) ? sanitize_key(wp_unslash($_POST['current_reason'])) : '';
		
		if (!$post_id || !$history_id || !$component) {
			AIPS_Ajax_Response::error(__('Invalid request.', 'ai-post-scheduler'));
		}
		
		// Validate component type
		$valid_components = array('title', 'excerpt', 'content', 'featured_image');
		if (!in_array($component, $valid_components)) {
			AIPS_Ajax_Response::error(__('Invalid component type.', 'ai-post-scheduler'));
		}
		
		// Check if user can edit this post
		if (!current_user_can('edit_post', $post_id)) {
			AIPS_Ajax_Response::error(__('You do not have permission to edit this post.', 'ai-post-scheduler'));
		}
		
		// Get generation context
		$context = $this->service->get_generation_context($history_id);
		if (is_wp_error($context)) {
			$this->log_wp_error($context, __METHOD__);
			AIPS_Ajax_Response::error(__('Failed to retrieve generation context.', 'ai-post-scheduler'));
		}
		
		// Ensure the history context belongs to the requested post
		if (isset($context['post_id']) && absint($context['post_id']) !== $post_id) {
			AIPS_Ajax_Response::error(__('Invalid history context for this post.', 'ai-post-scheduler'));
		}
		
		// Get current post data for context
		$post = get_post($post_id);
		if (!$post) {
			AIPS_Ajax_Response::error(__('Post not found.', 'ai-post-scheduler'));
		}
		
		// Add current post data to context
		$context['post_id'] = $post_id;
		$context['history_id'] = $history_id;
		$context['current_title'] = $post->post_title;
		$context['current_excerpt'] = $post->post_excerpt;
		$context['current_content'] = $post->post_content;

		if ('manual_edit' === $current_source && null !== $current_value && '' !== $current_value) {
			$snapshot_result = $this->service->capture_component_revision(
				$post_id,
				$history_id,
				$component,
				$this->sanitize_component_revision_value($component, $current_value),
				'manual_edit',
				$current_reason ? $current_reason : 'pre_regenerate_manual'
			);

			if (is_wp_error($snapshot_result)) {
				$this->log_wp_error($snapshot_result, __METHOD__);
				AIPS_Ajax_Response::error(__('Failed to capture component revision.', 'ai-post-scheduler'));
			}
		}
		
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
			$this->log_wp_error($result, __METHOD__);
			AIPS_Ajax_Response::error(__('An error occurred during component regeneration.', 'ai-post-scheduler'));
		}
		
		AIPS_Ajax_Response::success(array('new_value' => $result));
	}

	/**
	 * AJAX handler: Regenerate all components.
	 *
	 * Regenerates title, excerpt, and content. Featured image regeneration is
	 * conditional and only runs when a prior image exists or original generation
	 * logged a featured-image failure.
	 */
	public function ajax_regenerate_all_components() {
		if ( ! check_ajax_referer('aips_ajax_nonce', 'nonce', false) ) {
			AIPS_Ajax_Response::error(__('Invalid nonce.', 'ai-post-scheduler'));
		}

		if (!current_user_can('edit_posts')) {
			AIPS_Ajax_Response::permission_denied();
		}

		$post_id = isset($_POST['post_id']) ? absint($_POST['post_id']) : 0;
		$history_id = isset($_POST['history_id']) ? absint($_POST['history_id']) : 0;
		$manual_snapshots = isset($_POST['manual_snapshots']) && is_array($_POST['manual_snapshots'])
			? $_POST['manual_snapshots']
			: array();

		if (!$post_id || !$history_id) {
			AIPS_Ajax_Response::error(__('Invalid request.', 'ai-post-scheduler'));
		}

		if (!current_user_can('edit_post', $post_id)) {
			AIPS_Ajax_Response::error(__('You do not have permission to edit this post.', 'ai-post-scheduler'));
		}

		$context = $this->service->get_generation_context($history_id);
		if (is_wp_error($context)) {
			$this->log_wp_error($context, __METHOD__);
			AIPS_Ajax_Response::error(__('Failed to retrieve generation context.', 'ai-post-scheduler'));
		}

		if (isset($context['post_id']) && absint($context['post_id']) !== $post_id) {
			AIPS_Ajax_Response::error(__('Invalid history context for this post.', 'ai-post-scheduler'));
		}

		$post = get_post($post_id);
		if (!$post) {
			AIPS_Ajax_Response::error(__('Post not found.', 'ai-post-scheduler'));
		}

		$context['post_id'] = $post_id;
		$context['history_id'] = $history_id;
		$context['current_title'] = $post->post_title;
		$context['current_excerpt'] = $post->post_excerpt;
		$context['current_content'] = $post->post_content;

		$valid_components = array('title', 'excerpt', 'content', 'featured_image');
		foreach ($manual_snapshots as $component => $value) {
			if (!in_array($component, $valid_components, true)) {
				continue;
			}

			$sanitized_snapshot_value = $this->sanitize_component_revision_value($component, wp_unslash($value));
			$snapshot_result = $this->service->capture_component_revision(
				$post_id,
				$history_id,
				$component,
				$sanitized_snapshot_value,
				'manual_edit',
				'pre_regenerate_all_manual'
			);

			if (is_wp_error($snapshot_result)) {
				$this->log_wp_error($snapshot_result, __METHOD__);
				AIPS_Ajax_Response::error(__('Failed to capture component revision.', 'ai-post-scheduler'));
			}
		}

		$result = $this->service->regenerate_all_components($context);
		if (is_wp_error($result)) {
			$this->log_wp_error($result, __METHOD__);
			AIPS_Ajax_Response::error(__('An error occurred while regenerating all components.', 'ai-post-scheduler'));
		}

		$regenerated_count = count($result['regenerated']);
		$error_count = count($result['errors']);

		if ($regenerated_count === 0) {
			AIPS_Ajax_Response::error(array(
				'message' => __('No components were regenerated.', 'ai-post-scheduler'),
				'regenerated' => $result['regenerated'],
				'skipped' => $result['skipped'],
				'errors' => $result['errors'],
			));
		}

		$message = __('Components regenerated successfully.', 'ai-post-scheduler');
		if ($error_count > 0) {
			$message = __('Some components were regenerated, but others failed.', 'ai-post-scheduler');
		}

		AIPS_Ajax_Response::success(array(
			'message' => $message,
			'regenerated' => $result['regenerated'],
			'skipped' => $result['skipped'],
			'errors' => $result['errors'],
		));
	}
	
	/**
	 * AJAX handler: Save post components
	 *
	 * Persists the changed components to the WordPress post.
	 */
	public function ajax_save_post_components() {
		if ( ! check_ajax_referer('aips_ajax_nonce', 'nonce', false) ) {
			AIPS_Ajax_Response::error(__('Invalid nonce.', 'ai-post-scheduler'));
		}
		
		if (!current_user_can('edit_posts')) {
			AIPS_Ajax_Response::permission_denied();
		}
		
		$post_id = isset($_POST['post_id']) ? absint($_POST['post_id']) : 0;
		$components = isset($_POST['components']) ? $_POST['components'] : array();
		
		if (!$post_id || empty($components)) {
			AIPS_Ajax_Response::error(__('Invalid request.', 'ai-post-scheduler'));
		}
		
		// Check if user can edit this post
		if (!current_user_can('edit_post', $post_id)) {
			AIPS_Ajax_Response::error(__('You do not have permission to edit this post.', 'ai-post-scheduler'));
		}
		
		// Build update array
		$post_data = array('ID' => $post_id);
		$updated_components = array();
		
		if (isset($components['title'])) {
			$post_data['post_title'] = sanitize_text_field(wp_unslash($components['title']));
			$updated_components[] = 'title';
		}
		
		if (isset($components['excerpt'])) {
			$post_data['post_excerpt'] = sanitize_textarea_field(wp_unslash($components['excerpt']));
			$updated_components[] = 'excerpt';
		}
		
		if (isset($components['content'])) {
			$post_data['post_content'] = wp_kses_post(wp_unslash($components['content']));
			$updated_components[] = 'content';
		}
		
		// Update post
		$result = wp_update_post($post_data, true);
		
		if (is_wp_error($result)) {
			$this->log_wp_error($result, __METHOD__);
			AIPS_Ajax_Response::error(__('An error occurred while saving post components.', 'ai-post-scheduler'));
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

		// Security: Create a sanitized array of components for the action hook to prevent passing raw POST data
		$sanitized_components = array();
		if (isset($components['title'])) {
			$sanitized_components['title'] = sanitize_text_field(wp_unslash($components['title']));
		}
		if (isset($components['excerpt'])) {
			$sanitized_components['excerpt'] = sanitize_textarea_field(wp_unslash($components['excerpt']));
		}
		if (isset($components['content'])) {
			$sanitized_components['content'] = wp_kses_post(wp_unslash($components['content']));
		}
		if (isset($components['featured_image_id'])) {
			$sanitized_components['featured_image_id'] = absint($components['featured_image_id']);
		}

		do_action('aips_post_components_updated', $post_id, $updated_components, $sanitized_components);
		
		AIPS_Ajax_Response::success(array(
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
		if ( ! check_ajax_referer('aips_ajax_nonce', 'nonce', false) ) {
			AIPS_Ajax_Response::error(__('Invalid nonce.', 'ai-post-scheduler'));
		}
		
		if (!current_user_can('edit_posts')) {
			AIPS_Ajax_Response::permission_denied();
		}
		
		$post_id = isset($_POST['post_id']) ? absint($_POST['post_id']) : 0;
		$component = isset($_POST['component']) ? sanitize_text_field(wp_unslash($_POST['component'])) : '';
		if (empty($component) && isset($_POST['component_type'])) {
			$component = sanitize_text_field(wp_unslash($_POST['component_type']));
		}
		
		if (!$post_id || !$component) {
			AIPS_Ajax_Response::error(__('Invalid request.', 'ai-post-scheduler'));
		}
		
		// Validate component type
		$valid_components = array('title', 'excerpt', 'content', 'featured_image');
		if (!in_array($component, $valid_components)) {
			AIPS_Ajax_Response::error(__('Invalid component type.', 'ai-post-scheduler'));
		}
		
		// Check if user can edit this post
		if (!current_user_can('edit_post', $post_id)) {
			AIPS_Ajax_Response::error(__('You do not have permission to access this post.', 'ai-post-scheduler'));
		}
		
		// Get revisions
		$revisions = $this->history_repository->get_component_revisions($post_id, $component, 20);
		
		AIPS_Ajax_Response::success(array(
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
		if ( ! check_ajax_referer('aips_ajax_nonce', 'nonce', false) ) {
			AIPS_Ajax_Response::error(__('Invalid nonce.', 'ai-post-scheduler'));
		}
		
		if (!current_user_can('edit_posts')) {
			AIPS_Ajax_Response::permission_denied();
		}
		
		$post_id = isset($_POST['post_id']) ? absint($_POST['post_id']) : 0;
		$component = isset($_POST['component']) ? sanitize_text_field(wp_unslash($_POST['component'])) : '';
		if (empty($component) && isset($_POST['component_type'])) {
			$component = sanitize_text_field(wp_unslash($_POST['component_type']));
		}
		$revision_id = isset($_POST['revision_id']) ? absint($_POST['revision_id']) : 0;
		$current_value = isset($_POST['current_value']) ? wp_unslash($_POST['current_value']) : null;
		$current_source = isset($_POST['current_source']) ? sanitize_key(wp_unslash($_POST['current_source'])) : '';
		$current_reason = isset($_POST['current_reason']) ? sanitize_key(wp_unslash($_POST['current_reason'])) : '';
		
		if (!$post_id || !$component || !$revision_id) {
			AIPS_Ajax_Response::error(__('Invalid request.', 'ai-post-scheduler'));
		}
		
		// Validate component type
		$valid_components = array('title', 'excerpt', 'content', 'featured_image');
		if (!in_array($component, $valid_components)) {
			AIPS_Ajax_Response::error(__('Invalid component type.', 'ai-post-scheduler'));
		}
		
		// Check if user can edit this post
		if (!current_user_can('edit_post', $post_id)) {
			AIPS_Ajax_Response::error(__('You do not have permission to edit this post.', 'ai-post-scheduler'));
		}

		$history_record = $this->history_repository->get_by_post_id($post_id);
		if ('manual_edit' === $current_source && null !== $current_value && '' !== $current_value && $history_record) {
			$snapshot_result = $this->service->capture_component_revision(
				$post_id,
				absint($history_record->id),
				$component,
				$this->sanitize_component_revision_value($component, $current_value),
				'manual_edit',
				$current_reason ? $current_reason : 'pre_restore_manual'
			);

			if (is_wp_error($snapshot_result)) {
				$this->log_wp_error($snapshot_result, __METHOD__);
				AIPS_Ajax_Response::error(__('Failed to capture component revision.', 'ai-post-scheduler'));
			}
		}
		
		// Get the revision to restore
		$revisions = $this->history_repository->get_component_revisions($post_id, $component, 100);
		$revision_to_restore = null;
		foreach ($revisions as $rev) {
			if ($rev['id'] == $revision_id) {
				$revision_to_restore = $rev;
				break;
			}
		}
		
		if (!$revision_to_restore) {
			AIPS_Ajax_Response::error(__('Revision not found.', 'ai-post-scheduler'));
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
					$attachment_id = absint($restored_value['attachment_id']);
					if ($attachment_id > 0) {
						set_post_thumbnail($post_id, $attachment_id);
					} else {
						delete_post_thumbnail($post_id);
					}
					$restored_value = array(
						'attachment_id' => $attachment_id,
						'url' => $attachment_id ? wp_get_attachment_url($attachment_id) : '',
					);
				}
				break;
		}
		
		// Update post (unless it's featured image which was already set)
		if ($component !== 'featured_image') {
			$result = wp_update_post($post_data, true);
			
			if (is_wp_error($result)) {
				$this->log_wp_error($result, __METHOD__);
				AIPS_Ajax_Response::error(__('An error occurred while restoring component revision.', 'ai-post-scheduler'));
			}
		}
		
		AIPS_Ajax_Response::success(array(
			'message' => __('Revision restored successfully!', 'ai-post-scheduler'),
			'component' => $component,
			'value' => $restored_value,
		));
	}

	/**
	 * Logs a WP_Error server-side without exposing internal details to the client.
	 *
	 * @param WP_Error $error  The error to log.
	 * @param string   $method The calling method name; pass __METHOD__ from the caller.
	 */
	private function log_wp_error( WP_Error $error, $method = '' ) {
		$context = $method ? '[' . $method . '] ' : '';
		error_log( 'AIPS AI Edit Controller Error ' . $context . '(' . $error->get_error_code() . '): ' . $error->get_error_message() );
	}

	/**
	 * Sanitize a component value before storing it as a revision snapshot.
	 *
	 * @param string $component Component type.
	 * @param mixed  $value Raw revision value.
	 * @return mixed
	 */
	private function sanitize_component_revision_value($component, $value) {
		switch ($component) {
			case 'title':
				return sanitize_text_field((string) $value);

			case 'excerpt':
				return sanitize_textarea_field((string) $value);

			case 'content':
				return wp_kses_post((string) $value);

			case 'featured_image':
				if (!is_array($value)) {
					return array(
						'attachment_id' => 0,
						'url' => '',
					);
				}

				return array(
					'attachment_id' => isset($value['attachment_id']) ? absint($value['attachment_id']) : 0,
					'url' => isset($value['url']) ? esc_url_raw($value['url']) : '',
				);

			default:
				return '';
		}
	}
}
