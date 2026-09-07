<?php
/**
 * Advanced Custom Fields (ACF) Integration Adapter
 *
 * Flagship AIPS_Integration_Interface implementation: introspects ACF field
 * groups as generation schemas and writes generated values back onto posts
 * via ACF's own field API. Also compatible with Secure Custom Fields (SCF),
 * ACF's fork, since it mirrors the same acf_*()/update_field() functions.
 *
 * @package AI_Post_Scheduler
 * @since 2.10.0
 */

if (!defined('ABSPATH')) {
	exit;
}

class AIPS_Integration_ACF implements AIPS_Integration_Interface {

	public function get_id() {
		return 'acf';
	}

	public function get_label() {
		return __('Advanced Custom Fields', 'ai-post-scheduler');
	}

	public function is_available() {
		return function_exists('acf_get_field_groups') && function_exists('acf_get_fields') && function_exists('update_field');
	}

	public function get_field_groups($post_type = null) {
		if (!$this->is_available()) {
			return array();
		}

		$groups = acf_get_field_groups();

		if (!is_array($groups)) {
			return array();
		}

		$result = array();

		foreach ($groups as $group) {
			if (!isset($group['key'])) {
				continue;
			}

			if ($post_type && !$this->group_matches_post_type($group, $post_type)) {
				continue;
			}

			$result[] = array(
				'id'    => $group['key'],
				'label' => isset($group['title']) ? $group['title'] : $group['key'],
			);
		}

		return $result;
	}

	public function get_fields($group_id, $args = array()) {
		if (!$this->is_available()) {
			return array();
		}

		$fields = acf_get_fields($group_id);

		if (!is_array($fields)) {
			return array();
		}

		$type_map = $this->get_supported_field_types();
		$result = array();

		foreach ($fields as $field) {
			$native_type = isset($field['type']) ? $field['type'] : '';

			$result[] = array(
				'key'          => isset($field['key']) ? $field['key'] : $field['name'],
				'label'        => isset($field['label']) ? $field['label'] : (isset($field['name']) ? $field['name'] : ''),
				'native_type'  => $native_type,
				// Empty string means "unsupported" — the manager skips fields whose shape is empty.
				'shape'        => isset($type_map[$native_type]) ? $type_map[$native_type] : '',
				'instructions' => isset($field['instructions']) ? $field['instructions'] : '',
			);
		}

		return $result;
	}

	public function get_supported_field_types() {
		return array(
			'text'             => self::SHAPE_SHORT_TEXT,
			'email'            => self::SHAPE_SHORT_TEXT,
			'url'              => self::SHAPE_SHORT_TEXT,
			'number'           => self::SHAPE_SHORT_TEXT,
			'textarea'         => self::SHAPE_LONG_TEXT,
			'wysiwyg'          => self::SHAPE_HTML,
			'select'           => self::SHAPE_CHOICE,
			'radio'            => self::SHAPE_CHOICE,
			'true_false'       => self::SHAPE_BOOLEAN,
			// Listed for schema discovery; the manager does not yet generate
			// for these shapes (deferred to a later phase — see project plan).
			'repeater'         => self::SHAPE_STRUCTURED_LIST,
			'flexible_content' => self::SHAPE_STRUCTURED_LIST,
		);
	}

	public function write_field_value($post_id, $field_key, $value) {
		if (!$this->is_available()) {
			return new WP_Error('acf_unavailable', __('ACF is not active on this site.', 'ai-post-scheduler'));
		}

		$post_id = absint($post_id);

		if (!$post_id) {
			return new WP_Error('invalid_post', __('Invalid post ID.', 'ai-post-scheduler'));
		}

		// update_field() writes raw postmeta under $field_key when it doesn't
		// resolve to a real field, silently "succeeding" for a mapping whose
		// field was since deleted/renamed in ACF. Verify it still exists first
		// so a stale mapping surfaces as an error instead of orphaned meta.
		if (function_exists('acf_get_field') && !acf_get_field($field_key)) {
			return new WP_Error(
				'acf_field_not_found',
				sprintf(
					/* translators: %s: ACF field key. */
					__('ACF field %s no longer exists.', 'ai-post-scheduler'),
					$field_key
				)
			);
		}

		if (!update_field($field_key, $value, $post_id)) {
			return new WP_Error(
				'acf_write_failed',
				sprintf(
					/* translators: %s: ACF field key. */
					__('Failed to write ACF field %s.', 'ai-post-scheduler'),
					$field_key
				)
			);
		}

		return true;
	}

	public function supports_custom_field_keys() {
		// ACF's fields are always fully discoverable via acf_get_fields(); an
		// admin never needs to hand-type an ACF field key.
		return false;
	}

	public function validate_field_key($field_key) {
		// Already vetted by discovery (get_fields() only ever returns real,
		// currently-registered ACF field keys).
		return true;
	}

	/**
	 * Whether an ACF field group's location rules allow it on a post type.
	 *
	 * ACF location rules are an OR-of-ANDs structure. This only evaluates
	 * 'post_type' rules — groups that don't constrain by post_type, or that
	 * constrain only via other params (page template, user role, etc.), are
	 * treated as a match since AIPS has no post context to evaluate those
	 * other rule types against at schema-discovery time.
	 *
	 * @param array  $group     ACF field group array (from acf_get_field_groups()).
	 * @param string $post_type Post type slug to check.
	 * @return bool
	 */
	private function group_matches_post_type($group, $post_type) {
		$location = isset($group['location']) && is_array($group['location']) ? $group['location'] : array();

		if (empty($location)) {
			return true;
		}

		foreach ($location as $and_rules) {
			if (!is_array($and_rules)) {
				continue;
			}

			$has_post_type_rule = false;
			$satisfied = true;

			foreach ($and_rules as $rule) {
				if (!isset($rule['param']) || $rule['param'] !== 'post_type') {
					continue;
				}

				$has_post_type_rule = true;
				$values_match = isset($rule['value']) && $rule['value'] === $post_type;
				$rule_passes = (isset($rule['operator']) && $rule['operator'] === '!=') ? !$values_match : $values_match;

				if (!$rule_passes) {
					$satisfied = false;
				}
			}

			if (!$has_post_type_rule || $satisfied) {
				return true;
			}
		}

		return false;
	}
}
