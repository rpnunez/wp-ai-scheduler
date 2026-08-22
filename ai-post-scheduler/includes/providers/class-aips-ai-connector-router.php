<?php
/**
 * AI Connector Router
 *
 * Produces ordered connector candidate lists according to site settings,
 * configuration state, health cooldowns, and failover preferences.
 *
 * @package AI_Post_Scheduler
 * @since 3.2.0
 */

if (!defined('ABSPATH')) {
	exit;
}

class AIPS_AI_Connector_Router {

	/**
	 * @var AIPS_AI_Connector_Registry
	 */
	private $registry;

	/**
	 * @var AIPS_AI_Connector_Health_Store
	 */
	private $health_store;

	/**
	 * @var AIPS_Config
	 */
	private $config;

	/**
	 * Constructor.
	 *
	 * @param AIPS_AI_Connector_Registry|null     $registry     Optional connector registry.
	 * @param AIPS_AI_Connector_Health_Store|null $health_store Optional health store.
	 * @param AIPS_Config|null                    $config       Optional config service.
	 */
	public function __construct(
		?AIPS_AI_Connector_Registry $registry = null,
		?AIPS_AI_Connector_Health_Store $health_store = null,
		?AIPS_Config $config = null
	) {
		$this->registry     = $registry ?? new AIPS_AI_Connector_Registry($config);
		$this->health_store = $health_store ?? new AIPS_AI_Connector_Health_Store($this->registry);
		$this->config       = $config ?? AIPS_Config::get_instance();
	}

	/**
	 * Resolve configured connectors in the order they should be attempted.
	 *
	 * A null entry preserves compatibility with AI Client implementations that
	 * predate the Connectors registry while still supporting automatic routing.
	 *
	 * @return array<int,string|null>
	 */
	public function get_routing_connector_ids(): array {
		$connectors = $this->registry->apply_connector_selection($this->registry->get_active_ai_connectors());
		$configured = array();

		foreach ($connectors as $connector_id => $connector) {
			if (!is_array($connector) || !$this->registry->is_connector_configured($connector)) {
				continue;
			}

			$configured[] = (string) $connector_id;
		}

		if (!empty($configured)) {
			$failover_enabled = (bool) $this->config->get_option('aips_wp_ai_connector_failover');

			if (!$failover_enabled) {
				return $this->health_store->is_cooling_down($configured[0]) ? array() : array($configured[0]);
			}

			return array_values(array_filter($configured, function($connector_id) {
				return !$this->health_store->is_cooling_down($connector_id);
			}));
		}

		if ((string) $this->config->get_option('aips_wp_ai_connector_mode') !== 'selected' && empty($connectors)) {
			return array(null);
		}

		return array();
	}
}
