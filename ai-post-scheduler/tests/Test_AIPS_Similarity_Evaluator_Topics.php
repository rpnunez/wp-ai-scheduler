<?php
/**
 * Tests for AIPS_Similarity_Evaluator topic similarity methods:
 * O(1) query scaling and prevention of unbounded inline AI calls.
 *
 * @package AI_Post_Scheduler
 */

class Test_AIPS_Similarity_Evaluator_Topics extends WP_UnitTestCase {

	/** @var AIPS_Author_Topics_Repository */
	private $topics_repo;

	/** @var AIPS_Embeddings_Repository */
	private $embeddings_repo;

	/** @var int */
	private $author_id = 999;

	public function setUp(): void {
		parent::setUp();
		AIPS_DB_Manager::install_tables();

		$this->topics_repo     = new AIPS_Author_Topics_Repository();
		$this->embeddings_repo = new AIPS_Embeddings_Repository();

		global $wpdb;
		$wpdb->query( "DELETE FROM {$wpdb->prefix}aips_author_topics WHERE author_id = {$this->author_id}" );
		$wpdb->query( "DELETE FROM {$wpdb->prefix}aips_embeddings WHERE object_type = 'topic'" );
		AIPS_Embeddings_Repository::reset_table_cache();
	}

	public function tearDown(): void {
		global $wpdb;
		$wpdb->query( "DELETE FROM {$wpdb->prefix}aips_author_topics WHERE author_id = {$this->author_id}" );
		$wpdb->query( "DELETE FROM {$wpdb->prefix}aips_embeddings WHERE object_type = 'topic'" );
		AIPS_Embeddings_Repository::reset_table_cache();
		parent::tearDown();
	}

	/**
	 * Test that find_similar_topics has O(1) database queries in candidate count (no N+1).
	 */
	public function test_find_similar_topics_query_count_is_constant_with_candidate_count() {
		global $wpdb;

		$vector = array( 0.5, 0.5, 0.5, 0.5 );

		// 1. Create target topic with embedding
		$target_id = $this->topics_repo->create( array(
			'author_id'   => $this->author_id,
			'topic_title' => 'Target Topic Title',
			'status'      => 'pending',
		) );
		$this->embeddings_repo->upsert( 'topic', $target_id, $vector, 'test-model', 4, 'hash_target', '' );

		// 2. Create 5 candidate topics with embeddings
		for ( $i = 1; $i <= 5; $i++ ) {
			$cid = $this->topics_repo->create( array(
				'author_id'   => $this->author_id,
				'topic_title' => "Candidate Topic {$i}",
				'status'      => 'pending',
			) );
			$this->embeddings_repo->upsert( 'topic', $cid, $vector, 'test-model', 4, "hash_{$i}", '' );
		}

		$config    = AIPS_Config::get_instance();
		$evaluator = new AIPS_Similarity_Evaluator(
			$config,
			$this->embeddings_repo,
			new AIPS_Embeddings_Service( null, null, $config, null, $this->embeddings_repo, $this->topics_repo )
		);

		// Warm up any internal caches / setup queries
		$evaluator->find_similar_topics( $target_id, $this->author_id, 5 );

		// Measure queries for 5 candidates
		$start_queries = $wpdb->num_queries;
		$results_5 = $evaluator->find_similar_topics( $target_id, $this->author_id, 5 );
		$queries_5 = $wpdb->num_queries - $start_queries;

		$this->assertCount( 5, $results_5 );

		// 3. Add 20 more candidates (total 25 candidates)
		for ( $i = 6; $i <= 25; $i++ ) {
			$cid = $this->topics_repo->create( array(
				'author_id'   => $this->author_id,
				'topic_title' => "Candidate Topic {$i}",
				'status'      => 'pending',
			) );
			$this->embeddings_repo->upsert( 'topic', $cid, $vector, 'test-model', 4, "hash_{$i}", '' );
		}

		// Warm up 25 candidates
		$evaluator->find_similar_topics( $target_id, $this->author_id, 5 );

		// Measure queries for 25 candidates
		$start_queries = $wpdb->num_queries;
		$results_25 = $evaluator->find_similar_topics( $target_id, $this->author_id, 5 );
		$queries_25 = $wpdb->num_queries - $start_queries;

		$this->assertNotEmpty( $results_25 );

		// The query count must be constant (O(1)) and NOT scale linearly with candidate count (no N+1)
		$this->assertSame( $queries_5, $queries_25, "Query count scaled with candidate count: {$queries_5} vs {$queries_25}" );
	}

