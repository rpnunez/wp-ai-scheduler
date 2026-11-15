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
				),
			),
		) );

		$html = $this->renderer->get_field_html( $def, 'opt2' );
		$this->assertStringContainsString( '<select name="aips_test_select"', $html );
		$this->assertStringContainsString( 'value="opt1"', $html );
		$this->assertStringContainsString( 'value="opt2"', $html );
		$this->assertStringContainsString( 'selected', $html );
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
}
