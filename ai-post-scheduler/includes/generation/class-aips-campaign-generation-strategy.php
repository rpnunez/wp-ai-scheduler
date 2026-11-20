<?php
/**
 * Campaign Generation Strategy
 *
 * Implements AIPS_Pipeline_Executable_Strategy_Interface for AI Campaign
 * wizard generation, assembling structured JSON schemas from
 * AIPS_Campaign_Pillar_Component and AIPS_Campaign_Cluster_Component.
 *
 * @package AI_Post_Scheduler
 * @since   3.7.25
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Campaign_Generation_Strategy
 */
class AIPS_Campaign_Generation_Strategy implements AIPS_Pipeline_Executable_Strategy_Interface {

	/** Pipeline stage constants for Campaign Generation. */
	const STAGE_INTAKE             = 'intake';
	const STAGE_STRATEGY_PLANNING  = 'strategy_planning';
	const STAGE_PILLAR_SPOKE_SILOS = 'pillar_spoke_silos';
	const STAGE_PERSISTENCE        = 'persistence';

	/**
	 * @var AIPS_Generation_Component_Registry
	 */
	private $registry;

	/**
	 * @var AIPS_AI_Service_Interface
	 */
	private $ai_service;

	/**
	 * @var AIPS_Logger_Interface
	 */
	private $logger;

	/**
	 * @var AIPS_History_Service_Interface
	 */
	private $history_service;

	/**
	 * @var WP_Error|null
	 */
	private $last_error = null;

