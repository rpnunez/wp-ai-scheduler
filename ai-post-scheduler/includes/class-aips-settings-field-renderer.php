<?php
/**
 * Settings Field Renderer Class
 *
 * Declarative renderer for WordPress Settings API fields. Eliminates repetitive
 * field callback boilerplate and standardizes form markup, descriptions,
 * core helpers (checked, selected, disabled), and WordPress utilities.
 *
 * @package AI_Post_Scheduler
 * @since   3.7.20
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Settings_Field_Renderer
 */
class AIPS_Settings_Field_Renderer {

	/**
	 * Main entry point for WordPress Settings API field rendering.
	 *
	 * Passed as callback to add_settings_field().
	 *
	 * @param array<string, mixed> $args Field configuration arguments.
	 * @return void
	 */
	public static function render($args) {
		if (empty($args) || !is_array($args)) {
			return;
		}

		$type        = isset($args['type']) ? sanitize_key($args['type']) : 'text';
		$option_name = isset($args['option_name']) ? sanitize_key($args['option_name']) : '';
		if (empty($option_name) && !empty($args['field_id'])) {
			$option_name = sanitize_key($args['field_id']);
		}

		$id          = isset($args['id']) ? sanitize_key($args['id']) : $option_name;
		$name        = isset($args['name']) ? esc_attr($args['name']) : $option_name;
		$description = isset($args['description']) ? $args['description'] : '';
		$default     = isset($args['default']) ? $args['default'] : '';
		$class       = isset($args['class']) ? $args['class'] : '';

		// Resolve current option value.
		if (isset($args['value'])) {
			$value = $args['value'];
		} elseif (!empty($option_name)) {
			$value = AIPS_Config::get_instance()->get_option($option_name, $default);
		} else {
			$value = $default;
		}

		// Handle custom render callback if provided.
		if (!empty($args['custom_render']) && is_callable($args['custom_render'])) {
			call_user_func($args['custom_render'], $args, $value);
			self::render_description($description);
			return;
		}

		switch ($type) {
			case 'checkbox':
				self::render_checkbox($id, $name, $value, $args);
				break;

			case 'toggle':
				self::render_toggle($id, $name, $value, $args);
				break;

			case 'select':
				self::render_select($id, $name, $value, $args, $class);
				break;

			case 'multiselect':
				self::render_multiselect($id, $name, $value, $args, $class);
				break;

			case 'radio':
				self::render_radio($id, $name, $value, $args);
				break;

			case 'textarea':
				self::render_textarea($id, $name, $value, $args, $class);
				break;

			case 'number':
				self::render_number($id, $name, $value, $args, $class);
				break;

			case 'category':
				self::render_category($name, $value, $args);
				break;

			case 'code_editor':
				self::render_code_editor($id, $name, $value, $args, $class);
				break;

			case 'media':
				self::render_media($id, $name, $value, $args);
				break;

			case 'password':
				self::render_password($id, $name, $value, $args, $class);
				break;

			case 'url':
			case 'email':
			case 'text':
			default:
				self::render_text_input($type, $id, $name, $value, $args, $class);
				break;
		}

		self::render_description($description);
	}

