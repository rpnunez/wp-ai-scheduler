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
		$this->assertEqualsCanonicalizing( array( $page ), $this->service->get_scan_post_ids() );
	}

	private function run_ticks( AIPS_Link_Index_Service $service, string $job_id, int $max = 20 ): void {
		for ( $i = 0; $i < $max; $i++ ) {
			$status = $service->get_backfill_status();
			if ( ! $status || $status['status'] !== 'processing' ) {
				return;
			}
			wp_clear_scheduled_hook( AIPS_Link_Index_Service::SCAN_TICK_HOOK, array( $job_id ) );
			$service->process_scan_tick( $job_id );
		}
	}

	public function test_scan_modes_select_posts() {
		$old     = self::factory()->post->create( array( 'post_content' => '<p>old</p>' ) );
		$missing = self::factory()->post->create( array( 'post_content' => '<p>new</p>' ) );
		delete_post_meta( $missing, AIPS_Link_Index_Service::HASH_META_KEY );

		global $wpdb;
		$wpdb->update( $wpdb->posts, array( 'post_modified_gmt' => gmdate( 'Y-m-d H:i:s', time() - 90 * DAY_IN_SECONDS ) ), array( 'ID' => $old ) );
		clean_post_cache( $old );

		$this->assertContains( $missing, $this->service->get_scan_post_ids( AIPS_Link_Index_Service::MODE_MISSING ) );
		$this->assertNotContains( $old, $this->service->get_scan_post_ids( AIPS_Link_Index_Service::MODE_MISSING ) );
		$this->assertNotContains( $old, $this->service->get_scan_post_ids( AIPS_Link_Index_Service::MODE_RECENT, 30 ) );
		$this->assertContains( $old, $this->service->get_scan_post_ids( AIPS_Link_Index_Service::MODE_RECENT, 120 ) );
		$this->assertContains( $old, $this->service->get_scan_post_ids( AIPS_Link_Index_Service::MODE_ALL ) );
	}

	public function test_scan_runs_in_ticks_and_completes() {
		update_option( 'aips_link_index_batch_size', 10 );
		$target = self::factory()->post->create();
		$ids    = self::factory()->post->create_many( 12, array( 'post_content' => $this->content_linking_to( $target ) ) );
		$this->repo->delete_all();

		$result = $this->service->start_backfill( AIPS_Link_Index_Service::MODE_ALL );
		$this->assertSame( 13, $result['total'] );
		$this->assertNotFalse( wp_next_scheduled( AIPS_Link_Index_Service::SCAN_TICK_HOOK, array( $result['job_id'] ) ) );

		wp_clear_scheduled_hook( AIPS_Link_Index_Service::SCAN_TICK_HOOK, array( $result['job_id'] ) );
		$this->service->process_scan_tick( $result['job_id'] );
		$this->assertSame( 10, $this->service->get_backfill_status()['processed'] );
		$this->assertNotFalse( wp_next_scheduled( AIPS_Link_Index_Service::SCAN_TICK_HOOK, array( $result['job_id'] ) ) );

		$this->run_ticks( $this->service, $result['job_id'] );
		$this->assertSame( 'completed', $this->service->get_backfill_status()['status'] );
		$this->assertCount( 12, $this->repo->get_source_ids_linking_to( $target ) );
		delete_option( 'aips_link_index_batch_size' );
	}

	public function test_pause_resume_and_cancel() {
		update_option( 'aips_link_index_batch_size', 10 );
		self::factory()->post->create_many( 25 );

		$job = $this->service->start_backfill( AIPS_Link_Index_Service::MODE_ALL )['job_id'];
		$this->assertWPError( $this->service->start_backfill( AIPS_Link_Index_Service::MODE_ALL ), 'A second scan cannot start while one runs.' );

		$this->assertTrue( $this->service->pause_backfill() );
		$this->assertFalse( wp_next_scheduled( AIPS_Link_Index_Service::SCAN_TICK_HOOK, array( $job ) ) );
		$this->service->process_scan_tick( $job );
		$this->assertSame( 0, $this->service->get_backfill_status()['processed'], 'A paused scan does no work.' );

		$this->assertTrue( $this->service->resume_backfill() );
		wp_clear_scheduled_hook( AIPS_Link_Index_Service::SCAN_TICK_HOOK, array( $job ) );
		$this->service->process_scan_tick( $job );
		$this->assertSame( 10, $this->service->get_backfill_status()['processed'], 'Resume continues from the saved position.' );

		$this->assertTrue( $this->service->cancel_backfill() );
		$this->assertSame( 'cancelled', $this->service->get_backfill_status()['status'] );
		$this->assertFalse( $this->service->resume_backfill() );

		$never_scanned = self::factory()->post->create();
		delete_post_meta( $never_scanned, AIPS_Link_Index_Service::HASH_META_KEY );
		$this->assertSame( 1, $this->service->start_backfill( AIPS_Link_Index_Service::MODE_MISSING )['total'], 'A new scan can start after cancelling.' );
		delete_option( 'aips_link_index_batch_size' );
	}

	public function test_missing_mode_reports_nothing_to_do() {
		self::factory()->post->create();

		$this->assertWPError( $this->service->start_backfill( AIPS_Link_Index_Service::MODE_MISSING ) );
	}

	public function test_scan_tick_hook_is_registered_by_boot_cron() {
		$plugin = AI_Post_Scheduler::get_instance();
		$method = ( new ReflectionClass( $plugin ) )->getMethod( 'boot_cron' );
		$method->setAccessible( true );
		$method->invoke( $plugin );

		$this->assertNotFalse( has_action( AIPS_Link_Index_Service::SCAN_TICK_HOOK ) );
	}
}
