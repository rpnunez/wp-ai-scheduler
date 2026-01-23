#!/usr/bin/env php
<?php
/**
 * Post Review Feature Integration Test
 * 
 * This script tests that all the Post Review components are properly integrated.
 * Run from repository root: php ai-post-scheduler/tests/integration-test-post-review.php
 */

// Simulate WordPress constants
define('ABSPATH', '/tmp/');
define('AIPS_VERSION', '1.8.0');
define('AIPS_PLUGIN_DIR', __DIR__ . '/../');
define('AIPS_PLUGIN_URL', 'http://localhost/wp-content/plugins/ai-post-scheduler/');
define('AIPS_PLUGIN_BASENAME', 'ai-post-scheduler/ai-post-scheduler.php');

echo "=== Post Review Feature Integration Test ===\n\n";

// Test 1: Check if files exist
echo "Test 1: Checking if files exist...\n";
$files = array(
	'includes/class-aips-post-review-repository.php',
	'includes/class-aips-post-review.php',
	'templates/admin/post-review.php',
	'assets/js/admin-post-review.js',
	'assets/css/admin.css',
);

$all_exist = true;
foreach ($files as $file) {
	$path = AIPS_PLUGIN_DIR . $file;
	if (file_exists($path)) {
		echo "  ✓ $file exists\n";
	} else {
		echo "  ✗ $file NOT FOUND\n";
		$all_exist = false;
	}
}

if ($all_exist) {
	echo "  Result: PASS - All files exist\n";
} else {
	echo "  Result: FAIL - Some files are missing\n";
	exit(1);
}

echo "\n";

// Test 2: Check PHP syntax
echo "Test 2: Checking PHP syntax...\n";
$php_files = array(
	'includes/class-aips-post-review-repository.php',
	'includes/class-aips-post-review.php',
	'templates/admin/post-review.php',
);

$syntax_ok = true;
foreach ($php_files as $file) {
	$path = AIPS_PLUGIN_DIR . $file;
	$output = array();
	$return_var = 0;
	exec("php -l " . escapeshellarg($path) . " 2>&1", $output, $return_var);
	
	if ($return_var === 0) {
		echo "  ✓ $file syntax OK\n";
	} else {
		echo "  ✗ $file has syntax errors:\n";
		echo "    " . implode("\n    ", $output) . "\n";
		$syntax_ok = false;
	}
}

if ($syntax_ok) {
	echo "  Result: PASS - All PHP files have valid syntax\n";
} else {
	echo "  Result: FAIL - Some PHP files have syntax errors\n";
	exit(1);
}

echo "\n";

// Test 3: Check if classes are defined correctly
echo "Test 3: Checking if classes are defined...\n";

// Mock WordPress functions
function esc_html__($text, $domain) { return $text; }
function esc_html_e($text, $domain) { echo $text; }
function esc_attr_e($text, $domain) { echo $text; }
function esc_html($text) { return htmlspecialchars($text, ENT_QUOTES, 'UTF-8'); }
function esc_attr($text) { return htmlspecialchars($text, ENT_QUOTES, 'UTF-8'); }
function esc_url($url) { return $url; }
function sanitize_text_field($text) { return $text; }
function wp_parse_args($args, $defaults) { return array_merge($defaults, $args); }
function admin_url($path) { return "http://localhost/wp-admin/$path"; }
function get_option($key, $default = '') { return $default; }
function current_time($type) { return date('Y-m-d H:i:s'); }
function selected($selected, $current, $echo = true) { return ($selected === $current) ? 'selected' : ''; }
function get_edit_post_link($post_id) { return "http://localhost/wp-admin/post.php?post=$post_id&action=edit"; }
function get_permalink($post_id) { return "http://localhost/?p=$post_id"; }
function date_i18n($format, $timestamp = null) { return date($format, $timestamp ?: time()); }
function _n($single, $plural, $count, $domain) { return $count === 1 ? $single : $plural; }
function add_action($hook, $callback) { return true; }

// Mock wpdb
class MockWpdb {
	public $prefix = 'wp_';
	public $posts = 'wp_posts';
	
	public function esc_like($text) { return $text; }
	public function prepare($query, ...$args) { return $query; }
	public function get_results($query) { return array(); }
	public function get_var($query) { return 0; }
}

global $wpdb;
$wpdb = new MockWpdb();

