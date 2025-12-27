<?php
// Mock WordPress environment
function sanitize_text_field($str) { return trim(strip_tags($str)); }
function wp_send_json_error($data) { echo "ERROR: " . json_encode($data) . "\n"; }
function wp_send_json_success($data) { echo "SUCCESS: " . json_encode($data) . "\n"; }
function __($text, $domain) { return $text; }
define('JSON_ERROR_NONE', 0);

// Test Logic
$json_str = "1. Topic A\n2. Topic B\n- Topic C\n* Topic D\n\"Topic E\"";
echo "Input:\n$json_str\n";

$topics = json_decode($json_str);

if (json_last_error() !== JSON_ERROR_NONE || !is_array($topics)) {
    // Copied from class-aips-planner.php
    $lines = explode("\n", $json_str);
    $topics = array();

    foreach ($lines as $line) {
        $line = trim($line);
        if (empty($line)) continue;

        // Remove leading numbers, bullets, and dashes (e.g., "1.", "-", "*")
        $clean_line = preg_replace('/^[\d\-\*\.]+\s*/', '', $line);

        // Remove quotes if they wrap the line
        $clean_line = trim($clean_line, '"\'');

        if (!empty($clean_line)) {
            $topics[] = $clean_line;
        }
    }
}

print_r($topics);
