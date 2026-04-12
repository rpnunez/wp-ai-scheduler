<?php
/**
 * Component Regeneration Service
 *
 * Service class responsible for regenerating individual post components
 * (title, excerpt, content, featured image) using the original generation context.
 *
 * @package AI_Post_Scheduler
 * @since 2.0.0
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Component_Regeneration_Service
 *
 * Handles regeneration of individual post components using AI while preserving
 * the original generation context (template, author, topic).
 */
class AIPS_Component_Regeneration_Service {
	
	/**
	 * @var AIPS_Generator Generator instance
	 */
	private $generator;
	
	/**
	 * @var AIPS_Prompt_Builder Prompt builder instance
	 */
	private $prompt_builder;

	/**
	 * @var AIPS_Prompt_Builder_Post_Content Post content prompt builder instance
	 */
	private $post_content_prompt_builder;

	/**
	 * @var AIPS_Prompt_Builder_Post_Title Post title prompt builder instance
	 */
	private $post_title_prompt_builder;

	/**
	 * @var AIPS_Prompt_Builder_Post_Featured_Image Post featured image prompt builder instance
	 */
	private $post_featured_image_prompt_builder;
	
	/**
	 * @var AIPS_Image_Service Image service instance
	 */
	private $image_service;
	
	/**
	 * @var AIPS_History_Repository History repository
	 */
	private $history_repository;

	/**
	 * @var AIPS_Generation_Context_Factory Factory for creating generation contexts
	 */
	private $generation_context_factory;
	
	/**
	 * @var AIPS_Template_Processor Template processor
	 */
	private $template_processor;
	
	/**
	 * @var AIPS_Article_Structure_Manager Structure manager
	 */
	private $structure_manager;
	
	/**
	 * Constructor
	 *
	 * Dependencies are resolved from the container when available to ensure
	 * consistent singleton usage across the plugin.
	 */
	public function __construct() {
		$container = AIPS_Container::get_instance();

		// Use container for registered services
		$this->history_repository = $container->make(AIPS_History_Repository_Interface::class);
		$ai_service = $container->make(AIPS_AI_Service_Interface::class);

		$this->generation_context_factory = $container->make(AIPS_Generation_Context_Factory::class);
		$this->template_processor = new AIPS_Template_Processor();
		$this->structure_manager = new AIPS_Article_Structure_Manager();

		// Initialize services with container-resolved AI service
		$this->generator = new AIPS_Generator(null, $ai_service);
		$this->image_service = new AIPS_Image_Service($ai_service);
		$this->prompt_builder = new AIPS_Prompt_Builder($this->template_processor, $this->structure_manager);
		$this->post_content_prompt_builder = new AIPS_Prompt_Builder_Post_Content(
			$this->template_processor,
			new AIPS_Prompt_Builder_Article_Structure_Section($this->structure_manager, null, $this->template_processor)
		);
		$this->post_title_prompt_builder = $this->prompt_builder->get_post_title_builder();
		$this->post_featured_image_prompt_builder = new AIPS_Prompt_Builder_Post_Featured_Image($this->template_processor);
	}
	
	/**
	 * Get the generation context for a history record
	 *
	 * Retrieves the original generation context (Template or Topic) used to generate
	 * a post so that components can be regenerated with the same context.
	 *
	 * @param int $history_id History record ID
	 * @return array|WP_Error Context array with 'generation_context' key containing AIPS_Generation_Context object, or error on failure
	 */
	public function get_generation_context($history_id) {
		return $this->generation_context_factory->create_from_history_id($history_id);
	}
	
