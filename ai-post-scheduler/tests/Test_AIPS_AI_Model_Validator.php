<?php
/**
 * Unit tests for AIPS_AI_Model_Validator.
 *
 * @package AI_Post_Scheduler
 * @since 3.4.8
 */

class Test_AIPS_AI_Model_Validator extends WP_UnitTestCase {

	public function tearDown(): void {
		delete_option( 'aips_ai_model_validation' );
		delete_transient( 'aips_ai_model_catalog_text' );
		AIPS_Config::get_instance()->flush_option_cache();
		parent::tearDown();
	}

	/** @test */
	public function test_validate_returns_valid_when_policy_off() {
		update_option( 'aips_ai_model_validation', 'off' );
		AIPS_Config::get_instance()->flush_option_cache();

		$result = AIPS_AI_Model_Validator::validate( 'unknown-model-123' );
		$this->assertTrue( $result['valid'] );
		$this->assertTrue( $result['known'] );
	}

	/** @test */
	public function test_validate_returns_known_false_when_catalog_unavailable() {
		update_option( 'aips_ai_model_validation', 'warn' );
		delete_transient( 'aips_ai_model_catalog_text' );
		AIPS_Config::get_instance()->flush_option_cache();

		// Add filter to ensure empty catalog
		add_filter( 'aips_ai_model_catalog', '__return_empty_array' );
		$result = AIPS_AI_Model_Validator::validate( 'some-model' );
		remove_filter( 'aips_ai_model_catalog', '__return_empty_array' );

		$this->assertTrue( $result['valid'] );
		$this->assertFalse( $result['known'] );
	}

	/** @test */
	public function test_validate_matches_model_in_catalog_with_wp_ai_client_connector() {
		update_option( 'aips_ai_model_validation', 'strict' );
		AIPS_Config::get_instance()->flush_option_cache();

		add_filter( 'aips_ai_model_catalog', function() {
			return array(
				array(
					'id'             => 'gpt-4o',
					'label'          => 'GPT-4o',
					'provider'       => 'openai',
					'provider_label' => 'OpenAI',
					'capability'     => 'text',
				),
			);
		} );

		$result = AIPS_AI_Model_Validator::validate( 'gpt-4o', 'text', 'wp_ai_client' );
		$this->assertTrue( $result['valid'] );
		$this->assertTrue( $result['known'] );
		$this->assertEmpty( $result['message'] );
	}
}
