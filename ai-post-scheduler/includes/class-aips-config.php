<?php
/**
 * Configuration Manager
 *
 * Centralized configuration management for the plugin.
 * Provides access to default options, constants, and feature flags.
 *
 * @package AI_Post_Scheduler
 * @since 1.5.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class AIPS_Config
 *
 * Singleton class for managing plugin configuration.
 * Centralizes default options, constants, and feature flags.
 */
class AIPS_Config {
    
    /**
     * @var AIPS_Config Singleton instance
     */
    private static $instance = null;
    
    /**
     * @var array Feature flags cache
     */
    private $feature_flags = array();

    /**
     * @var AIPS_Cache Per-request cache for get_option() calls.
     */
    private $cache = null;

    /**
     * @var object Sentinel stored in the cache when the resolved option
     *             value is null. Required because AIPS_Cache::has() uses
     *             get() !== null internally, so storing a plain null would
     *             always look like a cache miss. The sentinel lets has()
     *             return true for genuinely cached null values.
     */
    private $null_sentinel = null;
    
    /**
     * Get singleton instance.
     *
     * @return AIPS_Config
     */
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Private constructor to enforce singleton pattern.
     */
    private function __construct() {
        $this->cache         = AIPS_Cache_Factory::named('aips_config', 'array');
        $this->null_sentinel = new stdClass();
        $this->load_feature_flags();
        $this->register_option_cache_hooks();
    }

    /**
     * Register WordPress hooks that invalidate specific cache entries
     * whenever an option is changed outside of set_option().
     *
     * This ensures external update_option() / delete_option() calls
     * (including those in tests) are always reflected on the next read.
     *
     * @return void
     */
    private function register_option_cache_hooks() {
        $this->reregister_option_cache_hooks();
    }

    /**
     * (Re-)register the option-cache invalidation action callbacks.
     *
     * Called once from the constructor and again by the test bootstrap after
     * each test tears down and resets all hooks, so that the singleton's cache
     * invalidation keeps working across test methods.
     *
     * @return void
     */
    public function reregister_option_cache_hooks() {
        // Registered with the default accepted-args count of 1, so WordPress
        // passes only $option (the option name) to the callback. The extra
        // arguments some hooks carry (e.g. $old_value / $value for
        // updated_option) are never delivered and are not needed here.
        $invalidate = function($option) {
            if ($this->cache !== null) {
                $this->cache->delete($option);
            }
        };
        add_action('updated_option', $invalidate);
        add_action('added_option',   $invalidate);
        add_action('deleted_option', $invalidate);
    }
    
    // ========================================
    // Default Options
    // ========================================
    
    /**
     * Get default plugin options.
     *
     * @return array Default options array.
     */
    public function get_default_options() {
        return array(
			// Generated posts / export thresholds
			'generated_posts_log_threshold_tmpfile' => 200,
			'generated_posts_log_threshold_client' => 20,
			'history_export_max_records' => 10000,
            // Plugin state / versioning
            'aips_db_version' => '0',
            'aips_onboarding_completed' => false,
            'aips_log_secret' => '',
            // AI model
            'aips_ai_model' => '',
            'aips_ai_env_id' => '',
            'aips_max_tokens_limit' => 16000,
            'aips_max_tokens_title' => 150,
            'aips_max_tokens_excerpt' => 300,
            'aips_max_tokens_content' => 4000,
            'aips_temperature' => 0.7,
            // Post defaults
            'aips_default_post_status' => 'draft',
            'aips_default_category' => 0,
            'aips_default_post_author' => 1,
            // General
            'aips_unsplash_access_key' => '',
            'aips_enable_logging' => true,
            'aips_developer_mode' => false,
            'aips_log_retention_days' => 30,
            'aips_topic_similarity_threshold' => 0.8,
            // Notifications
            'aips_review_notifications_email' => '',
            'aips_notification_preferences' => array(
                'generation_failed' => 'both',
                'quota_alert' => 'both',
                'integration_error' => 'both',
                'scheduler_error' => 'both',
                'system_error' => 'both',
				'template_generated' => 'db',
				'manual_generation_completed' => 'db',
				'post_ready_for_review' => 'db',
				'post_rejected' => 'db',
				'partial_generation_completed' => 'db',
            ),
            // Notification digest state (runtime markers, not user-configurable)
            'aips_notif_daily_digest_last_sent' => '',
            'aips_notif_weekly_summary_last_sent' => '',
            'aips_notif_monthly_report_last_sent' => '',
            // Resilience
            'aips_enable_retry' => false,
            'aips_retry_max_attempts' => 3,
            'aips_retry_initial_delay' => 1,
            'aips_enable_rate_limiting' => false,
            'aips_rate_limit_requests' => 10,
            'aips_rate_limit_period' => 60,
            'aips_enable_circuit_breaker' => false,
            'aips_circuit_breaker_threshold' => 5,
            'aips_circuit_breaker_timeout' => 300,
            // Site content strategy defaults (must match AIPS_Settings::get_content_strategy_options()).
            'aips_site_niche' => '',
            'aips_site_target_audience' => '',
            'aips_site_content_goals' => '',
            'aips_default_article_structure_id' => 0,
            'aips_site_brand_voice' => '',
            'aips_site_content_language' => 'en',
            'aips_site_content_guidelines' => '',
            'aips_site_excluded_topics' => '',
            // Cache framework settings.
            'aips_cache_driver'         => 'array',
            'aips_cache_db_prefix'      => '',
            'aips_cache_default_ttl'    => 3600,
            'aips_cache_redis_host'     => '127.0.0.1',
            'aips_cache_redis_port'     => 6379,
            'aips_cache_redis_password' => '',
            'aips_cache_redis_db'       => 0,
            'aips_cache_redis_prefix'   => 'aips',
            'aips_cache_redis_timeout'  => 2,
            // Research
            'aips_research_niches' => array(),
            // Queue manager: 'wpcron' (default) or 'action_scheduler'.
            'aips_queue_manager' => 'wpcron',
            // Telemetry
            'aips_enable_telemetry' => false,
        );
    }
    
