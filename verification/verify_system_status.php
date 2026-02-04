<?php
// Mock WordPress functions
function esc_html_e($text, $domain) { echo $text; }
function esc_html($text) { return $text; }
function esc_attr($text) { return $text; }
function esc_textarea($text) { return htmlspecialchars($text); }
function current_time($type) { return date('Y-m-d H:i:s'); }
function get_site_url() { return 'http://example.com'; }
define('ABSPATH', __DIR__ . '/../');

// Mock data
$system_info = array(
    'environment' => array(
        'php_version' => array('label' => 'PHP Version', 'value' => '8.2', 'status' => 'ok'),
    )
);

// Output buffer to capture HTML
ob_start();
include __DIR__ . '/../ai-post-scheduler/templates/admin/system-status.php';
$html = ob_get_clean();

// Check for button
if (strpos($html, 'class="button button-secondary aips-copy-btn"') !== false &&
    strpos($html, 'data-clipboard-target="#aips-system-report-raw"') !== false) {
    echo "PASS: Button found.\n";
} else {
    echo "FAIL: Button not found.\n";
    exit(1);
}

// Check for textarea
if (strpos($html, '<textarea id="aips-system-report-raw"') !== false) {
    echo "PASS: Hidden textarea found.\n";
} else {
    echo "FAIL: Hidden textarea not found.\n";
    exit(1);
}

// Check report content
if (strpos($html, '### AI Post Scheduler System Report ###') !== false) {
    echo "PASS: Report header found.\n";
} else {
    echo "FAIL: Report header not found.\n";
    exit(1);
}
