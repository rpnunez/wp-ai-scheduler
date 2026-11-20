<?php
/**
 * Campaign Cluster Component
 *
 * Represents the spoke/cluster article ideas and risk assumptions generated
 * alongside a campaign pillar within AIPS_Campaign_Generation_Strategy.
 *
 * @package AI_Post_Scheduler
 * @since   3.7.25
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Campaign_Cluster_Component
 */
class AIPS_Campaign_Cluster_Component implements AIPS_Generation_Component_Interface {

	/**
	 * @var AIPS_AI_Service_Interface|null
	 */
	private $ai_service;

	/**
	 * Constructor.
	 *
	 * @param AIPS_AI_Service_Interface|null $ai_service Optional AI service.
	 */
	public function __construct(?AIPS_AI_Service_Interface $ai_service = null) {
		$this->ai_service = $ai_service;
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_key(): string {
		return 'campaign_cluster';
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_label(): string {
		return __('Campaign Cluster Ideas', 'ai-post-scheduler');
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_strategy_key(): string {
		return 'campaigns';
	}

	/**
	 * {@inheritdoc}
	 */
	public function is_enabled(AIPS_Generation_Context $context): bool {
		return true;
	}

	/**
	 * {@inheritdoc}
	 */
	public function is_required(): bool {
		return false;
	}

	/**
	 * Return the JSON schema properties contributed by this component.
	 *
	 * @return array<string, mixed>
	 */
	public function get_schema_properties(): array {
		return array(
			'sample_article_ideas' => array(
				'type'  => 'array',
				'items' => array('type' => 'string'),
			),
			'risks_assumptions'    => array(
				'type'  => 'array',
				'items' => array('type' => 'string'),
			),
		);
	}

	/**
	 * Return required JSON schema keys owned by this component.
	 *
	 * @return string[]
	 */
	public function get_required_schema_keys(): array {
		return array('sample_article_ideas');
	}

	/**
	 * {@inheritdoc}
	 */
	public function build_prompt(AIPS_Generation_Context $context, ?AIPS_AI_Conversation $session = null): string {
		$niche = (string) $context->get_topic();
		return sprintf(
			'Generate 5 concrete spoke cluster article ideas and key editorial assumptions for a campaign in the "%s" niche. Return a JSON object with "sample_article_ideas" and "risks_assumptions" arrays of strings.',
			$niche !== '' ? $niche : 'General'
		);
	}

	/**
	 * {@inheritdoc}
	 */
	public function generate(AIPS_Generation_Context $context, ?AIPS_AI_Conversation $session = null, array $options = array()) {
		if (isset($options['raw_response']) && is_array($options['raw_response'])) {
			return $this->sanitize_value($options['raw_response']);
		}

		$prompt    = $this->build_prompt($context, $session);
		$directive = isset($options['directive']) ? trim((string) $options['directive']) : '';
		if ($directive !== '') {
			$prompt .= "\n\nAdditional instructions from the user:\n" . $directive;
		}

		$response = $this->get_ai_service()->generate_json($prompt, array(
			'json_schema' => array(
				'type'       => 'object',
				'properties' => $this->get_schema_properties(),
				'required'   => $this->get_required_schema_keys(),
			),
		));
		if (is_wp_error($response)) {
			return $response;
		}

		$value = $this->sanitize_value($response);
		if (empty($value['sample_article_ideas'])) {
			return new WP_Error('empty_cluster_ideas', __('The AI response did not include any cluster article ideas.', 'ai-post-scheduler'));
		}

		return $value;
	}

	/**
	 * {@inheritdoc}
	 */
	public function regenerate($target_id, AIPS_Generation_Context $context, ?AIPS_AI_Conversation $session = null, string $directive = '', array $options = array()) {
		unset($options['raw_response']);
		$options['directive'] = $directive;

		return $this->generate($context, $session, $options);
	}

	/**
	 * Resolve the AI service, falling back to the container.
	 *
	 * @return AIPS_AI_Service_Interface
	 */
	private function get_ai_service() {
		if ($this->ai_service === null) {
			$container        = AIPS_Container::get_instance();
			$this->ai_service = $container->has(AIPS_AI_Service_Interface::class)
				? $container->make(AIPS_AI_Service_Interface::class)
				: new AIPS_AI_Service();
		}

		return $this->ai_service;
	}

	/**
	 * {@inheritdoc}
	 */
	public function generate_variants($target_id, AIPS_Generation_Context $context, int $count = 3, array $options = array()) {
		return array();
	}

	/**
	 * {@inheritdoc}
	 */
	public function apply($target, $value): bool {
		// Campaign drafts are not persisted by the component; a value is applicable only when it has ideas.
		return is_array($value) && !empty($value['sample_article_ideas']);
	}

	/**
	 * {@inheritdoc}
	 */
	public function reconcile_status($target_id): bool {
		return true;
	}

	/**
	 * {@inheritdoc}
	 */
	public function supports_ai_edit(): bool {
		return true;
	}

	/**
	 * {@inheritdoc}
	 */
	public function sanitize_value($value) {
		if (!is_array($value)) {
			return array(
				'sample_article_ideas' => array(),
				'risks_assumptions'    => array(),
			);
		}

		$clean_list = static function ($items): array {
			if (!is_array($items)) {
				return array();
			}
			$out = array();
			foreach ($items as $item) {
				$text = trim(sanitize_text_field((string) $item));
				if ($text !== '') {
					$out[] = $text;
				}
			}
			return array_values(array_unique($out));
		};

		return array(
			'sample_article_ideas' => $clean_list($value['sample_article_ideas'] ?? array()),
			'risks_assumptions'    => $clean_list($value['risks_assumptions'] ?? array()),
		);
	}
}
