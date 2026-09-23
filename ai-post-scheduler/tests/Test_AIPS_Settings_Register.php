<?php
/**
 * Class Test_AIPS_Settings_Register
 *
 * Unit test characterization suite for AIPS_Settings::register_settings refactoring.
 *
 * @package AI_Post_Scheduler
 */

class Test_AIPS_Settings_Register extends WP_UnitTestCase {

	/**
	 * Settings instance.
	 *
	 * @var AIPS_Settings
	 */
	private $settings;

	/**
	 * Set up test case.
	 */
	public function setUp(): void {
		parent::setUp();
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		if ( ! class_exists( 'AIPS_Settings' ) ) {
			require_once AIPS_PLUGIN_DIR . 'includes/class-aips-settings.php';
		}
		if ( ! class_exists( 'AIPS_Settings_UI' ) ) {
			require_once AIPS_PLUGIN_DIR . 'includes/class-aips-settings-ui.php';
		}

		$ui             = new AIPS_Settings_UI();
		$this->settings = new AIPS_Settings( $ui );
	}

	/**
	 * Test that register_settings populates global $wp_settings_sections and $wp_settings_fields correctly.
	 */
	public function test_register_settings_populates_sections_and_fields() {
		global $wp_settings_sections, $wp_settings_fields;

		$this->settings->register_settings();

		$this->assertArrayHasKey( 'aips-settings', $wp_settings_sections );
		$sections = $wp_settings_sections['aips-settings'];

		$expected_sections = array(
			'aips_general_section',
			'aips_ai_section',
			'aips_feedback_section',
			'aips_notifications_section',
			'aips_api_keys_section',
			'aips_developers_section',
			'aips_resilience_section',
			'aips_content_strategy_section',
			'aips_cache_section',
		);

		foreach ( $expected_sections as $section_id ) {
			$this->assertArrayHasKey( $section_id, $sections, "Settings section '{$section_id}' should be registered." );
		}

		$this->assertArrayHasKey( 'aips-settings', $wp_settings_fields );
		$fields = $wp_settings_fields['aips-settings'];

		$expected_fields_by_section = array(
			'aips_general_section'          => array( 'aips_default_post_status', 'aips_default_category' ),
			'aips_ai_section'               => array(
				'aips_ai_provider',
				'aips_ai_model',
				'aips_wp_ai_connectors',
				'aips_prevent_scheduled_ai_generation',
				'aips_ai_env_id',
				'aips_max_tokens_limit',
				'aips_max_tokens_title',
				'aips_max_tokens_excerpt',
				'aips_max_tokens_content',
				'aips_conversational_generation',
				'aips_conversational_metadata_turn',
			),
			'aips_feedback_section'         => array( 'aips_topic_similarity_threshold' ),
			'aips_notifications_section'    => array( 'aips_review_notifications_email' ),
			'aips_api_keys_section'         => array( 'aips_unsplash_access_key' ),
			'aips_developers_section'       => array(
				'aips_enable_logging',
				'aips_developer_mode',
				'aips_enable_telemetry',
				'aips_cache_monitor_enabled',
			),
			'aips_resilience_section'       => array(
				'aips_enable_retry',
				'aips_retry_max_attempts',
				'aips_retry_initial_delay',
				'aips_enable_rate_limiting',
				'aips_rate_limit_requests',
				'aips_rate_limit_period',
				'aips_enable_circuit_breaker',
				'aips_circuit_breaker_threshold',
				'aips_circuit_breaker_timeout',
			),
			'aips_content_strategy_section' => array(
				'aips_site_niche',
				'aips_site_target_audience',
				'aips_site_content_goals',
				'aips_default_article_structure_id',
				'aips_site_brand_voice',
				'aips_site_content_language',
				'aips_site_content_guidelines',
				'aips_site_excluded_topics',
			),
			'aips_cache_section'            => array(
				'aips_enable_cache_system',
				'aips_cache_driver',
				'aips_cache_default_ttl',
				'aips_cache_db_prefix',
			),
		);

		foreach ( $expected_fields_by_section as $section_id => $field_ids ) {
			$this->assertArrayHasKey( $section_id, $fields, "Fields array should have key for section '{$section_id}'." );
			foreach ( $field_ids as $field_id ) {
				$this->assertArrayHasKey( $field_id, $fields[ $section_id ], "Field '{$field_id}' should be registered in section '{$section_id}'." );
			}
		}
	}
}
