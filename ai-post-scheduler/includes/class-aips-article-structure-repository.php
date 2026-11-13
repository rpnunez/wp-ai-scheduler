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

if (!trait_exists('AIPS_Cacheable_Repository')) {
	require_once __DIR__ . '/trait-aips-cacheable-repository.php';
}

/**
 * Class AIPS_Article_Structure_Repository
 *
 * Repository pattern implementation for article structure data access.
 * Encapsulates all database operations related to article structures.
 */
class AIPS_Article_Structure_Repository {
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
	 * Results are cached with a long-tier persistent cache and invalidated
	 * whenever a structure is created, updated, or deleted.
	 *
	 * @param bool $active_only Optional. Return only active structures. Default false.
	 * @return array Array of structure objects.
	 */
	public function get_all($active_only = false) {
		$active_only = (bool) $active_only;
		return $this->cache_read(
			'article_structures.get_all',
			array( 'active_only' => $active_only ),
			function() use ( $active_only ) {
				$where  = $active_only ? "WHERE is_active = 1" : "";
				return $this->wpdb->get_results( "SELECT * FROM {$this->table_name} $where ORDER BY name ASC" );
			}
		);
	}

	/**
	 * Get a single article structure by ID.
	 *
	 * Non-null results are cached with a long-tier persistent cache.
	 * Null results (record not found) are never cached.
	 *
	 * @param int $id Structure ID.
	 * @return object|null Structure object or null if not found.
	 */
	public function get_by_id($id) {
		$id = absint( $id );
		return $this->cache_read(
			'article_structures.get_by_id',
			array( 'structure_id' => $id ),
			function() use ( $id ) {
				return $this->wpdb->get_row( $this->wpdb->prepare(
					"SELECT * FROM {$this->table_name} WHERE id = %d",
					$id
				) );
			}
		);
	}

	/**
	 * Get the default article structure.
	 *
	 * The configured default article structure ID is stored as a plugin setting.
	 * The underlying structure row is resolved via get_by_id(), which is cached.
	 *
	 * @return object|null Default structure object or null if not found.
	 */
	public function get_default() {
		$structure_id = (int) AIPS_Config::get_instance()->get_option('aips_default_article_structure_id');

		if ($structure_id < 1) {
			return null;
		}

		$structure = $this->get_by_id($structure_id);

		if (!$structure) {
			return null;
		}

		if (isset($structure->is_active) && (int) $structure->is_active !== 1) {
			return null;
		}

		return $structure;
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
	 * }
	 * @return int|false The inserted ID on success, false on failure.
	 */
	public function create($data) {
		$now = AIPS_DateTime::now()->timestamp();

		$insert_data = array(
			'name' => sanitize_text_field($data['name']),
			'description' => isset($data['description']) ? sanitize_textarea_field($data['description']) : '',
			'archetype' => isset($data['archetype']) ? sanitize_key($data['archetype']) : 'custom',
			'editor_mode' => isset($data['editor_mode']) && in_array($data['editor_mode'], array('gutenberg', 'shortcode', 'auto'), true) ? $data['editor_mode'] : 'auto',
			'enable_schema' => !isset($data['enable_schema']) || !empty($data['enable_schema']) ? 1 : 0,
			'structure_data' => $data['structure_data'],
			'is_active' => !empty($data['is_active']) ? 1 : 0,
			'created_at' => $now,
			'updated_at' => $now,
		);

		$format = array('%s', '%s', '%s', '%s', '%d', '%s', '%d', '%d', '%d');

		$result = $this->wpdb->insert($this->table_name, $insert_data, $format);
		// Read insert_id before cache invalidation: invalidation can write
		// to other tables (cache, cache index), which overwrites it.
		$insert_id = $result ? (int) $this->wpdb->insert_id : 0;

		if ( $result ) {
			$this->invalidate_cache_domain( 'article_structure', array(), 'article_structure_created' );
		}

		return $result ? $insert_id : false;
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

		if (isset($data['archetype'])) {
			$update_data['archetype'] = sanitize_key($data['archetype']);
			$format[] = '%s';
		}

		if (isset($data['editor_mode'])) {
			$update_data['editor_mode'] = in_array($data['editor_mode'], array('gutenberg', 'shortcode', 'auto'), true) ? $data['editor_mode'] : 'auto';
			$format[] = '%s';
		}

		if (isset($data['enable_schema'])) {
			$update_data['enable_schema'] = !empty($data['enable_schema']) ? 1 : 0;
			$format[] = '%d';
		}

		if (isset($data['structure_data'])) {
			$update_data['structure_data'] = $data['structure_data'];
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
			$this->invalidate_cache_domain( 'article_structure', array( 'structure_id' => absint( $id ) ), 'article_structure_updated' );
		}

		return $result;
	}

