<?php
/**
 * Native WordPress Custom Field Integration Adapter
 *
 * Lets AIPS generate content into plain WordPress post meta ("custom
 * fields") on any post type — native (post, page) or custom — without
 * requiring ACF or any other plugin. Unlike ACF, native meta mostly has no
 * discoverable schema: only keys registered via register_post_meta() carry
 * a type/description, so this adapter surfaces those where available and
 * also lets the admin hand-type any other meta key (see
 * AIPS_Integration_Interface::supports_custom_field_keys()).
 *
 * @package AI_Post_Scheduler
 * @since 2.11.0
 */

if (!defined('ABSPATH')) {
	exit;
}

class AIPS_Integration_Native_Meta implements AIPS_Integration_Interface {

	public function get_id() {
		return 'native_meta';
	}

	public function get_label() {
		return __('WordPress Custom Fields', 'ai-post-scheduler');
	}

	public function is_available() {
		// No external dependency — native post meta is always available.
		return true;
	}

	public function get_field_groups($post_type = null) {
		// There's no real "groups" concept for native meta — one synthetic
		// group per post type, so a saved mapping's source_key is just the
		// post_type it targets.
		$post_type = $post_type ?: 'post';

		return array(
			array(
				'id'    => $post_type,
				'label' => __('Custom Fields', 'ai-post-scheduler'),
			),
		);
	}

	public function get_fields($group_id, $args = array()) {
		$post_type = (string) $group_id;
		$include_protected = !empty($args['include_protected']);
		$type_map = $this->get_supported_field_types();
		$registered = get_registered_meta_keys('post', $post_type);
		$fields = array();

		foreach ($registered as $meta_key => $meta_args) {
			if (!$this->is_valid_field_key($meta_key)) {
				continue;
			}

			// Protected/internal ('_'-prefixed) keys are hidden by default —
			// the admin opts in via the Template editor's "Show Advanced
			// Custom Meta Fields" toggle. Once shown/selected, they can be
			// saved and written like any other field; see write_field_value().
			if (!$include_protected && is_protected_meta($meta_key, 'post')) {
				continue;
			}

			$native_type = isset($meta_args['type']) ? $meta_args['type'] : 'string';

			$fields[] = array(
				'key'          => $meta_key,
				'label'        => !empty($meta_args['description']) ? $meta_args['description'] : $this->humanize_key($meta_key),
				'native_type'  => $native_type,
				'shape'        => isset($type_map[$native_type]) ? $type_map[$native_type] : '',
				'instructions' => isset($meta_args['description']) ? $meta_args['description'] : '',
			);
		}

		return $fields;
	}

	public function get_supported_field_types() {
		return array(
			// register_post_meta() 'type' values.
			'string'  => self::SHAPE_LONG_TEXT,
			'integer' => self::SHAPE_SHORT_TEXT,
			'number'  => self::SHAPE_SHORT_TEXT,
			// Listed for schema discovery; deferred, same posture as ACF's
			// repeater/boolean shapes.
			'boolean' => self::SHAPE_BOOLEAN,
			'array'   => self::SHAPE_STRUCTURED_LIST,
			'object'  => self::SHAPE_STRUCTURED_LIST,
			// Synthetic types for an admin-hand-added field, where there's no
			// registration to infer a type from — the admin picks the shape
			// directly in the UI.
			'freeform_short_text' => self::SHAPE_SHORT_TEXT,
			'freeform_long_text'  => self::SHAPE_LONG_TEXT,
			'freeform_html'       => self::SHAPE_HTML,
		);
	}

	public function write_field_value($post_id, $field_key, $value) {
		$post_id = absint($post_id);

		if (!$post_id) {
			return new WP_Error('invalid_post', __('Invalid post ID.', 'ai-post-scheduler'));
		}

		$valid = $this->validate_field_key($field_key);

		if (is_wp_error($valid)) {
			return $valid;
		}

		// update_post_meta() also returns false when the new value equals the
		// existing one — that's not a failure, so it isn't treated as an error.
		update_post_meta($post_id, $field_key, $value);

		return true;
	}

	public function supports_custom_field_keys() {
		return true;
	}

	public function validate_field_key($field_key) {
		// Protected/internal ('_'-prefixed) keys are intentionally allowed
		// here: they're just hidden from get_fields() by default, opted into
		// via the "Show Advanced Custom Meta Fields" toggle in the Template
		// editor. Once an admin has explicitly selected or typed one, it can
		// be saved and written like any other field.
		if (!$this->is_valid_field_key($field_key)) {
			return new WP_Error(
				'invalid_meta_key',
				__('Meta key may only contain letters, numbers, and underscores.', 'ai-post-scheduler')
			);
		}

		return true;
	}

	/**
	 * Whether a string is a well-formed meta key candidate.
	 *
	 * @param string $field_key Field/meta key.
	 * @return bool
	 */
	private function is_valid_field_key($field_key) {
		return is_string($field_key) && $field_key !== '' && preg_match('/^[a-zA-Z0-9_]+$/', $field_key) === 1;
	}

	/**
	 * Turn a raw meta key into a readable label when no description was
	 * registered (e.g. 'contact_phone_number' -> 'Contact Phone Number').
	 *
	 * @param string $meta_key Raw meta key.
	 * @return string
	 */
	private function humanize_key($meta_key) {
		return ucwords(str_replace(array('_', '-'), ' ', $meta_key));
	}
}
