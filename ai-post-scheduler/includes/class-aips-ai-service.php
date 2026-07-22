<?php
/**
 * AI Service Layer
 *
 * Abstracts AI provider interactions and provides a clean interface for AI
 * operations. Separates AI communication logic from content generation
 * orchestration; the raw transport is delegated to the active
 * AIPS_AI_Provider_Interface implementation.
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
 * Provides AI content generation capabilities through the active AI provider.
 * Handles error recovery, logging, and provides a consistent interface for AI operations.
 */
class AIPS_AI_Service implements AIPS_AI_Service_Interface {

    /**
     * @var AIPS_AI_Provider_Interface Active AI transport provider
     */
    private $provider;

    /**
     * @var AIPS_Logger_Interface Logger instance
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
     * Optional canonical query option keys forwarded to providers.
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
     *
     * @param AIPS_Logger_Interface|null       $logger             Logger.
     * @param mixed                            $config             Config manager.
     * @param mixed                            $resilience_service Resilience service.
     * @param AIPS_AI_Provider_Interface|null  $provider           AI transport provider.
     *                                                             Defaults to the
     *                                                             provider chosen by
     *                                                             AIPS_AI_Provider_Factory.
     */
    public function __construct(?AIPS_Logger_Interface $logger = null, $config = null, $resilience_service = null, ?AIPS_AI_Provider_Interface $provider = null) {
        if ($logger) {
            $this->logger = $logger;
        } else {
            $container = AIPS_Container::get_instance();
            if ($container->has(AIPS_Logger_Interface::class)) {
                $this->logger = $container->make(AIPS_Logger_Interface::class);
            } else {
                $this->logger = AIPS_Logger::instance();
            }
        }
        $this->config = $config ?: AIPS_Config::get_instance();
        $this->resilience_service = $resilience_service ?: new AIPS_Resilience_Service($this->logger, $this->config);
        $this->provider = $provider ?: AIPS_AI_Provider_Factory::create();

        $this->call_log = array();
    }

    /**
     * Get the active AI provider.
     *
     * @return AIPS_AI_Provider_Interface
     */
    private function get_ai_engine() {
        return $this->provider;
    }

