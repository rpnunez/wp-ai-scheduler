<?php
// Mock WordPress Environment
define('ABSPATH', '/tmp/');
define('HOUR_IN_SECONDS', 3600);

// Mocks
class MockWPDB {
    public $prefix = 'wp_';
    public $updated_data = [];
    public $update_result = true;

    public function update($table, $data, $where) {
        $this->updated_data[] = ['table' => $table, 'data' => $data, 'where' => $where];
        return $this->update_result;
    }

    public function prepare($query, $args) { return $query; }
    public function get_results($query) { return []; }
    public function get_row($query) { return null; }
    public function insert($table, $data) { return 1; }
    public function delete($table, $where) { return 1; }
}

global $wpdb;
$wpdb = new MockWPDB();

function current_time($type) {
    return $type === 'mysql' ? '2024-05-28 10:00:00' : 1716890400; // 10:00:00
}
function sanitize_text_field($str) { return $str; }
function absint($int) { return (int)$int; }
function wp_parse_args($args, $defaults) { return array_merge($defaults, $args); }
function is_wp_error($thing) { return false; }
function do_action($tag) {}
function add_action($tag, $callback) {}
function add_filter($tag, $callback) {}
function get_transient($key) { return false; }
function set_transient($key, $val, $exp) {}
function delete_transient($key) {}
function date_i18n($format, $timestamp) { return date($format, $timestamp); }
function get_option($opt) { return ''; }

// Mock Classes
class AIPS_Logger { public function log($msg, $level, $ctx = []) { echo "LOG: $msg\n"; } }
class AIPS_Interval_Calculator {
    public function calculate_next_run($freq, $start) {
        if ($freq === 'daily') return '2024-05-29 10:00:00'; // +1 day
        return $start;
    }
    public function get_intervals() { return []; }
    public function merge_with_wp_schedules($s) { return $s; }
}
class AIPS_Template_Type_Selector {
    public function select_structure($s) { return 1; }
    public function invalidate_count_cache($id) { echo "CACHE INVALIDATED: $id\n"; }
}
class AIPS_Generator {
    public function generate_post($t, $v, $topic) { echo "GENERATING POST...\n"; return 123; }
}
class AIPS_Activity_Repository { public function create($data) {} }

// Include needed files (we need to handle dependencies manually or use the actual files)
// For this verification, we'll just mock the Repository methods that Scheduler uses
class AIPS_Schedule_Repository {
    public function update($id, $data) {
        global $wpdb;
        echo "REPO UPDATE: ID=$id NextRun=" . (isset($data['next_run']) ? $data['next_run'] : 'N/A') . " LastRun=" . (isset($data['last_run']) ? $data['last_run'] : 'N/A') . "\n";
        return $wpdb->update('schedule', $data, ['id' => $id]);
    }
    public function delete($id) { echo "REPO DELETE: $id\n"; return true; }
    public function update_last_run($id, $time) {
        echo "REPO UPDATE LAST RUN: ID=$id Time=$time\n";
        return true;
    }
}

// Load Scheduler Class (assuming it's in the include path or we copy it)
// We will verify the logic by implementing a minimal Scheduler subclass or copying the method
// Ideally we include the real file
require_once 'ai-post-scheduler/includes/class-aips-scheduler.php';

// Test
echo "--- TEST START ---\n";
$scheduler = new AIPS_Scheduler();

// Inject Mocks via Reflection if needed, or rely on internal instantiation using mocked classes above
// The Scheduler constructor instantiates dependencies using `new ClassName()`, which uses our mocks.

// Mock Due Schedules
$due_schedules = [
    (object)[
        'schedule_id' => 1,
        'template_id' => 10,
        'name' => 'Test Template',
        'frequency' => 'daily',
        'next_run' => '2024-05-28 10:00:00', // Due now
        'topic' => 'Test Topic',
        'prompt_template' => 'Prompt',
        'title_prompt' => '',
        'post_status' => 'draft',
        'post_category' => '',
        'post_tags' => '',
        'post_author' => 1,
    ]
];

// We need to inject `due_schedules` into the logic.
// Since we can't easily mock `$wpdb->get_results` inside the method without a complex mock object (which we did basic version of),
// let's refine the MockWPDB to return our schedules.

$wpdb->get_results_return = $due_schedules;
$wpdb->get_results = function($query) use ($due_schedules) {
    return $due_schedules;
};

// We need to modify the MockWPDB class to support closure overrides or just return the static data
class MockWPDB_Advanced extends MockWPDB {
    public function get_results($query) {
        return [
            (object)[
                'schedule_id' => 1,
                'template_id' => 10,
                'name' => 'Test Template',
                'frequency' => 'daily',
                'next_run' => '2024-05-28 10:00:00',
                'topic' => 'Test Topic',
                'prompt_template' => 'Prompt',
                'title_prompt' => '',
                'post_status' => 'draft',
                'post_category' => '',
                'post_tags' => '',
                'post_author' => 1,
            ]
        ];
    }
}
$wpdb = new MockWPDB_Advanced();

// Run Process
$scheduler->process_scheduled_posts();

echo "--- TEST END ---\n";
