<?php
/**
 * Tests for AIPS_Link_Index_Service and its save/delete hooks.
 *
 * @package AI_Post_Scheduler
 */

class Test_AIPS_Link_Index_Service extends WP_UnitTestCase {

	/** @var AIPS_Link_Index_Service */
	private $service;

	/** @var AIPS_Link_Index_Repository */
	private $repo;

	public function setUp(): void {
		parent::setUp();
		$this->set_permalink_structure( '/%postname%/' );
		wp_cache_flush();

		$this->repo    = new AIPS_Link_Index_Repository();
		$this->service = new AIPS_Link_Index_Service( $this->repo );
		$this->repo->delete_all();
	}

	public function tearDown(): void {
		$this->repo->delete_all();
		delete_option( 'aips_link_index_enabled' );
		delete_option( 'aips_link_index_post_types' );
		delete_option( AIPS_Link_Index_Service::BACKFILL_JOB_OPTION );
		parent::tearDown();
	}

	private function content_linking_to( int ...$ids ): string {
		$html = '<!-- wp:paragraph --><p>Intro.';
		foreach ( $ids as $id ) {
			$html .= ' <a href="' . esc_url( get_permalink( $id ) ) . '">Post ' . $id . '</a>';
		}
		return $html . ' <a href="https://wordpress.org/" rel="nofollow">WP</a></p><!-- /wp:paragraph -->';
	}

	public function test_index_post_resolves_internal_and_external_links() {
		$target = self::factory()->post->create( array( 'post_name' => 'target-post' ) );
		$source = self::factory()->post->create( array( 'post_content' => $this->content_linking_to( $target ) . '<a href="/missing-page/">Gone</a>' ) );

		$result = $this->service->index_post( $source, true );

		$this->assertSame( array( 'status' => 'indexed', 'links' => 3 ), $result );

		$counts = $this->repo->get_counts_for_posts( array( $source, $target ) );
		$this->assertSame( 1, $counts[ $source ]['outbound'] );
		$this->assertSame( 1, $counts[ $source ]['external'] );
		$this->assertSame( 1, $counts[ $source ]['broken'] );
		$this->assertSame( 1, $counts[ $target ]['inbound'] );

		$external = $this->repo->get_outbound( $source, 'external' );
		$this->assertSame( '1', (string) $external[0]->is_nofollow );
	}

	public function test_unchanged_content_is_skipped_unless_forced() {
		$source = self::factory()->post->create( array( 'post_content' => '<p>No links.</p>' ) );
		$this->service->index_post( $source, true );

		$this->assertSame( 'unchanged', $this->service->index_post( $source )['status'] );
		$this->assertSame( 'indexed', $this->service->index_post( $source, true )['status'] );
	}

	public function test_save_post_hook_keeps_index_in_sync() {
		$target = self::factory()->post->create();
		$source = self::factory()->post->create( array( 'post_content' => $this->content_linking_to( $target ) ) );

		// The plugin's own save_post hook indexed the new post.
		$this->assertTrue( $this->repo->source_links_to( $source, $target ) );

		wp_update_post( array( 'ID' => $source, 'post_content' => '<p>Links removed.</p>' ) );
		$this->assertFalse( $this->repo->source_links_to( $source, $target ) );
	}

	public function test_unpublishing_or_trashing_removes_rows() {
		$target = self::factory()->post->create();
		$source = self::factory()->post->create( array( 'post_content' => $this->content_linking_to( $target ) ) );

		wp_update_post( array( 'ID' => $source, 'post_status' => 'draft' ) );
		$this->assertSame( array(), $this->repo->get_outbound( $source ) );

		wp_update_post( array( 'ID' => $source, 'post_status' => 'publish' ) );
		$this->assertTrue( $this->repo->source_links_to( $source, $target ) );

		wp_trash_post( $source );
		$this->assertSame( array(), $this->repo->get_outbound( $source ) );
	}

	public function test_deleting_target_turns_links_into_broken_links() {
		$target = self::factory()->post->create();
		$source = self::factory()->post->create( array( 'post_content' => $this->content_linking_to( $target ) ) );

		wp_delete_post( $target, true );

		$counts = $this->repo->get_counts_for_posts( array( $source ) );
		$this->assertSame( 0, $counts[ $source ]['outbound'] );
		$this->assertSame( 1, $counts[ $source ]['broken'] );
	}

	public function test_disabled_setting_stops_hook_indexing() {
		update_option( 'aips_link_index_enabled', 0 );

		$target = self::factory()->post->create();
		$source = self::factory()->post->create( array( 'post_content' => $this->content_linking_to( $target ) ) );

		$this->assertSame( array(), $this->repo->get_outbound( $source ) );
	}