	/**
	 * Regenerate post title
	 *
	 * @param array $context Generation context with 'generation_context' key
	 * @return string|WP_Error Generated title or error
	 */
	public function regenerate_title($context) {
		if (!isset($context['generation_context']) || !($context['generation_context'] instanceof AIPS_Generation_Context)) {
			return new WP_Error('missing_context', __('Generation context is required.', 'ai-post-scheduler'));
		}
		
		$generation_context = $context['generation_context'];
		$post_id = isset($context['post_id']) ? absint($context['post_id']) : 0;
		$history_id = isset($context['history_id']) ? absint($context['history_id']) : 0;
		
		$history_container = AIPS_History_Container::resolve_existing($this->history_repository, $post_id, $history_id);
		if (is_wp_error($history_container)) {
			return $history_container;
		}
		
		// Set the history container on the generator so it logs to the same container
		$this->generator->set_history_container($history_container);
		
		// Get template, voice, and topic for generator
		if ($generation_context->get_type() === 'template') {
			if (!method_exists($generation_context, 'get_template')) {
				return new WP_Error('invalid_context', __('Template context is missing template details.', 'ai-post-scheduler'));
			}

			$template = call_user_func(array($generation_context, 'get_template'));
			$voice = $generation_context->get_voice();
			$topic = $generation_context->get_topic();
			
			// Use generator's generate_title method
			$result = $this->generator->generate_title($template, $voice, $topic);
		} else {
			// For topic context, build the prompt and generate using generic method
			$post_content = $post_id ? get_post_field('post_content', $post_id) : '';
			$prompt = $this->post_title_prompt_builder->build($generation_context, null, null, $post_content);
			// Use generate_content with log_type 'title' for proper logging
			$result = $this->generator->generate_content($prompt, array(), 'title');
		}
		
		if (is_wp_error($result)) {
			return $result;
		}
		
		// Clean and return
		return trim($result);
	}
	
	/**
	 * Regenerate post excerpt
	 *
	 * @param array $context Generation context with 'generation_context' key
	 * @return string|WP_Error Generated excerpt or error
	 */
	public function regenerate_excerpt($context) {
		if (!isset($context['generation_context']) || !($context['generation_context'] instanceof AIPS_Generation_Context)) {
			return new WP_Error('missing_context', __('Generation context is required.', 'ai-post-scheduler'));
		}
		
		$generation_context = $context['generation_context'];
		$title = isset($context['current_title']) ? $context['current_title'] : '';
		$content = isset($context['current_content']) ? $context['current_content'] : '';
		$post_id = isset($context['post_id']) ? absint($context['post_id']) : 0;
		$history_id = isset($context['history_id']) ? absint($context['history_id']) : 0;
		
		$history_container = AIPS_History_Container::resolve_existing($this->history_repository, $post_id, $history_id);
		if (is_wp_error($history_container)) {
			return $history_container;
		}
		
		// Set the history container on the generator so it logs to the same container
		$this->generator->set_history_container($history_container);
		
		// Fall back to post data when the caller did not supply current values
		if (empty($title) && $post_id) {
			$title = get_the_title($post_id);
		}
		if (empty($content) && $post_id) {
			$content = get_post_field('post_content', $post_id);
		}

		// Get voice and topic for generator
		$voice = null;
		$topic_str = $generation_context->get_topic();
		
		if ($generation_context->get_type() === 'template') {
			$voice = $generation_context->get_voice();
		}
		
		// Use generator's generate_excerpt method
		$result = $this->generator->generate_excerpt($title, $content, $voice, $topic_str);
		
		if (is_wp_error($result)) {
			return $result;
		}
		
		return trim($result);
	}
	
	/**
	 * Regenerate post content
	 *
	 * @param array $context Generation context with 'generation_context' key
	 * @return string|WP_Error Generated content or error
	 */
	public function regenerate_content($context) {
		if (!isset($context['generation_context']) || !($context['generation_context'] instanceof AIPS_Generation_Context)) {
			return new WP_Error('missing_context', __('Generation context is required.', 'ai-post-scheduler'));
		}
		
		$generation_context = $context['generation_context'];
		$post_id = isset($context['post_id']) ? absint($context['post_id']) : 0;
		$history_id = isset($context['history_id']) ? absint($context['history_id']) : 0;
		
		$history_container = AIPS_History_Container::resolve_existing($this->history_repository, $post_id, $history_id);
		if (is_wp_error($history_container)) {
			return $history_container;
		}
		
		// Set the history container on the generator so it logs to the same container
		$this->generator->set_history_container($history_container);
		
		// Build the content prompt using the generation context
		$prompt = $this->post_content_prompt_builder->build($generation_context);
		
		// Generate content using the prompt
		$result = $this->generator->generate_content($prompt);
		
		if (is_wp_error($result)) {
			return $result;
		}
		
		return $result;
	}

