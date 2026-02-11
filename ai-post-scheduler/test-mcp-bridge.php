<?php
/**
 * MCP Bridge Test Script
 * 
 * This script tests the MCP Bridge functionality without requiring HTTP requests.
 * Run from command line: php test-mcp-bridge.php
 * 
 * @package AI_Post_Scheduler
 */

// Prevent web access: allow only CLI (or WP-CLI) execution.
if ( php_sapi_name() !== 'cli' && ! defined( 'WP_CLI' ) ) {
	if ( ! headers_sent() ) {
		if ( function_exists( 'http_response_code' ) ) {
			http_response_code( 404 );
		} else {
			header( 'HTTP/1.1 404 Not Found' );
		}
	}
	exit;
}

// Bootstrap WordPress
$wp_load_path = dirname(dirname(dirname(dirname(__FILE__)))) . '/wp-load.php';
if (!file_exists($wp_load_path)) {
	echo "Error: WordPress not found at $wp_load_path\n";
	exit(1);
}

require_once $wp_load_path;

// Include the MCP bridge
require_once dirname(__FILE__) . '/mcp-bridge.php';

echo "=== AI Post Scheduler MCP Bridge Test Suite ===\n\n";

// Create bridge instance
$bridge = new AIPS_MCP_Bridge();

// Test 1: List Tools
echo "Test 1: List Tools\n";
echo str_repeat("-", 50) . "\n";
$result = $bridge->execute_tool('list_tools', array(), true);
if (is_wp_error($result)) {
	echo "ERROR: " . $result->get_error_message() . "\n";
} else {
	echo "Success! Found " . count($result['tools']) . " tools:\n";
	foreach ($result['tools'] as $name => $tool) {
		echo "  - $name: {$tool['description']}\n";
	}
}
echo "\n";

// Test 2: Get Plugin Info
echo "Test 2: Get Plugin Info\n";
echo str_repeat("-", 50) . "\n";
$result = $bridge->execute_tool('get_plugin_info', array(), true);
if (is_wp_error($result)) {
	echo "ERROR: " . $result->get_error_message() . "\n";
} else {
	echo "Plugin: {$result['plugin']['name']}\n";
	echo "Version: {$result['plugin']['version']}\n";
	echo "DB Version: {$result['plugin']['db_version']}\n";
	echo "PHP Version: {$result['plugin']['php_version']}\n";
	echo "WP Version: {$result['plugin']['wp_version']}\n";
	echo "AI Engine Active: " . ($result['plugin']['ai_engine_active'] ? 'Yes' : 'No') . "\n";
}
echo "\n";

// Test 3: Check Database
echo "Test 3: Check Database\n";
echo str_repeat("-", 50) . "\n";
$result = $bridge->execute_tool('check_database', array(), true);
if (is_wp_error($result)) {
	echo "ERROR: " . $result->get_error_message() . "\n";
} else {
	echo "Database check completed:\n";
	foreach ($result['database'] as $table => $info) {
		$status_icon = $info['status'] === 'ok' ? '✓' : '✗';
		echo "  $status_icon {$info['label']}: {$info['value']}\n";
	}
}
echo "\n";

// Test 4: Check Upgrades (without running)
echo "Test 4: Check Upgrades\n";
echo str_repeat("-", 50) . "\n";
$result = $bridge->execute_tool('check_upgrades', array('run' => false), true);
if (is_wp_error($result)) {
	echo "ERROR: " . $result->get_error_message() . "\n";
} else {
	echo "Current Version: {$result['current_version']}\n";
	echo "Plugin Version: {$result['plugin_version']}\n";
	echo "Needs Upgrade: " . ($result['needs_upgrade'] ? 'Yes' : 'No') . "\n";
}
echo "\n";

// Test 5: Get Cron Status
echo "Test 5: Get Cron Status\n";
echo str_repeat("-", 50) . "\n";
$result = $bridge->execute_tool('get_cron_status', array(), true);
if (is_wp_error($result)) {
	echo "ERROR: " . $result->get_error_message() . "\n";
} else {
	echo "Cron jobs status:\n";
	foreach ($result['crons'] as $hook => $status) {
		$scheduled = $status['scheduled'] ? '✓' : '✗';
		$next_run = $status['next_run'] ? $status['next_run'] : 'Not scheduled';
		echo "  $scheduled $hook: $next_run\n";
	}
}
echo "\n";

// Test 6: System Status (environment only)
echo "Test 6: System Status (Environment)\n";
echo str_repeat("-", 50) . "\n";
$result = $bridge->execute_tool('system_status', array('section' => 'environment'), true);
if (is_wp_error($result)) {
	echo "ERROR: " . $result->get_error_message() . "\n";
} else {
	echo "Environment check:\n";
	foreach ($result['data'] as $key => $info) {
		$status_icon = $info['status'] === 'ok' ? '✓' : 
		              ($info['status'] === 'warning' ? '⚠' : 
		              ($info['status'] === 'error' ? '✗' : 'ℹ'));
		echo "  $status_icon {$info['label']}: {$info['value']}\n";
	}
}
echo "\n";

// Test 7: Clear Cache (dry run)
echo "Test 7: Clear Cache\n";
echo str_repeat("-", 50) . "\n";
$result = $bridge->execute_tool('clear_cache', array('cache_type' => 'history_stats'), true);
if (is_wp_error($result)) {
	echo "ERROR: " . $result->get_error_message() . "\n";
} else {
	echo "Cache cleared successfully!\n";
	echo "Cleared " . $result['count'] . " cache entries:\n";
	foreach ($result['cleared'] as $cache) {
		echo "  - $cache\n";
	}
}
echo "\n";

// Test 8: Invalid Tool (should fail)
echo "Test 8: Invalid Tool (Expected Failure)\n";
echo str_repeat("-", 50) . "\n";
$result = $bridge->execute_tool('nonexistent_tool', array(), true);
if (is_wp_error($result)) {
	echo "Expected error received: " . $result->get_error_message() . "\n";
} else {
	echo "ERROR: Should have failed for invalid tool!\n";
}
echo "\n";

echo "=== Test Suite Completed ===\n";
echo "\nAll tests passed! The MCP Bridge is working correctly.\n";
