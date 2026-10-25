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
		AIPS_DB_Manager::install_tables();
		$this->repo = new AIPS_Embeddings_Repository();
	}

	public function tearDown(): void {
		global $wpdb;
		$wpdb->query( 'DELETE FROM ' . $wpdb->prefix . 'aips_embeddings' );
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
	 * Test get_by_source alias.
	 */
	public function test_get_by_source() {
		$vector = array( 0.4, 0.5, 0.6 );
		$this->repo->upsert(
			'topic',
			55,
			$vector,
			'test-model',
			3,
			md5( 'sample topic' ),
			''
		);

		$record = $this->repo->get_by_source( 'topic', 55 );
		$this->assertNotNull( $record );
		$this->assertEquals( 'topic', $record->object_type );
		$this->assertEquals( 55, (int) $record->object_id );
		$this->assertEquals( 3, (int) $record->dimensions );
	}

	/**
	 * Test helper get_by_topic_id and delete_by_topic_id.
	 */
	public function test_get_and_delete_by_topic_id() {
		$vector = array( 0.7, 0.8, 0.9 );
		$this->repo->upsert(
			'topic',
			88,
			$vector,
			'test-model',
			3,
			'',
			''
		);

		$record = $this->repo->get_by_topic_id( 88 );
		$this->assertNotNull( $record );
		$this->assertEquals( 88, (int) $record->object_id );

		$this->repo->delete_by_topic_id( 88 );
		$this->assertNull( $this->repo->get_by_topic_id( 88 ) );
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

	/**
	 * Test encode_embedding and decode_embedding binary float32 roundtrip.
	 */
	public function test_encode_decode_roundtrip() {
		$original = array( 0.12345, -0.67891, 0.0001, 123.456 );
		$encoded  = $this->repo->encode_embedding( $original );
		$this->assertIsString( $encoded );
		$this->assertEquals( count( $original ) * 4, strlen( $encoded ) );

		$decoded = $this->repo->decode_embedding( $encoded );
		$this->assertCount( count( $original ), $decoded );
		for ( $i = 0; $i < count( $original ); $i++ ) {
			$this->assertEqualsWithDelta( $original[ $i ], $decoded[ $i ], 0.00001 );
		}
	}

	/**
	 * Test decode_embedding backward-compatibility with legacy JSON strings.
	 */
	public function test_decode_legacy_json_embedding() {
		$legacy_json = '[0.15, -0.25, 0.99]';
		$decoded     = $this->repo->decode_embedding( $legacy_json );
		$this->assertCount( 3, $decoded );
		$this->assertEqualsWithDelta( 0.15, $decoded[0], 0.00001 );
		$this->assertEqualsWithDelta( -0.25, $decoded[1], 0.00001 );
		$this->assertEqualsWithDelta( 0.99, $decoded[2], 0.00001 );
	}

	/**
	 * Test decode_embedding with array passthrough and empty/null inputs.
	 */
	public function test_decode_array_and_empty() {
		$arr = array( 0.5, 0.75 );
		$this->assertEquals( array( 0.5, 0.75 ), $this->repo->decode_embedding( $arr ) );
		$this->assertEquals( array(), $this->repo->decode_embedding( '' ) );
		$this->assertEquals( array(), $this->repo->decode_embedding( null ) );
	}

	/**
	 * Test format_vector_summary metadata helper.
	 */
	public function test_format_vector_summary() {
		$vector  = array( 0.1, 0.2, 0.3, 0.4, 0.5, 0.6 );
		$encoded = $this->repo->encode_embedding( $vector );
		$summary = $this->repo->format_vector_summary( $encoded, 3 );

		$this->assertEquals( 6, $summary['dimensions'] );
		$this->assertCount( 3, $summary['preview'] );
		$this->assertEquals( 24, $summary['byte_size'] );
		$this->assertTrue( $summary['is_binary'] );
	}

	/**
	 * Test get_by_source_ids and get_by_topic_ids batch readers.
	 */
	public function test_get_by_source_ids_and_topic_ids() {
		$vec1 = array( 0.1, 0.2, 0.3 );
		$vec2 = array( 0.4, 0.5, 0.6 );
		$this->repo->upsert( 'topic', 101, $vec1, 'model-1', 3, 'hash1', '' );
		$this->repo->upsert( 'topic', 102, $vec2, 'model-1', 3, 'hash2', '' );
		$this->repo->upsert( 'post', 201, $vec1, 'model-1', 3, 'hash3', 'post' );

		// Batch read topics
		$topics = $this->repo->get_by_topic_ids( array( 101, 102, 999 ) );
		$this->assertCount( 2, $topics );
		$this->assertArrayHasKey( 101, $topics );
		$this->assertArrayHasKey( 102, $topics );
		$this->assertArrayNotHasKey( 999, $topics );
		$this->assertEquals( 101, (int) $topics[101]->object_id );
		$this->assertEquals( 'topic', $topics[101]->object_type );

		// Batch read posts via get_by_source_ids
		$posts = $this->repo->get_by_source_ids( 'post', array( 201, 999 ) );
		$this->assertCount( 1, $posts );
		$this->assertArrayHasKey( 201, $posts );
		$this->assertEquals( 201, (int) $posts[201]->object_id );

		// Empty IDs
		$this->assertEmpty( $this->repo->get_by_source_ids( 'topic', array() ) );
	}

	/**
	 * Test decode_and_normalize_embedding normalizes vector to unit length and caches.
	 */
	public function test_decode_and_normalize_embedding() {
		// Vector with magnitude 5: [3, 4] -> normalized [0.6, 0.8]
		$raw = array( 3.0, 4.0 );
		$normalized = $this->repo->decode_and_normalize_embedding( $raw, 'topic', 301, 'hash_test' );

		$this->assertCount( 2, $normalized );
		$this->assertEqualsWithDelta( 0.6, $normalized[0], 0.0001 );
		$this->assertEqualsWithDelta( 0.8, $normalized[1], 0.0001 );

		// Second call should return cached instance
		$cached = $this->repo->decode_and_normalize_embedding( $raw, 'topic', 301, 'hash_test' );
		$this->assertSame( $normalized, $cached );

		// Invalidation flushes cache
		$this->repo->invalidate_cached_vector( 'topic', 301 );
		$refetched = $this->repo->decode_and_normalize_embedding( $raw, 'topic', 301, 'hash_test' );
		$this->assertEqualsWithDelta( 0.6, $refetched[0], 0.0001 );
	}

	/**
	 * Test reset_table_cache clears cached table existence flags.
	 */
	public function test_reset_table_cache() {
		$this->assertTrue( $this->repo->table_exists() );
		AIPS_Embeddings_Repository::reset_table_cache();
		$this->assertTrue( $this->repo->table_exists() );
	}
}

