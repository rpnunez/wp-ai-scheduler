<?php
/**
 * Existing Post Improvement Repository.
 *
 * @package AI_Post_Scheduler
 * @since 2.10.0
 */

if (!defined('ABSPATH')) {
exit;
}

class AIPS_Existing_Post_Improvement_Repository {

/** @var wpdb */
private $wpdb;
/** @var string */
private $table_schedules;
/** @var string */
private $table_runs;
/** @var string */
private $table_suggestions;
/** @var string */
private $table_items;
/** @var string */
private $table_history;

public function __construct() {
global $wpdb;
$this->wpdb              = $wpdb;
$this->table_schedules   = $wpdb->prefix . 'aips_existing_post_scan_schedules';
$this->table_runs        = $wpdb->prefix . 'aips_existing_post_scan_runs';
$this->table_suggestions = $wpdb->prefix . 'aips_existing_post_suggestions';
$this->table_items       = $wpdb->prefix . 'aips_existing_post_suggestion_items';
$this->table_history     = $wpdb->prefix . 'aips_history';
}

public function create_schedule($data) {
$now = time();
$row = array(
'title'                  => isset($data['title']) ? sanitize_text_field($data['title']) : '',
'description'            => isset($data['description']) ? sanitize_textarea_field($data['description']) : '',
'frequency'              => isset($data['frequency']) ? sanitize_key($data['frequency']) : 'daily',
'category_filters'       => isset($data['category_filters']) ? wp_json_encode(array_values(array_map('absint', (array) $data['category_filters']))) : wp_json_encode(array()),
'include_generated_posts'=> !empty($data['include_generated_posts']) ? 1 : 0,
'status'                 => isset($data['status']) ? sanitize_key($data['status']) : 'active',
'next_run'               => isset($data['next_run']) ? absint($data['next_run']) : 0,
'last_run'               => isset($data['last_run']) ? absint($data['last_run']) : 0,
'lock_token'             => '',
'lock_expires_at'        => 0,
'run_cursor'             => 0,
'retry_count'            => 0,
'last_error'             => '',
'created_at'             => $now,
'updated_at'             => $now,
);

$inserted = $this->wpdb->insert($this->table_schedules, $row, array('%s','%s','%s','%s','%d','%s','%d','%d','%s','%d','%d','%d','%s','%d','%d'));
if (false === $inserted) {
return 0;
}

return (int) $this->wpdb->insert_id;
}

public function update_schedule($id, $data) {
$id = absint($id);
if (!$id) {
return false;
}

$fields = array('updated_at' => time());
$formats = array('%d');

$map = array(
'title' => '%s',
'description' => '%s',
'frequency' => '%s',
'status' => '%s',
'next_run' => '%d',
'last_run' => '%d',
'include_generated_posts' => '%d',
'run_cursor' => '%d',
'retry_count' => '%d',
'last_error' => '%s',
);

foreach ($map as $key => $format) {
if (!array_key_exists($key, $data)) {
continue;
}
$value = $data[$key];
if ('%d' === $format) {
$value = absint($value);
} elseif (in_array($key, array('status', 'frequency'), true)) {
$value = sanitize_key((string) $value);
} else {
$value = sanitize_text_field((string) $value);
}
$fields[$key] = $value;
$formats[] = $format;
}

if (array_key_exists('category_filters', $data)) {
$fields['category_filters'] = wp_json_encode(array_values(array_map('absint', (array) $data['category_filters'])));
$formats[] = '%s';
}

$result = $this->wpdb->update($this->table_schedules, $fields, array('id' => $id), $formats, array('%d'));
return false !== $result;
}

public function get_schedule_by_id($id) {
$id = absint($id);
if (!$id) {
return null;
}
return $this->wpdb->get_row($this->wpdb->prepare("SELECT * FROM {$this->table_schedules} WHERE id = %d", $id));
}

public function get_schedules($status = '') {
if (!empty($status)) {
return $this->wpdb->get_results($this->wpdb->prepare("SELECT * FROM {$this->table_schedules} WHERE status = %s ORDER BY next_run ASC, id DESC", sanitize_key($status)));
}
return $this->wpdb->get_results("SELECT * FROM {$this->table_schedules} ORDER BY next_run ASC, id DESC");
}

public function get_due_schedules($now = null, $limit = 10) {
$now = null === $now ? time() : absint($now);
$limit = max(1, absint($limit));
return $this->wpdb->get_results($this->wpdb->prepare(
"SELECT * FROM {$this->table_schedules} WHERE status = %s AND next_run > 0 AND next_run <= %d AND (lock_expires_at = 0 OR lock_expires_at < %d) ORDER BY next_run ASC LIMIT %d",
'active', $now, $now, $limit
));
}

public function acquire_schedule_lock($schedule_id, $token, $ttl = 300) {
$schedule_id = absint($schedule_id);
$token = sanitize_text_field($token);
$ttl = max(30, absint($ttl));
$now = time();
$expires = $now + $ttl;
$result = $this->wpdb->query($this->wpdb->prepare(
"UPDATE {$this->table_schedules}
 SET lock_token = %s, lock_expires_at = %d, updated_at = %d
 WHERE id = %d AND (lock_expires_at = 0 OR lock_expires_at < %d)",
$token, $expires, $now, $schedule_id, $now
));
return !empty($result);
}

public function release_schedule_lock($schedule_id, $token = '') {
$schedule_id = absint($schedule_id);
if ($schedule_id <= 0) {
return false;
}
if (!empty($token)) {
$result = $this->wpdb->query($this->wpdb->prepare(
"UPDATE {$this->table_schedules} SET lock_token = '', lock_expires_at = 0, updated_at = %d WHERE id = %d AND lock_token = %s",
time(), $schedule_id, sanitize_text_field($token)
));
return false !== $result;
}
$result = $this->wpdb->update($this->table_schedules, array('lock_token' => '', 'lock_expires_at' => 0, 'updated_at' => time()), array('id' => $schedule_id), array('%s','%d','%d'), array('%d'));
return false !== $result;
}

public function delete_schedule($id) {
return false !== $this->wpdb->delete($this->table_schedules, array('id' => absint($id)), array('%d'));
}

public function create_run($schedule_id, $status = 'running', $started_by = 'cron') {
$now = time();
$this->wpdb->insert($this->table_runs, array(
'schedule_id' => absint($schedule_id),
'status' => sanitize_key($status),
'posts_scanned' => 0,
'suggestions_created' => 0,
'suggestions_applied' => 0,
'posts_skipped_unchanged' => 0,
'failures_count' => 0,
'error_summary' => '',
'ai_trace' => '',
'started_by' => sanitize_key($started_by),
'started_at' => $now,
'completed_at' => 0,
'created_at' => $now,
'updated_at' => $now,
), array('%d','%s','%d','%d','%d','%d','%d','%s','%s','%s','%d','%d','%d','%d'));
return (int) $this->wpdb->insert_id;
}

public function update_run($run_id, $data) {
$run_id = absint($run_id);
if (!$run_id) {
return false;
}
$fields = array('updated_at' => time());
$formats = array('%d');
foreach (array('status' => '%s','posts_scanned' => '%d','suggestions_created' => '%d','suggestions_applied' => '%d','posts_skipped_unchanged' => '%d','failures_count' => '%d','error_summary' => '%s','ai_trace' => '%s','completed_at' => '%d') as $key => $format) {
if (!array_key_exists($key, $data)) {
continue;
}
$value = $data[$key];
if ('%d' === $format) {
$value = absint($value);
} elseif ('status' === $key) {
$value = sanitize_key((string) $value);
} else {
$value = sanitize_textarea_field((string) $value);
}
$fields[$key] = $value;
$formats[] = $format;
}
return false !== $this->wpdb->update($this->table_runs, $fields, array('id' => $run_id), $formats, array('%d'));
}

public function get_recent_runs_by_schedule($schedule_id, $limit = 20) {
return $this->wpdb->get_results($this->wpdb->prepare(
"SELECT * FROM {$this->table_runs} WHERE schedule_id = %d ORDER BY started_at DESC LIMIT %d",
absint($schedule_id), max(1, absint($limit))
));
}

public function get_latest_pending_suggestion_for_post($post_id) {
return $this->wpdb->get_row($this->wpdb->prepare(
"SELECT * FROM {$this->table_suggestions} WHERE post_id = %d AND status IN ('pending','reviewed') ORDER BY created_at DESC LIMIT 1",
absint($post_id)
));
}

public function create_suggestion($data) {
$now = time();
$this->wpdb->insert($this->table_suggestions, array(
'post_id' => absint($data['post_id']),
'run_id' => absint($data['run_id']),
'schedule_id' => absint($data['schedule_id']),
'status' => isset($data['status']) ? sanitize_key($data['status']) : 'pending',
'priority' => isset($data['priority']) ? sanitize_key($data['priority']) : 'medium',
'severity' => isset($data['severity']) ? sanitize_key($data['severity']) : 'medium',
'content_hash' => isset($data['content_hash']) ? sanitize_text_field($data['content_hash']) : '',
'freshness_marker' => isset($data['freshness_marker']) ? sanitize_text_field($data['freshness_marker']) : '',
'last_scanned_at' => isset($data['last_scanned_at']) ? absint($data['last_scanned_at']) : $now,
'applied_items_count' => 0,
'dismissed_items_count' => 0,
'metadata' => isset($data['metadata']) ? wp_json_encode($data['metadata']) : '',
'created_at' => $now,
'updated_at' => $now,
), array('%d','%d','%d','%s','%s','%s','%s','%s','%d','%d','%d','%s','%d','%d'));
return (int) $this->wpdb->insert_id;
}

public function update_suggestion($suggestion_id, $data) {
$suggestion_id = absint($suggestion_id);
if (!$suggestion_id) {
return false;
}
$fields = array('updated_at' => time());
$formats = array('%d');
foreach (array('status' => '%s','priority' => '%s','severity' => '%s','content_hash' => '%s','freshness_marker' => '%s','last_scanned_at' => '%d','applied_items_count' => '%d','dismissed_items_count' => '%d','metadata' => '%s') as $key => $format) {
if (!array_key_exists($key, $data)) {
continue;
}
$value = $data[$key];
if ('%d' === $format) {
$value = absint($value);
} else {
$value = in_array($key, array('metadata'), true) ? (is_string($value) ? $value : wp_json_encode($value)) : sanitize_text_field((string) $value);
}
$fields[$key] = $value;
$formats[] = $format;
}
return false !== $this->wpdb->update($this->table_suggestions, $fields, array('id' => $suggestion_id), $formats, array('%d'));
}

public function add_suggestion_item($data) {
$now = time();
$this->wpdb->insert($this->table_items, array(
'suggestion_id' => absint($data['suggestion_id']),
'run_id' => absint($data['run_id']),
'post_id' => absint($data['post_id']),
'component' => sanitize_key($data['component']),
'item_type' => sanitize_key($data['item_type']),
'status' => isset($data['status']) ? sanitize_key($data['status']) : 'pending',
'original_value' => isset($data['original_value']) ? wp_json_encode($data['original_value']) : '',
'suggested_value' => isset($data['suggested_value']) ? wp_json_encode($data['suggested_value']) : '',
'rationale' => isset($data['rationale']) ? sanitize_textarea_field($data['rationale']) : '',
'confidence' => isset($data['confidence']) ? (float) $data['confidence'] : 0,
'diff_payload' => isset($data['diff_payload']) ? wp_json_encode($data['diff_payload']) : '',
'audit_meta' => isset($data['audit_meta']) ? wp_json_encode($data['audit_meta']) : '',
'decided_by' => isset($data['decided_by']) ? absint($data['decided_by']) : 0,
'decided_at' => isset($data['decided_at']) ? absint($data['decided_at']) : 0,
'applied_at' => 0,
'created_at' => $now,
'updated_at' => $now,
), array('%d','%d','%d','%s','%s','%s','%s','%s','%s','%f','%s','%s','%d','%d','%d','%d','%d'));
return (int) $this->wpdb->insert_id;
}

public function get_pending_suggestions($args = array()) {
$defaults = array(
'per_page' => 20,
'page' => 1,
'search' => '',
'status' => 'pending',
);
$args = wp_parse_args($args, $defaults);
$offset = max(0, ((int) $args['page'] - 1) * (int) $args['per_page']);
$where = array('s.status = %s');
$params = array(sanitize_key($args['status']));
if (!empty($args['search'])) {
$where[] = '(p.post_title LIKE %s OR s.priority LIKE %s OR s.severity LIKE %s)';
$term = '%' . $this->wpdb->esc_like($args['search']) . '%';
$params[] = $term;
$params[] = $term;
$params[] = $term;
}
$where_sql = implode(' AND ', $where);
$query = "SELECT s.*, p.post_title, p.post_status, COUNT(i.id) AS pending_items
FROM {$this->table_suggestions} s
INNER JOIN {$this->wpdb->posts} p ON p.ID = s.post_id
LEFT JOIN {$this->table_items} i ON i.suggestion_id = s.id AND i.status = 'pending'
WHERE {$where_sql}
GROUP BY s.id
HAVING pending_items > 0
ORDER BY s.updated_at DESC
LIMIT %d OFFSET %d";
$params[] = (int) $args['per_page'];
$params[] = $offset;
$items = $this->wpdb->get_results($this->wpdb->prepare($query, $params));

$count_query = "SELECT COUNT(DISTINCT s.id)
FROM {$this->table_suggestions} s
INNER JOIN {$this->wpdb->posts} p ON p.ID = s.post_id
INNER JOIN {$this->table_items} i ON i.suggestion_id = s.id AND i.status = 'pending'
WHERE {$where_sql}";
$total = (int) $this->wpdb->get_var($this->wpdb->prepare($count_query, array_slice($params, 0, count($params) - 2)));

return array(
'items' => $items,
'total' => $total,
'pages' => max(1, (int) ceil($total / max(1, (int) $args['per_page']))),
'current_page' => (int) $args['page'],
);
}

public function get_suggestion_detail($suggestion_id) {
$suggestion_id = absint($suggestion_id);
if (!$suggestion_id) {
return null;
}
$suggestion = $this->wpdb->get_row($this->wpdb->prepare(
"SELECT s.*, p.post_title, p.post_excerpt, p.post_content FROM {$this->table_suggestions} s INNER JOIN {$this->wpdb->posts} p ON p.ID = s.post_id WHERE s.id = %d",
$suggestion_id
));
if (!$suggestion) {
return null;
}
$items = $this->wpdb->get_results($this->wpdb->prepare("SELECT * FROM {$this->table_items} WHERE suggestion_id = %d ORDER BY component ASC, id ASC", $suggestion_id));
return array(
'suggestion' => $suggestion,
'items' => $items,
);
}

public function update_item_status($item_id, $status, $audit = array()) {
$item_id = absint($item_id);
$status = sanitize_key($status);
if (!$item_id || empty($status)) {
return false;
}
$fields = array(
'status' => $status,
'updated_at' => time(),
'audit_meta' => wp_json_encode($audit),
'decided_by' => isset($audit['user_id']) ? absint($audit['user_id']) : 0,
'decided_at' => time(),
);
$formats = array('%s','%d','%s','%d','%d');
if ('applied' === $status) {
$fields['applied_at'] = time();
$formats[] = '%d';
}
return false !== $this->wpdb->update($this->table_items, $fields, array('id' => $item_id), $formats, array('%d'));
}

public function get_items_by_ids($item_ids) {
$item_ids = array_values(array_filter(array_map('absint', (array) $item_ids)));
if (empty($item_ids)) {
return array();
}
$placeholders = implode(',', array_fill(0, count($item_ids), '%d'));
$query = "SELECT * FROM {$this->table_items} WHERE id IN ($placeholders)";
return $this->wpdb->get_results($this->wpdb->prepare($query, ...$item_ids));
}

public function get_plugin_generated_post_ids($post_ids) {
$post_ids = array_values(array_filter(array_map('absint', (array) $post_ids)));
if (empty($post_ids)) {
return array();
}
$placeholders = implode(',', array_fill(0, count($post_ids), '%d'));
$history_query = "SELECT DISTINCT post_id FROM {$this->table_history} WHERE post_id IN ($placeholders)";
$history_ids = $this->wpdb->get_col($this->wpdb->prepare($history_query, ...$post_ids));

$postmeta_table = $this->wpdb->postmeta;
$meta_keys = array('aips_post_generation_component_statuses', 'aips_post_generation_had_partial', '_aips_trending_topic_id');
$meta_placeholders = implode(',', array_fill(0, count($meta_keys), '%s'));
$meta_query = "SELECT DISTINCT post_id FROM {$postmeta_table} WHERE post_id IN ($placeholders) AND meta_key IN ($meta_placeholders)";
$args = array_merge($post_ids, $meta_keys);
$meta_ids = $this->wpdb->get_col($this->wpdb->prepare($meta_query, ...$args));

return array_values(array_unique(array_map('intval', array_merge((array) $history_ids, (array) $meta_ids))));
}

public function recalculate_suggestion_status($suggestion_id) {
$suggestion_id = absint($suggestion_id);
if (!$suggestion_id) {
return false;
}
$counts = $this->wpdb->get_row($this->wpdb->prepare(
"SELECT
SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) AS pending_count,
SUM(CASE WHEN status = 'applied' THEN 1 ELSE 0 END) AS applied_count,
SUM(CASE WHEN status IN ('dismissed','rejected') THEN 1 ELSE 0 END) AS dismissed_count
 FROM {$this->table_items}
 WHERE suggestion_id = %d",
$suggestion_id
));
$pending = isset($counts->pending_count) ? (int) $counts->pending_count : 0;
$applied = isset($counts->applied_count) ? (int) $counts->applied_count : 0;
$dismissed = isset($counts->dismissed_count) ? (int) $counts->dismissed_count : 0;
$status = 'pending';
if ($pending <= 0 && $applied > 0) {
$status = 'applied';
} elseif ($pending <= 0 && $dismissed > 0 && $applied <= 0) {
$status = 'dismissed';
} elseif ($pending <= 0 && $applied <= 0 && $dismissed <= 0) {
$status = 'closed';
}
return $this->update_suggestion($suggestion_id, array(
'status' => $status,
'applied_items_count' => $applied,
'dismissed_items_count' => $dismissed,
));
}

public function mark_suggestion_as($suggestion_id, $status) {
return $this->update_suggestion(absint($suggestion_id), array('status' => sanitize_key($status)));
}

public function is_plugin_generated_post($post_id) {
$post_id = absint($post_id);
if (!$post_id) {
return false;
}
$linked = (int) $this->wpdb->get_var($this->wpdb->prepare(
"SELECT COUNT(*) FROM {$this->table_history} WHERE post_id = %d",
$post_id
));
if ($linked > 0) {
return true;
}
$meta_keys = array('aips_post_generation_component_statuses', 'aips_post_generation_had_partial', '_aips_trending_topic_id');
foreach ($meta_keys as $meta_key) {
$value = get_post_meta($post_id, $meta_key, true);
if ('' !== (string) $value) {
return true;
}
}
return false;
}
}
