<?php
/**
 * Tests for container construction of History services and repositories.
 *
 * Verifies that History repository and service bindings are resolved properly
 * through AIPS_Container, adhere to interfaces, and support test doubles.
 *
 * @package AI_Post_Scheduler
 */

class Test_AIPS_History_Container_Construction extends WP_UnitTestCase {

	/**
	 * @var AIPS_Container
	 */
	private $container;

	public function setUp(): void {
		parent::setUp();
		$this->container = AIPS_Container::get_instance();
		$this->container->clear();
		AI_Post_Scheduler::get_instance()->register_container_bindings();
	}

	public function tearDown(): void {
		$this->container->clear();
		AI_Post_Scheduler::get_instance()->register_container_bindings();
		parent::tearDown();
	}

	/**
	 * Test resolving AIPS_History_Repository concrete class.
	 */
	public function test_container_resolves_history_repository_concrete() {
		$repo = $this->container->make(AIPS_History_Repository::class);

		$this->assertInstanceOf(AIPS_History_Repository::class, $repo);
		$this->assertInstanceOf(AIPS_History_Repository_Interface::class, $repo);
	}

	/**
	 * Test resolving AIPS_History_Repository_Interface interface.
	 */
	public function test_container_resolves_history_repository_interface() {
		$repo = $this->container->make(AIPS_History_Repository_Interface::class);

		$this->assertInstanceOf(AIPS_History_Repository_Interface::class, $repo);
		$this->assertInstanceOf(AIPS_History_Repository::class, $repo);
	}

	/**
	 * Test that concrete and interface resolve to the exact same singleton instance.
	 */
	public function test_history_repository_concrete_and_interface_share_singleton_instance() {
		$repo1 = $this->container->make(AIPS_History_Repository::class);
		$repo2 = $this->container->make(AIPS_History_Repository_Interface::class);

		$this->assertSame($repo1, $repo2);
	}

	/**
	 * Test resolving AIPS_History_Service concrete class.
	 */
	public function test_container_resolves_history_service_concrete() {
		$service = $this->container->make(AIPS_History_Service::class);

		$this->assertInstanceOf(AIPS_History_Service::class, $service);
		$this->assertInstanceOf(AIPS_History_Service_Interface::class, $service);
	}

	/**
	 * Test resolving AIPS_History_Service_Interface interface.
	 */
	public function test_container_resolves_history_service_interface() {
		$service = $this->container->make(AIPS_History_Service_Interface::class);

		$this->assertInstanceOf(AIPS_History_Service_Interface::class, $service);
		$this->assertInstanceOf(AIPS_History_Service::class, $service);
	}

	/**
	 * Test that concrete and interface resolve to the exact same service instance.
	 */
	public function test_history_service_concrete_and_interface_share_singleton_instance() {
		$service1 = $this->container->make(AIPS_History_Service::class);
		$service2 = $this->container->make(AIPS_History_Service_Interface::class);

		$this->assertSame($service1, $service2);
	}

	/**
	 * Test that the container allows rebinding / mocking the History Repository.
	 */
	public function test_container_allows_mocking_history_repository() {
		$mock_repo = $this->getMockBuilder(AIPS_History_Repository_Interface::class)->getMock();

		$this->container->singleton(AIPS_History_Repository_Interface::class, function () use ($mock_repo) {
			return $mock_repo;
		});

		$resolved = $this->container->make(AIPS_History_Repository_Interface::class);
		$this->assertSame($mock_repo, $resolved);
	}

	/**
	 * Test that the container allows rebinding / mocking the History Service.
	 */
	public function test_container_allows_mocking_history_service() {
		$mock_service = $this->getMockBuilder(AIPS_History_Service_Interface::class)->getMock();

		$this->container->singleton(AIPS_History_Service_Interface::class, function () use ($mock_service) {
			return $mock_service;
		});

		$resolved = $this->container->make(AIPS_History_Service_Interface::class);
		$this->assertSame($mock_service, $resolved);
	}
}
