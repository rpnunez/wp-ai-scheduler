<?php
/**
 * Tests for the Author Suggestions Prompt Builder.
 *
 * @package AI_Post_Scheduler
 */

class Test_Prompt_Builder_Authors extends WP_UnitTestCase {

	/**
	 * Prompt should request the full author profile schema required by import.
	 */
	public function test_build_includes_all_required_author_suggestion_keys() {
		$builder = new AIPS_Prompt_Builder_Authors();

		$prompt = $builder->build(
			array(
				'site_niche' => 'WordPress Development',
			),
			2
		);

		$this->assertStringContainsString( '"details":', $prompt );
		$this->assertStringContainsString( '"target_audience":', $prompt );
		$this->assertStringContainsString( '"expertise_level":', $prompt );
		$this->assertStringContainsString( '"content_goals":', $prompt );
		$this->assertStringContainsString( '"preferred_content_length":', $prompt );
	}

	/**
	 * Prompt should constrain enum-like fields to supported option values.
	 */
	public function test_build_includes_supported_enum_value_guidance() {
		$builder = new AIPS_Prompt_Builder_Authors();

		$prompt = $builder->build(
			array(
				'site_niche' => 'Software Engineering',
			),
			1
		);

		$this->assertStringContainsString( 'one of: beginner, intermediate, expert, thought_leader', $prompt );
		$this->assertStringContainsString( 'one of: short, medium, long', $prompt );
	}
}
