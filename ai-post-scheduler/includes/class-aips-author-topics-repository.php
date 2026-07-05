<?php
/**
 * Author Topics Repository
 *
 * Database abstraction layer for author topic operations.
 * Provides a clean interface for CRUD operations on the author_topics table.
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
 * Class AIPS_Author_Topics_Repository
 *
 * Repository pattern implementation for author topic data access.
 * Encapsulates all database operations related to author topics.
 */
class AIPS_Author_Topics_Repository {
	use AIPS_Cacheable_Repository;
	
	/**
	 * @var string The author_topics table name (with prefix)
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
		$this->table_name = $wpdb->prefix . 'aips_author_topics';
	}
	
	/**
	 * Get all topics for an author.
	 *
	 * @param int $author_id Author ID.
	 * @param string $status Optional. Filter by status (pending, approved, rejected). Default null (all).
	 * @return array Array of topic objects.
	 */
	public function get_by_author($author_id, $status = null) {
		return $this->cache_read(
			'author_topics.get_by_author',
			array(
				'author_id' => absint( $author_id ),
				'status'    => null !== $status ? (string) $status : '',
			),
			function() use ( $author_id, $status ) {
				if ($status) {
					return $this->wpdb->get_results($this->wpdb->prepare(
						"SELECT * FROM {$this->table_name} WHERE author_id = %d AND status = %s ORDER BY generated_at DESC",
						$author_id,
						$status
					));
				}
				return $this->wpdb->get_results($this->wpdb->prepare(
					"SELECT * FROM {$this->table_name} WHERE author_id = %d ORDER BY generated_at DESC",
					$author_id
				));
			}
		);
	}
	
	/**
	 * Get a single topic by ID.
	 *
	 * @param int $id Topic ID.
	 * @return object|null Topic object or null if not found.
	 */
	public function get_by_id($id) {
		return $this->cache_read(
			'author_topics.get_by_id',
			array(
				'topic_id' => absint( $id ),
			),
			function() use ( $id ) {
				$topic = $this->wpdb->get_row($this->wpdb->prepare(
					"SELECT * FROM {$this->table_name} WHERE id = %d",
					$id
				));

				return $topic;
			}
		);
	}

	/**
	 * Fetch multiple rows by ID in one query.
	 *
	 * Uncached raw read: results feed per-request memo caches in callers
	 * (e.g. AIPS_Generated_Posts_Controller), so a persistent-cache round
	 * trip per ID would cost more than the query it saves.
	 *
	 * @param array $ids Row IDs.
	 * @return array<int, object> Rows keyed by id; missing ids omitted.
	 */
	public function get_by_ids( array $ids ) {
		$ids = array_values( array_filter( array_unique( array_map( 'absint', $ids ) ) ) );
		if ( empty( $ids ) ) {
			return array();
		}

		$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $this->wpdb->get_results( $this->wpdb->prepare(
			"SELECT * FROM {$this->table_name} WHERE id IN ({$placeholders})",
			$ids
		) );

		$map = array();
		foreach ( (array) $rows as $row ) {
			$map[ (int) $row->id ] = $row;
		}

		return $map;
	}

	/**
	 * Create a new topic.
	 *
	 * @param array $data Topic data.
	 * @return int|false The ID of the created topic or false on failure.
	 */
	public function create($data) {
		if (!isset($data['generated_at'])) {
			$data['generated_at'] = AIPS_DateTime::now()->timestamp();
		}

		$result = $this->wpdb->insert($this->table_name, $data);
		if ( $result ) {
			$this->invalidate_cache_domain(
				'author_topic',
				array(
					'author_id' => isset( $data['author_id'] ) ? absint( $data['author_id'] ) : 0,
					'topic_id'  => (int) $this->wpdb->insert_id,
				),
				'author_topic_created'
			);
		}
		return $result ? $this->wpdb->insert_id : false;
	}
	
