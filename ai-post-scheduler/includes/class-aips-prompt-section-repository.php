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

if (!trait_exists('AIPS_Cacheable_Repository')) {
	require_once __DIR__ . '/trait-aips-cacheable-repository.php';
}

/**
 * Class AIPS_Prompt_Section_Repository
 *
 * Repository pattern implementation for prompt section data access.
 * Encapsulates all database operations related to prompt sections.
 */
class AIPS_Prompt_Section_Repository {
	use AIPS_Cacheable_Repository;

	/**
	 * @var self|null Singleton instance.
	 */
	private static $instance = null;

	/**
	 * Get the shared singleton instance.
	 *
	 * @return self
	 */
	public static function instance(): self {
		if ( self::$instance === null ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

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
	 * Results are cached with a long-tier persistent cache and invalidated
	 * whenever a section is created, updated, or deleted.
	 *
	 * @param bool $active_only Optional. Return only active sections. Default false.
	 * @return array Array of section objects.
	 */
	public function get_all($active_only = false) {
		return $this->cache_read(
			'prompt_sections.get_all',
			array( 'active_only' => (bool) $active_only ),
			function() use ( $active_only ) {
				$where  = $active_only ? "WHERE is_active = 1" : "";
				return $this->wpdb->get_results( "SELECT * FROM {$this->table_name} $where ORDER BY name ASC" );
			}
		);
	}

	/**
	 * Get a single prompt section by ID.
	 *
	 * Non-null results are cached with a long-tier persistent cache.
	 * Null results (record not found) are never cached.
	 *
	 * @param int $id Section ID.
	 * @return object|null Section object or null if not found.
	 */
	public function get_by_id($id) {
		return $this->cache_read(
			'prompt_sections.get_by_id',
			array( 'section_id' => absint( $id ) ),
			function() use ( $id ) {
				return $this->wpdb->get_row( $this->wpdb->prepare(
					"SELECT * FROM {$this->table_name} WHERE id = %d",
					$id
				) );
			}
		);
	}

	/**
	 * Get a prompt section by its key.
	 *
	 * Non-null results are cached with a long-tier persistent cache.
	 * Null results (record not found) are never cached.
	 *
	 * @param string $section_key Section key.
	 * @return object|null Section object or null if not found.
	 */
	public function get_by_key($section_key) {
		return $this->cache_read(
			'prompt_sections.get_by_key',
			array( 'section_key' => (string) $section_key ),
			function() use ( $section_key ) {
				return $this->wpdb->get_row( $this->wpdb->prepare(
					"SELECT * FROM {$this->table_name} WHERE section_key = %s",
					$section_key
				) );
			}
		);
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
		$now = AIPS_DateTime::now()->timestamp();

		$insert_data = array(
			'name' => sanitize_text_field($data['name']),
			'description' => isset($data['description']) ? sanitize_textarea_field($data['description']) : '',
			'section_key' => sanitize_key($data['section_key']),
			'content' => wp_kses_post($data['content']),
			'is_active' => !empty($data['is_active']) ? 1 : 0,
			'created_at' => $now,
			'updated_at' => $now,
		);

		$format = array('%s', '%s', '%s', '%s', '%d', '%d', '%d');

		$result = $this->wpdb->insert($this->table_name, $insert_data, $format);

		if ( $result ) {
			$this->invalidate_cache_domain( 'prompt_section', array(), 'prompt_section_created' );
		}

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

		$update_data['updated_at'] = AIPS_DateTime::now()->timestamp();
		$format[] = '%d';

		$result = $this->wpdb->update(
			$this->table_name,
			$update_data,
			array('id' => $id),
			$format,
			array('%d')
		) !== false;

		if ( $result ) {
			$this->invalidate_cache_domain( 'prompt_section', array( 'section_id' => absint( $id ) ), 'prompt_section_updated' );
		}

		return $result;
	}

	/**
	 * Delete a prompt section by ID.
	 *
	 * @param int $id Section ID.
	 * @return bool True on success, false on failure.
	 */
	public function delete($id) {
		$result = $this->wpdb->delete($this->table_name, array('id' => $id), array('%d')) !== false;
		if ( $result ) {
			$this->invalidate_cache_domain( 'prompt_section', array( 'section_id' => absint( $id ) ), 'prompt_section_deleted' );
		}
		return $result;
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
			'total' => isset($results->total) ? (int) $results->total : 0,
			'active' => isset($results->active) ? (int) $results->active : 0,
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

	/**
	 * Return the repository cache group for prompt section reads.
	 *
	 * @return string
	 */
	protected function repository_cache_group(): string {
		return 'aips_prompt_sections';
	}

	/**
	 * Return the explicit repository cache policies for prompt section reads.
	 *
	 * @return array
	 */
	protected function repository_cache_policies(): array {
		return array(
			'prompt_sections.get_all'    => array(
				'tier'        => 'long',
				'tags'        => array( 'prompt_sections' ),
				'description' => 'Cache prompt section list reads including active-only filtering.',
			),
			'prompt_sections.get_by_id'  => array(
				'tier'        => 'long',
				'tags'        => array( 'prompt_sections', 'prompt_section:{section_id}' ),
				'cache_null'  => false,
				'description' => 'Cache single prompt section reads by ID.',
			),
			'prompt_sections.get_by_key' => array(
				'tier'        => 'long',
				'tags'        => array( 'prompt_sections' ),
				'cache_null'  => false,
				'description' => 'Cache single prompt section reads by section_key.',
			),
		);
	}
}
