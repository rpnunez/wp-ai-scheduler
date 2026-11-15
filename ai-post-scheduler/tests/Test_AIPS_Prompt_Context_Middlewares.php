<?php
/**
 * Tests for AIPS_Voice_Tone_Middleware, AIPS_Site_Context_Middleware,
 * AIPS_Editorial_Guidelines_Middleware, and AIPS_Localization_Middleware.
 *
 * @package AI_Post_Scheduler
 */

class Test_AIPS_Prompt_Context_Middlewares extends WP_UnitTestCase {

	public function tearDown(): void {
		delete_option('aips_site_niche');
		delete_option('aips_site_target_audience');
		delete_option('aips_site_content_goals');
		delete_option('aips_site_brand_voice');
		delete_option('aips_site_content_language');
		delete_option('aips_site_content_guidelines');
		delete_option('aips_site_excluded_topics');
		parent::tearDown();
	}

	private function make_context($voice = null) {
		$template = (object) array();
		return new AIPS_Template_Context($template, $voice, 'Test Topic');
	}

	// -- AIPS_Voice_Tone_Middleware -----------------------------------------

	public function test_voice_tone_middleware_adds_voice_instructions_when_present() {
		$voice = (object) array('content_instructions' => 'Write in a conversational, friendly tone.');
		$middleware = new AIPS_Voice_Tone_Middleware();

		$document = $middleware->process(new AIPS_Prompt_Document(), $this->make_context($voice));

		$this->assertCount(1, $document->get_context_payload());
		$this->assertStringContainsString('Write in a conversational, friendly tone.', $document->get_context_payload()[0]);
	}

	public function test_voice_tone_middleware_noop_when_no_voice() {
		$middleware = new AIPS_Voice_Tone_Middleware();

		$document = $middleware->process(new AIPS_Prompt_Document(), $this->make_context(null));

		$this->assertSame(array(), $document->get_context_payload());
	}

	public function test_voice_tone_middleware_noop_when_voice_has_no_instructions() {
		$voice = (object) array('content_instructions' => '');
		$middleware = new AIPS_Voice_Tone_Middleware();

		$document = $middleware->process(new AIPS_Prompt_Document(), $this->make_context($voice));

		$this->assertSame(array(), $document->get_context_payload());
	}

	// -- AIPS_Site_Context_Middleware ----------------------------------------

	public function test_site_context_middleware_adds_configured_fields() {
		update_option('aips_site_niche', 'WordPress development');
		update_option('aips_site_target_audience', 'Freelance developers');
		update_option('aips_site_content_goals', 'Grow organic traffic');

		$middleware = new AIPS_Site_Context_Middleware();
		$document = $middleware->process(new AIPS_Prompt_Document(), $this->make_context());

		$payload = implode("\n", $document->get_context_payload());
		$this->assertStringContainsString('Site niche: WordPress development', $payload);
		$this->assertStringContainsString('Target audience: Freelance developers', $payload);
		$this->assertStringContainsString('Content goals: Grow organic traffic', $payload);
	}

	public function test_site_context_middleware_noop_when_nothing_configured() {
		$middleware = new AIPS_Site_Context_Middleware();
		$document = $middleware->process(new AIPS_Prompt_Document(), $this->make_context());

		$this->assertSame(array(), $document->get_context_payload());
	}

	public function test_site_context_middleware_excludes_brand_voice_and_language() {
		update_option('aips_site_niche', 'WordPress development');
		update_option('aips_site_brand_voice', 'Playful');
		update_option('aips_site_content_language', 'fr');

		$middleware = new AIPS_Site_Context_Middleware();
		$document = $middleware->process(new AIPS_Prompt_Document(), $this->make_context());

		$payload = implode("\n", $document->get_context_payload());
		$this->assertStringNotContainsString('Playful', $payload);
		$this->assertStringNotContainsString('fr', $payload);
	}

	// -- AIPS_Editorial_Guidelines_Middleware --------------------------------

	public function test_editorial_guidelines_middleware_adds_guidelines_and_excluded_topics() {
		update_option('aips_site_content_guidelines', 'Always cite primary sources.');
		update_option('aips_site_excluded_topics', 'politics, religion');

		$middleware = new AIPS_Editorial_Guidelines_Middleware();
		$document = $middleware->process(new AIPS_Prompt_Document(), $this->make_context());

		$constraints = implode("\n", $document->get_constraints());
		$this->assertStringContainsString('Always cite primary sources.', $constraints);
		$this->assertStringContainsString('politics, religion', $constraints);
	}

	public function test_editorial_guidelines_middleware_noop_when_nothing_configured() {
		$middleware = new AIPS_Editorial_Guidelines_Middleware();
		$document = $middleware->process(new AIPS_Prompt_Document(), $this->make_context());

		$this->assertSame(array(), $document->get_constraints());
	}

	// -- AIPS_Localization_Middleware ----------------------------------------

	public function test_localization_middleware_adds_constraint_for_non_english_language() {
		update_option('aips_site_content_language', 'es');

		$middleware = new AIPS_Localization_Middleware();
		$document = $middleware->process(new AIPS_Prompt_Document(), $this->make_context());

		$this->assertCount(1, $document->get_constraints());
		$this->assertStringContainsString('es', $document->get_constraints()[0]);
	}

	public function test_localization_middleware_noop_for_english() {
		update_option('aips_site_content_language', 'en');

		$middleware = new AIPS_Localization_Middleware();
		$document = $middleware->process(new AIPS_Prompt_Document(), $this->make_context());

		$this->assertSame(array(), $document->get_constraints());
	}

	public function test_localization_middleware_noop_when_unset() {
		$middleware = new AIPS_Localization_Middleware();
		$document = $middleware->process(new AIPS_Prompt_Document(), $this->make_context());

		$this->assertSame(array(), $document->get_constraints());
	}
}
