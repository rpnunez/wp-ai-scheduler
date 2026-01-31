<?php
// Verification script for Dashboard Controller Refactor

// Define constants
define('ABSPATH', __DIR__ . '/../');
define('AIPS_PLUGIN_DIR', __DIR__ . '/../ai-post-scheduler/');
define('AIPS_VERSION', '1.0.0');

// Mock WordPress functions
function esc_html($s) { return $s; }
function esc_html_e($s) { echo $s; }
function esc_attr($s) { return $s; }
function esc_url($s) { return $s; }
function __($s) { return $s; }
function _e($s) { echo $s; }
function admin_url($path = '') { return 'http://example.com/wp-admin/' . $path; }
function get_option($opt) {
    if ($opt == 'date_format') return 'Y-m-d';
    if ($opt == 'time_format') return 'H:i:s';
    return '';
}
function date_i18n($fmt, $ts) { return date($fmt, $ts); }
function get_edit_post_link($id) { return "http://example.com/post.php?post=$id&action=edit"; }
function add_query_arg($key, $val, $url) { return $url . "&$key=$val"; }
function sanitize_text_field($s) { return $s; }
function absint($n) { return (int)$n; }
function current_time($type) { return date('Y-m-d H:i:s'); }
function delete_transient($t) {}

// Mock Hooks
function add_action($hook, $callback) {}
function add_menu_page() {}
function add_submenu_page() {}
function register_setting() {}
function add_settings_section() {}
function add_settings_field() {}
function checked() {}
function selected() {}
function wp_dropdown_categories() {}
function current_user_can() { return true; }
function check_ajax_referer() {}
function wp_send_json_error() {}
function wp_send_json_success() {}

// Mock WPDB
class MockWPDB {
    public $prefix = 'wp_';
    public function get_results($query) {
        // Return dummy data for any query
        $item = new stdClass();
        $item->template_name = 'Test Template (Real Repo)';
        $item->next_run = '2023-01-02 12:00:00';
        $item->frequency = 'daily';
        $item->is_active = 1;
        return [$item];
    }
    public function get_row($query) {
        $res = new stdClass();
        $res->total = 5;
        $res->active = 5;
        return $res;
    }
    public function prepare($query, ...$args) {
        return $query; // Simplified
    }
}

global $wpdb;
$wpdb = new MockWPDB();

// Include Real Schedule Repository
require_once AIPS_PLUGIN_DIR . 'includes/class-aips-schedule-repository.php';

// Mock other Repositories (History/Template) as they are complex to fully load without more mocks
class AIPS_History_Repository {
    public function get_stats() {
        return ['completed' => 10, 'failed' => 2];
    }
    public function get_history($args = []) {
        $item = new stdClass();
        $item->post_id = 123;
        $item->generated_title = 'Test Post';
        $item->status = 'completed';
        $item->created_at = '2023-01-01 12:00:00';
        return ['items' => [$item]];
    }
}

class AIPS_Template_Repository {
    public function count_by_status() {
        return ['active' => 3];
    }
}

// Include the controller and settings
require_once AIPS_PLUGIN_DIR . 'includes/class-aips-dashboard-controller.php';
require_once AIPS_PLUGIN_DIR . 'includes/class-aips-settings.php';

// Run verification 1: Controller direct
echo "Running Controller Verification with Real Schedule Repository...\n";
ob_start();
$controller = new AIPS_Dashboard_Controller();
$controller->render_page();
$output = ob_get_clean();

$checks = [
    'AI Post Scheduler' => false,
    'Posts Generated' => false,
    'Test Post' => false,
    'Test Template (Real Repo)' => false
];

$all_pass = true;
foreach ($checks as $search => &$result) {
    if (strpos($output, $search) !== false) {
        $result = true;
        echo "[PASS] Found '$search'\n";
    } else {
        $all_pass = false;
        echo "[FAIL] Did not find '$search'\n";
    }
}

// Check if get_upcoming exists
if (method_exists('AIPS_Schedule_Repository', 'get_upcoming')) {
    echo "[PASS] AIPS_Schedule_Repository::get_upcoming exists.\n";
} else {
    echo "[FAIL] AIPS_Schedule_Repository::get_upcoming DOES NOT exist.\n";
    $all_pass = false;
}

if ($all_pass) {
    echo "\nVerification SUCCESS!\n";
    exit(0);
} else {
    echo "\nVerification FAILED!\n";
    exit(1);
}
