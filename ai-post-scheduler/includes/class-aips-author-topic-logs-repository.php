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

/**
 * Class AIPS_Author_Topic_Logs_Repository
 *
 * Repository pattern implementation for author topic log data access.
 * Encapsulates all database operations related to author topic logs.
 */
class AIPS_Author_Topic_Logs_Repository {
	
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
		$this->table_name = $wpdb->prefix . 'aips_author_topic_logs';
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
	
	/**
	 * Get a single log by ID.
	 *
	 * @param int $id Log ID.
	 * @return object|null Log object or null if not found.
	 */
	public function get_by_id($id) {
		return $this->wpdb->get_row($this->wpdb->prepare(
			"SELECT * FROM {$this->table_name} WHERE id = %d",
			$id
		));
	}
	
	/**
	 * Create a new log entry.
	 *
	 * @param array $data Log data.
	 * @return int|false The ID of the created log or false on failure.
	 */
	public function create($data) {
		$result = $this->wpdb->insert($this->table_name, $data);
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

		return $this->wpdb->query(
			$this->wpdb->prepare(
				"DELETE FROM {$this->table_name} WHERE author_topic_id IN ({$placeholders})",
				...$topic_ids
			)
		);
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
		$topics_table = $this->wpdb->prefix . 'aips_author_topics';

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
}

