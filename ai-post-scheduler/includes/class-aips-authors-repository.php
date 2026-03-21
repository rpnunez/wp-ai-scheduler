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
	 * @var string The authors table name (with prefix)
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
		$this->table_name = $wpdb->prefix . 'aips_authors';
	}
	
	/**
	 * Get all authors with optional filtering.
	 *
	 * @param bool $active_only Optional. Return only active authors. Default false.
	 * @return array Array of author objects.
	 */
	public function get_all($active_only = false) {
		if ( $active_only ) {
			$sql = $this->wpdb->prepare(
				"SELECT * FROM {$this->table_name} WHERE is_active = %d ORDER BY name ASC",
				1
			);
		} else {
			$sql = "SELECT * FROM {$this->table_name} ORDER BY name ASC";
		}

		return $this->wpdb->get_results( $sql );
	}
	
	/**
	 * Get a single author by ID.
	 *
	 * @param int $id Author ID.
	 * @return object|null Author object or null if not found.
	 */
	public function get_by_id($id) {
		return $this->wpdb->get_row($this->wpdb->prepare(
			"SELECT * FROM {$this->table_name} WHERE id = %d",
			$id
		));
	}
	
	/**
	 * Create a new author.
	 *
	 * @param array $data Author data.
	 * @return int|false The ID of the created author or false on failure.
	 */
	public function create($data) {
		$result = $this->wpdb->insert($this->table_name, $data);
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
		return $this->wpdb->update(
			$this->table_name,
			$data,
			array('id' => $id),
			null,
			array('%d')
		);
	}
	
	/**
	 * Delete an author.
	 *
	 * @param int $id Author ID.
	 * @return int|false The number of rows deleted, or false on error.
	 */
	public function delete($id) {
		return $this->wpdb->delete(
			$this->table_name,
			array('id' => $id),
			array('%d')
		);
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
			AND post_generation_next_run IS NOT NULL 
			AND post_generation_next_run <= %s
			ORDER BY post_generation_next_run ASC",
			$current_time
		));
	}
	
	/**
	 * Update topic generation schedule for an author.
	 *
	 * @param int $author_id Author ID.
	 * @param string $next_run Next run time in MySQL datetime format.
	 * @return int|false The number of rows updated, or false on error.
	 */
	public function update_topic_generation_schedule($author_id, $next_run) {
		return $this->wpdb->update(
			$this->table_name,
			array(
				'topic_generation_last_run' => current_time('mysql'),
				'topic_generation_next_run' => $next_run
			),
			array('id' => $author_id),
			array('%s', '%s'),
			array('%d')
		);
	}
	
	/**
	 * Update post generation schedule for an author.
	 *
	 * @param int $author_id Author ID.
	 * @param string $next_run Next run time in MySQL datetime format.
	 * @return int|false The number of rows updated, or false on error.
	 */
	public function update_post_generation_schedule($author_id, $next_run) {
		return $this->wpdb->update(
			$this->table_name,
			array(
				'post_generation_last_run' => current_time('mysql'),
				'post_generation_next_run' => $next_run
			),
			array('id' => $author_id),
			array('%s', '%s'),
			array('%d')
		);
	}
}
