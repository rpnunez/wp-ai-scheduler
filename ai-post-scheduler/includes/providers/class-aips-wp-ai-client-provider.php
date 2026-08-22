<?php
/**
 * WordPress AI Client Provider
 *
 * Adapts the native WordPress core AI Client (wp_ai_client_prompt(), introduced
 * with the WordPress 7.0 Connectors API) to AIPS_AI_Provider_Interface.
 * Credentials and model selection are handled by core. AIPS applies the site
 * administrator's connector allowlist and failover policy around those calls.
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
	 */
	public const REQUEST_TIMEOUT_SECONDS = AIPS_WP_AI_Prompt_Adapter::REQUEST_TIMEOUT_SECONDS;

	/** Default connector cooldown after a provider-specific failure. */
	public const CONNECTOR_COOLDOWN_SECONDS = AIPS_AI_Failover_Policy::DEFAULT_COOLDOWN_SECONDS;

	/** Longer cooldown for credential, billing, and quota failures. */
	public const CONNECTOR_CONFIGURATION_COOLDOWN_SECONDS = AIPS_AI_Failover_Policy::CONFIGURATION_COOLDOWN_SECONDS;

	/** Connector failures that should cool down immediately. */
	public const CONNECTOR_CONFIGURATION_FAILURE_CODES = AIPS_AI_Failover_Policy::CONFIGURATION_FAILURE_CODES;

	/** Connector failure codes caused by the request rather than the connector. */
	public const NON_FAILOVER_CODES = AIPS_AI_Failover_Policy::NON_FAILOVER_CODES;

	/**
	 * @var AIPS_AI_Connector_Registry
	 */
	private $registry;

	/**
	 * @var AIPS_AI_Connector_Router
	 */
	private $router;

	/**
	 * @var AIPS_AI_Connector_Health_Store
	 */
	private $health_store;

	/**
	 * @var AIPS_AI_Failover_Policy
	 */
	private $failover_policy;

	/**
	 * @var AIPS_WP_AI_Prompt_Adapter
	 */
	private $prompt_adapter;

	/**
	 * @var AIPS_WP_AI_Error_Mapper
	 */
	private $error_mapper;

	/**
	 * @var AIPS_Config
	 */
	private $config;

	/**
	 * Constructor.
	 *
	 * Supports optional dependency injection for testing and container resolution.
	 *
	 * @param AIPS_AI_Connector_Registry|null     $registry        Optional registry.
	 * @param AIPS_AI_Connector_Router|null       $router          Optional router.
	 * @param AIPS_AI_Connector_Health_Store|null $health_store    Optional health store.
	 * @param AIPS_AI_Failover_Policy|null        $failover_policy Optional failover policy.
	 * @param AIPS_WP_AI_Prompt_Adapter|null      $prompt_adapter  Optional prompt adapter.
	 * @param AIPS_WP_AI_Error_Mapper|null        $error_mapper    Optional error mapper.
	 * @param AIPS_Config|null                    $config          Optional config.
	 */
	public function __construct(
		?AIPS_AI_Connector_Registry $registry = null,
		?AIPS_AI_Connector_Router $router = null,
		?AIPS_AI_Connector_Health_Store $health_store = null,
		?AIPS_AI_Failover_Policy $failover_policy = null,
		?AIPS_WP_AI_Prompt_Adapter $prompt_adapter = null,
		?AIPS_WP_AI_Error_Mapper $error_mapper = null,
		?AIPS_Config $config = null
	) {
		$this->config          = $config ?? AIPS_Config::get_instance();
		$this->registry        = $registry ?? new AIPS_AI_Connector_Registry($this->config);
		$this->health_store    = $health_store ?? new AIPS_AI_Connector_Health_Store($this->registry);
		$this->failover_policy = $failover_policy ?? new AIPS_AI_Failover_Policy();
		$this->router          = $router ?? new AIPS_AI_Connector_Router($this->registry, $this->health_store, $this->config);
		$this->error_mapper    = $error_mapper ?? new AIPS_WP_AI_Error_Mapper();
		$this->prompt_adapter  = $prompt_adapter ?? new AIPS_WP_AI_Prompt_Adapter($this->error_mapper);
	}

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
		return $this->registry->has_configured_connector();
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

		$connectors = $this->registry->get_active_ai_connectors();

		if (empty($connectors)) {
			return __('WordPress AI Client has no active AI connector registered.', 'ai-post-scheduler');
		}

		if ((string) $this->config->get_option('aips_wp_ai_connector_mode') === 'selected'
			&& empty((array) $this->config->get_option('aips_wp_ai_connector_ids'))) {
			return __('WordPress AI Client is limited to selected connectors, but none are selected.', 'ai-post-scheduler');
		}

		if (!$this->registry->has_configured_connector($connectors)) {
			return __('WordPress AI Client has no allowed connector with configured credentials.', 'ai-post-scheduler');
		}

		return '';
	}

	/**
	 * Return active connectors registered as AI providers.
	 *
	 * @return array<string,array<string,mixed>> Active AI connectors keyed by ID.
	 */
	public function get_active_ai_connectors(): array {
		return $this->registry->get_active_ai_connectors();
	}

	/**
	 * Whether a connector has locally configured authentication.
	 *
	 * @param array $connector Connector registry entry.
	 * @return bool
	 */
	public function is_connector_configured(array $connector): bool {
		return $this->registry->is_connector_configured($connector);
	}

	/**
	 * Read connector health state.
	 *
	 * @param string $connector_id Connector ID.
	 * @return array
	 */
	public function get_connector_health(string $connector_id): array {
		return $this->health_store->get_health($connector_id);
	}

	/**
	 * Return the optional AI plugin's approval state for this caller.
	 *
	 * @param string $connector_id Connector ID.
	 * @return bool|null
	 */
	public function get_connector_approval_status(string $connector_id): ?bool {
		return $this->registry->get_connector_approval_status($connector_id);
	}

	/**
	 * Check whether the builder can perform text generation.
	 *
	 * @param object|null $builder Optional existing builder to avoid probing twice.
	 * @return bool True when the AI Client reports text generation support.
	 */
	public function supports_text_generation($builder = null): bool {
		return $this->prompt_adapter->supports_text_generation($builder, $this);
	}

	/**
	 * Check whether the builder can perform image generation.
	 *
	 * @param object|null $builder Optional existing builder to avoid probing twice.
	 * @return bool True when the AI Client reports image generation support.
	 */
	public function supports_image_generation($builder = null): bool {
		return $this->prompt_adapter->supports_image_generation($builder, $this);
	}

	/**
	 * {@inheritDoc}
	 */
	public function supports_native_json(): bool {
		return $this->prompt_adapter->supports_native_json($this);
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
	public function supports_conversation(): bool {
		return $this->prompt_adapter->has_message_dtos() && $this->is_available();
	}

	/**
	 * {@inheritDoc}
	 */
	public function extract_error_code(string $message): string {
		return $this->error_mapper->extract_error_code($message);
	}

	/**
	 * Execute an operation against allowed connectors with sequential failover.
	 *
	 * @param callable $operation Receives a connector ID or null for legacy auto-routing.
	 * @return mixed
	 * @throws Exception When no connector can complete the operation.
	 */
	private function execute_with_connector_failover(callable $operation) {
		$connector_ids = $this->router->get_routing_connector_ids();

		if (empty($connector_ids)) {
			throw new Exception('no_connector: ' . __('No allowed WordPress AI connector is currently available.', 'ai-post-scheduler'));
		}

		$failover_enabled = (bool) $this->config->get_option('aips_wp_ai_connector_failover');
		$last_exception   = null;

		foreach ($connector_ids as $index => $connector_id) {
			try {
				$result = $operation($connector_id);

				if ($connector_id !== null) {
					$this->health_store->record_success($connector_id);
				}

				return $result;
			} catch (Throwable $e) {
				$last_exception   = $this->error_mapper->to_exception($e);
				$error_code       = $this->error_mapper->extract_error_code($e->getMessage());
				$should_fail_over = $this->failover_policy->should_fail_over($error_code, $e->getMessage());

				if ($connector_id !== null && $should_fail_over) {
					$cooldown = $this->failover_policy->get_cooldown_seconds($error_code);
					$this->health_store->record_failure($connector_id, $error_code, $cooldown);
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
	 * {@inheritDoc}
	 */
	public function generate_text(string $prompt, array $params): string {
		return $this->execute_with_connector_failover(function($connector_id) use ($prompt, $params) {
			$builder = $this->prompt_adapter->build_prompt($prompt, $params, $connector_id, $this);
			$result  = $builder->generate_text();

			if (is_wp_error($result)) {
				$this->error_mapper->throw_from_wp_error($result);
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
			$builder = $this->prompt_adapter->build_prompt((string) $prompt, $params, $connector_id, $this);

			// Structural unavailability (a duck-typed builder without a JSON API)
			// requests the service's text-based fallback. Connector/model/network
			// failures are left to generate_text(), which returns a precise WP_Error.
			if (!is_callable(array($builder, 'as_json_response'))) {
				return null;
			}

			// A real connector error mid-chain must throw (reaching the resilience
			// layer), not silently trigger the fallback.
			$builder = $this->prompt_adapter->chain($builder, 'as_json_response', $params['json_schema']);
			$result  = $builder->generate_text();

			if (is_wp_error($result)) {
				$this->error_mapper->throw_from_wp_error($result);
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
			$builder = $this->prompt_adapter->build_prompt($prompt, $params, $connector_id, $this);
			$result  = $builder->generate_image();

			if (is_wp_error($result)) {
				$this->error_mapper->throw_from_wp_error($result);
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
		// The WordPress AI Client does not expose a stable embeddings API yet.
		throw new Exception('embeddings_not_supported: ' . __('Embeddings are not supported by the WordPress AI Client.', 'ai-post-scheduler'));
	}
}
