<?php
/**
 * Resilience Service Layer
 *
 * Handles Retry Logic, Circuit Breaker, and Rate Limiting.
 *
 * @package AI_Post_Scheduler
 * @since 1.5.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class AIPS_Resilience_Service
 *
 * Encapsulates resilience patterns to improve system stability.
 */
class AIPS_Resilience_Service {

    /**
     * Error codes that represent permanent "user error" conditions (4xx equivalent).
     * Retrying these wastes tokens and time — the loop exits immediately on a match.
     *
     * These codes are returned by OpenAI/compatible providers and may appear either as
     * structured JSON fields ({"error":{"code":"..."}}) or as substrings within
     * human-readable exception messages forwarded by Meow AI Engine.
     *
     * @var string[]
     */
    const NON_RETRYABLE_CODES = array(
        'invalid_api_key',
        'context_length_exceeded',
        'model_not_found',
        'invalid_request_error',
        'content_policy_violation',
        'billing_not_active',
    );

    /**
     * Error codes that should immediately open the circuit breaker without waiting
     * for the failure threshold to be reached.
     *
     * @var string[]
     */
    const IMMEDIATE_OPEN_CODES = array(
        'insufficient_quota',
    );

    /**
     * Error codes that execute_safely() must NOT record as circuit-breaker failures.
     *
     * - circuit_breaker_open / rate_limit_exceeded: self-generated resilience errors;
     *   the CB is already in the correct state.
     * - json_query_unavailable: non-fault sentinel returned by the generate_json closure
     *   when simpleJsonQuery fails due to a method-level issue (not a provider error);
     *   the caller will invoke the text-based fallback instead.
     *
     * @var string[]
     */
    const NON_FAULT_CODES = array(
        'circuit_breaker_open',
        'rate_limit_exceeded',
        'json_query_unavailable',
    );

    /**
     * Message-based pattern map.
     *
     * Meow AI Engine forwards the raw provider error message as the PHP exception
     * message — there is no structured error code to inspect.  These patterns match
     * the English-language strings that OpenAI (and compatible providers) actually
     * send so that free-text messages can be mapped to canonical internal codes.
     *
     * Each entry: 'substring_pattern' => 'canonical_code'
     * Patterns are matched case-insensitively via stripos().
     *
     * Transient errors ("high demand", "rate limit exceeded", "overloaded") are
     * intentionally ABSENT — they should be retried normally.
     *
     * @var array<string, string>
     */
    const MESSAGE_PATTERNS = array(
        // ---- Permanent auth / configuration errors ----
        'incorrect api key'             => 'invalid_api_key',
        'invalid api key'               => 'invalid_api_key',
        'no api key provided'           => 'invalid_api_key',
        'api key not found'             => 'invalid_api_key',
        // ---- Quota / billing errors (immediate open) ----
        'exceeded your current quota'   => 'insufficient_quota',
        'you have exceeded your quota'  => 'insufficient_quota',
        'insufficient_quota'            => 'insufficient_quota',
        'quota exceeded'                => 'insufficient_quota',
        'billing_not_active'            => 'billing_not_active',
        'account is not active'         => 'billing_not_active',
        // ---- Context / token limit errors ----
        'maximum context length'        => 'context_length_exceeded',
        'context_length_exceeded'       => 'context_length_exceeded',
        'context window'                => 'context_length_exceeded',
        'token limit'                   => 'context_length_exceeded',
        "reduce the length of your messages" => 'context_length_exceeded',
        // ---- Model errors ----
        'model not found'               => 'model_not_found',
        'does not exist'                => 'model_not_found',
        'model_not_found'               => 'model_not_found',
        // ---- Content policy errors ----
        'content policy'                => 'content_policy_violation',
        'violates our usage policies'   => 'content_policy_violation',
        'content_policy_violation'      => 'content_policy_violation',
        // ---- Invalid request errors ----
        'invalid_request_error'         => 'invalid_request_error',
    );

