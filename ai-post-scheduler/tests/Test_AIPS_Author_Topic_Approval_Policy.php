<?php
/**
 * Tests for AIPS_Author_Topic_Approval_Policy and policy-driven evaluation.
 *
 * @package AI_Post_Scheduler
 */

class Test_AIPS_Author_Topic_Approval_Policy extends WP_UnitTestCase {

	private $options = array(
		'aips_author_topic_auto_approval_mode',
		'aips_author_topic_auto_approval_min_score',
		'aips_author_topic_auto_approval_min_relevance',
		'aips_author_topic_auto_approval_max_similarity',
		'aips_author_topic_auto_approval_fallback',
	);

	public function tearDown(): void {
		foreach ($this->options as $option) {
			delete_option($option);
		}
		parent::tearDown();
	}

	private function set_global($mode, $extra = array()) {
		update_option('aips_author_topic_auto_approval_mode', $mode);
		foreach ($extra as $key => $value) {
			update_option('aips_author_topic_auto_approval_' . $key, $value);
		}
	}

	public function test_inherit_uses_global_options() {
		$this->set_global('score', array(
			'min_score'      => 80,
			'min_relevance'  => 50,
			'max_similarity' => 0.9,
			'fallback'       => 'rejected',
		));

		$policy = AIPS_Author_Topic_Approval_Policy::resolve((object) array('topic_auto_approval_mode' => 'inherit'));

		$this->assertSame('global', $policy['source']);
		$this->assertSame('score', $policy['mode']);
		$this->assertSame(80, $policy['min_score']);
		$this->assertEqualsWithDelta(0.5, $policy['min_relevance'], 0.0001);
		$this->assertEqualsWithDelta(0.9, $policy['max_similarity'], 0.0001);
		$this->assertSame('rejected', $policy['fallback']);
	}

	public function test_empty_mode_inherits() {
		$this->set_global('all');
		$policy = AIPS_Author_Topic_Approval_Policy::resolve((object) array());
		$this->assertSame('global', $policy['source']);
		$this->assertSame('all', $policy['mode']);
	}

	public function test_global_defaults_to_manual() {
		$policy = AIPS_Author_Topic_Approval_Policy::resolve((object) array('topic_auto_approval_mode' => 'inherit'));
		$this->assertSame('manual', $policy['mode']);
		$this->assertSame('smart_split', $policy['fallback']);
		$this->assertSame(70, $policy['min_score']);
	}

	public function test_custom_author_uses_author_columns() {
		$this->set_global('all');
		$policy = AIPS_Author_Topic_Approval_Policy::resolve((object) array(
			'topic_auto_approval_mode'          => 'similarity',
			'topic_auto_approval_min_score'     => 55,
			'topic_auto_approval_min_relevance' => 40,
			'topic_auto_approval_max_similarity' => 0.7,
			'topic_auto_approval_fallback'      => 'pending',
		));

		$this->assertSame('author', $policy['source']);
		$this->assertSame('similarity', $policy['mode']);
		$this->assertSame(55, $policy['min_score']);
		$this->assertEqualsWithDelta(0.4, $policy['min_relevance'], 0.0001);
		$this->assertEqualsWithDelta(0.7, $policy['max_similarity'], 0.0001);
		$this->assertSame('pending', $policy['fallback']);
	}

	public function test_legacy_values_are_normalized() {
		$this->assertSame('similarity', AIPS_Author_Topic_Approval_Policy::normalize_mode('embeddings'));
		$this->assertSame('manual', AIPS_Author_Topic_Approval_Policy::normalize_mode('none'));
		$this->assertSame('rejected', AIPS_Author_Topic_Approval_Policy::normalize_fallback('reject'));
	}

	public function test_invalid_values_fall_back_safely() {
		$this->assertSame('manual', AIPS_Author_Topic_Approval_Policy::normalize_mode('bogus'));
		$this->assertSame('pending', AIPS_Author_Topic_Approval_Policy::normalize_fallback('bogus'));

		$policy = AIPS_Author_Topic_Approval_Policy::resolve((object) array(
			'topic_auto_approval_mode'          => 'all',
			'topic_auto_approval_min_score'     => 500,
			'topic_auto_approval_min_relevance' => 0,
			'topic_auto_approval_max_similarity' => 5,
		));
		$this->assertSame(100, $policy['min_score']);
		$this->assertEqualsWithDelta(0.01, $policy['min_relevance'], 0.0001);
		$this->assertEqualsWithDelta(0.99, $policy['max_similarity'], 0.0001);
	}

