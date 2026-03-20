<?php
/**
 * Sources Repository
 *
 * Database abstraction layer for trusted sources used to guide AI content generation.
 *
 * @package AI_Post_Scheduler
 * @since 1.8.0
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Sources_Repository
 *
 * Repository pattern implementation for trusted source data access.
 * Encapsulates all database operations related to the aips_sources table.
 */
class AIPS_Sources_Repository {

	/**
	 * @var string The sources table name (with prefix).
	 */
	private $table_name;

	/**
	 * @var wpdb WordPress database abstraction object.
	 */
	private $wpdb;

	/**
	 * Initialize the repository.
	 */
	public function __construct() {
		global $wpdb;
		$this->wpdb       = $wpdb;
		$this->table_name = $wpdb->prefix . 'aips_sources';
	}

	/**
	 * Get all sources with optional filtering.
	 *
	 * @param bool $active_only Return only active sources. Default false.
	 * @return array Array of source objects.
	 */
	public function get_all($active_only = false) {
		$where = $active_only ? 'WHERE is_active = 1' : '';
		return $this->wpdb->get_results(
			"SELECT * FROM {$this->table_name} {$where} ORDER BY created_at ASC"
		);
	}

	/**
	 * Get a single source by ID.
	 *
	 * @param int $id Source ID.
	 * @return object|null Source object or null if not found.
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
	 * Get only the URLs of all active sources.
	 *
	 * Used by the prompt builder to inject trusted domains into AI prompts.
	 *
	 * @return string[] Array of URL strings.
	 */
	public function get_active_urls() {
		$rows = $this->wpdb->get_results(
			"SELECT url FROM {$this->table_name} WHERE is_active = 1 ORDER BY created_at ASC"
		);

		return array_map(function ($row) {
			return $row->url;
		}, $rows);
	}

	/**
	 * Create a new source.
	 *
	 * @param array $data {
	 *     Source data.
	 *
	 *     @type string $url         The source URL (required).
	 *     @type string $label       Short human-readable label.
	 *     @type string $description Optional notes about the source.
	 *     @type int    $is_active   Active flag (1 or 0). Default 1.
	 * }
	 * @return int|false Inserted ID on success, false on failure.
	 */
	public function create($data) {
		$insert_data = array(
			'url'         => esc_url_raw($data['url']),
			'label'       => isset($data['label']) ? sanitize_text_field($data['label']) : '',
			'description' => isset($data['description']) ? sanitize_textarea_field($data['description']) : '',
			'is_active'   => isset($data['is_active']) ? (int) $data['is_active'] : 1,
		);

		$format = array('%s', '%s', '%s', '%d');

		$result = $this->wpdb->insert($this->table_name, $insert_data, $format);

		return $result ? $this->wpdb->insert_id : false;
	}

	/**
	 * Update an existing source.
	 *
	 * @param int   $id   Source ID.
	 * @param array $data Data to update (same structure as create).
	 * @return bool True on success, false on failure.
	 */
	public function update($id, $data) {
		$update_data = array();
		$format      = array();

		if (isset($data['url'])) {
			$update_data['url'] = esc_url_raw($data['url']);
			$format[]           = '%s';
		}

		if (isset($data['label'])) {
			$update_data['label'] = sanitize_text_field($data['label']);
			$format[]             = '%s';
		}

		if (isset($data['description'])) {
			$update_data['description'] = sanitize_textarea_field($data['description']);
			$format[]                   = '%s';
		}

		if (isset($data['is_active'])) {
			$update_data['is_active'] = (int) $data['is_active'];
			$format[]                 = '%d';
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
	 * Delete a source by ID.
	 *
	 * @param int $id Source ID.
	 * @return bool True on success, false on failure.
	 */
	public function delete($id) {
		return $this->wpdb->delete(
			$this->table_name,
			array('id' => $id),
			array('%d')
		) !== false;
	}

	/**
	 * Set the active status for a source.
	 *
	 * @param int  $id        Source ID.
	 * @param bool $is_active Whether the source should be active.
	 * @return bool True on success, false on failure.
	 */
	public function set_active($id, $is_active) {
		return $this->update($id, array('is_active' => $is_active ? 1 : 0));
	}

	/**
	 * Check whether a URL already exists in the table (case-insensitive).
	 *
	 * @param string $url        URL to check.
	 * @param int    $exclude_id Optional. Exclude this ID from the check. Default 0.
	 * @return bool True if the URL already exists.
	 */
	public function url_exists($url, $exclude_id = 0) {
		$url = esc_url_raw($url);

		if ($exclude_id > 0) {
			$count = $this->wpdb->get_var(
				$this->wpdb->prepare(
					"SELECT COUNT(*) FROM {$this->table_name} WHERE url = %s AND id != %d",
					$url,
					$exclude_id
				)
			);
		} else {
			$count = $this->wpdb->get_var(
				$this->wpdb->prepare(
					"SELECT COUNT(*) FROM {$this->table_name} WHERE url = %s",
					$url
				)
			);
		}

		return $count > 0;
	}
}
