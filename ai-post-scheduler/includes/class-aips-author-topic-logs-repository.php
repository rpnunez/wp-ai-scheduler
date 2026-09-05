<?php
/**
 * Author Topic Logs Repository
 *
 * Database abstraction layer for author topic log operations.
 * Provides a clean interface for CRUD operations on the author_topic_logs table.
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

if (!trait_exists('AIPS_Repository_Tables')) {
	require_once __DIR__ . '/trait-aips-repository-tables.php';
}

/**
 * Class AIPS_Author_Topic_Logs_Repository
 *
 * Repository pattern implementation for author topic log data access.
 * Encapsulates all database operations related to author topic logs.
 */
class AIPS_Author_Topic_Logs_Repository {
	use AIPS_Cacheable_Repository;
	use AIPS_Repository_Tables;

	/**
	 * @var string The author_topic_logs table name (with prefix)
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
		$this->table_name = $this->table('aips_author_topic_logs');
	}

	/**
	 * Get logs for a topic.
	 *
	 * @param int $author_topic_id Author topic ID.
	 * @param int $limit           Maximum number of logs to return. 0 returns all. Default 0.
	 * @return array Array of log objects.
	 */
	public function get_by_topic($author_topic_id, $limit = 0) {
		$limit = absint($limit);
		return $this->cache_read(
			'author_topic_logs.get_by_topic',
			array(
				'author_topic_id' => (int) $author_topic_id,
				'limit'           => $limit,
			),
			function() use ( $author_topic_id, $limit ) {
				if ($limit > 0) {
					return $this->wpdb->get_results($this->wpdb->prepare(
						"SELECT * FROM {$this->table_name} WHERE author_topic_id = %d ORDER BY created_at DESC LIMIT %d",
						$author_topic_id,
						$limit
					));
				}
				return $this->wpdb->get_results($this->wpdb->prepare(
					"SELECT * FROM {$this->table_name} WHERE author_topic_id = %d ORDER BY created_at DESC",
					$author_topic_id
				));
			}
		);
	}

	/**
	 * Get a single log by ID.
	 *
	 * @param int $id Log ID.
	 * @return object|null Log object or null if not found.
	 */
	public function get_by_id($id) {
		return $this->cache_read(
			'author_topic_logs.get_by_id',
			array(
				'id' => (int) $id,
			),
			function() use ( $id ) {
				return $this->wpdb->get_row($this->wpdb->prepare(
					"SELECT * FROM {$this->table_name} WHERE id = %d",
					$id
				));
			}
		);
	}

	/**
	 * Create a new log entry.
	 *
	 * @param array $data Log data.
	 * @return int|false The ID of the created log or false on failure.
	 */
	public function create($data) {
		if (!isset($data['created_at'])) {
			$data['created_at'] = AIPS_DateTime::now()->timestamp();
		}

		$result = $this->wpdb->insert($this->table_name, $data);
		if ($result) {
			$this->invalidate_logs_cache(
				isset($data['author_topic_id']) ? $data['author_topic_id'] : 0,
				'author_topic_log_created'
			);
		}
		return $result ? $this->wpdb->insert_id : false;
	}

	/**
	 * Log an approval action.
	 *
	 * @param int $author_topic_id Author topic ID.
	 * @param int $user_id User ID performing the action.
	 * @param string $notes Optional notes.
	 * @return int|false The ID of the created log or false on failure.
	 */
	public function log_approval($author_topic_id, $user_id, $notes = '') {
		return $this->create(array(
			'author_topic_id' => $author_topic_id,
			'action' => 'approved',
			'user_id' => $user_id,
			'notes' => $notes
		));
	}

	/**
	 * Log a rejection action.
	 *
	 * @param int $author_topic_id Author topic ID.
	 * @param int $user_id User ID performing the action.
	 * @param string $notes Optional notes.
	 * @return int|false The ID of the created log or false on failure.
	 */
	public function log_rejection($author_topic_id, $user_id, $notes = '') {
		return $this->create(array(
			'author_topic_id' => $author_topic_id,
			'action' => 'rejected',
			'user_id' => $user_id,
			'notes' => $notes
		));
	}

