<?php
// Mock functions
if (!function_exists('esc_html_e')) { function esc_html_e($s) { echo $s; } }
if (!function_exists('esc_attr')) { function esc_attr($s) { return $s; } }
if (!function_exists('esc_html')) { function esc_html($s) { return $s; } }
if (!function_exists('esc_html__')) { function esc_html__($s) { return $s; } }
if (!function_exists('__')) { function __($s) { return $s; } }
if (!function_exists('esc_attr_e')) { function esc_attr_e($s) { echo $s; } }
if (!defined('ABSPATH')) { define('ABSPATH', true); }

// Mock data
$voices = [
    (object)[
        'id' => 1,
        'name' => 'Test Voice',
        'title_prompt' => 'Make it pop',
        'is_active' => 1
    ]
];

// Start buffer
ob_start();
?>
<!DOCTYPE html>
<html>
<head>
<style>
/* Minimal CSS to make it look like WP Admin */
.wrap { margin: 20px; font-family: sans-serif; }
.wp-list-table { border-collapse: collapse; width: 100%; }
.wp-list-table th, .wp-list-table td { border: 1px solid #ddd; padding: 8px; text-align: left; }
.button { padding: 5px 10px; cursor: pointer; }
.aips-clone-voice { background: #eee; }
</style>
</head>
<body>
<?php
include __DIR__ . '/../ai-post-scheduler/templates/admin/voices.php';
?>
</body>
</html>
<?php
$html = ob_get_clean();
file_put_contents(__DIR__ . '/mock_voices.html', $html);
echo "Generated mock_voices.html\n";
