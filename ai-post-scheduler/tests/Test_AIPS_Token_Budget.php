<?php
/**
 * Tests for shared token budget calculations.
 *
 * @package AI_Post_Scheduler
 */

class Test_AIPS_Token_Budget extends WP_UnitTestCase {

	public function test_calculate_applies_prompt_estimate_and_buffer() {
		$result = AIPS_Token_Budget::calculate(
			'Prompt',
			4000,
			array(
				'buffer_ratio' => 0.25,
			)
		);

		$this->assertSame(5003, $result);
	}

	public function test_calculate_respects_config_limit() {
		$original_limit = get_option('aips_max_tokens_limit');
		update_option('aips_max_tokens_limit', 100);

		try {
			$result = AIPS_Token_Budget::calculate(
				'Some prompt',
				4000,
				array(
					'buffer_ratio'         => 0.25,
					'respect_config_limit' => true,
				)
			);

			$this->assertSame(100, $result);
		} finally {
			if (false === $original_limit) {
				delete_option('aips_max_tokens_limit');
			} else {
				update_option('aips_max_tokens_limit', $original_limit);
			}
		}
	}

	public function test_calculate_uses_lower_of_explicit_and_config_limits() {
		$original_limit = get_option('aips_max_tokens_limit');
		update_option('aips_max_tokens_limit', 12000);

		try {
			$result = AIPS_Token_Budget::calculate(
				str_repeat('A', 4000),
				10000,
				array(
					'minimum_tokens'       => 256,
					'maximum_tokens'       => 10000,
					'respect_config_limit' => true,
				)
			);

			$this->assertSame(10000, $result);
		} finally {
			if (false === $original_limit) {
				delete_option('aips_max_tokens_limit');
			} else {
				update_option('aips_max_tokens_limit', $original_limit);
			}
		}
	}

	public function test_internal_link_inserter_uses_shared_budget_with_feature_profile() {
		$service    = new AIPS_Internal_Link_Inserter_Service();
		$reflection = new ReflectionMethod($service, 'calculate_max_tokens');
		$reflection->setAccessible(true);

		$prompt   = str_repeat('A', 1400);
		$result   = $reflection->invokeArgs($service, array($prompt, 3));
		$tokens   = (int) ceil(strlen($prompt) / 4);
		$expected = $tokens + 180 + (3 * 220);

		$this->assertSame($expected, $result);
	}

	public function test_internal_link_inserter_budget_respects_global_limit() {
		$original_limit = get_option('aips_max_tokens_limit');
		update_option('aips_max_tokens_limit', 300);

		try {
			$service    = new AIPS_Internal_Link_Inserter_Service();
			$reflection = new ReflectionMethod($service, 'calculate_max_tokens');
			$reflection->setAccessible(true);

			$result = $reflection->invokeArgs($service, array(str_repeat('A', 4000), 3));

			$this->assertSame(300, $result);
		} finally {
			if (false === $original_limit) {
				delete_option('aips_max_tokens_limit');
			} else {
				update_option('aips_max_tokens_limit', $original_limit);
			}
		}
	}
}