<?php
/**
 * Live Coverage Service
 *
 * Supports continuously revised stories that preserve a canonical article
 * identity while recording ordered update history in the existing history log.
 *
 * @package AI_Post_Scheduler
 * @since 2.0.0
 */

if (!defined('ABSPATH')) {
	exit;
}

class AIPS_Live_Coverage_Service {

	const META_LIVE_FLAG = 'aips_live_story_flag';
	const META_THREAD_ID = 'aips_live_story_thread_id';
	const META_PARENT_ID = 'aips_live_story_parent_post_id';
	const META_STATUS = 'aips_live_story_status';
	const META_LAST_UPDATE_AT = 'aips_live_story_last_update_at';
	const META_LAST_UPDATE_TYPE = 'aips_live_story_last_update_type';
	const META_MAJOR_COUNT = 'aips_live_story_major_update_count';
	const META_MINOR_COUNT = 'aips_live_story_minor_update_count';

	/**
	 * @var AIPS_History_Service
	 */
	private $history_service;

	/**
	 * @var AIPS_History_Repository
	 */
	private $history_repository;

	/**
	 * @var AIPS_Component_Regeneration_Service
	 */
	private $component_regeneration_service;

	/**
	 * @var AIPS_Post_Manager
	 */
	private $post_manager;

	public function __construct(
		$history_service = null,
		$history_repository = null,
		$component_regeneration_service = null,
		$post_manager = null
	) {
		$this->history_service = $history_service ?: new AIPS_History_Service();
		$this->history_repository = $history_repository ?: new AIPS_History_Repository();
		$this->component_regeneration_service = $component_regeneration_service ?: new AIPS_Component_Regeneration_Service();
		$this->post_manager = $post_manager ?: new AIPS_Post_Manager();
	}

	/**
	 * Apply or update live-story metadata on generated content.
	 *
	 * @param int   $post_id Post ID.
	 * @param array $args Metadata arguments.
	 * @return array Normalized metadata.
	 */
	public function update_generated_content_metadata($post_id, $args = array()) {
		$post_id = absint($post_id);
		if (!$post_id) {
			return array();
		}

		$post = get_post($post_id);
		if (!$post) {
			return array();
		}

		$current = $this->get_story_metadata($post_id);
		$is_live_story = isset($args['is_live_story']) ? (bool) $args['is_live_story'] : $current['is_live_story'];
		$thread_identifier = isset($args['thread_identifier']) ? $this->normalize_thread_identifier($args['thread_identifier']) : $current['thread_identifier'];
		$parent_story_id = isset($args['parent_story_id']) ? absint($args['parent_story_id']) : $current['parent_story_id'];
		$story_status = isset($args['story_status']) ? $this->normalize_story_status($args['story_status']) : $current['story_status'];

		if ('' === $thread_identifier) {
			$thread_source = isset($args['thread_source']) ? $args['thread_source'] : $post->post_title;
			$thread_identifier = $this->normalize_thread_identifier($thread_source);
		}

		if (!$parent_story_id) {
			$parent_story_id = $post_id;
		}

		update_post_meta($post_id, self::META_LIVE_FLAG, $is_live_story ? '1' : '0');
		update_post_meta($post_id, self::META_THREAD_ID, $thread_identifier);
		update_post_meta($post_id, self::META_PARENT_ID, $parent_story_id);
		update_post_meta($post_id, self::META_STATUS, $story_status);

		return $this->get_story_metadata($post_id);
	}

