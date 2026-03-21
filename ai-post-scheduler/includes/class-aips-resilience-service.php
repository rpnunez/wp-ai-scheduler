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
     * @var AIPS_Logger Logger instance
     */
    private $logger;

    /**
     * @var AIPS_Config Configuration manager
     */
    private $config;

    /**
     * Default circuit breaker state.
     */
    private const DEFAULT_CIRCUIT_STATE = array(
        'failures' => 0,
        'last_failure_time' => 0,
        'state' => 'closed',
    );

    /**
     * @var array Per-service circuit breaker states keyed by service name.
     */
    private $circuit_breaker_states = array();

    /**
     * @var callable|null Optional callback to determine if an error is retryable.
     */
    private $retryable_checker;

    /**
     * Initialize the Resilience Service.
     *
     * @param AIPS_Logger|null $logger Logger instance.
     * @param AIPS_Config|null $config Config instance.
     */
    public function __construct($logger = null, $config = null) {
        $this->logger = $logger ?: new AIPS_Logger();
        $this->config = $config ?: AIPS_Config::get_instance();
        $this->load_circuit_breaker_state();
    }

    // ========================================
    // Retry Logic with Exponential Backoff
    // ========================================

    /**
     * Set a callback to determine if a WP_Error is retryable.
     *
     * The callback receives a WP_Error and should return true if the
     * error is transient and the operation should be retried.
     *
     * @param callable $checker Callback accepting a WP_Error, returning bool.
     */
    public function set_retryable_checker($checker) {
        $this->retryable_checker = $checker;
    }

    /**
     * Check if an error is retryable.
     *
     * Non-retryable errors (e.g. invalid API key, bad prompt) will not be retried
     * even when retry is enabled. If no retryable checker is set, all errors are
     * considered retryable for backward compatibility.
     *
     * @param WP_Error $error The error to check.
     * @return bool True if the error should be retried.
     */
    public function is_retryable($error) {
        // Non-retryable error codes that should never be retried
        $non_retryable_codes = array(
            'ai_unavailable',
            'chatbot_unavailable',
            'invalid_api_key',
            'invalid_prompt',
        );

        $code = $error->get_error_code();
        if (in_array($code, $non_retryable_codes, true)) {
            return false;
        }

        // Delegate to custom checker if set
        if (is_callable($this->retryable_checker)) {
            return call_user_func($this->retryable_checker, $error);
        }

        // Default: all other errors are retryable
        return true;
    }

    /**
     * Execute a function with retry logic.
     *
     * Implements exponential backoff with jitter for retry attempts.
     * Only retries errors that pass the retryable check.
     *
     * @param callable $function Function to execute.
     * @param string   $type     Request type for logging and per-service circuit breaker.
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
        $max_delay = isset($retry_config['max_delay']) ? (int) $retry_config['max_delay'] : 60;
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

            // Check if this error is retryable
            if (!$this->is_retryable($result)) {
                $this->logger->log("Non-retryable error, aborting retry", 'warning', array(
                    'type' => $type,
                    'error_code' => $result->get_error_code(),
                    'error' => $result->get_error_message(),
                    'attempt' => $attempt,
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
            $delay = $this->calculate_retry_delay($attempt, $initial_delay, $retry_config, $max_delay);

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
     * Automatically records circuit breaker success/failure based on the result
     * so that callers do not need to call record_success()/record_failure() manually.
     * Rate limiter quota is only consumed after the circuit breaker check passes.
     *
     * @param callable $function Function to execute.
     * @param string   $type     Request type for logging and per-service circuit breaker.
     * @param string   $prompt   Prompt for logging.
     * @param array    $options  Options for logging.
     * @return mixed Function result or WP_Error.
     */
    public function execute_safely($function, $type, $prompt, $options) {
        // Check circuit breaker (per-service)
        if (!$this->check_circuit_breaker($type)) {
            return new WP_Error('circuit_breaker_open', __('Circuit breaker is open. Too many recent failures.', 'ai-post-scheduler'));
        }

        // Check rate limiting (does not consume quota; record_rate_limit_usage does)
        if (!$this->check_rate_limit()) {
            return new WP_Error('rate_limit_exceeded', __('Rate limit exceeded. Please try again later.', 'ai-post-scheduler'));
        }

        // Record rate limit usage now that we are proceeding
        $this->record_rate_limit_usage();

        // Execute with retry logic
        $result = $this->execute_with_retry($function, $type, $prompt, $options);

        // Auto-record circuit breaker outcome
        if (is_wp_error($result)) {
            $this->record_failure($type);
        } else {
            $this->record_success($type);
        }

        return $result;
    }

    /**
     * Calculate retry delay with exponential backoff and jitter.
     *
     * @param int   $attempt       Current attempt number.
     * @param int   $initial_delay Initial delay in seconds.
     * @param array $config        Retry configuration.
     * @param int   $max_delay     Maximum delay cap in seconds.
     * @return int Delay in seconds.
     */
    private function calculate_retry_delay($attempt, $initial_delay, $config, $max_delay = 60) {
        // Exponential backoff: delay = initial_delay * (2 ^ (attempt - 1))
        $delay = $initial_delay * pow(2, $attempt - 1);

        // Cap at configured max delay
        $delay = min($delay, $max_delay);

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

    /**
     * Load circuit breaker states from transient.
     *
     * Supports both legacy single-state and new per-service state format.
     */
    private function load_circuit_breaker_state() {
        $states = get_transient('aips_circuit_breaker_states');
        if (is_array($states) && !empty($states)) {
            $this->circuit_breaker_states = $states;
            return;
        }

        // Migrate legacy single-state transient
        $legacy = get_transient('aips_circuit_breaker_state');
        if (is_array($legacy) && isset($legacy['state'])) {
            $this->circuit_breaker_states = array('default' => $legacy);
            delete_transient('aips_circuit_breaker_state');
            $this->save_circuit_breaker_states();
        }
    }

    /**
     * Save all circuit breaker states to a single transient.
     */
    private function save_circuit_breaker_states() {
        set_transient('aips_circuit_breaker_states', $this->circuit_breaker_states, HOUR_IN_SECONDS);
    }

    /**
     * Get the circuit breaker state for a specific service.
     *
     * @param string $service Service name (e.g. 'text', 'image', 'json').
     * @return array Circuit breaker state array.
     */
    private function get_service_state($service) {
        if (!isset($this->circuit_breaker_states[$service])) {
            $this->circuit_breaker_states[$service] = self::DEFAULT_CIRCUIT_STATE;
        }
        return $this->circuit_breaker_states[$service];
    }

    /**
     * Update the circuit breaker state for a specific service.
     *
     * @param string $service Service name.
     * @param array  $state   New state array.
     */
    private function set_service_state($service, $state) {
        $this->circuit_breaker_states[$service] = $state;
        $this->save_circuit_breaker_states();
    }

    /**
     * Check if circuit breaker allows requests.
     *
     * @param string $service Optional. Service name for per-service circuit breakers.
     * @return bool True if requests are allowed.
     */
    public function check_circuit_breaker($service = 'default') {
        $cb_config = $this->config->get_circuit_breaker_config();

        if (!$cb_config['enabled']) {
            return true;
        }

        $cb_state = $this->get_service_state($service);
        $state = $cb_state['state'];
        $last_failure = $cb_state['last_failure_time'];
        $timeout = $cb_config['timeout'];

        // Circuit is open (blocking requests)
        if ($state === 'open') {
            $time_since_failure = time() - $last_failure;

            // Check if timeout has passed
            if ($time_since_failure >= $timeout) {
                // Try half-open state
                $cb_state['state'] = 'half_open';
                $this->set_service_state($service, $cb_state);

                $this->logger->log('Circuit breaker entering half-open state', 'info', array(
                    'service' => $service,
                ));

                /**
                 * Fires when a circuit breaker enters the half-open state.
                 *
                 * @since 1.10.0
                 * @param string $service The service name.
                 */
                do_action('aips_circuit_breaker_half_open', $service);
                return true;
            }

            $this->logger->log('Circuit breaker is open, blocking request', 'warning', array(
                'service' => $service,
            ));
            return false;
        }

        // Circuit is closed or half-open, allow requests
        return true;
    }

    /**
     * Record a successful request for circuit breaker.
     *
     * @param string $service Optional. Service name for per-service circuit breakers.
     */
    public function record_success($service = 'default') {
        $cb_config = $this->config->get_circuit_breaker_config();

        if (!$cb_config['enabled']) {
            return;
        }

        $cb_state = $this->get_service_state($service);
        $state = $cb_state['state'];

        // If half-open, close the circuit
        if ($state === 'half_open') {
            $cb_state['state'] = 'closed';
            $cb_state['failures'] = 0;
            $this->set_service_state($service, $cb_state);

            $this->logger->log('Circuit breaker closed after successful request', 'info', array(
                'service' => $service,
            ));

            /**
             * Fires when a circuit breaker closes (recovery).
             *
             * @since 1.10.0
             * @param string $service The service name.
             */
            do_action('aips_circuit_breaker_closed', $service);
        } elseif ($state === 'closed') {
            // Reset failure count on success
            if ($cb_state['failures'] > 0) {
                $cb_state['failures'] = 0;
                $this->set_service_state($service, $cb_state);
            }
        }
    }

    /**
     * Record a failed request for circuit breaker.
     *
     * When in half-open state, a single failure immediately re-opens the circuit.
     *
     * @param string $service Optional. Service name for per-service circuit breakers.
     */
    public function record_failure($service = 'default') {
        $cb_config = $this->config->get_circuit_breaker_config();

        if (!$cb_config['enabled']) {
            return;
        }

        $cb_state = $this->get_service_state($service);
        $threshold = $cb_config['failure_threshold'];

        $cb_state['failures']++;
        $cb_state['last_failure_time'] = time();

        // Half-open: single failure immediately re-opens the circuit
        if ($cb_state['state'] === 'half_open') {
            $cb_state['state'] = 'open';
            $this->set_service_state($service, $cb_state);

            $this->logger->log('Circuit breaker re-opened after failure in half-open state', 'error', array(
                'service' => $service,
                'failures' => $cb_state['failures'],
            ));

            /**
             * Fires when a circuit breaker opens (trips).
             *
             * @since 1.10.0
             * @param string $service   The service name.
             * @param int    $failures  Total failure count.
             * @param int    $threshold Configured failure threshold.
             */
            do_action('aips_circuit_breaker_opened', $service, $cb_state['failures'], $threshold);
            return;
        }

        // Closed: open circuit if threshold exceeded
        if ($cb_state['failures'] >= $threshold) {
            $cb_state['state'] = 'open';
            $this->set_service_state($service, $cb_state);

            $this->logger->log('Circuit breaker opened after reaching failure threshold', 'error', array(
                'service' => $service,
                'failures' => $cb_state['failures'],
                'threshold' => $threshold,
            ));

            /** This action is documented above. */
            do_action('aips_circuit_breaker_opened', $service, $cb_state['failures'], $threshold);
            return;
        }

        $this->set_service_state($service, $cb_state);
    }

    /**
     * Reset circuit breaker manually.
     *
     * @param string $service Optional. Service name. Pass null to reset all services.
     * @return bool True on success.
     */
    public function reset_circuit_breaker($service = null) {
        if ($service === null) {
            $this->circuit_breaker_states = array();
            $this->save_circuit_breaker_states();
            $this->logger->log('All circuit breakers manually reset', 'info');
        } else {
            $this->set_service_state($service, self::DEFAULT_CIRCUIT_STATE);
            $this->logger->log('Circuit breaker manually reset', 'info', array(
                'service' => $service,
            ));
        }
        return true;
    }

    /**
     * Get circuit breaker status.
     *
     * @param string|null $service Optional. Service name. Null returns all states.
     * @return array Circuit breaker status.
     */
    public function get_circuit_breaker_status($service = null) {
        if ($service === null) {
            return $this->circuit_breaker_states;
        }
        return $this->get_service_state($service);
    }

    // ========================================
    // Rate Limiting
    // ========================================

    /**
     * Check if rate limit allows requests (read-only check).
     *
     * Does not consume quota. Call record_rate_limit_usage() after
     * confirming the request will proceed.
     *
     * @return bool True if requests are allowed.
     */
    public function check_rate_limit() {
        $rl_config = $this->config->get_rate_limit_config();

        if (!$rl_config['enabled']) {
            return true;
        }

        $max_requests = $rl_config['requests'];
        $period = $rl_config['period'];
        $current_time = time();

        // Load rate limiter state from transient
        $requests = get_transient('aips_rate_limiter_requests');
        if ($requests === false) {
            $requests = array();
        }

        // Count recent requests within the time window
        $recent_count = 0;
        foreach ($requests as $timestamp) {
            if (($current_time - $timestamp) < $period) {
                $recent_count++;
            }
        }

        // Check if limit exceeded
        if ($recent_count >= $max_requests) {
            $this->logger->log('Rate limit exceeded', 'warning', array(
                'requests' => $recent_count,
                'max' => $max_requests,
                'period' => $period,
            ));
            return false;
        }

        return true;
    }

    /**
     * Record a rate limit usage entry.
     *
     * Called by execute_safely() after check_rate_limit() passes,
     * so that failed pre-checks don't consume quota.
     */
    public function record_rate_limit_usage() {
        $rl_config = $this->config->get_rate_limit_config();

        if (!$rl_config['enabled']) {
            return;
        }

        $period = $rl_config['period'];
        $current_time = time();

        $requests = get_transient('aips_rate_limiter_requests');
        if ($requests === false) {
            $requests = array();
        }

        // Prune old entries
        $requests = array_values(array_filter($requests, function($timestamp) use ($current_time, $period) {
            return ($current_time - $timestamp) < $period;
        }));

        $requests[] = $current_time;
        set_transient('aips_rate_limiter_requests', $requests, $period);
    }

    /**
     * Get rate limiter status.
     *
     * @return array Rate limiter status.
     */
    public function get_rate_limiter_status() {
        $rl_config = $this->config->get_rate_limit_config();
        $requests = get_transient('aips_rate_limiter_requests');

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
     * @return bool True on success.
     */
    public function reset_rate_limiter() {
        delete_transient('aips_rate_limiter_requests');
        $this->logger->log('Rate limiter manually reset', 'info');
        return true;
    }
}
