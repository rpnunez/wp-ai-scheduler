<?php
/**
 * Component Regeneration Service
 *
 * Service class responsible for regenerating individual post components
 * (title, slug, excerpt, content, featured image, post images) using the
 * original generation context. Delegates all component regeneration through
 * AIPS_Generation_Component_Registry and AIPS_Component_Regeneration_Coordinator
 * so initial generation and regeneration share a single source of truth.
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
	 * @var AIPS_Component_Regeneration_Coordinator|null Cached regeneration coordinator
	 */
	private $coordinator = null;

	/**
	 * @var string|null Composite object-ID signature of dependencies bound to $this->coordinator
	 */
	private $coordinator_deps_key = null;

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->history_repository         = new AIPS_History_Repository();
		$this->generation_context_factory = new AIPS_Generation_Context_Factory();
		$this->template_processor         = new AIPS_Template_Processor();
		$this->structure_manager          = new AIPS_Article_Structure_Manager();

		// Initialize AI services
		$ai_service                               = new AIPS_AI_Service();
		$this->generator                          = new AIPS_Generator(null, $ai_service);
		$this->image_service                      = new AIPS_Image_Service($ai_service);
		$this->prompt_builder                     = new AIPS_Prompt_Builder($this->template_processor, $this->structure_manager);
		$this->post_content_prompt_builder        = new AIPS_Prompt_Builder_Post_Content(
			$this->template_processor,
			new AIPS_Prompt_Builder_Article_Structure_Section($this->structure_manager, null, $this->template_processor)
		);
		$this->post_title_prompt_builder          = $this->prompt_builder->get_post_title_builder();
		$this->post_featured_image_prompt_builder = new AIPS_Prompt_Builder_Post_Featured_Image($this->template_processor);
	}

	/**
	 * Resolve the regeneration coordinator, rebuilding if dependencies were
	 * swapped (e.g. via reflection in unit tests).
	 *
	 * @return AIPS_Component_Regeneration_Coordinator
	 */
	public function get_coordinator(): AIPS_Component_Regeneration_Coordinator {
		$deps_key = implode(':', array(
			is_object($this->generator) ? (string) spl_object_id($this->generator) : '0',
			is_object($this->image_service) ? (string) spl_object_id($this->image_service) : '0',
			is_object($this->history_repository) ? (string) spl_object_id($this->history_repository) : '0',
		));

		if ($this->coordinator === null || $this->coordinator_deps_key !== $deps_key) {
			$registry = new AIPS_Generation_Component_Registry();
			$registry->register(new AIPS_Content_Post_Component($this->generator, $this->post_content_prompt_builder, $this->prompt_builder));
			$registry->register(new AIPS_Title_Post_Component($this->generator, $this->post_title_prompt_builder));
			$registry->register(new AIPS_Excerpt_Post_Component($this->generator));
			$registry->register(new AIPS_Slug_Post_Component($this->generator));
			$registry->register(new AIPS_Featured_Image_Post_Component($this->generator, $this->image_service));
			$registry->register(new AIPS_Content_Images_Post_Component($this->generator, $this->history_repository));
			$registry->register(new AIPS_Taxonomy_Post_Component($this->generator));
			$registry->register(new AIPS_Custom_Meta_Post_Component($this->generator));
			$registry->register(new AIPS_Internal_Links_Post_Component($this->generator));
			$registry->register(new AIPS_Affiliate_Links_Post_Component($this->generator));
			$registry->register(new AIPS_Integration_Fields_Post_Component($this->generator));
			$registry->register_default_topic_components();
			$registry->register_default_persona_components();
			$registry->register_default_campaign_components();
			$registry->register_default_research_components();
			$registry->register_default_taxonomy_components();
			$registry->register_default_planner_components();
			$registry->register_default_enrichment_components();
			$registry->register_default_ai_assistance_components();

			$this->coordinator = new AIPS_Component_Regeneration_Coordinator(
				$registry,
				$this->generator,
				$this->history_repository,
				$this->post_content_prompt_builder,
				$this->post_title_prompt_builder
			);
			$this->coordinator_deps_key = $deps_key;
		}

		return $this->coordinator;
	}

	/**
	 * Rebuild or rehydrate the original generation conversation for a post.
	 *
	 * Delegates to AIPS_Component_Regeneration_Coordinator::build_conversation().
	 *
	 * @param AIPS_Generation_Context $generation_context Original generation context.
	 * @param int                     $post_id            Post being regenerated.
	 * @param bool                    $include_title      Whether to append the title exchange.
	 * @param string|null             $body_override      Article body to replay.
	 * @param string|null             $title_override     Title to replay.
	 * @return AIPS_AI_Conversation|null Transcript, or null when unavailable.
	 */
	private function build_conversation($generation_context, $post_id, $include_title = false, $body_override = null, $title_override = null) {
		return $this->get_coordinator()->build_conversation(
			$generation_context,
			absint($post_id),
			(bool) $include_title,
			is_string($body_override) ? $body_override : null,
			is_string($title_override) ? $title_override : null
		);
	}

	/**
	 * Get the generation context for a history record.
	 *
	 * @param int $history_id History record ID
	 * @return array|WP_Error Context array with 'generation_context' key containing AIPS_Generation_Context object, or error on failure
	 */
	public function get_generation_context($history_id) {
		return $this->generation_context_factory->create_from_history_id($history_id);
	}

	/**
	 * Regenerate post title via the registered post_title component.
	 *
	 * @param array $context Generation context with 'generation_context' key
	 * @return string|WP_Error Generated title or error
	 */
	public function regenerate_title($context) {
		return $this->delegate_component_regeneration('post_title', __('Title', 'ai-post-scheduler'), $context);
	}

	/**
	 * Regenerate post slug via the registered post_slug component.
	 *
	 * @param array $context Generation context with 'generation_context' key
	 * @return string|WP_Error Generated slug or error
	 */
	public function regenerate_slug($context) {
		return $this->delegate_component_regeneration('post_slug', __('Slug', 'ai-post-scheduler'), $context);
	}

	/**
	 * Regenerate post excerpt via the registered post_excerpt component.
	 *
	 * @param array $context Generation context with 'generation_context' key
	 * @return string|WP_Error Generated excerpt or error
	 */
	public function regenerate_excerpt($context) {
		return $this->delegate_component_regeneration('post_excerpt', __('Excerpt', 'ai-post-scheduler'), $context);
	}

	/**
	 * Regenerate post content via the registered post_content component.
	 *
	 * @param array $context Generation context with 'generation_context' key
	 * @return string|WP_Error Generated content or error
	 */
	public function regenerate_content($context) {
		return $this->delegate_component_regeneration('post_content', __('Content', 'ai-post-scheduler'), $context);
	}

	/**
	 * Regenerate featured image via the registered featured_image component.
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

		return $this->delegate_component_regeneration('featured_image', __('Featured image', 'ai-post-scheduler'), $context);
	}

	/**
	 * Regenerate one or all post content images via the registered post_images component.
	 *
	 * @param array      $context  Generation context array with post_id, history_id, generation_context.
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

		$context['image_id'] = $image_id;
		return $this->delegate_component_regeneration('post_images', __('Post images', 'ai-post-scheduler'), $context);
	}

	/**
	 * Unified single-component dispatch entry point for AI Edit controllers.
	 *
	 * Routes any supported post component key (including short aliases and
	 * per-image keys `post_image_{id}`) through the component registry and
	 * coordinator without requiring a switch statement in controllers.
	 *
	 * @param string $component Component key or alias.
	 * @param array  $context   Regeneration context payload.
	 * @return mixed Regenerated component value or WP_Error.
	 */
	public function regenerate_component(string $component, array $context) {
		$component = sanitize_key($component);

		$image_id = self::post_image_id_from_component($component);
		if ('post_images' === $component || $image_id > 0) {
			return $this->regenerate_post_images($context, $image_id > 0 ? $image_id : 'all');
		}

		$canonical = AIPS_Generation_Component_Registry::normalize_key($component, 'posts');
		$method_map = array(
			'post_title'     => 'regenerate_title',
			'post_slug'      => 'regenerate_slug',
			'post_excerpt'   => 'regenerate_excerpt',
			'post_content'   => 'regenerate_content',
			'featured_image' => 'regenerate_featured_image',
		);

		if (isset($method_map[$canonical])) {
			$method = $method_map[$canonical];
			return $this->$method($context);
		}

		return $this->delegate_component_regeneration($canonical, ucwords(str_replace('_', ' ', $canonical)), $context);
	}

	/**
	 * Resolve and persist sticky per-component model and temperature overrides
	 * for a post's AI Edit session.
	 *
	 * @param int        $post_id            Post ID.
	 * @param string     $component          Component key.
	 * @param string     $model_override     Posted model override string.
	 * @param float|null $temp_override      Posted temperature override float or null.
	 * @param bool       $has_model_post     Whether `model_override` was present in the request.
	 * @param bool       $has_temp_post      Whether a non-empty `temperature_override` was present in the request.
	 * @return array{model:string,temperature:float|null,overrides:array}
	 */
	public function resolve_and_persist_component_overrides(
		int $post_id,
		string $component,
		string $model_override = '',
		?float $temp_override = null,
		bool $has_model_post = false,
		bool $has_temp_post = false
	): array {
		$existing_overrides = get_post_meta($post_id, AIPS_Post_Manager::META_AI_EDIT_OVERRIDES, true);
		if (!is_array($existing_overrides)) {
			$existing_overrides = array();
		}

		$comp_override = isset($existing_overrides[$component]) && is_array($existing_overrides[$component])
			? $existing_overrides[$component]
			: array();

		$updated_overrides = false;
		if ($has_model_post) {
			if ($model_override !== '') {
				$comp_override['model'] = $model_override;
			} else {
				unset($comp_override['model']);
			}
			$updated_overrides = true;
		} elseif (!empty($comp_override['model'])) {
			$model_override = (string) $comp_override['model'];
		}

		if ($has_temp_post) {
			if ($temp_override !== null) {
				$comp_override['temperature'] = max(0.0, min(1.0, $temp_override));
			} else {
				unset($comp_override['temperature']);
			}
			$updated_overrides = true;
		} elseif (isset($comp_override['temperature'])) {
			$temp_override = floatval($comp_override['temperature']);
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

		return array(
			'model'       => $model_override,
			'temperature' => $temp_override,
			'overrides'   => $existing_overrides,
		);
	}

	/**
	 * Shared delegation helper routing single-component regeneration through the
	 * AIPS_Component_Regeneration_Coordinator and component registry.
	 *
	 * @param string $component_key Canonical post component key.
	 * @param string $trigger_label Human-readable label for history trigger recording.
	 * @param array  $context       Regeneration context payload.
	 * @return mixed Component result or WP_Error.
	 */
	private function delegate_component_regeneration(string $component_key, string $trigger_label, $context) {
		if (!is_array($context) || !isset($context['generation_context']) || !($context['generation_context'] instanceof AIPS_Generation_Context)) {
			return new WP_Error('missing_context', __('Generation context is required.', 'ai-post-scheduler'));
		}

		$generation_context = $context['generation_context'];
		$post_id            = isset($context['post_id']) ? absint($context['post_id']) : 0;
		$history_id         = isset($context['history_id']) ? absint($context['history_id']) : 0;

		$history_container = $this->resolve_history_container($context, $post_id, $history_id);
		if (is_wp_error($history_container)) {
			return $history_container;
		}
		$this->record_regeneration_trigger($history_container, $context, $trigger_label, $post_id);

		$directive = $this->build_directive_instruction($context);
		$options   = array(
			'history_id'            => $history_id,
			'history_container'     => $history_container,
			'ai_options'            => $this->extract_ai_options($context),
			'preserve_placeholders' => !empty($context['preserve_placeholders']),
		);

		if (isset($context['current_title'])) {
			$options['current_title'] = (string) $context['current_title'];
		}
		if (isset($context['current_content'])) {
			$options['current_content'] = (string) $context['current_content'];
		}
		if (isset($context['image_id'])) {
			$options['image_id'] = $context['image_id'];
		}

		return $this->get_coordinator()->regenerate_raw(
			'posts',
			$component_key,
			$post_id,
			$generation_context,
			$directive,
			$options
		);
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
		$post_id    = absint($post_id);
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

		$component_statuses        = json_decode((string) $this->get_post_meta_value($post_id, AIPS_Post_Manager::META_GENERATION_COMPONENT_STATUSES), true);
		$has_featured_image_status = is_array($component_statuses)
			&& array_key_exists('featured_image', $component_statuses);
		$featured_image_status     = $has_featured_image_status ? $component_statuses['featured_image'] : null;

		if ($thumbnail_id > 0 && !empty($featured_image_status)) {
			return true;
		}

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
		$post_id            = isset($context['post_id']) ? absint($context['post_id']) : 0;
		$history_id         = isset($context['history_id']) ? absint($context['history_id']) : 0;

		if (!$post_id || !$history_id) {
			return new WP_Error('invalid_request', __('Post and history identifiers are required.', 'ai-post-scheduler'));
		}

		$result = array(
			'regenerated' => array(),
			'skipped'     => array(),
			'errors'      => array(),
		);

		// Record the trigger once for the whole run rather than once per component.
		$trigger_container = AIPS_History_Container::resolve_existing($this->history_repository, $post_id, $history_id);
		if (!is_wp_error($trigger_container)) {
			$this->record_regeneration_trigger($trigger_container, $context, __('All components', 'ai-post-scheduler'), $post_id);
			$context['trigger_recorded']  = true;
			$context['history_container'] = $trigger_container;
		}

		$title = $this->regenerate_title($context);
		if (is_wp_error($title)) {
			$result['errors']['title'] = $title->get_error_message();
		} else {
			$result['regenerated']['title'] = $title;
			$context['current_title']       = $title;
		}

		// Preserve model-placed image placeholders in content if post images will be regenerated.
		$preserve_placeholders = $this->should_regenerate_post_images($post_id, $context['generation_context']);
		if ($preserve_placeholders) {
			$context['preserve_placeholders'] = true;
		}

		$content = $this->regenerate_content($context);
		if (is_wp_error($content)) {
			$result['errors']['content'] = $content->get_error_message();
		} else {
			$result['regenerated']['content'] = $content;
			$context['current_content']       = $content;
		}

		if ($generation_context->should_generate_slug()) {
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

		// Clean up any remaining unresolved placeholders from the staged result in memory.
		if ($preserve_placeholders && isset($result['regenerated']['content'])) {
			$result['regenerated']['content'] = AIPS_Post_Images_Service::strip_unresolved_placeholders($result['regenerated']['content']);
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
	 * @param AIPS_History_Container $history_container Existing container.
	 * @param array                  $context           Regeneration context.
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
	 * Capture the current component value as a revision snapshot.
	 *
	 * @param int    $post_id    Post ID.
	 * @param int    $history_id History ID.
	 * @param string $component  Component type.
	 * @param mixed  $value      Current component value.
	 * @param string $source     Revision source marker.
	 * @param string $reason     Snapshot reason marker.
	 * @return bool|WP_Error True on success, or WP_Error on failure.
	 */
	public function capture_component_revision($post_id, $history_id, $component, $value, $source = 'manual_edit', $reason = 'manual_edit') {
		$post_id    = absint($post_id);
		$history_id = absint($history_id);
		$source     = sanitize_key($source);
		$reason     = sanitize_key($reason);

		if (!self::is_ai_edit_component($component) || 'post_images' === $component) {
			return new WP_Error('invalid_component', __('Invalid component type.', 'ai-post-scheduler'));
		}

		$history_container = AIPS_History_Container::resolve_existing($this->history_repository, $post_id, $history_id);
		if (is_wp_error($history_container)) {
			return $history_container;
		}

		$context = array(
			'component' => $component,
			'post_id'   => $post_id,
			'source'    => $source ? $source : 'manual_edit',
			'reason'    => $reason,
		);

		if ('manual_edit' === $context['source']) {
			$context['user_id']    = get_current_user_id();
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
	 * Build directive instruction from context parameters.
	 *
	 * @param array $context Context array.
	 * @return string Formatted directive instruction or empty string.
	 */
	public function build_directive_instruction(array $context): string {
		$instruction = '';
		$preset_key  = !empty($context['directive_preset']) ? sanitize_key($context['directive_preset']) : '';
		$custom      = !empty($context['custom_directive']) ? trim((string) $context['custom_directive']) : '';

		if ($preset_key) {
			$directive_service = new AIPS_Editor_Directive_Service();
			$presets           = $directive_service->get_standard_presets();
			if (isset($presets[$preset_key]['instruction'])) {
				$instruction = $presets[$preset_key]['instruction'];
			}
		}

		if ($custom !== '') {
			$instruction = $instruction ? $instruction . ' Additional instruction: ' . $custom : $custom;
		}

		return $instruction;
	}

	/**
	 * Extract AI execution options (model, temperature) from regeneration context.
	 *
	 * @param array $context Context array.
	 * @return array
	 */
	public function extract_ai_options(array $context): array {
		$options = array();

		if (!empty($context['model_override'])) {
			$options['model'] = sanitize_text_field($context['model_override']);
		}

		if (isset($context['temperature_override']) && is_numeric($context['temperature_override'])) {
			$options['temperature'] = max(0.0, min(1.0, floatval($context['temperature_override'])));
		}

		return $options;
	}

	/**
	 * Safely read post meta in runtime and limited test environments.
	 *
	 * @param int    $post_id  Post ID.
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
	 * Stage a restored post image into editor content without writing the post.
	 *
	 * @param int    $post_id    Post ID.
	 * @param int    $image_id   Generated image row ID.
	 * @param mixed  $value      Revision payload.
	 * @param string $content    Editor content to update.
	 * @param int    $history_id Optional history ID.
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
		$attachment    = get_post($attachment_id);
		if (!$attachment || 'attachment' !== $attachment->post_type || !wp_attachment_is_image($attachment_id)) {
			return new WP_Error('invalid_post_image_revision', __('Invalid post image revision data.', 'ai-post-scheduler'));
		}

		$url = wp_get_attachment_url($attachment_id);
		if (!$url) {
			return new WP_Error('invalid_post_image_revision', __('Invalid post image revision data.', 'ai-post-scheduler'));
		}

		$title           = get_the_title($post_id);
		$markup          = $this->generator->build_post_image_markup($image_id, $attachment_id, $url, $title, '');
		$siblings        = (new AIPS_Post_Images_Generated_Repository())->get_for_post_history($post_id, (int) $row->history_id);
		$images_comp     = $this->get_coordinator()->get_registry()->get('post_images', 'posts');
		$content_updater = ($images_comp instanceof AIPS_Content_Images_Post_Component)
			? $images_comp
			: new AIPS_Content_Images_Post_Component($this->generator, $this->history_repository);
		$content         = $content_updater->replace_post_image_markup($content, $row, $markup, max(1, count($siblings)));

		return array(
			'id'            => $image_id,
			'image_index'   => (int) $row->image_index,
			'attachment_id' => $attachment_id,
			'url'           => $url,
			'status'        => 'completed',
			'content'       => $content,
			'markup'        => $markup,
		);
	}
}
