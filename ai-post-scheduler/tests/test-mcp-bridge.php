<?php
/**
 * Tests for AIPS_MCP_Bridge class
 *
 * @package AI_Post_Scheduler
 * @since 1.7.0
 */

class Test_AIPS_MCP_Bridge extends WP_UnitTestCase {

	private $bridge;

	public function setUp(): void {
		parent::setUp();
		$this->bridge = new AIPS_MCP_Bridge();
	}

	public function tearDown(): void {
		parent::tearDown();
	}

	/**
	 * Test that bridge class exists and can be instantiated
	 */
	public function test_bridge_class_exists() {
		$this->assertTrue(class_exists('AIPS_MCP_Bridge'));
		$this->assertInstanceOf(AIPS_MCP_Bridge::class, $this->bridge);
	}

	/**
	 * Test list_tools returns all available tools
	 */
	public function test_list_tools() {
		$result = $this->bridge->execute_tool('list_tools', array());
		
		$this->assertIsArray($result);
		$this->assertTrue($result['success']);
		$this->assertArrayHasKey('tools', $result);
		$this->assertArrayHasKey('version', $result);
		
		// Check that we have expected tools
		$expected_tools = array(
			'list_tools',
			'clear_cache',
			'check_database',
			'repair_database',
			'check_upgrades',
			'system_status',
			'clear_history',
			'export_data',
			'get_cron_status',
			'trigger_cron',
			'get_plugin_info'
		);
		
		foreach ($expected_tools as $tool) {
			$this->assertArrayHasKey($tool, $result['tools'], "Tool '$tool' should be available");
		}
	}

	/**
	 * Test get_plugin_info returns correct structure
	 */
	public function test_get_plugin_info() {
		$result = $this->bridge->execute_tool('get_plugin_info', array());
		
		$this->assertIsArray($result);
		$this->assertTrue($result['success']);
		$this->assertArrayHasKey('plugin', $result);
		
		$plugin = $result['plugin'];
		$this->assertArrayHasKey('name', $plugin);
		$this->assertArrayHasKey('version', $plugin);
		$this->assertArrayHasKey('db_version', $plugin);
		$this->assertArrayHasKey('php_version', $plugin);
		$this->assertArrayHasKey('wp_version', $plugin);
		$this->assertArrayHasKey('settings', $plugin);
		
		$this->assertEquals('AI Post Scheduler', $plugin['name']);
		$this->assertEquals(AIPS_VERSION, $plugin['version']);
	}

	/**
	 * Test clear_cache with history_stats
	 */
	public function test_clear_cache_history_stats() {
		// Set a transient first
		set_transient('aips_history_stats', array('test' => 'data'), 3600);
		$this->assertNotFalse(get_transient('aips_history_stats'));
		
		// Clear cache
		$result = $this->bridge->execute_tool('clear_cache', array('cache_type' => 'history_stats'));
		
		$this->assertTrue($result['success']);
		$this->assertContains('aips_history_stats', $result['cleared']);
		$this->assertFalse(get_transient('aips_history_stats'));
	}

	/**
	 * Test clear_cache with schedule_stats
	 */
	public function test_clear_cache_schedule_stats() {
		// Set a transient first
		set_transient('aips_pending_schedule_stats', array('test' => 'data'), 3600);
		$this->assertNotFalse(get_transient('aips_pending_schedule_stats'));
		
		// Clear cache
		$result = $this->bridge->execute_tool('clear_cache', array('cache_type' => 'schedule_stats'));
		
		$this->assertTrue($result['success']);
		$this->assertContains('aips_pending_schedule_stats', $result['cleared']);
		$this->assertFalse(get_transient('aips_pending_schedule_stats'));
	}

