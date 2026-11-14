<?php
/**
 * Tests for AIPS_Job_Repository and AIPS_Job_Record (issue #2376 Slice 1).
 *
 * @package AI_Post_Scheduler
 */

class Test_AIPS_Job_Repository extends WP_UnitTestCase {

	/** @var AIPS_Job_Repository */
	private $repository;

	public function setUp(): void {
		parent::setUp();
		AIPS_DB_Manager::install_tables();
		$this->repository = new AIPS_Job_Repository();
		$this->clear_jobs_table();
	}

	public function tearDown(): void {
		$this->clear_jobs_table();
		parent::tearDown();
	}

	private function clear_jobs_table() {
		global $wpdb;
		$wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}aips_jobs" );
	}

	public function test_insert_and_find_job_record() {
		$job_id  = wp_generate_uuid4();
		$payload = array( 'items' => array( 1, 2, 3 ), 'scope' => 'test' );

		$inserted = $this->repository->insert(
			array(
				'job_id'       => $job_id,
				'job_type'     => 'test_slice',
				'group_name'   => 'aips-test-group',
				'status'       => AIPS_Job_Record::STATUS_PENDING,
				'total'        => 3,
				'processed'    => 0,
				'payload_json' => wp_json_encode( $payload ),
			)
		);

		$this->assertTrue( $inserted );

		$record = $this->repository->find( $job_id );
		$this->assertInstanceOf( AIPS_Job_Record::class, $record );
		$this->assertSame( $job_id, $record->get_job_id() );
		$this->assertSame( 'test_slice', $record->get_job_type() );
		$this->assertSame( 'aips-test-group', $record->get_group_name() );
		$this->assertSame( AIPS_Job_Record::STATUS_PENDING, $record->get_status() );
		$this->assertTrue( $record->is_active() );
		$this->assertSame( 3, $record->get_total() );
		$this->assertSame( 0, $record->get_processed() );
		$this->assertSame( $payload, $record->get_payload() );
		$this->assertSame( 0.0, $record->get_progress_percentage() );
	}

	public function test_update_and_increment_progress() {
		$job_id = wp_generate_uuid4();
		$this->repository->insert(
			array(
				'job_id'   => $job_id,
				'job_type' => 'test_slice',
				'total'    => 10,
			)
		);

		// Increment progress
		$updated = $this->repository->increment_progress( $job_id, 4, 1, 100 );
		$this->assertTrue( $updated );

		$record = $this->repository->find( $job_id );
		$this->assertSame( 4, $record->get_processed() );
		$this->assertSame( 1, $record->get_failed() );
		$this->assertSame( 100, $record->get_cursor_id() );
		$this->assertSame( 50.0, $record->get_progress_percentage() );

		// Update status and result
		$result_data = array( 'completed_items' => 4 );
		$this->repository->update(
			$job_id,
			array(
				'status' => AIPS_Job_Record::STATUS_COMPLETED,
				'result' => $result_data,
			)
		);

		$record = $this->repository->find( $job_id );
		$this->assertTrue( $record->is_completed() );
		$this->assertSame( $result_data, $record->get_result() );
	}

	public function test_query_filtering_and_active_jobs() {
		$id1 = wp_generate_uuid4();
		$id2 = wp_generate_uuid4();
		$id3 = wp_generate_uuid4();

		$this->repository->insert(
			array(
				'job_id'     => $id1,
				'job_type'   => 'type_a',
				'status'     => AIPS_Job_Record::STATUS_PENDING,
				'group_name' => 'group_1',
			)
		);
		$this->repository->insert(
			array(
				'job_id'     => $id2,
				'job_type'   => 'type_a',
				'status'     => AIPS_Job_Record::STATUS_RUNNING,
				'group_name' => 'group_1',
			)
		);
		$this->repository->insert(
			array(
				'job_id'     => $id3,
				'job_type'   => 'type_b',
				'status'     => AIPS_Job_Record::STATUS_COMPLETED,
				'group_name' => 'group_2',
			)
		);

		// Count
		$this->assertSame( 3, $this->repository->count() );
		$this->assertSame( 2, $this->repository->count( array( 'job_type' => 'type_a' ) ) );
		$this->assertSame( 1, $this->repository->count( array( 'status' => AIPS_Job_Record::STATUS_COMPLETED ) ) );

		// Active jobs
		$active = $this->repository->get_active_jobs();
		$this->assertCount( 2, $active );

		$active_type_a = $this->repository->get_active_jobs( 'type_a' );
		$this->assertCount( 2, $active_type_a );

		$active_type_b = $this->repository->get_active_jobs( 'type_b' );
		$this->assertCount( 0, $active_type_b );

		// Query pagination
		$queried = $this->repository->query( array( 'group_name' => 'group_1' ), 10, 0 );
		$this->assertCount( 2, $queried );
	}

	public function test_prune_finished_jobs() {
		$id_active    = wp_generate_uuid4();
		$id_finished1 = wp_generate_uuid4();
		$id_finished2 = wp_generate_uuid4();

		$old_time = time() - 1000;
		$now      = time();

		$this->repository->insert(
			array(
				'job_id'     => $id_active,
				'status'     => AIPS_Job_Record::STATUS_RUNNING,
				'updated_at' => $old_time,
			)
		);
		$this->repository->insert(
			array(
				'job_id'     => $id_finished1,
				'status'     => AIPS_Job_Record::STATUS_COMPLETED,
				'updated_at' => $old_time,
			)
		);
		$this->repository->insert(
			array(
				'job_id'     => $id_finished2,
				'status'     => AIPS_Job_Record::STATUS_FAILED,
				'updated_at' => $now,
			)
		);

		// Prune jobs older than time() - 500
		$deleted = $this->repository->prune_finished( time() - 500 );
		$this->assertSame( 1, $deleted );

		$this->assertNotNull( $this->repository->find( $id_active ) );
		$this->assertNull( $this->repository->find( $id_finished1 ) );
		$this->assertNotNull( $this->repository->find( $id_finished2 ) );
	}
}
