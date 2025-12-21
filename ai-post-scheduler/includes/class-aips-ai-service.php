<?php
/**
 * AI Service Layer
 *
 * Abstracts AI Engine interactions and provides a clean interface for AI operations.
 * Separates AI communication logic from content generation orchestration.
 *
 * @package AI_Post_Scheduler
 * @since 1.4.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class AIPS_AI_Service
 *
 * Provides AI content generation capabilities through Meow Apps AI Engine integration.
 * Handles error recovery, logging, and provides a consistent interface for AI operations.
 */
class AIPS_AI_Service {
    
    /**
     * @var mixed AI Engine instance
     */
    private $ai_engine;
    
    /**
     * @var AIPS_Logger Logger instance
     */
    private $logger;
    
    /**
     * @var array Array to store AI call logs for debugging
     */
    private $call_log;
    
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
     * @var array Rate limiter state
     */
    private $rate_limiter_state = array(
        'requests' => array(),
    );
    
    /**
     * Initialize the AI Service.
     */
    public function __construct() {
        $this->logger = new AIPS_Logger();
        $this->call_log = array();
        $this->config = AIPS_Config::get_instance();
        $this->load_circuit_breaker_state();
    }
    
    /**
     * Get the AI Engine instance.
     *
     * Lazy-loads the AI Engine and caches it for reuse.
     *
     * @return mixed|null The AI Engine instance or null if not available.
     */
    private function get_ai_engine() {
        if ($this->ai_engine === null) {
            if (class_exists('Meow_MWAI_Core')) {
                global $mwai_core;
                $this->ai_engine = $mwai_core;
            }
        }
        return $this->ai_engine;
    }
    
    /**
     * Check if AI Engine is available and ready to use.
     *
     * @return bool True if AI Engine is available, false otherwise.
     */
    public function is_available() {
        return $this->get_ai_engine() !== null;
    }
    
    /**
     * Generate text content using AI.
     *
     * Sends a text prompt to the AI Engine and returns the generated content.
     * Includes retry logic, circuit breaker, and rate limiting.
     *
     * @param string $prompt  The prompt to send to the AI.
     * @param array  $options Optional. AI generation options (model, max_tokens, temperature).
     * @return string|WP_Error The generated content or WP_Error on failure.
     */
    public function generate_text($prompt, $options = array()) {
        $ai = $this->get_ai_engine();
        
        if (!$ai) {
            $error = new WP_Error('ai_unavailable', __('AI Engine plugin is not available.', 'ai-post-scheduler'));
            $this->log_call('text', $prompt, null, $options, $error->get_error_message());
            return $error;
        }
        
        // Check circuit breaker
        if (!$this->check_circuit_breaker()) {
            $error = new WP_Error('circuit_breaker_open', __('Circuit breaker is open. Too many recent failures.', 'ai-post-scheduler'));
            $this->log_call('text', $prompt, null, $options, $error->get_error_message());
            return $error;
        }
        
        // Check rate limiting
        if (!$this->check_rate_limit()) {
            $error = new WP_Error('rate_limit_exceeded', __('Rate limit exceeded. Please try again later.', 'ai-post-scheduler'));
            $this->log_call('text', $prompt, null, $options, $error->get_error_message());
            return $error;
        }
        
        $options = $this->prepare_options($options);
        
        // Try with retry logic
        return $this->execute_with_retry(function() use ($ai, $prompt, $options) {
            try {
                $query = new Meow_MWAI_Query_Text($prompt);
                
                // Set model if specified
                if (!empty($options['model'])) {
                    $query->set_model($options['model']);
                }
                
                // Set max tokens
                if (isset($options['max_tokens'])) {
                    $query->set_max_tokens($options['max_tokens']);
                }
                
                // Set temperature
                if (isset($options['temperature'])) {
                    $query->set_temperature($options['temperature']);
                }
                
                $response = $ai->run_query($query);
                
                if ($response && !empty($response->result)) {
                    $this->log_call('text', $prompt, $response->result, $options);
                    $this->record_success();
                    return $response->result;
                }
                
                $error = new WP_Error('empty_response', __('AI Engine returned an empty response.', 'ai-post-scheduler'));
                $this->log_call('text', $prompt, null, $options, $error->get_error_message());
                $this->record_failure();
                return $error;
                
            } catch (Exception $e) {
                $error = new WP_Error('generation_failed', $e->getMessage());
                $this->log_call('text', $prompt, null, $options, $e->getMessage());
                $this->record_failure();
                return $error;
            }
        }, 'text', $prompt, $options);
    }
    
