<?php
/**
 * Tests for AIPS_Cron_Backend_* scheduling adapters (issue #2375).
 *
 * @package AI_Post_Scheduler
 */

class Test_AIPS_Cron_Backend extends WP_UnitTestCase {

	/** @var AIPS_Cron_Manager */
	private $cron_manager;

	/** @var AIPS_Cron_Registry */
	private $registry;

	public function setUp(): void {
		parent::setUp();
		AIPS_Cron_Backend_Resolver::reset();

		$this->registry     = new AIPS_Cron_Registry();
		$this->cron_manager = new AIPS_Cron_Manager( $this->registry );

		wp_unschedule_hook( 'aips_test_backend_hook' );
		wp_unschedule_hook( 'aips_test_backend_recurring' );
	}

	public function tearDown(): void {
		wp_unschedule_hook( 'aips_test_backend_hook' );
		wp_unschedule_hook( 'aips_test_backend_recurring' );
		AIPS_Cron_Backend_Resolver::reset();
		parent::tearDown();
	}

	// -------------------------------------------------------------------------
	// WP-Cron Backend Tests
	// -------------------------------------------------------------------------

	public function test_wp_cron_backend_schedule_single_and_dedup() {
		$backend = new AIPS_Cron_Backend_WP_Cron( $this->cron_manager );
		$this->assertSame( 'wp_cron', $backend->get_backend_name() );

		$time = time() + 600;
		$args = array( 42, 'test' );

		$this->assertFalse( $backend->has_scheduled( 'aips_test_backend_hook', $args ) );

		$this->assertTrue( $backend->schedule_single( $time, 'aips_test_backend_hook', $args ) );
		$this->assertTrue( $backend->has_scheduled( 'aips_test_backend_hook', $args ) );
		$this->assertSame( $time, $backend->next_scheduled( 'aips_test_backend_hook', $args ) );

		// Dedup: scheduling again does not fail or duplicate
		$this->assertTrue( $backend->schedule_single( $time + 100, 'aips_test_backend_hook', $args ) );
		$this->assertSame( $time, $backend->next_scheduled( 'aips_test_backend_hook', $args ) );

		// Unschedule
		$this->assertTrue( $backend->unschedule( 'aips_test_backend_hook', $args ) );
		$this->assertFalse( $backend->has_scheduled( 'aips_test_backend_hook', $args ) );
	}

	public function test_wp_cron_backend_schedule_recurring_and_clear_all() {
		$backend = new AIPS_Cron_Backend_WP_Cron( $this->cron_manager );
		$time    = time() + 300;

		$this->assertTrue( $backend->schedule_recurring( $time, 'hourly', 'aips_test_backend_recurring' ) );
		$this->assertTrue( $backend->has_scheduled( 'aips_test_backend_recurring' ) );

		$actions = $backend->get_scheduled_actions( 'aips_test_backend_recurring' );
		$this->assertCount( 1, $actions );
		$this->assertSame( 'aips_test_backend_recurring', $actions[0]['hook'] );
		$this->assertSame( 'wp_cron', $actions[0]['backend'] );

		// Unschedule all
		$this->assertTrue( $backend->unschedule_all( 'aips_test_backend_recurring' ) );
		$this->assertFalse( $backend->has_scheduled( 'aips_test_backend_recurring' ) );
	}

	public function test_wp_cron_backend_recurring_with_numeric_interval() {
		$backend = new AIPS_Cron_Backend_WP_Cron( $this->cron_manager );
		$time    = time() + 300;

		$this->assertTrue( $backend->schedule_recurring( $time, 3600, 'aips_test_backend_recurring' ) );
		$this->assertTrue( $backend->has_scheduled( 'aips_test_backend_recurring' ) );
		$this->assertTrue( $backend->unschedule_all( 'aips_test_backend_recurring' ) );
	}

	// -------------------------------------------------------------------------
	// Resolver & Container Tests
	// -------------------------------------------------------------------------

	public function test_resolver_defaults_to_wp_cron_when_as_absent() {
		$backend = AIPS_Cron_Backend_Resolver::resolve( $this->cron_manager, true );
		$this->assertInstanceOf( AIPS_Cron_Backend_Interface::class, $backend );
		$this->assertInstanceOf( AIPS_Cron_Backend_WP_Cron::class, $backend );
		$this->assertSame( 'wp_cron', $backend->get_backend_name() );
	}

	public function test_container_resolves_cron_backend_interface() {
		$container = AIPS_Container::get_instance();
		$backend   = $container->make( AIPS_Cron_Backend_Interface::class );

		$this->assertInstanceOf( AIPS_Cron_Backend_Interface::class, $backend );
		$this->assertSame( $backend, $container->make( AIPS_Cron_Backend_Interface::class ) );
	}

	// -------------------------------------------------------------------------
	// ActionScheduler Backend Unit / Normalization Tests
	// -------------------------------------------------------------------------

	public function test_actionscheduler_backend_argument_normalization() {
		$backend = new AIPS_Cron_Backend_ActionScheduler();

		$reflector = new ReflectionClass( $backend );
		$method    = $reflector->getMethod( 'normalize_args' );
		$method->setAccessible( true );

		// Empty array remains empty
		$this->assertSame( array(), $method->invoke( $backend, array() ) );

		// Indexed array remains flat
		$indexed = array( 123, 'slug' );
		$this->assertSame( $indexed, $method->invoke( $backend, $indexed ) );

		// Associative array gets wrapped into array($args) to prevent AS argument spreading
		$associative = array( 'author_id' => 456, 'limit' => 10 );
		$normalized  = $method->invoke( $backend, $associative );
		$this->assertSame( array( $associative ), $normalized );
	}

	public function test_actionscheduler_backend_graceful_when_as_missing() {
		$backend = new AIPS_Cron_Backend_ActionScheduler();

		// When AS functions do not exist, calls should return false/empty safely
		if ( ! function_exists( 'as_schedule_single_action' ) ) {
			$this->assertFalse( $backend->schedule_single( time() + 100, 'test_hook' ) );
			$this->assertFalse( $backend->schedule_recurring( time() + 100, 3600, 'test_hook' ) );
			$this->assertFalse( $backend->next_scheduled( 'test_hook' ) );
			$this->assertFalse( $backend->has_scheduled( 'test_hook' ) );
			$this->assertSame( array(), $backend->get_scheduled_actions() );
		}
	}

	// -------------------------------------------------------------------------
	// Registry Group Constants Tests
	// -------------------------------------------------------------------------

	public function test_cron_registry_action_scheduler_groups() {
		$groups = $this->registry->get_action_scheduler_groups();

		$this->assertContains( AIPS_Cron_Registry::GROUP_INDEXER, $groups );
		$this->assertContains( AIPS_Cron_Registry::GROUP_INTERNAL_LINKS, $groups );
		$this->assertContains( AIPS_Cron_Registry::GROUP_EMBEDDINGS, $groups );
		$this->assertContains( AIPS_Cron_Registry::GROUP_DEFAULT, $groups );

		$this->assertSame( 'aips-indexer', AIPS_Cron_Registry::GROUP_INDEXER );
		$this->assertSame( 'aips-internal-links', AIPS_Cron_Registry::GROUP_INTERNAL_LINKS );
		$this->assertSame( 'aips-embeddings', AIPS_Cron_Registry::GROUP_EMBEDDINGS );
		$this->assertSame( 'aips-default', AIPS_Cron_Registry::GROUP_DEFAULT );
	}
}