    /**
     * Get a specific option value with fallback to default.
     *
     * Resolved values are stored in a per-request in-memory cache so that
     * repeated reads of the same key within a single request do not trigger
     * additional get_option() calls.
     *
     * When an explicit $default is supplied by the caller the result is NOT
     * cached (to avoid polluting the cache with ad-hoc fallback values that
     * differ from the authoritative AIPS_Config defaults).
     *
     * @param string $option_name Option name.
     * @param mixed  $default     Optional. Default value if option not found.
     * @return mixed Option value or default.
     */
    public function get_option($option_name, $default = null) {
        // Use cached value only when no caller-supplied default is in play.
        if ($default === null && $this->cache !== null && $this->cache->has($option_name)) {
            $cached = $this->cache->get($option_name);
            // A stored null sentinel means the resolved value is null.
            return ($cached === $this->null_sentinel) ? null : $cached;
        }

        // Use a sentinel to distinguish "option not in database" from a stored
        // boolean false — WordPress returns false for both cases with a plain
        // get_option() call, which would silently override legitimate false values.
        static $not_set;
        if (!isset($not_set)) {
            $not_set = new stdClass();
        }

        $value = get_option($option_name, $not_set);

        if ($value === $not_set) {
            // Option is not stored in the database.
            if ($default === null) {
                $defaults = $this->get_default_options();
                $value    = isset($defaults[$option_name]) ? $defaults[$option_name] : null;
                // Cache only authoritative defaults; use the null sentinel when
                // the resolved value is null so that has() returns true next time.
                if ($this->cache !== null) {
                    $this->cache->set($option_name, ($value === null) ? $this->null_sentinel : $value);
                }
            } else {
                // Caller supplied a fallback — honour it but do NOT cache.
                $value = $default;
            }
        } else {
            // Option exists in the database — always cache the live value;
            // use the null sentinel when the DB value is null.
            if ($this->cache !== null) {
                $this->cache->set($option_name, ($value === null) ? $this->null_sentinel : $value);
            }
        }

        return $value;
    }

    /**
     * Set an option value and invalidate the per-request cache for that key.
     *
     * @param string    $option_name Option name.
     * @param mixed     $value       Option value.
     * @param bool|null $autoload    Optional. Whether to load the option when WordPress starts up.
     *                               Accepts 'yes'|true to enable autoloading, 'no'|false to disable,
     *                               or null to leave the existing setting unchanged (WordPress default).
     * @return bool True on success, false on failure.
     */
    public function set_option($option_name, $value, $autoload = null) {
        if ($this->cache !== null) {
            $this->cache->delete($option_name);
        }
        return update_option($option_name, $value, $autoload);
    }

    /**
     * Check whether an option key is present in the database.
     *
     * Unlike get_option(), this method returns false for any key that has no
     * stored value — it never falls back to the plugin's registered defaults.
     * This is useful when you need to distinguish "option not set at all" from
     * "option set to false/0/empty string".
     *
     * @param string $option_name Option name.
     * @return bool True when the option exists in the database, false otherwise.
     */
    public function has_option($option_name) {
        static $not_set;
        if (!isset($not_set)) {
            $not_set = new stdClass();
        }
        return get_option($option_name, $not_set) !== $not_set;
    }