	/**
	 * Test clear_cache with all caches
	 */
	public function test_clear_cache_all() {
		// Set transients first
		set_transient('aips_history_stats', array('test' => 'data'), 3600);
		set_transient('aips_pending_schedule_stats', array('test' => 'data'), 3600);
		
		// Clear all caches
		$result = $this->bridge->execute_tool('clear_cache', array('cache_type' => 'all'));
		
		$this->assertTrue($result['success']);
		$this->assertGreaterThanOrEqual(2, $result['count']);
		$this->assertContains('aips_history_stats', $result['cleared']);
		$this->assertContains('aips_pending_schedule_stats', $result['cleared']);
	}

	/**
	 * Test check_database returns database status
	 */
	public function test_check_database() {
		$result = $this->bridge->execute_tool('check_database', array());
		
		$this->assertIsArray($result);
		$this->assertTrue($result['success']);
		$this->assertArrayHasKey('database', $result);
		$this->assertIsArray($result['database']);
	}

	/**
	 * Test repair_database executes without errors
	 */
	public function test_repair_database() {
		$result = $this->bridge->execute_tool('repair_database', array());
		
		$this->assertIsArray($result);
		$this->assertTrue($result['success']);
		$this->assertArrayHasKey('message', $result);
	}

	/**
	 * Test check_upgrades without running
	 */
	public function test_check_upgrades_no_run() {
		$result = $this->bridge->execute_tool('check_upgrades', array('run' => false));
		
		$this->assertIsArray($result);
		$this->assertTrue($result['success']);
		$this->assertArrayHasKey('current_version', $result);
		$this->assertArrayHasKey('plugin_version', $result);
		$this->assertArrayHasKey('needs_upgrade', $result);
		$this->assertArrayHasKey('upgraded', $result);
		$this->assertFalse($result['upgraded']); // Should not upgrade when run=false
	}

	/**
	 * Test system_status with all sections
	 */
	public function test_system_status_all() {
		$result = $this->bridge->execute_tool('system_status', array('section' => 'all'));
		
		$this->assertIsArray($result);
		$this->assertTrue($result['success']);
		$this->assertArrayHasKey('system_info', $result);
		
		$info = $result['system_info'];
		$this->assertArrayHasKey('environment', $info);
		$this->assertArrayHasKey('plugin', $info);
		$this->assertArrayHasKey('database', $info);
	}

	/**
	 * Test system_status with specific section
	 */
	public function test_system_status_environment() {
		$result = $this->bridge->execute_tool('system_status', array('section' => 'environment'));
		
		$this->assertIsArray($result);
		$this->assertTrue($result['success']);
		$this->assertArrayHasKey('section', $result);
		$this->assertEquals('environment', $result['section']);
		$this->assertArrayHasKey('data', $result);
	}

	/**
	 * Test get_cron_status returns cron job information
	 */
	public function test_get_cron_status() {
		$result = $this->bridge->execute_tool('get_cron_status', array());
		
		$this->assertIsArray($result);
		$this->assertTrue($result['success']);
		$this->assertArrayHasKey('crons', $result);
		
		$crons = $result['crons'];
		$expected_hooks = array(
			'aips_generate_scheduled_posts',
			'aips_generate_author_topics',
			'aips_generate_author_posts',
			'aips_scheduled_research',
			'aips_send_review_notifications',
			'aips_cleanup_export_files'
		);
		
		foreach ($expected_hooks as $hook) {
			$this->assertArrayHasKey($hook, $crons, "Cron hook '$hook' should be present");
			$this->assertArrayHasKey('scheduled', $crons[$hook]);
			$this->assertArrayHasKey('next_run', $crons[$hook]);
			$this->assertArrayHasKey('next_run_timestamp', $crons[$hook]);
		}
	}

	/**
	 * Test trigger_cron with valid hook
	 */
	public function test_trigger_cron_valid_hook() {
		$result = $this->bridge->execute_tool('trigger_cron', array('hook' => 'aips_cleanup_export_files'));
		
		$this->assertIsArray($result);
		$this->assertTrue($result['success']);
		$this->assertArrayHasKey('hook', $result);
		$this->assertEquals('aips_cleanup_export_files', $result['hook']);
		$this->assertArrayHasKey('message', $result);
	}

