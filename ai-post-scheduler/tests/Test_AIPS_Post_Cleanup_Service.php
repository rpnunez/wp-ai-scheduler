<?php
/**
 * Tests for AIPS_Post_Cleanup_Service.
 *
 * @package AI_Post_Scheduler
 */

class Test_AIPS_Post_Cleanup_Service extends WP_UnitTestCase {

	/** @var AIPS_Post_Cleanup_Service */
	private $service;

	/** @var AIPS_Embeddings_Repository */
	private $embeddings;

	/** @var AIPS_Relationships_Repository */
	private $relationships;

	/** @var AIPS_Internal_Links_Repository */
	private $internal_links;

	/** @var AIPS_History_Repository */
	private $history;

	/** @var AIPS_Redirects_Repository */
	private $redirects;

	/** @var AIPS_Author_Topic_Logs_Repository */
	private $topic_logs;

	public function setUp(): void {
		parent::setUp();
		AIPS_DB_Manager::install_tables();

		$this->embeddings     = new AIPS_Embeddings_Repository();
		$this->relationships  = new AIPS_Relationships_Repository();
		$this->internal_links = new AIPS_Internal_Links_Repository();
		$this->history        = new AIPS_History_Repository();
		$this->redirects      = new AIPS_Redirects_Repository();
		$this->topic_logs     = new AIPS_Author_Topic_Logs_Repository();

		$this->service = new AIPS_Post_Cleanup_Service(
			$this->embeddings,
			$this->relationships,
			$this->internal_links,
			AIPS_Container::get_instance()->makeIfExists( AIPS_Link_Index_Service::class ),
			AIPS_Container::get_instance()->makeIfExists( AIPS_Link_Click_Tracking_Service::class ),
			$this->history,
			new AIPS_Redirects_Service( $this->redirects ),
			$this->topic_logs
		);
	}

	public function tearDown(): void {
		global $wpdb;
		$wpdb->query( "DELETE FROM {$wpdb->prefix}aips_embeddings" );
		$wpdb->query( "DELETE FROM {$wpdb->prefix}aips_relationships" );
		$wpdb->query( "DELETE FROM {$wpdb->prefix}aips_internal_links" );
		$wpdb->query( "DELETE FROM {$wpdb->prefix}aips_history_log" );
		$wpdb->query( "DELETE FROM {$wpdb->prefix}aips_history" );
		$wpdb->query( "DELETE FROM {$wpdb->prefix}aips_redirects" );
		$wpdb->query( "DELETE FROM {$wpdb->prefix}aips_author_topic_logs" );
		parent::tearDown();
	}

	public function test_post_cleanup_purges_embeddings_and_relationships() {
		$post_id = self::factory()->post->create( array( 'post_title' => 'Embedding Target' ) );
		$other_id = self::factory()->post->create( array( 'post_title' => 'Other Post' ) );

		$vector = array_fill( 0, 8, 0.123 );
		$this->embeddings->upsert( 'post', $post_id, $vector, 'test-model', 8, md5( 'test' ), 'post' );
		$this->embeddings->upsert( 'post', $other_id, $vector, 'test-model', 8, md5( 'other' ), 'post' );

		$this->relationships->upsert( array(
			'source_type'   => 'post',
			'source_id'     => $post_id,
			'target_type'   => 'post',
			'target_id'     => $other_id,
			'similarity'    => 0.95,
			'relation_type' => 'related_post',
		) );

		$this->assertNotNull( $this->embeddings->get( 'post', $post_id ) );
		$this->assertNotEmpty( $this->relationships->get_for_source( 'post', $post_id ) );

		$summary = $this->service->on_before_delete_post( $post_id );

		$this->assertGreaterThan( 0, $summary['embeddings'] );
		$this->assertGreaterThan( 0, $summary['relationships'] );
		$this->assertNull( $this->embeddings->get( 'post', $post_id ) );
		$this->assertEmpty( $this->relationships->get_for_source( 'post', $post_id ) );
		$this->assertNotNull( $this->embeddings->get( 'post', $other_id ) );
	}

