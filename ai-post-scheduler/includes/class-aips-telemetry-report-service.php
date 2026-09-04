<?php
/**
 * Telemetry Report Service
 *
 * Validates telemetry query inputs and assembles the paginated report and
 * per-row detail payloads. Shared by the admin-ajax handlers and the REST
 * endpoints so both return an identical shape.
 *
 * @package AI_Post_Scheduler
 * @since 3.7.0
 */

if (!defined('ABSPATH')) {
	exit;
}

class AIPS_Telemetry_Report_Service {

	/** Allowed page sizes. */
	const ALLOWED_PER_PAGE = array(25, 50, 100);

	/** Default page size. */
	const DEFAULT_PER_PAGE = 25;

	/** Default look-back window in days (inclusive of today). */
	const DEFAULT_WINDOW_DAYS = 30;

	/**
	 * @var AIPS_Telemetry_Repository
	 */
	private $repository;

	/**
	 * @param AIPS_Telemetry_Repository|null $repository Optional repository (resolved from the container when null).
	 */
	public function __construct($repository = null) {
		$this->repository = $repository ?: AIPS_Container::get_instance()->make(AIPS_Telemetry_Repository::class);
	}

	/**
	 * Whether telemetry collection/reporting is enabled.
	 *
	 * @return bool
	 */
	public function is_enabled() {
		return (bool) AIPS_Config::get_instance()->get_option('aips_enable_telemetry');
	}

	/**
	 * Default start date (Y-m-d) for the report window.
	 *
	 * @return string
	 */
	public function default_start_date() {
		return AIPS_DateTime::now()->advance('-' . (self::DEFAULT_WINDOW_DAYS - 1) . ' days')->toDisplay('Y-m-d');
	}

	/**
	 * Default end date (Y-m-d) — today.
	 *
	 * @return string
	 */
	public function default_end_date() {
		return AIPS_DateTime::now()->toDisplay('Y-m-d');
	}

	/**
	 * Return the filter option lists used by the Telemetry admin page.
	 *
	 * @return array<string, array<int, array<string, string>>>
	 */
	public function get_filter_options() {
		return array(
			'types'            => array(
				array('value' => 'admin', 'label' => __('Admin', 'ai-post-scheduler')),
				array('value' => 'ajax', 'label' => __('AJAX', 'ai-post-scheduler')),
				array('value' => 'cron', 'label' => __('Cron', 'ai-post-scheduler')),
				array('value' => 'frontend', 'label' => __('Frontend', 'ai-post-scheduler')),
			),
			'event_categories' => array(
				array('value' => 'cache', 'label' => __('Cache', 'ai-post-scheduler')),
				array('value' => 'classes', 'label' => __('Classes', 'ai-post-scheduler')),
				array('value' => 'query', 'label' => __('Queries', 'ai-post-scheduler')),
				array('value' => 'performance', 'label' => __('Performance', 'ai-post-scheduler')),
				array('value' => 'general', 'label' => __('General', 'ai-post-scheduler')),
			),
			'request_methods'  => array(
				array('value' => 'GET', 'label' => 'GET'),
				array('value' => 'POST', 'label' => 'POST'),
				array('value' => 'PUT', 'label' => 'PUT'),
				array('value' => 'PATCH', 'label' => 'PATCH'),
				array('value' => 'DELETE', 'label' => 'DELETE'),
			),
		);
	}

	/**
	 * Allowed values for one filter option list.
	 *
	 * @param string $key One of 'types', 'event_categories', 'request_methods'.
	 * @return array<string>
	 */
	public function allowed_values($key) {
		$options = $this->get_filter_options();
		return isset($options[$key]) ? wp_list_pluck($options[$key], 'value') : array();
	}

	/**
	 * Sanitize and validate a Y-m-d date string, falling back when invalid.
	 *
	 * @param string $value    Submitted value.
	 * @param string $fallback Fallback date.
	 * @return string
	 */
	public function sanitize_date($value, $fallback) {
		$value = (string) $value;
		if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
			return $fallback;
		}

		$date = DateTime::createFromFormat('Y-m-d', $value);
		if (!$date || $date->format('Y-m-d') !== $value) {
			return $fallback;
		}

