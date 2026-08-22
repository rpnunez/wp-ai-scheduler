<?php
/**
 * Unified Schedule Service
 *
 * Aggregates all schedule types (template schedules, author topic generation,
 * author post generation) into a single normalised list for the Schedules admin page.
 *
 * @package AI_Post_Scheduler
 * @since 1.10.0
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Unified_Schedule_Service
 *
 * Provides a unified view of every scheduled process in the plugin,
 * regardless of which underlying database table stores it.
 */
class AIPS_Unified_Schedule_Service {

	/** Schedule type constants */
	const TYPE_TEMPLATE    = 'template_schedule';
	const TYPE_AUTHOR_TOPIC = 'author_topic_gen';
	const TYPE_AUTHOR_POST  = 'author_post_gen';

	/**
	 * @var AIPS_Schedule_Repository
	 */
	private $schedule_repository;

	/**
	 * @var AIPS_Authors_Repository
	 */
	private $authors_repository;

	/**
	 * @var AIPS_History_Repository
	 */
	private $history_repository;

	/**
	 * @var AIPS_Author_Topics_Repository
	 */
	private $author_topics_repository;

	/**
	 * @var AIPS_Author_Topic_Logs_Repository
	 */
	private $author_topic_logs_repository;

	/**
	 * Initialise the service and its dependencies.
	 */
	public function __construct() {
		$this->schedule_repository          = new AIPS_Schedule_Repository();
		$this->authors_repository           = new AIPS_Authors_Repository();
		$this->history_repository           = new AIPS_History_Repository();
		$this->author_topics_repository     = new AIPS_Author_Topics_Repository();
		$this->author_topic_logs_repository = new AIPS_Author_Topic_Logs_Repository();
	}

	/**
	 * Return all scheduled processes, optionally filtered by type.
	 *
	 * Each element of the returned array is a normalised associative array
	 * (see private helpers for structure).
	 *
	 * @param string $type_filter   Optional type constant to restrict results.
	 * @param bool   $include_stats Whether to run aggregate stats queries. Set to false
	 *                              for lightweight listings that don't display stats.
	 * @return array Sorted, normalised schedule rows.
	 */
	public function get_all($type_filter = '', $include_stats = true) {
		$schedules = array();

		if (empty($type_filter) || $type_filter === self::TYPE_TEMPLATE) {
			$schedules = array_merge($schedules, $this->get_template_schedules($include_stats));
		}
		if (empty($type_filter) || $type_filter === self::TYPE_AUTHOR_TOPIC) {
			$schedules = array_merge($schedules, $this->get_author_topic_schedules($include_stats));
		}
		if (empty($type_filter) || $type_filter === self::TYPE_AUTHOR_POST) {
			$schedules = array_merge($schedules, $this->get_author_post_schedules($include_stats));
		}

		// Sort by run proximity for better operator UX:
		// 1) active upcoming schedules (soonest first)
		// 2) active past-due schedules (least overdue first)
		// 3) inactive/unscheduled rows (last)
		$now_ts = AIPS_DateTime::now()->timestamp();
		usort($schedules, function ($a, $b) use ($now_ts) {
			$a_active = !empty($a['is_active']);
			$b_active = !empty($b['is_active']);

			$a_ts = !empty($a['next_run']) ? (int) $a['next_run'] : false;
			$b_ts = !empty($b['next_run']) ? (int) $b['next_run'] : false;

			$a_group = 2;
			if ($a_active && $a_ts !== false) {
				$a_group = ($a_ts >= $now_ts) ? 0 : 1;
			}

			$b_group = 2;
			if ($b_active && $b_ts !== false) {
				$b_group = ($b_ts >= $now_ts) ? 0 : 1;
			}

			if ($a_group !== $b_group) {
				return $a_group - $b_group;
			}

			if (($a_group === 0 || $a_group === 1) && $a_ts !== false && $b_ts !== false) {
				return $a_ts - $b_ts;
			}

			if (!empty($a['title']) && !empty($b['title'])) {
				return strcasecmp((string) $a['title'], (string) $b['title']);
			}

			return 0;
		});

		return $schedules;
	}

