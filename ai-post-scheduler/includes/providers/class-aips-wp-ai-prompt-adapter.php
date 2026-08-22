<?php
/**
 * WordPress AI Prompt Adapter
 *
 * Builds, configures, and probes the WordPress core AI Client prompt and
 * client builders, mapping canonical parameters into SDK calls and DTOs.
 *
 * @package AI_Post_Scheduler
 * @since 3.2.0
 */

if (!defined('ABSPATH')) {
	exit;
}

class AIPS_WP_AI_Prompt_Adapter {

	/**
	 * Minimum timeout for AI Client requests created by this adapter.
	 *
	 * Full article and image generations regularly exceed the core client's
	 * 30-second default even when the connector remains healthy.
	 */
	public const REQUEST_TIMEOUT_SECONDS = 90.0;

	/**
	 * Fully-qualified names of the AI Client message DTOs.
	 *
	 * These are raw SDK classes shipped with WordPress core's AI Client — unlike
	 * the prompt builder they are not wrapped, so every use must be guarded to
	 * keep the plugin loadable when the AI Client is absent.
	 */
	public const DTO_USER_MESSAGE  = 'WordPress\\AiClient\\Messages\\DTO\\UserMessage';
	public const DTO_MODEL_MESSAGE = 'WordPress\\AiClient\\Messages\\DTO\\ModelMessage';
	public const DTO_MESSAGE_PART  = 'WordPress\\AiClient\\Messages\\DTO\\MessagePart';

	/**
	 * @var AIPS_WP_AI_Error_Mapper
	 */
	private $error_mapper;

	/**
	 * Constructor.
	 *
	 * @param AIPS_WP_AI_Error_Mapper|null $error_mapper Optional error mapper.
	 */
	public function __construct(?AIPS_WP_AI_Error_Mapper $error_mapper = null) {
		$this->error_mapper = $error_mapper ?? new AIPS_WP_AI_Error_Mapper();
	}