    /**
     * @var AIPS_Logger_Interface Logger instance
     */
    private $logger;

    /**
     * @var AIPS_Config Configuration manager
     */
    private $config;

    /**
     * @var array Circuit breaker state
     */
    private $circuit_breaker_state = array(
        'failures' => 0,
        'last_failure_time' => 0,
        'state' => 'closed', // closed, open, half_open
    );

    /**
     * Initialize the Resilience Service.
     *
     * @param AIPS_Logger_Interface|null $logger Logger instance.
     * @param AIPS_Config|null $config Config instance.
     */
    public function __construct(?AIPS_Logger_Interface $logger = null, $config = null) {
        $container = AIPS_Container::get_instance();
        $this->logger = $logger ?: ($container->has(AIPS_Logger_Interface::class) ? $container->make(AIPS_Logger_Interface::class) : new AIPS_Logger());
        $this->config = $config ?: AIPS_Config::get_instance();
        $this->load_circuit_breaker_state();
    }

    // ========================================
    // Retry Logic with Exponential Backoff
    // ========================================

    /**
     * Execute a function with retry logic.
     *
     * Implements exponential backoff with jitter for retry attempts.
     *
     * @param callable $function Function to execute.
     * @param string   $type     Request type for logging.
     * @param string   $prompt   Prompt for logging.
     * @param array    $options  Options for logging.
     * @return mixed Function result or WP_Error.
     */
    public function execute_with_retry($function, $type, $prompt, $options) {
        $retry_config = $this->config->get_retry_config();

        if (!$retry_config['enabled']) {
            return $function();
        }

        $max_attempts = $retry_config['max_attempts'];
        $initial_delay = $retry_config['initial_delay'];
        $attempt = 0;
        $last_error = null;

        while ($attempt < $max_attempts) {
            $attempt++;

            $result = $function();

            // If successful (not a WP_Error), return immediately
            if (!is_wp_error($result)) {
                if ($attempt > 1) {
                    $this->logger->log("Retry successful on attempt {$attempt}", 'info', array(
                        'type' => $type,
                        'attempts' => $attempt,
                    ));
                }
                return $result;
            }

            $last_error = $result;
            $error_code = $result->get_error_code();

            // Do not retry permanent "user error" conditions or immediate-open codes — retrying wastes tokens.
            $non_retryable = array_merge(self::NON_RETRYABLE_CODES, self::IMMEDIATE_OPEN_CODES);
            if (in_array($error_code, $non_retryable, true)) {
                $this->logger->log("Non-retryable error '{$error_code}', aborting retry loop", 'error', array(
                    'type'       => $type,
                    'error_code' => $error_code,
                    'error'      => $result->get_error_message(),
                ));
                break;
            }

            // If we've reached max attempts, return the error
            if ($attempt >= $max_attempts) {
                $this->logger->log("Max retry attempts reached ({$max_attempts})", 'error', array(
                    'type' => $type,
                    'error' => $result->get_error_message(),
                ));
                break;
            }

            // Calculate delay with exponential backoff and jitter
            $delay = $this->calculate_retry_delay($attempt, $initial_delay, $retry_config);

            $this->logger->log("Retry attempt {$attempt} failed, waiting {$delay}s before retry", 'warning', array(
                'type' => $type,
                'error' => $result->get_error_message(),
            ));

            sleep($delay);
        }

        return $last_error;
    }

