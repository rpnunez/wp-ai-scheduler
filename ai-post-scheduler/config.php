<?php
/**
 * Configuration and Constants for AI Post Scheduler
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

// Define plugin constants
if (!defined('AIPS_VERSION')) {
	define('AIPS_VERSION', '3.6.5');
}

if (!defined('AIPS_PLUGIN_DIR')) {
	define('AIPS_PLUGIN_DIR', plugin_dir_path(__FILE__));
}

if (!defined('AIPS_PLUGIN_URL')) {
	define('AIPS_PLUGIN_URL', plugin_dir_url(__FILE__));
}

if (!defined('AIPS_PLUGIN_BASENAME')) {
	define('AIPS_PLUGIN_BASENAME', plugin_basename(dirname(__FILE__) . '/ai-post-scheduler.php'));
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

/**
 * Tell Query Monitor that files under the real (symlink-resolved) plugin
 * directory belong to this plugin, not WordPress Core.
 *
 * When the plugin directory is a symlink, PHP's debug_backtrace() returns the
 * real path (e.g. C:/Projects/.../ai-post-scheduler) which does not start with
 * WP_PLUGIN_DIR, so QM falls back to "WordPress Core".
 *
 * The three companion filters together register the resolved path and map the
 * custom key back to TYPE_PLUGIN with the correct plugin-slug context so that
 * QM shows "Plugin: ai-post-scheduler" in the Component column.
 */
add_filter('qm/component_dirs', function( array $dirs ) {
	$real = realpath( AIPS_PLUGIN_DIR );
	if ( false === $real ) {
		return $dirs;
	}

	$real = rtrim( str_replace( '\\', '/', $real ), '/' );

	// Compare against the canonical WordPress plugins path, not AIPS_PLUGIN_DIR.
	// In symlinked installs plugin_dir_path(__FILE__) can already be resolved.
	$expected = rtrim( str_replace( '\\', '/', WP_PLUGIN_DIR . '/ai-post-scheduler' ), '/' );

	// Only add when the real path differs from the canonical WP plugin path
	// (i.e. a symlink is in use). Using a namespaced key avoids overwriting
	// QM's built-in 'plugin' entry which is appended after this filter runs.
	if ( $real !== $expected ) {
		$dirs['plugin:ai-post-scheduler'] = $real;
	}

	return $dirs;
});

// Map the custom dir-key type back to TYPE_PLUGIN so QM shows the correct
// component class and name rather than falling into the "unknown" branch.
add_filter('qm/component_type/plugin:ai-post-scheduler', function() {
	return 'plugin';
});

// Supply the plugin slug as the context so the component name becomes
// "Plugin: ai-post-scheduler" instead of "Plugin: plugin:ai-post-scheduler".
add_filter('qm/component_context/plugin', function( $context, $file ) {
	$real = realpath( AIPS_PLUGIN_DIR );

	if ( false === $real || ! is_string( $file ) ) {
		return $context;
	}

	$real = rtrim( str_replace( '\\', '/', $real ), '/' );
	$file = str_replace( '\\', '/', $file );

	if ( 0 === strpos( $file, $real . '/' ) || $file === $real ) {
		return 'ai-post-scheduler';
	}

	return $context;
}, 10, 2);
