<?php
namespace AIPS\Repositories;

/**
 * Feedback Repository
 *
 * Database abstraction layer for topic feedback operations.
 * Stores approval/rejection reasons and timestamps for topic review decisions.
 *
 * @package AI_Post_Scheduler
 * @since 1.9.0
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class FeedbackRepository
 *
 * Repository pattern implementation for feedback data access.
 * Encapsulates all database operations related to topic feedback.
 */
class FeedbackRepository {
	
	/**
	 * @var string The feedback table name (with prefix)
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
		$this->table_name = $wpdb->prefix . 'aips_topic_feedback';
	}
	
	/**
	 * Get all feedback for a topic.
	 *
	 * @param int $author_topic_id Author topic ID.
	 * @return array Array of feedback objects.
	 */
	public function get_by_topic($author_topic_id) {
		return $this->wpdb->get_results($this->wpdb->prepare(
			"SELECT * FROM {$this->table_name} WHERE author_topic_id = %d ORDER BY created_at DESC",
			$author_topic_id
		));
	}
	
	/**
	 * Get all feedback for an author.
	 *
	 * @param int $author_id Author ID.
	 * @return array Array of feedback objects with topic information.
	 */
	public function get_by_author($author_id) {
		$topics_table = $this->wpdb->prefix . 'aips_author_topics';
		
		return $this->wpdb->get_results($this->wpdb->prepare(
			"SELECT f.*, t.topic_title, t.author_id 
			FROM {$this->table_name} f
			INNER JOIN {$topics_table} t ON f.author_topic_id = t.id
			WHERE t.author_id = %d
			ORDER BY f.created_at DESC",
			$author_id
		));
	}
	
	/**
	 * Get feedback by ID.
	 *
	 * @param int $id Feedback ID.
	 * @return object|null Feedback object or null if not found.
	 */
	public function get_by_id($id) {
		return $this->wpdb->get_row($this->wpdb->prepare(
			"SELECT * FROM {$this->table_name} WHERE id = %d",
			$id
		));
	}
	
	/**
	 * Create a feedback entry.
	 *
	 * @param array $data {
	 *     Feedback data.
	 *
	 *     @type int    $author_topic_id Topic ID.
	 *     @type string $action          Action type (approved/rejected).
	 *     @type int    $user_id         User who gave feedback.
	 *     @type string $reason          Feedback reason.
	 *     @type string $reason_category Reason category (duplicate/tone/irrelevant/policy/other).
	 *     @type string $source          Source of feedback (UI/automation).
	 *     @type string $notes           Additional notes.
	 * }
	 * @return int|false The ID of the created feedback or false on failure.
	 */
	public function create($data) {
		$insert_data = array(
			'author_topic_id' => $data['author_topic_id'],
			'action' => $data['action'],
			'user_id' => isset($data['user_id']) ? $data['user_id'] : null,
			'reason' => isset($data['reason']) ? sanitize_textarea_field($data['reason']) : '',
			'reason_category' => isset($data['reason_category']) ? sanitize_text_field($data['reason_category']) : 'other',
			'source' => isset($data['source']) ? sanitize_text_field($data['source']) : 'UI',
			'notes' => isset($data['notes']) ? sanitize_textarea_field($data['notes']) : '',
			'created_at' => current_time('mysql')
		);
		
		$result = $this->wpdb->insert($this->table_name, $insert_data);
		return $result ? $this->wpdb->insert_id : false;
	}
	
	/**
	 * Record approval feedback.
	 *
	 * @param int    $author_topic_id Topic ID.
	 * @param int    $user_id         User ID.
	 * @param string $reason          Approval reason.
	 * @param string $notes           Additional notes.
	 * @param string $reason_category Reason category (duplicate/tone/irrelevant/policy/other).
	 * @param string $source          Source of feedback (UI/automation).
	 * @return int|false The ID of the created feedback or false on failure.
	 */
	public function record_approval($author_topic_id, $user_id, $reason = '', $notes = '', $reason_category = 'other', $source = 'UI') {
		return $this->create(array(
			'author_topic_id' => $author_topic_id,
			'action' => 'approved',
			'user_id' => $user_id,
			'reason' => $reason,
			'notes' => $notes,
			'reason_category' => $reason_category,
			'source' => $source
		));
	}
	
