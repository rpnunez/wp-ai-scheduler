<?php
/**
 * Tests for AIPS_Background_Processes_Controller.
 *
 * @package AI_Post_Scheduler
 */

class Test_AIPS_Background_Processes_Controller extends WP_UnitTestCase {

	/** @var AIPS_Background_Processes_Controller */
	private $controller;

	/** @var AIPS_Job_Manager */
	private $job_manager;

	/** @var AIPS_Job_Repository */
	private $job_repo;

	public function setUp(): void {
		parent::setUp();
		AIPS_DB_Manager::install_tables();

		global $wpdb;
		$wpdb->query( 'DELETE FROM ' . $wpdb->prefix . 'aips_jobs' );

		$container          = AIPS_Container::get_instance();
		$this->job_manager  = $container->get_job_manager();
		$this->job_repo     = $container->get_job_repository();
		$this->controller   = new AIPS_Background_Processes_Controller( $this->job_manager, $this->job_repo );
	}

	public function tearDown(): void {
		$this->controller->flush_summary();
		parent::tearDown();
	}

	public function test_snapshots_cover_all_six_processes() {
		$snapshots = $this->controller->get_snapshots();

		$this->assertCount( 6, $snapshots );
		$keys = wp_list_pluck( $snapshots, 'key' );

		$this->assertContains( 'internal_links_indexing', $keys );
		$this->assertContains( 'content_indexer_queue', $keys );
		$this->assertContains( 'link_index_scan', $keys );
		$this->assertContains( 'relationships_recompute', $keys );
		$this->assertContains( 'author_topic_embeddings', $keys );
		$this->assertContains( 'bulk_generation', $keys );
	}

	public function test_filter_active_and_status_labels() {
		$labels = AIPS_Background_Processes_Controller::get_status_labels();
		$this->assertArrayHasKey( 'idle', $labels );
		$this->assertArrayHasKey( 'running', $labels );
		$this->assertArrayHasKey( 'paused', $labels );

		$this->assertTrue( AIPS_Background_Processes_Controller::is_active_status( 'running' ) );
		$this->assertTrue( AIPS_Background_Processes_Controller::is_active_status( 'cooldown' ) );
		$this->assertTrue( AIPS_Background_Processes_Controller::is_active_status( 'waiting_quota' ) );
		$this->assertFalse( AIPS_Background_Processes_Controller::is_active_status( 'idle' ) );
		$this->assertFalse( AIPS_Background_Processes_Controller::is_active_status( 'paused' ) );

		$filtered = AIPS_Background_Processes_Controller::filter_active( array(
			array( 'key' => 'a', 'is_active' => true ),
			array( 'key' => 'b', 'is_active' => false ),
		) );
		$this->assertCount( 1, $filtered );
		$this->assertSame( 'a', $filtered[0]['key'] );
	}

	public function test_heartbeat_payload_structure() {
		$payload = $this->controller->get_heartbeat_payload();

		$this->assertArrayHasKey( 'processes', $payload );
		$this->assertArrayHasKey( 'jobs', $payload );
		$this->assertArrayHasKey( 'active_count', $payload );
		$this->assertArrayHasKey( 'server_time', $payload );
	}

	public function test_control_pause_resume_cancel_flow() {
		$job_id = $this->job_manager->dispatch(
			AIPS_Job_Registry::TYPE_RELATIONSHIPS_RECOMPUTE,
			array( 'mode' => 'missing' ),
			array( 'total' => 5 )
		);

		$snap = $this->controller->get_snapshot( 'relationships_recompute' );
		$this->assertSame( 'running', $snap['status'] );
		$this->assertTrue( $snap['can_pause'] );

		// Pause
		$paused_snap = $this->controller->control( 'relationships_recompute', AIPS_Background_Processes_Controller::ACTION_PAUSE );
		$this->assertIsArray( $paused_snap );
		$this->assertSame( 'paused', $paused_snap['status'] );
		$this->assertTrue( $paused_snap['can_resume'] );

		// Resume
		$resumed_snap = $this->controller->control( 'relationships_recompute', AIPS_Background_Processes_Controller::ACTION_RESUME );
		$this->assertIsArray( $resumed_snap );
		$this->assertSame( 'running', $resumed_snap['status'] );

		// Cancel
		$cancelled_snap = $this->controller->control( 'relationships_recompute', AIPS_Background_Processes_Controller::ACTION_CANCEL );
		$this->assertIsArray( $cancelled_snap );
		$this->assertSame( 'idle', $cancelled_snap['status'] );
	}

	public function test_bulk_generation_snapshot_and_controls() {
		$job_id = $this->job_manager->dispatch_batch(
			'bulk_post_generation',
			array( 1, 2, 3, 4 ),
			array( 'batch_size' => 2 )
		);

		$snap = $this->controller->get_snapshot( 'bulk_generation' );
		$this->assertSame( 'running', $snap['status'] );
		$this->assertSame( 4, $snap['total'] );
		$this->assertTrue( $snap['can_pause'] );

		$this->controller->control( 'bulk_generation', AIPS_Background_Processes_Controller::ACTION_PAUSE );
		$paused_snap = $this->controller->get_snapshot( 'bulk_generation' );
		$this->assertSame( 'paused', $paused_snap['status'] );

		$this->controller->control( 'bulk_generation', AIPS_Background_Processes_Controller::ACTION_CANCEL );
		$idle_snap = $this->controller->get_snapshot( 'bulk_generation' );
		$this->assertSame( 'idle', $idle_snap['status'] );
	}

