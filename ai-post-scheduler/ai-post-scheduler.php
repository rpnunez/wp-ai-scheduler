<?php
/**
 * Plugin Name: AI Post Scheduler
 * Plugin URI: https://nunezserver.com/nunezscheduler
 * Description: Schedule AI-generated posts using advanced features & scheduling options.
 * Version: 2.9.1
 * Author: Raymond Nunez
 * Author URI: https://nunezserver.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: ai-post-scheduler
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 8.2
 */

if (!defined('ABSPATH')) {
	exit;
}

// Load constants.
require_once plugin_dir_path(__FILE__) . 'includes/constants.php';

// Bootstrap dependency loaders.
// Primary autoloader: Composer-generated classmap.
$vendor_autoload = AIPS_PLUGIN_DIR . 'vendor/autoload.php';
if (file_exists($vendor_autoload)) {
	require_once $vendor_autoload;
}

// Fallback shim: legacy autoloader.
require_once AIPS_PLUGIN_DIR . 'includes/class-aips-autoloader.php';
AIPS_Autoloader::register();

/**
 * Backward compatibility class wrapper.
 */
final class AI_Post_Scheduler {
	private static $instance = null;

	public static function get_cron_events() {
		return AIPS_Core::get_cron_events();
	}

	public static function get_instance() {
		if (null === self::$instance) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {}

	public function init() {
		AIPS_Core::get_instance()->init();
	}

	public function activate() {
		AIPS_Core::get_instance()->activate();
	}

	public function deactivate() {
		AIPS_Core::get_instance()->deactivate();
	}
}

/**
 * Initialize and return the plugin singleton.
 *
 * @return AI_Post_Scheduler
 */
function aips_init() {
	return AI_Post_Scheduler::get_instance();
}

add_action('plugins_loaded', 'aips_init', 5);

/**
 * Tell Query Monitor that files under the real (symlink-resolved) plugin
 * directory belong to this plugin, not WordPress Core.
 */
add_filter('qm/component_dirs', function( array $dirs ) {
	$real = realpath( AIPS_PLUGIN_DIR );
	if ( false === $real ) {
		return $dirs;
	}

	$real = rtrim( str_replace( '\\', '/', $real ), '/' );
	$expected = rtrim( str_replace( '\\', '/', WP_PLUGIN_DIR . '/ai-post-scheduler' ), '/' );

	if ( $real !== $expected ) {
		$dirs['plugin:ai-post-scheduler'] = $real;
	}

	return $dirs;
});

add_filter('qm/component_type/plugin:ai-post-scheduler', function() {
	return 'plugin';
});

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

/**
 * Activation hook callback.
 *
 * @return void
 */
function aips_activate_callback() {
	AI_Post_Scheduler::get_instance()->activate();
}

register_activation_hook(__FILE__, 'aips_activate_callback');

/**
 * Deactivation hook callback.
 *
 * @return void
 */
function aips_deactivate_callback() {
	AI_Post_Scheduler::get_instance()->deactivate();
}

register_deactivation_hook(__FILE__, 'aips_deactivate_callback');
