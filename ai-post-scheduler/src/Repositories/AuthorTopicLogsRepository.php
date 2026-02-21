<?php
namespace AIPS\Repositories;

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
 * Class AuthorTopicLogsRepository
 *
 * Repository pattern implementation for author topic log data access.
 * Encapsulates all database operations related to author topic logs.
 */
class AuthorTopicLogsRepository {
	
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
	 * Get all logs for a topic.
	 *
	 * @param int $author_topic_id Author topic ID.
	 * @return array Array of log objects.
	 */
	public function get_by_topic($author_topic_id) {
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
	 * Get logs by post ID.
	 *
	 * @param int $post_id WordPress post ID.
	 * @return array Array of log objects.
	 */
	public function get_by_post($post_id) {
		return $this->wpdb->get_results($this->wpdb->prepare(
			"SELECT * FROM {$this->table_name} WHERE post_id = %d ORDER BY created_at DESC",
			$post_id
		));
	}
	
	/**
	 * Get all generated posts for a specific author.
	 *
	 * @param int $author_id Author ID.
	 * @return array Array of log objects with post information.
	 */
	public function get_generated_posts_by_author($author_id) {
		$topics_table = $this->wpdb->prefix . 'aips_author_topics';
		
		return $this->wpdb->get_results($this->wpdb->prepare(
			"SELECT l.*, t.topic_title, t.author_id 
			FROM {$this->table_name} l
			INNER JOIN {$topics_table} t ON l.author_topic_id = t.id
			WHERE t.author_id = %d 
			AND l.action = 'post_generated'
			AND l.post_id IS NOT NULL
			ORDER BY l.created_at DESC",
			$author_id
		));
	}
}
