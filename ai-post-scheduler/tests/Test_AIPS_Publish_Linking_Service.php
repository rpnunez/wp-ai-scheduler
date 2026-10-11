<?php
/**
 * Tests for the non-blocking outbound step of AIPS_Publish_Linking_Service.
 *
 * @package AI_Post_Scheduler
 */

class Test_AIPS_Publish_Linking_Service extends WP_UnitTestCase {

	public function setUp(): void {
		parent::setUp();
		AIPS_DB_Manager::install_tables();

		$config = AIPS_Config::get_instance();
		$config->set_option( 'aips_publish_linking_mode', 'review' );
		$config->set_option( 'aips_publish_linking_outbound', true );
		$config->set_option( 'aips_publish_linking_embed_inline', false );

		add_filter( 'aips_publish_linking_qualifies', '__return_true' );
	}

	public function tearDown(): void {
		global $wpdb;
		remove_filter( 'aips_publish_linking_qualifies', '__return_true' );
		$wpdb->query( 'DELETE FROM ' . $wpdb->prefix . 'aips_embeddings' );
		delete_option( 'aips_pending_index_queue' );
		wp_clear_scheduled_hook( AIPS_Publish_Linking_Service::CRON_HOOK );
		wp_clear_scheduled_hook( 'aips_process_pending_indexer_queue' );
		parent::tearDown();
	}

	private function make_published_post(): int {
		$post_id = wp_insert_post( array( 'post_title' => 'Fresh', 'post_content' => 'Body', 'post_status' => 'publish', 'post_type' => 'post' ) );
		update_post_meta( $post_id, AIPS_Post_Manager::META_GENERATED_POST, 1 );
		return $post_id;
	}

	public function test_unindexed_post_is_queued_and_deferred_instead_of_embedded_inline() {
		$post_id = $this->make_published_post();

		$result = ( new AIPS_Publish_Linking_Service() )->process( $post_id );

		$this->assertWPError( $result );
		$this->assertSame( 'aips_publish_linking_deferred', $result->get_error_code() );
		$this->assertEmpty( get_post_meta( $post_id, AIPS_Publish_Linking_Service::DONE_META, true ), 'A deferred pass is not marked done.' );
		$this->assertSame( 1, (int) get_post_meta( $post_id, AIPS_Publish_Linking_Service::DEFER_META, true ) );
		$this->assertContains( $post_id, (array) get_option( 'aips_pending_index_queue', array() ) );
		$this->assertNotFalse( wp_next_scheduled( AIPS_Publish_Linking_Service::CRON_HOOK, array( $post_id ) ) );
	}

	public function test_deferral_stops_after_the_limit_and_the_pass_runs_without_embedding() {
		$post_id = $this->make_published_post();
		update_post_meta( $post_id, AIPS_Publish_Linking_Service::DEFER_META, AIPS_Publish_Linking_Service::MAX_DEFERRALS );

		$result = ( new AIPS_Publish_Linking_Service() )->process( $post_id );

		$this->assertNotWPError( $result, 'After the deferral limit the pass runs (inbound only).' );
		$this->assertNotEmpty( get_post_meta( $post_id, AIPS_Publish_Linking_Service::DONE_META, true ) );
		$this->assertSame( 0, $result['outbound'] );
	}

	public function test_generate_suggestions_without_embedding_makes_no_provider_call() {
		$post_id = $this->make_published_post();

		$indexer = $this->createMock( AIPS_Content_Indexer_Service::class );
		$indexer->expects( $this->never() )->method( 'ensure_indexed' );

		$service = new AIPS_Internal_Links_Service( null, null, null, null, null, null, $indexer );
		$result  = $service->generate_suggestions_for_post( $post_id, null, null, false );

		$this->assertWPError( $result );
		$this->assertSame( 'not_indexed', $result->get_error_code() );
	}
}
