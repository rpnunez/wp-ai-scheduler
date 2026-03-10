<?php
/**
 * Tests for AIPS_Author_Topics_Generator::apply_fuzzy_duplicate_flags()
 *
 * The method is private and is exercised via the public generate_topics() method.
 *
 * @package AI_Post_Scheduler
 */

class Test_Author_Topics_Generator_Fuzzy_Duplicates extends WP_UnitTestCase {

	/**
	 * Build a minimal author stub with the fields generate_topics() needs.
	 *
	 * @param array $overrides Optional property overrides.
	 * @return object
	 */
	private function make_author( $overrides = array() ) {
		$defaults = array(
			'id'                        => 1,
			'name'                      => 'Test Author',
			'field_niche'               => 'WordPress Development',
			'keywords'                  => '',
			'details'                   => '',
			'voice_tone'                => '',
			'writing_style'             => '',
			'topic_generation_prompt'   => '',
			'topic_generation_quantity' => 2,
		);
		return (object) array_merge( $defaults, $overrides );
	}

	/**
	 * Build a mock topics repository that returns $existing_topics from get_by_author()
	 * and records create_bulk / get_latest_by_author calls.
	 *
	 * @param array $existing_topics Objects returned by get_by_author().
	 * @param array $bulk_topics     Objects returned by get_latest_by_author().
	 * @return object
	 */
	private function make_topics_repository( $existing_topics = array(), $bulk_topics = array() ) {
		return new class( $existing_topics, $bulk_topics ) {
			public $bulk_inserted   = array();
			private $existing;
			private $bulk;

			public function __construct( $existing, $bulk ) {
				$this->existing = $existing;
				$this->bulk     = $bulk;
			}

			public function get_by_author( $author_id, $status = null ) {
				return $this->existing;
			}

			public function create_bulk( $topics ) {
				$this->bulk_inserted = $topics;
				return true;
			}

			public function get_latest_by_author( $author_id, $limit, $after = null, $titles = null ) {
				return array_slice( $this->bulk, 0, $limit );
			}

			public function get_approved_summary( $author_id, $limit = 20 ) {
				return array();
			}

			public function get_rejected_summary( $author_id, $limit = 20 ) {
				return array();
			}

			public function update( $id, $data ) {
				return true;
			}
		};
	}

	/**
	 * Build a mock AI service that returns a JSON array of topics.
	 *
	 * @param array $topics Array of associative topic arrays to return.
	 * @return object
	 */
	private function make_ai_service( $topics ) {
		return new class( $topics ) {
			private $topics;
			public function __construct( $t ) { $this->topics = $t; }
			public function generate_json( $prompt, $options = array() ) {
				return $this->topics;
			}
		};
	}

	/**
	 * Build a no-op logger.
	 *
	 * @return object
	 */
	private function make_logger() {
		return new class {
			public function log( $message, $level = 'info', $context = array() ) {}
		};
	}

