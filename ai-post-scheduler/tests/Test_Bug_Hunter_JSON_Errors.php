<?php
/**
 * Regression tests for JSON decode bugs handled by the Bug Hunter.
 *
 * @group bug-hunter
 */
class Test_Bug_Hunter_JSON_Errors extends WP_UnitTestCase {

	/**
	 * Test that validate-mcp-bridge.php gracefully handles valid JSON that decodes to a string
	 * instead of an array.
	 */
	public function test_validate_mcp_bridge_handles_scalar_json() {
		// Create a temporary schema file
		$temp_dir = sys_get_temp_dir();
		$schema_file = $temp_dir . '/mcp-bridge-schema.json';
        $bridge_file = $temp_dir . '/mcp-bridge.php';
        $test_file = $temp_dir . '/test-validate-mcp-bridge.php';

		// Valid JSON, but not an array
		file_put_contents($schema_file, '"just a string"');

        // Provide enough of a fake bridge to pass the earlier class/method checks
        $fake_bridge = <<<'PHP'
<?php
class AIPS_MCP_Bridge {
    public function __construct() {}
    public function register_tools() {}
    public function handle_request() {}
    public function execute_tool() {}
    public function validate_params() {}
    public function send_success() {}
    public function send_error() {}
    public function tool_clear_cache() {}
    public function tool_check_database() {}
    public function tool_repair_database() {}
    public function tool_check_upgrades() {}
    public function tool_system_status() {}
    public function tool_clear_history() {}
    public function tool_export_data() {}
    public function tool_get_cron_status() {}
    public function tool_trigger_cron() {}
    public function tool_list_tools() {}
    public function tool_get_plugin_info() {}
}
add_action('rest_api_init', 'fake');
current_user_can('manage_options');
jsonrpc;
jsonrpc;
PHP;
        file_put_contents($bridge_file, $fake_bridge);

		$bridge_code = file_get_contents(dirname(__DIR__) . '/validate-mcp-bridge.php');

		// Replace paths
		$modified_code = str_replace(
			"__DIR__ . '/mcp-bridge-schema.json'",
			"'" . $schema_file . "'",
			$bridge_code
		);
		$modified_code = str_replace(
			"__DIR__ . '/mcp-bridge.php'",
			"'" . $bridge_file . "'",
			$modified_code
		);

		file_put_contents($test_file, $modified_code);

		$output = shell_exec('php ' . escapeshellarg($test_file));

		// Cleanup
		unlink($schema_file);
        unlink($bridge_file);
		unlink($test_file);

		// Should fail gracefully with a schema structure/type error, not a TypeError.
		$this->assertStringContainsString('FAILED: Schema file must decode to an array', $output);
	}
}
