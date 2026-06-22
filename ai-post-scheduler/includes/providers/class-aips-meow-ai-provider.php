<?php
/**
 * Meow Apps AI Engine Provider
 *
 * Adapts the Meow Apps AI Engine plugin (the global $mwai / $mwai_core objects)
 * to AIPS_AI_Provider_Interface. This is the historical default backend; its
 * behavior preserves what AIPS_AI_Service did before the provider abstraction was
 * introduced.
 *
 * @package AI_Post_Scheduler
 * @since 3.1.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class AIPS_Meow_AI_Provider implements AIPS_AI_Provider_Interface {

    /**
     * @var mixed Cached AI Engine instance (the global $mwai).
     */
    private $ai_engine = null;

    /**
     * {@inheritDoc}
     */
    public function get_id(): string {
        return 'meow';
    }

    /**
     * {@inheritDoc}
     */
    public function get_label(): string {
        return __('Meow Apps AI Engine', 'ai-post-scheduler');
    }

    /**
     * Lazy-load and cache the AI Engine instance from the global scope.
     *
     * @return mixed|null
     */
    private function get_ai_engine() {
        if ($this->ai_engine === null && isset($GLOBALS['mwai'])) {
            $this->ai_engine = $GLOBALS['mwai'];
        }

        return $this->ai_engine;
    }

    /**
     * {@inheritDoc}
     */
    public function is_available(): bool {
        return $this->get_ai_engine() !== null;
    }

    /**
     * {@inheritDoc}
     */
    public function get_unavailable_reason(): string {
        return __('Meow Apps AI Engine plugin is not installed or active.', 'ai-post-scheduler');
    }

    /**
     * Translate canonical parameters into Meow AI Engine's native param names.
     *
     * Meow expects camelCase keys (maxTokens, envId). The plugin's canonical
     * contract uses snake_case (max_tokens, env_id) plus a set of optional
     * pass-through keys that Meow's simpleTextQuery() understands.
     *
     * @param array $params Canonical parameters.
     * @return array Meow-native parameters.
     */
    private function map_params(array $params): array {
        $native = array();

        if (!empty($params['model'])) {
            $native['model'] = $params['model'];
        }

        // env_id (canonical) → envId (Meow). Tolerate a legacy 'envId' too.
        if (!empty($params['env_id'])) {
            $native['envId'] = $params['env_id'];
        } elseif (!empty($params['envId'])) {
            $native['envId'] = $params['envId'];
        }

        if (isset($params['max_tokens'])) {
            $native['maxTokens'] = $params['max_tokens'];
        } elseif (isset($params['maxTokens'])) {
            $native['maxTokens'] = $params['maxTokens'];
        }

        if (isset($params['temperature'])) {
            $native['temperature'] = $params['temperature'];
        }

        // Optional advanced keys forwarded verbatim to simpleTextQuery().
        foreach (array('context', 'instructions', 'messages', 'embeddings_env_id', 'max_results', 'api_key') as $key) {
            if (isset($params[$key])) {
                $native[$key] = $params[$key];
            }
        }

        return $native;
    }

    /**
     * {@inheritDoc}
     */
    public function generate_text(string $prompt, array $params): string {
        $ai = $this->get_ai_engine();

        if (!$ai) {
            throw new Exception(__('AI Engine plugin is not available.', 'ai-post-scheduler'));
        }

        return (string) $ai->simpleTextQuery($prompt, $this->map_params($params));
    }

    /**
     * {@inheritDoc}
     */
    public function generate_json(?string $prompt, array $params): ?array {
        $ai = $this->get_ai_engine();

        if (!$ai) {
            throw new Exception(__('AI Engine plugin is not available.', 'ai-post-scheduler'));
        }

        if (!$this->supports_native_json()) {
            // Signal the service to use its text-based JSON fallback.
            return null;
        }

        // simpleJsonQuery supports only a limited parameter set (model, env_id).
        $native = $this->map_params($params);
        $json_params = array();

        if (!empty($native['model'])) {
            $json_params['model'] = $native['model'];
        }

        if (!empty($native['envId'])) {
            $json_params['env_id'] = $native['envId'];
        }

        $result = $ai->simpleJsonQuery($prompt, $json_params);

        return is_array($result) ? $result : null;
    }

    /**
     * {@inheritDoc}
     */
    public function generate_image(string $prompt, array $params): string {
        $ai = $this->get_ai_engine();

        if (!$ai) {
            throw new Exception(__('AI Engine plugin is not available.', 'ai-post-scheduler'));
        }

        // Historically the image path passed the caller options straight through.
        $image = $ai->simpleImageQuery($prompt, $params);

        // Some engines return an array of URLs; unwrap the first.
        if (is_array($image) && !empty($image[0])) {
            $image = $image[0];
        }

        return (string) $image;
    }

    /**
     * {@inheritDoc}
     */
    public function generate_embedding(string $text, array $params): array {
        if (!$this->supports_embeddings()) {
            throw new Exception(__('Embeddings are not supported by the current AI Engine configuration.', 'ai-post-scheduler'));
        }

        $core = isset($GLOBALS['mwai_core']) ? $GLOBALS['mwai_core'] : null;

        if (!$core) {
            throw new Exception(__('AI Engine plugin is not available.', 'ai-post-scheduler'));
        }

        $query = new Meow_MWAI_Query_Embed($text);

        if (!empty($params['embeddings_env_id']) && method_exists($query, 'set_embeddings_env_id')) {
            $query->set_embeddings_env_id($params['embeddings_env_id']);
        }

        $response = $core->run_query($query);

        if (!$response || empty($response->result)) {
            throw new Exception(__('AI Engine returned an empty embedding response.', 'ai-post-scheduler'));
        }

        return $response->result;
    }

    /**
     * {@inheritDoc}
     */
    public function supports_native_json(): bool {
        $ai = $this->get_ai_engine();

        return $ai !== null && method_exists($ai, 'simpleJsonQuery');
    }

    /**
     * {@inheritDoc}
     */
    public function supports_embeddings(): bool {
        return class_exists('Meow_MWAI_Query_Embed') && isset($GLOBALS['mwai_core']) && $GLOBALS['mwai_core'] !== null;
    }

    /**
     * {@inheritDoc}
     */
    public function extract_error_code(string $message): string {
        // Meow forwards the raw provider (OpenAI, etc.) error as the exception
        // message; the resilience service already knows how to classify those.
        return AIPS_Resilience_Service::extract_error_code_from_message($message);
    }
}
