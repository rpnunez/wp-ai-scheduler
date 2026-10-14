<?php
/**
 * Tests for AIPS_Background_Process_Repository.
 *
 * @package AI_Post_Scheduler
 */

class Test_AIPS_Background_Process_Repository extends WP_UnitTestCase {

	/** @var AIPS_Background_Process_Repository */
	private $repo;

	public function setUp(): void {
		parent::setUp();
		AIPS_DB_Manager::install_tables();
		$this->repo = new AIPS_Background_Process_Repository();
	}

	public function tearDown(): void {
		global $wpdb;
		$wpdb->query( 'DELETE FROM ' . $wpdb->prefix . 'aips_background_processes' );
		parent::tearDown();
	}

	public function test_create_stores_a_running_run() {
		$id = $this->repo->create( 'demo', 40, array( 'mode' => 'all' ), 25, 7 );
		$this->assertGreaterThan( 0, $id );

		$run = $this->repo->get( $id );
		$this->assertSame( 'demo', $run->process_key );
		$this->assertSame( 'running', $run->status );
		$this->assertSame( 40, $run->total );
		$this->assertSame( 0, $run->processed );
		$this->assertSame( 25, $run->ai_calls_budget );
		$this->assertSame( 7, $run->started_by );
		$this->assertSame( array( 'mode' => 'all' ), $run->options );
	}

	public function test_get_open_returns_active_and_paused_runs_only() {
		$this->assertNull( $this->repo->get_open( 'demo' ) );

		$id = $this->repo->create( 'demo', 10 );
		$this->assertSame( $id, $this->repo->get_open( 'demo' )->id );

		$this->repo->transition( $id, 'paused', array( 'running' ) );
		$this->assertSame( $id, $this->repo->get_open( 'demo' )->id );

		$this->repo->transition( $id, 'cancelled', AIPS_Background_Process_Repository::open_statuses() );
		$this->assertNull( $this->repo->get_open( 'demo' ) );
		$this->assertSame( $id, $this->repo->get_latest( 'demo' )->id );
	}

	public function test_transition_only_applies_from_allowed_statuses() {
		$id = $this->repo->create( 'demo', 10 );

		// A completed run cannot be paused.
		$this->repo->transition( $id, 'completed', array( 'running' ) );
		$this->assertFalse( $this->repo->transition( $id, 'paused', AIPS_Background_Process_Repository::active_statuses() ) );
		$this->assertSame( 'completed', $this->repo->get( $id )->status );
		$this->assertGreaterThan( 0, $this->repo->get( $id )->finished_at );
	}

	public function test_add_progress_accumulates_without_changing_status() {
		$id = $this->repo->create( 'demo', 100 );
		$this->repo->transition( $id, 'paused', array( 'running' ) );

		$this->repo->add_progress( $id, 10, 2, 55, 8, 'first slice' );
		$this->repo->add_progress( $id, 5, 0, 40, 5 );

		$run = $this->repo->get( $id );
		$this->assertSame( 15, $run->processed );
		$this->assertSame( 2, $run->failed );
		$this->assertSame( 55, $run->cursor_id, 'Cursor never moves backwards.' );
		$this->assertSame( 13, $run->ai_calls_used );
		$this->assertSame( 'paused', $run->status, 'Progress must not overwrite a pause.' );
	}

	public function test_get_recent_returns_newest_first_and_filters_by_key() {
		$a = $this->repo->create( 'alpha', 1 );
		$b = $this->repo->create( 'beta', 1 );

		$all = $this->repo->get_recent( 10 );
		$this->assertSame( array( $b, $a ), wp_list_pluck( $all, 'id' ) );

		$only_alpha = $this->repo->get_recent( 10, 'alpha' );
		$this->assertSame( array( $a ), wp_list_pluck( $only_alpha, 'id' ) );
	}

	public function test_cleanup_old_removes_only_finished_stale_runs() {
		global $wpdb;

		$old_done   = $this->repo->create( 'demo', 1 );
		$old_active = $this->repo->create( 'other', 1 );
		$this->repo->transition( $old_done, 'completed', array( 'running' ) );

		$stale = time() - ( ( AIPS_Background_Process_Repository::CLEANUP_DAYS + 1 ) * DAY_IN_SECONDS );
		$wpdb->query( $wpdb->prepare( 'UPDATE ' . $wpdb->prefix . 'aips_background_processes SET updated_at = %d', $stale ) );

		$this->assertSame( 1, $this->repo->cleanup_old() );
		$this->assertNull( $this->repo->get( $old_done ) );
		$this->assertNotNull( $this->repo->get( $old_active ) );
	}
}
