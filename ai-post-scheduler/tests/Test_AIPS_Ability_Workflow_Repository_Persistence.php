<?php
/**
 * Persistence tests for AIPS_Ability_Workflow_Repository: step saving
 * (validation, atomicity), versioning, run status guards, the run History
 * link, and the options-based run locks.
 *
 * @package AI_Post_Scheduler
 */

class Test_AIPS_Ability_Workflow_Repository_Persistence extends WP_UnitTestCase {

	/** @var AIPS_Ability_Workflow_Repository */
	private $repository;

	/** @var int[] */
	private $workflow_ids = array();

	public function setUp(): void {
		parent::setUp();
		$this->repository = AIPS_Ability_Workflow_Repository::instance();
	}

	public function tearDown(): void {
		foreach ( $this->workflow_ids as $workflow_id ) {
			$this->repository->delete_workflow( $workflow_id );
		}

		parent::tearDown();
	}

	private function make_workflow( array $data = array() ) {
		$workflow_id = $this->repository->create_workflow( array_merge( array( 'name' => 'Repository test workflow' ), $data ) );
		$this->workflow_ids[] = $workflow_id;

		return $workflow_id;
	}

	private function step( $key, array $extra = array() ) {
		return array_merge( array( 'step_key' => $key, 'ability_name' => 'vendor/' . $key ), $extra );
	}

	private function step_keys( $workflow_id ) {
		return array_map(
			function ( $step ) {
				return $step->step_key;
			},
			$this->repository->get_steps( $workflow_id )
		);
	}

	// ---------------------------------------------------------------------
	// save_steps
	// ---------------------------------------------------------------------

	public function test_save_steps_replaces_steps_in_order_and_bumps_the_version() {
		$workflow_id = $this->make_workflow();
		$before      = $this->repository->get_workflow( $workflow_id )->version;

		$this->assertTrue( $this->repository->save_steps( $workflow_id, array( $this->step( 'one' ), $this->step( 'two' ) ) ) );
		$this->assertSame( array( 'one', 'two' ), $this->step_keys( $workflow_id ) );

		$this->assertTrue( $this->repository->save_steps( $workflow_id, array( $this->step( 'three' ) ) ) );
		$this->assertSame( array( 'three' ), $this->step_keys( $workflow_id ) );
		$this->assertSame( $before + 2, $this->repository->get_workflow( $workflow_id )->version );
	}

	public function test_save_steps_keeps_existing_steps_when_a_later_step_is_invalid() {
		$workflow_id = $this->make_workflow();
		$this->repository->save_steps( $workflow_id, array( $this->step( 'keep' ) ) );
		$version = $this->repository->get_workflow( $workflow_id )->version;

		$result = $this->repository->save_steps(
			$workflow_id,
			array( $this->step( 'new_one' ), array( 'step_key' => 'no_ability' ) )
		);

		$this->assertWPError( $result );
		$this->assertSame( 'ability_workflow_step_invalid', $result->get_error_code() );
		$this->assertSame( array( 'keep' ), $this->step_keys( $workflow_id ), 'Validation must happen before anything is deleted.' );
		$this->assertSame( $version, $this->repository->get_workflow( $workflow_id )->version );
	}

	public function test_save_steps_rejects_values_wider_than_their_columns() {
		$workflow_id = $this->make_workflow();
		$this->repository->save_steps( $workflow_id, array( $this->step( 'keep' ) ) );

		$cases = array(
			'step_key'     => $this->step( str_repeat( 'k', AIPS_Ability_Workflow_Repository::STEP_KEY_MAX_LENGTH + 1 ) ),
			'output_alias' => $this->step( 'ok', array( 'output_alias' => str_repeat( 'a', AIPS_Ability_Workflow_Repository::OUTPUT_ALIAS_MAX_LENGTH + 1 ) ) ),
			'ability_name' => array(
				'step_key'     => 'ok',
				'ability_name' => str_repeat( 'x', AIPS_Ability_Workflow_Repository::ABILITY_NAME_MAX_LENGTH + 1 ),
			),
		);

		foreach ( $cases as $field => $step ) {
			$result = $this->repository->save_steps( $workflow_id, array( $step ) );

			$this->assertWPError( $result, $field );
			$this->assertSame( array( 'keep' ), $this->step_keys( $workflow_id ), $field . ' must not wipe the saved steps.' );
		}
	}

