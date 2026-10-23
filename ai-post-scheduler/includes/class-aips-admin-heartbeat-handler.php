<?php
/**
 * Admin Heartbeat API Handler Class
 *
 * Unifies long-running background tasks, link indexing progress, and generation
 * queue monitoring onto the native WordPress Heartbeat API, eliminating wasteful
 * bespoke setInterval polling loops and optimizing server efficiency.
 *
 * @package AI_Post_Scheduler
 * @since   3.7.20
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Admin_Heartbeat_Handler
 */
class AIPS_Admin_Heartbeat_Handler {

	/**
	 * Initialize WordPress Heartbeat hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_filter('heartbeat_received', array(__CLASS__, 'handle_heartbeat'), 10, 2);
	}

	/**
	 * Process heartbeat requests from AIPS admin screens.
	 *
	 * @param array<string, mixed> $response Heartbeat response data.
	 * @param array<string, mixed> $data     Incoming heartbeat payload.
	 * @return array<string, mixed>
	 */
	public static function handle_heartbeat($response, $data) {
		if (!is_array($response)) {
			$response = array();
		}

		if (!is_array($data) || !current_user_can('manage_options')) {
			return $response;
		}

		// 1. Link Report backfill progress
		if (!empty($data['aips_link_report_backfill'])) {
			$response['aips_link_report_backfill'] = self::get_link_report_backfill_data();
		}

		// 2. Generation Queue & Active Tasks monitor
		if (!empty($data['aips_queue_monitor'])) {
			$response['aips_queue_monitor'] = self::get_queue_monitor_data();
		}

		// 3. Content Indexer Cooldown monitor
		if (!empty($data['aips_cooldown_monitor'])) {
			$response['aips_cooldown_monitor'] = self::get_cooldown_monitor_data();
		}

		return $response;
	}

	/**
	 * Retrieve Link Report backfill scanning progress.
	 *
	 * @return array<string, mixed>
	 */
	public static function get_link_report_backfill_data() {
		if (class_exists('AIPS_Link_Report_Service')) {
			try {
				$service = AIPS_Container::get_instance()->make(AIPS_Link_Report_Service::class);
				if (method_exists($service, 'ensure_scan_scheduled')) {
					$service->ensure_scan_scheduled();
				}
				return array(
					'backfill' => method_exists($service, 'get_backfill_status') ? $service->get_backfill_status() : null,
					'summary'  => method_exists($service, 'get_totals') ? $service->get_totals() : array(),
				);
			} catch (Exception $e) {
				return array(
					'error' => $e->getMessage(),
				);
			}
		}

		return array();
	}

	/**
	 * Retrieve generation queue and running background job telemetry.
	 *
	 * @return array<string, mixed>
	 */
	public static function get_queue_monitor_data() {
		$in_flight = 0;
		$queue_count = 0;

		if (class_exists('AIPS_Author_Topics_Repository')) {
			$topics_repo = new AIPS_Author_Topics_Repository();
			$counts = $topics_repo->get_global_status_counts();
			$queue_count = isset($counts['approved']) ? (int) $counts['approved'] : 0;
		}

		if (class_exists('AIPS_History_Repository')) {
			$history_repo = new AIPS_History_Repository();
			$in_flight = $history_repo->count_by_status('processing');
		}

		return array(
			'in_flight'    => $in_flight,
			'queue_length' => $queue_count,
			'timestamp'    => time(),
		);
	}

	/**
	 * Retrieve auto-cooldown countdown data for content indexer operations.
	 *
	 * @return array<string, mixed>
	 */
	public static function get_cooldown_monitor_data() {
		$cooldown_until = (int) get_option('aips_indexer_cooldown_until', 0);
		$now = time();

		return array(
			'is_active'         => $cooldown_until > $now,
			'remaining_seconds' => max(0, $cooldown_until - $now),
		);
	}
}
