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
	 * @var AIPS_History_Repository
	 */
	private $history_repository;
	
	/**
	 * Constructor
	 */
	public function __construct() {
		$this->service = new AIPS_Component_Regeneration_Service();
		$this->history_repository = new AIPS_History_Repository();
		
		// Register AJAX endpoints
		add_action('wp_ajax_aips_get_post_components', array($this, 'ajax_get_post_components'));
		add_action('wp_ajax_aips_regenerate_component', array($this, 'ajax_regenerate_component'));
		add_action('wp_ajax_aips_save_post_components', array($this, 'ajax_save_post_components'));
		add_action('wp_ajax_aips_get_component_revisions', array($this, 'ajax_get_component_revisions'));
		add_action('wp_ajax_aips_restore_component_revision', array($this, 'ajax_restore_component_revision'));
		add_action('wp_ajax_aips_generate_multi_draft', array($this, 'ajax_generate_multi_draft'));
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
		$component = isset($_POST['component']) ? sanitize_text_field(wp_unslash($_POST['component'])) : '';
		$current_value = isset($_POST['current_value']) ? wp_unslash($_POST['current_value']) : null;
		$current_source = isset($_POST['current_source']) ? sanitize_key(wp_unslash($_POST['current_source'])) : '';
		$current_reason = isset($_POST['current_reason']) ? sanitize_key(wp_unslash($_POST['current_reason'])) : '';
		
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
				wp_send_json_error(array('message' => $snapshot_result->get_error_message()));
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
		$component = isset($_POST['component']) ? sanitize_text_field(wp_unslash($_POST['component'])) : '';
		if (empty($component) && isset($_POST['component_type'])) {
			$component = sanitize_text_field(wp_unslash($_POST['component_type']));
		}
		
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
		$revisions = $this->history_repository->get_component_revisions($post_id, $component, 20);
		
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
		$component = isset($_POST['component']) ? sanitize_text_field(wp_unslash($_POST['component'])) : '';
		if (empty($component) && isset($_POST['component_type'])) {
			$component = sanitize_text_field(wp_unslash($_POST['component_type']));
		}
		$revision_id = isset($_POST['revision_id']) ? absint($_POST['revision_id']) : 0;
		$current_value = isset($_POST['current_value']) ? wp_unslash($_POST['current_value']) : null;
		$current_source = isset($_POST['current_source']) ? sanitize_key(wp_unslash($_POST['current_source'])) : '';
		$current_reason = isset($_POST['current_reason']) ? sanitize_key(wp_unslash($_POST['current_reason'])) : '';
		
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
				wp_send_json_error(array('message' => $snapshot_result->get_error_message()));
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
				wp_send_json_error(array('message' => $result->get_error_message()));
			}
		}
		
		wp_send_json_success(array(
			'message' => __('Revision restored successfully!', 'ai-post-scheduler'),
			'component' => $component,
			'value' => $restored_value,
		));
	}

	/**
	 * AJAX handler: Generate multiple draft variants for comparison.
	 *
	 * Generates N independent variants of selected post components using the
	 * original generation context so editors can pick the best version or merge
	 * sections across variants.
	 *
	 * Expected POST params:
	 *   post_id      (int)   – WordPress post ID
	 *   history_id   (int)   – History record ID used to reconstruct context
	 *   variant_count (int)  – Number of variants to generate (2–3)
	 *   components   (array) – Subset of ['title','excerpt','content'] to generate
	 */
	public function ajax_generate_multi_draft() {
		check_ajax_referer('aips_ajax_nonce', 'nonce');

		if (!current_user_can('edit_posts')) {
			wp_send_json_error(array('message' => __('Permission denied.', 'ai-post-scheduler')));
		}

		$post_id       = isset($_POST['post_id'])      ? absint($_POST['post_id'])      : 0;
		$history_id    = isset($_POST['history_id'])   ? absint($_POST['history_id'])   : 0;
		$variant_count = isset($_POST['variant_count']) ? absint($_POST['variant_count']) : 2;
		$requested     = isset($_POST['components']) && is_array($_POST['components'])
			? array_map('sanitize_key', wp_unslash($_POST['components']))
			: array('title', 'excerpt', 'content');

		if (!$post_id || !$history_id) {
			wp_send_json_error(array('message' => __('Invalid request.', 'ai-post-scheduler')));
		}

		// Clamp variant count to the configured maximum (2–3).
		$max_variants  = (int) get_option('aips_max_draft_variants', 3);
		$variant_count = max(2, min($max_variants, $variant_count));

		// Validate requested components (featured_image is excluded from multi-draft).
		$valid_components = array('title', 'excerpt', 'content');
		$components = array_values(array_intersect($requested, $valid_components));
		if (empty($components)) {
			wp_send_json_error(array('message' => __('No valid components selected.', 'ai-post-scheduler')));
		}

		if (!current_user_can('edit_post', $post_id)) {
			wp_send_json_error(array('message' => __('You do not have permission to edit this post.', 'ai-post-scheduler')));
		}

		$post = get_post($post_id);
		if (!$post) {
			wp_send_json_error(array('message' => __('Post not found.', 'ai-post-scheduler')));
		}

		// Build generation context once — it is stateless so safe to reuse.
		$context = $this->service->get_generation_context($history_id);
		if (is_wp_error($context)) {
			wp_send_json_error(array('message' => $context->get_error_message()));
		}

		if (isset($context['post_id']) && absint($context['post_id']) !== $post_id) {
			wp_send_json_error(array('message' => __('Invalid history context for this post.', 'ai-post-scheduler')));
		}

		// Enrich context with current post data so components that depend on
		// sibling values (e.g. excerpt needs title + content) remain coherent.
		$context['post_id']        = $post_id;
		$context['history_id']     = $history_id;
		$context['current_title']   = $post->post_title;
		$context['current_excerpt'] = $post->post_excerpt;
		$context['current_content'] = $post->post_content;

		// Generate all variants.
		$variants = array();
		$errors   = array();

		for ($i = 0; $i < $variant_count; $i++) {
			$variant = array();

			foreach ($components as $component) {
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
				}

				if (is_wp_error($result)) {
					$errors[] = sprintf(
						/* translators: 1: variant number (1-based), 2: component name, 3: error message */
						__('Variant %1$d – %2$s: %3$s', 'ai-post-scheduler'),
						$i + 1,
						$component,
						$result->get_error_message()
					);
					$variant[$component] = null;
				} else {
					$variant[$component] = $result;
				}
			}

			$variants[] = $variant;
		}

		if (!empty($errors) && count($errors) === $variant_count * count($components)) {
			wp_send_json_error(array(
				'message' => __('All variant generations failed. Please try again.', 'ai-post-scheduler'),
				'errors'  => $errors,
			));
		}

		wp_send_json_success(array(
			'variants'       => $variants,
			'components'     => $components,
			'variant_count'  => $variant_count,
			'errors'         => $errors,
		));
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
