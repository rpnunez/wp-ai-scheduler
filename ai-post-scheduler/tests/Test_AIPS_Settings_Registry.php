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
}
