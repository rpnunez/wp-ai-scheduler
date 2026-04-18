<?php
/**
 * Tests for interface implementations and constructor typehints (Phase D.1 - Step 12)
 *
 * Verifies that concrete classes implement the correct interfaces, that the
 * interfaces expose the required methods, and that class constructors accept
 * interface-typed arguments (dependency injection).
 *
 * @package AI_Post_Scheduler
 * @since 2.3.2
 */
class Test_AIPS_Interface_Implementations extends WP_UnitTestCase {

	// -----------------------------------------------------------------------
	// Interface existence
	// -----------------------------------------------------------------------

	/** @test */
	public function test_history_repository_interface_exists() {
		$this->assertTrue(interface_exists('AIPS_History_Repository_Interface'));
	}

	/** @test */
	public function test_history_service_interface_exists() {
		$this->assertTrue(interface_exists('AIPS_History_Service_Interface'));
	}

	/** @test */
	public function test_ai_service_interface_exists() {
		$this->assertTrue(interface_exists('AIPS_AI_Service_Interface'));
	}

	/** @test */
	public function test_logger_interface_exists() {
		$this->assertTrue(interface_exists('AIPS_Logger_Interface'));
	}

	/** @test */
	public function test_schedule_repository_interface_exists() {
		$this->assertTrue(interface_exists('AIPS_Schedule_Repository_Interface'));
	}

	/** @test */
	public function test_notifications_repository_interface_exists() {
		$this->assertTrue(interface_exists('AIPS_Notifications_Repository_Interface'));
	}

	// -----------------------------------------------------------------------
	// Concrete classes implement their interfaces
	// -----------------------------------------------------------------------

	/** @test */
	public function test_history_repository_implements_interface() {
		$this->assertTrue(is_a('AIPS_History_Repository', 'AIPS_History_Repository_Interface', true));
	}

	/** @test */
	public function test_history_service_implements_interface() {
		$this->assertTrue(is_a('AIPS_History_Service', 'AIPS_History_Service_Interface', true));
	}

	/** @test */
	public function test_ai_service_implements_interface() {
		$this->assertTrue(is_a('AIPS_AI_Service', 'AIPS_AI_Service_Interface', true));
	}

	/** @test */
	public function test_logger_implements_interface() {
		$this->assertTrue(is_a('AIPS_Logger', 'AIPS_Logger_Interface', true));
	}

	/** @test */
	public function test_schedule_repository_implements_interface() {
		$this->assertTrue(is_a('AIPS_Schedule_Repository', 'AIPS_Schedule_Repository_Interface', true));
	}

	/** @test */
	public function test_notifications_repository_implements_interface() {
		$this->assertTrue(is_a('AIPS_Notifications_Repository', 'AIPS_Notifications_Repository_Interface', true));
	}

	// -----------------------------------------------------------------------
	// Constructor typehints accept interface mocks
	// -----------------------------------------------------------------------

	/**
	 * Helper: create a minimal mock object that implements the given interface.
	 *
	 * @param string $interface Fully-qualified interface name.
	 * @return object
	 */
	private function make_mock(string $interface): object {
		return $this->createMock($interface);
	}

	/** @test */
	public function test_scheduler_constructor_accepts_interface_typed_args() {
		$repo        = $this->make_mock('AIPS_Schedule_Repository_Interface');
		$hist_repo   = $this->make_mock('AIPS_History_Repository_Interface');
		$hist_svc    = $this->make_mock('AIPS_History_Service_Interface');

		$scheduler = new AIPS_Scheduler($repo, $hist_repo, $hist_svc);

		$this->assertInstanceOf('AIPS_Scheduler', $scheduler);
	}

	/** @test */
	public function test_author_topics_scheduler_constructor_accepts_interface_typed_args() {
		$logger   = $this->make_mock('AIPS_Logger_Interface');
		$hist_svc = $this->make_mock('AIPS_History_Service_Interface');

		$scheduler = new AIPS_Author_Topics_Scheduler($logger, $hist_svc);

		$this->assertInstanceOf('AIPS_Author_Topics_Scheduler', $scheduler);
	}

