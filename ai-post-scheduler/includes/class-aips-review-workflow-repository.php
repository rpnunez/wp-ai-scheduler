<?php
/**
 * Review Workflow Repository
 *
 * Persists and queries multi-stage review workflow data for generated posts.
 *
 * @package AI_Post_Scheduler
 * @since 2.3.0
 */

if (!defined('ABSPATH')) {
	exit;
}

class AIPS_Review_Workflow_Repository {

	/**
	 * @var wpdb
	 */
	private $wpdb;

	/**
	 * @var string
	 */
	private $items_table;

	/**
	 * @var string
	 */
	private $stage_table;

	/**
	 * @var string
	 */
	private $comments_table;

	public function __construct() {
		global $wpdb;
		$this->wpdb           = $wpdb;
		$this->items_table    = $wpdb->prefix . 'aips_post_review_items';
		$this->stage_table    = $wpdb->prefix . 'aips_post_review_stage_data';
		$this->comments_table = $wpdb->prefix . 'aips_post_review_comments';
	}

	/**
	 * Fixed v1 workflow stages.
	 *
	 * @return string[]
	 */
	public static function get_stages() {
		return array( 'brief', 'outline', 'fact_check', 'seo', 'ready' );
	}

	/**
	 * Get a paginated list of review items.
	 *
	 * @param array $args Query args.
	 * @return array{items:array,total:int,pages:int,current_page:int}
	 */
	public function get_items($args = array()) {
		$defaults = array(
			'per_page'    => 20,
			'page'        => 1,
			'stage'       => '',
			'closed_state'=> 'open',
			'assigned_to' => 0,
			'template_id' => 0,
			'search'      => '',
			'orderby'     => 'updated_at',
			'order'       => 'DESC',
		);

		$args              = wp_parse_args($args, $defaults);
		$args['page']      = max(1, (int) $args['page']);
		$args['per_page']  = max(1, (int) $args['per_page']);
		$offset            = ($args['page'] - 1) * $args['per_page'];

		$where      = array( '1=1' );
		$where_args = array();

		if (!empty($args['closed_state'])) {
			$where[]      = 'i.closed_state = %s';
			$where_args[] = $args['closed_state'];
		}

		if (!empty($args['stage'])) {
			$where[]      = 'i.stage = %s';
			$where_args[] = $args['stage'];
		}

		if (!empty($args['assigned_to'])) {
			$where[]      = 'i.assigned_to = %d';
			$where_args[] = (int) $args['assigned_to'];
		}

		if (!empty($args['template_id'])) {
			$where[]      = 'i.template_id = %d';
			$where_args[] = (int) $args['template_id'];
		}

		if (!empty($args['search'])) {
			$like         = '%' . $this->wpdb->esc_like($args['search']) . '%';
			$where[]      = 'p.post_title LIKE %s';
			$where_args[] = $like;
		}

		$where_sql = implode(' AND ', $where);

		$orderby = in_array($args['orderby'], array('updated_at', 'due_at', 'created_at', 'post_title'), true) ? $args['orderby'] : 'updated_at';
		$order   = strtoupper($args['order']) === 'ASC' ? 'ASC' : 'DESC';

		if ('post_title' === $orderby) {
			$orderby_sql = "p.post_title $order";
		} else {
			$orderby_sql = "i.$orderby $order";
		}

		$posts_table     = $this->wpdb->posts;
		$templates_table = $this->wpdb->prefix . 'aips_templates';

		$query_args   = $where_args;
		$query_args[] = (int) $args['per_page'];
		$query_args[] = (int) $offset;

		$items = $this->wpdb->get_results($this->wpdb->prepare("
			SELECT
				i.*,
				p.post_title,
				p.post_status,
				p.post_modified,
				t.name AS template_name
			FROM {$this->items_table} i
			INNER JOIN {$posts_table} p ON i.post_id = p.ID
			LEFT JOIN {$templates_table} t ON i.template_id = t.id
			WHERE {$where_sql}
			ORDER BY {$orderby_sql}
			LIMIT %d OFFSET %d
		", $query_args));

		if (!empty($where_args)) {
			$total = (int) $this->wpdb->get_var($this->wpdb->prepare("
				SELECT COUNT(*)
				FROM {$this->items_table} i
				INNER JOIN {$posts_table} p ON i.post_id = p.ID
				WHERE {$where_sql}
			", $where_args));
		} else {
			$total = (int) $this->wpdb->get_var("
				SELECT COUNT(*)
				FROM {$this->items_table} i
				INNER JOIN {$posts_table} p ON i.post_id = p.ID
				WHERE {$where_sql}
			");
		}

		return array(
			'items'        => is_array($items) ? $items : array(),
			'total'        => $total,
			'pages'        => (int) ceil($total / max(1, (int) $args['per_page'])),
			'current_page' => (int) $args['page'],
		);
	}

	/**
	 * Get stage counts for open items.
	 *
	 * @return array<string,int> stage => count plus 'overdue'
	 */
	public function get_stage_counts() {
		$counts = array();
		foreach (self::get_stages() as $stage) {
			$counts[$stage] = 0;
		}

		$rows = $this->wpdb->get_results($this->wpdb->prepare("
			SELECT stage, COUNT(*) AS cnt
			FROM {$this->items_table}
			WHERE closed_state = %s
			GROUP BY stage
		", 'open'));

		if (is_array($rows)) {
			foreach ($rows as $row) {
				if (!empty($row->stage) && isset($counts[$row->stage])) {
					$counts[$row->stage] = (int) $row->cnt;
				}
			}
		}

		$overdue = (int) $this->wpdb->get_var($this->wpdb->prepare("
			SELECT COUNT(*)
			FROM {$this->items_table}
			WHERE closed_state = %s
			AND due_at IS NOT NULL
			AND due_at < %s
		", 'open', current_time('mysql')));

		$counts['overdue'] = $overdue;

		return $counts;
	}

	/**
	 * Get a single review item by ID.
	 *
	 * @param int $review_item_id
	 * @return object|null
	 */
	public function get_item_row($review_item_id) {
		$review_item_id = absint($review_item_id);
		if (!$review_item_id) {
			return null;
		}

		$posts_table     = $this->wpdb->posts;
		$templates_table = $this->wpdb->prefix . 'aips_templates';

		return $this->wpdb->get_row($this->wpdb->prepare("
			SELECT
				i.*,
				p.post_title,
				p.post_content,
				p.post_excerpt,
				p.post_status,
				p.post_modified,
				t.name AS template_name
			FROM {$this->items_table} i
			INNER JOIN {$posts_table} p ON i.post_id = p.ID
			LEFT JOIN {$templates_table} t ON i.template_id = t.id
			WHERE i.id = %d
			LIMIT 1
		", $review_item_id));
	}

	/**
	 * Get a review item by post ID.
	 *
	 * @param int $post_id
	 * @return object|null
	 */
	public function get_item_row_by_post_id($post_id) {
		$post_id = absint($post_id);
		if (!$post_id) {
			return null;
		}

		return $this->wpdb->get_row($this->wpdb->prepare("
			SELECT *
			FROM {$this->items_table}
			WHERE post_id = %d
			LIMIT 1
		", $post_id));
	}

	/**
	 * Get all stage rows for an item keyed by stage_key.
	 *
	 * @param int $review_item_id
	 * @return array<string,object>
	 */
	public function get_stage_rows($review_item_id) {
		$review_item_id = absint($review_item_id);
		if (!$review_item_id) {
			return array();
		}

		$rows = $this->wpdb->get_results($this->wpdb->prepare("
			SELECT *
			FROM {$this->stage_table}
			WHERE review_item_id = %d
		", $review_item_id));

		$map = array();
		if (is_array($rows)) {
			foreach ($rows as $row) {
				$map[(string) $row->stage_key] = $row;
			}
		}

		return $map;
	}

	/**
	 * Get comments for an item.
	 *
	 * @param int $review_item_id
	 * @return array
	 */
	public function get_comments($review_item_id) {
		$review_item_id = absint($review_item_id);
		if (!$review_item_id) {
			return array();
		}

		$rows = $this->wpdb->get_results($this->wpdb->prepare("
			SELECT *
			FROM {$this->comments_table}
			WHERE review_item_id = %d
			ORDER BY created_at ASC
		", $review_item_id));

		return is_array($rows) ? $rows : array();
	}

	/**
	 * Idempotently create or update a review workflow item for a post.
	 *
	 * @param int   $post_id
	 * @param int   $history_id Optional.
	 * @param array $context_fields Optional: template_id/author_id/topic_id.
	 * @return int Review item ID.
	 */
	public function get_or_create_item_for_post($post_id, $history_id = 0, $context_fields = array()) {
		$post_id   = absint($post_id);
		$history_id = absint($history_id);

		if (!$post_id) {
			return 0;
		}

		$existing = $this->get_item_row_by_post_id($post_id);

		$update = array(
			'history_id'  => $history_id ? $history_id : null,
			'template_id' => !empty($context_fields['template_id']) ? absint($context_fields['template_id']) : null,
			'author_id'   => !empty($context_fields['author_id']) ? absint($context_fields['author_id']) : null,
			'topic_id'    => !empty($context_fields['topic_id']) ? absint($context_fields['topic_id']) : null,
			'updated_at'  => current_time('mysql'),
		);

		$update_formats = array('%d', '%d', '%d', '%d', '%s');

		if ($existing) {
			$this->wpdb->update(
				$this->items_table,
				$update,
				array('id' => (int) $existing->id),
				$update_formats,
				array('%d')
			);
			$review_item_id = (int) $existing->id;
		} else {
			$insert = array(
				'post_id'      => $post_id,
				'history_id'   => $history_id ? $history_id : null,
				'template_id'  => !empty($context_fields['template_id']) ? absint($context_fields['template_id']) : null,
				'author_id'    => !empty($context_fields['author_id']) ? absint($context_fields['author_id']) : null,
				'topic_id'     => !empty($context_fields['topic_id']) ? absint($context_fields['topic_id']) : null,
				'stage'        => 'brief',
				'stage_state'  => 'pending',
				'priority'     => 'normal',
				'closed_state' => 'open',
				'created_at'   => current_time('mysql'),
				'updated_at'   => current_time('mysql'),
			);

			$this->wpdb->insert(
				$this->items_table,
				$insert,
				array('%d','%d','%d','%d','%d','%s','%s','%s','%s','%s','%s')
			);

			$review_item_id = (int) $this->wpdb->insert_id;
		}

		if ($review_item_id) {
			$this->ensure_stage_rows($review_item_id);
		}

		return $review_item_id;
	}

	/**
	 * Ensure stage rows exist for a review item.
	 *
	 * @param int $review_item_id
	 * @return void
	 */
	private function ensure_stage_rows($review_item_id) {
		$review_item_id = absint($review_item_id);
		if (!$review_item_id) {
			return;
		}

		$existing = $this->get_stage_rows($review_item_id);

		foreach (self::get_stages() as $stage_key) {
			if (isset($existing[$stage_key])) {
				continue;
			}

			$this->wpdb->insert(
				$this->stage_table,
				array(
					'review_item_id'  => $review_item_id,
					'stage_key'       => $stage_key,
					'state'           => 'pending',
					'checklist_state' => wp_json_encode(array()),
					'updated_at'      => current_time('mysql'),
				),
				array('%d','%s','%s','%s','%s')
			);
		}
	}

	/**
	 * Update item meta (assignee/priority/due).
	 *
	 * @param int   $review_item_id
	 * @param array $fields
	 * @return bool
	 */
	public function update_item_meta($review_item_id, $fields) {
		$review_item_id = absint($review_item_id);
		if (!$review_item_id) {
			return false;
		}

		$allowed = array('assigned_to', 'priority', 'due_at');
		$data    = array();
		$formats = array();

		foreach ($allowed as $key) {
			if (!array_key_exists($key, $fields)) {
				continue;
			}

			if ('assigned_to' === $key) {
				$data[$key] = $fields[$key] ? absint($fields[$key]) : null;
				$formats[]  = '%d';
			} elseif ('priority' === $key) {
				$priority = in_array($fields[$key], array('low','normal','high'), true) ? $fields[$key] : 'normal';
				$data[$key] = $priority;
				$formats[]  = '%s';
			} elseif ('due_at' === $key) {
				$data[$key] = !empty($fields[$key]) ? $fields[$key] : null;
				$formats[]  = '%s';
			}
		}

		if (empty($data)) {
			return false;
		}

		$data['updated_at'] = current_time('mysql');
		$formats[]          = '%s';

		$result = $this->wpdb->update(
			$this->items_table,
			$data,
			array('id' => $review_item_id),
			$formats,
			array('%d')
		);

		return $result !== false;
	}

	/**
	 * Set the current stage for an item.
	 *
	 * @param int    $review_item_id
	 * @param string $stage_key
	 * @return bool
	 */
	public function set_stage($review_item_id, $stage_key) {
		$review_item_id = absint($review_item_id);
		$stage_key      = sanitize_key($stage_key);

		if (!$review_item_id || !in_array($stage_key, self::get_stages(), true)) {
			return false;
		}

		$stage_rows = $this->get_stage_rows($review_item_id);
		$state      = isset($stage_rows[$stage_key]) ? (string) $stage_rows[$stage_key]->state : 'pending';

		$result = $this->wpdb->update(
			$this->items_table,
			array(
				'stage'       => $stage_key,
				'stage_state' => $state,
				'updated_at'  => current_time('mysql'),
			),
			array('id' => $review_item_id),
			array('%s','%s','%s'),
			array('%d')
		);

		return $result !== false;
	}

	/**
	 * Save stage notes.
	 *
	 * @param int    $review_item_id
	 * @param string $stage_key
	 * @param string $notes
	 * @return bool
	 */
	public function save_stage_notes($review_item_id, $stage_key, $notes) {
		$review_item_id = absint($review_item_id);
		$stage_key      = sanitize_key($stage_key);

		if (!$review_item_id || !in_array($stage_key, self::get_stages(), true)) {
			return false;
		}

		$result = $this->wpdb->update(
			$this->stage_table,
			array(
				'notes'      => $notes,
				'updated_at' => current_time('mysql'),
			),
			array(
				'review_item_id' => $review_item_id,
				'stage_key'      => $stage_key,
			),
			array('%s','%s'),
			array('%d','%s')
		);

		return $result !== false;
	}

	/**
	 * Toggle a checklist item.
	 *
	 * @param int    $review_item_id
	 * @param string $stage_key
	 * @param string $check_key
	 * @param bool   $checked
	 * @return array Updated checklist map.
	 */
	public function toggle_checklist_item($review_item_id, $stage_key, $check_key, $checked) {
		$review_item_id = absint($review_item_id);
		$stage_key      = sanitize_key($stage_key);
		$check_key      = sanitize_key($check_key);
		$checked        = (bool) $checked;

		if (!$review_item_id || !in_array($stage_key, self::get_stages(), true) || empty($check_key)) {
			return array();
		}

		$row = $this->wpdb->get_row($this->wpdb->prepare("
			SELECT checklist_state
			FROM {$this->stage_table}
			WHERE review_item_id = %d AND stage_key = %s
			LIMIT 1
		", $review_item_id, $stage_key));

		$decoded = array();
		if ($row && !empty($row->checklist_state)) {
			$tmp = json_decode((string) $row->checklist_state, true);
			if (is_array($tmp)) {
				$decoded = $tmp;
			}
		}

		$decoded[$check_key] = $checked;

		$this->wpdb->update(
			$this->stage_table,
			array(
				'checklist_state' => wp_json_encode($decoded),
				'updated_at'      => current_time('mysql'),
			),
			array(
				'review_item_id' => $review_item_id,
				'stage_key'      => $stage_key,
			),
			array('%s','%s'),
			array('%d','%s')
		);

		return $decoded;
	}

	/**
	 * Update stage state (approve/request changes/skip) and advance when applicable.
	 *
	 * @param int    $review_item_id
	 * @param string $stage_key
	 * @param string $state
	 * @param string $notes
	 * @param int    $reviewed_by
	 * @param bool   $advance
	 * @return bool
	 */
	public function set_stage_state($review_item_id, $stage_key, $state, $notes, $reviewed_by = 0, $advance = false) {
		$review_item_id = absint($review_item_id);
		$stage_key      = sanitize_key($stage_key);
		$state          = sanitize_key($state);
		$reviewed_by    = absint($reviewed_by);
		$advance        = (bool) $advance;

		if (!$review_item_id || !in_array($stage_key, self::get_stages(), true)) {
			return false;
		}

		if (!in_array($state, array('pending','approved','changes_requested','skipped'), true)) {
			$state = 'pending';
		}

		$this->wpdb->update(
			$this->stage_table,
			array(
				'state'       => $state,
				'notes'       => $notes,
				'reviewed_by' => $reviewed_by ? $reviewed_by : null,
				'reviewed_at' => in_array($state, array('approved','skipped'), true) ? current_time('mysql') : null,
				'updated_at'  => current_time('mysql'),
			),
			array(
				'review_item_id' => $review_item_id,
				'stage_key'      => $stage_key,
			),
			array('%s','%s','%d','%s','%s'),
			array('%d','%s')
		);

		if ($advance) {
			$next = $this->get_next_stage($stage_key);
			if ($next) {
				$this->set_stage($review_item_id, $next);
			} else {
				$this->set_stage($review_item_id, $stage_key);
			}
			return true;
		}

		// Update item stage_state if this is the current stage.
		$item = $this->get_item_row($review_item_id);
		if ($item && (string) $item->stage === $stage_key) {
			$this->wpdb->update(
				$this->items_table,
				array(
					'stage_state' => $state,
					'updated_at'  => current_time('mysql'),
				),
				array('id' => $review_item_id),
				array('%s','%s'),
				array('%d')
			);
		}

		return true;
	}

	/**
	 * Add a comment.
	 *
	 * @param int    $review_item_id
	 * @param int    $user_id
	 * @param string $comment
	 * @return int Comment ID.
	 */
	public function add_comment($review_item_id, $user_id, $comment) {
		$review_item_id = absint($review_item_id);
		$user_id        = absint($user_id);
		$comment        = trim((string) $comment);

		if (!$review_item_id || '' === $comment) {
			return 0;
		}

		$this->wpdb->insert(
			$this->comments_table,
			array(
				'review_item_id' => $review_item_id,
				'user_id'        => $user_id ? $user_id : null,
				'comment'        => $comment,
				'created_at'     => current_time('mysql'),
			),
			array('%d','%d','%s','%s')
		);

		$this->wpdb->update(
			$this->items_table,
			array('updated_at' => current_time('mysql')),
			array('id' => $review_item_id),
			array('%s'),
			array('%d')
		);

		return (int) $this->wpdb->insert_id;
	}

	/**
	 * Close an item.
	 *
	 * @param int    $review_item_id
	 * @param string $closed_state
	 * @return bool
	 */
	public function close_item($review_item_id, $closed_state) {
		$review_item_id = absint($review_item_id);
		$closed_state   = sanitize_key($closed_state);

		if (!$review_item_id || !in_array($closed_state, array('open','scheduled','published','archived'), true)) {
			return false;
		}

		$result = $this->wpdb->update(
			$this->items_table,
			array(
				'closed_state' => $closed_state,
				'updated_at'   => current_time('mysql'),
			),
			array('id' => $review_item_id),
			array('%s','%s'),
			array('%d')
		);

		return $result !== false;
	}

	/**
	 * Sync closed_state based on WordPress post_status.
	 *
	 * @param int $post_id
	 * @return void
	 */
	public function sync_closed_state_from_post_status($post_id) {
		$post_id = absint($post_id);
		if (!$post_id) {
			return;
		}

		$item = $this->get_item_row_by_post_id($post_id);
		if (!$item) {
			return;
		}

		$post = get_post($post_id);
		if (!$post) {
			return;
		}

		$new_state = 'open';
		if ('publish' === $post->post_status) {
			$new_state = 'published';
		} elseif ('future' === $post->post_status) {
			$new_state = 'scheduled';
		} elseif ('trash' === $post->post_status) {
			$new_state = 'archived';
		}

		if ((string) $item->closed_state !== $new_state) {
			$this->close_item((int) $item->id, $new_state);
		}
	}

	/**
	 * Fetch basic generation context fields from a history record.
	 *
	 * @param int $history_id
	 * @return array{template_id:int,author_id:int,topic_id:int}
	 */
	public function get_context_fields_from_history($history_id) {
		$history_id = absint($history_id);
		if (!$history_id) {
			return array('template_id' => 0, 'author_id' => 0, 'topic_id' => 0);
		}

		$history_table = $this->wpdb->prefix . 'aips_history';
		$row = $this->wpdb->get_row($this->wpdb->prepare("
			SELECT template_id, author_id, topic_id
			FROM {$history_table}
			WHERE id = %d
			LIMIT 1
		", $history_id));

		return array(
			'template_id' => $row && !empty($row->template_id) ? (int) $row->template_id : 0,
			'author_id'   => $row && !empty($row->author_id) ? (int) $row->author_id : 0,
			'topic_id'    => $row && !empty($row->topic_id) ? (int) $row->topic_id : 0,
		);
	}

	/**
	 * Get next stage key.
	 *
	 * @param string $stage_key
	 * @return string|null
	 */
	private function get_next_stage($stage_key) {
		$stages = self::get_stages();
		$idx = array_search($stage_key, $stages, true);
		if ($idx === false) {
			return null;
		}

		$next_idx = $idx + 1;
		if (!isset($stages[$next_idx])) {
			return null;
		}

		return $stages[$next_idx];
	}
}
