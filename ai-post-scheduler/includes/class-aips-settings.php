<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class AIPS_Settings
 *
 * Handles the registration of admin menu pages, settings, and rendering of admin interfaces
 * for the AI Post Scheduler plugin.
 *
 * @package AI_Post_Scheduler
 */
class AIPS_Settings {
	/**
	 * @var AIPS_Settings_UI
	 */
	private $ui;

	/**
	 * @var AIPS_Settings_AJAX
	 */
	private $ajax;

	/**
	 * Initialize the settings class.
	 *
	 * Hooks into admin_init, and wp_ajax.
	 */
	public function __construct() {
		$this->ui = new AIPS_Settings_UI();
		$this->ajax = new AIPS_Settings_AJAX();

		add_action('admin_init', array($this, 'register_settings'));
	}

	/**
	 * Register runtime hooks required by settings updates.
	 *
	 * @return void
	 */
	public static function register_runtime_hooks() {
		static $hooks_registered = false;

		if ($hooks_registered) {
			return;
		}

		$hooks_registered = true;

		$reset_cache_flag = function() {
			AIPS_Cache::reset_system_enabled_flag();
		};

		add_action('update_option_aips_enable_cache_system', $reset_cache_flag);
		add_action('add_option_aips_enable_cache_system', $reset_cache_flag);

		// Turning AI generation back on queues a one-off sweep that resumes any
		// large batch the setting stopped part-way through. The sweep runs on
		// cron rather than inline so saving settings stays a cheap request.
		add_action(
			'update_option_aips_prevent_scheduled_ai_generation',
			array(__CLASS__, 'maybe_queue_batch_resume'),
			10,
			2
		);
	}

	/**
	 * Queue a batch-resume sweep when AI generation is re-enabled.
	 *
	 * @param mixed $old_value Previous option value.
	 * @param mixed $new_value New option value.
	 * @return void
	 */
	public static function maybe_queue_batch_resume($old_value, $new_value) {
		// Only on the on -> off transition. Saving settings without changing this
		// option, or switching it on, must not queue anything.
		if (!(bool) $old_value || (bool) $new_value) {
			return;
		}

		if (wp_next_scheduled('aips_resume_terminated_batches')) {
			return;
		}

		wp_schedule_single_event(AIPS_DateTime::now()->timestamp() + 60, 'aips_resume_terminated_batches');
	}

