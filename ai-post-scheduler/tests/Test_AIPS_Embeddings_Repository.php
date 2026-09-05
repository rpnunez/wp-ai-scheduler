<?php
/**
 * Tests for AIPS_Embeddings_Repository.
 *
 * @package AI_Post_Scheduler
 */

class Test_AIPS_Embeddings_Repository extends WP_UnitTestCase {

	/** @var AIPS_Embeddings_Repository */
	private $repo;

	public function setUp(): void {
		parent::setUp();
		AIPS_Cache_Factory::reset();
		AIPS_DB_Manager::install_tables();
		$this->repo = new AIPS_Embeddings_Repository();
	}

	public function tearDown(): void {
		global $wpdb;
		$wpdb->query( 'DELETE FROM ' . $wpdb->prefix . 'aips_embeddings' );
		AIPS_Cache_Factory::reset();
		parent::tearDown();
	}

	/**
	 * Test upsert and retrieval by object type and ID.
	 */
	public function test_upsert_and_get_by_object() {
		$post_id = wp_insert_post( array(
			'post_title'   => 'Test Upsert Post',
			'post_content' => 'Content for testing embedding upsert.',
			'post_status'  => 'publish',
			'post_type'    => 'post',
		) );

		$vector = array( 0.12, 0.34, 0.56, 0.78 );
		$id     = $this->repo->upsert(
			'post',
			$post_id,
			$vector,
			'text-embedding-3-small',
			4,
			md5( 'test content' ),
			'post'
		);

		$this->assertNotFalse( $id );

		$record = $this->repo->get_by_object( 'post', $post_id );
		$this->assertNotNull( $record );
		$this->assertEquals( 'post', $record->object_type );
		$this->assertEquals( 'post', $record->object_post_type );
		$this->assertEquals( $post_id, (int) $record->object_id );
		$this->assertEquals( 4, (int) $record->dimensions );
		$this->assertEquals( 'text-embedding-3-small', $record->model );
	}

	/**
	 * Test helper get_by_post_id.
	 */
	public function test_get_by_post_id() {
		$page_id = wp_insert_post( array(
			'post_title'  => 'Test Page',
			'post_status' => 'publish',
			'post_type'   => 'page',
		) );

		$vector = array( 0.1, 0.2, 0.3 );
		$this->repo->upsert(
			'post',
			$page_id,
			$vector,
			'test-model',
			3,
			'',
			'page'
		);

		$record = $this->repo->get_by_post_id( $page_id );
		$this->assertNotNull( $record );
		$this->assertEquals( $page_id, (int) $record->object_id );
		$this->assertEquals( 'page', $record->object_post_type );
	}

	/**
	 * Test delete by object and delete by post id.
	 */
	public function test_delete_and_delete_by_post_id() {
		$post_id = wp_insert_post( array(
			'post_title'  => 'Delete Test Post',
			'post_status' => 'publish',
			'post_type'   => 'post',
		) );

		$this->repo->upsert(
			'post',
			$post_id,
			array( 0.1, 0.2 ),
			'model',
			2,
			'',
			'post'
		);
		$this->repo->upsert(
			'author_topic',
			20,
			array( 0.3, 0.4 ),
			'model',
			2,
			'',
			''
		);

		$this->assertNotNull( $this->repo->get_by_post_id( $post_id ) );
		$this->repo->delete_by_post_id( $post_id );
		$this->assertNull( $this->repo->get_by_post_id( $post_id ) );

		$this->assertNotNull( $this->repo->get_by_object( 'author_topic', 20 ) );
		$this->repo->delete( 'author_topic', 20 );
		$this->assertNull( $this->repo->get_by_object( 'author_topic', 20 ) );
	}

	/**
	 * Test get_all_for_similarity_by_type.
	 */
	public function test_get_all_for_similarity_by_type() {
		$p1 = wp_insert_post( array( 'post_title' => 'Post 1', 'post_status' => 'publish', 'post_type' => 'post' ) );
		$p2 = wp_insert_post( array( 'post_title' => 'Post 2', 'post_status' => 'publish', 'post_type' => 'post' ) );

		$this->repo->upsert( 'post', $p1, array( 1.0, 0.0 ), 'model', 2, '', 'post' );
		$this->repo->upsert( 'post', $p2, array( 0.0, 1.0 ), 'model', 2, '', 'post' );

		$posts_only = $this->repo->get_all_for_similarity_by_type( 'post' );
		$this->assertCount( 2, $posts_only );
	}

