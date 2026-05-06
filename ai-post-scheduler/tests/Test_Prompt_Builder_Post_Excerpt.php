<?php
/**
 * Tests for AIPS_Prompt_Builder_Post_Excerpt
 *
 * Covers the dedicated post-excerpt prompt builder: base structure, voice
 * instructions, topic placeholder resolution, empty inputs, and the
 * aips_excerpt_prompt filter.
 *
 * @package AI_Post_Scheduler
 */

class Test_Prompt_Builder_Post_Excerpt extends WP_UnitTestCase {

	/**
	 * @var AIPS_Prompt_Builder_Post_Excerpt
	 */
	private $builder;

	public function setUp(): void {
		parent::setUp();
		$this->builder = new AIPS_Prompt_Builder_Post_Excerpt(new AIPS_Template_Processor());
	}

	public function tearDown(): void {
		remove_all_filters('aips_excerpt_prompt');
		parent::tearDown();
	}

	// ------------------------------------------------------------------
	// Base prompt structure
	// ------------------------------------------------------------------

	/**
	 * Prompt always contains the core excerpt instruction.
	 */
	public function test_build_contains_core_instruction() {
		$result = $this->builder->build('Title', 'Content', null, null);

		$this->assertStringContainsString('Write an excerpt for an article', $result);
		$this->assertStringContainsString('between 40 and 60 words', $result);
	}

	/**
	 * Article title and body appear in clearly labelled sections.
	 */
	public function test_build_includes_title_and_body_sections() {
		$title   = 'Understanding Dependency Injection';
		$content = 'This article covers the fundamentals of DI in PHP.';

		$result = $this->builder->build($title, $content, null, null);

		$this->assertStringContainsString('ARTICLE TITLE:', $result);
		$this->assertStringContainsString($title, $result);
		$this->assertStringContainsString('ARTICLE BODY:', $result);
		$this->assertStringContainsString($content, $result);
	}

	/**
	 * Closing call-to-action line is present.
	 */
	public function test_build_contains_closing_cta() {
		$result = $this->builder->build('T', 'C', null, null);

		$this->assertStringContainsString('Create a compelling excerpt', $result);
	}

	// ------------------------------------------------------------------
	// Voice instructions
	// ------------------------------------------------------------------

	/**
	 * Voice excerpt_instructions are prepended when available.
	 */
	public function test_build_with_voice_instructions_prepends_them() {
		$voice = (object) array(
			'excerpt_instructions' => 'Write in a conversational style about {{topic}}.',
		);

		$result = $this->builder->build('My Title', 'Body text.', $voice, 'DI Patterns');

		$this->assertStringContainsString('Write in a conversational style about DI Patterns.', $result);
		$this->assertStringContainsString('ARTICLE TITLE:', $result);
	}

	/**
	 * Topic placeholder inside voice instructions is resolved.
	 */
	public function test_build_voice_instructions_resolve_topic_placeholder() {
		$voice = (object) array(
			'excerpt_instructions' => 'Focus on {{topic}} use-cases.',
		);

		$result = $this->builder->build('Title', 'Body.', $voice, 'Docker');

		$this->assertStringContainsString('Focus on Docker use-cases.', $result);
		$this->assertStringNotContainsString('{{topic}}', $result);
	}

	/**
	 * Null voice produces no extra instructions section.
	 */
	public function test_build_without_voice_no_extra_instructions() {
		$result = $this->builder->build('Title', 'Body.', null, null);

		$this->assertStringNotContainsString('conversational', $result);
	}

	/**
	 * Voice object with empty excerpt_instructions is silently skipped.
	 */
	public function test_build_voice_with_empty_instructions_skipped() {
		$voice = (object) array(
			'excerpt_instructions' => '',
		);

		$result = $this->builder->build('Title', 'Body.', $voice, 'Topic');

		$this->assertStringContainsString('ARTICLE TITLE:', $result);
		// No extra blank instruction block
		$this->assertStringNotContainsString("\n\n\n", $result);
	}

	// ------------------------------------------------------------------
	// Edge cases
	// ------------------------------------------------------------------

	/**
	 * Empty title and content are handled without errors.
	 */
	public function test_build_empty_title_and_content() {
		$result = $this->builder->build('', '', null, null);

		$this->assertStringContainsString('ARTICLE TITLE:', $result);
		$this->assertStringContainsString('ARTICLE BODY:', $result);
		$this->assertIsString($result);
	}

	// ------------------------------------------------------------------
	// build_instructions helper
	// ------------------------------------------------------------------

	/**
	 * build_instructions returns null when voice is null.
	 */
	public function test_build_instructions_null_voice_returns_null() {
		$result = $this->builder->build_instructions(null, 'Topic');

		$this->assertNull($result);
	}

	/**
	 * build_instructions returns null when voice has no excerpt_instructions.
	 */
	public function test_build_instructions_voice_missing_property_returns_null() {
		$voice = (object) array(); // no excerpt_instructions property

		$result = $this->builder->build_instructions($voice, 'Topic');

		$this->assertNull($result);
	}

	/**
	 * build_instructions returns processed instructions when voice has them.
	 */
	public function test_build_instructions_returns_processed_string() {
		$voice = (object) array(
			'excerpt_instructions' => 'Tone: expert on {{topic}}.',
		);

		$result = $this->builder->build_instructions($voice, 'Terraform');

		$this->assertSame('Tone: expert on Terraform.', $result);
	}

	// ------------------------------------------------------------------
	// Filter hook
	// ------------------------------------------------------------------

	/**
	 * aips_excerpt_prompt filter can modify the final prompt.
	 */
	public function test_filter_can_modify_prompt() {
		add_filter('aips_excerpt_prompt', function ( $prompt ) {
			return $prompt . "\nCUSTOM";
		});

		$result = $this->builder->build('Title', 'Content', null, null);

		$this->assertStringContainsString('CUSTOM', $result);
	}
}
