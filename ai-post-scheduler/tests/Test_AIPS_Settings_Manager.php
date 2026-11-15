<?php
/**
 * Tests for AIPS_Settings_Manager.
 *
 * @package AI_Post_Scheduler
 */

class Test_AIPS_Settings_Manager extends WP_UnitTestCase {

	/**
	 * @var AIPS_Settings_Registry
	 */
	private $registry;

	/**
	 * @var AIPS_Settings_Form_Renderer
	 */
	private $renderer;

	/**
	 * @var AIPS_Settings_Manager
	 */
	private $manager;

	public function setUp(): void {
		parent::setUp();
		$this->registry = AIPS_Settings_Registry::instance();
		$this->renderer = new AIPS_Settings_Form_Renderer();
		$this->manager  = new AIPS_Settings_Manager( $this->registry, $this->renderer );
	}

	public function test_dependencies() {
		$this->assertSame( $this->registry, $this->manager->get_registry() );
		$this->assertSame( $this->renderer, $this->manager->get_renderer() );
	}

	public function test_render_field_callback_outputs_html() {
		$custom_def = new AIPS_Setting_Definition( array(
			'key'          => 'aips_unit_test_mgr_field',
			'type'         => AIPS_Setting_Definition::TYPE_STRING,
			'default'      => 'hello_manager',
			'tab'          => 'settings-general',
			'section'      => 'aips_general_section',
			'label'        => 'Test Manager Field',
			'control_type' => AIPS_Setting_Definition::CONTROL_TEXT,
		) );

		$this->registry->register( $custom_def );

		ob_start();
		$this->manager->render_field_callback( array( 'key' => 'aips_unit_test_mgr_field' ) );
		$output = ob_get_clean();

		$this->assertStringContainsString( 'name="aips_unit_test_mgr_field"', $output );
		$this->assertStringContainsString( 'value="hello_manager"', $output );
	}

	public function test_render_field_callback_ignores_unknown_key() {
		ob_start();
		$this->manager->render_field_callback( array( 'key' => 'completely_unknown_key_xyz' ) );
		$output = ob_get_clean();

		$this->assertEmpty( $output );
	}

	public function test_register_runtime_hooks_idempotent() {
		AIPS_Settings_Manager::register_runtime_hooks();
		AIPS_Settings_Manager::register_runtime_hooks();

		$this->assertNotEmpty( has_action( 'update_option_aips_enable_cache_system' ) );
		$this->assertNotEmpty( has_action( 'update_option_aips_prevent_scheduled_ai_generation' ) );
	}

	public function test_render_field_callback_resolves_parent_key() {
		update_option( 'aips_notification_preferences', array(
			'generation_failed' => 'email',
		) );

		ob_start();
		$this->manager->render_field_callback( array( 'key' => 'aips_notification_preferences_generation_failed' ) );
		$output = ob_get_clean();

		$this->assertStringContainsString( 'name="aips_notification_preferences[generation_failed]"', $output );
		$this->assertStringContainsString( 'value="email"', $output );
	}
}
