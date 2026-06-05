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

if (!trait_exists('AIPS_Cacheable_Repository')) {
	require_once __DIR__ . '/trait-aips-cacheable-repository.php';
}

/**
 * Class AIPS_Authors_Repository
 *
 * Repository pattern implementation for author data access.
 * Encapsulates all database operations related to authors.
 */
class AIPS_Authors_Repository {
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
	 * Results are cached for the duration of the request using the named
	 * named cache instance so repeat calls within the same request
	 * do not issue additional DB queries.
	 *
	 * @param bool $active_only Optional. Return only active authors. Default false.
	 * @return array Array of author objects.
	 */
	public function get_all($active_only = false) {
		return $this->cache_read(
			'authors.get_all',
			array(
				'active_only' => (bool) $active_only,
			),
			function() use ( $active_only ) {
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
		);
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
		return $this->cache_read(
			'authors.get_by_id',
			array(
				'author_id' => absint( $id ),
			),
			function() use ( $id ) {
				return $this->wpdb->get_row(
					$this->wpdb->prepare(
						"SELECT * FROM {$this->table_name} WHERE id = %d",
						$id
					)
				);
			}
		);
	}
	
	/**
	 * Create a new author.
	 *
	 * @param array $data Author data.
	 * @return int|false The ID of the created author or false on failure.
	 */
	public function create($data) {
		$now = AIPS_DateTime::now()->timestamp();

		if (!isset($data['created_at'])) {
			$data['created_at'] = $now;
		}

		if (!isset($data['updated_at'])) {
			$data['updated_at'] = $now;
		}

		$result = $this->wpdb->insert($this->table_name, $data);
		if ( $result ) {
			$this->invalidate_cache_domain(
				'author',
				array(),
				'author_created'
			);
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
		if (!isset($data['updated_at'])) {
			$data['updated_at'] = AIPS_DateTime::now()->timestamp();
		}

		$result = $this->wpdb->update(
			$this->table_name,
			$data,
			array('id' => $id),
			null,
			array('%d')
		);
		if ( $result !== false ) {
			$this->invalidate_author_cache( $id, 'author_updated' );
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
			$this->invalidate_author_cache( $id, 'author_deleted' );
		}
		return $result;
	}
	
	/**
	 * Get authors that need topic generation (where next_run is due).
	 *
	 * @return array Array of author objects.
	 */
	public function get_due_for_topic_generation() {
		$current_time = AIPS_DateTime::now()->timestamp();

		return $this->cache_read(
			'authors.get_due_for_topic_generation',
			array(
				'current_time' => (int) $current_time,
			),
			function() use ( $current_time ) {
				return $this->wpdb->get_results($this->wpdb->prepare(
					"SELECT * FROM {$this->table_name}
					WHERE is_active = 1
					AND (topic_generation_is_active IS NULL OR topic_generation_is_active = 1)
					AND topic_generation_next_run IS NOT NULL
					AND topic_generation_next_run <= %d
					ORDER BY topic_generation_next_run ASC",
					$current_time
				));
			},
			array(
				'queue_sensitive' => true,
			)
		);
	}
	
	/**
	 * Get authors that need post generation (where post_generation_next_run is due).
	 *
	 * @return array Array of author objects.
	 */
	public function get_due_for_post_generation() {
		$current_time = AIPS_DateTime::now()->timestamp();

		return $this->cache_read(
			'authors.get_due_for_post_generation',
			array(
				'current_time' => (int) $current_time,
			),
			function() use ( $current_time ) {
				return $this->wpdb->get_results($this->wpdb->prepare(
					"SELECT * FROM {$this->table_name}
					WHERE is_active = 1
					AND (post_generation_is_active IS NULL OR post_generation_is_active = 1)
					AND post_generation_next_run IS NOT NULL
					AND post_generation_next_run <= %d
					ORDER BY post_generation_next_run ASC",
					$current_time
				));
			},
			array(
				'queue_sensitive' => true,
			)
		);
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
			array(
				'topic_generation_is_active' => (int) $is_active ? 1 : 0,
				'updated_at' => AIPS_DateTime::now()->timestamp(),
			),
			array('id' => absint($author_id)),
			array('%d', '%d'),
			array('%d')
		);
		if ( $result !== false ) {
			$this->invalidate_author_cache( $author_id, 'author_topic_generation_active_updated' );
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
			array(
				'post_generation_is_active' => (int) $is_active ? 1 : 0,
				'updated_at' => AIPS_DateTime::now()->timestamp(),
			),
			array('id' => absint($author_id)),
			array('%d', '%d'),
			array('%d')
		);
		if ( $result !== false ) {
			$this->invalidate_author_cache( $author_id, 'author_post_generation_active_updated' );
		}
		return $result;
	}
	
	/**
	 * Update topic generation schedule for an author.
	 *
	 * @param int $author_id Author ID.
	 * @param int $next_run Next run timestamp.
	 * @return int|false The number of rows updated, or false on error.
	 */
	public function update_topic_generation_schedule($author_id, $next_run) {
		$result = $this->wpdb->update(
			$this->table_name,
			array(
				'topic_generation_last_run' => AIPS_DateTime::now()->timestamp(),
				'topic_generation_next_run' => absint($next_run),
				'updated_at' => AIPS_DateTime::now()->timestamp(),
			),
			array('id' => $author_id),
			array('%d', '%d', '%d'),
			array('%d')
		);
		if ( $result !== false ) {
			$this->invalidate_author_cache( $author_id, 'author_topic_generation_schedule_updated' );
		}
		return $result;
	}
	
	/**
	 * Update post generation schedule for an author.
	 *
	 * @param int $author_id Author ID.
	 * @param int $next_run Next run timestamp.
	 * @return int|false The number of rows updated, or false on error.
	 */
	public function update_post_generation_schedule($author_id, $next_run) {
		$result = $this->wpdb->update(
			$this->table_name,
			array(
				'post_generation_last_run' => AIPS_DateTime::now()->timestamp(),
				'post_generation_next_run' => absint($next_run),
				'updated_at' => AIPS_DateTime::now()->timestamp(),
			),
			array('id' => $author_id),
			array('%d', '%d', '%d'),
			array('%d')
		);
		if ( $result !== false ) {
			$this->invalidate_author_cache( $author_id, 'author_post_generation_schedule_updated' );
		}
		return $result;
	}

	/**
	 * Return the repository cache group for author reads.
	 *
	 * @return string
	 */
	protected function repository_cache_group(): string {
		return 'aips_authors';
	}

	/**
	 * Return the explicit repository cache policies for author reads.
	 *
	 * @return array
	 */
	protected function repository_cache_policies(): array {
		return array(
			'authors.get_all'   => array(
				'tier'        => 'medium',
				'ttl'         => 300,
				'tags'        => array( 'authors' ),
				'description' => 'Cache author list reads, including active-only filtering.',
			),
			'authors.get_by_id' => array(
				'tier'        => 'medium',
				'tags'        => array( 'authors', 'author:{author_id}' ),
				'cache_null'  => false,
				'description' => 'Cache single-author reads by ID.',
			),
			'authors.get_due_for_topic_generation' => array(
				'tier'         => 'none',
				'bypass_cron'  => true,
				'description'  => 'Leave topic-generation due-item reads uncached.',
			),
			'authors.get_due_for_post_generation'  => array(
				'tier'         => 'none',
				'bypass_cron'  => true,
				'description'  => 'Leave post-generation due-item reads uncached.',
			),
		);
	}

	/**
	 * Invalidate author read caches for list and single-author operations.
	 *
	 * @param int    $author_id Author ID.
	 * @param string $reason Invalidation reason.
	 * @return void
	 */
	private function invalidate_author_cache( $author_id, $reason ) {
		$this->invalidate_cache_domain(
			'author',
			array(
				'author_id' => absint( $author_id ),
			),
			(string) $reason
		);
	}
}