	/** @test */
	public function test_author_post_generator_constructor_accepts_interface_typed_args() {
		$logger   = $this->make_mock('AIPS_Logger_Interface');
		$hist_svc = $this->make_mock('AIPS_History_Service_Interface');

		$generator = new AIPS_Author_Post_Generator($logger, $hist_svc);

		$this->assertInstanceOf('AIPS_Author_Post_Generator', $generator);
	}

	/** @test */
	public function test_component_regeneration_service_constructor_accepts_interface_typed_args() {
		$hist_repo  = $this->make_mock('AIPS_History_Repository_Interface');
		$ai_service = $this->make_mock('AIPS_AI_Service_Interface');

		$service = new AIPS_Component_Regeneration_Service($hist_repo, $ai_service);

		$this->assertInstanceOf('AIPS_Component_Regeneration_Service', $service);
	}

	/** @test */
	public function test_session_to_json_constructor_accepts_interface_typed_args() {
		$hist_repo = $this->make_mock('AIPS_History_Repository_Interface');
		$logger    = $this->make_mock('AIPS_Logger_Interface');

		$converter = new AIPS_Session_To_JSON($hist_repo, $logger);

		$this->assertInstanceOf('AIPS_Session_To_JSON', $converter);
	}

	/** @test */
	public function test_research_controller_constructor_accepts_interface_typed_args() {
		$logger   = $this->make_mock('AIPS_Logger_Interface');
		$hist_svc = $this->make_mock('AIPS_History_Service_Interface');

		$controller = new AIPS_Research_Controller($logger, $hist_svc);

		$this->assertInstanceOf('AIPS_Research_Controller', $controller);
	}

	/** @test */
	public function test_calendar_controller_constructor_accepts_interface_typed_args() {
		$repo = $this->make_mock('AIPS_Schedule_Repository_Interface');

		$controller = new AIPS_Calendar_Controller($repo);

		$this->assertInstanceOf('AIPS_Calendar_Controller', $controller);
	}

	/** @test */
	public function test_generated_posts_controller_constructor_accepts_interface_typed_args() {
		$hist_repo  = $this->make_mock('AIPS_History_Repository_Interface');
		$sched_repo = $this->make_mock('AIPS_Schedule_Repository_Interface');

		$controller = new AIPS_Generated_Posts_Controller($hist_repo, $sched_repo);

		$this->assertInstanceOf('AIPS_Generated_Posts_Controller', $controller);
	}

	/** @test */
	public function test_unified_schedule_service_constructor_accepts_interface_typed_args() {
		$sched_repo = $this->make_mock('AIPS_Schedule_Repository_Interface');
		$hist_repo  = $this->make_mock('AIPS_History_Repository_Interface');

		$service = new AIPS_Unified_Schedule_Service($sched_repo, $hist_repo);

		$this->assertInstanceOf('AIPS_Unified_Schedule_Service', $service);
	}

	/** @test */
	public function test_history_admin_constructor_accepts_interface_typed_args() {
		$repo = $this->make_mock('AIPS_History_Repository_Interface');

		$history = new AIPS_History($repo);

		$this->assertInstanceOf('AIPS_History', $history);
	}

	/** @test */
	public function test_generation_context_factory_constructor_accepts_interface_typed_args() {
		$repo = $this->make_mock('AIPS_History_Repository_Interface');

		$factory = new AIPS_Generation_Context_Factory($repo);

		$this->assertInstanceOf('AIPS_Generation_Context_Factory', $factory);
	}

	/** @test */
	public function test_post_review_constructor_accepts_interface_typed_args() {
		$hist_svc = $this->make_mock('AIPS_History_Service_Interface');

		$post_review = new AIPS_Post_Review($hist_svc);

		$this->assertInstanceOf('AIPS_Post_Review', $post_review);
	}

	/** @test */
	public function test_generation_logger_constructor_accepts_interface_typed_args() {
		$logger    = $this->make_mock('AIPS_Logger_Interface');
		$hist_repo = $this->make_mock('AIPS_History_Repository_Interface');
		$session   = new AIPS_Generation_Session();

		$gen_logger = new AIPS_Generation_Logger($logger, $hist_repo, $session);

		$this->assertInstanceOf('AIPS_Generation_Logger', $gen_logger);
	}

	// -----------------------------------------------------------------------
	// No-arg (null) constructors still work (BC)
	// -----------------------------------------------------------------------

