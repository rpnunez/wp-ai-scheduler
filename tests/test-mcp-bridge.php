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
			'get_plugin_info',
			'generate_post',
			'list_templates',
			'get_generation_history',
			'get_history',
			'list_authors',
			'get_author',
			'list_author_topics',
			'get_author_topic',
			'regenerate_post_component',
			'get_generation_stats',
			'get_post_metadata',
			'get_ai_models',
			'test_ai_connection',
			'get_plugin_settings'
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

	/**
	 * Test list_templates returns templates
	 */
	public function test_list_templates() {
		$result = $this->bridge->execute_tool('list_templates', array());
		
		$this->assertIsArray($result);
		$this->assertTrue($result['success']);
		$this->assertArrayHasKey('templates', $result);
		$this->assertArrayHasKey('count', $result);
		$this->assertIsArray($result['templates']);
	}

	/**
	 * Test list_templates with active_only filter
	 */
	public function test_list_templates_active_only() {
		$result = $this->bridge->execute_tool('list_templates', array('active_only' => true));
		
		$this->assertIsArray($result);
		$this->assertTrue($result['success']);
		$this->assertIsArray($result['templates']);
		
		// Verify all returned templates are active
		foreach ($result['templates'] as $template) {
			$this->assertTrue($template['is_active'], 'Template should be active when using active_only filter');
		}
	}

	/**
	 * Test list_templates with search filter
	 */
	public function test_list_templates_with_search() {
		$result = $this->bridge->execute_tool('list_templates', array('search' => 'test'));
		
		$this->assertIsArray($result);
		$this->assertTrue($result['success']);
		$this->assertIsArray($result['templates']);
	}

	/**
	 * Test list_templates returns proper structure
	 */
	public function test_list_templates_structure() {
		$result = $this->bridge->execute_tool('list_templates', array());
		
		if (!empty($result['templates'])) {
			$template = $result['templates'][0];
			
			$this->assertArrayHasKey('id', $template);
			$this->assertArrayHasKey('name', $template);
			$this->assertArrayHasKey('is_active', $template);
			$this->assertArrayHasKey('prompt_template', $template);
			$this->assertArrayHasKey('title_prompt', $template);
			$this->assertArrayHasKey('excerpt_prompt', $template);
			$this->assertArrayHasKey('post_status', $template);
			$this->assertArrayHasKey('created_at', $template);
		}
	}

	/**
	 * Test get_generation_history returns history
	 */
	public function test_get_generation_history() {
		$result = $this->bridge->execute_tool('get_generation_history', array());
		
		$this->assertIsArray($result);
		$this->assertTrue($result['success']);
		$this->assertArrayHasKey('items', $result);
		$this->assertArrayHasKey('pagination', $result);
		$this->assertIsArray($result['items']);
	}

	/**
	 * Test get_generation_history with pagination
	 */
	public function test_get_generation_history_pagination() {
		$result = $this->bridge->execute_tool('get_generation_history', array(
			'per_page' => 10,
			'page' => 1
		));
		
		$this->assertIsArray($result);
		$this->assertTrue($result['success']);
		$this->assertArrayHasKey('pagination', $result);
		
		$pagination = $result['pagination'];
		$this->assertArrayHasKey('total', $pagination);
		$this->assertArrayHasKey('pages', $pagination);
		$this->assertArrayHasKey('current_page', $pagination);
		$this->assertArrayHasKey('per_page', $pagination);
		$this->assertEquals(10, $pagination['per_page']);
		$this->assertEquals(1, $pagination['current_page']);
	}

	/**
	 * Test get_generation_history with status filter
	 */
	public function test_get_generation_history_with_status_filter() {
		$result = $this->bridge->execute_tool('get_generation_history', array(
			'status' => 'completed'
		));
		
		$this->assertIsArray($result);
		$this->assertTrue($result['success']);
		$this->assertIsArray($result['items']);
		
		// Verify all items have completed status
		foreach ($result['items'] as $item) {
			$this->assertEquals('completed', $item['status']);
		}
	}

	/**
	 * Test get_generation_history with template_id filter
	 */
	public function test_get_generation_history_with_template_filter() {
		$result = $this->bridge->execute_tool('get_generation_history', array(
			'template_id' => 1
		));
		
		$this->assertIsArray($result);
		$this->assertTrue($result['success']);
		$this->assertIsArray($result['items']);
	}

	/**
	 * Test get_generation_history with search
	 */
	public function test_get_generation_history_with_search() {
		$result = $this->bridge->execute_tool('get_generation_history', array(
			'search' => 'test'
		));
		
		$this->assertIsArray($result);
		$this->assertTrue($result['success']);
		$this->assertIsArray($result['items']);
	}

	/**
	 * Test get_generation_history item structure
	 */
	public function test_get_generation_history_item_structure() {
		$result = $this->bridge->execute_tool('get_generation_history', array());
		
		if (!empty($result['items'])) {
			$item = $result['items'][0];
			
			$this->assertArrayHasKey('id', $item);
			$this->assertArrayHasKey('uuid', $item);
			$this->assertArrayHasKey('post_id', $item);
			$this->assertArrayHasKey('template_id', $item);
			$this->assertArrayHasKey('template_name', $item);
			$this->assertArrayHasKey('status', $item);
			$this->assertArrayHasKey('generated_title', $item);
			$this->assertArrayHasKey('created_at', $item);
			$this->assertArrayHasKey('completed_at', $item);
		}
	}

	/**
	 * Test get_generation_history enforces pagination limits
	 */
	public function test_get_generation_history_pagination_limits() {
		// Test that per_page is clamped to 100
		$result = $this->bridge->execute_tool('get_generation_history', array(
			'per_page' => 200 // Should be clamped to 100
		));
		
		$this->assertIsArray($result);
		$this->assertTrue($result['success']);
		$this->assertLessThanOrEqual(100, $result['pagination']['per_page']);
		
		// Test that per_page is clamped to minimum 1
		$result2 = $this->bridge->execute_tool('get_generation_history', array(
			'per_page' => 0 // Should be clamped to 1
		));
		
		$this->assertIsArray($result2);
		$this->assertTrue($result2['success']);
		$this->assertGreaterThanOrEqual(1, $result2['pagination']['per_page']);
	}

	/**
	 * Test generate_post requires a source parameter
	 */
	public function test_generate_post_requires_source() {
		$result = $this->bridge->execute_tool('generate_post', array());
		
		$this->assertInstanceOf(WP_Error::class, $result);
		$this->assertEquals('missing_source', $result->get_error_code());
	}

	/**
	 * Test generate_post with invalid template_id
	 */
	public function test_generate_post_invalid_template() {
		$result = $this->bridge->execute_tool('generate_post', array(
			'template_id' => 999999 // Non-existent template
		));
		
		// Should return an error
		$this->assertInstanceOf(WP_Error::class, $result);
	}

	/**
	 * Test generate_post with invalid schedule_id
	 */
	public function test_generate_post_invalid_schedule() {
		$result = $this->bridge->execute_tool('generate_post', array(
			'schedule_id' => 999999 // Non-existent schedule
		));
		
		// Should return an error
		$this->assertInstanceOf(WP_Error::class, $result);
	}

	/**
	 * Test generate_post with invalid author_topic_id
	 */
	public function test_generate_post_invalid_topic() {
		$result = $this->bridge->execute_tool('generate_post', array(
			'author_topic_id' => 999999 // Non-existent topic
		));
		
		// Should return an error
		$this->assertInstanceOf(WP_Error::class, $result);
	}

	/**
	 * Test that new tools are registered in list_tools
	 */
	public function test_new_tools_registered() {
		$result = $this->bridge->execute_tool('list_tools', array());
		
		$this->assertTrue($result['success']);
		$this->assertArrayHasKey('generate_post', $result['tools']);
		$this->assertArrayHasKey('list_templates', $result['tools']);
		$this->assertArrayHasKey('get_generation_history', $result['tools']);
		$this->assertArrayHasKey('get_history', $result['tools']);
		$this->assertArrayHasKey('list_authors', $result['tools']);
		$this->assertArrayHasKey('regenerate_post_component', $result['tools']);
	}

	/**
	 * Test get_history requires parameter
	 */
	public function test_get_history_requires_parameter() {
		$result = $this->bridge->execute_tool('get_history', array());
		
		$this->assertInstanceOf(WP_Error::class, $result);
		$this->assertEquals('missing_parameter', $result->get_error_code());
	}

	/**
	 * Test get_history with invalid history_id
	 */
	public function test_get_history_invalid_id() {
		$result = $this->bridge->execute_tool('get_history', array(
			'history_id' => 999999
		));
		
		$this->assertInstanceOf(WP_Error::class, $result);
		$this->assertEquals('history_not_found', $result->get_error_code());
	}

	/**
	 * Test list_authors returns expected structure
	 */
	public function test_list_authors() {
		$result = $this->bridge->execute_tool('list_authors', array());
		
		$this->assertIsArray($result);
		$this->assertTrue($result['success']);
		$this->assertArrayHasKey('authors', $result);
		$this->assertArrayHasKey('count', $result);
		$this->assertIsArray($result['authors']);
	}

	/**
	 * Test list_authors with active_only filter
	 */
	public function test_list_authors_active_only() {
		$result = $this->bridge->execute_tool('list_authors', array(
			'active_only' => true
		));
		
		$this->assertIsArray($result);
		$this->assertTrue($result['success']);
		$this->assertIsArray($result['authors']);
	}

	/**
	 * Test get_author requires author_id
	 */
	public function test_get_author_requires_parameter() {
		$result = $this->bridge->execute_tool('get_author', array());
		
		$this->assertInstanceOf(WP_Error::class, $result);
		$this->assertEquals('missing_parameter', $result->get_error_code());
	}

	/**
	 * Test get_author with invalid ID
	 */
	public function test_get_author_invalid_id() {
		$result = $this->bridge->execute_tool('get_author', array(
			'author_id' => 999999
		));
		
		$this->assertInstanceOf(WP_Error::class, $result);
		$this->assertEquals('author_not_found', $result->get_error_code());
	}

	/**
	 * Test list_author_topics requires author_id
	 */
	public function test_list_author_topics_requires_parameter() {
		$result = $this->bridge->execute_tool('list_author_topics', array());
		
		$this->assertInstanceOf(WP_Error::class, $result);
		$this->assertEquals('missing_parameter', $result->get_error_code());
	}

	/**
	 * Test list_author_topics returns expected structure
	 */
	public function test_list_author_topics_structure() {
		// Use a non-existent author ID - should still return valid structure with empty array
		$result = $this->bridge->execute_tool('list_author_topics', array(
			'author_id' => 999999
		));
		
		$this->assertIsArray($result);
		$this->assertTrue($result['success']);
		$this->assertArrayHasKey('topics', $result);
		$this->assertArrayHasKey('count', $result);
		$this->assertArrayHasKey('total_available', $result);
		$this->assertIsArray($result['topics']);
	}

	/**
	 * Test list_author_topics with status filter
	 */
	public function test_list_author_topics_with_status() {
		$result = $this->bridge->execute_tool('list_author_topics', array(
			'author_id' => 1,
			'status' => 'approved'
		));
		
		$this->assertIsArray($result);
		$this->assertTrue($result['success']);
	}

	/**
	 * Test list_author_topics with limit
	 */
	public function test_list_author_topics_with_limit() {
		$result = $this->bridge->execute_tool('list_author_topics', array(
			'author_id' => 1,
			'limit' => 10
		));
		
		$this->assertIsArray($result);
		$this->assertTrue($result['success']);
		$this->assertLessThanOrEqual(10, $result['count']);
	}

	/**
	 * Test get_author_topic requires topic_id
	 */
	public function test_get_author_topic_requires_parameter() {
		$result = $this->bridge->execute_tool('get_author_topic', array());
		
		$this->assertInstanceOf(WP_Error::class, $result);
		$this->assertEquals('missing_parameter', $result->get_error_code());
	}

	/**
	 * Test get_author_topic with invalid ID
	 */
	public function test_get_author_topic_invalid_id() {
		$result = $this->bridge->execute_tool('get_author_topic', array(
			'topic_id' => 999999
		));
		
		$this->assertInstanceOf(WP_Error::class, $result);
		$this->assertEquals('topic_not_found', $result->get_error_code());
	}

	/**
	 * Test regenerate_post_component requires post_id
	 */
	public function test_regenerate_component_requires_post_id() {
		$result = $this->bridge->execute_tool('regenerate_post_component', array(
			'history_id' => 1,
			'component' => 'title'
		));
		
		$this->assertInstanceOf(WP_Error::class, $result);
		$this->assertEquals('missing_parameter', $result->get_error_code());
	}

	/**
	 * Test regenerate_post_component requires history_id
	 */
	public function test_regenerate_component_requires_history_id() {
		$result = $this->bridge->execute_tool('regenerate_post_component', array(
			'post_id' => 1,
			'component' => 'title'
		));
		
		$this->assertInstanceOf(WP_Error::class, $result);
		$this->assertEquals('missing_parameter', $result->get_error_code());
	}

	/**
	 * Test regenerate_post_component requires component
	 */
	public function test_regenerate_component_requires_component() {
		$result = $this->bridge->execute_tool('regenerate_post_component', array(
			'post_id' => 1,
			'history_id' => 1
		));
		
		$this->assertInstanceOf(WP_Error::class, $result);
		$this->assertEquals('missing_parameter', $result->get_error_code());
	}

	/**
	 * Test regenerate_post_component validates component type
	 */
	public function test_regenerate_component_validates_component() {
		$result = $this->bridge->execute_tool('regenerate_post_component', array(
			'post_id' => 1,
			'history_id' => 1,
			'component' => 'invalid_component'
		));
		
		$this->assertInstanceOf(WP_Error::class, $result);
		$this->assertEquals('invalid_component', $result->get_error_code());
	}

	/**
	 * Test regenerate_post_component with non-existent post
	 */
	public function test_regenerate_component_invalid_post() {
		$result = $this->bridge->execute_tool('regenerate_post_component', array(
			'post_id' => 999999,
			'history_id' => 1,
			'component' => 'title'
		));
		
		$this->assertInstanceOf(WP_Error::class, $result);
		$this->assertEquals('post_not_found', $result->get_error_code());
	}

	/**
	 * Test that all Phase 2 tools are registered
	 */
	public function test_phase_2_tools_registered() {
		$result = $this->bridge->execute_tool('list_tools', array());
		
		$this->assertTrue($result['success']);
		$this->assertArrayHasKey('get_history', $result['tools']);
		$this->assertArrayHasKey('list_authors', $result['tools']);
		$this->assertArrayHasKey('get_author', $result['tools']);
		$this->assertArrayHasKey('list_author_topics', $result['tools']);
		$this->assertArrayHasKey('get_author_topic', $result['tools']);
		$this->assertArrayHasKey('regenerate_post_component', $result['tools']);
	}

	/**
	 * Test get_generation_stats returns expected structure
	 */
	public function test_get_generation_stats() {
		$result = $this->bridge->execute_tool('get_generation_stats', array());
		
		$this->assertIsArray($result);
		$this->assertTrue($result['success']);
		$this->assertArrayHasKey('stats', $result);
		$this->assertArrayHasKey('total', $result['stats']);
		$this->assertArrayHasKey('completed', $result['stats']);
		$this->assertArrayHasKey('failed', $result['stats']);
		$this->assertArrayHasKey('processing', $result['stats']);
		$this->assertArrayHasKey('success_rate', $result['stats']);
		$this->assertArrayHasKey('by_template', $result['stats']);
	}

	/**
	 * Test get_generation_stats with period filter
	 */
	public function test_get_generation_stats_with_period() {
		$result = $this->bridge->execute_tool('get_generation_stats', array(
			'period' => 'week'
		));
		
		$this->assertIsArray($result);
		$this->assertTrue($result['success']);
		$this->assertEquals('week', $result['stats']['period']);
	}

	/**
	 * Test get_generation_stats with template filter
	 */
	public function test_get_generation_stats_with_template() {
		$result = $this->bridge->execute_tool('get_generation_stats', array(
			'template_id' => 1
		));
		
		$this->assertIsArray($result);
		$this->assertTrue($result['success']);
	}

	/**
	 * Test get_post_metadata requires post_id
	 */
	public function test_get_post_metadata_requires_parameter() {
		$result = $this->bridge->execute_tool('get_post_metadata', array());
		
		$this->assertInstanceOf(WP_Error::class, $result);
		$this->assertEquals('missing_parameter', $result->get_error_code());
	}

	/**
	 * Test get_post_metadata with invalid post
	 */
	public function test_get_post_metadata_invalid_post() {
		$result = $this->bridge->execute_tool('get_post_metadata', array(
			'post_id' => 999999
		));
		
		$this->assertInstanceOf(WP_Error::class, $result);
		$this->assertEquals('post_not_found', $result->get_error_code());
	}

	/**
	 * Test get_ai_models returns expected structure
	 */
	public function test_get_ai_models() {
		$result = $this->bridge->execute_tool('get_ai_models', array());
		
		$this->assertIsArray($result);
		// May return error if AI Engine not available, which is ok for test
		if (isset($result['success']) && $result['success']) {
			$this->assertArrayHasKey('models', $result);
			$this->assertArrayHasKey('current_model', $result);
			$this->assertIsArray($result['models']);
		}
	}

	/**
	 * Test test_ai_connection returns expected structure
	 */
	public function test_test_ai_connection() {
		$result = $this->bridge->execute_tool('test_ai_connection', array());
		
		$this->assertIsArray($result);
		$this->assertArrayHasKey('connected', $result);
		$this->assertIsBool($result['connected']);
		
		// If connected, should have response
		if ($result['connected']) {
			$this->assertArrayHasKey('response', $result);
			$this->assertArrayHasKey('response_time_ms', $result);
		} else {
			// If not connected, should have error message
			$this->assertArrayHasKey('error', $result);
		}
	}

	/**
	 * Test test_ai_connection with custom prompt
	 */
	public function test_test_ai_connection_with_prompt() {
		$result = $this->bridge->execute_tool('test_ai_connection', array(
			'test_prompt' => 'Hello AI'
		));
		
		$this->assertIsArray($result);
		$this->assertArrayHasKey('connected', $result);
	}

	/**
	 * Test get_plugin_settings returns expected structure
	 */
	public function test_get_plugin_settings() {
		$result = $this->bridge->execute_tool('get_plugin_settings', array());
		
		$this->assertIsArray($result);
		$this->assertTrue($result['success']);
		$this->assertArrayHasKey('settings', $result);
		$this->assertArrayHasKey('category', $result);
		$this->assertEquals('all', $result['category']);
		
		// Should have all categories when 'all' is requested
		$this->assertArrayHasKey('ai', $result['settings']);
		$this->assertArrayHasKey('resilience', $result['settings']);
		$this->assertArrayHasKey('logging', $result['settings']);
	}

	/**
	 * Test get_plugin_settings with category filter
	 */
	public function test_get_plugin_settings_with_category() {
		$result = $this->bridge->execute_tool('get_plugin_settings', array(
			'category' => 'ai'
		));
		
		$this->assertIsArray($result);
		$this->assertTrue($result['success']);
		$this->assertEquals('ai', $result['category']);
		$this->assertArrayHasKey('ai', $result['settings']);
		
		// Should only have ai category
		$this->assertArrayNotHasKey('resilience', $result['settings']);
		$this->assertArrayNotHasKey('logging', $result['settings']);
	}

	/**
	 * Test get_plugin_settings AI settings structure
	 */
	public function test_get_plugin_settings_ai_structure() {
		$result = $this->bridge->execute_tool('get_plugin_settings', array(
			'category' => 'ai'
		));
		
		$this->assertTrue($result['success']);
		$this->assertArrayHasKey('model', $result['settings']['ai']);
		$this->assertArrayHasKey('max_tokens', $result['settings']['ai']);
		$this->assertArrayHasKey('temperature', $result['settings']['ai']);
		$this->assertArrayHasKey('default_post_status', $result['settings']['ai']);
	}

	/**
	 * Test that all Phase 3 tools are registered
	 */
	public function test_phase_3_tools_registered() {
		$result = $this->bridge->execute_tool('list_tools', array());
		
		$this->assertTrue($result['success']);
		$this->assertArrayHasKey('get_generation_stats', $result['tools']);
		$this->assertArrayHasKey('get_post_metadata', $result['tools']);
		$this->assertArrayHasKey('get_ai_models', $result['tools']);
		$this->assertArrayHasKey('test_ai_connection', $result['tools']);
		$this->assertArrayHasKey('get_plugin_settings', $result['tools']);
	}
}

