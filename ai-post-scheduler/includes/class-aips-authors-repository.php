<?php
/**
 * Authors Repository
 *
 * Database abstraction layer for author operations.
 * Provides a clean interface for CRUD operations on the authors table.
 *
 * @package AI_Post_Scheduler
 * @since 1.8.0
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Authors_Repository
 *
 * Repository pattern implementation for author data access.
 * Encapsulates all database operations related to authors.
 */
class AIPS_Authors_Repository {

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
	 * @var string The authors table name (with prefix)
	 */
	private $table_name;
	
	/**
	 * @var wpdb WordPress database abstraction object
	 */
	private $wpdb;

	/**
	 * @var AIPS_Cache In-request identity-map cache (array driver).
	 */
	private $cache = null;
	
	/**
	 * Initialize the repository.
	 */
	public function __construct() {
		global $wpdb;
		$this->wpdb = $wpdb;
		$this->table_name = $wpdb->prefix . 'aips_authors';
		$this->cache = AIPS_Cache_Factory::named( 'aips_authors_repository', 'array' );
	}
	
	/**
	 * Get all authors with optional filtering.
	 *
	 * Results are cached for the duration of the request using the named
	 * array-driver cache instance so repeat calls within the same request
	 * do not issue additional DB queries.
	 *
	 * @param bool $active_only Optional. Return only active authors. Default false.
	 * @return array Array of author objects.
	 */
	public function get_all($active_only = false) {
		$key = 'all:' . ( $active_only ? '1' : '0' );
		if ( $this->cache->has( $key ) ) {
			return $this->cache->get( $key );
		}
		if ( $active_only ) {
			$sql = $this->wpdb->prepare(
				"SELECT * FROM {$this->table_name} WHERE is_active = %d ORDER BY name ASC",
				1
			);
		} else {
			$sql = "SELECT * FROM {$this->table_name} ORDER BY name ASC";
		}
		$result = $this->wpdb->get_results( $sql );
		$this->cache->set( $key, $result );
		return $result;
	}
	
	/**
	 * Get a single author by ID.
	 *
	 * Non-null results are cached for the duration of the request. Null
	 * results (record not found) are always fetched fresh from the DB.
	 *
	 * @param int $id Author ID.
	 * @return object|null Author object or null if not found.
	 */
	public function get_by_id($id) {
		$key = 'id:' . (int) $id;
		if ( $this->cache->has( $key ) ) {
			return $this->cache->get( $key );
		}
		$result = $this->wpdb->get_row( $this->wpdb->prepare(
			"SELECT * FROM {$this->table_name} WHERE id = %d",
			$id
		) );
		if ( $result !== null ) {
			$this->cache->set( $key, $result );
		}
		return $result;
	}
	
	/**
	 * Create a new author.
	 *
	 * @param array $data Author data.
	 * @return int|false The ID of the created author or false on failure.
	 */
	public function create($data) {
		$result = $this->wpdb->insert($this->table_name, $data);
		if ( $result ) {
			$this->cache->flush();
		}
		return $result ? $this->wpdb->insert_id : false;
	}
	
	/**
	 * Update an author.
	 *
	 * @param int $id Author ID.
	 * @param array $data Author data to update.
	 * @return int|false The number of rows updated, or false on error.
	 */
	public function update($id, $data) {
		$result = $this->wpdb->update(
			$this->table_name,
			$data,
			array('id' => $id),
			null,
			array('%d')
		);
		if ( $result !== false ) {
			$this->cache->flush();
		}
		return $result;
	}
	
	/**
	 * Delete an author.
	 *
	 * @param int $id Author ID.
	 * @return int|false The number of rows deleted, or false on error.
	 */
	public function delete($id) {
		$result = $this->wpdb->delete(
			$this->table_name,
			array('id' => $id),
			array('%d')
		);
		if ( $result !== false ) {
			$this->cache->flush();
		}
		return $result;
	}
	
	/**
	 * Get authors that need topic generation (where next_run is due).
	 *
	 * @return array Array of author objects.
	 */
	public function get_due_for_topic_generation() {
		$current_time = current_time('mysql');
		return $this->wpdb->get_results($this->wpdb->prepare(
			"SELECT * FROM {$this->table_name} 
			WHERE is_active = 1 
			AND (topic_generation_is_active IS NULL OR topic_generation_is_active = 1)
			AND topic_generation_next_run IS NOT NULL 
			AND topic_generation_next_run <= %s
			ORDER BY topic_generation_next_run ASC",
			$current_time
		));
	}
	
	/**
	 * Get authors that need post generation (where post_generation_next_run is due).
	 *
	 * @return array Array of author objects.
	 */
	public function get_due_for_post_generation() {
		$current_time = current_time('mysql');
		return $this->wpdb->get_results($this->wpdb->prepare(
			"SELECT * FROM {$this->table_name} 
			WHERE is_active = 1 
			AND (post_generation_is_active IS NULL OR post_generation_is_active = 1)
			AND post_generation_next_run IS NOT NULL 
			AND post_generation_next_run <= %s
			ORDER BY post_generation_next_run ASC",
			$current_time
		));
	}

	/**
	 * Set the active status for an author's topic generation schedule.
	 *
	 * @param int $author_id Author ID.
	 * @param int $is_active 1 to enable, 0 to disable.
	 * @return int|false Rows updated or false on failure.
	 */
	public function update_topic_generation_active($author_id, $is_active) {
		$result = $this->wpdb->update(
			$this->table_name,
			array('topic_generation_is_active' => (int) $is_active ? 1 : 0),
			array('id' => absint($author_id)),
			array('%d'),
			array('%d')
		);
		if ( $result !== false ) {
			$this->cache->flush();
		}
		return $result;
	}

	/**
	 * Set the active status for an author's post generation schedule.
	 *
	 * @param int $author_id Author ID.
	 * @param int $is_active 1 to enable, 0 to disable.
	 * @return int|false Rows updated or false on failure.
	 */
	public function update_post_generation_active($author_id, $is_active) {
		$result = $this->wpdb->update(
			$this->table_name,
			array('post_generation_is_active' => (int) $is_active ? 1 : 0),
			array('id' => absint($author_id)),
			array('%d'),
			array('%d')
		);
		if ( $result !== false ) {
			$this->cache->flush();
		}
		return $result;
	}
	
	/**
	 * Update topic generation schedule for an author.
	 *
	 * @param int $author_id Author ID.
	 * @param string $next_run Next run time in MySQL datetime format.
	 * @return int|false The number of rows updated, or false on error.
	 */
	public function update_topic_generation_schedule($author_id, $next_run) {
		$result = $this->wpdb->update(
			$this->table_name,
			array(
				'topic_generation_last_run' => current_time('mysql'),
				'topic_generation_next_run' => $next_run
			),
			array('id' => $author_id),
			array('%s', '%s'),
			array('%d')
		);
		if ( $result !== false ) {
			$this->cache->flush();
		}
		return $result;
	}
	
	/**
	 * Update post generation schedule for an author.
	 *
	 * @param int $author_id Author ID.
	 * @param string $next_run Next run time in MySQL datetime format.
	 * @return int|false The number of rows updated, or false on error.
	 */
	public function update_post_generation_schedule($author_id, $next_run) {
		$result = $this->wpdb->update(
			$this->table_name,
			array(
				'post_generation_last_run' => current_time('mysql'),
				'post_generation_next_run' => $next_run
			),
			array('id' => $author_id),
			array('%s', '%s'),
			array('%d')
		);
		if ( $result !== false ) {
			$this->cache->flush();
		}
		return $result;
	}
}
