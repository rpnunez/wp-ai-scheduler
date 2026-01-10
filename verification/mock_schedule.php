<?php
// Mock WordPress Environment for Testing Schedule Page
define('ABSPATH', '/var/www/html/');
define('AIPS_PLUGIN_DIR', __DIR__ . '/../');
define('AIPS_PLUGIN_URL', 'http://localhost/');
define('AIPS_VERSION', '1.0.0');

// Mock Functions
function esc_html($text) { return htmlspecialchars($text); }
function esc_html_e($text, $domain) { echo htmlspecialchars($text); }
function esc_html__($text, $domain) { return htmlspecialchars($text); }
function esc_attr($text) { return htmlspecialchars($text); }
function esc_attr_e($text, $domain) { echo htmlspecialchars($text); }
function __($text, $domain) { return $text; }
function _e($text, $domain) { echo $text; }
function selected($a, $b, $echo = true) { if ($a == $b) { if ($echo) echo "selected='selected'"; return "selected='selected'"; } }
function checked($a, $b, $echo = true) { if ($a == $b) { if ($echo) echo "checked='checked'"; return "checked='checked'"; } }
function get_option($key, $default = false) { return $default; }
function date_i18n($format, $timestamp) { return date($format, $timestamp); }
function admin_url($path) { return 'http://localhost/admin/' . $path; }
function wp_get_schedules() { return ['hourly' => ['interval' => 3600, 'display' => 'Hourly'], 'daily' => ['interval' => 86400, 'display' => 'Daily']]; }
function current_user_can($cap) { return true; }
function get_current_user_id() { return 1; }
function wp_create_nonce($action) { return 'mock_nonce'; }

// Mock Classes
class AIPS_Scheduler {
    public function get_all_schedules() {
        $s1 = new stdClass();
        $s1->id = 1;
        $s1->template_id = 101;
        $s1->template_name = 'Tech News Daily';
        $s1->frequency = 'daily';
        $s1->topic = 'Tech Trends';
        $s1->next_run = date('Y-m-d H:i:s', strtotime('+1 hour'));
        $s1->last_run = date('Y-m-d H:i:s', strtotime('-23 hours'));
        $s1->status = 'active';
        $s1->is_active = 1;
        $s1->article_structure_id = null;
        $s1->rotation_pattern = null;
        return [$s1];
    }
}
class AIPS_Templates {
    public function get_all($active_only = false) {
        $t1 = new stdClass();
        $t1->id = 101;
        $t1->name = 'Tech News Daily';
        return [$t1];
    }
}
class AIPS_Article_Structure_Manager {
    public function get_active_structures() { return []; }
}
class AIPS_Template_Type_Selector {
    public function get_rotation_patterns() { return []; }
}
class AIPS_Article_Structure_Repository {
    public function get_by_id($id) { return null; }
}

// Global Mocks
$schedules = (new AIPS_Scheduler())->get_all_schedules();

// Render
ob_start();
include '../ai-post-scheduler/templates/admin/schedule.php';
$content = ob_get_clean();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Mock Schedule Page</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/dashicons/4.6.3/css/dashicons.min.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <style>
        /* Mock CSS from admin.css */
        .aips-wrap { max-width: 1200px; margin: 20px auto; font-family: sans-serif; }
        .wp-list-table { width: 100%; border-collapse: collapse; }
        .wp-list-table th, .wp-list-table td { text-align: left; padding: 10px; border-bottom: 1px solid #ccc; }
        .button-link-delete { color: #b32d2e; background: none; border: none; cursor: pointer; text-decoration: underline; }
        .aips-confirm-delete { color: red; font-weight: bold; }
    </style>
</head>
<body>
    <?php echo $content; ?>
    <script>
        var aipsAjax = { ajaxUrl: '/mock-ajax', nonce: 'mock_nonce' };
        // Mock L10n
        var aipsAdminL10n = {
            clickToConfirm: 'Click again to confirm',
            errorOccurred: 'Error',
            deleteStructureFailed: 'Failed'
        };
    </script>
    <script>
    // Inject the admin.js content here for testing
    <?php echo file_get_contents('../ai-post-scheduler/assets/js/admin.js'); ?>
    </script>
</body>
</html>
