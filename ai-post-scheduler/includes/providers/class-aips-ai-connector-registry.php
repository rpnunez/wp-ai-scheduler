<?php
/**
 * AI Connector Registry
 *
 * Discovers and inspects active AI connectors registered with WordPress core,
 * checking local authentication presence and connector approval state.
 *
 * @package AI_Post_Scheduler
 * @since 3.2.0
 */

if (!defined('ABSPATH')) {
	exit;
}

class AIPS_AI_Connector_Registry {

	/**
	 * @var AIPS_Config Configuration provider.
	 */
	private $config;

	/**
	 * Constructor.
	 *
	 * @param AIPS_Config|null $config Optional config service.
	 */
	public function __construct(?AIPS_Config $config = null) {
		$this->config = $config ?? AIPS_Config::get_instance();
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
	public function has_configured_connector(?array $connectors = null): bool {
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
	public function apply_connector_selection(array $connectors): array {
		$mode = (string) $this->config->get_option('aips_wp_ai_connector_mode');

		if ($mode !== 'selected') {
			return $connectors;
		}

		$selected = (array) $this->config->get_option('aips_wp_ai_connector_ids');
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
}
