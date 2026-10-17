<?php
/**
 * Tests for the stored-payload limits (size cap and redaction filter) and the
 * retention of finished Ability Workflow runs.
 *
 * @package AI_Post_Scheduler
 */

class Test_AIPS_Ability_Workflow_Payload_Retention extends WP_UnitTestCase {

	/** @var AIPS_Ability_Workflow_Repository */
	private $repository;

	/** @var int */
	private $workflow_id;

	/** @var AIPS_Ability_Workflow_Step */
	private $step;

	public function setUp(): void {
		parent::setUp();

		$this->repository  = AIPS_Ability_Workflow_Repository::instance();
		$this->workflow_id = $this->repository->create_workflow( array( 'name' => 'Retention test' ) );
		$this->repository->save_steps( $this->workflow_id, array( array( 'step_key' => 'a', 'ability_name' => 'vendor/a' ) ) );
		$this->step = $this->repository->get_steps( $this->workflow_id )[0];
	}

	public function tearDown(): void {
		remove_all_filters( 'aips_ability_workflow_redact_payload' );
		remove_all_filters( 'aips_ability_workflow_retention_days' );
		AIPS_Config::get_instance()->set_option( 'aips_ability_workflow_retention_days', 30 );
		$this->repository->delete_workflow_with_runs( $this->workflow_id );

		parent::tearDown();
	}

	private function stored_output( $step_run_id ) {
		global $wpdb;

		return json_decode( $wpdb->get_var( $wpdb->prepare( "SELECT output_snapshot FROM {$wpdb->prefix}aips_ability_workflow_step_runs WHERE id = %d", $step_run_id ) ), true );
	}

	private function step_run() {
		$run_id = $this->repository->create_run( $this->workflow_id, 1 );

		return $this->repository->create_step_run( $run_id, $this->workflow_id, $this->step, array() );
	}

	// ---------------------------------------------------------------------
	// Size cap
	// ---------------------------------------------------------------------

	public function test_a_small_payload_is_stored_unchanged() {
		$step_run_id = $this->step_run();
		$output      = array( 'title' => 'Hello', 'count' => 3, 'nested' => array( 'a' => 1 ) );

		$this->repository->update_step_run_status( $step_run_id, 'completed', array( 'output_snapshot' => $output ) );

		$this->assertSame( $output, $this->stored_output( $step_run_id ) );
	}

	public function test_a_large_value_is_replaced_by_a_marker_and_small_fields_survive() {
		$step_run_id = $this->step_run();
		$output      = array(
			'title'   => 'Keep me',
			'items'   => range( 1, 120000 ),
			'summary' => 'Also keep me',
		);

		$this->repository->update_step_run_status( $step_run_id, 'completed', array( 'output_snapshot' => $output ) );

		$stored = $this->stored_output( $step_run_id );

		$this->assertSame( 'Keep me', $stored['title'] );
		$this->assertSame( 'Also keep me', $stored['summary'] );
		$this->assertTrue( $stored['items']['_truncated'] );
		$this->assertGreaterThan( AIPS_Ability_Workflow_Repository::SNAPSHOT_MAX_BYTES, $stored['items']['_original_bytes'] );
		$this->assertSame( array( 'items' ), $stored['_truncated'] );
	}

	public function test_a_long_text_is_cut_to_a_preview() {
		$step_run_id = $this->step_run();
		$long        = str_repeat( 'abcdefghij', 40000 );

		$this->repository->update_step_run_status( $step_run_id, 'completed', array( 'output_snapshot' => array( 'article' => $long, 'id' => 7 ) ) );

		$stored = $this->stored_output( $step_run_id );

		$this->assertSame( 7, $stored['id'] );
		$this->assertLessThan( 2000, strlen( $stored['article'] ) );
		$this->assertStringStartsWith( 'abcdefghij', $stored['article'] );
		$this->assertStringEndsWith( '[truncated]', $stored['article'] );
	}