	/**
	 * Render standard text/url/email input.
	 *
	 * @param string               $type  Input type.
	 * @param string               $id    Field ID.
	 * @param string               $name  Field name.
	 * @param mixed                $value Current value.
	 * @param array<string, mixed> $args  Arguments.
	 * @param string               $class Additional CSS class.
	 * @return void
	 */
	private static function render_text_input($type, $id, $name, $value, $args, $class) {
		$class_str    = 'regular-text ' . esc_attr($class);
		$placeholder  = isset($args['placeholder']) ? ' placeholder="' . esc_attr($args['placeholder']) . '"' : '';
		$disabled     = !empty($args['disabled']) ? ' disabled' : '';
		$autocomplete = isset($args['autocomplete']) ? ' autocomplete="' . esc_attr($args['autocomplete']) . '"' : '';

		echo '<input type="' . esc_attr($type) . '" id="' . esc_attr($id) . '" name="' . esc_attr($name) . '" value="' . esc_attr((string) $value) . '" class="' . esc_attr(trim($class_str)) . '"' . $placeholder . $disabled . $autocomplete . ' />'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/**
	 * Render password input with reveal toggle.
	 *
	 * @param string               $id    Field ID.
	 * @param string               $name  Field name.
	 * @param mixed                $value Current value.
	 * @param array<string, mixed> $args  Arguments.
	 * @param string               $class Additional CSS class.
	 * @return void
	 */
	private static function render_password($id, $name, $value, $args, $class) {
		$class_str = 'regular-text aips-password-input ' . esc_attr($class);
		echo '<div class="aips-password-field-wrapper">';
		echo '<input type="password" id="' . esc_attr($id) . '" name="' . esc_attr($name) . '" value="' . esc_attr((string) $value) . '" class="' . esc_attr(trim($class_str)) . '" autocomplete="new-password" />';
		echo ' <button type="button" class="button button-secondary aips-toggle-password" data-target="' . esc_attr($id) . '"><span class="dashicons dashicons-visibility" aria-hidden="true"></span><span class="screen-reader-text">' . esc_html__('Toggle visibility', 'ai-post-scheduler') . '</span></button>';
		echo '</div>';
	}

	/**
	 * Render numeric input.
	 *
	 * @param string               $id    Field ID.
	 * @param string               $name  Field name.
	 * @param mixed                $value Current value.
	 * @param array<string, mixed> $args  Arguments.
	 * @param string               $class Additional CSS class.
	 * @return void
	 */
	private static function render_number($id, $name, $value, $args, $class) {
		$class_str = !empty($class) ? $class : 'small-text';
		$min       = isset($args['min']) ? ' min="' . esc_attr($args['min']) . '"' : '';
		$max       = isset($args['max']) ? ' max="' . esc_attr($args['max']) . '"' : '';
		$step      = isset($args['step']) ? ' step="' . esc_attr($args['step']) . '"' : ' step="1"';
		$suffix    = isset($args['suffix']) ? ' ' . esc_html($args['suffix']) : '';

		echo '<input type="number" id="' . esc_attr($id) . '" name="' . esc_attr($name) . '" value="' . esc_attr((string) $value) . '" class="' . esc_attr($class_str) . '"' . $min . $max . $step . ' />' . $suffix; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/**
	 * Render checkbox input.
	 *
	 * @param string               $id    Field ID.
	 * @param string               $name  Field name.
	 * @param mixed                $value Current value.
	 * @param array<string, mixed> $args  Arguments.
	 * @return void
	 */
	private static function render_checkbox($id, $name, $value, $args) {
		$label    = isset($args['label']) ? $args['label'] : '';
		$checked  = !empty($value);
		$disabled = !empty($args['disabled']) ? ' disabled' : '';

		echo '<label for="' . esc_attr($id) . '">';
		echo '<input type="checkbox" id="' . esc_attr($id) . '" name="' . esc_attr($name) . '" value="1" ' . checked($checked, true, false) . $disabled . ' /> '; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		if (!empty($label)) {
			echo esc_html($label);
		}
		echo '</label>';
	}

	/**
	 * Render toggle switch.
	 *
	 * @param string               $id    Field ID.
	 * @param string               $name  Field name.
	 * @param mixed                $value Current value.
	 * @param array<string, mixed> $args  Arguments.
	 * @return void
	 */
	private static function render_toggle($id, $name, $value, $args) {
		$label   = isset($args['label']) ? $args['label'] : '';
		$checked = !empty($value);

		echo '<label class="aips-toggle-switch" for="' . esc_attr($id) . '">';
		echo '<input type="checkbox" id="' . esc_attr($id) . '" name="' . esc_attr($name) . '" value="1" ' . checked($checked, true, false) . ' />'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo '<span class="aips-toggle-slider" aria-hidden="true"></span>';
		if (!empty($label)) {
			echo '<span class="aips-toggle-label">' . esc_html($label) . '</span>';
		}
		echo '</label>';
	}

	/**
	 * Render select dropdown.
	 *
	 * @param string               $id    Field ID.
	 * @param string               $name  Field name.
	 * @param mixed                $value Current value.
	 * @param array<string, mixed> $args  Arguments.
	 * @param string               $class Additional CSS class.
	 * @return void
	 */
	private static function render_select($id, $name, $value, $args, $class) {
		$options  = isset($args['options']) && is_array($args['options']) ? $args['options'] : array();
		$disabled = !empty($args['disabled']) ? ' disabled' : '';

		echo '<select id="' . esc_attr($id) . '" name="' . esc_attr($name) . '" class="' . esc_attr($class) . '"' . $disabled . '>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		foreach ($options as $opt_val => $opt_label) {
			if (is_array($opt_label)) {
				echo '<optgroup label="' . esc_attr($opt_val) . '">';
				foreach ($opt_label as $sub_val => $sub_label) {
					echo '<option value="' . esc_attr((string) $sub_val) . '" ' . selected((string) $value, (string) $sub_val, false) . '>' . esc_html($sub_label) . '</option>';
				}
				echo '</optgroup>';
			} else {
				echo '<option value="' . esc_attr((string) $opt_val) . '" ' . selected((string) $value, (string) $opt_val, false) . '>' . esc_html($opt_label) . '</option>';
			}
		}
		echo '</select>';
	}

	/**
	 * Render multi-select dropdown.
	 *
	 * @param string               $id    Field ID.
	 * @param string               $name  Field name.
	 * @param mixed                $value Current value (array).
	 * @param array<string, mixed> $args  Arguments.
	 * @param string               $class Additional CSS class.
	 * @return void
	 */
	private static function render_multiselect($id, $name, $value, $args, $class) {
		$options      = isset($args['options']) && is_array($args['options']) ? $args['options'] : array();
		$values_array = is_array($value) ? $value : (array) $value;
		$size         = isset($args['size']) ? absint($args['size']) : 5;

		echo '<select id="' . esc_attr($id) . '" name="' . esc_attr($name) . '[]" multiple="multiple" size="' . esc_attr((string) $size) . '" class="' . esc_attr($class) . '">';
		foreach ($options as $opt_val => $opt_label) {
			$is_selected = in_array((string) $opt_val, array_map('strval', $values_array), true);
			echo '<option value="' . esc_attr((string) $opt_val) . '" ' . selected($is_selected, true, false) . '>' . esc_html($opt_label) . '</option>';
		}
		echo '</select>';
	}

	/**
	 * Render radio button group.
	 *
	 * @param string               $id    Field ID.
	 * @param string               $name  Field name.
	 * @param mixed                $value Current value.
	 * @param array<string, mixed> $args  Arguments.
	 * @return void
	 */
	private static function render_radio($id, $name, $value, $args) {
		$options = isset($args['options']) && is_array($args['options']) ? $args['options'] : array();

		echo '<fieldset><legend class="screen-reader-text"><span>' . esc_html(isset($args['label']) ? $args['label'] : $name) . '</span></legend>';
		foreach ($options as $opt_val => $opt_label) {
			$radio_id = $id . '_' . sanitize_key($opt_val);
			echo '<label for="' . esc_attr($radio_id) . '" class="aips-radio-label">';
			echo '<input type="radio" id="' . esc_attr($radio_id) . '" name="' . esc_attr($name) . '" value="' . esc_attr((string) $opt_val) . '" ' . checked((string) $value, (string) $opt_val, false) . ' /> '; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo esc_html($opt_label);
			echo '</label><br />';
		}
		echo '</fieldset>';
	}

	/**
	 * Render textarea.
	 *
	 * @param string               $id    Field ID.
	 * @param string               $name  Field name.
	 * @param mixed                $value Current value.
	 * @param array<string, mixed> $args  Arguments.
	 * @param string               $class Additional CSS class.
	 * @return void
	 */
	private static function render_textarea($id, $name, $value, $args, $class) {
		$rows      = isset($args['rows']) ? absint($args['rows']) : 5;
		$cols      = isset($args['cols']) ? absint($args['cols']) : 50;
		$class_str = 'large-text ' . esc_attr($class);

		echo '<textarea id="' . esc_attr($id) . '" name="' . esc_attr($name) . '" rows="' . esc_attr((string) $rows) . '" cols="' . esc_attr((string) $cols) . '" class="' . esc_attr(trim($class_str)) . '">' . esc_textarea((string) $value) . '</textarea>';
	}

	/**
	 * Render WordPress core category dropdown.
	 *
	 * @param string               $name  Field name.
	 * @param mixed                $value Current category ID.
	 * @param array<string, mixed> $args  Arguments.
	 * @return void
	 */
	private static function render_category($name, $value, $args) {
		$cat_args = array(
			'name'              => $name,
			'selected'          => absint($value),
			'show_option_none'  => isset($args['show_option_none']) ? $args['show_option_none'] : __('Select a category', 'ai-post-scheduler'),
			'option_none_value' => 0,
			'hide_empty'        => 0,
			'hierarchical'      => 1,
			'orderby'           => 'name',
			'class'             => isset($args['class']) ? $args['class'] : 'postform',
		);
		wp_dropdown_categories($cat_args);
	}

	/**
	 * Render code editor textarea with WordPress Core CodeMirror enqueuing.
	 *
	 * @param string               $id    Field ID.
	 * @param string               $name  Field name.
	 * @param mixed                $value Current value.
	 * @param array<string, mixed> $args  Arguments.
	 * @param string               $class Additional CSS class.
	 * @return void
	 */
	private static function render_code_editor($id, $name, $value, $args, $class) {
		$mime_type = isset($args['mime_type']) ? $args['mime_type'] : 'text/html';
		$rows      = isset($args['rows']) ? absint($args['rows']) : 8;

		if (function_exists('wp_enqueue_code_editor')) {
			$settings = wp_enqueue_code_editor(array('type' => $mime_type));
			if (false !== $settings) {
				wp_add_inline_script(
					'code-editor',
					sprintf('jQuery(function($){ if(wp.codeEditor){ wp.codeEditor.initialize("%s", %s); } });', esc_js($id), wp_json_encode($settings))
				);
			}
		}

		echo '<textarea id="' . esc_attr($id) . '" name="' . esc_attr($name) . '" rows="' . esc_attr((string) $rows) . '" class="large-text code ' . esc_attr($class) . '">' . esc_textarea((string) $value) . '</textarea>';
	}

	/**
	 * Render native WordPress Media Library image selector.
	 *
	 * @param string               $id    Field ID.
	 * @param string               $name  Field name.
	 * @param mixed                $value Current attachment ID or image URL.
	 * @param array<string, mixed> $args  Arguments.
	 * @return void
	 */
	private static function render_media($id, $name, $value, $args) {
		$image_url = '';
		if (is_numeric($value) && $value > 0) {
			$image_url = wp_get_attachment_image_url(absint($value), 'thumbnail');
		} elseif (is_string($value) && !empty($value)) {
			$image_url = $value;
		}

		echo '<div class="aips-media-field" id="' . esc_attr($id) . '_wrap">';
		echo '<input type="hidden" id="' . esc_attr($id) . '" name="' . esc_attr($name) . '" value="' . esc_attr((string) $value) . '" />';
		echo '<div class="aips-media-preview" style="margin-bottom:8px;' . (empty($image_url) ? 'display:none;' : '') . '">';
		echo '<img src="' . esc_url($image_url) . '" alt="" style="max-height:80px;border-radius:4px;border:1px solid #c3c4c7;" />';
		echo '</div>';
		echo '<button type="button" class="button button-secondary aips-choose-media" data-target="' . esc_attr($id) . '">' . esc_html__('Select Image', 'ai-post-scheduler') . '</button> ';
		echo '<button type="button" class="button button-link-delete aips-remove-media" data-target="' . esc_attr($id) . '" style="' . (empty($image_url) ? 'display:none;' : '') . '">' . esc_html__('Remove', 'ai-post-scheduler') . '</button>';
		echo '</div>';
	}

	/**
	 * Safely render field description.
	 *
	 * @param string $description Description HTML or plain text.
	 * @return void
	 */
	private static function render_description($description) {
		if (empty($description)) {
			return;
		}
		echo '<p class="description">' . wp_kses_post($description) . '</p>';
	}
}
