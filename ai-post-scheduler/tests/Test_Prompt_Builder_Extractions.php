<?php
/**
 * Tests for extracted prompt builders used outside the generation pipeline.
 *
 * @package AI_Post_Scheduler
 * @subpackage Tests
 */

class Test_Prompt_Builder_Extractions extends WP_UnitTestCase {

	public function test_content_audit_builder_includes_gap_analysis_contract() {
		$builder = new AIPS_Prompt_Builder_Content_Audit();

		$prompt = $builder->build_gap_analysis_prompt(
			'Organic Gardening',
			array(
				array(
					'title'      => 'Beginner Composting',
					'categories' => 'Gardening',
				),
			)
		);

		$this->assertStringContainsString( "website's core niche is: Organic Gardening", $prompt );
		$this->assertStringContainsString( '- Beginner Composting (Category: Gardening)', $prompt );
		$this->assertStringContainsString( '"missing_topic": The title of the missing topic or cluster (string)', $prompt );
	}

	public function test_campaign_builder_includes_guided_setup_contract() {
		$builder = new AIPS_Prompt_Builder_Campaign();

		$prompt = $builder->build_guided_setup_prompt(
			array(
				'intake'                    => array( 'topic_niche' => 'Local SEO' ),
				'available_frequencies'     => array( 'daily' ),
				'available_post_types'      => array( 'post' ),
				'available_output_styles'   => array( 'how_to_guide' ),
				'review_policy_allowed'     => array( 'draft', 'approval', 'auto_publish' ),
				'campaign_mode_allowed'     => array( 'template', 'author' ),
			)
		);

		$this->assertStringContainsString( 'configure an AI content campaign', $prompt );
		$this->assertStringContainsString( '- campaign_name (string)', $prompt );
		$this->assertStringContainsString( 'use {{topic}}', $prompt );
		$this->assertStringContainsString( '"topic_niche":"Local SEO"', $prompt );
	}

	public function test_ai_assistance_builder_includes_field_context() {
		$builder = new AIPS_Prompt_Builder_AI_Assistance();

		$prompt = $builder->build(
			array(
				'field_name'        => 'Target Audience',
				'description'       => 'Who this author writes for.',
				'influence'         => 'Shapes article depth.',
				'current_value'     => 'Beginners',
				'author_name'       => 'Alex',
				'field_niche'       => 'WordPress',
				'expected_response' => 'one sentence',
			)
		);

		$this->assertStringContainsString( 'Field: Target Audience', $prompt );
		$this->assertStringContainsString( 'Author Name: Alex', $prompt );
		$this->assertStringContainsString( 'Expected format: one sentence', $prompt );
	}

	public function test_internal_link_builder_includes_replacement_rules() {
		$builder = new AIPS_Prompt_Builder_Internal_Link();

		$prompt = $builder->build(
			'This article mentions web server configuration adjustments.',
			'Server Hardening',
			'configuration adjustments',
			'https://example.com/server-hardening',
			2
		);

		$this->assertStringContainsString( 'Task: Find 2 insertion locations', $prompt );
		$this->assertStringContainsString( 'replacement_snippet must be EXACTLY match_snippet', $prompt );
		$this->assertStringContainsString( 'Target post title: Server Hardening', $prompt );
	}

	public function test_planner_builder_includes_topic_array_contract() {
		$builder = new AIPS_Prompt_Builder_Planner();

		$prompt = $builder->build_topics_prompt( 'Email Marketing', 4 );

		$this->assertStringContainsString( "Generate a list of 4 unique, engaging blog post titles/topics about 'Email Marketing'.", $prompt );
		$this->assertStringContainsString( 'Return ONLY a valid JSON array of strings', $prompt );
	}

	public function test_seeder_builder_includes_seed_prompt_contracts() {
		$builder = new AIPS_Prompt_Builder_Seeder();

		$voice_prompt    = $builder->build_voices_prompt( 2, 'SaaS' );
		$template_prompt = $builder->build_templates_prompt( 3, 'finance' );
		$planner_prompt  = $builder->build_planner_topics_prompt( 4, '' );

		$this->assertStringContainsString( 'Generate a list of 2 unique personas', $voice_prompt );
		$this->assertStringContainsString( 'Use the following keywords to inspire the personas: SaaS.', $voice_prompt );
		$this->assertStringContainsString( 'Generate a list of 3 blog post templates.', $template_prompt );
		$this->assertStringContainsString( 'templates should be relevant', $template_prompt );
		$this->assertStringContainsString( 'Topics should be about Technology, Lifestyle, or Business.', $planner_prompt );
	}

	public function test_ai_variables_builder_includes_json_contract() {
		$builder = new AIPS_Prompt_Builder_AI_Variables();

		$prompt = $builder->build(
			array( 'ProductA', 'ProductB' ),
			'Compare two project management tools.'
		);

		$this->assertStringContainsString( 'ProductA, ProductB', $prompt );
		$this->assertStringContainsString( 'Content Context:', $prompt );
		$this->assertStringContainsString( 'Respond ONLY with a JSON object', $prompt );
	}
}
