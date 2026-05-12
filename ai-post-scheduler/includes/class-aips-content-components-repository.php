<?php
/**
 * Content Components Repository
 *
 * @package AI_Post_Scheduler
 * @since 2.7.0
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Content_Components_Repository
 */
class AIPS_Content_Components_Repository {

	/**
	 * @var string
	 */
	private $table_name;

	/**
	 * @var wpdb
	 */
	private $wpdb;

	/**
	 * Initialize repository.
	 */
	public function __construct() {
		global $wpdb;
		$this->wpdb = $wpdb;
		$this->table_name = $wpdb->prefix . 'aips_content_components';
	}

	/**
	 * Get all content components.
	 *
	 * @param bool        $active_only Whether to return active records only.
	 * @param string|null $qa_status Optional QA status filter.
	 * @return array
	 */
	public function get_all($active_only = false, $qa_status = null) {
		$where = array();
		$args  = array();

		if ($active_only) {
			$where[] = 'is_active = %d';
			$args[]  = 1;
		}

		if (is_string($qa_status) && $qa_status !== '') {
			$where[] = 'qa_status = %s';
			$args[]  = $qa_status;
		}

		$sql = "SELECT * FROM {$this->table_name}";
		if (!empty($where)) {
			$sql .= ' WHERE ' . implode(' AND ', $where);
		}
		$sql .= ' ORDER BY updated_at DESC, id DESC';

		if (!empty($args)) {
			return $this->wpdb->get_results($this->wpdb->prepare($sql, $args));
		}

		return $this->wpdb->get_results($sql);
	}

	/**
	 * Get content component by ID.
	 *
	 * @param int $id Component ID.
	 * @return object|null
	 */
	public function get_by_id($id) {
		return $this->wpdb->get_row(
			$this->wpdb->prepare(
				"SELECT * FROM {$this->table_name} WHERE id = %d",
				absint($id)
			)
		);
	}

	/**
	 * Create content component.
	 *
	 * @param array $data Component data.
	 * @return int|false
	 */
	public function create($data) {
		$now = AIPS_DateTime::now()->timestamp();
		$title = isset($data['title']) ? sanitize_text_field($data['title']) : '';
		$content = isset($data['content']) ? wp_kses_post($data['content']) : '';

		$insert_data = array(
			'title'          => $title,
			'slug'           => isset($data['slug']) ? sanitize_title($data['slug']) : sanitize_title($title),
			'description'    => isset($data['description']) ? sanitize_textarea_field($data['description']) : '',
			'status'         => !empty($data['is_active']) ? 'active' : 'draft',
			'component_type' => isset($data['component_type']) ? sanitize_key($data['component_type']) : 'custom',
			'content_mode'   => isset($data['content_mode']) ? sanitize_key($data['content_mode']) : 'html',
			'content'        => $content,
			'content_payload'=> isset($data['content_payload']) ? wp_kses_post($data['content_payload']) : $content,
			'media_payload'  => isset($data['media_payload']) ? wp_json_encode($data['media_payload']) : wp_json_encode(array()),
			'cta_payload'    => isset($data['cta_payload']) ? wp_json_encode($data['cta_payload']) : wp_json_encode(array()),
			'rules_json'     => isset($data['rules_json']) ? wp_json_encode($data['rules_json']) : wp_json_encode(array()),
			'qa_status'      => isset($data['qa_status']) ? sanitize_key($data['qa_status']) : 'untested',
			'qa_notes'       => isset($data['qa_notes']) ? sanitize_textarea_field($data['qa_notes']) : '',
			'is_active'      => !empty($data['is_active']) ? 1 : 0,
			'created_at'     => $now,
			'updated_at'     => $now,
		);

		$result = $this->wpdb->insert(
			$this->table_name,
			$insert_data,
			array('%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%d')
		);

		return $result ? $this->wpdb->insert_id : false;
	}