	/**
	 * Create multiple topics at once.
	 *
	 * @param array $topics Array of topic data arrays.
	 * @return bool True on success, false on failure.
	 */
	public function create_bulk($topics) {
		if (empty($topics)) {
			return false;
		}

		$values = array();
		$placeholders = array();

		foreach ($topics as $topic) {
			// Validate required fields before including in bulk insert.
			if (
				!isset($topic['author_id']) ||
				!isset($topic['topic_title']) ||
				!$topic['author_id'] ||
				$topic['author_id'] <= 0 ||
				'' === trim((string) $topic['topic_title'])
			) {
				// Skip topics that are missing required fields.
				continue;
			}

			array_push(
				$values,
				(int) $topic['author_id'],
				$topic['topic_title'],
				isset($topic['topic_prompt']) ? $topic['topic_prompt'] : '',
				isset($topic['status']) ? $topic['status'] : 'pending',
				isset($topic['score']) ? (int) $topic['score'] : 50,
				isset($topic['metadata']) ? $topic['metadata'] : '',
				isset($topic['generated_at']) ? absint($topic['generated_at']) : AIPS_DateTime::now()->timestamp()
			);
			$placeholders[] = "(%d, %s, %s, %s, %d, %s, %d)";
		}

		// If no valid topics remain after validation, do not attempt the insert.
		if (empty($placeholders)) {
			return false;
		}
		$sql = "INSERT INTO {$this->table_name} (author_id, topic_title, topic_prompt, status, score, metadata, generated_at) VALUES ";
		$sql .= implode(', ', $placeholders);

		$query = $this->wpdb->prepare($sql, $values);

		$result = $this->wpdb->query($query) !== false;
		if ( $result ) {
			$author_ids = array();
			foreach ( $topics as $topic ) {
				if (isset( $topic['author_id'] ) && absint( $topic['author_id'] ) > 0) {
					$author_ids[] = absint( $topic['author_id'] );
				}
			}

			foreach ( array_unique( $author_ids ) as $author_id ) {
				$this->invalidate_cache_domain(
					'author_topic',
					array(
						'author_id' => $author_id,
					),
					'author_topic_bulk_created'
				);
			}
		}

		return $result;
	}

	/**
	 * Get latest topics for an author.
	 *
	 * This method can optionally be constrained to topics generated after a
	 * specific timestamp or to a specific set of topic titles. This allows
	 * callers (e.g. bulk insert operations) to reliably retrieve only the
	 * topics created in a particular batch, even in concurrent environments.
	 *
	 * @param int        $author_id        Author ID.
	 * @param int        $limit            Number of topics to retrieve.
	 * @param int|null   $generated_after  Optional. Timestamp used as a lower bound
	 *                                     for generated_at. Default null.
	 * @param array|null $titles           Optional. Array of topic_title strings
	 *                                     to limit results to. If provided and not
	 *                                     empty, this takes precedence over
	 *                                     $generated_after. Default null.
	 * @return array Array of topic objects.
	 */
	public function get_latest_by_author( $author_id, $limit, $generated_after = null, $titles = null ) {
		$query   = "SELECT * FROM {$this->table_name} WHERE author_id = %d";
		$params  = array( $author_id );

		// If specific titles are provided, restrict results to those titles.
		if ( is_array( $titles ) && ! empty( $titles ) ) {
			$placeholders = implode( ',', array_fill( 0, count( $titles ), '%s' ) );
			$query       .= " AND topic_title IN ($placeholders)";
			$params       = array_merge( $params, $titles );
		} elseif ( null !== $generated_after ) {
			// Otherwise, if a lower-bound timestamp is provided, use it.
			$query   .= " AND generated_at >= %d";
			$params[] = absint($generated_after);
		}

		$query   .= " ORDER BY id DESC LIMIT %d";
		$params[] = (int) $limit;

		return $this->wpdb->get_results( $this->wpdb->prepare( $query, $params ) );
	}
	
	/**
	 * Update a topic.
	 *
	 * @param int $id Topic ID.
	 * @param array $data Topic data to update.
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
		if ( false !== $result ) {
			$this->invalidate_author_topic_cache_by_id( $id, 'author_topic_updated' );
		}

		return $result;
	}
	
	/**
	 * Update topic status.
	 *
	 * @param int $id Topic ID.
	 * @param string $status New status.
	 * @param int $user_id User ID performing the action.
	 * @return int|false The number of rows updated, or false on error.
	 */
	public function update_status($id, $status, $user_id = null) {
		$data = array(
			'status' => $status,
			'reviewed_at' => AIPS_DateTime::now()->timestamp()
		);
		
		if ($user_id) {
			$data['reviewed_by'] = $user_id;
		}
		
		return $this->update($id, $data);
	}
	
