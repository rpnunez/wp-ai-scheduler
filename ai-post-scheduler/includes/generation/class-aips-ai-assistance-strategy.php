<?php
/**
 * AI Assistance Generation Strategy
 *
 * Implements AIPS_Pipeline_Executable_Strategy_Interface for generating and
 * persisting contextual admin form-field AI suggestions across workflows
 * ('authors', 'templates', 'sources', 'content_strategy').
 *
 * @package AI_Post_Scheduler
 * @since   3.7.25
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_AI_Assistance_Strategy
 */
class AIPS_AI_Assistance_Strategy implements AIPS_Pipeline_Executable_Strategy_Interface {

	/** Pipeline stage constants for AI Assistance. */
	const STAGE_POLICY_CHECK = 'policy_check';
	const STAGE_SUGGESTION   = 'suggestion';
	const STAGE_PERSISTENCE  = 'persistence';

	/**
	 * @var AIPS_Generation_Component_Registry
	 */
	private $registry;

	/**
	 * @var AIPS_AI_Service_Interface
	 */
	private $ai_service;

	/**
	 * @var AIPS_AI_Assistance_Repository
	 */
	private $repository;

	/**
	 * @var WP_Error|null
	 */
	private $last_error = null;

	/**
	 * Constructor.
	 *
	 * @param AIPS_Generation_Component_Registry|null $registry   Optional component registry.
	 * @param AIPS_AI_Service_Interface|null          $ai_service Optional AI service.
	 * @param AIPS_AI_Assistance_Repository|null      $repository Optional AI assistance repository.
	 */
	public function __construct(
		?AIPS_Generation_Component_Registry $registry = null,
		$ai_service = null,
		?AIPS_AI_Assistance_Repository $repository = null
	) {
		$container        = AIPS_Container::get_instance();
		$this->ai_service = $ai_service ?: ($container->has(AIPS_AI_Service_Interface::class) ? $container->make(AIPS_AI_Service_Interface::class) : new AIPS_AI_Service());
		$this->repository = $repository ?: new AIPS_AI_Assistance_Repository();

		if ($registry !== null) {
			$this->registry = $registry;
		} else {
			$this->registry = new AIPS_Generation_Component_Registry();
			$this->registry->register_default_ai_assistance_components($this->ai_service, $this->repository);
		}
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_key(): string {
		return 'ai_assistance';
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_label(): string {
		return __('AI Form Field Assistance', 'ai-post-scheduler');
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_stages(): array {
		return array(
			self::STAGE_POLICY_CHECK,
			self::STAGE_SUGGESTION,
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
	 * {@inheritdoc}
	 */
	public function execute_run(AIPS_Generation_Context $context): AIPS_Generation_Result {
		$start            = microtime(true);
		$this->last_error = null;

		if (!($context instanceof AIPS_AI_Assistance_Generation_Context)) {
			$error            = new WP_Error('invalid_context', __('Invalid AI Assistance generation context.', 'ai-post-scheduler'));
			$this->last_error = $error;
			return AIPS_Generation_Result::from_wp_error($error, microtime(true) - $start);
		}

		// Stage 1: Policy check.
		if (!AIPS_Settings::is_ai_assistance_enabled_for($context->get_form_context())) {
			$error = new WP_Error(
				'ai_assistance_disabled',
				__('AI Assistance is disabled for this workflow. Enable it in Settings > AI Assistance.', 'ai-post-scheduler')
			);
			$this->last_error = $error;
			return AIPS_Generation_Result::from_wp_error($error, microtime(true) - $start);
		}

		// Stage 2: Suggestion generation.
		$component = $this->registry->get('field_suggestion', $this->get_key());
		if (!($component instanceof AIPS_Field_Suggestion_Component)) {
			$component = new AIPS_Field_Suggestion_Component($this->ai_service, $this->repository);
		}

		$generated = $component->generate($context);
		if (is_wp_error($generated)) {
			$this->last_error = $generated;
			return AIPS_Generation_Result::from_wp_error($generated, microtime(true) - $start);
		}

		// Stage 3: Persistence.
		$record_id = $component->persist_suggestion($context, $generated);
		if ($record_id === false) {
			$error = new WP_Error(
				'db_insert_failed',
				__('AI suggestion was generated but could not be saved to history.', 'ai-post-scheduler')
			);
			$this->last_error = $error;
			return AIPS_Generation_Result::from_wp_error($error, microtime(true) - $start);
		}

		$payload = array(
			'response'  => $generated['response'],
			'record_id' => $record_id,
			'prompt'    => $generated['prompt'],
		);

		return AIPS_Generation_Result::from_data(
			$payload,
			AIPS_Generation_Result::STATUS_COMPLETED,
			array('field_suggestion' => true),
			array(),
			(int) $record_id,
			microtime(true) - $start
		);
	}

	/**
	 * {@inheritdoc}
	 */
	public function execute_preview_run(AIPS_Generation_Context $context) {
		$component = $this->registry->get('field_suggestion', $this->get_key());
		if (!($component instanceof AIPS_Field_Suggestion_Component)) {
			$component = new AIPS_Field_Suggestion_Component($this->ai_service, $this->repository);
		}

		return $component->generate($context);
	}
}
