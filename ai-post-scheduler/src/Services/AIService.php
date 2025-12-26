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
     * @var AIPS_Resilience_Service Resilience service
     */
    private $resilience_service;
    
    /**
     * Initialize the AI Service.
     */
    public function __construct($logger = null, $config = null, $resilience_service = null) {
        $this->logger = $logger ?: new AIPS_Logger();
        $this->config = $config ?: AIPS_Config::get_instance();
        $this->call_log = array();

        $this->resilience_service = $resilience_service ?: new AIPS_Resilience_Service($this->logger, $this->config);
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
        if (!$this->resilience_service->check_circuit_breaker()) {
            $error = new WP_Error('circuit_breaker_open', __('Circuit breaker is open. Too many recent failures.', 'ai-post-scheduler'));
            $this->log_call('text', $prompt, null, $options, $error->get_error_message());
            return $error;
        }
        
        // Check rate limiting
        if (!$this->resilience_service->check_rate_limit()) {
            $error = new WP_Error('rate_limit_exceeded', __('Rate limit exceeded. Please try again later.', 'ai-post-scheduler'));
            $this->log_call('text', $prompt, null, $options, $error->get_error_message());
            return $error;
        }
        
        $options = $this->prepare_options($options);
        
        // Try with retry logic
        return $this->resilience_service->execute_with_retry(function() use ($ai, $prompt, $options) {
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
                    $this->resilience_service->record_success();
                    return $response->result;
                }
                
                $error = new WP_Error('empty_response', __('AI Engine returned an empty response.', 'ai-post-scheduler'));
                $this->log_call('text', $prompt, null, $options, $error->get_error_message());
                $this->resilience_service->record_failure();
                return $error;
                
            } catch (Exception $e) {
                $error = new WP_Error('generation_failed', $e->getMessage());
                $this->log_call('text', $prompt, null, $options, $e->getMessage());
                $this->resilience_service->record_failure();
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
    
    // Delegate methods to Resilience Service for backward compatibility and access
    
    /**
     * Reset circuit breaker manually.
     *
     * @return bool True on success.
     */
    public function reset_circuit_breaker() {
        return $this->resilience_service->reset_circuit_breaker();
    }
    
    /**
     * Get circuit breaker status.
     *
     * @return array Circuit breaker status.
     */
    public function get_circuit_breaker_status() {
        return $this->resilience_service->get_circuit_breaker_status();
    }
    
    /**
     * Get rate limiter status.
     *
     * @return array Rate limiter status.
     */
    public function get_rate_limiter_status() {
        return $this->resilience_service->get_rate_limiter_status();
    }
    
    /**
     * Reset rate limiter manually.
     *
     * @return bool True on success.
     */
    public function reset_rate_limiter() {
        return $this->resilience_service->reset_rate_limiter();
    }
}