	/**
	 * Constructor.
	 *
	 * @param AIPS_Generation_Component_Registry|null $registry        Optional component registry.
	 * @param AIPS_AI_Service_Interface|null          $ai_service      Optional AI service.
	 * @param AIPS_Logger_Interface|null              $logger          Optional logger.
	 * @param AIPS_History_Service_Interface|null     $history_service Optional history service.
	 */
	public function __construct(
		?AIPS_Generation_Component_Registry $registry = null,
		?AIPS_AI_Service_Interface $ai_service = null,
		?AIPS_Logger_Interface $logger = null,
		?AIPS_History_Service_Interface $history_service = null
	) {
		$container             = AIPS_Container::get_instance();
		$this->ai_service      = $ai_service ?: ($container->has(AIPS_AI_Service_Interface::class) ? $container->make(AIPS_AI_Service_Interface::class) : new AIPS_AI_Service());
		$this->logger          = $logger ?: ($container->has(AIPS_Logger_Interface::class) ? $container->make(AIPS_Logger_Interface::class) : new AIPS_Logger());
		$this->history_service = $history_service ?: ($container->has(AIPS_History_Service_Interface::class) ? $container->make(AIPS_History_Service_Interface::class) : new AIPS_History_Service());

		if ($registry !== null) {
			$this->registry = $registry;
		} else {
			$this->registry = new AIPS_Generation_Component_Registry();
			$this->registry->register_default_campaign_components($this->ai_service);
		}
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_key(): string {
		return 'campaigns';
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_label(): string {
		return __('Campaigns', 'ai-post-scheduler');
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_stages(): array {
		return array(
			self::STAGE_INTAKE,
			self::STAGE_STRATEGY_PLANNING,
			self::STAGE_PILLAR_SPOKE_SILOS,
			self::STAGE_PERSISTENCE,
		);
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_components(AIPS_Generation_Context $context): array {
		$ordered = array();
		foreach ($this->registry->get_by_strategy($this->get_key()) as $component) {
			if ($component->is_enabled($context)) {
				$ordered[] = $component;
			}
		}

		return $ordered;
	}

	/**
	 * {@inheritdoc}
	 */
	public function before_pipeline(AIPS_Generation_Context $context): void {
		$this->last_error = null;
	}

	/**
	 * {@inheritdoc}
	 */
	public function after_pipeline(AIPS_Generation_Context $context, AIPS_Generation_Result $result): AIPS_Generation_Result {
		return $result;
	}

	/**
	 * Return the last WP_Error encountered during a failed run.
	 *
	 * @return WP_Error|null
	 */
	public function get_last_error(): ?WP_Error {
		return $this->last_error;
	}

	/**
	 * Build the combined campaign JSON schema from registered pillar & cluster components.
	 *
	 * @return array<string, mixed>
	 */
	public function get_combined_json_schema(): array {
		$pillar  = $this->registry->get('campaign_pillar', $this->get_key());
		$cluster = $this->registry->get('campaign_cluster', $this->get_key());

		$properties = array();
		$required   = array();

		if ($pillar instanceof AIPS_Campaign_Pillar_Component) {
			$properties = array_merge($properties, $pillar->get_schema_properties());
			$required   = array_merge($required, $pillar->get_required_schema_keys());
		}

		if ($cluster instanceof AIPS_Campaign_Cluster_Component) {
			$properties = array_merge($properties, $cluster->get_schema_properties());
			$required   = array_merge($required, $cluster->get_required_schema_keys());
		}

		return array(
			'type'       => 'object',
			'properties' => $properties,
			'required'   => array_values(array_unique($required)),
		);
	}

	/**
	 * {@inheritdoc}
	 */
	public function execute_run(AIPS_Generation_Context $context): AIPS_Generation_Result {
		$start            = microtime(true);
		$this->last_error = null;

		if (!$this->ai_service->is_available()) {
			$error            = new WP_Error('ai_unavailable', __('AI Engine is not available.', 'ai-post-scheduler'));
			$this->last_error = $error;
			return AIPS_Generation_Result::from_wp_error($error, microtime(true) - $start);
		}

		$pillar = $this->registry->get('campaign_pillar', $this->get_key());
		if (!($pillar instanceof AIPS_Campaign_Pillar_Component)) {
			$pillar = new AIPS_Campaign_Pillar_Component($this->ai_service);
		}

		$cluster = $this->registry->get('campaign_cluster', $this->get_key());
		if (!($cluster instanceof AIPS_Campaign_Cluster_Component)) {
			$cluster = new AIPS_Campaign_Cluster_Component();
		}

		$history    = $this->history_service->create('campaign_generation', array(
			'niche' => (string) $context->get_topic(),
		));
		$history_id = ($history && $history->get_id()) ? (int) $history->get_id() : 0;

		$prompt = $pillar->build_prompt($context);
		$schema = $this->get_combined_json_schema();

		if ($history) {
			$history->record('ai_request', 'Campaign strategy prompt sent to AI', array('prompt' => $prompt));
		}

		$response = $this->ai_service->generate_json($prompt, array(
			'json_schema' => $schema,
		));

		if (is_wp_error($response)) {
			$this->last_error = $response;
			if ($history) {
				$history->record_error('AI call failed during campaign generation', array(), $response);
				$history->complete_failure($response->get_error_message());
			}

			return AIPS_Generation_Result::from_wp_error($response, microtime(true) - $start, $history_id);
		}

		if (!is_array($response)) {
			$error            = new WP_Error('invalid_ai_response', __('AI response was not valid JSON.', 'ai-post-scheduler'));
			$this->last_error = $error;
			if ($history) {
				$history->record_error('AI response was not valid JSON');
				$history->complete_failure('Invalid AI response');
			}

			return AIPS_Generation_Result::from_wp_error($error, microtime(true) - $start, $history_id);
		}

		if ($history) {
			$history->record('ai_response', 'AI response received for campaign generation', null, $response);
		}

		$cluster_data       = $cluster->sanitize_value($response);
		$has_cluster_ideas  = !empty($cluster_data['sample_article_ideas']);
		$component_statuses = array(
			'campaign_pillar'  => true,
			'campaign_cluster' => $has_cluster_ideas,
		);
		$warnings = array();
		if (!$has_cluster_ideas) {
			$warnings[] = __('Campaign response did not include spoke/cluster article ideas; fallback ideas will be used.', 'ai-post-scheduler');
		}

		$is_partial = !$has_cluster_ideas;
		$elapsed    = microtime(true) - $start;

		if ($history) {
			if ($is_partial) {
				$history->complete_partial(implode(' ', $warnings), array('component_statuses' => $component_statuses));
			} else {
				$history->complete_success(array('component_statuses' => $component_statuses));
			}
		}

		return AIPS_Generation_Result::from_data(
			$response,
			$is_partial ? AIPS_Generation_Result::STATUS_PARTIAL : AIPS_Generation_Result::STATUS_COMPLETED,
			$component_statuses,
			$warnings,
			$history_id,
			$elapsed
		);
	}

	/**
	 * {@inheritdoc}
	 */
	public function execute_preview_run(AIPS_Generation_Context $context) {
		$result = $this->execute_run($context);
		if ($result->is_failure()) {
			return $this->last_error ?: new WP_Error('ai_generation_failed', __('Campaign generation failed.', 'ai-post-scheduler'));
		}

		return $result->get_data();
	}
}