	/**
	 * Log a post generation action.
	 *
	 * @param int $author_topic_id Author topic ID.
	 * @param int $post_id WordPress post ID.
	 * @param string $metadata Optional metadata as JSON.
	 * @return int|false The ID of the created log or false on failure.
	 */
	public function log_post_generation($author_topic_id, $post_id, $metadata = '') {
		return $this->create(array(
			'author_topic_id' => $author_topic_id,
			'post_id' => $post_id,
			'action' => 'post_generated',
			'metadata' => $metadata
		));
	}

	/**
	 * Log an edit action.
	 *
	 * @param int $author_topic_id Author topic ID.
	 * @param int $user_id User ID performing the action.
	 * @param string $notes Optional notes (e.g., old title).
	 * @return int|false The ID of the created log or false on failure.
	 */
	public function log_edit($author_topic_id, $user_id, $notes = '') {
		return $this->create(array(
			'author_topic_id' => $author_topic_id,
			'action' => 'edited',
			'user_id' => $user_id,
			'notes' => $notes
		));
	}

	/**
	 * Delete all logs for the given topic IDs.
	 *
	 * @param int[] $topic_ids Array of author_topic IDs whose logs should be deleted.
	 * @return int|false Number of rows deleted, or false on failure. Returns 0 for an empty array.
	 */
	public function delete_by_topic_ids(array $topic_ids) {
		if (empty($topic_ids)) {
			return 0;
		}

		$topic_ids    = array_map('absint', $topic_ids);
		$topic_ids    = array_filter($topic_ids);

		if (empty($topic_ids)) {
			return 0;
		}

		$placeholders = implode(',', array_fill(0, count($topic_ids), '%d'));

		$result = $this->wpdb->query(
			$this->wpdb->prepare(
				"DELETE FROM {$this->table_name} WHERE author_topic_id IN ({$placeholders})",
				...$topic_ids
			)
		);

		if ($result) {
			$this->invalidate_logs_cache_for_topics($topic_ids, 'author_topic_logs_deleted');
		}

		return $result;
	}

	/**
	 * Count the number of generated posts for a specific author.
	 *
	 * More efficient than get_generated_posts_by_author() when only the count is needed,
	 * as it issues a COUNT(*) query instead of fetching all rows.
	 *
	 * @param int $author_id Author ID.
	 * @return int Number of generated posts.
	 */
	public function count_generated_posts_by_author($author_id) {
		return $this->cache_read(
			'author_topic_logs.count_generated_posts_by_author',
			array(
				'author_id' => (int) $author_id,
			),
			function() use ( $author_id ) {
				$topics_table = $this->table('aips_author_topics');

				$count = $this->wpdb->get_var($this->wpdb->prepare(
					"SELECT COUNT(*)
					FROM {$this->table_name} l
					INNER JOIN {$topics_table} t ON l.author_topic_id = t.id
					WHERE t.author_id = %d
					AND l.action = 'post_generated'
					AND l.post_id IS NOT NULL",
					$author_id
				));

				return (int) $count;
			}
		);
	}

	/**
	 * Get generated-post counts keyed by author ID.
	 *
	 * Returns an associative array of author_id => count for all authors that
	 * have at least one 'post_generated' log entry.  Used by schedule listing to
	 * show per-author stats without issuing a separate query per author.
	 *
	 * @return array<int, int> Map of author_id => post count.
	 */
	public function get_post_generation_counts_grouped_by_author() {
		return $this->cache_read(
			'author_topic_logs.get_post_generation_counts_grouped_by_author',
			array(),
			function() {
				$topics_table = $this->table('aips_author_topics');

				$results = $this->wpdb->get_results(
					"SELECT at.author_id, COUNT(*) AS cnt
					 FROM {$this->table_name} atl
					 INNER JOIN {$topics_table} at ON atl.author_topic_id = at.id
					 WHERE atl.action = 'post_generated'
					 GROUP BY at.author_id"
				);

				$counts = array();
				foreach ( $results as $row ) {
					$counts[ (int) $row->author_id ] = (int) $row->cnt;
				}

				return $counts;
			}
		);
	}