	/**
	 * Create an AI Client builder with an AIPS-scoped request timeout.
	 *
	 * WordPress reads wp_ai_client_default_request_timeout only while the
	 * builder is constructed. Registering and removing the filter around that
	 * call avoids changing requests made by other plugins.
	 *
	 * @param string      $prompt          Prompt text.
	 * @param object|null $caller_provider Optional provider instance for filter backward-compat.
	 * @return object|WP_Error Prompt builder or client error.
	 */
	public function create_client_builder(string $prompt, ?object $caller_provider = null) {
		/**
		 * Filters an optional prebuilt WordPress AI prompt builder.
		 *
		 * Returning null delegates builder creation to wp_ai_client_prompt().
		 *
		 * @param object|WP_Error|null $builder         Replacement builder.
		 * @param string               $prompt          Prompt text.
		 * @param object               $caller_provider Current provider adapter.
		 */
		$builder = apply_filters('aips_wp_ai_client_prompt_builder', null, $prompt, $caller_provider ?? $this);

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
	 * Create a probe builder for capability checks (always uses an empty prompt).
	 *
	 * Uses WeakMap so that entries are automatically removed when the key
	 * instance is GC'd, preventing stale-cache hits when PHP recycles object identities.
	 *
	 * @param object|null $cache_key Optional key object for the WeakMap cache.
	 * @return object|null Prompt builder, or null when unavailable/errored.
	 */
	public function create_prompt_builder(?object $cache_key = null) {
		static $cache = null;

		if ($cache === null) {
			$cache = new WeakMap();
		}

		$key = $cache_key ?? $this;

		// offsetExists() must be used instead of isset() because isset() returns
		// false for null values even when the key exists, causing the unavailable
		// provider path (stored null) to be re-probed on every call.
		if ($cache->offsetExists($key)) {
			return $cache[$key];
		}

		if (!function_exists('wp_ai_client_prompt')) {
			$cache[$key] = null;
			return null;
		}

		$builder = $this->create_client_builder('', $key);
		$cache[$key] = is_wp_error($builder) ? null : $builder;

		return $cache[$key];
	}

	/**
	 * Check whether the builder can perform text generation.
	 *
	 * @param object|null $builder   Optional existing builder to avoid probing twice.
	 * @param object|null $cache_key Optional key object for probe caching.
	 * @return bool True when the AI Client reports text generation support.
	 */
	public function supports_text_generation($builder = null, ?object $cache_key = null): bool {
		if ($builder === null) {
			$builder = $this->create_prompt_builder($cache_key);
		}

		if (!is_object($builder)) {
			return false;
		}

		return (bool) $builder->is_supported_for_text_generation();
	}

	/**
	 * Check whether the builder can perform image generation.
	 *
	 * @param object|null $builder   Optional existing builder to avoid probing twice.
	 * @param object|null $cache_key Optional key object for probe caching.
	 * @return bool True when the AI Client reports image generation support.
	 */
	public function supports_image_generation($builder = null, ?object $cache_key = null): bool {
		if ($builder === null) {
			$builder = $this->create_prompt_builder($cache_key);
		}

		if (!is_object($builder)) {
			return false;
		}

		return (bool) $builder->is_supported_for_image_generation();
	}

	/**
	 * Whether native JSON response configuration is supported.
	 *
	 * @param object|null $cache_key Optional key object for probe caching.
	 * @return bool
	 */
	public function supports_native_json(?object $cache_key = null): bool {
		$builder = $this->create_prompt_builder($cache_key);

		return is_object($builder) && is_callable(array($builder, 'as_json_response'));
	}

	/**
	 * Whether the AI Client message DTOs needed for history are present.
	 *
	 * @return bool
	 */
	public function has_message_dtos(): bool {
		return class_exists(self::DTO_USER_MESSAGE)
			&& class_exists(self::DTO_MODEL_MESSAGE)
			&& class_exists(self::DTO_MESSAGE_PART);
	}

	/**
	 * Call a fluent builder method, guarding against WP_Error returns.
	 *
	 * @param object $builder Current builder instance.
	 * @param string $method  Builder method to invoke.
	 * @param mixed  ...$args Arguments for the method.
	 * @return object The (possibly new) builder instance.
	 * @throws Exception When the builder method returns a WP_Error.
	 */
	public function chain($builder, string $method, ...$args) {
		if (!is_callable(array($builder, $method))) {
			return $builder;
		}

		$result = $builder->$method(...$args);

		if (is_wp_error($result)) {
			$this->error_mapper->throw_from_wp_error($result);
		}

		return $result;
	}

	/**
	 * Build a configured prompt builder from canonical parameters.
	 *
	 * @param string      $prompt          Prompt text.
	 * @param array       $params          Canonical parameters.
	 * @param string|null $connector_id    Connector/provider ID, or null for automatic routing.
	 * @param object|null $caller_provider Optional provider instance for filter backward-compat.
	 * @return object The Prompt_Builder_With_WP_Error instance.
	 * @throws Exception When the AI Client rejects the prompt or a configuration step.
	 */
	public function build_prompt(string $prompt, array $params, ?string $connector_id = null, ?object $caller_provider = null) {
		$builder = $this->create_client_builder($prompt, $caller_provider);

		if (is_wp_error($builder)) {
			$this->error_mapper->throw_from_wp_error($builder);
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
	 * Convert canonical conversation turns into AI Client Message objects.
	 *
	 * @param array $turns Canonical turns from AIPS_AI_Conversation.
	 * @return array List of Message objects; empty when the DTOs are unavailable.
	 * @throws Exception When a turn cannot be represented as a Message.
	 */
	public function to_history_messages(array $turns): array {
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
	 * @param array $params Canonical parameters.
	 * @return string System instruction text, or '' when neither key is set.
	 */
	public function build_system_instruction(array $params): string {
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
}
