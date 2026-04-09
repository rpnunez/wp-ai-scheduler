<?php
/**
 * Tests for static instance() singleton factories added to stateless services.
 *
 * Verifies that each class:
 *   1. Exposes a public static `instance()` method.
 *   2. Returns an object of the correct type.
 *   3. Returns the same object on repeated calls (singleton guarantee).
 *   4. Still allows independent instances via `new ClassName()`.
 *
 * @package AI_Post_Scheduler
 * @since 2.2.0
 */
class Test_AIPS_Singleton_Instances extends WP_UnitTestCase {

	/**
	 * Helper: assert that a class has a *public static* instance() method and that
	 * successive calls return the same object.
	 *
	 * Uses ReflectionMethod to enforce that the method is both public and static,
	 * not just that it exists.
	 *
	 * @param string $class Fully-qualified class name.
	 */
	private function assert_singleton_contract( $class ) {
		$this->assertTrue(
			method_exists( $class, 'instance' ),
			"$class::instance() method should exist"
		);

		$ref = new ReflectionMethod( $class, 'instance' );
		$this->assertTrue(
			$ref->isPublic(),
			"$class::instance() should be a public method"
		);
		$this->assertTrue(
			$ref->isStatic(),
			"$class::instance() should be a static method"
		);

		$a = $class::instance();
		$b = $class::instance();

		$this->assertInstanceOf( $class, $a, "$class::instance() should return an instance of $class" );
		$this->assertSame( $a, $b, "$class::instance() should return the same object on repeated calls" );
	}

	/**
	 * Helper: assert that new ClassName() still produces an independent instance
	 * (constructor is not private).
	 *
	 * @param string $class Fully-qualified class name.
	 */
	private function assert_new_produces_independent_instance( $class ) {
		$singleton = $class::instance();
		$fresh     = new $class();
		$this->assertNotSame(
			$singleton,
			$fresh,
			"new $class() should produce an instance independent of the singleton"
		);
	}

	public function test_history_repository_singleton() {
		$this->assert_singleton_contract( 'AIPS_History_Repository' );
	}

	public function test_history_service_singleton() {
		$this->assert_singleton_contract( 'AIPS_History_Service' );
	}

	public function test_notifications_repository_singleton() {
		$this->assert_singleton_contract( 'AIPS_Notifications_Repository' );
	}

	public function test_logger_singleton() {
		$this->assert_singleton_contract( 'AIPS_Logger' );
	}

	public function test_interval_calculator_singleton() {
		$this->assert_singleton_contract( 'AIPS_Interval_Calculator' );
	}

	public function test_template_repository_singleton() {
		$this->assert_singleton_contract( 'AIPS_Template_Repository' );
	}

	public function test_ai_service_singleton() {
		$this->assert_singleton_contract( 'AIPS_AI_Service' );
	}

	/**
	 * Verify that new ClassName() still produces an independent instance for every
	 * class that received a singleton (constructors are not private).
	 */
	public function test_history_repository_new_produces_independent_instance() {
		$this->assert_new_produces_independent_instance( 'AIPS_History_Repository' );
	}

	public function test_history_service_new_produces_independent_instance() {
		$this->assert_new_produces_independent_instance( 'AIPS_History_Service' );
	}

	public function test_notifications_repository_new_produces_independent_instance() {
		$this->assert_new_produces_independent_instance( 'AIPS_Notifications_Repository' );
	}

	public function test_logger_new_produces_independent_instance() {
		$this->assert_new_produces_independent_instance( 'AIPS_Logger' );
	}

	public function test_interval_calculator_new_produces_independent_instance() {
		$this->assert_new_produces_independent_instance( 'AIPS_Interval_Calculator' );
	}

	public function test_template_repository_new_produces_independent_instance() {
		$this->assert_new_produces_independent_instance( 'AIPS_Template_Repository' );
	}

	public function test_ai_service_new_produces_independent_instance() {
		$this->assert_new_produces_independent_instance( 'AIPS_AI_Service' );
	}
}
