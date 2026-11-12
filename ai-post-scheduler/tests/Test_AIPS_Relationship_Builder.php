<?php
/**
 * Tests for AIPS_Relationship_Builder and the Related Posts Recompute process.
 *
 * @package AI_Post_Scheduler
 */

class Test_AIPS_Relationship_Builder extends WP_UnitTestCase {

	/** @var AIPS_Embeddings_Repository */
	private $embeddings;

	/** @var AIPS_Relationships_Repository */
	private $relationships;

	/** @var AIPS_Relationship_Builder */
	private $builder;

	public function setUp(): void {
		parent::setUp();
		AIPS_DB_Manager::install_tables();

		AIPS_Config::get_instance()->set_option( 'aips_indexer_post_types', array( 'post' ) );

		foreach ( get_posts( array( 'post_type' => 'post', 'post_status' => 'any', 'numberposts' => -1, 'fields' => 'ids' ) ) as $existing_id ) {
			wp_delete_post( $existing_id, true );
		}

		$this->embeddings    = new AIPS_Embeddings_Repository();
		$this->relationships = new AIPS_Relationships_Repository();
		$this->builder       = new AIPS_Relationship_Builder( $this->embeddings, $this->relationships );
	}

	public function tearDown(): void {
		global $wpdb;
		$wpdb->query( 'DELETE FROM ' . $wpdb->prefix . 'aips_embeddings' );
		$wpdb->query( 'DELETE FROM ' . $wpdb->prefix . 'aips_relationships' );
		$wpdb->query( 'DELETE FROM ' . $wpdb->prefix . 'aips_background_processes' );
		parent::tearDown();
	}

	/**
	 * Create a published post with a stored vector.
	 *
	 * @param float[] $vector Vector.
	 * @return int Post ID.
	 */
	private function indexed_post( array $vector ): int {
		$id = wp_insert_post( array(
			'post_title'   => 'Post ' . wp_generate_password( 6, false ),
			'post_content' => 'Content',
			'post_status'  => 'publish',
			'post_type'    => 'post',
		) );
		$this->embeddings->upsert( 'post', $id, $vector, 'test-model', count( $vector ), md5( (string) $id ), 'post' );

		return $id;
	}

	/**
	 * @param int $source Source post.
	 * @return array<int,float> Target post ID => similarity.
	 */
	private function related_of( int $source ): array {
		$map = array();
		foreach ( $this->relationships->get_related( 'post', $source, 50, 0.0 ) as $row ) {
			$map[ (int) $row->target_id ] = (float) $row->similarity;
		}
		return $map;
	}

	public function test_decode_vector_reads_packed_json_and_arrays() {
		$packed = pack( 'f*', 0.5, 0.25 );
		$this->assertEqualsWithDelta( array( 0.5, 0.25 ), AIPS_Relationship_Builder::decode_vector( $packed ), 0.0001 );
		$this->assertSame( array( 1.0, 2.0 ), AIPS_Relationship_Builder::decode_vector( '[1,2]' ) );
		$this->assertSame( array( 3.0 ), AIPS_Relationship_Builder::decode_vector( array( 'a' => 3 ) ) );
		$this->assertSame( array(), AIPS_Relationship_Builder::decode_vector( '' ) );
	}

	public function test_normalize_and_dot_give_cosine_similarity() {
		$a = AIPS_Relationship_Builder::normalize( array( 3.0, 4.0 ) );
		$this->assertEqualsWithDelta( 1.0, AIPS_Relationship_Builder::dot( $a, $a, 2 ), 0.0001 );

		$b = AIPS_Relationship_Builder::normalize( array( 0.0, 5.0 ) );
		$this->assertEqualsWithDelta( 0.8, AIPS_Relationship_Builder::dot( $a, $b, 2 ), 0.0001 );

		$this->assertNull( AIPS_Relationship_Builder::normalize( array( 0.0, 0.0 ) ) );
		$this->assertNull( AIPS_Relationship_Builder::normalize( array() ) );
	}

