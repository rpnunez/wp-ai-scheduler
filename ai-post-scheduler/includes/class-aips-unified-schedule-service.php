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
	 * @var AIPS_Unified_Schedule_Formatter
	 */
	private $formatter;

	/**
	 * Initialise the service and its dependencies.
	 */
	public function __construct() {
		$this->schedule_repository = new AIPS_Schedule_Repository();
		$this->authors_repository  = new AIPS_Authors_Repository();
		$this->history_repository  = new AIPS_History_Repository();
		$this->formatter           = new AIPS_Unified_Schedule_Formatter(
			$this->authors_repository,
			$this->history_repository
		);
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
			$raw = $this->schedule_repository->get_all();
			$schedules = array_merge($schedules, $this->formatter->format_template_schedules($raw, self::TYPE_TEMPLATE));
		}
		if (empty($type_filter) || $type_filter === self::TYPE_AUTHOR_TOPIC) {
			$schedules = array_merge($schedules, $this->formatter->format_author_topic_schedules(self::TYPE_AUTHOR_TOPIC));
		}
		if (empty($type_filter) || $type_filter === self::TYPE_AUTHOR_POST) {
			$schedules = array_merge($schedules, $this->formatter->format_author_post_schedules(self::TYPE_AUTHOR_POST));
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
				return $this->formatter->format_history_logs($logs);

			case self::TYPE_AUTHOR_TOPIC:
				$logs = $this->history_repository->get_author_schedule_logs_by_event_types(
					$id,
					array('author_topic_generation'),
					100
				);
				return $this->formatter->format_history_logs($logs);

			case self::TYPE_AUTHOR_POST:
				$logs = $this->history_repository->get_author_schedule_logs_by_event_types(
					$id,
					array('topic_post_generation'),
					100
				);
				return $this->formatter->format_history_logs($logs);

			default:
				return array();
		}
	}
}
