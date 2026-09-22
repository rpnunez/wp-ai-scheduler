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
			'aips_default_post_status' => array(
				'sanitize_callback' => 'sanitize_text_field',
				'default'           => $defaults['aips_default_post_status'],
			),
			'aips_default_category' => array(
				'sanitize_callback' => 'absint',
				'default'           => $defaults['aips_default_category'],
			),
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
			'aips_conversational_generation' => array(
				'sanitize_callback' => 'absint',
				'default'           => $defaults['aips_conversational_generation'],
			),
			'aips_conversational_metadata_turn' => array(
				'sanitize_callback' => 'absint',
				'default'           => $defaults['aips_conversational_metadata_turn'],
			),
			'aips_unsplash_access_key' => array(
				'sanitize_callback' => 'sanitize_text_field',
				'default'           => $defaults['aips_unsplash_access_key'],
			),
			'aips_review_notifications_email' => array(
				'sanitize_callback' => array($ui, 'sanitize_notification_emails'),
				'default'           => $defaults['aips_review_notifications_email'],
			),
			'aips_notification_preferences' => array(
				'sanitize_callback' => array($ui, 'sanitize_notification_preferences'),
				'default'           => $defaults['aips_notification_preferences'],
			),
			'aips_topic_similarity_threshold' => array(
				'sanitize_callback' => array($ui, 'sanitize_similarity_threshold'),
				'default'           => $defaults['aips_topic_similarity_threshold'],
			),
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
			'aips_embeddings_enabled' => array(
				'sanitize_callback' => 'absint',
				'default'           => $defaults['aips_embeddings_enabled'],
			),
			'aips_embeddings_scope' => array(
				'sanitize_callback' => array($ui, 'sanitize_embeddings_scope'),
				'default'           => $defaults['aips_embeddings_scope'],
			),
			'aips_embeddings_date_days' => array(
				'sanitize_callback' => 'absint',
				'default'           => $defaults['aips_embeddings_date_days'],
			),
			'aips_embeddings_date_after' => array(
				'sanitize_callback' => 'sanitize_text_field',
				'default'           => $defaults['aips_embeddings_date_after'],
			),
			'aips_embeddings_rate_limits_enabled' => array(
				'sanitize_callback' => 'absint',
				'default'           => $defaults['aips_embeddings_rate_limits_enabled'],
			),
			'aips_embeddings_daily_limit' => array(
				'sanitize_callback' => 'absint',
				'default'           => $defaults['aips_embeddings_daily_limit'],
			),
			'aips_embeddings_weekly_limit' => array(
				'sanitize_callback' => 'absint',
				'default'           => $defaults['aips_embeddings_weekly_limit'],
			),
			'aips_embeddings_monthly_limit' => array(
				'sanitize_callback' => 'absint',
				'default'           => $defaults['aips_embeddings_monthly_limit'],
			),
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
				'sanitize_callback' => 'floatval',
				'default'           => $defaults['aips_indexer_similarity_threshold'],
			),
			'aips_auto_index_on_publish' => array(
				'sanitize_callback' => 'absint',
				'default'           => $defaults['aips_auto_index_on_publish'],
			),
			'aips_related_posts_enabled' => array(
				'sanitize_callback' => 'absint',
				'default'           => $defaults['aips_related_posts_enabled'],
			),
			'aips_related_posts_auto_append' => array(
				'sanitize_callback' => 'absint',
				'default'           => $defaults['aips_related_posts_auto_append'],
			),
			'aips_related_posts_heading' => array(
				'sanitize_callback' => 'sanitize_text_field',
				'default'           => $defaults['aips_related_posts_heading'],
			),
			'aips_related_posts_count' => array(
				'sanitize_callback' => 'absint',
				'default'           => $defaults['aips_related_posts_count'],
			),
			'aips_related_posts_layout' => array(
				'sanitize_callback' => array($ui, 'sanitize_related_posts_layout'),
				'default'           => $defaults['aips_related_posts_layout'],
			),
			'aips_deduplication_mode' => array(
				'sanitize_callback' => array($ui, 'sanitize_deduplication_mode'),
				'default'           => $defaults['aips_deduplication_mode'],
			),
			'aips_deduplication_threshold' => array(
				'sanitize_callback' => 'floatval',
				'default'           => $defaults['aips_deduplication_threshold'],
			),
			'aips_indexer_publish_execution_timing' => array(
				'sanitize_callback' => array($ui, 'sanitize_publish_execution_timing'),
				'default'           => $defaults['aips_indexer_publish_execution_timing'],
			),
			'aips_indexer_batch_size' => array(
				'sanitize_callback' => 'absint',
				'default'           => $defaults['aips_indexer_batch_size'],
			),
			'aips_indexer_queue_debounce_seconds' => array(
				'sanitize_callback' => 'absint',
				'default'           => $defaults['aips_indexer_queue_debounce_seconds'],
			),
			'aips_indexer_quota_pause_enabled' => array(
				'sanitize_callback' => 'absint',
				'default'           => $defaults['aips_indexer_quota_pause_enabled'],
			),
			'aips_indexer_queue_notifications_enabled' => array(
				'sanitize_callback' => 'absint',
				'default'           => $defaults['aips_indexer_queue_notifications_enabled'],
			),
			'aips_indexer_error_pause_duration' => array(
				'sanitize_callback' => 'absint',
				'default'           => $defaults['aips_indexer_error_pause_duration'],
			),
			'aips_indexer_error_pause_unit' => array(
				'sanitize_callback' => array($ui, 'sanitize_error_pause_unit'),
				'default'           => $defaults['aips_indexer_error_pause_unit'],
			),
			'aips_indexer_consecutive_error_threshold' => array(
				'sanitize_callback' => 'absint',
				'default'           => $defaults['aips_indexer_consecutive_error_threshold'],
			),
			'aips_indexer_post_cluster_threshold' => array(
				'sanitize_callback' => 'floatval',
				'default'           => $defaults['aips_indexer_post_cluster_threshold'],
			),
			'aips_author_topic_auto_approval_mode' => array(
				'sanitize_callback' => array($ui, 'sanitize_author_topic_auto_approval_mode'),
				'default'           => $defaults['aips_author_topic_auto_approval_mode'],
			),
			'aips_author_topic_auto_approval_min_score' => array(
				'sanitize_callback' => 'floatval',
				'default'           => $defaults['aips_author_topic_auto_approval_min_score'],
			),
			'aips_author_topic_auto_approval_max_similarity' => array(
				'sanitize_callback' => 'floatval',
				'default'           => $defaults['aips_author_topic_auto_approval_max_similarity'],
			),
			'aips_author_topic_auto_approval_fallback' => array(
				'sanitize_callback' => array($ui, 'sanitize_author_topic_auto_approval_fallback'),
				'default'           => $defaults['aips_author_topic_auto_approval_fallback'],
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
        // General section: Default Post Status, Default Category
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

        // -----------------------------------------------------------------------
        // Card 1: Content Generation AI Provider
        // -----------------------------------------------------------------------
        add_settings_section(
            'aips_ai_provider_section',
            __('Content Generation AI Provider', 'ai-post-scheduler'),
            array($this->ui, 'ai_provider_section_callback'),
            'aips-settings'
        );

        add_settings_field(
            'aips_ai_provider',
            __('AI Provider', 'ai-post-scheduler'),
            array($this->ui, 'ai_provider_field_callback'),
            'aips-settings',
            'aips_ai_provider_section'
        );

		add_settings_field(
			'aips_wp_ai_connectors',
			__('WordPress AI Connectors', 'ai-post-scheduler'),
			array($this->ui, 'wp_ai_connectors_field_callback'),
			'aips-settings',
			'aips_ai_provider_section'
		);

        add_settings_field(
            'aips_ai_model',
            __('AI Model', 'ai-post-scheduler'),
            array($this->ui, 'ai_model_field_callback'),
            'aips-settings',
            'aips_ai_provider_section'
        );

        add_settings_field(
            'aips_ai_env_id',
            __('Environment ID', 'ai-post-scheduler'),
            array($this->ui, 'ai_env_id_field_callback'),
            'aips-settings',
            'aips_ai_provider_section'
        );

		add_settings_field(
			'aips_prevent_scheduled_ai_generation',
			AIPS_Config::get_instance()->get_scheduled_ai_generation_prevention_label(),
			array($this->ui, 'prevent_scheduled_ai_generation_field_callback'),
			'aips-settings',
			'aips_ai_provider_section'
		);

        // -----------------------------------------------------------------------
        // Card 2: Token Budgets & Prompt Optimization
        // -----------------------------------------------------------------------
        add_settings_section(
            'aips_ai_tokens_section',
            __('Token Budgets & Prompt Optimization', 'ai-post-scheduler'),
            array($this->ui, 'ai_tokens_section_callback'),
            'aips-settings'
        );

        add_settings_field(
            'aips_max_tokens_limit',
            __('Max Tokens Limit', 'ai-post-scheduler'),
            array($this->ui, 'max_tokens_limit_field_callback'),
            'aips-settings',
            'aips_ai_tokens_section'
        );

        add_settings_field(
            'aips_max_tokens_title',
            __('Max Tokens for Post Titles', 'ai-post-scheduler'),
            array($this->ui, 'max_tokens_title_field_callback'),
            'aips-settings',
            'aips_ai_tokens_section'
        );

        add_settings_field(
            'aips_max_tokens_excerpt',
            __('Max Tokens for Post Excerpts', 'ai-post-scheduler'),
            array($this->ui, 'max_tokens_excerpt_field_callback'),
            'aips-settings',
            'aips_ai_tokens_section'
        );

        add_settings_field(
            'aips_max_tokens_content',
            __('Max Tokens for Post Content', 'ai-post-scheduler'),
            array($this->ui, 'max_tokens_content_field_callback'),
            'aips-settings',
            'aips_ai_tokens_section'
        );

        add_settings_field(
            'aips_conversational_generation',
            __('Conversational Generation', 'ai-post-scheduler'),
            array($this->ui, 'conversational_generation_field_callback'),
            'aips-settings',
            'aips_ai_tokens_section'
        );

        add_settings_field(
            'aips_conversational_metadata_turn',
            __('Combined Metadata Turn', 'ai-post-scheduler'),
            array($this->ui, 'conversational_metadata_turn_field_callback'),
            'aips-settings',
            'aips_ai_tokens_section'
        );

        // -----------------------------------------------------------------------
        // Card 3: Vector Embeddings Engine & Model
        // -----------------------------------------------------------------------
        add_settings_section(
            'aips_ai_embeddings_section',
            __('Vector Embeddings Engine & Model', 'ai-post-scheduler'),
            array($this->ui, 'ai_embeddings_section_callback'),
            'aips-settings'
        );

        add_settings_field(
            'aips_embeddings_enabled',
            __('Enable Embeddings System', 'ai-post-scheduler'),
            array($this->ui, 'embeddings_enabled_field_callback'),
            'aips-settings',
            'aips_ai_embeddings_section'
        );

        add_settings_field(
            'aips_embeddings_provider',
            __('Vector Embeddings Provider', 'ai-post-scheduler'),
            array($this->ui, 'embeddings_provider_field_callback'),
            'aips-settings',
            'aips_ai_embeddings_section'
        );

        add_settings_field(
            'aips_embeddings_model',
            __('Embedding Model', 'ai-post-scheduler'),
            array($this->ui, 'embeddings_model_field_callback'),
            'aips-settings',
            'aips_ai_embeddings_section'
        );

        add_settings_field(
            'aips_embeddings_env_id',
            __('Meow AI Engine Environment', 'ai-post-scheduler'),
            array($this->ui, 'embeddings_env_id_field_callback'),
            'aips-settings',
            'aips_ai_embeddings_section'
        );

        add_settings_field(
            'aips_embeddings_dimensions',
            __('Vector Dimensions', 'ai-post-scheduler'),
            array($this->ui, 'embeddings_dimensions_field_callback'),
            'aips-settings',
            'aips_ai_embeddings_section'
        );

        // -----------------------------------------------------------------------
        // Card 4: Indexing Scope, Continuous Sync & Rate Limits
        // -----------------------------------------------------------------------
        add_settings_section(
            'aips_ai_scope_section',
            __('Indexing Scope, Continuous Sync & Rate Limits', 'ai-post-scheduler'),
            array($this->ui, 'ai_scope_section_callback'),
            'aips-settings'
        );

        add_settings_field(
            'aips_embeddings_quota_meters',
            __('Usage & Rate Limits Tracking', 'ai-post-scheduler'),
            array($this->ui, 'embeddings_quota_meters_field_callback'),
            'aips-settings',
            'aips_ai_scope_section'
        );

        add_settings_field(
            'aips_embeddings_scope',
            __('Content Ingestion Scope', 'ai-post-scheduler'),
            array($this->ui, 'embeddings_scope_field_callback'),
            'aips-settings',
            'aips_ai_scope_section'
        );

        add_settings_field(
            'aips_indexer_post_types',
            __('Post Types to Index', 'ai-post-scheduler'),
            array($this->ui, 'indexer_post_types_field_callback'),
            'aips-settings',
            'aips_ai_scope_section'
        );

        add_settings_field(
            'aips_auto_index_on_publish',
            __('Auto-Sync on Publish', 'ai-post-scheduler'),
            array($this->ui, 'auto_index_on_publish_field_callback'),
            'aips-settings',
            'aips_ai_scope_section'
        );

        add_settings_field(
            'aips_indexer_publish_execution_timing',
            __('Publish Indexing Mode', 'ai-post-scheduler'),
            array($this->ui, 'indexer_publish_execution_timing_field_callback'),
            'aips-settings',
            'aips_ai_scope_section'
        );

        add_settings_field(
            'aips_indexer_batch_config',
            __('Batch Queue & Quota Pause', 'ai-post-scheduler'),
            array($this->ui, 'indexer_batch_config_field_callback'),
            'aips-settings',
            'aips_ai_scope_section'
        );

        add_settings_field(
            'aips_indexer_error_cooldown',
            __('Rate Limit Error Auto-Cooldown', 'ai-post-scheduler'),
            array($this->ui, 'indexer_error_cooldown_field_callback'),
            'aips-settings',
            'aips_ai_scope_section'
        );

        add_settings_field(
            'aips_indexer_verbose_history',
            __('Activity Logging', 'ai-post-scheduler'),
            array($this->ui, 'indexer_verbose_history_field_callback'),
            'aips-settings',
            'aips_ai_scope_section'
        );

        add_settings_field(
            'aips_embeddings_rate_limits',
            __('Rate Limits & Quota Protections', 'ai-post-scheduler'),
            array($this->ui, 'embeddings_rate_limits_field_callback'),
            'aips-settings',
            'aips_ai_scope_section'
        );

        add_settings_field(
            'aips_indexer_similarity_threshold',
            __('Related Posts Similarity Threshold', 'ai-post-scheduler'),
            array($this->ui, 'indexer_similarity_threshold_field_callback'),
            'aips-settings',
            'aips_ai_scope_section'
        );

        add_settings_field(
            'aips_indexer_post_cluster_threshold',
            __('Post Cluster Similarity Threshold', 'ai-post-scheduler'),
            array($this->ui, 'indexer_post_cluster_threshold_field_callback'),
            'aips-settings',
            'aips_ai_scope_section'
        );

        // -----------------------------------------------------------------------
        // Card 5: Frontend Related Posts Engine
        // -----------------------------------------------------------------------
        add_settings_section(
            'aips_ai_related_posts_section',
            __('Frontend Related Posts Engine', 'ai-post-scheduler'),
            array($this->ui, 'ai_related_posts_section_callback'),
            'aips-settings'
        );

        add_settings_field(
            'aips_related_posts_enabled',
            __('Enable Related Posts Engine', 'ai-post-scheduler'),
            array($this->ui, 'related_posts_enabled_field_callback'),
            'aips-settings',
            'aips_ai_related_posts_section'
        );

        add_settings_field(
            'aips_related_posts_auto_append',
            __('Auto-Append to Post Content', 'ai-post-scheduler'),
            array($this->ui, 'related_posts_auto_append_field_callback'),
            'aips-settings',
            'aips_ai_related_posts_section'
        );

        add_settings_field(
            'aips_related_posts_heading',
            __('Section Heading', 'ai-post-scheduler'),
            array($this->ui, 'related_posts_heading_field_callback'),
            'aips-settings',
            'aips_ai_related_posts_section'
        );

        add_settings_field(
            'aips_related_posts_count_layout',
            __('Default Count & Layout', 'ai-post-scheduler'),
            array($this->ui, 'related_posts_count_layout_field_callback'),
            'aips-settings',
            'aips_ai_related_posts_section'
        );

        add_settings_field(
            'aips_related_posts_shortcode',
            __('Shortcode & Block Integration', 'ai-post-scheduler'),
            array($this->ui, 'related_posts_shortcode_field_callback'),
            'aips-settings',
            'aips_ai_related_posts_section'
        );

        // -----------------------------------------------------------------------
        // Card 6: Semantic Duplicate Detection & Gatekeeper Guard
        // -----------------------------------------------------------------------
        add_settings_section(
            'aips_ai_deduplication_section',
            __('Semantic Duplicate Detection & Gatekeeper Guard', 'ai-post-scheduler'),
            array($this->ui, 'ai_deduplication_section_callback'),
            'aips-settings'
        );

        add_settings_field(
            'aips_deduplication_mode',
            __('Gatekeeper Action on Duplicate', 'ai-post-scheduler'),
            array($this->ui, 'deduplication_mode_field_callback'),
            'aips-settings',
            'aips_ai_deduplication_section'
        );

        add_settings_field(
            'aips_deduplication_threshold',
            __('Duplicate Similarity Threshold', 'ai-post-scheduler'),
            array($this->ui, 'deduplication_threshold_field_callback'),
            'aips-settings',
            'aips_ai_deduplication_section'
        );

        // -----------------------------------------------------------------------
        // Authors Section: Global Topic Approval & Semantic Gate
        // -----------------------------------------------------------------------
        add_settings_section(
            'aips_authors_section',
            __('Global Author Topic Policy & Semantic Gate', 'ai-post-scheduler'),
            array($this->ui, 'authors_section_callback'),
            'aips-settings'
        );

        add_settings_field(
            'aips_author_topic_auto_approval_mode',
            __('Default Auto-Approval Mode', 'ai-post-scheduler'),
            array($this->ui, 'author_topic_auto_approval_mode_field_callback'),
            'aips-settings',
            'aips_authors_section'
        );

        add_settings_field(
            'aips_author_topic_auto_approval_min_score',
            __('Minimum Niche Relevance %', 'ai-post-scheduler'),
            array($this->ui, 'author_topic_auto_approval_min_score_field_callback'),
            'aips-settings',
            'aips_authors_section'
        );

        add_settings_field(
            'aips_author_topic_auto_approval_max_similarity',
            __('Maximum Duplicate Ceiling', 'ai-post-scheduler'),
            array($this->ui, 'author_topic_auto_approval_max_similarity_field_callback'),
            'aips-settings',
            'aips_authors_section'
        );

        add_settings_field(
            'aips_author_topic_auto_approval_fallback',
            __('Sub-Threshold Handling', 'ai-post-scheduler'),
            array($this->ui, 'author_topic_auto_approval_fallback_field_callback'),
            'aips-settings',
            'aips_authors_section'
        );

        // -----------------------------------------------------------------------
        // Feedback section: Topic Similarity Threshold
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
        // API Keys section: Unsplash Access Key
        // -----------------------------------------------------------------------
        add_settings_section(
            'aips_api_keys_section',
            __('API Keys', 'ai-post-scheduler'),
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

        // -----------------------------------------------------------------------
        // Developers section: Enable Logging, Developer Mode
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

        // -----------------------------------------------------------------------
        // Site-wide Content Strategy settings
        //
        // Options are defined via self::get_content_strategy_options(), so the
        // full list is maintained in ONE place. Both settings registration here
        // and AIPS_Site_Context::get() read from that shared list — no duplicates.
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

        // -----------------------------------------------------------------------
        // Cache section: Driver selection + per-driver configuration.
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
