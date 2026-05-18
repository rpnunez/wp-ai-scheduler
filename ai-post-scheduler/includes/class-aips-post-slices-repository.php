<?php
/**
 * Post Slices Repository
 *
 * Database abstraction layer for post slice operations.
 *
 * @package AI_Post_Scheduler
 * @since 2.6.0
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Post_Slices_Repository
 */
class AIPS_Post_Slices_Repository {

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
	 * @var AIPS_Cache
	 */
	private $cache = null;

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
		$this->table_name = $wpdb->prefix . 'aips_post_slices';
		$this->cache = AIPS_Cache_Factory::named( AIPS_Cache_Policy::cache_name( AIPS_Cache_Policy::SUBSYSTEM_POST_SLICES_REPOSITORY ) );
	}

	/**
	 * Get all slices.
	 *
	 * @param bool $active_only Whether to return only active slices.
	 * @return array
	 */
	public function get_all($active_only = false) {
		$key = AIPS_Cache_Policy::key( AIPS_Cache_Policy::SUBSYSTEM_POST_SLICES_REPOSITORY, 'all', array('active_only' => $active_only) );
		if ($this->cache->has($key)) {
			return $this->cache->get($key);
		}

		if ($active_only) {
			$sql = $this->wpdb->prepare(
				"SELECT * FROM {$this->table_name} WHERE is_active = %d ORDER BY sort_order ASC, name ASC, id ASC",
				1
			);
		} else {
			$sql = "SELECT * FROM {$this->table_name} ORDER BY sort_order ASC, name ASC, id ASC";
		}

		$result = $this->wpdb->get_results($sql);
		if (!is_array($result)) {
			$result = array();
		}

		$this->cache->set($key, $result);

		return $result;
	}

	/**
	 * Get one slice by ID.
	 *
	 * @param int $id Slice ID.
	 * @return object|null
	 */
	public function get_by_id($id) {
		$key = AIPS_Cache_Policy::key( AIPS_Cache_Policy::SUBSYSTEM_POST_SLICES_REPOSITORY, 'id', array('id' => $id) );
		if ($this->cache->has($key)) {
			return $this->cache->get($key);
		}

		$result = $this->wpdb->get_row(
			$this->wpdb->prepare(
				"SELECT * FROM {$this->table_name} WHERE id = %d",
				$id
			)
		);

		if ($result !== null) {
			$this->cache->set($key, $result);
		}

		return $result;
	}

	/**
	 * Create a slice.
	 *
	 * @param array $data Slice data.
	 * @return int|false
	 */
	public function create($data) {
		$now = AIPS_DateTime::now()->timestamp();
		$insert_data = array(
			'name'        => isset($data['name']) ? sanitize_text_field($data['name']) : '',
			'description' => isset($data['description']) ? sanitize_textarea_field($data['description']) : '',
			'sort_order'  => isset($data['sort_order']) ? (int) $data['sort_order'] : 0,
			'is_active'   => !empty($data['is_active']) ? 1 : 0,
			'created_at'  => isset($data['created_at']) ? absint($data['created_at']) : $now,
			'updated_at'  => isset($data['updated_at']) ? absint($data['updated_at']) : $now,
		);

		$result = $this->wpdb->insert(
			$this->table_name,
			$insert_data,
			array('%s', '%s', '%d', '%d', '%d', '%d')
		);

		if ($result) {
			AIPS_Cache_Invalidation_Bus::invalidate( AIPS_Cache_Policy::SUBSYSTEM_POST_SLICES_REPOSITORY, 'update' );
		}

		return $result ? $this->wpdb->insert_id : false;
	}

	/**
	 * Update a slice.
	 *
	 * @param int   $id Slice ID.
	 * @param array $data Slice data.
	 * @return int|false
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

		if (array_key_exists('sort_order', $data)) {
			$update_data['sort_order'] = (int) $data['sort_order'];
			$formats[] = '%d';
		}

		if (array_key_exists('is_active', $data)) {
			$update_data['is_active'] = !empty($data['is_active']) ? 1 : 0;
			$formats[] = '%d';
		}

		$update_data['updated_at'] = isset($data['updated_at']) ? absint($data['updated_at']) : AIPS_DateTime::now()->timestamp();
		$formats[] = '%d';

		$result = $this->wpdb->update(
			$this->table_name,
			$update_data,
			array('id' => absint($id)),
			$formats,
			array('%d')
		);

		if ($result !== false) {
			AIPS_Cache_Invalidation_Bus::invalidate( AIPS_Cache_Policy::SUBSYSTEM_POST_SLICES_REPOSITORY, 'update' );
		}

		return $result;
	}

	/**
	 * Delete a slice.
	 *
	 * @param int $id Slice ID.
	 * @return int|false
	 */
	public function delete($id) {
		$result = $this->wpdb->delete(
			$this->table_name,
			array('id' => absint($id)),
			array('%d')
		);

		if ($result !== false) {
			AIPS_Cache_Invalidation_Bus::invalidate( AIPS_Cache_Policy::SUBSYSTEM_POST_SLICES_REPOSITORY, 'update' );
		}

		return $result;
	}

	/**
	 * Set active status.
	 *
	 * @param int  $id Slice ID.
	 * @param bool $is_active Active status.
	 * @return int|false
	 */
	public function set_active($id, $is_active) {
		return $this->update($id, array('is_active' => $is_active ? 1 : 0));
	}

	/**
	 * Bulk set active status for multiple slices.
	 *
	 * @param array $ids Array of slice IDs.
	 * @param bool  $is_active Active status.
	 * @return int|false Number of rows affected, or false on error.
	 */
	public function bulk_set_active(array $ids, $is_active) {
		if (empty($ids)) {
			return 0;
		}

		$ids = array_map('absint', $ids);
		$ids = array_filter($ids);

		if (empty($ids)) {
			return 0;
		}

		$placeholders = implode(',', array_fill(0, count($ids), '%d'));
		$is_active_value = $is_active ? 1 : 0;
		$updated_at = AIPS_DateTime::now()->timestamp();

		$sql = $this->wpdb->prepare(
			"UPDATE {$this->table_name}
			SET is_active = %d, updated_at = %d
			WHERE id IN ({$placeholders})",
			array_merge(
				array($is_active_value, $updated_at),
				$ids
			)
		);

		$result = $this->wpdb->query($sql);

		if ($result !== false) {
			AIPS_Cache_Invalidation_Bus::invalidate( AIPS_Cache_Policy::SUBSYSTEM_POST_SLICES_REPOSITORY, 'update' );
		}

		return $result;
	}

	/**
	 * Bulk delete multiple slices.
	 *
	 * @param array $ids Array of slice IDs.
	 * @return int|false Number of rows affected, or false on error.
	 */
	public function bulk_delete(array $ids) {
		if (empty($ids)) {
			return 0;
		}

		$ids = array_map('absint', $ids);
		$ids = array_filter($ids);

		if (empty($ids)) {
			return 0;
		}

		$placeholders = implode(',', array_fill(0, count($ids), '%d'));

		$sql = $this->wpdb->prepare(
			"DELETE FROM {$this->table_name} WHERE id IN ({$placeholders})",
			$ids
		);

		$result = $this->wpdb->query($sql);

		if ($result !== false) {
			AIPS_Cache_Invalidation_Bus::invalidate( AIPS_Cache_Policy::SUBSYSTEM_POST_SLICES_REPOSITORY, 'update' );
		}

		return $result;
	}

	/**
	 * Check whether a name already exists.
	 *
	 * @param string $name Slice name.
	 * @param int    $exclude_id Optional slice ID to exclude.
	 * @return bool
	 */
	public function name_exists($name, $exclude_id = 0) {
		$name = sanitize_text_field($name);

		if ($exclude_id > 0) {
			$sql = $this->wpdb->prepare(
				"SELECT id FROM {$this->table_name} WHERE name = %s AND id != %d LIMIT 1",
				$name,
				$exclude_id
			);
		} else {
			$sql = $this->wpdb->prepare(
				"SELECT id FROM {$this->table_name} WHERE name = %s LIMIT 1",
				$name
			);
		}

		return (bool) $this->wpdb->get_var($sql);
	}

	/**
	 * Get counts grouped by active status.
	 *
	 * @return array
	 */
	public function get_counts() {
		$results = $this->wpdb->get_results(
			"SELECT is_active, COUNT(*) AS count FROM {$this->table_name} GROUP BY is_active",
			ARRAY_A
		);
		if (!is_array($results)) {
			$results = array();
		}

		$counts = array(
			'total'    => 0,
			'active'   => 0,
			'inactive' => 0,
		);

		foreach ($results as $row) {
			$count = (int) $row['count'];
			$counts['total'] += $count;

			if ((int) $row['is_active'] === 1) {
				$counts['active'] = $count;
			} else {
				$counts['inactive'] = $count;
			}
		}

		return $counts;
	}

	/**
	 * Return active slice labels in display order.
	 *
	 * @return array
	 */
	public function get_active_names() {
		$rows = $this->get_all(true);

		return array_values(
			array_filter(
				array_map(
					function($row) {
						return (is_object($row) && !empty($row->name)) ? (string) $row->name : '';
					},
					$rows
				)
			)
		);
	}
}