	/**
	 * Delete a topic.
	 *
	 * @param int $id Topic ID.
	 * @return int|false The number of rows deleted, or false on error.
	 */
	public function delete($id) {
		$context = $this->author_topic_context_by_id( $id );
		$result = $this->wpdb->delete(
			$this->table_name,
			array('id' => $id),
			array('%d')
		);
		if ( false !== $result ) {
			$this->invalidate_cache_domain(
				'author_topic',
				$context,
				'author_topic_deleted'
			);
		}

		return $result;
	}
	
	/**
	 * Delete all topics belonging to an author.
	 *
	 * @param int $author_id Author ID.
	 * @return int|false Number of rows deleted (0 if none matched), or false on failure.
	 */
	public function delete_by_author($author_id) {
		$result = $this->wpdb->delete(
			$this->table_name,
			array('author_id' => absint($author_id)),
			array('%d')
		);
		if ( false !== $result ) {
			$this->invalidate_cache_domain(
				'author_topic',
				array(
					'author_id' => absint( $author_id ),
				),
				'author_topic_deleted_by_author'
			);
		}

		return $result;
	}

	/**
	 * Get approved topics for an author (for post generation).
	 *
	 * @param int $author_id Author ID.
	 * @param int $limit     Optional. Maximum number of topics to return. Default 1.
	 * @param int $after_id  Optional. Return topics with ID greater than this value. Default 0.
	 * @return array Array of approved topic objects.
	 */
	public function get_approved_for_generation($author_id, $limit = 1, $after_id = 0) {
		$logs_table = $this->wpdb->prefix . 'aips_author_topic_logs';

		return $this->cache_read(
			'author_topics.get_approved_for_generation',
			array(
				'author_id' => absint( $author_id ),
				'limit'     => (int) $limit,
				'after_id'  => (int) $after_id,
			),
			function() use ( $author_id, $limit, $after_id, $logs_table ) {
				if ($after_id > 0) {
					return $this->wpdb->get_results($this->wpdb->prepare(
						"SELECT t.* FROM {$this->table_name} t
						LEFT JOIN {$logs_table} l
							ON l.author_topic_id = t.id
							AND l.action = 'post_generated'
							AND l.post_id IS NOT NULL
						WHERE t.author_id = %d
						AND t.status = 'approved'
						AND l.id IS NULL
						AND t.id > %d
						ORDER BY t.id ASC
						LIMIT %d",
						$author_id,
						$after_id,
						$limit
					));
				}

				return $this->wpdb->get_results($this->wpdb->prepare(
					"SELECT t.* FROM {$this->table_name} t
					LEFT JOIN {$logs_table} l
						ON l.author_topic_id = t.id
						AND l.action = 'post_generated'
						AND l.post_id IS NOT NULL
					WHERE t.author_id = %d
					AND t.status = 'approved'
					AND l.id IS NULL
					ORDER BY t.id ASC
					LIMIT %d",
					$author_id,
					$limit
				));
			},
			array(
				'queue_sensitive' => true,
			)
		);
	}
	
	/**
	 * Get summary of approved topics for context (for feedback loop).
	 *
	 * @param int $author_id Author ID.
	 * @param int $limit Optional. Maximum number of topics to include. Default 20.
	 * @return array Array of approved topic titles.
	 */
	public function get_approved_summary($author_id, $limit = 20) {
		return $this->cache_read(
			'author_topics.get_approved_summary',
			array(
				'author_id' => absint( $author_id ),
				'limit'     => (int) $limit,
			),
			function() use ( $author_id, $limit ) {
				$results = $this->wpdb->get_col($this->wpdb->prepare(
					"SELECT topic_title FROM {$this->table_name}
					WHERE author_id = %d
					AND status = 'approved'
					ORDER BY reviewed_at DESC
					LIMIT %d",
					$author_id,
					$limit
				));
				return $results ? $results : array();
			}
		);
	}
	
	/**
	 * Get summary of rejected topics for context (for feedback loop).
	 *
	 * @param int $author_id Author ID.
	 * @param int $limit Optional. Maximum number of topics to include. Default 20.
	 * @return array Array of rejected topic titles.
	 */
	public function get_rejected_summary($author_id, $limit = 20) {
		return $this->cache_read(
			'author_topics.get_rejected_summary',
			array(
				'author_id' => absint( $author_id ),
				'limit'     => (int) $limit,
			),
			function() use ( $author_id, $limit ) {
				$results = $this->wpdb->get_col($this->wpdb->prepare(
					"SELECT topic_title FROM {$this->table_name}
					WHERE author_id = %d
					AND status = 'rejected'
					ORDER BY reviewed_at DESC
					LIMIT %d",
					$author_id,
					$limit
				));
				return $results ? $results : array();
			}
		);
	}
	