	/**
	 * Get normalized live-story metadata for a post.
	 *
	 * @param int $post_id Post ID.
	 * @return array
	 */
	public function get_story_metadata($post_id) {
		$post_id = absint($post_id);
		if (!$post_id) {
			return array(
				'is_live_story' => false,
				'thread_identifier' => '',
				'parent_story_id' => 0,
				'story_status' => '',
				'last_update_at' => '',
				'last_update_type' => '',
				'major_update_count' => 0,
				'minor_update_count' => 0,
			);
		}

		return array(
			'is_live_story' => '1' === (string) get_post_meta($post_id, self::META_LIVE_FLAG, true),
			'thread_identifier' => (string) get_post_meta($post_id, self::META_THREAD_ID, true),
			'parent_story_id' => absint(get_post_meta($post_id, self::META_PARENT_ID, true)),
			'story_status' => $this->normalize_story_status(get_post_meta($post_id, self::META_STATUS, true)),
			'last_update_at' => (string) get_post_meta($post_id, self::META_LAST_UPDATE_AT, true),
			'last_update_type' => (string) get_post_meta($post_id, self::META_LAST_UPDATE_TYPE, true),
			'major_update_count' => absint(get_post_meta($post_id, self::META_MAJOR_COUNT, true)),
			'minor_update_count' => absint(get_post_meta($post_id, self::META_MINOR_COUNT, true)),
		);
	}

	/**
	 * Append a timestamped update block to an existing canonical story.
	 *
	 * @param int   $post_id Post ID.
	 * @param int   $history_id History ID.
	 * @param array $args Update details.
	 * @return array|WP_Error
	 */
	public function append_update_block($post_id, $history_id, $args = array()) {
		$post_id = absint($post_id);
		$history_id = absint($history_id);
		$post = get_post($post_id);
		if (!$post) {
			return new WP_Error('missing_post', __('Post not found.', 'ai-post-scheduler'));
		}

		$update_brief = isset($args['update_brief']) ? sanitize_textarea_field(wp_unslash($args['update_brief'])) : '';
		if ('' === $update_brief) {
			return new WP_Error('missing_update_brief', __('An update brief is required.', 'ai-post-scheduler'));
		}

		$published_at = isset($args['published_at']) && '' !== $args['published_at'] ? sanitize_text_field($args['published_at']) : current_time('mysql');
		$published_at_ts = strtotime($published_at);
		if (false === $published_at_ts) {
			$published_at = current_time('mysql');
			$published_at_ts = strtotime($published_at);
		}

		$is_major_update = !empty($args['is_major_update']);
		$changed_sections = isset($args['changed_sections']) ? $this->normalize_changed_sections($args['changed_sections']) : array('update_block');
		$editor_user_id = isset($args['editor_user_id']) ? absint($args['editor_user_id']) : get_current_user_id();
		$update_reason = isset($args['update_reason']) ? sanitize_text_field(wp_unslash($args['update_reason'])) : $update_brief;
		$metadata = $this->update_generated_content_metadata($post_id, $args);

		$timestamp_label = date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $published_at_ts);
		$update_block = "\n\n<!-- aips-live-update:start -->\n";
		$update_block .= '<section class="aips-live-update-block" data-update-type="' . esc_attr($is_major_update ? 'major' : 'minor') . '">';
		$update_block .= '<h3>' . esc_html(sprintf(__('Update — %s', 'ai-post-scheduler'), $timestamp_label)) . '</h3>';
		$update_block .= '<p>' . esc_html($update_brief) . '</p>';
		$update_block .= '</section>';
		$update_block .= "\n<!-- aips-live-update:end -->";

		$result = wp_update_post(array(
			'ID' => $post_id,
			'post_content' => (string) $post->post_content . $update_block,
		), true);

		if (is_wp_error($result)) {
			return $result;
		}

		$this->bump_update_counters($post_id, $is_major_update, $published_at);
		$this->record_live_event(
			$post_id,
			$history_id,
			__('Live story update appended', 'ai-post-scheduler'),
			'live_story_update_appended',
			$update_reason,
			$changed_sections,
			$editor_user_id,
			$published_at,
			$metadata,
			array(
				'update_brief' => $update_brief,
				'update_type' => $is_major_update ? 'major' : 'minor',
			)
		);

