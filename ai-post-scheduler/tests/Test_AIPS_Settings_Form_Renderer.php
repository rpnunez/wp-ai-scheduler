<?php
/**
 * Tests for AIPS_Settings_Form_Renderer.
 *
 * @package AI_Post_Scheduler
 */

class Test_AIPS_Settings_Form_Renderer extends WP_UnitTestCase {

	/**
	 * @var AIPS_Settings_Form_Renderer
	 */
	private $renderer;

	public function setUp(): void {
		parent::setUp();
		$this->renderer = new AIPS_Settings_Form_Renderer();
	}

	public function test_render_toggle() {
		$def = new AIPS_Setting_Definition( array(
			'key'          => 'aips_test_toggle',
			'type'         => AIPS_Setting_Definition::TYPE_BOOL,
			'control_type' => AIPS_Setting_Definition::CONTROL_TOGGLE,
			'label'        => 'Toggle Label',
			'description'  => 'Toggle Description',
		) );

		$html_on = $this->renderer->get_field_html( $def, true );
		$this->assertStringContainsString( 'type="hidden" name="aips_test_toggle" value="0"', $html_on );
		$this->assertStringContainsString( 'type="checkbox" name="aips_test_toggle"', $html_on );
		$this->assertStringContainsString( 'role="switch"', $html_on );
		$this->assertStringContainsString( 'aria-checked="true"', $html_on );
		$this->assertStringContainsString( 'checked', $html_on );
		$this->assertStringContainsString( 'Toggle Description', $html_on );

		$html_off = $this->renderer->get_field_html( $def, false );
		$this->assertStringContainsString( 'aria-checked="false"', $html_off );
		$this->assertStringNotContainsString( 'checked=\'checked\'', $html_off );
	}

	public function test_render_text() {
		$def = new AIPS_Setting_Definition( array(
			'key'             => 'aips_test_text',
			'type'            => AIPS_Setting_Definition::TYPE_STRING,
			'control_type'    => AIPS_Setting_Definition::CONTROL_TEXT,
			'control_options' => array( 'placeholder' => 'Enter text here' ),
		) );

		$html = $this->renderer->get_field_html( $def, 'Current Value' );
		$this->assertStringContainsString( 'type="text"', $html );
		$this->assertStringContainsString( 'name="aips_test_text"', $html );
		$this->assertStringContainsString( 'value="Current Value"', $html );
		$this->assertStringContainsString( 'placeholder="Enter text here"', $html );
		$this->assertStringContainsString( 'class="regular-text"', $html );
	}

	public function test_render_number() {
		$def = new AIPS_Setting_Definition( array(
			'key'             => 'aips_test_number',
			'type'            => AIPS_Setting_Definition::TYPE_INT,
			'control_type'    => AIPS_Setting_Definition::CONTROL_NUMBER,
			'control_options' => array(
				'min'  => 5,
				'max'  => 100,
				'step' => 5,
				'unit' => 'seconds',
			),
		) );

		$html = $this->renderer->get_field_html( $def, 25 );
		$this->assertStringContainsString( 'type="number"', $html );
		$this->assertStringContainsString( 'name="aips_test_number"', $html );
		$this->assertStringContainsString( 'value="25"', $html );
		$this->assertStringContainsString( 'min="5"', $html );
		$this->assertStringContainsString( 'max="100"', $html );
		$this->assertStringContainsString( 'step="5"', $html );
		$this->assertStringContainsString( '<span class="aips-unit">seconds</span>', $html );
	}

	public function test_render_select() {
		$def = new AIPS_Setting_Definition( array(
			'key'             => 'aips_test_select',
			'type'            => AIPS_Setting_Definition::TYPE_STRING,
			'control_type'    => AIPS_Setting_Definition::CONTROL_SELECT,
			'control_options' => array(
				'options' => array(
					'opt1' => 'First Option',
					'opt2' => 'Second Option',
					'opt3' => 'Third Option',
					'opt4' => 'Fourth Option',
				),
			),
		) );

		$html = $this->renderer->get_field_html( $def, 'opt2' );
		$this->assertSame( 4, substr_count( $html, '<option ' ) );
		$this->assertStringContainsString( '<select name="aips_test_select"', $html );
		$this->assertStringContainsString( 'value="opt1"', $html );
		$this->assertStringContainsString( 'value="opt2"', $html );
		$this->assertSame( 1, substr_count( $html, 'selected' ) );
		$this->assertStringContainsString( 'Second Option', $html );
	}

