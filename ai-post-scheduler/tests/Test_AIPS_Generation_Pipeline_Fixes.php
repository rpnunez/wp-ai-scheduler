<?php
/**
 * Regression tests for post generation pipeline invariants:
 *  - AIPS_Excerpt_Post_Component uses the non-deprecated public primitive
 *    generate_excerpt_text() while keeping the deprecated wrapper for legacy callers.
 *  - AIPS_Post_Generation_Strategy::execute_run() closes the conversational session
 *    when the content stage fails (no session leak).
 *  - AIPS_Generator::strip_leading_title_block_from_content() is public so the content
 *    component's regenerate() can strip a repeated leading <h1>/title block.
 *
 * @package AI_Post_Scheduler
 */
class Test_AIPS_Generation_Pipeline_Fixes extends WP_UnitTestCase {

	private function make_template_context(array $overrides = array()) {
		$defaults = array(
			'id'                      => 101,
			'name'                    => 'Pipeline Fixes Template',
			'prompt_template'         => 'Write an article about {{topic}}.',
			'post_status'             => 'draft',
			'generate_featured_image' => 0,
			'featured_image_source'   => 'ai_prompt',
			'include_sources'         => 0,
			'affiliate_links_enabled' => 0,
		);
		$template = (object) array_merge($defaults, $overrides);
		return new AIPS_Template_Context($template, null, 'PHP Design Patterns');
	}

	/**
	 * Content-stage failure must still close the conversational session.
	 */
	public function test_execute_run_closes_conversation_session_on_content_failure() {
		$context = $this->make_template_context();

		$history = new class() {
			public function with_session($context) { return $this; }
			public function get_id() { return 42; }
			public function record() {}
			public function complete_failure() {}
			public function complete_partial() {}
			public function complete_success() {}
		};

		$history_service = $this->createMock(AIPS_History_Service_Interface::class);
		$history_service->method('create')->willReturn($history);

		$generator = $this->getMockBuilder(AIPS_Generator::class)
			->disableOriginalConstructor()
			->onlyMethods(array(
				'set_history_container',
				'open_conversation_session',
				'run_content_stage',
				'close_conversation_session',
			))
			->getMock();
		$generator->method('run_content_stage')->willReturn(new WP_Error('content_failed', 'boom'));

		// The guard under test: the session opened before the content stage must be closed
		// on the failure path, exactly once.
		$generator->expects($this->once())->method('close_conversation_session');

		$strategy = new AIPS_Post_Generation_Strategy(
			new AIPS_Generation_Component_Registry(),
			$generator,
			$this->createMock(AIPS_Post_Manager::class),
			$history_service,
			$this->createMock(AIPS_Logger_Interface::class)
		);

		$result = $strategy->execute_run($context);
		$this->assertTrue($result->is_failure());
	}

	/**
	 * The deprecated generate_excerpt() delegates to a non-deprecated public
	 * primitive generate_excerpt_text() that the excerpt component calls directly.
	 */
	public function test_generate_excerpt_text_primitive_is_public_and_wrapper_kept() {
		$ref = new ReflectionClass('AIPS_Generator');

		$this->assertTrue($ref->hasMethod('generate_excerpt_text'), 'Expected non-deprecated primitive generate_excerpt_text().');
		$this->assertTrue($ref->getMethod('generate_excerpt_text')->isPublic());

		// Backward-compat wrapper retained for external/legacy callers.
		$this->assertTrue($ref->hasMethod('generate_excerpt'));

		// The migrated excerpt component must call the primitive, not the deprecated wrapper.
		$component_src = file_get_contents(AIPS_PLUGIN_DIR . 'includes/generation/components/class-aips-excerpt-post-component.php');
		$this->assertStringContainsString('generate_excerpt_text(', $component_src);
		$this->assertStringNotContainsString('->generate_excerpt(', $component_src);
	}

	/**
	 * The title-block stripper is public (so the content component can call it) and
	 * removes a leading repeated <h1> title block.
	 */
	public function test_strip_leading_title_block_is_public_and_strips_leading_h1() {
		$ref = new ReflectionMethod('AIPS_Generator', 'strip_leading_title_block_from_content');
		$this->assertTrue($ref->isPublic(), 'strip_leading_title_block_from_content() must be public for component reuse.');

		$generator = new AIPS_Generator();
		$out = $generator->strip_leading_title_block_from_content("<h1>Duplicate Title</h1>\n<p>Body paragraph.</p>");

		$this->assertStringNotContainsString('<h1>', $out);
		$this->assertStringStartsWith('<p>Body paragraph.</p>', $out);
	}
}
