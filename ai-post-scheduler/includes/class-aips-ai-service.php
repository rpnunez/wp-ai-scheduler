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
 * Provides AI content generation capabilities through AI Engine integration.
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
        $this->resilience_service = $resilience_service ?: new AIPS_Resilience_Service($this->logger, $this->config);

        $this->call_log = array();
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
            $this->log_call('text', $prompt, $options, $error);
            $this->emit_integration_error_notification('text', $error, $options);
            return $error;
        }
        
        $params = $this->prepare_options($options, $prompt);
        
        // Execute safely with retry, circuit breaker, and rate limiting.
        // CB state (record_failure / record_success) is managed by execute_safely — do NOT
        // call those methods inside this closure.
        $result = $this->resilience_service->execute_safely(function() use ($ai, $prompt, $options, $params) {
            try {
                // Use simpleTextQuery API method
                $result = $ai->simpleTextQuery($prompt, $params);

                if ($result && !empty($result)) {
                    $this->log_call('text', $prompt, $options, null, $result);
                    return $result;
                }

                $error = new WP_Error('empty_response', __('AI Engine returned an empty response.', 'ai-post-scheduler'));
                $this->log_call('text', $prompt, $options, $error);
                return $error;

            } catch (Exception $e) {
                $provider_code = AIPS_Resilience_Service::extract_error_code_from_message($e->getMessage());
                $error = new WP_Error($provider_code ?: 'generation_failed', $e->getMessage());
                $this->log_call('text', $prompt, $options, $error);
                return $error;
            }
        }, 'text', $prompt, $options);

        // Log resilience failures (circuit breaker, rate limit)
        if (is_wp_error($result)) {
            $code = $result->get_error_code();
            
            if (in_array($code, array('circuit_breaker_open', 'rate_limit_exceeded'), true)) {
                $this->log_call('text', $prompt, $options, $result);
                $this->emit_quota_alert_notification('text', $result, $options);
            }
        }

        return $result;
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

            $this->log_call('json', $prompt, $options, $error);
            $this->emit_integration_error_notification('json', $error, $options);

            return $error;
        }
        
        // If $ai doesn't have simpleJsonQuery, fall back to text-based JSON
        if (!method_exists($ai, 'simpleJsonQuery')) {
            $this->logger->log('Using fallback JSON generation (simpleJsonQuery not available)', 'info');

            return $this->fallback_json_generation($prompt, $options);
        }
        
        $params = $this->prepare_options($options, $prompt);
        
        // Execute safely with retry, circuit breaker, and rate limiting.
        // CB state is managed by execute_safely — do NOT call record_failure / record_success
        // inside this closure.
        // The closure must NOT invoke fallback_json_generation() because that method calls
        // generate_text() → execute_safely(), creating a nested resilience context inside a
        // retry iteration.  Instead, return WP_Error('json_query_unavailable', …) as a
        // sentinel so the caller can invoke the fallback after execute_safely() returns.
        $result = $this->resilience_service->execute_safely(function() use ($ai, $prompt, $options, $params) {
            try {
                // Filter options for simpleJsonQuery - it only supports specific parameters
                // According to AI Engine docs, simpleJsonQuery has a very limited parameter set
                $json_query_params = array();
                
                // Only pass model if specified
                if (!empty($params['model'])) {
                    $json_query_params['model'] = $params['model'];
                }
                
                // Only pass temperature if specified
                // if (isset($options['temperature'])) {
                //    $json_query_params['temperature'] = $options['temperature'];
                // }
                
                // Convert maxTokens to maxTokens for AI Engine
                // if (isset($options['maxTokens'])) {
                //    $json_query_params['maxTokens'] = $options['maxTokens'];
                // }
                
                // Only pass env_id if specified. Support both 'env_id' and legacy 'envId' keys.
                if (isset($params['env_id'])) {
                    $json_query_params['env_id'] = $params['env_id'];
                } elseif (isset($params['envId'])) {
                    $json_query_params['env_id'] = $params['envId'];
                }
                
                // Log what we're sending to help debug
                $this->logger->log('Calling simpleJsonQuery with params: ' . wp_json_encode(array_keys($json_query_params)), 'debug');
                
                // Use simpleJsonQuery which returns structured JSON data
                $result = $ai->simpleJsonQuery($prompt, $json_query_params);
                
                if (empty($result)) {
                    $error = new WP_Error('empty_response', __('AI Engine returned an empty JSON response.', 'ai-post-scheduler'));

                    $this->log_call('json', $prompt, $options, $error);

                    return $error;
                }
                
                // Validate that we got valid JSON data
                if (!is_array($result)) {
                    $error = new WP_Error('invalid_json', __('AI Engine did not return valid JSON data.', 'ai-post-scheduler'));

                    $this->log_call('json', $prompt, $options, $error);

                    return $error;
                }
                
                $this->log_call('json', $prompt, $options, null, wp_json_encode($result));

                return $result;
            } catch (Exception $e) {
                // Extract the provider error code from the message.
                // If a known provider code is found, propagate it so the retry loop can classify
                // it (permanent codes abort immediately; others are retried normally).
                // If the exception is not a recognisable provider error (e.g. the method itself
                // rejected the params), return the 'json_query_unavailable' sentinel so the
                // caller triggers the text-based fallback path outside this closure.
                $provider_code = AIPS_Resilience_Service::extract_error_code_from_message($e->getMessage());

                if ($provider_code !== '') {
                    $error = new WP_Error($provider_code, $e->getMessage());
                    $this->log_call('json', $prompt, $options, $error);
                    return $error;
                }

                $this->logger->log('simpleJsonQuery failed with non-provider error, will try fallback: ' . $e->getMessage(), 'warning');

                return new WP_Error('json_query_unavailable', $e->getMessage());
            }
        }, 'json', $prompt, $options);

        // If simpleJsonQuery failed with a non-provider error, fall back to text-based JSON
        // generation.  The fallback calls generate_text() which runs its own execute_safely()
        // pass — no nesting within the retry closure above.
        if (is_wp_error($result) && $result->get_error_code() === 'json_query_unavailable') {
            $this->logger->log('Falling back to text-based JSON generation after simpleJsonQuery failure', 'info');
            return $this->fallback_json_generation($prompt, $options);
        }

        // Log resilience failures (circuit breaker, rate limit)
        if (is_wp_error($result)) {
            $code = $result->get_error_code();

            if (in_array($code, array('circuit_breaker_open', 'rate_limit_exceeded'), true)) {
                $this->log_call('json', $prompt, $options, $result);
                $this->emit_quota_alert_notification('json', $result, $options);
            }
        }

        return $result;
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
        
        // Generate text response
        $text_response = $this->generate_text($prompt, $options);
        
        if (is_wp_error($text_response)) {
            // Re-log as json type for accurate statistics
            $this->log_call('json', $prompt, $options, $text_response);

            return $text_response;
        }
        
        // Clean and parse JSON
        $json_str = trim($text_response);
        
        // Remove potential markdown code blocks
        $json_str = preg_replace('/^```json\s*/m', '', $json_str);
        $json_str = preg_replace('/^```\s*/m', '', $json_str);
        $json_str = preg_replace('/```$/m', '', $json_str);
        $json_str = trim($json_str);
        
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
            $this->log_call('json', $prompt, $options, $error);

            return $error;
        }
        
        if (!is_array($data)) {
            $error = new WP_Error('invalid_json_format', __('Parsed JSON is not in expected array format.', 'ai-post-scheduler'));

            $this->log_call('json', $prompt, $options, $error);

            return $error;
        }
        
        // Log successful JSON generation in fallback mode
        $this->log_call('json', $prompt, $options, null, wp_json_encode($data));
        
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

            $this->log_call('image', $prompt, $options, $error);
            $this->emit_integration_error_notification('image', $error, $options);

            return $error;
        }

        // Execute safely with retry, circuit breaker, and rate limiting.
        // CB state is managed by execute_safely — do NOT call record_failure / record_success
        // inside this closure.
        $result = $this->resilience_service->execute_safely(function() use ($ai, $prompt, $options) {
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

                    $this->log_call('image', $prompt, $options, $error);

                    return $error;
                }

                // Handle array response (some AI engines return arrays)
                if (is_array($image_url) && !empty($image_url[0])) {
                    $image_url = $image_url[0];
                }

                if (empty($image_url)) {
                    $error = new WP_Error('no_image_url', __('No image URL in AI response.', 'ai-post-scheduler'));

                    $this->log_call('image', $prompt, $options, $error);

                    return $error;
                }

                $this->log_call('image', $prompt, $options, null, $image_url);

                return $image_url;
            } catch (Exception $e) {
                $provider_code = AIPS_Resilience_Service::extract_error_code_from_message($e->getMessage());
                $error = new WP_Error($provider_code ?: 'generation_failed', $e->getMessage());

                $this->log_call('image', $prompt, $options, $error);

                return $error;
            }
        }, 'image', $prompt, $options);

        // Log resilience failures (circuit breaker, rate limit)
        if (is_wp_error($result)) {
            $code = $result->get_error_code();
            
            if (in_array($code, array('circuit_breaker_open', 'rate_limit_exceeded'), true)) {
                $this->log_call('image', $prompt, $options, $result);
                $this->emit_quota_alert_notification('image', $result, $options);
            }
        }

        return $result;
    }
    
    /**
     * Calculate the appropriate maxTokens for an AI request.
     *
     * Determines the output token limit based on the type of request, applies a
     * safety buffer, and caps the result at the configured aips_max_tokens_limit
     * setting to prevent unexpectedly large or costly requests.
     *
     * @param string     $prompt The prompt that will be sent to the AI.
     * @param string|int $type   Request type: 'title', 'excerpt', 'content', or a
     *                           custom integer token count. Unknown string types fall
     *                           back to 'content' sizing.
     * @return int The calculated maxTokens value (always ≥ 1).
     */
    private function calculate_max_tokens($prompt, $type = 'content') {
        // If a custom integer token count is provided, use it as the base.
        if (is_int($type) && $type > 0) {
            $base_tokens = $type;
        } else {
            // Realistic expected output token counts per request type.
            // Approximation: 1 token ≈ 4 characters.
            switch ($type) {
                case 'title':
                    // Short titles: ~10-20 words, generous ceiling.
                    $base_tokens = 150;
                    break;
                case 'excerpt':
                    // 2-3 sentence summary: ~50-75 words.
                    $base_tokens = 300;
                    break;
                case 'content':
                default:
                    // Full article body: up to ~3000-4000 words.
                    $base_tokens = 4000;
                    break;
            }
        }

        // Apply a 25% safety buffer to avoid truncation near the boundary.
        $buffer     = (int) ceil($base_tokens * 0.25);
        $calculated = $base_tokens + $buffer;

        // Respect the hard maximum configured in settings.
        $limit = (int) AIPS_Config::get_instance()->get_option('aips_max_tokens_limit');
        if ($limit > 0 && $calculated > $limit) {
            $calculated = $limit;
        }

        return max(1, $calculated);
    }

    /**
     * Prepare and normalize AI generation options.
     *
     * Merges user-provided options with defaults from plugin settings.
     * When the caller has not explicitly set maxTokens, the value is calculated
     * dynamically via calculate_max_tokens() based on the prompt and request type.
     *
     * @param array  $options User-provided options.
     * @param string $prompt  The prompt that will be sent to the AI (used for dynamic token calculation).
     * @return array Normalized options array.
     */
    private function prepare_options($options, $prompt = '') {
        $config = AIPS_Config::get_instance();
        $model = $config->get_option('aips_ai_model');
        $env_id = $config->get_option('aips_ai_env_id');
        
        $default_options = array(
            'model' => $model,
            'envId' => $env_id,
            'temperature' => (float) $config->get_option('aips_temperature'),
        );

        if (isset($options['env_id'])) {
            $default_options['envId'] = $options['env_id'];
        } elseif (isset($options['envId'])) {
            $default_options['envId'] = $options['envId'];
        }

        $options = wp_parse_args($options, $default_options);
        $params  = array();

        if (!empty($options['model'])) {
            $params['model'] = $options['model'];
        }

        if (!empty($options['envId'])) {
            $params['envId'] = $options['envId'];
        }

        // Determine maxTokens: respect any explicit developer override; otherwise
        // calculate dynamically based on the prompt and request type.
        if (isset($options['maxTokens'])) {
            $params['maxTokens'] = $options['maxTokens'];
        } elseif (isset($options['max_tokens'])) {
            // Backward compatibility for legacy callers.
            $params['maxTokens'] = $options['max_tokens'];
        } else {
            $type               = isset($options['request_type']) ? $options['request_type'] : 'content';
            $params['maxTokens'] = $this->calculate_max_tokens($prompt, $type);
        }

        if (isset($options['temperature'])) {
            $params['temperature'] = $options['temperature'];
        }

        // Forward optional advanced options to maintain backwards compatibility
        // with callers that rely on passing these through to simpleTextQuery().
        if (defined('self::OPTIONAL_QUERY_OPTION_KEYS') || true) {
            foreach (self::OPTIONAL_QUERY_OPTION_KEYS as $key) {
                // env_id is already normalized to envId above, so we avoid
                // passing it through again here to prevent ambiguity.
                if ('env_id' === $key) {
                    continue;
                }

                if (isset($options[$key])) {
                    $params[$key] = $options[$key];
                }
            }
        }
        return $params;
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
     * Emit an integration error notification payload.
     *
     * @param string   $request_type Request type.
     * @param WP_Error $error        Error object.
     * @param array    $options      Request options.
     * @return void
     */
    private function emit_integration_error_notification($request_type, WP_Error $error, $options = array()) {
        do_action('aips_integration_error', array(
            'request_type'   => $request_type,
            'error_code'     => $error->get_error_code(),
            'error_message'  => $error->get_error_message(),
            'dedupe_key'     => 'integration_error_' . sanitize_key($request_type) . '_' . sanitize_key($error->get_error_code()),
            'dedupe_window'  => 1800,
            'url'            => admin_url('admin.php?page=aips-settings'),
            'ai_model'       => isset($options['model']) ? $options['model'] : AIPS_Config::get_instance()->get_option('aips_ai_model'),
        ));
    }

    /**
     * Emit a quota alert notification payload.
     *
     * @param string   $request_type Request type.
     * @param WP_Error $error        Error object.
     * @param array    $options      Request options.
     * @return void
     */
    private function emit_quota_alert_notification($request_type, WP_Error $error, $options = array()) {
        do_action('aips_quota_alert', array(
            'request_type'   => $request_type,
            'error_code'     => $error->get_error_code(),
            'error_message'  => $error->get_error_message(),
            'dedupe_key'     => 'quota_alert_' . sanitize_key($request_type) . '_' . sanitize_key($error->get_error_code()),
            'dedupe_window'  => 1800,
            'url'            => admin_url('admin.php?page=aips-settings'),
            'ai_model'       => isset($options['model']) ? $options['model'] : AIPS_Config::get_instance()->get_option('aips_ai_model'),
        ));
    }
    
    /**
     * Log an AI call for debugging and auditing.
     *
     * Stores call information in memory and writes to the system logger.
     *
     * @param string                         $type     The type of AI call ('text' or 'image').
     * @param string                         $prompt   The prompt sent to AI.
     * @param array                          $options  The options used for the call.
     * @param WP_Error|Exception|string|null $error    Error object or message, if call failed.
     * @param string|null                    $response The AI response, if successful.
     */
    private function log_call($type, $prompt, $options, $error = null, $response = null) {
        $prompt_for_length   = (string) $prompt;
        $response_for_length = (string) $response;

        // Normalize error to a string message if a WP_Error is provided.
        if ($error instanceof WP_Error) {
            $error_message = $error->get_error_message();
        } elseif ($error instanceof Exception) {
            $error_message = $error->getMessage();
        } else {
            $error_message = $error;
        }

        $call_data = array(
            'type' => $type,
            'timestamp' => current_time('mysql'),
            'request' => array(
                'prompt' => $prompt,
                'options' => $options,
            ),
            'response' => array(
                'success' => $error_message === null,
                'content' => $response,
                'error' => $error_message,
            ),
        );
        
        $this->call_log[] = $call_data;
        
        // Log to system logger
        $level   = $error_message ? 'error' : 'info';
        $message = $error_message ? "AI {$type} generation failed: {$error_message}" : "AI {$type} generation successful";
        
        $this->logger->log($message, $level, array(
            'type' => $type,
            'prompt_length' => strlen($prompt_for_length),
            'response_length' => strlen($response_for_length),
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
