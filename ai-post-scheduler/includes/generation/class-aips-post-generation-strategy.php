<?php
/**
 * Post Generation Strategy
 *
 * Implements AIPS_Generation_Strategy_Interface for WordPress post creation,
 * coordinating the 6 lifecycle stages (Content, Metadata, Persistence, Media,
 * Integrations, Finalization) through registered Post Components.
 *
 * @package AI_Post_Scheduler
 * @since   3.7.25
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Post_Generation_Strategy
 */
class AIPS_Post_Generation_Strategy implements AIPS_Pipeline_Executable_Strategy_Interface {

	/**
	 * @var AIPS_Generation_Component_Registry
	 */
	private $registry;

	/**
	 * @var AIPS_Generator
	 */
	private $generator;

	/**
	 * @var AIPS_Post_Manager
	 */
	private $post_manager;

	/**
	 * @var AIPS_History_Service_Interface
	 */
	private $history_service;

	/**
	 * @var AIPS_Logger_Interface
	 */
	private $logger;

	/**
	 * Logger for finalization warnings (status downgrade/release failures).
	 *
	 * @var AIPS_Logger_Interface
	 */
	private $generation_logger;

	/**
	 * @var AIPS_Template_Processor
	 */
	private $template_processor;

	/**
	 * Last WP_Error encountered during a failed run, preserved for legacy proxy callers.
	 *
	 * @var WP_Error|null
	 */
	private $last_error = null;

