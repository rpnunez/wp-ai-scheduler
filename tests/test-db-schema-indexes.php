<?php
/**
 * Test Database Schema Indexes
 *
 * Verifies that the AIPS_DB_Manager class returns the correct SQL with the new indexes.
 */

define('ABSPATH', '/tmp/');

// Mock WPDB
class MockWPDB {
    public $prefix = 'wp_';

    public function get_charset_collate() {
        return 'DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci';
    }
}

global $wpdb;
$wpdb = new MockWPDB();

// Include the class file
require_once __DIR__ . '/../ai-post-scheduler/includes/class-aips-db-manager.php';

// Instantiate and get schema
$db_manager = new AIPS_DB_Manager();
$schema = $db_manager->get_schema();

// Helper to find table definition
function get_table_def($schema, $table_suffix) {
    global $wpdb;
    $table_name = $wpdb->prefix . $table_suffix;
    foreach ($schema as $sql) {
        if (strpos($sql, "CREATE TABLE $table_name") !== false) {
            return $sql;
        }
    }
    return null;
}

// Verify aips_history indexes
$history_sql = get_table_def($schema, 'aips_history');

if (!$history_sql) {
    echo "FAIL: aips_history table definition not found.\n";
    exit(1);
}

$required_history_indexes = [
    'KEY template_status_created (template_id, status, created_at)',
    'KEY created_at (created_at)'
];

$missing_indexes = [];
foreach ($required_history_indexes as $index) {
    if (strpos($history_sql, $index) === false) {
        $missing_indexes[] = $index;
    }
}

if (!empty($missing_indexes)) {
    echo "FAIL: Missing indexes in aips_history:\n";
    foreach ($missing_indexes as $index) {
        echo "  - $index\n";
    }
    echo "SQL Content:\n$history_sql\n";
    exit(1);
} else {
    echo "PASS: aips_history has required indexes.\n";
}

// Verify aips_templates indexes
$templates_sql = get_table_def($schema, 'aips_templates');

if (!$templates_sql) {
    echo "FAIL: aips_templates table definition not found.\n";
    exit(1);
}

$required_templates_indexes = [
    'KEY is_active (is_active)'
];

$missing_templates_indexes = [];
foreach ($required_templates_indexes as $index) {
    if (strpos($templates_sql, $index) === false) {
        $missing_templates_indexes[] = $index;
    }
}

if (!empty($missing_templates_indexes)) {
    echo "FAIL: Missing indexes in aips_templates:\n";
    foreach ($missing_templates_indexes as $index) {
        echo "  - $index\n";
    }
    echo "SQL Content:\n$templates_sql\n";
    exit(1);
} else {
    echo "PASS: aips_templates has required indexes.\n";
}

exit(0);