	/**
	 * Toggle the active status of any schedule type.
	 *
	 * @param int    $id        Numeric ID.
	 * @param string $type      One of the TYPE_* constants.
	 * @param int    $is_active 1 to enable, 0 to pause.
	 * @return bool|int False on failure, truthy on success.
	 */
	public function toggle($id, $type, $is_active) {
		$is_active = (int) $is_active;

		switch ($type) {
			case self::TYPE_TEMPLATE:
				$scheduler = new AIPS_Scheduler();
				return $scheduler->toggle_active($id, $is_active);

			case self::TYPE_AUTHOR_TOPIC:
				return $this->authors_repository->update_topic_generation_active($id, $is_active);

			case self::TYPE_AUTHOR_POST:
				return $this->authors_repository->update_post_generation_active($id, $is_active);

			default:
				return false;
		}
	}

	/**
	 * Run a specific schedule immediately.
	 *
	 * Return value varies by type:
	 *  – template_schedule : array of post IDs (or WP_Error)
	 *  – author_topic_gen  : array of topics (or WP_Error)
	 *  – author_post_gen   : array of generated post IDs (or WP_Error)
	 *
	 * @param int      $id       Numeric ID.
	 * @param string   $type     One of the TYPE_* constants.
	 * @param int|null $quantity Optional quantity override for author_post_gen.
	 * @param bool     $advance_schedule Whether this run consumes the next scheduled occurrence.
	 * @return mixed
	 */
	public function run_now($id, $type, $quantity = null, $advance_schedule = true) {
		switch ($type) {
			case self::TYPE_TEMPLATE:
				$scheduler = new AIPS_Scheduler();
				return $scheduler->run_schedule_now($id, null, $advance_schedule);

			case self::TYPE_AUTHOR_TOPIC:
				$scheduler = new AIPS_Author_Topics_Scheduler();
				return $scheduler->generate_now($id, $advance_schedule);

			case self::TYPE_AUTHOR_POST:
				$generator = new AIPS_Author_Post_Generator();
				$author    = $this->authors_repository->get_by_id($id);
				if (!$author) {
					return new WP_Error('not_found', __('Author not found.', 'ai-post-scheduler'));
				}
				return $generator->generate_posts_for_author($author, $quantity, 'manual', $advance_schedule);

			default:
				return new WP_Error('invalid_type', __('Invalid schedule type.', 'ai-post-scheduler'));
		}
	}

	/**
	 * Delete a specific schedule when the schedule type supports deletion.
	 *
	 * Currently only template schedules are deletable.
	 *
	 * @param int    $id   Numeric ID.
	 * @param string $type One of the TYPE_* constants.
	 * @return true|WP_Error
	 */
	public function delete($id, $type) {
		switch ($type) {
			case self::TYPE_TEMPLATE:
				if ($this->schedule_repository->delete($id)) {
					return true;
				}

				return new WP_Error(
					'delete_failed',
					__('Failed to delete schedule.', 'ai-post-scheduler')
				);

			case self::TYPE_AUTHOR_TOPIC:
			case self::TYPE_AUTHOR_POST:
				return new WP_Error(
					'not_deletable',
					__('This schedule type cannot be deleted.', 'ai-post-scheduler')
				);

			default:
				return new WP_Error(
					'invalid_type',
					__('Invalid schedule type.', 'ai-post-scheduler')
				);
		}
	}