    /**
     * Generate an image using AI.
     *
     * Sends an image prompt to the AI Engine and returns the generated image URL.
     *
     * @param string $prompt  The image generation prompt.
     * @param array  $options Optional. AI generation options.
     * @return string|WP_Error The image URL or WP_Error on failure.
     */
    public function generate_image($prompt, $options = array()) {
        $ai = $this->get_ai_engine();
        
        if (!$ai) {
            $error = new WP_Error('ai_unavailable', __('AI Engine plugin is not available.', 'ai-post-scheduler'));
            $this->log_call('image', $prompt, null, $options, $error->get_error_message());
            return $error;
        }
        
        try {
            $query = new Meow_MWAI_Query_Image($prompt);
            $response = $ai->run_query($query);
            
            if (!$response || empty($response->result)) {
                $error = new WP_Error('empty_response', __('AI Engine returned an empty response for image generation.', 'ai-post-scheduler'));
                $this->log_call('image', $prompt, null, $options, $error->get_error_message());
                return $error;
            }
            
            $image_url = $response->result;
            
            // Handle array response (some AI engines return arrays)
            if (is_array($image_url) && !empty($image_url[0])) {
                $image_url = $image_url[0];
            }
            
            if (empty($image_url)) {
                $error = new WP_Error('no_image_url', __('No image URL in AI response.', 'ai-post-scheduler'));
                $this->log_call('image', $prompt, null, $options, $error->get_error_message());
                return $error;
            }
            
            $this->log_call('image', $prompt, $image_url, $options);
            return $image_url;
            
        } catch (Exception $e) {
            $error = new WP_Error('generation_failed', $e->getMessage());
            $this->log_call('image', $prompt, null, $options, $e->getMessage());
            return $error;
        }
    }
    
    /**
     * Prepare and normalize AI generation options.
     *
     * Merges user-provided options with defaults from plugin settings.
     *
     * @param array $options User-provided options.
     * @return array Normalized options array.
     */
    private function prepare_options($options) {
        $model = get_option('aips_ai_model', '');
        
        $default_options = array(
            'model' => $model,
            'max_tokens' => 2000,
            'temperature' => 0.7,
        );
        
        return wp_parse_args($options, $default_options);
    }
    
    /**
     * Log an AI call for debugging and auditing.
     *
     * Stores call information in memory and writes to the system logger.
     *
     * @param string      $type     The type of AI call ('text' or 'image').
     * @param string      $prompt   The prompt sent to AI.
     * @param string|null $response The AI response, if successful.
     * @param array       $options  The options used for the call.
     * @param string|null $error    Error message, if call failed.
     */
    private function log_call($type, $prompt, $response, $options, $error = null) {
        $call_data = array(
            'type' => $type,
            'timestamp' => current_time('mysql'),
            'request' => array(
                'prompt' => $prompt,
                'options' => $options,
            ),
            'response' => array(
                'success' => $error === null,
                'content' => $response,
                'error' => $error,
            ),
        );
        
        $this->call_log[] = $call_data;
        
        // Log to system logger
        $level = $error ? 'error' : 'info';
        $message = $error ? "AI {$type} generation failed: {$error}" : "AI {$type} generation successful";
        
        $this->logger->log($message, $level, array(
            'type' => $type,
            'prompt_length' => strlen($prompt),
            'response_length' => $response ? strlen($response) : 0,
        ));
    }
    
    /**
     * Get all AI call logs from this session.
     *
     * Useful for debugging and displaying generation history.
     *
     * @return array Array of call log entries.
     */
    public function get_call_log() {
        return $this->call_log;
    }
    
    /**
     * Clear the call log.
     *
     * Resets the in-memory call log. Useful when starting a new generation task.
     */
    public function clear_call_log() {
        $this->call_log = array();
    }
    
    /**
     * Get statistics about AI calls in this session.
     *
     * @return array Statistics including total calls, successes, failures.
     */
    public function get_call_statistics() {
        $total = count($this->call_log);
        $successes = 0;
        $failures = 0;
        $types = array();
        
        foreach ($this->call_log as $call) {
            if ($call['response']['success']) {
                $successes++;
            } else {
                $failures++;
            }
            
            $type = $call['type'];
            if (!isset($types[$type])) {
                $types[$type] = 0;
            }
            $types[$type]++;
        }
        
        return array(
            'total' => $total,
            'successes' => $successes,
            'failures' => $failures,
            'by_type' => $types,
        );
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
    private function execute_with_retry($function, $type, $prompt, $options) {
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
    private function check_circuit_breaker() {
        $cb_config = $this->config->get_circuit_breaker_config();
        
        if (!$cb_config['enabled']) {
            return true;
        }
        
        $state = $this->circuit_breaker_state['state'];
        $failures = $this->circuit_breaker_state['failures'];
        $last_failure = $this->circuit_breaker_state['last_failure_time'];
        $threshold = $cb_config['failure_threshold'];
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
    private function record_success() {
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
    private function record_failure() {
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
    private function check_rate_limit() {
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
