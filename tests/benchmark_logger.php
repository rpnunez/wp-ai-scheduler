<?php
/**
 * Benchmark script for AIPS_Logger::get_logs()
 *
 * This script demonstrates the performance difference between iterating through a large file
 * to count lines (O(N)) versus reading a chunk from the end (O(1)).
 *
 * Usage: php tests/benchmark_logger.php
 */

// Mock WordPress functions
function wp_upload_dir() { return ['basedir' => '/tmp']; }
function get_option($name, $default) { return $default; }
define('ABSPATH', true);

// Include the class
require_once __DIR__ . '/../ai-post-scheduler/includes/class-aips-logger.php';

// Setup
$log_dir = '/tmp/aips-logs';
if (!is_dir($log_dir)) mkdir($log_dir, 0777, true);
$date = date('Y-m-d');
$log_file = $log_dir . '/aips-' . $date . '.log';

// Generate a large log file (~10MB) if it doesn't exist
if (!file_exists($log_file) || filesize($log_file) < 10000000) {
    echo "Generating large log file (10MB)... ";
    $fp = fopen($log_file, 'w');
    $line_content = " [INFO] This is a test log entry to make the file bigger. | Context: {\"foo\":\"bar\"}\n";
    for ($i = 0; $i < 100000; $i++) {
        fwrite($fp, "[$date 12:00:00] $i $line_content");
    }
    fclose($fp);
    echo "Done.\n";
}

echo "File size: " . number_format(filesize($log_file) / 1024 / 1024, 2) . " MB\n";

$logger = new AIPS_Logger();

// Benchmark
echo "Reading last 100 lines...\n";
$start = microtime(true);
$logs = $logger->get_logs(100);
$end = microtime(true);

$duration = ($end - $start) * 1000;
echo "Time taken: " . number_format($duration, 2) . " ms\n";
echo "Lines retrieved: " . count($logs) . "\n";
if (count($logs) > 0) {
    echo "First line retrieved: " . substr($logs[0], 0, 50) . "...\n";
}

// Cleanup (optional, commented out to inspect file)
// unlink($log_file);
