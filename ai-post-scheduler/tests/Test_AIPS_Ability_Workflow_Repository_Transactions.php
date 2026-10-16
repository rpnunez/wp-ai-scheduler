<?php
/**
 * Tests the real transaction path of AIPS_Ability_Workflow_Repository::save_steps().
 *
 * WP_UnitTestCase keeps a transaction open with autocommit off, which makes
 * save_steps() use a savepoint. These tests commit that transaction and turn
 * autocommit on, as on a normal request, so the real START TRANSACTION /
 * COMMIT / ROLLBACK statements run. They therefore create real rows and clean
 * up after themselves; the transaction is re-opened so the harness can roll
 * back as usual.
 *
 * @group no-transaction
 * @package AI_Post_Scheduler
 */

class Test_AIPS_Ability_Workflow_Repository_Transactions extends WP_UnitTestCase {

	/** @var AIPS_Ability_Workflow_Repository */
	private $repository;

	/** @var int[] */
	private $workflow_ids = array();

	public function setUp(): void {
		parent::setUp();

		global $wpdb;

		$this->repository = AIPS_Ability_Workflow_Repository::instance();

		// Leave the harness transaction and behave like a normal request.
		$wpdb->query( 'COMMIT' );
		$wpdb->query( 'SET autocommit = 1' );
	}

	public function tearDown(): void {
		global $wpdb;

		foreach ( $this->workflow_ids as $workflow_id ) {
			$this->repository->delete_workflow( $workflow_id );
		}

		// Give the harness the open transaction it expects to roll back.
		$wpdb->query( 'SET autocommit = 0' );
		$wpdb->query( 'START TRANSACTION' );

		parent::tearDown();
	}

	private function make_workflow() {
		$workflow_id = $this->repository->create_workflow( array( 'name' => 'Transaction test workflow' ) );
		$this->workflow_ids[] = $workflow_id;

		return $workflow_id;
	}

	private function step( $key ) {
		return array( 'step_key' => $key, 'ability_name' => 'vendor/' . $key );
	}

	private function step_keys( $workflow_id ) {
		return array_map(
			function ( $step ) {
				return $step->step_key;
			},
			$this->repository->get_steps( $workflow_id )
		);
	}

	private function uses_savepoint() {
		$property = new ReflectionProperty( $this->repository, 'use_savepoint' );
		$property->setAccessible( true );

		return $property->getValue( $this->repository );
	}

	public function test_a_normal_request_uses_a_real_transaction_not_a_savepoint() {
		global $wpdb;

		$this->assertSame( '1', (string) $wpdb->get_var( 'SELECT @@autocommit' ), 'The test must run with autocommit on.' );

		$workflow_id = $this->make_workflow();

		$this->assertTrue( $this->repository->save_steps( $workflow_id, array( $this->step( 'one' ), $this->step( 'two' ) ) ) );

		$this->assertFalse( $this->uses_savepoint() );
		$this->assertSame( array( 'one', 'two' ), $this->step_keys( $workflow_id ) );
	}

	public function test_a_failed_insert_rolls_the_real_transaction_back() {
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
		$this->assertFalse( $this->uses_savepoint(), 'The real ROLLBACK, not ROLLBACK TO SAVEPOINT, must have run.' );
		$this->assertSame( array( 'keep' ), $this->step_keys( $workflow_id ), 'The first insert of the failed save must not survive.' );
		$this->assertSame( $version, $this->repository->get_workflow( $workflow_id )->version );
		$this->assertSame( '1', (string) $wpdb->get_var( 'SELECT @@autocommit' ) );
	}

	public function test_a_failed_save_leaves_no_transaction_open() {
		global $wpdb;

		$workflow_id = $this->make_workflow();
		$this->repository->save_steps( $workflow_id, array( $this->step( 'keep' ) ) );

		$this->repository->save_steps( $workflow_id, array( $this->step( 'ok' ), array( 'step_key' => 'no_ability' ) ) );

		// Validation fails before anything is opened, and a following save still works.
		$this->assertTrue( $this->repository->save_steps( $workflow_id, array( $this->step( 'later' ) ) ) );
		$this->assertSame( array( 'later' ), $this->step_keys( $workflow_id ) );
		$this->assertSame( '0', (string) $wpdb->get_var( 'SELECT @@in_transaction' ) );
	}
}
