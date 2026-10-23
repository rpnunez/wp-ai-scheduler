<?php
/**
 * Unit Tests for AIPS_Settings_Field_Renderer
 *
 * @package AI_Post_Scheduler
 */

class Test_AIPS_Settings_Field_Renderer extends WP_UnitTestCase {

	/**
	 * Test rendering empty or invalid arguments safely.
	 */
	public function test_render_empty_args() {
		ob_start();
		AIPS_Settings_Field_Renderer::render(array());
		$output = ob_get_clean();
		$this->assertSame('', $output);
	}

	/**
	 * Test rendering standard text input.
	 */
	public function test_render_text_input() {
		ob_start();
		AIPS_Settings_Field_Renderer::render(array(
			'type'        => 'text',
			'option_name' => 'test_text_opt',
			'value'       => 'Sample Value',
			'placeholder' => 'Enter text...',
			'description' => 'Helpful description.',
		));
		$output = ob_get_clean();

		$this->assertStringContainsString('type="text"', $output);
		$this->assertStringContainsString('id="test_text_opt"', $output);
		$this->assertStringContainsString('name="test_text_opt"', $output);
		$this->assertStringContainsString('value="Sample Value"', $output);
		$this->assertStringContainsString('placeholder="Enter text..."', $output);
		$this->assertStringContainsString('<p class="description">Helpful description.</p>', $output);
	}

	/**
	 * Test rendering password input with reveal toggle.
	 */
	public function test_render_password() {
		ob_start();
		AIPS_Settings_Field_Renderer::render(array(
			'type'        => 'password',
			'option_name' => 'test_api_key',
			'value'       => 'secret_123',
		));
		$output = ob_get_clean();

		$this->assertStringContainsString('type="password"', $output);
		$this->assertStringContainsString('class="regular-text aips-password-input"', $output);
		$this->assertStringContainsString('aips-toggle-password', $output);
		$this->assertStringContainsString('dashicons-visibility', $output);
	}

	/**
	 * Test rendering number input with min, max, step, and suffix.
	 */
	public function test_render_number() {
		ob_start();
		AIPS_Settings_Field_Renderer::render(array(
			'type'        => 'number',
			'option_name' => 'test_num_opt',
			'value'       => 15,
			'min'         => 1,
			'max'         => 100,
			'step'        => 5,
			'suffix'      => 'seconds',
		));
		$output = ob_get_clean();

		$this->assertStringContainsString('type="number"', $output);
		$this->assertStringContainsString('min="1"', $output);
		$this->assertStringContainsString('max="100"', $output);
		$this->assertStringContainsString('step="5"', $output);
		$this->assertStringContainsString('value="15"', $output);
		$this->assertStringContainsString('seconds', $output);
	}

	/**
	 * Test rendering checkbox and toggle switch.
	 */
	public function test_render_checkbox_and_toggle() {
		// Checkbox
		ob_start();
		AIPS_Settings_Field_Renderer::render(array(
			'type'        => 'checkbox',
			'option_name' => 'test_check_opt',
			'value'       => 1,
			'label'       => 'Check to enable',
		));
		$output_cb = ob_get_clean();

		$this->assertStringContainsString('type="checkbox"', $output_cb);
		$this->assertStringContainsString('checked=\'checked\'', $output_cb);
		$this->assertStringContainsString('Check to enable', $output_cb);

		// Toggle switch
		ob_start();
		AIPS_Settings_Field_Renderer::render(array(
			'type'        => 'toggle',
			'option_name' => 'test_toggle_opt',
			'value'       => 1,
			'label'       => 'Toggle Switch',
		));
		$output_toggle = ob_get_clean();

		$this->assertStringContainsString('aips-toggle-switch', $output_toggle);
		$this->assertStringContainsString('aips-toggle-slider', $output_toggle);
		$this->assertStringContainsString('Toggle Switch', $output_toggle);
	}

	/**
	 * Test rendering select and multiselect.
	 */
	public function test_render_select_and_multiselect() {
		// Single select
		ob_start();
		AIPS_Settings_Field_Renderer::render(array(
			'type'        => 'select',
			'option_name' => 'test_select_opt',
			'value'       => 'beta',
			'options'     => array(
				'alpha' => 'Alpha Option',
				'beta'  => 'Beta Option',
				'gamma' => 'Gamma Option',
			),
		));
		$output_sel = ob_get_clean();

		$this->assertStringContainsString('<select', $output_sel);
		$this->assertStringContainsString('value="beta" selected=\'selected\'', $output_sel);
		$this->assertStringContainsString('Beta Option', $output_sel);

		// Multi-select
		ob_start();
		AIPS_Settings_Field_Renderer::render(array(
			'type'        => 'multiselect',
			'option_name' => 'test_multi_opt',
			'value'       => array('opt1', 'opt3'),
			'options'     => array(
				'opt1' => 'First Choice',
				'opt2' => 'Second Choice',
				'opt3' => 'Third Choice',
			),
		));
		$output_multi = ob_get_clean();

		$this->assertStringContainsString('multiple="multiple"', $output_multi);
		$this->assertStringContainsString('name="test_multi_opt[]"', $output_multi);
		$this->assertStringContainsString('value="opt1" selected=\'selected\'', $output_multi);
		$this->assertStringContainsString('value="opt3" selected=\'selected\'', $output_multi);
	}

	/**
	 * Test rendering radio group.
	 */
	public function test_render_radio() {
		ob_start();
		AIPS_Settings_Field_Renderer::render(array(
			'type'        => 'radio',
			'option_name' => 'test_radio_opt',
			'value'       => 'yes',
			'options'     => array(
				'yes' => 'Affirmative',
				'no'  => 'Negative',
			),
		));
		$output = ob_get_clean();

		$this->assertStringContainsString('<fieldset>', $output);
		$this->assertStringContainsString('type="radio"', $output);
		$this->assertStringContainsString('value="yes" checked=\'checked\'', $output);
		$this->assertStringContainsString('Affirmative', $output);
	}

	/**
	 * Test rendering textarea.
	 */
	public function test_render_textarea() {
		ob_start();
		AIPS_Settings_Field_Renderer::render(array(
			'type'        => 'textarea',
			'option_name' => 'test_area_opt',
			'value'       => 'Line 1\nLine 2',
			'rows'        => 6,
		));
		$output = ob_get_clean();

		$this->assertStringContainsString('<textarea', $output);
		$this->assertStringContainsString('rows="6"', $output);
		$this->assertStringContainsString('Line 1\nLine 2', $output);
	}

	/**
	 * Test rendering media image field.
	 */
	public function test_render_media() {
		ob_start();
		AIPS_Settings_Field_Renderer::render(array(
			'type'        => 'media',
			'option_name' => 'test_media_opt',
			'value'       => 'https://example.com/image.png',
		));
		$output = ob_get_clean();

		$this->assertStringContainsString('aips-media-field', $output);
		$this->assertStringContainsString('aips-choose-media', $output);
		$this->assertStringContainsString('https://example.com/image.png', $output);
	}

	/**
	 * Test custom render callback.
	 */
	public function test_custom_render_callback() {
		ob_start();
		AIPS_Settings_Field_Renderer::render(array(
			'custom_render' => function($args, $value) {
				echo '<div class="custom-output">' . esc_html($value) . '</div>';
			},
			'value'         => 'Custom Payload',
			'description'   => 'Custom footer',
		));
		$output = ob_get_clean();

		$this->assertStringContainsString('<div class="custom-output">Custom Payload</div>', $output);
		$this->assertStringContainsString('Custom footer', $output);
	}
}
