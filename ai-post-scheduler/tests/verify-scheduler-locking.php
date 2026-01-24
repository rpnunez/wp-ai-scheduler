<?php
// Mock WordPress environment
define('ABSPATH', __DIR__ . '/');

// Mock Functions
function absint($n) { return (int)$n; }
function sanitize_text_field($s) { return trim($s); }
function delete_transient($t) { return true; }
function current_time($type) {
    if ($type === 'timestamp') return time();
    return date('Y-m-d H:i:s');
}
function add_action($tag, $callback) {}
function add_filter($tag, $callback) {}
function __($text, $domain) { return $text; }
// function sprintf is native
function is_wp_error($thing) { return false; }
function get_post($id) { return (object)['post_status' => 'publish', 'post_title' => 'Test', 'post_type' => 'post']; }
function do_action($tag, ...$args) {}

// Mock Dependencies
class AIPS_Logger {
    public function log($msg, $level = 'info', $ctx = []) {
        echo "LOG [$level]: $msg\n";
    }
}
class AIPS_Template_Type_Selector {
    public function select_structure($s) { return 1; }
    public function invalidate_count_cache($id) {}
}
class AIPS_Activity_Repository {
    public function create($data) {}
}
class AIPS_Generator {
    public function generate_post($t, $v, $topic) { return 123; }
}
class AIPS_Templates {
}
class AIPS_Voices {
}
class AIPS_Interval_Calculator {
    public function calculate_next_run($f, $start) {
        return date('Y-m-d H:i:s', strtotime($start) + 3600);
    }
    public function get_intervals() { return []; }
    public function merge_with_wp_schedules($s) { return $s; }
}

// Mock WPDB
class MockWPDB {
    public $prefix = 'wp_';
    public $rows_affected = 0;
    public $prepare_returns = '';
    public $results = [];

    public function prepare($query, ...$args) {
        $this->last_query = $query;
        foreach ($args as $arg) {
            $this->last_query = preg_replace('/%[sd]/', "'$arg'", $this->last_query, 1);
        }
        return $this->last_query;
    }

    public function query($query) {
        $this->last_query = $query;
        return $this->rows_affected;
    }

    public function update($table, $data, $where) {
        return $this->rows_affected > 0 ? 1 : 0;
    }

    public function get_results($query) {
        return $this->results;
    }
}

global $wpdb;
$wpdb = new MockWPDB();

// Include classes
require_once dirname(__DIR__) . '/includes/class-aips-schedule-repository.php';
require_once dirname(__DIR__) . '/includes/class-aips-scheduler.php';

echo "Starting Scheduler Locking Verification...\n";

$scheduler = new AIPS_Scheduler();

// Setup Mock Data
// We need get_results to return a schedule
$wpdb->results = [
    (object)[
        'schedule_id' => 1,
        'template_id' => 1,
        'frequency' => 'hourly',
        'next_run' => '2023-01-01 10:00:00',
        'is_active' => 1,
        'name' => 'Test Template',
        'prompt_template' => 'test',
        'title_prompt' => 'test',
        'post_status' => 'publish',
        'post_category' => 1,
        'post_tags' => '',
        'post_author' => 1
    ]
];

// Test Case: Lock Lost (rows_affected = 0)
echo "Test Case: Lock Lost (rows_affected = 0)\n";
$wpdb->rows_affected = 0;

ob_start(); // Capture output
$scheduler->process_scheduled_posts();
$output = ob_get_clean();

echo $output;

if (strpos($output, "Optimistic Lock Lost") !== false) {
    echo "PASS: Scheduler correctly detected lost lock.\n";
} else {
    echo "FAIL: Scheduler did not report lost lock.\n";
    exit(1);
}

// Test Case: Lock Acquired (rows_affected = 1)
echo "Test Case: Lock Acquired (rows_affected = 1)\n";
$wpdb->rows_affected = 1;

ob_start();
$scheduler->process_scheduled_posts();
$output = ob_get_clean();

echo $output;

if (strpos($output, "Processing schedule: 1") !== false) {
    echo "PASS: Scheduler proceeded when lock acquired.\n";
} else {
    echo "FAIL: Scheduler did not process when lock acquired.\n";
    exit(1);
}

echo "Scheduler Verification Complete.\n";