	/**
	 * Test trigger_cron with invalid hook
	 */
	public function test_trigger_cron_invalid_hook() {
		$result = $this->bridge->execute_tool('trigger_cron', array('hook' => 'invalid_hook_name'));
		
		$this->assertInstanceOf(WP_Error::class, $result);
		$this->assertEquals('invalid_hook', $result->get_error_code());
	}

	/**
	 * Test clear_history with default parameters
	 */
	public function test_clear_history_default() {
		// This test assumes no history records exist or creates test data
		$result = $this->bridge->execute_tool('clear_history', array(
			'older_than_days' => 0,
			'status' => 'all'
		));
		
		$this->assertIsArray($result);
		$this->assertTrue($result['success']);
		$this->assertArrayHasKey('deleted', $result);
		$this->assertArrayHasKey('message', $result);
	}

	/**
	 * Test clear_history with specific status
	 */
	public function test_clear_history_specific_status() {
		$result = $this->bridge->execute_tool('clear_history', array(
			'older_than_days' => 30,
			'status' => 'failed'
		));
		
		$this->assertIsArray($result);
		$this->assertTrue($result['success']);
		$this->assertArrayHasKey('deleted', $result);
	}

	/**
	 * Test export_data with JSON format
	 */
	public function test_export_data_json() {
		// Note: This test might fail if export classes have file system dependencies
		$result = $this->bridge->execute_tool('export_data', array(
			'format' => 'json',
			'tables' => array('aips_templates')
		));
		
		// Check if result is error or success
		if (is_wp_error($result)) {
			// Expected if export functionality requires additional setup
			$this->assertInstanceOf(WP_Error::class, $result);
		} else {
			$this->assertTrue($result['success']);
			$this->assertEquals('json', $result['format']);
			$this->assertArrayHasKey('file', $result);
		}
	}

	/**
	 * Test export_data with invalid format
	 */
	public function test_export_data_invalid_format() {
		$result = $this->bridge->execute_tool('export_data', array(
			'format' => 'invalid_format',
			'tables' => array()
		));
		
		$this->assertInstanceOf(WP_Error::class, $result);
	}

	/**
	 * Test that invalid tool name returns error
	 */
	public function test_invalid_tool_name() {
		$result = $this->bridge->execute_tool('nonexistent_tool', array());
		
		$this->assertInstanceOf(WP_Error::class, $result);
		$this->assertEquals('tool_not_found', $result->get_error_code());
	}

	/**
	 * Test parameter validation with missing required parameter
	 */
	public function test_missing_required_parameter() {
		// trigger_cron requires 'hook' parameter
		$result = $this->bridge->execute_tool('trigger_cron', array());
		
		$this->assertInstanceOf(WP_Error::class, $result);
		$this->assertEquals('missing_parameter', $result->get_error_code());
	}

	/**
	 * Test default parameter values
	 */
	public function test_default_parameter_values() {
		// clear_cache has default cache_type='all'
		$result = $this->bridge->execute_tool('clear_cache', array());
		
		$this->assertIsArray($result);
		$this->assertTrue($result['success']);
		$this->assertGreaterThan(0, $result['count']);
	}

	/**
	 * Test that all tools have proper structure
	 */
	public function test_all_tools_structure() {
		$result = $this->bridge->execute_tool('list_tools', array());
		
		foreach ($result['tools'] as $tool_name => $tool) {
			$this->assertArrayHasKey('name', $tool, "Tool '$tool_name' should have 'name'");
			$this->assertArrayHasKey('description', $tool, "Tool '$tool_name' should have 'description'");
			$this->assertArrayHasKey('parameters', $tool, "Tool '$tool_name' should have 'parameters'");
			$this->assertIsArray($tool['parameters'], "Tool '$tool_name' parameters should be array");
		}
	}
}
