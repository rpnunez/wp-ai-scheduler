<?php
/**
 * Post Images Generated Repository
 *
 * Repository pattern implementation for generated post images data access.
 * Manages tracking of images generated and injected into posts (in aips_post_images_generated).
 *
 * @package AI_Post_Scheduler
 * @since 3.7.21
 */

if (!defined('ABSPATH')) {
	exit;
}

class AIPS_Post_Images_Generated_Repository {

	/**
	 * @var string Table name with prefix
	 */
	private $table_name;

	/**
	 * @var wpdb WordPress database abstraction object
	 */
	private $wpdb;

	/**
	 * Initialize the repository.
	 *
	 * @param wpdb|null $wpdb Optional database instance.
	 */
	public function __construct($wpdb = null) {
		global $wpdb;
		$this->wpdb = $wpdb ?: $GLOBALS['wpdb'];
		$this->table_name = $this->wpdb->prefix . 'aips_post_images_generated';
	}

	/**
	 * Get all generated image records for a post.
	 *
	 * @param int $post_id WordPress post ID.
	 * @return array Array of generated image objects.
	 */
	public function get_by_post_id($post_id) {
		return $this->wpdb->get_results($this->wpdb->prepare(
			"SELECT * FROM {$this->table_name} WHERE post_id = %d ORDER BY image_index ASC, id ASC",
			absint($post_id)
		));
	}

	/**
	 * Get a single generated image record by ID.
	 *
	 * @param int $id Record ID.
	 * @return object|null
	 */
	public function get_by_id($id) {
		return $this->wpdb->get_row($this->wpdb->prepare(
			"SELECT * FROM {$this->table_name} WHERE id = %d",
			absint($id)
		));
	}

	/**
	 * Create a generated image record.
	 *
	 * @param array $data Record data.
	 * @return int|false Inserted ID or false on failure.
	 */
	public function create($data) {
		$now = time();
		$defaults = array(
			'post_id'         => 0,
			'history_id'      => null,
			'config_image_id' => null,
			'image_index'     => 1,
			'attachment_id'   => 0,
			'image_source'    => 'ai_prompt',
			'image_prompt'    => '',
			'image_url'       => '',
			'placement'       => 'auto',
			'status'          => 'completed',
			'error_message'   => '',
			'created_at'      => $now,
			'updated_at'      => $now,
		);

		$record = wp_parse_args($data, $defaults);

		$inserted = $this->wpdb->insert(
			$this->table_name,
			array(
				'post_id'         => absint($record['post_id']),
				'history_id'      => !empty($record['history_id']) ? absint($record['history_id']) : null,
				'config_image_id' => !empty($record['config_image_id']) ? absint($record['config_image_id']) : null,
				'image_index'     => (int) $record['image_index'],
				'attachment_id'   => absint($record['attachment_id']),
				'image_source'    => sanitize_key($record['image_source']),
				'image_prompt'    => wp_kses_post($record['image_prompt']),
				'image_url'       => esc_url_raw($record['image_url']),
				'placement'       => sanitize_text_field($record['placement']),
				'status'          => sanitize_key($record['status']),
				'error_message'   => sanitize_textarea_field($record['error_message']),
				'created_at'      => absint($record['created_at']),
				'updated_at'      => absint($record['updated_at']),
			),
			array('%d', '%d', '%d', '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d')
		);

		return $inserted ? (int) $this->wpdb->insert_id : false;
	}

	/**
	 * Update an existing generated image record.
	 *
	 * @param int   $id   Record ID.
	 * @param array $data Updated fields.
	 * @return bool True on success.
	 */
	public function update($id, $data) {
		$id = absint($id);
		if (!$id) {
			return false;
		}

		$fields = array();
		$formats = array();

		if (isset($data['attachment_id'])) {
			$fields['attachment_id'] = absint($data['attachment_id']);
			$formats[] = '%d';
		}
		if (isset($data['image_source'])) {
			$fields['image_source'] = sanitize_key($data['image_source']);
			$formats[] = '%s';
		}
		if (isset($data['image_prompt'])) {
			$fields['image_prompt'] = wp_kses_post($data['image_prompt']);
			$formats[] = '%s';
		}
		if (isset($data['image_url'])) {
			$fields['image_url'] = esc_url_raw($data['image_url']);
			$formats[] = '%s';
		}
		if (isset($data['placement'])) {
			$fields['placement'] = sanitize_text_field($data['placement']);
			$formats[] = '%s';
		}
		if (isset($data['status'])) {
			$fields['status'] = sanitize_key($data['status']);
			$formats[] = '%s';
		}
		if (isset($data['error_message'])) {
			$fields['error_message'] = sanitize_textarea_field($data['error_message']);
			$formats[] = '%s';
		}

		$fields['updated_at'] = time();
		$formats[] = '%d';

		$result = $this->wpdb->update(
			$this->table_name,
			$fields,
			array('id' => $id),
			$formats,
			array('%d')
		);

		return false !== $result;
	}

	/**
	 * Delete a generated image record.
	 *
	 * @param int $id Record ID.
	 * @return bool
	 */
	public function delete($id) {
		$result = $this->wpdb->delete(
			$this->table_name,
			array('id' => absint($id)),
			array('%d')
		);
		return false !== $result;
	}

	/**
	 * Delete all generated image records for a post.
	 *
	 * @param int $post_id Post ID.
	 * @return bool
	 */
	public function delete_by_post_id($post_id) {
		$result = $this->wpdb->delete(
			$this->table_name,
			array('post_id' => absint($post_id)),
			array('%d')
		);
		return false !== $result;
	}

	/**
	 * Mark status and attachment for a generated image record.
	 *
	 * @param int    $id            Record ID.
	 * @param string $status        'completed' or 'failed'.
	 * @param int    $attachment_id Media attachment ID (if successful).
	 * @param string $image_url     Image URL (if successful).
	 * @param string $error_message Error message (if failed).
	 * @return bool
	 */
	public function mark_status($id, $status, $attachment_id = 0, $image_url = '', $error_message = '') {
		return $this->update($id, array(
			'status'        => $status,
			'attachment_id' => $attachment_id,
			'image_url'     => $image_url,
			'error_message' => $error_message,
		));
	}
}
