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
	private ;

	public function setUp(): void {
		parent::setUp();
		->generator = new AIPS_Author_Topics_Generator();
	}

	/**
	 * Test that manual mode leaves all topics in pending status.
	 */
	public function test_manual_mode_leaves_topics_pending() {
		 = (object) array(
			'id'                       => 1,
			'name'                     => 'Test Author',
			'topic_auto_approval_mode' => 'manual',
		);

		 = array(
			array(
				'topic_title' => 'Sample Topic 1',
				'score'       => 85,
				'metadata'    => wp_json_encode( array( 'potential_duplicate' => false ) ),
			),
		);

		 = ->generator->apply_auto_approval_rules( ,  );

		->assertEquals( 'pending', isset( [0]['status'] ) ? [0]['status'] : 'pending' );
	}

	/**
	 * Test that 'all' mode auto-approves all generated topics.
	 */
	public function test_all_mode_approves_all_topics() {
		 = (object) array(
			'id'                       => 1,
			'name'                     => 'Test Author',
			'topic_auto_approval_mode' => 'all',
		);

		 = array(
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

		 = ->generator->apply_auto_approval_rules( ,  );

		->assertEquals( 'approved', [0]['status'] );
		->assertEquals( 'approved', [1]['status'] );
		 = json_decode( [0]['metadata'], true );
		->assertTrue( ['auto_approved'] );
		->assertEquals( 'all', ['auto_approval_rule'] );
	}

	/**
	 * Test that 'score' mode approves topics meeting min score threshold and applies fallback.
	 */
	public function test_score_mode_threshold_and_fallback() {
		 = (object) array(
			'id'                            => 1,
			'name'                          => 'Test Author',
			'topic_auto_approval_mode'      => 'score',
			'topic_auto_approval_min_score' => 75,
			'topic_auto_approval_fallback'  => 'rejected',
		);

		 = array(
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

		 = ->generator->apply_auto_approval_rules( ,  );

		->assertEquals( 'approved', [0]['status'] );
		->assertEquals( 'rejected', [1]['status'] );

		 = json_decode( [0]['metadata'], true );
		->assertTrue( ['auto_approved'] );
		->assertEquals( 80, ['auto_approval_score'] );

		 = json_decode( [1]['metadata'], true );
		->assertTrue( ['auto_rejected'] );
	}

	/**
	 * Test that 'similarity' mode approves topics with low duplicate similarity and keeps duplicates pending.
	 */
	public function test_similarity_mode_threshold_and_pending_fallback() {
		 = (object) array(
			'id'                                 => 1,
			'name'                               => 'Test Author',
			'topic_auto_approval_mode'           => 'similarity',
			'topic_auto_approval_max_similarity' => 0.80,
			'topic_auto_approval_fallback'       => 'pending',
		);

		 = array(
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

		 = ->generator->apply_auto_approval_rules( ,  );

		->assertEquals( 'approved', [0]['status'] );
		->assertEquals( 'pending', [1]['status'] );

		 = json_decode( [0]['metadata'], true );
		->assertTrue( ['auto_approved'] );
		->assertEquals( 0.25, ['auto_approval_similarity'] );

		 = json_decode( [1]['metadata'], true );
		->assertTrue( ['auto_approval_evaluated'] );
	}
}
