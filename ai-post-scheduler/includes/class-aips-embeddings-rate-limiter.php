<?php
/**
 * Embeddings Rate Limiter
 *
 * Tracks vector embedding generation usage using a sliding window stored in
 * persistent WordPress options (immune to cache flushes) and enforces daily,
 * weekly, and monthly quotas to prevent unexpected API cost spikes.
 *
 * @package AI_Post_Scheduler
 * @since 3.6.7
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Embeddings_Rate_Limiter
 */
class AIPS_Embeddings_Rate_Limiter {

	/**
	 * Option name for persisting timestamps history.
	 *
	 * @var string
	 */
	const OPTION_NAME = 'aips_embeddings_usage_history';

	/**
	 * @var AIPS_Config Config instance
	 */
	private $config;

	/**
	 * @var AIPS_Logger_Interface Logger instance
	 */
	private $logger;

	/**
	 * Initialize the rate limiter.
	 *
	 * @param AIPS_Config|null           $config Config instance.
	 * @param AIPS_Logger_Interface|null $logger Logger instance.
	 */
	public function __construct(?AIPS_Config $config = null, ?AIPS_Logger_Interface $logger = null) {
		$container = AIPS_Container::get_instance();
		$this->config = $config ?: ($container->has(AIPS_Config::class) ? $container->make(AIPS_Config::class) : AIPS_Config::get_instance());
		$this->logger = $logger ?: ($container->has(AIPS_Logger_Interface::class) ? $container->make(AIPS_Logger_Interface::class) : new AIPS_Logger());
	}

	/**
	 * Check if rate limiting is enabled.
	 *
	 * @return bool
	 */
	public function is_enabled(): bool {
		return (bool) $this->config->get_option('aips_embeddings_rate_limits_enabled', true);
	}

	/**
	 * Get the raw timestamp history array.
	 *
	 * @return int[]
	 */
	public function get_usage_history(): array {
		$history = get_option(self::OPTION_NAME, array());
		if (!is_array($history)) {
			return array();
		}
		return array_values(array_filter(array_map('intval', $history)));
	}

	/**
	 * Record embedding generation calls into the sliding window.
	 *
	 * @param int $count Number of embeddings generated.
	 * @return void
	 */
	public function record_usage(int $count = 1): void {
		if ($count <= 0) {
			return;
		}

		$now = time();
		$history = $this->get_usage_history();

		for ($i = 0; $i < $count; $i++) {
			$history[] = $now;
		}

		// Prune entries older than 30 days
		$cutoff_30d = $now - (30 * DAY_IN_SECONDS);
		$history = array_values(array_filter($history, function ($ts) use ($cutoff_30d) {
			return $ts >= $cutoff_30d;
		}));

		// Persist with autoload = false to prevent bloating the autoloaded options
		update_option(self::OPTION_NAME, $history, false);
	}

	/**
	 * Reset the usage history (e.g. for testing or administrative reset).
	 *
	 * @return void
	 */
	public function reset_history(): void {
		update_option(self::OPTION_NAME, array(), false);
	}

	/**
	 * Retrieve detailed usage statistics and quota status.
	 *
	 * @return array
	 */
	public function get_usage_stats(): array {
		$enabled = $this->is_enabled();
		$history = $this->get_usage_history();
		$now = time();

		$cutoff_24h = $now - DAY_IN_SECONDS;
		$cutoff_7d  = $now - (7 * DAY_IN_SECONDS);
		$cutoff_30d = $now - (30 * DAY_IN_SECONDS);

		$daily_count   = 0;
		$weekly_count  = 0;
		$monthly_count = 0;
		$oldest_in_24h = null;

		foreach ($history as $ts) {
			if ($ts >= $cutoff_24h) {
				$daily_count++;
				if ($oldest_in_24h === null || $ts < $oldest_in_24h) {
					$oldest_in_24h = $ts;
				}
			}
			if ($ts >= $cutoff_7d) {
				$weekly_count++;
			}
			if ($ts >= $cutoff_30d) {
				$monthly_count++;
			}
		}

		$daily_limit   = (int) $this->config->get_option('aips_embeddings_daily_limit', 50);
		$weekly_limit  = (int) $this->config->get_option('aips_embeddings_weekly_limit', 200);
		$monthly_limit = (int) $this->config->get_option('aips_embeddings_monthly_limit', 500);

		$daily_reset_in = 0;
		if ($oldest_in_24h !== null) {
			$daily_reset_in = max(0, ($oldest_in_24h + DAY_IN_SECONDS) - $now);
		}

		$is_rate_limited = false;
		$exceeded_limit  = null;

		if ($enabled) {
			if ($daily_limit > 0 && $daily_count >= $daily_limit) {
				$is_rate_limited = true;
				$exceeded_limit  = 'daily';
			} elseif ($weekly_limit > 0 && $weekly_count >= $weekly_limit) {
				$is_rate_limited = true;
				$exceeded_limit  = 'weekly';
			} elseif ($monthly_limit > 0 && $monthly_count >= $monthly_limit) {
				$is_rate_limited = true;
				$exceeded_limit  = 'monthly';
			}
		}

		return array(
			'enabled'         => $enabled,
			'daily_count'     => $daily_count,
			'daily_limit'     => $daily_limit,
			'weekly_count'    => $weekly_count,
			'weekly_limit'    => $weekly_limit,
			'monthly_count'   => $monthly_count,
			'monthly_limit'   => $monthly_limit,
			'daily_reset_in'  => $daily_reset_in,
			'is_rate_limited' => $is_rate_limited,
			'exceeded_limit'  => $exceeded_limit,
		);
	}

