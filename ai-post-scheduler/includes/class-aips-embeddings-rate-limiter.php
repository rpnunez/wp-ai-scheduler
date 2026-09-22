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
	 * In-memory cache for usage stats within a single request lifecycle.
	 *
	 * @var array|null
	 */
	private $cached_stats = null;

	/**
	 * In-memory cache for raw timestamp history array.
	 *
	 * @var array|null
	 */
	private $cached_history = null;

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
	 * Uses an in-memory cache to avoid redundant get_option DB lookups
	 * during repetitive batch processing loops within the same request.
	 *
	 * @return int[]
	 */
	public function get_usage_history(): array {
		if ($this->cached_history !== null) {
			return $this->cached_history;
		}

		$history = get_option(self::OPTION_NAME, array());
		if (!is_array($history)) {
			$this->cached_history = array();
			return $this->cached_history;
		}

		$this->cached_history = array_values(array_filter(array_map('intval', $history)));
		return $this->cached_history;
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

		// Invalidate in-memory caches upon new usage recording
		$this->cached_history = $history;
		$this->cached_stats   = null;

		// Persist with autoload = false to prevent bloating the autoloaded options
		update_option(self::OPTION_NAME, $history, false);
	}

	/**
	 * Reset the usage history (e.g. for testing or administrative reset).
	 *
	 * @return void
	 */
	public function reset_history(): void {
		$this->cached_history = array();
		$this->cached_stats   = null;
		update_option(self::OPTION_NAME, array(), false);
	}

	/**
	 * Retrieve detailed usage statistics and quota status.
	 *
	 * Computes sliding-window usage metrics for 24-hour (daily), 7-day (weekly),
	 * and 30-day (monthly) rolling windows. Uses an in-memory cache to avoid
	 * recalculating repeatedly during high-frequency checks within a single request.
	 *
	 * @param bool $bypass_cache Whether to bypass the in-memory cache and re-read from storage.
	 * @return array Multi-dimensional array containing usage counts, limits, reset times, and cooldown state.
	 */
	public function get_usage_stats(bool $bypass_cache = false): array {
		if (!$bypass_cache && $this->cached_stats !== null) {
			return $this->cached_stats;
		}

		$enabled = $this->is_enabled();
		$history = $this->get_usage_history();
		$now = time();

		// Calculate sliding window cutoffs relative to current timestamp
		$cutoff_24h = $now - DAY_IN_SECONDS;
		$cutoff_7d  = $now - (7 * DAY_IN_SECONDS);
		$cutoff_30d = $now - (30 * DAY_IN_SECONDS);

		$daily_count   = 0;
		$weekly_count  = 0;
		$monthly_count = 0;
		$oldest_in_24h = null;

		// Iterate through historical call timestamps and bucket into sliding windows
		foreach ($history as $ts) {
			// Daily 24h rolling window
			if ($ts >= $cutoff_24h) {
				$daily_count++;
				if ($oldest_in_24h === null || $ts < $oldest_in_24h) {
					$oldest_in_24h = $ts;
				}
			}
			// Weekly 7d rolling window
			if ($ts >= $cutoff_7d) {
				$weekly_count++;
			}
			// Monthly 30d rolling window
			if ($ts >= $cutoff_30d) {
				$monthly_count++;
			}
		}

		// Retrieve configured administrative threshold limits (0 indicates unlimited)
		$daily_limit   = (int) $this->config->get_option('aips_embeddings_daily_limit', 50);
		$weekly_limit  = (int) $this->config->get_option('aips_embeddings_weekly_limit', 200);
		$monthly_limit = (int) $this->config->get_option('aips_embeddings_monthly_limit', 500);

		// Calculate approximate time in seconds until the oldest call in 24h expires out of the window
		$daily_reset_in = 0;
		if ($oldest_in_24h !== null) {
			$daily_reset_in = max(0, ($oldest_in_24h + DAY_IN_SECONDS) - $now);
		}

		// Determine if any quota limit is currently reached or breached
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

		// Fetch current auto-cooldown state (if active from consecutive provider errors)
		$cooldown = $this->get_cooldown_status();

		$this->cached_stats = array(
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
			'cooldown'        => $cooldown,
		);

		return $this->cached_stats;
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

		$cooldown = $this->get_cooldown_status();
		if ($cooldown['is_paused']) {
			return new WP_Error(
				'embeddings_cooldown_active',
				sprintf(
					/* translators: 1: reason, 2: remaining seconds */
					__('Embeddings generation is temporarily paused. %1$s (Resumes in %2$d seconds).', 'ai-post-scheduler'),
					$cooldown['reason'],
					$cooldown['remaining_seconds']
				),
				array(
					'paused_until'      => $cooldown['paused_until'],
					'remaining_seconds' => $cooldown['remaining_seconds'],
					'reason'            => $cooldown['reason'],
				)
			);
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

	/**
	 * Check if an error is a rate limit, quota exhaustion, or throttling error.
	 *
	 * Scans error codes and messages for provider-agnostic rate limit keywords
	 * such as Google Vertex AI 429 ("Resource exhausted") and OpenAI rate limits.
	 *
	 * @param mixed $error WP_Error instance or string message.
	 * @return bool True if error indicates rate limiting or quota exhaustion.
	 */
	public function is_rate_limit_or_exhaustion_error($error): bool {
		if (empty($error)) {
			return false;
		}

		$haystacks = array();

		if (is_wp_error($error)) {
			$haystacks[] = (string) $error->get_error_code();
			$haystacks[] = (string) $error->get_error_message();
			$data = $error->get_error_data();
			if (is_string($data)) {
				$haystacks[] = $data;
			} elseif (is_array($data)) {
				$haystacks[] = wp_json_encode($data);
			}
		} elseif (is_string($error)) {
			$haystacks[] = $error;
		}

		$patterns = array(
			'resource exhausted',
			'429',
			'rate limit',
			'rate_limit',
			'quota exceeded',
			'quota_exceeded',
			'too many requests',
			'insufficient_quota',
			'exceeded your current quota',
			'billing',
			'overloaded',
			'service unavailable',
		);

		foreach ($haystacks as $text) {
			$lower = strtolower($text);
			foreach ($patterns as $pattern) {
				if (strpos($lower, $pattern) !== false) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Get current indexing cooldown status.
	 *
	 * @return array{is_paused: bool, paused_until: int, remaining_seconds: int, reason: string, consecutive_fails: int}
	 */
	public function get_cooldown_status(): array {
		$paused_until = (int) get_option('aips_indexer_paused_until', 0);
		$now          = time();
		$is_paused    = ($paused_until > $now);

		return array(
			'is_paused'         => $is_paused,
			'paused_until'      => $paused_until,
			'remaining_seconds' => $is_paused ? max(0, $paused_until - $now) : 0,
			'reason'            => (string) get_option('aips_indexer_pause_reason', ''),
			'consecutive_fails' => (int) get_transient('aips_indexer_consecutive_errors'),
		);
	}

	/**
	 * Record an indexing failure and engage auto-cooldown if threshold is reached.
	 *
	 * @param mixed $error Error object or string.
	 * @return array Cooldown status.
	 */
	public function record_failure($error): array {
		$is_exhaustion = $this->is_rate_limit_or_exhaustion_error($error);
		$fails = (int) get_transient('aips_indexer_consecutive_errors') + 1;
		set_transient('aips_indexer_consecutive_errors', $fails, 2 * HOUR_IN_SECONDS);

		$threshold = (int) $this->config->get_option('aips_indexer_consecutive_error_threshold', 2);

		if ($is_exhaustion || $fails >= $threshold) {
			$duration = max(1, (int) $this->config->get_option('aips_indexer_error_pause_duration', 30));
			$unit     = (string) $this->config->get_option('aips_indexer_error_pause_unit', 'minutes');

			$multiplier = MINUTE_IN_SECONDS;
			if ($unit === 'hours') {
				$multiplier = HOUR_IN_SECONDS;
			} elseif ($unit === 'days') {
				$multiplier = DAY_IN_SECONDS;
			}

			$pause_seconds = $duration * $multiplier;
			$paused_until  = time() + $pause_seconds;

			$error_msg = is_wp_error($error) ? $error->get_error_message() : (string) $error;
			$reason    = sprintf(
				/* translators: 1: error message, 2: duration number, 3: duration unit */
				__('Provider rate limit or quota exhaustion (%1$s). Paused for %2$d %3$s.', 'ai-post-scheduler'),
				wp_strip_all_tags($error_msg),
				$duration,
				$unit
			);

			update_option('aips_indexer_paused_until', $paused_until, false);
			update_option('aips_indexer_pause_reason', $reason, false);

			$this->logger->warning(
				sprintf('Embeddings auto-cooldown engaged until %s: %s', gmdate('Y-m-d H:i:s', $paused_until), $reason)
			);
		}

		return $this->get_cooldown_status();
	}

	/**
	 * Reset consecutive failure counter upon successful embedding generation.
	 *
	 * @return void
	 */
	public function record_success(): void {
		delete_transient('aips_indexer_consecutive_errors');
	}

	/**
	 * Check whether embedding generation is currently in an auto-cooldown period.
	 *
	 * Cooldown engages automatically when consecutive provider failures exceed
	 * the configured threshold or when an HTTP 429/quota exhaustion error occurs.
	 *
	 * @return bool True if cooldown is active (paused), false if clear.
	 */
	public function is_in_cooldown(): bool {
		$cooldown = $this->get_cooldown_status();
		return !empty($cooldown['is_paused']);
	}

	/**
	 * Execute a vector generation or API operation guarded by the rate limiter and cooldown policy.
	 *
	 * Implements the resilience callback pattern for embeddings:
	 * 1. Checks if rate limiting / cooldown is active; returns WP_Error immediately without executing.
	 * 2. Checks sliding-window daily/weekly/monthly quota allowances; returns WP_Error if exhausted.
	 * 3. Safely invokes the $operation callable.
	 * 4. On failure (WP_Error returned or Exception thrown):
	 *    - Automatically records the failure and engages auto-cooldown if threshold met.
	 *    - Fires the optional $on_failure callback.
	 * 5. On success:
	 *    - Records usage count against sliding-window persistent quotas.
	 *    - Resets consecutive error counters via record_success().
	 *    - Fires the optional $on_success callback.
	 *
	 * @param callable      $operation  The core operation to execute. Should return the result or WP_Error.
	 * @param callable|null $on_success Optional callback executed upon success: fn($result).
	 * @param callable|null $on_failure Optional callback executed upon failure: fn(WP_Error $error).
	 * @param int           $count      Number of quota units to consume (default 1).
	 * @return mixed The operation's return value on success, or WP_Error on rejection/failure.
	 */
	public function execute(callable $operation, ?callable $on_success = null, ?callable $on_failure = null, int $count = 1) {
		if ($count <= 0) {
			$count = 1;
		}

		// Step 1: Pre-execution cooldown guard
		if ($this->is_in_cooldown()) {
			$status = $this->get_cooldown_status();
			$error  = new WP_Error(
				'embeddings_cooldown_active',
				sprintf(
					/* translators: 1: reason, 2: remaining seconds */
					__('Embeddings generation is temporarily paused. %1$s (Resumes in %2$d seconds).', 'ai-post-scheduler'),
					$status['reason'],
					$status['remaining_seconds']
				),
				$status
			);
			if ($on_failure) {
				call_user_func($on_failure, $error);
			}
			return $error;
		}

		// Step 2: Pre-execution sliding-window quota check
		$limit_check = $this->check_limits($count);
		if (is_wp_error($limit_check)) {
			if ($on_failure) {
				call_user_func($on_failure, $limit_check);
			}
			return $limit_check;
		}

		// Step 3: Execute operation with exception safety
		try {
			$result = call_user_func($operation);
		} catch (\Throwable $e) {
			$result = new WP_Error(
				'embedding_execution_exception',
				$e->getMessage(),
				array('exception' => get_class($e))
			);
		}

		// Step 4: Handle failure
		if (is_wp_error($result)) {
			$this->record_failure($result);
			if ($on_failure) {
				call_user_func($on_failure, $result);
			}
			return $result;
		}

		// Step 5: Handle success
		$this->record_usage($count);
		$this->record_success();
		if ($on_success) {
			call_user_func($on_success, $result);
		}

		return $result;
	}

	/**
	 * Clear active cooldown lock and resume indexing immediately.
	 *
	 * @return void
	 */
	public function clear_cooldown(): void {
		delete_option('aips_indexer_paused_until');
		delete_option('aips_indexer_pause_reason');
		delete_transient('aips_indexer_consecutive_errors');
	}

	/**
	 * Check if current embeddings usage is approaching hard quota limits (>= 90%).
	 *
	 * @param float $threshold Ratio threshold (default: 0.90 for 90%).
	 * @return bool True if approaching daily or weekly limit.
	 */
	public function is_approaching_quota(float $threshold = 0.90): bool {
		if (!$this->is_enabled()) {
			return false;
		}

		$stats = $this->get_usage_stats();

		if ($stats['daily_limit'] > 0 && ($stats['daily_count'] / $stats['daily_limit']) >= $threshold) {
			return true;
		}

		if ($stats['weekly_limit'] > 0 && ($stats['weekly_count'] / $stats['weekly_limit']) >= $threshold) {
			return true;
		}

		return false;
	}
}
