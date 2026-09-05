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
	 * Register the same bindings used by plugin init.
	 *
	 * @return void
	 */
	private function register_plugin_bindings(): void {
		$plugin = AI_Post_Scheduler::get_instance();

		$reflection = new ReflectionClass($plugin);
		$method = $reflection->getMethod('register_container_bindings');
		$method->setAccessible(true);
		$method->invoke($plugin);
	}

	/**
	 * Test that core singletons are registered.
	 */
	public function test_core_singletons_are_registered() {
		$this->register_plugin_bindings();

		$this->assertTrue($this->container->has(AIPS_Config::class));
		$this->assertTrue($this->container->has(AIPS_History_Repository::class));
		$this->assertTrue($this->container->has(AIPS_History_Repository_Interface::class));
		$this->assertTrue($this->container->has(AIPS_History_Service::class));
		$this->assertTrue($this->container->has(AIPS_History_Service_Interface::class));
		$this->assertTrue($this->container->has(AIPS_Notifications_Repository::class));
		$this->assertTrue($this->container->has(AIPS_Notifications_Repository_Interface::class));
		$this->assertTrue($this->container->has(AIPS_Logger::class));
		$this->assertTrue($this->container->has(AIPS_Logger_Interface::class));
		$this->assertTrue($this->container->has(AIPS_AI_Provider_Interface::class));
		$this->assertTrue($this->container->has(AIPS_AI_Service::class));
		$this->assertTrue($this->container->has(AIPS_AI_Service_Interface::class));
		$this->assertTrue($this->container->has(AIPS_Schedule_Repository::class));
		$this->assertTrue($this->container->has(AIPS_Schedule_Repository_Interface::class));
		$this->assertTrue($this->container->has(AIPS_Telemetry_Repository::class));
		$this->assertTrue($this->container->has(AIPS_Template_Repository::class));
	}

	/**
	 * Test that all Monetization Hub dependencies are registered.
	 */
	public function test_monetization_bindings_are_registered() {
		$this->register_plugin_bindings();

		$bindings = array(
			AIPS_Ad_Slots_Repository::class,
			AIPS_Sponsor_Campaigns_Repository::class,
			AIPS_Affiliate_Links_Repository::class,
			AIPS_Monetization_Telemetry_Repository::class,
			AIPS_Ad_Injection_Service::class,
			AIPS_Monetization_AI_Service::class,
			AIPS_Monetization_Controller::class,
			AIPS_Link_Cloaking_Service::class,
		);

		foreach ($bindings as $binding) {
			$this->assertTrue(
				$this->container->has($binding),
				sprintf('Expected container binding for %s.', $binding)
			);
		}
	}

	/**
	 * Regression test for the fatal that occurred when Link Cloaking was resolved
	 * without first registering AIPS_Affiliate_Links_Repository.
	 */
	public function test_link_cloaking_service_resolves_with_registered_dependencies() {
		$this->register_plugin_bindings();

		$service = $this->container->make(AIPS_Link_Cloaking_Service::class);

		$this->assertInstanceOf(AIPS_Link_Cloaking_Service::class, $service);
		$this->assertSame($service, $this->container->make(AIPS_Link_Cloaking_Service::class));
		$this->assertInstanceOf(
			AIPS_Affiliate_Links_Repository::class,
			$this->container->make(AIPS_Affiliate_Links_Repository::class)
		);
	}

	/**
	 * Test that registered bindings return singleton instances.
	 */
	public function test_registered_bindings_return_singletons() {
		$this->register_plugin_bindings();

		$config_a = $this->container->make(AIPS_Config::class);
		$config_b = $this->container->make(AIPS_Config::class);
		$this->assertSame($config_a, $config_b);
		$this->assertSame(AIPS_Config::get_instance(), $config_a);

		$repo_a = $this->container->make(AIPS_History_Repository::class);
		$repo_b = $this->container->make(AIPS_History_Repository::class);
		$this->assertSame($repo_a, $repo_b);
		$this->assertSame(AIPS_History_Repository::instance(), $repo_a);

		$service_a = $this->container->make(AIPS_History_Service::class);
		$service_b = $this->container->make(AIPS_History_Service::class);
		$this->assertSame($service_a, $service_b);
		$this->assertSame(AIPS_History_Service::instance(), $service_a);

		$notif_a = $this->container->make(AIPS_Notifications_Repository::class);
		$notif_b = $this->container->make(AIPS_Notifications_Repository::class);
		$this->assertSame($notif_a, $notif_b);

		$template_a = $this->container->make(AIPS_Template_Repository::class);
		$template_b = $this->container->make(AIPS_Template_Repository::class);
		$this->assertSame($template_a, $template_b);
		$this->assertSame(AIPS_Template_Repository::instance(), $template_a);

		$telemetry_a = $this->container->make(AIPS_Telemetry_Repository::class);
		$telemetry_b = $this->container->make(AIPS_Telemetry_Repository::class);
		$this->assertSame($telemetry_a, $telemetry_b);
		$this->assertSame(AIPS_Telemetry_Repository::instance(), $telemetry_a);

		$affiliate_a = $this->container->make(AIPS_Affiliate_Links_Repository::class);
		$affiliate_b = $this->container->make(AIPS_Affiliate_Links_Repository::class);
		$this->assertSame($affiliate_a, $affiliate_b);
	}

	/**
	 * Test that all registered bindings have singleton scope.
	 */
	public function test_all_registered_bindings_have_singleton_scope() {
		$this->register_plugin_bindings();

		$registered = $this->container->get_registered_bindings();

		foreach ($registered as $binding => $scope) {
			$this->assertEquals('singleton', $scope, sprintf('%s should use singleton scope.', $binding));
		}
	}

	/**
	 * Test that binding count is correct.
	 */
	public function test_binding_count_is_correct() {
		$this->register_plugin_bindings();

		$counts = $this->container->get_binding_counts();

		$this->assertEquals(0, $counts['transient']);
		$this->assertEquals(32, $counts['singleton']);
		$this->assertEquals(32, $counts['total']);
	}
}
