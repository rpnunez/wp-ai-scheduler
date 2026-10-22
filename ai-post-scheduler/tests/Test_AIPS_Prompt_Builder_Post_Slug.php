<?php
/**
 * Tests for AIPS_Prompt_Builder_Post_Slug
 *
 * @package AI_Post_Scheduler
 */

class Test_AIPS_Prompt_Builder_Post_Slug extends WP_UnitTestCase {

	/**
	 * @var AIPS_Prompt_Builder_Post_Slug
	 */
	private $builder;

	public function setUp(): void {
		parent::setUp();
		$this->builder = new AIPS_Prompt_Builder_Post_Slug(new AIPS_Template_Processor());
	}

	public function tearDown(): void {
		remove_all_filters('aips_slug_prompt');
		parent::tearDown();
	}

	/**
	 * Default prompt contains SEO guidelines when no custom instructions are set.
	 */
	public function test_default_seo_guidelines_in_prompt() {
		$template = (object) array(
			'slug_prompt' => '',
		);

		$result = $this->builder->build($template, 'WordPress Development', null, 'Post content here.', 'WordPress Development Guide');

		$this->assertStringContainsString('Generate an SEO-friendly URL slug', $result);
		$this->assertStringContainsString('lowercase', $result);
		$this->assertStringContainsString('hyphens', $result);
		$this->assertStringContainsString('3 to 6 words', $result);
		$this->assertStringContainsString('WordPress Development Guide', $result);
	}

	/**
	 * Custom slug prompt replaces variables like {{topic}} and {{title}}.
	 */
	public function test_custom_slug_prompt_with_variables() {
		$template = (object) array(
			'slug_prompt' => 'Focus on {{topic}} and ensure it matches {{title}}.',
		);

		$result = $this->builder->build($template, 'AI Automation', null, 'Content...', 'Automating WordPress with AI');

		$this->assertStringContainsString('Focus on AI Automation and ensure it matches Automating WordPress with AI.', $result);
	}

	/**
	 * Voice slug_prompt overrides template slug_prompt.
	 */
	public function test_voice_slug_prompt_overrides_template() {
		$template = (object) array(
			'slug_prompt' => 'Template slug prompt for {{topic}}.',
		);
		$voice = (object) array(
			'slug_prompt' => 'Voice slug prompt for {{topic}}.',
		);

		$result = $this->builder->build($template, 'Cloud Computing', $voice, 'Content...');

		$this->assertStringContainsString('Voice slug prompt for Cloud Computing.', $result);
		$this->assertStringNotContainsString('Template slug prompt', $result);
	}

	/**
	 * Topic Context correctly resolves slug instructions from author.
	 */
	public function test_topic_context_slug_prompt() {
		$author = (object) array(
			'id'          => 1,
			'name'        => 'Jane Doe',
			'slug_prompt' => 'Short 2-3 word slug for {{topic}}',
		);
		$topic = (object) array(
			'id'          => 2,
			'topic_title' => 'Serverless Architecture',
		);
		$context = new AIPS_Topic_Context($author, $topic);

		$result = $this->builder->build($context, 'Serverless Architecture', null, 'Content...', 'Serverless Architecture 101');

		$this->assertStringContainsString('Short 2-3 word slug for Serverless Architecture', $result);
	}

	/**
	 * Filter aips_slug_prompt allows modifying the final prompt string.
	 */
	public function test_filter_aips_slug_prompt() {
		add_filter('aips_slug_prompt', function($prompt, $template, $topic) {
			return $prompt . ' [FILTERED]';
		}, 10, 3);

		$template = (object) array('slug_prompt' => '');
		$result = $this->builder->build($template, 'Testing Filters');

		$this->assertStringContainsString('[FILTERED]', $result);
	}

	/**
	 * Follow-up prompt builder includes base SEO guidelines and output contract.
	 */
	public function test_build_followup() {
		$template = (object) array('slug_prompt' => 'Keep it ultra brief.');
		$result = $this->builder->build_followup($template, 'Security');

		$this->assertStringContainsString('Generate an SEO-friendly URL slug for the article generated above', $result);
		$this->assertStringContainsString('Keep it ultra brief.', $result);
		$this->assertStringContainsString('Respond ONLY with the slug string', $result);
	}
}
