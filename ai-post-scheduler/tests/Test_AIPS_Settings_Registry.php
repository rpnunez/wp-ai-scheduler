<?php
/**
 * Tests for AIPS_Settings_Registry.
 *
 * @package AI_Post_Scheduler
 */

class Test_AIPS_Settings_Registry extends WP_UnitTestCase {

	public function tearDown(): void {
		AIPS_Settings_Registry::reset();
		parent::tearDown();
	}

	public function test_singleton_instance() {
		$instance1 = AIPS_Settings_Registry::instance();
		$instance2 = AIPS_Settings_Registry::instance();
		$this->assertSame( $instance1, $instance2 );

		AIPS_Settings_Registry::reset();
		$instance3 = AIPS_Settings_Registry::instance();
		$this->assertNotSame( $instance1, $instance3 );
	}

	public function test_default_tabs_registered() {
		$registry = AIPS_Settings_Registry::instance();
		$tabs     = $registry->get_tabs();

		$expected_tabs = array(
			'settings-general',
			'settings-ai',
			'settings-ai-assistance',
			'settings-linking',
			'settings-feedback',
			'settings-notifications',
			'settings-resilience',
			'settings-content-strategy',
			'settings-cache',
			'settings-api-keys',
			'settings-developers',
		);

		foreach ( $expected_tabs as $tab_key ) {
			$this->assertArrayHasKey( $tab_key, $tabs, "Expected tab {$tab_key} to be registered" );
			$this->assertNotEmpty( $tabs[ $tab_key ]['label'] );
		}
	}

	public function test_sections_by_tab() {
		$registry = AIPS_Settings_Registry::instance();
		$general_sections = $registry->get_sections_by_tab( 'settings-general' );

		$this->assertArrayHasKey( 'aips_general_section', $general_sections );
		$this->assertArrayHasKey( 'aips_post_images_section', $general_sections );
	}

	public function test_get_definition() {
		$registry = AIPS_Settings_Registry::instance();

		$this->assertTrue( $registry->has_definition( 'aips_default_post_status' ) );
		$this->assertFalse( $registry->has_definition( 'aips_non_existent_key_123' ) );

		$def = $registry->get_definition( 'aips_default_post_status' );
		$this->assertInstanceOf( AIPS_Setting_Definition::class, $def );
		$this->assertSame( 'draft', $def->get_default() );
		$this->assertSame( 'settings-general', $def->get_tab() );
		$this->assertSame( 'aips_general_section', $def->get_section() );
	}

	public function test_get_definitions_by_tab_and_section() {
		$registry = AIPS_Settings_Registry::instance();

		$general_settings = $registry->get_definitions_by_tab( 'settings-general' );
		$this->assertArrayHasKey( 'aips_default_post_status', $general_settings );
		$this->assertArrayHasKey( 'aips_default_category', $general_settings );

		$general_section_settings = $registry->get_definitions_by_section( 'aips_general_section' );
		$this->assertArrayHasKey( 'aips_default_post_status', $general_section_settings );
		$this->assertArrayNotHasKey( 'aips_max_post_images_limit', $general_section_settings );
	}

	public function test_custom_registration() {
		$registry = AIPS_Settings_Registry::instance();

		$custom_def = new AIPS_Setting_Definition( array(
			'key'     => 'aips_custom_test_option',
			'type'    => AIPS_Setting_Definition::TYPE_STRING,
			'default' => 'custom_default',
			'tab'     => 'settings-developers',
			'section' => 'aips_developers_section',
		) );

		$registry->register( $custom_def );

		$this->assertTrue( $registry->has_definition( 'aips_custom_test_option' ) );
		$defaults = $registry->get_defaults();
		$this->assertArrayHasKey( 'aips_custom_test_option', $defaults );
		$this->assertSame( 'custom_default', $defaults['aips_custom_test_option'] );
	}

	public function test_defaults_match_aips_config() {
		$registry = AIPS_Settings_Registry::instance();
		$defaults = $registry->get_defaults();

		$config_defaults = AIPS_Config::get_instance()->get_default_options();

		$this->assertSame( count( $defaults ), count( $config_defaults ) );
		$this->assertSame( $defaults, $config_defaults );
	}