	/**
	 * Get topic counts by status for an author.
	 *
	 * Returns counts for each status bucket:
	 * - pending:         topics awaiting review
	 * - approved:        approved topics that have not yet produced a post
	 * - rejected:        rejected topics
	 * - posts_generated: approved topics that have at least one generated post
	 *
	 * The four buckets are mutually exclusive and exhaustive, so
	 * pending + approved + rejected + posts_generated == total topics for
	 * the author.
	 *
	 * @param int $author_id Author ID.
	 * @return array Associative array of bucket => count.
	 */
	public function get_status_counts($author_id) {
		$logs_table = $this->wpdb->prefix . 'aips_author_topic_logs';

		return $this->cache_read(
			'author_topics.get_status_counts',
			array(
				'author_id' => absint( $author_id ),
			),
			function() use ( $author_id, $logs_table ) {
				$results = $this->wpdb->get_results(
					$this->wpdb->prepare(
						"SELECT
							CASE
								WHEN t.status = 'approved' AND l.id IS NOT NULL THEN 'posts_generated'
								ELSE t.status
							END AS bucket,
							COUNT(DISTINCT t.id) AS count
						FROM {$this->table_name} t
						LEFT JOIN {$logs_table} l
							ON l.author_topic_id = t.id
							AND l.action = 'post_generated'
							AND l.post_id IS NOT NULL
						WHERE t.author_id = %d
						GROUP BY bucket",
						$author_id
					),
					ARRAY_A
				);

				$counts = array(
					'pending'         => 0,
					'approved'        => 0,
					'rejected'        => 0,
					'posts_generated' => 0,
				);

				foreach ($results as $row) {
					if (array_key_exists($row['bucket'], $counts)) {
						$counts[$row['bucket']] = (int) $row['count'];
					}
				}

				return $counts;
			}
		);
	}

	/**
	 * Get global topic counts by status across all authors.
	 *
	 * @return array Associative array of status => count.
	 */
	public function get_global_status_counts() {
		return $this->cache_read(
			'author_topics.get_global_status_counts',
			array(),
			function() {
				$results = $this->wpdb->get_results(
					"SELECT status, COUNT(*) as count
					FROM {$this->table_name}
					GROUP BY status",
					ARRAY_A
				);

				$counts = array(
					'pending' => 0,
					'approved' => 0,
					'rejected' => 0
				);

				foreach ($results as $row) {
					$counts[$row['status']] = (int) $row['count'];
				}

				return $counts;
			}
		);
	}
	
	/**
	 * Get all approved topics across all authors for the generation queue.
	 *
	 * @return array Array of approved topic objects with author info.
	 */
	public function get_all_approved_for_queue() {
		$authors_table = $this->wpdb->prefix . 'aips_authors';
		
		return $this->cache_read(
			'author_topics.get_all_approved_for_queue',
			array(),
			function() use ( $authors_table ) {
				return $this->wpdb->get_results(
					$this->wpdb->prepare(
						"SELECT t.*, a.name as author_name, a.field_niche
						FROM {$this->table_name} t
						INNER JOIN {$authors_table} a ON t.author_id = a.id
						WHERE t.status = %s
						ORDER BY t.score DESC, t.reviewed_at ASC",
						'approved'
					)
				);
			},
			array(
				'queue_sensitive' => true,
			)
		);
	}

