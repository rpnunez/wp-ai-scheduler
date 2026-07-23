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
        
        $builder = wp_ai_client_prompt(null);
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

        if (!is_object($builder)) {
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

        if (!is_object($builder)) {
            return false;
        }

        return (bool) $builder->is_supported_for_image_generation();
    }

    /**
     * Call a fluent builder method, guarding against WP_Error returns.
     *
     * The core builder proxies snake_case calls via __call and converts SDK
     * exceptions into WP_Error returns even for chainable configuration methods.
     * Without this guard a mid-chain WP_Error would fatal on the next chained
     * call (WP_Error has no such methods). The is_callable check is always true
     * for the real __call-based builder; it exists so duck-typed builders (test
     * stubs, future implementations) without the method are skipped gracefully.
     *
     * @param object $builder Current builder instance.
     * @param string $method  Builder method to invoke.
     * @param mixed  ...$args Arguments for the method.
     * @return object The (possibly new) builder instance.
     * @throws Exception When the builder method returns a WP_Error.
     */
    private function chain($builder, string $method, ...$args) {
        if (!is_callable(array($builder, $method))) {
            return $builder;
        }

        $result = $builder->$method(...$args);

        if (is_wp_error($result)) {
            $this->throw_from_wp_error($result);
        }

        return $result;
    }

    /**
     * Build a configured prompt builder from canonical parameters.
     *
     * @param string $prompt Prompt text.
     * @param array  $params Canonical parameters.
     * @return object The Prompt_Builder_With_WP_Error instance.
     * @throws Exception When the AI Client rejects the prompt or a configuration step.
     */
    private function build_prompt(string $prompt, array $params) {
        $builder = wp_ai_client_prompt($prompt);

        if (is_wp_error($builder)) {
            $this->throw_from_wp_error($builder);
        }

        // The canonical 'context' and 'instructions' keys carry the stable guidance
        // (voice instructions, output formatting rules) that must apply to every turn.
        // The AI Client models that as a system instruction rather than prompt text.
        $system_instruction = $this->build_system_instruction($params);

        if ($system_instruction !== '') {
            $builder = $this->chain($builder, 'using_system_instruction', $system_instruction);
        }

        // Prior turns are replayed so follow-up prompts can refer back to text the
        // model already produced. withHistory() PREPENDS to the message list, so it
        // must be called exactly once with the full ordered transcript.
        if (!empty($params['messages']) && is_array($params['messages'])) {
            $history = $this->to_history_messages($params['messages']);

            if (!empty($history)) {
                $builder = $this->chain($builder, 'with_history', ...$history);
            }
        }

        // model may be a comma-separated preference list (primary, fallback, ...).
        if (!empty($params['model'])) {
            $preferences = array_filter(array_map('trim', explode(',', (string) $params['model'])));

            if (!empty($preferences)) {
                $builder = $this->chain($builder, 'using_model_preference', ...array_values($preferences));
            }
        }

        if (isset($params['temperature'])) {
            $builder = $this->chain($builder, 'using_temperature', (float) $params['temperature']);
        }

        $max_tokens = isset($params['max_tokens']) ? $params['max_tokens'] : (isset($params['maxTokens']) ? $params['maxTokens'] : null);

        if ($max_tokens !== null) {
            $builder = $this->chain($builder, 'using_max_tokens', (int) $max_tokens);
        }

        return $builder;
    }

    /**
     * Fully-qualified names of the AI Client message DTOs.
     *
     * These are raw SDK classes shipped with WordPress core's AI Client — unlike
     * the prompt builder they are not wrapped, so every use must be guarded to
     * keep the plugin loadable when the AI Client is absent.
     */
    private const DTO_USER_MESSAGE  = 'WordPress\\AiClient\\Messages\\DTO\\UserMessage';
    private const DTO_MODEL_MESSAGE = 'WordPress\\AiClient\\Messages\\DTO\\ModelMessage';
    private const DTO_MESSAGE_PART  = 'WordPress\\AiClient\\Messages\\DTO\\MessagePart';

    /**
     * Whether the AI Client message DTOs needed for history are present.
     *
     * @return bool
     */
    private function has_message_dtos(): bool {
        return class_exists(self::DTO_USER_MESSAGE)
            && class_exists(self::DTO_MODEL_MESSAGE)
            && class_exists(self::DTO_MESSAGE_PART);
    }

    /**
     * Convert canonical conversation turns into AI Client Message objects.
     *
     * MessagePart infers a TEXT part from a string, and UserMessage/ModelMessage
     * each take an array of parts.
     *
     * Any malformed turn throws rather than being skipped. A conversational
     * follow-up prompt deliberately omits the article ("generate a title for the
     * article you just wrote"), so quietly sending it without the history behind
     * it would not fail — it would produce a confidently fabricated answer that
     * gets saved to the post. Failing loudly lets the resilience layer surface it
     * and the caller mark the component incomplete.
     *
     * @param array $turns Canonical turns from AIPS_AI_Conversation.
     * @return array List of Message objects; empty when the DTOs are unavailable.
     * @throws Exception When a turn cannot be represented as a Message.
     */
    private function to_history_messages(array $turns): array {
        if (!$this->has_message_dtos()) {
            return array();
        }

        $user_class  = self::DTO_USER_MESSAGE;
        $model_class = self::DTO_MODEL_MESSAGE;
        $part_class  = self::DTO_MESSAGE_PART;

        $messages = array();

        foreach ($turns as $turn) {
            // MessagePart rejects empty strings, and a skipped turn would break the
            // strict user/model alternation the SDK validates before generating.
            if (!is_array($turn) || !isset($turn['role'], $turn['text']) || trim((string) $turn['text']) === '') {
                throw new Exception('invalid_conversation_history: ' . __('Conversation history contains a malformed turn.', 'ai-post-scheduler'));
            }

            $part = new $part_class((string) $turn['text']);

            $messages[] = ($turn['role'] === AIPS_AI_Conversation::ROLE_MODEL)
                ? new $model_class(array($part))
                : new $user_class(array($part));
        }

        return $messages;
    }

    /**
     * Assemble the system instruction from the canonical context/instructions keys.
     *
     * AIPS_AI_Service forwards both keys for every provider; Meow passes them to
     * simpleTextQuery() as separate channels. The AI Client has a single system
     * instruction slot, so the two are joined in the order the plugin builds them
     * (context first, then any explicit instructions).
     *
     * @param array $params Canonical parameters.
     * @return string System instruction text, or '' when neither key is set.
     */
    private function build_system_instruction(array $params): string {
        $parts = array();

        foreach (array('context', 'instructions') as $key) {
            if (!isset($params[$key]) || !is_string($params[$key])) {
                continue;
            }

            $value = trim($params[$key]);

            if ($value !== '') {
                $parts[] = $value;
            }
        }

        return implode("\n\n", $parts);
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

        // Structural unavailability (duck-typed builder without a JSON API, or no
        // text-capable connector) requests the service's text-based fallback.
        if (!is_callable([$builder, 'as_json_response']) || !$this->supports_text_generation($builder)) {
            return null;
        }

        // A real connector error mid-chain must throw (reaching the resilience
        // layer), not silently trigger the fallback.
        $builder = $this->chain($builder, 'as_json_response', $params['json_schema']);
        $result  = $builder->generate_text();

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

        return is_object($builder) && is_callable([$builder, 'as_json_response']) && $this->supports_text_generation($builder);
    }

    /**
     * {@inheritDoc}
     */
    public function supports_embeddings(): bool {
        return false;
    }

    /**
     * {@inheritDoc}
     *
     * Requires both a text-capable connector and the AI Client message DTOs used
     * to build the history payload.
     */
    public function supports_conversation(): bool {
        return $this->has_message_dtos() && $this->supports_text_generation();
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
