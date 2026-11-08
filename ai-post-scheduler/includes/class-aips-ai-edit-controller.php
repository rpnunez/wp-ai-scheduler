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
	 * @var AIPS_Post_Manager
	 */
	private $post_manager;
	
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
		$this->post_manager       = new AIPS_Post_Manager();
		
		// Register AJAX endpoints
		add_action('wp_ajax_aips_get_post_components', array($this, 'ajax_get_post_components'));
		add_action('wp_ajax_aips_regenerate_component', array($this, 'ajax_regenerate_component'));
		add_action('wp_ajax_aips_regenerate_all_components', array($this, 'ajax_regenerate_all_components'));
		add_action('wp_ajax_aips_save_post_components', array($this, 'ajax_save_post_components'));
		add_action('wp_ajax_aips_get_component_revisions', array($this, 'ajax_get_component_revisions'));
		add_action('wp_ajax_aips_restore_component_revision', array($this, 'ajax_restore_component_revision'));
		add_action('wp_ajax_aips_reset_component_overrides', array($this, 'ajax_reset_component_overrides'));
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
		
		if (!$post_id) {
			AIPS_Ajax_Response::error(__('Invalid request.', 'ai-post-scheduler'));
		}

		if (!$history_id) {
			$history_record = $this->history_repository->get_by_post_id($post_id);
			if (is_object($history_record) && !empty($history_record->id)) {
				$history_id = absint($history_record->id);
			} else {
				$history_container = AIPS_History_Container::resolve_or_create($this->history_repository, $post_id, 0);
				if (!is_wp_error($history_container) && $history_container->get_id()) {
					$history_id = $history_container->get_id();
				} else {
					AIPS_Ajax_Response::error(__('Invalid request.', 'ai-post-scheduler'));
				}
			}
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
			'post_id' => $post_id,
			'history_id' => $history_id,
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
			'overrides' => get_post_meta($post_id, AIPS_Post_Manager::META_AI_EDIT_OVERRIDES, true) ?: array(),
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
		$custom_directive     = isset($_POST['custom_directive']) ? sanitize_textarea_field(wp_unslash($_POST['custom_directive'])) : '';
		$directive_preset     = isset($_POST['directive_preset']) ? sanitize_key(wp_unslash($_POST['directive_preset'])) : '';
		$model_override       = isset($_POST['model_override']) ? sanitize_text_field(wp_unslash($_POST['model_override'])) : '';
		$temperature_override = isset($_POST['temperature_override']) && '' !== $_POST['temperature_override'] && is_numeric($_POST['temperature_override']) ? floatval($_POST['temperature_override']) : null;
		$downstream_cascaded  = !empty($_POST['downstream_cascaded']) && filter_var($_POST['downstream_cascaded'], FILTER_VALIDATE_BOOLEAN);
		
		if (!$post_id || !$component) {
			AIPS_Ajax_Response::error(__('Invalid request.', 'ai-post-scheduler'));
		}

		if (!$history_id && $post_id) {
			$history_record = $this->history_repository->get_by_post_id($post_id);
			if (is_object($history_record) && !empty($history_record->id)) {
				$history_id = absint($history_record->id);
			} else {
				$history_container = AIPS_History_Container::resolve_or_create($this->history_repository, $post_id, 0);
				if (!is_wp_error($history_container) && $history_container->get_id()) {
					$history_id = $history_container->get_id();
				}
			}
		}

		if (!$history_id) {
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

		// Resolve the current component value to snapshot before regeneration
		$snapshot_value = null;
		if (null !== $current_value && '' !== $current_value) {
			$snapshot_value = $this->sanitize_component_revision_value($component, $current_value);
		} else {
			$raw_current = $this->get_post_component_value($post, $component);
			if (null !== $raw_current && '' !== $raw_current) {
				$snapshot_value = $this->sanitize_component_revision_value($component, $raw_current);
			}
		}

		if (null !== $snapshot_value && !$this->is_component_value_empty($component, $snapshot_value)) {
			if (!$this->is_component_value_in_revisions($post_id, $component, $snapshot_value)) {
				$source = !empty($current_source) ? $current_source : 'persisted';
				$reason = !empty($current_reason) ? $current_reason : ('manual_edit' === $source ? 'pre_regenerate_manual' : 'pre_regenerate_current');

				$snapshot_result = $this->service->capture_component_revision(
					$post_id,
					$history_id,
					$component,
					$snapshot_value,
					$source,
					$reason
				);

				if (is_wp_error($snapshot_result)) {
					$this->log_wp_error($snapshot_result, __METHOD__);
					AIPS_Ajax_Response::error(__('Failed to capture component revision.', 'ai-post-scheduler'));
				}
			}
		}

		// Handle sticky component overrides (model and temperature)
		$existing_overrides = get_post_meta($post_id, AIPS_Post_Manager::META_AI_EDIT_OVERRIDES, true);
		if (!is_array($existing_overrides)) {
			$existing_overrides = array();
		}
		$comp_override = isset($existing_overrides[$component]) && is_array($existing_overrides[$component]) ? $existing_overrides[$component] : array();

		$updated_overrides = false;
		if (isset($_POST['model_override'])) {
			if (!empty($model_override)) {
				$comp_override['model'] = $model_override;
			} else {
				unset($comp_override['model']);
			}
			$updated_overrides = true;
		} elseif (!empty($comp_override['model'])) {
			$model_override = $comp_override['model'];
		}

		if (isset($_POST['temperature_override']) && '' !== $_POST['temperature_override']) {
			if (null !== $temperature_override) {
				$comp_override['temperature'] = max(0.0, min(1.0, $temperature_override));
			} else {
				unset($comp_override['temperature']);
			}
			$updated_overrides = true;
		} elseif (isset($comp_override['temperature'])) {
			$temperature_override = floatval($comp_override['temperature']);
		}

		if ($updated_overrides) {
			if (!empty($comp_override)) {
				$existing_overrides[$component] = $comp_override;
			} else {
				unset($existing_overrides[$component]);
			}
			if (empty($existing_overrides)) {
				delete_post_meta($post_id, AIPS_Post_Manager::META_AI_EDIT_OVERRIDES);
			} else {
				update_post_meta($post_id, AIPS_Post_Manager::META_AI_EDIT_OVERRIDES, $existing_overrides);
			}
		}

		$context['custom_directive']    = $custom_directive;
		$context['directive_preset']    = $directive_preset;
		$context['model_override']      = $model_override;
		$context['temperature_override'] = $temperature_override;

		if ($downstream_cascaded) {
			$context['downstream_cascaded'] = true;
			$context['trigger_detail']     = __('Cascaded downstream alignment from upstream component acceptance', 'ai-post-scheduler');
			$this->apply_upstream_component_context($context);
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
		
		AIPS_Ajax_Response::success(array(
			'new_value'    => $result,
			'model'        => $model_override,
			'temperature'  => $temperature_override,
			'overrides'    => $existing_overrides,
		));
	}

	/**
	 * AJAX handler: Reset component overrides to template default.
	 */
	public function ajax_reset_component_overrides() {
		if (!check_ajax_referer('aips_ajax_nonce', 'nonce', false)) {
			AIPS_Ajax_Response::error(__('Invalid nonce.', 'ai-post-scheduler'));
		}

		if (!current_user_can('edit_posts')) {
			AIPS_Ajax_Response::permission_denied();
		}

		$post_id   = isset($_POST['post_id']) ? absint($_POST['post_id']) : 0;
		$component = isset($_POST['component']) ? sanitize_text_field(wp_unslash($_POST['component'])) : '';

		if (!$post_id || !$component) {
			AIPS_Ajax_Response::error(__('Invalid request.', 'ai-post-scheduler'));
		}

		if (!current_user_can('edit_post', $post_id)) {
			AIPS_Ajax_Response::error(__('You do not have permission to edit this post.', 'ai-post-scheduler'));
		}

		$existing_overrides = get_post_meta($post_id, AIPS_Post_Manager::META_AI_EDIT_OVERRIDES, true);
		if (is_array($existing_overrides) && isset($existing_overrides[$component])) {
			unset($existing_overrides[$component]);
			if (empty($existing_overrides)) {
				delete_post_meta($post_id, AIPS_Post_Manager::META_AI_EDIT_OVERRIDES);
			} else {
				update_post_meta($post_id, AIPS_Post_Manager::META_AI_EDIT_OVERRIDES, $existing_overrides);
			}
		}

		$default_config = AIPS_Config::get_instance()->get_ai_config();
		AIPS_Ajax_Response::success(array(
			'message'             => __('Component overrides reset to template defaults.', 'ai-post-scheduler'),
			'default_model'       => isset($default_config['model']) ? $default_config['model'] : '',
			'default_temperature' => isset($default_config['temperature']) ? floatval($default_config['temperature']) : 0.7,
		));
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

		$current_snapshots = array();
		if (isset($_POST['current_snapshots']) && is_array($_POST['current_snapshots'])) {
			$current_snapshots = $_POST['current_snapshots'];
		} elseif (isset($_POST['manual_snapshots']) && is_array($_POST['manual_snapshots'])) {
			$current_snapshots = $_POST['manual_snapshots'];
		}

		$valid_components = array('title', 'excerpt', 'content', 'featured_image');
		foreach ($valid_components as $component) {
			$snapshot_value = null;
			$source = 'persisted';

			if (isset($current_snapshots[$component]) && null !== $current_snapshots[$component] && '' !== $current_snapshots[$component]) {
				$snapshot_value = $this->sanitize_component_revision_value($component, wp_unslash($current_snapshots[$component]));
				if (isset($manual_snapshots[$component])) {
					$source = 'manual_edit';
				}
			} else {
				$raw_current = $this->get_post_component_value($post, $component);
				if (null !== $raw_current && '' !== $raw_current) {
					$snapshot_value = $this->sanitize_component_revision_value($component, $raw_current);
				}
			}

			if (null !== $snapshot_value && !$this->is_component_value_empty($component, $snapshot_value)) {
				if (!$this->is_component_value_in_revisions($post_id, $component, $snapshot_value)) {
					$reason = ('manual_edit' === $source) ? 'pre_regenerate_all_manual' : 'pre_regenerate_all_current';
					$snapshot_result = $this->service->capture_component_revision(
						$post_id,
						$history_id,
						$component,
						$snapshot_value,
						$source,
						$reason
					);

					if (is_wp_error($snapshot_result)) {
						$this->log_wp_error($snapshot_result, __METHOD__);
						AIPS_Ajax_Response::error(__('Failed to capture component revision.', 'ai-post-scheduler'));
					}
				}
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
		
		// Update post (re-slash post data because wp_update_post expects slashed data)
		$result = wp_update_post(wp_slash($post_data), true);
		
		if (is_wp_error($result)) {
			$this->log_wp_error($result, __METHOD__);
			AIPS_Ajax_Response::error(__('An error occurred while saving post components.', 'ai-post-scheduler'));
		}
		
		// Update featured image
		if (isset($components['featured_image_id'])) {
			$attachment_id = absint($components['featured_image_id']);
			$updated_components[] = 'featured_image';
			if ($attachment_id > 0) {
				set_post_thumbnail($post_id, $attachment_id);
			} else {
				delete_post_thumbnail($post_id);
			}
		}

		// Security: Create a sanitized array of components for the action hook to prevent passing raw POST data
		$sanitized_components = array();
		if (isset($post_data['post_title'])) {
			$sanitized_components['title'] = $post_data['post_title'];
		}
		if (isset($post_data['post_excerpt'])) {
			$sanitized_components['excerpt'] = $post_data['post_excerpt'];
		}
		if (isset($post_data['post_content'])) {
			$sanitized_components['content'] = $post_data['post_content'];
		}
		if (isset($components['featured_image_id'])) {
			$sanitized_components['featured_image_id'] = absint($components['featured_image_id']);
		}

		do_action('aips_post_components_updated', $post_id, $updated_components, $sanitized_components);
		$this->reconcile_partial_generation_state($post_id);

		// Record timeline activity events for each saved/regenerated component
		$history_container = AIPS_History_Container::resolve_or_create($this->history_repository, $post_id);
		if ($history_container && !is_wp_error($history_container)) {
			foreach ($updated_components as $comp) {
				$comp_label = ucfirst(str_replace('_', ' ', $comp));
				$history_container->record(
					'activity',
					sprintf(__('Post %s regenerated via AI Edit', 'ai-post-scheduler'), $comp_label),
					null,
					$this->activity_output_for_component($comp, $sanitized_components),
					array('component' => $comp, 'post_id' => $post_id, 'action' => 'save_component')
				);
			}
		}
		
		AIPS_Ajax_Response::success(array(
			'message' => __('Post updated successfully!', 'ai-post-scheduler'),
			'updated_components' => $updated_components,
			'post_id' => $post_id,
			'title' => isset($post_data['post_title']) ? $post_data['post_title'] : get_the_title($post_id),
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
				$restored_featured_image = $this->normalize_featured_image_revision_value($restored_value);
				if (is_wp_error($restored_featured_image)) {
					AIPS_Ajax_Response::error(__('Invalid featured image revision data.', 'ai-post-scheduler'));
				}

				$attachment_id = $restored_featured_image['attachment_id'];
				if ($attachment_id > 0) {
					$thumbnail_result = set_post_thumbnail($post_id, $attachment_id);
					if (false === $thumbnail_result) {
						AIPS_Ajax_Response::error(__('Failed to restore featured image revision.', 'ai-post-scheduler'));
					}
				} else {
					delete_post_thumbnail($post_id);
				}

				$restored_value = $restored_featured_image;
				break;
		}
		
		// Update post (unless it's featured image which was already set)
		if ($component !== 'featured_image') {
			$result = wp_update_post(wp_slash($post_data), true);
			
			if (is_wp_error($result)) {
				$this->log_wp_error($result, __METHOD__);
				AIPS_Ajax_Response::error(__('An error occurred while restoring component revision.', 'ai-post-scheduler'));
			}
		}

		$sanitized_components = array();
		switch ($component) {
			case 'title':
				$sanitized_components['title'] = sanitize_text_field((string) $restored_value);
				break;
			case 'excerpt':
				$sanitized_components['excerpt'] = sanitize_textarea_field((string) $restored_value);
				break;
			case 'content':
				$sanitized_components['content'] = wp_kses_post((string) $restored_value);
				break;
			case 'featured_image':
				$sanitized_components['featured_image_id'] = is_array($restored_value) && isset($restored_value['attachment_id'])
					? absint($restored_value['attachment_id'])
					: 0;
				break;
		}

		do_action('aips_post_components_updated', $post_id, array($component), $sanitized_components);
		$this->reconcile_partial_generation_state($post_id);

		// Record timeline activity event for restored revision
		$history_container = AIPS_History_Container::resolve_or_create($this->history_repository, $post_id);
		if ($history_container && !is_wp_error($history_container)) {
			$comp_label = ucfirst(str_replace('_', ' ', $component));
			$saved_val  = is_array($restored_value) || is_object($restored_value) ? wp_json_encode($restored_value) : (string) $restored_value;
			$history_container->record(
				'activity',
				sprintf(__('Post %s regenerated via AI Edit', 'ai-post-scheduler'), $comp_label),
				null,
				$saved_val,
				array('component' => $component, 'post_id' => $post_id, 'action' => 'restore_revision', 'revision_id' => $revision_id)
			);
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
	 * Use this instead of calling error_log() directly when handling WP_Error
	 * instances in AJAX handlers. It ensures a consistent log format that includes
	 * the calling method name and the WP_Error code, aiding debugging while keeping
	 * internal error details out of client-facing responses.
	 *
	 * @param WP_Error $error  The error to log.
	 * @param string   $method The calling method name; pass __METHOD__ from the caller.
	 * @return void
	 */
	private function log_wp_error( WP_Error $error, $method = '' ) {
		$context = $method ? '[' . $method . '] ' : '';
		AIPS_Logger::instance()->error( 'AIPS AI Edit Controller Error ' . $context . '(' . $error->get_error_code() . '): ' . $error->get_error_message() );
	}

	/**
	 * Overlay title and content the editor just accepted onto a cascaded regeneration.
	 *
	 * Downstream excerpt generation must follow the draft the user accepted, even
	 * when that draft has not yet been read back from the persisted post.
	 *
	 * @param array $context Generation context, passed by reference.
	 * @return void
	 */
	private function apply_upstream_component_context(array &$context) {
		if (isset($_POST['upstream_title'])) {
			$upstream_title = sanitize_text_field(wp_unslash($_POST['upstream_title']));
			if ('' !== $upstream_title) {
				$context['current_title'] = $upstream_title;
			}
		}

		if (isset($_POST['upstream_content'])) {
			$upstream_content = wp_kses_post(wp_unslash($_POST['upstream_content']));
			if ('' !== trim($upstream_content)) {
				$context['current_content'] = $upstream_content;
			}
		}
	}

	/**
	 * Build the timeline output payload for one saved component.
	 *
	 * Featured image saves are stored under featured_image_id, while the
	 * updated-component list uses featured_image.
	 *
	 * @param string $component            Component key from the update list.
	 * @param array  $sanitized_components Sanitized save payload.
	 * @return string
	 */
	private function activity_output_for_component($component, array $sanitized_components) {
		if ('featured_image' === $component) {
			$attachment_id = 0;
			if (isset($sanitized_components['featured_image_id'])) {
				$attachment_id = absint($sanitized_components['featured_image_id']);
			} elseif (isset($sanitized_components['featured_image'])) {
				$raw = $sanitized_components['featured_image'];
				if (is_array($raw) && isset($raw['attachment_id'])) {
					$attachment_id = absint($raw['attachment_id']);
				} else {
					$attachment_id = absint($raw);
				}
			}

			return wp_json_encode(array(
				'attachment_id' => $attachment_id,
				'url'           => $attachment_id ? (string) wp_get_attachment_url($attachment_id) : '',
			));
		}

		$saved_val = isset($sanitized_components[$component]) ? $sanitized_components[$component] : '';
		if (is_array($saved_val) || is_object($saved_val)) {
			return wp_json_encode($saved_val);
		}

		return (string) $saved_val;
	}

	/**
	 * Accept a featured-image revision as an array, attachment ID, or JSON object.
	 *
	 * Editor widgets send the current thumbnail as a bare attachment ID. Treating
	 * only arrays as valid snapshots drops that ID and skips pre-regeneration capture.
	 *
	 * @param mixed $value Raw featured image value.
	 * @return array|null Normalized array, or null when the shape is unusable.
	 */
	private function coerce_featured_image_revision_input($value) {
		if (is_array($value)) {
			return $value;
		}

		if (is_int($value) || is_float($value)) {
			return array(
				'attachment_id' => absint($value),
			);
		}

		if (!is_string($value)) {
			return null;
		}

		$trimmed = trim($value);
		if ('' !== $trimmed && ctype_digit($trimmed)) {
			return array(
				'attachment_id' => absint($trimmed),
			);
		}

		$decoded = json_decode($trimmed, true);
		if (is_array($decoded)) {
			return $decoded;
		}

		return null;
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
				$value = $this->coerce_featured_image_revision_input($value);
				if (!is_array($value)) {
					return array(
						'attachment_id' => 0,
						'url' => '',
					);
				}

				$attachment_id = 0;
				if (isset($value['attachment_id'])) {
					$attachment_id = absint($value['attachment_id']);
				} elseif (isset($value['id'])) {
					$attachment_id = absint($value['id']);
				}

				$url = '';
				if ($attachment_id > 0) {
					$resolved_url = wp_get_attachment_url($attachment_id);
					if ($resolved_url) {
						$url = esc_url_raw($resolved_url);
					}
				}
				if ('' === $url && isset($value['url'])) {
					$url = esc_url_raw($value['url']);
				}

				return array(
					'attachment_id' => $attachment_id,
					'url' => $url,
				);

			default:
				return '';
		}
	}

	/**
	 * Validate and normalize a featured image revision payload before restore.
	 *
	 * Featured image revisions are stored as arrays with attachment_id/url. If
	 * that shape is missing, the restore should fail instead of silently
	 * pretending the component was restored.
	 *
	 * @param mixed $value Raw revision payload.
	 * @return array|WP_Error
	 */
	private function normalize_featured_image_revision_value($value) {
		if (!is_array($value) || !array_key_exists('attachment_id', $value)) {
			return new WP_Error('invalid_featured_image_revision', __('Invalid featured image revision data.', 'ai-post-scheduler'));
		}

		$attachment_id = absint($value['attachment_id']);
		if ($attachment_id > 0) {
			$attachment = get_post($attachment_id);
			if (!$attachment || 'attachment' !== $attachment->post_type) {
				return new WP_Error('invalid_featured_image_revision', __('Invalid featured image revision data.', 'ai-post-scheduler'));
			}
		}

		return array(
			'attachment_id' => $attachment_id,
			'url' => $attachment_id ? wp_get_attachment_url($attachment_id) : '',
		);
	}

	/**
	 * Reconcile partial-generation metadata against the post's current persisted state.
	 *
	 * This is used after AJAX save/restore requests because the AI Edit
	 * controller is booted in AJAX mode without the admin save_post reconciler.
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	private function reconcile_partial_generation_state($post_id) {
		$this->post_manager->reconcile_generation_status_meta_from_post($post_id);
	}

	/**
	 * Retrieve current component value from the post object.
	 *
	 * @param WP_Post $post Post object.
	 * @param string  $component Component name.
	 * @return mixed
	 */
	private function get_post_component_value($post, $component) {
		if (!$post instanceof WP_Post && !is_object($post)) {
			return '';
		}

		switch ($component) {
			case 'title':
				return isset($post->post_title) ? $post->post_title : '';
			case 'excerpt':
				return isset($post->post_excerpt) ? $post->post_excerpt : '';
			case 'content':
				return isset($post->post_content) ? $post->post_content : '';
			case 'featured_image':
				$attachment_id = isset($post->ID) ? absint(get_post_thumbnail_id($post->ID)) : 0;
				return array(
					'attachment_id' => $attachment_id,
					'url'           => $attachment_id ? (string) wp_get_attachment_url($attachment_id) : '',
				);
			default:
				return '';
		}
	}

	/**
	 * Determine whether a component value is empty.
	 *
	 * @param string $component Component type.
	 * @param mixed  $value Value to check.
	 * @return bool
	 */
	private function is_component_value_empty($component, $value) {
		if ('featured_image' === $component) {
			if (is_array($value)) {
				return empty($value['attachment_id']) && empty($value['url']);
			}
			return empty($value);
		}

		return '' === trim((string) $value);
	}

	/**
	 * Check if a component value is already captured in recent revisions.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $component Component type.
	 * @param mixed  $value Value to check.
	 * @return bool
	 */
	private function is_component_value_in_revisions($post_id, $component, $value) {
		$revisions = $this->history_repository->get_component_revisions($post_id, $component, 10);
		if (empty($revisions)) {
			return false;
		}

		$normalized_target = $this->normalize_revision_comparison_value($component, $value);

		foreach ($revisions as $revision) {
			$existing_value = isset($revision['value']) ? $revision['value'] : null;
			if ($normalized_target === $this->normalize_revision_comparison_value($component, $existing_value)) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Normalize a component value for string comparison.
	 *
	 * @param string $component Component type.
	 * @param mixed  $value Value to normalize.
	 * @return string
	 */
	private function normalize_revision_comparison_value($component, $value) {
		if ('featured_image' === $component) {
			if (is_array($value)) {
				return (string) (isset($value['attachment_id']) ? absint($value['attachment_id']) : 0);
			}
			return (string) absint($value);
		}

		return trim((string) $value);
	}
}
