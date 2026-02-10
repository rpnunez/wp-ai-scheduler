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
     * Optional query option keys supported by AI Engine.
     */
    private const OPTIONAL_QUERY_OPTION_KEYS = array(
        'context',
        'instructions',
        'messages',
        'env_id',
        'embeddings_env_id',
        'max_results',
        'api_key',
    );
    
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
            global $mwai;
            $this->ai_engine = $mwai;
            //if (class_exists('Meow_MWAI_Core')) {
                //global $mwai_core;
                //$this->ai_engine = $mwai_core;
            //}
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
                // Build params array for simpleTextQuery
                $params = array();
                
                // Set model if specified
                if (!empty($options['model'])) {
                    $params['model'] = $options['model'];
                }
                
                // Set max tokens
                if (isset($options['max_tokens'])) {
                    $params['maxTokens'] = $options['max_tokens'];
                }
                
                // Set temperature
                if (isset($options['temperature'])) {
                    $params['temperature'] = $options['temperature'];
                }

                // Optional advanced parameters supported by AI Engine
                foreach (self::OPTIONAL_QUERY_OPTION_KEYS as $key) {
                    if (isset($options[$key])) {
                        $params[$key] = $options[$key];
                    }
                }
                
                // Use simpleTextQuery API method
                $result = $ai->simpleTextQuery($prompt, $params);
                
                if ($result && !empty($result)) {
                    $this->log_call('text', $prompt, $result, $options);
                    $this->resilience_service->record_success();
                    return $result;
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
     * Generate structured JSON data using AI.
     *
     * Uses AI Engine's simpleJsonQuery method for structured data generation.
     * This is particularly useful for generating lists, topics, or any structured data
     * that needs to be reliably parsed as JSON.
     *
     * @param string $prompt  The prompt to send to the AI.
     * @param array  $options Optional. AI generation options (model, max_tokens, temperature).
     * @return array|WP_Error The parsed JSON data as an array, or WP_Error on failure.
     */
    public function generate_json($prompt, $options = array()) {
        // Check if AI Engine is available using consistent availability check
        $ai = $this->get_ai_engine();
        
        if (!$ai) {
            $error = new WP_Error('ai_unavailable', __('AI Engine plugin is not available.', 'ai-post-scheduler'));
            $this->log_call('json', $prompt, null, $options, $error->get_error_message());
            return $error;
        }
        
        // Try to use global $mwai for simpleJsonQuery if available
        global $mwai;
        
        // If $mwai is not available or doesn't have simpleJsonQuery, fall back to text-based JSON
        if (!$mwai || !method_exists($mwai, 'simpleJsonQuery')) {
            return $this->fallback_json_generation($prompt, $options);
        }
        
        // Check circuit breaker
        if (!$this->resilience_service->check_circuit_breaker()) {
            $error = new WP_Error('circuit_breaker_open', __('Circuit breaker is open. Too many recent failures.', 'ai-post-scheduler'));
            $this->log_call('json', $prompt, null, $options, $error->get_error_message());
            return $error;
        }
        
        // Check rate limiting
        if (!$this->resilience_service->check_rate_limit()) {
            $error = new WP_Error('rate_limit_exceeded', __('Rate limit exceeded. Please try again later.', 'ai-post-scheduler'));
            $this->log_call('json', $prompt, null, $options, $error->get_error_message());
            return $error;
        }
        
        $options = $this->prepare_options($options);
        
        // Try with retry logic
        return $this->resilience_service->execute_with_retry(function() use ($mwai, $prompt, $options) {
            try {
                // Filter options for simpleJsonQuery - it only supports specific parameters
                // According to AI Engine docs, simpleJsonQuery has a very limited parameter set
                $json_query_params = array();
                
                // Only pass model if specified
                if (!empty($options['model'])) {
                    $json_query_params['model'] = $options['model'];
                }
                
                // Only pass temperature if specified
                if (isset($options['temperature'])) {
                    //$json_query_params['temperature'] = $options['temperature'];
                }
                
                // Convert max_tokens to maxTokens for AI Engine
                if (isset($options['max_tokens'])) {
                    //$json_query_params['maxTokens'] = $options['max_tokens'];
                }
                
                // Only pass env_id if specified  
                if (isset($options['env_id'])) {
                    $json_query_params['env_id'] = $options['env_id'];
                }
                
                // Log what we're sending to help debug
                $this->logger->log('Calling simpleJsonQuery with params: ' . wp_json_encode(array_keys($json_query_params)), 'debug');
                
                // Use simpleJsonQuery which returns structured JSON data
                // $result = $mwai->simpleJsonQuery($prompt, $json_query_params);
                $result = $mwai->simpleJsonQuery($prompt);

                error_log('Result type: ' . gettype($result));
                error_log('Result content: ' . var_export($result, true));
                
                if (empty($result)) {
                    $error = new WP_Error('empty_response', __('AI Engine returned an empty JSON response.', 'ai-post-scheduler'));
                    $this->log_call('json', $prompt, null, $options, $error->get_error_message());
                    $this->resilience_service->record_failure();
                    return $error;
                }
                
                // Validate that we got valid JSON data
                if (!is_array($result)) {
                    $error = new WP_Error('invalid_json', __('AI Engine did not return valid JSON data.', 'ai-post-scheduler'));
                    $this->log_call('json', $prompt, null, $options, $error->get_error_message());
                    $this->resilience_service->record_failure();
                    return $error;
                }
                
                $this->log_call('json', $prompt, wp_json_encode($result), $options);
                $this->resilience_service->record_success();
                return $result;
                
            } catch (Exception $e) {
                $error = new WP_Error('generation_failed', $e->getMessage());
                $this->log_call('json', $prompt, null, $options, $e->getMessage());
                $this->resilience_service->record_failure();
                return $error;
            }
        }, 'json', $prompt, $options);
    }
    
    /**
     * Fallback JSON generation using text query with JSON parsing.
     *
     * Used when simpleJsonQuery is not available. Generates text and parses as JSON.
     *
     * @param string $prompt  The prompt to send to the AI.
     * @param array  $options Optional. AI generation options.
     * @return array|WP_Error The parsed JSON data or WP_Error on failure.
     */
    private function fallback_json_generation($prompt, $options = array()) {
        $this->logger->log('Using fallback JSON generation (simpleJsonQuery not available)', 'info');
        
        // Log the JSON generation attempt in fallback mode for accurate statistics
        $start_time = microtime(true);
        
        // Generate text response
        $text_response = $this->generate_text($prompt, $options);
        
        if (is_wp_error($text_response)) {
            // Re-log as json type for accurate statistics
            $this->log_call('json', $prompt, null, $options, $text_response->get_error_message());
            return $text_response;
        }
        
        // Clean and parse JSON
        $json_str = trim($text_response);
        
        // 1. Try to extract from markdown code blocks first
        if (preg_match('/```(?:json)?\s*([\s\S]*?)\s*```/', $json_str, $matches)) {
            $json_str = trim($matches[1]);
        }
        // 2. If no code blocks, look for JSON object or array structure
        // This is a simple heuristic: find the first { or [ and the last } or ]
        elseif (preg_match('/(\{[\s\S]*\}|\[[\s\S]*\])/', $json_str, $matches)) {
            $json_str = trim($matches[1]);
        }
        
        // Decode JSON
        $data = json_decode($json_str, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            $error = new WP_Error('json_parse_error', sprintf(
                __('Failed to parse JSON: %s', 'ai-post-scheduler'),
                json_last_error_msg()
            ));
            $this->logger->log('JSON parse error: ' . json_last_error_msg(), 'error', array(
                'response_preview' => substr($json_str, 0, 200),
            ));
            // Log as json type with error
            $this->log_call('json', $prompt, null, $options, $error->get_error_message());
            return $error;
        }
        
        if (!is_array($data)) {
            $error = new WP_Error('invalid_json_format', __('Parsed JSON is not in expected array format.', 'ai-post-scheduler'));
            $this->log_call('json', $prompt, null, $options, $error->get_error_message());
            return $error;
        }
        
        // Log successful JSON generation in fallback mode
        $this->log_call('json', $prompt, wp_json_encode($data), $options);
        
        return $data;
    }
    
    /**
     * Generate an image using AI.
     *
     * Sends an image prompt to the AI Engine and returns the generated image URL.
     * Includes retry logic, circuit breaker, and rate limiting.
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

        // Check circuit breaker
        if (!$this->resilience_service->check_circuit_breaker()) {
            $error = new WP_Error('circuit_breaker_open', __('Circuit breaker is open. Too many recent failures.', 'ai-post-scheduler'));
            $this->log_call('image', $prompt, null, $options, $error->get_error_message());
            return $error;
        }
        
        // Check rate limiting
        if (!$this->resilience_service->check_rate_limit()) {
            $error = new WP_Error('rate_limit_exceeded', __('Rate limit exceeded. Please try again later.', 'ai-post-scheduler'));
            $this->log_call('image', $prompt, null, $options, $error->get_error_message());
            return $error;
        }

        return $this->resilience_service->execute_with_retry(function() use ($ai, $prompt, $options) {
            try {
                // Build params array for simpleImageQuery
                $params = array();
                
                // Pass through any options as params
                if (!empty($options)) {
                    $params = $options;
                }
                
                // Use simpleImageQuery API method
                $image_url = $ai->simpleImageQuery($prompt, $params);

                if (!$image_url || empty($image_url)) {
                    $error = new WP_Error('empty_response', __('AI Engine returned an empty response for image generation.', 'ai-post-scheduler'));
                    $this->log_call('image', $prompt, null, $options, $error->get_error_message());
                    $this->resilience_service->record_failure();
                    return $error;
                }

                // Handle array response (some AI engines return arrays)
                if (is_array($image_url) && !empty($image_url[0])) {
                    $image_url = $image_url[0];
                }

                if (empty($image_url)) {
                    $error = new WP_Error('no_image_url', __('No image URL in AI response.', 'ai-post-scheduler'));
                    $this->log_call('image', $prompt, null, $options, $error->get_error_message());
                    $this->resilience_service->record_failure();
                    return $error;
                }

                $this->log_call('image', $prompt, $image_url, $options);
                $this->resilience_service->record_success();
                return $image_url;

            } catch (Exception $e) {
                $error = new WP_Error('generation_failed', $e->getMessage());
                $this->log_call('image', $prompt, null, $options, $e->getMessage());
                $this->resilience_service->record_failure();
                return $error;
            }
        }, 'image', $prompt, $options);
    }
    
    /**
     * Generate text using chatbot for conversational context.
     *
     * Uses the AI Engine's chatbot feature to maintain conversational context
     * between multiple AI requests. This allows subsequent requests to reference
     * previous responses, creating more coherent and contextually aware content.
     *
     * @param string      $chatbot_id The chatbot ID/environment to use (e.g., 'default').
     * @param string      $message    The message/prompt to send to the chatbot.
     * @param array       $options    Optional. Chatbot options including chatId for continuing a conversation.
     * @param string|null $log_type   Optional type label for logging (defaults to 'chatbot').
     * @return array|WP_Error Array with 'reply' and 'chatId' keys on success, or WP_Error on failure.
     */
    public function generate_with_chatbot($chatbot_id, $message, $options = array(), $log_type = 'chatbot') {
        $ai = $this->get_ai_engine();
        
        if (!$ai) {
            $error = new WP_Error('ai_unavailable', __('AI Engine plugin is not available.', 'ai-post-scheduler'));
            $this->log_call($log_type, $message, null, $options, $error->get_error_message());
            return $error;
        }
        
        // Check if simpleChatbotQuery method exists with better diagnostics
        if (!method_exists($ai, 'simpleChatbotQuery')) {
            // Log detailed diagnostics
            $this->logger->log('Chatbot method unavailable', 'error', array(
                'ai_engine_class' => get_class($ai),
                'available_methods' => get_class_methods($ai),
                'chatbot_id' => $chatbot_id
            ));
            
            $error = new WP_Error('chatbot_unavailable', sprintf(__('%s', 'ai-post-scheduler'), 'AI Engine chatbot feature is not available.'));

            $this->log_call($log_type, $message, null, $options, $error->get_error_message());

            return $error;
        }
        
        // Check circuit breaker
        if (!$this->resilience_service->check_circuit_breaker()) {
            $error = new WP_Error('circuit_breaker_open', __('Circuit breaker is open. Too many recent failures.', 'ai-post-scheduler'));
            $this->log_call($log_type, $message, null, $options, $error->get_error_message());
            return $error;
        }
        
        // Check rate limiting
        if (!$this->resilience_service->check_rate_limit()) {
            $error = new WP_Error('rate_limit_exceeded', __('Rate limit exceeded. Please try again later.', 'ai-post-scheduler'));
            $this->log_call($log_type, $message, null, $options, $error->get_error_message());
            return $error;
        }
        
        // Try with retry logic
        return $this->resilience_service->execute_with_retry(function() use ($ai, $chatbot_id, $message, $options, $log_type) {
            try {
                // Extract supported options for the chatbot call
                // AI Engine's simpleChatbotQuery supports: chatId, context, instructions
                $chatbot_options = array();
                $supported_option_keys = array('chatId', 'context', 'instructions');
                
                foreach ($supported_option_keys as $key) {
                    if (isset($options[$key])) {
                        $chatbot_options[$key] = $options[$key];
                    }
                }
                
                // Log any unsupported options that were provided and ignored
                $unsupported_keys = array_diff(array_keys($options), $supported_option_keys);

                if (!empty($unsupported_keys)) {
                    $this->logger->log(
                        'Unsupported chatbot options were provided and ignored.',
                        'warning',
                        array(
                            'unsupported_keys' => $unsupported_keys,
                            'chatbot_id'       => $chatbot_id,
                        )
                    );
                }
                
                // Call the chatbot
                $response = $ai->simpleChatbotQuery($chatbot_id, $message, $chatbot_options);
                
                // Log the raw response for debugging
                $this->logger->log('Chatbot raw response received', 'debug', array(
                    'response_type' => gettype($response),
                    'is_string' => is_string($response),
                    'chatbot_id' => $chatbot_id,
                    'response_length' => is_string($response) ? strlen($response) : 0,
                    'response' => var_export($response, true)
                ));
                
                // Validate response structure
                if (!is_string($response)) {
                    $error = new WP_Error('invalid_chatbot_response', 
                        sprintf(__('AI Engine returned an unexpected response type: %s', 'ai-post-scheduler'), gettype($response))
                    );

                    $this->log_call($log_type, $message, null, $options, $error->get_error_message());

                    $this->resilience_service->record_failure();

                    return $error;
                }
                
                // Return the expected format with reply and chatId
                $result = array(
                    'reply' => $response,
                    'chatId' => $chatbot_options['chatId'] ?? null,
                );
                
                // Log successful chatbot interaction
                $this->log_call($log_type, $message, $result['reply'], array_merge($options, array('chatId' => $result['chatId'])));

                $this->logger->log('Chatbot interaction successful', 'info', array(
                    'chatbot_id' => $chatbot_id,
                    'response' => var_export($response, true)
                ));
                
                $this->resilience_service->record_success();
                
                return $result;
                
            } catch (Exception $e) {
                $error = new WP_Error('chatbot_failed', $e->getMessage());
                $this->log_call($log_type, $message, null, $options, $e->getMessage());
                $this->resilience_service->record_failure();
                return $error;
            }
        }, $log_type, $message, $options);
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
     * Apply optional AI Engine query settings when available.
     *
     * @param object $query   The AI Engine query object.
     * @param array  $options Options passed to the AI request.
     * @return void
     */
    private function apply_optional_query_settings($query, $options) {
        foreach (self::OPTIONAL_QUERY_OPTION_KEYS as $key) {
            if (!isset($options[$key])) {
                continue;
            }

            switch ($key) {
                case 'context':
                    if (method_exists($query, 'set_context')) {
                        $query->set_context($options[$key]);
                    }
                    break;
                case 'instructions':
                    if (method_exists($query, 'set_instructions')) {
                        $query->set_instructions($options[$key]);
                    }
                    break;
                case 'messages':
                    if (method_exists($query, 'set_messages')) {
                        $query->set_messages($options[$key]);
                    }
                    break;
                case 'env_id':
                    if (method_exists($query, 'set_env_id')) {
                        $query->set_env_id($options[$key]);
                    }
                    break;
                case 'embeddings_env_id':
                    if (method_exists($query, 'set_embeddings_env_id')) {
                        $query->set_embeddings_env_id($options[$key]);
                    }
                    break;
                case 'max_results':
                    if (method_exists($query, 'set_max_results')) {
                        $query->set_max_results($options[$key]);
                    }
                    break;
                case 'api_key':
                    if (method_exists($query, 'set_api_key')) {
                        $query->set_api_key($options[$key]);
                    }
                    break;
            }
        }
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