	/**
	 * Delete an article structure by ID.
	 *
	 * @param int $id Structure ID.
	 * @return bool True on success, false on failure.
	 */
	public function delete($id) {
		$id = absint($id);
		$result = $this->wpdb->delete($this->table_name, array('id' => $id), array('%d')) !== false;
		if ( $result ) {
			if (class_exists('AIPS_Structure_Section_Rel_Repository')) {
				AIPS_Structure_Section_Rel_Repository::instance()->delete_by_structure_id($id);
			}
			$this->invalidate_cache_domain( 'article_structure', array( 'structure_id' => $id ), 'article_structure_deleted' );
		}
		return $result;
	}

	/**
	 * Clone an existing structure and all its child relational sections.
	 *
	 * @param int    $id       Source structure ID.
	 * @param string $new_name Optional new name.
	 * @return int|false Cloned structure ID or false.
	 */
	public function clone($id, $new_name = '') {
		$structure = $this->get_by_id($id);
		if (!$structure) {
			return false;
		}

		if (empty($new_name)) {
			/* translators: %s: original structure name */
			$new_name = sprintf(__('Copy of %s', 'ai-post-scheduler'), $structure->name);
		}

		$data = array(
			'name'           => $new_name,
			'description'    => isset($structure->description) ? $structure->description : '',
			'archetype'      => isset($structure->archetype) ? $structure->archetype : 'custom',
			'editor_mode'    => isset($structure->editor_mode) ? $structure->editor_mode : 'auto',
			'enable_schema'  => isset($structure->enable_schema) ? (int) $structure->enable_schema : 1,
			'structure_data' => $structure->structure_data,
			'is_active'      => 1,
		);

		$new_id = $this->create($data);
		if (!$new_id) {
			return false;
		}

		if (class_exists('AIPS_Structure_Section_Rel_Repository')) {
			AIPS_Structure_Section_Rel_Repository::instance()->clone_structure_sections($id, $new_id);
		}

		return $new_id;
	}

	/**
	 * Get ordered sections for a structure from the relational repository.
	 *
	 * @param int $structure_id Structure ID.
	 * @return array Array of section objects.
	 */
	public function get_sections($structure_id) {
		if (class_exists('AIPS_Structure_Section_Rel_Repository')) {
			return AIPS_Structure_Section_Rel_Repository::instance()->get_by_structure_id($structure_id);
		}
		return array();
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
			'total' => isset($results->total) ? (int) $results->total : 0,
			'active' => isset($results->active) ? (int) $results->active : 0,
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

	/**
	 * Return the repository cache group for article structure reads.
	 *
	 * @return string
	 */
	protected function repository_cache_group(): string {
		return 'aips_article_structures';
	}

	/**
	 * Return the explicit repository cache policies for article structure reads.
	 *
	 * @return array
	 */
	protected function repository_cache_policies(): array {
		return array(
			'article_structures.get_all'   => array(
				'tier'        => 'long',
				'tags'        => array( 'article_structures' ),
				'description' => 'Cache article structure list reads including active-only filtering.',
			),
			'article_structures.get_by_id' => array(
				'tier'        => 'long',
				'tags'        => array( 'article_structures', 'article_structure:{structure_id}' ),
				'cache_null'  => false,
				'description' => 'Cache single article structure reads by ID.',
			),
		);
	}
}