	public function test_post_cleanup_purges_internal_links() {
		$source_id = self::factory()->post->create( array( 'post_title' => 'Source' ) );
		$target_id = self::factory()->post->create( array( 'post_title' => 'Target' ) );

		$this->internal_links->insert( array(
			'source_post_id' => $source_id,
			'target_post_id' => $target_id,
			'anchor_text'    => 'Click here',
			'status'         => 'pending',
		) );

		$summary = $this->service->on_before_delete_post( $source_id );

		$this->assertSame( 1, $summary['links'] );
		$this->assertEmpty( $this->internal_links->get_by_source_post( $source_id ) );
	}

	public function test_post_cleanup_purges_history_containers_and_logs() {
		global $wpdb;
		$post_id = self::factory()->post->create( array( 'post_title' => 'Post with History' ) );

		$history_id = $this->history->create( array(
			'post_id'         => $post_id,
			'post_type'       => 'post',
			'status'          => 'completed',
			'generated_title' => 'Post with History',
		) );

		$this->history->add_log_entry( $history_id, 'Generation initiated' );
		$this->history->add_log_entry( $history_id, 'Post completed' );

		$logs_before = $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$wpdb->prefix}aips_history_log WHERE history_id = %d",
			$history_id
		) );
		$this->assertSame( 2, (int) $logs_before );

		$summary = $this->service->on_before_delete_post( $post_id );

		$this->assertSame( 1, $summary['history'] );
		$this->assertNull( $this->history->get_by_id( $history_id ) );

		$logs_after = $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$wpdb->prefix}aips_history_log WHERE history_id = %d",
			$history_id
		) );
		$this->assertSame( 0, (int) $logs_after );
	}

	public function test_post_cleanup_disables_pointing_redirects_and_deletes_origin_redirects() {
		$target_post = self::factory()->post->create( array( 'post_title' => 'Target Page' ) );
		$origin_post = self::factory()->post->create( array( 'post_title' => 'Consolidated Page' ) );

		$pointing_id = $this->redirects->insert( array(
			'source_path'    => '/old-path-pointing/',
			'source_hash'    => md5( '/old-path-pointing/' ),
			'target_url'     => get_permalink( $target_post ),
			'target_post_id' => $target_post,
			'enabled'        => 1,
		) );

		$origin_id = $this->redirects->insert( array(
			'source_path'    => '/consolidated-origin/',
			'source_hash'    => md5( '/consolidated-origin/' ),
			'target_url'     => 'https://example.com/winner',
			'origin'         => 'consolidation',
			'origin_ref'     => $origin_post,
			'enabled'        => 1,
		) );

		// Clean target_post
		$summary_target = $this->service->on_before_delete_post( $target_post );
		$this->assertSame( 1, $summary_target['redirects']['disabled'] );
		$pointing_row = $this->redirects->get( $pointing_id );
		$this->assertSame( 0, (int) $pointing_row->enabled );
		$this->assertSame( 0, (int) $pointing_row->target_post_id );

		// Clean origin_post
		$summary_origin = $this->service->on_before_delete_post( $origin_post );
		$this->assertSame( 1, $summary_origin['redirects']['deleted'] );
		$this->assertNull( $this->redirects->get( $origin_id ) );
	}

	public function test_post_cleanup_disassociates_author_topic_logs() {
		global $wpdb;
		$post_id = self::factory()->post->create( array( 'post_title' => 'Topic Generated Post' ) );

		$log_id = $this->topic_logs->log_post_generation( 101, $post_id, '{"author_id":1}' );
		$this->assertNotEmpty( $log_id );

		$summary = $this->service->on_before_delete_post( $post_id );

		$this->assertSame( 1, $summary['topic_logs'] );
		$row = $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM {$wpdb->prefix}aips_author_topic_logs WHERE id = %d",
			$log_id
		) );
		$this->assertNull( $row->post_id );
		$this->assertSame( 'post_generated', $row->action );
	}

	public function test_revisions_and_autosaves_are_skipped() {
		$post_id = self::factory()->post->create( array( 'post_title' => 'Original Post' ) );
		$rev_id  = wp_save_post_revision( $post_id );

		$history_id = $this->history->create( array(
			'post_id'   => $post_id,
			'post_type' => 'post',
			'status'    => 'completed',
		) );

		$summary = $this->service->on_before_delete_post( $rev_id );
		$this->assertEmpty( $summary );

		// Verify the original post's history record is untouched.
		$this->assertNotNull( $this->history->get_by_id( $history_id ) );
	}

	public function test_without_history_deletion_suppresses_history_deletion() {
		$post_id = self::factory()->post->create( array( 'post_title' => 'Regenerating Post' ) );

		$vector = array_fill( 0, 8, 0.5 );
		$this->embeddings->upsert( 'post', $post_id, $vector, 'model', 8, 'hash', 'post' );

		$history_id = $this->history->create( array(
			'post_id'   => $post_id,
			'post_type' => 'post',
			'status'    => 'pending',
		) );
		$this->history->add_log_entry( $history_id, 'Regeneration started' );

		$service = $this->service;
		AIPS_Post_Cleanup_Service::without_history_deletion( function() use ( $service, $post_id ) {
			$this->assertTrue( AIPS_Post_Cleanup_Service::is_history_deletion_suppressed() );
			$summary = $service->on_before_delete_post( $post_id );

			$this->assertSame( 0, $summary['history'] );
			$this->assertGreaterThan( 0, $summary['embeddings'] );
		} );

		$this->assertFalse( AIPS_Post_Cleanup_Service::is_history_deletion_suppressed() );

		// History container and logs still exist!
		$this->assertNotNull( $this->history->get_by_id( $history_id ) );
		// But embedding was deleted
		$this->assertNull( $this->embeddings->get( 'post', $post_id ) );
	}

	public function test_aips_should_cleanup_post_filter_can_abort_cleanup() {
		$post_id = self::factory()->post->create( array( 'post_title' => 'Protected Post' ) );
		$vector  = array_fill( 0, 8, 0.5 );
		$this->embeddings->upsert( 'post', $post_id, $vector, 'model', 8, 'hash', 'post' );

		add_filter( 'aips_should_cleanup_post', '__return_false' );
		$summary = $this->service->on_before_delete_post( $post_id );
		remove_filter( 'aips_should_cleanup_post', '__return_false' );

		$this->assertEmpty( $summary );
		$this->assertNotNull( $this->embeddings->get( 'post', $post_id ) );
	}

	public function test_actions_fire_on_cleanup() {
		$post_id = self::factory()->post->create( array( 'post_title' => 'Action Target' ) );

		$before_fired = false;
		$after_fired  = false;
		$after_data   = null;

		add_action( 'aips_before_post_cleanup', function( $id ) use ( &$before_fired, $post_id ) {
			if ( $id === $post_id ) {
				$before_fired = true;
			}
		} );

		add_action( 'aips_post_cleanup_executed', function( $id, $summary ) use ( &$after_fired, &$after_data, $post_id ) {
			if ( $id === $post_id ) {
				$after_fired = true;
				$after_data  = $summary;
			}
		}, 10, 2 );

		$this->service->on_before_delete_post( $post_id );

		$this->assertTrue( $before_fired );
		$this->assertTrue( $after_fired );
		$this->assertSame( $post_id, $after_data['post_id'] );
	}

	public function test_wp_delete_post_triggers_cleanup_via_hook() {
		$post_id = self::factory()->post->create( array( 'post_title' => 'End to End Deletion' ) );

		$vector = array_fill( 0, 8, 0.77 );
		$this->embeddings->upsert( 'post', $post_id, $vector, 'model', 8, 'hash', 'post' );

		$history_id = $this->history->create( array(
			'post_id'   => $post_id,
			'post_type' => 'post',
			'status'    => 'completed',
		) );

		wp_delete_post( $post_id, true );

		$this->assertNull( $this->embeddings->get( 'post', $post_id ) );
		$this->assertNull( $this->history->get_by_id( $history_id ) );
	}
}
