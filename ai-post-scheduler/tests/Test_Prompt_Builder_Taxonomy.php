<?php
/**
 * Tests for the Taxonomy Prompt Builder.
 *
 * @package AI_Post_Scheduler
 */

class Test_Prompt_Builder_Taxonomy extends WP_UnitTestCase {

	/**
	 * Prompt should reflect category generation requirements and post summaries.
	 */
	public function test_build_includes_category_context_and_posts() {
		$builder = new AIPS_Prompt_Builder_Taxonomy();

		$prompt = $builder->build(
			'category',
			array(
				array(
					'title' => 'WordPress Performance Tips',
					'excerpt' => 'A practical guide to improving page speed.',
				),
			),
			'Focus on actionable site architecture terms.'
		);

		$this->assertStringContainsString('generate appropriate categories for a WordPress site', $prompt);
		$this->assertStringContainsString('Title: WordPress Performance Tips', $prompt);
		$this->assertStringContainsString('Excerpt: A practical guide to improving page speed.', $prompt);
		$this->assertStringContainsString('Additional instructions: Focus on actionable site architecture terms.', $prompt);
	}

	/**
	 * Prompt should normalize non-category requests to tag guidance.
	 */
	public function test_build_defaults_to_tag_guidance_for_non_category_types() {
		$builder = new AIPS_Prompt_Builder_Taxonomy();

		$prompt = $builder->build(
			'post_tag',
			array(
				array(
					'title' => 'AI Content Workflow',
					'excerpt' => '',
				),
			)
		);

		$this->assertStringContainsString('generate appropriate tags for a WordPress site', $prompt);
		$this->assertStringContainsString('Return only the tags names, one per line', $prompt);
	}
}