<?php
/**
 * Configuration Layer
 *
 * Centralizes plugin configuration including default options, constants, and feature flags.
 * Provides a single point of truth for all configuration values.
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
 * Manages all plugin configuration in a centralized location.
 * Provides methods to get configuration values, feature flags, and default options.
 */
class AIPS_Config {
    
    /**
     * @var array Default plugin options
     */
    private static $default_options = array(
        'aips_default_post_status' => 'draft',
        'aips_default_category' => 0,
        'aips_enable_logging' => 1,
        'aips_max_retries' => 3,
        'aips_ai_model' => '',
        'aips_requests_per_minute' => 20,
        'aips_circuit_breaker_threshold' => 5,
        'aips_circuit_breaker_timeout' => 60,
        'aips_initial_retry_delay' => 1,
        'aips_max_retry_delay' => 30,
    );
    
    /**
     * @var array Feature flags for enabling/disabling features
     */
    private static $feature_flags = array(
        'enable_circuit_breaker' => true,
        'enable_rate_limiting' => true,
        'enable_retry_logic' => true,
        'enable_event_system' => true,
        'enable_performance_logging' => false,
    );
    
    /**
     * Get default plugin options.
     *
     * @return array Default options array.
     */
    public static function get_default_options() {
        return apply_filters('aips_default_options', self::$default_options);
    }
    
    /**
     * Get a specific default option value.
     *
     * @param string $key     Option key.
     * @param mixed  $default Default value if key not found.
     * @return mixed Option value.
     */
    public static function get_default_option($key, $default = null) {
        $options = self::get_default_options();
        return isset($options[$key]) ? $options[$key] : $default;
    }
    
    /**
     * Get an option value with default fallback.
     *
     * @param string $key     Option key.
     * @param mixed  $default Optional. Default value if option doesn't exist.
     * @return mixed Option value.
     */
    public static function get($key, $default = null) {
        // Try to get from WordPress options
        $value = get_option($key);
        
        // If not found, use provided default or fall back to default options
        if ($value === false) {
            if ($default !== null) {
                return $default;
            }
            return self::get_default_option($key);
        }
        
        return $value;
    }
    
    /**
     * Set an option value.
     *
     * @param string $key   Option key.
     * @param mixed  $value Option value.
     * @return bool True on success, false on failure.
     */
    public static function set($key, $value) {
        return update_option($key, $value);
    }
    
    /**
     * Check if a feature flag is enabled.
     *
     * @param string $flag Feature flag name.
     * @return bool True if enabled, false otherwise.
     */
    public static function is_feature_enabled($flag) {
        $flags = apply_filters('aips_feature_flags', self::$feature_flags);
        return isset($flags[$flag]) && $flags[$flag] === true;
    }
    
    /**
     * Get all feature flags.
     *
     * @return array Feature flags array.
     */
    public static function get_feature_flags() {
        return apply_filters('aips_feature_flags', self::$feature_flags);
    }
    
    /**
     * Get plugin constants.
     *
     * @return array Plugin constants.
     */
    public static function get_constants() {
        return array(
            'version' => AIPS_VERSION,
            'plugin_dir' => AIPS_PLUGIN_DIR,
            'plugin_url' => AIPS_PLUGIN_URL,
            'plugin_basename' => AIPS_PLUGIN_BASENAME,
        );
    }
    
    /**
     * Get retry configuration.
     *
     * @return array Retry configuration settings.
     */
    public static function get_retry_config() {
        return array(
            'max_retries' => self::get('aips_max_retries', 3),
            'initial_delay' => self::get('aips_initial_retry_delay', 1),
            'max_delay' => self::get('aips_max_retry_delay', 30),
        );
    }
    
    /**
     * Get circuit breaker configuration.
     *
     * @return array Circuit breaker configuration settings.
     */
    public static function get_circuit_breaker_config() {
        return array(
            'enabled' => self::is_feature_enabled('enable_circuit_breaker'),
            'failure_threshold' => self::get('aips_circuit_breaker_threshold', 5),
            'timeout' => self::get('aips_circuit_breaker_timeout', 60),
        );
    }
    
    /**
     * Get rate limiter configuration.
     *
     * @return array Rate limiter configuration settings.
     */
    public static function get_rate_limiter_config() {
        return array(
            'enabled' => self::is_feature_enabled('enable_rate_limiting'),
            'requests_per_minute' => self::get('aips_requests_per_minute', 20),
        );
    }
    
    /**
     * Get AI service configuration.
     *
     * @return array AI service configuration settings.
     */
    public static function get_ai_config() {
        return array(
            'model' => self::get('aips_ai_model', ''),
            'max_tokens' => 2000,
            'temperature' => 0.7,
        );
    }
    
    /**
     * Get post generation configuration.
     *
     * @return array Post generation configuration settings.
     */
    public static function get_post_config() {
        return array(
            'default_status' => self::get('aips_default_post_status', 'draft'),
            'default_category' => self::get('aips_default_category', 0),
        );
    }
    
    /**
     * Get logging configuration.
     *
     * @return array Logging configuration settings.
     */
    public static function get_logging_config() {
        return array(
            'enabled' => self::get('aips_enable_logging', 1),
            'performance_logging' => self::is_feature_enabled('enable_performance_logging'),
        );
    }
    
    /**
     * Initialize default options.
     *
     * Called during plugin activation to set up default values.
     */
    public static function init_defaults() {
        foreach (self::get_default_options() as $key => $value) {
            if (get_option($key) === false) {
                add_option($key, $value);
            }
        }
    }
    
    /**
     * Get all configuration as array.
     *
     * Useful for debugging or exporting configuration.
     *
     * @return array Complete configuration array.
     */
    public static function get_all() {
        return array(
            'constants' => self::get_constants(),
            'options' => self::get_default_options(),
            'feature_flags' => self::get_feature_flags(),
            'retry' => self::get_retry_config(),
            'circuit_breaker' => self::get_circuit_breaker_config(),
            'rate_limiter' => self::get_rate_limiter_config(),
            'ai' => self::get_ai_config(),
            'posts' => self::get_post_config(),
            'logging' => self::get_logging_config(),
        );
    }
}