	public function test_matches_the_similarity_evaluator() {
		$evaluator = new AIPS_Similarity_Evaluator();
		$x         = array( 0.2, 0.9, -0.1, 0.4 );
		$y         = array( 0.5, 0.3, 0.8, 0.1 );

		$expected = $evaluator->cosine_similarity( $x, $y );
		$actual   = AIPS_Relationship_Builder::dot( AIPS_Relationship_Builder::normalize( $x ), AIPS_Relationship_Builder::normalize( $y ), 4 );

		$this->assertEqualsWithDelta( $expected, $actual, 0.0001 );
	}

	public function test_compute_stores_ranked_neighbours_above_the_threshold() {
		$source   = $this->indexed_post( array( 1.0, 0.0 ) );
		$close    = $this->indexed_post( array( 0.9, 0.1 ) );
		$middling = $this->indexed_post( array( 0.7, 0.7 ) );
		$far      = $this->indexed_post( array( 0.0, 1.0 ) );

		$saved = $this->builder->compute_for_posts( array( $source ), 15, 0.50 );

		$this->assertSame( array( $source => 2 ), $saved );

		$related = $this->related_of( $source );
		$this->assertArrayHasKey( $close, $related );
		$this->assertArrayHasKey( $middling, $related );
		$this->assertArrayNotHasKey( $far, $related, 'Orthogonal posts fall below the threshold.' );
		$this->assertArrayNotHasKey( $source, $related, 'A post is never related to itself.' );
		$this->assertGreaterThan( $related[ $middling ], $related[ $close ] );
	}

	public function test_top_k_keeps_only_the_best_matches() {
		$source = $this->indexed_post( array( 1.0, 0.0 ) );
		$ids    = array();
		foreach ( array( 0.95, 0.9, 0.85, 0.8, 0.75 ) as $x ) {
			$ids[ (string) $x ] = $this->indexed_post( array( $x, sqrt( 1 - $x * $x ) ) );
		}

		$this->builder->compute_for_posts( array( $source ), 3, 0.50 );

		$related = $this->related_of( $source );
		$this->assertCount( 3, $related );
		$this->assertArrayHasKey( $ids['0.95'], $related );
		$this->assertArrayHasKey( $ids['0.85'], $related );
		$this->assertArrayNotHasKey( $ids['0.75'], $related );
	}

	public function test_a_batch_is_computed_in_one_pass_across_pages() {
		$a = $this->indexed_post( array( 1.0, 0.0 ) );
		$b = $this->indexed_post( array( 0.95, 0.31 ) );
		$c = $this->indexed_post( array( 0.0, 1.0 ) );
		$d = $this->indexed_post( array( 0.31, 0.95 ) );
		$e = $this->indexed_post( array( 0.6, 0.8 ) );

		$this->builder->set_page_size( 2 );
		$saved = $this->builder->compute_for_posts( array( $a, $c ), 15, 0.60 );

		$this->assertSame( array( $a, $c ), array_keys( $saved ) );
		$this->assertArrayHasKey( $b, $this->related_of( $a ) );
		$this->assertArrayHasKey( $d, $this->related_of( $c ) );
		$this->assertArrayHasKey( $e, $this->related_of( $c ) );
	}

	public function test_candidates_with_a_different_dimension_are_skipped() {
		$source = $this->indexed_post( array( 1.0, 0.0 ) );
		$other  = $this->indexed_post( array( 1.0, 0.0, 0.0 ) );

		$this->builder->compute_for_posts( array( $source ), 15, 0.10 );

		$this->assertArrayNotHasKey( $other, $this->related_of( $source ) );
	}

