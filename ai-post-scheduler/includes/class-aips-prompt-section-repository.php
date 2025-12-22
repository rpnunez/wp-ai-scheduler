<?php
/**
 * Prompt Section Repository
 *
 * Database abstraction layer for prompt section operations.
 * Provides a clean interface for CRUD operations on the prompt sections table.
 *
 * @package AI_Post_Scheduler
 * @since 1.5.0
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Prompt_Section_Repository
 *
 * Repository pattern implementation for prompt section data access.
 * Encapsulates all database operations related to prompt sections.
 */
class AIPS_Prompt_Section_Repository {
	
	/**
	 * @var string The prompt sections table name (with prefix)
	 */
	private $table_name;
	
	/**
	 * @var wpdb WordPress database abstraction object
	 */
	private $wpdb;
	
	/**
	 * Initialize the repository.
	 */
	public function __construct() {
		global $wpdb;
		$this->wpdb = $wpdb;
		$this->table_name = $wpdb->prefix . 'aips_prompt_sections';
	}
	
	/**
	 * Get all prompt sections with optional filtering.
	 *
	 * @param bool $active_only Optional. Return only active sections. Default false.
	 * @return array Array of section objects.
	 */
	public function get_all($active_only = false) {
		$where = $active_only ? "WHERE is_active = 1" : "";
		return $this->wpdb->get_results("SELECT * FROM {$this->table_name} $where ORDER BY name ASC");
	}
	
	/**
	 * Get a single prompt section by ID.
	 *
	 * @param int $id Section ID.
	 * @return object|null Section object or null if not found.
	 */
	public function get_by_id($id) {
		return $this->wpdb->get_row($this->wpdb->prepare(
			"SELECT * FROM {$this->table_name} WHERE id = %d",
			$id
		));
	}
	
	/**
	 * Get a prompt section by its key.
	 *
	 * @param string $section_key Section key.
	 * @return object|null Section object or null if not found.
	 */
	public function get_by_key($section_key) {
		return $this->wpdb->get_row($this->wpdb->prepare(
			"SELECT * FROM {$this->table_name} WHERE section_key = %s",
			$section_key
		));
	}
	
	/**
	 * Get multiple sections by their keys.
	 *
	 * @param array $section_keys Array of section keys.
	 * @return array Array of section objects indexed by section_key.
	 */
	public function get_by_keys($section_keys) {
		if (empty($section_keys)) {
			return array();
		}
		
		$placeholders = implode(',', array_fill(0, count($section_keys), '%s'));
		$sections = $this->wpdb->get_results($this->wpdb->prepare(
			"SELECT * FROM {$this->table_name} WHERE section_key IN ($placeholders)",
			$section_keys
		));
		
		// Index by section_key for easy lookup
		$indexed = array();
		foreach ($sections as $section) {
			$indexed[$section->section_key] = $section;
		}
		
		return $indexed;
	}
	
	/**
	 * Create a new prompt section.
	 *
	 * @param array $data {
	 *     Section data.
	 *
	 *     @type string $name        Section name.
	 *     @type string $description Section description.
	 *     @type string $section_key Unique section key.
	 *     @type string $content     Section prompt content.
	 *     @type int    $is_active   Active status flag.
	 * }
	 * @return int|false The inserted ID on success, false on failure.
	 */
	public function create($data) {
		$insert_data = array(
			'name' => sanitize_text_field($data['name']),
			'description' => isset($data['description']) ? sanitize_textarea_field($data['description']) : '',
			'section_key' => sanitize_key($data['section_key']),
			'content' => wp_kses_post($data['content']),
			'is_active' => !empty($data['is_active']) ? 1 : 0,
		);
		
		$format = array('%s', '%s', '%s', '%s', '%d');
		
		$result = $this->wpdb->insert($this->table_name, $insert_data, $format);
		
		return $result ? $this->wpdb->insert_id : false;
	}
	
	/**
	 * Update an existing prompt section.
	 *
	 * @param int   $id   Section ID.
	 * @param array $data Data to update (same structure as create).
	 * @return bool True on success, false on failure.
	 */
	public function update($id, $data) {
		$update_data = array();
		$format = array();
		
		if (isset($data['name'])) {
			$update_data['name'] = sanitize_text_field($data['name']);
			$format[] = '%s';
		}
		
		if (isset($data['description'])) {
			$update_data['description'] = sanitize_textarea_field($data['description']);
			$format[] = '%s';
		}
		
		if (isset($data['section_key'])) {
			$update_data['section_key'] = sanitize_key($data['section_key']);
			$format[] = '%s';
		}
		
		if (isset($data['content'])) {
			$update_data['content'] = wp_kses_post($data['content']);
			$format[] = '%s';
		}
		
		if (isset($data['is_active'])) {
			$update_data['is_active'] = $data['is_active'] ? 1 : 0;
			$format[] = '%d';
		}
		
		if (empty($update_data)) {
			return false;
		}
		
		return $this->wpdb->update(
			$this->table_name,
			$update_data,
			array('id' => $id),
			$format,
			array('%d')
		) !== false;
	}
	
	/**
	 * Delete a prompt section by ID.
	 *
	 * @param int $id Section ID.
	 * @return bool True on success, false on failure.
	 */
	public function delete($id) {
		return $this->wpdb->delete($this->table_name, array('id' => $id), array('%d')) !== false;
	}
	
	/**
	 * Toggle section active status.
	 *
	 * @param int  $id        Section ID.
	 * @param bool $is_active Active status.
	 * @return bool True on success, false on failure.
	 */
	public function set_active($id, $is_active) {
		return $this->update($id, array('is_active' => $is_active));
	}
	
	/**
	 * Count sections by status.
	 *
	 * @return array {
	 *     @type int $total  Total number of sections.
	 *     @type int $active Number of active sections.
	 * }
	 */
	public function count_by_status() {
		$results = $this->wpdb->get_row("
			SELECT
				COUNT(*) as total,
				SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active
			FROM {$this->table_name}
		");
		
		return array(
			'total' => (int) $results->total,
			'active' => (int) $results->active,
		);
	}
	
	/**
	 * Check if a section key already exists.
	 *
	 * @param string $section_key Section key.
	 * @param int    $exclude_id  Optional. Exclude this ID from check. Default 0.
	 * @return bool True if key exists, false otherwise.
	 */
	public function key_exists($section_key, $exclude_id = 0) {
		if ($exclude_id > 0) {
			$result = $this->wpdb->get_var($this->wpdb->prepare(
				"SELECT COUNT(*) FROM {$this->table_name} WHERE section_key = %s AND id != %d",
				$section_key,
				$exclude_id
			));
		} else {
			$result = $this->wpdb->get_var($this->wpdb->prepare(
				"SELECT COUNT(*) FROM {$this->table_name} WHERE section_key = %s",
				$section_key
			));
		}
		
		return $result > 0;
	}
}
