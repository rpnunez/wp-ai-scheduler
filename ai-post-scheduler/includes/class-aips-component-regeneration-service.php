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
	 */
	public function __construct() {
		$this->history_repository = new AIPS_History_Repository();
		$this->generation_context_factory = new AIPS_Generation_Context_Factory();
		$this->template_processor = new AIPS_Template_Processor();
		$this->structure_manager = new AIPS_Article_Structure_Manager();
		
		// Initialize AI services
		$ai_service = new AIPS_AI_Service();
		$this->generator = new AIPS_Generator(null, $ai_service);
		$this->image_service = new AIPS_Image_Service($ai_service);
		$this->prompt_builder = new AIPS_Prompt_Builder($this->template_processor, $this->structure_manager);
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
		
		// Find and reuse existing History Container for this post
		$history_record = $this->history_repository->get_by_post_id($post_id);
		if (!$history_record) {
			return new WP_Error('no_history', __('Could not find history record for post.', 'ai-post-scheduler'));
		}
		
		// Load the existing History Container
		$history_container = AIPS_History_Container::load_existing($this->history_repository, $history_record->id);
		if (!$history_container) {
			return new WP_Error('container_load_failed', __('Could not load history container.', 'ai-post-scheduler'));
		}
		
		// Set the history container on the generator so it logs to the same container
		$this->generator->set_history_container($history_container);
		
		// Get template, voice, and topic for generator
		if ($generation_context->get_type() === 'template') {
			$template = $generation_context->get_template();
			$voice = $generation_context->get_voice();
			$topic = $generation_context->get_topic();
			
			// Use generator's generate_title method
			$result = $this->generator->generate_title($template, $voice, $topic);
		} else {
			// For topic context, build the prompt and generate using generic method
			$prompt = $this->prompt_builder->build_title_prompt($generation_context, null, null, '');
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
		
		// Find and reuse existing History Container for this post
		$history_record = $this->history_repository->get_by_post_id($post_id);
		if (!$history_record) {
			return new WP_Error('no_history', __('Could not find history record for post.', 'ai-post-scheduler'));
		}
		
		// Load the existing History Container
		$history_container = AIPS_History_Container::load_existing($this->history_repository, $history_record->id);
		if (!$history_container) {
			return new WP_Error('container_load_failed', __('Could not load history container.', 'ai-post-scheduler'));
		}
		
		// Set the history container on the generator so it logs to the same container
		$this->generator->set_history_container($history_container);
		
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
		
		// Find and reuse existing History Container for this post
		$history_record = $this->history_repository->get_by_post_id($post_id);
		if (!$history_record) {
			return new WP_Error('no_history', __('Could not find history record for post.', 'ai-post-scheduler'));
		}
		
		// Load the existing History Container
		$history_container = AIPS_History_Container::load_existing($this->history_repository, $history_record->id);
		if (!$history_container) {
			return new WP_Error('container_load_failed', __('Could not load history container.', 'ai-post-scheduler'));
		}
		
		// Set the history container on the generator so it logs to the same container
		$this->generator->set_history_container($history_container);
		
		// Build the content prompt using the generation context
		$prompt = $this->prompt_builder->build_content_prompt($generation_context);
		
		// Generate content using the prompt
		$result = $this->generator->generate_content($prompt);
		
		if (is_wp_error($result)) {
			return $result;
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
		$history_id = isset($context['history_id']) ? absint($context['history_id']) : 0;
		
		// Get the image prompt from the generation context
		$image_prompt = $generation_context->get_image_prompt();
		if (empty($image_prompt)) {
			return new WP_Error('no_image_prompt', __('No image prompt available for this context.', 'ai-post-scheduler'));
		}
		
		// Process the image prompt with topic if available
		$topic_str = $generation_context->get_topic();
		$processed_image_prompt = $this->template_processor->process($image_prompt, $topic_str);
		
		// Find and reuse existing History Container for this post
		$history_record = $this->history_repository->get_by_post_id($post_id);
		if (!$history_record) {
			return new WP_Error('no_history', __('Could not find history record for post.', 'ai-post-scheduler'));
		}
		
		// Load the existing History Container
		$history_container = AIPS_History_Container::load_existing($this->history_repository, $history_record->id);
		if (!$history_container) {
			return new WP_Error('container_load_failed', __('Could not load history container.', 'ai-post-scheduler'));
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
		
		// Return attachment ID and URL
		return array(
			'attachment_id' => $attachment_id,
			'url' => wp_get_attachment_url($attachment_id),
		);
	}
	

}
