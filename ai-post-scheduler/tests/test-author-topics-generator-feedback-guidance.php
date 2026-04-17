<?php
/**
 * Tests for AIPS_Author_Topics_Generator feedback guidance prompt section.
 *
 * Validates that admin feedback reason patterns are correctly translated into
 * actionable guidance included in the topic-generation prompt.
 *
 * @package AI_Post_Scheduler
 */

class AIPS_Test_Stub_AI_Service_For_Feedback implements AIPS_AI_Service_Interface {
	public $last_prompt = '';
	private $topics;
	public function __construct( $t ) { $this->topics = $t; }
	public function generate_json( $prompt, $options = array() ) {
		$this->last_prompt = $prompt;
		return $this->topics;
	}
	public function is_available() { return true; }
	public function generate_text($prompt, $options = array()) { return ""; }
	public function generate_image($prompt, $options = array()) { return ""; }
	public function get_call_log() { return array(); }
}

class AIPS_Test_Stub_Logger_For_Feedback implements AIPS_Logger_Interface {
	public function log( $message, $level = 'info', $context = array() ) {}
	public function clear() {}
	public function get_logs($limit = 100, $offset = 0) { return array(); }
	public function set_level($level) {}
	public function addSeparator($text = "") {}
}

class Test_Author_Topics_Generator_Feedback_Guidance extends WP_UnitTestCase {

	/**
	 * Build a minimal author stub used by build_topic_generation_prompt().
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
	 * Build a stub topics repository that returns no existing topics.
	 *
	 * @return object
	 */
	private function make_topics_repository() {
		return new class {
			public $bulk_inserted = array();
			public function get_by_author( $author_id, $status = null ) { return array(); }
			public function create_bulk( $topics ) {
				$this->bulk_inserted = $topics;
				return true;
			}
			public function get_latest_by_author( $author_id, $limit, $after = null, $titles = null ) {
				return array();
			}
			public function get_approved_summary( $author_id, $limit = 20 ) { return array(); }
			public function get_rejected_summary( $author_id, $limit = 20 ) { return array(); }
		};
	}

	/**
	 * Build a stub feedback repository that returns the given statistics.
	 *
	 * @param array $stats Results returned by get_reason_category_statistics().
	 * @return object
	 */
	private function make_feedback_repository( $stats ) {
		return new class( $stats ) {
			private $stats;
			public function __construct( $s ) { $this->stats = $s; }
			public function get_reason_category_statistics( $author_id ) { return $this->stats; }
		};
	}

	/**
	 * Build a stub AI service that returns a list of topics.
	 *
	 * @param array $topics Topics to return from generate_json().
	 * @return object
	 */
	private function make_ai_service( $topics = array() ) {
		return new AIPS_Test_Stub_AI_Service_For_Feedback( $topics );
	}

	/**
	 * Build a no-op logger.
	 *
	 * @return object
	 */
	private function make_logger() {
		return new AIPS_Test_Stub_Logger_For_Feedback();
	}

	/**
	 * Build a no-op embeddings service that indicates embeddings are not supported.
	 *
	 * @return object
	 */
	private function make_embeddings_service() {
		return new class {
			public function is_embeddings_supported() { return false; }
		};
	}

	/**
	 * When there are no feedback stats, the guidance section is omitted from the prompt.
	 */
	public function test_no_guidance_when_no_feedback_stats() {
		$ai_service = $this->make_ai_service( array( array( 'title' => 'Topic A', 'score' => 50 ) ) );
		$topics_repo = $this->make_topics_repository();

		$generator = new AIPS_Author_Topics_Generator(
			$ai_service,
			$this->make_logger(),
			$topics_repo,
			new class { public function log_post_generation( $a, $b, $c ) {} },
			$this->make_embeddings_service(),
			$this->make_feedback_repository( array() )
		);

		$generator->generate_topics( $this->make_author() );

		$this->assertStringNotContainsString(
			'Quality guidance derived from admin feedback',
			$ai_service->last_prompt,
			'Guidance section should not appear when there is no feedback'
		);
	}

	/**
	 * Duplicate rejection pattern adds a "avoid duplicate" note to the prompt.
	 */
	public function test_duplicate_rejection_adds_guidance() {
		$ai_service = $this->make_ai_service( array( array( 'title' => 'Topic A', 'score' => 50 ) ) );
		$stats      = array(
			'duplicate' => array( 'rejected' => 3, 'approved' => 0 ),
		);

		$generator = new AIPS_Author_Topics_Generator(
			$ai_service,
			$this->make_logger(),
			$this->make_topics_repository(),
			new class { public function log_post_generation( $a, $b, $c ) {} },
			$this->make_embeddings_service(),
			$this->make_feedback_repository( $stats )
		);

		$generator->generate_topics( $this->make_author() );

		$this->assertStringContainsString(
			'Quality guidance derived from admin feedback',
			$ai_service->last_prompt
		);
		$this->assertStringContainsString(
			'similar or duplicate',
			$ai_service->last_prompt,
			'Duplicate rejection pattern should add duplicate avoidance note'
		);
	}