    /**
     * Execute a function with full resilience (Circuit Breaker, Rate Limiter, Retry).
     *
     * Circuit-breaker state is mutated exactly once per execute_safely() call,
     * regardless of how many retry attempts were made internally.  Callers must
     * NOT call record_failure() / record_success() inside the $function closure;
     * those side-effects are handled here after the retry loop completes.
     *
     * @param callable   $function Function to execute.  Must return a non-WP_Error value
     *                             on success, or a WP_Error (with a meaningful error code)
     *                             on failure.  Must NOT call record_failure/record_success.
     * @param string     $type     Request type for logging.
     * @param string     $prompt   Prompt for logging.
     * @param array      $options  Options for logging.
     * @param array|null $context  Optional context array for per-object scoping of circuit
     *                             breaker and rate limiter state. When null, global state is used.
     * @return mixed Function result or WP_Error.
     */
    public function execute_safely($function, $type, $prompt, $options, $context = null) {
        // Check circuit breaker
        if (!$this->check_circuit_breaker($context)) {
            return new WP_Error('circuit_breaker_open', __('Circuit breaker is open. Too many recent failures.', 'ai-post-scheduler'));
        }

        // Check rate limiting
        if (!$this->check_rate_limit($context)) {
            return new WP_Error('rate_limit_exceeded', __('Rate limit exceeded. Please try again later.', 'ai-post-scheduler'));
        }

        // Execute with retry logic
        $result = $this->execute_with_retry($function, $type, $prompt, $options);

        // Mutate circuit-breaker state exactly once per execute_safely() call.
        // Self-generated resilience errors (circuit already open / rate-limited) and
        // the 'json_query_unavailable' signal (a non-fault fallback sentinel) are
        // excluded so that the caller's fallback path can record its own outcome.
        if (is_wp_error($result)) {
            if (!in_array($result->get_error_code(), self::NON_FAULT_CODES, true)) {
                $this->record_failure($result->get_error_code(), $context);
            }
        } else {
            $this->record_success($context);
        }

        return $result;
    }

    /**
     * Calculate retry delay with exponential backoff and jitter.
     *
     * @param int   $attempt       Current attempt number.
     * @param int   $initial_delay Initial delay in seconds.
     * @param array $config        Retry configuration.
     * @return int Delay in seconds.
     */
    private function calculate_retry_delay($attempt, $initial_delay, $config) {
        // Exponential backoff: delay = initial_delay * (2 ^ (attempt - 1))
        $delay = $initial_delay * pow(2, $attempt - 1);

        // Cap at 60 seconds
        $delay = min($delay, 60);

        // Add jitter (random 0-25% of delay) to prevent thundering herd
        if ($config['jitter']) {
            $jitter = rand(0, (int)($delay * 0.25));
            $delay += $jitter;
        }

        return (int) $delay;
    }

    // ========================================
    // Circuit Breaker Pattern
    // ========================================

    // ========================================
    // Context Helpers
    // ========================================

    /**
     * Normalize a context array so keying is stable.
     *
     * - Ensures a default global context when none is provided.
     * - Removes null values.
     * - Sorts keys for deterministic encoding.
     *
     * @param array|null $context Context array.
     * @return array Normalized context array.
     */
    private function normalize_context($context) {
        if (empty($context) || !is_array($context)) {
            $context = array(
                'type' => 'global',
                'id'   => 'site',
            );
        }

        // Remove null values so they don't affect hashing.
        foreach ($context as $k => $v) {
            if ($v === null) {
                unset($context[$k]);
            }
        }

        // Deterministic ordering for stable hashing.
        ksort($context);

        return $context;
    }

    /**
     * Convert a context array to a stable key suffix.
     *
     * @param array|null $context Context array.
     * @return string Stable hash for transient keys.
     */
    private function context_to_hash($context) {
        $normalized = $this->normalize_context($context);

        // wp_json_encode provides consistent encoding in WP environments.
        $json = wp_json_encode($normalized);

        // sha1 is sufficient for keying; can be upgraded to sha256 if desired.
        return sha1((string) $json);
    }

    /**
     * Get the transient key for circuit breaker state for a given context.
     *
     * @param array|null $context Context array.
     * @return string Transient key.
     */
    private function get_circuit_breaker_transient_key($context) {
        return 'aips_circuit_breaker_state_' . $this->context_to_hash($context);
    }

