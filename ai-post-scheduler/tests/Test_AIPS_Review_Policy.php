<?php
/**
 * Tests for review policy decisions.
 *
 * @package AI_Post_Scheduler
 */

class Test_AIPS_Review_Policy extends WP_UnitTestCase {

	public function setUp(): void {
		parent::setUp();
		$GLOBALS['aips_test_options'] = array();
		AIPS_Config::get_instance()->flush_option_cache();
	}

	public function test_disabled_policy_never_requires_review() {
		update_option('aips_review_policy_mode', 'disabled');
		$policy = new AIPS_Review_Policy();

		$decision = $policy->evaluate(array(
			'score' => 10,
			'has_critical_flags' => true,
			'critical_flags' => array('missing_content'),
			'flags' => array('missing_content'),
		));

		$this->assertFalse($decision['requires_review']);
		$this->assertSame('disabled', $decision['mode']);
	}

	public function test_always_policy_requires_review() {
		update_option('aips_review_policy_mode', 'always');
		$policy = new AIPS_Review_Policy();

		$decision = $policy->evaluate(array(
			'score' => 100,
			'has_critical_flags' => false,
			'critical_flags' => array(),
			'flags' => array(),
		));

		$this->assertTrue($decision['requires_review']);
		$this->assertSame('Manual review is required by policy.', $decision['reason']);
	}

	public function test_quality_gate_requires_review_below_threshold() {
		update_option('aips_review_policy_mode', 'quality_gate');
		update_option('aips_review_quality_threshold', 80);
		$policy = new AIPS_Review_Policy();

		$decision = $policy->evaluate(array(
			'score' => 72,
			'has_critical_flags' => false,
			'critical_flags' => array(),
			'flags' => array('thin_content'),
		));

		$this->assertTrue($decision['requires_review']);
		$this->assertSame('Quality score is below the configured threshold.', $decision['reason']);
	}

	public function test_quality_gate_allows_post_above_threshold_without_critical_flags() {
		update_option('aips_review_policy_mode', 'quality_gate');
		update_option('aips_review_quality_threshold', 80);
		$policy = new AIPS_Review_Policy();

		$decision = $policy->evaluate(array(
			'score' => 92,
			'has_critical_flags' => false,
			'critical_flags' => array(),
			'flags' => array(),
		));

		$this->assertFalse($decision['requires_review']);
	}

	public function test_partial_generation_requires_review_when_configured() {
		update_option('aips_review_policy_mode', 'quality_gate');
		update_option('aips_review_require_partial_generations', 1);
		$policy = new AIPS_Review_Policy();

		$decision = $policy->evaluate(array(
			'score' => 90,
			'has_critical_flags' => true,
			'critical_flags' => array('partial_generation'),
			'flags' => array('partial_generation'),
		));

		$this->assertTrue($decision['requires_review']);
		$this->assertSame('Generation completed with missing or failed components.', $decision['reason']);
	}
}