	public function test_post_type_scope() {
		update_option( 'aips_link_index_post_types', array( 'page', 'not_a_real_type' ) );

		$this->assertSame( array( 'page' ), $this->service->get_post_types() );

		$target = self::factory()->post->create();
		$post   = self::factory()->post->create( array( 'post_content' => $this->content_linking_to( $target ) ) );
		$page   = self::factory()->post->create( array( 'post_type' => 'page', 'post_content' => $this->content_linking_to( $target ) ) );

		$this->assertSame( 'removed', $this->service->index_post( $post, true )['status'] );
		$this->assertSame( 'indexed', $this->service->index_post( $page, true )['status'] );
		$this->assertEqualsCanonicalizing( array( $page ), $this->service->get_backfill_post_ids() );
	}

	public function test_backfill_item_returns_post_id_and_errors_only_for_missing_posts() {
		$source = self::factory()->post->create( array( 'post_content' => '<p>Text</p>' ) );
		$draft  = self::factory()->post->create( array( 'post_status' => 'draft' ) );

		$this->assertSame( $source, $this->service->process_backfill_item( $source ) );
		$this->assertSame( $draft, $this->service->process_backfill_item( (string) $draft ) );
		$this->assertWPError( $this->service->process_backfill_item( 999999 ) );
	}

	public function test_backfill_strategy_is_registered_by_boot_cron() {
		$plugin = AI_Post_Scheduler::get_instance();
		$method = ( new ReflectionClass( $plugin ) )->getMethod( 'boot_cron' );
		$method->setAccessible( true );
		$method->invoke( $plugin );

		$this->assertTrue( AIPS_Bulk_Batch_Processor::instance()->has_strategy( AIPS_Link_Index_Service::BACKFILL_JOB_TYPE ) );
	}

	public function test_start_backfill_creates_and_dispatches_job() {
		$ids = self::factory()->post->create_many( 3 );

		$job_store = $this->getMockBuilder( AIPS_Bulk_Batch_Job_Store::class )
			->onlyMethods( array( 'create', 'get', 'mark_failed' ) )
			->getMock();
		$job_store->expects( $this->once() )
			->method( 'create' )
			->with( AIPS_Link_Index_Service::BACKFILL_JOB_TYPE, $this->callback( function ( $items ) use ( $ids ) {
				return count( array_intersect( $ids, $items ) ) === 3;
			} ) )
			->willReturn( 'job-123' );
		$job_store->method( 'get' )->willReturn( (object) array( 'status' => 'processing', 'processed' => 1, 'total' => 3 ) );

		$dispatcher = $this->getMockBuilder( AIPS_Batch_Queue_Service::class )
			->disableOriginalConstructor()
			->onlyMethods( array( 'dispatch_generic' ) )
			->getMock();
		$dispatcher->expects( $this->once() )
			->method( 'dispatch_generic' )
			->with( AIPS_Bulk_Batch_Processor::HOOK, $this->greaterThanOrEqual( 3 ), $this->anything(), array( 'job-123' ) )
			->willReturn( array( 'batches' => 1 ) );

		$service = new AIPS_Link_Index_Service( $this->repo, null, null, null, $job_store, $dispatcher );
		$result  = $service->start_backfill();

		$this->assertSame( 'job-123', $result['job_id'] );
		$this->assertSame(
			array( 'job_id' => 'job-123', 'status' => 'processing', 'processed' => 1, 'total' => 3 ),
			$service->get_backfill_status()
		);
	}

	public function test_start_backfill_marks_job_failed_when_dispatch_fails() {
		self::factory()->post->create();

		$job_store = $this->getMockBuilder( AIPS_Bulk_Batch_Job_Store::class )
			->onlyMethods( array( 'create', 'mark_failed' ) )
			->getMock();
		$job_store->method( 'create' )->willReturn( 'job-9' );
		$job_store->expects( $this->once() )->method( 'mark_failed' )->with( 'job-9' );

		$dispatcher = $this->getMockBuilder( AIPS_Batch_Queue_Service::class )
			->disableOriginalConstructor()
			->onlyMethods( array( 'dispatch_generic' ) )
			->getMock();
		$dispatcher->method( 'dispatch_generic' )->willReturn( new WP_Error( 'boom', 'failed' ) );

		$service = new AIPS_Link_Index_Service( $this->repo, null, null, null, $job_store, $dispatcher );

		$this->assertWPError( $service->start_backfill() );
		$this->assertNull( $service->get_backfill_status() );
	}
}