    /**
     * Get the transient key for rate limiter requests for a given context.
     *
     * @param array|null $context Context array.
     * @return string Transient key.
     */
    private function get_rate_limiter_transient_key($context) {
        return 'aips_rate_limiter_requests_' . $this->context_to_hash($context);
    }

    // ========================================
    // Circuit Breaker Pattern
    // ========================================

    /**
     * Load circuit breaker state from transient.
     *
     * When no persisted state exists for the given context, the in-memory state
     * is reset to the default (closed, 0 failures) so that stale state from a
     * previous context operation is never returned.
     *
     * @param array|null $context Context array.
     */
    private function load_circuit_breaker_state($context = null) {
        $key   = $this->get_circuit_breaker_transient_key($context);
        $state = get_transient($key);
        if ($state !== false) {
            $this->circuit_breaker_state = $state;
        } else {
            // Reset to default so stale in-memory state from another context is not used.
            $this->circuit_breaker_state = array(
                'failures'          => 0,
                'last_failure_time' => 0,
                'state'             => 'closed',
            );
        }
    }

    /**
     * Save circuit breaker state to transient.
     *
     * @param array|null $context Context array.
     */
    private function save_circuit_breaker_state($context = null) {
        $key = $this->get_circuit_breaker_transient_key($context);
        set_transient($key, $this->circuit_breaker_state, HOUR_IN_SECONDS);
    }

    /**
     * Check if circuit breaker allows requests.
     *
     * @param array|null $context Optional context array for per-object scoping.
     * @return bool True if requests are allowed.
     */
    public function check_circuit_breaker($context = null) {
        $cb_config = $this->config->get_circuit_breaker_config();

        if (!$cb_config['enabled']) {
            return true;
        }

        // Load state for this specific context.
        $this->load_circuit_breaker_state($context);

        $state = $this->circuit_breaker_state['state'];
        $last_failure = $this->circuit_breaker_state['last_failure_time'];
        $timeout = $cb_config['timeout'];

        // Circuit is open (blocking requests)
        if ($state === 'open') {
            $time_since_failure = time() - $last_failure;

            // Check if timeout has passed
            if ($time_since_failure >= $timeout) {
                // Try half-open state
                $this->circuit_breaker_state['state'] = 'half_open';
                $this->save_circuit_breaker_state($context);
                $this->logger->log('Circuit breaker entering half-open state', 'info');
                return true;
            }

            $this->logger->log('Circuit breaker is open, blocking request', 'warning');
            return false;
        }

        // Circuit is closed or half-open, allow requests
        return true;
    }

    /**
     * Record a successful request for circuit breaker.
     *
     * @param array|null $context Optional context array for per-object scoping.
     */
    public function record_success($context = null) {
        $cb_config = $this->config->get_circuit_breaker_config();

        if (!$cb_config['enabled']) {
            return;
        }

        // Load state for this specific context before mutating.
        $this->load_circuit_breaker_state($context);

        $state = $this->circuit_breaker_state['state'];

        // If half-open, close the circuit
        if ($state === 'half_open') {
            $this->circuit_breaker_state['state'] = 'closed';
            $this->circuit_breaker_state['failures'] = 0;
            $this->save_circuit_breaker_state($context);
            $this->logger->log('Circuit breaker closed after successful request', 'info');
        } elseif ($state === 'closed') {
            // Reset failure count on success
            $this->circuit_breaker_state['failures'] = 0;
            $this->save_circuit_breaker_state($context);
        }
    }

