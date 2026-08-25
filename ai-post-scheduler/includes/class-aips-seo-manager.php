<?php
/**
 * SEO Manager
 *
 * Orchestrates SEO metadata generation, Schema.org JSON-LD generation,
 * Media SEO optimization, and provider write-through. Handles automatic SEO
 * generation for newly created posts as well as on-demand generation and
 * multi-provider synchronization for existing posts.
 * Respects global/template killswitches, pattern overrides, and selective field SEO Profiles.
 * Always maintains canonical internal backup in _aips_seo_data.
 *
 * @package AI_Post_Scheduler
 * @since 2.10.0
 */

if (!defined('ABSPATH')) {
	exit;
}

class AIPS_SEO_Manager {

	/**
	 * @var AIPS_AI_Service_Interface
	 */
	private $ai_service;

	/**
	 * @var AIPS_Prompt_Builder_SEO
	 */
	private $prompt_builder;

	/**
	 * @var AIPS_SEO_Provider_Native
	 */
	private $native_provider;

	/**
	 * @var AIPS_SEO_Profiles_Repository
	 */
	private $profiles_repo;

	/**
	 * @var AIPS_SEO_Schema_Generator
	 */
	private $schema_generator;

	/**
	 * @var AIPS_SEO_Media_Manager
	 */
	private $media_manager;

	/**
	 * @var AIPS_Logger
	 */
	private $logger;

	/**
	 * @param AIPS_AI_Service_Interface|null    $ai_service       Optional AI service.
	 * @param AIPS_Prompt_Builder_SEO|null      $prompt_builder   Optional SEO prompt builder.
	 * @param AIPS_SEO_Provider_Native|null     $native_provider  Optional native provider.
	 * @param AIPS_SEO_Profiles_Repository|null $profiles_repo    Optional profiles repository.
	 * @param AIPS_SEO_Schema_Generator|null    $schema_generator Optional schema generator.
	 * @param AIPS_SEO_Media_Manager|null       $media_manager    Optional media manager.
	 * @param AIPS_Logger|null                  $logger           Optional logger.
	 */
	public function __construct($ai_service = null, $prompt_builder = null, $native_provider = null, $profiles_repo = null, $schema_generator = null, $media_manager = null, $logger = null) {
		$container = AIPS_Container::get_instance();

		$this->ai_service       = $ai_service ?: ($container->has(AIPS_AI_Service_Interface::class) ? $container->make(AIPS_AI_Service_Interface::class) : new AIPS_AI_Service());
		$this->prompt_builder   = $prompt_builder ?: new AIPS_Prompt_Builder_SEO();
		$this->native_provider  = $native_provider ?: new AIPS_SEO_Provider_Native();
		$this->profiles_repo    = $profiles_repo ?: AIPS_SEO_Profiles_Repository::instance();
		$this->schema_generator = $schema_generator ?: new AIPS_SEO_Schema_Generator();
		$this->media_manager    = $media_manager ?: new AIPS_SEO_Media_Manager($this->ai_service, $logger);
		$this->logger           = $logger ?: new AIPS_Logger();
	}

	/**
	 * Handle post generation event (hook: 'aips_post_generated').
	 *
	 * Generates and applies SEO metadata for the post when SEO generation
	 * is enabled globally and on the driving template.
	 *
	 * @param int                     $post_id             Generated post ID.
	 * @param object|null             $template_or_context Template object or context.
	 * @param int|string|null         $history_id          History ID.
	 * @param AIPS_Generation_Context $context             Generation context.
	 * @return void
	 */
	public function handle_post_generated($post_id, $template_or_context, $history_id, $context) {
		$post_id = absint($post_id);

		if (!$post_id || !get_post($post_id)) {
			return;
		}

		// 1. Check Global SEO generation killswitch
		if (!AIPS_Config::get_instance()->get_option('aips_enable_seo_generation', true)) {
			return;
		}

		$options = array(
			'context' => $context,
		);

		// 2. Check Template-level SEO settings
		if ($context instanceof AIPS_Generation_Context && $context->get_type() === 'template') {
			$template_id = $context->get_id();
			if ($template_id) {
				$template = AIPS_Template_Repository::instance()->get_by_id($template_id);
				if ($template) {
					// Check if template disabled SEO generation
					if (isset($template->generate_seo) && (int) $template->generate_seo === 0) {
						return;
					}
					// Check if template specifies an SEO profile
					if (!empty($template->seo_profile_id)) {
						$options['profile_id'] = absint($template->seo_profile_id);
					}
				}
			}
		}

		$this->generate_for_post($post_id, $options);
	}