	/**
	 * Test that topics above the similarity threshold are flagged as duplicates
	 * and have their score reduced by 15.
	 */
	public function test_topics_above_threshold_are_flagged_and_score_reduced() {
		$embedding_a = array( 1.0, 0.0, 0.0 );
		$embedding_b = array( 1.0, 0.0, 0.0 ); // Identical → similarity = 1.0

		// Existing topic with a stored embedding.
		$existing_topic = (object) array(
			'id'          => 10,
			'topic_title' => 'Existing Similar Topic',
			'status'      => 'approved',
			'metadata'    => wp_json_encode( array( 'embedding' => $embedding_b ) ),
		);

		$topics_repo = $this->make_topics_repository( array( $existing_topic ) );

		// Embeddings service: supported, returns embedding_a for any text, similarity = 1.0.
		$embeddings_service = new class( $embedding_a ) {
			private $emb;
			public function __construct( $e ) { $this->emb = $e; }
			public function is_embeddings_supported() { return true; }
			public function generate_embedding( $text, $options = array(), $history_container = null ) { return $this->emb; }
			public function calculate_similarity( $a, $b ) { return 1.0; }
		};

		$new_topic_data = array(
			array(
				'title' => 'Very Similar Topic Title',
				'score' => 70,
			),
		);

		// Repository returns the inserted topics as objects on get_latest_by_author().
		$bulk_topics = array(
			(object) array(
				'id'          => 100,
				'author_id'   => 1,
				'topic_title' => 'Very Similar Topic Title',
				'status'      => 'pending',
				'score'       => 55, // 70 - 15
				'metadata'    => wp_json_encode( array( 'potential_duplicate' => true ) ),
				'generated_at' => current_time( 'mysql' ),
			),
		);

		$topics_repo2 = $this->make_topics_repository( array( $existing_topic ), $bulk_topics );

		$generator = new AIPS_Author_Topics_Generator(
			$this->make_ai_service( $new_topic_data ),
			$this->make_logger(),
			$topics_repo2,
			new class { public function log_post_generation( $a, $b, $c ) {} },
			$embeddings_service
		);

		$result = $generator->generate_topics( $this->make_author() );

		$this->assertIsArray( $result, 'generate_topics() should return an array' );
		$this->assertNotEmpty( $result, 'At least one topic should be returned' );

		// Verify that the topic was inserted with score reduced by 15 (70 → 55).
		$inserted = $topics_repo2->bulk_inserted;
		$this->assertNotEmpty( $inserted );
		$this->assertEquals( 55, $inserted[0]['score'], 'Score should be reduced by 15 for flagged duplicate' );

		// Verify duplicate metadata was stored.
		$meta = json_decode( $inserted[0]['metadata'], true );
		$this->assertTrue( $meta['potential_duplicate'], 'potential_duplicate flag should be true' );
		$this->assertArrayHasKey( 'duplicate_similarity', $meta, 'duplicate_similarity key should be present' );
		$this->assertArrayHasKey( 'duplicate_match', $meta, 'duplicate_match key should be present' );
		$this->assertEquals( 'Existing Similar Topic', $meta['duplicate_match'] );

		// Verify embedding is stored in metadata.
		$this->assertArrayHasKey( 'embedding', $meta, 'embedding should be stored in metadata' );
	}

	/**
	 * Test that topics below the similarity threshold are NOT flagged as duplicates.
	 */
	public function test_topics_below_threshold_are_not_flagged() {
		$embedding_a = array( 1.0, 0.0, 0.0 );
		$embedding_b = array( 0.0, 1.0, 0.0 );

		$existing_topic = (object) array(
			'id'          => 10,
			'topic_title' => 'Completely Different Topic',
			'status'      => 'approved',
			'metadata'    => wp_json_encode( array( 'embedding' => $embedding_b ) ),
		);

		// Similarity = 0.3 (below 0.92 threshold).
		$embeddings_service = new class( $embedding_a ) {
			private $emb;
			public function __construct( $e ) { $this->emb = $e; }
			public function is_embeddings_supported() { return true; }
			public function generate_embedding( $text, $options = array(), $history_container = null ) { return $this->emb; }
			public function calculate_similarity( $a, $b ) { return 0.3; }
		};

		$new_topic_data = array(
			array(
				'title' => 'Unique Fresh Topic About Something Else',
				'score' => 70,
			),
		);

		$bulk_topics = array(
			(object) array(
				'id'          => 100,
				'author_id'   => 1,
				'topic_title' => 'Unique Fresh Topic About Something Else',
				'status'      => 'pending',
				'score'       => 70,
				'metadata'    => wp_json_encode( array( 'potential_duplicate' => false ) ),
				'generated_at' => current_time( 'mysql' ),
			),
		);

		$topics_repo = $this->make_topics_repository( array( $existing_topic ), $bulk_topics );

		$generator = new AIPS_Author_Topics_Generator(
			$this->make_ai_service( $new_topic_data ),
			$this->make_logger(),
			$topics_repo,
			new class { public function log_post_generation( $a, $b, $c ) {} },
			$embeddings_service
		);

		$generator->generate_topics( $this->make_author() );

		$inserted = $topics_repo->bulk_inserted;
		$this->assertNotEmpty( $inserted );

		// Score must remain at 70 (not reduced).
		$this->assertEquals( 70, $inserted[0]['score'], 'Score should NOT be reduced for non-duplicate topic' );

		// Metadata should indicate it is not a duplicate.
		$meta = json_decode( $inserted[0]['metadata'], true );
		$this->assertFalse( $meta['potential_duplicate'], 'potential_duplicate flag should be false' );
		$this->assertArrayNotHasKey( 'duplicate_similarity', $meta );
		$this->assertArrayNotHasKey( 'duplicate_match', $meta );
	}

