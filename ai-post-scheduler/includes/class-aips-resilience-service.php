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
     * @param callable $function Function to execute.
     * @param string   $type     Request type for logging.
     * @param string   $prompt   Prompt for logging.
     * @param array    $options  Options for logging.
     * @return mixed Function result or WP_Error.
     */
    public function execute_safely($function, $type, $prompt, $options) {
        // Check circuit breaker
        if (!$this->check_circuit_breaker()) {
            return new WP_Error('circuit_breaker_open', __('Circuit breaker is open. Too many recent failures.', 'ai-post-scheduler'));
        }

        // Check rate limiting
        if (!$this->check_rate_limit()) {
            return new WP_Error('rate_limit_exceeded', __('Rate limit exceeded. Please try again later.', 'ai-post-scheduler'));
        }

        // Execute with retry logic
        return $this->execute_with_retry($function, $type, $prompt, $options);
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

    /**
     * Load circuit breaker state from transient.
     */
    private function load_circuit_breaker_state() {
        $state = get_transient('aips_circuit_breaker_state');
        if ($state !== false) {
            $this->circuit_breaker_state = $state;
        }
    }

    /**
     * Save circuit breaker state to transient.
     */
    private function save_circuit_breaker_state() {
        set_transient('aips_circuit_breaker_state', $this->circuit_breaker_state, HOUR_IN_SECONDS);
    }

    /**
     * Check if circuit breaker allows requests.
     *
     * @return bool True if requests are allowed.
     */
    public function check_circuit_breaker() {
        $cb_config = $this->config->get_circuit_breaker_config();

        if (!$cb_config['enabled']) {
            return true;
        }

        $state = $this->circuit_breaker_state['state'];
        $failures = $this->circuit_breaker_state['failures'];
        $last_failure = $this->circuit_breaker_state['last_failure_time'];
        $timeout = $cb_config['timeout'];

        // Circuit is open (blocking requests)
        if ($state === 'open') {
            $time_since_failure = time() - $last_failure;

            // Check if timeout has passed
            if ($time_since_failure >= $timeout) {
                // Try half-open state
                $this->circuit_breaker_state['state'] = 'half_open';
                $this->save_circuit_breaker_state();
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
     */
    public function record_success() {
        $cb_config = $this->config->get_circuit_breaker_config();

        if (!$cb_config['enabled']) {
            return;
        }

        $state = $this->circuit_breaker_state['state'];

        // If half-open, close the circuit
        if ($state === 'half_open') {
            $this->circuit_breaker_state['state'] = 'closed';
            $this->circuit_breaker_state['failures'] = 0;
            $this->save_circuit_breaker_state();
            $this->logger->log('Circuit breaker closed after successful request', 'info');
        } elseif ($state === 'closed') {
            // Reset failure count on success
            $this->circuit_breaker_state['failures'] = 0;
            $this->save_circuit_breaker_state();
        }
    }

    /**
     * Record a failed request for circuit breaker.
     */
    public function record_failure() {
        $cb_config = $this->config->get_circuit_breaker_config();

        if (!$cb_config['enabled']) {
            return;
        }

        $threshold = $cb_config['failure_threshold'];

        $this->circuit_breaker_state['failures']++;
        $this->circuit_breaker_state['last_failure_time'] = time();

        // Open circuit if threshold exceeded
        if ($this->circuit_breaker_state['failures'] >= $threshold) {
            $this->circuit_breaker_state['state'] = 'open';
            $this->logger->log('Circuit breaker opened after reaching failure threshold', 'error', array(
                'failures' => $this->circuit_breaker_state['failures'],
                'threshold' => $threshold,
            ));
        }

        $this->save_circuit_breaker_state();
    }

    /**
     * Reset circuit breaker manually.
     *
     * @return bool True on success.
     */
    public function reset_circuit_breaker() {
        $this->circuit_breaker_state = array(
            'failures' => 0,
            'last_failure_time' => 0,
            'state' => 'closed',
        );
        $this->save_circuit_breaker_state();
        $this->logger->log('Circuit breaker manually reset', 'info');
        return true;
    }

    /**
     * Get circuit breaker status.
     *
     * @return array Circuit breaker status.
     */
    public function get_circuit_breaker_status() {
        return $this->circuit_breaker_state;
    }

    // ========================================
    // Rate Limiting
    // ========================================

    /**
     * Check if rate limit allows requests.
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
            return false;
        }

        // Add current request
        $requests[] = $current_time;
        set_transient('aips_rate_limiter_requests', $requests, $period);

        return true;
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
