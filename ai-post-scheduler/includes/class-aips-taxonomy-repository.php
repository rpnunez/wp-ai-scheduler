<?php
/**
 * Taxonomy Repository
 *
 * Handles database operations for AI-generated taxonomy items (categories and tags).
 *
 * @package AI_Post_Scheduler
 * @since 1.7.3
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Taxonomy_Repository
 *
 * Repository for managing AI-generated taxonomy items in the database.
 */
class AIPS_Taxonomy_Repository {

	/**
	 * @var wpdb WordPress database object
	 */
	private $wpdb;

	/**
	 * @var string Table name
	 */
	private $table_name;

	/**
	 * Initialize the repository.
	 */
	public function __construct() {
		global $wpdb;
		$this->wpdb = $wpdb;
		$this->table_name = $wpdb->prefix . 'aips_taxonomy';
	}

	/**
	 * Get all taxonomy items by type.
	 *
	 * @param string $taxonomy_type Either 'category' or 'post_tag'.
	 * @return array Array of taxonomy items.
	 */
	public function get_by_type($taxonomy_type) {
		return $this->wpdb->get_results(
			$this->wpdb->prepare(
				"SELECT * FROM {$this->table_name} WHERE taxonomy_type = %s ORDER BY created_at DESC",
				$taxonomy_type
			)
		);
	}

	/**
	 * Get all taxonomy items by status and type.
	 *
	 * @param string $status Status filter (pending, approved, rejected).
	 * @param string $taxonomy_type Either 'category' or 'post_tag'.
	 * @return array Array of taxonomy items.
	 */
	public function get_by_status_and_type($status, $taxonomy_type) {
		return $this->wpdb->get_results(
			$this->wpdb->prepare(
				"SELECT * FROM {$this->table_name} WHERE status = %s AND taxonomy_type = %s ORDER BY created_at DESC",
				$status,
				$taxonomy_type
			)
		);
	}

	/**
	 * Get a single taxonomy item by ID.
	 *
	 * @param int $id Taxonomy item ID.
	 * @return object|null Taxonomy item object or null.
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
	 * Insert a new taxonomy item.
	 *
	 * @param array $data Taxonomy item data.
	 * @return int|false Insert ID on success, false on failure.
	 */
	public function insert($data) {
		$defaults = array(
			'name'              => '',
			'taxonomy_type'     => '',
			'status'            => 'pending',
			'base_post_ids'     => '',
			'generation_prompt' => '',
			'created_at'        => AIPS_DateTime::now()->timestamp(),
		);

		$allowed_columns = array(
			'name'              => '%s',
			'taxonomy_type'     => '%s',
			'status'            => '%s',
			'base_post_ids'     => '%s',
			'generation_prompt' => '%s',
			'created_at'        => '%d',
		);

		$data   = wp_parse_args($data, $defaults);
		$row    = array_intersect_key($data, $allowed_columns);
		$format = array_values(array_intersect_key($allowed_columns, $row));

		$result = $this->wpdb->insert(
			$this->table_name,
			$row,
			$format
		);

		return $result ? $this->wpdb->insert_id : false;
	}

	/**
	 * Update an existing taxonomy item.
	 *
	 * @param int   $id   Taxonomy item ID.
	 * @param array $data Data to update.
	 * @return bool True on success, false on failure.
	 */
	public function update($id, $data) {
		if (!isset($data['updated_at'])) {
			$data['updated_at'] = AIPS_DateTime::now()->timestamp();
		}

		return (bool) $this->wpdb->update(
			$this->table_name,
			$data,
			array('id' => $id),
			null,
			array('%d')
		);
	}

	/**
	 * Update the status of a taxonomy item.
	 *
	 * @param int    $id     Taxonomy item ID.
	 * @param string $status New status (pending, approved, rejected, created).
	 * @return bool True on success, false on failure.
	 */
	public function update_status($id, $status) {
		return $this->update($id, array(
			'status'     => $status,
			'updated_at' => AIPS_DateTime::now()->timestamp(),
		));
	}

	/**
	 * Delete a taxonomy item.
	 *
	 * @param int $id Taxonomy item ID.
	 * @return bool True on success, false on failure.
	 */
	public function delete($id) {
		return (bool) $this->wpdb->delete(
			$this->table_name,
			array('id' => $id),
			array('%d')
		);
	}

	/**
	 * Get status counts for all taxonomy types.
	 *
	 * @return array Associative array with counts.
	 */
	public function get_status_counts() {
		$results = $this->wpdb->get_results(
			"SELECT taxonomy_type, status, COUNT(*) as count
			FROM {$this->table_name}
			GROUP BY taxonomy_type, status"
		);

		$counts = array(
			'categories' => array(
				'pending' => 0,
				'approved' => 0,
				'rejected' => 0,
			),
			'tags' => array(
				'pending' => 0,
				'approved' => 0,
				'rejected' => 0,
			),
		);

		$taxonomy_type_map = array(
			'category' => 'categories',
			'post_tag'  => 'tags',
		);

		foreach ($results as $row) {
			if (!isset($taxonomy_type_map[$row->taxonomy_type])) {
				continue;
			}

			$type_key = $taxonomy_type_map[$row->taxonomy_type];
			if (isset($counts[$type_key][$row->status])) {
				$counts[$type_key][$row->status] = (int) $row->count;
			}
		}

		return $counts;
	}

	/**
	 * Search taxonomy items by name.
	 *
	 * @param string $search_term Search term.
	 * @param string $taxonomy_type Optional. Filter by taxonomy type.
	 * @return array Array of matching taxonomy items.
	 */
	public function search($search_term, $taxonomy_type = '') {
		$sql = "SELECT * FROM {$this->table_name} WHERE name LIKE %s";
		$params = array('%' . $this->wpdb->esc_like($search_term) . '%');

		if (!empty($taxonomy_type)) {
			$sql .= " AND taxonomy_type = %s";
			$params[] = $taxonomy_type;
		}

		$sql .= " ORDER BY created_at DESC";

		return $this->wpdb->get_results(
			$this->wpdb->prepare($sql, $params)
		);
	}
}
