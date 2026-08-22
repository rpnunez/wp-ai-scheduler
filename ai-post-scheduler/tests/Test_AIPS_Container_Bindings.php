<?php
/**
 * Tests for container bindings registration
 *
 * Verifies that core singletons are properly registered in the container
 * during plugin initialization.
 *
 * @package AI_Post_Scheduler
 * @since 2.4.0
 */
class Test_AIPS_Container_Bindings extends WP_UnitTestCase {

	/**
	 * @var AIPS_Container
	 */
	private $container;

	/**
	 * Set up before each test.
	 */
	public function setUp(): void {
		parent::setUp();
		$this->container = AIPS_Container::get_instance();
		$this->container->clear();
	}

	/**
	 * Tear down after each test.
	 */
	public function tearDown(): void {
		$this->container->clear();
		parent::tearDown();
	}

	/**
	 * Test that core singletons are registered.
	 */
	public function test_core_singletons_are_registered() {
		// Simulate what the plugin does during init
		$plugin = AI_Post_Scheduler::get_instance();

		// Use reflection to call the private method
		$reflection = new ReflectionClass($plugin);
		$method = $reflection->getMethod('register_container_bindings');
		$method->setAccessible(true);
		$method->invoke($plugin);

		// Verify bindings are registered
		$this->assertTrue($this->container->has(AIPS_Config::class));
		$this->assertTrue($this->container->has(AIPS_History_Repository::class));
		$this->assertTrue($this->container->has(AIPS_History_Repository_Interface::class));
		$this->assertTrue($this->container->has(AIPS_History_Service::class));
		$this->assertTrue($this->container->has(AIPS_History_Service_Interface::class));
		$this->assertTrue($this->container->has(AIPS_Notifications_Repository::class));
		$this->assertTrue($this->container->has(AIPS_Notifications_Repository_Interface::class));
		$this->assertTrue($this->container->has(AIPS_Logger::class));
		$this->assertTrue($this->container->has(AIPS_Logger_Interface::class));
		$this->assertTrue($this->container->has(AIPS_AI_Connector_Registry::class));
		$this->assertTrue($this->container->has(AIPS_AI_Connector_Health_Store::class));
		$this->assertTrue($this->container->has(AIPS_AI_Failover_Policy::class));
		$this->assertTrue($this->container->has(AIPS_AI_Connector_Router::class));
		$this->assertTrue($this->container->has(AIPS_WP_AI_Error_Mapper::class));
		$this->assertTrue($this->container->has(AIPS_WP_AI_Prompt_Adapter::class));
		$this->assertTrue($this->container->has(AIPS_WP_AI_Client_Provider::class));
		$this->assertTrue($this->container->has(AIPS_AI_Provider_Interface::class));
		$this->assertTrue($this->container->has(AIPS_AI_Service::class));
		$this->assertTrue($this->container->has(AIPS_AI_Service_Interface::class));
		$this->assertTrue($this->container->has(AIPS_Schedule_Repository::class));
		$this->assertTrue($this->container->has(AIPS_Schedule_Repository_Interface::class));
		$this->assertTrue($this->container->has(AIPS_Telemetry_Repository::class));
		$this->assertTrue($this->container->has(AIPS_Generated_Content_Repository::class));
		$this->assertTrue($this->container->has(AIPS_Generated_Content_Repository_Interface::class));
		$this->assertTrue($this->container->has(AIPS_Generation_Pipeline::class));
		$this->assertTrue($this->container->has(AIPS_Post_Audit_Service::class));
		$this->assertTrue($this->container->has(AIPS_System_Diagnostics_Service::class));
		$this->assertTrue($this->container->has(AIPS_System_Status_Diagnostics_Service::class));
	}

	/**
	 * Test that registered bindings return singleton instances.
	 */
	public function test_registered_bindings_return_singletons() {
		// Simulate what the plugin does during init
		$plugin = AI_Post_Scheduler::get_instance();

		$reflection = new ReflectionClass($plugin);
		$method = $reflection->getMethod('register_container_bindings');
		$method->setAccessible(true);
		$method->invoke($plugin);

		// Test AIPS_Config
		$config_a = $this->container->make(AIPS_Config::class);
		$config_b = $this->container->make(AIPS_Config::class);
		$this->assertSame($config_a, $config_b);
		$this->assertSame(AIPS_Config::get_instance(), $config_a);

		// Test AIPS_History_Repository
		$repo_a = $this->container->make(AIPS_History_Repository::class);
		$repo_b = $this->container->make(AIPS_History_Repository::class);
		$this->assertSame($repo_a, $repo_b);
		$this->assertSame(AIPS_History_Repository::instance(), $repo_a);

		// Test AIPS_History_Service
		$service_a = $this->container->make(AIPS_History_Service::class);
		$service_b = $this->container->make(AIPS_History_Service::class);
		$this->assertSame($service_a, $service_b);
		$this->assertSame(AIPS_History_Service::instance(), $service_a);

		// Test AIPS_Notifications_Repository
		$notif_a = $this->container->make(AIPS_Notifications_Repository::class);
		$notif_b = $this->container->make(AIPS_Notifications_Repository::class);
		$this->assertSame($notif_a, $notif_b);

		// Test AIPS_Telemetry_Repository
		$telemetry_a = $this->container->make(AIPS_Telemetry_Repository::class);
		$telemetry_b = $this->container->make(AIPS_Telemetry_Repository::class);
		$this->assertSame($telemetry_a, $telemetry_b);
		$this->assertSame(AIPS_Telemetry_Repository::instance(), $telemetry_a);
	}