	/**
	 * Determine whether featured image should be regenerated.
	 *
	 * For Regenerate All we only regenerate images when there is an existing
	 * generated featured image, or when the original generation attempted and
	 * failed to produce the featured image.
	 *
	 * @param int $post_id Post ID.
	 * @param int $history_id History ID.
	 * @return bool
	 */
	public function should_regenerate_featured_image($post_id, $history_id = 0) {
		$post_id = absint($post_id);
		$history_id = absint($history_id);

		if (!$post_id) {
			return false;
		}

		$thumbnail_id = 0;
		if (function_exists('get_post_thumbnail_id')) {
			$thumbnail_id = absint(get_post_thumbnail_id($post_id));
		} else {
			$thumbnail_id = absint($this->get_post_meta_value($post_id, '_thumbnail_id'));
		}

		$component_statuses = json_decode((string) $this->get_post_meta_value($post_id, 'aips_post_generation_component_statuses'), true);
		$has_featured_image_status = is_array($component_statuses)
			&& array_key_exists('featured_image', $component_statuses);
		$featured_image_status = $has_featured_image_status ? $component_statuses['featured_image'] : null;

		// Regenerate when there is an existing generated featured image.
		if ($thumbnail_id > 0 && !empty($featured_image_status)) {
			return true;
		}

		// Regenerate when the original generation attempted and failed to produce the featured image.
		if ($has_featured_image_status && empty($featured_image_status)) {
			return true;
		}

		if ($history_id && $this->history_repository->did_featured_image_generation_fail($history_id)) {
			return true;
		}

		return false;
	}

	/**
	 * Regenerate all supported post components.
	 *
	 * @param array $context Generation context payload.
	 * @return array|WP_Error
	 */
	public function regenerate_all_components($context) {
		if (!isset($context['generation_context']) || !($context['generation_context'] instanceof AIPS_Generation_Context)) {
			return new WP_Error('missing_context', __('Generation context is required.', 'ai-post-scheduler'));
		}

		$post_id = isset($context['post_id']) ? absint($context['post_id']) : 0;
		$history_id = isset($context['history_id']) ? absint($context['history_id']) : 0;

		if (!$post_id || !$history_id) {
			return new WP_Error('invalid_request', __('Post and history identifiers are required.', 'ai-post-scheduler'));
		}

		$result = array(
			'regenerated' => array(),
			'skipped' => array(),
			'errors' => array(),
		);

		$title = $this->regenerate_title($context);
		if (is_wp_error($title)) {
			$result['errors']['title'] = $title->get_error_message();
		} else {
			$result['regenerated']['title'] = $title;
			$context['current_title'] = $title;
		}

		$content = $this->regenerate_content($context);
		if (is_wp_error($content)) {
			$result['errors']['content'] = $content->get_error_message();
		} else {
			$result['regenerated']['content'] = $content;
			$context['current_content'] = $content;
		}

		$excerpt = $this->regenerate_excerpt($context);
		if (is_wp_error($excerpt)) {
			$result['errors']['excerpt'] = $excerpt->get_error_message();
		} else {
			$result['regenerated']['excerpt'] = $excerpt;
		}

		if ($this->should_regenerate_featured_image($post_id, $history_id)) {
			$featured_image = $this->regenerate_featured_image($context);
			if (is_wp_error($featured_image)) {
				$result['errors']['featured_image'] = $featured_image->get_error_message();
			} else {
				$result['regenerated']['featured_image'] = $featured_image;
			}
		} else {
			$result['skipped']['featured_image'] = __('Featured image skipped because there was no previous generation and no original image-generation failure.', 'ai-post-scheduler');
		}

		return $result;
	}
	