	/** @test */
	public function test_scheduler_no_arg_constructor_still_works() {
		$scheduler = new AIPS_Scheduler();
		$this->assertInstanceOf('AIPS_Scheduler', $scheduler);
	}

	/** @test */
	public function test_author_topics_scheduler_no_arg_constructor_still_works() {
		$scheduler = new AIPS_Author_Topics_Scheduler();
		$this->assertInstanceOf('AIPS_Author_Topics_Scheduler', $scheduler);
	}

	/** @test */
	public function test_author_post_generator_no_arg_constructor_still_works() {
		$generator = new AIPS_Author_Post_Generator();
		$this->assertInstanceOf('AIPS_Author_Post_Generator', $generator);
	}

	/** @test */
	public function test_session_to_json_no_arg_constructor_still_works() {
		$converter = new AIPS_Session_To_JSON();
		$this->assertInstanceOf('AIPS_Session_To_JSON', $converter);
	}

	/** @test */
	public function test_unified_schedule_service_no_arg_constructor_still_works() {
		$service = new AIPS_Unified_Schedule_Service();
		$this->assertInstanceOf('AIPS_Unified_Schedule_Service', $service);
	}

	/** @test */
	public function test_generation_context_factory_no_arg_constructor_still_works() {
		$factory = new AIPS_Generation_Context_Factory();
		$this->assertInstanceOf('AIPS_Generation_Context_Factory', $factory);
	}

	// -----------------------------------------------------------------------
	// Reflection: verify constructor parameter types
	// -----------------------------------------------------------------------

	/**
	 * Assert that a constructor parameter is typed to a specific interface.
	 *
	 * @param string $class          Class name.
	 * @param int    $param_index    Zero-based parameter position.
	 * @param string $expected_type  Expected type (interface name).
	 */
	private function assert_constructor_param_type(string $class, int $param_index, string $expected_type): void {
		$ref    = new ReflectionClass($class);
		$ctor   = $ref->getConstructor();
		$params = $ctor->getParameters();

		$this->assertArrayHasKey($param_index, $params, "Constructor of $class has no parameter at index $param_index");

		$type = $params[$param_index]->getType();
		$this->assertNotNull($type, "Parameter $param_index of $class constructor has no type hint");
		$this->assertSame($expected_type, $type->getName(), "Unexpected type for parameter $param_index of $class constructor");
	}

	/** @test */
	public function test_scheduler_first_param_is_schedule_repository_interface() {
		$this->assert_constructor_param_type('AIPS_Scheduler', 0, 'AIPS_Schedule_Repository_Interface');
	}

	/** @test */
	public function test_scheduler_second_param_is_history_repository_interface() {
		$this->assert_constructor_param_type('AIPS_Scheduler', 1, 'AIPS_History_Repository_Interface');
	}

	/** @test */
	public function test_scheduler_third_param_is_history_service_interface() {
		$this->assert_constructor_param_type('AIPS_Scheduler', 2, 'AIPS_History_Service_Interface');
	}

	/** @test */
	public function test_author_topics_scheduler_first_param_is_logger_interface() {
		$this->assert_constructor_param_type('AIPS_Author_Topics_Scheduler', 0, 'AIPS_Logger_Interface');
	}

	/** @test */
	public function test_author_topics_scheduler_second_param_is_history_service_interface() {
		$this->assert_constructor_param_type('AIPS_Author_Topics_Scheduler', 1, 'AIPS_History_Service_Interface');
	}

	/** @test */
	public function test_author_post_generator_first_param_is_logger_interface() {
		$this->assert_constructor_param_type('AIPS_Author_Post_Generator', 0, 'AIPS_Logger_Interface');
	}

	/** @test */
	public function test_author_post_generator_second_param_is_history_service_interface() {
		$this->assert_constructor_param_type('AIPS_Author_Post_Generator', 1, 'AIPS_History_Service_Interface');
	}

	/** @test */
	public function test_generation_logger_first_param_is_logger_interface() {
		$this->assert_constructor_param_type('AIPS_Generation_Logger', 0, 'AIPS_Logger_Interface');
	}

	/** @test */
	public function test_generation_logger_second_param_is_history_repository_interface() {
		$this->assert_constructor_param_type('AIPS_Generation_Logger', 1, 'AIPS_History_Repository_Interface');
	}
}