	/**
	 * Return the repository cache group for author topic log reads.
	 *
	 * @return string
	 */
	protected function repository_cache_group(): string {
		return 'aips_author_topic_logs';
	}

	/**
	 * Return the explicit repository cache policies for author topic log reads.
	 *
	 * Every cached read carries the broad `author_topic_logs` tag, so bumping it
	 * on any write invalidates all log read caches. This tag is also bumped by the
	 * existing `post_generation` / `author_topic_log` invalidation domains, so
	 * generation flows keep these reads correct as well.
	 *
	 * @return array
	 */
	protected function repository_cache_policies(): array {
		return array(
			'author_topic_logs.get_by_topic' => array(
				'tier'        => 'medium',
				'ttl'         => 300,
				'tags'        => array( 'author_topic_logs', 'author_topic_logs:topic:{author_topic_id}' ),
				'description' => 'Cache per-topic log history reads.',
			),
			'author_topic_logs.get_by_id' => array(
				'tier'        => 'medium',
				'ttl'         => 300,
				'tags'        => array( 'author_topic_logs' ),
				'cache_null'  => false,
				'description' => 'Cache single-log reads by ID.',
			),
			'author_topic_logs.count_generated_posts_by_author' => array(
				'tier'        => 'medium',
				'ttl'         => 300,
				'tags'        => array( 'author_topic_logs', 'author_topic_logs:author:{author_id}' ),
				'description' => 'Cache per-author generated-post counts.',
			),
			'author_topic_logs.get_post_generation_counts_grouped_by_author' => array(
				'tier'        => 'medium',
				'ttl'         => 300,
				'tags'        => array( 'author_topic_logs' ),
				'description' => 'Cache grouped-by-author generated-post counts.',
			),
		);
	}

	/**
	 * Invalidate log read caches after a single-topic write.
	 *
	 * @param int    $author_topic_id Topic ID, or 0 when unknown.
	 * @param string $reason Invalidation reason.
	 * @return void
	 */
	private function invalidate_logs_cache($author_topic_id, $reason) {
		$tags = array( 'author_topic_logs' );

		$author_topic_id = absint($author_topic_id);
		if ($author_topic_id > 0) {
			$tags[] = 'author_topic_logs:topic:' . $author_topic_id;
		}

		$this->invalidate_cache_tags($tags, (string) $reason);
	}

	/**
	 * Invalidate log read caches after a multi-topic delete.
	 *
	 * @param int[]  $topic_ids Topic IDs affected.
	 * @param string $reason Invalidation reason.
	 * @return void
	 */
	private function invalidate_logs_cache_for_topics(array $topic_ids, $reason) {
		$tags = array( 'author_topic_logs' );

		foreach ($topic_ids as $topic_id) {
			$topic_id = absint($topic_id);
			if ($topic_id > 0) {
				$tags[] = 'author_topic_logs:topic:' . $topic_id;
			}
		}

		$this->invalidate_cache_tags($tags, (string) $reason);
	}

	/**
	 * Returns an associative array of author_id => MAX(created_at) for all authors
	 * that have at least one post_generated log row.
	 *
	 * @return array<int, int> Map of author_id => latest created_at timestamp.
	 */
	public function get_latest_post_generation_timestamps_grouped_by_author() {
		$topics_table = $this->wpdb->prefix . 'aips_author_topics';

		$results = $this->wpdb->get_results(
			"SELECT at.author_id, MAX(atl.created_at) AS latest_ts
			 FROM {$this->table_name} atl
			 INNER JOIN {$topics_table} at ON atl.author_topic_id = at.id
			 WHERE atl.action = 'post_generated'
			 GROUP BY at.author_id"
		);

		$timestamps = array();
		foreach ( $results as $row ) {
			$timestamps[ (int) $row->author_id ] = (int) $row->latest_ts;
		}

		return $timestamps;
	}
}
