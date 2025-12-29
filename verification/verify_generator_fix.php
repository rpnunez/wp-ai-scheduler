<?php
// Mock WordPress environment
define('ABSPATH', '/var/www/html/');
define('WP_CONTENT_DIR', '/var/www/html/wp-content');
define('OBJECT', 'OBJECT');
define('OBJECT_K', 'OBJECT_K');
define('ARRAY_A', 'ARRAY_A');
define('ARRAY_N', 'ARRAY_N');

// Mock classes
class WP_Error {
    public function get_error_message() { return "Error"; }
}
function is_wp_error($thing) { return false; }
function wp_send_json_success($data = null) { echo "SUCCESS: " . json_encode($data) . "\n"; }
function wp_send_json_error($data = null) { echo "ERROR: " . json_encode($data) . "\n"; }
function current_user_can($cap) { return true; }
function check_ajax_referer($action, $query_arg) { return true; }
function absint($val) { return intval($val); }
function sanitize_text_field($str) { return trim($str); }
function get_edit_post_link($id, $context) { return "http://example.com/wp-admin/post.php?post=$id&action=edit"; }
function __($text, $domain) { return $text; }

// Mock AIPS classes
class AIPS_Scheduler {
    public function save_schedule($data) { return 1; }
    public function delete_schedule($id) { return true; }
    public function toggle_active($id, $is_active) { return true; }
}
class AIPS_Templates {
    public function get($id) {
        $t = new stdClass();
        $t->id = $id;
        $t->voice_id = 0;
        $t->post_quantity = 2;
        return $t;
    }
}
class AIPS_Voices {
    public function get($id) { return null; }
}
class AIPS_Generator {
    public $last_topic = null;
    public function generate_post($template, $voice = null, $topic = null) {
        $this->last_topic = $topic;
        return 123;
    }
}
class AIPS_Interval_Calculator {
    public function is_valid_frequency($freq) { return true; }
}

// Include Controller
require_once 'ai-post-scheduler/includes/class-aips-schedule-controller.php';

// Test Run Now
$_POST['template_id'] = 1;
$_POST['topic'] = 'Test Topic';
$_POST['quantity'] = 1; // Not used by code but simulating post data

// Capture output
ob_start();
$controller = new AIPS_Schedule_Controller();
// We need to inject our mock generator or use reflection/modifiers if we can't inject.
// Since AIPS_Schedule_Controller instantiates AIPS_Generator inside the method,
// we will have to modify the file first to prove the fix works,
// OR we rely on the fact that I will read the file and see the change.
// But to verify via script, I need to see if the topic is passed.

// Wait, I can't mock AIPS_Generator inside the method without namespaces or runkit.
// I'll just verify the code change by reading the file after modification.
// But I can write a script that instantiates the controller and runs the method
// IF I can modify the class to accept a generator dependency or if I use a mock that the controller uses.

// Actually, in this environment, I can't easily mock internal `new Class()` calls.
// So I will apply the fix and verify by reading the code.
// AND I will verify syntax using php -l if possible, or just trust my eyes.
// The user wants verification.
// I can modify the verify script to include the MODIFIED file content (simulated)
// and check if it passes the topic.

echo "Verification script prepared.\n";