	public function test_render_multiselect() {
		$def = new AIPS_Setting_Definition( array(
			'key'             => 'aips_test_multiselect',
			'type'            => AIPS_Setting_Definition::TYPE_ARRAY,
			'control_type'    => AIPS_Setting_Definition::CONTROL_MULTISELECT,
			'control_options' => array(
				'options' => array(
					'post' => 'Posts',
					'page' => 'Pages',
				),
			),
		) );

		$html = $this->renderer->get_field_html( $def, array( 'post' ) );
		$this->assertStringContainsString( 'name="aips_test_multiselect[]"', $html );
		$this->assertStringContainsString( 'value="post"', $html );
		$this->assertStringContainsString( 'value="page"', $html );
		$this->assertStringContainsString( 'checked', $html );
	}

	public function test_render_multiselect_dropdown_emits_one_option_per_choice_and_selects_saved_values() {
		$def = new AIPS_Setting_Definition( array(
			'key'             => 'aips_test_multiselect_dropdown',
			'type'            => AIPS_Setting_Definition::TYPE_ARRAY,
			'control_type'    => AIPS_Setting_Definition::CONTROL_MULTISELECT,
			'control_options' => array(
				'render_as' => 'select',
				'options'   => array(
					1 => 'First Option',
					2 => 'Second Option',
					3 => 'Third Option',
					4 => 'Fourth Option',
				),
			),
		) );

		$html = $this->renderer->get_field_html( $def, array( '2' ) );
		$this->assertSame( 4, substr_count( $html, '<option ' ) );
		$this->assertSame( 1, substr_count( $html, 'selected' ) );
		$this->assertStringContainsString( 'name="aips_test_multiselect_dropdown[]"', $html );
		$this->assertStringContainsString( 'value="2" selected', $html );
	}

	public function test_choice_renderer_preserves_saved_values_missing_from_current_choices() {
		$select = new AIPS_Setting_Definition( array(
			'key'             => 'aips_test_stale_select',
			'type'            => AIPS_Setting_Definition::TYPE_STRING,
			'control_type'    => AIPS_Setting_Definition::CONTROL_SELECT,
			'control_options' => array( 'options' => array( 'current' => 'Current choice' ) ),
		) );
		$multiselect = new AIPS_Setting_Definition( array(
			'key'             => 'aips_test_stale_multiselect',
			'type'            => AIPS_Setting_Definition::TYPE_ARRAY,
			'control_type'    => AIPS_Setting_Definition::CONTROL_MULTISELECT,
			'control_options' => array( 'options' => array( 'current' => 'Current choice' ) ),
		) );

		$select_html = $this->renderer->get_field_html( $select, 'retired-choice' );
		$this->assertStringContainsString( 'value="retired-choice" selected', $select_html );
		$this->assertStringContainsString( 'Saved value not currently available: retired-choice', $select_html );

		$multiselect_html = $this->renderer->get_field_html( $multiselect, array( 'retired-choice' ) );
		$this->assertStringContainsString( 'value="retired-choice" checked', $multiselect_html );
		$this->assertStringContainsString( 'Saved value not currently available: retired-choice', $multiselect_html );
	}

	public function test_render_secret() {
		$def = new AIPS_Setting_Definition( array(
			'key'          => 'aips_test_secret',
			'type'         => AIPS_Setting_Definition::TYPE_STRING,
			'control_type' => AIPS_Setting_Definition::CONTROL_SECRET,
		) );

		$html = $this->renderer->get_field_html( $def, 'secret_token_123' );
		$this->assertStringContainsString( 'type="password"', $html );
		$this->assertStringContainsString( 'name="aips_test_secret"', $html );
		$this->assertStringContainsString( 'value="secret_token_123"', $html );
		$this->assertStringContainsString( 'aips-secret-toggle', $html );
	}

	public function test_render_slider() {
		$def = new AIPS_Setting_Definition( array(
			'key'             => 'aips_test_slider',
			'type'            => AIPS_Setting_Definition::TYPE_FLOAT,
			'control_type'    => AIPS_Setting_Definition::CONTROL_SLIDER,
			'control_options' => array( 'min' => 0.0, 'max' => 1.0, 'step' => 0.1 ),
		) );

		$html = $this->renderer->get_field_html( $def, 0.7 );
		$this->assertStringContainsString( 'type="range"', $html );
		$this->assertStringContainsString( 'min="0"', $html );
		$this->assertStringContainsString( 'max="1"', $html );
		$this->assertStringContainsString( 'step="0.1"', $html );
		$this->assertStringContainsString( 'value="0.7"', $html );
		$this->assertStringContainsString( 'class="aips-slider-value"', $html );
	}

