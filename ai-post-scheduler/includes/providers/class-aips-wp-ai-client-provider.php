<?php
/**
 * WordPress AI Client Provider
 *
 * Adapts the native WordPress core AI Client (wp_ai_client_prompt(), introduced
 * with the WordPress 7.0 Connectors API) to AIPS_AI_Provider_Interface.
 * Credentials and provider/model selection are handled by core's Connectors UI,
 * so this adapter only shapes prompts and reads results.
 *
 * The AI Client uses a fluent builder and returns WP_Error on failure. We convert
 * those into exceptions so AIPS_AI_Service's existing try/catch + error
 * classification path applies uniformly across providers.
 *
 * @package AI_Post_Scheduler
 * @since 3.1.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class AIPS_WP_AI_Client_Provider implements AIPS_AI_Provider_Interface {

    /**
     * {@inheritDoc}
     */
    public function get_id(): string {
        return 'wp_ai_client';
    }

    /**
     * {@inheritDoc}
     */
    public function get_label(): string {
        return __('WordPress AI Client', 'ai-post-scheduler');
    }

    /**
     * {@inheritDoc}
     */
    public function is_available(): bool {
        return $this->supports_text_generation();
    }

    /**
     * Explain why the provider cannot currently serve generation requests.
     *
     * @return string Human-readable unavailable reason, or an empty string when ready.
     */
    public function get_unavailable_reason(): string {
        if (!function_exists('wp_ai_client_prompt')) {
            return __('WordPress AI Client is not installed or active.', 'ai-post-scheduler');
        }

        $builder = $this->create_prompt_builder();

        if ($builder === null) {
            return __('WordPress AI Client could not create a prompt builder. Check connector configuration.', 'ai-post-scheduler');
        }

        if (!$this->supports_text_generation($builder)) {
            return __('WordPress AI Client has no connector/model configured for text generation.', 'ai-post-scheduler');
        }

        return '';
    }

    /**
     * Create a probe builder for capability checks (always uses an empty prompt).
     *
     * Uses WeakMap so that entries are automatically removed when the provider
     * instance is GC'd, preventing stale-cache hits when PHP recycles object
     * identities (which spl_object_hash-keyed static arrays suffer from).
     *
     * @return object|null Prompt builder, or null when unavailable/errored.
     */
    private function create_prompt_builder() {
        static $cache = null;

        if ($cache === null) {
            $cache = new WeakMap();
        }

        if (isset($cache[$this])) {
            return $cache[$this];
        }

        if (!function_exists('wp_ai_client_prompt')) {
            $cache[$this] = null;
            return null;
        }

        $builder = wp_ai_client_prompt('');
        $cache[$this] = is_wp_error($builder) ? null : $builder;

        return $cache[$this];
    }

    /**
     * Check whether the builder can perform text generation.
     *
     * @param object|null $builder Optional existing builder to avoid probing twice.
     * @return bool True when the AI Client reports text generation support.
     */
    public function supports_text_generation($builder = null): bool {
        if ($builder === null) {
            $builder = $this->create_prompt_builder();
        }

        if (!is_object($builder) || !method_exists($builder, 'is_supported_for_text_generation')) {
            return false;
        }

        return (bool) $builder->is_supported_for_text_generation();
    }

    /**
     * Check whether the builder can perform image generation.
     *
     * @param object|null $builder Optional existing builder to avoid probing twice.
     * @return bool True when the AI Client reports image generation support.
     */
    public function supports_image_generation($builder = null): bool {
        if ($builder === null) {
            $builder = $this->create_prompt_builder();
        }

        if (!is_object($builder) || !method_exists($builder, 'is_supported_for_image_generation')) {
            return false;
        }

        return (bool) $builder->is_supported_for_image_generation();
    }

    /**
     * Build a configured prompt builder from canonical parameters.
     *
     * @param string $prompt Prompt text.
     * @param array  $params Canonical parameters.
     * @return object The Prompt_Builder_With_WP_Error instance.
     */
    private function build_prompt(string $prompt, array $params) {
        $builder = wp_ai_client_prompt($prompt);

        if (is_wp_error($builder)) {
            $this->throw_from_wp_error($builder);
        }

        // model may be a comma-separated preference list (primary, fallback, ...).
        if (!empty($params['model'])) {
            $preferences = array_filter(array_map('trim', explode(',', (string) $params['model'])));

            if (!empty($preferences) && method_exists($builder, 'using_model_preference')) {
                $builder = $builder->using_model_preference(...array_values($preferences));
            }
        }

        if (isset($params['temperature']) && method_exists($builder, 'using_temperature')) {
            $builder = $builder->using_temperature((float) $params['temperature']);
        }

        $max_tokens = isset($params['max_tokens']) ? $params['max_tokens'] : (isset($params['maxTokens']) ? $params['maxTokens'] : null);

        if ($max_tokens !== null && method_exists($builder, 'using_max_tokens')) {
            $builder = $builder->using_max_tokens((int) $max_tokens);
        }

        return $builder;
    }

    /**
     * Convert a WP_Error result into an exception carrying its code and message.
     *
     * @param WP_Error $error Error returned by the AI Client.
     * @return void
     * @throws Exception Always.
     */
    private function throw_from_wp_error(WP_Error $error): void {
        // Prefix with the error code so extract_error_code() can recover it.
        $code = $error->get_error_code();
        $message = $error->get_error_message();

        throw new Exception($code ? $code . ': ' . $message : $message);
    }

    /**
     * {@inheritDoc}
     */
    public function generate_text(string $prompt, array $params): string {
        $builder = $this->build_prompt($prompt, $params);

        if (!$this->supports_text_generation($builder)) {
            throw new Exception('text_generation_not_supported: ' . __('Text generation is not supported by the configured WordPress AI Client connector.', 'ai-post-scheduler'));
        }

        $result = $builder->generate_text();

        if (is_wp_error($result)) {
            $this->throw_from_wp_error($result);
        }

        return $result;
    }

    /**
     * {@inheritDoc}
     */
    public function generate_json(?string $prompt, array $params): ?array {
        // Native structured JSON requires a schema; without one, fall back to the
        // service's text-based JSON extraction.
        if (empty($params['json_schema']) || !is_array($params['json_schema'])) {
            return null;
        }

        $builder = $this->build_prompt((string) $prompt, $params);

        if (!method_exists($builder, 'as_json_response') || !$this->supports_text_generation($builder)) {
            return null;
        }

        $result = $builder->as_json_response($params['json_schema'])->generate_text();

        if (is_wp_error($result)) {
            $this->throw_from_wp_error($result);
        }

        $decoded = is_array($result) ? $result : json_decode((string) $result, true);

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * {@inheritDoc}
     */
    public function generate_image(string $prompt, array $params): string {
        $builder = $this->build_prompt($prompt, $params);

        if (!$this->supports_image_generation($builder)) {
            throw new Exception('image_generation_not_supported: ' . __('Image generation is not supported by the configured WordPress AI Client connector.', 'ai-post-scheduler'));
        }

        $result = $builder->generate_image();

        if (is_wp_error($result)) {
            $this->throw_from_wp_error($result);
        }

        // The client returns a file object; expose a usable URL/data URI string.
        if (is_object($result) && method_exists($result, 'getDataUri')) {
            return $result->getDataUri();
        }

        if (is_array($result) && !empty($result[0])) {
            $first = $result[0];

            return (is_object($first) && method_exists($first, 'getDataUri')) ? $first->getDataUri() : (string) $first;
        }

        return is_string($result) ? $result : '';
    }

    /**
     * {@inheritDoc}
     */
    public function generate_embedding(string $text, array $params): array {
        // The WordPress AI Client does not expose a stable embeddings API yet.
        throw new Exception('embeddings_not_supported: ' . __('Embeddings are not supported by the WordPress AI Client.', 'ai-post-scheduler'));
    }

    /**
     * {@inheritDoc}
     */
    public function supports_native_json(): bool {
        $builder = $this->create_prompt_builder();

        return is_object($builder) && method_exists($builder, 'as_json_response') && $this->supports_text_generation($builder);
    }

    /**
     * {@inheritDoc}
     */
    public function supports_embeddings(): bool {
        return false;
    }

    /**
     * {@inheritDoc}
     */
    public function extract_error_code(string $message): string {
        // generate_*() prefix WP_Error codes as "code: message"; recover the code
        // when present, otherwise fall back to free-text pattern matching.
        if (preg_match('/^([a-z0-9_\-]+):\s/i', $message, $matches)) {
            return $matches[1];
        }

        return AIPS_Resilience_Service::extract_error_code_from_message($message);
    }
}
