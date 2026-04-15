#!/usr/bin/env php
<?php
/**
 * Quick validation script for MCP Bridge
 * 
 * This script validates the MCP Bridge structure and functionality
 * without requiring a full WordPress environment.
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
echo "=== MCP Bridge Validation Script ===\n\n";

// Check that mcp-bridge.php exists
$bridge_file = __DIR__ . '/mcp-bridge.php';
if (!file_exists($bridge_file)) {
	echo "❌ FAILED: mcp-bridge.php not found!\n";
	exit(1);
}
echo "✅ mcp-bridge.php exists\n";

// Check file size
$filesize = filesize($bridge_file);
if ($filesize === false) {
	$filesize = 0;
}
echo "   File size: " . number_format($filesize) . " bytes\n";

// Parse the PHP file
$content = file_get_contents($bridge_file);

// Check for class definition
if (strpos($content, 'class AIPS_MCP_Bridge') === false) {
	echo "❌ FAILED: AIPS_MCP_Bridge class not found!\n";
	exit(1);
}
echo "✅ AIPS_MCP_Bridge class defined\n";

// Check for required methods
$required_methods = array(
	'__construct',
	'register_tools',
	'handle_request',
	'execute_tool',
	'validate_params',
	'send_success',
	'send_error',
	// Tool handlers
	'tool_clear_cache',
	'tool_check_database',
	'tool_repair_database',
	'tool_check_upgrades',
	'tool_system_status',
	'tool_clear_history',
	'tool_export_data',
	'tool_get_cron_status',
	'tool_trigger_cron',
	'tool_list_tools',
	'tool_get_plugin_info'
);

$missing_methods = array();
foreach ($required_methods as $method) {
	if (strpos($content, "function $method") === false && 
	    strpos($content, "private function $method") === false) {
		$missing_methods[] = $method;
	}
}

if (!empty($missing_methods)) {
	echo "❌ FAILED: Missing methods: " . implode(', ', $missing_methods) . "\n";
	exit(1);
}
echo "✅ All required methods present (" . count($required_methods) . " methods)\n";

// Check for security
if (strpos($content, 'current_user_can') === false) {
	echo "⚠️  WARNING: No capability check found\n";
} else {
	echo "✅ Capability check present\n";
}

// Check for JSON-RPC protocol
if (strpos($content, 'jsonrpc') === false) {
	echo "❌ FAILED: JSON-RPC protocol not implemented\n";
	exit(1);
}
echo "✅ JSON-RPC 2.0 protocol implemented\n";

// Check documentation files
$docs = array(
	'MCP_BRIDGE_README.md' => 'Documentation',
	'mcp-bridge-schema.json' => 'JSON Schema',
	'test-mcp-bridge.php' => 'Test script',
	'mcp-client-example.py' => 'Python client',
	'mcp-client-example.sh' => 'Shell client'
);

foreach ($docs as $file => $desc) {
	if (file_exists(__DIR__ . '/' . $file)) {
		echo "✅ $desc found ($file)\n";
	} else {
		echo "⚠️  WARNING: $desc not found ($file)\n";
	}
}

// Validate JSON schema
$schema_file = __DIR__ . '/mcp-bridge-schema.json';
if (file_exists($schema_file)) {
	$schema_content = file_get_contents($schema_file);
	if ($schema_content === false) {
		echo "❌ FAILED: Could not read schema file\n";
		exit(1);
	}

	$schema = json_decode($schema_content, true);
	if (json_last_error() !== JSON_ERROR_NONE || !is_array($schema)) {
		echo "❌ FAILED: Invalid JSON in schema file\n";
		exit(1);
	}
	
	if (!isset($schema['tools']) || !is_array($schema['tools'])) {
		echo "❌ FAILED: Schema missing tools array\n";
		exit(1);
	}
	
	echo "✅ JSON schema valid (" . count($schema['tools']) . " tools documented)\n";
}

// Check for proper error handling
$error_checks = array(
	'WP_Error' => 'WordPress error handling',
	'try {' => 'Exception handling',
	'is_wp_error' => 'Error checking'
);

foreach ($error_checks as $pattern => $desc) {
	if (strpos($content, $pattern) !== false) {
		echo "✅ $desc implemented\n";
	} else {
		echo "⚠️  WARNING: $desc not found\n";
	}
}

// Check for logger integration
if (strpos($content, 'AIPS_Logger') !== false) {
	echo "✅ Logger integration present\n";
} else {
	echo "⚠️  WARNING: No logger integration found\n";
}

// Count tools defined
preg_match_all('/\'([a-z_]+)\'\s*=>\s*array\(\s*[\'"]description[\'"]\s*=>/i', $content, $matches);
$tool_count = count($matches[1]);
echo "\n📊 Statistics:\n";
echo "   Total tools: $tool_count\n";
echo "   Lines of code: " . count(explode("\n", $content)) . "\n";
echo "   File size: " . number_format($filesize) . " bytes\n";

echo "\n✨ MCP Bridge validation PASSED!\n";
echo "\nNext steps:\n";
echo "1. Test the bridge with actual HTTP requests\n";
echo "2. Verify authentication works correctly\n";
echo "3. Test all tools with real WordPress environment\n";
echo "4. Review security and add rate limiting if needed\n";

exit(0);
