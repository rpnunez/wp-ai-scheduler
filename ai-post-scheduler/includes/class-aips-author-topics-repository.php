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

/**
 * Class AIPS_Author_Topics_Repository
 *
 * Repository pattern implementation for author topic data access.
 * Encapsulates all database operations related to author topics.
 */
class AIPS_Author_Topics_Repository {
	
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
	
	/**
	 * Get a single topic by ID.
	 *
	 * @param int $id Topic ID.
	 * @return object|null Topic object or null if not found.
	 */
	public function get_by_id($id) {
		return $this->wpdb->get_row($this->wpdb->prepare(
			"SELECT * FROM {$this->table_name} WHERE id = %d",
			$id
		));
	}
	
	/**
	 * Create a new topic.
	 *
	 * @param array $data Topic data.
	 * @return int|false The ID of the created topic or false on failure.
	 */
	public function create($data) {
		$result = $this->wpdb->insert($this->table_name, $data);
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
				isset($topic['generated_at']) ? $topic['generated_at'] : current_time('mysql')
			);
			$placeholders[] = "(%d, %s, %s, %s, %d, %s, %s)";
		}

		// If no valid topics remain after validation, do not attempt the insert.
		if (empty($placeholders)) {
			return false;
		}
		$sql = "INSERT INTO {$this->table_name} (author_id, topic_title, topic_prompt, status, score, metadata, generated_at) VALUES ";
		$sql .= implode(', ', $placeholders);

		$query = $this->wpdb->prepare($sql, $values);

		return $this->wpdb->query($query) !== false;
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
	 * @param string|nil $generated_after  Optional. ISO datetime or MySQL datetime
	 *                                     string to filter topics generated on or
	 *                                     after this timestamp. Default null.
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
			$query   .= " AND generated_at >= %s";
			$params[] = $generated_after;
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
		return $this->wpdb->update(
			$this->table_name,
			$data,
			array('id' => $id),
			null,
			array('%d')
		);
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
			'reviewed_at' => current_time('mysql')
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
		return $this->wpdb->delete(
			$this->table_name,
			array('id' => $id),
			array('%d')
		);
	}
	
	/**
	 * Delete all topics belonging to an author.
	 *
	 * @param int $author_id Author ID.
	 * @return int|false Number of rows deleted (0 if none matched), or false on failure.
	 */
	public function delete_by_author($author_id) {
		return $this->wpdb->delete(
			$this->table_name,
			array('author_id' => absint($author_id)),
			array('%d')
		);
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
		if ($after_id > 0) {
			return $this->wpdb->get_results($this->wpdb->prepare(
				"SELECT * FROM {$this->table_name}
				WHERE author_id = %d
				AND status = 'approved'
				AND id > %d
				ORDER BY id ASC
				LIMIT %d",
				$author_id,
				$after_id,
				$limit
			));
		}

		return $this->wpdb->get_results($this->wpdb->prepare(
			"SELECT * FROM {$this->table_name}
			WHERE author_id = %d
			AND status = 'approved'
			ORDER BY id ASC
			LIMIT %d",
			$author_id,
			$limit
		));
	}
	
	/**
	 * Get summary of approved topics for context (for feedback loop).
	 *
	 * @param int $author_id Author ID.
	 * @param int $limit Optional. Maximum number of topics to include. Default 20.
	 * @return array Array of approved topic titles.
	 */
	public function get_approved_summary($author_id, $limit = 20) {
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
	
	/**
	 * Get summary of rejected topics for context (for feedback loop).
	 *
	 * @param int $author_id Author ID.
	 * @param int $limit Optional. Maximum number of topics to include. Default 20.
	 * @return array Array of rejected topic titles.
	 */
	public function get_rejected_summary($author_id, $limit = 20) {
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

		// Bucket approved topics that already produced a post under
		// 'posts_generated' so the Approved tab only shows topics still
		// waiting for post creation, while keeping all totals additive.
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

	/**
	 * Get global topic counts by status across all authors.
	 *
	 * @return array Associative array of status => count.
	 */
	public function get_global_status_counts() {
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
	
	/**
	 * Get all approved topics across all authors for the generation queue.
	 *
	 * @return array Array of approved topic objects with author info.
	 */
	public function get_all_approved_for_queue() {
		$authors_table = $this->wpdb->prefix . 'aips_authors';
		
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
		$results = $this->wpdb->get_results(
			"SELECT author_id, COUNT(*) AS cnt FROM {$this->table_name} GROUP BY author_id"
		);

		$counts = array();
		foreach ( $results as $row ) {
			$counts[ (int) $row->author_id ] = (int) $row->cnt;
		}

		return $counts;
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
		$days  = max( 1, absint( $days ) );
		$start = date( 'Y-m-d', current_time( 'timestamp' ) - ( ( $days - 1 ) * DAY_IN_SECONDS ) );

		$results = $this->wpdb->get_results(
			$this->wpdb->prepare(
				"SELECT DATE(created_at) AS day, COUNT(*) AS total
				 FROM {$this->table_name}
				 WHERE created_at >= %s
				 GROUP BY DATE(created_at)
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
}


