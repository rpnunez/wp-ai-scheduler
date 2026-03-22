<?php
/**
 * Story Budget Repository
 *
 * Persistence layer for editorial story budget items.
 *
 * @package AI_Post_Scheduler
 */

if (!defined('ABSPATH')) {
	exit;
}

class AIPS_Story_Budget_Repository {

	/**
	 * @var wpdb
	 */
	private $wpdb;

	/**
	 * @var string
	 */
	private $table_name;

	/**
	 * @var string
	 */
	private $users_table;

	public function __construct() {
		global $wpdb;
		$this->wpdb = $wpdb;
		$this->table_name = $wpdb->prefix . 'aips_story_budget';
		$this->users_table = $wpdb->users;
	}

	public static function get_statuses() {
		return array(
			'pitched' => __('Pitched', 'ai-post-scheduler'),
			'assigned' => __('Assigned', 'ai-post-scheduler'),
			'in_research' => __('In Research', 'ai-post-scheduler'),
			'drafting' => __('Drafting', 'ai-post-scheduler'),
			'in_review' => __('In Review', 'ai-post-scheduler'),
			'scheduled' => __('Scheduled', 'ai-post-scheduler'),
			'published' => __('Published', 'ai-post-scheduler'),
			'killed' => __('Killed', 'ai-post-scheduler'),
		);
	}

	public static function get_priorities() {
		return array(
			'low' => __('Low', 'ai-post-scheduler'),
			'medium' => __('Medium', 'ai-post-scheduler'),
			'high' => __('High', 'ai-post-scheduler'),
			'urgent' => __('Urgent', 'ai-post-scheduler'),
		);
	}

	public static function get_story_types() {
		return array(
			'feature' => __('Feature', 'ai-post-scheduler'),
			'analysis' => __('Analysis', 'ai-post-scheduler'),
			'breaking' => __('Breaking', 'ai-post-scheduler'),
			'explainer' => __('Explainer', 'ai-post-scheduler'),
			'opinion' => __('Opinion', 'ai-post-scheduler'),
			'newsletter' => __('Newsletter', 'ai-post-scheduler'),
			'roundup' => __('Roundup', 'ai-post-scheduler'),
			'profile' => __('Profile', 'ai-post-scheduler'),
			'interview' => __('Interview', 'ai-post-scheduler'),
			'live_blog' => __('Live Blog', 'ai-post-scheduler'),
		);
	}

	public static function get_source_types() {
		return array(
			'manual' => __('Manual Editorial Entry', 'ai-post-scheduler'),
			'research' => __('Research Library Entry', 'ai-post-scheduler'),
			'author_topic' => __('Approved Author Topic', 'ai-post-scheduler'),
		);
	}

	public function create($data) {
		$insert_data = $this->sanitize_for_write($data);
		$result = $this->wpdb->insert(
			$this->table_name,
			$insert_data,
			$this->get_formats_for_data($insert_data)
		);

		return $result ? $this->wpdb->insert_id : false;
	}

	public function update($id, $data) {
		$update_data = $this->sanitize_for_write($data);

		if (empty($update_data)) {
			return false;
		}

		return $this->wpdb->update(
			$this->table_name,
			$update_data,
			array('id' => absint($id)),
			$this->get_formats_for_data($update_data),
			array('%d')
		);
	}

	public function delete($id) {
		return $this->wpdb->delete($this->table_name, array('id' => absint($id)), array('%d'));
	}

	public function get_by_id($id) {
		$query = $this->wpdb->prepare(
			"SELECT sb.*, editor.display_name AS assigned_editor_name, writer.display_name AS assigned_writer_name
			FROM {$this->table_name} sb
			LEFT JOIN {$this->users_table} editor ON editor.ID = sb.assigned_editor_user_id
			LEFT JOIN {$this->users_table} writer ON writer.ID = sb.assigned_writer_user_id
			WHERE sb.id = %d",
			absint($id)
		);

		return $this->wpdb->get_row($query);
	}

