<?php
/**
 * Component Retry Runner
 *
 * Encapsulates per-component inline retry loops, exponential jitter backoff,
 * multi-item prompt deficit recovery, and history telemetry during post
 * generation.
 *
 * @package AI_Post_Scheduler
 * @since 3.7.25
 */

if (!defined('ABSPATH')) {
	exit;
}

class AIPS_Component_Retry_Runner {

	/**
	 * Minimum jitter backoff delay in milliseconds.
	 */
	const MIN_BACKOFF_MS = 500;

	/**
	 * Maximum jitter backoff delay in milliseconds.
	 */
	const MAX_BACKOFF_MS = 1500;

	/**
	 * WP_Error codes that should abort retries immediately instead of sleeping.
	 *
	 * @var string[]
	 */
	private static $non_retryable_codes = array(
		'ai_unavailable',
		'ai_engine_missing',
		'ai_disabled',
		'rate_limit_exceeded',
		'generation_limit_exceeded',
		'circuit_breaker_open',
	);

	/**
	 * Optional sleeper callable for deterministic testing.
	 *
	 * @var callable|null
	 */
	private $sleeper;

	/**
	 * Constructor.
	 *
	 * @param callable|null $sleeper Optional callable accepting delay in milliseconds.
	 */
	public function __construct($sleeper = null) {
		$this->sleeper = is_callable($sleeper) ? $sleeper : null;
	}

	/**
	 * Resolve the configured inline retry budget for a component.
	 *
	 * @param string $component Component key (e.g. 'post_title', 'post_images').
	 * @return int Clamped retry count (0..5).
	 */
	public function get_retry_limit($component) {
		return AIPS_Config::get_instance()->get_component_retry_limit($component);
	}

	/**
	 * Determine whether a result represents a non-retryable WP_Error.
	 *
	 * @param mixed $result Attempt return value.
	 * @return bool
	 */
	public function is_non_retryable_error($result) {
		if (!is_wp_error($result)) {
			return false;
		}

		$code = (string) $result->get_error_code();
		if (in_array($code, self::$non_retryable_codes, true)) {
			return true;
		}

		$message = strtolower((string) $result->get_error_message());
		if (false !== strpos($message, 'ai engine') && (false !== strpos($message, 'not active') || false !== strpos($message, 'not installed') || false !== strpos($message, 'not available'))) {
			return true;
		}

		return false;
	}

	/**
	 * Compute an exponential jitter backoff delay in milliseconds (500..1500ms).
	 *
	 * @param string $component     Component key.
	 * @param int    $retry_attempt 1-based retry attempt number.
	 * @return int Delay in milliseconds (>= 0).
	 */
	public function compute_backoff_ms($component, $retry_attempt) {
		$attempt = max(1, (int) $retry_attempt);
		$base    = min(self::MAX_BACKOFF_MS, self::MIN_BACKOFF_MS * (int) pow(2, $attempt - 1));
		$low     = min(self::MIN_BACKOFF_MS, $base);
		$high    = min(self::MAX_BACKOFF_MS, max($low, $base));
		$jitter  = ($high > $low) ? wp_rand($low, $high) : $low;

		$delay_ms = (int) apply_filters('aips_component_retry_delay_ms', $jitter, $component, $attempt);

		return max(0, $delay_ms);
	}

	/**
	 * Pause execution for the given number of milliseconds.
	 *
	 * @param int $delay_ms Delay in milliseconds.
	 * @return void
	 */
	public function sleep_ms($delay_ms) {
		$delay_ms = (int) $delay_ms;
		if ($delay_ms <= 0) {
			return;
		}

		if ($this->sleeper !== null) {
			call_user_func($this->sleeper, $delay_ms);
			return;
		}

		usleep($delay_ms * 1000);
	}

	/**
	 * Execute a component generation callable with inline retries.
	 *
	 * @param string        $component  Component key (e.g. 'post_title', 'post_slug').
	 * @param callable      $attempt_fn Callable receiving int $attempt (0 = initial, 1..N = retries).
	 * @param callable      $is_valid_fn Callable receiving $result and returning bool.
	 * @param object|null   $history    Optional AIPS_History_Container for telemetry.
	 * @param object|null   $logger     Optional AIPS_Logger_Interface for warnings.
	 * @param callable|null $reason_fn  Optional callable receiving $result and returning reason string.
	 * @return array{result: mixed, valid: bool, attempts: int, retries_used: int}
	 */
	public function execute($component, callable $attempt_fn, callable $is_valid_fn, $history = null, $logger = null, $reason_fn = null) {
		$max_retries  = $this->get_retry_limit($component);
		$attempts     = 0;
		$retries_used = 0;

		$result = call_user_func($attempt_fn, 0);
		$attempts++;
		$valid = (bool) call_user_func($is_valid_fn, $result);

		while (!$valid && $retries_used < $max_retries) {
			if ($this->is_non_retryable_error($result)) {
				break;
			}

			$retries_used++;
			$reason = $this->resolve_reason($result, $reason_fn);

			$this->record_retry_telemetry($component, $retries_used, $max_retries, $reason, array(), $history, $logger);
			$this->sleep_ms($this->compute_backoff_ms($component, $retries_used));

			$result = call_user_func($attempt_fn, $retries_used);
			$attempts++;
			$valid = (bool) call_user_func($is_valid_fn, $result);
		}

		return array(
			'result'       => $result,
			'valid'        => $valid,
			'attempts'     => $attempts,
			'retries_used' => $retries_used,
		);
	}

