<?php
// Mock WordPress Environment
define('ABSPATH', '/tmp/');
define('AIPS_PLUGIN_DIR', '/app/ai-post-scheduler/');

// Mock Classes
class AIPS_History_Repository {
    public function get_stats() { return array('total' => 10, 'completed' => 8, 'failed' => 2, 'processing' => 0, 'success_rate' => 80); }
}
class AIPS_Template_Repository {
    public function get_all() { return array(); }
}
class AIPS_Logger {
    public function get_log_files() { return array(); }
    public function get_logs() { return array(); }
}

// Mock Functions
function add_action() {}
function get_option($key, $default) { return $default; }
function wp_send_json_success() {}
function check_ajax_referer() {}
function current_user_can() { return true; }

// Global DB Mock
class WPDB {
    public $prefix = 'wp_';
    public function get_results() { return array(); }
    public function prepare($q) { return $q; }
    public function esc_like($s) { return $s; }
}
$wpdb = new WPDB();

// Include the class to test
require_once '/app/ai-post-scheduler/includes/class-aips-dashboard.php';

// Test
echo "Instantiating Dashboard...\n";
$dashboard = new AIPS_Dashboard();

echo "Fetching Data...\n";
$data = $dashboard->get_dashboard_data();

if (isset($data['stats']) && $data['stats']['success_rate'] == 80) {
    echo "Stats verified.\n";
} else {
    echo "Stats failed.\n";
    exit(1);
}

if (isset($data['suggestions']) && count($data['suggestions']) > 0) {
    echo "Suggestions verified.\n";
} else {
    echo "Suggestions failed.\n";
    exit(1);
}

echo "Backend Test Passed.\n";
