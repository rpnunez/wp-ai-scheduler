<?php
/**
 * Integration Adapter Contract
 *
 * Defines the contract any "AIPS-compatible plugin" adapter must implement so
 * AIPS_Integration_Manager can introspect a third-party plugin's data schema
 * (e.g. an ACF field group) and write generated values back into it.
 *
 * @package AI_Post_Scheduler
 * @since 2.10.0
 */

if (!defined('ABSPATH')) {
	exit;
}

interface AIPS_Integration_Interface {

	/** Field carries a short single-line value (e.g. a text input). */
	const SHAPE_SHORT_TEXT = 'short_text';

	/** Field carries a longer plain-text value (e.g. a textarea). */
	const SHAPE_LONG_TEXT = 'long_text';

	/** Field carries HTML markup (e.g. a WYSIWYG editor). */
	const SHAPE_HTML = 'html';

	/** Field carries one value chosen from a fixed set of choices. */
	const SHAPE_CHOICE = 'choice';

	/** Field carries a true/false value. */
	const SHAPE_BOOLEAN = 'boolean';

	/** Field carries a list of structured sub-records (e.g. a repeater). */
	const SHAPE_STRUCTURED_LIST = 'structured_list';

	/**
	 * Unique, stable identifier for this integration (e.g. 'acf').
	 *
	 * @return string
	 */
	public function get_id();

	/**
	 * Human-readable label shown in the admin UI (e.g. 'Advanced Custom Fields').
	 *
	 * @return string
	 */
	public function get_label();

	/**
	 * Whether the target plugin is active on this WordPress instance.
	 *
	 * @return bool
	 */
	public function is_available();

	/**
	 * List the schema "groups" this integration exposes for a given post type
	 * (e.g. ACF field groups whose location rules match the post type).
	 *
	 * @param string|null $post_type Post type slug, or null for all groups.
	 * @return array<int, array{id: string, label: string}>
	 */
	public function get_field_groups($post_type = null);

	/**
	 * List the fields belonging to a schema group.
	 *
	 * @param string $group_id Group identifier as returned by get_field_groups().
	 * @param array  $args     Optional, adapter-specific discovery options. Adapters
	 *                         that don't recognise a given option ignore it. Currently
	 *                         recognised: 'include_protected' (bool) — used by
	 *                         AIPS_Integration_Native_Meta to include normally-hidden
	 *                         protected/internal ('_'-prefixed) meta keys.
	 * @return array<int, array{
	 *     key: string,
	 *     label: string,
	 *     native_type: string,
	 *     shape: string,
	 *     instructions: string,
	 * }>
	 */
	public function get_fields($group_id, $args = array());

	/**
	 * Map of native field types this adapter understands to the generation
	 * "shape" AIPS_Integration_Manager uses to decide how to generate for them.
	 *
	 * @return array<string, string> native_type => shape (one of the SHAPE_* constants).
	 */
	public function get_supported_field_types();

	/**
	 * Persist a generated value onto a post's field.
	 *
	 * @param int    $post_id   Target post ID.
	 * @param string $field_key Field identifier as returned by get_fields().
	 * @param mixed  $value     Generated value to write.
	 * @return bool|WP_Error True on success, WP_Error on failure.
	 */
	public function write_field_value($post_id, $field_key, $value);

	/**
	 * Whether the admin can hand-add a field key that wasn't returned by
	 * get_fields() (e.g. an unregistered native WordPress meta key), rather
	 * than only picking from the discovered list. Adapters whose fields are
	 * always fully discoverable (e.g. ACF) return false.
	 *
	 * @return bool
	 */
	public function supports_custom_field_keys();

	/**
	 * Validate a field key before it's saved as a mapping or written to.
	 * Adapters whose keys are already vetted by discovery (e.g. ACF) can
	 * always return true; adapters that accept hand-typed keys (e.g. native
	 * WordPress meta) should reject unsafe or malformed ones here.
	 *
	 * @param string $field_key Field identifier.
	 * @return true|WP_Error
	 */
	public function validate_field_key($field_key);
}