    /**
     * Flush the entire per-request option cache.
     *
     * Useful in tests or after a batch of update_option() calls made outside
     * of set_option() that need to be reflected on the next read.
     *
     * @return void
     */
    public function flush_option_cache() {
        if ($this->cache !== null) {
            $this->cache->flush();
        }
    }

    /**
     * Return a named runtime cache instance.
     *
     * This is a thin wrapper around the cache factory so classes that already
     * depend on AIPS_Config can request a scoped cache without managing the
     * factory directly.
     *
     * @param string $name   Cache namespace.
     * @param string $driver Cache driver. Defaults to the request-scoped array driver.
     * @return AIPS_Cache
     */
    public function get_runtime_cache($name, $driver = 'array') {
        return AIPS_Cache_Factory::named($name, $driver);
    }
    
    // ========================================
    // Constants
    // ========================================
    
    /**
     * Get plugin version.
     *
     * @return string Plugin version.
     */
    public function get_version() {
        return AIPS_VERSION;
    }
    
    /**
     * Get plugin directory path.
     *
     * @return string Plugin directory path.
     */
    public function get_plugin_dir() {
        return AIPS_PLUGIN_DIR;
    }
    
    /**
     * Get plugin URL.
     *
     * @return string Plugin URL.
     */
    public function get_plugin_url() {
        return AIPS_PLUGIN_URL;
    }
    
    /**
     * Check if debug mode is enabled.
     *
     * @return bool True if debug mode is enabled.
     */
    public function is_debug_mode() {
        return defined('AIPS_DEBUG') && AIPS_DEBUG;
    }
    
    /**
     * Get AI model configuration.
     *
     * Returns all settings needed to configure an AI generation request,
     * including the model identifier, optional environment/project ID,
     * token limit, and temperature.
     *
     * @return array AI model configuration with keys:
     *               'model'            (string) AI model identifier.
     *               'env_id'           (string) Optional AI Engine environment ID.
     *               'max_tokens_limit' (int)    Hard cap on total tokens per request.
     *               'temperature'      (float)  Sampling temperature (creativity).
     */
    public function get_ai_config() {
        return array(
            'model'            => (string) $this->get_option('aips_ai_model'),
            'env_id'           => (string) $this->get_option('aips_ai_env_id'),
            'max_tokens_limit' => (int) $this->get_option('aips_max_tokens_limit'),
            'temperature'      => (float) $this->get_option('aips_temperature'),
        );
    }
    
    /**
     * Get retry configuration.
     *
     * @return array Retry configuration.
     */
    public function get_retry_config() {
        return array(
            'enabled' => (bool) $this->get_option('aips_enable_retry'),
            'max_attempts' => (int) $this->get_option('aips_retry_max_attempts'),
            'initial_delay' => (int) $this->get_option('aips_retry_initial_delay'),
            'exponential' => true,
            'jitter' => true,
        );
    }
    
    /**
     * Get rate limiting configuration.
     *
     * @return array Rate limiting configuration.
     */
    public function get_rate_limit_config() {
        return array(
            'enabled' => (bool) $this->get_option('aips_enable_rate_limiting'),
            'requests' => (int) $this->get_option('aips_rate_limit_requests'),
            'period' => (int) $this->get_option('aips_rate_limit_period'),
        );
    }
    
    /**
     * Get circuit breaker configuration.
     *
     * @return array Circuit breaker configuration.
     */
    public function get_circuit_breaker_config() {
        return array(
            'enabled' => (bool) $this->get_option('aips_enable_circuit_breaker'),
            'failure_threshold' => (int) $this->get_option('aips_circuit_breaker_threshold'),
            'timeout' => (int) $this->get_option('aips_circuit_breaker_timeout'),
        );
    }
    
    /**
     * Get logging configuration.
     *
     * @return array Logging configuration.
     */
    public function get_logging_config() {
        return array(
            'enabled' => (bool) $this->get_option('aips_enable_logging'),
            'retention_days' => (int) $this->get_option('aips_log_retention_days'),
            'level' => $this->is_debug_mode() ? 'debug' : 'info',
        );
    }

