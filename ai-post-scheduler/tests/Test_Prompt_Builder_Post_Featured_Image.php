<?php
/**
 * Tests for AIPS_Prompt_Builder_Post_Featured_Image
 *
 * Covers both the legacy template-object path and the generation-context path:
 * disabled image generation, wrong source type, missing prompts, topic
 * placeholder resolution, and context-level flag checks.
 *
 * @package AI_Post_Scheduler
 */

class Test_Prompt_Builder_Post_Featured_Image extends WP_UnitTestCase {

	/**
	 * @var AIPS_Prompt_Builder_Post_Featured_Image
	 */
	private $builder;

	public function setUp(): void {
		parent::setUp();
		$this->builder = new AIPS_Prompt_Builder_Post_Featured_Image(new AIPS_Template_Processor());
	}

	// ------------------------------------------------------------------
	// Legacy template path
	// ------------------------------------------------------------------

	/**
	 * Returns processed prompt when all conditions are met.
	 */
	public function test_legacy_returns_processed_prompt_when_enabled() {
		$template = (object) array(
			'generate_featured_image' => 1,
			'featured_image_source'   => 'ai_prompt',
			'image_prompt'            => 'A vivid illustration of {{topic}}.',
		);

		$result = $this->builder->build($template, 'Machine Learning');

		$this->assertSame('A vivid illustration of Machine Learning.', $result);
	}

	/**
	 * Returns empty string when generate_featured_image is false.
	 */
	public function test_legacy_returns_empty_when_generation_disabled() {
		$template = (object) array(
			'generate_featured_image' => 0,
			'featured_image_source'   => 'ai_prompt',
			'image_prompt'            => 'A photo of {{topic}}.',
		);

		$result = $this->builder->build($template, 'Testing');

		$this->assertSame('', $result);
	}

	/**
	 * Returns empty string when source is not ai_prompt.
	 */
	public function test_legacy_returns_empty_when_source_is_not_ai_prompt() {
		$template = (object) array(
			'generate_featured_image' => 1,
			'featured_image_source'   => 'unsplash',
			'image_prompt'            => 'A photo of {{topic}}.',
		);

		$result = $this->builder->build($template, 'Design');

		$this->assertSame('', $result);
	}

	/**
	 * Returns empty string when image_prompt is empty.
	 */
	public function test_legacy_returns_empty_when_image_prompt_missing() {
		$template = (object) array(
			'generate_featured_image' => 1,
			'featured_image_source'   => 'ai_prompt',
			'image_prompt'            => '',
		);

		$result = $this->builder->build($template, 'Architecture');

		$this->assertSame('', $result);
	}

	/**
	 * Topic placeholder in image_prompt is resolved.
	 */
	public function test_legacy_topic_placeholder_resolved() {
		$template = (object) array(
			'generate_featured_image' => 1,
			'featured_image_source'   => 'ai_prompt',
			'image_prompt'            => 'Cinematic shot of {{topic}} in action.',
		);

		$result = $this->builder->build($template, 'Kubernetes');

		$this->assertStringContainsString('Kubernetes', $result);
		$this->assertStringNotContainsString('{{topic}}', $result);
	}

	/**
	 * Null topic is handled gracefully.
	 */
	public function test_legacy_null_topic_handled_gracefully() {
		$template = (object) array(
			'generate_featured_image' => 1,
			'featured_image_source'   => 'ai_prompt',
			'image_prompt'            => 'An abstract image.',
		);

		$result = $this->builder->build($template, null);

		$this->assertSame('An abstract image.', $result);
	}

	// ------------------------------------------------------------------
	// Generation context path – template type
	// ------------------------------------------------------------------

	/**
	 * Template context: returns processed prompt when image generation is enabled.
	 */
	public function test_context_template_type_returns_prompt_when_enabled() {
		$template = (object) array(
			'id'                      => 1,
			'name'                    => 'T',
			'generate_featured_image' => 1,
			'featured_image_source'   => 'ai_prompt',
			'image_prompt'            => 'Hero image for {{topic}} article.',
			'prompt_template'         => 'Write about {{topic}}',
		);
		$context = new AIPS_Template_Context($template, null, 'Serverless');

		$result = $this->builder->build($context);

		$this->assertStringContainsString('Hero image for Serverless article.', $result);
	}

	/**
	 * Template context: returns empty string when image generation is disabled.
	 */
	public function test_context_template_type_returns_empty_when_disabled() {
		$template = (object) array(
			'id'                      => 1,
			'name'                    => 'T',
			'generate_featured_image' => 0,
			'image_prompt'            => 'Hero image for {{topic}}.',
			'prompt_template'         => 'Write about {{topic}}',
		);
		$context = new AIPS_Template_Context($template, null, 'Serverless');

		$result = $this->builder->build($context);

		$this->assertSame('', $result);
	}

	/**
	 * Template context: returns empty string when image_prompt is empty.
	 */
	public function test_context_template_type_returns_empty_when_no_image_prompt() {
		$template = (object) array(
			'id'                      => 1,
			'name'                    => 'T',
			'generate_featured_image' => 1,
			'image_prompt'            => '',
			'prompt_template'         => 'Write about {{topic}}',
		);
		$context = new AIPS_Template_Context($template, null, 'GraphQL');

		$result = $this->builder->build($context);

		$this->assertSame('', $result);
	}

	// ------------------------------------------------------------------
	// Generation context path – topic type
	// ------------------------------------------------------------------

	/**
	 * Topic context: returns processed prompt when author has image generation enabled.
	 */
	public function test_context_topic_type_returns_prompt_when_enabled() {
		$author = (object) array(
			'id'                      => 1,
			'name'                    => 'Alice',
			'field_niche'             => 'AI',
			'generate_featured_image' => 1,
			'featured_image_source'   => 'ai_prompt',
		);
		$topic = (object) array(
			'id'           => 7,
			'topic_title'  => 'Large Language Models',
			'topic_prompt' => 'Explain LLMs.',
		);
		$context = new AIPS_Topic_Context($author, $topic);

		$result = $this->builder->build($context);

		// The topic context uses topic_title as the image_prompt
		$this->assertStringContainsString('Large Language Models', $result);
	}

	/**
	 * Topic context: returns empty string when author has image generation disabled.
	 */
	public function test_context_topic_type_returns_empty_when_disabled() {
		$author = (object) array(
			'id'                      => 2,
			'name'                    => 'Bob',
			'field_niche'             => 'DevOps',
			'generate_featured_image' => 0,
		);
		$topic = (object) array(
			'id'           => 8,
			'topic_title'  => 'GitOps Workflows',
			'topic_prompt' => 'Explain GitOps.',
		);
		$context = new AIPS_Topic_Context($author, $topic);

		$result = $this->builder->build($context);

		$this->assertSame('', $result);
	}

	// ------------------------------------------------------------------
	// Constructor injection defaults
	// ------------------------------------------------------------------

	/**
	 * Builder can be constructed without explicit dependencies.
	 */
	public function test_instantiation_without_explicit_dependencies() {
		$builder  = new AIPS_Prompt_Builder_Post_Featured_Image();
		$template = (object) array(
			'generate_featured_image' => 1,
			'featured_image_source'   => 'ai_prompt',
			'image_prompt'            => 'Simple image prompt.',
		);

		$result = $builder->build($template, null);

		$this->assertSame('Simple image prompt.', $result);
	}
}
