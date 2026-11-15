<?php
/**
 * AIPS Settings Form Renderer — component-based UI renderer for settings controls.
 *
 * Replaces manual HTML string assembly in callback methods with standardized,
 * accessible, and secure UI primitives.
 *
 * @package AI_Post_Scheduler
 * @since   3.7.25
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AIPS_Settings_Form_Renderer {

	/**
	 * Render a setting field based on its definition.
	 *
	 * @param AIPS_Setting_Definition $definition Setting definition object.
	 * @param mixed                   $value      Current option value. If null, resolved from config.
	 * @return void
	 */
	public function render_field( AIPS_Setting_Definition $definition, $value = null ) {
		echo $this->get_field_html( $definition, $value ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/**
	 * Get the complete HTML markup for a setting field.
	 *
	 * @param AIPS_Setting_Definition $definition Setting definition object.
	 * @param mixed                   $value      Current option value.
	 * @return string HTML markup.
	 */
	public function get_field_html( AIPS_Setting_Definition $definition, $value = null ) {
		if ( null === $value ) {
			$value = AIPS_Config::get_instance()->get_option( $definition->get_key(), $definition->get_default() );
		}

		$control_type = $definition->get_control_type();
		$control_html = '';

		switch ( $control_type ) {
			case AIPS_Setting_Definition::CONTROL_TOGGLE:
				$control_html = $this->render_toggle( $definition, $value );
				break;

			case AIPS_Setting_Definition::CONTROL_NUMBER:
				$control_html = $this->render_number( $definition, $value );
				break;

			case AIPS_Setting_Definition::CONTROL_SELECT:
				$control_html = $this->render_select( $definition, $value );
				break;

			case AIPS_Setting_Definition::CONTROL_MULTISELECT:
				$control_html = $this->render_multiselect( $definition, $value );
				break;

			case AIPS_Setting_Definition::CONTROL_TEXTAREA:
				$control_html = $this->render_textarea( $definition, $value );
				break;

			case AIPS_Setting_Definition::CONTROL_SECRET:
				$control_html = $this->render_secret( $definition, $value );
				break;

			case AIPS_Setting_Definition::CONTROL_SLIDER:
				$control_html = $this->render_slider( $definition, $value );
				break;

			case AIPS_Setting_Definition::CONTROL_ACTION:
				$control_html = $this->render_action( $definition, $value );
				break;

			case AIPS_Setting_Definition::CONTROL_TEXT:
			default:
				$control_html = $this->render_text( $definition, $value );
				break;
		}

		$wrapper_attrs = $this->build_wrapper_attributes( $definition );
		$description   = $definition->get_description();
		$desc_html     = '';

		if ( ! empty( $description ) ) {
			$desc_html = sprintf(
				'<p class="description" id="%s_desc">%s</p>',
				esc_attr( $definition->get_key() ),
				esc_html( $description )
			);
		}

		if ( ! empty( $wrapper_attrs ) ) {
			return sprintf(
				'<div class="aips-setting-field-wrapper" %s>%s%s</div>',
				$wrapper_attrs,
				$control_html,
				$desc_html
			);
		}

		return $control_html . $desc_html;
	}

	/**
	 * Render a boolean switch / checkbox.
	 *
	 * @param AIPS_Setting_Definition $def   Setting definition.
	 * @param mixed                   $value Current value.
	 * @return string HTML.
	 */
	public function render_toggle( AIPS_Setting_Definition $def, $value ) {
		$key       = $def->get_key();
		$opts      = $def->get_control_options();
		$name      = ! empty( $opts['name'] ) ? $opts['name'] : $key;
		$id        = ! empty( $opts['id'] ) ? $opts['id'] : $key;
		$is_active = (bool) $value;

		return sprintf(
			'<input type="hidden" name="%1$s" value="0">' .
			'<label class="aips-switch" for="%2$s">' .
			'<input type="checkbox" name="%1$s" id="%2$s" value="1" role="switch" aria-checked="%3$s" onchange="this.setAttribute(\'aria-checked\', this.checked ? \'true\' : \'false\')" %4$s>' .
			'<span class="aips-slider round" aria-hidden="true"></span>' .
			'</label>',
			esc_attr( $name ),
			esc_attr( $id ),
			$is_active ? 'true' : 'false',
			checked( $is_active, true, false )
		);
	}

	/**
	 * Render a text input field.
	 *
	 * @param AIPS_Setting_Definition $def   Setting definition.
	 * @param mixed                   $value Current value.
	 * @return string HTML.
	 */
	public function render_text( AIPS_Setting_Definition $def, $value ) {
		$key     = $def->get_key();
		$opts    = $def->get_control_options();
		$class   = ! empty( $opts['class'] ) ? $opts['class'] : 'regular-text';
		$ph      = ! empty( $opts['placeholder'] ) ? ' placeholder="' . esc_attr( $opts['placeholder'] ) . '"' : '';
		$val_str = is_scalar( $value ) ? (string) $value : '';

		return sprintf(
			'<input type="text" name="%1$s" id="%1$s" value="%2$s" class="%3$s"%4$s>',
			esc_attr( $key ),
			esc_attr( $val_str ),
			esc_attr( $class ),
			$ph
		);
	}

	/**
	 * Render a number input field with step/min/max constraints and optional unit label.
	 *
	 * @param AIPS_Setting_Definition $def   Setting definition.
	 * @param mixed                   $value Current value.
	 * @return string HTML.
	 */
	public function render_number( AIPS_Setting_Definition $def, $value ) {
		$key   = $def->get_key();
		$opts  = $def->get_control_options();
		$min   = array_key_exists( 'min', $opts ) ? ' min="' . esc_attr( (string) $opts['min'] ) . '"' : '';
		$max   = array_key_exists( 'max', $opts ) ? ' max="' . esc_attr( (string) $opts['max'] ) . '"' : '';
		$step  = array_key_exists( 'step', $opts ) ? ' step="' . esc_attr( (string) $opts['step'] ) . '"' : ' step="1"';
		$class = ! empty( $opts['class'] ) ? $opts['class'] : 'small-text';
		$unit  = ! empty( $opts['unit'] ) ? ' <span class="aips-unit">' . esc_html( $opts['unit'] ) . '</span>' : '';

		return sprintf(
			'<input type="number" name="%1$s" id="%1$s" value="%2$s"%3$s%4$s%5$s class="%6$s">%7$s',
			esc_attr( $key ),
			esc_attr( (string) $value ),
			$min,
			$max,
			$step,
			esc_attr( $class ),
			$unit
		);
	}

	/**
	 * Render a select dropdown.
	 *
	 * @param AIPS_Setting_Definition $def   Setting definition.
	 * @param mixed                   $value Current value.
	 * @return string HTML.
	 */
	public function render_select( AIPS_Setting_Definition $def, $value ) {
		$key     = $def->get_key();
		$opts    = $def->get_control_options();
		$name    = ! empty( $opts['name'] ) ? $opts['name'] : $key;
		$id      = ! empty( $opts['id'] ) ? $opts['id'] : $key;
		$val_str = is_scalar( $value ) ? (string) $value : '';

		// Native WordPress taxonomy dropdown support
		if ( ! empty( $opts['taxonomy'] ) && 'category' === $opts['taxonomy'] && function_exists( 'wp_dropdown_categories' ) ) {
			return wp_dropdown_categories( array(
				'echo'             => 0,
				'name'             => $name,
				'id'               => $id,
				'selected'         => (int) $value,
				'show_option_none' => ! empty( $opts['show_option_none'] ) ? $opts['show_option_none'] : __( 'None', 'ai-post-scheduler' ),
				'hide_empty'       => 0,
			) );
		}

		// Native WordPress user dropdown support
		if ( ! empty( $opts['user_dropdown'] ) && function_exists( 'wp_dropdown_users' ) ) {
			return wp_dropdown_users( array(
				'echo'     => 0,
				'name'     => $name,
				'id'       => $id,
				'selected' => (int) $value,
			) );
		}

		$choices = array();
		if ( isset( $opts['options'] ) ) {
			if ( is_callable( $opts['options'] ) ) {
				$choices = call_user_func( $opts['options'] );
			} elseif ( is_array( $opts['options'] ) ) {
				$choices = $opts['options'];
			}
		}

		$options_html = '';
		foreach ( $choices as $opt_key => $opt_label ) {
			$options_html .= sprintf(
				'<option value="%1$s" %2$s>%3$s</option>',
				esc_attr( (string) $opt_key ),
				selected( (string) $opt_key, $val_str, false ),
				esc_html( (string) $opt_label )
			);
		}

		return sprintf(
			'<select name="%1$s" id="%2$s">%3$s</select>',
			esc_attr( $name ),
			esc_attr( $id ),
			$options_html
		);
	}

	/**
	 * Render a multiselect control or checkbox group.
	 *
	 * @param AIPS_Setting_Definition $def   Setting definition.
	 * @param mixed                   $value Current value.
	 * @return string HTML.
	 */
	public function render_multiselect( AIPS_Setting_Definition $def, $value ) {
		$key          = $def->get_key();
		$opts         = $def->get_control_options();
		$name         = ! empty( $opts['name'] ) ? $opts['name'] : $key;
		$id           = ! empty( $opts['id'] ) ? $opts['id'] : $key;
		$choices      = array();
		if ( isset( $opts['options'] ) ) {
			if ( is_callable( $opts['options'] ) ) {
				$choices = call_user_func( $opts['options'] );
			} elseif ( is_array( $opts['options'] ) ) {
				$choices = $opts['options'];
			}
		}
		$active_items = is_array( $value ) ? $value : array();

		if ( ! empty( $opts['render_as'] ) && 'select' === $opts['render_as'] ) {
			$options_html = '';
			foreach ( $choices as $opt_key => $opt_label ) {
				$options_html .= sprintf(
					'<option value="%1$s" %2$s>%3$s</option>',
					esc_attr( (string) $opt_key ),
					selected( in_array( $opt_key, $active_items, true ), true, false ),
					esc_html( (string) $opt_label )
				);
			}

			return sprintf(
				'<input type="hidden" name="%1$s[]" value="">' .
				'<select name="%1$s[]" id="%2$s" multiple size="%3$d" class="regular-text">%4$s</select>',
				esc_attr( $name ),
				esc_attr( $id ),
				min( 8, max( 3, count( $choices ) ) ),
				$options_html
			);
		}

		// Default checkbox group.
		$html = sprintf( '<input type="hidden" name="%s[]" value="">', esc_attr( $name ) );
		$html .= '<fieldset class="aips-checkbox-group">';

		foreach ( $choices as $opt_key => $opt_label ) {
			$item_id   = esc_attr( $id . '_' . sanitize_key( $opt_key ) );
			$is_active = in_array( $opt_key, $active_items, true );

			$html .= sprintf(
				'<label for="%1$s" class="aips-checkbox-label">' .
				'<input type="checkbox" name="%2$s[]" id="%1$s" value="%3$s" %4$s> ' .
				'%5$s' .
				'</label><br>',
				$item_id,
				esc_attr( $name ),
				esc_attr( (string) $opt_key ),
				checked( $is_active, true, false ),
				esc_html( (string) $opt_label )
			);
		}

		$html .= '</fieldset>';
		return $html;
	}

	/**
	 * Render a password / secret credential input with a reveal toggle button.
	 *
	 * @param AIPS_Setting_Definition $def   Setting definition.
	 * @param mixed                   $value Current value.
	 * @return string HTML.
	 */
	public function render_secret( AIPS_Setting_Definition $def, $value ) {
		$key         = $def->get_key();
		$opts        = $def->get_control_options();
		$name        = ! empty( $opts['name'] ) ? $opts['name'] : $key;
		$id          = ! empty( $opts['id'] ) ? $opts['id'] : $key;
		$val_str     = is_scalar( $value ) ? (string) $value : '';
		$has_value   = ! empty( $val_str );
		$placeholder = ! empty( $opts['placeholder'] ) ? $opts['placeholder'] : ( $has_value ? '••••••••••••••••' : '' );

		return sprintf(
			'<div class="aips-secret-field-container" style="display:inline-flex;align-items:center;gap:6px;">' .
			'<input type="password" name="%1$s" id="%2$s" value="%3$s" class="regular-text" autocomplete="new-password" placeholder="%4$s">' .
			'<button type="button" class="button button-secondary aips-secret-toggle" data-target="%2$s" aria-label="%5$s" onclick="(function(btn){var input=document.getElementById(\'%2$s\');if(!input)return;var isPass=input.type===\'password\';input.type=isPass?\'text\':\'password\';var icon=btn.querySelector(\'.dashicons\');if(icon){icon.classList.toggle(\'dashicons-visibility\',!isPass);icon.classList.toggle(\'dashicons-hidden\',isPass);}})(this);">' .
			'<span class="dashicons dashicons-visibility" aria-hidden="true"></span>' .
			'</button>' .
			'</div>',
			esc_attr( $name ),
			esc_attr( $id ),
			esc_attr( $val_str ),
			esc_attr( $placeholder ),
			esc_attr__( 'Show or hide secret', 'ai-post-scheduler' )
		);
	}

	/**
	 * Render an action button / trigger control.
	 *
	 * @param AIPS_Setting_Definition $def   Setting definition.
	 * @param mixed                   $value Current value.
	 * @return string HTML.
	 */
	public function render_action( AIPS_Setting_Definition $def, $value ) {
		$key   = $def->get_key();
		$opts  = $def->get_control_options();
		$label = ! empty( $opts['button_label'] ) ? $opts['button_label'] : __( 'Run Action', 'ai-post-scheduler' );
		$class = ! empty( $opts['class'] ) ? $opts['class'] : 'button button-secondary';
		$id    = ! empty( $opts['id'] ) ? $opts['id'] : esc_attr( $key );
		$extra = ! empty( $opts['extra_html'] ) ? ( is_callable( $opts['extra_html'] ) ? call_user_func( $opts['extra_html'] ) : (string) $opts['extra_html'] ) : '';

		return sprintf(
			'<div class="aips-settings-action-row" style="display:inline-flex;align-items:center;gap:10px;">' .
			'<button type="button" name="%1$s" id="%2$s" class="%3$s">%4$s</button>%5$s' .
			'</div>',
			esc_attr( $key ),
			esc_attr( $id ),
			esc_attr( $class ),
			esc_html( $label ),
			$extra
		);
	}

	/**
	 * Render a textarea field.
	 *
	 * @param AIPS_Setting_Definition $def   Setting definition.
	 * @param mixed                   $value Current value.
	 * @return string HTML.
	 */
	public function render_textarea( AIPS_Setting_Definition $def, $value ) {
		$key     = $def->get_key();
		$opts    = $def->get_control_options();
		$rows    = ! empty( $opts['rows'] ) ? (int) $opts['rows'] : 4;
		$class   = ! empty( $opts['class'] ) ? $opts['class'] : 'large-text';
		$ph      = ! empty( $opts['placeholder'] ) ? ' placeholder="' . esc_attr( $opts['placeholder'] ) . '"' : '';
		$val_str = is_scalar( $value ) ? (string) $value : '';

		return sprintf(
			'<textarea name="%1$s" id="%1$s" rows="%2$d" class="%3$s"%4$s>%5$s</textarea>',
			esc_attr( $key ),
			$rows,
			esc_attr( $class ),
			$ph,
			esc_textarea( $val_str )
		);
	}

	/**
	 * Render an interactive slider coupled with a numeric indicator.
	 *
	 * @param AIPS_Setting_Definition $def   Setting definition.
	 * @param mixed                   $value Current value.
	 * @return string HTML.
	 */
	public function render_slider( AIPS_Setting_Definition $def, $value ) {
		$key   = $def->get_key();
		$opts  = $def->get_control_options();
		$min   = array_key_exists( 'min', $opts ) ? (float) $opts['min'] : 0.0;
		$max   = array_key_exists( 'max', $opts ) ? (float) $opts['max'] : 1.0;
		$step  = array_key_exists( 'step', $opts ) ? (float) $opts['step'] : 0.05;
		$val   = (float) $value;

		return sprintf(
			'<div class="aips-slider-container" style="display:inline-flex;align-items:center;gap:12px;">' .
			'<input type="range" name="%1$s" id="%1$s" min="%2$s" max="%3$s" step="%4$s" value="%5$s" class="aips-slider-input" oninput="this.nextElementSibling.textContent=this.value">' .
			'<span class="aips-slider-value" aria-live="polite" style="font-weight:600;min-width:3em;">%5$s</span>' .
			'</div>',
			esc_attr( $key ),
			esc_attr( (string) $min ),
			esc_attr( (string) $max ),
			esc_attr( (string) $step ),
			esc_attr( (string) $val )
		);
	}

	/**
	 * Build HTML data attributes for field wrapper based on setting dependencies.
	 *
	 * @param AIPS_Setting_Definition $def Setting definition.
	 * @return string HTML attribute string.
	 */
	private function build_wrapper_attributes( AIPS_Setting_Definition $def ) {
		$deps = $def->get_dependencies();
		if ( empty( $deps ) ) {
			return '';
		}

		$attrs = array();
		if ( isset( $deps['field'] ) ) {
			$attrs[] = sprintf( 'data-aips-depends-on="%s"', esc_attr( $deps['field'] ) );
		}
		if ( isset( $deps['value'] ) ) {
			$attrs[] = sprintf( 'data-aips-depends-value="%s"', esc_attr( (string) $deps['value'] ) );
		}

		return implode( ' ', $attrs );
	}
}
