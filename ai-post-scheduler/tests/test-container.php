<?php
/**
 * Tests for AIPS_Container.
 *
 * @package AI_Post_Scheduler
 */

class Test_AIPS_Container extends WP_UnitTestCase {

	/**
	 * Reset the container singleton before each test.
	 */
	public function setUp(): void {
		parent::setUp();
		AIPS_Container::reset();
	}

	/**
	 * Reset after each test to prevent state leaking.
	 */
	public function tearDown(): void {
		AIPS_Container::reset();
		parent::tearDown();
	}

	// -------------------------------------------------------------------------
	// get_instance
	// -------------------------------------------------------------------------

	public function test_get_instance_returns_container() {
		$this->assertInstanceOf( AIPS_Container::class, AIPS_Container::get_instance() );
	}

	public function test_get_instance_is_singleton() {
		$a = AIPS_Container::get_instance();
		$b = AIPS_Container::get_instance();
		$this->assertSame( $a, $b );
	}

	// -------------------------------------------------------------------------
	// register / get
	// -------------------------------------------------------------------------

	public function test_register_and_get_returns_same_instance() {
		$obj = new stdClass();
		$container = AIPS_Container::get_instance();
		$container->register( 'foo', $obj );

		$this->assertSame( $obj, $container->get( 'foo' ) );
	}

	public function test_register_overwrites_previous_instance() {
		$obj1 = new stdClass();
		$obj2 = new stdClass();
		$container = AIPS_Container::get_instance();

		$container->register( 'foo', $obj1 );
		$container->register( 'foo', $obj2 );

		$this->assertSame( $obj2, $container->get( 'foo' ) );
	}

	// -------------------------------------------------------------------------
	// has
	// -------------------------------------------------------------------------

	public function test_has_returns_true_after_register() {
		$container = AIPS_Container::get_instance();
		$container->register( 'bar', new stdClass() );

		$this->assertTrue( $container->has( 'bar' ) );
	}

	public function test_has_returns_false_for_unknown_id() {
		$this->assertFalse( AIPS_Container::get_instance()->has( 'does_not_exist' ) );
	}

	// -------------------------------------------------------------------------
	// register — error case
	// -------------------------------------------------------------------------

	public function test_register_throws_for_non_object() {
		$this->expectException( InvalidArgumentException::class );
		AIPS_Container::get_instance()->register( 'bad', 'not_an_object' );
	}

	// -------------------------------------------------------------------------
	// get — error case
	// -------------------------------------------------------------------------

	public function test_get_throws_for_unknown_id() {
		$this->expectException( RuntimeException::class );
		AIPS_Container::get_instance()->get( 'unknown' );
	}

	// -------------------------------------------------------------------------
	// reset
	// -------------------------------------------------------------------------

	public function test_reset_creates_fresh_instance() {
		$before = AIPS_Container::get_instance();
		AIPS_Container::reset();
		$after = AIPS_Container::get_instance();

		$this->assertNotSame( $before, $after );
	}

	public function test_reset_clears_registered_services() {
		$container = AIPS_Container::get_instance();
		$container->register( 'service', new stdClass() );

		AIPS_Container::reset();

		$this->assertFalse( AIPS_Container::get_instance()->has( 'service' ) );
	}
}
