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
	 * Whether a component key is valid for AI Edit regeneration and revisions.
	 *
	 * Per-image keys use post_image_{id} so each content image keeps its own
	 * revision history beside the featured image.
	 *
	 * @param string $component Component key.
	 * @return bool
	 */
	public static function is_ai_edit_component($component) {
		$component = sanitize_key((string) $component);
		if (in_array($component, array('title', 'slug', 'excerpt', 'content', 'featured_image', 'post_images'), true)) {
			return true;
		}

		return self::post_image_id_from_component($component) > 0;
	}

	/**
	 * Generated-row ID encoded in a post_image_{id} component key.
	 *
	 * @param string $component Component key.
	 * @return int Row ID, or 0 when the key is not a single post image.
	 */
	public static function post_image_id_from_component($component) {
		if (preg_match('/^post_image_([1-9][0-9]*)$/', (string) $component, $matches)) {
			return (int) $matches[1];
		}

		return 0;
	}
	
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
		$this->post_content_prompt_builder = new AIPS_Prompt_Builder_Post_Content(
			$this->template_processor,
			new AIPS_Prompt_Builder_Article_Structure_Section($this->structure_manager, null, $this->template_processor)
		);
		$this->post_title_prompt_builder = $this->prompt_builder->get_post_title_builder();
		$this->post_featured_image_prompt_builder = new AIPS_Prompt_Builder_Post_Featured_Image($this->template_processor);
	}
	
	/**
	 * Rebuild the original generation conversation for a post.
	 *
	 * The transcript is reconstructed rather than stored: the content prompt is
	 * deterministic from the generation context, and the article it produced is
	 * the post body. That gives a regenerated component the same context the
	 * original run had, without re-pasting the article into the prompt.
	 *
	 * @param AIPS_Generation_Context $generation_context Original generation context.
	 * @param int                     $post_id            Post being regenerated.
	 * @param bool                    $include_title      Whether to append the title
	 *                                                    exchange. Excluded when the
	 *                                                    title itself is being redone.
	 * @return AIPS_AI_Conversation|null Transcript, or null when unavailable.
	 */
	private function build_conversation($generation_context, $post_id, $include_title = false) {
		if (!$post_id || !$this->generator->supports_conversation()) {
			return null;
		}

		$body = (string) get_post_field('post_content', $post_id);

		if (trim($body) === '') {
			return null;
		}

		$conversation = new AIPS_AI_Conversation();

		if (!$conversation->add_exchange($this->post_content_prompt_builder->build($generation_context), $body)) {
			return null;
		}

		if ($include_title) {
			$title = (string) get_the_title($post_id);

			if (trim($title) !== '') {
				$conversation->add_exchange(
					$this->post_title_prompt_builder->build_followup($generation_context),
					$title
				);
			}
		}

		return $conversation;
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
		
		$history_container = $this->resolve_history_container($context, $post_id, $history_id);
		if (is_wp_error($history_container)) {
			return $history_container;
		}
		$this->record_regeneration_trigger($history_container, $context, __('Title', 'ai-post-scheduler'), $post_id);
		
		// Set the history container on the generator so it logs to the same container
		$this->generator->set_history_container($history_container);

		// Replay the original article so the new title is derived from it rather
		// than from a prompt that pastes the body back in. The title exchange is
		// excluded — that is the component being redone.
		$conversation = $this->build_conversation($generation_context, $post_id);

		if ($conversation !== null) {
			$this->generator->set_conversation($conversation);

			$result = $this->generator->generate_title_for_context($generation_context);

			$this->generator->set_conversation(null);
		} else {
			$post_content = $post_id ? get_post_field('post_content', $post_id) : '';
			$result = $this->generator->generate_title_for_context($generation_context, $post_content);
		}

		if (is_wp_error($result)) {
			return $result;
		}

		// Clean and return
		return trim($result);
	}

	/**
	 * Regenerate post slug
	 *
	 * @param array $context Generation context with 'generation_context' key
	 * @return string|WP_Error Generated slug or error
	 */
	public function regenerate_slug($context) {
		if (!isset($context['generation_context']) || !($context['generation_context'] instanceof AIPS_Generation_Context)) {
			return new WP_Error('missing_context', __('Generation context is required.', 'ai-post-scheduler'));
		}

		$generation_context = $context['generation_context'];
		$post_id            = isset($context['post_id']) ? absint($context['post_id']) : 0;
		$history_id         = isset($context['history_id']) ? absint($context['history_id']) : 0;
		$title              = isset($context['current_title']) ? $context['current_title'] : '';
		$content            = isset($context['current_content']) ? $context['current_content'] : '';

		$history_container = $this->resolve_history_container($context, $post_id, $history_id);
		if (is_wp_error($history_container)) {
			return $history_container;
		}
		$this->record_regeneration_trigger($history_container, $context, __('Slug', 'ai-post-scheduler'), $post_id);

		$this->generator->set_history_container($history_container);

		if (empty($title) && $post_id) {
			$title = get_the_title($post_id);
		}
		if (empty($content) && $post_id) {
			$content = get_post_field('post_content', $post_id);
		}

		$conversation = $this->build_conversation($generation_context, $post_id, true);
		if ($conversation !== null) {
			$this->generator->set_conversation($conversation);
			$success = false;
			$result  = $this->generator->generate_slug_from_context($generation_context, '', $title, array(), $success);
			$this->generator->set_conversation(null);
		} else {
			$success = false;
			$result  = $this->generator->generate_slug_from_context($generation_context, $content, $title, array(), $success);
		}

		if (is_wp_error($result)) {
			return $result;
		}

		$slug = trim($result);
		if (empty($slug)) {
			return new WP_Error('slug_generation_failed', __('Failed to generate a valid slug.', 'ai-post-scheduler'));
		}

		return $slug;
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
		
		$history_container = $this->resolve_history_container($context, $post_id, $history_id);
		if (is_wp_error($history_container)) {
			return $history_container;
		}
		$this->record_regeneration_trigger($history_container, $context, __('Excerpt', 'ai-post-scheduler'), $post_id);
		
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

		// Replay the article and its title so the excerpt is written against the
		// same context the original run had, without re-sending either.
		$conversation = $this->build_conversation($generation_context, $post_id, true);

		$this->generator->set_conversation($conversation);

		// Use generator's generate_excerpt method
		$result = $this->generator->generate_excerpt($title, $content, $voice, $topic_str, array(), $generation_context);

		$this->generator->set_conversation(null);

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
		
		$history_container = $this->resolve_history_container($context, $post_id, $history_id);
		if (is_wp_error($history_container)) {
			return $history_container;
		}
		$this->record_regeneration_trigger($history_container, $context, __('Content', 'ai-post-scheduler'), $post_id);
		
		// Set the history container on the generator so it logs to the same container
		$this->generator->set_history_container($history_container);
		
		// Deliberately not conversational: the component being regenerated IS the
		// article, so replaying the previous body would bias the model toward
		// reproducing it rather than writing a fresh one.
		$this->generator->set_conversation(null);

		// Build the content prompt using the generation context
		$prompt = $this->post_content_prompt_builder->build($generation_context);

		// Generate content using the prompt
		$result = $this->generator->generate_content($prompt, array(
			'_aips_sources_selection' => $this->post_content_prompt_builder->get_sources_selection(),
		));
		
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

		$component_statuses = json_decode((string) $this->get_post_meta_value($post_id, AIPS_Post_Manager::META_GENERATION_COMPONENT_STATUSES), true);
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

		$generation_context = $context['generation_context'];
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

		// Record the trigger once for the whole run rather than once per component.
		$trigger_container = AIPS_History_Container::resolve_existing($this->history_repository, $post_id, $history_id);
		if (!is_wp_error($trigger_container)) {
			$this->record_regeneration_trigger($trigger_container, $context, __('All components', 'ai-post-scheduler'), $post_id);
			$context['trigger_recorded']  = true;
			$context['history_container'] = $trigger_container; // Reused by the per-component calls below.
		}

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

		if (method_exists($generation_context, 'should_generate_slug') && $generation_context->should_generate_slug()) {
			$slug = $this->regenerate_slug($context);
			if (is_wp_error($slug)) {
				$result['errors']['slug'] = $slug->get_error_message();
			} else {
				$result['regenerated']['slug'] = $slug;
			}
		} else {
			$result['skipped']['slug'] = __('Slug generation skipped because it is not enabled for this context.', 'ai-post-scheduler');
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

		if ($this->should_regenerate_post_images($post_id, $context['generation_context'])) {
			$post_images = $this->regenerate_post_images($context, 'all');
			if (is_wp_error($post_images)) {
				$result['errors']['post_images'] = $post_images->get_error_message();
			} else {
				$result['regenerated']['post_images'] = $post_images;
			}
		} else {
			$result['skipped']['post_images'] = __('Post images skipped because this post has no content images configured.', 'ai-post-scheduler');
		}

		return $result;
	}

	/**
	 * Whether content images should be included in a regenerate-all run.
	 *
	 * @param int                     $post_id            Post ID.
	 * @param AIPS_Generation_Context $generation_context Generation context.
	 * @return bool
	 */
	private function should_regenerate_post_images($post_id, $generation_context) {
		$existing = (new AIPS_Post_Images_Generated_Repository())->get_by_post_id($post_id);
		if (!empty($existing)) {
			return true;
		}

		return $generation_context->has_content_images();
	}

	/**
	 * Resolve the post's existing History container, reusing one already
	 * resolved by a multi-component caller (passed in the context).
	 *
	 * @param array $context    Regeneration context (may carry 'history_container').
	 * @param int   $post_id    Post ID.
	 * @param int   $history_id History ID.
	 * @return AIPS_History_Container|WP_Error
	 */
	private function resolve_history_container(array $context, $post_id, $history_id) {
		if (isset($context['history_container']) && $context['history_container'] instanceof AIPS_History_Container) {
			return $context['history_container'];
		}

		return AIPS_History_Container::resolve_existing($this->history_repository, $post_id, $history_id);
	}

	/**
	 * Record who/what triggered a regeneration on the post's existing History
	 * container, so the appended entries are not mistaken for the original run.
	 *
	 * Skipped when the caller already recorded it for a multi-component run.
	 *
	 * @param AIPS_History_Container $history_container Existing container.
	 * @param array                  $context           Regeneration context ('trigger_recorded', 'trigger_detail').
	 * @param string                 $component_label   Human-readable component name.
	 * @param int                    $post_id           Post ID.
	 * @return void
	 */
	private function record_regeneration_trigger($history_container, array $context, $component_label, $post_id) {
		if (!empty($context['trigger_recorded'])) {
			return;
		}

		$source = array(
			/* translators: %s: component name (Title, Content, ...) */
			'event'   => sprintf(__('Component regeneration: %s', 'ai-post-scheduler'), $component_label),
			'post_id' => (int) $post_id,
		);
		if (!empty($context['trigger_detail'])) {
			$source['detail'] = (string) $context['trigger_detail'];
		}

		AIPS_Generation_Trigger::record($history_container, $source, 'regenerate');
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

		$history_container = $this->resolve_history_container($context, $post_id, $history_id);
		if (is_wp_error($history_container)) {
			return $history_container;
		}
		$this->record_regeneration_trigger($history_container, $context, __('Featured image', 'ai-post-scheduler'), $post_id);
		
		$current_content = '';
		if ($post_id > 0) {
			$current_content = (string) get_post_field('post_content', $post_id);
		}

		$processed_image_prompt = $this->generator->process_featured_image_prompt($generation_context, $current_content, $title);
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

		if (!self::is_ai_edit_component($component) || 'post_images' === $component) {
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

	/**
	 * Regenerate one or all post content images.
	 *
	 * Follows the AI Edit modal's contract: nothing is written to the post here.
	 * The updated content is returned to the modal and saved with its Save action,
	 * so 'current_content' in $context should carry the editor's unsaved value.
	 * Generated rows keep describing the stored post until that save, when
	 * AIPS_Post_Images_Service::sync_generated_rows_from_content() updates them,
	 * so cancelling the modal leaves rows and post in step.
	 * A failed attempt leaves any existing image in place.
	 *
	 * @param array      $context Generation context array with post_id, history_id, generation_context.
	 * @param int|string $image_id Specific generated row ID or 'all'.
	 * @return array|WP_Error Array with regenerated image information and updated post content.
	 */
	public function regenerate_post_images($context, $image_id = 'all') {
		if (!isset($context['generation_context']) || !($context['generation_context'] instanceof AIPS_Generation_Context)) {
			return new WP_Error('missing_context', __('Generation context is required.', 'ai-post-scheduler'));
		}

		if (!isset($context['post_id'])) {
			return new WP_Error('missing_post_id', __('Post ID is required.', 'ai-post-scheduler'));
		}

		$post_id = absint($context['post_id']);
		$history_id = isset($context['history_id']) ? absint($context['history_id']) : 0;
		$generation_context = $context['generation_context'];
		$title = isset($context['current_title']) ? $context['current_title'] : get_the_title($post_id);
		$content = isset($context['current_content']) ? $context['current_content'] : (string) get_post_field('post_content', $post_id);

		$history_container = $this->resolve_history_container($context, $post_id, $history_id);
		if (is_wp_error($history_container)) {
			return $history_container;
		}
		$this->record_regeneration_trigger($history_container, $context, __('Post images', 'ai-post-scheduler'), $post_id);

		$generated_repo = new AIPS_Post_Images_Generated_Repository();
		$existing_generated = $generated_repo->get_for_post_history($post_id, $history_id);

		$images_to_regenerate = array();
		if ($image_id === 'all') {
			$images_to_regenerate = $existing_generated;
		} else {
			$target_id = absint($image_id);
			foreach ($existing_generated as $eg) {
				if ((int) $eg->id === $target_id && (int) $eg->post_id === $post_id) {
					$images_to_regenerate[] = $eg;
					break;
				}
			}
		}

		// Fallback: a post generated before images were configured has no rows yet.
		if ($image_id === 'all' && empty($images_to_regenerate) && $generation_context->has_content_images()) {
			$config_images = $generation_context->get_content_images();
			foreach ($config_images as $idx => $ci) {
				$new_gen_id = $generated_repo->create(array(
					'post_id'         => $post_id,
					'history_id'      => $history_id,
					'config_image_id' => !empty($ci->id) ? (int) $ci->id : 0,
					'image_index'     => !empty($ci->placeholder_index) ? (int) $ci->placeholder_index : ($idx + 1),
					'attachment_id'   => 0,
					'image_source'    => !empty($ci->source) ? $ci->source : 'ai_prompt',
					'image_prompt'    => !empty($ci->image_prompt) ? $ci->image_prompt : '',
					'image_url'       => '',
					'placement'       => !empty($ci->placement) ? $ci->placement : 'auto',
					'status'          => 'pending',
				));
				$row = $generated_repo->get_by_id($new_gen_id);
				if ($row) {
					$images_to_regenerate[] = $row;
				}
			}
		}

		if (empty($images_to_regenerate)) {
			return new WP_Error('no_images', __('No post content images found to regenerate.', 'ai-post-scheduler'));
		}

		$total_images = max(count($existing_generated), count($images_to_regenerate));
		$regenerated_results = array();
		$source_content = $content;

		foreach ($images_to_regenerate as $target_row) {
			$gen_id = (int) $target_row->id;
			$order = (int) $target_row->image_index;
			$component_key = 'post_image_' . $gen_id;
			$config = $this->resolve_post_image_config($target_row, $generation_context);
			$placement = !empty($config->placement) ? $config->placement : 'auto';
			$current_url = !empty($target_row->image_url) ? (string) $target_row->image_url : '';
			if ('' === $current_url && (int) $target_row->attachment_id > 0) {
				$current_url = (string) wp_get_attachment_url((int) $target_row->attachment_id);
			}

			if ((int) $target_row->attachment_id > 0 || '' !== $current_url) {
				$snapshot = array(
					'id' => $gen_id,
					'image_index' => $order,
					'attachment_id' => (int) $target_row->attachment_id,
					'url' => $current_url,
				);
				if (!$this->post_image_revision_exists($post_id, $component_key, (int) $target_row->attachment_id)) {
					$this->capture_component_revision($post_id, $history_id, $component_key, $snapshot, 'persisted', 'pre_regenerate_current');
				}
			}

			$resolved = $this->generator->resolve_post_image($config, $title, $generation_context->get_topic());

			if ($resolved['attachment_id'] <= 0) {
				// Keep a working image rather than trading it for a placeholder.
				// The row itself stays unchanged until Save, so closing the modal
				// does not record a failed status against the stored figure.
				$has_previous_image = (int) $target_row->attachment_id > 0;
				$status = $has_previous_image ? 'completed' : 'failed';

				$history_container->record_error($resolved['error'], array(
					'component' => 'post_images',
					'image_id' => $gen_id,
					'post_id' => $post_id,
				));

				$regenerated_results[] = array(
					'id'            => $gen_id,
					'image_index'   => $order,
					'status'        => $status,
					'error_message' => $resolved['error'],
				);
				continue;
			}

			$new_markup = $this->generator->build_post_image_markup(
				$gen_id,
				$resolved['attachment_id'],
				$resolved['image_url'],
				$title,
				!empty($config->caption) ? $config->caption : ''
			);
			$isolated = $this->replace_post_image_markup($source_content, $target_row, $new_markup, $total_images);
			$content = $this->replace_post_image_markup($content, $target_row, $new_markup, $total_images);

			$image_value = array(
				'id' => $gen_id,
				'image_index' => $order,
				'attachment_id' => $resolved['attachment_id'],
				'url' => $resolved['image_url'],
			);
			$history_container->record(
				'ai_response',
				sprintf('AI image generation successful for %s', $component_key),
				null,
				$image_value,
				array('component' => $component_key, 'post_id' => $post_id, 'source' => 'ai_generated')
			);

			$regenerated_results[] = array(
				'id'            => $gen_id,
				'image_index'   => $order,
				'attachment_id' => $resolved['attachment_id'],
				'url'           => $resolved['image_url'],
				'status'        => 'completed',
				'error_message' => '',
				'markup'        => $new_markup,
				'content'       => $isolated,
			);
		}

		return array(
			'post_images' => $regenerated_results,
			'content' => $content,
			'source_content' => $source_content,
		);
	}

	/**
	 * Stage a restored post image into editor content without writing the post.
	 *
	 * @param int    $post_id  Post ID.
	 * @param int    $image_id Generated image row ID.
	 * @param mixed  $value    Revision payload.
	 * @param string $content  Editor content to update.
	 * @return array|WP_Error
	 */
	public function apply_restored_post_image($post_id, $image_id, $value, $content, $history_id = 0) {
		$row = (new AIPS_Post_Images_Generated_Repository())->get_by_id($image_id);
		if (!$row || (int) $row->post_id !== (int) $post_id) {
			return new WP_Error('invalid_post_image', __('Post image not found.', 'ai-post-scheduler'));
		}
		if ($history_id && (int) $row->history_id > 0 && (int) $row->history_id !== (int) $history_id) {
			return new WP_Error('invalid_post_image', __('Post image not found.', 'ai-post-scheduler'));
		}

		if (!is_array($value) || empty($value['attachment_id'])) {
			return new WP_Error('invalid_post_image_revision', __('Invalid post image revision data.', 'ai-post-scheduler'));
		}

		$attachment_id = absint($value['attachment_id']);
		$attachment = get_post($attachment_id);
		if (!$attachment || 'attachment' !== $attachment->post_type || !wp_attachment_is_image($attachment_id)) {
			return new WP_Error('invalid_post_image_revision', __('Invalid post image revision data.', 'ai-post-scheduler'));
		}

		$url = wp_get_attachment_url($attachment_id);
		if (!$url) {
			return new WP_Error('invalid_post_image_revision', __('Invalid post image revision data.', 'ai-post-scheduler'));
		}

		$title = get_the_title($post_id);
		$markup = $this->generator->build_post_image_markup($image_id, $attachment_id, $url, $title, '');
		$siblings = (new AIPS_Post_Images_Generated_Repository())->get_for_post_history($post_id, (int) $row->history_id);
		$content = $this->replace_post_image_markup($content, $row, $markup, max(1, count($siblings)));

		return array(
			'id' => $image_id,
			'image_index' => (int) $row->image_index,
			'attachment_id' => $attachment_id,
			'url' => $url,
			'status' => 'completed',
			'content' => $content,
			'markup' => $markup,
		);
	}

	/**
	 * Whether a post image attachment is already stored as a revision.
	 *
	 * @param int    $post_id       Post ID.
	 * @param string $component_key post_image_{id} key.
	 * @param int    $attachment_id Attachment ID.
	 * @return bool
	 */
	private function post_image_revision_exists($post_id, $component_key, $attachment_id) {
		$revisions = $this->history_repository->get_component_revisions($post_id, $component_key, 10);
		foreach ($revisions as $revision) {
			$value = isset($revision['value']) ? $revision['value'] : null;
			$existing_id = is_array($value) && isset($value['attachment_id']) ? (int) $value['attachment_id'] : 0;
			if ($existing_id === (int) $attachment_id) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Replace one image figure, or insert it when the content has no figure yet.
	 *
	 * @param string $content      Post content.
	 * @param object $row          Generated image row.
	 * @param string $markup       Replacement figure.
	 * @param int    $total_images Images being distributed.
	 * @return string
	 */
	private function replace_post_image_markup($content, $row, $markup, $total_images) {
		$gen_id = (int) $row->id;
		$order = (int) $row->image_index;
		$placement = !empty($row->placement) ? $row->placement : 'auto';
		$figure_pattern = AIPS_Post_Images_Service::get_figure_pattern($gen_id);
		$comment_pattern = '/<!--\s*aips-image-placeholder\s+id=["\']?' . preg_quote((string) $gen_id, '/') . '["\']?\s*-->/i';
		$replace_with_markup = static function () use ($markup) {
			return $markup;
		};

		if (preg_match($figure_pattern, $content)) {
			return preg_replace_callback($figure_pattern, $replace_with_markup, $content, 1);
		}

		if (preg_match($comment_pattern, $content)) {
			return preg_replace_callback($comment_pattern, $replace_with_markup, $content, 1);
		}

		return $this->generator->inject_single_post_image($content, $order, $placement, $markup, $total_images);
	}

	/**
	 * Find the configuration behind a generated image row.
	 *
	 * The generated row only records what was produced, so keywords, media IDs
	 * and captions come from the template/author configuration. When that has
	 * since been removed, fall back to what the row itself recorded.
	 *
	 * @param object                  $row                Generated image row.
	 * @param AIPS_Generation_Context $generation_context Generation context.
	 * @return object Image configuration.
	 */
	private function resolve_post_image_config($row, $generation_context) {
		if (!empty($row->config_image_id)) {
			$config = (new AIPS_Post_Images_Repository())->get_by_id((int) $row->config_image_id);
			if ($config) {
				return $config;
			}
		}

		$indexed = AIPS_Post_Images_Service::with_placeholder_indexes($generation_context->get_content_images());
		foreach ($indexed as $image) {
			if ((int) $image->placeholder_index === (int) $row->image_index) {
				return $image;
			}
		}
		foreach ($indexed as $image) {
			$image_order = isset($image->image_order) ? (int) $image->image_order : 0;
			if ($image_order > 0 && $image_order === (int) $row->image_index) {
				return $image;
			}
		}

		return (object) array(
			'source'            => $row->image_source,
			'image_prompt'      => $row->image_prompt,
			'unsplash_keywords' => $row->image_prompt,
			'media_ids'         => '',
			'placement'         => $row->placement,
			'caption'           => '',
		);
	}

}
