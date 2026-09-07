<?php
/**
 * PHPUnit bootstrap for the canonical full WordPress test suite.
 *
 * @package AI_Post_Scheduler
 */

if (PHP_SESSION_NONE === session_status()) {
	session_start();
}

if (!defined('WP_TESTS_DIR')) {
	define('WP_TESTS_DIR', getenv('WP_TESTS_DIR') ? getenv('WP_TESTS_DIR') : '/tmp/wordpress-tests-lib');
}

if (!defined('WP_CORE_DIR')) {
	define('WP_CORE_DIR', getenv('WP_CORE_DIR') ? getenv('WP_CORE_DIR') : '/tmp/wordpress');
}

if (!file_exists(WP_TESTS_DIR . '/includes/functions.php')) {
	fwrite(STDERR, "WordPress test library not found at " . WP_TESTS_DIR . "\n");
	fwrite(STDERR, "Set WP_TESTS_DIR and WP_CORE_DIR or run bash scripts/run-wp-tests-docker.sh.\n");
	exit(1);
}

require_once dirname(__DIR__) . '/vendor/autoload.php';

if (file_exists(dirname(__DIR__) . '/vendor/yoast/phpunit-polyfills/phpunitpolyfills-autoload.php')) {
	require_once dirname(__DIR__) . '/vendor/yoast/phpunit-polyfills/phpunitpolyfills-autoload.php';
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

function _manually_load_plugin() {
	if (!defined('ABSPATH')) {
		define('ABSPATH', rtrim(WP_CORE_DIR, '/\\') . '/');
	}

	require dirname(__DIR__) . '/ai-post-scheduler.php';
}

tests_add_filter('muplugins_loaded', '_manually_load_plugin');

require WP_TESTS_DIR . '/includes/bootstrap.php';

add_filter('wp_doing_ajax', '__return_true');
add_filter('wp_die_ajax_handler', '_aips_use_full_wp_ajax_die_handler');
