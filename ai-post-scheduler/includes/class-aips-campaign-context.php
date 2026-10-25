<?php
/**
 * Campaign Generation Context
 *
 * Decorates an inner generation context (e.g. Template Context) with
 * campaign directives, strategy goals, and default attribute overrides.
 *
 * @package AI_Post_Scheduler
 * @since 3.8.0
 */

if (!defined('ABSPATH')) {
	exit;
}

class AIPS_Campaign_Context implements AIPS_Generation_Context {

	/**
	 * @var AIPS_Generation_Context
	 */
	private $inner_context;

	/**
	 * @var object
	 */
	private $campaign;

	/**
	 * @var array
	 */
	private $overrides;

	/**
	 * @var array<string,mixed>
	 */
	private $trigger_context = array();

	/**
	 * Constructor.
	 *
	 * @param AIPS_Generation_Context $inner_context Inner generation context.
	 * @param object                  $campaign      Campaign database record.
	 * @param array                   $overrides     Optional per-template overrides for this campaign.
	 */
	public function __construct(AIPS_Generation_Context $inner_context, $campaign, array $overrides = array()) {
		$this->inner_context = $inner_context;
		$this->campaign      = is_object($campaign) ? $campaign : (object) $campaign;
		$this->overrides     = $overrides;

		// Carry over trigger context from inner context if available
		if (method_exists($inner_context, 'get_trigger_context')) {
			$this->trigger_context = (array) $inner_context->get_trigger_context();
		}
		$this->trigger_context['campaign_id']   = (int) $this->campaign->id;
		$this->trigger_context['campaign_name'] = isset($this->campaign->name) ? (string) $this->campaign->name : '';
	}

	/**
	 * Get the inner decorated context.
	 *
	 * @return AIPS_Generation_Context
	 */
	public function get_inner_context(): AIPS_Generation_Context {
		return $this->inner_context;
	}

	/**
	 * Get the campaign object.
	 *
	 * @return object
	 */
	public function get_campaign() {
		return $this->campaign;
	}

	/**
	 * Get the campaign ID.
	 *
	 * @return int
	 */
	public function get_campaign_id(): int {
		return isset($this->campaign->id) ? (int) $this->campaign->id : 0;
	}

	/**
	 * Get the campaign name.
	 *
	 * @return string
	 */
	public function get_campaign_name(): string {
		return isset($this->campaign->name) ? (string) $this->campaign->name : '';
	}

	/**
	 * Get campaign content goal.
	 *
	 * @return string
	 */
	public function get_campaign_goal(): string {
		return isset($this->campaign->content_goal) ? (string) $this->campaign->content_goal : '';
	}

	/**
	 * Get campaign prompt directives.
	 *
	 * @return string
	 */
	public function get_campaign_directives(): string {
		return isset($this->campaign->prompt_directives) ? (string) $this->campaign->prompt_directives : '';
	}

	/**
	 * Get the context type identifier.
	 *
	 * @return string
	 */
	public function get_type() {
		return $this->inner_context->get_type();
	}

	/**
	 * Get the context identifier.
	 *
	 * @return int|string|null
	 */
	public function get_id() {
		return $this->inner_context->get_id();
	}

	/**
	 * Get the context name.
	 *
	 * @return string
	 */
	public function get_name() {
		return $this->inner_context->get_name();
	}

	/**
	 * Get the content prompt.
	 *
	 * @return string
	 */
	public function get_content_prompt() {
		return $this->inner_context->get_content_prompt();
	}

	/**
	 * Get the title prompt.
	 *
	 * @return string
	 */
	public function get_title_prompt() {
		return $this->inner_context->get_title_prompt();
	}

	/**
	 * Get the image prompt.
	 *
	 * @return string|null
	 */
	public function get_image_prompt() {
		return $this->inner_context->get_image_prompt();
	}

	/**
	 * Check if featured image generation is enabled.
	 *
	 * @return bool
	 */
	public function should_generate_featured_image() {
		return $this->inner_context->should_generate_featured_image();
	}

	/**
	 * Get featured image source.
	 *
	 * @return string
	 */
	public function get_featured_image_source() {
		return $this->inner_context->get_featured_image_source();
	}

	/**
	 * Get Unsplash keywords.
	 *
	 * @return string
	 */
	public function get_unsplash_keywords() {
		return $this->inner_context->get_unsplash_keywords();
	}

	/**
	 * Get media library IDs.
	 *
	 * @return string
	 */
	public function get_media_library_ids() {
		return $this->inner_context->get_media_library_ids();
	}

	/**
	 * Get effective post status.
	 * Precedence: Per-template override > Campaign default > Inner context.
	 *
	 * @return string
	 */
	public function get_post_status() {
		if (!empty($this->overrides['post_status'])) {
			return sanitize_key($this->overrides['post_status']);
		}

		if (!empty($this->campaign->default_post_status)) {
			return sanitize_key($this->campaign->default_post_status);
		}

		return $this->inner_context->get_post_status();
	}

