<?php
/**
 * Dashboard Metrics Service
 *
 * Builds the date-range-scoped dashboard payload (summary counters, recent
 * activity lists, and daily chart series). Shared by the admin-ajax handler
 * and the REST endpoint so both return an identical shape.
 *
 * @package AI_Post_Scheduler
 * @since 3.7.0
 */

if (!defined('ABSPATH')) {
	exit;
}

class AIPS_Dashboard_Metrics_Service {

	/** Maximum number of days allowed in the dashboard date filter. */
	const MAX_DATE_RANGE_DAYS = 365;

	/** Row limit for each recent-activity list. */
	const LIST_LIMIT = 10;

	/**
	 * @var AIPS_Dashboard_Repository
	 */
	private $repository;

	/**
	 * @param AIPS_Dashboard_Repository|null $repository Optional repository (for tests).
	 */
	public function __construct($repository = null) {
		$this->repository = $repository ?: new AIPS_Dashboard_Repository();
	}

	/**
	 * Normalize a dashboard date filter value to a valid YYYY-MM-DD date.
	 *
	 * @param string $value    Raw date filter value.
	 * @param string $fallback Fallback date in YYYY-MM-DD format.
	 * @return string
	 */
	public function normalize_date_input($value, $fallback) {
		if (empty($value) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
			return $fallback;
		}

		list($year, $month, $day) = array_map('intval', explode('-', $value));

		return checkdate($month, $day, $year) ? $value : $fallback;
	}

	/**
	 * Retrieve the site date format via AIPS_Cache with a one-week expiration.
	 *
	 * @return string
	 */
	public function get_cached_date_format() {
		return AIPS_Cache_Factory::instance()->remember('aips_date_format', WEEK_IN_SECONDS, function () {
			return get_option('date_format', 'Y-m-d');
		});
	}

	/**
	 * Resolve raw date inputs into a validated, bounded range.
	 *
	 * Defaults to the first of the current month → today (site timezone),
	 * swaps to the default when from > to, and clamps to MAX_DATE_RANGE_DAYS.
	 *
	 * @param string $date_from_input Raw `date_from` value.
	 * @param string $date_to_input   Raw `date_to` value.
	 * @return array{date_from:string,date_to:string,from_ts:int,to_ts:int}
	 */
	public function resolve_range($date_from_input, $date_to_input) {
		$site_now     = AIPS_DateTime::now()->toSiteTimezone();
		$default_from = $site_now->format('Y-m-01');
		$default_to   = $site_now->format('Y-m-d');

		$date_from = $this->normalize_date_input($date_from_input, $default_from);
		$date_to   = $this->normalize_date_input($date_to_input, $default_to);

		$timezone = wp_timezone();
		try {
			$date_from_obj = new DateTimeImmutable($date_from . ' 00:00:00', $timezone);
			$date_to_obj   = new DateTimeImmutable($date_to . ' 23:59:59', $timezone);

			if ($date_from_obj > $date_to_obj) {
				$date_from     = $default_from;
				$date_to       = $default_to;
				$date_from_obj = new DateTimeImmutable($date_from . ' 00:00:00', $timezone);
				$date_to_obj   = new DateTimeImmutable($date_to . ' 23:59:59', $timezone);
			}

			if ($date_from_obj->diff($date_to_obj)->days >= self::MAX_DATE_RANGE_DAYS) {
				$date_from_obj = $date_to_obj->sub(new DateInterval('P' . (self::MAX_DATE_RANGE_DAYS - 1) . 'D'))->setTime(0, 0, 0);
				$date_from     = $date_from_obj->format('Y-m-d');
			}
		} catch (Exception $e) {
			$date_from     = $default_from;
			$date_to       = $default_to;
			$date_from_obj = new DateTimeImmutable($date_from . ' 00:00:00', $timezone);
			$date_to_obj   = new DateTimeImmutable($date_to . ' 23:59:59', $timezone);
		}

		return array(
			'date_from' => $date_from,
			'date_to'   => $date_to,
			'from_ts'   => $date_from_obj->getTimestamp(),
			'to_ts'     => $date_to_obj->getTimestamp(),
		);
	}

