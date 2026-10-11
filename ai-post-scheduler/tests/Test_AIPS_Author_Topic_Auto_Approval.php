<?php
/**
 * Tests for Author Topic Auto-Approval Rules in AIPS_Author_Topics_Generator.
 *
 * @package AI_Post_Scheduler
 */

class Test_AIPS_Author_Topic_Auto_Approval extends WP_UnitTestCase {

	/**
	 * @var AIPS_Author_Topics_Generator
	 */
	private $generator;

	public function setUp(): void {
		parent::setUp();
		$this->generator = new AIPS_Author_Topics_Generator();
	}

	/**
	 * Test that manual mode leaves all topics in pending status.
	 */
	public function test_manual_mode_leaves_topics_pending() {
		$author = (object) array(
			'id'                       => 1,
			'name'                     => 'Test Author',
			'topic_auto_approval_mode' => 'manual',
		);

		$topics = array(
			array(
				'topic_title' => 'Sample Topic 1',
				'score'       => 85,
				'metadata'    => wp_json_encode( array( 'potential_duplicate' => false ) ),
			),
		);

		$result = $this->generator->apply_auto_approval_rules( $author, $topics );

		$this->assertEquals( 'pending', isset( $result[0]['status'] ) ? $result[0]['status'] : 'pending' );
	}

	/**
	 * Test that 'all' mode auto-approves all generated topics.
	 */
	public function test_all_mode_approves_all_topics() {
		$author = (object) array(
			'id'                       => 1,
			'name'                     => 'Test Author',
			'topic_auto_approval_mode' => 'all',
		);

		$topics = array(
			array(
				'topic_title' => 'Sample Topic 1',
				'score'       => 40,
				'metadata'    => wp_json_encode( array() ),
			),
			array(
				'topic_title' => 'Sample Topic 2',
				'score'       => 90,
				'metadata'    => wp_json_encode( array() ),
			),
		);

		$result = $this->generator->apply_auto_approval_rules( $author, $topics );

		$this->assertEquals( 'approved', $result[0]['status'] );
		$this->assertEquals( 'approved', $result[1]['status'] );
		$meta = json_decode( $result[0]['metadata'], true );
		$this->assertTrue( $meta['auto_approved'] );
		$this->assertEquals( 'all', $meta['auto_approval_rule'] );
	}

	/**
	 * Test that 'score' mode approves topics meeting min score threshold and applies fallback.
	 */
	public function test_score_mode_threshold_and_fallback() {
		$author = (object) array(
			'id'                            => 1,
			'name'                          => 'Test Author',
			'topic_auto_approval_mode'      => 'score',
			'topic_auto_approval_min_score' => 75,
			'topic_auto_approval_fallback'  => 'rejected',
		);

		$topics = array(
			array(
				'topic_title' => 'High Score Topic',
				'score'       => 80,
				'metadata'    => wp_json_encode( array() ),
			),
			array(
				'topic_title' => 'Low Score Topic',
				'score'       => 60,
				'metadata'    => wp_json_encode( array() ),
			),
		);

		$result = $this->generator->apply_auto_approval_rules( $author, $topics );

		$this->assertEquals( 'approved', $result[0]['status'] );
		$this->assertEquals( 'rejected', $result[1]['status'] );

		$meta = json_decode( $result[0]['metadata'], true );
		$this->assertTrue( $meta['auto_approved'] );
		$this->assertEquals( 80, $meta['auto_approval_score'] );

		// evaluate_author_topic_auto_approval() records a single "auto_approved"
		// flag (true/false) rather than a separate auto_rejected key; the
		// decision itself (checked above) already distinguishes rejected from
		// pending.
		$rejected_meta = json_decode( $result[1]['metadata'], true );
		$this->assertFalse( $rejected_meta['auto_approved'] );
		$this->assertSame( 'score', $rejected_meta['auto_approval_rule'] );
	}