	/**
	 * Get run-history log entries for a schedule.
	 *
	 * @param int    $id    Numeric ID.
	 * @param string $type  One of the TYPE_* constants.
	 * @param int    $limit Max entries to return; 0 = no limit.
	 * @return array Normalised log entry arrays.
	 */
	public function get_history($id, $type, $limit = 0) {
		$limit = absint($limit);

		switch ($type) {
			case self::TYPE_TEMPLATE:
				$schedule = $this->schedule_repository->get_by_id($id);
				if (!$schedule || empty($schedule->schedule_history_id)) {
					return array();
				}
				$logs = $this->history_repository->get_logs_by_history_id(
					absint($schedule->schedule_history_id),
					array(AIPS_History_Type::ACTIVITY, AIPS_History_Type::ERROR),
					$limit
				);
				return $this->expand_and_format_logs($logs);

			case self::TYPE_AUTHOR_TOPIC:
				$logs = $this->history_repository->get_author_schedule_logs_by_event_types(
					$id,
					array('author_topic_generation'),
					$limit > 0 ? $limit : 100
				);
				return $this->expand_and_format_logs($logs);

			case self::TYPE_AUTHOR_POST:
				$logs = $this->history_repository->get_author_schedule_logs_by_event_types(
					$id,
					array('topic_post_generation'),
					$limit > 0 ? $limit : 100
				);
				return $this->expand_and_format_logs($logs);

			default:
				return array();
		}
	}

	/**
	 * Get detailed, paginated, filtered run-history log entries and statistics for a schedule.
	 *
	 * @param int    $id   Numeric ID.
	 * @param string $type One of the TYPE_* constants.
	 * @param array  $args Filter & pagination parameters.
	 * @return array Payload containing entries, stats, and pagination metadata.
	 */
	public function get_history_data($id, $type, $args = array()) {
		$id           = absint($id);
		$page         = isset($args['page']) ? max(1, absint($args['page'])) : 1;
		$per_page     = isset($args['per_page']) ? max(1, absint($args['per_page'])) : 10;
		$search       = isset($args['search']) ? sanitize_text_field(wp_unslash($args['search'])) : '';
		$event_filter = isset($args['event_filter']) ? sanitize_key($args['event_filter']) : 'all';
		$date_range   = isset($args['date_range']) ? sanitize_key($args['date_range']) : 'all';
		$date_from    = isset($args['date_from']) ? sanitize_text_field($args['date_from']) : '';
		$date_to      = isset($args['date_to']) ? sanitize_text_field($args['date_to']) : '';

		$logs = array();

		switch ($type) {
			case self::TYPE_TEMPLATE:
				$schedule = $this->schedule_repository->get_by_id($id);
				if ($schedule && !empty($schedule->schedule_history_id)) {
					$logs = $this->history_repository->get_logs_by_history_id(
						absint($schedule->schedule_history_id),
						array(AIPS_History_Type::ACTIVITY, AIPS_History_Type::ERROR),
						0
					);
				}
				break;

			case self::TYPE_AUTHOR_TOPIC:
				$logs = $this->history_repository->get_author_schedule_logs_by_event_types(
					$id,
					array('author_topic_generation'),
					500
				);
				break;

			case self::TYPE_AUTHOR_POST:
				$logs = $this->history_repository->get_author_schedule_logs_by_event_types(
					$id,
					array('topic_post_generation'),
					500
				);
				break;
		}

		$all_entries = $this->expand_and_format_logs($logs);

		// Calculate Statistics (All Time, Week, Month, Execution count)
		$now       = current_time('timestamp');
		$week_ago  = $now - (7 * DAY_IN_SECONDS);
		$month_ago = $now - (30 * DAY_IN_SECONDS);

		$posts_this_week  = 0;
		$posts_this_month = 0;
		$posts_all_time   = 0;
		$total_runs       = 0;
		$successful_runs  = 0;

		foreach ($all_entries as $entry) {
			if (!empty($entry['is_post'])) {
				$posts_all_time++;
				if ($entry['timestamp'] >= $week_ago) {
					$posts_this_week++;
				}
				if ($entry['timestamp'] >= $month_ago) {
					$posts_this_month++;
				}
			}

			if (in_array($entry['event_type'], array('schedule_executed', 'manual_schedule_started', 'manual_schedule_completed', 'manual_schedule_failed', 'schedule_failed'), true)) {
				$total_runs++;
				if ($entry['event_status'] !== 'failed') {
					$successful_runs++;
				}
			}
		}

		$stats = array(
			'posts_this_week'  => $posts_this_week,
			'posts_this_month' => $posts_this_month,
			'posts_all_time'   => $posts_all_time,
			'total_runs'       => $total_runs,
			'success_rate'     => $total_runs > 0 ? round(($successful_runs / $total_runs) * 100) . '%' : '100%',
		);

		// Filter entries
		$filtered_entries = array();
		foreach ($all_entries as $entry) {
			// Date Range Filter
			if ($date_range === 'week' && $entry['timestamp'] < $week_ago) {
				continue;
			}
			if (($date_range === 'month' || $date_range === '30days') && $entry['timestamp'] < $month_ago) {
				continue;
			}
			if ($date_range === 'custom') {
				if (!empty($date_from) && $entry['timestamp'] < strtotime($date_from . ' 00:00:00')) {
					continue;
				}
				if (!empty($date_to) && $entry['timestamp'] > strtotime($date_to . ' 23:59:59')) {
					continue;
				}
			}

			// Event Filter
			if ($event_filter === 'posts' && empty($entry['is_post'])) {
				continue;
			}
			if ($event_filter === 'manual' && strpos($entry['event_type'], 'manual_') === false) {
				continue;
			}
			if ($event_filter === 'system' && (!empty($entry['is_post']) || strpos($entry['event_type'], 'manual_') !== false)) {
				continue;
			}

			// Text Search Filter
			if (!empty($search)) {
				$needle   = mb_strtolower($search);
				$haystack = mb_strtolower($entry['name'] . ' ' . $entry['message'] . ' ' . $entry['event_type']);
				if (mb_strpos($haystack, $needle) === false) {
					continue;
				}
			}

			$filtered_entries[] = $entry;
		}

		// Pagination
		$total_entries = count($filtered_entries);
		$total_pages   = max(1, (int) ceil($total_entries / $per_page));
		$page          = min($page, $total_pages);
		$offset        = ($page - 1) * $per_page;

		$paged_entries = array_slice($filtered_entries, $offset, $per_page);

		return array(
			'entries'    => $paged_entries,
			'stats'      => $stats,
			'pagination' => array(
				'current_page'  => $page,
				'per_page'      => $per_page,
				'total_entries' => $total_entries,
				'total_pages'   => $total_pages,
			),
		);
	}