	public function test_sources_without_an_embedding_are_left_alone() {
		$no_vector = wp_insert_post( array( 'post_title' => 'No vector', 'post_status' => 'publish', 'post_type' => 'post' ) );
		$target    = wp_insert_post( array( 'post_title' => 'Target', 'post_status' => 'publish', 'post_type' => 'post' ) );
		$this->relationships->sync_for_source( 'post', $no_vector, array( array( 'target_type' => 'post', 'target_id' => $target, 'similarity' => 0.9 ) ), 'related_post' );

		$saved = $this->builder->compute_for_posts( array( $no_vector ) );

		$this->assertSame( array(), $saved );
		$this->assertArrayHasKey( $target, $this->related_of( $no_vector ), 'Existing rows are not wiped when the source cannot be computed.' );
	}

	public function test_recompute_replaces_stale_relationships() {
		$source = $this->indexed_post( array( 1.0, 0.0 ) );
		$close  = $this->indexed_post( array( 0.95, 0.31 ) );
		$stale  = wp_insert_post( array( 'post_title' => 'Stale', 'post_status' => 'publish', 'post_type' => 'post' ) );
		$this->relationships->sync_for_source( 'post', $source, array( array( 'target_type' => 'post', 'target_id' => $stale, 'similarity' => 0.99 ) ), 'related_post' );
		$this->assertArrayHasKey( $stale, $this->related_of( $source ) );

		$this->builder->compute_for_posts( array( $source ), 15, 0.50 );

		$related = $this->related_of( $source );
		$this->assertArrayNotHasKey( $stale, $related );
		$this->assertArrayHasKey( $close, $related );
	}

	public function test_batch_size_shrinks_as_the_library_grows() {
		$this->assertSame( 50, $this->builder->get_batch_size_for_budget( 12.0, 50 ), 'A tiny library fits the maximum batch.' );
		$this->assertGreaterThanOrEqual( 1, $this->builder->get_batch_size_for_budget( 0.0001, 50 ) );
	}

	public function test_recompute_process_fills_in_missing_relationships_then_completes() {
		$a = $this->indexed_post( array( 1.0, 0.0 ) );
		$b = $this->indexed_post( array( 0.95, 0.31 ) );
		$c = $this->indexed_post( array( 0.9, 0.43 ) );

		$process = new AIPS_Relationships_Recompute_Process( null, $this->builder, $this->relationships );

		$snapshot = $process->start();
		$this->assertIsArray( $snapshot );
		$this->assertSame( 3, $snapshot['total'] );

		$process->tick();
		$this->assertArrayHasKey( $b, $this->related_of( $a ) );
		$this->assertArrayHasKey( $a, $this->related_of( $c ) );

		$snapshot = $process->get_snapshot();
		$this->assertSame( 'idle', $snapshot['status'] );
		$this->assertSame( 'completed', $snapshot['last_status'] );
		$this->assertSame( 3, $snapshot['processed'] );
		$this->assertSame( 0, $snapshot['ai_calls_used'], 'Recompute makes no AI calls.' );
		$this->assertFalse( $process->uses_ai() );

		wp_clear_scheduled_hook( AIPS_Managed_Background_Process::TICK_HOOK, array( AIPS_Relationships_Recompute_Process::KEY ) );
	}

	public function test_recompute_process_skips_posts_that_already_have_relationships_unless_asked() {
		$a = $this->indexed_post( array( 1.0, 0.0 ) );
		$b = $this->indexed_post( array( 0.95, 0.31 ) );
		$this->builder->compute_for_posts( array( $a, $b ) );

		$process  = new AIPS_Relationships_Recompute_Process( null, $this->builder, $this->relationships );
		$estimate = $process->get_estimate();
		$this->assertSame( 0, $estimate['items'] );

		$start = $process->start();
		$this->assertWPError( $start );
		$this->assertSame( 'aips_bg_nothing_to_do', $start->get_error_code() );

		$snapshot = $process->start( array( 'mode' => 'all' ) );
		$this->assertIsArray( $snapshot );
		$this->assertSame( 2, $snapshot['total'] );

		$process->cancel();
		wp_clear_scheduled_hook( AIPS_Managed_Background_Process::TICK_HOOK, array( AIPS_Relationships_Recompute_Process::KEY ) );
	}
}
