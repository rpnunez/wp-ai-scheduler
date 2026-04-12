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
     * Register plugin settings and fields.
     *
     * Defines the settings section and fields for general configuration including
     * post status, category, AI model, retries, and logging.
     *
     * @return void
     */
    public function register_settings() {
        $defaults = AIPS_Config::get_instance()->get_default_options();

        register_setting('aips_settings', 'aips_default_post_status', array(
            'sanitize_callback' => 'sanitize_text_field',
            'default'           => $defaults['aips_default_post_status'],
        ));
        register_setting('aips_settings', 'aips_default_category', array(
            'sanitize_callback' => 'absint',
            'default'           => $defaults['aips_default_category'],
        ));
        register_setting('aips_settings', 'aips_enable_logging', array(
            'sanitize_callback' => 'absint',
            'default'           => $defaults['aips_enable_logging'],
        ));
        register_setting('aips_settings', 'aips_developer_mode', array(
            'sanitize_callback' => 'absint',
            'default'           => $defaults['aips_developer_mode'],
        ));
        register_setting('aips_settings', 'aips_enable_retry', array(
            'sanitize_callback' => 'absint',
            'default'           => $defaults['aips_enable_retry'],
        ));
        register_setting('aips_settings', 'aips_retry_max_attempts', array(
            'sanitize_callback' => 'absint',
            'default'           => $defaults['aips_retry_max_attempts'],
        ));
        register_setting('aips_settings', 'aips_retry_initial_delay', array(
            'sanitize_callback' => 'absint',
            'default'           => $defaults['aips_retry_initial_delay'],
        ));
        register_setting('aips_settings', 'aips_enable_rate_limiting', array(
            'sanitize_callback' => 'absint',
            'default'           => $defaults['aips_enable_rate_limiting'],
        ));
        register_setting('aips_settings', 'aips_rate_limit_requests', array(
            'sanitize_callback' => 'absint',
            'default'           => $defaults['aips_rate_limit_requests'],
        ));
        register_setting('aips_settings', 'aips_rate_limit_period', array(
            'sanitize_callback' => 'absint',
            'default'           => $defaults['aips_rate_limit_period'],
        ));
        register_setting('aips_settings', 'aips_enable_circuit_breaker', array(
            'sanitize_callback' => 'absint',
            'default'           => $defaults['aips_enable_circuit_breaker'],
        ));
        register_setting('aips_settings', 'aips_circuit_breaker_threshold', array(
            'sanitize_callback' => 'absint',
            'default'           => $defaults['aips_circuit_breaker_threshold'],
        ));
        register_setting('aips_settings', 'aips_circuit_breaker_timeout', array(
            'sanitize_callback' => 'absint',
            'default'           => $defaults['aips_circuit_breaker_timeout'],
        ));
        register_setting('aips_settings', 'aips_ai_model', array(
            'sanitize_callback' => 'sanitize_text_field',
            'default'           => $defaults['aips_ai_model'],
        ));
        register_setting('aips_settings', 'aips_ai_env_id', array(
            'sanitize_callback' => 'sanitize_text_field',
            'default'           => $defaults['aips_ai_env_id'],
        ));
        register_setting('aips_settings', 'aips_max_tokens_limit', array(
            'sanitize_callback' => 'absint',
            'default'           => $defaults['aips_max_tokens_limit'],
        ));
        register_setting('aips_settings', 'aips_max_tokens_title', array(
            'sanitize_callback' => array($this->ui, 'sanitize_token_budget'),
            'default'           => $defaults['aips_max_tokens_title'],
        ));
        register_setting('aips_settings', 'aips_max_tokens_excerpt', array(
            'sanitize_callback' => array($this->ui, 'sanitize_token_budget'),
            'default'           => $defaults['aips_max_tokens_excerpt'],
        ));
        register_setting('aips_settings', 'aips_max_tokens_content', array(
            'sanitize_callback' => array($this->ui, 'sanitize_token_budget'),
            'default'           => $defaults['aips_max_tokens_content'],
        ));
        register_setting('aips_settings', 'aips_unsplash_access_key', array(
            'sanitize_callback' => 'sanitize_text_field',
            'default'           => $defaults['aips_unsplash_access_key'],
        ));
        register_setting('aips_settings', 'aips_review_notifications_email', array(
            'sanitize_callback' => array($this->ui, 'sanitize_notification_emails'),
            'default'           => $defaults['aips_review_notifications_email'],
        ));
        register_setting('aips_settings', 'aips_notification_preferences', array(
            'sanitize_callback' => array($this->ui, 'sanitize_notification_preferences'),
            'default'           => $defaults['aips_notification_preferences'],
        ));
        register_setting('aips_settings', 'aips_topic_similarity_threshold', array(
            'sanitize_callback' => array($this->ui, 'sanitize_similarity_threshold'),
            'default'           => $defaults['aips_topic_similarity_threshold'],
        ));
        
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
        // AI section: AI Model, Environment ID
        // -----------------------------------------------------------------------
        add_settings_section(
            'aips_ai_section',
            __('AI Settings', 'ai-post-scheduler'),
            array($this->ui, 'ai_section_callback'),
            'aips-settings'
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
        $cs_options = self::get_content_strategy_options();
        foreach ($cs_options as $option_key => $meta) {
            register_setting('aips_settings', $option_key, array(
                'sanitize_callback' => $meta['sanitize_callback'],
                'default'           => $meta['default'],
            ));
        }

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
        $defaults = AIPS_Config::get_instance()->get_default_options();

        register_setting('aips_settings', 'aips_cache_driver', array(
            'sanitize_callback' => array($this->ui, 'sanitize_cache_driver'),
            'default'           => $defaults['aips_cache_driver'],
        ));
        register_setting('aips_settings', 'aips_cache_db_prefix', array(
            'sanitize_callback' => 'sanitize_text_field',
            'default'           => $defaults['aips_cache_db_prefix'],
        ));
        register_setting('aips_settings', 'aips_cache_default_ttl', array(
            'sanitize_callback' => 'absint',
            'default'           => $defaults['aips_cache_default_ttl'],
        ));
        register_setting('aips_settings', 'aips_cache_redis_host', array(
            'sanitize_callback' => 'sanitize_text_field',
            'default'           => $defaults['aips_cache_redis_host'],
        ));
        register_setting('aips_settings', 'aips_cache_redis_port', array(
            'sanitize_callback' => 'absint',
            'default'           => $defaults['aips_cache_redis_port'],
        ));
        register_setting('aips_settings', 'aips_cache_redis_password', array(
            'sanitize_callback' => 'sanitize_text_field',
            'default'           => $defaults['aips_cache_redis_password'],
        ));
        register_setting('aips_settings', 'aips_cache_redis_db', array(
            'sanitize_callback' => 'absint',
            'default'           => $defaults['aips_cache_redis_db'],
        ));
        register_setting('aips_settings', 'aips_cache_redis_prefix', array(
            'sanitize_callback' => 'sanitize_text_field',
            'default'           => $defaults['aips_cache_redis_prefix'],
        ));
        register_setting('aips_settings', 'aips_cache_redis_timeout', array(
            'sanitize_callback' => 'absint',
            'default'           => $defaults['aips_cache_redis_timeout'],
        ));

        add_settings_section(
            'aips_cache_section',
            __('Cache Settings', 'ai-post-scheduler'),
            array($this->ui, 'cache_section_callback'),
            'aips-settings'
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
            'aips_cache_redis_host',
            __('Redis Host', 'ai-post-scheduler'),
            array($this->ui, 'cache_redis_host_field_callback'),
            'aips-settings',
            'aips_cache_section'
        );

        add_settings_field(
            'aips_cache_redis_port',
            __('Redis Port', 'ai-post-scheduler'),
            array($this->ui, 'cache_redis_port_field_callback'),
            'aips-settings',
            'aips_cache_section'
        );

        add_settings_field(
            'aips_cache_redis_password',
            __('Redis Password', 'ai-post-scheduler'),
            array($this->ui, 'cache_redis_password_field_callback'),
            'aips-settings',
            'aips_cache_section'
        );

        add_settings_field(
            'aips_cache_redis_db',
            __('Redis Database Index', 'ai-post-scheduler'),
            array($this->ui, 'cache_redis_db_field_callback'),
            'aips-settings',
            'aips_cache_section'
        );

        add_settings_field(
            'aips_cache_redis_prefix',
            __('Redis Key Prefix', 'ai-post-scheduler'),
            array($this->ui, 'cache_redis_prefix_field_callback'),
            'aips-settings',
            'aips_cache_section'
        );

        add_settings_field(
            'aips_cache_redis_timeout',
            __('Redis Connection Timeout (seconds)', 'ai-post-scheduler'),
            array($this->ui, 'cache_redis_timeout_field_callback'),
            'aips-settings',
            'aips_cache_section'
        );

        // -----------------------------------------------------------------------
        // Feature Flags section
        //
        // All flags are stored together in a single 'aips_feature_flags' option
        // (an associative array of flag_name => bool). The registry in
        // AIPS_Config::get_available_features() is the single source of truth;
        // each flag defined there gets a settings field here automatically.
        // -----------------------------------------------------------------------
        register_setting('aips_settings', 'aips_feature_flags', array(
            'sanitize_callback' => array($this->ui, 'sanitize_feature_flags'),
            'default'           => array(),
        ));

        add_settings_section(
            'aips_feature_flags_section',
            __('Feature Flags', 'ai-post-scheduler'),
            array($this->ui, 'feature_flags_section_callback'),
            'aips-settings'
        );

        $available_features = AIPS_Config::get_instance()->get_available_features();
        foreach ($available_features as $flag_name => $flag_meta) {
            add_settings_field(
                'aips_feature_flag_' . $flag_name,
                esc_html($flag_meta['name']),
                array($this->ui, 'feature_flag_field_callback'),
                'aips-settings',
                'aips_feature_flags_section',
                array(
                    'flag'        => $flag_name,
                    'description' => $flag_meta['description'],
                )
            );
        }
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