		return array(
			'post_id' => $post_id,
			'content' => get_post_field('post_content', $post_id),
			'metadata' => $this->get_story_metadata($post_id),
		);
	}

	/**
	 * Regenerate selected live-story sections while keeping the same post ID.
	 *
	 * @param int   $post_id Post ID.
	 * @param int   $history_id History ID.
	 * @param array $args Request arguments.
	 * @return array|WP_Error
	 */
	public function regenerate_selected_sections($post_id, $history_id, $args = array()) {
		$post_id = absint($post_id);
		$history_id = absint($history_id);
		$post = get_post($post_id);
		if (!$post) {
			return new WP_Error('missing_post', __('Post not found.', 'ai-post-scheduler'));
		}

		$sections = isset($args['sections']) ? $this->normalize_changed_sections($args['sections']) : array();
		if (empty($sections)) {
			return new WP_Error('missing_sections', __('Select at least one section to regenerate.', 'ai-post-scheduler'));
		}

		$context = $this->component_regeneration_service->get_generation_context($history_id);
		if (is_wp_error($context)) {
			return $context;
		}

		$context['post_id'] = $post_id;
		$context['history_id'] = $history_id;
		$context['current_title'] = $post->post_title;
		$context['current_excerpt'] = $post->post_excerpt;
		$context['current_content'] = $post->post_content;

		$post_update = array('ID' => $post_id);
		$changed_sections = array();
		$response = array();

		if (in_array('headline', $sections, true)) {
			$new_title = $this->component_regeneration_service->regenerate_title($context);
			if (is_wp_error($new_title)) {
				return $new_title;
			}
			$post_update['post_title'] = sanitize_text_field($new_title);
			$response['headline'] = $post_update['post_title'];
			$changed_sections[] = 'headline';
		}

		if (in_array('top_summary', $sections, true)) {
			$new_excerpt = $this->component_regeneration_service->regenerate_excerpt($context);
			if (is_wp_error($new_excerpt)) {
				return $new_excerpt;
			}
			$post_update['post_excerpt'] = sanitize_textarea_field($new_excerpt);
			$response['top_summary'] = $post_update['post_excerpt'];
			$changed_sections[] = 'top_summary';
		}

		if (count($post_update) > 1) {
			$result = wp_update_post($post_update, true);
			if (is_wp_error($result)) {
				return $result;
			}
		}

		$this->post_manager->reconcile_generation_status_meta_from_post($post_id);
		$published_at = current_time('mysql');
		$metadata = $this->update_generated_content_metadata($post_id, $args);
		$is_major_update = !empty($args['is_major_update']) || count($changed_sections) > 1;
		$this->bump_update_counters($post_id, $is_major_update, $published_at);

		$update_reason = isset($args['update_reason']) ? sanitize_text_field(wp_unslash($args['update_reason'])) : __('Live story section refresh', 'ai-post-scheduler');
		$editor_user_id = isset($args['editor_user_id']) ? absint($args['editor_user_id']) : get_current_user_id();
		$this->record_live_event(
			$post_id,
			$history_id,
			__('Live story sections regenerated', 'ai-post-scheduler'),
			'live_story_sections_regenerated',
			$update_reason,
			$changed_sections,
			$editor_user_id,
			$published_at,
			$metadata,
			array(
				'update_type' => $is_major_update ? 'major' : 'minor',
			)
		);

		$response['post_id'] = $post_id;
		$response['metadata'] = $this->get_story_metadata($post_id);
		return $response;
	}

	/**
	 * Reopen a previously published story when a new development matches the same thread.
	 *
	 * @param string $thread_identifier Thread identifier.
	 * @param array  $args Reopen context.
	 * @return array|null
	 */
	public function reopen_matching_story($thread_identifier, $args = array()) {
		$match = $this->find_matching_story_by_thread($thread_identifier);
		if (!$match) {
			return null;
		}

		$post_id = (int) $match->ID;
		$published_at = current_time('mysql');
		$metadata = $this->update_generated_content_metadata($post_id, array_merge($args, array(
			'is_live_story' => true,
			'story_status' => 'developing',
			'thread_identifier' => $thread_identifier,
			'parent_story_id' => absint(get_post_meta($post_id, self::META_PARENT_ID, true)) ?: $post_id,
		)));
		$this->record_live_event(
			$post_id,
			isset($args['history_id']) ? absint($args['history_id']) : 0,
			__('Live story reopened for a matching thread', 'ai-post-scheduler'),
			'live_story_reopened',
			isset($args['update_reason']) ? sanitize_text_field($args['update_reason']) : __('New development matched existing thread', 'ai-post-scheduler'),
			array('thread_match'),
			isset($args['editor_user_id']) ? absint($args['editor_user_id']) : get_current_user_id(),
			$published_at,
			$metadata,
			array(
				'matched_post_id' => $post_id,
				'matched_post_title' => get_the_title($post_id),
			)
		);

		return array(
			'post_id' => $post_id,
			'post_title' => get_the_title($post_id),
			'edit_link' => get_edit_post_link($post_id, 'raw'),
			'thread_identifier' => $metadata['thread_identifier'],
			'story_status' => $metadata['story_status'],
		);
	}

	/**
	 * Add live-thread metadata to intake/topic payloads.
	 *
	 * @param array  $metadata Topic metadata.
	 * @param string $thread_source Source string for the thread identifier.
	 * @return array
	 */
	public function decorate_intake_metadata($metadata, $thread_source) {
		if (!is_array($metadata)) {
			$metadata = array();
		}

		$thread_identifier = $this->normalize_thread_identifier(isset($metadata['thread_identifier']) ? $metadata['thread_identifier'] : $thread_source);
		$metadata['thread_identifier'] = $thread_identifier;
		$metadata['live_reopen_candidate'] = false;

		if ('' === $thread_identifier) {
			return $metadata;
		}

		$match = $this->find_matching_story_by_thread($thread_identifier);
		if ($match) {
			$metadata['live_reopen_candidate'] = true;
			$metadata['matched_story'] = array(
				'post_id' => (int) $match->ID,
				'post_title' => get_the_title($match->ID),
				'edit_link' => get_edit_post_link($match->ID, 'raw'),
				'story_status' => (string) get_post_meta($match->ID, self::META_STATUS, true),
			);
		}

		return $metadata;
	}

	/**
	 * Get ordered live-story history for a post.
	 *
	 * @param int $post_id Post ID.
	 * @param int $history_id Optional history container ID.
	 * @param int $limit Maximum entries.
	 * @return array
	 */
	public function get_change_history($post_id, $history_id = 0, $limit = 50) {
		$post_id = absint($post_id);
		$history_id = absint($history_id);
		$limit = absint($limit);
		if (!$post_id) {
			return array();
		}

		if (!$history_id) {
			$history = $this->history_repository->get_by_post_id($post_id);
			$history_id = $history ? absint($history->id) : 0;
		}

		if (!$history_id) {
			return array();
		}

		$logs = $this->history_repository->get_logs_by_history_id($history_id, array(AIPS_History_Type::ACTIVITY));
		$entries = array();
		foreach ($logs as $log_entry) {
			$details = json_decode($log_entry->details, true);
			if (!is_array($details) || empty($details['context']['event_type'])) {
				continue;
			}

			$event_type = (string) $details['context']['event_type'];
			if (0 !== strpos($event_type, 'live_story_')) {
				continue;
			}

			$entries[] = array(
				'timestamp' => $log_entry->timestamp,
				'message' => isset($details['message']) ? $details['message'] : '',
				'update_reason' => isset($details['context']['update_reason']) ? $details['context']['update_reason'] : '',
				'changed_sections' => isset($details['context']['changed_sections']) && is_array($details['context']['changed_sections']) ? $details['context']['changed_sections'] : array(),
				'editor_user_id' => isset($details['context']['editor_user_id']) ? absint($details['context']['editor_user_id']) : 0,
				'published_at' => isset($details['context']['published_at']) ? $details['context']['published_at'] : $log_entry->timestamp,
				'event_type' => $event_type,
				'update_type' => isset($details['context']['update_type']) ? $details['context']['update_type'] : '',
			);
		}

		usort($entries, function($left, $right) {
			return strcmp((string) $left['published_at'], (string) $right['published_at']);
		});

		if ($limit > 0 && count($entries) > $limit) {
			$entries = array_slice($entries, -1 * $limit);
		}

		return $entries;
	}

	/**
	 * Count live/developing stories for dashboard indicators.
	 *
	 * @return array
	 */
	public function get_live_story_counts() {
		$live_posts = get_posts(array(
			'post_type' => 'post',
			'post_status' => array('publish', 'draft', 'future', 'pending'),
			'posts_per_page' => -1,
			'fields' => 'ids',
			'meta_query' => array(
				array(
					'key' => self::META_LIVE_FLAG,
					'value' => '1',
				),
			),
		));

		$counts = array(
			'total' => 0,
			'live' => 0,
			'developing' => 0,
		);

		foreach ($live_posts as $post_id) {
			$counts['total']++;
			$status = $this->normalize_story_status(get_post_meta($post_id, self::META_STATUS, true));
			if ('live' === $status) {
				$counts['live']++;
			} elseif ('developing' === $status) {
				$counts['developing']++;
			}
		}

		return $counts;
	}

	/**
	 * Get recent live/developing stories.
	 *
	 * @param int $limit Maximum posts.
	 * @return array
	 */
	public function get_recent_live_stories($limit = 5) {
		$posts = get_posts(array(
			'post_type' => 'post',
			'post_status' => array('publish', 'draft', 'future', 'pending'),
			'posts_per_page' => absint($limit),
			'meta_key' => self::META_LIVE_FLAG,
			'meta_value' => '1',
			'orderby' => 'modified',
			'order' => 'DESC',
		));

		$items = array();
		foreach ($posts as $post) {
			$metadata = $this->get_story_metadata($post->ID);
			$items[] = array(
				'post_id' => $post->ID,
				'post_title' => $post->post_title,
				'post_modified' => $post->post_modified,
				'edit_link' => get_edit_post_link($post->ID, 'raw'),
				'metadata' => $metadata,
			);
		}

		return $items;
	}

	/**
	 * Get live-story calendar events for a month.
	 *
	 * @param int $year Year.
	 * @param int $month Month.
	 * @return array
	 */
	public function get_live_story_calendar_events($year, $month) {
		$start = sprintf('%04d-%02d-01 00:00:00', $year, $month);
		$end = sprintf('%04d-%02d-%02d 23:59:59', $year, $month, date('t', strtotime($start)));

		$posts = get_posts(array(
			'post_type' => 'post',
			'post_status' => array('publish', 'draft', 'future', 'pending'),
			'posts_per_page' => -1,
			'meta_key' => self::META_LIVE_FLAG,
			'meta_value' => '1',
			'date_query' => array(
				'relation' => 'OR',
				array(
					'column' => 'post_modified',
					'after' => $start,
					'before' => $end,
					'inclusive' => true,
				),
				array(
					'column' => 'post_date',
					'after' => $start,
					'before' => $end,
					'inclusive' => true,
				),
			),
		));

		$events = array();
		foreach ($posts as $post) {
			$metadata = $this->get_story_metadata($post->ID);
			$event_time = !empty($metadata['last_update_at']) ? $metadata['last_update_at'] : $post->post_modified;
			$events[] = array(
				'id' => 'live-' . $post->ID,
				'title' => $post->post_title,
				'start' => $event_time,
				'template_id' => null,
				'template_name' => __('Live Story', 'ai-post-scheduler'),
				'frequency' => __('Rolling updates', 'ai-post-scheduler'),
				'topic' => $metadata['thread_identifier'],
				'category' => ucfirst($metadata['story_status'] ? $metadata['story_status'] : __('Live', 'ai-post-scheduler')),
				'author' => get_the_author_meta('display_name', $post->post_author),
				'event_kind' => 'live_story',
				'story_status' => $metadata['story_status'],
				'edit_link' => get_edit_post_link($post->ID, 'raw'),
			);
		}

		return $events;
	}

	/**
	 * Find a matching canonical story by thread identifier.
	 *
	 * @param string $thread_identifier Thread identifier.
	 * @return WP_Post|null
	 */
	public function find_matching_story_by_thread($thread_identifier) {
		$thread_identifier = $this->normalize_thread_identifier($thread_identifier);
		if ('' === $thread_identifier) {
			return null;
		}

		$posts = get_posts(array(
			'post_type' => 'post',
			'post_status' => array('publish', 'draft', 'future', 'pending'),
			'posts_per_page' => 1,
			'meta_query' => array(
				array(
					'key' => self::META_THREAD_ID,
					'value' => $thread_identifier,
				),
			),
			'orderby' => 'modified',
			'order' => 'DESC',
		));

		if (!empty($posts)) {
			return $posts[0];
		}

		return null;
	}

	private function normalize_story_status($status) {
		$status = sanitize_key((string) $status);
		if (!in_array($status, array('live', 'developing'), true)) {
			return '';
		}
		return $status;
	}

	private function normalize_thread_identifier($value) {
		$value = sanitize_title(wp_strip_all_tags((string) $value));
		return (string) $value;
	}

	private function normalize_changed_sections($sections) {
		$map = array(
			'headline' => 'headline',
			'title' => 'headline',
			'top_summary' => 'top_summary',
			'excerpt' => 'top_summary',
			'update_block' => 'update_block',
			'thread_match' => 'thread_match',
		);

		$sections = (array) $sections;
		$normalized = array();
		foreach ($sections as $section) {
			$section = sanitize_key((string) $section);
			if (isset($map[$section])) {
				$normalized[] = $map[$section];
			}
		}

		$normalized = array_values(array_unique($normalized));
		return $normalized;
	}

	private function bump_update_counters($post_id, $is_major_update, $published_at) {
		$meta_key = $is_major_update ? self::META_MAJOR_COUNT : self::META_MINOR_COUNT;
		$current = absint(get_post_meta($post_id, $meta_key, true));
		update_post_meta($post_id, $meta_key, $current + 1);
		update_post_meta($post_id, self::META_LAST_UPDATE_AT, $published_at);
		update_post_meta($post_id, self::META_LAST_UPDATE_TYPE, $is_major_update ? 'major' : 'minor');
	}

	private function record_live_event($post_id, $history_id, $message, $event_type, $update_reason, $changed_sections, $editor_user_id, $published_at, $metadata, $extra_context = array()) {
		$history_container = AIPS_History_Container::resolve_existing($this->history_repository, $post_id, $history_id);
		if (is_wp_error($history_container)) {
			$history_container = $this->history_service->create('live_story_update', array(
				'post_id' => $post_id,
			));
		}

		$context = array_merge(array(
			'event_type' => $event_type,
			'event_status' => 'success',
			'update_reason' => $update_reason,
			'changed_sections' => $changed_sections,
			'editor_user_id' => $editor_user_id,
			'published_at' => $published_at,
			'thread_identifier' => isset($metadata['thread_identifier']) ? $metadata['thread_identifier'] : '',
			'parent_story_id' => isset($metadata['parent_story_id']) ? absint($metadata['parent_story_id']) : 0,
			'story_status' => isset($metadata['story_status']) ? $metadata['story_status'] : '',
			'update_type' => !empty($extra_context['update_type']) ? $extra_context['update_type'] : '',
		), $extra_context);

		$history_container->record('activity', $message, null, null, $context);
	}
}
