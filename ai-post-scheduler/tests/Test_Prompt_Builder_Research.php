<?php
/**
 * Tests for AIPS_Prompt_Builder_Research.
 *
 * @package AI_Post_Scheduler
 * @subpackage Tests
 */

class Test_Prompt_Builder_Research extends WP_UnitTestCase {

	/**
	 * Test trending-topic research prompt contract.
	 */
	public function test_build_trending_topics_prompt_includes_research_contract() {
		$builder = new AIPS_Prompt_Builder_Research();

		$prompt = $builder->build_trending_topics_prompt(
			'Digital Marketing',
			7,
			array( 'SEO', 'content strategy' )
		);

		$this->assertStringContainsString( "analyzing trending topics for 'Digital Marketing'", $prompt );
		$this->assertStringContainsString( 'Identify the top 7 most trending', $prompt );
		$this->assertStringContainsString( 'Focus areas: SEO, content strategy', $prompt );
		$this->assertStringContainsString( 'Current events and news in ' . AIPS_DateTime::now()->toDisplay( 'Y' ), $prompt );
		$this->assertStringContainsString( '"topic": The topic/title (string)', $prompt );
		$this->assertStringContainsString( '"score": Relevance score 1-100 (integer)', $prompt );
		$this->assertStringEndsWith( 'Return ONLY the JSON array. No markdown, no explanations, no code blocks.', $prompt );
	}

	/**
	 * Test source-grounded research prompt contract.
	 */
	public function test_build_source_research_prompt_includes_source_grounding_contract() {
		$builder = new AIPS_Prompt_Builder_Research();

		$prompt = $builder->build_source_research_prompt(
			'WordPress Security',
			3,
			array( 'plugins', 'hardening' ),
			"--- Source: Example ---\nImportant source insight."
		);

		$this->assertStringContainsString( "identify 3 specific, high-value blog post topics for the 'WordPress Security' niche", $prompt );
		$this->assertStringContainsString( 'Additional focus keywords: plugins, hardening', $prompt );
		$this->assertStringContainsString( "SOURCE MATERIAL:\n--- Source: Example ---\nImportant source insight.", $prompt );
		$this->assertStringContainsString( 'Ground your topic suggestions in the specific facts, trends, and insights from the sources above.', $prompt );
		$this->assertStringContainsString( '"reason": Why it\'s relevant to the source material (max 100 chars, string)', $prompt );
		$this->assertStringEndsWith( 'Return ONLY the JSON array. No markdown, no explanations, no code blocks.', $prompt );
	}
}