	/**
	 * Regenerate featured image
	 *
	 * @param array $context Generation context with 'generation_context' key
	 * @return array|WP_Error Array with attachment_id and url, or error
	 */
	public function regenerate_featured_image($context) {
		if (!isset($context['generation_context']) || !($context['generation_context'] instanceof AIPS_Generation_Context)) {
			return new WP_Error('missing_context', __('Generation context is required.', 'ai-post-scheduler'));
		}
		
		if (!isset($context['post_id'])) {
			return new WP_Error('missing_post_id', __('Post ID is required.', 'ai-post-scheduler'));
		}
		
		$generation_context = $context['generation_context'];
		$title = isset($context['current_title']) ? $context['current_title'] : '';
		$post_id = absint($context['post_id']);

		// Fall back to the post title when the caller did not supply one
		if (empty($title) && $post_id) {
			$title = get_the_title($post_id);
		}

		$history_id = isset($context['history_id']) ? absint($context['history_id']) : 0;
		$image_generation_start = microtime(true);

		$history_container = AIPS_History_Container::resolve_existing($this->history_repository, $post_id, $history_id);
		if (is_wp_error($history_container)) {
			return $history_container;
		}
		
		$processed_image_prompt = $this->post_featured_image_prompt_builder->build($generation_context);
		if (empty($processed_image_prompt)) {
			$history_container->record(
				'metric_generation_result',
				'Featured image regeneration metric snapshot',
				array(
					'outcome' => 'failed',
					'duration_seconds' => (int) round( microtime(true) - $image_generation_start ),
					'image_attempted' => true,
					'image_success' => false,
				)
			);

			return new WP_Error('no_image_prompt', __('No image prompt available for this context.', 'ai-post-scheduler'));
		}
		
		// Log the AI request for image generation
		$history_container->record(
			'ai_request',
			"Requesting AI image generation for featured_image",
			array(
				'prompt' => $processed_image_prompt,
				'title' => $title,
			),
			null,
			array('component' => 'featured_image', 'post_id' => $post_id)
		);
		
		// Generate and upload the featured image
		$attachment_id = $this->image_service->generate_and_upload_featured_image($processed_image_prompt, $title);
		
		if (is_wp_error($attachment_id)) {
			// Log the error
			$history_container->record_error($attachment_id->get_error_message(), array(
				'component' => 'featured_image',
				'post_id' => $post_id,
			));

			$history_container->record(
				'metric_generation_result',
				'Featured image regeneration metric snapshot',
				array(
					'outcome' => 'failed',
					'duration_seconds' => (int) round( microtime(true) - $image_generation_start ),
					'image_attempted' => true,
					'image_success' => false,
				)
			);

			return $attachment_id;
		}
		
		// Log successful image generation
		$history_container->record(
			'ai_response',
			"AI image generation successful for featured_image",
			null,
			array(
				'attachment_id' => $attachment_id,
				'url' => wp_get_attachment_url($attachment_id),
			),
			array('component' => 'featured_image', 'post_id' => $post_id)
		);

		$history_container->record(
			'metric_generation_result',
			'Featured image regeneration metric snapshot',
			array(
				'outcome' => 'completed',
				'duration_seconds' => (int) round( microtime(true) - $image_generation_start ),
				'image_attempted' => true,
				'image_success' => true,
			)
		);
		
		// Return attachment ID and URL
		return array(
			'attachment_id' => $attachment_id,
			'url' => wp_get_attachment_url($attachment_id),
		);
	}

	/**
	 * Capture the current component value as a revision snapshot.
	 *
	 * This enables immediate restore of the value that existed before a new
	 * regeneration request changes the modal content.
	 *
	 * @param int    $post_id Post ID.
	 * @param int    $history_id History ID.
	 * @param string $component Component type.
	 * @param mixed  $value Current component value.
	 * @param string $reason Snapshot reason marker.
	 * @return bool|WP_Error True on success, or WP_Error on failure.
	 */
	public function capture_component_revision($post_id, $history_id, $component, $value, $source = 'manual_edit', $reason = 'manual_edit') {
		$post_id = absint($post_id);
		$history_id = absint($history_id);
		$source = sanitize_key($source);
		$reason = sanitize_key($reason);

		$valid_components = array('title', 'excerpt', 'content', 'featured_image');
		if (!in_array($component, $valid_components, true)) {
			return new WP_Error('invalid_component', __('Invalid component type.', 'ai-post-scheduler'));
		}

		$history_container = AIPS_History_Container::resolve_existing($this->history_repository, $post_id, $history_id);
		if (is_wp_error($history_container)) {
			return $history_container;
		}

		$context = array(
			'component' => $component,
			'post_id' => $post_id,
			'source' => $source ? $source : 'manual_edit',
			'reason' => $reason,
		);

		if ('manual_edit' === $context['source']) {
			$context['user_id'] = get_current_user_id();
			$context['user_login'] = wp_get_current_user()->user_login;
		}

		$history_container->record(
			'ai_response',
			sprintf('Captured revision snapshot for %s before regeneration', $component),
			null,
			$value,
			$context
		);

		return true;
	}

	/**
	 * Safely read post meta in runtime and limited test environments.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $meta_key Meta key.
	 * @return mixed
	 */
	private function get_post_meta_value($post_id, $meta_key) {
		if (function_exists('get_post_meta')) {
			return get_post_meta($post_id, $meta_key, true);
		}

		global $aips_test_meta;
		if (isset($aips_test_meta[$post_id]) && array_key_exists($meta_key, $aips_test_meta[$post_id])) {
			return $aips_test_meta[$post_id][$meta_key];
		}

		return '';
	}

}