	/**
	 * Build an evaluator whose relevance measurement is fixed.
	 *
	 * @param float|null $relevance Relevance to report (null = unmeasurable).
	 * @return AIPS_Similarity_Evaluator
	 */
	private function make_evaluator($relevance) {
		return new class($relevance) extends AIPS_Similarity_Evaluator {
			private $fixed;

			public function __construct($fixed) {
				parent::__construct();
				$this->fixed = $fixed;
			}

			protected function measure_topic_relevance(string $topic_title, ?array $author_baseline_vec, ?array &$candidate_vec = null): ?float {
				return $this->fixed;
			}
		};
	}

	private function similarity_policy(array $overrides = array()) {
		return array_merge(array(
			'mode'           => 'similarity',
			'min_score'      => 70,
			'min_relevance'  => 0.65,
			'max_similarity' => 0.80,
			'fallback'       => 'pending',
			'source'         => 'author',
		), $overrides);
	}

	/**
	 * Similarity mode: unique + relevant approves; duplicates and off-niche topics fall back.
	 */
	public function test_similarity_mode_threshold_and_pending_fallback() {
		$author    = (object) array('id' => 1, 'topic_auto_approval_mode' => 'similarity');
		$unique    = array('topic_title' => 'Unique Topic', 'metadata' => array('duplicate_similarity' => 0.25));
		$duplicate = array('topic_title' => 'Duplicate Topic', 'metadata' => array('potential_duplicate' => true, 'duplicate_similarity' => 0.92));

		$relevant = $this->make_evaluator(0.9);

		$approved = $relevant->evaluate_author_topic_auto_approval($unique, $author, array(1.0), $this->similarity_policy());
		$this->assertSame('approved', $approved['decision']);
		$this->assertTrue($approved['meta']['auto_approved']);
		$this->assertEquals(0.25, $approved['meta']['auto_approval_duplicate_sim']);

		$dup = $relevant->evaluate_author_topic_auto_approval($duplicate, $author, array(1.0), $this->similarity_policy());
		$this->assertSame('pending', $dup['decision']);
		$this->assertFalse($dup['meta']['auto_approved']);
		$this->assertSame(0.92, $dup['meta']['auto_approval_duplicate_sim']);

		$off_niche = $this->make_evaluator(0.2)->evaluate_author_topic_auto_approval($unique, $author, array(1.0), $this->similarity_policy());
		$this->assertSame('pending', $off_niche['decision']);
		$this->assertSame('low_niche_relevance', $off_niche['reason']);
	}

	/**
	 * Similarity mode must never approve a topic whose relevance could not be measured.
	 */
	public function test_similarity_mode_unmeasurable_relevance_uses_fallback() {
		$author = (object) array('id' => 1);
		$topic  = array('topic_title' => 'Unique Topic', 'metadata' => array('duplicate_similarity' => 0.1));
		$eval   = $this->make_evaluator(null);

		$pending = $eval->evaluate_author_topic_auto_approval($topic, $author, null, $this->similarity_policy(array('fallback' => 'pending')));
		$this->assertSame('pending', $pending['decision']);
		$this->assertSame('relevance_unavailable', $pending['reason']);
		$this->assertNull($pending['meta']['auto_approval_relevance']);

		$rejected = $eval->evaluate_author_topic_auto_approval($topic, $author, null, $this->similarity_policy(array('fallback' => 'rejected')));
		$this->assertSame('rejected', $rejected['decision']);

		$split = $eval->evaluate_author_topic_auto_approval($topic, $author, null, $this->similarity_policy(array('fallback' => 'smart_split')));
		$this->assertSame('pending', $split['decision']);
	}

	/**
	 * Relevance and duplicate thresholds come from the policy, with relevance independent of quality score.
	 */
	public function test_similarity_relevance_threshold_is_independent_of_min_score() {
		$author = (object) array('id' => 1);
		$topic  = array('topic_title' => 'T', 'metadata' => array('duplicate_similarity' => 0.1));
		$eval   = $this->make_evaluator(0.5);

		// Quality score threshold is high, relevance threshold low: relevance 0.5 passes.
		$result = $eval->evaluate_author_topic_auto_approval($topic, $author, array(1.0), $this->similarity_policy(array('min_score' => 95, 'min_relevance' => 0.4)));
		$this->assertSame('approved', $result['decision']);
	}