    /**
     * Record a failed request for circuit breaker.
     *
     * @param string     $error_code Optional. Provider error code.  If this matches one of the
     *                               IMMEDIATE_OPEN_CODES (e.g. 'insufficient_quota'), the circuit
     *                               is opened right away without waiting for the failure threshold.
     * @param array|null $context    Optional context array for per-object scoping.
     */
    public function record_failure($error_code = '', $context = null) {
        $cb_config = $this->config->get_circuit_breaker_config();

        if (!$cb_config['enabled']) {
            return;
        }

        // Load state for this specific context before mutating.
        $this->load_circuit_breaker_state($context);

        $threshold = $cb_config['failure_threshold'];

        $this->circuit_breaker_state['failures']++;
        $this->circuit_breaker_state['last_failure_time'] = time();

        // Determine whether to open the circuit
        $immediate_open = !empty($error_code) && in_array($error_code, self::IMMEDIATE_OPEN_CODES, true);
        $threshold_reached = $this->circuit_breaker_state['failures'] >= $threshold;

        if ($immediate_open || $threshold_reached) {
            $was_already_open = $this->circuit_breaker_state['state'] === 'open';
            $this->circuit_breaker_state['state'] = 'open';

            $reason = $immediate_open
                ? sprintf('immediate open due to error code: %s', $error_code)
                : sprintf('failure threshold reached (%d/%d)', $this->circuit_breaker_state['failures'], $threshold);

            $this->logger->log('Circuit breaker opened — ' . $reason, 'error', array(
                'failures'   => $this->circuit_breaker_state['failures'],
                'threshold'  => $threshold,
                'error_code' => $error_code,
            ));

            // Fire notification action once per transition to open
            if (!$was_already_open) {
                do_action('aips_circuit_breaker_opened', array(
                    'failures'    => $this->circuit_breaker_state['failures'],
                    'threshold'   => $threshold,
                    'error_code'  => $error_code,
                    'reason_code' => $immediate_open ? 'immediate_open' : 'threshold_reached',
                    'dedupe_key'    => 'circuit_breaker_opened',
                    'dedupe_window' => 1800,
                ));
            }
        }

        $this->save_circuit_breaker_state($context);
    }

    /**
     * Reset circuit breaker manually.
     *
     * @param array|null $context Optional context array for per-object scoping.
     * @return bool True on success.
     */
    public function reset_circuit_breaker($context = null) {
        $this->circuit_breaker_state = array(
            'failures' => 0,
            'last_failure_time' => 0,
            'state' => 'closed',
        );
        $this->save_circuit_breaker_state($context);
        $this->logger->log('Circuit breaker manually reset', 'info');
        return true;
    }

    /**
     * Get circuit breaker status.
     *
     * @param array|null $context Optional context array for per-object scoping.
     * @return array Circuit breaker status.
     */
    public function get_circuit_breaker_status($context = null) {
        $this->load_circuit_breaker_state($context);
        return $this->circuit_breaker_state;
    }

    // ========================================
    // Error Code Helpers
    // ========================================

    /**
     * Attempt to extract a structured provider error code from an exception message.
     *
     * Meow AI Engine forwards the raw provider (OpenAI, etc.) error as the PHP
     * exception message — there is no guaranteed structured error code field.
     * This method uses a two-step approach:
     *
     * 1. Scan the raw message text for known human-readable patterns (MESSAGE_PATTERNS).
     *    This is the primary path because Meow AI Engine typically surfaces plain-text
     *    OpenAI/provider error strings.
     *
     * 2. If the message contains a JSON blob (OpenAI-style {"error":{"code":...}}),
     *    extract the code/type field and map it through MESSAGE_PATTERNS and the
     *    known code lists for a final lookup.
     *
     * Transient errors ("This model is currently experiencing high demand", empty
     * responses, rate-limit retries) intentionally return '' so the retry loop
     * continues normally.
     *
     * @param string $message Raw exception or error message.
     * @return string Canonical provider error code, or '' when none can be identified.
     */
    public static function extract_error_code_from_message($message) {
        // Step 1: Message pattern matching (primary — covers Meow AI Engine free-text errors)
        foreach (self::MESSAGE_PATTERNS as $pattern => $code) {
            if (stripos($message, $pattern) !== false) {
                return $code;
            }
        }

        // Step 2: JSON blob embedded in the message (OpenAI API response body style)
        $json_start = strpos($message, '{');
        if ($json_start !== false) {
            $json_str = substr($message, $json_start);
            $decoded  = json_decode($json_str, true);
            if (is_array($decoded)) {
                // e.g. {"error": {"code": "invalid_api_key", "type": "...", "message": "..."}}
                $raw_code = '';
                if (!empty($decoded['error']['code'])) {
                    $raw_code = (string) $decoded['error']['code'];
                } elseif (!empty($decoded['error']['type'])) {
                    $raw_code = (string) $decoded['error']['type'];
                }

                if ($raw_code !== '') {
                    // Check against known code lists first (exact match)
                    $all_codes = array_merge(self::NON_RETRYABLE_CODES, self::IMMEDIATE_OPEN_CODES);
                    if (in_array($raw_code, $all_codes, true)) {
                        return $raw_code;
                    }
                    // Fall through to pattern matching on the extracted code string itself
                    foreach (self::MESSAGE_PATTERNS as $pattern => $code) {
                        if (stripos($raw_code, $pattern) !== false) {
                            return $code;
                        }
                    }
                    // Return the raw code from JSON as-is (may be useful for logging)
                    return $raw_code;
                }
            }
        }

        return '';
    }