	/**
	 * Get post type.
	 *
	 * @return string
	 */
	public function get_post_type() {
		if (!empty($this->overrides['post_type'])) {
			return sanitize_key($this->overrides['post_type']);
		}

		return $this->inner_context->get_post_type();
	}

	/**
	 * Get effective post category.
	 * Precedence: Per-template override > Campaign default > Inner context.
	 *
	 * @return mixed
	 */
	public function get_post_category() {
		if (!empty($this->overrides['post_category'])) {
			return $this->overrides['post_category'];
		}

		if (!empty($this->campaign->default_categories)) {
			return $this->campaign->default_categories;
		}

		return $this->inner_context->get_post_category();
	}

	/**
	 * Get effective post tags.
	 * Precedence: Per-template override > Campaign default > Inner context.
	 *
	 * @return string
	 */
	public function get_post_tags() {
		if (!empty($this->overrides['post_tags'])) {
			return (string) $this->overrides['post_tags'];
		}

		if (!empty($this->campaign->default_tags)) {
			return (string) $this->campaign->default_tags;
		}

		return $this->inner_context->get_post_tags();
	}

	/**
	 * Get effective post author.
	 * Precedence: Per-template override > Campaign default > Inner context.
	 *
	 * @return int
	 */
	public function get_post_author() {
		if (!empty($this->overrides['post_author'])) {
			return absint($this->overrides['post_author']);
		}

		if (!empty($this->campaign->default_author_id)) {
			return absint($this->campaign->default_author_id);
		}

		return $this->inner_context->get_post_author();
	}

	/**
	 * Get effective article structure ID.
	 * Precedence: Per-template override > Campaign default structure > Inner context structure.
	 *
	 * @return int|null
	 */
	public function get_article_structure_id() {
		if (!empty($this->overrides['article_structure_id'])) {
			return absint($this->overrides['article_structure_id']);
		}

		if (!empty($this->campaign->default_article_structure_id)) {
			return absint($this->campaign->default_article_structure_id);
		}

		if (method_exists($this->inner_context, 'get_article_structure_id')) {
			$structure_id = $this->inner_context->get_article_structure_id();
			if (!empty($structure_id)) {
				return absint($structure_id);
			}
		}

		return null;
	}

	/**
	 * Get effective voice ID.
	 * Precedence: Per-template override > Campaign default > Inner context.
	 *
	 * @return int|null
	 */
	public function get_voice_id() {
		if (!empty($this->overrides['voice_id'])) {
			return absint($this->overrides['voice_id']);
		}

		if (!empty($this->campaign->default_voice_id)) {
			return absint($this->campaign->default_voice_id);
		}

		if (method_exists($this->inner_context, 'get_voice_id')) {
			return $this->inner_context->get_voice_id();
		}

		return null;
	}

	/**
	 * Get voice object.
	 *
	 * @return object|null
	 */
	public function get_voice() {
		$voice_id = $this->get_voice_id();
		if ($voice_id) {
			$voices_repo = new AIPS_Voices_Repository();
			return $voices_repo->get_by_id($voice_id);
		}

		if (method_exists($this->inner_context, 'get_voice')) {
			return $this->inner_context->get_voice();
		}

		return null;
	}

	/**
	 * Get topic.
	 *
	 * @return string|null
	 */
	public function get_topic() {
		if (method_exists($this->inner_context, 'get_topic')) {
			return $this->inner_context->get_topic();
		}

		return null;
	}

	/**
	 * Get creation method.
	 *
	 * @return string|null
	 */
	public function get_creation_method() {
		if (method_exists($this->inner_context, 'get_creation_method')) {
			return $this->inner_context->get_creation_method();
		}

		return null;
	}

	/**
	 * Get trigger context.
	 *
	 * @return array<string,mixed>
	 */
	public function get_trigger_context() {
		return $this->trigger_context;
	}

	/**
	 * Set trigger context.
	 *
	 * @param array<string,mixed> $context Trigger context.
	 * @return void
	 */
	public function set_trigger_context(array $context): void {
		$this->trigger_context = array_merge($this->trigger_context, $context);
		if (method_exists($this->inner_context, 'set_trigger_context')) {
			$this->inner_context->set_trigger_context($this->trigger_context);
		}
	}

	/**
	 * Convert context to array.
	 *
	 * @return array
	 */
	public function to_array() {
		$data = method_exists($this->inner_context, 'to_array') ? $this->inner_context->to_array() : array();
		$data['campaign_id']         = $this->get_campaign_id();
		$data['campaign_name']       = $this->get_campaign_name();
		$data['campaign_goal']       = $this->get_campaign_goal();
		$data['campaign_directives'] = $this->get_campaign_directives();
		$data['post_status']         = $this->get_post_status();
		$data['post_category']       = $this->get_post_category();
		$data['post_tags']           = $this->get_post_tags();
		$data['post_author']         = $this->get_post_author();
		$data['voice_id']            = $this->get_voice_id();
		$data['article_structure_id'] = $this->get_article_structure_id();

		return $data;
	}
}
