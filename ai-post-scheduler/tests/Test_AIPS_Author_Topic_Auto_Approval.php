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

		$rejected_meta = json_decode( $result[1]['metadata'], true );
		$this->assertTrue( $rejected_meta['auto_rejected'] );
	}

	/**
	 * Test that 'similarity' mode approves topics with low duplicate similarity and keeps duplicates pending.
	 */
	public function test_similarity_mode_threshold_and_pending_fallback() {
		$author = (object) array(
			'id'                                 => 1,
			'name'                               => 'Test Author',
			'topic_auto_approval_mode'           => 'similarity',
			'topic_auto_approval_max_similarity' => 0.80,
			'topic_auto_approval_fallback'       => 'pending',
		);

		$topics = array(
			array(
				'topic_title' => 'Unique Topic',
				'score'       => 50,
				'metadata'    => wp_json_encode( array(
					'potential_duplicate'  => false,
					'duplicate_similarity' => 0.25,
				) ),
			),
			array(
				'topic_title' => 'Duplicate Topic',
				'score'       => 30,
				'metadata'    => wp_json_encode( array(
					'potential_duplicate'  => true,
					'duplicate_similarity' => 0.92,
				) ),
			),
		);

		$result = $this->generator->apply_auto_approval_rules( $author, $topics );

		$this->assertEquals( 'approved', $result[0]['status'] );
		$this->assertEquals( 'pending', $result[1]['status'] );

		$meta = json_decode( $result[0]['metadata'], true );
		$this->assertTrue( $meta['auto_approved'] );
		$this->assertEquals( 0.25, $meta['auto_approval_similarity'] );

		$pending_meta = json_decode( $result[1]['metadata'], true );
		$this->assertTrue( $pending_meta['auto_approval_evaluated'] );
	}
}
