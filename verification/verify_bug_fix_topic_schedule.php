<?php
// Mock script to verify the logic flaw in AIPS_Research_Controller::ajax_schedule_trending_topics

// Mock the environment
function absint($val) { return (int)$val; }
function sanitize_text_field($val) { return trim($val); }
function current_time($type) { return date('Y-m-d H:i:s'); }
function check_ajax_referer($action, $nonce) { return true; }
function current_user_can($cap) { return true; }
function wp_send_json_error($data) { echo "ERROR: " . json_encode($data) . "\n"; exit; }
function wp_send_json_success($data) { echo "SUCCESS: " . json_encode($data) . "\n"; }
function do_action($tag, ...$args) {}

class AIPS_Research_Service {
    public function research_trending_topics($niche, $count, $keywords) {}
    public function get_top_topics($topics, $count) {}
}

class AIPS_Trending_Topics_Repository {
    public function save_research_batch($topics, $niche) {}
    public function get_all($args) {}
    public function get_stats() {}
    public function get_niche_list() {}
    public function delete($id) {}
    public function get_by_id($id) {
        // Return a mock topic array
        return ['topic' => 'Mock Topic ' . $id];
    }
}

class AIPS_Logger {
    public function log($msg, $type, $context = []) {
        echo "LOG [$type]: $msg\n";
    }
}

class AIPS_Interval_Calculator {
    public function get_interval_duration($freq) { return 3600; }
}

class AIPS_Scheduler {
    // Mock scheduler
}

class AIPS_Schedule_Repository {
    public function create($data) {
        // THIS IS THE CHECK
        if (!isset($data['topic'])) {
            echo "FAIL: 'topic' key is missing in schedule data!\n";
            print_r($data);
        } else {
            echo "PASS: 'topic' key is present: " . $data['topic'] . "\n";
        }
        return 123;
    }
}

// Simulating the controller method logic
// We can't instantiate the real controller easily because it calls `new` in constructor
// So we will just simulate the specific logic block that is broken.

$topic_ids = [1, 2, 3];
$template_id = 10;
$start_date = '2024-05-25 10:00:00';
$frequency = 'hourly';

// Mock repository behavior
$repository = new AIPS_Trending_Topics_Repository();
$topics = [];
foreach ($topic_ids as $topic_id) {
    $t = $repository->get_by_id($topic_id);
    if ($t) {
        $topics[] = $t['topic'];
    }
}

$schedule_repository = new AIPS_Schedule_Repository();
$interval_calculator = new AIPS_Interval_Calculator();

$base_time = strtotime($start_date);
$interval_duration = $interval_calculator->get_interval_duration($frequency);

echo "--- Simulating flawed logic ---\n";

foreach ($topics as $index => $topic) {
    $next_run_time = $base_time + ($interval_duration * $index);

    // THIS IS THE ORIGINAL BROKEN CODE BLOCK FROM CONTROLLER
    $schedule_data = array(
        'template_id' => $template_id,
        'frequency' => $frequency,
        'next_run' => date('Y-m-d H:i:s', $next_run_time),
        'active' => 1,
        'created_at' => current_time('mysql'),
    );

    $schedule_repository->create($schedule_data);
}
