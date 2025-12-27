<?php
/**
 * PHPUnit bootstrap file
 *
 * Initializes WordPress test environment and loads plugin files.
 *
 * @package AI_Post_Scheduler
 */

// Composer autoloader
if (file_exists(dirname(__DIR__) . '/vendor/autoload.php')) {
    require_once dirname(__DIR__) . '/vendor/autoload.php';
}

// Load the Yoast PHPUnit Polyfills autoloader
if (file_exists(dirname(__DIR__) . '/vendor/yoast/phpunit-polyfills/phpunitpolyfills-autoload.php')) {
    require_once dirname(__DIR__) . '/vendor/yoast/phpunit-polyfills/phpunitpolyfills-autoload.php';
}

// Define WordPress test environment constants
if (!defined('WP_TESTS_DIR')) {
    define('WP_TESTS_DIR', getenv('WP_TESTS_DIR') ? getenv('WP_TESTS_DIR') : '/tmp/wordpress-tests-lib');
}

// Define WordPress core directory
if (!defined('WP_CORE_DIR')) {
    define('WP_CORE_DIR', getenv('WP_CORE_DIR') ? getenv('WP_CORE_DIR') : '/tmp/wordpress');
}

// Check if WordPress test library exists
if (!file_exists(WP_TESTS_DIR . '/includes/functions.php')) {
    die("The WordPress Test Library could not be found at " . WP_TESTS_DIR . ".\n");
}

require_once WP_TESTS_DIR . '/includes/functions.php';

/**
 * Manually load the plugin being tested.
 */
function _manually_load_plugin() {
    // Ensure ABSPATH is defined (should be by WP test lib, but just in case)
    if (!defined('ABSPATH')) {
        define('ABSPATH', WP_CORE_DIR . '/');
    }
    
    // Load plugin files
    require dirname(__DIR__) . '/ai-post-scheduler.php';
}
tests_add_filter('muplugins_loaded', '_manually_load_plugin');

// Start up the WP testing environment
require WP_TESTS_DIR . '/includes/bootstrap.php';
