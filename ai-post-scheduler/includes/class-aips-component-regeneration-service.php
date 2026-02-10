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
	 * @var AIPS_History_Service History service for unified logging
	 */
	private $history_service;
	
	/**
	 * @var AIPS_Template_Repository Template repository
	 */
	private $template_repository;
	
	/**
	 * @var AIPS_Author_Topics_Repository Author topics repository
	 */
	private $author_topics_repository;
	
	/**
	 * @var AIPS_Authors_Repository Authors repository
	 */
	private $authors_repository;
	
	/**
	 * @var AIPS_Voices_Repository Voices repository
	 */
	private $voices_repository;
	
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
		$this->history_service = new AIPS_History_Service();
		$this->template_repository = new AIPS_Template_Repository();
		$this->author_topics_repository = new AIPS_Author_Topics_Repository();
		$this->authors_repository = new AIPS_Authors_Repository();
		$this->voices_repository = new AIPS_Voices_Repository();
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
		// Fetch history record
		$history = $this->history_repository->get_by_id($history_id);
		
		if (!$history) {
			return new WP_Error('invalid_history', __('Invalid history record.', 'ai-post-scheduler'));
		}
		
		$context = array(
			'history_id' => $history_id,
			'post_id' => $history->post_id,
			'generation_context' => null,
			'context_type' => null,
			'context_name' => null,
		);
		
		// Determine the generation context type and reconstruct the context object
		if ($history->template_id) {
			// This is a Template-based post
			$template = $this->template_repository->get_by_id($history->template_id);
			if (!$template) {
				return new WP_Error('missing_template', __('Template data not found.', 'ai-post-scheduler'));
			}
			
			// Fetch voice if available
			$voice = null;
			if (!empty($template->voice_id)) {
				$voice = $this->voices_repository->get_by_id($template->voice_id);
			}
			
			// Get topic string if available from topic_id
			$topic_string = null;
			if ($history->topic_id) {
				$topic_data = $this->author_topics_repository->get_by_id($history->topic_id);
				if ($topic_data) {
					$topic_string = $topic_data->topic_title;
				}
			}
			
			// Create Template Context
			$context['generation_context'] = new AIPS_Template_Context($template, $voice, $topic_string);
			$context['context_type'] = 'template';
			$context['context_name'] = $template->name;
			
		} elseif ($history->author_id && $history->topic_id) {
			// This is a Topic-based post (Author + Topic)
			$author = $this->authors_repository->get_by_id($history->author_id);
			if (!$author) {
				return new WP_Error('missing_author', __('Author data not found.', 'ai-post-scheduler'));
			}
			
			$topic = $this->author_topics_repository->get_by_id($history->topic_id);
			if (!$topic) {
				return new WP_Error('missing_topic', __('Topic data not found.', 'ai-post-scheduler'));
			}
			
			// Create Topic Context
			$context['generation_context'] = new AIPS_Topic_Context($author, $topic);
			$context['context_type'] = 'topic';
			$context['context_name'] = $author->name . ': ' . $topic->topic_title;
			
		} else {
			return new WP_Error('invalid_context', __('Unable to determine generation context type.', 'ai-post-scheduler'));
		}
		
		return $context;
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
		
		// Create a History Container for this regeneration
		$history_container = $this->history_service->create('component_regeneration', array(
			'post_id' => $post_id,
			'parent_history_id' => $history_id,
		));
		
		// Set the history container on the generator so it logs properly
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
		
		// Create a History Container for this regeneration
		$history_container = $this->history_service->create('component_regeneration', array(
			'post_id' => $post_id,
			'parent_history_id' => $history_id,
		));
		
		// Set the history container on the generator so it logs properly
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
		
		// Create a History Container for this regeneration
		$history_container = $this->history_service->create('component_regeneration', array(
			'post_id' => $post_id,
			'parent_history_id' => $history_id,
		));
		
		// Set the history container on the generator so it logs properly
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
		
		// Create a History Container for this regeneration
		$history_container = $this->history_service->create('component_regeneration', array(
			'post_id' => $post_id,
			'parent_history_id' => $history_id,
		));
		
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
	
	/**
	 * Get all revisions for a specific post component
	 *
	 * Retrieves all AI_RESPONSE logs for a given post and component type,
	 * ordered by timestamp (newest first). Queries based on component in context.
	 *
	 * @param int $post_id Post ID
	 * @param string $component_type Component type (title, excerpt, content, featured_image)
	 * @param int $limit Maximum number of revisions to retrieve (default: 20)
	 * @return array Array of revision objects with id, timestamp, value, history_id
	 */
	public function get_component_revisions($post_id, $component_type, $limit = 20) {
		global $wpdb;
		
		$history_log_table = $wpdb->prefix . 'aips_history_log';
		$history_table = $wpdb->prefix . 'aips_history';
		
		// Query for AI_RESPONSE logs with matching component in context
		// The context field contains JSON like {"component":"title","post_id":123}
		$sql = $wpdb->prepare("
			SELECT 
				hl.id,
				hl.timestamp,
				hl.details,
				h.id as history_id,
				h.uuid,
				h.post_id
			FROM {$history_log_table} hl
			INNER JOIN {$history_table} h ON hl.history_id = h.id
			WHERE hl.history_type_id = %d
			AND hl.log_type = 'ai_response'
			AND hl.details LIKE %s
			AND (
				h.post_id = %d
				OR hl.details LIKE %s
			)
			ORDER BY hl.timestamp DESC
			LIMIT %d
		",
			AIPS_History_Type::AI_RESPONSE,
			'%"component":"' . $wpdb->esc_like($component_type) . '"%',
			$post_id,
			'%"post_id":' . absint($post_id) . '%',
			$limit
		);
		
		$results = $wpdb->get_results($sql);
		
		if (empty($results)) {
			return array();
		}
		
		// Parse and format the results
		$revisions = array();
		foreach ($results as $row) {
			$details = json_decode($row->details, true);
			if (!$details) {
				continue;
			}
			
			// Extract the output value (the regenerated content)
			$value = '';
			if (isset($details['output'])) {
				if (isset($details['output_encoded']) && $details['output_encoded']) {
					$value = base64_decode($details['output']);
				} else if (is_array($details['output']) && isset($details['output']['value'])) {
					$value = $details['output']['value'];
				} else if (is_string($details['output'])) {
					$value = $details['output'];
				} else {
					// For complex outputs like featured_image with attachment_id and url
					$value = $details['output'];
				}
			}
			
			$revisions[] = array(
				'id' => $row->id,
				'history_id' => $row->history_id,
				'uuid' => $row->uuid,
				'timestamp' => $row->timestamp,
				'component_type' => $component_type,
				'value' => $value,
				'context' => isset($details['context']) ? $details['context'] : array(),
			);
		}
		
		return $revisions;
	}
}