	/**
	 * Test that find_similar_topics does NOT make AI API calls when candidates lack embeddings.
	 */
	public function test_find_similar_topics_does_not_call_ai_service_when_embeddings_missing() {
		$vector = array( 0.2, 0.4, 0.6, 0.8 );

		// Create target topic with embedding
		$target_id = $this->topics_repo->create( array(
			'author_id'   => $this->author_id,
			'topic_title' => 'Target Topic Without Neighbors With Embeddings',
			'status'      => 'pending',
		) );
		$this->embeddings_repo->upsert( 'topic', $target_id, $vector, 'test-model', 4, 'hash_target_no_emb', '' );

		// Create candidate topics WITHOUT embeddings
		for ( $i = 1; $i <= 5; $i++ ) {
			$this->topics_repo->create( array(
				'author_id'   => $this->author_id,
				'topic_title' => "Missing Embedding Topic {$i}",
				'status'      => 'pending',
			) );
		}

		$ai_service = $this->createMock( AIPS_AI_Service_Interface::class );
		// Ensure AI API is NEVER called during similarity evaluation
		$ai_service->expects( $this->never() )->method( 'generate_embedding' );

		$config          = AIPS_Config::get_instance();
		$emb_service     = new AIPS_Embeddings_Service( $ai_service, null, $config, null, $this->embeddings_repo, $this->topics_repo );
		$evaluator       = new AIPS_Similarity_Evaluator( $config, $this->embeddings_repo, $emb_service );

		// Without compute_missing (default false): no AI API calls made
		$similar = $evaluator->find_similar_topics( $target_id, $this->author_id, 5, null, false );
		$this->assertIsArray( $similar );
		$this->assertEmpty( $similar );

		// calculate_max_similarity_for_topic also must not call AI service
		$candidate_topics = array_filter(
			$this->topics_repo->get_by_author( $this->author_id, 'pending' ),
			function( $t ) use ( $target_id ) {
				return (int) $t->id !== (int) $target_id;
			}
		);
		$max_sim          = $evaluator->calculate_max_similarity_for_topic( $vector, $candidate_topics, $emb_service, false, $this->author_id );
		$this->assertSame( 0.0, $max_sim );
	}

	/**
	 * Test that opt-in compute_missing allows inline generation when explicitly requested.
	 */
	public function test_find_similar_topics_computes_inline_only_when_requested() {
		$vector = array( 0.2, 0.4, 0.6, 0.8 );

		$target_id = $this->topics_repo->create( array(
			'author_id'   => $this->author_id,
			'topic_title' => 'Opt-in Target',
			'status'      => 'pending',
		) );
		$this->embeddings_repo->upsert( 'topic', $target_id, $vector, 'test-model', 4, 'hash_target_optin', '' );

		$candidate_id = $this->topics_repo->create( array(
			'author_id'   => $this->author_id,
			'topic_title' => 'Opt-in Candidate',
			'status'      => 'pending',
		) );

		$ai_service = $this->createMock( AIPS_AI_Service_Interface::class );
		$ai_service->expects( $this->once() )
			->method( 'generate_embedding' )
			->willReturn( $vector );

		$config      = AIPS_Config::get_instance();
		$emb_service = new AIPS_Embeddings_Service( $ai_service, null, $config, null, $this->embeddings_repo, $this->topics_repo );
		$evaluator   = new AIPS_Similarity_Evaluator( $config, $this->embeddings_repo, $emb_service );

		$similar = $evaluator->find_similar_topics( $target_id, $this->author_id, 5, null, true );
		$this->assertCount( 1, $similar );
		$this->assertSame( $candidate_id, (int) $similar[0]['id'] );
	}
}
