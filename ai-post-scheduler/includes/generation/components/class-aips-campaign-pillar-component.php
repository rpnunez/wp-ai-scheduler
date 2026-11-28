<?php
/**
 * Campaign Pillar Component
 *
 * Represents the core campaign strategy & pillar configuration fields
 * (campaign_name, content_goal, prompt_template, title_prompt, mode, review
 * policy, schedule, and taxonomy defaults) within AIPS_Campaign_Generation_Strategy.
 *
 * @package AI_Post_Scheduler
 * @since   3.7.25
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Campaign_Pillar_Component
 */
class AIPS_Campaign_Pillar_Component implements AIPS_Generation_Component_Interface {

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
		return 'campaign_pillar';
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_label(): string {
		return __('Campaign Pillar Strategy', 'ai-post-scheduler');
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
		return true;
	}

	/**
	 * Return the JSON schema properties contributed by this component.
	 *
	 * @return array<string, mixed>
	 */
	public function get_schema_properties(): array {
		return array(
			'campaign_name'     => array('type' => 'string'),
			'content_goal'      => array('type' => 'string'),
			'post_type'         => array('type' => 'string'),
			'prompt_template'   => array('type' => 'string'),
			'title_prompt'      => array('type' => 'string'),
			'author_persona'    => array('type' => 'string'),
			'campaign_mode'     => array('type' => 'string', 'enum' => array('template', 'author')),
			'review_policy'     => array('type' => 'string', 'enum' => array('draft', 'approval', 'auto_publish')),
			'frequency'         => array('type' => 'string'),
			'time_window_start' => array('type' => 'string'),
			'time_window_end'   => array('type' => 'string'),
			'post_tags'         => array('type' => 'string'),
			'post_category'     => array('type' => 'integer'),
			'template_style'    => array('type' => 'string'),
		);
	}

	/**
	 * Return required JSON schema keys owned by this component.
	 *
	 * @return string[]
	 */
	public function get_required_schema_keys(): array {
		return array(
			'campaign_name',
			'content_goal',
			'post_type',
			'prompt_template',
			'title_prompt',
			'campaign_mode',
			'review_policy',
		);
	}

	/**
	 * {@inheritdoc}
	 */
	public function build_prompt(AIPS_Generation_Context $context, ?AIPS_AI_Conversation $session = null): string {
		$prompt_context = array(
			'intake'                  => ($context instanceof AIPS_Campaign_Generation_Context) ? $context->get_intake() : array(),
			'available_frequencies'   => ($context instanceof AIPS_Campaign_Generation_Context) ? $context->get_available_frequencies() : array(),
			'available_post_types'    => ($context instanceof AIPS_Campaign_Generation_Context) ? $context->get_available_post_types() : array('post'),
			'available_output_styles' => ($context instanceof AIPS_Campaign_Generation_Context) ? $context->get_available_output_styles() : array(),
			'review_policy_allowed'   => array('draft', 'approval', 'auto_publish'),
			'campaign_mode_allowed'   => array('template', 'author'),
		);

		return
			"You are helping a WordPress user configure an AI content campaign.\n" .
			"Return a single JSON object with realistic, user-friendly values suitable for immediate editing and publishing.\n\n" .
			"Important constraints:\n" .
			"- prompt_template: use {{topic}} (lowercase, double curly braces) as the sole placeholder for the article subject — never [TOPIC] or any other format.\n" .
			"- title_prompt: must instruct the AI to generate exactly 1 title; do NOT use {{topic}} here (it maps to the final title in this system); make it self-contained.\n" .
			"- campaign_mode: must be one of template|author\n" .
			"- review_policy: must be one of draft|approval|auto_publish\n" .
			"- post_category: use 0 when unknown\n\n" .
			"Context:\n" . wp_json_encode($prompt_context);
	}

	/**
	 * {@inheritdoc}
	 */
	public function generate(AIPS_Generation_Context $context, ?AIPS_AI_Conversation $session = null, array $options = array()) {
		if (isset($options['raw_response']) && is_array($options['raw_response'])) {
			return $this->sanitize_value($options['raw_response']);
		}

		$ai_service = $this->ai_service;
		if ($ai_service === null) {
			$container  = AIPS_Container::get_instance();
			$ai_service = $container->has(AIPS_AI_Service_Interface::class)
				? $container->make(AIPS_AI_Service_Interface::class)
				: new AIPS_AI_Service();
		}

		$prompt    = $this->build_prompt($context, $session);
		$directive = isset($options['directive']) ? trim((string) $options['directive']) : '';
		if ($directive !== '') {
			$prompt .= "\n\nAdditional instructions from the user:\n" . $directive;
		}
		$schema = isset($options['json_schema']) && is_array($options['json_schema'])
			? $options['json_schema']
			: array(
				'type'       => 'object',
				'properties' => $this->get_schema_properties(),
				'required'   => $this->get_required_schema_keys(),
			);

		$response = $ai_service->generate_json($prompt, array('json_schema' => $schema));
		if (is_wp_error($response)) {
			return $response;
		}

		return $this->sanitize_value($response);
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
	 * {@inheritdoc}
	 */
	public function generate_variants($target_id, AIPS_Generation_Context $context, int $count = 3, array $options = array()) {
		return array();
	}

	/**
	 * {@inheritdoc}
	 */
	public function apply($target, $value): bool {
		return is_array($value) && !empty($value);
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
			return array();
		}

		$clean = array();
		foreach (array_keys($this->get_schema_properties()) as $key) {
			if (!array_key_exists($key, $value)) {
				continue;
			}
			if ($key === 'post_category') {
				$clean[$key] = absint($value[$key]);
			} elseif ($key === 'prompt_template') {
				$clean[$key] = wp_kses_post((string) $value[$key]);
			} elseif ($key === 'content_goal') {
				$clean[$key] = sanitize_textarea_field((string) $value[$key]);
			} elseif (in_array($key, array('post_type', 'campaign_mode', 'review_policy', 'frequency'), true)) {
				$clean[$key] = sanitize_key((string) $value[$key]);
			} else {
				$clean[$key] = sanitize_text_field((string) $value[$key]);
			}
		}

		return $clean;
	}
}
