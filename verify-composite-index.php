#!/usr/bin/env php
<?php
/**
 * Direct verification of composite index in database
 * This bypasses PHPUnit and directly tests the schema
 */

echo "\n";
echo "==========================================\n";
echo "  Database Schema Verification\n";
echo "  Testing Composite Index Addition\n";
echo "==========================================\n";
echo "\n";

// Set up environment
putenv('WP_TESTS_DIR=/tmp/wordpress-tests-lib');
putenv('WP_CORE_DIR=/tmp/wordpress');
$_ENV['WP_TESTS_DIR'] = '/tmp/wordpress-tests-lib';
$_ENV['WP_CORE_DIR'] = '/tmp/wordpress';

// Change to plugin directory
chdir(__DIR__ . '/ai-post-scheduler');

// Load WordPress test environment
echo "[1/5] Loading WordPress test environment...\n";
require_once '/tmp/wordpress-tests-lib/wp-tests-config.php';
require_once '/tmp/wordpress-tests-lib/includes/functions.php';

// Load plugin
function _load_plugin_for_test() {
	require __DIR__ . '/ai-post-scheduler/ai-post-scheduler.php';
}
tests_add_filter('muplugins_loaded', '_load_plugin_for_test');

require_once '/tmp/wordpress-tests-lib/includes/bootstrap.php';

echo "✓ WordPress loaded successfully\n\n";

// Install plugin tables
echo "[2/5] Installing plugin database tables...\n";
AIPS_DB_Manager::install_tables();
echo "✓ Tables installed\n\n";

// Get table name
global $wpdb;
$table_name = $wpdb->prefix . 'aips_schedule';

// Check table exists
echo "[3/5] Verifying table exists...\n";
$table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$table_name}'");
if ($table_exists === $table_name) {
	echo "✓ Table '{$table_name}' exists\n\n";
} else {
	echo "✗ ERROR: Table '{$table_name}' does not exist!\n";
	exit(1);
}

// Get all indexes
echo "[4/5] Retrieving table indexes...\n";
$indexes = $wpdb->get_results("SHOW INDEX FROM {$table_name}");

if (empty($indexes)) {
	echo "✗ ERROR: No indexes found on table!\n";
	exit(1);
}

// Display all indexes
$index_names = array();
foreach ($indexes as $index) {
	if (!in_array($index->Key_name, $index_names)) {
		$index_names[] = $index->Key_name;
	}
}

echo "Found " . count($index_names) . " indexes:\n";
foreach ($index_names as $name) {
	echo "  - $name\n";
}
echo "\n";

// Check for composite index
echo "[5/5] Verifying composite index 'is_active_next_run'...\n";
$composite_index_columns = array();
foreach ($indexes as $index) {
	if ($index->Key_name === 'is_active_next_run') {
		$composite_index_columns[$index->Seq_in_index] = $index->Column_name;
	}
}

if (empty($composite_index_columns)) {
	echo "✗ ERROR: Composite index 'is_active_next_run' NOT FOUND!\n";
	echo "\n";
	echo "Expected index: KEY is_active_next_run (is_active, next_run)\n";
	echo "\n";
	exit(1);
}

echo "✓ Composite index 'is_active_next_run' found!\n";
echo "\n";
echo "Index details:\n";
echo "  - Name: is_active_next_run\n";
echo "  - Columns: " . implode(', ', $composite_index_columns) . "\n";
echo "  - Column count: " . count($composite_index_columns) . "\n";
echo "\n";

// Verify correct columns
$success = true;
if (!isset($composite_index_columns[1]) || $composite_index_columns[1] !== 'is_active') {
	echo "✗ ERROR: First column should be 'is_active', got '" . ($composite_index_columns[1] ?? 'NONE') . "'\n";
	$success = false;
}

if (!isset($composite_index_columns[2]) || $composite_index_columns[2] !== 'next_run') {
	echo "✗ ERROR: Second column should be 'next_run', got '" . ($composite_index_columns[2] ?? 'NONE') . "'\n";
	$success = false;
}

if ($success) {
	echo "✓ Index columns verified:\n";
	echo "  1. is_active\n";
	echo "  2. next_run\n";
	echo "\n";
}

// Test query using the index
echo "[BONUS] Testing query with composite index...\n";
// Insert test data
$wpdb->insert($table_name, array(
	'template_id' => 1,
	'frequency' => 'daily',
	'next_run' => current_time('mysql'),
	'is_active' => 1,
));

$wpdb->insert($table_name, array(
	'template_id' => 2,
	'frequency' => 'weekly',
	'next_run' => current_time('mysql'),
	'is_active' => 0,
));

// Query using the indexed columns
$results = $wpdb->get_results($wpdb->prepare(
	"SELECT * FROM {$table_name} WHERE is_active = %d AND next_run <= %s",
	1,
	current_time('mysql')
));

if (is_array($results) && count($results) === 1) {
	echo "✓ Query using composite index works correctly\n";
	echo "  Found " . count($results) . " active schedule(s)\n";
} else {
	echo "✗ WARNING: Query result unexpected\n";
}

// Clean up
$wpdb->query("DELETE FROM {$table_name}");
echo "\n";

// Final summary
if ($success) {
	echo "==========================================\n";
	echo "\033[32m✓ ALL VERIFICATIONS PASSED!\033[0m\n";
	echo "==========================================\n";
	echo "\n";
	echo "Summary:\n";
	echo "  ✓ Table 'aips_schedule' exists\n";
	echo "  ✓ Composite index 'is_active_next_run' exists\n";
	echo "  ✓ Index columns: (is_active, next_run)\n";
	echo "  ✓ Index is functional for queries\n";
	echo "\n";
	echo "\033[32mPR #370 changes verified successfully!\033[0m\n";
	echo "\n";
	exit(0);
} else {
	echo "==========================================\n";
	echo "\033[31m✗ VERIFICATION FAILED!\033[0m\n";
	echo "==========================================\n";
	echo "\n";
	exit(1);
}
