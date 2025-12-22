<?php
/**
 * AI Service Layer
 *
 * Abstracts AI Engine interactions and provides a clean interface for AI operations.
 * Separates AI communication logic from content generation orchestration.
 * Includes retry logic with exponential backoff and circuit breaker pattern.
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
 * Handles error recovery, logging, retry logic, and provides a consistent interface for AI operations.
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
     * @var int Maximum number of retry attempts
     */
    private $max_retries;
    
    /**
     * @var int Initial delay in seconds for exponential backoff
     */
    private $initial_delay;
    
    /**
     * @var int Maximum delay in seconds for exponential backoff
     */
    private $max_delay;
    
    /**
     * @var array Circuit breaker state
     */
    private $circuit_breaker;
    
    /**
     * @var array Rate limiting state
     */
    private $rate_limiter;
    
    /**
     * Initialize the AI Service.
     *
     * @param array $config Optional. Configuration options.
     */
    public function __construct($config = array()) {
        $this->logger = new AIPS_Logger();
        $this->call_log = array();
        
        // Retry configuration
        $this->max_retries = isset($config['max_retries']) 
            ? absint($config['max_retries']) 
            : absint(get_option('aips_max_retries', 3));
        $this->initial_delay = isset($config['initial_delay']) ? absint($config['initial_delay']) : 1;
        $this->max_delay = isset($config['max_delay']) ? absint($config['max_delay']) : 30;
        
        // Circuit breaker configuration
        $this->circuit_breaker = array(
            'failure_threshold' => isset($config['circuit_breaker_threshold']) ? absint($config['circuit_breaker_threshold']) : 5,
            'timeout' => isset($config['circuit_breaker_timeout']) ? absint($config['circuit_breaker_timeout']) : 60,
            'failures' => 0,
            'state' => 'closed', // closed, open, half-open
            'last_failure' => 0,
        );
        
        // Rate limiting configuration
        $this->rate_limiter = array(
            'requests_per_minute' => isset($config['requests_per_minute']) ? absint($config['requests_per_minute']) : 20,
            'requests' => array(),
        );
    }
    
    /**
     * Check if circuit breaker allows requests.
     *
     * Implements the circuit breaker pattern to prevent cascading failures.
     *
     * @return bool True if requests are allowed, false otherwise.
     */
    private function is_circuit_open() {
        // Check if circuit is open
        if ($this->circuit_breaker['state'] === 'open') {
            // Check if timeout has passed
            if (time() - $this->circuit_breaker['last_failure'] > $this->circuit_breaker['timeout']) {
                // Move to half-open state
                $this->circuit_breaker['state'] = 'half-open';
                $this->logger->log('Circuit breaker moved to half-open state', 'info');
                return false;
            }
            
            return true;
        }
        
        return false;
    }
    
    /**
     * Record a successful request for circuit breaker.
     */
    private function record_success() {
        if ($this->circuit_breaker['state'] === 'half-open') {
            // Reset circuit breaker on successful request in half-open state
            $this->circuit_breaker['state'] = 'closed';
            $this->circuit_breaker['failures'] = 0;
            $this->logger->log('Circuit breaker closed after successful request', 'info');
        } elseif ($this->circuit_breaker['state'] === 'closed') {
            // Reset failure count on success
            $this->circuit_breaker['failures'] = 0;
        }
    }
    
    /**
     * Record a failed request for circuit breaker.
     */
    private function record_failure() {
        $this->circuit_breaker['failures']++;
        $this->circuit_breaker['last_failure'] = time();
        
        if ($this->circuit_breaker['failures'] >= $this->circuit_breaker['failure_threshold']) {
            $this->circuit_breaker['state'] = 'open';
            $this->logger->log('Circuit breaker opened after ' . $this->circuit_breaker['failures'] . ' failures', 'warning');
        }
    }
    
    /**
     * Check rate limiting and enforce limits.
     *
     * @return bool True if request is allowed, false if rate limited.
     */
    private function check_rate_limit() {
        $now = time();
        $minute_ago = $now - 60;
        
        // Remove requests older than 1 minute
        $this->rate_limiter['requests'] = array_filter(
            $this->rate_limiter['requests'],
            function($timestamp) use ($minute_ago) {
                return $timestamp > $minute_ago;
            }
        );
        
        // Check if limit is exceeded
        if (count($this->rate_limiter['requests']) >= $this->rate_limiter['requests_per_minute']) {
            $this->logger->log('Rate limit exceeded: ' . count($this->rate_limiter['requests']) . ' requests in the last minute', 'warning');
            return false;
        }
        
        // Record this request
        $this->rate_limiter['requests'][] = $now;
        
        return true;
    }
    
    /**
     * Calculate delay for exponential backoff.
     *
     * @param int $attempt The current attempt number (0-indexed).
     * @return int Delay in seconds.
     */
    private function calculate_backoff_delay($attempt) {
        // Calculate exponential backoff: initial_delay * 2^attempt
        $delay = $this->initial_delay * pow(2, $attempt);
        
        // Add jitter (random variation) to prevent thundering herd
        $jitter = rand(0, 1000) / 1000; // 0 to 1 second
        $delay = $delay + $jitter;
        
        // Cap at maximum delay
        return min($delay, $this->max_delay);
    }
    
    /**
     * Execute a function with retry logic.
     *
     * Implements exponential backoff, circuit breaker, and rate limiting.
     *
     * @param callable $callback The function to execute.
     * @param array    $args     Arguments to pass to the function.
     * @return mixed The result of the callback or WP_Error on failure.
     */
    private function execute_with_retry($callback, $args = array()) {
        // Check circuit breaker
        if ($this->is_circuit_open()) {
            $error = new WP_Error(
                'circuit_breaker_open',
                __('Circuit breaker is open. Too many recent failures. Please try again later.', 'ai-post-scheduler')
            );
            $this->logger->log('Request blocked by circuit breaker', 'error');
            return $error;
        }
        
        // Check rate limiting
        if (!$this->check_rate_limit()) {
            $error = new WP_Error(
                'rate_limit_exceeded',
                __('Rate limit exceeded. Please wait before making more requests.', 'ai-post-scheduler')
            );
            return $error;
        }
        
        $last_error = null;
        
        // Attempt the request with retries
        for ($attempt = 0; $attempt <= $this->max_retries; $attempt++) {
            try {
                // Execute the callback
                $result = call_user_func_array($callback, $args);
                
                // Check if result is an error
                if (is_wp_error($result)) {
                    $last_error = $result;
                    
                    // If this is not the last attempt, wait and retry
                    if ($attempt < $this->max_retries) {
                        $delay = $this->calculate_backoff_delay($attempt);
                        $this->logger->log(
                            "Attempt " . ($attempt + 1) . " failed: " . $result->get_error_message() . ". Retrying in {$delay} seconds...",
                            'warning'
                        );
                        sleep($delay);
                        continue;
                    }
                    
                    // Last attempt failed, record failure
                    $this->record_failure();
                    return $result;
                }
                
                // Success!
                $this->record_success();
                
                if ($attempt > 0) {
                    $this->logger->log("Request succeeded after " . ($attempt + 1) . " attempts", 'info');
                }
                
                return $result;
                
            } catch (Exception $e) {
                $last_error = new WP_Error('exception', $e->getMessage());
                
                if ($attempt < $this->max_retries) {
                    $delay = $this->calculate_backoff_delay($attempt);
                    $this->logger->log(
                        "Attempt " . ($attempt + 1) . " threw exception: " . $e->getMessage() . ". Retrying in {$delay} seconds...",
                        'warning'
                    );
                    sleep($delay);
                    continue;
                }
            }
        }
        
        // All retries exhausted
        $this->record_failure();
        
        if ($last_error) {
            $this->logger->log('All retry attempts failed: ' . $last_error->get_error_message(), 'error');
            return $last_error;
        }
        
        return new WP_Error('unknown_error', __('Request failed after all retry attempts.', 'ai-post-scheduler'));
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
     * Uses retry logic with exponential backoff and circuit breaker pattern.
     *
     * @param string $prompt  The prompt to send to the AI.
     * @param array  $options Optional. AI generation options (model, max_tokens, temperature).
     * @return string|WP_Error The generated content or WP_Error on failure.
     */
    public function generate_text($prompt, $options = array()) {
        // Define the actual generation logic
        $generate = function() use ($prompt, $options) {
            $ai = $this->get_ai_engine();
            
            if (!$ai) {
                $error = new WP_Error('ai_unavailable', __('AI Engine plugin is not available.', 'ai-post-scheduler'));
                $this->log_call('text', $prompt, null, $options, $error->get_error_message());
                return $error;
            }
            
            $options = $this->prepare_options($options);
            
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
                    return $response->result;
                }
                
                $error = new WP_Error('empty_response', __('AI Engine returned an empty response.', 'ai-post-scheduler'));
                $this->log_call('text', $prompt, null, $options, $error->get_error_message());
                return $error;
                
            } catch (Exception $e) {
                $error = new WP_Error('generation_failed', $e->getMessage());
                $this->log_call('text', $prompt, null, $options, $e->getMessage());
                return $error;
            }
        };
        
        // Execute with retry logic
        return $this->execute_with_retry($generate);
    }
    
    /**
     * Generate an image using AI.
     *
     * Sends an image prompt to the AI Engine and returns the generated image URL.
     * Uses retry logic with exponential backoff and circuit breaker pattern.
     *
     * @param string $prompt  The image generation prompt.
     * @param array  $options Optional. AI generation options.
     * @return string|WP_Error The image URL or WP_Error on failure.
     */
    public function generate_image($prompt, $options = array()) {
        // Define the actual generation logic
        $generate = function() use ($prompt, $options) {
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
        };
        
        // Execute with retry logic
        return $this->execute_with_retry($generate);
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
    
    /**
     * Get circuit breaker status.
     *
     * @return array Circuit breaker state information.
     */
    public function get_circuit_breaker_status() {
        return array(
            'state' => $this->circuit_breaker['state'],
            'failures' => $this->circuit_breaker['failures'],
            'failure_threshold' => $this->circuit_breaker['failure_threshold'],
            'timeout' => $this->circuit_breaker['timeout'],
            'last_failure' => $this->circuit_breaker['last_failure'],
            'time_until_retry' => $this->circuit_breaker['state'] === 'open' 
                ? max(0, ($this->circuit_breaker['last_failure'] + $this->circuit_breaker['timeout']) - time())
                : 0,
        );
    }
    
    /**
     * Get rate limiter status.
     *
     * @return array Rate limiter state information.
     */
    public function get_rate_limiter_status() {
        $now = time();
        $minute_ago = $now - 60;
        
        // Count requests in last minute
        $recent_requests = array_filter(
            $this->rate_limiter['requests'],
            function($timestamp) use ($minute_ago) {
                return $timestamp > $minute_ago;
            }
        );
        
        return array(
            'requests_per_minute' => $this->rate_limiter['requests_per_minute'],
            'current_requests' => count($recent_requests),
            'remaining' => max(0, $this->rate_limiter['requests_per_minute'] - count($recent_requests)),
            'will_reset_at' => !empty($recent_requests) ? min($recent_requests) + 60 : $now,
        );
    }
    
    /**
     * Reset circuit breaker to closed state.
     *
     * Use with caution. This should only be called manually when recovering from issues.
     */
    public function reset_circuit_breaker() {
        $this->circuit_breaker['state'] = 'closed';
        $this->circuit_breaker['failures'] = 0;
        $this->circuit_breaker['last_failure'] = 0;
        $this->logger->log('Circuit breaker manually reset', 'info');
    }
    
    /**
     * Get retry configuration.
     *
     * @return array Retry configuration settings.
     */
    public function get_retry_config() {
        return array(
            'max_retries' => $this->max_retries,
            'initial_delay' => $this->initial_delay,
            'max_delay' => $this->max_delay,
        );
    }
}
