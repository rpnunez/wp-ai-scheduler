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
	const TYPE_TEMPLATE     = 'template_schedule';
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
	 * @var AIPS_Trending_Topics_Repository
	 */
	private $trending_topics_repository;

	/**
	 * Initialise the service and its dependencies.
	 */
	public function __construct() {
		$this->schedule_repository        = new AIPS_Schedule_Repository();
		$this->authors_repository         = new AIPS_Authors_Repository();
		$this->history_repository         = new AIPS_History_Repository();
		$this->author_topics_repository   = new AIPS_Author_Topics_Repository();
		$this->trending_topics_repository = new AIPS_Trending_Topics_Repository();
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
		usort(
			$schedules,
			function ($a, $b) {
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
			}
		);

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

	/**
	 * Return the normalised editorial mix rules from site settings.
	 *
	 * @return array
	 */
	public function get_editorial_mix_rules() {
		return AIPS_Site_Context::get_editorial_mix_rules();
	}

	/**
	 * Build candidate schedule items from a list of proposed topics.
	 *
	 * @param array $topics Planner topics.
	 * @param array $args  Optional context such as start date/frequency/template.
	 * @return array
	 */
	public function build_candidate_items_from_topics($topics, $args = array()) {
		$topics = is_array($topics) ? $topics : array();
		$topics = array_values(array_filter(array_map('sanitize_text_field', $topics)));
		if (empty($topics)) {
			return array();
		}

		$start_time = current_time('timestamp');
		if (!empty($args['start_date'])) {
			$parsed_start = strtotime(sanitize_text_field($args['start_date']));
			if ($parsed_start) {
				$start_time = $parsed_start;
			}
		}

		$frequency = !empty($args['frequency']) ? sanitize_key($args['frequency']) : 'daily';
		$interval  = 86400;
		$scheduler = new AIPS_Scheduler();
		$intervals = $scheduler->get_intervals();
		if (isset($intervals[ $frequency ]['interval'])) {
			$interval = absint($intervals[ $frequency ]['interval']);
		}

		$template_label = !empty($args['template_label']) ? sanitize_text_field($args['template_label']) : __('Planner Candidate', 'ai-post-scheduler');
		$candidates     = array();

		foreach ($topics as $index => $topic) {
			$candidates[] = array(
				'id'           => 'planner_' . ($index + 1),
				'type'         => 'planner_candidate',
				'title'        => $topic,
				'topic'        => $topic,
				'subtitle'     => $template_label,
				'next_run'     => gmdate('Y-m-d H:i:s', $start_time + ($index * $interval)),
				'is_active'    => 1,
				'source'       => 'planner',
				'author_name'  => '',
				'template_id'  => !empty($args['template_id']) ? absint($args['template_id']) : 0,
			);
		}

		return $candidates;
	}

	/**
	 * Build a planner-friendly editorial mix snapshot.
	 *
	 * @param array $candidate_items Optional candidate items.
	 * @param array $args            Optional report settings.
	 * @return array
	 */
	public function get_planner_mix_insights($candidate_items = array(), $args = array()) {
		$current_report    = $this->get_upcoming_mix_report(array(), $args);
		$candidate_scores  = $this->score_candidate_items($candidate_items, $args);
		$suggestions       = $this->get_rebalance_suggestions($candidate_items, $args);
		$projected_report  = !empty($candidate_scores['projected_report']) ? $candidate_scores['projected_report'] : $current_report;

		return array(
			'rules'            => $this->get_editorial_mix_rules(),
			'current_report'   => $current_report,
			'candidate_scores' => $candidate_scores,
			'projected_report' => $projected_report,
			'suggestions'      => $suggestions,
		);
	}

	/**
	 * Build a mix report for the upcoming calendar plus optional candidates.
	 *
	 * @param array $candidate_items Optional candidate items.
	 * @param array $args            Optional report settings.
	 * @return array
	 */
	public function get_upcoming_mix_report($candidate_items = array(), $args = array()) {
		$window_days = !empty($args['window_days']) ? absint($args['window_days']) : 7;
		$items       = $this->get_upcoming_mix_items($window_days);

		foreach ((array) $candidate_items as $candidate_item) {
			$items[] = $this->normalise_mix_item($candidate_item, true);
		}

		return $this->build_mix_report_from_items($items, $window_days);
	}

	/**
	 * Score candidate items against the configured editorial mix rules.
	 *
	 * @param array $candidate_items Candidate schedule rows.
	 * @param array $args            Optional report settings.
	 * @return array
	 */
	public function score_candidate_items($candidate_items, $args = array()) {
		$window_days      = !empty($args['window_days']) ? absint($args['window_days']) : 7;
		$rules            = $this->get_editorial_mix_rules();
		$baseline_items   = $this->get_upcoming_mix_items($window_days);
		$running_items    = $baseline_items;
		$score_rows       = array();
		$summary          = array(
			'improved' => 0,
			'worsened' => 0,
			'neutral'  => 0,
		);

		foreach ((array) $candidate_items as $candidate_item) {
			$normalised_candidate = $this->normalise_mix_item($candidate_item, true);
			$before_report        = $this->build_mix_report_from_items($running_items, $window_days);
			$before_score         = isset($before_report['imbalance_score']) ? (float) $before_report['imbalance_score'] : 0.0;

			$running_items[]      = $normalised_candidate;
			$after_report         = $this->build_mix_report_from_items($running_items, $window_days);
			$after_score          = isset($after_report['imbalance_score']) ? (float) $after_report['imbalance_score'] : 0.0;
			$score_row            = $this->build_candidate_score_row($normalised_candidate, $before_report, $after_report, $rules, $before_score, $after_score);
			$summary[ $score_row['impact'] ]++;
			$score_rows[] = $score_row;
		}

		return array(
			'items'           => $score_rows,
			'summary'         => $summary,
			'projected_report'=> $this->build_mix_report_from_items($running_items, $window_days),
		);
	}

	/**
	 * Suggest alternative topics from research and approved-topic queues.
	 *
	 * @param array $candidate_items Candidate planner rows.
	 * @param array $args            Optional report settings.
	 * @return array
	 */
	public function get_rebalance_suggestions($candidate_items = array(), $args = array()) {
		$selected_candidates = array();
		$selected_keys       = array();

		foreach ((array) $candidate_items as $candidate_item) {
			$normalised = $this->normalise_mix_item($candidate_item, true);
			$selected_candidates[] = $normalised;
			$selected_keys[]       = $normalised['topic_key'];
		}

		$raw_suggestions = array();
		$research_rows   = $this->trending_topics_repository->get_top_topics(10, 14);
		foreach ($research_rows as $research_row) {
			$raw_suggestions[] = array(
				'title'        => isset($research_row['topic']) ? $research_row['topic'] : '',
				'topic'        => isset($research_row['topic']) ? $research_row['topic'] : '',
				'subtitle'     => __('Research queue', 'ai-post-scheduler'),
				'source'       => 'research',
				'source_label' => __('Research', 'ai-post-scheduler'),
				'next_run'     => current_time('mysql'),
				'reason'       => isset($research_row['reason']) ? $research_row['reason'] : '',
			);
		}

		$approved_rows = $this->author_topics_repository->get_all_approved_for_queue();
		foreach (array_slice($approved_rows, 0, 10) as $approved_row) {
			$raw_suggestions[] = array(
				'title'        => isset($approved_row->topic) ? $approved_row->topic : '',
				'topic'        => isset($approved_row->topic) ? $approved_row->topic : '',
				'subtitle'     => !empty($approved_row->author_name) ? $approved_row->author_name : __('Approved topic queue', 'ai-post-scheduler'),
				'source'       => 'approved_topic',
				'source_label' => __('Approved topic', 'ai-post-scheduler'),
				'next_run'     => current_time('mysql'),
				'author_name'  => !empty($approved_row->author_name) ? $approved_row->author_name : '',
			);
		}

		$deduped = array();
		$seen    = array();
		foreach ($raw_suggestions as $suggestion) {
			$normalised = $this->normalise_mix_item($suggestion, true);
			if (empty($normalised['title']) || in_array($normalised['topic_key'], $selected_keys, true) || in_array($normalised['topic_key'], $seen, true)) {
				continue;
			}
			$seen[]    = $normalised['topic_key'];
			$deduped[] = array_merge($suggestion, $normalised);
		}

		$scored = $this->score_candidate_items($deduped, $args);
		$items  = !empty($scored['items']) ? $scored['items'] : array();
		usort(
			$items,
			function ($left, $right) {
				$impact_order = array('improved' => 0, 'neutral' => 1, 'worsened' => 2);
				$left_impact  = isset($impact_order[ $left['impact'] ]) ? $impact_order[ $left['impact'] ] : 3;
				$right_impact = isset($impact_order[ $right['impact'] ]) ? $impact_order[ $right['impact'] ] : 3;
				if ($left_impact !== $right_impact) {
					return $left_impact - $right_impact;
				}
				return $right['score'] <=> $left['score'];
			}
		);

		return array_slice(
			array_values(
				array_filter(
					$items,
					function ($item) {
						return $item['score'] >= 65 || 'improved' === $item['impact'];
					}
				)
			),
			0,
			6
		);
	}

	/**
	 * Return upcoming active schedule items normalised for mix analysis.
	 *
	 * @param int $window_days Rolling window size.
	 * @return array
	 */
	private function get_upcoming_mix_items($window_days) {
		$end_timestamp = current_time('timestamp') + ($window_days * 86400);
		$items         = array();

		foreach ($this->get_all() as $schedule) {
			if (empty($schedule['is_active']) || empty($schedule['next_run'])) {
				continue;
			}
			$next_run = strtotime($schedule['next_run']);
			if (!$next_run || $next_run > $end_timestamp) {
				continue;
			}
			$items[] = $this->normalise_mix_item($schedule, false);
		}

		return $items;
	}

	/**
	 * Convert one row into the canonical mix-analysis structure.
	 *
	 * @param array $item         Schedule or candidate item.
	 * @param bool  $is_candidate Whether the item is a proposed candidate.
	 * @return array
	 */
	private function normalise_mix_item($item, $is_candidate = false) {
		$title      = '';
		$subtitle   = '';
		$topic      = '';
		$author     = '';
		$next_run   = '';
		$type       = '';
		$source     = '';
		$source_lbl = '';
		$reason     = '';

		if (is_array($item)) {
			$title      = isset($item['title']) ? (string) $item['title'] : '';
			$subtitle   = isset($item['subtitle']) ? (string) $item['subtitle'] : '';
			$topic      = isset($item['topic']) ? (string) $item['topic'] : $title;
			$author     = isset($item['author_name']) ? (string) $item['author_name'] : '';
			$next_run   = isset($item['next_run']) ? (string) $item['next_run'] : '';
			$type       = isset($item['type']) ? (string) $item['type'] : '';
			$source     = isset($item['source']) ? (string) $item['source'] : $type;
			$source_lbl = isset($item['source_label']) ? (string) $item['source_label'] : ucfirst(str_replace('_', ' ', $source));
			$reason     = isset($item['reason']) ? (string) $item['reason'] : '';
		} elseif (is_object($item)) {
			$title    = isset($item->title) ? (string) $item->title : '';
			$subtitle = isset($item->subtitle) ? (string) $item->subtitle : '';
			$topic    = isset($item->topic) ? (string) $item->topic : $title;
			$author   = isset($item->author_name) ? (string) $item->author_name : '';
			$next_run = isset($item->next_run) ? (string) $item->next_run : '';
			$type     = isset($item->type) ? (string) $item->type : '';
		}

		$combined_text = trim($title . ' ' . $topic . ' ' . $subtitle);
		$beat          = $this->infer_beat($combined_text, $subtitle);
		$format        = $this->infer_format($combined_text);
		$evergreen     = $this->is_evergreen_topic($combined_text);
		$topic_key     = $this->build_topic_key($topic ? $topic : $title);

		return array(
			'id'           => is_array($item) && isset($item['id']) ? $item['id'] : 0,
			'title'        => $title,
			'topic'        => $topic ? $topic : $title,
			'subtitle'     => $subtitle,
			'author_name'  => $author,
			'next_run'     => $next_run,
			'type'         => $type,
			'beat'         => $beat,
			'format'       => $format,
			'story_type'   => $format,
			'evergreen'    => $evergreen,
			'topic_key'    => $topic_key,
			'is_candidate' => $is_candidate,
			'source'       => $source,
			'source_label' => $source_lbl,
			'reason'       => $reason,
		);
	}

	/**
	 * Create one candidate score row.
	 *
	 * @param array $candidate    Normalised candidate.
	 * @param array $before_report Report before scheduling the candidate.
	 * @param array $after_report  Report after scheduling the candidate.
	 * @param array $rules         Editorial mix rules.
	 * @param float $before_score  Prior imbalance score.
	 * @param float $after_score   New imbalance score.
	 * @return array
	 */
	private function build_candidate_score_row($candidate, $before_report, $after_report, $rules, $before_score, $after_score) {
		$score    = 100;
		$notes    = array();
		$warnings = array();

		$beat_share = isset($after_report['beats'][ $candidate['beat'] ]['share']) ? $after_report['beats'][ $candidate['beat'] ]['share'] : 0;
		if ($beat_share > $rules['max_beat_share']) {
			$overflow   = round($beat_share - $rules['max_beat_share'], 1);
			$score     -= min(30, (int) ceil($overflow) + 8);
			$warnings[] = sprintf(__('Beat concentration would rise to %s%%.', 'ai-post-scheduler'), number_format_i18n($beat_share, 1));
			$notes[]    = sprintf(__('This topic pushes the %s beat above the %s%% weekly cap.', 'ai-post-scheduler'), ucfirst($candidate['beat']), number_format_i18n($rules['max_beat_share'], 0));
		}

		$topic_repeat_count = isset($after_report['topic_repeats'][ $candidate['topic_key'] ]) ? $after_report['topic_repeats'][ $candidate['topic_key'] ] : 0;
		if ($topic_repeat_count > $rules['max_same_topic_repeats']) {
			$overflow   = $topic_repeat_count - $rules['max_same_topic_repeats'];
			$score     -= min(30, 12 * $overflow);
			$warnings[] = sprintf(__('Same-topic repeats would hit %d.', 'ai-post-scheduler'), $topic_repeat_count);
			$notes[]    = __('Consider swapping in a related but distinct angle to avoid topic fatigue.', 'ai-post-scheduler');
		}

		$evergreen_before = isset($before_report['evergreen_share']) ? $before_report['evergreen_share'] : 0;
		$evergreen_after  = isset($after_report['evergreen_share']) ? $after_report['evergreen_share'] : 0;
		if ($evergreen_after < $rules['min_evergreen_quota']) {
			if ($candidate['evergreen']) {
				$score  += 8;
				$notes[] = __('Evergreen coverage improves the weekly reserve.', 'ai-post-scheduler');
			} else {
				$score     -= 14;
				$warnings[] = sprintf(__('Evergreen share would remain below the %s%% floor.', 'ai-post-scheduler'), number_format_i18n($rules['min_evergreen_quota'], 0));
			}
		} elseif ($candidate['evergreen'] && $evergreen_after > $evergreen_before) {
			$score  += 6;
			$notes[] = __('Adds durable coverage without relying on a timely peg.', 'ai-post-scheduler');
		}

		$format_target = isset($rules['target_format_mix'][ $candidate['format'] ]) ? $rules['target_format_mix'][ $candidate['format'] ] : 0;
		$format_before = isset($before_report['formats'][ $candidate['format'] ]['share']) ? $before_report['formats'][ $candidate['format'] ]['share'] : 0;
		$format_after  = isset($after_report['formats'][ $candidate['format'] ]['share']) ? $after_report['formats'][ $candidate['format'] ]['share'] : 0;
		$before_gap    = abs($format_target - $format_before);
		$after_gap     = abs($format_target - $format_after);
		if ($after_gap < $before_gap) {
			$score  += min(12, (int) round($before_gap - $after_gap));
			$notes[] = sprintf(__('Moves %s coverage closer to its %s%% target.', 'ai-post-scheduler'), $candidate['format'], number_format_i18n($format_target, 0));
		} elseif ($after_gap > $before_gap) {
			$score     -= min(12, (int) round($after_gap - $before_gap));
			$warnings[] = sprintf(__('Pulls %s coverage away from its target mix.', 'ai-post-scheduler'), $candidate['format']);
		}

		$impact = 'neutral';
		if ($after_score < $before_score) {
			$impact = 'improved';
			$score += 10;
			$notes[] = __('Overall calendar balance improves when this item is added.', 'ai-post-scheduler');
		} elseif ($after_score > $before_score) {
			$impact = 'worsened';
			$score -= 10;
			$warnings[] = __('Overall weekly mix becomes less balanced.', 'ai-post-scheduler');
		}

		return array_merge(
			$candidate,
			array(
				'score'             => max(0, min(100, (int) round($score))),
				'impact'            => $impact,
				'notes'             => array_values(array_unique($notes)),
				'warnings'          => array_values(array_unique($warnings)),
				'before_imbalance'  => round($before_score, 2),
				'after_imbalance'   => round($after_score, 2),
			)
		);
	}

	/**
	 * Build a mix report from a list of normalised items.
	 *
	 * @param array $items       Normalised items.
	 * @param int   $window_days Rolling window size.
	 * @return array
	 */
	private function build_mix_report_from_items($items, $window_days) {
		$rules             = $this->get_editorial_mix_rules();
		$total_items       = count($items);
		$beat_counts       = array();
		$author_counts     = array();
		$format_counts     = array_fill_keys(array_keys($rules['target_format_mix']), 0);
		$topic_repeats     = array();
		$evergreen_count   = 0;
		$warnings          = array();

		foreach ($items as $item) {
			$beat   = !empty($item['beat']) ? $item['beat'] : 'general';
			$format = !empty($item['format']) ? $item['format'] : 'analysis';
			$author = !empty($item['author_name']) ? $item['author_name'] : __('Unassigned', 'ai-post-scheduler');

			if (!isset($beat_counts[ $beat ])) {
				$beat_counts[ $beat ] = 0;
			}
			if (!isset($author_counts[ $author ])) {
				$author_counts[ $author ] = 0;
			}
			if (!isset($format_counts[ $format ])) {
				$format_counts[ $format ] = 0;
			}

			$beat_counts[ $beat ]++;
			$author_counts[ $author ]++;
			$format_counts[ $format ]++;
			$topic_repeats[ $item['topic_key'] ] = isset($topic_repeats[ $item['topic_key'] ]) ? $topic_repeats[ $item['topic_key'] ] + 1 : 1;
			if (!empty($item['evergreen'])) {
				$evergreen_count++;
			}
		}

		$beats   = $this->format_dimension_breakdown($beat_counts, $total_items);
		$authors = $this->format_dimension_breakdown($author_counts, $total_items);
		$formats = $this->format_dimension_breakdown($format_counts, $total_items, $rules['target_format_mix']);

		foreach ($beats as $label => $data) {
			if ($data['share'] > $rules['max_beat_share']) {
				$warnings[] = array(
					'type'    => 'beat',
					'label'   => ucfirst($label),
					'message' => sprintf(__('Upcoming calendar over-indexes the %1$s beat at %2$s%%.', 'ai-post-scheduler'), ucfirst($label), number_format_i18n($data['share'], 1)),
				);
			}
		}

		foreach ($authors as $label => $data) {
			if ('Unassigned' === $label) {
				continue;
			}
			if ($data['share'] > $rules['max_beat_share']) {
				$warnings[] = array(
					'type'    => 'author',
					'label'   => $label,
					'message' => sprintf(__('One author accounts for %1$s%% of the next %2$d scheduled items.', 'ai-post-scheduler'), number_format_i18n($data['share'], 1), $total_items),
				);
			}
		}

		foreach ($formats as $label => $data) {
			$target = isset($rules['target_format_mix'][ $label ]) ? $rules['target_format_mix'][ $label ] : 0;
			if ($data['share'] > ($target + 10)) {
				$warnings[] = array(
					'type'    => 'format',
					'label'   => ucfirst($label),
					'message' => sprintf(__('Story type %1$s is running hot at %2$s%% versus a %3$s%% target.', 'ai-post-scheduler'), ucfirst($label), number_format_i18n($data['share'], 1), number_format_i18n($target, 0)),
				);
			}
		}

		$evergreen_share = $total_items > 0 ? round(($evergreen_count / $total_items) * 100, 1) : 0;
		if ($total_items > 0 && $evergreen_share < $rules['min_evergreen_quota']) {
			$warnings[] = array(
				'type'    => 'evergreen',
				'label'   => __('Evergreen reserve', 'ai-post-scheduler'),
				'message' => sprintf(__('Only %1$s%% of the next %2$d items look evergreen; target is at least %3$s%%.', 'ai-post-scheduler'), number_format_i18n($evergreen_share, 1), $total_items, number_format_i18n($rules['min_evergreen_quota'], 0)),
			);
		}

		return array(
			'window_days'      => $window_days,
			'total_items'      => $total_items,
			'evergreen_count'  => $evergreen_count,
			'evergreen_share'  => $evergreen_share,
			'beats'            => $beats,
			'authors'          => $authors,
			'formats'          => $formats,
			'topic_repeats'    => $topic_repeats,
			'warnings'         => $warnings,
			'imbalance_score'  => $this->calculate_imbalance_score($beats, $authors, $formats, $topic_repeats, $evergreen_share, $rules),
		);
	}

	/**
	 * Format counts as breakdown rows with share percentages.
	 *
	 * @param array $counts        Raw counts.
	 * @param int   $total_items   Total number of items.
	 * @param array $target_lookup Optional target map.
	 * @return array
	 */
	private function format_dimension_breakdown($counts, $total_items, $target_lookup = array()) {
		arsort($counts);
		$rows = array();
		foreach ($counts as $label => $count) {
			$rows[ $label ] = array(
				'count'  => (int) $count,
				'share'  => $total_items > 0 ? round(($count / $total_items) * 100, 1) : 0,
				'target' => isset($target_lookup[ $label ]) ? (int) $target_lookup[ $label ] : null,
			);
		}
		return $rows;
	}

	/**
	 * Calculate a single imbalance score for analytics/explanations.
	 *
	 * @param array $beats           Beat breakdown.
	 * @param array $authors         Author breakdown.
	 * @param array $formats         Format breakdown.
	 * @param array $topic_repeats   Topic repeat counts.
	 * @param float $evergreen_share Evergreen percentage.
	 * @param array $rules           Mix rules.
	 * @return float
	 */
	private function calculate_imbalance_score($beats, $authors, $formats, $topic_repeats, $evergreen_share, $rules) {
		$score = 0.0;
		foreach ($beats as $beat) {
			if ($beat['share'] > $rules['max_beat_share']) {
				$score += ($beat['share'] - $rules['max_beat_share']);
			}
		}
		foreach ($authors as $author_label => $author) {
			if ('Unassigned' !== $author_label && $author['share'] > $rules['max_beat_share']) {
				$score += ($author['share'] - $rules['max_beat_share']) / 2;
			}
		}
		foreach ($formats as $format_label => $format) {
			$target = isset($rules['target_format_mix'][ $format_label ]) ? $rules['target_format_mix'][ $format_label ] : 0;
			$score += abs($format['share'] - $target) / 4;
		}
		if ($evergreen_share < $rules['min_evergreen_quota']) {
			$score += ($rules['min_evergreen_quota'] - $evergreen_share);
		}
		foreach ($topic_repeats as $repeat_count) {
			if ($repeat_count > $rules['max_same_topic_repeats']) {
				$score += 8 * ($repeat_count - $rules['max_same_topic_repeats']);
			}
		}
		return round($score, 2);
	}

	/**
	 * Infer a beat label from a title/topic string.
	 *
	 * @param string $text     Full candidate text.
	 * @param string $fallback Fallback source.
	 * @return string
	 */
	private function infer_beat($text, $fallback = '') {
		$text = strtolower(wp_strip_all_tags($text . ' ' . $fallback));
		$map  = array(
			'politics'      => array('politics', 'policy', 'election', 'government'),
			'business'      => array('business', 'economy', 'finance', 'market', 'startup'),
			'technology'    => array('tech', 'technology', 'ai ', 'saas', 'software', 'app'),
			'health'        => array('health', 'wellness', 'medical', 'nutrition'),
			'science'       => array('science', 'research', 'space', 'climate'),
			'sports'        => array('sports', 'game', 'season', 'playoff'),
			'culture'       => array('culture', 'media', 'entertainment', 'tv', 'film'),
			'lifestyle'     => array('lifestyle', 'travel', 'food', 'style', 'home'),
			'education'     => array('education', 'learning', 'career', 'skills'),
		);
		foreach ($map as $beat => $keywords) {
			foreach ($keywords as $keyword) {
				if (false !== strpos($text, trim($keyword))) {
					return $beat;
				}
			}
		}
		if (preg_match('/^([^:\-|]+)[:\-|]/', trim($fallback ? $fallback : $text), $matches)) {
			$candidate = sanitize_title($matches[1]);
			if (!empty($candidate)) {
				return str_replace('-', ' ', $candidate);
			}
		}
		return 'general';
	}

	/**
	 * Infer story format from text.
	 *
	 * @param string $text Topic/title text.
	 * @return string
	 */
	private function infer_format($text) {
		$text = strtolower(wp_strip_all_tags($text));
		if (preg_match('/(opinion|editorial|column|view|perspective)/', $text)) {
			return 'opinion';
		}
		if (preg_match('/(analysis|explainer|breakdown|deep dive|decoded)/', $text)) {
			return 'analysis';
		}
		if (preg_match('/(guide|how to|tutorial|playbook|checklist)/', $text)) {
			return 'guide';
		}
		if (preg_match('/(roundup|best |top |list of|what to watch|picks)/', $text)) {
			return 'roundup';
		}
		if (preg_match('/(news|update|latest|breaking|today|this week)/', $text)) {
			return 'news';
		}
		return 'analysis';
	}

	/**
	 * Determine whether a topic is likely evergreen.
	 *
	 * @param string $text Topic/title text.
	 * @return bool
	 */
	private function is_evergreen_topic($text) {
		$text = strtolower(wp_strip_all_tags($text));
		if (preg_match('/(breaking|latest|today|this week|weekly|launch|earnings|live|trending|202[0-9])/', $text)) {
			return false;
		}
		return true;
	}

	/**
	 * Build a repeat-detection key for a topic.
	 *
	 * @param string $text Topic text.
	 * @return string
	 */
	private function build_topic_key($text) {
		$text = strtolower(wp_strip_all_tags($text));
		$text = preg_replace('/[^a-z0-9\s]+/', ' ', $text);
		$text = preg_replace('/\s+/', ' ', trim($text));
		$words = array_slice(array_filter(explode(' ', $text)), 0, 6);
		return implode(' ', $words);
	}

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

		$authors           = $this->authors_repository->get_all();
		$result            = array();
		$author_topics_tbl = $wpdb->prefix . 'aips_author_topics';

		$topic_counts_raw = $wpdb->get_results(
			"SELECT author_id, COUNT(*) AS cnt FROM {$author_topics_tbl} GROUP BY author_id"
		);
		$topic_counts = array();
		foreach ($topic_counts_raw as $row) {
			$topic_counts[$row->author_id] = (int) $row->cnt;
		}

		foreach ($authors as $author) {
			if (empty($author->topic_generation_frequency)) {
				continue;
			}
			if (empty($author->topic_generation_next_run) && empty($author->topic_generation_last_run)) {
				continue;
			}

			$is_active = isset($author->topic_generation_is_active)
				? (int) $author->topic_generation_is_active
				: 1;
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
	 * Each active author with `post_generation_next_run` set appears as one row.
	 *
	 * @return array
	 */
	private function get_author_post_schedules() {
		global $wpdb;

		$authors           = $this->authors_repository->get_all();
		$result            = array();
		$topic_logs_tbl    = $wpdb->prefix . 'aips_author_topic_logs';
		$author_topics_tbl = $wpdb->prefix . 'aips_author_topics';

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