	/**
	 * Generate and persist SEO metadata for any post (new or existing).
	 *
	 * @param int   $post_id Target WordPress post ID.
	 * @param array $options Options {
	 *     @type AIPS_Generation_Context $context             Optional generation context.
	 *     @type int                     $profile_id          Optional SEO Profile ID for field selection & rules.
	 *     @type string                  $provider_id         Specific provider ID to write to (default: active provider).
	 *     @type array                   $selected_fields     Specific field keys to generate (overrides profile).
	 *     @type array                   $field_prompts       Specific field prompt overrides.
	 *     @type string                  $custom_instructions Optional custom SEO prompt instructions.
	 *     @type bool                    $optimize_media      Whether to optimize post images.
	 * }
	 * @return array {
	 *     @type bool        $success   Whether generation and write succeeded.
	 *     @type array|null  $seo_data  Generated canonical SEO metadata.
	 *     @type string      $provider  Identifier of the provider written to.
	 *     @type string|null $error     Error message if failed.
	 * }
	 */
	public function generate_for_post($post_id, array $options = array()) {
		$post_id = absint($post_id);
		$post = get_post($post_id);

		if (!$post) {
			return array(
				'success'  => false,
				'seo_data' => null,
				'provider' => '',
				'error'    => __('Post not found.', 'ai-post-scheduler'),
			);
		}

		$context = isset($options['context']) && ($options['context'] instanceof AIPS_Generation_Context) ? $options['context'] : null;
		$profile_id = !empty($options['profile_id']) ? absint($options['profile_id']) : (int) AIPS_Config::get_instance()->get_option('aips_default_seo_profile_id', 0);
		$profile = $profile_id ? $this->profiles_repo->get_by_id($profile_id) : null;

		$selected_fields = isset($options['selected_fields']) ? (array) $options['selected_fields'] : (is_object($profile) && !empty($profile->fields) ? (array) $profile->fields : array());
		$field_prompts = isset($options['field_prompts']) ? (array) $options['field_prompts'] : (is_object($profile) && !empty($profile->field_prompts) ? (array) $profile->field_prompts : array());
		$field_modes = is_object($profile) && !empty($profile->field_modes) ? (array) $profile->field_modes : array();
		$field_patterns = is_object($profile) && !empty($profile->field_patterns) ? (array) $profile->field_patterns : array();
		$custom_instructions = isset($options['custom_instructions']) ? sanitize_text_field($options['custom_instructions']) : (is_object($profile) && !empty($profile->custom_instructions) ? $profile->custom_instructions : '');
		$provider_id = !empty($options['provider_id']) ? sanitize_key($options['provider_id']) : (is_object($profile) && !empty($profile->provider_id) && $profile->provider_id !== 'auto' ? $profile->provider_id : '');

		// Separate AI fields from Pattern / Static override fields
		$ai_fields = array();
		$static_field_results = array();

		foreach ($selected_fields as $field_key) {
			$mode = isset($field_modes[$field_key]) ? $field_modes[$field_key] : 'ai';

			if ($mode === 'pattern' && !empty($field_patterns[$field_key])) {
				$static_field_results[$field_key] = AIPS_SEO_Pattern_Engine::evaluate($field_patterns[$field_key], $post);
			} elseif ($mode === 'fixed' && isset($field_patterns[$field_key])) {
				$static_field_results[$field_key] = $field_patterns[$field_key];
			} else {
				$ai_fields[] = $field_key;
			}
		}

		$ai_results = array();

		// 1. Build SEO Prompt and call AI for requested AI fields (if any)
		if (!empty($ai_fields)) {
			if ($context instanceof AIPS_Generation_Context) {
				$prompt = $this->prompt_builder->build($context, $post->post_content, $post->post_title, $custom_instructions, $ai_fields, $field_prompts);
			} else {
				$prompt = $this->prompt_builder->build_for_post($post, $custom_instructions, $ai_fields, $field_prompts);
			}

			if (!is_wp_error($prompt)) {
				$response = $this->ai_service->generate_json($prompt);
				if (is_array($response)) {
					$ai_results = $response;
				}
			}
		}

		// 2. Normalize Canonical Data & Merge with Existing Backup & Pattern Overrides
		$existing_seo = $this->native_provider->read_post_seo($post_id) ?: array();
		$new_seo = $this->native_provider->sanitize_seo_data(array_merge($ai_results, $static_field_results));

		// Apply Title Prefix/Suffix and Meta Description Prefix/Suffix if configured
		if (is_object($profile)) {
			if (!empty($new_seo['seo_title']) && (!empty($profile->title_prefix) || !empty($profile->title_suffix))) {
				$new_seo['seo_title'] = AIPS_SEO_Pattern_Engine::wrap($new_seo['seo_title'], $profile->title_prefix ?: '', $profile->title_suffix ?: '', $post);
			}
			if (!empty($new_seo['meta_description']) && (!empty($profile->meta_desc_prefix) || !empty($profile->meta_desc_suffix))) {
				$new_seo['meta_description'] = AIPS_SEO_Pattern_Engine::wrap($new_seo['meta_description'], $profile->meta_desc_prefix ?: '', $profile->meta_desc_suffix ?: '', $post);
			}
		}

		// If specific fields were selected, only overwrite those specific fields
		if (!empty($selected_fields)) {
			$merged_seo = $existing_seo;
			foreach ($selected_fields as $field_key) {
				if (array_key_exists($field_key, $new_seo)) {
					$merged_seo[$field_key] = $new_seo[$field_key];
				}
			}
			$seo_data = $merged_seo;
		} else {
			$seo_data = array_merge($existing_seo, $new_seo);
		}

		// 3. Generate Schema.org JSON-LD Structured Data
		$schema_types = is_object($profile) && !empty($profile->schema_types) ? (array) $profile->schema_types : array('article', 'breadcrumbs', 'faq');
		$seo_data['schema'] = $this->schema_generator->generate_for_post($post, $schema_types, $seo_data);

		// Resolve target provider
		$provider = (!empty($provider_id) && AIPS_SEO_Registry::has($provider_id))
			? AIPS_SEO_Registry::get($provider_id)
			: AIPS_SEO_Registry::get_active_provider();

		if (!$provider instanceof AIPS_SEO_Provider_Interface) {
			$provider = $this->native_provider;
		}

		// 4. Save Canonical Backup internally
		$seo_data['provider_synced'] = $provider->get_id();
		$seo_data['sync_status']     = 'pending';
		$this->native_provider->write_post_seo($post_id, $seo_data);

		// 5. Write through to Target Provider (if non-native and available)
		$write_result = true;
		if ($provider->get_id() !== 'native' && $provider->is_available()) {
			$write_result = $provider->write_post_seo($post_id, $seo_data);
		}

		if (is_wp_error($write_result)) {
			$seo_data['sync_status'] = 'failed';
			$this->native_provider->write_post_seo($post_id, $seo_data);

			$this->logger->log(
				sprintf('AIPS_SEO_Manager: Write-through to %s failed for post %d: %s', $provider->get_label(), $post_id, $write_result->get_error_message()),
				'warning',
				array('post_id' => $post_id, 'provider' => $provider->get_id())
			);

			return array(
				'success'  => false,
				'seo_data' => $seo_data,
				'provider' => $provider->get_id(),
				'error'    => $write_result->get_error_message(),
			);
		}

		$seo_data['sync_status'] = 'success';
		$this->native_provider->write_post_seo($post_id, $seo_data);

		// 6. Optional: Optimize associated media attachments
		$optimize_media = isset($options['optimize_media']) ? (bool) $options['optimize_media'] : (is_object($profile) && !empty($profile->media_seo_enabled));
		if ($optimize_media) {
			$media_mode = is_object($profile) && !empty($profile->media_seo_mode) ? $profile->media_seo_mode : 'text';
			$this->media_manager->optimize_post_images($post_id, array('mode' => $media_mode));
		}

		/**
		 * Fires after SEO metadata is generated and written for a post.
		 *
		 * @param int    $post_id  Target post ID.
		 * @param array  $seo_data Canonical SEO data array.
		 * @param string $provider Provider identifier written to.
		 */
		do_action('aips_post_seo_generated', $post_id, $seo_data, $provider->get_id());

		return array(
			'success'  => true,
			'seo_data' => $seo_data,
			'provider' => $provider->get_id(),
			'error'    => null,
		);
	}

