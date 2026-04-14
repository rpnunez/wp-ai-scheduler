<?php
/**
 * Telemetry Controller
 *
 * Handles Telemetry admin page rendering and AJAX data loading.
 *
 * @package AI_Post_Scheduler
 * @since   2.4.0
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Telemetry_Controller
 */
class AIPS_Telemetry_Controller {

	/**
	 * @var AIPS_Telemetry_Repository
	 */
	private $repository;

	/**
	 * Resolve dependencies and register AJAX handlers.
	 */
	public function __construct() {
		$container = AIPS_Container::get_instance();
		$this->repository = $container->make(AIPS_Telemetry_Repository::class);

		add_action('wp_ajax_aips_get_telemetry', array($this, 'ajax_get_telemetry'));
		add_action('wp_ajax_aips_get_telemetry_details', array($this, 'ajax_get_telemetry_details'));
	}

	/**
	 * Render the Telemetry admin page.
	 *
	 * @return void
	 */
	public function render_page() {
		if (!AIPS_Config::get_instance()->get_option('aips_enable_telemetry')) {
			wp_die(esc_html__('Telemetry is currently disabled.', 'ai-post-scheduler'));
		}

		$end_date   = date_i18n('Y-m-d', current_time('timestamp'));
		$start_date = date_i18n('Y-m-d', strtotime('-29 days', current_time('timestamp')));
		$per_page   = 25;

		include AIPS_PLUGIN_DIR . 'templates/admin/telemetry.php';
	}

	/**
	 * Return telemetry charts and paginated rows for the selected date range.
	 *
	 * @return void
	 */
	public function ajax_get_telemetry() {
		check_ajax_referer('aips_get_telemetry', 'nonce');

		if (!current_user_can('manage_options')) {
			AIPS_Ajax_Response::permission_denied();
		}

		if (!AIPS_Config::get_instance()->get_option('aips_enable_telemetry')) {
			AIPS_Ajax_Response::error(__('Telemetry is disabled.', 'ai-post-scheduler'));
		}

		$today      = date_i18n('Y-m-d', current_time('timestamp'));
		$start_date = $this->sanitize_date(
			isset($_POST['start_date']) ? sanitize_text_field(wp_unslash($_POST['start_date'])) : '',
			date_i18n('Y-m-d', strtotime('-29 days', current_time('timestamp')))
		);
		$end_date   = $this->sanitize_date(
			isset($_POST['end_date']) ? sanitize_text_field(wp_unslash($_POST['end_date'])) : '',
			$today
		);

		if ($start_date > $end_date) {
			$temp       = $start_date;
			$start_date = $end_date;
			$end_date   = $temp;
		}

		$requested_per_page = isset($_POST['per_page']) ? absint(wp_unslash($_POST['per_page'])) : 25;
		$allowed_per_page   = array(25, 50, 100);
		$per_page           = in_array($requested_per_page, $allowed_per_page, true) ? $requested_per_page : 25;
		$page               = max(1, isset($_POST['page']) ? absint(wp_unslash($_POST['page'])) : 1);

		$total       = $this->repository->count_filtered($start_date, $end_date);
		$total_pages = max(1, (int) ceil($total / max(1, $per_page)));
		$page        = min($page, $total_pages);
		$offset      = ($page - 1) * $per_page;

		$rows        = $this->repository->get_filtered_page($start_date, $end_date, $per_page, $offset);
		$chart_rows  = $this->repository->get_daily_rollup($start_date, $end_date);
		$chart_data  = $this->build_chart_series($start_date, $end_date, $chart_rows);

		AIPS_Ajax_Response::success(array(
			'rows'        => $rows,
			'total'       => $total,
			'per_page'    => $per_page,
			'total_pages' => $total_pages,
			'page'        => $page,
			'start_date'  => $start_date,
			'end_date'    => $end_date,
			'charts'      => $chart_data,
		));
	}

	/**
	 * Return a single telemetry row and decoded payload details.
	 *
	 * @return void
	 */
	public function ajax_get_telemetry_details() {
		check_ajax_referer('aips_get_telemetry_details', 'nonce');

		if (!current_user_can('manage_options')) {
			AIPS_Ajax_Response::permission_denied();
		}

		if (!AIPS_Config::get_instance()->get_option('aips_enable_telemetry')) {
			AIPS_Ajax_Response::error(__('Telemetry is disabled.', 'ai-post-scheduler'));
		}

		$row_id = isset($_POST['id']) ? absint(wp_unslash($_POST['id'])) : 0;
		if ($row_id < 1) {
			AIPS_Ajax_Response::invalid_request(__('A valid telemetry row ID is required.', 'ai-post-scheduler'));
		}

		$row = $this->repository->get_row($row_id);
		if (!$row) {
			AIPS_Ajax_Response::not_found(__('Telemetry row', 'ai-post-scheduler'));
		}

		$payload_decoded = null;
		$events          = array();

		if (!empty($row['payload'])) {
			$payload_decoded = json_decode($row['payload'], true);
			if (is_array($payload_decoded) && !empty($payload_decoded['events']) && is_array($payload_decoded['events'])) {
				$events = $payload_decoded['events'];
			}
		}

		AIPS_Ajax_Response::success(array(
			'row'             => $row,
			'payload_decoded' => is_array($payload_decoded) ? $payload_decoded : null,
			'events'          => $events,
		));
	}

	/**
	 * Sanitize and validate a Y-m-d date string.
	 *
	 * @param string $value    Submitted value.
	 * @param string $fallback Fallback date.
	 * @return string
	 */
	private function sanitize_date($value, $fallback) {
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
	 * Expand daily aggregate rows into continuous chart series.
	 *
	 * @param string $start_date Inclusive start date.
	 * @param string $end_date   Inclusive end date.
	 * @param array  $rows       Repository rollup rows keyed by metric date.
	 * @return array<string, array>
	 */
	private function build_chart_series($start_date, $end_date, array $rows) {
		$indexed = array();
		foreach ($rows as $row) {
			$indexed[$row['metric_date']] = $row;
		}

		$labels         = array();
		$requests       = array();
		$queries        = array();
		$peak_memory_mb = array();
		$avg_elapsed_ms = array();

		$cursor = strtotime($start_date);
		$end    = strtotime($end_date);

		while ($cursor <= $end) {
			$metric_date = date('Y-m-d', $cursor);
			$row         = isset($indexed[$metric_date]) ? $indexed[$metric_date] : array();

			$labels[]         = $metric_date;
			$requests[]       = isset($row['request_count']) ? (int) $row['request_count'] : 0;
			$queries[]        = isset($row['total_queries']) ? (int) $row['total_queries'] : 0;
			$peak_memory_mb[] = isset($row['peak_memory_bytes_max']) ? round(((int) $row['peak_memory_bytes_max']) / 1048576, 2) : 0;
			$avg_elapsed_ms[] = isset($row['avg_elapsed_ms']) ? round((float) $row['avg_elapsed_ms'], 2) : 0;

			$cursor = strtotime('+1 day', $cursor);
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