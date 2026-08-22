<?php
/**
 * Append-only persistence for generated-post feedback.
 *
 * @package AI_Post_Scheduler
 */

if (!defined('ABSPATH')) {
	exit;
}

class AIPS_Post_Feedback_Repository {

	private $wpdb;
	private $table;

	/**
	 * Memoized result of the aips_post_feedback table-existence check. Null
	 * until first resolved. Guards every DB operation so an install that has
	 * upgraded plugin code but not yet run the schema migration no-ops
	 * silently instead of emitting "table doesn't exist" errors on every
	 * Generated Posts render.
	 *
	 * @var bool|null
	 */
	private $table_exists = null;

	public function __construct() {
		global $wpdb;
		$this->wpdb  = $wpdb;
		$this->table = $wpdb->prefix . 'aips_post_feedback';
	}

	private function table_ready() {
		if (null !== $this->table_exists) {
			return $this->table_exists;
		}
		$found = $this->wpdb->get_var($this->wpdb->prepare('SHOW TABLES LIKE %s', $this->table));
		$this->table_exists = ($found === $this->table);
		return $this->table_exists;
	}

	public function append_event(array $event) {
		if (!$this->table_ready()) {
			return new WP_Error('feedback_table_missing', __('Post feedback storage is not yet available. Please try again after the plugin upgrade completes.', 'ai-post-scheduler'));
		}
		$data = array(
			'post_id'         => absint($event['post_id'] ?? 0),
			'history_id'      => !empty($event['history_id']) ? absint($event['history_id']) : null,
			'user_id'         => absint($event['user_id'] ?? 0),
			'reaction'        => sanitize_key($event['reaction'] ?? ''),
			'reason_category' => !empty($event['reason_category']) ? sanitize_key($event['reason_category']) : null,
			'comment'         => isset($event['comment']) && $event['comment'] !== '' ? sanitize_textarea_field($event['comment']) : null,
			'content_hash'    => !empty($event['content_hash']) ? sanitize_text_field($event['content_hash']) : null,
			'author_id'       => !empty($event['author_id']) ? absint($event['author_id']) : null,
			'template_id'     => !empty($event['template_id']) ? absint($event['template_id']) : null,
			'embedding_text'  => !empty($event['embedding_text']) ? (string) $event['embedding_text'] : null,
			'created_at'      => !empty($event['created_at']) ? absint($event['created_at']) : AIPS_DateTime::now()->timestamp(),
		);

		if (!$data['post_id'] || !$data['user_id'] || !$data['reaction']) {
			return new WP_Error('invalid_feedback_event', __('Post, user, and reaction are required.', 'ai-post-scheduler'));
		}

		$result = $this->wpdb->insert(
			$this->table,
			$data,
			array('%d', '%d', '%d', '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%d')
		);

		return false === $result
			? new WP_Error('feedback_insert_failed', __('Could not save post feedback.', 'ai-post-scheduler'))
			: (int) $this->wpdb->insert_id;
	}

	public function get_by_id($event_id) {
		if (!$this->table_ready()) {
			return null;
		}
		return $this->wpdb->get_row($this->wpdb->prepare("SELECT * FROM {$this->table} WHERE id = %d", absint($event_id)));
	}

	public function update_embedding($event_id, array $embedding) {
		if (!$this->table_ready()) {
			return false;
		}
		return false !== $this->wpdb->update($this->table, array('embedding' => wp_json_encode($embedding)), array('id' => absint($event_id)), array('%s'), array('%d'));
	}

	public function get_current_for_post($post_id) {
		if (!$this->table_ready()) {
			return null;
		}
		return $this->wpdb->get_row($this->wpdb->prepare(
			"SELECT * FROM {$this->table} WHERE post_id = %d ORDER BY id DESC LIMIT 1",
			absint($post_id)
		));
	}

	public function get_current_for_posts(array $post_ids) {
		if (!$this->table_ready()) {
			return array();
		}
		$post_ids = array_values(array_filter(array_unique(array_map('absint', $post_ids))));
		if (empty($post_ids)) {
			return array();
		}

		$placeholders = implode(',', array_fill(0, count($post_ids), '%d'));
		$sql = "SELECT f.* FROM {$this->table} f
			INNER JOIN (
				SELECT post_id, MAX(id) latest_id FROM {$this->table}
				WHERE post_id IN ($placeholders) GROUP BY post_id
			) latest ON latest.latest_id = f.id";
		$rows = $this->wpdb->get_results($this->wpdb->prepare($sql, ...$post_ids));
		$result = array();
		foreach ($rows as $row) {
			$result[(int) $row->post_id] = $row;
		}
		return $result;
	}

	public function get_history_for_post($post_id, $limit = 100) {
		if (!$this->table_ready()) {
			return array();
		}
		return $this->wpdb->get_results($this->wpdb->prepare(
			"SELECT * FROM {$this->table} WHERE post_id = %d ORDER BY id DESC LIMIT %d",
			absint($post_id),
			max(1, min(500, absint($limit)))
		));
	}

	/**
	 * Return latest active events with their current WordPress post content.
	 */
	public function get_active_candidates($scope = array(), $template_id = 0, $limit = 100) {
		if (!$this->table_ready()) {
			return array();
		}
		if (is_array($scope)) {
			$limit       = $template_id ?: $limit;
			$template_id = absint($scope['template_id'] ?? 0);
			$author_id   = absint($scope['author_id'] ?? 0);
		} else {
			$author_id = absint($scope);
			$template_id = absint($template_id);
		}
		$limit = max(1, min(500, absint($limit)));
		$where = array("f.reaction IN ('liked','disliked')", "p.post_status NOT IN ('trash','auto-draft')");
		$args  = array();
		$order = 'f.id DESC';
		if ($template_id || $author_id) {
			$order_parts = array();
			if ($template_id) { $order_parts[] = 'WHEN f.template_id = %d THEN 0'; $args[] = $template_id; }
			if ($author_id) { $order_parts[] = 'WHEN f.author_id = %d THEN 1'; $args[] = $author_id; }
			$order_parts[] = 'WHEN f.template_id IS NULL AND f.author_id IS NULL THEN 2';
			$order = 'CASE ' . implode(' ', $order_parts) . ' ELSE 3 END ASC, f.id DESC';
		}

		$args[] = $limit;
		$sql = "SELECT f.*, p.post_title, p.post_excerpt, p.post_content, p.post_status
			FROM {$this->table} f
			INNER JOIN (SELECT post_id, MAX(id) latest_id FROM {$this->table} GROUP BY post_id) latest ON latest.latest_id = f.id
			INNER JOIN {$this->wpdb->posts} p ON p.ID = f.post_id
			WHERE " . implode(' AND ', $where) . " ORDER BY {$order} LIMIT %d";
		return $this->wpdb->get_results($this->wpdb->prepare($sql, ...$args));
	}

	public function delete_all() {
		if (!$this->table_ready()) {
			return 0;
		}
		return $this->wpdb->query("DELETE FROM {$this->table}");
	}
}