	/**
	 * Expand multi-post execution logs into discrete individual post entries.
	 * Decodes HTML entities and formats timestamps.
	 *
	 * @param array $logs Raw log objects.
	 * @return array Formatted entries.
	 */
	private function expand_and_format_logs($logs) {
		$entries = array();

		foreach ($logs as $log) {
			$details = array();
			if (!empty($log->details)) {
				$decoded = json_decode($log->details, true);
				if (is_array($decoded)) {
					$details = $decoded;
				}
			}

			$input     = isset($details['input']) && is_array($details['input']) ? $details['input'] : array();
			$context   = isset($details['context']) && is_array($details['context']) ? $details['context'] : array();
			$raw_msg   = isset($details['message']) ? $details['message'] : '';
			$clean_msg = html_entity_decode($raw_msg, ENT_QUOTES | ENT_HTML5, 'UTF-8');

			$event_type   = isset($input['event_type']) ? esc_html($input['event_type']) : '';
			$event_status = isset($input['event_status']) ? esc_html($input['event_status']) : '';
			$ts           = (int) $log->timestamp;
			$date_fmt     = wp_date(get_option('date_format') . ' ' . get_option('time_format'), $ts);

			// Extract post ID or post IDs array from context or details
			$post_ids = array();
			if (isset($context['post_id'])) {
				if (is_array($context['post_id'])) {
					$post_ids = array_map('absint', $context['post_id']);
				} elseif (is_numeric($context['post_id']) && (int) $context['post_id'] > 0) {
					$post_ids = array((int) $context['post_id']);
				}
			} elseif (isset($details['post_id'])) {
				if (is_array($details['post_id'])) {
					$post_ids = array_map('absint', $details['post_id']);
				} elseif (is_numeric($details['post_id']) && (int) $details['post_id'] > 0) {
					$post_ids = array((int) $details['post_id']);
				}
			}

			// Filter out invalid IDs
			$post_ids = array_filter($post_ids);

			if (!empty($post_ids)) {
				foreach ($post_ids as $post_id) {
					$post = get_post($post_id);
					if ($post) {
						$post_title  = html_entity_decode(get_the_title($post), ENT_QUOTES | ENT_HTML5, 'UTF-8');
						$post_status = $post->post_status;
						$view_url    = get_permalink($post_id);
						$edit_url    = get_edit_post_link($post_id, '');

						$entries[] = array(
							'id'             => absint($log->id) . '_' . absint($post_id),
							'raw_id'         => absint($log->id),
							'timestamp'      => $ts,
							'formatted_date' => $date_fmt,
							'name'           => $post_title ? $post_title : sprintf(__('Post #%d', 'ai-post-scheduler'), $post_id),
							'event_type'     => ($post_status === 'draft') ? 'post_draft' : 'post_published',
							'event_status'   => ($post_status === 'draft') ? 'draft' : 'success',
							'log_type'       => isset($details['log_subtype']) ? esc_html($details['log_subtype']) : 'post_created',
							'post_id'        => $post_id,
							'post_title'     => $post_title,
							'post_status'    => $post_status,
							'view_url'       => $view_url ? $view_url : '',
							'edit_url'       => $edit_url ? $edit_url : '',
							'is_post'        => true,
							'message'        => sprintf(__('Post created: %s', 'ai-post-scheduler'), $post_title),
						);
					}
				}
			} else {
				// Non-post entry or post not found
				// Remove "(and X more)" if present in legacy text
				$clean_msg = preg_replace('/\s*\([^)]*and \d+ more\)/i', '', $clean_msg);

				$entries[] = array(
					'id'             => absint($log->id),
					'raw_id'         => absint($log->id),
					'timestamp'      => $ts,
					'formatted_date' => $date_fmt,
					'name'           => $clean_msg ? $clean_msg : __('Schedule event', 'ai-post-scheduler'),
					'event_type'     => $event_type ? $event_type : 'system_event',
					'event_status'   => $event_status ? $event_status : 'info',
					'log_type'       => isset($details['log_subtype']) ? esc_html($details['log_subtype']) : '',
					'post_id'        => null,
					'view_url'       => null,
					'edit_url'       => null,
					'is_post'        => false,
					'message'        => $clean_msg,
				);
			}
		}

		// Sort by timestamp DESC
		usort($entries, static function($a, $b) {
			return $b['timestamp'] <=> $a['timestamp'];
		});

		return $entries;
	}