	public function get_all($args = array()) {
		$defaults = array(
			'beat' => '',
			'assignee' => 0,
			'priority' => '',
			'status' => '',
			'publish_window_start' => '',
			'publish_window_end' => '',
			'limit' => 50,
			'offset' => 0,
		);

		$args = wp_parse_args($args, $defaults);
		$where = array('1=1');
		$prepare = array();

		if (!empty($args['beat'])) {
			$where[] = '(sb.beat = %s OR sb.desk = %s)';
			$prepare[] = $args['beat'];
			$prepare[] = $args['beat'];
		}

		if (!empty($args['assignee'])) {
			$where[] = '(sb.assigned_editor_user_id = %d OR sb.assigned_writer_user_id = %d)';
			$prepare[] = absint($args['assignee']);
			$prepare[] = absint($args['assignee']);
		}

		if (!empty($args['priority'])) {
			$where[] = 'sb.priority = %s';
			$prepare[] = $args['priority'];
		}

		if (!empty($args['status'])) {
			$where[] = 'sb.status = %s';
			$prepare[] = $args['status'];
		}

		if (!empty($args['publish_window_start'])) {
			$where[] = '(sb.publish_window_end IS NULL OR sb.publish_window_end >= %s)';
			$prepare[] = $args['publish_window_start'];
		}

		if (!empty($args['publish_window_end'])) {
			$where[] = '(sb.publish_window_start IS NULL OR sb.publish_window_start <= %s)';
			$prepare[] = $args['publish_window_end'];
		}

		$sql = "SELECT sb.*, editor.display_name AS assigned_editor_name, writer.display_name AS assigned_writer_name
			FROM {$this->table_name} sb
			LEFT JOIN {$this->users_table} editor ON editor.ID = sb.assigned_editor_user_id
			LEFT JOIN {$this->users_table} writer ON writer.ID = sb.assigned_writer_user_id
			WHERE " . implode(' AND ', $where) . "
			ORDER BY COALESCE(sb.publish_window_start, sb.due_at, sb.created_at) ASC, sb.created_at DESC
			LIMIT %d OFFSET %d";

		$prepare[] = absint($args['limit']);
		$prepare[] = absint($args['offset']);

		return $this->wpdb->get_results($this->wpdb->prepare($sql, $prepare));
	}

	public function count_all($args = array()) {
		$defaults = array(
			'beat' => '',
			'assignee' => 0,
			'priority' => '',
			'status' => '',
			'publish_window_start' => '',
			'publish_window_end' => '',
		);
		$args = wp_parse_args($args, $defaults);
		$where = array('1=1');
		$prepare = array();

		if (!empty($args['beat'])) {
			$where[] = '(beat = %s OR desk = %s)';
			$prepare[] = $args['beat'];
			$prepare[] = $args['beat'];
		}
		if (!empty($args['assignee'])) {
			$where[] = '(assigned_editor_user_id = %d OR assigned_writer_user_id = %d)';
			$prepare[] = absint($args['assignee']);
			$prepare[] = absint($args['assignee']);
		}
		if (!empty($args['priority'])) {
			$where[] = 'priority = %s';
			$prepare[] = $args['priority'];
		}
		if (!empty($args['status'])) {
			$where[] = 'status = %s';
			$prepare[] = $args['status'];
		}
		if (!empty($args['publish_window_start'])) {
			$where[] = '(publish_window_end IS NULL OR publish_window_end >= %s)';
			$prepare[] = $args['publish_window_start'];
		}
		if (!empty($args['publish_window_end'])) {
			$where[] = '(publish_window_start IS NULL OR publish_window_start <= %s)';
			$prepare[] = $args['publish_window_end'];
		}

		$sql = "SELECT COUNT(*) FROM {$this->table_name} WHERE " . implode(' AND ', $where);
		if (!empty($prepare)) {
			$sql = $this->wpdb->prepare($sql, $prepare);
		}

		return (int) $this->wpdb->get_var($sql);
	}

	public function get_beats() {
		$results = $this->wpdb->get_col("SELECT DISTINCT beat FROM {$this->table_name} WHERE beat IS NOT NULL AND beat <> '' ORDER BY beat ASC");
		return array_values(array_filter(array_map('strval', (array) $results)));
	}

	public function get_dashboard_window($hours = 72, $limit = 8) {
		$hours = absint($hours);
		$limit = absint($limit);

		$query = $this->wpdb->prepare(
			"SELECT sb.*, editor.display_name AS assigned_editor_name, writer.display_name AS assigned_writer_name,
			COALESCE(sb.publish_window_start, sb.due_at) AS planning_date
			FROM {$this->table_name} sb
			LEFT JOIN {$this->users_table} editor ON editor.ID = sb.assigned_editor_user_id
			LEFT JOIN {$this->users_table} writer ON writer.ID = sb.assigned_writer_user_id
			WHERE sb.status NOT IN ('published', 'killed')
			AND COALESCE(sb.publish_window_start, sb.due_at) IS NOT NULL
			AND COALESCE(sb.publish_window_start, sb.due_at) BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL %d HOUR)
			ORDER BY planning_date ASC
			LIMIT %d",
			$hours,
			$limit
		);