	/**
	 * Test that all registered bindings have singleton scope.
	 */
	public function test_all_registered_bindings_have_singleton_scope() {
		// Simulate what the plugin does during init
		$plugin = AI_Post_Scheduler::get_instance();

		$reflection = new ReflectionClass($plugin);
		$method = $reflection->getMethod('register_container_bindings');
		$method->setAccessible(true);
		$method->invoke($plugin);

		$registered = $this->container->get_registered_bindings();

		// All core bindings should be singleton scope
		$this->assertEquals('singleton', $registered[AIPS_Config::class]);
		$this->assertEquals('singleton', $registered[AIPS_History_Repository::class]);
		$this->assertEquals('singleton', $registered[AIPS_History_Repository_Interface::class]);
		$this->assertEquals('singleton', $registered[AIPS_History_Service::class]);
		$this->assertEquals('singleton', $registered[AIPS_History_Service_Interface::class]);
		$this->assertEquals('singleton', $registered[AIPS_Notifications_Repository::class]);
		$this->assertEquals('singleton', $registered[AIPS_Notifications_Repository_Interface::class]);
		$this->assertEquals('singleton', $registered[AIPS_Logger::class]);
		$this->assertEquals('singleton', $registered[AIPS_Logger_Interface::class]);
		$this->assertEquals('singleton', $registered[AIPS_AI_Connector_Registry::class]);
		$this->assertEquals('singleton', $registered[AIPS_AI_Connector_Health_Store::class]);
		$this->assertEquals('singleton', $registered[AIPS_AI_Failover_Policy::class]);
		$this->assertEquals('singleton', $registered[AIPS_AI_Connector_Router::class]);
		$this->assertEquals('singleton', $registered[AIPS_WP_AI_Error_Mapper::class]);
		$this->assertEquals('singleton', $registered[AIPS_WP_AI_Prompt_Adapter::class]);
		$this->assertEquals('singleton', $registered[AIPS_WP_AI_Client_Provider::class]);
		$this->assertEquals('singleton', $registered[AIPS_AI_Provider_Interface::class]);
		$this->assertEquals('singleton', $registered[AIPS_AI_Service::class]);
		$this->assertEquals('singleton', $registered[AIPS_AI_Service_Interface::class]);
		$this->assertEquals('singleton', $registered[AIPS_Schedule_Repository::class]);
		$this->assertEquals('singleton', $registered[AIPS_Schedule_Repository_Interface::class]);
		$this->assertEquals('singleton', $registered[AIPS_Telemetry_Repository::class]);
		$this->assertEquals('singleton', $registered[AIPS_Generated_Content_Repository::class]);
		$this->assertEquals('singleton', $registered[AIPS_Generated_Content_Repository_Interface::class]);
		$this->assertEquals('singleton', $registered[AIPS_Generation_Pipeline::class]);
		$this->assertEquals('singleton', $registered[AIPS_Post_Audit_Service::class]);
		$this->assertEquals('singleton', $registered[AIPS_System_Diagnostics_Service::class]);
		$this->assertEquals('singleton', $registered[AIPS_System_Status_Diagnostics_Service::class]);
	}

	/**
	 * Test that binding count is correct.
	 */
	public function test_binding_count_is_correct() {
		// Simulate what the plugin does during init
		$plugin = AI_Post_Scheduler::get_instance();

		$reflection = new ReflectionClass($plugin);
		$method = $reflection->getMethod('register_container_bindings');
		$method->setAccessible(true);
		$method->invoke($plugin);

		$counts = $this->container->get_binding_counts();

		$this->assertEquals(0, $counts['transient']);
		$this->assertEquals(28, $counts['singleton']);
		$this->assertEquals(28, $counts['total']);
	}
}
