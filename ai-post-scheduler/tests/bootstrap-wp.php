<?php
/**
 * Full WordPress-mode PHPUnit bootstrap.
 *
 * This intentionally avoids loading the repo root Composer autoloader because
 * the main project currently uses PHPUnit 10 while the full WordPress test
 * framework must run under the isolated PHPUnit 9 toolchain.
 */

if (PHP_SESSION_NONE === session_status()) {
	session_start();
}

if (!defined('AIPS_SKIP_VENDOR_AUTOLOAD_FOR_TESTS')) {
	define('AIPS_SKIP_VENDOR_AUTOLOAD_FOR_TESTS', true);
}

if (!defined('AIPS_FULL_WP_TEST_MODE')) {
	define('AIPS_FULL_WP_TEST_MODE', true);
}

if (!defined('WP_TESTS_DIR')) {
	define('WP_TESTS_DIR', getenv('WP_TESTS_DIR') ? getenv('WP_TESTS_DIR') : '/tmp/wordpress-tests-lib');
}

if (!defined('WP_CORE_DIR')) {
	define('WP_CORE_DIR', getenv('WP_CORE_DIR') ? getenv('WP_CORE_DIR') : '/tmp/wordpress');
}

if (!file_exists(WP_TESTS_DIR . '/includes/functions.php')) {
	echo "Warning: WordPress test library not found at " . WP_TESTS_DIR . "\n";
	echo "Full WordPress mode requires a valid WordPress test library.\n";
	exit(1);
}

require_once WP_TESTS_DIR . '/includes/functions.php';

function _aips_full_wp_ajax_die_handler($message = '') {
	$buffer = ob_get_contents();

	if (false !== $buffer && '' !== $buffer) {
		throw new WPAjaxDieContinueException(is_scalar($message) ? (string) $message : '');
	}

	if (is_scalar($message)) {
		throw new WPAjaxDieStopException((string) $message);
	}

	throw new WPAjaxDieStopException('0');
}

function _aips_use_full_wp_ajax_die_handler() {
	return '_aips_full_wp_ajax_die_handler';
}

function _manually_load_plugin_wp_mode() {
	if (!defined('ABSPATH')) {
		define('ABSPATH', rtrim(WP_CORE_DIR, '/\\') . '/');
	}

	require dirname(__DIR__) . '/ai-post-scheduler.php';
}

tests_add_filter('muplugins_loaded', '_manually_load_plugin_wp_mode');

require WP_TESTS_DIR . '/includes/bootstrap.php';

add_filter('wp_doing_ajax', '__return_true');
add_filter('wp_die_ajax_handler', '_aips_use_full_wp_ajax_die_handler');