    /**
     * Get token limits configuration for AI content generation.
     *
     * @return array Token limits configuration.
     */
    public function get_token_config() {
        return array(
            'max_tokens_limit'   => (int) $this->get_option('aips_max_tokens_limit'),
            'max_tokens_title'   => (int) $this->get_option('aips_max_tokens_title'),
            'max_tokens_excerpt' => (int) $this->get_option('aips_max_tokens_excerpt'),
            'max_tokens_content' => (int) $this->get_option('aips_max_tokens_content'),
        );
    }

    /**
     * Get post creation defaults configuration.
     *
     * @return array Post defaults configuration.
     */
    public function get_post_defaults_config() {
        return array(
            'post_status' => (string) $this->get_option('aips_default_post_status'),
            'category'    => (int) $this->get_option('aips_default_category'),
            'post_author' => (int) $this->get_option('aips_default_post_author'),
        );
    }

    /**
     * Get notification configuration.
     *
     * @return array Notification configuration.
     */
    public function get_notification_config() {
        $preferences = $this->get_option('aips_notification_preferences');
        return array(
            'review_email'  => (string) $this->get_option('aips_review_notifications_email'),
            'preferences'   => is_array($preferences) ? $preferences : array(),
        );
    }

    /**
     * Get notification digest scheduling markers.
     *
     * These runtime markers record the last date/period for which each periodic
     * summary was sent, allowing cron handlers and the system-status page to
     * determine whether a summary is due. They are read together in multiple
     * places and updated individually via set_option().
     *
     * @return array Notification digest configuration with keys:
     *               'daily_last_sent'   (string) ISO date (Y-m-d) of the last daily digest.
     *               'weekly_last_sent'  (string) ISO year-week (o-W) of the last weekly summary.
     *               'monthly_last_sent' (string) ISO year-month (Y-m) of the last monthly report.
     */
    public function get_notification_digest_config() {
        return array(
            'daily_last_sent'   => (string) $this->get_option('aips_notif_daily_digest_last_sent'),
            'weekly_last_sent'  => (string) $this->get_option('aips_notif_weekly_summary_last_sent'),
            'monthly_last_sent' => (string) $this->get_option('aips_notif_monthly_report_last_sent'),
        );
    }

    /**
     * Get cache framework configuration.
     *
     * Returns all settings that configure the plugin's cache layer, including
     * driver selection, DB prefix, default TTL, and Redis connection details.
     *
     * Note: AIPS_Cache_Factory::make_driver() reads these settings via direct
     * get_option() calls to avoid a bootstrapping circular dependency (AIPS_Config
     * internally relies on AIPS_Cache_Factory). All other callers that need these
     * values should use this accessor instead.
     *
     * @return array Cache configuration with keys:
     *               'driver'         (string) Cache driver name ('array', 'db', 'redis', 'wp_object_cache', 'session').
     *               'db_prefix'      (string) Table prefix for the DB driver.
     *               'default_ttl'    (int)    Default time-to-live in seconds.
     *               'redis_host'     (string) Redis hostname.
     *               'redis_port'     (int)    Redis port.
     *               'redis_password' (string) Redis auth password (empty = no auth).
     *               'redis_db'       (int)    Redis database index.
     *               'redis_prefix'   (string) Key prefix for Redis entries.
     *               'redis_timeout'  (float)  Connection timeout in seconds.
     */
    public function get_cache_config() {
        return array(
            'driver'         => (string) $this->get_option('aips_cache_driver'),
            'db_prefix'      => (string) $this->get_option('aips_cache_db_prefix'),
            'default_ttl'    => (int)    $this->get_option('aips_cache_default_ttl'),
            'redis_host'     => (string) $this->get_option('aips_cache_redis_host'),
            'redis_port'     => (int)    $this->get_option('aips_cache_redis_port'),
            'redis_password' => (string) $this->get_option('aips_cache_redis_password'),
            'redis_db'       => (int)    $this->get_option('aips_cache_redis_db'),
            'redis_prefix'   => (string) $this->get_option('aips_cache_redis_prefix'),
            'redis_timeout'  => (float)  $this->get_option('aips_cache_redis_timeout'),
        );
    }

    /**
     * Get site content strategy configuration.
     *
     * @return array Site content strategy configuration.
     */
    public function get_site_content_config() {
        return array(
            'niche'               => (string) $this->get_option('aips_site_niche'),
            'target_audience'     => (string) $this->get_option('aips_site_target_audience'),
            'content_goals'       => (string) $this->get_option('aips_site_content_goals'),
            'default_article_structure_id' => (int) $this->get_option('aips_default_article_structure_id'),
            'brand_voice'         => (string) $this->get_option('aips_site_brand_voice'),
            'content_language'    => (string) $this->get_option('aips_site_content_language'),
            'content_guidelines'  => (string) $this->get_option('aips_site_content_guidelines'),
            'excluded_topics'     => (string) $this->get_option('aips_site_excluded_topics'),
        );
    }