	/**
	 * Tone rejection pattern adds a tone-related note; voice_tone is referenced if set.
	 */
	public function test_tone_rejection_adds_guidance_with_voice_tone() {
		$ai_service = $this->make_ai_service( array( array( 'title' => 'Topic A', 'score' => 50 ) ) );
		$stats      = array(
			'tone' => array( 'rejected' => 2, 'approved' => 0 ),
		);

		$author = $this->make_author( array( 'voice_tone' => 'professional' ) );

		$generator = new AIPS_Author_Topics_Generator(
			$ai_service,
			$this->make_logger(),
			$this->make_topics_repository(),
			new class { public function log_post_generation( $a, $b, $c ) {} },
			$this->make_embeddings_service(),
			$this->make_feedback_repository( $stats )
		);

		$generator->generate_topics( $author );

		$this->assertStringContainsString(
			'professional',
			$ai_service->last_prompt,
			'Voice tone should be referenced in tone rejection guidance'
		);
	}

	/**
	 * Irrelevant rejection pattern references the author's field_niche.
	 */
	public function test_irrelevant_rejection_references_field_niche() {
		$ai_service = $this->make_ai_service( array( array( 'title' => 'Topic A', 'score' => 50 ) ) );
		$stats      = array(
			'irrelevant' => array( 'rejected' => 4, 'approved' => 0 ),
		);

		$author = $this->make_author( array( 'field_niche' => 'Machine Learning' ) );

		$generator = new AIPS_Author_Topics_Generator(
			$ai_service,
			$this->make_logger(),
			$this->make_topics_repository(),
			new class { public function log_post_generation( $a, $b, $c ) {} },
			$this->make_embeddings_service(),
			$this->make_feedback_repository( $stats )
		);

		$generator->generate_topics( $author );

		$this->assertStringContainsString(
			'Machine Learning',
			$ai_service->last_prompt,
			'field_niche should be referenced in irrelevant rejection guidance'
		);
	}

	/**
	 * Policy rejection pattern adds a policy-related warning to the prompt.
	 */
	public function test_policy_rejection_adds_guidance() {
		$ai_service = $this->make_ai_service( array( array( 'title' => 'Topic A', 'score' => 50 ) ) );
		$stats      = array(
			'policy' => array( 'rejected' => 1, 'approved' => 0 ),
		);

		$generator = new AIPS_Author_Topics_Generator(
			$ai_service,
			$this->make_logger(),
			$this->make_topics_repository(),
			new class { public function log_post_generation( $a, $b, $c ) {} },
			$this->make_embeddings_service(),
			$this->make_feedback_repository( $stats )
		);

		$generator->generate_topics( $this->make_author() );

		$this->assertStringContainsString(
			'policy',
			strtolower( $ai_service->last_prompt ),
			'Policy rejection pattern should add policy warning to prompt'
		);
	}

	/**
	 * Positive approval signals (e.g., approved with 'tone' reason) add encouraging notes.
	 */
	public function test_approval_pattern_adds_positive_guidance() {
		$ai_service = $this->make_ai_service( array( array( 'title' => 'Topic A', 'score' => 50 ) ) );
		$stats      = array(
			'tone' => array( 'rejected' => 0, 'approved' => 5 ),
		);

		$author = $this->make_author( array( 'voice_tone' => 'empathetic' ) );

		$generator = new AIPS_Author_Topics_Generator(
			$ai_service,
			$this->make_logger(),
			$this->make_topics_repository(),
			new class { public function log_post_generation( $a, $b, $c ) {} },
			$this->make_embeddings_service(),
			$this->make_feedback_repository( $stats )
		);

		$generator->generate_topics( $author );

		$this->assertStringContainsString(
			'Patterns that work well',
			$ai_service->last_prompt,
			'Approval patterns should add positive reinforcement section'
		);
	}

	/**
	 * Multiple rejection categories all contribute guidance notes.
	 */
	public function test_multiple_rejection_categories_all_add_guidance() {
		$ai_service = $this->make_ai_service( array( array( 'title' => 'Topic A', 'score' => 50 ) ) );
		$stats      = array(
			'duplicate'  => array( 'rejected' => 2, 'approved' => 0 ),
			'irrelevant' => array( 'rejected' => 3, 'approved' => 0 ),
			'policy'     => array( 'rejected' => 1, 'approved' => 0 ),
		);

		$generator = new AIPS_Author_Topics_Generator(
			$ai_service,
			$this->make_logger(),
			$this->make_topics_repository(),
			new class { public function log_post_generation( $a, $b, $c ) {} },
			$this->make_embeddings_service(),
			$this->make_feedback_repository( $stats )
		);

		$generator->generate_topics( $this->make_author() );

		$prompt = $ai_service->last_prompt;
		$this->assertStringContainsString( 'similar or duplicate', $prompt );
		$this->assertStringContainsString( 'WordPress Development', $prompt );
		$this->assertStringContainsString( 'policy', strtolower( $prompt ) );
	}
}