	public function test_save_steps_accepts_values_at_the_column_limit() {
		$workflow_id = $this->make_workflow();
		$key         = str_repeat( 'k', AIPS_Ability_Workflow_Repository::STEP_KEY_MAX_LENGTH );

		$this->assertTrue( $this->repository->save_steps( $workflow_id, array( $this->step( $key ) ) ) );
		$this->assertSame( array( $key ), $this->step_keys( $workflow_id ) );
	}

	public function test_save_steps_rolls_back_when_an_insert_fails_part_way() {
		global $wpdb;

		$workflow_id = $this->make_workflow();
		$this->repository->save_steps( $workflow_id, array( $this->step( 'keep' ) ) );
		$version = $this->repository->get_workflow( $workflow_id )->version;

		$inserts   = 0;
		$steps_tbl = $wpdb->prefix . 'aips_ability_workflow_steps';
		$sabotage  = function ( $query ) use ( &$inserts, $steps_tbl, $wpdb ) {
			if ( 0 === strpos( $query, "INSERT INTO `{$steps_tbl}`" ) && 2 === ++$inserts ) {
				return "INSERT INTO `{$wpdb->prefix}aips_table_that_does_not_exist` (id) VALUES (1)";
			}

			return $query;
		};

		$suppress = $wpdb->suppress_errors( true );
		add_filter( 'query', $sabotage );

		$result = $this->repository->save_steps( $workflow_id, array( $this->step( 'a' ), $this->step( 'b' ), $this->step( 'c' ) ) );

		remove_filter( 'query', $sabotage );
		$wpdb->suppress_errors( $suppress );

		$this->assertWPError( $result );
		$this->assertSame( 'ability_workflow_step_save_failed', $result->get_error_code() );
		$this->assertSame( array( 'keep' ), $this->step_keys( $workflow_id ), 'A failed save must restore the previous steps.' );
		$this->assertSame( $version, $this->repository->get_workflow( $workflow_id )->version );
	}

	public function test_save_steps_normalizes_optional_fields() {
		$workflow_id = $this->make_workflow();

		$this->repository->save_steps( $workflow_id, array( $this->step( 'plain', array( 'name' => '', 'output_alias' => '' ) ) ) );
		$step = $this->repository->get_steps( $workflow_id )[0];

		$this->assertNull( $step->name );
		$this->assertNull( $step->output_alias );
		$this->assertSame( array( 'strategy' => 'continue' ), $step->on_success );
		$this->assertSame( array( 'strategy' => 'stop' ), $step->on_failure );
	}

	// ---------------------------------------------------------------------
	// Workflow header
	// ---------------------------------------------------------------------

	public function test_changing_settings_bumps_the_version_but_renaming_does_not() {
		$workflow_id = $this->make_workflow();
		$version     = $this->repository->get_workflow( $workflow_id )->version;

		$this->repository->update_workflow( $workflow_id, array( 'name' => 'Renamed' ) );
		$this->assertSame( $version, $this->repository->get_workflow( $workflow_id )->version );

		$this->repository->update_workflow( $workflow_id, array( 'settings' => array( 'max_steps' => 3 ) ) );
		$this->assertSame( $version + 1, $this->repository->get_workflow( $workflow_id )->version );
	}

	public function test_duplicate_workflow_copies_steps_into_a_draft() {
		$workflow_id = $this->make_workflow( array( 'status' => 'active' ) );
		$this->repository->save_steps( $workflow_id, array( $this->step( 'one' ), $this->step( 'two' ) ) );

		$copy_id = $this->repository->duplicate_workflow( $workflow_id );
		$this->workflow_ids[] = $copy_id;

		$this->assertIsInt( $copy_id );
		$this->assertSame( 'draft', $this->repository->get_workflow( $copy_id )->status );
		$this->assertSame( array( 'one', 'two' ), $this->step_keys( $copy_id ) );
	}

	// ---------------------------------------------------------------------
	// Run status and History link
	// ---------------------------------------------------------------------

	public function test_run_status_cannot_leave_a_terminal_status() {
		$workflow_id = $this->make_workflow();

		foreach ( array( 'completed', 'failed', 'cancelled' ) as $terminal ) {
			$run_id = $this->repository->create_run( $workflow_id, 1 );

			$this->assertTrue( $this->repository->update_run_status( $run_id, 'running', array( 'started_at' => 100 ) ) );
			$this->assertSame( 'running', $this->repository->get_run( $run_id )->status );

			$this->repository->update_run_status( $run_id, $terminal, array( 'finished_at' => 200 ) );
			$this->repository->update_run_status( $run_id, 'running', array( 'finished_at' => 0 ) );
			$this->repository->update_run_status( $run_id, 'completed', array( 'finished_at' => 300 ) );

			$run = $this->repository->get_run( $run_id );
			$this->assertSame( $terminal, $run->status, 'A late write must not overwrite ' . $terminal . '.' );
			$this->assertSame( 200, $run->finished_at );
		}
	}