	/**
	 * A reused candidate vector whose dimensions differ from the baseline is
	 * unmeasurable and must follow the fallback instead of silently passing.
	 */
	public function test_candidate_vector_dimension_mismatch_follows_fallback() {
		$evaluator = new AIPS_Similarity_Evaluator();
		$author    = (object) array('id' => 1);
		$topic     = array(
			'topic_title' => 'Mismatched',
			'embedding'   => array(0.1, 0.2),
			'metadata'    => array('duplicate_similarity' => 0.1),
		);

		$result = $evaluator->evaluate_author_topic_auto_approval($topic, $author, array(1.0, 0.0, 0.0), $this->similarity_policy());

		$this->assertSame('pending', $result['decision']);
		$this->assertSame('relevance_unavailable', $result['reason']);
	}

	/**
	 * Test that evaluate_author_topic_auto_approval reuses candidate embeddings from topic array or metadata.
	 */
	public function test_auto_approval_reuses_precomputed_candidate_embedding() {
		$evaluator = new AIPS_Similarity_Evaluator();

		$author = (object) array(
			'id'                                 => 1,
			'name'                               => 'Test Author',
			'topic_auto_approval_mode'           => 'similarity',
			'topic_auto_approval_min_score'      => 60,
			'topic_auto_approval_max_similarity' => 0.85,
			'topic_auto_approval_fallback'       => 'pending',
		);

		$baseline_vec = array( 1.0, 0.0, 0.0 );
		$candidate_vec = array( 0.9, 0.1, 0.0 ); // High similarity to baseline

		$topic = array(
			'topic_title' => 'Pre-embedded Topic',
			'score'       => 80,
			'embedding'   => $candidate_vec,
			'metadata'    => wp_json_encode( array(
				'potential_duplicate'  => false,
				'duplicate_similarity' => 0.15,
			) ),
		);

		$result = $evaluator->evaluate_author_topic_auto_approval( $topic, $author, $baseline_vec );

		$this->assertSame( 'approved', $result['decision'] );
		$this->assertTrue( $result['meta']['auto_approved'] );
		$this->assertGreaterThan( 0.80, $result['meta']['auto_approval_relevance'] );
		$this->assertSame( $candidate_vec, $result['meta']['embedding'] );
	}

	/**
	 * Test that get_author_composite_embedding retrieves cached vector from embeddings repository by content hash.
	 */
	public function test_author_composite_embedding_reuses_matching_content_hash() {
		$author = (object) array(
			'id'             => 42,
			'name'           => 'Tech Writer',
			'author_persona' => 'Expert in cloud computing and databases.',
			'author_bio'     => 'Writes extensively about distributed systems.',
			'niche'          => 'Cloud Infrastructure',
		);

		$profile_text = 'Tech Writer Expert in cloud computing and databases. Writes extensively about distributed systems. Cloud Infrastructure';
		$content_hash = md5( $profile_text );
		$saved_vector = array( 0.25, 0.50, 0.75 );

		$emb_repo = $this->createMock( AIPS_Embeddings_Repository::class );
		$emb_repo->method( 'get_by_object' )
			->with( 'author', 42 )
			->willReturn( (object) array(
				'embedding'    => json_encode( $saved_vector ),
				'content_hash' => $content_hash,
			) );
		$emb_repo->method( 'decode_embedding' )
			->willReturn( $saved_vector );

		$emb_service = $this->createMock( AIPS_Embeddings_Service::class );
		$emb_service->method( 'is_enabled' )->willReturn( true );
		// Ensure remote generate_embedding is NEVER called because repository cache matches!
		$emb_service->expects( $this->never() )->method( 'generate_embedding' );

		$generator = new AIPS_Author_Topics_Generator(
			null,
			null,
			null,
			null,
			$emb_service,
			null,
			null,
			null,
			null,
			$emb_repo
		);

		$result = $generator->get_author_composite_embedding( $author );
		$this->assertSame( $saved_vector, $result );
	}
}