	public function test_many_medium_values_that_cannot_be_shortened_individually_collapse_to_one_marker() {
		$payload = array();
		for ( $i = 0; $i < 600; $i++ ) {
			$payload[ 'k' . $i ] = str_repeat( 'x', 900 );
		}

		$json = $this->repository->prepare_payload( $payload, 'step_output' );

		$this->assertLessThanOrEqual( AIPS_Ability_Workflow_Repository::SNAPSHOT_MAX_BYTES, strlen( $json ) );
		$this->assertTrue( json_decode( $json, true )['_truncated'] );
	}

	public function test_input_and_trigger_payloads_are_capped_too() {
		$big = array( 'blob' => str_repeat( 'z', 400000 ), 'keep' => 'yes' );

		$run_id = $this->repository->create_run( $this->workflow_id, 1, $big );
		$run    = $this->repository->get_run( $run_id );
		$this->assertSame( 'yes', $run->trigger_context['keep'] );
		$this->assertLessThan( 5000, strlen( wp_json_encode( $run->trigger_context ) ) );

		$step_run_id = $this->repository->create_step_run( $run_id, $this->workflow_id, $this->step, $big );
		$step_run    = $this->repository->get_step_runs( $run_id )[0];
		$this->assertSame( $step_run_id, $step_run->id );
		$this->assertSame( 'yes', $step_run->input_snapshot['keep'] );
		$this->assertLessThan( 5000, strlen( wp_json_encode( $step_run->input_snapshot ) ) );
	}

	// ---------------------------------------------------------------------
	// Redaction filter
	// ---------------------------------------------------------------------

	public function test_the_redaction_filter_runs_before_storing_and_receives_the_kind() {
		$seen = array();
		add_filter(
			'aips_ability_workflow_redact_payload',
			function ( $data, $kind, $id ) use ( &$seen ) {
				$seen[] = $kind;
				unset( $data['api_key'] );

				return $data;
			},
			10,
			3
		);

		$run_id      = $this->repository->create_run( $this->workflow_id, 1, array( 'api_key' => 'secret', 'topic' => 'bikes' ) );
		$step_run_id = $this->repository->create_step_run( $run_id, $this->workflow_id, $this->step, array( 'api_key' => 'secret', 'text' => 'hi' ) );
		$this->repository->update_step_run_status( $step_run_id, 'completed', array( 'output_snapshot' => array( 'api_key' => 'secret', 'ok' => true ) ) );

		$this->assertSame( array( 'topic' => 'bikes' ), $this->repository->get_run( $run_id )->trigger_context );
		$this->assertSame( array( 'text' => 'hi' ), $this->repository->get_step_runs( $run_id )[0]->input_snapshot );
		$this->assertSame( array( 'ok' => true ), $this->stored_output( $step_run_id ) );
		$this->assertSame( array( 'trigger', 'step_input', 'step_output' ), $seen );
	}

	public function test_a_filter_that_returns_something_other_than_an_array_is_ignored() {
		add_filter( 'aips_ability_workflow_redact_payload', '__return_false' );

		$this->assertSame( array( 'a' => 1 ), json_decode( $this->repository->prepare_payload( array( 'a' => 1 ), 'step_output' ), true ) );
	}

	public function test_a_payload_that_cannot_be_encoded_is_stored_as_a_marker() {
		$json = $this->repository->prepare_payload( array( 'bad' => NAN ), 'step_output' );

		$this->assertSame( array( '_unencodable' => true ), json_decode( $json, true ) );
	}

	// ---------------------------------------------------------------------
	// Retention
	// ---------------------------------------------------------------------

	private function make_run( $status, $age_days ) {
		global $wpdb;

		$run_id = $this->repository->create_run( $this->workflow_id, 1 );
		$then   = time() - ( $age_days * DAY_IN_SECONDS );

		$wpdb->update(
			$wpdb->prefix . 'aips_ability_workflow_runs',
			array( 'status' => $status, 'created_at' => $then, 'started_at' => $then, 'finished_at' => $then ),
			array( 'id' => $run_id )
		);
		$this->repository->create_step_run( $run_id, $this->workflow_id, $this->step, array() );

		return $run_id;
	}

