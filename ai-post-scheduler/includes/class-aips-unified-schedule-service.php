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
	const TYPE_TEMPLATE        = 'template_schedule';
	const TYPE_AUTHOR_TOPIC    = 'author_topic_gen';
	const TYPE_AUTHOR_POST     = 'author_post_gen';
	const TYPE_BLUEPRINT       = 'blueprint';
	const TYPE_AUTHOR_WORKFLOW = self::TYPE_BLUEPRINT; // Alias for backward compatibility.

	/** Workflow pipeline stages */
	private const WORKFLOW_STAGES = array(self::TYPE_AUTHOR_TOPIC, self::TYPE_AUTHOR_POST);

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

		$this->sort_by_run_proximity($schedules);

		return $schedules;
	}

	/**
	 * Return all schedules grouped by entity.
	 *
	 * Persona schedules (topic generation + post generation) are collapsed into
	 * a single Blueprint row carrying both stages. Template schedules remain as
	 * standalone rows.
	 *
	 * @param string $type_filter  Optional filter.
	 * @param bool   $include_stats Whether to run aggregate stats queries.
	 * @return array Sorted, normalised rows.
	 */
	public function get_all_grouped($type_filter = '', $include_stats = true) {
		// The blueprint type is synthesised here, so ask underlying fetchers for all stage types.
		$fetch_filter = ($type_filter === self::TYPE_BLUEPRINT || $type_filter === self::TYPE_AUTHOR_WORKFLOW) ? '' : $type_filter;
		$flat = $this->get_all($fetch_filter, $include_stats);

		$rows       = array();
		$blueprints = array();

		foreach ($flat as $row) {
			$type = isset($row['type']) ? $row['type'] : '';

			if (!in_array($type, self::WORKFLOW_STAGES, true)) {
				if ($type_filter === self::TYPE_BLUEPRINT || $type_filter === self::TYPE_AUTHOR_WORKFLOW) {
					continue;
				}
				$rows[] = $row;
				continue;
			}

			$author_id = !empty($row['author_id']) ? (int) $row['author_id'] : (int) $row['id'];
			if (!isset($blueprints[$author_id])) {
				$blueprints[$author_id] = array();
			}
			$blueprints[$author_id][$type] = $row;
		}

		foreach ($blueprints as $author_id => $stages) {
			$rows[] = $this->build_blueprint_row($author_id, $stages);
		}

		$this->sort_by_run_proximity($rows);

		return $rows;
	}

	/**
	 * Collapse a persona's stage rows into one unified Blueprint row.
	 *
	 * @param int   $author_id Author ID.
	 * @param array $stages    Stage rows keyed by stage type.
	 * @return array Normalised blueprint row.
	 */
	private function build_blueprint_row($author_id, array $stages) {
		$ordered = array();
		foreach (self::WORKFLOW_STAGES as $stage_type) {
			if (isset($stages[$stage_type])) {
				$ordered[$stage_type] = $stages[$stage_type];
			}
		}

		$first = reset($ordered);

		$stage_labels = array(
			self::TYPE_AUTHOR_TOPIC => __('Stage 1 · Topics', 'ai-post-scheduler'),
			self::TYPE_AUTHOR_POST  => __('Stage 2 · Posts', 'ai-post-scheduler'),
		);

		$stage_rows  = array();
		$next_runs   = array();
		$last_runs   = array();
		$frequencies = array();
		$is_active   = 0;
		$has_failure = false;

		foreach ($ordered as $stage_type => $stage) {
			$stage_active   = !empty($stage['is_active']) ? 1 : 0;
			$stage_next_run = !empty($stage['next_run']) ? (int) $stage['next_run'] : 0;
			$stage_last_run = !empty($stage['last_run']) ? (int) $stage['last_run'] : 0;

			if ($stage_active) {
				$is_active = 1;
				if ($stage_next_run > 0) {
					$next_runs[] = $stage_next_run;
				}
			}
			if ($stage_last_run > 0) {
				$last_runs[] = $stage_last_run;
			}
			if (!empty($stage['frequency'])) {
				$frequencies[] = $stage['frequency'];
			}
			if (isset($stage['status']) && $stage['status'] === 'failed') {
				$has_failure = true;
			}

			$stage_rows[] = array(
				'type'        => $stage_type,
				'id'          => (int) $stage['id'],
				'label'       => isset($stage_labels[$stage_type]) ? $stage_labels[$stage_type] : $stage_type,
				'frequency'   => isset($stage['frequency']) ? $stage['frequency'] : '',
				'cron_hook'   => isset($stage['cron_hook']) ? $stage['cron_hook'] : '',
				'next_run'    => $stage_next_run,
				'last_run'    => $stage_last_run,
				'is_active'   => $stage_active,
				'status'      => isset($stage['status']) ? $stage['status'] : 'inactive',
				'stats_count' => isset($stage['stats_count']) ? (int) $stage['stats_count'] : 0,
				'stats_label' => isset($stage['stats_label']) ? $stage['stats_label'] : '',
			);
		}

		$unique_frequencies = array_values(array_unique($frequencies));

		$status = 'inactive';
		if ($has_failure) {
			$status = 'failed';
		} elseif ($is_active) {
			$status = 'active';
		}

		// The headline count is posts produced; topics are an intermediate artefact.
		$post_stage = isset($ordered[self::TYPE_AUTHOR_POST]) ? $ordered[self::TYPE_AUTHOR_POST] : null;
		$stats_count = $post_stage ? (int) $post_stage['stats_count'] : 0;
		$stats_label = $post_stage
			? $post_stage['stats_label']
			: _n('post generated', 'posts generated', 0, 'ai-post-scheduler');

		return array(
			'id'                   => (int) $author_id,
			'type'                 => self::TYPE_BLUEPRINT,
			'title'                => isset($first['title']) ? $first['title'] : '',
			'subtitle'             => isset($first['subtitle']) ? $first['subtitle'] : '',
			'cron_hook'            => implode(', ', wp_list_pluck($stage_rows, 'cron_hook')),
			'frequency'            => count($unique_frequencies) === 1 ? $unique_frequencies[0] : '',
			'mixed_frequency'      => count($unique_frequencies) > 1,
			'last_run'             => !empty($last_runs) ? max($last_runs) : 0,
			'next_run'             => !empty($next_runs) ? min($next_runs) : 0,
			'is_active'            => $is_active,
			'status'               => $status,
			'stats_count'          => $stats_count,
			'stats_label'          => $stats_label,
			'can_delete'           => false,
			'history_id'           => null,
			'author_id'            => (int) $author_id,
			'author_name'          => isset($first['title']) ? $first['title'] : '',
			'circuit_state'        => 'closed',
			'batch_progress'       => null,
			'has_incomplete_batch' => false,
			'stages'               => $stage_rows,
		);
	}

	/**
	 * Sort rows by run proximity for better operator UX:
	 * 1) active upcoming schedules (soonest first)
	 * 2) active past-due schedules (least overdue first)
	 * 3) inactive/unscheduled rows (last)
	 *
	 * @param array $schedules Rows to sort, by reference.
	 * @return void
	 */
	private function sort_by_run_proximity(array &$schedules) {
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

			case self::TYPE_BLUEPRINT:
			case self::TYPE_AUTHOR_WORKFLOW:
				// One persona, one switch: both stages follow the row toggle.
				$topic_result = $this->authors_repository->update_topic_generation_active($id, $is_active);
				$post_result  = $this->authors_repository->update_post_generation_active($id, $is_active);
				return (false !== $topic_result) && (false !== $post_result);

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
	 *  – blueprint         : array keyed by stage with generated topics/post IDs
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

			case self::TYPE_BLUEPRINT:
			case self::TYPE_AUTHOR_WORKFLOW:
				// Run the stages in order: topics first, so post stage has approved topics to draw from.
				$topics = $this->run_now($id, self::TYPE_AUTHOR_TOPIC, null, $advance_schedule);
				if (is_wp_error($topics)) {
					return $topics;
				}

				// If author has no approved topics ready, approve newly generated topics for immediate execution
				$topics_repo = new AIPS_Author_Topics_Repository();
				$existing_approved = $topics_repo->get_approved_for_generation($id, 1);
				if (empty($existing_approved) && is_array($topics)) {
					$logs_repo = new AIPS_Author_Topic_Logs_Repository();
					$current_user_id = get_current_user_id() ? get_current_user_id() : null;
					foreach ($topics as $topic_item) {
						$topic_id = is_array($topic_item) ? (isset($topic_item['id']) ? $topic_item['id'] : null) : (isset($topic_item->id) ? $topic_item->id : null);
						if ($topic_id) {
							$topics_repo->update((int) $topic_id, array('status' => 'approved'));
							$logs_repo->create(array(
								'author_topic_id' => (int) $topic_id,
								'action'          => 'approved',
								'user_id'         => $current_user_id,
								'notes'           => __('Auto-approved for Blueprint Run Now execution.', 'ai-post-scheduler'),
							));
						}
					}
				}

				$posts = $this->run_now($id, self::TYPE_AUTHOR_POST, $quantity, $advance_schedule);
				if (is_wp_error($posts)) {
					return $posts;
				}

				return array(
					self::TYPE_AUTHOR_TOPIC => $topics,
					self::TYPE_AUTHOR_POST  => $posts,
				);

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
			case self::TYPE_BLUEPRINT:
			case self::TYPE_AUTHOR_WORKFLOW:
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
				return $this->format_history_logs($logs);

			case self::TYPE_AUTHOR_TOPIC:
				$logs = $this->history_repository->get_author_schedule_logs_by_event_types(
					$id,
					array('author_topic_generation'),
					$limit > 0 ? $limit : 100
				);
				return $this->format_history_logs($logs);

			case self::TYPE_AUTHOR_POST:
				$logs = $this->history_repository->get_author_schedule_logs_by_event_types(
					$id,
					array(AIPS_History_Event_Type::AUTHOR_POST_GENERATION),
					$limit > 0 ? $limit : 100
				);
				return $this->format_history_logs($logs);

			case self::TYPE_BLUEPRINT:
			case self::TYPE_AUTHOR_WORKFLOW:
				// One persona's run history is both stages interleaved by time.
				$logs = $this->history_repository->get_author_schedule_logs_by_event_types(
					$id,
					array('author_topic_generation', AIPS_History_Event_Type::AUTHOR_POST_GENERATION),
					$limit > 0 ? $limit : 100
				);
				return $this->format_history_logs($logs);

			default:
				return array();
		}
	}

	/**
	 * Build the expandable-row details for one schedule: activity, health and
	 * the most recent generated output.
	 *
	 * All strings are returned unescaped; the admin UI renders them through
	 * AIPS.Templates.render(), which escapes every value.
	 *
	 * @param int    $id           Numeric ID (schedule ID, or author ID for author/blueprint rows).
	 * @param string $type         One of the TYPE_* constants.
	 * @param int    $recent_limit Number of recent output items to return.
	 * @return array|WP_Error {activity, health, recent, recent_limit} or WP_Error.
	 */
	public function get_details($id, $type, $recent_limit = 10) {
		$id           = absint($id);
		$recent_limit = max(1, min(50, absint($recent_limit)));

		if (!in_array($type, array(self::TYPE_TEMPLATE, self::TYPE_AUTHOR_TOPIC, self::TYPE_AUTHOR_POST, self::TYPE_BLUEPRINT), true)) {
			return new WP_Error('invalid_type', __('Invalid schedule type.', 'ai-post-scheduler'));
		}

		$row = $this->find_row($id, $type);
		if (!$row) {
			return new WP_Error('not_found', __('Schedule not found.', 'ai-post-scheduler'));
		}

		return array(
			'activity'     => $this->build_activity($row),
			'health'       => $this->build_health($row),
			'recent'       => $this->get_recent_output($row, $recent_limit),
			'recent_limit' => $recent_limit,
		);
	}

	/**
	 * Find one normalised schedule row.
	 *
	 * @param int    $id   Numeric ID.
	 * @param string $type One of the TYPE_* constants.
	 * @return array|null
	 */
	private function find_row($id, $type) {
		$rows = (self::TYPE_BLUEPRINT === $type)
			? $this->get_all_grouped(self::TYPE_BLUEPRINT)
			: $this->get_all($type);

		foreach ($rows as $row) {
			if ((int) $row['id'] === (int) $id && $row['type'] === $type) {
				return $row;
			}
		}

		return null;
	}

	/**
	 * Activity panel: headline counts plus, for blueprints, a per-stage breakdown.
	 *
	 * @param array $row Normalised schedule row.
	 * @return array{stats: array, stages: array}
	 */
	private function build_activity(array $row) {
		$stats  = array();
		$stages = array();

		if (self::TYPE_BLUEPRINT === $row['type']) {
			$topics_count = 0;
			foreach ((array) ($row['stages'] ?? array()) as $stage) {
				if (self::TYPE_AUTHOR_TOPIC === $stage['type']) {
					$topics_count = (int) $stage['stats_count'];
				}

				$stages[] = array(
					'label'     => $stage['label'],
					'frequency' => $this->frequency_label($stage['frequency']),
					'output'    => $this->count_label((int) $stage['stats_count'], self::TYPE_AUTHOR_TOPIC === $stage['type'] ? 'topic' : 'post'),
					'last_run'  => !empty($stage['last_run']) ? AIPS_DateTime::formatRelativeOrAbsolute((int) $stage['last_run']) : __('Never', 'ai-post-scheduler'),
					'is_active' => !empty($stage['is_active']),
				);
			}

			$stats[] = array('value' => number_format_i18n((int) $row['stats_count']), 'label' => __('Posts generated', 'ai-post-scheduler'));
			$stats[] = array('value' => number_format_i18n($topics_count), 'label' => __('Topics generated', 'ai-post-scheduler'));
		} elseif (self::TYPE_AUTHOR_TOPIC === $row['type']) {
			$stats[] = array('value' => number_format_i18n((int) $row['stats_count']), 'label' => __('Topics generated', 'ai-post-scheduler'));
		} else {
			$stats[] = array('value' => number_format_i18n((int) $row['stats_count']), 'label' => __('Posts generated', 'ai-post-scheduler'));
		}

		$stats[] = array(
			'value' => !empty($row['last_run']) ? AIPS_DateTime::formatRelativeOrAbsolute($row['last_run']) : __('Never', 'ai-post-scheduler'),
			'label' => __('Last run', 'ai-post-scheduler'),
		);

		$batch = isset($row['batch_progress']) && is_array($row['batch_progress']) ? $row['batch_progress'] : null;
		if (!empty($row['has_incomplete_batch']) && $batch) {
			$stats[] = array(
				/* translators: 1: completed posts, 2: total posts */
				'value' => sprintf(__('%1$s / %2$s', 'ai-post-scheduler'), number_format_i18n((int) $batch['completed']), number_format_i18n((int) $batch['total'])),
				'label' => __('Batch progress', 'ai-post-scheduler'),
			);
		}

		return array(
			'stats'  => $stats,
			'stages' => $stages,
		);
	}

	/**
	 * Health panel: overall state, explanation, recovery actions and the last error.
	 *
	 * @param array $row Normalised schedule row.
	 * @return array{state: string, label: string, message: string, actions: array, last_error: array|null}
	 */
	private function build_health(array $row) {
		$circuit_state = isset($row['circuit_state']) ? $row['circuit_state'] : 'closed';
		$actions       = array();
		$last_error    = null;
		$latest_failed = false;

		if (self::TYPE_TEMPLATE === $row['type']) {
			if (!empty($row['history_id'])) {
				$logs = $this->history_repository->get_logs_by_history_id((int) $row['history_id'], array(AIPS_History_Type::ERROR), 1);
				if (!empty($logs)) {
					$last_error = $this->format_error_log($logs[0]);
				}
			}
			$latest_failed = (isset($row['status']) && 'failed' === $row['status']);
		} else {
			$author_id   = !empty($row['author_id']) ? (int) $row['author_id'] : (int) $row['id'];
			$event_types = array();
			if (self::TYPE_AUTHOR_TOPIC === $row['type'] || self::TYPE_BLUEPRINT === $row['type']) {
				$event_types[] = 'author_topic_generation';
			}
			if (self::TYPE_AUTHOR_POST === $row['type'] || self::TYPE_BLUEPRINT === $row['type']) {
				$event_types[] = AIPS_History_Event_Type::AUTHOR_POST_GENERATION;
			}

			$logs = $this->history_repository->get_author_schedule_logs_by_event_types($author_id, $event_types, 20);
			foreach ($logs as $index => $log) {
				if (!$this->is_failed_log($log)) {
					continue;
				}
				if (0 === $index) {
					$latest_failed = true;
				}
				$last_error = $this->format_error_log($log);
				break;
			}
		}

		if ('open' === $circuit_state) {
			$state   = 'error';
			$label   = __('Circuit breaker tripped', 'ai-post-scheduler');
			$message = __('Runs are blocked after repeated failures. Reset the circuit to try again.', 'ai-post-scheduler');
			$actions[] = 'reset_circuit';
		} elseif ('half_open' === $circuit_state) {
			$state   = 'warning';
			$label   = __('Recovering', 'ai-post-scheduler');
			$message = __('The next run is a trial after recent failures. It closes the circuit if it succeeds.', 'ai-post-scheduler');
		} elseif ($latest_failed) {
			$state   = 'error';
			$label   = __('Last run failed', 'ai-post-scheduler');
			$message = __('The most recent run did not finish. See the error below, or open History for the full log.', 'ai-post-scheduler');
		} elseif (!empty($row['has_incomplete_batch'])) {
			$state   = 'warning';
			$label   = __('Batch incomplete', 'ai-post-scheduler');
			$message = __('A multi-post run stopped before every post was generated.', 'ai-post-scheduler');
		} else {
			$state   = 'healthy';
			$label   = __('Healthy', 'ai-post-scheduler');
			$message = __('No problems with recent runs.', 'ai-post-scheduler');
		}

		if (!empty($row['has_incomplete_batch'])) {
			$actions[] = 'resume_batch';
		}

		return array(
			'state'      => $state,
			'label'      => $label,
			'message'    => $message,
			'actions'    => $actions,
			'last_error' => $last_error,
		);
	}

	/**
	 * Most recent generated output (posts and/or topics), newest first.
	 *
	 * @param array $row   Normalised schedule row.
	 * @param int   $limit Maximum items.
	 * @return array<int, array> Items: kind, title, url, status, status_label, timestamp, time_display, time_full, time_iso.
	 */
	private function get_recent_output(array $row, $limit) {
		$items = array();

		if (self::TYPE_TEMPLATE === $row['type']) {
			if (empty($row['history_id'])) {
				return array();
			}

			// One run can log several post events (draft + completion) for the same post,
			// so over-fetch and de-duplicate by post ID.
			$logs     = $this->history_repository->get_recent_schedule_post_logs((int) $row['history_id'], $limit * 3);
			$post_ids = array();
			foreach ($logs as $log) {
				foreach ($this->extract_log_post_ids($log) as $post_id) {
					if (!isset($post_ids[$post_id])) {
						$post_ids[$post_id] = isset($log->timestamp) ? (int) $log->timestamp : 0;
					}
				}
			}

			if (!empty($post_ids) && function_exists('_prime_post_caches')) {
				_prime_post_caches(array_keys($post_ids), false, false);
			}

			foreach ($post_ids as $post_id => $timestamp) {
				$item = $this->build_post_item($post_id, $timestamp);
				if ($item) {
					$items[] = $item;
				}
				if (count($items) >= $limit) {
					break;
				}
			}

			return $items;
		}

		$author_id = !empty($row['author_id']) ? (int) $row['author_id'] : (int) $row['id'];

		if (self::TYPE_AUTHOR_POST === $row['type'] || self::TYPE_BLUEPRINT === $row['type']) {
			$post_rows = $this->author_topic_logs_repository->get_recent_generated_posts_by_author($author_id, $limit);
			$ids       = array_map('absint', wp_list_pluck($post_rows, 'post_id'));
			if (!empty($ids) && function_exists('_prime_post_caches')) {
				_prime_post_caches($ids, false, false);
			}
			foreach ($post_rows as $post_row) {
				$item = $this->build_post_item((int) $post_row->post_id, (int) $post_row->created_at);
				if ($item) {
					$items[] = $item;
				}
			}
		}

		if (self::TYPE_AUTHOR_TOPIC === $row['type'] || self::TYPE_BLUEPRINT === $row['type']) {
			$topics = $this->author_topics_repository->get_latest_by_author($author_id, $limit);
			foreach ((array) $topics as $topic) {
				$items[] = $this->build_topic_item($topic, $author_id);
			}
		}

		usort($items, function ($a, $b) {
			return $b['timestamp'] <=> $a['timestamp'];
		});

		return array_slice($items, 0, $limit);
	}

	/**
	 * Display item for a generated post, or null if the post no longer exists.
	 *
	 * @param int $post_id   Post ID.
	 * @param int $timestamp When it was generated (Unix).
	 * @return array|null
	 */
	private function build_post_item($post_id, $timestamp) {
		$post = get_post($post_id);
		if (!$post) {
			return null;
		}

		$status_object = get_post_status_object($post->post_status);
		$title         = trim((string) $post->post_title);

		return array_merge(
			array(
				'kind'         => 'post',
				'title'        => '' !== $title ? $title : __('(no title)', 'ai-post-scheduler'),
				'url'          => esc_url_raw((string) get_edit_post_link($post_id, 'raw')),
				'status'       => sanitize_html_class($post->post_status),
				'status_label' => ($status_object && !empty($status_object->label)) ? $status_object->label : ucfirst($post->post_status),
			),
			$this->time_fields($timestamp > 0 ? $timestamp : (int) get_post_time('U', true, $post))
		);
	}

	/**
	 * Display item for a generated author topic.
	 *
	 * @param object $topic     Topic row.
	 * @param int    $author_id Author ID.
	 * @return array
	 */
	private function build_topic_item($topic, $author_id) {
		$status_labels = array(
			'pending'  => __('Pending review', 'ai-post-scheduler'),
			'approved' => __('Approved', 'ai-post-scheduler'),
			'rejected' => __('Rejected', 'ai-post-scheduler'),
		);
		$status = isset($topic->status) ? (string) $topic->status : 'pending';

		$url = add_query_arg(array(
			'page'      => 'aips-automations',
			'tab'       => 'author-topics',
			'author_id' => absint($author_id),
		), admin_url('admin.php'));

		return array_merge(
			array(
				'kind'         => 'topic',
				'title'        => isset($topic->topic_title) ? (string) $topic->topic_title : '',
				'url'          => esc_url_raw($url),
				'status'       => sanitize_html_class($status),
				'status_label' => isset($status_labels[$status]) ? $status_labels[$status] : ucfirst($status),
			),
			$this->time_fields(!empty($topic->generated_at) ? (int) $topic->generated_at : 0)
		);
	}

	/**
	 * Common time fields for a recent-output item.
	 *
	 * @param int $timestamp Unix timestamp (0 = unknown).
	 * @return array
	 */
	private function time_fields($timestamp) {
		if ($timestamp <= 0) {
			return array('timestamp' => 0, 'time_display' => '', 'time_full' => '', 'time_iso' => '');
		}

		$date_time = AIPS_DateTime::fromTimestamp($timestamp);

		return array(
			'timestamp'    => $timestamp,
			'time_display' => AIPS_DateTime::formatRelativeOrAbsolute($timestamp),
			'time_full'    => $date_time->toDisplay(),
			'time_iso'     => $date_time->toIso8601(),
		);
	}

	/**
	 * Post IDs referenced by a history log row (details.context.post_id or details.output.post_id).
	 *
	 * @param object $log Raw log row.
	 * @return int[]
	 */
	private function extract_log_post_ids($log) {
		$details = !empty($log->details) ? json_decode($log->details, true) : array();
		if (!is_array($details)) {
			return array();
		}

		$source = null;
		if (!empty($details['context']['post_id'])) {
			$source = $details['context']['post_id'];
		} elseif (!empty($details['output']['post_id'])) {
			$source = $details['output']['post_id'];
		}

		if (empty($source)) {
			return array();
		}

		return array_values(array_filter(array_map('absint', (array) $source)));
	}

	/**
	 * Whether a history log row records a failure.
	 *
	 * @param object $log Raw log row.
	 * @return bool
	 */
	private function is_failed_log($log) {
		if (isset($log->history_type_id) && (int) $log->history_type_id === AIPS_History_Type::ERROR) {
			return true;
		}

		$status = isset($log->event_status) ? (string) $log->event_status : '';

		return '' !== $status && AIPS_History_Event_Status::FAILED === AIPS_History_Event_Status::canonicalize($status);
	}

	/**
	 * Unescaped message + time fields for an error log row.
	 *
	 * @param object $log Raw log row.
	 * @return array{message: string, time_display: string, time_full: string, time_iso: string, timestamp: int}
	 */
	private function format_error_log($log) {
		$details = !empty($log->details) ? json_decode($log->details, true) : array();
		$message = (is_array($details) && !empty($details['message'])) ? (string) $details['message'] : '';
		if ('' === $message) {
			$message = __('The run failed without an error message.', 'ai-post-scheduler');
		}

		return array_merge(
			array('message' => wp_strip_all_tags($message)),
			$this->time_fields(isset($log->timestamp) ? (int) $log->timestamp : 0)
		);
	}

	/**
	 * Human label for a cron recurrence key.
	 *
	 * @param string $frequency Recurrence key.
	 * @return string
	 */
	private function frequency_label($frequency) {
		if (empty($frequency)) {
			return '—';
		}
		$schedules = wp_get_schedules();
		if (isset($schedules[$frequency]['display'])) {
			return $schedules[$frequency]['display'];
		}
		return ucfirst(str_replace('_', ' ', $frequency));
	}

	/**
	 * "37 posts" / "66 topics".
	 *
	 * @param int    $count Count.
	 * @param string $noun  'post' or 'topic'.
	 * @return string
	 */
	private function count_label($count, $noun) {
		if ('topic' === $noun) {
			/* translators: %s: number of topics */
			return sprintf(_n('%s topic', '%s topics', $count, 'ai-post-scheduler'), number_format_i18n($count));
		}
		/* translators: %s: number of posts */
		return sprintf(_n('%s post', '%s posts', $count, 'ai-post-scheduler'), number_format_i18n($count));
	}

	// -------------------------------------------------------------------------
	// Private helpers
	// -------------------------------------------------------------------------

	/**
	 * Normalise template-based schedules.
	 *
	 * @param bool $include_stats Whether to run the aggregate stats query.
	 * @return array
	 */
	private function get_template_schedules($include_stats = true) {
		$raw    = $this->schedule_repository->get_all();
		$result = array();

		// Batch-fetch generated-post counts by schedule history container.
		$schedule_stats = array();
		if ($include_stats) {
			$history_ids = array();
			foreach ($raw as $schedule) {
				if (!empty($schedule->schedule_history_id)) {
					$history_ids[] = absint($schedule->schedule_history_id);
				}
			}
			$schedule_stats = $this->history_repository->get_schedule_generated_post_counts($history_ids);
		}

		foreach ($raw as $schedule) {
			$schedule_history_id = !empty($schedule->schedule_history_id) ? (int) $schedule->schedule_history_id : 0;
			$stats  = isset($schedule_stats[$schedule_history_id]) ? (int) $schedule_stats[$schedule_history_id] : 0;
			$status = !empty($schedule->is_active) ? 'active' : 'inactive';
			if (isset($schedule->status) && $schedule->status === 'failed') {
				$status = 'failed';
			}

			$title = !empty($schedule->title) ? $schedule->title
				: ($schedule->template_name ?: sprintf(__('Schedule #%d', 'ai-post-scheduler'), $schedule->id));

			// Parse batch_progress to detect incomplete batches
			$batch_progress_data = null;
			$has_incomplete_batch = false;
			if (!empty($schedule->batch_progress)) {
				$batch_progress_data = json_decode($schedule->batch_progress, true);
				if (is_array($batch_progress_data) && isset($batch_progress_data['completed'], $batch_progress_data['total'])) {
					$has_incomplete_batch = $batch_progress_data['completed'] < $batch_progress_data['total'];
				}
			}

			$result[] = array(
				'id'                   => absint($schedule->id),
				'type'                 => self::TYPE_TEMPLATE,
				'title'                => $title,
				'subtitle'             => $schedule->template_name ?: __('Unknown Template', 'ai-post-scheduler'),
				'campaign_id'          => !empty($schedule->campaign_id) ? (int) $schedule->campaign_id : 0,
				'cron_hook'            => 'aips_generate_scheduled_posts',
				'frequency'            => $schedule->frequency,
				'topic'                => isset($schedule->topic) ? $schedule->topic : '',
				'article_structure_id' => isset($schedule->article_structure_id) ? $schedule->article_structure_id : '',
				'rotation_pattern'     => isset($schedule->rotation_pattern) ? $schedule->rotation_pattern : '',
				'last_run'             => $schedule->last_run,
				'next_run'             => $schedule->next_run,
				'is_active'            => (int) $schedule->is_active,
				'status'               => $status,
				'stats_count'          => $stats,
				'stats_label'          => _n('post generated', 'posts generated', $stats, 'ai-post-scheduler'),
				'can_delete'           => empty($schedule->campaign_id),
				'history_id'           => $schedule_history_id ? $schedule_history_id : null,
				'template_id'          => (int) $schedule->template_id,
				'circuit_state'        => isset($schedule->circuit_state) ? $schedule->circuit_state : 'closed',
				'batch_progress'       => $batch_progress_data,
				'has_incomplete_batch' => $has_incomplete_batch,
			);
		}

		return $result;
	}

	/**
	 * Normalise author topic-generation schedules.
	 *
	 * Each active author with `topic_generation_next_run` set appears as one row.
	 *
	 * @param bool $include_stats Whether to run the aggregate topic-count query.
	 * @return array
	 */
	private function get_author_topic_schedules($include_stats = true) {
		$authors           = $this->authors_repository->get_all();
		$result            = array();
		$topic_counts      = array();
		$latest_topic_runs = array();

		// Batch fetch topic counts and latest generation timestamps per author using the repository.
		if ($include_stats) {
			$topic_counts      = $this->author_topics_repository->get_counts_grouped_by_author();
			$latest_topic_runs = $this->author_topics_repository->get_latest_generation_timestamps_grouped_by_author();
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

			$stats    = isset($topic_counts[$author->id]) ? $topic_counts[$author->id] : 0;
			$last_run = !empty($author->topic_generation_last_run) ? (int) $author->topic_generation_last_run : 0;
			if ($last_run <= 0 && isset($latest_topic_runs[$author->id])) {
				$last_run = (int) $latest_topic_runs[$author->id];
			}

			$result[] = array(
				'id'                   => absint($author->id),
				'type'                 => self::TYPE_AUTHOR_TOPIC,
				'title'                => $author->name,
				'subtitle'             => isset($author->field_niche) ? $author->field_niche : '',
				'cron_hook'            => 'aips_generate_author_topics',
				'frequency'            => $author->topic_generation_frequency,
				'last_run'             => $last_run,
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
		$authors          = $this->authors_repository->get_all();
		$result           = array();
		$post_counts      = array();
		$latest_post_runs = array();

		// Batch fetch post-generation counts and latest generation timestamps per author using the repository.
		if ($include_stats) {
			$post_counts      = $this->author_topic_logs_repository->get_post_generation_counts_grouped_by_author();
			$latest_post_runs = $this->author_topic_logs_repository->get_latest_post_generation_timestamps_grouped_by_author();
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

			$stats    = isset($post_counts[$author->id]) ? $post_counts[$author->id] : 0;
			$last_run = !empty($author->post_generation_last_run) ? (int) $author->post_generation_last_run : 0;
			if ($last_run <= 0 && isset($latest_post_runs[$author->id])) {
				$last_run = (int) $latest_post_runs[$author->id];
			}

			$result[] = array(
				'id'                   => absint($author->id),
				'type'                 => self::TYPE_AUTHOR_POST,
				'title'                => $author->name,
				'subtitle'             => $author->field_niche,
				'cron_hook'            => 'aips_generate_author_posts',
				'frequency'            => $author->post_generation_frequency,
				'last_run'             => $last_run,
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
		// Delegate decoding + canonicalization to the shared read model so every
		// consumer sees one stable event vocabulary and record shape.
		return AIPS_History_Event_View::from_logs($logs);
	}
}
