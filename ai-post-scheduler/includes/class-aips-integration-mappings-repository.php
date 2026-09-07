<?php
/**
 * Integration Field Mappings Repository
 *
 * Database abstraction layer for aips_integration_field_mappings: which
 * fields of a third-party plugin's schema (e.g. an ACF field group) a
 * Template should generate content for, and any per-field custom prompt.
 *
 * @package AI_Post_Scheduler
 * @since 2.10.0
 */

if (!defined('ABSPATH')) {
	exit;
}

class AIPS_Integration_Mappings_Repository {

	/**
	 * @var wpdb
	 */
	private $wpdb;

	/**
	 * @var string
	 */
	private $table_name;

	public function __construct() {
		global $wpdb;
		$this->wpdb = $wpdb;
		$this->table_name = $wpdb->prefix . 'aips_integration_field_mappings';
	}

	/**
	 * Get all mapping rows for a template, optionally limited to active rows.
	 *
	 * @param int  $template_id Template ID.
	 * @param bool $active_only Optional. Return only active rows. Default true.
	 * @return array<int, object> Mapping rows.
	 */
	public function get_by_template($template_id, $active_only = true) {
		$template_id = absint($template_id);
		$where = $active_only ? 'AND is_active = 1' : '';

		return $this->wpdb->get_results($this->wpdb->prepare(
			"SELECT * FROM {$this->table_name} WHERE template_id = %d $where ORDER BY id ASC",
			$template_id
		));
	}

	/**
	 * Get a single mapping row by ID.
	 *
	 * @param int $id Mapping ID.
	 * @return object|null
	 */
	public function get_by_id($id) {
		return $this->wpdb->get_row($this->wpdb->prepare(
			"SELECT * FROM {$this->table_name} WHERE id = %d",
			absint($id)
		));
	}