	/**
	 * Check if generating $count embeddings would breach any active limit.
	 *
	 * @param int $count Number of embeddings requested.
	 * @return true|WP_Error True if allowed, WP_Error if limit reached.
	 */
	public function check_limits(int $count = 1) {
		if (!$this->is_enabled() || $count <= 0) {
			return true;
		}

		$stats = $this->get_usage_stats();

		// Check daily quota
		if ($stats['daily_limit'] > 0 && ($stats['daily_count'] + $count) > $stats['daily_limit']) {
			$reset_str = $stats['daily_reset_in'] > 0 ? human_time_diff(time(), time() + $stats['daily_reset_in']) : __('soon', 'ai-post-scheduler');
			$error_message = sprintf(
				/* translators: 1: Daily limit count, 2: Reset timeframe */
				__('Vector Embeddings daily rate limit reached (%1$d/%1$d). Resets in approximately %2$s.', 'ai-post-scheduler'),
				$stats['daily_limit'],
				$reset_str
			);

			$this->emit_quota_alert('daily', $stats['daily_count'], $stats['daily_limit'], $error_message);

			return new WP_Error(
				'rate_limit_exceeded',
				$error_message,
				array(
					'period'    => 'daily',
					'current'   => $stats['daily_count'],
					'limit'     => $stats['daily_limit'],
					'reset_in'  => $stats['daily_reset_in'],
				)
			);
		}

		// Check weekly quota
		if ($stats['weekly_limit'] > 0 && ($stats['weekly_count'] + $count) > $stats['weekly_limit']) {
			$error_message = sprintf(
				/* translators: 1: Weekly limit count */
				__('Vector Embeddings weekly rate limit reached (%1$d/%1$d).', 'ai-post-scheduler'),
				$stats['weekly_limit']
			);

			$this->emit_quota_alert('weekly', $stats['weekly_count'], $stats['weekly_limit'], $error_message);

			return new WP_Error(
				'rate_limit_exceeded',
				$error_message,
				array(
					'period'  => 'weekly',
					'current' => $stats['weekly_count'],
					'limit'   => $stats['weekly_limit'],
				)
			);
		}

		// Check monthly quota
		if ($stats['monthly_limit'] > 0 && ($stats['monthly_count'] + $count) > $stats['monthly_limit']) {
			$error_message = sprintf(
				/* translators: 1: Monthly limit count */
				__('Vector Embeddings monthly rate limit reached (%1$d/%1$d).', 'ai-post-scheduler'),
				$stats['monthly_limit']
			);

			$this->emit_quota_alert('monthly', $stats['monthly_count'], $stats['monthly_limit'], $error_message);

			return new WP_Error(
				'rate_limit_exceeded',
				$error_message,
				array(
					'period'  => 'monthly',
					'current' => $stats['monthly_count'],
					'limit'   => $stats['monthly_limit'],
				)
			);
		}

		return true;
	}

	/**
	 * Emit quota alert action for notifications system.
	 *
	 * @param string $period        Period ('daily', 'weekly', 'monthly').
	 * @param int    $current_count Current count.
	 * @param int    $limit         Limit count.
	 * @param string $error_message Error message.
	 * @return void
	 */
	private function emit_quota_alert(string $period, int $current_count, int $limit, string $error_message): void {
		$this->logger->warning(
			sprintf('Embeddings rate limit reached [%s]: %d/%d', $period, $current_count, $limit),
			array('period' => $period, 'current' => $current_count, 'limit' => $limit)
		);

		/**
		 * Fires when an embeddings rate limit quota is exceeded.
		 *
		 * @since 3.6.7
		 * @param array $payload Notification payload.
		 */
		do_action('aips_quota_alert', array(
			'request_type'  => 'embedding',
			'error_code'    => 'rate_limit_exceeded',
			'error_message' => $error_message,
			'dedupe_key'    => 'quota_alert_embedding_' . sanitize_key($period),
			'dedupe_window' => 1800,
			'url'           => admin_url('admin.php?page=aips-settings#settings-ai'),
			'ai_model'      => (string) $this->config->get_option('aips_embeddings_model', 'text-embedding-3-small'),
		));
	}
}
