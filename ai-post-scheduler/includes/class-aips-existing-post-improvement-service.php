<?php
/**
 * Existing Post Improvement scan service.
 *
 * @package AI_Post_Scheduler
 * @since 2.10.0
 */

if (!defined('ABSPATH')) {
exit;
}

class AIPS_Existing_Post_Improvement_Service {

/** @var AIPS_Existing_Post_Improvement_Repository */
private $repository;
/** @var AIPS_AI_Service_Interface */
private $ai_service;
/** @var AIPS_Logger_Interface */
private $logger;
/** @var AIPS_Existing_Post_Suggestion_Prompts */
private $prompts;

public function __construct(
?AIPS_Existing_Post_Improvement_Repository $repository = null,
?AIPS_AI_Service_Interface $ai_service = null,
?AIPS_Logger_Interface $logger = null,
?AIPS_Existing_Post_Suggestion_Prompts $prompts = null
) {
$container = AIPS_Container::get_instance();
$this->repository = $repository ?: new AIPS_Existing_Post_Improvement_Repository();
$this->ai_service = $ai_service ?: ($container->has(AIPS_AI_Service_Interface::class) ? $container->make(AIPS_AI_Service_Interface::class) : new AIPS_AI_Service());
$this->logger = $logger ?: ($container->has(AIPS_Logger_Interface::class) ? $container->make(AIPS_Logger_Interface::class) : new AIPS_Logger());
$this->prompts = $prompts ?: new AIPS_Existing_Post_Suggestion_Prompts();
}

public function process_due_schedules($limit = 5) {
$schedules = $this->repository->get_due_schedules(time(), $limit);
foreach ($schedules as $schedule) {
$this->run_schedule((int) $schedule->id, 'cron');
}
}

public function run_schedule($schedule_id, $trigger = 'manual') {
$schedule_id = absint($schedule_id);
$schedule = $this->repository->get_schedule_by_id($schedule_id);
if (!$schedule) {
return new WP_Error('schedule_not_found', __('Scan schedule not found.', 'ai-post-scheduler'));
}

$token = wp_generate_password(16, false, false);
if (!$this->repository->acquire_schedule_lock($schedule_id, $token, 300)) {
return new WP_Error('schedule_locked', __('Scan schedule is already running.', 'ai-post-scheduler'));
}

$run_id = $this->repository->create_run($schedule_id, 'running', $trigger);
$metrics = array(
'posts_scanned' => 0,
'suggestions_created' => 0,
'posts_skipped_unchanged' => 0,
'failures_count' => 0,
);

try {
$category_filters = json_decode((string) $schedule->category_filters, true);
if (!is_array($category_filters)) {
$category_filters = array();
}
$cursor = isset($schedule->run_cursor) ? absint($schedule->run_cursor) : 0;
$posts = $this->get_candidate_posts($category_filters, !empty($schedule->include_generated_posts), $cursor, 20);

foreach ($posts as $post) {
$metrics['posts_scanned']++;
$fingerprint = $this->compute_post_fingerprint($post);
$previous = $this->repository->get_latest_pending_suggestion_for_post($post->ID);
if ($previous && !empty($previous->content_hash) && hash_equals((string) $previous->content_hash, $fingerprint)) {
$metrics['posts_skipped_unchanged']++;
continue;
}

$suggestion_id = $this->repository->create_suggestion(array(
'post_id' => $post->ID,
'run_id' => $run_id,
'schedule_id' => $schedule_id,
'content_hash' => $fingerprint,
'freshness_marker' => gmdate('Y-m-d'),
'last_scanned_at' => time(),
));
if (!$suggestion_id) {
$metrics['failures_count']++;
continue;
}

$items = $this->build_suggestions_for_post($post);
foreach ($items as $item) {
$item['suggestion_id'] = $suggestion_id;
$item['run_id'] = $run_id;
$item['post_id'] = $post->ID;
$this->repository->add_suggestion_item($item);
$metrics['suggestions_created']++;
}
}

$next_run = wp_next_scheduled('aips_process_existing_post_scans');
if (!$next_run) {
$interval = wp_get_schedules();
$freq = isset($interval[$schedule->frequency]['interval']) ? (int) $interval[$schedule->frequency]['interval'] : DAY_IN_SECONDS;
$next_run = time() + max(HOUR_IN_SECONDS, $freq);
}

$this->repository->update_run($run_id, array_merge($metrics, array(
'status' => 'completed',
'completed_at' => time(),
)));
$this->repository->update_schedule($schedule_id, array(
'last_run' => time(),
'next_run' => (int) $next_run,
'run_cursor' => !empty($posts) ? (int) end($posts)->ID : $cursor,
'retry_count' => 0,
'last_error' => '',
));
} catch (Exception $e) {
$metrics['failures_count']++;
$this->repository->update_run($run_id, array_merge($metrics, array(
'status' => 'failed',
'error_summary' => $e->getMessage(),
'completed_at' => time(),
)));
$this->repository->update_schedule($schedule_id, array(
'retry_count' => (int) $schedule->retry_count + 1,
'last_error' => $e->getMessage(),
));
$this->logger->log('Existing post scan failed: ' . $e->getMessage(), 'error');
} finally {
$this->repository->release_schedule_lock($schedule_id, $token);
}

return $run_id;
}

public function apply_items($suggestion_id, $item_ids, $user_id) {
$detail = $this->repository->get_suggestion_detail($suggestion_id);
if (!$detail) {
return new WP_Error('suggestion_not_found', __('Suggestion not found.', 'ai-post-scheduler'));
}
$items = $this->repository->get_items_by_ids($item_ids);
$post_id = (int) $detail['suggestion']->post_id;
$post = get_post($post_id);
if (!$post) {
return new WP_Error('post_not_found', __('Post not found.', 'ai-post-scheduler'));
}

$post_update = array('ID' => $post_id);
$category_ids = wp_get_post_categories($post_id);
$applied = 0;
foreach ($items as $item) {
if ((int) $item->suggestion_id !== (int) $suggestion_id) {
continue;
}
$suggested_value = json_decode((string) $item->suggested_value, true);
switch ($item->component) {
case 'title':
if (is_string($suggested_value) && '' !== trim($suggested_value)) {
$post_update['post_title'] = $suggested_value;
}
break;
case 'excerpt':
if (is_string($suggested_value)) {
$post_update['post_excerpt'] = $suggested_value;
}
break;
case 'content':
if (is_string($suggested_value)) {
$post_update['post_content'] = $suggested_value;
}
break;
case 'categories':
if (is_array($suggested_value)) {
$category_ids = array_values(array_filter(array_map('absint', $suggested_value)));
}
break;
}
$this->repository->update_item_status((int) $item->id, 'applied', array(
'user_id' => absint($user_id),
'action' => 'apply',
'timestamp' => time(),
));
$applied++;
}

if (count($post_update) > 1) {
wp_update_post(wp_slash($post_update));
}
if (!empty($category_ids)) {
wp_set_post_categories($post_id, $category_ids, false);
}

$this->repository->recalculate_suggestion_status($suggestion_id);
return array('applied' => $applied);
}

public function dismiss_items($suggestion_id, $item_ids, $user_id) {
$items = $this->repository->get_items_by_ids($item_ids);
$count = 0;
foreach ($items as $item) {
if ((int) $item->suggestion_id !== (int) $suggestion_id) {
continue;
}
if ($this->repository->update_item_status((int) $item->id, 'dismissed', array(
'user_id' => absint($user_id),
'action' => 'dismiss',
'timestamp' => time(),
))) {
$count++;
}
}
$this->repository->recalculate_suggestion_status($suggestion_id);
return $count;
}

private function get_candidate_posts($category_filters, $include_generated_posts, $cursor = 0, $limit = 20) {
$args = array(
'post_type' => 'post',
'post_status' => 'publish',
'posts_per_page' => max(1, absint($limit)),
'orderby' => 'ID',
'order' => 'ASC',
'fields' => 'all',
'post__not_in' => array(),
);

if (!empty($cursor)) {
$args['date_query'] = array();
$args['post__not_in'] = array();
$args['post_parent__not_in'] = array();
$args['post__in'] = get_posts(array(
'post_type' => 'post',
'post_status' => 'publish',
'posts_per_page' => max(1, absint($limit * 2)),
'orderby' => 'ID',
'order' => 'ASC',
'fields' => 'ids',
'post__not_in' => array(),
'suppress_filters' => false,
'no_found_rows' => true,
'paged' => 1,
'offset' => 0,
));
}

if (!empty($category_filters)) {
$args['category__in'] = array_values(array_filter(array_map('absint', (array) $category_filters)));
}

$query = new WP_Query($args);
$posts = array();
if ($query->have_posts()) {
foreach ($query->posts as $post) {
if (!$include_generated_posts && $this->repository->is_plugin_generated_post($post->ID)) {
continue;
}
if (!empty($cursor) && (int) $post->ID <= (int) $cursor) {
continue;
}
$posts[] = $post;
}
}
return $posts;
}

private function compute_post_fingerprint($post) {
return hash('sha256', implode('|', array(
(string) $post->post_title,
(string) $post->post_excerpt,
(string) $post->post_content,
)));
}

private function build_suggestions_for_post($post) {
$categories = get_the_category((int) $post->ID);
$prompt = $this->prompts->build_post_scan_prompt($post, $categories);
$response = $this->ai_service->generate_json($prompt, array('temperature' => 0.2));
$items = array();
$decoded = array();
if (!is_wp_error($response) && is_array($response) && isset($response['suggestions']) && is_array($response['suggestions'])) {
$decoded = $response['suggestions'];
}

if (empty($decoded)) {
$decoded = $this->fallback_suggestions($post);
}

foreach ($decoded as $entry) {
$component = isset($entry['component']) ? sanitize_key($entry['component']) : '';
$item_type = isset($entry['item_type']) ? sanitize_key($entry['item_type']) : 'recommendation';
if (empty($component)) {
continue;
}
$original = $this->extract_original_value_for_component($post, $component);
$suggested = isset($entry['suggested_value']) ? $entry['suggested_value'] : '';
$items[] = array(
'component' => $component,
'item_type' => $item_type,
'status' => 'pending',
'original_value' => $original,
'suggested_value' => $suggested,
'rationale' => isset($entry['rationale']) ? sanitize_textarea_field($entry['rationale']) : '',
'confidence' => isset($entry['confidence']) ? (float) $entry['confidence'] : 0,
'diff_payload' => $this->build_diff_payload($original, $suggested),
'audit_meta' => array(
'priority' => isset($entry['priority']) ? sanitize_key($entry['priority']) : 'medium',
'severity' => isset($entry['severity']) ? sanitize_key($entry['severity']) : 'medium',
),
);
}

return $items;
}

private function fallback_suggestions($post) {
$suggestions = array();
$title = (string) $post->post_title;
if (strlen($title) < 30) {
$suggestions[] = array(
'component' => 'title',
'item_type' => 'expand',
'suggested_value' => $title . ' – Updated Guide',
'rationale' => __('Title appears short and may benefit from stronger context.', 'ai-post-scheduler'),
'confidence' => 0.45,
'priority' => 'medium',
'severity' => 'low',
);
}
$excerpt = (string) $post->post_excerpt;
if (empty($excerpt)) {
$suggestions[] = array(
'component' => 'excerpt',
'item_type' => 'rewrite',
'suggested_value' => wp_trim_words(wp_strip_all_tags((string) $post->post_content), 30, '...'),
'rationale' => __('Post has no excerpt and could benefit from one.', 'ai-post-scheduler'),
'confidence' => 0.6,
'priority' => 'high',
'severity' => 'medium',
);
}
return $suggestions;
}

private function extract_original_value_for_component($post, $component) {
switch ($component) {
case 'title':
return (string) $post->post_title;
case 'excerpt':
return (string) $post->post_excerpt;
case 'content':
return (string) $post->post_content;
case 'categories':
return wp_get_post_categories((int) $post->ID);
default:
return '';
}
}

private function build_diff_payload($original, $suggested) {
return array(
'original' => $original,
'suggested' => $suggested,
'original_preview' => is_scalar($original) ? wp_trim_words((string) $original, 24, '...') : wp_json_encode($original),
'suggested_preview' => is_scalar($suggested) ? wp_trim_words((string) $suggested, 24, '...') : wp_json_encode($suggested),
);
}
}
