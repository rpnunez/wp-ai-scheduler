<?php
/**
 * Tests for AIPS_Site_Context.
 *
 * Verifies that get(), get_setting(), build_prompt_context(), and
 * is_configured() behave correctly with and without stored options.
 *
 * @package AI_Post_Scheduler
 */

class Test_AIPS_Site_Context extends WP_UnitTestCase {

	/**
	 * Restore option state after each test.
	 */
	public function tearDown(): void {
		foreach ( AIPS_Site_Context::OPTIONS as $option ) {
			delete_option( $option );
		}
		parent::tearDown();
	}

	// ------------------------------------------------------------------
	// get()
	// ------------------------------------------------------------------

	/** @test */
	public function test_get_returns_empty_strings_when_no_options_set() {
		$ctx = AIPS_Site_Context::get();

		$this->assertSame( '', $ctx['niche'] );
		$this->assertSame( '', $ctx['target_audience'] );
		$this->assertSame( '', $ctx['content_goals'] );
		$this->assertSame( '', $ctx['brand_voice'] );
		$this->assertSame( 'en', $ctx['content_language'] );
		$this->assertSame( '', $ctx['content_guidelines'] );
		$this->assertSame( '', $ctx['excluded_topics'] );
	}

	/** @test */
	public function test_get_returns_stored_option_values() {
		update_option( 'aips_site_niche', 'WordPress Development' );
		update_option( 'aips_site_target_audience', 'Senior developers' );
		update_option( 'aips_site_brand_voice', 'Authoritative' );

		$ctx = AIPS_Site_Context::get();

		$this->assertSame( 'WordPress Development', $ctx['niche'] );
		$this->assertSame( 'Senior developers', $ctx['target_audience'] );
		$this->assertSame( 'Authoritative', $ctx['brand_voice'] );
	}

	// ------------------------------------------------------------------
	// get_setting()
	// ------------------------------------------------------------------

	/** @test */
	public function test_get_setting_returns_stored_value() {
		update_option( 'aips_site_niche', 'Fitness' );
		$this->assertSame( 'Fitness', AIPS_Site_Context::get_setting( 'niche' ) );
	}

	/** @test */
	public function test_get_setting_returns_default_when_not_set() {
		$this->assertSame( 'my_default', AIPS_Site_Context::get_setting( 'niche', 'my_default' ) );
	}

	// ------------------------------------------------------------------
	// is_configured()
	// ------------------------------------------------------------------

	/** @test */
	public function test_is_configured_returns_false_when_niche_empty() {
		$this->assertFalse( AIPS_Site_Context::is_configured() );
	}

	/** @test */
	public function test_is_configured_returns_true_when_niche_set() {
		update_option( 'aips_site_niche', 'Personal Finance' );
		$this->assertTrue( AIPS_Site_Context::is_configured() );
	}

	// ------------------------------------------------------------------
	// build_prompt_context()
	// ------------------------------------------------------------------

	/** @test */
	public function test_build_prompt_context_returns_empty_when_no_settings() {
		$this->assertSame( '', AIPS_Site_Context::build_prompt_context() );
	}

	/** @test */
	public function test_build_prompt_context_includes_niche_when_set() {
		update_option( 'aips_site_niche', 'SaaS Marketing' );
		$ctx = AIPS_Site_Context::build_prompt_context();

		$this->assertStringContainsString( 'SaaS Marketing', $ctx );
		$this->assertStringContainsString( 'Site niche:', $ctx );
	}

	/** @test */
	public function test_build_prompt_context_omits_english_language_line() {
		update_option( 'aips_site_niche', 'Test' );
		update_option( 'aips_site_content_language', 'en' );

		$ctx = AIPS_Site_Context::build_prompt_context();
		$this->assertStringNotContainsString( 'Language:', $ctx );
	}

	/** @test */
	public function test_build_prompt_context_includes_non_english_language() {
		update_option( 'aips_site_niche', 'Tech' );
		update_option( 'aips_site_content_language', 'es' );

		$ctx = AIPS_Site_Context::build_prompt_context();
		$this->assertStringContainsString( 'Language: es', $ctx );
	}

	/** @test */
	public function test_build_prompt_context_includes_excluded_topics() {
		update_option( 'aips_site_niche', 'Health' );
		update_option( 'aips_site_excluded_topics', 'competitor products, adult content' );

		$ctx = AIPS_Site_Context::build_prompt_context();
		$this->assertStringContainsString( 'competitor products', $ctx );
		$this->assertStringContainsString( 'Topics to avoid globally:', $ctx );
	}

	/** @test */
	public function test_build_prompt_context_includes_brand_voice() {
		update_option( 'aips_site_niche', 'Finance' );
		update_option( 'aips_site_brand_voice', 'Friendly and approachable' );

		$ctx = AIPS_Site_Context::build_prompt_context();
		$this->assertStringContainsString( 'Brand voice/tone: Friendly and approachable', $ctx );
	}
}