	/**
	 * Constructor.
	 *
	 * @param AIPS_Generation_Component_Registry|null $registry          Optional component registry.
	 * @param AIPS_Generator|null                     $generator         Optional generator service.
	 * @param AIPS_Post_Manager|object|null           $post_manager      Optional post manager (or compatible double).
	 * @param AIPS_History_Service_Interface|null     $history_service   Optional history service.
	 * @param AIPS_Logger_Interface|null              $logger            Optional logger.
	 * @param AIPS_Logger_Interface|null              $generation_logger Optional logger for finalization warnings; defaults to $logger.
	 */
	public function __construct(
		?AIPS_Generation_Component_Registry $registry = null,
		?AIPS_Generator $generator = null,
		$post_manager = null,
		?AIPS_History_Service_Interface $history_service = null,
		?AIPS_Logger_Interface $logger = null,
		?AIPS_Logger_Interface $generation_logger = null
	) {
		$container = AIPS_Container::get_instance();

		$this->generator          = $generator ?: new AIPS_Generator();
		$this->post_manager       = $post_manager ?: new AIPS_Post_Manager();
		$this->history_service    = $history_service ?: ($container->has(AIPS_History_Service_Interface::class) ? $container->make(AIPS_History_Service_Interface::class) : new AIPS_History_Service());
		$this->logger             = $logger ?: ($container->has(AIPS_Logger_Interface::class) ? $container->make(AIPS_Logger_Interface::class) : new AIPS_Logger());
		$this->generation_logger  = $generation_logger ?: $this->logger;
		$this->template_processor = new AIPS_Template_Processor();

		if ($registry !== null) {
			$this->registry = $registry;
		} else {
			$this->registry = new AIPS_Generation_Component_Registry();
			$this->registry->register_default_post_components($this->generator, null, $this->post_manager);
		}
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_key(): string {
		return 'posts';
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_label(): string {
		return __('Posts', 'ai-post-scheduler');
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_stages(): array {
		return array(
			AIPS_Post_Component_Interface::STAGE_CONTENT,
			AIPS_Post_Component_Interface::STAGE_METADATA,
			AIPS_Post_Component_Interface::STAGE_PERSISTENCE,
			AIPS_Post_Component_Interface::STAGE_MEDIA,
			AIPS_Post_Component_Interface::STAGE_INTEGRATIONS,
			AIPS_Post_Component_Interface::STAGE_FINALIZATION,
		);
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_components(AIPS_Generation_Context $context): array {
		$ordered = array();
		foreach ($this->get_stages() as $stage) {
			foreach ($this->registry->get_by_stage($stage, $this->get_key()) as $component) {
				if ($component->is_enabled($context)) {
					$ordered[] = $component;
				}
			}
		}

		return $ordered;
	}

	/**
	 * {@inheritdoc}
	 */
	public function before_pipeline(AIPS_Generation_Context $context): void {
		$this->last_error = null;
	}

	/**
	 * {@inheritdoc}
	 */
	public function after_pipeline(AIPS_Generation_Context $context, AIPS_Generation_Result $result): AIPS_Generation_Result {
		return $result;
	}

	/**
	 * Return the last WP_Error produced by execute_run() (or null if none).
	 *
	 * @return WP_Error|null
	 */
	public function get_last_error(): ?WP_Error {
		return $this->last_error;
	}

	/**
	 * Execute the full 6-stage post generation lifecycle.
	 *
	 * @param AIPS_Generation_Context $context Generation context.
	 * @return AIPS_Generation_Result
	 */
	public function execute_run(AIPS_Generation_Context $context): AIPS_Generation_Result {
		$this->last_error = null;
		$generation_start = microtime(true);

		$component_statuses = array(
			'post_title'     => false,
			'post_slug'      => true, // Optional: a failed slug falls back to WordPress's title-based slug.
			'post_excerpt'   => false,
			'featured_image' => !$context->should_generate_featured_image(),
			'post_content'   => false,
		);

		if ($context->has_content_images()) {
			$component_statuses['post_images'] = false;
		}

		// Dispatch post generation started event.
		do_action('aips_post_generation_started', $context->get_id(), $context->get_topic() ? $context->get_topic() : '');

		// Initialize history container for this run.
		$history_metadata = array();
		if ($context instanceof AIPS_Template_Context) {
			$history_metadata['template_id'] = $context->get_id();
			$template = $context->get_template();
			if ($template && !empty($template->campaign_id)) {
				$history_metadata['campaign_id'] = absint($template->campaign_id);
			}
		} elseif ($context instanceof AIPS_Topic_Context) {
			$history_metadata['topic_id'] = $context->get_id();
			$author = $context->get_author();
			if ($author && isset($author->id)) {
				$history_metadata['author_id'] = $author->id;
			}
		}

		$creation_method = $context->get_creation_method() ?: 'manual';
		$history_metadata['creation_method'] = $creation_method;
		$history_metadata['record_kind']     = AIPS_History_Record_Kind::GENERATION;

		$history = $this->history_service->create('post_generation', $history_metadata)->with_session($context);
		$this->generator->set_history_container($history);

		if (!$history->get_id()) {
			$this->logger->log('Failed to create history record', 'error');
		}

		$this->record_generation_trigger($history, $context);

		// Note on Stage Execution (Issue #2400 / Sub-issue #2407):
		// Full post generation delegates each multi-turn stage (content, metadata, post-processing)
		// through AIPS_Generator's stage bridge methods (run_content_stage, run_metadata_stage,
		// run_post_processing_stage). This maintains conversational session continuity, telemetry,
		// and historical lifecycle hooks during the Phase 1 pipeline migration. Component-level
		// prompt builder absorption directly into concrete post components is scheduled for sub-issue #2407.

		// Stage 1: Content (opens conversational session if enabled).
		$this->generator->open_conversation_session();

		$content = $this->generator->run_content_stage($context, $component_statuses, $generation_start);
		if (is_wp_error($content)) {
			$this->last_error = $content;
			return AIPS_Generation_Result::from_wp_error(
				$content,
				microtime(true) - $generation_start,
				(int) $history->get_id()
			);
		}

		$component_statuses['post_content'] = true;

		// Stage 2: Metadata (combined metadata turn + excerpt fallback + gated content image deficit recovery + slug telemetry).
		$metadata_result                = $this->generator->run_metadata_stage($context, $content);
		$title                          = $metadata_result['title'];
		$slug                           = $metadata_result['slug'];
		$excerpt                        = $metadata_result['excerpt'];
		$resolved_image_prompt          = $metadata_result['resolved_image_prompt'];
		$resolved_content_image_prompts = isset($metadata_result['resolved_content_image_prompts']) ? $metadata_result['resolved_content_image_prompts'] : array();

		$component_statuses['post_title']   = $metadata_result['title_success'];
		$component_statuses['post_excerpt'] = $metadata_result['excerpt_success'];
		if (array_key_exists('slug_success', $metadata_result)) {
			$component_statuses['post_slug'] = (bool) $metadata_result['slug_success'];
		}

		// Image components are resolved after post persistence; post_slug is optional.
		$pre_image_statuses = $component_statuses;
		unset($pre_image_statuses['featured_image'], $pre_image_statuses['post_images'], $pre_image_statuses['post_slug']);

		$pre_image_incomplete  = in_array(false, $pre_image_statuses, true);
		$generation_incomplete = $pre_image_incomplete;

		$intended_post_status = $context->get_post_status();
		$hold_for_post_images = !$pre_image_incomplete && $context->has_content_images();
		$initial_post_status  = ($pre_image_incomplete || $hold_for_post_images) ? 'draft' : $intended_post_status;

		// Generation-time affiliate link injection (when enabled on the template/author).
		if ($context->get_affiliate_links_enabled() && !empty($content)) {
			$raw_tags = $context->get_post_tags();
			if (!empty($raw_tags)) {
				$tag_names = array_filter(array_map('trim', explode(',', $raw_tags)));
				if (!empty($tag_names)) {
					$content = (new AIPS_Affiliate_Links_Service())->inject_into_content($content, $tag_names);
				}
			}
		}

		// Stage 3: Persistence.
		$post_creation_data = array(
			'title'                 => $title,
			'slug'                  => $slug,
			'content'               => $content,
			'excerpt'               => $excerpt,
			'context'               => $context,
			'post_status'           => $initial_post_status,
			'focus_keyword'         => $context->get_topic() ? $context->get_topic() : $title,
			'meta_description'      => $excerpt,
			'seo_title'             => $title,
			'generation_incomplete' => $generation_incomplete,
			'component_statuses'    => $component_statuses,
		);

		do_action('aips_post_generation_before_post_create', $post_creation_data);

		$post_id = $this->post_manager->create_post($post_creation_data);

		if (is_wp_error($post_id)) {
			$this->last_error = $post_id;
			$history->complete_failure($post_id->get_error_message(), array(
				'component'      => 'post_creation',
				'title'          => $title,
				'content_length' => strlen($content),
			));

			$history->record(
				'metric_generation_result',
				'Generation failed — post could not be created',
				array(
					'outcome'          => 'failed',
					'duration_seconds' => (int) round(microtime(true) - $generation_start),
					'image_attempted'  => false,
					'image_success'    => null,
				)
			);

			$this->generator->close_conversation_session();

			return AIPS_Generation_Result::from_wp_error(
				$post_id,
				microtime(true) - $generation_start,
				(int) $history->get_id()
			);
		}

		// Stage 4 & 5: Custom Meta & Media components.
		$source_snapshots = $this->generator->get_current_source_snapshots();
		$meta_component   = $this->registry->get('post_meta', $this->get_key());
		if (!empty($source_snapshots)) {
			if ($meta_component instanceof AIPS_Post_Component_Interface) {
				$meta_component->apply_to_post((int) $post_id, array('aips_source_snapshots_used' => $source_snapshots));
			} else {
				update_post_meta($post_id, 'aips_source_snapshots_used', $source_snapshots);
			}
		}

		// Featured image component.
		$featured_image_success   = !$context->should_generate_featured_image();
		$featured_image_component = $this->registry->get('featured_image', $this->get_key());
		if ($featured_image_component instanceof AIPS_Post_Component_Interface && $featured_image_component->is_enabled($context)) {
			$fi_result = $featured_image_component->generate($context, null, array(
				'post_id'                  => $post_id,
				'title'                    => $title,
				'content'                  => $content,
				'precomputed_image_prompt' => $resolved_image_prompt,
			));
			$featured_image_success = is_array($fi_result) && !empty($fi_result['success']);
		} elseif ($context->should_generate_featured_image()) {
			$this->generator->resolve_and_attach_featured_image(
				$context,
				$post_id,
				$title,
				$featured_image_success,
				$content,
				$resolved_image_prompt
			);
		}
		$component_statuses['featured_image'] = (bool) $featured_image_success;

		// Content images component.
		if ($context->has_content_images()) {
			$content_images_component = $this->registry->get('post_images', $this->get_key());
			if ($content_images_component instanceof AIPS_Post_Component_Interface) {
				$post_images_result = $content_images_component->generate($context, null, array(
					'post_id'             => $post_id,
					'title'               => $title,
					'content'             => $content,
					'precomputed_prompts' => $resolved_content_image_prompts,
				));
			} else {
				$post_images_result = $this->generator->generate_and_inject_content_images(
					$context,
					$post_id,
					$title,
					$content,
					$resolved_content_image_prompts
				);
			}

			$content                           = $post_images_result['content'];
			$component_statuses['post_images'] = (bool) $post_images_result['success'];
			wp_update_post(array(
				'ID'           => $post_id,
				'post_content' => wp_slash($content),
			));
		}

		// Stage 6: Finalization (status reconciliation, draft downgrade/release, history, metrics, hooks, transcript persistence).
		$required_final_statuses = $component_statuses;
		unset($required_final_statuses['post_slug']);
		$generation_incomplete = in_array(false, $required_final_statuses, true);
		$this->post_manager->update_generation_status_meta($post_id, $component_statuses, $generation_incomplete);

		if ($generation_incomplete && !$pre_image_incomplete && $initial_post_status !== 'draft') {
			$downgrade_result = $this->post_manager->force_post_status($post_id, 'draft');
			if (is_wp_error($downgrade_result)) {
				$this->generation_logger->log(
					'Failed to downgrade post status to draft after featured image failure: ' . $downgrade_result->get_error_message(),
					'error',
					array('post_id' => $post_id)
				);
			}
		}

		if ($hold_for_post_images && !$generation_incomplete && $intended_post_status !== 'draft') {
			$release_result = $this->post_manager->force_post_status($post_id, $intended_post_status);
			if (is_wp_error($release_result)) {
				$this->generation_logger->log(
					'Failed to apply intended post status after post image injection: ' . $release_result->get_error_message(),
					'error',
					array('post_id' => $post_id)
				);
			}
		}

		if ($generation_incomplete) {
			do_action('aips_post_generation_incomplete', $post_id, $component_statuses, $context, $history->get_id() ? $history->get_id() : 0);
		}

		$completion_data = array(
			'post_id'               => $post_id,
			'post_type'             => $context->get_post_type(),
			'generated_title'       => $title,
			'generated_content'     => $content,
			'generation_incomplete' => $generation_incomplete,
			'component_statuses'    => $component_statuses,
		);
		if ($generation_incomplete) {
			$history->complete_partial('', $completion_data);
		} else {
			$history->complete_success($completion_data);
		}

		if ($context instanceof AIPS_Template_Context) {
			$template = $context->get_template();
			if ($template && !empty($template->campaign_id) && class_exists('AIPS_Campaigns_Repository')) {
				$campaigns_repo = AIPS_Campaigns_Repository::instance();
				$campaigns_repo->flush_campaign_cache((int) $template->campaign_id);
			}
		}

		$elapsed             = microtime(true) - $generation_start;
		$image_was_attempted = $context->should_generate_featured_image();
		$history->record(
			'metric_generation_result',
			'Generation metric snapshot',
			array(
				'outcome'            => $generation_incomplete ? 'partial' : 'completed',
				'duration_seconds'   => (int) round($elapsed),
				'image_attempted'    => $image_was_attempted,
				'image_success'      => $image_was_attempted ? (bool) $featured_image_success : null,
				'word_count'         => str_word_count(wp_strip_all_tags((string) $content)),
				'char_count'         => mb_strlen((string) $content),
				'component_statuses' => $component_statuses,
			)
		);

		if ($generation_incomplete) {
			$history->record(
				'warning',
				sprintf('Post "%s" generated with missing components', $title),
				null,
				null,
				array(
					'post_id'            => $post_id,
					'context_type'       => $context->get_type(),
					'context_id'         => $context->get_id(),
					'component_statuses' => $component_statuses,
				)
			);

			$this->logger->log('Post generated with missing components', 'warning', array(
				'post_id'            => $post_id,
				'context_type'       => $context->get_type(),
				'context_id'         => $context->get_id(),
				'title'              => $title,
				'component_statuses' => $component_statuses,
			));
		} else {
			$history->record(
				'activity',
				sprintf('Post "%s" generated successfully', $title),
				null,
				null,
				array(
					'post_id'      => $post_id,
					'context_type' => $context->get_type(),
					'context_id'   => $context->get_id(),
				)
			);

			$this->logger->log('Post generated successfully', 'info', array(
				'post_id'      => $post_id,
				'context_type' => $context->get_type(),
				'context_id'   => $context->get_id(),
				'title'        => $title,
			));
		}

		if ($context instanceof AIPS_Template_Context) {
			$template_obj = $context->get_template();
			do_action('aips_post_generated', $post_id, $template_obj, $history->get_id(), $context);
		} else {
			do_action('aips_post_generated', $post_id, $context, $history->get_id(), $context);
		}

		$conversation = $this->generator->get_active_conversation();
		if ($conversation !== null && !$conversation->is_empty()) {
			$history->record(
				'conversation_transcript',
				'Conversation transcript for post generation',
				null,
				array(
					'exchanges_count'  => $conversation->count_exchanges(),
					'estimated_tokens' => $conversation->estimated_tokens(),
					'transcript'       => $conversation->to_array(),
				),
				array(
					'component' => 'conversation',
					'post_id'   => $post_id,
				)
			);
		}

		$this->generator->close_conversation_session();

		if ($generation_incomplete) {
			return AIPS_Generation_Result::partial(
				(int) $post_id,
				array(),
				$component_statuses,
				$elapsed,
				(int) $history->get_id()
			);
		}

		return AIPS_Generation_Result::success(
			(int) $post_id,
			$component_statuses,
			$elapsed,
			(int) $history->get_id()
		);
	}

	/**
	 * Execute a preview run without creating a WordPress post.
	 *
	 * Maintains full PR #2401 / #2409 preview parity (conversational mode,
	 * combined metadata turn + deficit recovery, slug fallback, featured image
	 * prompt, and content image mock preview cards).
	 *
	 * @param AIPS_Generation_Context $context Generation context.
	 * @return array|WP_Error
	 */
	public function execute_preview_run(AIPS_Generation_Context $context) {
		$this->generator->open_conversation_session();

		$content = $this->generator->run_preview_content_stage($context);
		if (is_wp_error($content)) {
			$this->generator->close_conversation_session();
			return $content;
		}

		$resolved_image_prompt          = null;
		$resolved_content_image_prompts = array();
		$slug                           = '';

		if ($this->generator->is_metadata_turn_active()) {
			$metadata_result                = $this->generator->run_metadata_stage($context, $content);
			$title                          = $metadata_result['title'];
			$excerpt                        = $metadata_result['excerpt'];
			$slug                           = isset($metadata_result['slug']) ? $metadata_result['slug'] : '';
			$resolved_image_prompt          = $metadata_result['resolved_image_prompt'];
			$resolved_content_image_prompts = isset($metadata_result['resolved_content_image_prompts'])
				? (array) $metadata_result['resolved_content_image_prompts']
				: array();
		} else {
			$metadata_preview = $this->generator->run_preview_standalone_metadata($context, $content);
			$title            = $metadata_preview['title'];
			$excerpt          = $metadata_preview['excerpt'];
			$slug             = $metadata_preview['slug'];
		}

		$result = array(
			'title'        => $title,
			'slug'         => $slug,
			'content'      => $content,
			'excerpt'      => $excerpt,
			'image_prompt' => '',
			'image_source' => $context->get_featured_image_source(),
			'post_images'  => array(),
		);

		if ($context->should_generate_featured_image()) {
			if ($context->get_featured_image_source() === 'ai_prompt') {
				$result['image_prompt'] = ($resolved_image_prompt !== null && $resolved_image_prompt !== '')
					? $resolved_image_prompt
					: $this->generator->process_featured_image_prompt($context, $content, $title);
			} elseif ($context->get_featured_image_source() === 'unsplash') {
				$keywords               = $context->get_unsplash_keywords();
				$topic_str              = $context->get_topic();
				$result['image_prompt'] = $this->template_processor->process($keywords, $topic_str);
			}
		}

		if ($context->has_content_images()) {
			$content_images = AIPS_Post_Images_Service::limited_content_images($context->get_content_images());
			$total_images   = count($content_images);
			foreach ($content_images as $img) {
				$order     = (int) $img->placeholder_index;
				$source    = !empty($img->source) ? $img->source : 'ai_prompt';
				$placement = !empty($img->placement) ? $img->placement : 'auto';
				$prompt    = '';
				if ($source === 'ai_prompt' && isset($resolved_content_image_prompts[$order]) && '' !== trim((string) $resolved_content_image_prompts[$order])) {
					$prompt = trim((string) $resolved_content_image_prompts[$order]);
				} elseif (!empty($img->image_prompt)) {
					$prompt = $this->template_processor->process($img->image_prompt, $context->get_topic());
				}

				$result['post_images'][] = array(
					'image_index'  => $order,
					'source'       => $source,
					'placement'    => $placement,
					'image_prompt' => $prompt,
				);

				$preview_markup = sprintf(
					'<figure class="wp-block-image size-large aips-preview-image" data-preview-index="%d"><div style="border: 2px dashed #999; padding: 16px; text-align: center; background: #f9f9f9;"><strong>%s %d (%s)</strong><br/><small>%s</small></div></figure>',
					$order,
					esc_html__('Content Image', 'ai-post-scheduler'),
					$order,
					esc_html($source),
					esc_html($prompt ? $prompt : $title)
				);
				$content = $this->generator->inject_single_post_image($content, $order, $placement, $preview_markup, $total_images);
			}
			$content           = AIPS_Post_Images_Service::strip_unresolved_placeholders($content);
			$result['content'] = $content;
		}

		$this->generator->close_conversation_session();

		return $result;
	}

	/**
	 * Record trigger source and method in the history container.
	 *
	 * @param AIPS_History_Container|object $history History container (or a compatible double with record()).
	 * @param AIPS_Generation_Context       $context Generation context.
	 * @return void
	 */
	private function record_generation_trigger($history, AIPS_Generation_Context $context): void {
		$trigger = AIPS_Generation_Trigger::describe($context);
		$method  = (string) $context->get_creation_method();

		AIPS_Generation_Trigger::record(
			$history,
			$trigger['source'],
			$method !== '' ? $method : AIPS_Generation_Trigger::METHOD_MANUAL
		);
	}
}
