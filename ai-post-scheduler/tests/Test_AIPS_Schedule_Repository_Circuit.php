<?php
/**
 * Tests for AIPS_Schedule_Repository circuit-breaker methods.
 *
 * Covers:
 *   - update_circuit_and_run_state (atomic circuit_state + run_state write)
 *   - reset_circuit (close circuit, clear consecutive_failures / circuit_opened_at)
 *
 * @package AI_Post_Scheduler
 */

class Test_AIPS_Schedule_Repository_Circuit extends WP_UnitTestCase {

	/** @var AIPS_Schedule_Repository */
	private $repository;

	/** @var int */
	private $template_id;

	public function setUp(): void {
		parent::setUp();

		$this->repository = new AIPS_Schedule_Repository();

		global $wpdb;
		$wpdb->insert(
			$wpdb->prefix . 'aips_templates',
			array(
				'name'            => 'Circuit Test Template',
				'prompt_template' => 'Write about {{topic}}',
				'is_active'       => 1,
				'post_quantity'   => 1,
			)
		);
		$this->template_id = (int) $wpdb->insert_id;
	}

	public function tearDown(): void {
		global $wpdb;
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}aips_schedule WHERE template_id = %d", $this->template_id ) );
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}aips_templates WHERE id = %d", $this->template_id ) );
		parent::tearDown();
	}

	private function insert_schedule( $circuit_state = 'closed', $run_state = null ) {
		global $wpdb;
		$wpdb->insert(
			$wpdb->prefix . 'aips_schedule',
			array(
				'template_id'   => $this->template_id,
				'frequency'     => 'daily',
				'next_run'      => 100000,
				'is_active'     => 1,
				'topic'         => 'Circuit Topic',
				'circuit_state' => $circuit_state,
				'run_state'     => $run_state,
			)
		);
		return (int) $wpdb->insert_id;
	}

	public function test_update_circuit_and_run_state_persists_both() {
		$id = $this->insert_schedule( 'closed' );

		$state = array(
			'status'               => 'failed',
			'consecutive_failures' => 5,
			'circuit_opened_at'    => 123456,
		);
		$result = $this->repository->update_circuit_and_run_state( $id, 'open', $state );

		$this->assertTrue( $result );

		$row = $this->repository->get_by_id( $id );
		$this->assertEquals( 'open', $row->circuit_state );

		$decoded = json_decode( $row->run_state, true );
		$this->assertIsArray( $decoded );
		$this->assertEquals( 5, $decoded['consecutive_failures'] );
		$this->assertEquals( 123456, $decoded['circuit_opened_at'] );
		$this->assertEquals( 'failed', $decoded['status'] );
	}

	public function test_reset_circuit_closes_and_clears_bookkeeping() {
		$run_state = wp_json_encode( array(
			'status'               => 'failed',
			'consecutive_failures' => 7,
			'circuit_opened_at'    => 999999,
		) );
		$id = $this->insert_schedule( 'open', $run_state );

		$result = $this->repository->reset_circuit( $id );

		$this->assertTrue( $result );

		$row = $this->repository->get_by_id( $id );
		$this->assertEquals( 'closed', $row->circuit_state );

		$decoded = json_decode( $row->run_state, true );
		$this->assertIsArray( $decoded );
		$this->assertEquals( 0, $decoded['consecutive_failures'] );
		$this->assertEquals( 0, $decoded['circuit_opened_at'] );
		// Unrelated run_state fields are preserved.
		$this->assertEquals( 'failed', $decoded['status'] );
	}

	public function test_reset_circuit_handles_missing_run_state() {
		$id = $this->insert_schedule( 'open', null );

		$result = $this->repository->reset_circuit( $id );

		$this->assertTrue( $result );

		$row = $this->repository->get_by_id( $id );
		$this->assertEquals( 'closed', $row->circuit_state );

		$decoded = json_decode( $row->run_state, true );
		$this->assertIsArray( $decoded );
		$this->assertEquals( 0, $decoded['consecutive_failures'] );
		$this->assertEquals( 0, $decoded['circuit_opened_at'] );
	}
}
