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

		// Ensure all inserts either succeed or fail together.
		$this->wpdb->query('START TRANSACTION');

		foreach ($topics as $topic) {
			$result = $this->create($topic);
			if (!$result) {
				$this->wpdb->query('ROLLBACK');
				return false;
			}
		}

		$this->wpdb->query('COMMIT');
		return true;
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
	 * Get approved topics for an author (for post generation).
	 *
	 * @param int $author_id Author ID.
	 * @param int $limit Optional. Maximum number of topics to return. Default 1.
	 * @return array Array of approved topic objects.
	 */
	public function get_approved_for_generation($author_id, $limit = 1) {
		return $this->wpdb->get_results($this->wpdb->prepare(
			"SELECT * FROM {$this->table_name} 
			WHERE author_id = %d 
			AND status = 'approved' 
			ORDER BY reviewed_at ASC 
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
	 * @param int $author_id Author ID.
	 * @return array Associative array of status => count.
	 */
	public function get_status_counts($author_id) {
		$results = $this->wpdb->get_results($this->wpdb->prepare(
			"SELECT status, COUNT(*) as count 
			FROM {$this->table_name} 
			WHERE author_id = %d 
			GROUP BY status",
			$author_id
		), ARRAY_A);
		
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
				ORDER BY t.reviewed_at ASC",
				'approved'
			)
		);
	}
}
