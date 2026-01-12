<?php
/**
 * Verification script for Log Injection vulnerability.
 *
 * Usage: php verification/verify_log_injection.php
 *
 * This script demonstrates that a log message containing newlines
 * can create fake log entries if not sanitized.
 */

// Mock WordPress environment
define('ABSPATH', __DIR__ . '/../');
define('WP_DEBUG', true);

// Mock get_option
function get_option($key, $default = false) {
    if ($key === 'aips_log_secret') return 'test_secret';
    if ($key === 'aips_enable_logging') return true;
    return $default;
}

// Mock update_option
function update_option($key, $value) {
    return true;
}

// Mock wp_upload_dir
function wp_upload_dir() {
    $dir = sys_get_temp_dir() . '/aips-test-uploads';
    if (!file_exists($dir)) mkdir($dir, 0777, true);
    return array('basedir' => $dir);
}

// Mock wp_mkdir_p
function wp_mkdir_p($dir) {
    if (!file_exists($dir)) mkdir($dir, 0777, true);
    return true;
}

// Mock current_time
function current_time($type) {
    return date('Y-m-d H:i:s');
}

// Include the class
require_once __DIR__ . '/../ai-post-scheduler/includes/class-aips-logger.php';

// Instantiate Logger
$logger = new AIPS_Logger();

// Attack payload: A message containing a newline and a fake log entry
$fake_timestamp = date('Y-m-d H:i:s');
$attack_message = "Normal log message\n[$fake_timestamp] [INFO] FAKE LOG ENTRY: Admin password is 12345";

// Log the attack message
echo "Logging attack message...\n";
$logger->log($attack_message, 'info');

// Read the log file
$files = $logger->get_log_files();
if (empty($files)) {
    echo "Error: No log files found.\n";
    exit(1);
}

$log_content = implode("\n", $logger->get_logs(10));
echo "Log Content:\n----------------\n$log_content\n----------------\n";

// Check if the fake log entry appears as a separate line
if (strpos($log_content, "FAKE LOG ENTRY") !== false) {
    // Check if it looks like a real entry (start of line)
    // Note: get_logs returns an array of lines.
    // If we simply check the array count, it should be 2 lines for 1 log call if vulnerable.
    $logs = $logger->get_logs(10);
    echo "Log count: " . count($logs) . "\n";

    // We expect 1 entry if fixed, 2 entries if vulnerable (or more depending on splitting)
    // Actually, get_logs uses explode("\n", $content).

    if (count($logs) >= 2) {
         echo "VULNERABILITY CONFIRMED: Log message with newline created multiple log entries.\n";
    } else {
         echo "FIX VERIFIED: Log message was sanitized into a single line.\n";
    }
} else {
    echo "Something went wrong, fake entry not found.\n";
}
