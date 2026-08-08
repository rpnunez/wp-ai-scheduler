<?php
/**
 * Tests for AIPS_Prompt_Builder_Generation_Instructions
 *
 * Covers the generation instructions prompt builder in isolation: enabled/
 * disabled states, empty instructions, block formatting, filter injection
 * into content/title/excerpt prompts, and the aips_generation_instructions_block
 * filter.
 *
 * @package AI_Post_Scheduler
 */

class Test_Prompt_Builder_Generation_Instructions extends WP_UnitTestCase {

	/**
	 * @var AIPS_Prompt_Builder_Generation_Instructions
	 */
	private $builder;

	public function setUp(): void {
		parent::setUp();
		AIPS_Prompt_Builder_Generation_Instructions::reset_registration();
		$this->builder = new AIPS_Prompt_Builder_Generation_Instructions();
		// Ensure options start clean.
		update_option('aips_generation_instructions_enabled', '0');
		update_option('aips_generation_instructions', '');
	}

	public function tearDown(): void {
		remove_all_filters('aips_generation_instructions_block');
		remove_all_filters('aips_content_prompt');
		remove_all_filters('aips_title_prompt');
		remove_all_filters('aips_excerpt_prompt');
		delete_option('aips_generation_instructions_enabled');
		delete_option('aips_generation_instructions');
		AIPS_Prompt_Builder_Generation_Instructions::reset_registration();
		parent::tearDown();
	}

	// ------------------------------------------------------------------
	// build() — disabled state
	// ------------------------------------------------------------------

	/**
	 * build() returns empty string when feature is disabled.
	 */
	public function test_build_returns_empty_when_disabled() {
		update_option('aips_generation_instructions_enabled', '0');
		update_option('aips_generation_instructions', 'Some instructions.');

		$result = $this->builder->build();

		$this->assertSame('', $result);
	}

	/**
	 * build() returns empty string when enabled but instructions text is empty.
	 */
	public function test_build_returns_empty_when_enabled_but_no_instructions() {
		update_option('aips_generation_instructions_enabled', '1');
		update_option('aips_generation_instructions', '');

		$result = $this->builder->build();

		$this->assertSame('', $result);
	}

	/**
	 * build() returns empty string when enabled but instructions is whitespace only.
	 */
	public function test_build_returns_empty_when_instructions_only_whitespace() {
		update_option('aips_generation_instructions_enabled', '1');
		update_option('aips_generation_instructions', '   ');

		$result = $this->builder->build();

		$this->assertSame('', $result);
	}

	// ------------------------------------------------------------------
	// build() — enabled state
	// ------------------------------------------------------------------

	/**
	 * build() returns a formatted block when enabled and instructions are set.
	 */
	public function test_build_returns_formatted_block_when_enabled() {
		update_option('aips_generation_instructions_enabled', '1');
		update_option('aips_generation_instructions', 'Always write in second person.');

		$result = $this->builder->build();

		$this->assertStringContainsString('### GENERATION INSTRUCTIONS:', $result);
		$this->assertStringContainsString('Always write in second person.', $result);
	}

	/**
	 * build() block ends with two newlines so it separates cleanly from the prompt.
	 */
	public function test_build_block_ends_with_double_newline() {
		update_option('aips_generation_instructions_enabled', '1');
		update_option('aips_generation_instructions', 'Use active voice.');

		$result = $this->builder->build();

		$this->assertStringEndsWith("\n\n", $result);
	}

	// ------------------------------------------------------------------
	// aips_generation_instructions_block filter
	// ------------------------------------------------------------------

	/**
	 * The aips_generation_instructions_block filter can modify the block.
	 */
	public function test_filter_can_modify_block() {
		update_option('aips_generation_instructions_enabled', '1');
		update_option('aips_generation_instructions', 'Original instructions.');

		add_filter('aips_generation_instructions_block', function ( $block ) {
			return 'MODIFIED_BLOCK' . "\n\n";
		});

		$result = $this->builder->build();

		$this->assertSame('MODIFIED_BLOCK' . "\n\n", $result);
	}

