<?php
define('ABSPATH', '/tmp/');
define('WP_CONTENT_DIR', '/tmp/wp-content');
function wp_upload_dir() { return ['basedir' => '/tmp/wp-content/uploads']; }
function get_option($name, $default = false) { return $default; }
function update_option($name, $value) {}
function wp_generate_password($len=12) { return 'testsecret'; }
function wp_mkdir_p($dir) { if (!is_dir($dir)) mkdir($dir, 0777, true); }
function current_time($type) { return date('Y-m-d H:i:s'); }
function is_wp_error($thing) { return false; }
function wp_parse_args($args, $defaults) { return array_merge($defaults, $args); }

require_once 'ai-post-scheduler/includes/class-aips-logger.php';

// Mock WP env
if (!is_dir('/tmp/wp-content/uploads/aips-logs')) {
    mkdir('/tmp/wp-content/uploads/aips-logs', 0777, true);
}

// Create old logs
$old_log = '/tmp/wp-content/uploads/aips-logs/aips-2020-01-01-old.log';
file_put_contents($old_log, 'old log content');
touch($old_log, time() - (31 * 86400)); // 31 days old

$new_log = '/tmp/wp-content/uploads/aips-logs/aips-' . date('Y-m-d') . '-new.log';
file_put_contents($new_log, 'new log content');

echo "Files before cleanup:\n";
print_r(glob('/tmp/wp-content/uploads/aips-logs/*.log'));

$logger = new AIPS_Logger();
// Force cleanup
$logger->cleanup_old_logs(30);

echo "\nFiles after cleanup:\n";
$files = glob('/tmp/wp-content/uploads/aips-logs/*.log');
print_r($files);

if (count($files) === 1 && strpos($files[0], 'new.log') !== false) {
    echo "\nPASS: Old log deleted, new log kept.\n";
} else {
    echo "\nFAIL: Cleanup logic failed.\n";
}

// Test Clear All
$logger->clear_all_logs();
echo "\nFiles after clear all:\n";
$files_cleared = glob('/tmp/wp-content/uploads/aips-logs/*.log');
print_r($files_cleared);

if (empty($files_cleared)) {
    echo "\nPASS: All logs cleared.\n";
} else {
    echo "\nFAIL: Clear all logs failed.\n";
}