	public function test_field_dependencies() {
		$def = new AIPS_Setting_Definition( array(
			'key'          => 'aips_child_field',
			'type'         => AIPS_Setting_Definition::TYPE_STRING,
			'dependencies' => array( 'field' => 'aips_parent_field', 'value' => 'enabled' ),
		) );

		$html = $this->renderer->get_field_html( $def, '' );
		$this->assertStringContainsString( 'data-aips-depends-on="aips_parent_field"', $html );
		$this->assertStringContainsString( 'data-aips-depends-value="enabled"', $html );
	}

	public function test_render_toggle_clean_attributes() {
		$def = new AIPS_Setting_Definition( array(
			'key'          => 'aips_test_sync',
			'type'         => AIPS_Setting_Definition::TYPE_BOOL,
			'control_type' => AIPS_Setting_Definition::CONTROL_TOGGLE,
		) );

		$html = $this->renderer->get_field_html( $def, true );
		$this->assertStringContainsString( 'role="switch"', $html );
		$this->assertStringContainsString( 'aria-checked="true"', $html );
		$this->assertStringNotContainsString( 'onchange=', $html );
	}

	public function test_render_select_callable_options() {
		$def = new AIPS_Setting_Definition( array(
			'key'             => 'aips_test_callable',
			'type'            => AIPS_Setting_Definition::TYPE_STRING,
			'control_type'    => AIPS_Setting_Definition::CONTROL_SELECT,
			'control_options' => array(
				'options' => function() {
					return array(
						'val1' => 'Callable Label 1',
						'val2' => 'Callable Label 2',
					);
				},
			),
		) );

		$html = $this->renderer->get_field_html( $def, 'val1' );
		$this->assertStringContainsString( 'Callable Label 1', $html );
		$this->assertStringContainsString( 'Callable Label 2', $html );
		$this->assertStringContainsString( 'selected', $html );
	}

	public function test_render_custom_render_callback() {
		$def = new AIPS_Setting_Definition( array(
			'key'             => 'aips_test_custom_render',
			'type'            => AIPS_Setting_Definition::TYPE_INT,
			'control_type'    => AIPS_Setting_Definition::CONTROL_SELECT,
			'control_options' => array(
				'render_callback' => function( AIPS_Setting_Definition $d, $val ) {
					return '<div class="custom-dropdown">' . esc_html( $d->get_key() ) . ':' . esc_html( (string) $val ) . '</div>';
				},
			),
		) );

		$html = $this->renderer->get_field_html( $def, 42 );
		$this->assertStringContainsString( '<div class="custom-dropdown">aips_test_custom_render:42</div>', $html );
	}

	public function test_render_secret_clean_markup() {
		$def = new AIPS_Setting_Definition( array(
			'key'          => 'aips_test_secret_inline',
			'type'         => AIPS_Setting_Definition::TYPE_STRING,
			'control_type' => AIPS_Setting_Definition::CONTROL_SECRET,
		) );

		$html = $this->renderer->get_field_html( $def, 'pass123' );
		$this->assertStringContainsString( 'data-target="aips_test_secret_inline"', $html );
		$this->assertStringContainsString( 'dashicons-visibility', $html );
		$this->assertStringNotContainsString( 'onclick=', $html );
		$this->assertStringNotContainsString( 'style=', $html );
	}

	public function test_render_action_clean_markup() {
		$def = new AIPS_Setting_Definition( array(
			'key'             => 'aips_test_action',
			'type'            => AIPS_Setting_Definition::TYPE_STRING,
			'control_type'    => AIPS_Setting_Definition::CONTROL_ACTION,
			'control_options' => array(
				'button_label' => 'Flush Queue',
				'id'           => 'custom-action-id',
				'class'        => 'button button-primary',
				'extra_html'   => '<span>Extra info</span>',
			),
		) );

		$html = $this->renderer->get_field_html( $def, '' );
		$this->assertStringContainsString( '<button type="button"', $html );
		$this->assertStringContainsString( 'id="custom-action-id"', $html );
		$this->assertStringContainsString( 'Flush Queue', $html );
		$this->assertStringContainsString( '<span>Extra info</span>', $html );
		$this->assertStringNotContainsString( 'style=', $html );
	}

	public function test_render_slider_clean_markup() {
		$def = new AIPS_Setting_Definition( array(
			'key'          => 'aips_test_slider_clean',
			'type'         => AIPS_Setting_Definition::TYPE_FLOAT,
			'control_type' => AIPS_Setting_Definition::CONTROL_SLIDER,
		) );

		$html = $this->renderer->get_field_html( $def, 0.5 );
		$this->assertStringNotContainsString( 'oninput=', $html );
		$this->assertStringNotContainsString( 'style=', $html );
	}
}