	/**
	 * Returning an empty string from the filter suppresses the block.
	 */
	public function test_filter_returning_empty_suppresses_block() {
		update_option('aips_generation_instructions_enabled', '1');
		update_option('aips_generation_instructions', 'Some instructions.');

		add_filter('aips_generation_instructions_block', '__return_empty_string');

		$result = $this->builder->build();

		$this->assertSame('', $result);
	}

	// ------------------------------------------------------------------
	// inject() callback
	// ------------------------------------------------------------------

	/**
	 * inject() prepends the block to the supplied prompt when enabled.
	 */
	public function test_inject_prepends_block_to_prompt() {
		update_option('aips_generation_instructions_enabled', '1');
		update_option('aips_generation_instructions', 'Global rule.');

		$result = $this->builder->inject('Original prompt text.');

		$this->assertStringStartsWith('### GENERATION INSTRUCTIONS:', $result);
		$this->assertStringContainsString('Original prompt text.', $result);
	}

	/**
	 * inject() returns the prompt unchanged when the feature is disabled.
	 */
	public function test_inject_returns_unchanged_prompt_when_disabled() {
		update_option('aips_generation_instructions_enabled', '0');

		$original = 'The original prompt.';
		$result   = $this->builder->inject($original);

		$this->assertSame($original, $result);
	}

	// ------------------------------------------------------------------
	// register_hooks() — filter integration
	// ------------------------------------------------------------------

	/**
	 * Registered hooks inject instructions into aips_content_prompt.
	 */
	public function test_hooks_inject_into_content_prompt() {
		update_option('aips_generation_instructions_enabled', '1');
		update_option('aips_generation_instructions', 'Content rule.');

		$this->builder->register_hooks();

		$result = apply_filters('aips_content_prompt', 'Content prompt body.', null, null);

		$this->assertStringContainsString('### GENERATION INSTRUCTIONS:', $result);
		$this->assertStringContainsString('Content rule.', $result);
		$this->assertStringContainsString('Content prompt body.', $result);
	}

	/**
	 * Registered hooks inject instructions into aips_title_prompt.
	 */
	public function test_hooks_inject_into_title_prompt() {
		update_option('aips_generation_instructions_enabled', '1');
		update_option('aips_generation_instructions', 'Title rule.');

		$this->builder->register_hooks();

		$result = apply_filters('aips_title_prompt', 'Title prompt body.', null, null, null, '');

		$this->assertStringContainsString('### GENERATION INSTRUCTIONS:', $result);
		$this->assertStringContainsString('Title rule.', $result);
		$this->assertStringContainsString('Title prompt body.', $result);
	}

	/**
	 * Registered hooks inject instructions into aips_excerpt_prompt.
	 */
	public function test_hooks_inject_into_excerpt_prompt() {
		update_option('aips_generation_instructions_enabled', '1');
		update_option('aips_generation_instructions', 'Excerpt rule.');

		$this->builder->register_hooks();

		$result = apply_filters('aips_excerpt_prompt', 'Excerpt prompt body.', 'T', 'C', null, null);

		$this->assertStringContainsString('### GENERATION INSTRUCTIONS:', $result);
		$this->assertStringContainsString('Excerpt rule.', $result);
		$this->assertStringContainsString('Excerpt prompt body.', $result);
	}

	/**
	 * register_hooks() is idempotent — calling it multiple times does not
	 * double-inject the instructions block.
	 */
	public function test_register_hooks_idempotent() {
		update_option('aips_generation_instructions_enabled', '1');
		update_option('aips_generation_instructions', 'Idempotent rule.');

		$this->builder->register_hooks();
		$this->builder->register_hooks();
		$this->builder->register_hooks();

		$result = apply_filters('aips_content_prompt', 'Prompt.', null, null);

		// Block should appear exactly once.
		$this->assertSame(1, substr_count($result, '### GENERATION INSTRUCTIONS:'));
	}
}
