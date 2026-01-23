<?php
/**
 * Article Structure Repository
 *
 * Database abstraction layer for article structure operations.
 * Provides a clean interface for CRUD operations on the article structures table.
 *
 * @package AI_Post_Scheduler
 * @since 1.5.0
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Article_Structure_Repository
 *
 * Repository pattern implementation for article structure data access.
 * Encapsulates all database operations related to article structures.
 */
class AIPS_Article_Structure_Repository {
	
	/**
	 * @var string The article structures table name (with prefix)
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
		$this->table_name = $wpdb->prefix . 'aips_article_structures';
	}
	
	/**
	 * Get all article structures with optional filtering.
	 *
	 * @param bool $active_only Optional. Return only active structures. Default false.
	 * @return array Array of structure objects.
	 */
	public function get_all($active_only = false) {
		$where = $active_only ? "WHERE is_active = 1" : "";
		return $this->wpdb->get_results("SELECT * FROM {$this->table_name} $where ORDER BY name ASC");
	}
	
	/**
	 * Get a single article structure by ID.
	 *
	 * @param int $id Structure ID.
	 * @return object|null Structure object or null if not found.
	 */
	public function get_by_id($id) {
		return $this->wpdb->get_row($this->wpdb->prepare(
			"SELECT * FROM {$this->table_name} WHERE id = %d",
			$id
		));
	}
	
	/**
	 * Get the default article structure.
	 *
	 * @return object|null Default structure object or null if not found.
	 */
	public function get_default() {
		return $this->wpdb->get_row(
			"SELECT * FROM {$this->table_name} WHERE is_default = 1 AND is_active = 1 ORDER BY id ASC LIMIT 1"
		);
	}
	
	/**
	 * Get article structure by name.
	 *
	 * @param string $name Structure name.
	 * @return object|null Structure object or null if not found.
	 */
	public function get_by_name($name) {
		return $this->wpdb->get_row($this->wpdb->prepare(
			"SELECT * FROM {$this->table_name} WHERE name = %s",
			$name
		));
	}
	
	/**
	 * Create a new article structure.
	 *
	 * @param array $data {
	 *     Structure data.
	 *
	 *     @type string $name            Structure name.
	 *     @type string $description     Structure description.
	 *     @type string $structure_data  JSON-encoded structure configuration.
	 *     @type int    $is_active       Active status flag.
	 *     @type int    $is_default      Default structure flag.
	 * }
	 * @return int|false The inserted ID on success, false on failure.
	 */
	public function create($data) {
		$insert_data = array(
			'name' => sanitize_text_field($data['name']),
			'description' => isset($data['description']) ? sanitize_textarea_field($data['description']) : '',
			'structure_data' => $data['structure_data'],
			'is_active' => !empty($data['is_active']) ? 1 : 0,
			'is_default' => !empty($data['is_default']) ? 1 : 0,
		);
		
		// If setting as default, unset other defaults
		if ($insert_data['is_default']) {
			$this->wpdb->update($this->table_name, array('is_default' => 0), array('is_default' => 1));
		}
		
		$format = array('%s', '%s', '%s', '%d', '%d');
		
		$result = $this->wpdb->insert($this->table_name, $insert_data, $format);
		
		return $result ? $this->wpdb->insert_id : false;
	}
	
	/**
	 * Update an existing article structure.
	 *
	 * @param int   $id   Structure ID.
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
		
		if (isset($data['structure_data'])) {
			$update_data['structure_data'] = $data['structure_data'];
			$format[] = '%s';
		}
		
		if (isset($data['is_active'])) {
			$update_data['is_active'] = $data['is_active'] ? 1 : 0;
			$format[] = '%d';
		}
		
		if (isset($data['is_default'])) {
			$update_data['is_default'] = $data['is_default'] ? 1 : 0;
			$format[] = '%d';
			
			// If setting as default, unset other defaults
			if ($update_data['is_default']) {
				$this->wpdb->update($this->table_name, array('is_default' => 0), array('is_default' => 1));
			}
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
	 * Delete an article structure by ID.
	 *
	 * @param int $id Structure ID.
	 * @return bool True on success, false on failure.
	 */
	public function delete($id) {
		return $this->wpdb->delete($this->table_name, array('id' => $id), array('%d')) !== false;
	}
	
	/**
	 * Toggle structure active status.
	 *
	 * @param int  $id        Structure ID.
	 * @param bool $is_active Active status.
	 * @return bool True on success, false on failure.
	 */
	public function set_active($id, $is_active) {
		return $this->update($id, array('is_active' => $is_active));
	}
	
	/**
	 * Set a structure as default.
	 *
	 * @param int $id Structure ID.
	 * @return bool True on success, false on failure.
	 */
	public function set_default($id) {
		// Unset all defaults first
		$this->wpdb->update($this->table_name, array('is_default' => 0), array('is_default' => 1));
		
		// Set this one as default
		return $this->update($id, array('is_default' => 1));
	}
	
	/**
	 * Count structures by status.
	 *
	 * @return array {
	 *     @type int $total  Total number of structures.
	 *     @type int $active Number of active structures.
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
	 * Check if a structure name already exists.
	 *
	 * @param string $name       Structure name.
	 * @param int    $exclude_id Optional. Exclude this ID from check. Default 0.
	 * @return bool True if name exists, false otherwise.
	 */
	public function name_exists($name, $exclude_id = 0) {
		if ($exclude_id > 0) {
			$result = $this->wpdb->get_var($this->wpdb->prepare(
				"SELECT COUNT(*) FROM {$this->table_name} WHERE name = %s AND id != %d",
				$name,
				$exclude_id
			));
		} else {
			$result = $this->wpdb->get_var($this->wpdb->prepare(
				"SELECT COUNT(*) FROM {$this->table_name} WHERE name = %s",
				$name
			));
		}
		
		return $result > 0;
	}
}
