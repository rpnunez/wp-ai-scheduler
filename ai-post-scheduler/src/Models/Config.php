<?php
namespace AIPS\Models;

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
 * Class Config
 *
 * Singleton class for managing plugin configuration.
 * Centralizes default options, constants, and feature flags.
 */
class Config {
    
    /**
     * @var AIPS_Config Singleton instance
     */
    private static $instance = null;
    
    /**
     * @var array Feature flags cache
     */
    private $feature_flags = array();
    
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
        $this->load_feature_flags();
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
            'aips_ai_model' => '',
            'aips_max_tokens' => 2000,
            'aips_temperature' => 0.7,
            'aips_default_post_status' => 'draft',
            'aips_default_post_author' => 1,
            'aips_enable_logging' => true,
            'aips_log_retention_days' => 30,
            'aips_enable_retry' => true,
            'aips_retry_max_attempts' => 3,
            'aips_retry_initial_delay' => 1,
            'aips_enable_rate_limiting' => false,
            'aips_rate_limit_requests' => 10,
            'aips_rate_limit_period' => 60,
            'aips_enable_circuit_breaker' => false,
            'aips_circuit_breaker_threshold' => 5,
            'aips_circuit_breaker_timeout' => 300,
        );
    }
    
    /**
     * Get a specific option value with fallback to default.
     *
     * @param string $option_name Option name.
     * @param mixed  $default     Optional. Default value if option not found.
     * @return mixed Option value or default.
     */
    public function get_option($option_name, $default = null) {
        $value = get_option($option_name);
        
        if ($value === false && $default === null) {
            $defaults = $this->get_default_options();
            return isset($defaults[$option_name]) ? $defaults[$option_name] : null;
        }
        
        return $value !== false ? $value : $default;
    }
    
    /**
     * Set an option value.
     *
     * @param string $option_name Option name.
     * @param mixed  $value       Option value.
     * @return bool True on success, false on failure.
     */
    public function set_option($option_name, $value) {
        return update_option($option_name, $value);
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
        return defined('AIPS_VERSION') ? AIPS_VERSION : '1.5.0';
    }
    
    /**
     * Get plugin directory path.
     *
     * @return string Plugin directory path.
     */
    public function get_plugin_dir() {
        return defined('AIPS_PLUGIN_DIR') ? AIPS_PLUGIN_DIR : plugin_dir_path(__FILE__);
    }
    
    /**
     * Get plugin URL.
     *
     * @return string Plugin URL.
     */
    public function get_plugin_url() {
        return defined('AIPS_PLUGIN_URL') ? AIPS_PLUGIN_URL : plugin_dir_url(__FILE__);
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
     * @return array AI model configuration.
     */
    public function get_ai_config() {
        return array(
            'model' => $this->get_option('aips_ai_model', ''),
            'max_tokens' => (int) $this->get_option('aips_max_tokens', 2000),
            'temperature' => (float) $this->get_option('aips_temperature', 0.7),
        );
    }
    
    /**
     * Get retry configuration.
     *
     * @return array Retry configuration.
     */
    public function get_retry_config() {
        return array(
            //@TODO: Intentionally disabled due to making too many requests to the AI Engine
            'enabled' => false,//(bool) $this->get_option('aips_enable_retry', true),
            'max_attempts' => (int) $this->get_option('aips_retry_max_attempts', 3),
            'initial_delay' => (int) $this->get_option('aips_retry_initial_delay', 1),
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
            'enabled' => (bool) $this->get_option('aips_enable_rate_limiting', false),
            'requests' => (int) $this->get_option('aips_rate_limit_requests', 10),
            'period' => (int) $this->get_option('aips_rate_limit_period', 60),
        );
    }
    
    /**
     * Get circuit breaker configuration.
     *
     * @return array Circuit breaker configuration.
     */
    public function get_circuit_breaker_config() {
        return array(
            'enabled' => (bool) $this->get_option('aips_enable_circuit_breaker', false),
            'failure_threshold' => (int) $this->get_option('aips_circuit_breaker_threshold', 5),
            'timeout' => (int) $this->get_option('aips_circuit_breaker_timeout', 300),
        );
    }
    
    /**
     * Get logging configuration.
     *
     * @return array Logging configuration.
     */
    public function get_logging_config() {
        return array(
            'enabled' => (bool) $this->get_option('aips_enable_logging', true),
            'retention_days' => (int) $this->get_option('aips_log_retention_days', 30),
            'level' => $this->is_debug_mode() ? 'debug' : 'info',
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
