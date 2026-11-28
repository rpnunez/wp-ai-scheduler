<?php
/**
 * Golden Tests for AIPS_Prompt_Builder_Post_Title
 *
 * @package AI_Post_Scheduler
 */

require_once __DIR__ . '/helpers/class-aips-fake-ai-provider.php';
require_once __DIR__ . '/helpers/class-aips-golden-helper.php';

/**
 * Class Test_AIPS_Prompt_Builder_Post_Title_Golden
 */
class Test_AIPS_Prompt_Builder_Post_Title_Golden extends WP_UnitTestCase {

	/**
	 * @var AIPS_Prompt_Builder_Post_Title
	 */
	private $builder;

	/**
	 * Set up test environment with pinned template variables and site settings.
	 */
	public function setUp(): void {
		parent::setUp();

		update_option('blogname', 'Test WordPress Site');
		update_option('blogdescription', 'A testing site for AI Post Scheduler');
		update_option('timezone_string', 'UTC');

		add_filter('aips_template_variables', array($this, 'pin_template_variables'));

		$this->builder = new AIPS_Prompt_Builder_Post_Title();
	}

	/**
	 * Clean up test environment and remove filters.
	 */
	public function tearDown(): void {
		remove_filter('aips_template_variables', array($this, 'pin_template_variables'));

		parent::tearDown();
	}

	/**
	 * Pin dynamic template variables to deterministic fixed values.
	 *
	 * @param array $vars Dynamic template variables.
	 * @return array
	 */
	public function pin_template_variables(array $vars): array {
		$vars['date']             = '2026-10-12';
		$vars['year']             = '2026';
		$vars['month']            = '10';
		$vars['day']              = '12';
		$vars['time']             = '12:00:00';
		$vars['random_number']    = '42';
		$vars['site_name']        = 'Test WordPress Site';
		$vars['site_description'] = 'A testing site for AI Post Scheduler';

		return $vars;
	}

	/**
	 * Test title prompt generation for a standard AIPS_Template_Context flow.
	 */
	public function test_golden_template_standard(): void {
		$template_data = (object) array(
			'id'           => 101,
			'name'         => 'Tech Insight Template',
			'title_prompt' => 'Craft a compelling headline for {{topic}} that engages software engineers.',
			'voice_id'     => 0,
		);

		$context = new AIPS_Template_Context($template_data, null, 'Artificial Intelligence in PHP 8.2');

		$article_content = "Artificial Intelligence is transforming PHP 8.2 development. " .
			"Developers can build autonomous agents, leverage static analysis tools, and write cleaner, " .
			"more resilient web applications.";

		$prompt = $this->builder->build($context, 'Artificial Intelligence in PHP 8.2', null, $article_content);

		$golden_path = __DIR__ . '/golden/prompt-builder-post-title/template_standard.golden.txt';
		AIPS_Golden_Helper::assert_golden($this, $golden_path, $prompt, 'Standard template context title prompt generation');
	}

	/**
	 * Test title prompt generation when voice title prompt overrides template title prompt.
	 */
	public function test_golden_voice_override(): void {
		$voice = (object) array(
			'id'           => 5,
			'name'         => 'Executive Editor Voice',
			'title_prompt' => 'Generate an authoritative, punchy title focusing on executive strategy for {{topic}}.',
		);

		$template_data = (object) array(
			'id'           => 202,
			'name'         => 'Strategy Post Template',
			'title_prompt' => 'Default template title prompt for {{topic}}.',
			'voice_id'     => 5,
			'voice'        => $voice,
		);

		$context = new AIPS_Template_Context($template_data, $voice, 'Enterprise AI Adoption');

		$article_content = "Enterprise AI adoption requires clear executive leadership, robust governance models, " .
			"and strategic alignment across engineering and business units.";

		$prompt = $this->builder->build($context, 'Enterprise AI Adoption', $voice, $article_content);

		$golden_path = __DIR__ . '/golden/prompt-builder-post-title/voice_override.golden.txt';
		AIPS_Golden_Helper::assert_golden($this, $golden_path, $prompt, 'Voice title prompt override flow');
	}

	/**
	 * Test multi-turn follow-up title prompt generation.
	 */
	public function test_golden_followup_conversation(): void {
		$template_data = (object) array(
			'id'           => 303,
			'name'         => 'Conversational Template',
			'title_prompt' => 'Create a captivating headline for {{topic}}.',
			'voice_id'     => 0,
		);

		$context = new AIPS_Template_Context($template_data, null, 'Modern Web Performance');

		$prompt = $this->builder->build_followup($context);

		$golden_path = __DIR__ . '/golden/prompt-builder-post-title/followup_conversation.golden.txt';
		AIPS_Golden_Helper::assert_golden($this, $golden_path, $prompt, 'Multi-turn follow-up title prompt generation');
	}
}