	public function test_evaluator_with_explicit_policy_for_score_and_fallbacks() {
		$evaluator = new AIPS_Similarity_Evaluator();
		$author    = (object) array('topic_auto_approval_mode' => 'manual');
		$base      = array('mode' => 'score', 'min_score' => 70, 'min_relevance' => 0.65, 'max_similarity' => 0.85, 'source' => 'global');
		$topic     = array('topic_title' => 'T', 'score' => 60, 'metadata' => array());

		$met = $evaluator->evaluate_author_topic_auto_approval(array_merge($topic, array('score' => 90)), $author, null, $base + array('fallback' => 'pending'));
		$this->assertSame('approved', $met['decision']);
		$this->assertSame('global', $met['meta']['auto_approval_source']);

		$pending = $evaluator->evaluate_author_topic_auto_approval($topic, $author, null, $base + array('fallback' => 'pending'));
		$this->assertSame('pending', $pending['decision']);

		$rejected = $evaluator->evaluate_author_topic_auto_approval($topic, $author, null, $base + array('fallback' => 'rejected'));
		$this->assertSame('rejected', $rejected['decision']);

		$split = $evaluator->evaluate_author_topic_auto_approval($topic, $author, null, $base + array('fallback' => 'smart_split'));
		$this->assertSame('pending', $split['decision']);
	}

	public function test_evaluator_similarity_duplicate_rejected_under_smart_split() {
		$evaluator = new AIPS_Similarity_Evaluator();
		$policy    = array('mode' => 'similarity', 'min_score' => 70, 'min_relevance' => 0.65, 'max_similarity' => 0.85, 'fallback' => 'smart_split', 'source' => 'author');
		$topic     = array('topic_title' => 'T', 'metadata' => array('duplicate_similarity' => 0.95));

		$result = $evaluator->evaluate_author_topic_auto_approval($topic, (object) array(), null, $policy);
		$this->assertSame('rejected', $result['decision']);
		$this->assertTrue($result['is_dup_rejection']);
	}

	public function test_generator_inherit_with_global_all_approves() {
		$this->set_global('all');
		$generator = new AIPS_Author_Topics_Generator();
		$author    = (object) array('id' => 1, 'topic_auto_approval_mode' => 'inherit');
		$topics    = array(array('topic_title' => 'A', 'score' => 10, 'metadata' => wp_json_encode(array())));

		$result = $generator->apply_auto_approval_rules($author, $topics);

		$this->assertSame('approved', $result[0]['status']);
		$meta = json_decode($result[0]['metadata'], true);
		$this->assertSame('global', $meta['auto_approval_source']);
	}

	public function test_generator_inherit_with_global_manual_stays_pending() {
		$this->set_global('manual');
		$generator = new AIPS_Author_Topics_Generator();
		$author    = (object) array('id' => 1, 'topic_auto_approval_mode' => 'inherit');
		$topics    = array(array('topic_title' => 'A', 'score' => 99, 'metadata' => wp_json_encode(array())));

		$result = $generator->apply_auto_approval_rules($author, $topics);

		$this->assertArrayNotHasKey('status', $result[0]);
	}

	public function test_generator_inherit_with_global_score_threshold() {
		$this->set_global('score', array('min_score' => 75, 'fallback' => 'rejected'));
		$generator = new AIPS_Author_Topics_Generator();
		$author    = (object) array('id' => 1, 'topic_auto_approval_mode' => 'inherit');
		$topics    = array(
			array('topic_title' => 'High', 'score' => 80, 'metadata' => wp_json_encode(array())),
			array('topic_title' => 'Low', 'score' => 50, 'metadata' => wp_json_encode(array())),
		);

		$result = $generator->apply_auto_approval_rules($author, $topics);

		$this->assertSame('approved', $result[0]['status']);
		$this->assertSame('rejected', $result[1]['status']);
	}

	public function test_hidden_option_family_is_not_in_defaults() {
		$defaults = AIPS_Config::get_instance()->get_default_options();
		foreach (array('aips_author_topic_auto_approval_enabled', 'aips_author_topic_approval_mode', 'aips_author_topic_min_relevance', 'aips_author_topic_max_duplicate', 'aips_author_topic_fallback_action') as $legacy) {
			$this->assertArrayNotHasKey($legacy, $defaults);
		}
		$this->assertArrayHasKey('aips_author_topic_auto_approval_min_relevance', $defaults);
	}

	public function test_settings_sanitizers_reject_unknown_values() {
		$ui = new AIPS_Settings_UI();
		$this->assertSame('manual', $ui->sanitize_author_topic_auto_approval_mode('nope'));
		$this->assertSame('score', $ui->sanitize_author_topic_auto_approval_mode('score'));
		$this->assertSame('pending', $ui->sanitize_author_topic_auto_approval_fallback('nope'));
		$this->assertSame('rejected', $ui->sanitize_author_topic_auto_approval_fallback('reject'));
		$this->assertSame(100, $ui->sanitize_author_topic_auto_approval_percent(250));
		$this->assertEqualsWithDelta(0.5, $ui->sanitize_author_topic_auto_approval_max_similarity(0.1), 0.0001);
	}

	public function test_describe_global_omits_fallback_for_all_mode() {
		$this->set_global('all', array('fallback' => 'rejected'));
		$this->assertSame('Auto-approve all topics', AIPS_Author_Topic_Approval_Policy::describe_global());

		$this->set_global('score', array('min_score' => 80, 'fallback' => 'rejected'));
		$this->assertStringContainsString('others are rejected', AIPS_Author_Topic_Approval_Policy::describe_global());
	}
}