	/**
	 * Re-synchronize existing canonical SEO metadata to a specified SEO provider.
	 *
	 * @param int         $post_id     Target post ID.
	 * @param string|null $provider_id Target provider identifier (or null for active provider).
	 * @return true|WP_Error
	 */
	public function sync_to_provider($post_id, $provider_id = null) {
		$post_id = absint($post_id);

		if (!$post_id || !get_post($post_id)) {
			return new WP_Error('invalid_post', __('Invalid post ID.', 'ai-post-scheduler'));
		}

		$canonical_data = $this->native_provider->read_post_seo($post_id);

		if (empty($canonical_data)) {
			return new WP_Error('no_seo_data', __('No saved SEO metadata found for this post. Generate SEO first.', 'ai-post-scheduler'));
		}

		$provider_id = $provider_id ? sanitize_key($provider_id) : '';
		$provider = (!empty($provider_id) && AIPS_SEO_Registry::has($provider_id))
			? AIPS_SEO_Registry::get($provider_id)
			: AIPS_SEO_Registry::get_active_provider();

		if (!$provider instanceof AIPS_SEO_Provider_Interface) {
			return new WP_Error('invalid_provider', __('SEO provider could not be resolved.', 'ai-post-scheduler'));
		}

		if (!$provider->is_available()) {
			return new WP_Error('provider_unavailable', sprintf(
				/* translators: %s: provider name */
				__('SEO provider "%s" is not active on this site.', 'ai-post-scheduler'),
				$provider->get_label()
			));
		}

		$write_result = $provider->write_post_seo($post_id, $canonical_data);

		if (is_wp_error($write_result)) {
			return $write_result;
		}

		// Update canonical record status
		$canonical_data['provider_synced'] = $provider->get_id();
		$canonical_data['sync_status']     = 'success';
		$canonical_data['synced_at']        = AIPS_DateTime::now()->timestamp();
		$this->native_provider->write_post_seo($post_id, $canonical_data);

		/**
		 * Fires after existing SEO metadata is synced to an SEO provider.
		 *
		 * @param int    $post_id     Target post ID.
		 * @param string $provider_id Provider identifier.
		 * @param array  $data        Canonical SEO data.
		 */
		do_action('aips_post_seo_synced', $post_id, $provider->get_id(), $canonical_data);

		return true;
	}

	/**
	 * Get comprehensive SEO data for a post (canonical backup + live provider data).
	 *
	 * @param int $post_id Target post ID.
	 * @return array
	 */
	public function get_post_seo_status($post_id) {
		$post_id = absint($post_id);

		$canonical = $this->native_provider->read_post_seo($post_id);
		$active_provider = AIPS_SEO_Registry::get_active_provider();
		$provider_data = $active_provider ? $active_provider->read_post_seo($post_id) : null;

		return array(
			'canonical'       => $canonical,
			'provider_data'   => $provider_data,
			'active_provider' => $active_provider ? $active_provider->get_id() : 'native',
			'active_label'    => $active_provider ? $active_provider->get_label() : 'Native',
		);
	}
}