		return $value;
	}

	/**
	 * Normalize a requested page size to an allowed value.
	 *
	 * @param mixed $requested Requested size.
	 * @return int
	 */
	public function sanitize_per_page($requested) {
		$requested = absint($requested);
		return in_array($requested, self::ALLOWED_PER_PAGE, true) ? $requested : self::DEFAULT_PER_PAGE;
	}

	/**
	 * Sanitize request filters for telemetry queries.
	 *
	 * Accepts either a raw request array ($_POST) or already-typed REST params.
	 *
	 * @param array $source Submitted request data.
	 * @return array<string, string|bool>
	 */
	public function sanitize_filters(array $source) {
		$type           = isset($source['type']) ? sanitize_key(wp_unslash($source['type'])) : '';
		$event_category = isset($source['event_category']) ? sanitize_key(wp_unslash($source['event_category'])) : '';
		$request_method = isset($source['request_method']) ? strtoupper(sanitize_text_field(wp_unslash($source['request_method']))) : '';
		$page_search    = isset($source['page_search']) ? sanitize_text_field(wp_unslash($source['page_search'])) : '';
		$issues_only    = isset($source['issues_only']) ? rest_sanitize_boolean($source['issues_only']) : false;

		return array(
			'type'           => in_array($type, $this->allowed_values('types'), true) ? $type : '',
			'event_category' => in_array($event_category, $this->allowed_values('event_categories'), true) ? $event_category : '',
			'request_method' => in_array($request_method, $this->allowed_values('request_methods'), true) ? $request_method : '',
			'page_search'    => $page_search,
			'issues_only'    => $issues_only,
		);
	}

	/**
	 * Build the paginated report (rows + chart series) for a window and filters.
	 *
	 * Dates are validated with fallbacks and swapped if reversed; page is
	 * clamped to the last available page.
	 *
	 * @param string $start_date Raw start date.
	 * @param string $end_date   Raw end date.
	 * @param array  $filters    Raw filter inputs (see sanitize_filters()).
	 * @param mixed  $per_page   Requested page size.
	 * @param mixed  $page       Requested page number.
	 * @return array<string, mixed>
	 */
	public function get_report($start_date, $end_date, array $filters, $per_page, $page) {
		$start_date = $this->sanitize_date($start_date, $this->default_start_date());
		$end_date   = $this->sanitize_date($end_date, $this->default_end_date());

		if ($start_date > $end_date) {
			list($start_date, $end_date) = array($end_date, $start_date);
		}

		$per_page = $this->sanitize_per_page($per_page);
		$page     = max(1, absint($page));
		$filters  = $this->sanitize_filters($filters);

		$total       = $this->repository->count_filtered($start_date, $end_date, $filters);
		$total_pages = max(1, (int) ceil($total / max(1, $per_page)));
		$page        = min($page, $total_pages);
		$offset      = ($page - 1) * $per_page;

		$rows       = $this->repository->get_filtered_page($start_date, $end_date, $filters, $per_page, $offset);
		$chart_rows = $this->repository->get_daily_rollup($start_date, $end_date, $filters);

		return array(
			'rows'        => $rows,
			'total'       => $total,
			'per_page'    => $per_page,
			'total_pages' => $total_pages,
			'page'        => $page,
			'start_date'  => $start_date,
			'end_date'    => $end_date,
			'filters'     => $filters,
			'charts'      => $this->build_chart_series($chart_rows),
		);
	}

	/**
	 * Fetch a single telemetry row with its decoded payload and events.
	 *
	 * @param int $row_id Row ID.
	 * @return array<string, mixed>|null Null when the row does not exist.
	 */
	public function get_details($row_id) {
		$row = $this->repository->get_row(absint($row_id));
		if (!$row) {
			return null;
		}

		$payload_decoded = null;
		$events          = array();

		if (!empty($row['payload'])) {
			$payload_decoded = json_decode($row['payload'], true);
			if (is_array($payload_decoded) && !empty($payload_decoded['events']) && is_array($payload_decoded['events'])) {
				$events = $payload_decoded['events'];
			}
		}

		return array(
			'row'             => $row,
			'payload_decoded' => is_array($payload_decoded) ? $payload_decoded : null,
			'events'          => $events,
		);
	}

	/**
	 * Expand daily aggregate rows into chart series, skipping all-zero days.
	 *
	 * @param array $rows Repository rollup rows keyed by metric date.
	 * @return array<string, array>
	 */
	private function build_chart_series(array $rows) {
		$indexed = array();
		foreach ($rows as $row) {
			$indexed[$row['metric_date']] = $row;
		}
		ksort($indexed);

		$labels = $requests = $queries = $peak_memory_mb = $avg_elapsed_ms = array();

		foreach ($indexed as $metric_date => $row) {
			$requests_value    = isset($row['request_count']) ? (int) $row['request_count'] : 0;
			$queries_value     = isset($row['total_queries']) ? (int) $row['total_queries'] : 0;
			$peak_memory_value = isset($row['peak_memory_bytes_max']) ? round(((int) $row['peak_memory_bytes_max']) / 1048576, 2) : 0;
			$elapsed_value     = isset($row['avg_elapsed_ms']) ? round((float) $row['avg_elapsed_ms'], 2) : 0;

			if (0 === $requests_value && 0 === $queries_value && 0 === $peak_memory_value && 0 === $elapsed_value) {
				continue;
			}

			$labels[]         = $metric_date;
			$requests[]       = $requests_value;
			$queries[]        = $queries_value;
			$peak_memory_mb[] = $peak_memory_value;
			$avg_elapsed_ms[] = $elapsed_value;
		}

		return array(
			'labels'         => $labels,
			'requests'       => $requests,
			'queries'        => $queries,
			'peak_memory_mb' => $peak_memory_mb,
			'avg_elapsed_ms' => $avg_elapsed_ms,
		);
	}
}