	/**
	 * Test get_stored_dimensions returns distinct dimensions.
	 */
	public function test_get_stored_dimensions() {
		$this->repo->upsert( 'post', 101, array( 0.1, 0.2 ), 'model', 2, '', 'post' );
		$this->repo->upsert( 'post', 102, array( 0.1, 0.2, 0.3, 0.4 ), 'model', 4, '', 'post' );

		$dims = $this->repo->get_stored_dimensions();
		$this->assertContains( 2, $dims );
		$this->assertContains( 4, $dims );
	}

	/**
	 * Test clear_all purges entire embeddings table.
	 */
	public function test_clear_all() {
		$this->repo->upsert( 'post', 101, array( 0.1, 0.2 ), 'model', 2, '', 'post' );

		$this->assertEquals( 1, $this->repo->get_total_indexed() );
		$this->repo->clear_all();
		$this->assertEquals( 0, $this->repo->get_total_indexed() );
	}

	// -------------------------------------------------------------------------
	// Repository cache behaviour (#2053)
	// -------------------------------------------------------------------------

	/**
	 * Every write must bump the broad tag so previously cached reads refresh.
	 */
	public function test_cached_reads_refresh_after_writes() {
		$post_id = wp_insert_post( array(
			'post_title'  => 'Cache Refresh Post',
			'post_status' => 'publish',
			'post_type'   => 'post',
		) );

		$this->assertNull( $this->repo->get_by_post_id( $post_id ) );
		$this->assertSame( 0, $this->repo->count( 'post' ) );

		$this->repo->upsert( 'post', $post_id, array( 0.1, 0.2 ), 'model', 2, 'hash-one', 'post' );

		$first = $this->repo->get_by_post_id( $post_id );
		$this->assertNotNull( $first );
		$this->assertSame( 'hash-one', $first->content_hash );
		$this->assertSame( 1, $this->repo->count( 'post' ) );
		$this->assertSame( array( $post_id ), $this->repo->get_all_indexed_post_ids() );

		// Update path bumps too.
		$this->repo->upsert( 'post', $post_id, array( 0.3, 0.4 ), 'model', 2, 'hash-two', 'post' );
		$this->assertSame( 'hash-two', $this->repo->get_by_post_id( $post_id )->content_hash );

		$stats = $this->repo->get_stats();
		$this->assertSame( 1, $stats['posts'] );

		$this->repo->delete_by_post_id( $post_id );

		$this->assertNull( $this->repo->get_by_post_id( $post_id ) );
		$this->assertSame( 0, $this->repo->count( 'post' ) );
		$this->assertSame( array(), $this->repo->get_all_indexed_post_ids() );
		$this->assertSame( 0, $this->repo->get_stats()['posts'] );
	}

	/**
	 * get_by_post_ids() results must not depend on the caller's ID ordering, and
	 * must refresh after writes like every other cached read.
	 */
	public function test_get_by_post_ids_is_order_independent_and_refreshes() {
		$this->repo->upsert( 'post', 301, array( 0.1 ), 'model', 1, '', 'post' );
		$this->repo->upsert( 'post', 302, array( 0.2 ), 'model', 1, '', 'post' );

		$a = $this->repo->get_by_post_ids( array( 302, 301, 302 ) );
		$b = $this->repo->get_by_post_ids( array( 301, 302 ) );

		$this->assertEqualsCanonicalizing( array( 301, 302 ), array_keys( $b ) );
		$this->assertEqualsCanonicalizing( array_keys( $a ), array_keys( $b ) );

		$this->repo->delete( 'post', 302 );

		$this->assertSame( array( 301 ), array_keys( $this->repo->get_by_post_ids( array( 301, 302 ) ) ) );
	}

