<?php
// Mock WordPress functions
if (!function_exists('esc_html')) { function esc_html($s) { return htmlspecialchars($s); } }
if (!function_exists('esc_html_e')) { function esc_html_e($s) { echo htmlspecialchars($s); } }
if (!function_exists('esc_url')) { function esc_url($s) { return $s; } }
if (!function_exists('esc_attr')) { function esc_attr($s) { return htmlspecialchars($s); } }
if (!function_exists('__')) { function __($s) { return $s; } }
if (!function_exists('_e')) { function _e($s) { echo $s; } }
if (!function_exists('admin_url')) { function admin_url($s = '') { return 'http://example.com/wp-admin/' . $s; } }
if (!function_exists('get_option')) { function get_option($s) { return $s === 'date_format' ? 'F j, Y' : ($s === 'time_format' ? 'g:i a' : ''); } }
if (!function_exists('date_i18n')) { function date_i18n($f, $t) { return date($f, $t); } }
if (!function_exists('get_edit_post_link')) { function get_edit_post_link($id) { return "http://example.com/wp-admin/post.php?post=$id&action=edit"; } }
if (!function_exists('add_query_arg')) { function add_query_arg($k, $v, $u) { return $u . '&' . $k . '=' . $v; } }

// Define constants
define('ABSPATH', __DIR__ . '/');

// Mock data
$total_generated = 120;
$pending_scheduled = 5;
$total_templates = 10;
$failed_count = 2;

$recent_posts = array(
    (object) array('post_id' => 101, 'generated_title' => 'AI Revolution', 'status' => 'completed', 'created_at' => date('Y-m-d H:i:s', strtotime('-1 hour'))),
    (object) array('post_id' => 102, 'generated_title' => 'Machine Learning', 'status' => 'failed', 'created_at' => date('Y-m-d H:i:s', strtotime('-2 hours'))),
);

$upcoming = array(
    (object) array(
        'id' => 1,
        'template_name' => 'Tech News',
        'next_run' => date('Y-m-d H:i:s', strtotime('+1 hour')),
        'frequency' => 'daily'
    ),
    (object) array(
        'id' => 2,
        'template_name' => 'Weekly Digest',
        'next_run' => date('Y-m-d H:i:s', strtotime('+1 day')),
        'frequency' => 'weekly'
    ),
);

// Capture output
ob_start();
?>
<!DOCTYPE html>
<html>
<head>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/dashicons/3.2.0/css/dashicons.min.css">
    <style>
        body { font-family: sans-serif; background: #f1f1f1; padding: 20px; }
        .wrap { max-width: 1200px; margin: 0 auto; }
        .aips-dashboard { display: grid; gap: 20px; }
        .aips-stats-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; margin-bottom: 20px; }
        .aips-stat-card { background: #fff; padding: 20px; border-radius: 4px; border: 1px solid #ccd0d4; display: flex; align-items: center; }
        .aips-stat-icon { font-size: 32px; width: 32px; height: 32px; margin-right: 15px; color: #2271b1; }
        .aips-stat-content { display: flex; flex-direction: column; }
        .aips-stat-number { font-size: 24px; font-weight: bold; color: #1d2327; }
        .aips-stat-label { color: #646970; }
        .aips-stat-warning .aips-stat-icon { color: #d63638; }
        .widefat { width: 100%; border-spacing: 0; background: #fff; border: 1px solid #c3c4c7; }
        .widefat td, .widefat th { padding: 8px 10px; text-align: left; }
        .widefat thead th { border-bottom: 1px solid #c3c4c7; }
        .button { background: #f6f7f7; border: 1px solid #2271b1; color: #2271b1; padding: 4px 10px; cursor: pointer; border-radius: 3px; }
        .button-primary { background: #2271b1; color: #fff; }
    </style>
</head>
<body>
<?php
include __DIR__ . '/../ai-post-scheduler/templates/admin/dashboard.php';
?>
</body>
</html>
<?php
$html = ob_get_clean();
file_put_contents(__DIR__ . '/mock_dashboard.html', $html);
echo "Mock dashboard generated at " . __DIR__ . "/mock_dashboard.html\n";
