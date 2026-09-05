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
     * Errors no other model in the preference list can satisfy either, so trying
     * the next one only burns another provider call. Mirrors the WordPress AI
     * Client adapter's failover policy.
     */
    private const NON_FALLBACK_CODES = array(
        'content_policy_violation',
        'context_length_exceeded',
        'invalid_request_error',
    );

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
			$model = (string) $params['model'];
			if (isset($params['routing_fallback_enabled']) && !$params['routing_fallback_enabled']) {
				$model = trim(explode(',', $model)[0]);
			}
			$native['model'] = $model;
        }

        // Translate canonical env_id to Meow's native envId parameter.
        if (!empty($params['env_id'])) {
            $native['envId'] = $params['env_id'];
        }

        if (isset($params['max_tokens'])) {
            $native['maxTokens'] = $params['max_tokens'];
        }

        if (isset($params['temperature'])) {
            $native['temperature'] = $params['temperature'];
        }

        // Optional advanced keys forwarded verbatim to simpleTextQuery().
        foreach (array('context', 'instructions', 'embeddings_env_id', 'max_results', 'api_key') as $key) {
            if (isset($params[$key])) {
                $native[$key] = $params[$key];
            }
        }

        // Conversation history uses the plugin's canonical role names; AI Engine
        // expects OpenAI-style 'assistant' for model turns and a 'content' key.
        if (!empty($params['messages']) && is_array($params['messages'])) {
            $messages = $this->map_messages($params['messages']);

            if (!empty($messages)) {
                $native['messages'] = $messages;
            }
        }

        return $native;
    }

    /**
     * Whether a failure should be retried against the next ordered model.
     *
     * @param Exception $exception Provider exception.
     * @param array     $params    Request parameters.
     * @return bool
     */
    private function should_try_next_model(Exception $exception, array $params): bool {
        if (isset($params['routing_fallback_enabled']) && !$params['routing_fallback_enabled']) {
            return false;
        }

        return !in_array($this->extract_error_code($exception->getMessage()), self::NON_FALLBACK_CODES, true);
    }

    /**
     * Return the ordered model list for a request. Meow accepts one model per
     * query, so the adapter performs fallback attempts at this boundary.
     */
    private function model_preferences(array $params): array {
        if (empty($params['model'])) {
            return array('');
        }

        $models = array_values(array_filter(array_map('trim', explode(',', (string) $params['model']))));
        if (empty($models)) {
            return array('');
        }

        if (isset($params['routing_fallback_enabled']) && !$params['routing_fallback_enabled']) {
            return array($models[0]);
        }

        return $models;
    }

    /**
     * Translate conversation turns into AI Engine's message format.
     *
     * Accepts the plugin's canonical shape (role user|model, 'text') and passes
     * through entries already in AI Engine's own shape ('content'). The
     * pass-through matters because 'messages' was a documented free-form option
     * forwarded verbatim before conversation support existed — dropping
     * unrecognised entries would silently discard a caller's history.
     *
     * @param array $turns Canonical turns from AIPS_AI_Conversation, or native messages.
     * @return array AI Engine messages.
     */
    private function map_messages(array $turns): array {
        $messages = array();

        foreach ($turns as $turn) {
            if (!is_array($turn) || !isset($turn['role'])) {
                continue;
            }

            if (isset($turn['text'])) {
                $messages[] = array(
                    'role'    => ($turn['role'] === AIPS_AI_Conversation::ROLE_MODEL) ? 'assistant' : 'user',
                    'content' => (string) $turn['text'],
                );

                continue;
            }

            if (isset($turn['content'])) {
                // Already native; forward unchanged.
                $messages[] = $turn;
            }
        }

        return $messages;
    }

    /**
     * {@inheritDoc}
     */
    public function generate_text(string $prompt, array $params): string {
        $ai = $this->get_ai_engine();

        if (!$ai) {
            throw new Exception(__('AI Engine plugin is not available.', 'ai-post-scheduler'));
        }

        $last_exception = null;
        foreach ($this->model_preferences($params) as $model) {
            $attempt = $params;
            if ($model !== '') {
                $attempt['model'] = $model;
            }
            try {
                return (string) $ai->simpleTextQuery($prompt, $this->map_params($attempt));
            } catch (Exception $exception) {
                $last_exception = $exception;
                if (!$this->should_try_next_model($exception, $params)) {
                    throw $exception;
                }
            }
        }

        if ($last_exception instanceof Exception) {
            throw $last_exception;
        }

        throw new Exception(__('AI Engine returned no text response.', 'ai-post-scheduler'));
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

        // simpleJsonQuery cannot carry conversation history. Silently dropping it
        // would leave the model answering a follow-up prompt ("based on the article
        // you just wrote...") with no article in context, producing confidently
        // fabricated output. Request the text-based JSON fallback instead: it runs
        // through generate_text(), which does forward messages.
        if (!empty($params['messages']) && is_array($params['messages'])) {
            return null;
        }

        // simpleJsonQuery supports only a limited parameter set (model, env_id).
        $json_params = array();

        if (!empty($params['model'])) {
            $json_params['model'] = $params['model'];
        }

        if (!empty($params['env_id'])) {
            $json_params['env_id'] = $params['env_id'];
        }

        $last_exception = null;
        foreach ($this->model_preferences($params) as $model) {
            if ($model !== '') {
                $json_params['model'] = $model;
            }
            try {
                $result = $ai->simpleJsonQuery($prompt, $json_params);
                return is_array($result) ? $result : null;
            } catch (Exception $exception) {
                $last_exception = $exception;
                if (!$this->should_try_next_model($exception, $params)) {
                    throw $exception;
                }
            }
        }

        if ($last_exception instanceof Exception) {
            throw $last_exception;
        }

        return null;
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
        $image = null;
        $last_exception = null;
        foreach ($this->model_preferences($params) as $model) {
            $attempt = $params;
            if ($model !== '') {
                $attempt['model'] = $model;
            }
            try {
                $image = $ai->simpleImageQuery($prompt, $attempt);
                break;
            } catch (Exception $exception) {
                $last_exception = $exception;
                if (!$this->should_try_next_model($exception, $params)) {
                    throw $exception;
                }
            }
        }

        if ($image === null && $last_exception instanceof Exception) {
            throw $last_exception;
        }

        // Some engines return an array of URLs; unwrap the first.
        if (is_array($image)) {
            if (empty($image[0])) {
                throw new Exception(__('AI Engine returned an empty image response.', 'ai-post-scheduler'));
            }
            $image = $image[0];
        }

        return is_string($image) ? $image : (string) $image;
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

        if (!empty($params['embeddings_env_id'])) {
            if (method_exists($query, 'set_embeddings_env_id')) {
                $query->set_embeddings_env_id($params['embeddings_env_id']);
            } elseif (method_exists($query, 'set_env_id')) {
                $query->set_env_id($params['embeddings_env_id']);
            } elseif (property_exists($query, 'envId')) {
                $query->envId = $params['embeddings_env_id'];
            }
        }

        if (!empty($params['model'])) {
            if (method_exists($query, 'set_model')) {
                $query->set_model($params['model']);
            } elseif (property_exists($query, 'model')) {
                $query->model = $params['model'];
            }
        }

        $response = $core->run_query($query);

        if (!$response || empty($response->result)) {
            throw new Exception(__('AI Engine returned an empty embedding response.', 'ai-post-scheduler'));
        }

        return $response->result;
    }

    /**
     * Fetch configured embedding environments / connections from Meow Apps AI Engine.
     *
     * Queries AI Engine configuration for custom vector environments (e.g. Percona,
     * OpenAI, Pinecone, Qdrant, Ollama) and returns normalized metadata.
     *
     * @return array<int, array{id: string, name: string, model: string, dimensions: int, serverType: string}>
     */
    public function get_embeddings_environments(): array {
        $environments = array();

        if (function_exists('mwai_get_embeddings_environments')) {
            $raw = mwai_get_embeddings_environments();
            if (is_array($raw)) {
                $environments = $raw;
            }
        }

        if (empty($environments) && isset($GLOBALS['mwai_core']) && is_object($GLOBALS['mwai_core']) && method_exists($GLOBALS['mwai_core'], 'get_embeddings_environments')) {
            $raw = $GLOBALS['mwai_core']->get_embeddings_environments();
            if (is_array($raw)) {
                $environments = $raw;
            }
        }

        if (empty($environments)) {
            $opt = get_option('mwai_embeddings', array());
            if (is_array($opt) && !empty($opt['environments']) && is_array($opt['environments'])) {
                $environments = $opt['environments'];
            } elseif (is_array($opt) && !empty($opt)) {
                $environments = $opt;
            } else {
                $mwai_opt = get_option('mwai_options', array());
                if (is_array($mwai_opt) && !empty($mwai_opt['embeddings_environments']) && is_array($mwai_opt['embeddings_environments'])) {
                    $environments = $mwai_opt['embeddings_environments'];
                }
            }
        }

        $normalized = array();
        foreach ($environments as $key => $env) {
            if (!is_array($env)) {
                continue;
            }
            $id         = !empty($env['id']) ? (string) $env['id'] : (string) $key;
            $name       = !empty($env['name']) ? (string) $env['name'] : (!empty($env['label']) ? (string) $env['label'] : $id);
            $model      = !empty($env['model']) ? (string) $env['model'] : (!empty($env['embeddingsModel']) ? (string) $env['embeddingsModel'] : 'text-embedding-3-small');
            $dimensions = !empty($env['dimensions']) ? absint($env['dimensions']) : (!empty($env['dims']) ? absint($env['dims']) : 1536);
            $serverType = !empty($env['serverType']) ? (string) $env['serverType'] : (!empty($env['type']) ? (string) $env['type'] : 'default');

            $normalized[] = array(
                'id'         => $id,
                'name'       => $name,
                'model'      => $model,
                'dimensions' => $dimensions,
                'serverType' => $serverType,
            );
        }

        return $normalized;
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
     *
     * AI Engine's simpleTextQuery() accepts a 'messages' parameter carrying prior
     * turns. map_messages() converts the plugin's canonical roles to the
     * user/assistant pair it expects.
     */
    public function supports_conversation(): bool {
        return $this->is_available();
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