	/**
	 * Update content component.
	 *
	 * @param int   $id Component ID.
	 * @param array $data Update data.
	 * @return int|false
	 */
	public function update($id, $data) {
		$update_data = array();
		$formats     = array();

		if (array_key_exists('title', $data)) {
			$update_data['title'] = sanitize_text_field($data['title']);
			$formats[] = '%s';
		}

		if (array_key_exists('description', $data)) {
			$update_data['description'] = sanitize_textarea_field($data['description']);
			$formats[] = '%s';
		}

		if (array_key_exists('slug', $data)) {
			$update_data['slug'] = sanitize_title($data['slug']);
			$formats[] = '%s';
		}

		if (array_key_exists('status', $data) && !array_key_exists('is_active', $data)) {
			$update_data['status'] = sanitize_key($data['status']);
			$formats[] = '%s';
		}

		if (array_key_exists('component_type', $data)) {
			$update_data['component_type'] = sanitize_key($data['component_type']);
			$formats[] = '%s';
		}

		if (array_key_exists('content_mode', $data)) {
			$update_data['content_mode'] = sanitize_key($data['content_mode']);
			$formats[] = '%s';
		}

		if (array_key_exists('content', $data)) {
			$update_data['content'] = wp_kses_post($data['content']);
			$formats[] = '%s';
		}

		if (array_key_exists('content_payload', $data)) {
			$update_data['content_payload'] = wp_kses_post($data['content_payload']);
			$formats[] = '%s';
		}

		if (array_key_exists('media_payload', $data)) {
			$update_data['media_payload'] = wp_json_encode($data['media_payload']);
			$formats[] = '%s';
		}

		if (array_key_exists('cta_payload', $data)) {
			$update_data['cta_payload'] = wp_json_encode($data['cta_payload']);
			$formats[] = '%s';
		}

		if (array_key_exists('rules_json', $data)) {
			$update_data['rules_json'] = wp_json_encode($data['rules_json']);
			$formats[] = '%s';
		}

		if (array_key_exists('qa_status', $data)) {
			$update_data['qa_status'] = sanitize_key($data['qa_status']);
			$formats[] = '%s';
		}

		if (array_key_exists('qa_notes', $data)) {
			$update_data['qa_notes'] = sanitize_textarea_field($data['qa_notes']);
			$formats[] = '%s';
		}

		if (array_key_exists('is_active', $data)) {
			$update_data['is_active'] = !empty($data['is_active']) ? 1 : 0;
			$formats[] = '%d';
			$update_data['status'] = !empty($data['is_active']) ? 'active' : 'draft';
			$formats[] = '%s';
		}

		$update_data['updated_at'] = AIPS_DateTime::now()->timestamp();
		$formats[] = '%d';

		return $this->wpdb->update(
			$this->table_name,
			$update_data,
			array('id' => absint($id)),
			$formats,
			array('%d')
		);
	}

	/**
	 * Delete content component.
	 *
	 * @param int $id Component ID.
	 * @return int|false
	 */
	public function delete($id) {
		return $this->wpdb->delete(
			$this->table_name,
			array('id' => absint($id)),
			array('%d')
		);
	}

	/**
	 * Toggle active flag.
	 *
	 * @param int  $id Component ID.
	 * @param bool $is_active Active status.
	 * @return int|false
	 */
	public function set_active($id, $is_active) {
		return $this->update($id, array('is_active' => $is_active ? 1 : 0));
	}

	/**
	 * Check whether a title exists.
	 *
	 * @param string $title Component title.
	 * @param int    $exclude_id Optional ID to exclude.
	 * @return bool
	 */
	public function title_exists($title, $exclude_id = 0) {
		if ($exclude_id > 0) {
			$sql = $this->wpdb->prepare(
				"SELECT id FROM {$this->table_name} WHERE title = %s AND id != %d LIMIT 1",
				sanitize_text_field($title),
				absint($exclude_id)
			);
		} else {
			$sql = $this->wpdb->prepare(
				"SELECT id FROM {$this->table_name} WHERE title = %s LIMIT 1",
				sanitize_text_field($title)
			);
		}

		return (bool) $this->wpdb->get_var($sql);
	}

	/**
	 * Get count summary.
	 *
	 * @return array
	 */
	public function get_counts() {
		$counts = array(
			'total'          => 0,
			'active'         => 0,
			'inactive'       => 0,
			'passed'         => 0,
			'needs_review'   => 0,
			'untested'       => 0,
		);

		$status_results = $this->wpdb->get_results(
			"SELECT is_active, COUNT(*) AS count FROM {$this->table_name} GROUP BY is_active",
			ARRAY_A
		);

		if (is_array($status_results)) {
			foreach ($status_results as $row) {
				$count = (int) $row['count'];
				$counts['total'] += $count;
				if ((int) $row['is_active'] === 1) {
					$counts['active'] = $count;
				} else {
					$counts['inactive'] = $count;
				}
			}
		}

		$qa_results = $this->wpdb->get_results(
			"SELECT qa_status, COUNT(*) AS count FROM {$this->table_name} GROUP BY qa_status",
			ARRAY_A
		);

		if (is_array($qa_results)) {
			foreach ($qa_results as $row) {
				$status = (string) $row['qa_status'];
				if (array_key_exists($status, $counts)) {
					$counts[$status] = (int) $row['count'];
				}
			}
		}

		return $counts;
	}
}

