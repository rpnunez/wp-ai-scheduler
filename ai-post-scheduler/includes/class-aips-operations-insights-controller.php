<?php
/**
 * Operations Insights Controller
 *
 * @package AI_Post_Scheduler
 * @since 2.5.0
 */

if (!defined('ABSPATH')) {
	exit;
}

class AIPS_Operations_Insights_Controller {
	private $telemetry_repository;
	private $history_repository;

	public function __construct() {
		$container = AIPS_Container::get_instance();
		$this->telemetry_repository = $container->make(AIPS_Telemetry_Repository::class);
		$this->history_repository   = $container->make(AIPS_History_Repository::class);

		add_action('admin_post_aips_operations_insights_export', array($this, 'handle_export'));
	}

	public function render_page() {
		if (!current_user_can('manage_options')) {
			wp_die(esc_html__('You do not have permission to access this page.', 'ai-post-scheduler'));
		}

		$days = isset($_GET['days']) ? max(1, min(90, absint($_GET['days']))) : 14;

		$history_trend = $this->history_repository->get_daily_success_failure_trend($days);
		$duration_by_flow = $this->history_repository->get_average_duration_by_flow($days);
		$failure_reasons = $this->history_repository->get_top_failure_reasons($days, 8);
		$retry_counts = AIPS_Telemetry::is_enabled() ? $this->history_repository->get_retry_counts_by_service($days) : array();
		$recommended_actions = $this->build_recommended_actions($failure_reasons, $retry_counts);
		$telemetry_enabled = AIPS_Telemetry::is_enabled();

		include AIPS_PLUGIN_DIR . 'templates/admin/operations-insights.php';
	}

	public function handle_export() {
		if (!current_user_can('manage_options')) {
			wp_die(esc_html__('You do not have permission to export this data.', 'ai-post-scheduler'));
		}
		check_admin_referer('aips_operations_insights_export');

		$format = isset($_GET['format']) ? sanitize_key(wp_unslash($_GET['format'])) : 'json';
		$days   = isset($_GET['days']) ? max(1, min(90, absint($_GET['days']))) : 14;

		$data = array(
			'generated_at'      => current_time('mysql'),
			'days'              => $days,
			'telemetry_enabled' => AIPS_Telemetry::is_enabled(),
			'history_trend'     => $this->history_repository->get_daily_success_failure_trend($days),
			'duration_by_flow'  => $this->history_repository->get_average_duration_by_flow($days),
			'retry_counts'      => AIPS_Telemetry::is_enabled() ? $this->history_repository->get_retry_counts_by_service($days) : array(),
			'failure_reasons'   => $this->history_repository->get_top_failure_reasons($days, 25),
		);

		if ($format === 'csv') {
			header('Content-Type: text/csv; charset=utf-8');
			header('Content-Disposition: attachment; filename="aips-operations-insights.csv"');
			$output = fopen('php://output', 'w');
			fputcsv($output, array('section', 'label', 'value_1', 'value_2'));
			foreach ($data['history_trend'] as $row) { fputcsv($output, array('history_trend', $row['metric_date'], $row['success_count'], $row['failure_count'])); }
			foreach ($data['duration_by_flow'] as $row) { fputcsv($output, array('duration_by_flow', $row['flow_type'], $row['avg_duration_seconds'], $row['sample_count'])); }
			foreach ($data['retry_counts'] as $row) { fputcsv($output, array('retry_counts', $row['service_key'], $row['retry_count'], '')); }
			foreach ($data['failure_reasons'] as $row) { fputcsv($output, array('failure_reasons', $row['reason'], $row['failure_count'], '')); }
			fclose($output);
			exit;
		}

		header('Content-Type: application/json; charset=utf-8');
		header('Content-Disposition: attachment; filename="aips-operations-insights.json"');
		echo wp_json_encode($data);
		exit;
	}

	private function build_recommended_actions($failure_reasons, $retry_counts) {
		$actions = array();
		$top_failure = isset($failure_reasons[0]) ? strtolower((string) $failure_reasons[0]['reason']) : '';
		if (strpos($top_failure, 'timeout') !== false || strpos($top_failure, 'rate limit') !== false) {
			$actions[] = __('High timeout/rate-limit failures detected: reduce batch size and increase interval between slices.', 'ai-post-scheduler');
		}
		foreach ($retry_counts as $retry) {
			if ((int) $retry['retry_count'] >= 5) {
				$actions[] = sprintf(__('Heavy retry volume on %s: review retry backoff settings and upstream reliability.', 'ai-post-scheduler'), $retry['service_key']);
			}
		}
		if (empty($actions) && !empty($failure_reasons)) {
			$actions[] = __('Failure volume is present but distributed. Enable review gate for sensitive flows and audit prompts/templates.', 'ai-post-scheduler');
		}
		if (empty($actions)) {
			$actions[] = __('No major risk patterns detected. Continue monitoring and export snapshots for support baselines.', 'ai-post-scheduler');
		}
		return array_unique($actions);
	}
}
