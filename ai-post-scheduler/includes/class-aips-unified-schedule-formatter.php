<?php
/**
 * Unified Schedule Formatter
 *
 * Handles formatting and normalization of schedule data for the Unified Schedule Service.
 *
 * @package AI_Post_Scheduler
 * @since 1.10.0
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Unified_Schedule_Formatter
 *
 * Encapsulates the formatting logic to transform raw DB models into normalized
 * arrays used by the UI.
 */
class AIPS_Unified_Schedule_Formatter {

	/**
	 * @var AIPS_Authors_Repository
	 */
	private $authors_repository;

	/**
	 * @var AIPS_History_Repository
	 */
	private $history_repository;

	/**
	 * Constructor.
	 *
	 * @param AIPS_Authors_Repository $authors_repository
	 * @param AIPS_History_Repository $history_repository
	 */
	public function __construct(AIPS_Authors_Repository $authors_repository, AIPS_History_Repository $history_repository) {
		$this->authors_repository = $authors_repository;
		$this->history_repository = $history_repository;
	}

	/**
	 * Normalise template-based schedules.
	 *
	 * @param array  $raw                 Raw schedule objects.
	 * @param string $type_template_const The TYPE_TEMPLATE constant.
	 * @return array
	 */
	public function format_template_schedules($raw, $type_template_const) {
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
				'type'        => $type_template_const,
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
	 * @param string $type_author_topic_const The TYPE_AUTHOR_TOPIC constant.
	 * @return array
	 */
	public function format_author_topic_schedules($type_author_topic_const) {
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
				'type'        => $type_author_topic_const,
				'title'       => sprintf(
					/* translators: Author name */
					__('%s – Topic Generation', 'ai-post-scheduler'),
					$author->name
				),
				'subtitle'    => isset($author->field_niche) ? $author->field_niche : '',
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
	 * @param string $type_author_post_const The TYPE_AUTHOR_POST constant.
	 * @return array
	 */
	public function format_author_post_schedules($type_author_post_const) {
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
				'type'        => $type_author_post_const,
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
	public function format_history_logs($logs) {
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
