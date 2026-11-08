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

		// Background process Heartbeat (`aips_bg`) is owned by AIPS_Background_Process_Manager.

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

		// 4. Content Indexer progress (index counts, queue, quota meters, cooldown)
		if (!empty($data['aips_content_indexer'])) {
			$response['aips_content_indexer'] = self::get_content_indexer_data();
		}

		return $response;
	}

	/**
	 * Retrieve Link Report backfill scanning progress.
	 *
	 * @return array<string, mixed>
	 */
	public static function get_link_report_backfill_data() {
		try {
			$service = AIPS_Container::get_instance()->make(AIPS_Link_Report_Service::class);
			$service->ensure_scan_scheduled();

			return array(
				'backfill' => $service->get_backfill_status(),
				'summary'  => $service->get_totals(),
			);
		} catch (Exception $e) {
			return array(
				'error' => $e->getMessage(),
			);
		}
	}

	/**
	 * Retrieve generation queue and running background job telemetry.
	 *
	 * @return array<string, mixed>
	 */
	public static function get_queue_monitor_data() {
		$topics_repo = new AIPS_Author_Topics_Repository();
		$counts      = $topics_repo->get_global_status_counts();
		$queue_count = isset($counts['approved']) ? (int) $counts['approved'] : 0;

		$history_repo = new AIPS_History_Repository();
		$in_flight    = $history_repo->count_by_status('processing');

		$digest_queue = (int) AIPS_Notifications::instance()->get_digest_queue_count();

		return array(
			'in_flight'     => $in_flight,
			'queue_length'  => $queue_count,
			'digest_queue'  => $digest_queue,
			'timestamp'     => time(),
		);
	}

	/**
	 * Retrieve auto-cooldown countdown data for content indexer operations.
	 *
	 * @return array<string, mixed>
	 */
	public static function get_cooldown_monitor_data() {
		$cooldown = self::get_rate_limiter()->get_cooldown_status();

		return array(
			'is_active'         => (bool) $cooldown['is_paused'],
			'remaining_seconds' => (int) $cooldown['remaining_seconds'],
		);
	}

	/**
	 * Retrieve the Content Indexer progress the indexer screen renders.
	 *
	 * Only the fields the screen draws are returned: index counts, the
	 * background queue state, the embeddings quota meters and the cooldown.
	 *
	 * @return array<string, mixed>
	 */
	public static function get_content_indexer_data() {
		try {
			$container  = AIPS_Container::get_instance();
			$indexer    = $container->make(AIPS_Content_Indexer_Service::class);
			$config     = AIPS_Config::get_instance();
			$post_types = (array) $config->get_option('aips_indexer_post_types', array('post'));
			$status     = $indexer->get_indexing_status($post_types);
			$queue      = $indexer->get_queue_status();
			$cooldown   = self::get_rate_limiter()->get_cooldown_status();
			$limits     = isset($status['rate_limits']) && is_array($status['rate_limits']) ? $status['rate_limits'] : array();

			$meter_keys  = array('daily_count', 'daily_limit', 'weekly_count', 'weekly_limit', 'monthly_count', 'monthly_limit');
			$rate_limits = array();
			foreach ($meter_keys as $key) {
				$rate_limits[$key] = isset($limits[$key]) ? (int) $limits[$key] : 0;
			}

			return array(
				'status'      => array(
					'indexed'          => (int) $status['indexed'],
					'total_posts'      => (int) $status['total_posts'],
					'unindexed'        => (int) $status['unindexed'],
					'percent'          => (int) $status['percent'],
					'indexed_topics'   => (int) $status['indexed_topics'],
					'total_topics'     => (int) $status['total_topics'],
					'unindexed_topics' => (int) $status['unindexed_topics'],
					'topics_percent'   => (int) $status['topics_percent'],
				),
				'rate_limits' => $rate_limits,
				'queue'       => array(
					'pending_count' => (int) $queue['pending_count'],
					'is_running'    => (bool) $queue['is_running'],
					'paused'        => $indexer->is_queue_paused(),
				),
				'cooldown'    => array(
					'active'            => (bool) $cooldown['is_paused'],
					'until'             => (int) $cooldown['paused_until'],
					'remaining_seconds' => (int) $cooldown['remaining_seconds'],
					'reason'            => $cooldown['is_paused'] ? (string) $cooldown['reason'] : '',
				),
			);
		} catch (Exception $e) {
			return array(
				'error' => $e->getMessage(),
			);
		}
	}

	/**
	 * Embeddings rate limiter that owns the indexer cooldown state.
	 *
	 * @return AIPS_Embeddings_Rate_Limiter
	 */
	private static function get_rate_limiter() {
		return new AIPS_Embeddings_Rate_Limiter();
	}
}