	/**
	 * Record rejection feedback.
	 *
	 * @param int    $author_topic_id Topic ID.
	 * @param int    $user_id         User ID.
	 * @param string $reason          Rejection reason.
	 * @param string $notes           Additional notes.
	 * @param string $reason_category Reason category (duplicate/tone/irrelevant/policy/other).
	 * @param string $source          Source of feedback (UI/automation).
	 * @return int|false The ID of the created feedback or false on failure.
	 */
	public function record_rejection($author_topic_id, $user_id, $reason = '', $notes = '', $reason_category = 'other', $source = 'UI') {
		return $this->create(array(
			'author_topic_id' => $author_topic_id,
			'action' => 'rejected',
			'user_id' => $user_id,
			'reason' => $reason,
			'notes' => $notes,
			'reason_category' => $reason_category,
			'source' => $source
		));
	}
	
	/**
	 * Delete feedback by ID.
	 *
	 * @param int $id Feedback ID.
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
	 * Delete all feedback for a topic.
	 *
	 * @param int $author_topic_id Topic ID.
	 * @return int|false The number of rows deleted, or false on error.
	 */
	public function delete_by_topic($author_topic_id) {
		return $this->wpdb->delete(
			$this->table_name,
			array('author_topic_id' => $author_topic_id),
			array('%d')
		);
	}
	
	/**
	 * Get feedback statistics for an author.
	 *
	 * @param int $author_id Author ID.
	 * @return array Statistics array with counts.
	 */
	public function get_statistics($author_id) {
		$topics_table = $this->wpdb->prefix . 'aips_author_topics';
		
		$results = $this->wpdb->get_row($this->wpdb->prepare(
			"SELECT
				COUNT(*) as total,
				SUM(CASE WHEN f.action = 'approved' THEN 1 ELSE 0 END) as approved,
				SUM(CASE WHEN f.action = 'rejected' THEN 1 ELSE 0 END) as rejected
			FROM {$this->table_name} f
			INNER JOIN {$topics_table} t ON f.author_topic_id = t.id
			WHERE t.author_id = %d",
			$author_id
		));
		
		return array(
			'total' => (int) $results->total,
			'approved' => (int) $results->approved,
			'rejected' => (int) $results->rejected
		);
	}
	
	/**
	 * Get feedback by reason category.
	 *
	 * @param string $reason_category Reason category to filter by.
	 * @param int    $author_id       Optional. Filter by author ID.
	 * @return array Array of feedback objects.
	 */
	public function get_by_reason_category($reason_category, $author_id = null) {
		if ($author_id) {
			$topics_table = $this->wpdb->prefix . 'aips_author_topics';
			
			return $this->wpdb->get_results($this->wpdb->prepare(
				"SELECT f.*, t.topic_title, t.author_id 
				FROM {$this->table_name} f
				INNER JOIN {$topics_table} t ON f.author_topic_id = t.id
				WHERE f.reason_category = %s AND t.author_id = %d
				ORDER BY f.created_at DESC",
				$reason_category,
				$author_id
			));
		}
		
		return $this->wpdb->get_results($this->wpdb->prepare(
			"SELECT * FROM {$this->table_name} WHERE reason_category = %s ORDER BY created_at DESC",
			$reason_category
		));
	}
	
	/**
	 * Get feedback statistics by reason category.
	 *
	 * @param int $author_id Optional. Filter by author ID.
	 * @return array Array of reason category counts.
	 */
	public function get_reason_category_statistics($author_id = null) {
		if ($author_id) {
			$topics_table = $this->wpdb->prefix . 'aips_author_topics';
			
			$results = $this->wpdb->get_results($this->wpdb->prepare(
				"SELECT f.reason_category, f.action, COUNT(*) as count
				FROM {$this->table_name} f
				INNER JOIN {$topics_table} t ON f.author_topic_id = t.id
				WHERE t.author_id = %d
				GROUP BY f.reason_category, f.action",
				$author_id
			), ARRAY_A);
		} else {
			$results = $this->wpdb->get_results(
				"SELECT reason_category, action, COUNT(*) as count
				FROM {$this->table_name}
				GROUP BY reason_category, action",
				ARRAY_A
			);
		}
		
		$stats = array();
		foreach ($results as $row) {
			if (!isset($stats[$row['reason_category']])) {
				$stats[$row['reason_category']] = array('approved' => 0, 'rejected' => 0);
			}
			$stats[$row['reason_category']][$row['action']] = (int) $row['count'];
		}
		
		return $stats;
	}
}