	/**
	 *
	 * Each active author with `topic_generation_next_run` set appears as one row.
	 *
	 * @param bool $include_stats Whether to run the aggregate topic-count query.
	 * @return array
	 */
	private function get_author_topic_schedules($include_stats = true) {
		$authors      = $this->authors_repository->get_all();
		$result       = array();

		// Batch fetch topic counts per author using the repository.
		$topic_counts = array();
		if ($include_stats) {
			$topic_counts = $this->author_topics_repository->get_counts_grouped_by_author();
		}

		foreach ($authors as $author) {
			// Only include authors with a topic-generation schedule configured.
			if (empty($author->topic_generation_frequency)) {
				continue;
			}
			if (empty($author->topic_generation_next_run) && empty($author->topic_generation_last_run)) {
				continue;
			}

			$is_active = isset($author->topic_generation_is_active)
				? (int) $author->topic_generation_is_active
				: 1; // Treat NULL (pre-migration) as active.
			if (!$author->is_active) {
				$is_active = 0;
			}

			$stats = isset($topic_counts[$author->id]) ? $topic_counts[$author->id] : 0;

			$result[] = array(
				'id'                   => absint($author->id),
				'type'                 => self::TYPE_AUTHOR_TOPIC,
				'title'                => $author->name,
				'subtitle'             => isset($author->field_niche) ? $author->field_niche : '',
				'cron_hook'            => 'aips_generate_author_topics',
				'frequency'            => $author->topic_generation_frequency,
				'last_run'             => $author->topic_generation_last_run,
				'next_run'             => $author->topic_generation_next_run,
				'is_active'            => $is_active,
				'status'               => $is_active ? 'active' : 'inactive',
				'stats_count'          => $stats,
				'stats_label'          => _n('topic generated', 'topics generated', $stats, 'ai-post-scheduler'),
				'can_delete'           => false,
				'history_id'           => null,
				'author_id'            => (int) $author->id,
				'author_name'          => $author->name,
				'circuit_state'        => 'closed', // Author schedules don't have per-schedule circuit state
				'batch_progress'       => null,
				'has_incomplete_batch' => false,
			);
		}

		return $result;
	}