	/**
	 * Return the canonical registry of registered setting arguments.
	 *
	 * @param AIPS_Settings_UI|null $ui Settings UI helper for instance callbacks.
	 * @return array<string, array<string, mixed>>
	 */
	public static function get_registered_settings_args($ui = null) {
		$ui = $ui ?: new AIPS_Settings_UI();
		$defaults = AIPS_Config::get_instance()->get_default_options();

		$settings = array(
			// General Tab
			'aips_default_post_status' => array(
				'sanitize_callback' => 'sanitize_text_field',
				'default'           => $defaults['aips_default_post_status'],
			),
			'aips_default_category' => array(
				'sanitize_callback' => 'absint',
				'default'           => $defaults['aips_default_category'],
			),
			'aips_default_post_author' => array(
				'sanitize_callback' => 'absint',
				'default'           => $defaults['aips_default_post_author'],
			),
			'aips_default_post_format' => array(
				'sanitize_callback' => 'sanitize_key',
				'default'           => $defaults['aips_default_post_format'],
			),
			'aips_default_comment_status' => array(
				'sanitize_callback' => 'sanitize_key',
				'default'           => $defaults['aips_default_comment_status'],
			),
			'aips_default_ping_status' => array(
				'sanitize_callback' => 'sanitize_key',
				'default'           => $defaults['aips_default_ping_status'],
			),
			'aips_auto_generate_meta_description' => array(
				'sanitize_callback' => 'absint',
				'default'           => $defaults['aips_auto_generate_meta_description'],
			),
			'aips_auto_generate_tags' => array(
				'sanitize_callback' => 'absint',
				'default'           => $defaults['aips_auto_generate_tags'],
			),
			'aips_max_tags_count' => array(
				'sanitize_callback' => 'absint',
				'default'           => $defaults['aips_max_tags_count'],
			),
			// AI Tab
			'aips_ai_provider' => array(
				'sanitize_callback' => array($ui, 'sanitize_ai_provider'),
				'default'           => $defaults['aips_ai_provider'],
			),
			'aips_wp_ai_connector_mode' => array(
				'sanitize_callback' => array($ui, 'sanitize_wp_ai_connector_mode'),
				'default'           => $defaults['aips_wp_ai_connector_mode'],
			),
			'aips_wp_ai_connector_ids' => array(
				'sanitize_callback' => array($ui, 'sanitize_wp_ai_connector_ids'),
				'default'           => $defaults['aips_wp_ai_connector_ids'],
			),
			'aips_wp_ai_connector_failover' => array(
				'sanitize_callback' => 'absint',
				'default'           => $defaults['aips_wp_ai_connector_failover'],
			),
			'aips_ai_model' => array(
				'sanitize_callback' => 'sanitize_text_field',
				'default'           => $defaults['aips_ai_model'],
			),
			'aips_ai_env_id' => array(
				'sanitize_callback' => 'sanitize_text_field',
				'default'           => $defaults['aips_ai_env_id'],
			),
			'aips_temperature' => array(
				'sanitize_callback' => array($ui, 'sanitize_temperature'),
				'default'           => $defaults['aips_temperature'],
			),
			'aips_global_system_prompt' => array(
				'sanitize_callback' => 'sanitize_textarea_field',
				'default'           => $defaults['aips_global_system_prompt'],
      ),
			'aips_prevent_scheduled_ai_generation' => array(
				'sanitize_callback' => 'absint',
				'default'           => $defaults['aips_prevent_scheduled_ai_generation'],
			),
			'aips_max_tokens_limit' => array(
				'sanitize_callback' => 'absint',
				'default'           => $defaults['aips_max_tokens_limit'],
			),
			'aips_max_tokens_title' => array(
				'sanitize_callback' => array($ui, 'sanitize_token_budget'),
				'default'           => $defaults['aips_max_tokens_title'],
			),
			'aips_max_tokens_excerpt' => array(
				'sanitize_callback' => array($ui, 'sanitize_token_budget'),
				'default'           => $defaults['aips_max_tokens_excerpt'],
			),
			'aips_max_tokens_content' => array(
				'sanitize_callback' => array($ui, 'sanitize_token_budget'),
				'default'           => $defaults['aips_max_tokens_content'],
			),
			'aips_max_tokens_outline' => array(
				'sanitize_callback' => array($ui, 'sanitize_token_budget'),
				'default'           => $defaults['aips_max_tokens_outline'],
			),
			'aips_max_tokens_faq' => array(
				'sanitize_callback' => array($ui, 'sanitize_token_budget'),
				'default'           => $defaults['aips_max_tokens_faq'],
			),
			'aips_conversational_generation' => array(
				'sanitize_callback' => 'absint',
				'default'           => $defaults['aips_conversational_generation'],
			),
			'aips_conversational_metadata_turn' => array(
				'sanitize_callback' => 'absint',
				'default'           => $defaults['aips_conversational_metadata_turn'],
			),
			'aips_ai_fallback_provider' => array(
				'sanitize_callback' => array($ui, 'sanitize_ai_provider'),
				'default'           => $defaults['aips_ai_fallback_provider'],
			),
			'aips_ai_fallback_model' => array(
				'sanitize_callback' => 'sanitize_text_field',
				'default'           => $defaults['aips_ai_fallback_model'],
			),
			// Feedback Tab
			'aips_topic_similarity_threshold' => array(
				'sanitize_callback' => array($ui, 'sanitize_similarity_threshold'),
				'default'           => $defaults['aips_topic_similarity_threshold'],
			),
			'aips_max_topic_suggestions_batch' => array(
				'sanitize_callback' => 'absint',
				'default'           => $defaults['aips_max_topic_suggestions_batch'],
			),
			'aips_topics_retention_days' => array(
				'sanitize_callback' => 'absint',
				'default'           => $defaults['aips_topics_retention_days'],
			),
			// Notifications Tab
			'aips_review_notifications_email' => array(
				'sanitize_callback' => array($ui, 'sanitize_notification_emails'),
				'default'           => $defaults['aips_review_notifications_email'],
			),
			'aips_notification_preferences' => array(
				'sanitize_callback' => array($ui, 'sanitize_notification_preferences'),
				'default'           => $defaults['aips_notification_preferences'],
			),
			// Resilience & Limits Tab
			'aips_enable_retry' => array(
				'sanitize_callback' => 'absint',
				'default'           => $defaults['aips_enable_retry'],
			),
			'aips_retry_max_attempts' => array(
				'sanitize_callback' => 'absint',
				'default'           => $defaults['aips_retry_max_attempts'],
			),
			'aips_retry_initial_delay' => array(
				'sanitize_callback' => 'absint',
				'default'           => $defaults['aips_retry_initial_delay'],
			),
			'aips_retry_jitter' => array(
				'sanitize_callback' => 'absint',
				'default'           => $defaults['aips_retry_jitter'],
			),
			'aips_enable_rate_limiting' => array(
				'sanitize_callback' => 'absint',
				'default'           => $defaults['aips_enable_rate_limiting'],
			),
			'aips_rate_limit_requests' => array(
				'sanitize_callback' => 'absint',
				'default'           => $defaults['aips_rate_limit_requests'],
			),
			'aips_rate_limit_period' => array(
				'sanitize_callback' => 'absint',
				'default'           => $defaults['aips_rate_limit_period'],
			),
			'aips_enable_circuit_breaker' => array(
				'sanitize_callback' => 'absint',
				'default'           => $defaults['aips_enable_circuit_breaker'],
			),
			'aips_circuit_breaker_threshold' => array(
				'sanitize_callback' => 'absint',
				'default'           => $defaults['aips_circuit_breaker_threshold'],
			),
			'aips_circuit_breaker_timeout' => array(
				'sanitize_callback' => 'absint',
				'default'           => $defaults['aips_circuit_breaker_timeout'],
			),
			'aips_enable_schedule_jitter' => array(
				'sanitize_callback' => 'absint',
				'default'           => $defaults['aips_enable_schedule_jitter'],
			),
			'aips_schedule_jitter_minutes' => array(
				'sanitize_callback' => 'absint',
				'default'           => $defaults['aips_schedule_jitter_minutes'],
			),
			'aips_cron_batch_size' => array(
				'sanitize_callback' => 'absint',
				'default'           => $defaults['aips_cron_batch_size'],
			),
			'aips_large_batch_threshold' => array(
				'sanitize_callback' => 'absint',
				'default'           => $defaults['aips_large_batch_threshold'],
			),
			'aips_batch_max_slices' => array(
				'sanitize_callback' => 'absint',
				'default'           => $defaults['aips_batch_max_slices'],
			),
			'aips_batch_queue_window_seconds' => array(
				'sanitize_callback' => 'absint',
				'default'           => $defaults['aips_batch_queue_window_seconds'],
			),
			'aips_generation_timeout_seconds' => array(
				'sanitize_callback' => 'absint',
				'default'           => $defaults['aips_generation_timeout_seconds'],
			),
			// Content Strategy / Embeddings / Related Posts
			'aips_embeddings_provider' => array(
				'sanitize_callback' => 'sanitize_key',
				'default'           => $defaults['aips_embeddings_provider'],
			),
			'aips_embeddings_model' => array(
				'sanitize_callback' => 'sanitize_text_field',
				'default'           => $defaults['aips_embeddings_model'],
			),
			'aips_indexer_verbose_history' => array(
				'sanitize_callback' => 'absint',
				'default'           => $defaults['aips_indexer_verbose_history'],
			),
			'aips_embeddings_env_id' => array(
				'sanitize_callback' => 'sanitize_text_field',
				'default'           => $defaults['aips_embeddings_env_id'],
			),
			'aips_embeddings_dimensions' => array(
				'sanitize_callback' => 'absint',
				'default'           => $defaults['aips_embeddings_dimensions'],
			),
			'aips_indexer_post_types' => array(
				'sanitize_callback' => array($ui, 'sanitize_post_types'),
				'default'           => $defaults['aips_indexer_post_types'],
			),
			'aips_indexer_similarity_threshold' => array(
				'sanitize_callback' => array($ui, 'sanitize_similarity_threshold'),
				'default'           => $defaults['aips_indexer_similarity_threshold'],
			),
			'aips_auto_index_on_publish' => array(
				'sanitize_callback' => 'absint',
				'default'           => $defaults['aips_auto_index_on_publish'],
			),
			'aips_deduplication_mode' => array(
				'sanitize_callback' => array($ui, 'sanitize_deduplication_mode'),
				'default'           => $defaults['aips_deduplication_mode'],
			),
			'aips_deduplication_threshold' => array(
				'sanitize_callback' => array($ui, 'sanitize_similarity_threshold'),
				'default'           => $defaults['aips_deduplication_threshold'],
			),
			'aips_generation_inject_related_context' => array(
				'sanitize_callback' => 'absint',
				'default'           => $defaults['aips_generation_inject_related_context'],
			),
			'aips_related_posts_enabled' => array(
				'sanitize_callback' => 'absint',
				'default'           => $defaults['aips_related_posts_enabled'],
			),
			'aips_related_posts_auto_append' => array(
				'sanitize_callback' => 'absint',
				'default'           => $defaults['aips_related_posts_auto_append'],
			),
			'aips_related_posts_count' => array(
				'sanitize_callback' => 'absint',
				'default'           => $defaults['aips_related_posts_count'],
			),
			'aips_related_posts_heading' => array(
				'sanitize_callback' => 'sanitize_text_field',
				'default'           => $defaults['aips_related_posts_heading'],
			),
			'aips_related_posts_layout' => array(
				'sanitize_callback' => array($ui, 'sanitize_related_posts_layout'),
				'default'           => $defaults['aips_related_posts_layout'],
			),
			'aips_related_posts_show_thumbnails' => array(
				'sanitize_callback' => 'absint',
				'default'           => $defaults['aips_related_posts_show_thumbnails'],
			),
			'aips_related_posts_show_excerpts' => array(
				'sanitize_callback' => 'absint',
				'default'           => $defaults['aips_related_posts_show_excerpts'],
			),
			// Cache / Performance Tab
			'aips_enable_cache_system' => array(
				'sanitize_callback' => array($ui, 'sanitize_enable_cache_system'),
				'default'           => $defaults['aips_enable_cache_system'],
			),
			'aips_cache_driver' => array(
				'sanitize_callback' => array($ui, 'sanitize_cache_driver'),
				'default'           => $defaults['aips_cache_driver'],
			),
			'aips_cache_db_prefix' => array(
				'sanitize_callback' => 'sanitize_text_field',
				'default'           => $defaults['aips_cache_db_prefix'],
			),
			'aips_cache_default_ttl' => array(
				'sanitize_callback' => 'absint',
				'default'           => $defaults['aips_cache_default_ttl'],
			),
			'aips_cache_monitor_event_retention_days' => array(
				'sanitize_callback' => 'absint',
				'default'           => $defaults['aips_cache_monitor_event_retention_days'],
			),
			'aips_cache_monitor_max_index_entries' => array(
				'sanitize_callback' => 'absint',
				'default'           => $defaults['aips_cache_monitor_max_index_entries'],
			),
			'aips_cache_monitor_preview_length' => array(
				'sanitize_callback' => 'absint',
				'default'           => $defaults['aips_cache_monitor_preview_length'],
			),
			'aips_cache_monitor_live_refresh_interval' => array(
				'sanitize_callback' => 'absint',
				'default'           => $defaults['aips_cache_monitor_live_refresh_interval'],
			),
			// API Keys Tab
			'aips_unsplash_access_key' => array(
				'sanitize_callback' => 'sanitize_text_field',
				'default'           => $defaults['aips_unsplash_access_key'],
			),
			'aips_webhook_url' => array(
				'sanitize_callback' => 'esc_url_raw',
				'default'           => $defaults['aips_webhook_url'],
			),
			'aips_webhook_secret' => array(
				'sanitize_callback' => 'sanitize_text_field',
				'default'           => $defaults['aips_webhook_secret'],
			),
			'aips_webhook_events' => array(
				'sanitize_callback' => array($ui, 'sanitize_webhook_events'),
				'default'           => $defaults['aips_webhook_events'],
			),
			// Developers Tab
			'aips_enable_logging' => array(
				'sanitize_callback' => 'absint',
				'default'           => $defaults['aips_enable_logging'],
			),
			'aips_developer_mode' => array(
				'sanitize_callback' => 'absint',
				'default'           => $defaults['aips_developer_mode'],
			),
			'aips_enable_telemetry' => array(
				'sanitize_callback' => 'absint',
				'default'           => $defaults['aips_enable_telemetry'],
			),
			'aips_cache_monitor_enabled' => array(
				'sanitize_callback' => 'absint',
				'default'           => $defaults['aips_cache_monitor_enabled'],
			),
			'aips_log_retention_days' => array(
				'sanitize_callback' => 'absint',
				'default'           => $defaults['aips_log_retention_days'],
			),
			'aips_history_retention_days' => array(
				'sanitize_callback' => 'absint',
				'default'           => $defaults['aips_history_retention_days'],
			),
		);

		foreach (self::get_content_strategy_options() as $option_key => $meta) {
			$settings[$option_key] = array(
				'sanitize_callback' => $meta['sanitize_callback'],
				'default'           => $meta['default'],
			);
		}

		return $settings;
	}