    /**
     * Get general plugin configuration.
     *
     * @return array General configuration.
     */
    public function get_general_config() {
        return array(
            'developer_mode'           => (bool) $this->get_option('aips_developer_mode'),
            'unsplash_access_key'      => (string) $this->get_option('aips_unsplash_access_key'),
            'topic_similarity_threshold' => (float) $this->get_option('aips_topic_similarity_threshold'),
        );
    }
    
    // ========================================
    // Feature Flags
    // ========================================
    
    /**
     * Load feature flags from database.
     */
    private function load_feature_flags() {
        $this->feature_flags = get_option('aips_feature_flags', array());
    }
    
    /**
     * Check if a feature is enabled.
     *
     * @param string $feature_name Feature name.
     * @param bool   $default      Optional. Default value if flag not set. Default false.
     * @return bool True if feature is enabled.
     */
    public function is_feature_enabled($feature_name, $default = false) {
        if (isset($this->feature_flags[$feature_name])) {
            return (bool) $this->feature_flags[$feature_name];
        }
        
        // Check for environment variable override
        $env_var = 'AIPS_FEATURE_' . strtoupper($feature_name);
        if (defined($env_var)) {
            return (bool) constant($env_var);
        }
        
        return $default;
    }
    
    /**
     * Enable a feature.
     *
     * @param string $feature_name Feature name.
     * @return bool True on success.
     */
    public function enable_feature($feature_name) {
        $this->feature_flags[$feature_name] = true;
        return update_option('aips_feature_flags', $this->feature_flags);
    }
    
    /**
     * Disable a feature.
     *
     * @param string $feature_name Feature name.
     * @return bool True on success.
     */
    public function disable_feature($feature_name) {
        $this->feature_flags[$feature_name] = false;
        return update_option('aips_feature_flags', $this->feature_flags);
    }
    
    /**
     * Get all feature flags.
     *
     * @return array Feature flags array.
     */
    public function get_all_feature_flags() {
        return $this->feature_flags;
    }
    
    /**
     * Get available features list with descriptions.
     *
     * @return array Available features with descriptions.
     */
    public function get_available_features() {
        return array(
            'advanced_retry' => array(
                'name' => __('Advanced Retry Logic', 'ai-post-scheduler'),
                'description' => __('Enable exponential backoff and circuit breaker for AI requests.', 'ai-post-scheduler'),
                'default' => true,
            ),
            'rate_limiting' => array(
                'name' => __('Rate Limiting', 'ai-post-scheduler'),
                'description' => __('Limit the number of AI requests per time period.', 'ai-post-scheduler'),
                'default' => false,
            ),
            'event_system' => array(
                'name' => __('Event System', 'ai-post-scheduler'),
                'description' => __('Enable event dispatching for better extensibility.', 'ai-post-scheduler'),
                'default' => true,
            ),
            'performance_monitoring' => array(
                'name' => __('Performance Monitoring', 'ai-post-scheduler'),
                'description' => __('Track and log performance metrics.', 'ai-post-scheduler'),
                'default' => false,
            ),
            'batch_generation' => array(
                'name' => __('Batch Generation', 'ai-post-scheduler'),
                'description' => __('Generate multiple posts in a single batch.', 'ai-post-scheduler'),
                'default' => true,
            ),
        );
    }
    
    // ========================================
    // Environment Helpers
    // ========================================
    
    /**
     * Check if running in production environment.
     *
     * @return bool True if in production.
     */
    public function is_production() {
        if (defined('WP_ENVIRONMENT_TYPE')) {
            return WP_ENVIRONMENT_TYPE === 'production';
        }
        return !$this->is_debug_mode();
    }
    
    /**
     * Check if running in development environment.
     *
     * @return bool True if in development.
     */
    public function is_development() {
        if (defined('WP_ENVIRONMENT_TYPE')) {
            return in_array(WP_ENVIRONMENT_TYPE, array('development', 'local'));
        }
        return $this->is_debug_mode();
    }
    
    /**
     * Get environment name.
     *
     * @return string Environment name.
     */
    public function get_environment() {
        if (defined('WP_ENVIRONMENT_TYPE')) {
            return WP_ENVIRONMENT_TYPE;
        }
        return $this->is_debug_mode() ? 'development' : 'production';
    }
}