	/**
	 * Test that fuzzy flagging is skipped when embeddings are not supported.
	 */
	public function test_fuzzy_flagging_skipped_when_embeddings_unsupported() {
		$embeddings_service = new class {
			public function is_embeddings_supported() { return false; }
			public function generate_embedding( $text, $options = array(), $history_container = null ) { return array(); }
			public function calculate_similarity( $a, $b ) { return 1.0; }
		};

		$new_topic_data = array(
			array(
				'title' => 'Any Topic Title For Testing Purposes',
				'score' => 60,
			),
		);

		$bulk_topics = array(
			(object) array(
				'id'          => 100,
				'author_id'   => 1,
				'topic_title' => 'Any Topic Title For Testing Purposes',
				'status'      => 'pending',
				'score'       => 60,
				'metadata'    => wp_json_encode( array() ),
				'generated_at' => current_time( 'mysql' ),
			),
		);

		$existing_topic = (object) array(
			'id'          => 10,
			'topic_title' => 'Any Topic Title For Testing Purposes',
			'status'      => 'approved',
			'metadata'    => wp_json_encode( array( 'embedding' => array( 1.0, 0.0 ) ) ),
		);

		$topics_repo = $this->make_topics_repository( array( $existing_topic ), $bulk_topics );

		$generator = new AIPS_Author_Topics_Generator(
			$this->make_ai_service( $new_topic_data ),
			$this->make_logger(),
			$topics_repo,
			new class { public function log_post_generation( $a, $b, $c ) {} },
			$embeddings_service
		);

		$generator->generate_topics( $this->make_author() );

		$inserted = $topics_repo->bulk_inserted;
		$this->assertNotEmpty( $inserted );

		// Score must remain unchanged since fuzzy flagging was skipped.
		$this->assertEquals( 60, $inserted[0]['score'], 'Score should be unchanged when embeddings are unsupported' );
	}

	/**
	 * Test that a topic with borderline similarity IS flagged when threshold is lowered.
	 *
	 * Similarity 0.75 is above 0.70 threshold → should be flagged.
	 */
	public function test_borderline_topic_flagged_with_lower_threshold() {
		update_option( 'aips_topic_similarity_threshold', 0.70 );

		$embedding = array( 1.0, 0.0, 0.0 );

		$existing_topic = (object) array(
			'id'          => 10,
			'topic_title' => 'Somewhat Similar Topic',
			'status'      => 'approved',
			'metadata'    => wp_json_encode( array( 'embedding' => $embedding ) ),
		);

		// Similarity = 0.75 — above the 0.70 threshold but below the default 0.8.
		$embeddings_service = new class( $embedding ) {
			private $emb;
			public function __construct( $e ) { $this->emb = $e; }
			public function is_embeddings_supported() { return true; }
			public function generate_embedding( $text, $options = array(), $history_container = null ) { return $this->emb; }
			public function calculate_similarity( $a, $b ) { return 0.75; }
		};

		$new_topic_data = array(
			array( 'title' => 'Borderline Duplicate Topic', 'score' => 60 ),
		);

		$bulk_topics = array(
			(object) array(
				'id'          => 100,
				'author_id'   => 1,
				'topic_title' => 'Borderline Duplicate Topic',
				'status'      => 'pending',
				'score'       => 45, // 60 - 15
				'metadata'    => wp_json_encode( array( 'potential_duplicate' => true ) ),
				'generated_at' => current_time( 'mysql' ),
			),
		);

		$topics_repo = $this->make_topics_repository( array( $existing_topic ), $bulk_topics );

		$generator = new AIPS_Author_Topics_Generator(
			$this->make_ai_service( $new_topic_data ),
			$this->make_logger(),
			$topics_repo,
			new class { public function log_post_generation( $a, $b, $c ) {} },
			$embeddings_service
		);

		$generator->generate_topics( $this->make_author() );

		$inserted = $topics_repo->bulk_inserted;
		$this->assertNotEmpty( $inserted );

		// Score should be reduced because similarity (0.75) exceeds the lowered threshold (0.70).
		$this->assertEquals( 45, $inserted[0]['score'], 'Score should be reduced when similarity exceeds lowered threshold' );

		$meta = json_decode( $inserted[0]['metadata'], true );
		$this->assertTrue( $meta['potential_duplicate'], 'Topic should be flagged as potential duplicate at lower threshold' );

		delete_option( 'aips_topic_similarity_threshold' );
	}

