<?php
// Mock WordPress environment
$history = array(
    'items' => array(),
    'total' => 0,
    'pages' => 0,
    'current_page' => 1
);
$stats = array(
    'total' => 0,
    'completed' => 0,
    'failed' => 0,
    'success_rate' => 0
);
$status_filter = '';

// Helper functions
function esc_html_e($text, $domain) { echo htmlspecialchars($text); }
function esc_attr_e($text, $domain) { echo htmlspecialchars($text); }
function esc_html($text) { return htmlspecialchars($text); }
function esc_attr($text) { return htmlspecialchars($text); }
function esc_url($url) { return htmlspecialchars($url); }
function selected($selected, $current, $echo = true) {
    if ($selected === $current) {
        if ($echo) echo ' selected="selected"';
        return ' selected="selected"';
    }
}
function _e($text, $domain) { echo htmlspecialchars($text); }
function __($text, $domain) { return $text; }
function date_i18n($format, $timestamp) { return date($format, $timestamp); }
function get_option($option) { return 'Y-m-d H:i:s'; }
function add_query_arg($args, $url) { return $url; }
function admin_url($path) { return 'http://example.com/wp-admin/' . $path; }
function absint($n) { return (int)$n; }
function sanitize_text_field($s) { return htmlspecialchars($s); }

define('ABSPATH', true);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Mock History Page</title>
    <style>
        .search-box { float: right; margin-bottom: 10px; }
        .tablenav { clear: both; margin: 6px 0 4px; vertical-align: middle; }
        .tablenav .actions { padding: 2px 8px 0 0; }
        .alignleft { float: left; }
        .alignright { float: right; }
        .screen-reader-text {
            border: 0;
            clip: rect(1px, 1px, 1px, 1px);
            -webkit-clip-path: inset(50%);
            clip-path: inset(50%);
            height: 1px;
            margin: -1px;
            overflow: hidden;
            padding: 0;
            position: absolute;
            width: 1px;
            word-wrap: normal !important;
        }
    </style>
</head>
<body>
    <?php include 'ai-post-scheduler/templates/admin/history.php'; ?>
</body>
</html>
