<?php
/**
 * WordPress AI Client Provider
 *
 * Adapts the native WordPress core AI Client (wp_ai_client_prompt(), introduced
 * with the WordPress 7.0 Connectors API) to AIPS_AI_Provider_Interface.
 * Credentials and model selection are handled by core. AIPS applies the site
 * administrator's connector allowlist and failover policy around those calls.
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
     * Minimum timeout for AI Client requests created by this adapter.
     *
     * Full article and image generations regularly exceed the core client's
     * 30-second default even when the connector remains healthy.
     */
    private const REQUEST_TIMEOUT_SECONDS = 90.0;

	/** Default connector cooldown after a provider-specific failure. */
	private const CONNECTOR_COOLDOWN_SECONDS = 60;

	/** Longer cooldown for credential, billing, and quota failures. */
	private const CONNECTOR_CONFIGURATION_COOLDOWN_SECONDS = 900;

	/** Connector failures that should cool down immediately. */
	private const CONNECTOR_CONFIGURATION_FAILURE_CODES = array(
		'invalid_api_key',
		'billing_not_active',
		'insufficient_quota',
		'wpai_connector_not_approved',
	);

	/** Connector failure codes caused by the request rather than the connector. */
	private const NON_FAILOVER_CODES = array(
		'content_policy_violation',
		'context_length_exceeded',
		'invalid_request_error',
		'json_query_unavailable',
	);

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
        return $this->has_configured_connector();
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

        $connectors = $this->get_active_ai_connectors();

        if (empty($connectors)) {
            return __('WordPress AI Client has no active AI connector registered.', 'ai-post-scheduler');
        }

		$config = AIPS_Config::get_instance();
		if ((string) $config->get_option('aips_wp_ai_connector_mode') === 'selected'
			&& empty((array) $config->get_option('aips_wp_ai_connector_ids'))) {
			return __('WordPress AI Client is limited to selected connectors, but none are selected.', 'ai-post-scheduler');
		}

        if (!$this->has_configured_connector($connectors)) {
			return __('WordPress AI Client has no allowed connector with configured credentials.', 'ai-post-scheduler');
        }

        return '';
    }

    /**
     * Return active connectors registered as AI providers.
     *
     * This is intentionally a local registry check. Calling the AI Client's
     * is_supported_for_* methods can make a remote model-catalog request, so it
     * must not be used to decide whether to show a missing-provider notice.
     *
     * @return array<string,array<string,mixed>> Active AI connectors keyed by ID.
     */
	public function get_active_ai_connectors(): array {
        if (!function_exists('wp_get_connectors')) {
            return array();
        }

        $active = array();

        foreach (wp_get_connectors() as $id => $connector) {
            if (!is_array($connector) || ($connector['type'] ?? '') !== 'ai_provider') {
                continue;
            }

            $is_active = $connector['plugin']['is_active'] ?? null;

            if (is_callable($is_active)) {
                try {
                    if (!$is_active()) {
                        continue;
                    }
                } catch (Throwable $e) {
                    continue;
                }
            }

            $active[(string) $id] = $connector;
        }

		/**
		 * Filters active WordPress AI connectors visible to AIPS.
		 *
		 * @param array $active Active AI connectors keyed by provider ID.
		 */
		return (array) apply_filters('aips_wp_ai_client_connectors', $active);
    }

	/**
	 * Whether a connector has locally configured authentication.
	 *
	 * This deliberately does not make a network request. WordPress and the
	 * connector own live credential validation when a request is attempted.
	 *
	 * @param array $connector Connector registry entry.
	 * @return bool
	 */
	public function is_connector_configured(array $connector): bool {
		$authentication = isset($connector['authentication']) && is_array($connector['authentication'])
			? $connector['authentication']
			: array();
		$method = isset($authentication['method']) ? (string) $authentication['method'] : '';

		if ($method === 'none') {
			return true;
		}

		if ($method !== '' && $method !== 'api_key') {
			return true;
		}

		if ($method === '') {
			return false;
		}

		$env_name = isset($authentication['env_var_name']) ? (string) $authentication['env_var_name'] : '';
		if ($env_name !== '') {
			$env_value = getenv($env_name);
			if ($env_value !== false && $env_value !== '') {
				return true;
			}
		}

		$constant_name = isset($authentication['constant_name']) ? (string) $authentication['constant_name'] : '';
		if ($constant_name !== '' && defined($constant_name)) {
			$constant_value = constant($constant_name);
			if (is_string($constant_value) && $constant_value !== '') {
				return true;
			}
		}

		$setting_name = isset($authentication['setting_name']) ? (string) $authentication['setting_name'] : '';
		$stored_value = $setting_name !== '' ? get_option($setting_name, '') : '';

		return is_string($stored_value) && $stored_value !== '';
	}

    /**
     * Whether at least one active AI connector has local authentication.
     *
     * Credential presence is distinct from operational health. The latter may
     * require a remote request and is checked when generation is attempted.
     * Connectors using non-API-key authentication are treated as configured;
     * their provider owns any additional authentication validation.
     *
     * @param array|null $connectors Optional pre-fetched active connectors.
     * @return bool True when a connector is locally configured.
     */
    private function has_configured_connector(?array $connectors = null): bool {
        $connectors = $connectors ?? $this->get_active_ai_connectors();
		$connectors = $this->apply_connector_selection($connectors);
        $configured = false;

        foreach ($connectors as $connector) {
			if (is_array($connector) && $this->is_connector_configured($connector)) {
				$configured = true;
				break;
			}
        }

        /**
         * Filters whether the WordPress AI Client has a configured connector.
         *
         * This supports connectors with authentication mechanisms not represented
         * by the core API-key metadata, without forcing a network health probe.
         *
         * @param bool  $configured Whether local connector configuration was found.
         * @param array $connectors Active AI connector definitions.
         */
        return (bool) apply_filters('aips_wp_ai_client_has_configured_connector', $configured, $connectors);
    }

	/**
	 * Apply the administrator's connector allowlist and priority.
	 *
	 * @param array $connectors Active connector definitions.
	 * @return array
	 */
	private function apply_connector_selection(array $connectors): array {
		$config = AIPS_Config::get_instance();
		$mode   = (string) $config->get_option('aips_wp_ai_connector_mode');

		if ($mode !== 'selected') {
			return $connectors;
		}

		$selected = (array) $config->get_option('aips_wp_ai_connector_ids');
		$ordered  = array();

		foreach ($selected as $connector_id) {
			$connector_id = (string) $connector_id;
			if (isset($connectors[$connector_id])) {
				$ordered[$connector_id] = $connectors[$connector_id];
			}
		}

		return $ordered;
	}

	/**
	 * Resolve configured connectors in the order they should be attempted.
	 *
	 * A null entry preserves compatibility with AI Client implementations that
	 * predate the Connectors registry while still supporting automatic routing.
	 *
	 * @return array<int,string|null>
	 */
	private function get_routing_connector_ids(): array {
		$connectors = $this->apply_connector_selection($this->get_active_ai_connectors());
		$configured = array();

		foreach ($connectors as $connector_id => $connector) {
			if (!is_array($connector) || !$this->is_connector_configured($connector)) {
				continue;
			}

			$configured[] = (string) $connector_id;
		}

		if (!empty($configured)) {
			$failover_enabled = (bool) AIPS_Config::get_instance()->get_option('aips_wp_ai_connector_failover');

			if (!$failover_enabled) {
				return $this->is_connector_cooling_down($configured[0]) ? array() : array($configured[0]);
			}

			return array_values(array_filter($configured, function($connector_id) {
				return !$this->is_connector_cooling_down($connector_id);
			}));
		}

		$config = AIPS_Config::get_instance();
		if ((string) $config->get_option('aips_wp_ai_connector_mode') !== 'selected' && empty($connectors)) {
			return array(null);
		}

		return array();
	}

	/**
	 * Execute an operation against allowed connectors with sequential failover.
	 *
	 * @param callable $operation Receives a connector ID or null for legacy auto-routing.
	 * @return mixed
	 * @throws Exception When no connector can complete the operation.
	 */
	private function execute_with_connector_failover(callable $operation) {
		$connector_ids = $this->get_routing_connector_ids();

		if (empty($connector_ids)) {
			throw new Exception('no_connector: ' . __('No allowed WordPress AI connector is currently available.', 'ai-post-scheduler'));
		}

		$failover_enabled = (bool) AIPS_Config::get_instance()->get_option('aips_wp_ai_connector_failover');
		$last_exception   = null;

		foreach ($connector_ids as $index => $connector_id) {
			try {
				$result = $operation($connector_id);

				if ($connector_id !== null) {
					$this->record_connector_success($connector_id);
				}

				return $result;
			} catch (Throwable $e) {
				$last_exception = $e instanceof Exception ? $e : new Exception($e->getMessage(), 0, $e);
				$error_code     = $this->extract_error_code($e->getMessage());

				$should_fail_over = $this->should_fail_over($error_code, $e->getMessage());

				if ($connector_id !== null && $should_fail_over) {
					$this->record_connector_failure($connector_id, $error_code);
				}

				$has_next = isset($connector_ids[$index + 1]);
				if (!$failover_enabled || !$has_next || !$should_fail_over) {
					break;
				}
			}
		}

		throw $last_exception ?: new Exception('no_connector: ' . __('No WordPress AI connector could complete the request.', 'ai-post-scheduler'));
	}

	/**
	 * Whether an error is appropriate to retry against another connector.
	 *
	 * @param string $error_code Canonical provider error code.
	 * @param string $message    Provider error message.
	 * @return bool
	 */
	private function should_fail_over(string $error_code, string $message = ''): bool {
		if ($error_code === 'prompt_invalid_argument') {
			return stripos($message, 'No models found for provider') !== false;
		}

		return !in_array($error_code, self::NON_FAILOVER_CODES, true);
	}

	/**
	 * Read connector health state.
	 *
	 * @param string $connector_id Connector ID.
	 * @return array
	 */
	public function get_connector_health(string $connector_id): array {
		$health = get_transient($this->get_connector_health_key($connector_id));

		return is_array($health) ? $health : array();
	}

	/**
	 * Return the optional AI plugin's approval state for this caller.
	 *
	 * Connector Approval is experimental and not part of WordPress core, so null
	 * means no active approval layer was detected.
	 *
	 * @param string $connector_id Connector ID.
	 * @return bool|null
	 */
	public function get_connector_approval_status(string $connector_id): ?bool {
		$feature_class = 'WordPress\\AI\\Experiments\\Connector_Approval\\Connector_Approval';
		$store_class   = 'WordPress\\AI\\Connector_Approval\\Approvals_Store';

		if (!defined('AIPS_PLUGIN_BASENAME') || !class_exists($feature_class) || !class_exists($store_class)) {
			return null;
		}

		try {
			$feature = new $feature_class();
			if (!method_exists($feature, 'is_enabled') || !$feature->is_enabled()) {
				return null;
			}

			$store = new $store_class();

			return (bool) $store->is_approved(AIPS_PLUGIN_BASENAME, $connector_id);
		} catch (Throwable $e) {
			return null;
		}
	}

	/**
	 * Whether a connector remains inside its failure cooldown.
	 *
	 * @param string $connector_id Connector ID.
	 * @return bool
	 */
	private function is_connector_cooling_down(string $connector_id): bool {
		$health = $this->get_connector_health($connector_id);
		$error  = isset($health['last_error_code']) ? (string) $health['last_error_code'] : '';

		if ($error === 'wpai_connector_not_approved' && $this->get_connector_approval_status($connector_id) === true) {
			return false;
		}

		$ready_to_cool_down = (int) ($health['failures'] ?? 0) >= 2
			|| in_array($error, self::CONNECTOR_CONFIGURATION_FAILURE_CODES, true);

		return $ready_to_cool_down
			&& !empty($health['cooldown_until'])
			&& (int) $health['cooldown_until'] > time();
	}

	/**
	 * Record a successful connector operation.
	 *
	 * @param string $connector_id Connector ID.
	 * @return void
	 */
	private function record_connector_success(string $connector_id): void {
		set_transient(
			$this->get_connector_health_key($connector_id),
			array(
				'state'        => 'healthy',
				'failures'     => 0,
				'last_success' => time(),
			),
			DAY_IN_SECONDS
		);
	}

	/**
	 * Record connector-specific failure state and cooldown.
	 *
	 * @param string $connector_id Connector ID.
	 * @param string $error_code   Canonical error code when available.
	 * @return void
	 */
	private function record_connector_failure(string $connector_id, string $error_code): void {
		$health   = $this->get_connector_health($connector_id);
		$failures = isset($health['failures']) ? (int) $health['failures'] + 1 : 1;
		$cooldown = in_array($error_code, self::CONNECTOR_CONFIGURATION_FAILURE_CODES, true)
			? self::CONNECTOR_CONFIGURATION_COOLDOWN_SECONDS
			: self::CONNECTOR_COOLDOWN_SECONDS;

		set_transient(
			$this->get_connector_health_key($connector_id),
			array(
				'state'          => 'cooling_down',
				'failures'       => $failures,
				'last_failure'   => time(),
				'last_error_code'=> sanitize_key($error_code),
				'cooldown_until' => time() + $cooldown,
			),
			DAY_IN_SECONDS
		);
	}

	/**
	 * Build the private transient key for connector health.
	 *
	 * @param string $connector_id Connector ID.
	 * @return string
	 */
	private function get_connector_health_key(string $connector_id): string {
		return 'aips_wp_ai_health_' . md5($connector_id);
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

        // offsetExists() must be used instead of isset() because isset() returns
        // false for null values even when the key exists, causing the unavailable
        // provider path (stored null) to be re-probed on every call.
        if ($cache->offsetExists($this)) {
            return $cache[$this];
        }

        if (!function_exists('wp_ai_client_prompt')) {
            $cache[$this] = null;
            return null;
        }

        $builder = $this->create_client_builder('');
        $cache[$this] = is_wp_error($builder) ? null : $builder;

        return $cache[$this];
    }

    /**
     * Create an AI Client builder with an AIPS-scoped request timeout.
     *
     * WordPress reads wp_ai_client_default_request_timeout only while the
     * builder is constructed. Registering and removing the filter around that
     * call avoids changing requests made by other plugins.
     *
     * @param string $prompt Prompt text.
     * @return object|WP_Error Prompt builder or client error.
     */
    private function create_client_builder(string $prompt) {
		/**
		 * Filters an optional prebuilt WordPress AI prompt builder.
		 *
		 * Returning null delegates builder creation to wp_ai_client_prompt().
		 *
		 * @param object|WP_Error|null       $builder  Replacement builder.
		 * @param string                     $prompt   Prompt text.
		 * @param AIPS_WP_AI_Client_Provider $provider Current provider adapter.
		 */
		$builder = apply_filters('aips_wp_ai_client_prompt_builder', null, $prompt, $this);

		if ($builder !== null) {
			if (is_object($builder) && method_exists($builder, 'set_prompt')) {
				$builder->set_prompt($prompt);
			}

			return $builder;
		}

        $minimum_timeout = (float) apply_filters(
            'aips_wp_ai_client_request_timeout',
            self::REQUEST_TIMEOUT_SECONDS
        );
        $minimum_timeout = max(0.0, $minimum_timeout);

        $timeout_filter = static function($default_timeout) use ($minimum_timeout) {
            return max((float) $default_timeout, $minimum_timeout);
        };

        add_filter('wp_ai_client_default_request_timeout', $timeout_filter, PHP_INT_MAX);

        try {
            return wp_ai_client_prompt($prompt);
        } finally {
            remove_filter('wp_ai_client_default_request_timeout', $timeout_filter, PHP_INT_MAX);
        }
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
     * @param array       $params       Canonical parameters.
     * @param string|null $connector_id Connector/provider ID, or null for automatic routing.
     * @return object The Prompt_Builder_With_WP_Error instance.
     * @throws Exception When the AI Client rejects the prompt or a configuration step.
     */
    private function build_prompt(string $prompt, array $params, ?string $connector_id = null) {
        $builder = $this->create_client_builder($prompt);

        if (is_wp_error($builder)) {
            $this->throw_from_wp_error($builder);
        }

		if ($connector_id !== null) {
			$builder = $this->chain($builder, 'using_provider', $connector_id);
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
		return $this->execute_with_connector_failover(function($connector_id) use ($prompt, $params) {
			$builder = $this->build_prompt($prompt, $params, $connector_id);
			$result  = $builder->generate_text();

			if (is_wp_error($result)) {
				$this->throw_from_wp_error($result);
			}

			return (string) $result;
		});
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

		return $this->execute_with_connector_failover(function($connector_id) use ($prompt, $params) {
			$builder = $this->build_prompt((string) $prompt, $params, $connector_id);

			// Structural unavailability (a duck-typed builder without a JSON API)
			// requests the service's text-based fallback. Connector/model/network
			// failures are left to generate_text(), which returns a precise WP_Error.
			if (!is_callable([$builder, 'as_json_response'])) {
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
		});
    }

    /**
     * {@inheritDoc}
     */
    public function generate_image(string $prompt, array $params): string {
		return $this->execute_with_connector_failover(function($connector_id) use ($prompt, $params) {
			$builder = $this->build_prompt($prompt, $params, $connector_id);
			$result  = $builder->generate_image();

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
		});
    }

    /**
     * {@inheritDoc}
     */
    public function generate_embedding(string $text, array $params): array {
        // 1. Check if the active connector or builder exposes a native embeddings method
        if ($this->is_available()) {
            $builder = $this->create_prompt_builder($text);
            if (is_object($builder) && is_callable(array($builder, 'generate_embedding'))) {
                $result = $builder->generate_embedding();
                if (is_array($result) && !empty($result)) {
                    return $result;
                }
            }
        }

        // 2. If Meow AI Engine is available on the site, fallback gracefully
        $meow = new AIPS_Meow_AI_Provider();
        if ($meow->is_available() && $meow->supports_embeddings()) {
            return $meow->generate_embedding($text, $params);
        }

        throw new Exception('embeddings_not_supported: ' . __('The active WordPress AI Client connector does not support vector embeddings, and Meow Apps AI Engine is not available.', 'ai-post-scheduler'));
    }

    /**
     * {@inheritDoc}
     */
    public function supports_native_json(): bool {
        $builder = $this->create_prompt_builder();

        return is_object($builder) && is_callable([$builder, 'as_json_response']);
    }

    /**
     * {@inheritDoc}
     */
    public function supports_embeddings(): bool {
        $builder = $this->create_prompt_builder();
        if (is_object($builder) && is_callable(array($builder, 'generate_embedding'))) {
            return true;
        }

        $meow = new AIPS_Meow_AI_Provider();
        return $meow->is_available() && $meow->supports_embeddings();
    }

    /**
     * {@inheritDoc}
     *
     * Requires local connector configuration and the AI Client message DTOs used
     * to build the history payload. Live text capability is checked by the
     * generation call so temporary network failures do not disable the UI.
     */
    public function supports_conversation(): bool {
        return $this->has_message_dtos() && $this->is_available();
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
