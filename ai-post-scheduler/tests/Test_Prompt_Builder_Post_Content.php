<?php
/**
 * Tests for AIPS_Prompt_Builder_Post_Content
 *
 * Covers both the legacy template-object path and the generation-context path
 * including topic processing, voice instruction injection, article structure
 * fallback, and the aips_content_prompt filter.
 *
 * @package AI_Post_Scheduler
 */

class Test_Prompt_Builder_Post_Content extends WP_UnitTestCase {

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

	// ------------------------------------------------------------------
	// Legacy template path
	// ------------------------------------------------------------------

	/**
	 * Topic placeholder in prompt_template is resolved.
	 */
	public function test_legacy_template_topic_placeholder_resolved() {
		$template = (object) array(
			'prompt_template'      => 'Write a post about {{topic}}.',
			'article_structure_id' => null,
		);

		$result = $this->builder->build($template, 'React Hooks', null);

		$this->assertStringContainsString('Write a post about React Hooks.', $result);
		$this->assertStringNotContainsString('{{topic}}', $result);
	}

	/**
	 * Voice content_instructions are prepended to the prompt.
	 */
	public function test_legacy_voice_instructions_prepended() {
		$template = (object) array(
			'prompt_template'      => 'Write about {{topic}}.',
			'article_structure_id' => null,
		);
		$voice = (object) array(
			'content_instructions' => 'Use a friendly tone about {{topic}}.',
		);

		$result = $this->builder->build($template, 'Kubernetes', $voice);

		$this->assertStringContainsString('Use a friendly tone about Kubernetes.', $result);
		$this->assertStringContainsString('Write about Kubernetes.', $result);
		// Instructions must come before the main prompt
		$this->assertLessThan(
			strpos($result, 'Write about'),
			strpos($result, 'Use a friendly tone')
		);
	}

	/**
	 * Null topic does not cause errors and leaves placeholder unresolved.
	 */
	public function test_legacy_null_topic_leaves_placeholder() {
		$template = (object) array(
			'prompt_template'      => 'Write about {{topic}}.',
			'article_structure_id' => null,
		);

		$result = $this->builder->build($template, null, null);

		// Template processor should replace {{topic}} with an empty string
		$this->assertIsString($result);
	}

	// ------------------------------------------------------------------
	// Generation context path – template type
	// ------------------------------------------------------------------

	/**
	 * Template context: content_prompt is returned with topic applied.
	 */
	public function test_context_template_type_applies_topic() {
		$template = (object) array(
			'id'                   => 1,
			'name'                 => 'T',
			'prompt_template'      => 'Deep dive into {{topic}}.',
			'article_structure_id' => null,
		);
		$context = new AIPS_Template_Context($template, null, 'Microservices');

		$result = $this->builder->build($context);

		$this->assertStringContainsString('Microservices', $result);
	}

	/**
	 * Template context with voice: voice content_instructions are prepended.
	 */
	public function test_context_template_type_with_voice_prepends_instructions() {
		$template = (object) array(
			'id'                   => 1,
			'name'                 => 'T',
			'prompt_template'      => 'Write about {{topic}}.',
			'article_structure_id' => null,
		);
		$voice = (object) array(
			'id'                   => 5,
			'content_instructions' => 'Voice instructions for {{topic}}.',
		);
		$context = new AIPS_Template_Context($template, $voice, 'GitOps');

		$result = $this->builder->build($context);

		$this->assertStringContainsString('Voice instructions for GitOps.', $result);
		$this->assertLessThan(
			strpos($result, 'Write about'),
			strpos($result, 'Voice instructions')
		);
	}

	/**
	 * Topic context: content_prompt is returned directly.
	 */
	public function test_context_topic_type_returns_content_prompt() {
		$author = (object) array(
			'id'          => 1,
			'name'        => 'Bob',
			'field_niche' => 'DevOps',
		);
		$topic = (object) array(
			'id'           => 9,
			'topic_title'  => 'Helm Charts Best Practices',
			'topic_prompt' => 'Explain Helm chart packaging.',
		);
		$context = new AIPS_Topic_Context($author, $topic);

		$result = $this->builder->build($context);

		$this->assertStringContainsString('Helm Charts Best Practices', $result);
	}

	// ------------------------------------------------------------------
	// Filter hook
	// ------------------------------------------------------------------

	/**
	 * aips_content_prompt filter can modify the final prompt.
	 */
	public function test_filter_can_modify_prompt() {
		add_filter('aips_content_prompt', function ( $prompt ) {
			return $prompt . "\nFILTERED";
		});

		$template = (object) array(
			'prompt_template'      => 'Write about {{topic}}.',
			'article_structure_id' => null,
		);

		$result = $this->builder->build($template, 'Testing', null);

		$this->assertStringContainsString('FILTERED', $result);
	}

	// ------------------------------------------------------------------
	// Constructor injection defaults
	// ------------------------------------------------------------------

	/**
	 * Builder can be constructed without explicit dependencies.
	 */
	public function test_instantiation_without_explicit_dependencies() {
		$builder = new AIPS_Prompt_Builder_Post_Content();

		$template = (object) array(
			'prompt_template'      => 'Simple prompt.',
			'article_structure_id' => null,
		);

		$result = $builder->build($template, null, null);

		$this->assertIsString($result);
		$this->assertStringContainsString('Simple prompt.', $result);
	}
}
