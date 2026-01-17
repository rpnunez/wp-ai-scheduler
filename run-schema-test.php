#!/usr/bin/env php
<?php
/**
 * Direct test runner for database schema tests
 * Bypasses WordPress' strict test file discovery
 */

// Set up environment
putenv('WP_TESTS_DIR=/tmp/wordpress-tests-lib');
putenv('WP_CORE_DIR=/tmp/wordpress');
$_ENV['WP_TESTS_DIR'] = '/tmp/wordpress-tests-lib';
$_ENV['WP_CORE_DIR'] = '/tmp/wordpress';

// Change to plugin directory
chdir(__DIR__ . '/ai-post-scheduler');

// Load bootstrap
require_once 'tests/bootstrap.php';

// Load the test file
require_once 'tests/test-db-schema.php';

// Run the test manually
echo "\n";
echo "======================================\n";
echo "  Database Schema Test Runner\n";
echo "======================================\n";
echo "\n";

$test = new Test_AIPS_DB_Schema();
$test->setUp();

$tests_run = 0;
$tests_passed = 0;
$tests_failed = 0;
$errors = array();

// Run each test method
$methods = array(
	'test_schedule_table_has_composite_index',
	'test_schedule_table_has_all_indexes',
	'test_schedule_table_structure',
	'test_composite_index_query_usage',
	'test_dbdelta_adds_new_index',
);

foreach ($methods as $method) {
	$tests_run++;
	echo "Running: $method ... ";
	
	try {
		$test->setUp();
		$test->$method();
		$test->tearDown();
		echo "\033[32mPASSED\033[0m\n";
		$tests_passed++;
	} catch (Exception $e) {
		echo "\033[31mFAILED\033[0m\n";
		$tests_failed++;
		$errors[] = array(
			'method' => $method,
			'error' => $e->getMessage(),
			'trace' => $e->getTraceAsString(),
		);
	}
}

echo "\n";
echo "======================================\n";
echo "  Test Results\n";
echo "======================================\n";
echo "\n";
echo "Tests run: $tests_run\n";
echo "\033[32mPassed: $tests_passed\033[0m\n";
if ($tests_failed > 0) {
	echo "\033[31mFailed: $tests_failed\033[0m\n";
	echo "\n";
	echo "Failures:\n";
	echo "----------\n";
	foreach ($errors as $error) {
		echo "\n";
		echo "Method: {$error['method']}\n";
		echo "Error: {$error['error']}\n";
		echo "\n";
	}
}

echo "\n";

if ($tests_failed === 0) {
	echo "\033[32m✓ All tests passed!\033[0m\n";
	exit(0);
} else {
	echo "\033[31m✗ Some tests failed.\033[0m\n";
	exit(1);
}