    /**
     * Check if an AI provider is available and ready to use.
     *
     * @return bool True if a provider is available, false otherwise.
     */
    public function is_available() {
        return $this->provider->is_available();
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
        if (!$this->provider->is_available()) {
            $error = new WP_Error('ai_unavailable', __('The selected AI provider is not available.', 'ai-post-scheduler'));
            $this->log_call('text', $prompt, $options, $error);
            $this->emit_integration_error_notification('text', $error, $options);
            return $error;
        }

        $params = $this->prepare_options($options, $prompt);

        $log_context = array(
            'model' => isset($params['model']) ? $params['model'] : '',
            'max_tokens' => isset($params['maxTokens']) ? $params['maxTokens'] : ( isset($params['max_tokens']) ? $params['max_tokens'] : '' ),
            'temperature' => isset($params['temperature']) ? $params['temperature'] : '',
            'prompt_length' => is_string($prompt) ? strlen($prompt) : 0,
            'has_prompt' => !empty($prompt),
        );

        if (defined('AIPS_AI_DEBUG_LOG_PROMPTS') && AIPS_AI_DEBUG_LOG_PROMPTS) {
            $prompt_preview = is_string($prompt) ? substr($prompt, 0, 500) : '';

            if (is_string($prompt) && strlen($prompt) > 500) {
                $prompt_preview .= '... [truncated]';
            }

            $log_context['prompt_preview'] = $prompt_preview;
            $log_context['options_keys'] = array_keys($options);
            $log_context['params_keys'] = array_keys($params);
        }

        $this->logger->addSeparator('[AIPS_AI_Service->generate_text] New AI Text Generation Request');
        $this->logger->log(
            'Calling AI Engine for text generation: ' . wp_json_encode($log_context),
            'info'
        );
        
        // Execute safely with retry, circuit breaker, and rate limiting.
        // CB state (record_failure / record_success) is managed by execute_safely — do NOT
        // call those methods inside this closure.
        $result = $this->resilience_service->execute_safely(function() use ($prompt, $options, $params) {
            try {
                $result = $this->provider->generate_text($prompt, $params);

                $this->logger->log('Received response from provider text generation', 'debug', array(
                    'response' => $result,
                ));

                if ($result && !empty($result)) {
                    $this->log_call('text', $prompt, $options, null, $result);
                    return $result;
                }

                $error = new WP_Error('empty_response', __('AI Engine returned an empty response.', 'ai-post-scheduler'));
                $this->log_call('text', $prompt, $options, $error);
                return $error;

            } catch (Exception $e) {
                $provider_code = $this->provider->extract_error_code($e->getMessage());
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
        $available = $this->provider->is_available();

        $this->logger->log(
            sprintf(
                'Attempting to generate JSON with AI provider. AI available: %s',
                $available ? 'Yes' : 'No'
             ),
             'info'
        );

        // If no provider is available, log and emit notification, then return error.
        if (!$available) {
            $this->logger->log('AI provider is not available.', 'error');

            $error = new WP_Error('ai_unavailable', __('The selected AI provider is not available.', 'ai-post-scheduler'));

            $this->log_call('json', $prompt, $options, $error);
            $this->emit_integration_error_notification('json', $error, $options);

            return $error;
        }

        // If the provider has no native structured-JSON path, fall back to text-based JSON.
        if (!$this->provider->supports_native_json()) {
            $this->logger->log('Using fallback JSON generation (native JSON not supported)', 'info');

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
        $result = $this->resilience_service->execute_safely(function() use ($prompt, $options, $params) {
            try {
                // Use the provider's native structured-JSON path. It returns a
                // decoded array, or null to request the text-based fallback.
                $result = $this->provider->generate_json($prompt, $params);

                if ($result === null) {
                    $this->logger->log('Provider requested text-based JSON fallback', 'info');

                    return new WP_Error('json_query_unavailable', __('Native JSON generation unavailable.', 'ai-post-scheduler'));
                }

                $this->logger->log('AI provider JSON response: ' . print_r($result, true), 'debug');

                if (empty($result)) {
                    $error = new WP_Error('empty_response', __('AI Engine returned an empty JSON response.', 'ai-post-scheduler'));

                    $this->logger->log('AI provider returned an empty native JSON response.', 'error');

                    $this->log_call('json', $prompt, $options, $error);

                    return $error;
                }
                
                // Validate that we got valid JSON data
                if (!is_array($result)) {
                    $error = new WP_Error('invalid_json', __('AI Engine did not return valid JSON data.', 'ai-post-scheduler'));

                    $this->logger->log('AI provider returned invalid native JSON data.', 'error', array(
                        'response_preview' => substr(print_r($result, true), 0, 200),
                    ));

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
                $provider_code = $this->provider->extract_error_code($e->getMessage());

                if ($provider_code !== '') {
                    $error = new WP_Error($provider_code, $e->getMessage());
                    $this->log_call('json', $prompt, $options, $error);
                    return $error;
                }

                $this->logger->log('Native JSON generation failed with non-provider error, will try fallback: ' . $e->getMessage(), 'warning');

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

        return $this->generate_json_from_text($prompt, $options);
    }

    /**
     * Generate JSON via text completion with robust extraction.
     *
     * This path intentionally does not rely on simpleJsonQuery(). Retries,
     * rate limiting, and circuit-breaker behavior are delegated to
     * AIPS_Resilience_Service::execute_safely().
     *
     * @param string $prompt  Prompt instructing JSON output.
     * @param array  $options Optional generation options.
     * @return array|WP_Error
     */
    public function generate_json_from_text($prompt, $options = array()) {
        if (!$this->provider->is_available()) {
            $error = new WP_Error('ai_unavailable', __('The selected AI provider is not available.', 'ai-post-scheduler'));
            $this->log_call('json', $prompt, $options, $error);
            $this->emit_integration_error_notification('json', $error, $options);
            return $error;
        }

        $params = $this->prepare_options($options, $prompt);

        $log_context = array(
            'model'         => isset($params['model']) ? $params['model'] : '',
            'max_tokens'    => isset($params['maxTokens']) ? $params['maxTokens'] : (isset($params['max_tokens']) ? $params['max_tokens'] : ''),
            'temperature'   => isset($params['temperature']) ? $params['temperature'] : '',
            'prompt_length' => is_string($prompt) ? strlen($prompt) : 0,
            'has_prompt'    => !empty($prompt),
        );

        if (defined('AIPS_AI_DEBUG_LOG_PROMPTS') && AIPS_AI_DEBUG_LOG_PROMPTS) {
            $prompt_preview = is_string($prompt) ? substr($prompt, 0, 500) : '';

            if (is_string($prompt) && strlen($prompt) > 500) {
                $prompt_preview .= '... [truncated]';
            }

            $log_context['prompt_preview'] = $prompt_preview;
            $log_context['params_keys']    = array_keys($params);
        }

        $this->logger->log('Calling AI Engine for text-based JSON generation: ' . wp_json_encode($log_context), 'info');

        $result = $this->resilience_service->execute_safely(function() use ($prompt, $options, $params) {
            try {
                $text_response = $this->provider->generate_text($prompt, $params);

                if (!$text_response || empty($text_response)) {
                    $error = new WP_Error('empty_response', __('AI Engine returned an empty response.', 'ai-post-scheduler'));
                    $this->log_call('json', $prompt, $options, $error);
                    return $error;
                }

                $extract_result = $this->extract_json_fragment((string) $text_response);

                if (is_wp_error($extract_result)) {
                    $error = new WP_Error('json_parse_error', $extract_result->get_error_message());

                    $this->logger->log('JSON extraction failed for text-based JSON generation.', 'error', array(
                        'response_preview' => substr((string) $text_response, 0, 220),
                        'response_full' => (string) $text_response,
                    ));

                    $this->log_call('json', $prompt, $options, $error);

                    return $error;
                }

                $data = json_decode($extract_result, true);

                if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) {
                    $error = new WP_Error(
                        'json_parse_error',
                        sprintf(
                            __('Failed to parse JSON: %s', 'ai-post-scheduler'),
                            json_last_error_msg()
                        )
                    );

                    $this->logger->log('JSON decode failed for text-based JSON generation.', 'error', array(
                        'response_preview' => substr((string) $text_response, 0, 220),
                        'response_full' => (string) $text_response,
                    ));

                    $this->log_call('json', $prompt, $options, $error);

                    return $error;
                }

                $this->log_call('json', $prompt, $options, null, wp_json_encode($data));

                return $data;
            } catch (Exception $e) {
                $provider_code = $this->provider->extract_error_code($e->getMessage());
                $error = new WP_Error($provider_code ?: 'generation_failed', $e->getMessage());
                $this->log_call('json', $prompt, $options, $error);
                return $error;
            }
        }, 'json', $prompt, $options);

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
     * Extract the first balanced JSON object/array from text.
     *
     * @param string $text Raw AI text response.
     * @return string|WP_Error Balanced JSON fragment or WP_Error.
     */
    private function extract_json_fragment($text) {
        $text = trim((string) $text);

        // Remove common markdown wrappers.
        $text = preg_replace('/^```(?:json)?\s*/i', '', $text);
        $text = preg_replace('/```\s*$/', '', $text);
        $text = trim((string) $text);

        $start_pos_obj = strpos($text, '{');
        $start_pos_arr = strpos($text, '[');

        if ($start_pos_obj === false && $start_pos_arr === false) {
            return new WP_Error('json_extract_failed', __('No JSON start token found in AI response.', 'ai-post-scheduler'));
        }

        if ($start_pos_obj === false) {
            $start_pos = $start_pos_arr;
        } elseif ($start_pos_arr === false) {
            $start_pos = $start_pos_obj;
        } else {
            $start_pos = min($start_pos_obj, $start_pos_arr);
        }

        $slice = substr($text, $start_pos);

        $in_string = false;
        $escape    = false;
        $stack     = array();
        $length    = strlen($slice);

        for ($i = 0; $i < $length; $i++) {
            $ch = $slice[$i];

            if ($in_string) {
                if ($escape) {
                    $escape = false;
                } elseif ($ch === '\\') {
                    $escape = true;
                } elseif ($ch === '"') {
                    $in_string = false;
                }

                continue;
            }

            if ($ch === '"') {
                $in_string = true;
                continue;
            }

            if ($ch === '{' || $ch === '[') {
                $stack[] = $ch;
                continue;
            }

            if ($ch === '}' || $ch === ']') {
                if (empty($stack)) {
                    return new WP_Error('json_extract_failed', __('JSON appears malformed (unexpected closing token).', 'ai-post-scheduler'));
                }

                $open = array_pop($stack);
                if (($open === '{' && $ch !== '}') || ($open === '[' && $ch !== ']')) {
                    return new WP_Error('json_extract_failed', __('JSON appears malformed (mismatched tokens).', 'ai-post-scheduler'));
                }

                if (empty($stack)) {
                    $candidate = substr($slice, 0, $i + 1);
                    return $this->sanitize_json_candidate($candidate);
                }
            }
        }

        return new WP_Error('json_extract_failed', __('JSON appears truncated before closing token.', 'ai-post-scheduler'));
    }

    /**
     * Normalize control characters in a candidate JSON fragment.
     *
     * @param string $candidate Candidate JSON fragment.
     * @return string
     */
    private function sanitize_json_candidate($candidate) {
        return preg_replace_callback(
            '/"((?:[^"\\\\]|\\\\.)*)"/',
            function ($m) {
                $inner = $m[1];
                $inner = str_replace("\r", '\\r', $inner);
                $inner = str_replace("\n", '\\n', $inner);
                $inner = str_replace("\t", '\\t', $inner);
                $inner = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $inner);

                return '"' . $inner . '"';
            },
            (string) $candidate
        );
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
        if (!$this->provider->is_available()) {
            $error = new WP_Error('ai_unavailable', __('The selected AI provider is not available.', 'ai-post-scheduler'));

            $this->log_call('image', $prompt, $options, $error);
            $this->emit_integration_error_notification('image', $error, $options);

            return $error;
        }

        // Image options are passed through to the provider as-is (no token
        // budgeting), preserving historical behavior. Providers translate the
        // canonical keys they understand.
        $params = is_array($options) ? $options : array();

        // Execute safely with retry, circuit breaker, and rate limiting.
        // CB state is managed by execute_safely — do NOT call record_failure / record_success
        // inside this closure.
        $result = $this->resilience_service->execute_safely(function() use ($prompt, $options, $params) {
            try {
                $image_url = $this->provider->generate_image($prompt, $params);

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

                // Data URIs can be megabytes of base64 — never log them verbatim.
                $loggable_url = $image_url;

                if (is_string($loggable_url) && strpos($loggable_url, 'data:') === 0 && strlen($loggable_url) > 100) {
                    $loggable_url = substr($loggable_url, 0, 100) . '... [data URI truncated]';
                }

                $this->log_call('image', $prompt, $options, null, $loggable_url);

                return $image_url;
            } catch (Exception $e) {
                $provider_code = $this->provider->extract_error_code($e->getMessage());
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
     * Combines the estimated input (prompt) token cost with the expected output
     * size for the given request type, applies a 25% safety buffer, and caps the
     * result at the configured aips_max_tokens_limit setting to prevent
     * unexpectedly large or costly requests.
     *
     * Token estimation uses the standard approximation of 1 token ≈ 4 characters.
     *
     * @param string     $prompt The prompt that will be sent to the AI. Its length
     *                           is used to estimate the input token cost.
     * @param string|int $type   Request type: 'title', 'excerpt', 'content', or a
     *                           custom integer expected-output token count. Unknown
     *                           string types fall back to 'content' sizing.
     * @return int The calculated maxTokens value (always ≥ 1).
     */
    private function calculate_max_tokens($prompt, $type = 'content') {
        // Determine the expected output token requirement for this request type.
        if (is_int($type) && $type > 0) {
            // Caller supplied a custom output token count as the base.
            $output_tokens = $type;
        } else {
            $config = AIPS_Config::get_instance();
            switch ($type) {
                case 'title':
                    // Short titles: ~10-20 words.
                    $output_tokens = (int) $config->get_option('aips_max_tokens_title');
                    break;
                case 'excerpt':
                    // 2-3 sentence summary: ~50-75 words.
                    $output_tokens = (int) $config->get_option('aips_max_tokens_excerpt');
                    break;
                case 'content':
                default:
                    // Full article body: use the configured content output token budget.
                    $output_tokens = (int) $config->get_option('aips_max_tokens_content');
                    break;
            }

            // Option values can be empty or zero after sanitization/casting.
            // Ensure a minimum non-zero output budget for calculation safety.
            $output_tokens = max(1, $output_tokens);
        }

        return AIPS_Token_Budget::calculate(
            $prompt,
            $output_tokens,
            array(
                'buffer_ratio' => 0.25,
                'minimum_tokens' => 1,
                'respect_config_limit' => true,
            )
        );
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
        $ai_config = AIPS_Config::get_instance()->get_ai_config();

        $default_options = array(
            'model'       => $ai_config['model'],
            'env_id'      => $ai_config['env_id'],
            'temperature' => $ai_config['temperature'],
        );

        $options = wp_parse_args($options);

        // Accept legacy 'envId' from callers; canonicalize to 'env_id'.
        if (isset($options['envId']) && !isset($options['env_id'])) {
            $options['env_id'] = $options['envId'];
        }

        $options = wp_parse_args($options, $default_options);
        $params  = array();

        if (!empty($options['model'])) {
            $params['model'] = $options['model'];
        }

        if (!empty($options['env_id'])) {
            $params['env_id'] = $options['env_id'];
        }

        // Determine max_tokens: respect any explicit developer override; otherwise
        // calculate dynamically based on the prompt and request type.
        if (isset($options['max_tokens'])) {
            $params['max_tokens'] = $options['max_tokens'];
        } elseif (isset($options['maxTokens'])) {
            // Backward compatibility for legacy callers using camelCase.
            $params['max_tokens'] = $options['maxTokens'];
        } else {
            $type                 = isset($options['request_type']) ? $options['request_type'] : 'content';
            $params['max_tokens'] = $this->calculate_max_tokens($prompt, $type);
        }

        if (isset($options['temperature'])) {
            $params['temperature'] = $options['temperature'];
        }

        // Forward optional advanced canonical keys; providers translate them to
        // their native API as needed.
        foreach (self::OPTIONAL_QUERY_OPTION_KEYS as $key) {
            // env_id is already handled above.
            if ('env_id' === $key) {
                continue;
            }

            if (isset($options[$key])) {
                $params[$key] = $options[$key];
            }
        }

        // Pass through a JSON schema for providers that support native structured JSON.
        if (isset($options['json_schema'])) {
            $params['json_schema'] = $options['json_schema'];
        }

        return $params;
    }

    /**
     * Generate an embedding vector for a text string.
     *
     * Delegates the raw call to the active provider while reusing the service's
     * resilience and logging. Providers that cannot do embeddings surface an
     * 'embeddings_not_supported' error.
     *
     * Callers may pass 'embeddings_env_id' to target a specific Meow embeddings
     * environment; there is no plugin setting for it, so by default embeddings
     * run against the provider's default environment.
     *
     * @param string $text    The text to embed.
     * @param array  $options Optional. Canonical options (e.g. embeddings_env_id).
     * @return array|WP_Error The embedding vector or WP_Error on failure.
     */
    public function generate_embedding($text, $options = array()) {
        if (!$this->provider->is_available()) {
            $error = new WP_Error('ai_unavailable', __('The selected AI provider is not available.', 'ai-post-scheduler'));
            $this->log_call('embedding', $text, $options, $error);
            $this->emit_integration_error_notification('embedding', $error, $options);
            return $error;
        }

        if (!$this->provider->supports_embeddings()) {
            return new WP_Error('embeddings_not_supported', __('Embeddings are not supported by the current AI provider.', 'ai-post-scheduler'));
        }

        $params = is_array($options) ? $options : array();

        $result = $this->resilience_service->execute_safely(function() use ($text, $params, $options) {
            try {
                $embedding = $this->provider->generate_embedding($text, $params);

                if (empty($embedding) || !is_array($embedding)) {
                    $error = new WP_Error('empty_response', __('AI provider returned an empty embedding response.', 'ai-post-scheduler'));
                    $this->log_call('embedding', $text, $options, $error);
                    return $error;
                }

                $this->log_call('embedding', $text, $options, null, wp_json_encode($embedding));
                return $embedding;
            } catch (Exception $e) {
                $provider_code = $this->provider->extract_error_code($e->getMessage());
                $error = new WP_Error($provider_code ?: 'embedding_failed', $e->getMessage());
                $this->log_call('embedding', $text, $options, $error);
                return $error;
            }
        }, 'embedding', $text, $options);

        if (is_wp_error($result)) {
            $code = $result->get_error_code();

            if (in_array($code, array('circuit_breaker_open', 'rate_limit_exceeded'), true)) {
                $this->log_call('embedding', $text, $options, $result);
                $this->emit_quota_alert_notification('embedding', $result, $options);
            }
        }

        return $result;
    }

    /**
     * Whether the active provider can generate embeddings.
     *
     * @return bool
     */
    public function supports_embeddings() {
        return $this->provider->is_available() && $this->provider->supports_embeddings();
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
            'timestamp' => AIPS_DateTime::now()->toIso8601(),
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
            'prompt' => $prompt,
            'response_length' => strlen($response_for_length),
            'response' => $response,
            'options' => $options,
            'error_message' => $error_message,
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
