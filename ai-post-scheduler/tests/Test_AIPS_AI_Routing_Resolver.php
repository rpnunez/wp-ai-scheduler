<?php
/**
 * Unit tests for AIPS_AI_Routing_Resolver.
 *
 * @package AI_Post_Scheduler
 * @since 3.4.8
 */

class Test_AIPS_AI_Routing_Resolver extends WP_UnitTestCase {

	/**
	 * Clean up options after tests.
	 */
	public function tearDown(): void {
		delete_option( 'aips_ai_routing_profiles' );
		delete_option( 'aips_ai_model' );
		delete_option( 'aips_ai_model_title' );
		delete_option( 'aips_ai_model_excerpt' );
		delete_option( 'aips_ai_model_content' );
		delete_option( 'aips_ai_image_model' );
		AIPS_Config::get_instance()->flush_option_cache();
		parent::tearDown();
	}

	/** @test */
	public function test_get_profiles_returns_site_default_when_empty() {
		delete_option( 'aips_ai_routing_profiles' );
		AIPS_Config::get_instance()->flush_option_cache();

		$profiles = AIPS_AI_Routing_Resolver::get_profiles();
		$this->assertArrayHasKey( 'site_default', $profiles );
		$this->assertTrue( $profiles['site_default']['fallback_enabled'] );
	}

	/** @test */
	public function test_resolve_uses_global_model_when_no_template_or_profile_set() {
		update_option( 'aips_ai_model', 'gpt-4o-mini' );
		AIPS_Config::get_instance()->flush_option_cache();

		$resolved = AIPS_AI_Routing_Resolver::resolve( array(), 'content' );
		$this->assertSame( 'gpt-4o-mini', $resolved['model'] );
		$this->assertTrue( $resolved['fallback_enabled'] );
	}

	/** @test */
	public function test_resolve_respects_request_type_model_overrides() {
		update_option( 'aips_ai_model', 'gpt-4o-mini' );
		update_option( 'aips_ai_model_title', 'gpt-4o-title' );
		AIPS_Config::get_instance()->flush_option_cache();

		$resolved_title   = AIPS_AI_Routing_Resolver::resolve( array(), 'title' );
		$resolved_content = AIPS_AI_Routing_Resolver::resolve( array(), 'content' );

		$this->assertSame( 'gpt-4o-title', $resolved_title['model'] );
		$this->assertSame( 'gpt-4o-mini', $resolved_content['model'] );
	}

	/** @test */
	public function test_resolve_template_overrides_take_precedence() {
		update_option( 'aips_ai_model', 'global-model' );
		AIPS_Config::get_instance()->flush_option_cache();

		$template_policy = array(
			'profile'          => 'site_default',
			'fallback_enabled' => false,
			'overrides'        => array(
				'content_model' => 'template-custom-model',
				'connector'     => 'anthropic',
			),
		);

		$resolved = AIPS_AI_Routing_Resolver::resolve( $template_policy, 'content' );

		$this->assertSame( 'template-custom-model', $resolved['model'] );
		$this->assertSame( 'anthropic', $resolved['connector'] );
		$this->assertFalse( $resolved['fallback_enabled'] );
	}

	/** @test */
	public function test_explicit_request_parameters_override_template_and_profile() {
		$template_policy = array(
			'overrides' => array(
				'content_model' => 'template-model',
				'connector'     => 'google',
			),
		);

		$explicit = array(
			'model'     => 'explicit-model',
			'connector' => 'openai',
		);

		$resolved = AIPS_AI_Routing_Resolver::resolve( $template_policy, 'content', $explicit );

		$this->assertSame( 'explicit-model', $resolved['model'] );
		$this->assertSame( 'openai', $resolved['connector'] );
		$this->assertSame( 'explicit_request', $resolved['source'] );
	}
}
