<?php
/**
 * Field Suggestion Component
 *
 * Encapsulates prompt construction, AI suggestion generation, and repository
 * persistence for form-field AI Assistance within AIPS_AI_Assistance_Strategy.
 *
 * @package AI_Post_Scheduler
 * @since   3.7.25
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Field_Suggestion_Component
 */
class AIPS_Field_Suggestion_Component implements AIPS_Generation_Component_Interface {

	/**
	 * @var AIPS_AI_Service_Interface|null
	 */
	private $ai_service;

	/**
	 * @var AIPS_AI_Assistance_Repository|null
	 */
	private $repository;

	/**
	 * Prompt preamble describing the kind of form being filled in, keyed by
	 * form_context. Falls back to a generic description for an unrecognised
	 * context so a new form never gets Author-specific wording by default.
	 *
	 * @var array<string, string>
	 */
	private $preambles = array(
		'authors'          => 'an AI author persona',
		'templates'        => 'an AI content-generation template',
		'sources'          => 'a tracked external content source',
		'content_strategy' => 'a site-wide AI content strategy',
	);

	/**
	 * Constructor.
	 *
	 * @param AIPS_AI_Service_Interface|null     $ai_service Optional AI service.
	 * @param AIPS_AI_Assistance_Repository|null $repository Optional AI assistance repository.
	 */
	public function __construct(
		$ai_service = null,
		?AIPS_AI_Assistance_Repository $repository = null
	) {
		$this->ai_service = $ai_service;
		$this->repository = $repository;
	}

