<?php
/**
 * Activity Repository
 *
 * Database abstraction layer for activity tracking operations.
 * Provides a clean interface for logging and retrieving activity events.
 *
 * @package AI_Post_Scheduler
 * @since 1.7.0
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Activity_Repository
 *
 * Repository pattern implementation for activity data access.
 * Encapsulates all database operations related to activity logging.
 */
class AIPS_Activity_Repository {
	
	/**
	 * @var string The activity table name (with prefix)
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
		$this->table_name = $wpdb->prefix . 'aips_activity';
	}
	
	/**
	 * Create a new activity record.
	 *
	 * @param array $data {
	 *     Activity data.
	 *
	 *     @type string $event_type    Type of event (post_published, post_draft, schedule_failed, schedule_completed).
	 *     @type string $event_status  Status of event (success, failed, draft).
	 *     @type int    $schedule_id   Optional. Schedule ID if related to a schedule.
	 *     @type int    $post_id       Optional. Post ID if a post was created.
	 *     @type int    $template_id   Optional. Template ID used.
	 *     @type string $message       Optional. Human-readable message.
	 *     @type array  $metadata      Optional. Additional metadata as array (will be JSON encoded).
	 * }
	 * @return int|false The activity ID on success, false on failure.
	 */
	public function create($data) {
		$defaults = array(
			'event_type' => '',
			'event_status' => '',
			'schedule_id' => null,
			'post_id' => null,
			'template_id' => null,
			'message' => '',
			'metadata' => null,
		);
		
		$data = wp_parse_args($data, $defaults);
		
		// JSON encode metadata if it's an array
		if (is_array($data['metadata'])) {
			$data['metadata'] = wp_json_encode($data['metadata']);
		}
		
		$result = $this->wpdb->insert(
			$this->table_name,
			array(
				'event_type' => $data['event_type'],
				'event_status' => $data['event_status'],
				'schedule_id' => $data['schedule_id'],
				'post_id' => $data['post_id'],
				'template_id' => $data['template_id'],
				'message' => $data['message'],
				'metadata' => $data['metadata'],
			),
			array('%s', '%s', '%d', '%d', '%d', '%s', '%s')
		);
		
		return $result ? $this->wpdb->insert_id : false;
	}
	
	/**
	 * Get recent activity with optional filtering.
	 *
	 * @param array $args {
	 *     Optional. Query arguments.
	 *
	 *     @type int    $limit        Number of items to return. Default 50.
	 *     @type int    $offset       Number of items to skip. Default 0.
	 *     @type string $event_type   Filter by event type. Default empty.
	 *     @type string $event_status Filter by event status. Default empty.
	 * }
	 * @return array Array of activity objects.
	 */
	public function get_recent($args = array()) {
		$defaults = array(
			'limit' => 50,
			'offset' => 0,
			'event_type' => '',
			'event_status' => '',
		);
		
		$args = wp_parse_args($args, $defaults);
		
		$where_clauses = array("1=1");
		$where_args = array();
		
		if (!empty($args['event_type'])) {
			$where_clauses[] = "event_type = %s";
			$where_args[] = $args['event_type'];
		}
		
		if (!empty($args['event_status'])) {
			$where_clauses[] = "event_status = %s";
			$where_args[] = $args['event_status'];
		}
		
		$where_sql = implode(' AND ', $where_clauses);
		$where_args[] = $args['limit'];
		$where_args[] = $args['offset'];
		
		$sql = "SELECT * FROM {$this->table_name} WHERE $where_sql ORDER BY created_at DESC LIMIT %d OFFSET %d";
		
		if (empty($where_args)) {
			return $this->wpdb->get_results($sql);
		}
		
		return $this->wpdb->get_results($this->wpdb->prepare($sql, $where_args));
	}
	
	/**
	 * Get activity by event type.
	 *
	 * @param string $event_type Event type to filter by.
	 * @param int    $limit      Number of items to return.
	 * @return array Array of activity objects.
	 */
	public function get_by_type($event_type, $limit = 20) {
		return $this->get_recent(array(
			'event_type' => $event_type,
			'limit' => $limit,
		));
	}
	
	/**
	 * Get failed schedule activities.
	 *
	 * @param int $limit Number of items to return.
	 * @return array Array of activity objects for failed schedules.
	 */
	public function get_failed_schedules($limit = 20) {
		return $this->get_recent(array(
			'event_type' => 'schedule_failed',
			'limit' => $limit,
		));
	}
	
	/**
	 * Get draft post activities.
	 *
	 * @param int $limit Number of items to return.
	 * @return array Array of activity objects for draft posts.
	 */
	public function get_draft_posts($limit = 20) {
		return $this->get_recent(array(
			'event_status' => 'draft',
			'limit' => $limit,
		));
	}
	
	/**
	 * Get activity count by type.
	 *
	 * @param string $event_type Optional. Event type to count. Default empty (all).
	 * @return int Activity count.
	 */
	public function get_count($event_type = '') {
		if (empty($event_type)) {
			return (int) $this->wpdb->get_var("SELECT COUNT(*) FROM {$this->table_name}");
		}
		
		return (int) $this->wpdb->get_var($this->wpdb->prepare(
			"SELECT COUNT(*) FROM {$this->table_name} WHERE event_type = %s",
			$event_type
		));
	}
	
	/**
	 * Delete old activity records.
	 *
	 * @param int $days Number of days to keep. Records older than this will be deleted.
	 * @return int|false Number of rows deleted, or false on error.
	 */
	public function delete_old_records($days = 30) {
		$date = date('Y-m-d H:i:s', strtotime("-{$days} days"));
		
		return $this->wpdb->query($this->wpdb->prepare(
			"DELETE FROM {$this->table_name} WHERE created_at < %s",
			$date
		));
	}
}
