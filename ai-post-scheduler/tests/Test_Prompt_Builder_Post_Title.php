<?php
/**
 * Tests for AIPS_Prompt_Builder_Post_Title
 *
 * Covers the dedicated post-title prompt builder in isolation: legacy template
 * objects, generation context objects (template and topic types), voice
 * overrides, empty-content handling, and the aips_title_prompt filter.
 *
 * @package AI_Post_Scheduler
 */

class Test_Prompt_Builder_Post_Title extends WP_UnitTestCase {

	/**
	 * @var AIPS_Prompt_Builder_Post_Title
	 */
	private $builder;

	public function setUp(): void {
		parent::setUp();
		$this->builder = new AIPS_Prompt_Builder_Post_Title(new AIPS_Template_Processor());
	}

	public function tearDown(): void {
		remove_all_filters('aips_title_prompt');
		parent::tearDown();
	}

	// ------------------------------------------------------------------
	// Legacy template object path
	// ------------------------------------------------------------------

	/**
	 * Template title_prompt is used when no voice is supplied.
	 */
	public function test_legacy_template_only_includes_title_instructions() {
		$template = (object) array(
			'title_prompt' => 'Write a definitive guide title about {{topic}}.',
		);

		$result = $this->builder->build($template, 'PHP', null, 'Article body.');

		$this->assertStringContainsString('Generate a title for a blog post', $result);
		$this->assertStringContainsString('Write a definitive guide title about PHP.', $result);
		$this->assertStringContainsString('Article body.', $result);
	}

	/**
	 * Voice title_prompt overrides the template title_prompt.
	 */
	public function test_legacy_voice_overrides_template_title_prompt() {
		$template = (object) array(
			'title_prompt' => 'Template title prompt for {{topic}}.',
		);
		$voice = (object) array(
			'title_prompt' => 'Voice-specific title for {{topic}}.',
		);

		$result = $this->builder->build($template, 'DevOps', $voice, 'Body text.');

		$this->assertStringContainsString('Voice-specific title for DevOps.', $result);
		$this->assertStringNotContainsString('Template title prompt', $result);
	}

	/**
	 * When neither template nor voice has a title_prompt, only the base
	 * instruction and the content are present.
	 */
	public function test_legacy_no_title_instructions() {
		$template = (object) array(
			'title_prompt' => '',
		);

		$result = $this->builder->build($template, 'SEO', null, 'Some content.');

		$this->assertStringContainsString('Generate a title for a blog post', $result);
		$this->assertStringContainsString('Here is the content:', $result);
		$this->assertStringNotContainsString('Here are your instructions', $result);
	}

	/**
	 * Content string is always appended to the prompt, even when empty.
	 */
	public function test_legacy_empty_content_still_included() {
		$template = (object) array(
			'title_prompt' => 'Some instruction.',
		);

		$result = $this->builder->build($template, 'Topic', null, '');

		$this->assertStringContainsString('Here is the content:', $result);
	}

	/**
	 * Topic placeholder in title_prompt is resolved by the template processor.
	 */
	public function test_legacy_topic_placeholder_resolved() {
		$template = (object) array(
			'title_prompt' => 'A guide for {{topic}} developers.',
		);

		$result = $this->builder->build($template, 'Rust', null, 'Content.');

		$this->assertStringContainsString('A guide for Rust developers.', $result);
		$this->assertStringNotContainsString('{{topic}}', $result);
	}

	// ------------------------------------------------------------------
	// Generation context path – template type
	// ------------------------------------------------------------------

	/**
	 * Template context without voice uses the template title_prompt.
	 */
	public function test_context_template_type_uses_template_title_prompt() {
		$template = (object) array(
			'id'           => 1,
			'name'         => 'Guide template',
			'title_prompt' => 'Authoritative guide title for {{topic}}.',
			'prompt_template' => 'Write about {{topic}}',
		);
		$context = new AIPS_Template_Context($template, null, 'Docker');

		$result = $this->builder->build($context, null, null, 'Post body here.');

		$this->assertStringContainsString('Generate a title for a blog post', $result);
		$this->assertStringContainsString('Authoritative guide title for Docker.', $result);
		$this->assertStringContainsString('Post body here.', $result);
	}

	/**
	 * Template context with a voice whose title_prompt takes precedence.
	 */
	public function test_context_template_type_voice_overrides_template() {
		$template = (object) array(
			'id'           => 1,
			'name'         => 'T',
			'title_prompt' => 'Template title for {{topic}}.',
			'prompt_template' => 'Write about {{topic}}',
		);
		$voice = (object) array(
			'id'           => 10,
			'title_prompt' => 'Voice title for {{topic}}.',
		);
		$context = new AIPS_Template_Context($template, $voice, 'Kubernetes');

		$result = $this->builder->build($context, null, null, 'Body.');

		$this->assertStringContainsString('Voice title for Kubernetes.', $result);
		$this->assertStringNotContainsString('Template title for', $result);
	}

	/**
	 * Template context: voice has no title_prompt so template title_prompt is used.
	 */
	public function test_context_template_type_voice_without_title_prompt_falls_back_to_template() {
		$template = (object) array(
			'id'           => 1,
			'name'         => 'T',
			'title_prompt' => 'Template title for {{topic}}.',
			'prompt_template' => 'Write about {{topic}}',
		);
		$voice = (object) array(
			'id'           => 10,
			// title_prompt intentionally absent
		);
		$context = new AIPS_Template_Context($template, $voice, 'CI/CD');

		$result = $this->builder->build($context, null, null, 'Body.');

		$this->assertStringContainsString('Template title for CI/CD.', $result);
	}

	// ------------------------------------------------------------------
	// Generation context path – topic type
	// ------------------------------------------------------------------

	/**
	 * Topic context uses the topic title_prompt from the topic object.
	 */
	public function test_context_topic_type_uses_topic_title_prompt() {
		$author = (object) array(
			'id'          => 1,
			'name'        => 'Alice',
			'field_niche' => 'Cloud Computing',
		);
		$topic = (object) array(
			'id'          => 5,
			'topic_title' => 'Serverless on AWS',
			'topic_prompt' => 'Describe serverless architectures.',
		);
		$context = new AIPS_Topic_Context($author, $topic);

		$result = $this->builder->build($context, null, null, 'Existing body.');

		$this->assertStringContainsString('Generate a title for a blog post', $result);
		$this->assertStringContainsString('Existing body.', $result);
	}

	// ------------------------------------------------------------------
	// Filter hook
	// ------------------------------------------------------------------

	/**
	 * aips_title_prompt filter can modify the final prompt.
	 */
	public function test_filter_can_modify_prompt() {
		add_filter('aips_title_prompt', function ( $prompt ) {
			return $prompt . "\nFILTERED";
		});

		$template = (object) array(
			'title_prompt' => 'Some instruction.',
		);

		$result = $this->builder->build($template, 'AI', null, 'Body.');

		$this->assertStringContainsString('FILTERED', $result);
	}
}