	/**
	 * Build the full dashboard payload for a date range.
	 *
	 * @param string $date_from_input Raw `date_from` value (YYYY-MM-DD or empty).
	 * @param string $date_to_input   Raw `date_to` value (YYYY-MM-DD or empty).
	 * @return array<string, mixed>
	 */
	public function get_data($date_from_input, $date_to_input) {
		$range     = $this->resolve_range($date_from_input, $date_to_input);
		$date_from = $range['date_from'];
		$date_to   = $range['date_to'];
		$from_ts   = $range['from_ts'];
		$to_ts     = $range['to_ts'];

		$summary_stats          = $this->repository->get_summary_stats($from_ts, $to_ts);
		$total_in_period        = isset($summary_stats['total']) ? (int) $summary_stats['total'] : 0;
		$completed_in_period    = isset($summary_stats['completed']) ? (int) $summary_stats['completed'] : 0;
		$failed_in_period       = isset($summary_stats['failed']) ? (int) $summary_stats['failed'] : 0;
		$partial_in_period      = isset($summary_stats['partial']) ? (int) $summary_stats['partial'] : 0;
		$success_rate_in_period = $total_in_period > 0 ? round(($completed_in_period / $total_in_period) * 100, 1) : 100.0;

		$topics_created_stats     = $this->repository->get_topics_stats($from_ts, $to_ts);
		$topics_created_in_period = isset($topics_created_stats['total']) ? (int) $topics_created_stats['total'] : 0;
		$topics_pending_in_period = isset($topics_created_stats['pending']) ? (int) $topics_created_stats['pending'] : 0;

		$ai_stats_row            = $this->repository->get_ai_stats($from_ts, $to_ts);
		$ai_calls_in_period      = isset($ai_stats_row['ai_calls']) ? (int) $ai_stats_row['ai_calls'] : 0;
		$ai_errors_in_period     = isset($ai_stats_row['ai_errors']) ? (int) $ai_stats_row['ai_errors'] : 0;
		$ai_error_rate_in_period = $ai_calls_in_period > 0 ? round(($ai_errors_in_period / $ai_calls_in_period) * 100, 1) : 0.0;

		$date_format = $this->get_cached_date_format();

		$recent_posts   = $this->repository->get_recent_posts($from_ts, $to_ts, self::LIST_LIMIT);
		$posts_by_topic = $this->repository->get_posts_by_topic($from_ts, $to_ts, self::LIST_LIMIT);
		$this->prime_post_caches($recent_posts, $posts_by_topic);

		$recent_posts_formatted = array();
		foreach ($recent_posts as $item) {
			$recent_posts_formatted[] = array(
				'id'                   => $item->id,
				'post_id'              => $item->post_id,
				'generated_title'      => esc_html($item->generated_title ?: __('Untitled', 'ai-post-scheduler')),
				'template_name'        => esc_html($item->template_name ?: __('Custom Author Workflow', 'ai-post-scheduler')),
				'creation_method'      => esc_html(ucfirst(str_replace('_', ' ', $item->creation_method))),
				'status'               => esc_html($item->status),
				'status_badge'         => esc_attr($this->status_badge($item->status, 'pending')),
				'created_at_formatted' => esc_html(AIPS_DateTime::formatRelativeOrAbsolute($item->created_at, $date_format)),
				'edit_url'             => $item->post_id ? esc_url(get_edit_post_link($item->post_id)) : '',
			);
		}

		$recent_topics           = $this->repository->get_recent_topics($from_ts, $to_ts, self::LIST_LIMIT);
		$recent_topics_formatted = array();
		foreach ($recent_topics as $item) {
			$recent_topics_formatted[] = array(
				'id'                     => $item->id,
				'topic_title'            => esc_html($item->topic_title),
				'author_name'            => esc_html($item->author_name ?: __('Unknown Author', 'ai-post-scheduler')),
				'score'                  => esc_html($item->score),
				'status'                 => esc_html($item->status),
				'status_badge'           => esc_attr($item->status === 'approved' ? 'success' : ($item->status === 'rejected' ? 'error' : ($item->status === 'pending' ? 'warning' : 'neutral'))),
				'generated_at_formatted' => esc_html(AIPS_DateTime::formatRelativeOrAbsolute($item->generated_at, $date_format)),
			);
		}

		$posts_by_topic_formatted = array();
		foreach ($posts_by_topic as $item) {
			$posts_by_topic_formatted[] = array(
				'post_id'                => $item->post_id,
				'generated_title'        => esc_html($item->generated_title ?: __('Untitled', 'ai-post-scheduler')),
				'topic_title'            => esc_html($item->topic_title),
				'author_name'            => esc_html($item->author_name ?: __('Unknown Author', 'ai-post-scheduler')),
				'completed_at_formatted' => esc_html(AIPS_DateTime::formatRelativeOrAbsolute($item->completed_at, $date_format)),
				'edit_url'               => $item->post_id ? esc_url(get_edit_post_link($item->post_id)) : '',
			);
		}

		$executed_schedules           = $this->repository->get_executed_schedules($from_ts, $to_ts, self::LIST_LIMIT);
		$executed_schedules_formatted = array();
		$method_labels                = array(
			'scheduled'        => __('Template Automation', 'ai-post-scheduler'),
			'author_topic_gen' => __('Author Topic Cron', 'ai-post-scheduler'),
			'author_post_gen'  => __('Author Post Cron', 'ai-post-scheduler'),
			'batch_job'        => __('Batch Processor Slice', 'ai-post-scheduler'),
		);
		foreach ($executed_schedules as $item) {
			$display_title = $item->schedule_title ?: ($item->template_name ?: ($item->author_name ?: __('General Schedule', 'ai-post-scheduler')));
			$method_label  = isset($method_labels[$item->creation_method]) ? $method_labels[$item->creation_method] : ucfirst(str_replace('_', ' ', $item->creation_method));

			$executed_schedules_formatted[] = array(
				'id'                   => $item->id,
				'display_title'        => esc_html($display_title),
				'method_label'         => esc_html($method_label),
				'status'               => esc_html($item->status),
				'status_badge'         => esc_attr($this->status_badge($item->status, 'processing')),
				'created_at_formatted' => esc_html(AIPS_DateTime::formatRelativeOrAbsolute($item->created_at, $date_format)),
			);
		}

		return array(
			'date_from_formatted'      => date_i18n($date_format, strtotime($date_from)),
			'date_to_formatted'        => date_i18n($date_format, strtotime($date_to)),
			'date_from'                => $date_from,
			'date_to'                  => $date_to,
			'completed_in_period'      => $completed_in_period,
			'failed_in_period'         => $failed_in_period,
			'partial_in_period'        => $partial_in_period,
			'success_rate_in_period'   => $success_rate_in_period,
			'topics_created_in_period' => $topics_created_in_period,
			'topics_pending_in_period' => $topics_pending_in_period,
			'ai_calls_in_period'       => $ai_calls_in_period,
			'ai_errors_in_period'      => $ai_errors_in_period,
			'ai_error_rate_in_period'  => $ai_error_rate_in_period,
			'recent_posts'             => $recent_posts_formatted,
			'recent_topics'            => $recent_topics_formatted,
			'posts_by_topic'           => $posts_by_topic_formatted,
			'executed_schedules'       => $executed_schedules_formatted,
			'chart_data'               => $this->build_chart_data($date_from, $date_to, $from_ts, $to_ts),
		);
	}