	/**
	 * upsert() must decide insert-vs-update from the database, never from a
	 * cached row. A row removed out-of-band (no tag bump) must be re-inserted,
	 * not turned into a zero-row update.
	 */
	public function test_upsert_reinserts_when_row_was_removed_out_of_band() {
		global $wpdb;
		$table = $wpdb->prefix . 'aips_embeddings';

		$this->repo->upsert( 'post', 501, array( 0.1 ), 'model', 1, 'hash-one', 'post' );
		$this->assertNotNull( $this->repo->get_by_object( 'post', 501 ) ); // warms the cache

		// Remove the row behind the repository's back: no cache bump happens.
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE object_type = 'post' AND object_id = %d", 501 ) );

		$result = $this->repo->upsert( 'post', 501, array( 0.2 ), 'model', 1, 'hash-two', 'post' );
		$this->assertNotFalse( $result );

		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE object_type = 'post' AND object_id = %d", 501 ) );
		$this->assertNotNull( $row, 'upsert must re-insert a row that vanished out-of-band' );
		$this->assertSame( 'hash-two', $row->content_hash );
	}

	/**
	 * count_indexed_for_types() joins wp_posts. Trashing a post through the
	 * native WP flow must refresh it via AIPS_Embeddings_Cache_Invalidator.
	 */
	public function test_count_indexed_for_types_refreshes_when_post_is_trashed() {
		$invalidator = new AIPS_Embeddings_Cache_Invalidator( $this->repo );
		$invalidator->register();

		try {
			$post_id = wp_insert_post( array(
				'post_title'  => 'Trash Me',
				'post_status' => 'publish',
				'post_type'   => 'post',
			) );
			$this->repo->upsert( 'post', $post_id, array( 1.0, 0.0 ), 'model', 2, '', 'post' );

			$this->assertSame( 1, $this->repo->count_indexed_for_types( array( 'post' ), 'publish' ) );

			wp_trash_post( $post_id );

			$this->assertSame( 0, $this->repo->count_indexed_for_types( array( 'post' ), 'publish' ) );
		} finally {
			remove_action( 'transition_post_status', array( $invalidator, 'on_transition_post_status' ), 10 );
			remove_action( 'deleted_post', array( $invalidator, 'on_deleted_post' ), 10 );
		}
	}

	/**
	 * Permanent deletion never fires save_post and leaves an orphaned embedding
	 * row; the deleted_post hook must still refresh the joined count.
	 */
	public function test_count_indexed_for_types_refreshes_when_post_is_deleted() {
		$invalidator = new AIPS_Embeddings_Cache_Invalidator( $this->repo );
		$invalidator->register();

		try {
			$post_id = wp_insert_post( array(
				'post_title'  => 'Delete Me',
				'post_status' => 'publish',
				'post_type'   => 'post',
			) );
			$this->repo->upsert( 'post', $post_id, array( 1.0, 0.0 ), 'model', 2, '', 'post' );

			$this->assertSame( 1, $this->repo->count_indexed_for_types( array( 'post' ), 'publish' ) );

			wp_delete_post( $post_id, true );

			$this->assertSame( 0, $this->repo->count_indexed_for_types( array( 'post' ), 'publish' ) );
		} finally {
			remove_action( 'transition_post_status', array( $invalidator, 'on_transition_post_status' ), 10 );
			remove_action( 'deleted_post', array( $invalidator, 'on_deleted_post' ), 10 );
		}
	}

	/**
	 * count_indexed_for_types() must share a cache key regardless of post-type
	 * ordering so equivalent calls do not fan out into duplicate entries.
	 */
	public function test_count_indexed_for_types_is_order_independent() {
		$post_id = wp_insert_post( array( 'post_title' => 'P', 'post_status' => 'publish', 'post_type' => 'post' ) );
		$page_id = wp_insert_post( array( 'post_title' => 'G', 'post_status' => 'publish', 'post_type' => 'page' ) );

		$this->repo->upsert( 'post', $post_id, array( 1.0 ), 'model', 1, '', 'post' );
		$this->repo->upsert( 'post', $page_id, array( 1.0 ), 'model', 1, '', 'page' );

		$this->assertSame( 2, $this->repo->count_indexed_for_types( array( 'page', 'post' ), 'publish' ) );
		$this->assertSame( 2, $this->repo->count_indexed_for_types( array( 'post', 'page', 'post' ), 'publish' ) );
	}
}
