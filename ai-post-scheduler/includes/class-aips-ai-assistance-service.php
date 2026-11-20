<?php
/**
 * AI Assistance Service
 *
 * Business logic for generating AI field suggestions, delegating through
 * AIPS_Generation_Pipeline and AIPS_AI_Assistance_Strategy.
 *
 * @package AI_Post_Scheduler
 * @since 2.5.1
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_AI_Assistance_Service
 *
 * Builds prompts, calls the AI engine, and persists AI field suggestions via
 * the Universal Generation Strategy & Component Framework.
 */
class AIPS_AI_Assistance_Service {

	/**
	 * @var AIPS_AI_Service_Interface AI service instance.
	 */
	private $ai_service;

	/**
	 * @var AIPS_AI_Assistance_Repository Repository instance.
	 */
	private $repository;

	/**
	 * @var AIPS_Generation_Pipeline|null
	 */
	private $pipeline;

	/**
	 * Constructor.
	 *
	 * @param AIPS_AI_Service_Interface     $ai_service AI service.
	 * @param AIPS_AI_Assistance_Repository $repository Repository.
	 * @param AIPS_Generation_Pipeline|null $pipeline   Optional generation pipeline.
	 */
	public function __construct(
		$ai_service,
		AIPS_AI_Assistance_Repository $repository,
		?AIPS_Generation_Pipeline $pipeline = null
	) {
		$this->ai_service = $ai_service;
		$this->repository = $repository;
		$this->pipeline   = $pipeline;
	}

	/**
	 * Resolve the AI Assistance generation pipeline.
	 *
	 * @return AIPS_Generation_Pipeline
	 */
	private function get_pipeline(): AIPS_Generation_Pipeline {
		if ($this->pipeline === null) {
			$registry = new AIPS_Generation_Component_Registry();
			$registry->register_default_ai_assistance_components($this->ai_service, $this->repository);
			$strategy       = new AIPS_AI_Assistance_Strategy($registry, $this->ai_service, $this->repository);
			$this->pipeline = new AIPS_Generation_Pipeline($strategy, $registry);
		}

		return $this->pipeline;
	}

	/**
	 * Generate an AI suggestion for a form field.
	 *
	 * @param array  $field_config {
	 *     Field configuration.
	 *     @type string $field_name        Human-readable field label.
	 *     @type string $form_field_id     HTML id of the field.
	 *     @type string $form_context      Form name (e.g. 'authors').
	 *     @type string $description       Purpose of the field.
	 *     @type string $influence         How this field influences AI content.
	 *     @type string $expected_response Format hint for the AI response.
	 *     @type string $current_value     Current value in the field.
	 *     @type array  $context           Optional: map of sibling field id => value,
	 *                                     as declared by that field's contextFields
	 *                                     entry in the form's field map.
	 * }
	 * @param string $session_id  Browser session identifier.
	 * @param int    $user_id     WordPress user ID.
	 * @return array|WP_Error Array with 'response', 'record_id', 'prompt', or WP_Error on failure.
	 */
	public function get_field_suggestion(array $field_config, string $session_id, int $user_id) {
		$result = $this->get_field_suggestion_result($field_config, $session_id, $user_id);

		if (!$result->is_success()) {
			$strategy = $this->get_pipeline()->get_strategy();
			if ($strategy instanceof AIPS_AI_Assistance_Strategy && $strategy->get_last_error() instanceof WP_Error) {
				return $strategy->get_last_error();
			}

			$errors = $result->get_errors();
			return new WP_Error(
				'ai_assistance_failed',
				!empty($errors) ? $errors[0] : __('Failed to generate AI field suggestion.', 'ai-post-scheduler')
			);
		}

		return $result->get_data();
	}

	/**
	 * Execute the AI Assistance generation pipeline and return the typed AIPS_Generation_Result.
	 *
	 * @param array  $field_config Field configuration map.
	 * @param string $session_id   Browser session identifier.
	 * @param int    $user_id      WordPress user ID.
	 * @return AIPS_Generation_Result
	 */
	public function get_field_suggestion_result(array $field_config, string $session_id, int $user_id): AIPS_Generation_Result {
		$context = new AIPS_AI_Assistance_Generation_Context($field_config, $session_id, $user_id);
		return $this->get_pipeline()->execute($context);
	}

	/**
	 * Build a structured prompt from the field configuration.
	 *
	 * Retained for backward compatibility; delegates to AIPS_Field_Suggestion_Component.
	 *
	 * @param array $field_config Field configuration (see get_field_suggestion()).
	 * @return string The prompt string.
	 */
	private function build_prompt(array $field_config): string {
		$component = new AIPS_Field_Suggestion_Component($this->ai_service, $this->repository);
		return $component->build_prompt(new AIPS_AI_Assistance_Generation_Context($field_config));
	}
}
