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
		$this->assertNotSame('', AIPS_VERSION);

		$plugin_file = dirname(__DIR__) . '/ai-post-scheduler.php';
		$metadata    = get_file_data($plugin_file, array('Version' => 'Version'), 'plugin');
		$this->assertNotSame('', $metadata['Version'], 'Plugin header Version must be present');
		$this->assertSame($metadata['Version'], AIPS_VERSION, 'AIPS_VERSION must match the plugin header');

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
		$this->assertTrue($container->has(AIPS_Post_Cleanup_Service::class));
		$this->assertTrue($container->has(AIPS_Internal_Links_Repository::class));
		$this->assertTrue($container->has(AIPS_Author_Topic_Logs_Repository::class));
		$this->assertTrue($container->has(AIPS_Redirects_Repository::class));

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

	/**
	 * A missing Composer autoload must still register the AIPS_ fallback loader.
	 */
	public function test_missing_vendor_autoload_registers_fallback_loader() {
		$missing = AIPS_PLUGIN_DIR . 'vendor/autoload-missing-for-entrypoint-test.php';
		$this->assertFileDoesNotExist($missing);

		// PHP refuses a second copy of the same autoload callable, so drop the
		// current registration and prove the missing-vendor path puts it back.
		while ($this->count_fallback_autoloaders() > 0) {
			spl_autoload_unregister(array('AIPS_Autoloader', 'load'));
		}
		$this->assertSame(0, $this->count_fallback_autoloaders());

		$this->invoke_private('load_class_loaders', $missing);
		$this->assertSame(1, $this->count_fallback_autoloaders());

		$loaded = null;
		foreach (array('AIPS_Voices', 'AIPS_Planner', 'AIPS_Stress_Test_Service', 'AIPS_Seeder_Service', 'AIPS_Session_To_JSON') as $candidate) {
			if (class_exists($candidate, false)) {
				continue;
			}
			$file = AIPS_PLUGIN_DIR . 'includes/' . AIPS_Autoloader::convert_class_name_to_filename($candidate);
			if (!file_exists($file)) {
				continue;
			}
			AIPS_Autoloader::load($candidate);
			$this->assertTrue(class_exists($candidate, false), $candidate . ' must load through the fallback autoloader');
			$loaded = $candidate;
			break;
		}

		$this->assertNotNull($loaded, 'Fallback autoloader must be able to load an AIPS class that Composer has not loaded');
	}

	/**
	 * Digest scheduling follows the setting and uses the shared interval name.
	 */
	public function test_digest_cron_event_follows_the_setting() {
		$config   = AIPS_Config::get_instance();
		$previous = $config->get_option('aips_notif_digest_enabled');

		try {
			$config->set_option('aips_notif_digest_enabled', false);
			$this->assertArrayNotHasKey('aips_notif_digest_cron', AI_Post_Scheduler::get_cron_events());

			$config->set_option('aips_notif_digest_enabled', true);
			$events = AI_Post_Scheduler::get_cron_events();
			$this->assertArrayHasKey('aips_notif_digest_cron', $events);
			$this->assertSame('fifteen_minutes', $events['aips_notif_digest_cron']['schedule']);
		} finally {
			$config->set_option('aips_notif_digest_enabled', $previous);
		}
	}

	/**
	 * Cron boot must not add a second cron_schedules callback.
	 *
	 * Intervals are registered from the common boot path so admin, AJAX,
	 * cron, and frontend requests share one filter.
	 */
	public function test_cron_boot_does_not_duplicate_interval_filter() {
		$before = $this->count_hook_callbacks('cron_schedules');
		$this->assertGreaterThan(0, $before, 'Common boot must register cron intervals');

		$this->invoke_private('boot_cron');

		$this->assertSame($before, $this->count_hook_callbacks('cron_schedules'));
		$this->assertGreaterThan(0, has_action('aips_generate_scheduled_posts'));

		$schedules = apply_filters('cron_schedules', array());
		$this->assertArrayHasKey('fifteen_minutes', $schedules);
	}

	/**
	 * Admin boot registers the menu and the native editor AI Edit hooks.
	 */
	public function test_admin_boot_registers_menu_and_editor_assets() {
		$menu_before = $this->count_hook_callbacks('admin_menu');
		$meta_before = $this->count_hook_callbacks('add_meta_boxes');

		$this->invoke_private('boot_admin');

		$this->assertGreaterThan($menu_before, $this->count_hook_callbacks('admin_menu'));
		$this->assertGreaterThan($meta_before, $this->count_hook_callbacks('add_meta_boxes'));
		$this->assertGreaterThan(0, has_action('enqueue_block_editor_assets'));
	}

	/**
	 * AJAX boot resolves a registry action to its controller hooks.
	 */
	public function test_ajax_boot_resolves_registered_controller() {
		$previous = isset($_REQUEST['action']) ? $_REQUEST['action'] : null;
		$_REQUEST['action'] = 'aips_save_template';

		try {
			$before = $this->count_hook_callbacks('wp_ajax_aips_save_template');
			$this->invoke_private('boot_ajax');
			$this->assertGreaterThan($before, $this->count_hook_callbacks('wp_ajax_aips_save_template'));
		} finally {
			if (null === $previous) {
				unset($_REQUEST['action']);
			} else {
				$_REQUEST['action'] = $previous;
			}
		}
	}

	/**
	 * An unknown aips_* AJAX action falls back to lazy registry hooks.
	 */
	public function test_ajax_boot_registers_lazy_hooks_for_unknown_action() {
		$previous = isset($_REQUEST['action']) ? $_REQUEST['action'] : null;
		$_REQUEST['action'] = 'aips_entrypoint_refactor_unknown';

		try {
			$before = $this->count_hook_callbacks('wp_ajax_aips_get_template');
			$this->invoke_private('boot_ajax');
			$this->assertGreaterThan($before, $this->count_hook_callbacks('wp_ajax_aips_get_template'));
		} finally {
			if (null === $previous) {
				unset($_REQUEST['action']);
			} else {
				$_REQUEST['action'] = $previous;
			}
		}
	}

	/**
	 * Frontend boot registers content filtering and redirect handling.
	 */
	public function test_frontend_boot_registers_content_and_redirect_hooks() {
		$content_before  = $this->count_hook_callbacks('the_content');
		$redirect_before = $this->count_hook_callbacks('template_redirect');

		$this->invoke_private('boot_frontend');

		$this->assertGreaterThan($content_before, $this->count_hook_callbacks('the_content'));
		$this->assertGreaterThan($redirect_before, $this->count_hook_callbacks('template_redirect'));
	}

	/**
	 * Invoke a private bootstrap method on the plugin singleton.
	 *
	 * @param string $method Method name.
	 * @param mixed  ...$args Arguments.
	 * @return mixed
	 */
	private function invoke_private($method, ...$args) {
		$reflection = new ReflectionMethod('AI_Post_Scheduler', $method);
		$reflection->setAccessible(true);
		return $reflection->invoke(AI_Post_Scheduler::get_instance(), ...$args);
	}

	/**
	 * Count callbacks registered on an action or filter.
	 *
	 * @param string $hook Hook name.
	 * @return int
	 */
	private function count_hook_callbacks($hook) {
		global $wp_filter;

		if (!isset($wp_filter[$hook]) || !is_object($wp_filter[$hook]) || !isset($wp_filter[$hook]->callbacks)) {
			return 0;
		}

		$count = 0;
		foreach ($wp_filter[$hook]->callbacks as $callbacks) {
			$count += count($callbacks);
		}

		return $count;
	}

	/**
	 * Count AIPS_Autoloader::load registrations.
	 *
	 * @return int
	 */
	private function count_fallback_autoloaders() {
		$count = 0;
		$autoloaders = spl_autoload_functions();
		if (!is_array($autoloaders)) {
			return 0;
		}

		foreach ($autoloaders as $autoloader) {
			if (is_array($autoloader) && isset($autoloader[0], $autoloader[1]) && 'AIPS_Autoloader' === $autoloader[0] && 'load' === $autoloader[1]) {
				$count++;
			}
		}

		return $count;
	}
}