	/**
	 * Test that a topic with borderline similarity is NOT flagged when threshold is raised.
	 *
	 * Similarity 0.75 is below 0.90 threshold → should NOT be flagged.
	 */
	public function test_borderline_topic_not_flagged_with_higher_threshold() {
		update_option( 'aips_topic_similarity_threshold', 0.90 );

		$embedding = array( 1.0, 0.0, 0.0 );

		$existing_topic = (object) array(
			'id'          => 10,
			'topic_title' => 'Similar But Not Enough',
			'status'      => 'approved',
			'metadata'    => wp_json_encode( array( 'embedding' => $embedding ) ),
		);

		// Similarity = 0.75 — below the 0.90 threshold.
		$embeddings_service = new class( $embedding ) {
			private $emb;
			public function __construct( $e ) { $this->emb = $e; }
			public function is_embeddings_supported() { return true; }
			public function generate_embedding( $text, $options = array(), $history_container = null ) { return $this->emb; }
			public function calculate_similarity( $a, $b ) { return 0.75; }
		};

		$new_topic_data = array(
			array( 'title' => 'Moderately Similar Topic', 'score' => 60 ),
		);

		$bulk_topics = array(
			(object) array(
				'id'          => 100,
				'author_id'   => 1,
				'topic_title' => 'Moderately Similar Topic',
				'status'      => 'pending',
				'score'       => 60,
				'metadata'    => wp_json_encode( array( 'potential_duplicate' => false ) ),
				'generated_at' => current_time( 'mysql' ),
			),
		);

		$topics_repo = $this->make_topics_repository( array( $existing_topic ), $bulk_topics );

		$generator = new AIPS_Author_Topics_Generator(
			$this->make_ai_service( $new_topic_data ),
			$this->make_logger(),
			$topics_repo,
			new class { public function log_post_generation( $a, $b, $c ) {} },
			$embeddings_service
		);

		$generator->generate_topics( $this->make_author() );

		$inserted = $topics_repo->bulk_inserted;
		$this->assertNotEmpty( $inserted );

		// Score should remain at 60 because similarity (0.75) is below the raised threshold (0.90).
		$this->assertEquals( 60, $inserted[0]['score'], 'Score should NOT be reduced when similarity is below raised threshold' );

		$meta = json_decode( $inserted[0]['metadata'], true );
		$this->assertFalse( $meta['potential_duplicate'], 'Topic should NOT be flagged when similarity is below raised threshold' );

		delete_option( 'aips_topic_similarity_threshold' );
	}

	/**
	 * Test that score does not go below 0 when reduced.
	 */
	public function test_score_does_not_go_below_zero() {
		$embedding = array( 1.0, 0.0 );

		$existing_topic = (object) array(
			'id'          => 10,
			'topic_title' => 'Overlapping Topic',
			'status'      => 'approved',
			'metadata'    => wp_json_encode( array( 'embedding' => $embedding ) ),
		);

		// Similarity above threshold → flag as duplicate.
		$embeddings_service = new class( $embedding ) {
			private $emb;
			public function __construct( $e ) { $this->emb = $e; }
			public function is_embeddings_supported() { return true; }
			public function generate_embedding( $text, $options = array(), $history_container = null ) { return $this->emb; }
			public function calculate_similarity( $a, $b ) { return 0.95; }
		};

		$new_topic_data = array(
			array(
				'title' => 'Nearly Identical Overlapping Topic Here',
				'score' => 5, // Low initial score; 5 - 15 = -10, should clamp to 0.
			),
		);

		$bulk_topics = array(
			(object) array(
				'id'          => 100,
				'author_id'   => 1,
				'topic_title' => 'Nearly Identical Overlapping Topic Here',
				'status'      => 'pending',
				'score'       => 0,
				'metadata'    => wp_json_encode( array() ),
				'generated_at' => current_time( 'mysql' ),
			),
		);

		$topics_repo = $this->make_topics_repository( array( $existing_topic ), $bulk_topics );

		$generator = new AIPS_Author_Topics_Generator(
			$this->make_ai_service( $new_topic_data ),
			$this->make_logger(),
			$topics_repo,
			new class { public function log_post_generation( $a, $b, $c ) {} },
			$embeddings_service
		);

		$generator->generate_topics( $this->make_author() );

		$inserted = $topics_repo->bulk_inserted;
		$this->assertNotEmpty( $inserted );

		// Score must be clamped to 0, not negative.
		$this->assertGreaterThanOrEqual( 0, $inserted[0]['score'], 'Score must not go below 0' );
		$this->assertEquals( 0, $inserted[0]['score'], 'Score should be clamped to 0 when penalty exceeds it' );
	}
}