	/**
	 * Register the canonical settings schema with WordPress.
	 *
	 * @param AIPS_Settings_UI|null $ui Settings UI helper for instance callbacks.
	 * @return void
	 */
	public static function register_setting_schema($ui = null) {
		self::register_runtime_hooks();

		foreach (self::get_registered_settings_args($ui) as $option_name => $args) {
			register_setting('aips_settings', $option_name, $args);
		}
	}

     /**
     * Register plugin settings and fields.
     *
     * Defines the settings section and fields for general configuration including
     * post status, category, AI model, retries, and logging.
     *
     * @return void
     */
	public function register_settings() {
		self::register_setting_schema($this->ui);
        
        // -----------------------------------------------------------------------
        // General section: Default Post Status, Category, Author, Formats, Comments, SEO
        // -----------------------------------------------------------------------
        add_settings_section(
            'aips_general_section',
            __('General Settings', 'ai-post-scheduler'),
            array($this->ui, 'general_section_callback'),
            'aips-settings'
        );

        add_settings_field(
            'aips_default_post_status',
            __('Default Post Status', 'ai-post-scheduler'),
            array($this->ui, 'post_status_field_callback'),
            'aips-settings',
            'aips_general_section'
        );

        add_settings_field(
            'aips_default_category',
            __('Default Category', 'ai-post-scheduler'),
            array($this->ui, 'category_field_callback'),
            'aips-settings',
            'aips_general_section'
        );

        add_settings_field(
            'aips_default_post_author',
            __('Default Post Author', 'ai-post-scheduler'),
            array($this->ui, 'post_author_field_callback'),
            'aips-settings',
            'aips_general_section'
        );

        add_settings_field(
            'aips_default_post_format',
            __('Default Post Format', 'ai-post-scheduler'),
            array($this->ui, 'default_post_format_field_callback'),
            'aips-settings',
            'aips_general_section'
        );

        add_settings_field(
            'aips_default_comment_status',
            __('Default Comment Status', 'ai-post-scheduler'),
            array($this->ui, 'default_comment_status_field_callback'),
            'aips-settings',
            'aips_general_section'
        );

        add_settings_field(
            'aips_default_ping_status',
            __('Default Ping Status', 'ai-post-scheduler'),
            array($this->ui, 'default_ping_status_field_callback'),
            'aips-settings',
            'aips_general_section'
        );

        add_settings_field(
            'aips_auto_generate_meta_description',
            __('Auto-generate Meta Description', 'ai-post-scheduler'),
            array($this->ui, 'auto_generate_meta_description_field_callback'),
            'aips-settings',
            'aips_general_section'
        );

        add_settings_field(
            'aips_auto_generate_tags',
            __('Auto-generate Tags', 'ai-post-scheduler'),
            array($this->ui, 'auto_generate_tags_field_callback'),
            'aips-settings',
            'aips_general_section'
        );

        add_settings_field(
            'aips_max_tags_count',
            __('Max Tags Count', 'ai-post-scheduler'),
            array($this->ui, 'max_tags_count_field_callback'),
            'aips-settings',
            'aips_general_section'
        );

        // -----------------------------------------------------------------------
        // AI section: Provider, Connectors, Model, Temperature, Token Budgets, Fallbacks
        // -----------------------------------------------------------------------
        add_settings_section(
            'aips_ai_section',
            __('AI Settings', 'ai-post-scheduler'),
            array($this->ui, 'ai_section_callback'),
            'aips-settings'
        );

        add_settings_field(
            'aips_ai_provider',
            __('AI Provider', 'ai-post-scheduler'),
            array($this->ui, 'ai_provider_field_callback'),
            'aips-settings',
            'aips_ai_section'
        );

		add_settings_field(
			'aips_wp_ai_connectors',
			__('WordPress AI Connectors', 'ai-post-scheduler'),
			array($this->ui, 'wp_ai_connectors_field_callback'),
			'aips-settings',
			'aips_ai_section'
		);

        add_settings_field(
            'aips_ai_model',
            __('AI Model', 'ai-post-scheduler'),
            array($this->ui, 'ai_model_field_callback'),
            'aips-settings',
            'aips_ai_section'
        );

        add_settings_field(
            'aips_ai_env_id',
            __('Environment ID', 'ai-post-scheduler'),
            array($this->ui, 'ai_env_id_field_callback'),
            'aips-settings',
            'aips_ai_section'
        );

		add_settings_field(
			'aips_prevent_scheduled_ai_generation',
			AIPS_Config::get_instance()->get_scheduled_ai_generation_prevention_label(),
			array($this->ui, 'prevent_scheduled_ai_generation_field_callback'),
			'aips-settings',
			'aips_ai_section'
		);

        add_settings_field(
            'aips_temperature',
            __('AI Temperature', 'ai-post-scheduler'),
            array($this->ui, 'temperature_field_callback'),
            'aips-settings',
            'aips_ai_section'
        );

        add_settings_field(
            'aips_global_system_prompt',
            __('Global System Instructions', 'ai-post-scheduler'),
            array($this->ui, 'global_system_prompt_field_callback'),
            'aips-settings',
            'aips_ai_section'
        );

        add_settings_field(
            'aips_max_tokens_limit',
            __('Max Tokens Limit', 'ai-post-scheduler'),
            array($this->ui, 'max_tokens_limit_field_callback'),
            'aips-settings',
            'aips_ai_section'
        );

        add_settings_field(
            'aips_max_tokens_title',
            __('Max Tokens for Post Titles', 'ai-post-scheduler'),
            array($this->ui, 'max_tokens_title_field_callback'),
            'aips-settings',
            'aips_ai_section'
        );

        add_settings_field(
            'aips_max_tokens_excerpt',
            __('Max Tokens for Post Excerpts', 'ai-post-scheduler'),
            array($this->ui, 'max_tokens_excerpt_field_callback'),
            'aips-settings',
            'aips_ai_section'
        );

        add_settings_field(
            'aips_max_tokens_content',
            __('Max Tokens for Post Content', 'ai-post-scheduler'),
            array($this->ui, 'max_tokens_content_field_callback'),
            'aips-settings',
            'aips_ai_section'
        );

        add_settings_field(
            'aips_max_tokens_outline',
            __('Max Tokens for Outline', 'ai-post-scheduler'),
            array($this->ui, 'max_tokens_outline_field_callback'),
            'aips-settings',
            'aips_ai_section'
        );

        add_settings_field(
            'aips_max_tokens_faq',
            __('Max Tokens for FAQ & Takeaways', 'ai-post-scheduler'),
            array($this->ui, 'max_tokens_faq_field_callback'),
            'aips-settings',
            'aips_ai_section'
        );

        add_settings_field(
            'aips_conversational_generation',
            __('Conversational Generation', 'ai-post-scheduler'),
            array($this->ui, 'conversational_generation_field_callback'),
            'aips-settings',
            'aips_ai_section'
        );

        add_settings_field(
            'aips_conversational_metadata_turn',
            __('Combined Metadata Turn', 'ai-post-scheduler'),
            array($this->ui, 'conversational_metadata_turn_field_callback'),
            'aips-settings',
            'aips_ai_section'
        );

        add_settings_field(
            'aips_ai_fallback_provider',
            __('Fallback AI Provider', 'ai-post-scheduler'),
            array($this->ui, 'ai_fallback_provider_field_callback'),
            'aips-settings',
            'aips_ai_section'
        );

        add_settings_field(
            'aips_ai_fallback_model',
            __('Fallback AI Model', 'ai-post-scheduler'),
            array($this->ui, 'ai_fallback_model_field_callback'),
            'aips-settings',
            'aips_ai_section'
        );

        // -----------------------------------------------------------------------
        // Feedback section: Topic Similarity Threshold, Batch Size, Retention
        // -----------------------------------------------------------------------
        add_settings_section(
            'aips_feedback_section',
            __('Feedback Settings', 'ai-post-scheduler'),
            array($this->ui, 'feedback_section_callback'),
            'aips-settings'
        );

        add_settings_field(
            'aips_topic_similarity_threshold',
            __('Topic Similarity Threshold', 'ai-post-scheduler'),
            array($this->ui, 'topic_similarity_threshold_field_callback'),
            'aips-settings',
            'aips_feedback_section'
        );

        add_settings_field(
            'aips_max_topic_suggestions_batch',
            __('Max Suggestions per Batch', 'ai-post-scheduler'),
            array($this->ui, 'max_topic_suggestions_batch_field_callback'),
            'aips-settings',
            'aips_feedback_section'
        );

        add_settings_field(
            'aips_topics_retention_days',
            __('Topic Suggestions Retention (Days)', 'ai-post-scheduler'),
            array($this->ui, 'topics_retention_days_field_callback'),
            'aips-settings',
            'aips_feedback_section'
        );

        // -----------------------------------------------------------------------
        // Notifications section: Email address + all per-type preferences
        // -----------------------------------------------------------------------
        add_settings_section(
            'aips_notifications_section',
            __('Notifications', 'ai-post-scheduler'),
            array($this->ui, 'notifications_section_callback'),
            'aips-settings'
        );

        add_settings_field(
            'aips_review_notifications_email',
            __('Notifications Email Address', 'ai-post-scheduler'),
            array($this->ui, 'review_notifications_email_field_callback'),
            'aips-settings',
            'aips_notifications_section'
        );

        foreach (AIPS_Notifications::get_notification_type_registry() as $type => $meta) {
            add_settings_field(
                'aips_notification_preferences_' . $type,
                $meta['label'],
                array($this->ui, 'notification_preference_field_callback'),
                'aips-settings',
                'aips_notifications_section',
                array(
                    'type'        => $type,
                    'description' => $meta['description'],
                )
            );
        }

        // -----------------------------------------------------------------------
        // API Keys section: Unsplash Access Key, Webhooks
        // -----------------------------------------------------------------------
        add_settings_section(
            'aips_api_keys_section',
            __('API Keys & External Integrations', 'ai-post-scheduler'),
            array($this->ui, 'api_keys_section_callback'),
            'aips-settings'
        );

        add_settings_field(
            'aips_unsplash_access_key',
            __('Unsplash Access Key', 'ai-post-scheduler'),
            array($this->ui, 'unsplash_access_key_field_callback'),
            'aips-settings',
            'aips_api_keys_section'
        );

        add_settings_field(
            'aips_webhook_url',
            __('Webhook Target URL', 'ai-post-scheduler'),
            array($this->ui, 'webhook_url_field_callback'),
            'aips-settings',
            'aips_api_keys_section'
        );

        add_settings_field(
            'aips_webhook_secret',
            __('Webhook Secret', 'ai-post-scheduler'),
            array($this->ui, 'webhook_secret_field_callback'),
            'aips-settings',
            'aips_api_keys_section'
        );

        add_settings_field(
            'aips_webhook_events',
            __('Webhook Notification Events', 'ai-post-scheduler'),
            array($this->ui, 'webhook_events_field_callback'),
            'aips-settings',
            'aips_api_keys_section'
        );

        // -----------------------------------------------------------------------
        // Developers section: Enable Logging, Developer Mode, Retention
        // -----------------------------------------------------------------------
        add_settings_section(
            'aips_developers_section',
            __('Developer Settings', 'ai-post-scheduler'),
            array($this->ui, 'developers_section_callback'),
            'aips-settings'
        );

        add_settings_field(
            'aips_enable_logging',
            __('Enable Logging', 'ai-post-scheduler'),
            array($this->ui, 'logging_field_callback'),
            'aips-settings',
            'aips_developers_section'
        );

        add_settings_field(
            'aips_developer_mode',
            __('Developer Mode', 'ai-post-scheduler'),
            array($this->ui, 'developer_mode_field_callback'),
            'aips-settings',
            'aips_developers_section'
        );

        add_settings_field(
            'aips_enable_telemetry',
            __('Enable Telemetry', 'ai-post-scheduler'),
            array($this->ui, 'enable_telemetry_field_callback'),
            'aips-settings',
            'aips_developers_section'
        );

        add_settings_field(
            'aips_cache_monitor_enabled',
            __('Enable Cache Monitor', 'ai-post-scheduler'),
            array($this->ui, 'cache_monitor_enabled_field_callback'),
            'aips-settings',
            'aips_developers_section'
        );

        add_settings_field(
            'aips_log_retention_days',
            __('Log Retention (Days)', 'ai-post-scheduler'),
            array($this->ui, 'log_retention_days_field_callback'),
            'aips-settings',
            'aips_developers_section'
        );

        add_settings_field(
            'aips_history_retention_days',
            __('History Retention (Days)', 'ai-post-scheduler'),
            array($this->ui, 'history_retention_days_field_callback'),
            'aips-settings',
            'aips_developers_section'
        );

        // -----------------------------------------------------------------------
        // Resilience & Limits section: Retries, Rate Limit, Circuit Breaker, Jitter, Queue
        // -----------------------------------------------------------------------
        add_settings_section(
            'aips_resilience_section',
            __('Resilience & Limits', 'ai-post-scheduler'),
            array($this->ui, 'resilience_section_callback'),
            'aips-settings'
        );

        add_settings_field(
            'aips_enable_retry',
            __('Enable Retry', 'ai-post-scheduler'),
            array($this->ui, 'enable_retry_field_callback'),
            'aips-settings',
            'aips_resilience_section'
        );

        add_settings_field(
            'aips_retry_max_attempts',
            __('Max Retries on Failure', 'ai-post-scheduler'),
            array($this->ui, 'max_retries_field_callback'),
            'aips-settings',
            'aips_resilience_section'
        );

        add_settings_field(
            'aips_retry_initial_delay',
            __('Retry Initial Delay (Seconds)', 'ai-post-scheduler'),
            array($this->ui, 'retry_initial_delay_field_callback'),
            'aips-settings',
            'aips_resilience_section'
        );

        add_settings_field(
            'aips_retry_jitter',
            __('Retry Delay Jitter', 'ai-post-scheduler'),
            array($this->ui, 'retry_jitter_field_callback'),
            'aips-settings',
            'aips_resilience_section'
        );

        add_settings_field(
            'aips_enable_rate_limiting',
            __('Enable Rate Limiting', 'ai-post-scheduler'),
            array($this->ui, 'enable_rate_limiting_field_callback'),
            'aips-settings',
            'aips_resilience_section'
        );

        add_settings_field(
            'aips_rate_limit_requests',
            __('Rate Limit Max Requests', 'ai-post-scheduler'),
            array($this->ui, 'rate_limit_requests_field_callback'),
            'aips-settings',
            'aips_resilience_section'
        );

        add_settings_field(
            'aips_rate_limit_period',
            __('Rate Limit Period (Seconds)', 'ai-post-scheduler'),
            array($this->ui, 'rate_limit_period_field_callback'),
            'aips-settings',
            'aips_resilience_section'
        );

        add_settings_field(
            'aips_enable_circuit_breaker',
            __('Enable Circuit Breaker', 'ai-post-scheduler'),
            array($this->ui, 'enable_circuit_breaker_field_callback'),
            'aips-settings',
            'aips_resilience_section'
        );

        add_settings_field(
            'aips_circuit_breaker_threshold',
            __('Circuit Breaker Failure Threshold', 'ai-post-scheduler'),
            array($this->ui, 'circuit_breaker_threshold_field_callback'),
            'aips-settings',
            'aips_resilience_section'
        );

        add_settings_field(
            'aips_circuit_breaker_timeout',
            __('Circuit Breaker Timeout (Seconds)', 'ai-post-scheduler'),
            array($this->ui, 'circuit_breaker_timeout_field_callback'),
            'aips-settings',
            'aips_resilience_section'
        );

        add_settings_field(
            'aips_enable_schedule_jitter',
            __('Enable Schedule Jitter', 'ai-post-scheduler'),
            array($this->ui, 'enable_schedule_jitter_field_callback'),
            'aips-settings',
            'aips_resilience_section'
        );

        add_settings_field(
            'aips_schedule_jitter_minutes',
            __('Max Schedule Jitter (Minutes)', 'ai-post-scheduler'),
            array($this->ui, 'schedule_jitter_minutes_field_callback'),
            'aips-settings',
            'aips_resilience_section'
        );

        add_settings_field(
            'aips_cron_batch_size',
            __('Cron Batch Size (Due Schedules)', 'ai-post-scheduler'),
            array($this->ui, 'cron_batch_size_field_callback'),
            'aips-settings',
            'aips_resilience_section'
        );

        add_settings_field(
            'aips_large_batch_threshold',
            __('Batch Queue Slicing Threshold', 'ai-post-scheduler'),
            array($this->ui, 'large_batch_threshold_field_callback'),
            'aips-settings',
            'aips_resilience_section'
        );

        add_settings_field(
            'aips_batch_max_slices',
            __('Batch Queue Max Slices', 'ai-post-scheduler'),
            array($this->ui, 'batch_max_slices_field_callback'),
            'aips-settings',
            'aips_resilience_section'
        );

        add_settings_field(
            'aips_batch_queue_window_seconds',
            __('Batch Queue Window (Seconds)', 'ai-post-scheduler'),
            array($this->ui, 'batch_queue_window_seconds_field_callback'),
            'aips-settings',
            'aips_resilience_section'
        );

        add_settings_field(
            'aips_generation_timeout_seconds',
            __('Generation Timeout (Seconds)', 'ai-post-scheduler'),
            array($this->ui, 'generation_timeout_seconds_field_callback'),
            'aips-settings',
            'aips_resilience_section'
        );

        // -----------------------------------------------------------------------
        // Site-wide Content Strategy settings
        // -----------------------------------------------------------------------
        add_settings_section(
            'aips_content_strategy_section',
            __('Site Content Strategy', 'ai-post-scheduler'),
            array($this->ui, 'content_strategy_section_callback'),
            'aips-settings'
        );

        add_settings_field(
            'aips_site_niche',
            __('Site Niche / Primary Topic', 'ai-post-scheduler'),
            array($this->ui, 'site_niche_field_callback'),
            'aips-settings',
            'aips_content_strategy_section'
        );

        add_settings_field(
            'aips_site_target_audience',
            __('Target Audience', 'ai-post-scheduler'),
            array($this->ui, 'site_target_audience_field_callback'),
            'aips-settings',
            'aips_content_strategy_section'
        );

        add_settings_field(
            'aips_site_content_goals',
            __('Content Goals', 'ai-post-scheduler'),
            array($this->ui, 'site_content_goals_field_callback'),
            'aips-settings',
            'aips_content_strategy_section'
        );

		add_settings_field(
			'aips_default_article_structure_id',
			__('Default Article Structure', 'ai-post-scheduler'),
			array($this->ui, 'site_default_article_structure_field_callback'),
			'aips-settings',
			'aips_content_strategy_section'
		);

        add_settings_field(
            'aips_site_brand_voice',
            __('Brand Voice / Tone', 'ai-post-scheduler'),
            array($this->ui, 'site_brand_voice_field_callback'),
            'aips-settings',
            'aips_content_strategy_section'
        );

        add_settings_field(
            'aips_site_content_language',
            __('Content Language', 'ai-post-scheduler'),
            array($this->ui, 'site_content_language_field_callback'),
            'aips-settings',
            'aips_content_strategy_section'
        );

        add_settings_field(
            'aips_site_content_guidelines',
            __('Content Guidelines', 'ai-post-scheduler'),
            array($this->ui, 'site_content_guidelines_field_callback'),
            'aips-settings',
            'aips_content_strategy_section'
        );

        add_settings_field(
            'aips_site_excluded_topics',
            __('Excluded Topics (site-wide)', 'ai-post-scheduler'),
            array($this->ui, 'site_excluded_topics_field_callback'),
            'aips-settings',
            'aips_content_strategy_section'
        );

        add_settings_field(
            'aips_embeddings_provider',
            __('Embeddings Provider', 'ai-post-scheduler'),
            array($this->ui, 'embeddings_provider_field_callback'),
            'aips-settings',
            'aips_content_strategy_section'
        );

        add_settings_field(
            'aips_embeddings_model',
            __('Embeddings Model', 'ai-post-scheduler'),
            array($this->ui, 'embeddings_model_field_callback'),
            'aips-settings',
            'aips_content_strategy_section'
        );

        add_settings_field(
            'aips_embeddings_env_id',
            __('Embeddings Environment ID', 'ai-post-scheduler'),
            array($this->ui, 'embeddings_env_id_field_callback'),
            'aips-settings',
            'aips_content_strategy_section'
        );

        add_settings_field(
            'aips_embeddings_dimensions',
            __('Embeddings Dimensions', 'ai-post-scheduler'),
            array($this->ui, 'embeddings_dimensions_field_callback'),
            'aips-settings',
            'aips_content_strategy_section'
        );

        add_settings_field(
            'aips_indexer_post_types',
            __('Indexed Post Types', 'ai-post-scheduler'),
            array($this->ui, 'indexer_post_types_field_callback'),
            'aips-settings',
            'aips_content_strategy_section'
        );

        add_settings_field(
            'aips_indexer_similarity_threshold',
            __('Indexer Similarity Threshold', 'ai-post-scheduler'),
            array($this->ui, 'indexer_similarity_threshold_field_callback'),
            'aips-settings',
            'aips_content_strategy_section'
        );

        add_settings_field(
            'aips_auto_index_on_publish',
            __('Auto-index on Publish', 'ai-post-scheduler'),
            array($this->ui, 'auto_index_on_publish_field_callback'),
            'aips-settings',
            'aips_content_strategy_section'
        );

        add_settings_field(
            'aips_deduplication_mode',
            __('Deduplication & Cannibalization Shield', 'ai-post-scheduler'),
            array($this->ui, 'deduplication_mode_field_callback'),
            'aips-settings',
            'aips_content_strategy_section'
        );

        add_settings_field(
            'aips_deduplication_threshold',
            __('Deduplication Similarity Threshold', 'ai-post-scheduler'),
            array($this->ui, 'deduplication_threshold_field_callback'),
            'aips-settings',
            'aips_content_strategy_section'
        );

        add_settings_field(
            'aips_generation_inject_related_context',
            __('Inject Related Context into Prompts', 'ai-post-scheduler'),
            array($this->ui, 'generation_inject_related_context_field_callback'),
            'aips-settings',
            'aips_content_strategy_section'
        );

        add_settings_field(
            'aips_related_posts_enabled',
            __('Enable Frontend Related Posts', 'ai-post-scheduler'),
            array($this->ui, 'related_posts_enabled_field_callback'),
            'aips-settings',
            'aips_content_strategy_section'
        );

        add_settings_field(
            'aips_related_posts_auto_append',
            __('Auto-append Related Posts', 'ai-post-scheduler'),
            array($this->ui, 'related_posts_auto_append_field_callback'),
            'aips-settings',
            'aips_content_strategy_section'
        );

        add_settings_field(
            'aips_related_posts_count',
            __('Related Posts Count', 'ai-post-scheduler'),
            array($this->ui, 'related_posts_count_field_callback'),
            'aips-settings',
            'aips_content_strategy_section'
        );

        add_settings_field(
            'aips_related_posts_heading',
            __('Related Posts Heading', 'ai-post-scheduler'),
            array($this->ui, 'related_posts_heading_field_callback'),
            'aips-settings',
            'aips_content_strategy_section'
        );

        add_settings_field(
            'aips_related_posts_layout',
            __('Related Posts Layout', 'ai-post-scheduler'),
            array($this->ui, 'related_posts_layout_field_callback'),
            'aips-settings',
            'aips_content_strategy_section'
        );

        add_settings_field(
            'aips_related_posts_show_thumbnails',
            __('Show Thumbnails in Related Posts', 'ai-post-scheduler'),
            array($this->ui, 'related_posts_show_thumbnails_field_callback'),
            'aips-settings',
            'aips_content_strategy_section'
        );

        add_settings_field(
            'aips_related_posts_show_excerpts',
            __('Show Excerpts in Related Posts', 'ai-post-scheduler'),
            array($this->ui, 'related_posts_show_excerpts_field_callback'),
            'aips-settings',
            'aips_content_strategy_section'
        );

        // -----------------------------------------------------------------------
        // Cache section: Driver selection + per-driver configuration + monitor settings
        // -----------------------------------------------------------------------
        add_settings_section(
            'aips_cache_section',
            '',
            array($this->ui, 'cache_section_callback'),
            'aips-settings'
        );

        add_settings_field(
            'aips_enable_cache_system',
            __('Enable Cache System?', 'ai-post-scheduler'),
            array($this->ui, 'enable_cache_system_field_callback'),
            'aips-settings',
            'aips_cache_section'
        );

        add_settings_field(
            'aips_cache_driver',
            __('Cache Driver', 'ai-post-scheduler'),
            array($this->ui, 'cache_driver_field_callback'),
            'aips-settings',
            'aips_cache_section'
        );

        add_settings_field(
            'aips_cache_default_ttl',
            __('Default TTL (seconds)', 'ai-post-scheduler'),
            array($this->ui, 'cache_default_ttl_field_callback'),
            'aips-settings',
            'aips_cache_section'
        );

        add_settings_field(
            'aips_cache_db_prefix',
            __('DB Cache Key Prefix', 'ai-post-scheduler'),
            array($this->ui, 'cache_db_prefix_field_callback'),
            'aips-settings',
            'aips_cache_section'
        );

        add_settings_field(
            'aips_cache_monitor_event_retention_days',
            __('Cache Monitor Event Retention (Days)', 'ai-post-scheduler'),
            array($this->ui, 'cache_monitor_event_retention_days_field_callback'),
            'aips-settings',
            'aips_cache_section'
        );

        add_settings_field(
            'aips_cache_monitor_max_index_entries',
            __('Cache Monitor Max Index Entries', 'ai-post-scheduler'),
            array($this->ui, 'cache_monitor_max_index_entries_field_callback'),
            'aips-settings',
            'aips_cache_section'
        );

        add_settings_field(
            'aips_cache_monitor_preview_length',
            __('Cache Monitor Value Preview Length', 'ai-post-scheduler'),
            array($this->ui, 'cache_monitor_preview_length_field_callback'),
            'aips-settings',
            'aips_cache_section'
        );

        add_settings_field(
            'aips_cache_monitor_live_refresh_interval',
            __('Cache Monitor Live Refresh Interval (Seconds)', 'ai-post-scheduler'),
            array($this->ui, 'cache_monitor_live_refresh_interval_field_callback'),
            'aips-settings',
            'aips_cache_section'
        );

    }

