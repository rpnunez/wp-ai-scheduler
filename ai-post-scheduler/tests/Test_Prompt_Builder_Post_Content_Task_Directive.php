<?php
/**
 * Tests for AIPS_Prompt_Builder_Post_Content::build_task_directive_from_context()
 *
 * Covers the new composer-oriented entry point, which must include
 * per-context content (topic substitution, content images, related
 * context) but omit what middlewares now handle instead: voice
 * instructions, diversity blocks, and the aips_content_prompt filter.
 *
 * @package AI_Post_Scheduler
 */

class Test_Prompt_Builder_Post_Content_Task_Directive extends WP_UnitTestCase {

	/**
	 * @var AIPS_Prompt_Builder_Post_Content
	 */
	private $builder;

	public function setUp(): void {
		parent::setUp();
		$template_processor = new AIPS_Template_Processor();
		$structure_manager  = new AIPS_Article_Structure_Manager();
		$section_builder    = new AIPS_Prompt_Builder_Article_Structure_Section(
			$structure_manager,
			null,
			$template_processor
		);
		$this->builder = new AIPS_Prompt_Builder_Post_Content($template_processor, $section_builder);
	}

	public function tearDown(): void {
		remove_all_filters('aips_content_prompt');
		parent::tearDown();
	}

	public function test_applies_topic_substitution() {
		$template = (object) array(
			'id'                   => 1,
			'prompt_template'      => 'Deep dive into {{topic}}.',
			'article_structure_id' => null,
		);
		$context = new AIPS_Template_Context($template, null, 'Microservices');

		$result = $this->builder->build_task_directive_from_context($context);

		$this->assertStringContainsString('Microservices', $result);
	}

	public function test_omits_voice_instructions() {
		$template = (object) array(
			'id'                   => 1,
			'prompt_template'      => 'Write about {{topic}}.',
			'article_structure_id' => null,
		);
		$voice = (object) array(
			'id'                   => 5,
			'content_instructions' => 'Voice instructions for {{topic}}.',
		);
		$context = new AIPS_Template_Context($template, $voice, 'GitOps');

		$result = $this->builder->build_task_directive_from_context($context);

		$this->assertStringNotContainsString('Voice instructions', $result);
	}

	public function test_omits_aips_content_prompt_filter() {
		add_filter('aips_content_prompt', function ($prompt) {
			return $prompt . "\nFILTERED";
		});

		$template = (object) array(
			'id'                   => 1,
			'prompt_template'      => 'Write about {{topic}}.',
			'article_structure_id' => null,
		);
		$context = new AIPS_Template_Context($template, null, 'Observability');

		$result = $this->builder->build_task_directive_from_context($context);

		$this->assertStringNotContainsString('FILTERED', $result);
	}

	public function test_resets_sources_selection() {
		$this->builder->set_sources_selection(array('block' => 'stale', 'snapshots' => array('x')));

		$template = (object) array(
			'id'                   => 1,
			'prompt_template'      => 'Write about {{topic}}.',
			'article_structure_id' => null,
		);
		$context = new AIPS_Template_Context($template, null, 'Topic');

		$this->builder->build_task_directive_from_context($context);

		$this->assertSame(array(), $this->builder->get_sources_selection());
	}

	public function test_topic_context_returns_content_prompt() {
		$author = (object) array('id' => 1, 'name' => 'Bob', 'field_niche' => 'DevOps');
		$topic = (object) array(
			'id'           => 9,
			'topic_title'  => 'Helm Charts Best Practices',
			'topic_prompt' => 'Explain Helm chart packaging.',
		);
		$context = new AIPS_Topic_Context($author, $topic);

		$result = $this->builder->build_task_directive_from_context($context);

		$this->assertStringContainsString('Helm Charts Best Practices', $result);
	}
}