	public function test_bulk_generation_remains_visible_and_controllable_beyond_100_newer_jobs() {
		$now         = time();
		$bulk_job_id = wp_generate_uuid4();
		$this->job_repo->insert( array(
			'job_id'     => $bulk_job_id,
			'job_type'   => 'bulk_post_generation',
			'group_name' => AIPS_Job_Registry::GROUP_BULK_BATCH,
			'status'     => AIPS_Job_Record::STATUS_RUNNING,
			'total'      => 10,
			'processed'  => 3,
			'created_at' => $now - 500,
			'updated_at' => $now - 500,
		) );

		// Insert 105 newer completed jobs to push the bulk job past a naive LIMIT 100 query.
		for ( $i = 0; $i < 105; $i++ ) {
			$this->job_repo->insert( array(
				'job_id'     => wp_generate_uuid4(),
				'job_type'   => AIPS_Job_Registry::TYPE_SCHEDULE_POST_GENERATION,
				'group_name' => AIPS_Job_Registry::GROUP_SCHEDULES,
				'status'     => AIPS_Job_Record::STATUS_COMPLETED,
				'total'      => 1,
				'processed'  => 1,
				'created_at' => $now - 400 + $i,
				'updated_at' => $now - 400 + $i,
			) );
		}

		$snap = $this->controller->get_snapshot( 'bulk_generation' );
		$this->assertSame( 'running', $snap['status'], 'Open bulk job must remain visible past 100 completed rows' );
		$this->assertSame( 10, $snap['total'] );

		$paused = $this->controller->control( 'bulk_generation', AIPS_Background_Processes_Controller::ACTION_PAUSE );
		$this->assertSame( 'paused', $paused['status'] );

		$resumed = $this->controller->control( 'bulk_generation', AIPS_Background_Processes_Controller::ACTION_RESUME );
		$this->assertSame( 'running', $resumed['status'] );

		$cancelled = $this->controller->control( 'bulk_generation', AIPS_Background_Processes_Controller::ACTION_CANCEL );
		$this->assertSame( 'idle', $cancelled['status'] );
	}

	public function test_heartbeat_active_count_not_double_counted_and_recent_runs_zeroes_non_ai() {
		$this->job_manager->dispatch(
			AIPS_Job_Registry::TYPE_AUTHOR_TOPIC_EMBEDDINGS,
			array( 'author_id' => 0 ),
			array( 'total' => 5 )
		);

		$payload = $this->controller->get_heartbeat_payload();
		$this->assertSame( 1, $payload['active_count'], 'Single active unified job must not be double-counted across snapshots and jobs' );

		$cpu_job_id = wp_generate_uuid4();
		$this->job_repo->insert( array(
			'job_id'      => $cpu_job_id,
			'job_type'    => AIPS_Job_Registry::TYPE_RELATIONSHIPS_RECOMPUTE,
			'group_name'  => AIPS_Job_Registry::GROUP_INDEXER,
			'status'      => AIPS_Job_Record::STATUS_COMPLETED,
			'total'       => 8,
			'processed'   => 8,
			'finished_at' => time(),
			'created_at'  => time() + 10,
			'updated_at'  => time() + 10,
		) );

		$runs = $this->controller->get_recent_runs( 5 );
		$this->assertNotEmpty( $runs );
		$this->assertSame( $cpu_job_id, $runs[0]->id );
		$this->assertSame( 0, $runs[0]->ai_calls_used, 'CPU-only relationships_recompute job must report 0 AI calls used' );

		$estimate = $this->controller->get_estimate( AIPS_Relationships_Recompute_Service::KEY, array( 'mode' => 'missing' ) );
		$this->assertSame( 0, $estimate['ai_calls'] );
		$this->assertArrayHasKey( 'message', $estimate );
	}

	public function test_relationships_recompute_slice_streams_vectors_once_per_batch() {
		$builder = $this->getMockBuilder( AIPS_Relationship_Builder::class )
			->disableOriginalConstructor()
			->onlyMethods( array( 'get_batch_size_for_budget', 'get_post_types', 'compute_for_posts' ) )
			->getMock();
		$builder->method( 'get_batch_size_for_budget' )->willReturn( 25 );
		$builder->method( 'get_post_types' )->willReturn( array( 'post' ) );
		$builder->expects( $this->once() )
			->method( 'compute_for_posts' )
			->with( array( 10, 20, 30 ) )
			->willReturn( array(
				10 => 5,
				20 => 0,
				30 => 2,
			) );

		$rel_repo = $this->getMockBuilder( AIPS_Relationships_Repository::class )
			->disableOriginalConstructor()
			->onlyMethods( array( 'get_source_post_ids' ) )
			->getMock();
		$rel_repo->method( 'get_source_post_ids' )->willReturn( array( 10, 20, 30 ) );

		$service = new AIPS_Relationships_Recompute_Service( $builder, $rel_repo );
		$record  = new AIPS_Job_Record( array(
			'job_id'    => 'rel-batch-1',
			'job_type'  => AIPS_Job_Registry::TYPE_RELATIONSHIPS_RECOMPUTE,
			'cursor_id' => 0,
			'payload'   => array( 'mode' => 'all' ),
		) );
		$result  = $service->handle_job_slice( new AIPS_Job_Context( $record ) );

		$this->assertSame( 3, $result->get_processed_count() );
		$this->assertSame( 0, $result->get_failed_count() );
		$this->assertSame( 30, $result->get_next_cursor() );
		$this->assertTrue( $result->is_complete() );
	}
}
