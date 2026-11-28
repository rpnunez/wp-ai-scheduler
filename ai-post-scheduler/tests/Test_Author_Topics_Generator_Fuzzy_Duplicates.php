<?php
/**
 * Tests for AIPS_Author_Topics_Generator::apply_fuzzy_duplicate_flags()
 *
 * The method is private and is exercised via the public generate_topics() method.
 *
 * @package AI_Post_Scheduler
 */

class AIPS_Test_Stub_AI_Service_For_Fuzzy implements AIPS_AI_Service_Interface {
	private $topics;
	public function __construct( $t ) { $this->topics = $t; }
	public function generate_json( $prompt, $options = array() ) {
		return $this->topics;
	}
	public function is_available() { return true; }
	public function generate_text($prompt, $options = array()) { return ""; }
	public function generate_image($prompt, $options = array()) { return ""; }
	public function generate_embedding($text, $options = array()) { return array(); }
	public function supports_embeddings() { return false; }
	public function supports_conversation() { return false; }
	public function get_call_log() { return array(); }
}

class AIPS_Test_Stub_Logger_For_Fuzzy implements AIPS_Logger_Interface {
	public function log( $message, $level = 'info', $context = array() ) {}
	public function clear() {}
	public function get_logs($limit = 100, $offset = 0) { return array(); }
	public function set_level($level) {}
	public function addSeparator($text = "") {}
}

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
		};
	}

	/**
	 * Build a mock AI service that returns a JSON array of topics.
	 *
	 * @param array $topics Array of associative topic arrays to return.
	 * @return object
	 */
	private function make_ai_service( $topics ) {
		return new AIPS_Test_Stub_AI_Service_For_Fuzzy( $topics );
	}

	/**
	 * Build a no-op logger.
	 *
	 * @return object
	 */
	private function make_logger() {
		return new AIPS_Test_Stub_Logger_For_Fuzzy();
	}

	/**
	 * Build a mock embeddings repository returning stored topic vectors for similarity checks.
	 *
	 * @param array $existing_topics Existing topic objects containing metadata.embedding.
	 * @return AIPS_Embeddings_Repository
	 */
	private function make_embeddings_repository( $existing_topics = array() ) {
		$rows    = array();
		$vectors = array();
		foreach ( $existing_topics as $t ) {
			$meta = isset( $t->metadata ) ? json_decode( (string) $t->metadata, true ) : array();
			if ( is_array( $meta ) && ! empty( $meta['embedding'] ) ) {
				$id             = (int) $t->id;
				$vectors[ $id ] = $meta['embedding'];
				$rows[]         = (object) array(
					'object_id'    => $id,
					'object_type'  => 'topic',
					'embedding'    => wp_json_encode( $meta['embedding'] ),
					'content_hash' => md5( (string) $t->topic_title ),
				);
			}
		}

		$mock = $this->getMockBuilder( AIPS_Embeddings_Repository::class )
			->disableOriginalConstructor()
			->onlyMethods( array( 'get_all_for_similarity', 'decode_embedding' ) )
			->getMock();

		$mock->method( 'get_all_for_similarity' )
			->willReturnCallback( function ( $object_type ) use ( $rows ) {
				return 'topic' === $object_type ? $rows : array();
			} );

		$mock->method( 'decode_embedding' )
			->willReturnCallback( function ( $raw, $type, $object_id ) use ( $vectors ) {
				return isset( $vectors[ (int) $object_id ] ) ? $vectors[ (int) $object_id ] : array();
			} );

		return $mock;
	}

	/**
	 * Build an AIPS_Author_Topics_Generator wired with test doubles including embeddings_repo.
	 *
	 * @param array  $new_topic_data   AI topic payload.
	 * @param object $topics_repo      Topics repository double.
	 * @param object $embeddings_svc   Embeddings service double.
	 * @param array  $existing_topics  Existing topics for similarity index.
	 * @return AIPS_Author_Topics_Generator
	 */
	private function make_generator_with_embeddings( $new_topic_data, $topics_repo, $embeddings_svc, $existing_topics ) {
		return new AIPS_Author_Topics_Generator(
			$this->make_ai_service( $new_topic_data ),
			$this->make_logger(),
			$topics_repo,
			new class {
				public function log_post_generation( $a, $b, $c ) {}
				public function create( $data ) { return 1; }
			},
			$embeddings_svc,
			null,
			null,
			null,
			null,
			$this->make_embeddings_repository( $existing_topics )
		);
	}

	/**
	 * Test that topics above the similarity threshold are flagged as duplicates
	 * and have their score reduced by 20.
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

		// Embeddings service: supported, returns embedding_a for any text, similarity = 1.0.
		$embeddings_service = new class( $embedding_a ) {
			private $emb;
			public function __construct( $e ) { $this->emb = $e; }
			public function is_enabled() { return true; }
			public function is_embeddings_supported() { return true; }
			public function generate_embedding( $text ) { return $this->emb; }
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
				'score'       => 50, // 70 - 20
				'metadata'    => wp_json_encode( array( 'potential_duplicate' => true ) ),
				'generated_at' => current_time( 'mysql' ),
			),
		);

		$topics_repo2 = $this->make_topics_repository( array( $existing_topic ), $bulk_topics );
		$generator    = $this->make_generator_with_embeddings( $new_topic_data, $topics_repo2, $embeddings_service, array( $existing_topic ) );

		$result = $generator->generate_topics( $this->make_author() );

		$this->assertIsArray( $result, 'generate_topics() should return an array' );
		$this->assertNotEmpty( $result, 'At least one topic should be returned' );

		// Verify that the topic was inserted with score reduced by 20 (70 → 50).
		$inserted = $topics_repo2->bulk_inserted;
		$this->assertNotEmpty( $inserted );
		$this->assertEquals( 50, $inserted[0]['score'], 'Score should be reduced by 20 for flagged duplicate' );

		// Verify duplicate metadata was stored.
		$meta = json_decode( $inserted[0]['metadata'], true );
		$this->assertTrue( $meta['potential_duplicate'], 'potential_duplicate flag should be true' );
		$this->assertArrayHasKey( 'duplicate_similarity', $meta, 'duplicate_similarity key should be present' );
		$this->assertArrayHasKey( 'duplicate_match', $meta, 'duplicate_match key should be present' );
		$this->assertEquals( 'Existing Similar Topic', $meta['duplicate_match'] );

		// The candidate embedding must NOT be persisted in metadata — it is a
		// ~50 KB/row float array kept only in-memory for same-request reuse; the
		// canonical copy lives in aips_embeddings.
		$this->assertArrayNotHasKey( 'embedding', $meta, 'embedding must not be persisted in metadata' );
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
			public function is_enabled() { return true; }
			public function is_embeddings_supported() { return true; }
			public function generate_embedding( $text ) { return $this->emb; }
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
		$generator   = $this->make_generator_with_embeddings( $new_topic_data, $topics_repo, $embeddings_service, array( $existing_topic ) );

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
			public function is_enabled() { return false; }
			public function is_embeddings_supported() { return false; }
			public function generate_embedding( $text ) { return array(); }
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
		$generator   = $this->make_generator_with_embeddings( $new_topic_data, $topics_repo, $embeddings_service, array( $existing_topic ) );

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
		update_option( 'aips_deduplication_threshold', 0.70 );

		$stored_vec    = array( 1.0, 0.0, 0.0 );
		$candidate_vec = array( 0.75, sqrt( 1.0 - 0.5625 ), 0.0 );

		$existing_topic = (object) array(
			'id'          => 10,
			'topic_title' => 'Somewhat Similar Topic',
			'status'      => 'approved',
			'metadata'    => wp_json_encode( array( 'embedding' => $stored_vec ) ),
		);

		// Similarity = 0.75 — above the 0.70 threshold but below the default 0.85.
		$embeddings_service = new class( $candidate_vec ) {
			private $emb;
			public function __construct( $e ) { $this->emb = $e; }
			public function is_enabled() { return true; }
			public function is_embeddings_supported() { return true; }
			public function generate_embedding( $text ) { return $this->emb; }
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
				'score'       => 40, // 60 - 20
				'metadata'    => wp_json_encode( array( 'potential_duplicate' => true ) ),
				'generated_at' => current_time( 'mysql' ),
			),
		);

		$topics_repo = $this->make_topics_repository( array( $existing_topic ), $bulk_topics );
		$generator   = $this->make_generator_with_embeddings( $new_topic_data, $topics_repo, $embeddings_service, array( $existing_topic ) );

		$generator->generate_topics( $this->make_author() );

		$inserted = $topics_repo->bulk_inserted;
		$this->assertNotEmpty( $inserted );

		// Score should be reduced because similarity (0.75) exceeds the lowered threshold (0.70).
		$this->assertEquals( 40, $inserted[0]['score'], 'Score should be reduced when similarity exceeds lowered threshold' );

		$meta = json_decode( $inserted[0]['metadata'], true );
		$this->assertTrue( $meta['potential_duplicate'], 'Topic should be flagged as potential duplicate at lower threshold' );

		delete_option( 'aips_deduplication_threshold' );
	}

	/**
	 * Test that a topic with borderline similarity is NOT flagged when threshold is raised.
	 *
	 * Similarity 0.75 is below 0.90 threshold → should NOT be flagged.
	 */
	public function test_borderline_topic_not_flagged_with_higher_threshold() {
		update_option( 'aips_deduplication_threshold', 0.90 );

		$stored_vec    = array( 1.0, 0.0, 0.0 );
		$candidate_vec = array( 0.75, sqrt( 1.0 - 0.5625 ), 0.0 );

		$existing_topic = (object) array(
			'id'          => 10,
			'topic_title' => 'Similar But Not Enough',
			'status'      => 'approved',
			'metadata'    => wp_json_encode( array( 'embedding' => $stored_vec ) ),
		);

		// Similarity = 0.75 — below the 0.90 threshold.
		$embeddings_service = new class( $candidate_vec ) {
			private $emb;
			public function __construct( $e ) { $this->emb = $e; }
			public function is_enabled() { return true; }
			public function is_embeddings_supported() { return true; }
			public function generate_embedding( $text ) { return $this->emb; }
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
		$generator   = $this->make_generator_with_embeddings( $new_topic_data, $topics_repo, $embeddings_service, array( $existing_topic ) );

		$generator->generate_topics( $this->make_author() );

		$inserted = $topics_repo->bulk_inserted;
		$this->assertNotEmpty( $inserted );

		// Score should remain at 60 because similarity (0.75) is below the raised threshold (0.90).
		$this->assertEquals( 60, $inserted[0]['score'], 'Score should NOT be reduced when similarity is below raised threshold' );

		$meta = json_decode( $inserted[0]['metadata'], true );
		$this->assertFalse( $meta['potential_duplicate'], 'Topic should NOT be flagged when similarity is below raised threshold' );

		delete_option( 'aips_deduplication_threshold' );
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
			public function is_enabled() { return true; }
			public function is_embeddings_supported() { return true; }
			public function generate_embedding( $text ) { return $this->emb; }
			public function calculate_similarity( $a, $b ) { return 0.95; }
		};

		$new_topic_data = array(
			array(
				'title' => 'Nearly Identical Overlapping Topic Here',
				'score' => 5, // Low initial score; 5 - 20 = -15, should clamp to 0.
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
		$generator   = $this->make_generator_with_embeddings( $new_topic_data, $topics_repo, $embeddings_service, array( $existing_topic ) );

		$generator->generate_topics( $this->make_author() );

		$inserted = $topics_repo->bulk_inserted;
		$this->assertNotEmpty( $inserted );

		// Score must be clamped to 0, not negative.
		$this->assertGreaterThanOrEqual( 0, $inserted[0]['score'], 'Score must not go below 0' );
		$this->assertEquals( 0, $inserted[0]['score'], 'Score should be clamped to 0 when penalty exceeds it' );
	}
}
