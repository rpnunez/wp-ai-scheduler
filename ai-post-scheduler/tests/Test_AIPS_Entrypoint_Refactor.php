<?php
/**
 * Tests for Entrypoint Refactor (config.php, AIPS_Container_Bindings, AIPS_Cron_Bootstrapper, AIPS_Query_Monitor).
 *
 * @package AI_Post_Scheduler
 */

class Test_AIPS_Entrypoint_Refactor extends WP_UnitTestCase {

	/**
	 * Test that constants defined in config.php are present and valid.
	 */
	public function test_config_constants_are_defined() {
		$this->assertTrue(defined('AIPS_VERSION'));
		$this->assertSame('3.7.19', AIPS_VERSION);
		$this->assertTrue(defined('AIPS_REQUEST_START'));
		$this->assertTrue(defined('AIPS_TELEMETRY_SLOW_QUERY_MS'));
		$this->assertTrue(defined('AIPS_TELEMETRY_SLOW_REQUEST_MS'));
		$this->assertTrue(defined('AIPS_TELEMETRY_QUERY_SAMPLE_LIMIT'));
		$this->assertTrue(defined('AIPS_AI_DEBUG_LOG_PROMPTS'));
		$this->assertTrue(defined('AIPS_DEBUG'));
		$this->assertTrue(defined('AIPS_DEBUG_LEVEL'));
	}

	/**
	 * Test that AIPS_Container_Bindings directly registers bindings in the container.
	 */
	public function test_container_bindings_direct_registration() {
		$container = AIPS_Container::get_instance();
		$container->clear();

		AIPS_Container_Bindings::register($container);

		$this->assertTrue($container->has(AIPS_Config::class));
		$this->assertTrue($container->has(AIPS_History_Repository::class));
		$this->assertTrue($container->has(AIPS_AI_Service_Interface::class));
		$this->assertTrue($container->has(AIPS_Schedule_Repository::class));

		// Re-register bindings through entrypoint proxy to preserve clean container state
		AI_Post_Scheduler::get_instance()->register_container_bindings();
	}

	/**
	 * Test that AI_Post_Scheduler proxy delegates to AIPS_Container_Bindings properly with optional argument.
	 */
	public function test_entrypoint_delegates_to_container_bindings() {
		$container = AIPS_Container::get_instance();
		$container->clear();

		AI_Post_Scheduler::get_instance()->register_container_bindings($container);

		$this->assertTrue($container->has(AIPS_Config::class));
		$this->assertTrue($container->has(AIPS_History_Service::class));
		$this->assertTrue($container->has(AIPS_Notifications_Repository::class));
		$this->assertTrue($container->has(AIPS_Logger::class));
	}

	/**
	 * Test that AIPS_Cron_Bootstrapper registers cron action hooks.
	 */
	public function test_cron_bootstrapper_registers_hooks() {
		AIPS_Cron_Bootstrapper::boot();

		$this->assertGreaterThan(0, has_action('aips_generate_scheduled_posts'));
		$this->assertGreaterThan(0, has_action('aips_generate_author_topics'));
		$this->assertGreaterThan(0, has_action('aips_generate_author_posts'));
		$this->assertGreaterThan(0, has_action('aips_cleanup_bulk_batch_jobs'));
		$this->assertGreaterThan(0, has_action('aips_cache_monitor_maintenance'));
	}

	/**
	 * Test Query Monitor helper methods.
	 */
	public function test_query_monitor_component_type() {
		$this->assertSame('plugin', AIPS_Query_Monitor::filter_component_type());
	}

	/**
	 * Test Query Monitor component context filters.
	 */
	public function test_query_monitor_component_context() {
		$real = realpath(AIPS_PLUGIN_DIR);
		if (false !== $real) {
			$file = str_replace('\\', '/', $real . '/ai-post-scheduler.php');
			$this->assertSame('ai-post-scheduler', AIPS_Query_Monitor::filter_component_context('default', $file));
		}
		$this->assertSame('other', AIPS_Query_Monitor::filter_component_context('other', '/wp-content/plugins/other-plugin/file.php'));
	}

	/**
	 * Test Query Monitor component directory filter.
	 */
	public function test_query_monitor_filter_component_dirs() {
		$dirs = array('some_key' => '/some/path');
		$result = AIPS_Query_Monitor::filter_component_dirs($dirs);
		$this->assertIsArray($result);
		$this->assertArrayHasKey('some_key', $result);
	}
}
