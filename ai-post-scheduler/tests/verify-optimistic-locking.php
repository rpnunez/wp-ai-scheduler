<?php
// Mock WordPress environment
define('ABSPATH', __DIR__ . '/');

// Mock Functions
function absint($n) { return (int)$n; }
function sanitize_text_field($s) { return trim($s); }
function delete_transient($t) { return true; }
function current_time($type) { return date('Y-m-d H:i:s'); }

// Mock WPDB
class MockWPDB {
    public $prefix = 'wp_';
    public $rows_affected = 0;
    public $last_query = '';

    public function prepare($query, ...$args) {
        $this->last_query = $query; // In real life this interpolates
        foreach ($args as $arg) {
            // Simple replace for verification (not secure, just for test)
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
}

global $wpdb;
$wpdb = new MockWPDB();

// Include class
require_once dirname(__DIR__) . '/includes/class-aips-schedule-repository.php';

echo "Starting Verification of Actual Implementation...\n";

$repo = new AIPS_Schedule_Repository();
$id = 123;
$new_run = '2023-01-01 12:00:00';
$old_run = '2023-01-01 10:00:00';

if (!method_exists($repo, 'update_next_run_conditional')) {
    echo "FAIL: Method update_next_run_conditional does not exist.\n";
    exit(1);
}

// Test Case 1: Success (Lock acquired)
$wpdb->rows_affected = 1;
$result = $repo->update_next_run_conditional($id, $new_run, $old_run);

if ($result === true) {
    echo "PASS: Lock acquired when rows_affected > 0.\n";
} else {
    echo "FAIL: Lock failed when rows_affected > 0. Result: " . var_export($result, true) . "\n";
}

// Verify Query
// We expect: UPDATE wp_aips_schedule SET next_run = '...' WHERE id = '...' AND next_run = '...'
if (strpos($wpdb->last_query, "WHERE id = '123' AND next_run = '2023-01-01 10:00:00'") !== false) {
    echo "PASS: Query contains correct WHERE clause.\n";
} else {
    echo "FAIL: Query incorrect: " . $wpdb->last_query . "\n";
}


// Test Case 2: Failure (Lock lost)
$wpdb->rows_affected = 0;
$result = $repo->update_next_run_conditional($id, $new_run, $old_run);

if ($result === false) {
    echo "PASS: Lock denied when rows_affected == 0.\n";
} else {
    echo "FAIL: Lock succeeded when rows_affected == 0.\n";
}

echo "Verification Complete.\n";