	/**
	 * Execute iterative deficit recovery for multi-item components (e.g. post_images).
	 *
	 * Invokes $recover_fn(array $missing_indexes, int $retry_attempt) up to the
	 * component's retry budget, narrowing $missing_indexes after each pass until
	 * all items are recovered or the budget is exhausted.
	 *
	 * @param string      $component       Component key (e.g. 'post_images').
	 * @param int[]       $missing_indexes Missing 1-based placeholder indexes.
	 * @param callable    $recover_fn      Callable returning array<int, string> recovered prompts.
	 * @param object|null $history         Optional AIPS_History_Container.
	 * @param object|null $logger          Optional AIPS_Logger_Interface.
	 * @return array{recovered: array<int, string>, remaining: int[], retries_used: int}
	 */
	public function execute_deficit_recovery($component, array $missing_indexes, callable $recover_fn, $history = null, $logger = null) {
		$remaining    = array_values(array_unique(array_map('intval', $missing_indexes)));
		$recovered    = array();
		$max_retries  = $this->get_retry_limit($component);
		$retries_used = 0;

		if (empty($remaining) || $max_retries <= 0) {
			return array(
				'recovered'    => $recovered,
				'remaining'    => $remaining,
				'retries_used' => 0,
			);
		}

		while (!empty($remaining) && $retries_used < $max_retries) {
			$retries_used++;

			$this->record_retry_telemetry(
				$component,
				$retries_used,
				$max_retries,
				'prompt_deficit',
				array('missing_indexes' => $remaining),
				$history,
				$logger
			);

			if ($retries_used > 1) {
				$this->sleep_ms($this->compute_backoff_ms($component, $retries_used));
			}

			$pass_recovered = call_user_func($recover_fn, $remaining, $retries_used);
			if (is_array($pass_recovered)) {
				foreach ($pass_recovered as $idx => $prompt_str) {
					$idx_int = (int) $idx;
					if (in_array($idx_int, $remaining, true) && is_scalar($prompt_str) && '' !== trim((string) $prompt_str)) {
						$recovered[$idx_int] = trim((string) $prompt_str);
					}
				}
			}

			$still_missing = array();
			foreach ($remaining as $idx) {
				if (!isset($recovered[$idx])) {
					$still_missing[] = $idx;
				}
			}
			$remaining = $still_missing;
		}

		return array(
			'recovered'    => $recovered,
			'remaining'    => $remaining,
			'retries_used' => $retries_used,
		);
	}

	/**
	 * Determine a short machine-readable failure reason for telemetry.
	 *
	 * @param mixed         $result    Failed attempt result.
	 * @param callable|null $reason_fn Optional custom reason resolver.
	 * @return string
	 */
	private function resolve_reason($result, $reason_fn = null) {
		if (is_callable($reason_fn)) {
			$custom = (string) call_user_func($reason_fn, $result);
			if ('' !== $custom) {
				return $custom;
			}
		}

		if (is_wp_error($result)) {
			$code = (string) $result->get_error_code();
			return '' !== $code ? $code : 'wp_error';
		}

		return 'empty_or_invalid_output';
	}

	/**
	 * Record a retry attempt to history and logger.
	 *
	 * @param string      $component    Component key.
	 * @param int         $retry_attempt 1-based retry attempt number.
	 * @param int         $max_retries  Configured max retries.
	 * @param string      $reason       Machine-readable failure reason.
	 * @param array       $extra        Additional metadata.
	 * @param object|null $history      Optional AIPS_History_Container.
	 * @param object|null $logger       Optional AIPS_Logger_Interface.
	 * @return void
	 */
	private function record_retry_telemetry($component, $retry_attempt, $max_retries, $reason, array $extra = array(), $history = null, $logger = null) {
		$message = sprintf(
			'Retrying %s generation (attempt %d of %d, reason: %s).',
			$component,
			$retry_attempt,
			$max_retries,
			$reason
		);

		if ($logger && method_exists($logger, 'log')) {
			$logger->log($message, 'warning', array_merge(array(
				'component'     => $component,
				'retry_attempt' => $retry_attempt,
				'max_retries'   => $max_retries,
				'reason'        => $reason,
			), $extra));
		}

		if ($history && method_exists($history, 'record')) {
			$history->record(
				'ai_request',
				$message,
				array_merge(array(
					'retry_attempt' => $retry_attempt,
					'max_retries'   => $max_retries,
					'component'     => $component,
					'reason'        => $reason,
				), $extra),
				null,
				array(
					'component'     => $component,
					'retry_attempt' => $retry_attempt,
					'reason'        => $reason,
				)
			);
		}
	}
}