    // ========================================
    // Rate Limiting
    // ========================================

    /**
     * Check if rate limit allows requests.
     *
     * @param array|null $context Optional context array for per-object scoping.
     * @return bool True if requests are allowed.
     */
    public function check_rate_limit($context = null) {
        $rl_config = $this->config->get_rate_limit_config();

        if (!$rl_config['enabled']) {
            return true;
        }

        $max_requests = $rl_config['requests'];
        $period = $rl_config['period'];
        $current_time = time();
        $key = $this->get_rate_limiter_transient_key($context);

        // Load rate limiter state from transient
        $requests = get_transient($key);
        if ($requests === false) {
            $requests = array();
        }

        // Remove old requests outside the time window
        $requests = array_filter($requests, function($timestamp) use ($current_time, $period) {
            return ($current_time - $timestamp) < $period;
        });

        // Check if limit exceeded
        if (count($requests) >= $max_requests) {
            $this->logger->log('Rate limit exceeded', 'warning', array(
                'requests' => count($requests),
                'max' => $max_requests,
                'period' => $period,
            ));

            do_action('aips_rate_limit_reached', array(
                'current_requests' => count($requests),
                'max_requests'     => $max_requests,
                'period_seconds'   => $period,
                'dedupe_key'       => 'rate_limit_reached',
                'dedupe_window'    => 900,
            ));

            return false;
        }

        // Add current request
        $requests[] = $current_time;
        set_transient($key, $requests, $period);

        return true;
    }

    /**
     * Get rate limiter status.
     *
     * @param array|null $context Optional context array for per-object scoping.
     * @return array Rate limiter status.
     */
    public function get_rate_limiter_status($context = null) {
        $rl_config = $this->config->get_rate_limit_config();
        $key = $this->get_rate_limiter_transient_key($context);
        $requests = get_transient($key);

        if ($requests === false) {
            $requests = array();
        }

        $current_time = time();
        $period = $rl_config['period'];

        // Count recent requests
        $recent_requests = array_filter($requests, function($timestamp) use ($current_time, $period) {
            return ($current_time - $timestamp) < $period;
        });

        return array(
            'enabled' => $rl_config['enabled'],
            'current_requests' => count($recent_requests),
            'max_requests' => $rl_config['requests'],
            'period' => $period,
            'remaining' => max(0, $rl_config['requests'] - count($recent_requests)),
        );
    }

    /**
     * Reset rate limiter manually.
     *
     * @param array|null $context Optional context array for per-object scoping.
     * @return bool True on success.
     */
    public function reset_rate_limiter($context = null) {
        $key = $this->get_rate_limiter_transient_key($context);
        delete_transient($key);
        $this->logger->log('Rate limiter manually reset', 'info');
        return true;
    }
}