	public function test_section_alignments() {
		$registry = AIPS_Settings_Registry::instance();

		// Clicks and silos section alignment
		$click_def = $registry->get_definition( 'aips_link_click_tracking_enabled' );
		$this->assertNotNull( $click_def );
		$this->assertSame( 'aips_link_clicks_section', $click_def->get_section() );

		$silo_def = $registry->get_definition( 'aips_silo_guide_enabled' );
		$this->assertNotNull( $silo_def );
		$this->assertSame( 'aips_silo_section', $silo_def->get_section() );

		// Notifications sections
		$email_def = $registry->get_definition( 'aips_review_notifications_email' );
		$this->assertNotNull( $email_def );
		$this->assertSame( 'aips_notifications_delivery_section', $email_def->get_section() );

		$gen_failed_def = $registry->get_definition( 'aips_notification_preferences_generation_failed' );
		$this->assertNotNull( $gen_failed_def );
		$this->assertSame( 'aips_notifications_system_section', $gen_failed_def->get_section() );

		$post_gen_def = $registry->get_definition( 'aips_notification_preferences_post_generated' );
		$this->assertNotNull( $post_gen_def );
		$this->assertSame( 'aips_notifications_content_section', $post_gen_def->get_section() );

		$tmpl_def = $registry->get_definition( 'aips_notification_preferences_template_generated' );
		$this->assertNotNull( $tmpl_def );
		$this->assertSame( 'aips_notifications_automation_section', $tmpl_def->get_section() );

		$digest_def = $registry->get_definition( 'aips_notification_preferences_daily_digest' );
		$this->assertNotNull( $digest_def );
		$this->assertSame( 'aips_notifications_digests_section', $digest_def->get_section() );

		$action_def = $registry->get_definition( 'aips_notif_digest_actions' );
		$this->assertNotNull( $action_def );
		$this->assertSame( 'aips_notifications_summarized_digest_section', $action_def->get_section() );

		// Custom dropdown render callbacks for native WP functions
		$cat_def = $registry->get_definition( 'aips_default_category' );
		$this->assertNotNull( $cat_def );
		$this->assertTrue( is_callable( $cat_def->get_control_option( 'render_callback' ) ) );

		$author_def = $registry->get_definition( 'aips_default_post_author' );
		$this->assertNotNull( $author_def );
		$this->assertTrue( is_callable( $author_def->get_control_option( 'render_callback' ) ) );
	}

	public function test_composite_controls_and_visual_parents() {
		$registry = AIPS_Settings_Registry::instance();

		// 1. AI Connectors
		$connectors_def = $registry->get_definition( 'aips_wp_ai_connectors' );
		$this->assertNotNull( $connectors_def );
		$this->assertTrue( $connectors_def->is_action() );
		$this->assertNull( $connectors_def->get_type() );
		$this->assertTrue( is_callable( $connectors_def->get_render_callback() ) );

		$mode_def = $registry->get_definition( 'aips_wp_ai_connector_mode' );
		$this->assertNotNull( $mode_def );
		$this->assertSame( 'aips_wp_ai_connectors', $mode_def->get_control_option( 'visual_parent' ) );

		$ids_def = $registry->get_definition( 'aips_wp_ai_connector_ids' );
		$this->assertNotNull( $ids_def );
		$this->assertSame( 'aips_wp_ai_connectors', $ids_def->get_control_option( 'visual_parent' ) );

		// 2. GSC Panel
		$gsc_def = $registry->get_definition( 'aips_gsc' );
		$this->assertNotNull( $gsc_def );
		$this->assertTrue( $gsc_def->is_action() );
		$this->assertTrue( is_callable( $gsc_def->get_render_callback() ) );

		$gsc_sa_def = $registry->get_definition( 'aips_gsc_service_account' );
		$this->assertNotNull( $gsc_sa_def );
		$this->assertSame( 'aips_gsc', $gsc_sa_def->get_control_option( 'visual_parent' ) );

		// 3. Shortcode Action Card
		$shortcode_def = $registry->get_definition( 'aips_related_posts_shortcode' );
		$this->assertNotNull( $shortcode_def );
		$this->assertTrue( $shortcode_def->is_action() );
		$this->assertTrue( is_callable( $shortcode_def->get_render_callback() ) );

		// 4. Specialized render callbacks
		$env_def = $registry->get_definition( 'aips_embeddings_env_id' );
		$this->assertNotNull( $env_def );
		$this->assertTrue( is_callable( $env_def->get_render_callback() ) );

		$retention_def = $registry->get_definition( 'aips_source_archive_retention_days' );
		$this->assertNotNull( $retention_def );
		$this->assertTrue( is_callable( $retention_def->get_render_callback() ) );

		$approval_def = $registry->get_definition( 'aips_author_topic_auto_approval_mode' );
		$this->assertNotNull( $approval_def );
		$this->assertTrue( is_callable( $approval_def->get_render_callback() ) );
	}

	public function test_retired_telemetry_and_cache_monitor_options_are_not_registered() {
		$registry = AIPS_Settings_Registry::instance();

		$this->assertFalse( $registry->has_definition( 'aips_enable_telemetry' ) );
		$this->assertFalse( $registry->has_definition( 'aips_telemetry_retention_value' ) );
		$this->assertFalse( $registry->has_definition( 'aips_telemetry_retention_unit' ) );
		$this->assertFalse( $registry->has_definition( 'aips_telemetry_prune_interval' ) );
		$this->assertFalse( $registry->has_definition( 'aips_cache_monitor_enabled' ) );
		$this->assertTrue( $registry->has_definition( 'aips_db_prune_interval' ) );
		$this->assertArrayNotHasKey( 'aips_cache_monitor_section', $registry->get_sections_by_tab( 'settings-cache' ) );
	}
}