// Include repository class
require_once AIPS_PLUGIN_DIR . 'includes/class-aips-post-review-repository.php';

if (class_exists('AIPS_Post_Review_Repository')) {
	echo "  ✓ AIPS_Post_Review_Repository class is defined\n";
	$repo = new AIPS_Post_Review_Repository();
	echo "  ✓ AIPS_Post_Review_Repository can be instantiated\n";
} else {
	echo "  ✗ AIPS_Post_Review_Repository class NOT defined\n";
	exit(1);
}

// Mock additional WordPress functions for controller
function wp_send_json_error($data) { echo json_encode(array('success' => false, 'data' => $data)); }
function wp_send_json_success($data) { echo json_encode(array('success' => true, 'data' => $data)); }
function current_user_can($capability) { return true; }
function check_ajax_referer($action, $key) { return true; }
function absint($value) { return abs((int) $value); }
function wp_update_post($data) { return $data['ID']; }
function is_wp_error($thing) { return false; }
function wp_delete_post($post_id, $force_delete = false) { return true; }
function wp_insert_post($data, $wp_error = false) { return rand(1, 1000); }

// Mock other classes
class AIPS_Activity_Repository {
	public function create($data) { return 1; }
}

class AIPS_History_Repository {
	public function get_by_id($id) { return (object) array('id' => $id, 'template_id' => 1, 'post_id' => 1); }
	public function update($id, $data) { return true; }
}

class AIPS_Template_Repository {
	public function get_by_id($id) { return (object) array('id' => $id, 'name' => 'Test Template'); }
	public function get_all() { return array(); }
}

class AIPS_Schedule_Controller {
	public function generate_post($template) { return true; }
}

// Include controller class
require_once AIPS_PLUGIN_DIR . 'includes/class-aips-post-review.php';

if (class_exists('AIPS_Post_Review')) {
	echo "  ✓ AIPS_Post_Review class is defined\n";
	$controller = new AIPS_Post_Review();
	echo "  ✓ AIPS_Post_Review can be instantiated\n";
} else {
	echo "  ✗ AIPS_Post_Review class NOT defined\n";
	exit(1);
}

echo "  Result: PASS - All classes are properly defined\n";

echo "\n";

// Test 4: Check JavaScript
echo "Test 4: Checking JavaScript file...\n";
$js_path = AIPS_PLUGIN_DIR . 'assets/js/admin-post-review.js';
$js_content = file_get_contents($js_path);

$required_functions = array(
	'aips-publish-post',
	'aips-delete-post',
	'aips-regenerate-post',
	'aips-view-logs',
	'aips-bulk-action-btn',
);

$js_ok = true;
foreach ($required_functions as $func) {
	if (strpos($js_content, $func) !== false) {
		echo "  ✓ Found handler for: $func\n";
	} else {
		echo "  ✗ Missing handler for: $func\n";
		$js_ok = false;
	}
}

if ($js_ok) {
	echo "  Result: PASS - JavaScript has all required handlers\n";
} else {
	echo "  Result: FAIL - JavaScript is missing some handlers\n";
	exit(1);
}

echo "\n";

// Test 5: Check CSS
echo "Test 5: Checking CSS styling...\n";
$css_path = AIPS_PLUGIN_DIR . 'assets/css/admin.css';
$css_content = file_get_contents($css_path);

$required_classes = array(
	'.aips-post-review-stats',
	'.aips-action-buttons',
	'.aips-modal',
	'.aips-empty-state',
);

$css_ok = true;
foreach ($required_classes as $class) {
	if (strpos($css_content, $class) !== false) {
		echo "  ✓ Found CSS class: $class\n";
	} else {
		echo "  ✗ Missing CSS class: $class\n";
		$css_ok = false;
	}
}

if ($css_ok) {
	echo "  Result: PASS - CSS has all required styles\n";
} else {
	echo "  Result: FAIL - CSS is missing some styles\n";
	exit(1);
}

echo "\n";

echo "=== All Integration Tests Passed! ===\n";
echo "\nThe Post Review feature has been successfully integrated:\n";
echo "  • Repository class for database queries\n";
echo "  • Controller class for AJAX handlers\n";
echo "  • Admin template for the UI\n";
echo "  • JavaScript for interactivity\n";
echo "  • CSS for styling\n";
echo "\nThe feature is ready for manual testing in a WordPress environment.\n";

exit(0);
