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
			'Generate 5 concrete spoke cluster article ideas and key editorial assumptions for a campaign in the "%s" niche.',
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

		return array(
			'sample_article_ideas' => array(),
			'risks_assumptions'    => array(),
		);
	}

	/**
	 * {@inheritdoc}
	 */
	public function regenerate($target_id, AIPS_Generation_Context $context, ?AIPS_AI_Conversation $session = null, string $directive = '', array $options = array()) {
		return $this->generate($context, $session, $options);
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
		return is_array($value);
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