	/**
	 * Create or update a mapping row.
	 *
	 * Upserts on the (template_id, integration_id, field_key) unique key: if
	 * a row already exists for that combination it is updated in place,
	 * otherwise a new row is inserted.
	 *
	 * @param array $data {
	 *     @type int    $template_id    Template ID.
	 *     @type string $integration_id Integration identifier (e.g. 'acf').
	 *     @type string $source_key     Schema group identifier (e.g. ACF field group key).
	 *     @type string $field_key      Field identifier.
	 *     @type string $field_label    Field label (cached for display).
	 *     @type string $field_type     Native field type (cached for display).
	 *     @type string $custom_prompt  Optional per-field generation instruction.
	 *     @type bool   $is_active      Whether this field should be generated.
	 * }
	 * @return int|false Mapping ID on success, false on failure.
	 */
	public function save_mapping($data) {
		$template_id = !empty($data['template_id']) ? absint($data['template_id']) : null;
		$integration_id = sanitize_key($data['integration_id']);
		$field_key = sanitize_text_field($data['field_key']);

		if ($template_id) {
			$existing = $this->wpdb->get_var($this->wpdb->prepare(
				"SELECT id FROM {$this->table_name} WHERE integration_id = %s AND field_key = %s AND template_id = %d",
				$integration_id,
				$field_key,
				$template_id
			));
		} else {
			$existing = $this->wpdb->get_var($this->wpdb->prepare(
				"SELECT id FROM {$this->table_name} WHERE integration_id = %s AND field_key = %s AND template_id IS NULL",
				$integration_id,
				$field_key
			));
		}

		$now = AIPS_DateTime::now()->timestamp();

		$row = array(
			'template_id'    => $template_id,
			'integration_id' => $integration_id,
			'source_key'     => sanitize_text_field($data['source_key']),
			'field_key'      => $field_key,
			'field_label'    => isset($data['field_label']) ? sanitize_text_field($data['field_label']) : '',
			'field_type'     => isset($data['field_type']) ? sanitize_key($data['field_type']) : '',
			'custom_prompt'  => isset($data['custom_prompt']) ? sanitize_textarea_field($data['custom_prompt']) : '',
			'is_active'      => !empty($data['is_active']) ? 1 : 0,
			'updated_at'     => $now,
		);
		$format = array('%d', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d');

		if ($existing) {
			$this->wpdb->update($this->table_name, $row, array('id' => (int) $existing), $format, array('%d'));
			return (int) $existing;
		}

		$row['created_at'] = $now;
		$format[] = '%d';

		$result = $this->wpdb->insert($this->table_name, $row, $format);

		return $result ? $this->wpdb->insert_id : false;
	}

	/**
	 * Delete a single mapping row.
	 *
	 * @param int $id Mapping ID.
	 * @return bool
	 */
	public function delete_mapping($id) {
		return $this->wpdb->delete($this->table_name, array('id' => absint($id)), array('%d')) !== false;
	}

	/**
	 * Delete all mapping rows for a template (call when a template is deleted).
	 *
	 * @param int $template_id Template ID.
	 * @return bool
	 */
	public function delete_by_template($template_id) {
		return $this->wpdb->delete($this->table_name, array('template_id' => absint($template_id)), array('%d')) !== false;
	}

	/**
	 * Retire mappings left over from a previously-selected schema group.
	 *
	 * A template's saved mappings for one integration should always reflect
	 * exactly one selected group (e.g. one ACF field group) at a time. Call
	 * this before saving a new batch of mappings so switching groups doesn't
	 * leave the old group's rows active alongside the new one — otherwise
	 * both groups' fields would be generated on every post.
	 *
	 * @param int    $template_id    Template ID.
	 * @param string $integration_id Integration identifier (e.g. 'acf').
	 * @param string $source_key     The group identifier being kept.
	 * @return bool
	 */
	public function delete_stale_group_mappings($template_id, $integration_id, $source_key) {
		$template_id = absint($template_id);

		if (!$template_id) {
			return false;
		}

		return $this->wpdb->query($this->wpdb->prepare(
			"DELETE FROM {$this->table_name} WHERE template_id = %d AND integration_id = %s AND source_key != %s",
			$template_id,
			sanitize_key($integration_id),
			sanitize_text_field($source_key)
		)) !== false;
	}

	/**
	 * Synchronize all field mappings for a specific (template_id, integration_id, source_key) group.
	 *
	 * Deletes all existing mappings for this template + integration, then saves
	 * the provided mapping rows in a single clean pass.
	 *
	 * @param int               $template_id    Template ID.
	 * @param string            $integration_id Integration identifier.
	 * @param string            $source_key     Schema group identifier (e.g. ACF group key or post type).
	 * @param array<int, array> $mappings       Array of mapping data arrays.
	 * @return bool True on success, false on failure.
	 */
	public function sync_group_mappings($template_id, $integration_id, $source_key, array $mappings) {
		$template_id = absint($template_id);
		$integration_id = sanitize_key($integration_id);
		$source_key = sanitize_text_field($source_key);

		if (!$template_id || empty($integration_id)) {
			return false;
		}

		// Delete all existing mappings for this template + integration.
		$this->wpdb->delete(
			$this->table_name,
			array(
				'template_id'    => $template_id,
				'integration_id' => $integration_id,
			),
			array('%d', '%s')
		);

		foreach ($mappings as $mapping) {
			if (empty($mapping['field_key'])) {
				continue;
			}

			$mapping['template_id']    = $template_id;
			$mapping['integration_id'] = $integration_id;
			$mapping['source_key']     = $source_key;
			$this->save_mapping($mapping);
		}

		return true;
	}

	/**
	 * Duplicate all field mappings from a source template to a destination template.
	 *
	 * @param int $source_template_id      Source template ID.
	 * @param int $destination_template_id Destination template ID.
	 * @return bool True on success, false on failure.
	 */
	public function clone_template_mappings($source_template_id, $destination_template_id) {
		$source_template_id = absint($source_template_id);
		$destination_template_id = absint($destination_template_id);

		if (!$source_template_id || !$destination_template_id) {
			return false;
		}

		$existing = $this->get_by_template($source_template_id, false);

		if (empty($existing)) {
			return true;
		}

		foreach ($existing as $mapping) {
			$this->save_mapping(array(
				'template_id'    => $destination_template_id,
				'integration_id' => $mapping->integration_id,
				'source_key'     => $mapping->source_key,
				'field_key'      => $mapping->field_key,
				'field_label'    => $mapping->field_label,
				'field_type'     => $mapping->field_type,
				'custom_prompt'  => $mapping->custom_prompt,
				'is_active'      => (int) $mapping->is_active,
			));
		}

		return true;
	}
}

