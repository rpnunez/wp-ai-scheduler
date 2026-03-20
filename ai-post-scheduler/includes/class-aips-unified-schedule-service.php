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
	 * Initialise the service and its dependencies.
	 */
	public function __construct() {
		$this->schedule_repository = new AIPS_Schedule_Repository();
		$this->authors_repository  = new AIPS_Authors_Repository();
		$this->history_repository  = new AIPS_History_Repository();
	}

	/**
	 * Return all scheduled processes, optionally filtered by type.
	 *
	 * Each element of the returned array is a normalised associative array
	 * (see private helpers for structure).
	 *
	 * @param string $type_filter Optional type constant to restrict results.
	 * @return array Sorted, normalised schedule rows.
	 */
	public function get_all($type_filter = '') {
		$schedules = array();

		if (empty($type_filter) || $type_filter === self::TYPE_TEMPLATE) {
			$schedules = array_merge($schedules, $this->get_template_schedules());
		}
		if (empty($type_filter) || $type_filter === self::TYPE_AUTHOR_TOPIC) {
			$schedules = array_merge($schedules, $this->get_author_topic_schedules());
		}
		if (empty($type_filter) || $type_filter === self::TYPE_AUTHOR_POST) {
			$schedules = array_merge($schedules, $this->get_author_post_schedules());
		}

		// Sort by next_run ascending, nulls last.
		usort($schedules, function ($a, $b) {
			if (empty($a['next_run']) && empty($b['next_run'])) {
				return 0;
			}
			if (empty($a['next_run'])) {
				return 1;
			}
			if (empty($b['next_run'])) {
				return -1;
			}
			return strcmp($a['next_run'], $b['next_run']);
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
	 *  – template_schedule : int post ID  (or WP_Error)
	 *  – author_topic_gen  : array of topics (or WP_Error)
	 *  – author_post_gen   : int post ID  (or WP_Error)
	 *
	 * @param int    $id   Numeric ID.
	 * @param string $type One of the TYPE_* constants.
	 * @return mixed
	 */
	public function run_now($id, $type) {
		switch ($type) {
			case self::TYPE_TEMPLATE:
				$scheduler = new AIPS_Scheduler();
				return $scheduler->run_schedule_now($id);

			case self::TYPE_AUTHOR_TOPIC:
				$scheduler = new AIPS_Author_Topics_Scheduler();
				return $scheduler->generate_now($id);

			case self::TYPE_AUTHOR_POST:
				$generator = new AIPS_Author_Post_Generator();
				$author    = $this->authors_repository->get_by_id($id);
				if (!$author) {
					return new WP_Error('not_found', __('Author not found.', 'ai-post-scheduler'));
				}
				return $generator->generate_post_for_author($author);

			default:
				return new WP_Error('invalid_type', __('Invalid schedule type.', 'ai-post-scheduler'));
		}
	}

	/**
	 * Get run-history log entries for a schedule.
	 *
	 * @param int    $id   Numeric ID.
	 * @param string $type One of the TYPE_* constants.
	 * @return array Normalised log entry arrays.
	 */
	public function get_history($id, $type) {
		switch ($type) {
			case self::TYPE_TEMPLATE:
				$schedule = $this->schedule_repository->get_by_id($id);
				if (!$schedule || empty($schedule->schedule_history_id)) {
					return array();
				}
				$logs = $this->history_repository->get_logs_by_history_id(
					absint($schedule->schedule_history_id),
					array(AIPS_History_Type::ACTIVITY, AIPS_History_Type::ERROR)
				);
				return $this->format_history_logs($logs);

			case self::TYPE_AUTHOR_TOPIC:
				$logs = $this->history_repository->get_author_schedule_logs_by_event_types(
					$id,
					array('author_topic_generation'),
					100
				);
				return $this->format_history_logs($logs);

			case self::TYPE_AUTHOR_POST:
				$logs = $this->history_repository->get_author_schedule_logs_by_event_types(
					$id,
					array('topic_post_generation'),
					100
				);
				return $this->format_history_logs($logs);

			default:
				return array();
		}
	}

	// -------------------------------------------------------------------------
	// Private helpers
	// -------------------------------------------------------------------------

	/**
	 * Normalise template-based schedules.
	 *
	 * @return array
	 */
	private function get_template_schedules() {
		$raw    = $this->schedule_repository->get_all();
		$result = array();

		// Batch-fetch generated-post counts by schedule history container.
		$history_ids = array();
		foreach ($raw as $schedule) {
			if (!empty($schedule->schedule_history_id)) {
				$history_ids[] = absint($schedule->schedule_history_id);
			}
		}
		$schedule_stats = $this->history_repository->get_schedule_generated_post_counts($history_ids);

		foreach ($raw as $schedule) {
			$schedule_history_id = !empty($schedule->schedule_history_id) ? (int) $schedule->schedule_history_id : 0;
			$stats  = isset($schedule_stats[$schedule_history_id]) ? (int) $schedule_stats[$schedule_history_id] : 0;
			$status = !empty($schedule->is_active) ? 'active' : 'inactive';
			if (isset($schedule->status) && $schedule->status === 'failed') {
				$status = 'failed';
			}

			$title = !empty($schedule->title) ? $schedule->title
				: ($schedule->template_name ?: sprintf(__('Schedule #%d', 'ai-post-scheduler'), $schedule->id));

			$result[] = array(
				'id'          => absint($schedule->id),
				'type'        => self::TYPE_TEMPLATE,
				'title'       => $title,
				'subtitle'    => $schedule->template_name ?: __('Unknown Template', 'ai-post-scheduler'),
				'cron_hook'   => 'aips_generate_scheduled_posts',
				'frequency'   => $schedule->frequency,
				'last_run'    => $schedule->last_run,
				'next_run'    => $schedule->next_run,
				'is_active'   => (int) $schedule->is_active,
				'status'      => $status,
				'stats_count' => $stats,
				'stats_label' => _n('post generated', 'posts generated', $stats, 'ai-post-scheduler'),
				'can_delete'  => true,
				'history_id'  => $schedule_history_id ? $schedule_history_id : null,
				'template_id' => (int) $schedule->template_id,
			);
		}

		return $result;
	}

	/**
	 * Normalise author topic-generation schedules.
	 *
	 * Each active author with `topic_generation_next_run` set appears as one row.
	 *
	 * @return array
	 */
	private function get_author_topic_schedules() {
		global $wpdb;

		$authors            = $this->authors_repository->get_all();
		$result             = array();
		$author_topics_tbl  = $wpdb->prefix . 'aips_author_topics';

		// Batch fetch topic counts per author.
		$topic_counts_raw = $wpdb->get_results(
			"SELECT author_id, COUNT(*) AS cnt FROM {$author_topics_tbl} GROUP BY author_id"
		);
		$topic_counts = array();
		foreach ($topic_counts_raw as $row) {
			$topic_counts[$row->author_id] = (int) $row->cnt;
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
				'id'          => absint($author->id),
				'type'        => self::TYPE_AUTHOR_TOPIC,
				'title'       => sprintf(
					/* translators: Author name */
					__('%s – Topic Generation', 'ai-post-scheduler'),
					$author->name
				),
				'subtitle'    => esc_html($author->field_niche),
				'cron_hook'   => 'aips_generate_author_topics',
				'frequency'   => $author->topic_generation_frequency,
				'last_run'    => $author->topic_generation_last_run,
				'next_run'    => $author->topic_generation_next_run,
				'is_active'   => $is_active,
				'status'      => $is_active ? 'active' : 'inactive',
				'stats_count' => $stats,
				'stats_label' => _n('topic generated', 'topics generated', $stats, 'ai-post-scheduler'),
				'can_delete'  => false,
				'history_id'  => null,
				'author_id'   => (int) $author->id,
				'author_name' => $author->name,
			);
		}

		return $result;
	}

	/**
	 * Normalise author post-generation schedules.
	 *
	 * Each active author with `post_generation_next_run` set appears as one row.
	 *
	 * @return array
	 */
	private function get_author_post_schedules() {
		global $wpdb;

		$authors              = $this->authors_repository->get_all();
		$result               = array();
		$topic_logs_tbl       = $wpdb->prefix . 'aips_author_topic_logs';
		$author_topics_tbl    = $wpdb->prefix . 'aips_author_topics';

		// Batch fetch post-generation counts per author.
		$post_counts_raw = $wpdb->get_results(
			"SELECT at.author_id, COUNT(*) AS cnt
			 FROM {$topic_logs_tbl} atl
			 INNER JOIN {$author_topics_tbl} at ON atl.author_topic_id = at.id
			 WHERE atl.action = 'post_generated'
			 GROUP BY at.author_id"
		);
		$post_counts = array();
		foreach ($post_counts_raw as $row) {
			$post_counts[$row->author_id] = (int) $row->cnt;
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
				'id'          => absint($author->id),
				'type'        => self::TYPE_AUTHOR_POST,
				'title'       => sprintf(
					/* translators: Author name */
					__('%s – Post Generation', 'ai-post-scheduler'),
					$author->name
				),
				'subtitle'    => $author->field_niche,
				'cron_hook'   => 'aips_generate_author_posts',
				'frequency'   => $author->post_generation_frequency,
				'last_run'    => $author->post_generation_last_run,
				'next_run'    => $author->post_generation_next_run,
				'is_active'   => $is_active,
				'status'      => $is_active ? 'active' : 'inactive',
				'stats_count' => $stats,
				'stats_label' => _n('post generated', 'posts generated', $stats, 'ai-post-scheduler'),
				'can_delete'  => false,
				'history_id'  => null,
				'author_id'   => (int) $author->id,
				'author_name' => $author->name,
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
				'log_type'        => esc_html($log->log_type),
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
