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
		$context = new AIPS_Job_Context( $record, 10, 30.0 );

		$result = $service->handle_job_slice( $context );
		$this->assertInstanceOf( AIPS_Job_Slice_Result::class, $result );
	}
}