	/**
	 * Get total topic counts keyed by author ID.
	 *
	 * Returns an associative array of author_id => count for all authors that have
	 * at least one topic row, used by schedule listing to show per-author stats
	 * without running individual COUNT queries in a loop.
	 *
	 * @return array<int, int> Map of author_id => topic count.
	 */
	public function get_counts_grouped_by_author() {
		return $this->cache_read(
			'author_topics.get_counts_grouped_by_author',
			array(),
			function() {
				$results = $this->wpdb->get_results(
					"SELECT author_id, COUNT(*) AS cnt FROM {$this->table_name} GROUP BY author_id"
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
	 * Get per-day topic-creation counts for the last N days.
	 *
	 * Returns an array keyed by ISO date string (Y-m-d) with an integer count.
	 * Days with no records are omitted; callers should fill gaps as needed.
	 *
	 * @param int $days Number of calendar days to look back (inclusive today). Default 14.
	 * @return array<string, int>
	 */
	public function get_daily_topic_counts( $days = 14 ) {
		$days      = max( 1, absint( $days ) );
		$start_day = AIPS_DateTime::now()->advance( '-' . ( $days - 1 ) . ' days' )->format( 'Y-m-d' );
		$start     = AIPS_DateTime::fromDate( $start_day )->timestamp();

		return $this->cache_read(
			'author_topics.get_daily_topic_counts',
			array(
				'days'  => $days,
				'start' => (int) $start,
			),
			function() use ( $start ) {
				$results = $this->wpdb->get_results(
					$this->wpdb->prepare(
						"SELECT DATE(FROM_UNIXTIME(generated_at)) AS day, COUNT(*) AS total
						 FROM {$this->table_name}
						 WHERE generated_at >= %d
						 GROUP BY DATE(FROM_UNIXTIME(generated_at))
						 ORDER BY day ASC",
						$start
					)
				);

				$data = array();
				foreach ( $results as $row ) {
					$data[ $row->day ] = (int) $row->total;
				}

				return $data;
			}
		);
	}

	/**
	 * Return the repository cache group for author-topic reads.
	 *
	 * @return string
	 */
	protected function repository_cache_group(): string {
		return 'aips_author_topics';
	}

	/**
	 * Return the explicit repository cache policies for author-topic reads.
	 *
	 * @return array
	 */
	protected function repository_cache_policies(): array {
		return array(
			'author_topics.get_by_author'             => array(
				'tier'        => 'medium',
				'description' => 'Cache author-topic lists for admin review views.',
			),
			'author_topics.get_by_id'                 => array(
				'tier'        => 'medium',
				'cache_null'  => false,
				'description' => 'Cache single author-topic reads by ID.',
			),
			'author_topics.get_approved_summary'      => array(
				'tier'        => 'medium',
				'description' => 'Cache approved-topic summaries for prompt feedback.',
			),
			'author_topics.get_rejected_summary'      => array(
				'tier'        => 'medium',
				'description' => 'Cache rejected-topic summaries for prompt feedback.',
			),
			'author_topics.get_status_counts'         => array(
				'tier'        => 'medium',
				'description' => 'Cache per-author topic status counts.',
			),
			'author_topics.get_global_status_counts'  => array(
				'tier'        => 'medium',
				'description' => 'Cache global topic status counts.',
			),
			'author_topics.get_counts_grouped_by_author' => array(
				'tier'        => 'medium',
				'description' => 'Cache author-topic counts grouped by author.',
			),
			'author_topics.get_daily_topic_counts'    => array(
				'tier'        => 'medium',
				'description' => 'Cache daily author-topic count aggregates.',
			),
			'author_topics.get_approved_for_generation' => array(
				'tier'        => 'none',
				'bypass_cron' => true,
				'description' => 'Leave generation-sensitive approved-topic reads uncached.',
			),
			'author_topics.get_all_approved_for_queue' => array(
				'tier'        => 'none',
				'bypass_cron' => true,
				'description' => 'Leave queue-sensitive approved-topic reads uncached.',
			),
		);
	}

	/**
	 * Invalidate author-topic caches using topic context when available.
	 *
	 * @param int    $topic_id Topic ID.
	 * @param string $reason Invalidation reason.
	 * @return void
	 */
	private function invalidate_author_topic_cache_by_id( $topic_id, $reason ) {
		$this->invalidate_cache_domain(
			'author_topic',
			$this->author_topic_context_by_id( $topic_id ),
			(string) $reason
		);
	}

	/**
	 * Resolve minimal author-topic invalidation context by topic ID.
	 *
	 * @param int $topic_id Topic ID.
	 * @return array
	 */
	private function author_topic_context_by_id( $topic_id ) {
		$topic = $this->wpdb->get_row(
			$this->wpdb->prepare(
				"SELECT id, author_id FROM {$this->table_name} WHERE id = %d",
				$topic_id
			)
		);

		return array(
			'topic_id'  => absint( $topic_id ),
			'author_id' => $topic && isset( $topic->author_id ) ? absint( $topic->author_id ) : 0,
		);
	}
}
