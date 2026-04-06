<?php
/**
 * Tests for the Internal Links feature (schema + basic repository API).
 *
 * @package AI_Post_Scheduler
 */

class Test_AIPS_Internal_Links extends WP_UnitTestCase {

	/** @var AIPS_Post_Embeddings_Repository */
	private $embeddings_repo;

	/** @var AIPS_Internal_Links_Repository */
	private $links_repo;

	public function setUp(): void {
		parent::setUp();
		AIPS_DB_Manager::install_tables();
		$this->embeddings_repo = new AIPS_Post_Embeddings_Repository();
		$this->links_repo      = new AIPS_Internal_Links_Repository();
	}

	public function tearDown(): void {
		global $wpdb;
		$wpdb->query( 'DELETE FROM ' . $wpdb->prefix . 'aips_post_embeddings' );
		$wpdb->query( 'DELETE FROM ' . $wpdb->prefix . 'aips_internal_links' );
		parent::tearDown();
	}

	// -------------------------------------------------------------------------
	// Schema tests
	// -------------------------------------------------------------------------

	/**
	 * aips_post_embeddings table should exist and have the expected columns.
	 */
	public function test_post_embeddings_table_exists() {
		global $wpdb;
		$table   = $wpdb->prefix . 'aips_post_embeddings';
		$columns = $wpdb->get_col( "DESCRIBE $table" );

		$this->assertNotEmpty( $columns, 'aips_post_embeddings table should exist' );
		$this->assertContains( 'id',         $columns );
		$this->assertContains( 'post_id',    $columns );
		$this->assertContains( 'embedding',  $columns );
		$this->assertContains( 'model',      $columns );
		$this->assertContains( 'indexed_at', $columns );
	}

	/**
	 * aips_internal_links table should exist and have the expected columns.
	 */
	public function test_internal_links_table_exists() {
		global $wpdb;
		$table   = $wpdb->prefix . 'aips_internal_links';
		$columns = $wpdb->get_col( "DESCRIBE $table" );

		$this->assertNotEmpty( $columns, 'aips_internal_links table should exist' );
		$this->assertContains( 'id',               $columns );
		$this->assertContains( 'source_post_id',   $columns );
		$this->assertContains( 'target_post_id',   $columns );
		$this->assertContains( 'similarity_score', $columns );
		$this->assertContains( 'anchor_text',      $columns );
		$this->assertContains( 'status',           $columns );
	}

	// -------------------------------------------------------------------------
	// Post Embeddings Repository
	// -------------------------------------------------------------------------

	/**
	 * Upsert should insert a new row and retrieve it by post_id.
	 */
	public function test_embeddings_upsert_and_get() {
		$embedding = array( 0.1, 0.2, 0.3 );

		$this->embeddings_repo->upsert( 42, $embedding, 'test-model' );

		$row = $this->embeddings_repo->get_by_post_id( 42 );

		$this->assertNotNull( $row );
		$this->assertEquals( 42, (int) $row->post_id );
		$this->assertEquals( 'test-model', $row->model );
		$this->assertEquals( $embedding, json_decode( $row->embedding, true ) );
	}

	/**
	 * Upsert on an existing post_id should update the embedding.
	 */
	public function test_embeddings_upsert_updates_existing() {
		$this->embeddings_repo->upsert( 1, array( 0.1 ), 'old-model' );
		$this->embeddings_repo->upsert( 1, array( 0.9 ), 'new-model' );

		$row = $this->embeddings_repo->get_by_post_id( 1 );

		$this->assertEquals( array( 0.9 ), json_decode( $row->embedding, true ) );
		$this->assertEquals( 'new-model', $row->model );
	}

	/**
	 * Count() should reflect the number of indexed posts.
	 */
	public function test_embeddings_count() {
		$this->assertEquals( 0, $this->embeddings_repo->count() );

		$this->embeddings_repo->upsert( 10, array( 1.0 ) );
		$this->embeddings_repo->upsert( 11, array( 0.5 ) );

		$this->assertEquals( 2, $this->embeddings_repo->count() );
	}

