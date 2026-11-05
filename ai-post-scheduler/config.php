<?php
/**
 * Plugin Configuration and Constant Definitions.
 *
 * Defines runtime flags, telemetry thresholds, debugging options, and versioning
 * for AI Post Scheduler. Loaded before any plugin classes or hooks are initialized.
 *
 * @package AI_Post_Scheduler
 */

if (!defined('ABSPATH')) {
    exit;
}

// Capture the request start time as early as possible so AIPS_Telemetry
// can compute an accurate elapsed-time measurement.
if (!defined('AIPS_REQUEST_START')) {
    define('AIPS_REQUEST_START', microtime(true));
}

// Enable SAVEQUERIES as early as possible for telemetry-enabled requests so
// slow/duplicate query analysis can inspect the collected query log.
if (!defined('SAVEQUERIES') && function_exists('get_option') && get_option('aips_enable_telemetry', false)) {
    define('SAVEQUERIES', true);
}

if (!defined('AIPS_TELEMETRY_SLOW_QUERY_MS')) {
    define('AIPS_TELEMETRY_SLOW_QUERY_MS', 100);
}

if (!defined('AIPS_TELEMETRY_SLOW_REQUEST_MS')) {
    define('AIPS_TELEMETRY_SLOW_REQUEST_MS', 1500);
}

if (!defined('AIPS_TELEMETRY_QUERY_SAMPLE_LIMIT')) {
    define('AIPS_TELEMETRY_QUERY_SAMPLE_LIMIT', 10);
}

// Plugin version
if (!defined('AIPS_VERSION')) {
    define('AIPS_VERSION', '3.7.19');
}

// Prompt-preview logging can expose generated content in logs. Off by default;
// opt-in by defining the constant to true earlier (e.g. in wp-config.php), or
// it will automatically enable when WP_DEBUG is true.
if (!defined('AIPS_AI_DEBUG_LOG_PROMPTS')) {
    define('AIPS_AI_DEBUG_LOG_PROMPTS', defined('WP_DEBUG') && WP_DEBUG);
}

if (!defined('AIPS_DEBUG')) {
    define('AIPS_DEBUG', defined('WP_DEBUG') && WP_DEBUG);
}

if (!defined('AIPS_DEBUG_LEVEL')) {
    define('AIPS_DEBUG_LEVEL', defined('WP_DEBUG') && WP_DEBUG ? 1 : 0);
}
