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
		$vector = array( 0.12, 0.34, 0.56, 0.78 );
		$id     = $this->repo->upsert( array(
			'object_type'      => 'post',
			'object_post_type' => 'post',
			'object_id'        => 42,
			'embedding'        => $vector,
			'dimensions'       => 4,
			'model'            => 'text-embedding-3-small',
			'content_hash'     => md5( 'test content' ),
		) );

		$this->assertIsInt( $id );
		$this->assertGreaterThan( 0, $id );

		$record = $this->repo->get_by_object( 'post', 42 );
		$this->assertNotNull( $record );
		$this->assertEquals( 'post', $record['object_type'] );
		$this->assertEquals( 'post', $record['object_post_type'] );
		$this->assertEquals( 42, (int) $record['object_id'] );
		$this->assertEquals( 4, (int) $record['dimensions'] );
		$this->assertEquals( 'text-embedding-3-small', $record['model'] );
		$this->assertEquals( $vector, $record['embedding'] );
	}

	/**
	 * Test helper get_by_post_id.
	 */
	public function test_get_by_post_id() {
		$vector = array( 0.1, 0.2, 0.3 );
		$this->repo->upsert( array(
			'object_type'      => 'post',
			'object_post_type' => 'page',
			'object_id'        => 99,
			'embedding'        => $vector,
			'dimensions'       => 3,
			'model'            => 'test-model',
		) );

		$record = $this->repo->get_by_post_id( 99 );
		$this->assertNotNull( $record );
		$this->assertEquals( 99, (int) $record['object_id'] );
		$this->assertEquals( 'page', $record['object_post_type'] );
	}

	/**
	 * Test delete by object and delete by post id.
	 */
	public function test_delete_and_delete_by_post_id() {
		$this->repo->upsert( array(
			'object_type'      => 'post',
			'object_post_type' => 'post',
			'object_id'        => 10,
			'embedding'        => array( 0.1, 0.2 ),
			'dimensions'       => 2,
		) );
		$this->repo->upsert( array(
			'object_type'      => 'author_topic',
			'object_post_type' => '',
			'object_id'        => 20,
			'embedding'        => array( 0.3, 0.4 ),
			'dimensions'       => 2,
		) );

		$this->assertNotNull( $this->repo->get_by_post_id( 10 ) );
		$this->repo->delete_by_post_id( 10 );
		$this->assertNull( $this->repo->get_by_post_id( 10 ) );

		$this->assertNotNull( $this->repo->get_by_object( 'author_topic', 20 ) );
		$this->repo->delete( 'author_topic', 20 );
		$this->assertNull( $this->repo->get_by_object( 'author_topic', 20 ) );
	}

	/**
	 * Test get_all_for_similarity and get_all_for_similarity_by_type.
	 */
	public function test_get_all_for_similarity() {
		$this->repo->upsert( array(
			'object_type'      => 'post',
			'object_post_type' => 'post',
			'object_id'        => 1,
			'embedding'        => array( 1.0, 0.0 ),
			'dimensions'       => 2,
		) );
		$this->repo->upsert( array(
			'object_type'      => 'post',
			'object_post_type' => 'news',
			'object_id'        => 2,
			'embedding'        => array( 0.0, 1.0 ),
			'dimensions'       => 2,
		) );
		$this->repo->upsert( array(
			'object_type'      => 'author_topic',
			'object_post_type' => '',
			'object_id'        => 3,
			'embedding'        => array( 0.5, 0.5 ),
			'dimensions'       => 2,
		) );

		$all = $this->repo->get_all_for_similarity();
		$this->assertCount( 3, $all );

		$posts_only = $this->repo->get_all_for_similarity_by_type( 'post' );
		$this->assertCount( 2, $posts_only );
	}

	/**
	 * Test get_stored_dimensions returns distinct dimensions.
	 */
	public function test_get_stored_dimensions() {
		$this->repo->upsert( array(
			'object_type'      => 'post',
			'object_post_type' => 'post',
			'object_id'        => 1,
			'embedding'        => array( 0.1, 0.2 ),
			'dimensions'       => 2,
		) );
		$this->repo->upsert( array(
			'object_type'      => 'post',
			'object_post_type' => 'post',
			'object_id'        => 2,
			'embedding'        => array( 0.1, 0.2, 0.3, 0.4 ),
			'dimensions'       => 4,
		) );

		$dims = $this->repo->get_stored_dimensions();
		$this->assertContains( 2, $dims );
		$this->assertContains( 4, $dims );
	}

	/**
	 * Test clear_all purges entire embeddings table.
	 */
	public function test_clear_all() {
		$this->repo->upsert( array(
			'object_type'      => 'post',
			'object_post_type' => 'post',
			'object_id'        => 1,
			'embedding'        => array( 0.1, 0.2 ),
			'dimensions'       => 2,
		) );

		$this->assertEquals( 1, $this->repo->get_total_indexed() );
		$this->repo->clear_all();
		$this->assertEquals( 0, $this->repo->get_total_indexed() );
	}
}