	/**
	 * delete() should remove the row.
	 */
	public function test_embeddings_delete() {
		$this->embeddings_repo->upsert( 5, array( 0.5 ) );
		$this->assertNotNull( $this->embeddings_repo->get_by_post_id( 5 ) );

		$this->embeddings_repo->delete( 5 );
		$this->assertNull( $this->embeddings_repo->get_by_post_id( 5 ) );
	}

	// -------------------------------------------------------------------------
	// Internal Links Repository
	// -------------------------------------------------------------------------

	/**
	 * insert() should create a pending suggestion row.
	 */
	public function test_links_insert_creates_pending_row() {
		$id = $this->links_repo->insert( 100, 200, 0.85, 'Related article' );

		$this->assertNotFalse( $id );
		$this->assertGreaterThan( 0, $id );

		$rows = $this->links_repo->get_by_source_post( 100 );
		$this->assertCount( 1, $rows );
		$this->assertEquals( 200, (int) $rows[0]->target_post_id );
		$this->assertEquals( 'pending', $rows[0]->status );
		$this->assertEquals( 'Related article', $rows[0]->anchor_text );
	}

	/**
	 * update_status() should change the status of a suggestion.
	 */
	public function test_links_update_status() {
		$id = $this->links_repo->insert( 10, 20, 0.9 );

		$result = $this->links_repo->update_status( $id, 'accepted' );
		$this->assertNotFalse( $result );

		$rows = $this->links_repo->get_by_source_post( 10 );
		$this->assertEquals( 'accepted', $rows[0]->status );
	}

	/**
	 * update_status() should reject unknown status values.
	 */
	public function test_links_update_status_rejects_invalid() {
		$id     = $this->links_repo->insert( 30, 40, 0.8 );
		$result = $this->links_repo->update_status( $id, 'invalid_status' );
		$this->assertFalse( $result );
	}

	/**
	 * update_anchor_text() should update the anchor.
	 */
	public function test_links_update_anchor_text() {
		$id = $this->links_repo->insert( 50, 60, 0.7, 'Old anchor' );
		$this->links_repo->update_anchor_text( $id, 'New anchor' );

		$rows = $this->links_repo->get_by_source_post( 50 );
		$this->assertEquals( 'New anchor', $rows[0]->anchor_text );
	}

	/**
	 * delete() should remove a specific suggestion.
	 */
	public function test_links_delete() {
		$id = $this->links_repo->insert( 70, 80, 0.75 );
		$this->links_repo->delete( $id );

		$rows = $this->links_repo->get_by_source_post( 70 );
		$this->assertCount( 0, $rows );
	}

	/**
	 * exists() should return true for a known pair.
	 */
	public function test_links_exists() {
		$this->links_repo->insert( 90, 91, 0.8 );

		$this->assertTrue( $this->links_repo->exists( 90, 91 ) );
		$this->assertFalse( $this->links_repo->exists( 90, 99 ) );
	}

	/**
	 * get_status_counts() should return correct per-status totals.
	 */
	public function test_links_get_status_counts() {
		$id1 = $this->links_repo->insert( 1, 2, 0.8 );
		$id2 = $this->links_repo->insert( 1, 3, 0.75 );
		$id3 = $this->links_repo->insert( 2, 3, 0.9 );

		$this->links_repo->update_status( $id1, 'accepted' );
		$this->links_repo->update_status( $id2, 'rejected' );

		$counts = $this->links_repo->get_status_counts();

		$this->assertEquals( 1, $counts['accepted'] );
		$this->assertEquals( 1, $counts['rejected'] );
		$this->assertEquals( 1, $counts['pending'] );
		$this->assertEquals( 0, $counts['inserted'] );
	}

	/**
	 * delete_by_source_post() should remove all rows for the source.
	 */
	public function test_links_delete_by_source_post() {
		$this->links_repo->insert( 5, 6, 0.8 );
		$this->links_repo->insert( 5, 7, 0.75 );
		$this->links_repo->insert( 8, 9, 0.9 );

		$this->links_repo->delete_by_source_post( 5 );

		$this->assertCount( 0, $this->links_repo->get_by_source_post( 5 ) );
		$this->assertCount( 1, $this->links_repo->get_by_source_post( 8 ) );
	}
}
