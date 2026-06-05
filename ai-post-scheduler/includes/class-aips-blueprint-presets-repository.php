<?php
/**
 * Blueprint Presets Repository
 *
 * Database abstraction layer for blueprint preset operations.
 *
 * @package AI_Post_Scheduler
 * @since 2.9.0
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Blueprint_Presets_Repository
 */
class AIPS_Blueprint_Presets_Repository {

	/**
	 * @var self|null
	 */
	private static $instance = null;

	/**
	 * @var string
	 */
	private $table_name;

	/**
	 * @var wpdb
	 */
	private $wpdb;

	/**
	 * @return self
	 */
	public static function instance(): self {
		if (self::$instance === null) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Initialize repository.
	 */
	public function __construct() {
		global $wpdb;
		$this->wpdb = $wpdb;
		$this->table_name = $wpdb->prefix . 'aips_blueprint_presets';
	}

	/**
	 * Get all presets.
	 *
	 * @param bool $active_only Whether to return only active presets.
	 * @return array
	 */
	public function get_all($active_only = false) {
		if ($active_only) {
			$sql = $this->wpdb->prepare(
				"SELECT * FROM {$this->table_name} WHERE is_active = %d ORDER BY is_default DESC, name ASC",
				1
			);
		} else {
			$sql = "SELECT * FROM {$this->table_name} ORDER BY is_default DESC, name ASC";
		}

		$result = $this->wpdb->get_results($sql);
		if (!is_array($result)) {
			$result = array();
		}

		return $result;
	}

	/**
	 * Get one preset by ID.
	 *
	 * @param int $id Preset ID.
	 * @return object|null
	 */
	public function get_by_id($id) {
		return $this->wpdb->get_row(
			$this->wpdb->prepare(
				"SELECT * FROM {$this->table_name} WHERE id = %d",
				$id
			)
		);
	}

	/**
	 * Get the default preset (if any).
	 *
	 * @return object|null
	 */
	public function get_default() {
		return $this->wpdb->get_row(
			$this->wpdb->prepare(
				"SELECT * FROM {$this->table_name} WHERE is_default = %d AND is_active = %d LIMIT 1",
				1,
				1
			)
		);
	}

	/**
	 * Create a preset.
	 *
	 * @param array $data Preset data.
	 * @return int|false Insert ID or false on failure.
	 */
	public function create($data) {
		$now = AIPS_DateTime::now()->timestamp();

		// If marking as default, clear existing default first.
		if (!empty($data['is_default'])) {
			$this->clear_default();
		}

		$insert_data = array(
			'name'              => isset($data['name']) ? sanitize_text_field($data['name']) : '',
			'description'       => isset($data['description']) ? sanitize_textarea_field($data['description']) : '',
			'structure_id'      => !empty($data['structure_id']) ? absint($data['structure_id']) : null,
			'voice_id'          => !empty($data['voice_id']) ? absint($data['voice_id']) : null,
			'slice_ids'         => isset($data['slice_ids']) ? $this->sanitize_json_array($data['slice_ids']) : null,
			'section_overrides' => isset($data['section_overrides']) ? $this->sanitize_json_array($data['section_overrides']) : null,
			'is_active'         => !empty($data['is_active']) ? 1 : 0,
			'is_default'        => !empty($data['is_default']) ? 1 : 0,
			'created_at'        => $now,
			'updated_at'        => $now,
		);

		$formats = array('%s', '%s', '%d', '%d', '%s', '%s', '%d', '%d', '%d', '%d');

		// Handle nullable FK columns — use NULL instead of 0.
		if ($insert_data['structure_id'] === null) {
			$formats[2] = null;
		}
		if ($insert_data['voice_id'] === null) {
			$formats[3] = null;
		}

		$result = $this->wpdb->insert($this->table_name, $insert_data, $formats);

		return $result ? $this->wpdb->insert_id : false;
	}

	/**
	 * Update a preset.
	 *
	 * @param int   $id   Preset ID.
	 * @param array $data Preset data.
	 * @return int|false Number of rows updated or false.
	 */
	public function update($id, $data) {
		$update_data = array();
		$formats = array();

		if (array_key_exists('name', $data)) {
			$update_data['name'] = sanitize_text_field($data['name']);
			$formats[] = '%s';
		}

		if (array_key_exists('description', $data)) {
			$update_data['description'] = sanitize_textarea_field($data['description']);
			$formats[] = '%s';
		}

		if (array_key_exists('structure_id', $data)) {
			$update_data['structure_id'] = !empty($data['structure_id']) ? absint($data['structure_id']) : null;
			$formats[] = '%d';
		}

		if (array_key_exists('voice_id', $data)) {
			$update_data['voice_id'] = !empty($data['voice_id']) ? absint($data['voice_id']) : null;
			$formats[] = '%d';
		}

		if (array_key_exists('slice_ids', $data)) {
			$update_data['slice_ids'] = $this->sanitize_json_array($data['slice_ids']);
			$formats[] = '%s';
		}

		if (array_key_exists('section_overrides', $data)) {
			$update_data['section_overrides'] = $this->sanitize_json_array($data['section_overrides']);
			$formats[] = '%s';
		}

		if (array_key_exists('is_active', $data)) {
			$update_data['is_active'] = !empty($data['is_active']) ? 1 : 0;
			$formats[] = '%d';
		}

		if (array_key_exists('is_default', $data)) {
			$is_default = !empty($data['is_default']) ? 1 : 0;
			if ($is_default) {
				$this->clear_default($id);
			}
			$update_data['is_default'] = $is_default;
			$formats[] = '%d';
		}

		$update_data['updated_at'] = AIPS_DateTime::now()->timestamp();
		$formats[] = '%d';

		return $this->wpdb->update(
			$this->table_name,
			$update_data,
			array('id' => absint($id)),
			$formats,
			array('%d')
		);
	}

	/**
	 * Delete a preset.
	 *
	 * @param int $id Preset ID.
	 * @return int|false
	 */
	public function delete($id) {
		return $this->wpdb->delete(
			$this->table_name,
			array('id' => absint($id)),
			array('%d')
		);
	}

	/**
	 * Clear the is_default flag from all presets.
	 *
	 * @param int $exclude_id Optional preset ID to exclude from clearing.
	 */
	private function clear_default($exclude_id = 0) {
		if ($exclude_id > 0) {
			$this->wpdb->query(
				$this->wpdb->prepare(
					"UPDATE {$this->table_name} SET is_default = 0 WHERE is_default = 1 AND id != %d",
					$exclude_id
				)
			);
		} else {
			$this->wpdb->query("UPDATE {$this->table_name} SET is_default = 0 WHERE is_default = 1");
		}
	}

	/**
	 * Sanitize a JSON array value. Accepts either a JSON string or a PHP array.
	 *
	 * @param mixed $value Raw value.
	 * @return string|null JSON string or null.
	 */
	private function sanitize_json_array($value) {
		if (is_string($value)) {
			$decoded = json_decode($value, true);
			if (is_array($decoded)) {
				return wp_json_encode($decoded);
			}
			return null;
		}

		if (is_array($value)) {
			return wp_json_encode($value);
		}

		return null;
	}
}