	/**
	 * Build gap-free daily chart series for the range.
	 *
	 * @param string $date_from YYYY-MM-DD.
	 * @param string $date_to   YYYY-MM-DD.
	 * @param int    $from_ts   Range start timestamp.
	 * @param int    $to_ts     Range end timestamp.
	 * @return array<string, array>
	 */
	public function build_chart_data($date_from, $date_to, $from_ts, $to_ts) {
		$daily_gens_map   = $this->repository->get_daily_generation_stats($from_ts, $to_ts);
		$daily_topics_map = $this->repository->get_daily_topic_totals($from_ts, $to_ts);
		$daily_ai_map     = $this->repository->get_daily_ai_stats($from_ts, $to_ts);

		$labels = $completed = $failed = $error_rate = $topics = $ai_calls = $ai_errors = array();

		$start_date = new DateTime($date_from);
		$end_date   = new DateTime($date_to);
		$date_range = new DatePeriod($start_date, new DateInterval('P1D'), $end_date->modify('+1 day'));

		foreach ($date_range as $date) {
			$day_key  = $date->format('Y-m-d');
			$labels[] = $date->format('M j');

			$gen = isset($daily_gens_map[$day_key]) ? $daily_gens_map[$day_key] : array('completed' => 0, 'failed' => 0, 'total' => 0);

			$completed[]  = $gen['completed'];
			$failed[]     = $gen['failed'];
			$error_rate[] = $gen['total'] > 0 ? round(($gen['failed'] / $gen['total']) * 100, 1) : 0;
			$topics[]     = isset($daily_topics_map[$day_key]) ? $daily_topics_map[$day_key] : 0;
			$ai_calls[]   = isset($daily_ai_map[$day_key]) ? (int) $daily_ai_map[$day_key]['ai_calls'] : 0;
			$ai_errors[]  = isset($daily_ai_map[$day_key]) ? (int) $daily_ai_map[$day_key]['ai_errors'] : 0;
		}

		return array(
			'labels'    => $labels,
			'completed' => $completed,
			'failed'    => $failed,
			'errorRate' => $error_rate,
			'topics'    => $topics,
			'aiCalls'   => $ai_calls,
			'aiErrors'  => $ai_errors,
		);
	}

	/**
	 * Map a history/schedule status to a badge variant.
	 *
	 * @param string $status         Status value.
	 * @param string $warning_status Which status maps to the warning badge.
	 * @return string
	 */
	private function status_badge($status, $warning_status) {
		if ('completed' === $status) {
			return 'success';
		}
		if ('failed' === $status) {
			return 'error';
		}
		return $status === $warning_status ? 'warning' : 'neutral';
	}

	/**
	 * Prime WP post caches for every post referenced by the given row sets.
	 *
	 * @param array ...$row_sets Row lists carrying a `post_id` property.
	 * @return void
	 */
	private function prime_post_caches(...$row_sets) {
		$post_ids = array();
		foreach ($row_sets as $rows) {
			foreach ((array) $rows as $item) {
				if (!empty($item->post_id)) {
					$post_ids[] = (int) $item->post_id;
				}
			}
		}

		if (!empty($post_ids) && function_exists('_prime_post_caches')) {
			_prime_post_caches(array_unique(array_filter($post_ids)), false, true);
		}
	}
}