	public function test_only_finished_runs_older_than_the_cutoff_are_deleted_with_their_step_runs() {
		$old_done    = $this->make_run( 'completed', 40 );
		$old_failed  = $this->make_run( 'failed', 40 );
		$old_cancel  = $this->make_run( 'cancelled', 40 );
		$old_running = $this->make_run( 'running', 40 );
		$old_queued  = $this->make_run( 'queued', 40 );
		$recent_done = $this->make_run( 'completed', 5 );

		$deleted = $this->repository->delete_terminal_runs_before( time() - 30 * DAY_IN_SECONDS );

		$this->assertSame( 3, $deleted );
		foreach ( array( $old_done, $old_failed, $old_cancel ) as $run_id ) {
			$this->assertNull( $this->repository->get_run( $run_id ) );
			$this->assertSame( array(), $this->repository->get_step_runs( $run_id ) );
		}
		foreach ( array( $old_running, $old_queued, $recent_done ) as $run_id ) {
			$this->assertNotNull( $this->repository->get_run( $run_id ), 'Open and recent runs stay.' );
			$this->assertCount( 1, $this->repository->get_step_runs( $run_id ) );
		}
	}

	public function test_deletion_is_limited_per_call() {
		$this->make_run( 'completed', 40 );
		$this->make_run( 'completed', 41 );
		$this->make_run( 'completed', 42 );

		$this->assertSame( 2, $this->repository->delete_terminal_runs_before( time() - 30 * DAY_IN_SECONDS, 2 ) );
		$this->assertSame( 1, $this->repository->delete_terminal_runs_before( time() - 30 * DAY_IN_SECONDS, 2 ) );
	}

	public function test_the_process_prunes_by_the_retention_setting() {
		$old    = $this->make_run( 'completed', 40 );
		$recent = $this->make_run( 'completed', 5 );
		AIPS_Config::get_instance()->set_option( 'aips_ability_workflow_retention_days', 30 );

		$this->assertSame( 1, ( new AIPS_Ability_Workflows_Process() )->prune_old_runs() );

		$this->assertNull( $this->repository->get_run( $old ) );
		$this->assertNotNull( $this->repository->get_run( $recent ) );
	}

	public function test_a_retention_of_zero_keeps_everything() {
		$old = $this->make_run( 'completed', 400 );
		AIPS_Config::get_instance()->set_option( 'aips_ability_workflow_retention_days', 0 );

		$this->assertSame( 0, ( new AIPS_Ability_Workflows_Process() )->prune_old_runs() );
		$this->assertNotNull( $this->repository->get_run( $old ) );
	}

	public function test_the_retention_filter_can_override_the_setting() {
		$old = $this->make_run( 'completed', 40 );
		AIPS_Config::get_instance()->set_option( 'aips_ability_workflow_retention_days', 0 );
		add_filter(
			'aips_ability_workflow_retention_days',
			function () {
				return 10;
			}
		);

		$this->assertSame( 1, ( new AIPS_Ability_Workflows_Process() )->prune_old_runs() );
		$this->assertNull( $this->repository->get_run( $old ) );
	}

	public function test_pruning_runs_even_when_workflows_are_switched_off() {
		$old = $this->make_run( 'completed', 40 );
		AIPS_Config::get_instance()->set_option( 'aips_enable_ability_workflows', 0 );

		$this->assertSame( 1, ( new AIPS_Ability_Workflows_Process() )->prune_old_runs() );
		$this->assertNull( $this->repository->get_run( $old ) );
	}

	public function test_the_retention_setting_defaults_to_thirty_days_and_is_registered() {
		$defaults = AIPS_Config::get_instance()->get_default_options();

		$this->assertSame( 30, $defaults['aips_ability_workflow_retention_days'] );
		$this->assertSame( 30, AIPS_Config::get_instance()->get_option( 'aips_ability_workflow_retention_days' ) );
	}
}