		return $this->wpdb->get_results($query);
	}

	public function get_stats() {
		$results = $this->wpdb->get_results(
			"SELECT status, COUNT(*) AS count FROM {$this->table_name} GROUP BY status",
			ARRAY_A
		);

		$stats = array(
			'total' => 0,
			'pitched' => 0,
			'assigned' => 0,
			'in_research' => 0,
			'drafting' => 0,
			'in_review' => 0,
			'scheduled' => 0,
			'published' => 0,
			'killed' => 0,
		);

		foreach ($results as $row) {
			$status = $row['status'];
			$count = (int) $row['count'];
			$stats['total'] += $count;
			if (isset($stats[$status])) {
				$stats[$status] = $count;
			}
		}

		return $stats;
	}

	private function sanitize_for_write($data) {
		$allowed_statuses = array_keys(self::get_statuses());
		$allowed_priorities = array_keys(self::get_priorities());
		$allowed_story_types = array_keys(self::get_story_types());
		$allowed_source_types = array_keys(self::get_source_types());

		$sanitized = array();

		if (isset($data['title'])) {
			$sanitized['title'] = sanitize_text_field($data['title']);
		}
		if (isset($data['beat'])) {
			$sanitized['beat'] = sanitize_text_field($data['beat']);
		}
		if (isset($data['desk'])) {
			$sanitized['desk'] = sanitize_text_field($data['desk']);
		}
		if (isset($data['story_type'])) {
			$story_type = sanitize_key($data['story_type']);
			$sanitized['story_type'] = in_array($story_type, $allowed_story_types, true) ? $story_type : 'feature';
		}
		if (isset($data['priority'])) {
			$priority = sanitize_key($data['priority']);
			$sanitized['priority'] = in_array($priority, $allowed_priorities, true) ? $priority : 'medium';
		}
		if (array_key_exists('assigned_editor_user_id', $data)) {
			$sanitized['assigned_editor_user_id'] = !empty($data['assigned_editor_user_id']) ? absint($data['assigned_editor_user_id']) : null;
		}
		if (array_key_exists('assigned_writer_user_id', $data)) {
			$sanitized['assigned_writer_user_id'] = !empty($data['assigned_writer_user_id']) ? absint($data['assigned_writer_user_id']) : null;
		}
		if (array_key_exists('due_at', $data)) {
			$sanitized['due_at'] = $this->normalize_datetime($data['due_at']);
		}
		if (array_key_exists('publish_window_start', $data)) {
			$sanitized['publish_window_start'] = $this->normalize_datetime($data['publish_window_start']);
		}
		if (array_key_exists('publish_window_end', $data)) {
			$sanitized['publish_window_end'] = $this->normalize_datetime($data['publish_window_end']);
		}
		if (array_key_exists('source_topic_id', $data)) {
			$sanitized['source_topic_id'] = !empty($data['source_topic_id']) ? absint($data['source_topic_id']) : null;
		}
		if (array_key_exists('source_research_id', $data)) {
			$sanitized['source_research_id'] = !empty($data['source_research_id']) ? absint($data['source_research_id']) : null;
		}
		if (isset($data['source_type'])) {
			$source_type = sanitize_key($data['source_type']);
			$sanitized['source_type'] = in_array($source_type, $allowed_source_types, true) ? $source_type : 'manual';
		}
		if (isset($data['status'])) {
			$status = sanitize_key($data['status']);
			$sanitized['status'] = in_array($status, $allowed_statuses, true) ? $status : 'pitched';
		}
		if (isset($data['notes'])) {
			$sanitized['notes'] = sanitize_textarea_field($data['notes']);
		}

		return $sanitized;
	}

	private function normalize_datetime($value) {
		if (empty($value)) {
			return null;
		}

		$value = is_string($value) ? trim($value) : '';
		if (empty($value)) {
			return null;
		}

		$value = str_replace('T', ' ', $value);
		if (strlen($value) === 16) {
			$value .= ':00';
		}

		$timestamp = strtotime($value);
		if (false === $timestamp) {
			return null;
		}

		return gmdate('Y-m-d H:i:s', $timestamp);
	}

	private function get_formats_for_data($data) {
		$format_map = array(
			'title' => '%s',
			'beat' => '%s',
			'desk' => '%s',
			'story_type' => '%s',
			'priority' => '%s',
			'assigned_editor_user_id' => '%d',
			'assigned_writer_user_id' => '%d',
			'due_at' => '%s',
			'publish_window_start' => '%s',
			'publish_window_end' => '%s',
			'source_topic_id' => '%d',
			'source_research_id' => '%d',
			'source_type' => '%s',
			'status' => '%s',
			'notes' => '%s',
		);

		$formats = array();
		foreach ($data as $key => $value) {
			if (isset($format_map[$key])) {
				$formats[] = $format_map[$key];
			}
		}

		return $formats;
	}
}