	public function test_run_remembers_its_history_row() {
		$workflow_id = $this->make_workflow();
		$run_id      = $this->repository->create_run( $workflow_id, 1 );

		$this->assertNull( $this->repository->get_run( $run_id )->history_id );

		$this->assertTrue( $this->repository->set_run_history_id( $run_id, 4242 ) );
		$this->assertSame( 4242, $this->repository->get_run( $run_id )->history_id );
	}

	// ---------------------------------------------------------------------
	// Locks
	// ---------------------------------------------------------------------

	public function test_lock_is_exclusive_until_released_by_its_owner() {
		$key = 'aips_ability_workflow_lock_test_exclusive';

		$this->assertTrue( $this->repository->acquire_lock( $key, 'worker-a', 60 ) );
		$this->assertFalse( $this->repository->acquire_lock( $key, 'worker-b', 60 ) );

		$this->repository->release_lock( $key, 'worker-b' );
		$this->assertFalse( $this->repository->acquire_lock( $key, 'worker-b', 60 ), 'Only the owner may release a lock.' );

		$this->repository->release_lock( $key, 'worker-a' );
		$this->assertTrue( $this->repository->acquire_lock( $key, 'worker-b', 60 ) );

		$this->repository->release_lock( $key, 'worker-b' );
	}

	public function test_expired_lock_is_taken_over_and_the_old_owner_loses_it() {
		global $wpdb;

		$key = 'aips_ability_workflow_lock_test_expired';
		$this->assertTrue( $this->repository->acquire_lock( $key, 'crashed', 60 ) );
		$wpdb->update( $wpdb->options, array( 'option_value' => 'crashed|' . ( time() - 5 ) ), array( 'option_name' => $key ) );
		wp_cache_delete( $key, 'options' );

		$this->assertTrue( $this->repository->acquire_lock( $key, 'recovery', 60 ) );

		$this->assertFalse( $this->repository->refresh_lock( $key, 'crashed', 60 ), 'The old owner must not extend a lock it lost.' );
		$this->repository->release_lock( $key, 'crashed' );
		$this->assertFalse( $this->repository->acquire_lock( $key, 'third', 60 ), 'The old owner must not release the new owner lock.' );

		$this->repository->release_lock( $key, 'recovery' );
	}

	public function test_an_expired_lock_cannot_be_taken_over_twice() {
		global $wpdb;

		$key = 'aips_ability_workflow_lock_test_cas';
		$this->assertTrue( $this->repository->acquire_lock( $key, 'crashed', 60 ) );
		$wpdb->update( $wpdb->options, array( 'option_value' => 'crashed|' . ( time() - 5 ) ), array( 'option_name' => $key ) );
		wp_cache_delete( $key, 'options' );

		$this->assertTrue( $this->repository->acquire_lock( $key, 'winner', 60 ) );
		$this->assertFalse( $this->repository->acquire_lock( $key, 'loser', 60 ), 'Two workers must not both take over the same expired lock.' );

		$this->repository->release_lock( $key, 'winner' );
	}

	public function test_refreshing_twice_within_one_second_still_reports_ownership() {
		$key = 'aips_ability_workflow_lock_test_same_second';
		$this->repository->acquire_lock( $key, 'worker', 600 );

		// The stored value is unchanged, which MySQL reports as zero changed rows.
		$this->assertTrue( $this->repository->refresh_lock( $key, 'worker', 600 ) );
		$this->assertTrue( $this->repository->refresh_lock( $key, 'worker', 600 ) );
		$this->assertFalse( $this->repository->refresh_lock( $key, 'someone-else', 600 ) );

		$this->repository->release_lock( $key, 'worker' );
	}

	public function test_refresh_extends_a_held_lock() {
		global $wpdb;

		$key = 'aips_ability_workflow_lock_test_refresh';
		$this->repository->acquire_lock( $key, 'worker', 5 );

		$this->assertTrue( $this->repository->refresh_lock( $key, 'worker', 600 ) );

		$value = $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", $key ) );
		$this->assertGreaterThan( time() + 300, (int) explode( '|', $value )[1] );

		$this->repository->release_lock( $key, 'worker' );
	}
}