    /**
     * Return the canonical registry of site-wide content strategy options.
     *
     * This is the single source of truth for every option that belongs to the
     * "Site Content Strategy" settings group. Adding a new option here
     * automatically makes it available to AIPS_Site_Context::get() and
     * AIPS_Prompt_Builder::build_site_context_block() without touching those
     * classes.
     *
     * Each entry has:
     *   - 'key'               Short key used by AIPS_Site_Context (e.g. 'niche')
     *   - 'sanitize_callback' Callable used to sanitize the option value on save
     *   - 'default'           Default value returned when the option is not set
     *
     * @return array<string, array{key: string, sanitize_callback: callable, default: mixed}>
     *     Associative array keyed by the full WordPress option name.
     */
    public static function get_content_strategy_options() {
        $config_defaults = AIPS_Config::get_instance()->get_default_options();
        return array(
            'aips_site_niche' => array(
                'key'               => 'niche',
                'sanitize_callback' => 'sanitize_text_field',
                'default'           => $config_defaults['aips_site_niche'],
            ),
            'aips_site_target_audience' => array(
                'key'               => 'target_audience',
                'sanitize_callback' => 'sanitize_text_field',
                'default'           => $config_defaults['aips_site_target_audience'],
            ),
            'aips_site_content_goals' => array(
                'key'               => 'content_goals',
                'sanitize_callback' => 'sanitize_textarea_field',
                'default'           => $config_defaults['aips_site_content_goals'],
            ),
			'aips_default_article_structure_id' => array(
				'key'               => 'default_article_structure_id',
				'sanitize_callback' => 'absint',
				'default'           => $config_defaults['aips_default_article_structure_id'],
			),
            'aips_site_brand_voice' => array(
                'key'               => 'brand_voice',
                'sanitize_callback' => 'sanitize_text_field',
                'default'           => $config_defaults['aips_site_brand_voice'],
            ),
            'aips_site_content_language' => array(
                'key'               => 'content_language',
                'sanitize_callback' => 'sanitize_text_field',
                'default'           => $config_defaults['aips_site_content_language'],
            ),
            'aips_site_content_guidelines' => array(
                'key'               => 'content_guidelines',
                'sanitize_callback' => 'sanitize_textarea_field',
                'default'           => $config_defaults['aips_site_content_guidelines'],
            ),
            'aips_site_excluded_topics' => array(
                'key'               => 'excluded_topics',
                'sanitize_callback' => 'sanitize_textarea_field',
                'default'           => $config_defaults['aips_site_excluded_topics'],
            ),
        );
    }
        
}