	/**
	 * Normalise author post-generation schedules.
	 *
	 * Each active author with `post_generation_next_run` set appears as one row.
	 *
	 * @param bool $include_stats Whether to run the aggregate post-count query.
	 * @return array
	 */
	private function get_author_post_schedules($include_stats = true) {
		$authors     = $this->authors_repository->get_all();
		$result      = array();

		// Batch fetch post-generation counts per author using the repository.
		$post_counts = array();
		if ($include_stats) {
			$post_counts = $this->author_topic_logs_repository->get_post_generation_counts_grouped_by_author();
		}

		foreach ($authors as $author) {
			if (empty($author->post_generation_frequency)) {
				continue;
			}
			if (empty($author->post_generation_next_run) && empty($author->post_generation_last_run)) {
				continue;
			}

			$is_active = isset($author->post_generation_is_active)
				? (int) $author->post_generation_is_active
				: 1;
			if (!$author->is_active) {
				$is_active = 0;
			}

			$stats = isset($post_counts[$author->id]) ? $post_counts[$author->id] : 0;

			$result[] = array(
				'id'                   => absint($author->id),
				'type'                 => self::TYPE_AUTHOR_POST,
				'title'                => $author->name,
				'subtitle'             => $author->field_niche,
				'cron_hook'            => 'aips_generate_author_posts',
				'frequency'            => $author->post_generation_frequency,
				'last_run'             => $author->post_generation_last_run,
				'next_run'             => $author->post_generation_next_run,
				'is_active'            => $is_active,
				'status'               => $is_active ? 'active' : 'inactive',
				'stats_count'          => $stats,
				'stats_label'          => _n('post generated', 'posts generated', $stats, 'ai-post-scheduler'),
				'can_delete'           => false,
				'history_id'           => null,
				'author_id'            => (int) $author->id,
				'author_name'          => $author->name,
				'circuit_state'        => 'closed', // Author schedules don't have per-schedule circuit state
				'batch_progress'       => null,
				'has_incomplete_batch' => false,
			);
		}

		return $result;
	}

	/**
	 * Convert raw log rows into the standard entry format expected by the UI.
	 *
	 * @param array $logs Raw DB rows from aips_history_log.
	 * @return array
	 */
	private function format_history_logs($logs) {
		$entries = array();
		foreach ($logs as $log) {
			$details = array();
			if (!empty($log->details)) {
				$decoded = json_decode($log->details, true);
				if (is_array($decoded)) {
					$details = $decoded;
				}
			}

			$input = isset($details['input']) && is_array($details['input']) ? $details['input'] : array();

			$entries[] = array(
				'id'              => absint($log->id),
				'timestamp'       => esc_html($log->timestamp),
				'log_type'        => isset($details['log_subtype']) ? esc_html($details['log_subtype']) : '',
				'history_type_id' => absint($log->history_type_id),
				'message'         => isset($details['message']) ? esc_html($details['message']) : '',
				'event_type'      => isset($input['event_type']) ? esc_html($input['event_type']) : '',
				'event_status'    => isset($input['event_status']) ? esc_html($input['event_status']) : '',
				'context'         => isset($details['context']) && is_array($details['context']) ? $details['context'] : array(),
			);
		}
		return $entries;
	}
}
