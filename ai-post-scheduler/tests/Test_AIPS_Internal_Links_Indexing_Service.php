<?php
/**
 * Tests for AIPS_Internal_Links_Indexing_Service.
 *
 * @package AI_Post_Scheduler
 */

class Test_AIPS_Internal_Links_Indexing_Service extends WP_UnitTestCase {

	public function setUp(): void {
		parent::setUp();
		AIPS_DB_Manager::install_tables();

		global $wpdb;
		$wpdb->query( 'DELETE FROM ' . $wpdb->prefix . 'aips_jobs' );
	}

	public function test_metadata_and_capabilities() {
		$service = new AIPS_Internal_Links_Indexing_Service();

		$this->assertSame( 'internal_links_indexing', $service->get_key() );
		$this->assertTrue( $service->uses_ai() );
		$this->assertIsInt( $service->count_remaining() );
	}

	public function test_handle_job_slice_empty_or_delegated() {
		$service = new AIPS_Internal_Links_Indexing_Service();
		$record  = new AIPS_Job_Record( array(
			'job_id'   => 'job-index-1',
			'job_type' => AIPS_Job_Registry::TYPE_INTERNAL_LINKS_INDEXING,
		) );
		$context = new AIPS_Job_Context( $record );

		$result = $service->handle_job_slice( $context );
		$this->assertInstanceOf( AIPS_Job_Slice_Result::class, $result );
	}

	public function test_mid_slice_rate_limit_yields_instead_of_completing_prematurely() {
		$links_service = $this->getMockBuilder( AIPS_Internal_Links_Service::class )
			->disableOriginalConstructor()
			->onlyMethods( array( 'process_indexing_batch', 'get_indexing_status' ) )
			->getMock();

		$links_service->method( 'process_indexing_batch' )->willReturn( array(
			'success'      => 0,
			'failed'       => 1,
			'last_post_id' => 10,
			'done'         => true,
		) );
		$links_service->method( 'get_indexing_status' )->willReturn( array(
			'unindexed' => 4,
		) );

		$embeddings_service = $this->getMockBuilder( AIPS_Embeddings_Service::class )
			->disableOriginalConstructor()
			->onlyMethods( array( 'is_enabled', 'is_embeddings_supported', 'get_rate_limiter' ) )
			->getMock();
		$embeddings_service->method( 'is_enabled' )->willReturn( true );
		$embeddings_service->method( 'is_embeddings_supported' )->willReturn( true );
		$embeddings_service->method( 'get_rate_limiter' )->willReturn( new AIPS_Embeddings_Rate_Limiter() );

		$service = new AIPS_Internal_Links_Indexing_Service( $links_service, $embeddings_service );
		$record  = new AIPS_Job_Record( array(
			'job_id'    => 'job-index-ratelimit',
			'job_type'  => AIPS_Job_Registry::TYPE_INTERNAL_LINKS_INDEXING,
			'cursor_id' => 10,
		) );
		$context = new AIPS_Job_Context( $record );

		$result = $service->handle_job_slice( $context );
		$this->assertFalse( $result->is_complete(), 'Mid-slice rate limit on <batch_size slice must not mark job completed' );
		$this->assertSame( 0, $result->get_failed_count(), 'Stalled cursor item must not increment permanent failure count' );
		$this->assertSame( 10, $result->get_next_cursor() );
		$this->assertGreaterThanOrEqual( 60, $result->get_retry_delay() );
	}

	public function test_ai_budget_caps_batch_limit_and_pauses_job() {
		$captured_limit = null;
		$links_service  = $this->getMockBuilder( AIPS_Internal_Links_Service::class )
			->disableOriginalConstructor()
			->onlyMethods( array( 'process_indexing_batch', 'get_indexing_status' ) )
			->getMock();

		$links_service->expects( $this->once() )
			->method( 'process_indexing_batch' )
			->willReturnCallback( function ( $limit, $cursor ) use ( &$captured_limit ) {
				$captured_limit = $limit;
				return array(
					'success'      => 2,
					'failed'       => 0,
					'last_post_id' => 25,
					'done'         => false,
				);
			} );
		$links_service->method( 'get_indexing_status' )->willReturn( array( 'unindexed' => 5 ) );

		$embeddings_service = $this->getMockBuilder( AIPS_Embeddings_Service::class )
			->disableOriginalConstructor()
			->onlyMethods( array( 'is_enabled', 'is_embeddings_supported', 'get_rate_limiter' ) )
			->getMock();
		$embeddings_service->method( 'is_enabled' )->willReturn( true );
		$embeddings_service->method( 'is_embeddings_supported' )->willReturn( true );
		$embeddings_service->method( 'get_rate_limiter' )->willReturn( new AIPS_Embeddings_Rate_Limiter() );

		$job_manager = AIPS_Container::get_instance()->get_job_manager();
		$job_id      = $job_manager->dispatch(
			AIPS_Job_Registry::TYPE_INTERNAL_LINKS_INDEXING,
			array( 'ai_budget' => 3 ),
			array( 'total' => 7 )
		);
		AIPS_Container::get_instance()->get_job_repository()->update( $job_id, array( 'processed' => 1 ) );

		$service = new AIPS_Internal_Links_Indexing_Service( $links_service, $embeddings_service );
		$record  = $job_manager->get_job( $job_id );
		$result  = $service->handle_job_slice( new AIPS_Job_Context( $record ) );

		$this->assertSame( 2, $captured_limit, 'Remaining budget (3 - 1 = 2) must cap batch limit' );
		$this->assertSame( 2, $result->get_processed_count() );
		$this->assertFalse( $result->is_complete() );
		$this->assertTrue( $job_manager->get_job( $job_id )->is_paused() );
	}
}