	/**
	 * Resolve AI service lazily.
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
	 * Resolve AI assistance repository lazily.
	 *
	 * @return AIPS_AI_Assistance_Repository
	 */
	private function get_repository(): AIPS_AI_Assistance_Repository {
		if ($this->repository === null) {
			$this->repository = new AIPS_AI_Assistance_Repository();
		}

		return $this->repository;
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_key(): string {
		return 'field_suggestion';
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_label(): string {
		return __('Form Field Suggestion', 'ai-post-scheduler');
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_strategy_key(): string {
		return 'ai_assistance';
	}

	/**
	 * {@inheritdoc}
	 */
	public function is_enabled(AIPS_Generation_Context $context): bool {
		if ($context instanceof AIPS_AI_Assistance_Generation_Context) {
			return AIPS_Settings::is_ai_assistance_enabled_for($context->get_form_context());
		}

		return true;
	}

	/**
	 * {@inheritdoc}
	 */
	public function is_required(): bool {
		return true;
	}

	/**
	 * {@inheritdoc}
	 */
	public function build_prompt(AIPS_Generation_Context $context, ?AIPS_AI_Conversation $session = null): string {
		$field_config = ($context instanceof AIPS_AI_Assistance_Generation_Context)
			? $context->get_field_config()
			: array();

		$form_context = $field_config['form_context'] ?? '';
		$preamble     = $this->preambles[$form_context] ?? 'a WordPress admin form';
		$lengths      = array(
			'concise'  => 'Keep it concise.',
			'standard' => 'Use an appropriately complete answer.',
			'detailed' => 'Provide a detailed, useful answer.',
		);
		$policy = AIPS_Settings::get_ai_assistance_policy();
		$lines  = array(
			'You are a helpful assistant for an AI content creation WordPress plugin.',
			'Help fill in a form field for ' . $preamble . '.',
			'',
			'Field: ' . ($field_config['field_name'] ?? ''),
			'Purpose: ' . ($field_config['description'] ?? ''),
			'How it influences AI content generation: ' . ($field_config['influence'] ?? ''),
			'Current value: ' . ($field_config['current_value'] ?? ''),
		);
		$lines[] = 'Workflow: ' . ($field_config['form_context'] ?? '');
		$lines[] = $lengths[$policy['responseLength']];
		$guidance = AIPS_Config::get_instance()->get_option('aips_ai_assistance_guidance');
		if (!empty($guidance)) {
			$lines[] = 'Additional site guidance: ' . $guidance;
		}
		foreach ((array) AIPS_Site_Context::get() as $context_key => $context_value) {
			if ('' === $context_value || is_array($context_value)) {
				continue;
			}
			$lines[] = 'Site Content Strategy — ' . ucwords(str_replace('_', ' ', $context_key)) . ': ' . $context_value;
		}

		foreach ((array) ($field_config['context'] ?? array()) as $context_key => $context_value) {
			if ('' === $context_value) {
				continue;
			}
			$lines[] = ucwords(str_replace(array('_', '-'), ' ', $context_key)) . ': ' . $context_value;
		}

		$lines[] = '';
		$lines[] = 'Respond with ONLY the suggested value for this field. No explanation, no quotes, no prefix. Expected format: ' . ($field_config['expected_response'] ?? '');

		return implode("\n", $lines);
	}

	/**
	 * {@inheritdoc}
	 */
	public function generate(AIPS_Generation_Context $context, ?AIPS_AI_Conversation $session = null, array $options = array()) {
		$prompt = isset($options['prompt']) && is_string($options['prompt']) && $options['prompt'] !== ''
			? $options['prompt']
			: $this->build_prompt($context, $session);

		if (!empty($options['custom_directive'])) {
			$prompt .= "\n\n" . sprintf(__('Follow this directive: %s', 'ai-post-scheduler'), (string) $options['custom_directive']);
		}

		$response = $this->get_ai_service()->generate_text($prompt);
		if (is_wp_error($response)) {
			return $response;
		}

		return array(
			'response' => $this->sanitize_value($response),
			'prompt'   => $prompt,
		);
	}

	/**
	 * {@inheritdoc}
	 */
	public function regenerate($target_id, AIPS_Generation_Context $context, ?AIPS_AI_Conversation $session = null, string $directive = '', array $options = array()) {
		if (trim($directive) !== '') {
			$options['custom_directive'] = trim($directive);
		}

		return $this->generate($context, $session, $options);
	}

	/**
	 * Persist a generated field suggestion to AIPS_AI_Assistance_Repository.
	 *
	 * @param mixed $target AIPS_AI_Assistance_Generation_Context instance.
	 * @param mixed $value  Array containing 'response' and 'prompt' (or response string).
	 * @return int|false Inserted record ID or false on failure.
	 */
	public function persist_suggestion(AIPS_AI_Assistance_Generation_Context $context, array $value) {
		$field_config = $context->get_field_config();

		return $this->get_repository()->create(array(
			'session_id'     => $context->get_session_id(),
			'user_id'        => $context->get_user_id(),
			'form_context'   => sanitize_text_field($field_config['form_context'] ?? ''),
			'field_key'      => sanitize_text_field($field_config['form_field_id'] ?? ''),
			'request_object' => wp_json_encode($field_config),
			'prompt'         => isset($value['prompt']) ? (string) $value['prompt'] : '',
			'response'       => isset($value['response']) ? (string) $value['response'] : '',
		));
	}

	/**
	 * {@inheritdoc}
	 */
	public function generate_variants($target_id, AIPS_Generation_Context $context, int $count = 3, array $options = array()) {
		// Field suggestions are not AI-editable; each request already returns a full suggestion set.
		return array();
	}

	/**
	 * {@inheritdoc}
	 */
	public function apply($target, $value): bool {
		if (!($target instanceof AIPS_AI_Assistance_Generation_Context) || !is_array($value)) {
			return false;
		}

		return $this->persist_suggestion($target, $value) !== false;
	}

	/**
	 * {@inheritdoc}
	 */
	public function reconcile_status($target_id): bool {
		return absint($target_id) > 0;
	}

	/**
	 * {@inheritdoc}
	 */
	public function supports_ai_edit(): bool {
		return false;
	}

	/**
	 * {@inheritdoc}
	 */
	public function sanitize_value($value) {
		return is_string($value) ? $value : (string) $value;
	}
}
