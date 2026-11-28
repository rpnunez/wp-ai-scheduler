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

	/**
	 * @var array<string, mixed>
	 */
	private $original_options = array();

	public function setUp(): void {
		parent::setUp();
		$this->registry = AIPS_Settings_Registry::instance();
		$this->renderer = new AIPS_Settings_Form_Renderer();
		$this->manager  = new AIPS_Settings_Manager( $this->registry, $this->renderer );
	}

	public function tearDown(): void {
		foreach ( $this->original_options as $key => $value ) {
			if ( null === $value ) {
				delete_option( $key );
			} else {
				update_option( $key, $value );
			}
		}
		$this->original_options = array();
		parent::tearDown();
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

	public function test_registered_settings_save_and_load_values_without_losing_unrelated_options() {
		$keys = array(
			'aips_related_posts_layout',
			'aips_embeddings_model',
			'aips_embeddings_enabled',
			'aips_ai_assistance_contexts',
			'aips_default_post_status',
		);
		foreach ( $keys as $key ) {
			$this->original_options[ $key ] = get_option( $key, null );
		}

		update_option( 'aips_default_post_status', 'pending' );
		$this->manager->register_all();

		update_option( 'aips_related_posts_layout', 'list' );
		update_option( 'aips_embeddings_model', 'test-embedding-model' );
		update_option( 'aips_embeddings_enabled', '0' );
		update_option( 'aips_ai_assistance_contexts', array( 'authors', 'templates', 'invalid-context' ) );

		$this->assertSame( 'list', get_option( 'aips_related_posts_layout' ) );
		$this->assertSame( 'test-embedding-model', get_option( 'aips_embeddings_model' ) );
		$this->assertSame( 0, get_option( 'aips_embeddings_enabled' ) );
		$this->assertSame( array( 'authors', 'templates' ), get_option( 'aips_ai_assistance_contexts' ) );
		$this->assertSame( 'pending', get_option( 'aips_default_post_status' ), 'Saving other settings must preserve unsubmitted options.' );

		ob_start();
		$this->manager->render_field_callback( array( 'key' => 'aips_related_posts_layout' ) );
		$rendered = ob_get_clean();
		$this->assertStringContainsString( 'value="list" selected', $rendered, 'The settings UI must render the saved value as selected.' );
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

	public function test_render_field_callback_supports_custom_render_callback() {
		$custom_def = new AIPS_Setting_Definition( array(
			'key'             => 'aips_unit_test_custom_render',
			'type'            => null,
			'control_type'    => AIPS_Setting_Definition::CONTROL_ACTION,
			'description'     => 'Custom callback help text',
			'render_callback' => static function() {
				return '<div id="custom-rendered-markup">Special Content</div>';
			},
		) );

		$this->registry->register( $custom_def );

		ob_start();
		$this->manager->render_field_callback( array( 'key' => 'aips_unit_test_custom_render' ) );
		$output = ob_get_clean();

		$this->assertStringContainsString( '<div id="custom-rendered-markup">Special Content</div>', $output );
		$this->assertStringContainsString( 'Custom callback help text', $output );
	}
}
