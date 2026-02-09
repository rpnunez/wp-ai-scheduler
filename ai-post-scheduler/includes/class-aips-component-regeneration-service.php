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
	 * @var AIPS_Template_Repository Template repository
	 */
	private $template_repository;
	
	/**
	 * @var AIPS_Author_Topics_Repository Author topics repository
	 */
	private $author_topics_repository;
	
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
		$this->template_repository = new AIPS_Template_Repository();
		$this->author_topics_repository = new AIPS_Author_Topics_Repository();
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
	 * Retrieves the original template, author, and topic data used to generate
	 * a post so that components can be regenerated with the same context.
	 *
	 * @param int $history_id History record ID
	 * @return array|WP_Error Context array or error on failure
	 */
	public function get_generation_context($history_id) {
		// Fetch history record
		$history = $this->history_repository->get_by_id($history_id);
		
		if (!$history) {
			return new WP_Error('invalid_history', __('Invalid history record.', 'ai-post-scheduler'));
		}
		
		$context = array(
			'history_id' => $history_id,
			'post_id' => $history->post_id,
			'template_id' => $history->template_id,
			'template_data' => null,
			'author_id' => $history->author_id,
			'author_data' => null,
			'topic_id' => $history->topic_id,
			'topic_data' => null,
			'structure_id' => $history->structure_id,
		);
		
		// Fetch template data
		if ($history->template_id) {
			$template = $this->template_repository->get_by_id($history->template_id);
			if ($template) {
				$context['template_data'] = $template;
			}
		}
		
		// Fetch topic data (author-topic relationship)
		if ($history->topic_id) {
			$topic = $this->author_topics_repository->get_by_id($history->topic_id);
			if ($topic) {
				$context['topic_data'] = $topic;
			}
		}
		
		return $context;
	}
	
	/**
	 * Regenerate post title
	 *
	 * @param array $context Generation context
	 * @return string|WP_Error Generated title or error
	 */
	public function regenerate_title($context) {
		if (!isset($context['template_data'])) {
			return new WP_Error('missing_template', __('Template data is required.', 'ai-post-scheduler'));
		}
		
		$template = $context['template_data'];
		$topic = isset($context['topic_data']) ? $context['topic_data']->title : '';
		
		// Build title prompt using the prompt builder
		$prompt = $this->prompt_builder->build_title_prompt($template, $topic);
		
		// Call AI service
		$result = $this->generator->generate($prompt);
		
		if (is_wp_error($result)) {
			return $result;
		}
		
		// Clean and return
		return trim($result);
	}
	
	/**
	 * Regenerate post excerpt
	 *
	 * @param array $context Generation context
	 * @return string|WP_Error Generated excerpt or error
	 */
	public function regenerate_excerpt($context) {
		if (!isset($context['template_data'])) {
			return new WP_Error('missing_template', __('Template data is required.', 'ai-post-scheduler'));
		}
		
		$template = $context['template_data'];
		$topic = isset($context['topic_data']) ? $context['topic_data']->title : '';
		$title = isset($context['current_title']) ? $context['current_title'] : '';
		
		// Build excerpt prompt
		$prompt = $this->prompt_builder->build_excerpt_prompt($template, $topic, $title);
		
		$result = $this->generator->generate($prompt);
		
		if (is_wp_error($result)) {
			return $result;
		}
		
		return trim($result);
	}
	
	/**
	 * Regenerate post content
	 *
	 * @param array $context Generation context
	 * @return string|WP_Error Generated content or error
	 */
	public function regenerate_content($context) {
		if (!isset($context['template_data'])) {
			return new WP_Error('missing_template', __('Template data is required.', 'ai-post-scheduler'));
		}
		
		$template = $context['template_data'];
		$topic = isset($context['topic_data']) ? $context['topic_data']->title : '';
		$title = isset($context['current_title']) ? $context['current_title'] : '';
		$structure_id = isset($context['structure_id']) ? $context['structure_id'] : null;
		
		// Check if article structure is used
		if ($structure_id) {
			// Use structured content generation
			$result = $this->generator->generate_structured_content($title, $template, $topic, $structure_id);
		} else {
			// Use regular content generation
			$prompt = $this->prompt_builder->build_content_prompt($template, $topic, $title);
			$result = $this->generator->generate($prompt);
		}
		
		if (is_wp_error($result)) {
			return $result;
		}
		
		return $result;
	}
	
	/**
	 * Regenerate featured image
	 *
	 * @param array $context Generation context
	 * @return array|WP_Error Array with attachment_id and url, or error
	 */
	public function regenerate_featured_image($context) {
		if (!isset($context['template_data'])) {
			return new WP_Error('missing_template', __('Template data is required.', 'ai-post-scheduler'));
		}
		
		if (!isset($context['post_id'])) {
			return new WP_Error('missing_post_id', __('Post ID is required.', 'ai-post-scheduler'));
		}
		
		$template = $context['template_data'];
		$topic = isset($context['topic_data']) ? $context['topic_data']->title : '';
		$title = isset($context['current_title']) ? $context['current_title'] : '';
		
		// Build image prompt
		$image_prompt = $this->prompt_builder->build_image_prompt($template, $topic, $title);
		
		// Generate image
		$image_result = $this->image_service->generate_and_attach_image($image_prompt, array(
			'post_id' => $context['post_id'],
			'title' => $title,
		));
		
		if (is_wp_error($image_result)) {
			return $image_result;
		}
		
		// Return attachment ID and URL
		return array(
			'attachment_id' => $image_result['attachment_id'],
			'url' => wp_get_attachment_url($image_result['attachment_id']),
		);
	}
}
