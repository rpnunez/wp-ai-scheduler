<?php
/**
 * Author Suggestions Service
 *
 * Uses AI to generate author profile suggestions based on site/blog context inputs.
 * Delegates execution through AIPS_Generation_Pipeline and
 * AIPS_Author_Persona_Strategy while preserving full backward compatibility.
 *
 * @package AI_Post_Scheduler
 * @since 1.7.2
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Author_Suggestions_Service
 */
class AIPS_Author_Suggestions_Service {

	/**
	 * @var AIPS_Author_Persona_Strategy
	 */
	private $strategy;

	/**
	 * @var AIPS_Generation_Pipeline
	 */
	private $pipeline;

	/**
	 * Initialize the service.
	 *
	 * @param AIPS_AI_Service_Interface|null      $ai_service      AI service instance (optional for testing).
	 * @param AIPS_Logger_Interface|null          $logger          Logger instance (optional for testing).
	 * @param AIPS_History_Service_Interface|null $history_service History service (optional for testing).
	 * @param AIPS_Prompt_Builder_Authors|null    $prompt_builder  Prompt builder (optional for testing).
	 */
	public function __construct(
		?AIPS_AI_Service_Interface $ai_service = null,
		?AIPS_Logger_Interface $logger = null,
		?AIPS_History_Service_Interface $history_service = null,
		$prompt_builder = null
	) {
		$registry = new AIPS_Generation_Component_Registry();
		$registry->register_default_persona_components($ai_service, $prompt_builder);

		$this->strategy = new AIPS_Author_Persona_Strategy(
			$registry,
			$ai_service,
			$logger,
			$history_service,
			$prompt_builder
		);

		$this->pipeline = new AIPS_Generation_Pipeline($this->strategy, $registry);
	}

	/**
	 * Generate author profile suggestions and return a typed AIPS_Generation_Result DTO.
	 *
	 * @param array $inputs Context inputs for suggestion generation.
	 * @param int   $count  Number of author suggestions to generate (1-10). Default 3.
	 * @return AIPS_Generation_Result
	 */
	public function suggest_authors_result(array $inputs, int $count = 3): AIPS_Generation_Result {
		$context = new AIPS_Author_Persona_Generation_Context($inputs, $count);
		return $this->pipeline->execute($context);
	}

	/**
	 * Generate author profile suggestions using AI.
	 *
	 * @param array $inputs Context inputs for suggestion generation.
	 * @param int   $count  Number of author suggestions to generate (1-10). Default 3.
	 * @return array|WP_Error Array of suggestion arrays, or WP_Error on failure.
	 */
	public function suggest_authors(array $inputs, $count = 3) {
		$result = $this->suggest_authors_result($inputs, (int) $count);

		if ($result->is_failure()) {
			$last_error = $this->strategy->get_last_error();
			if ($last_error instanceof WP_Error) {
				return $last_error;
			}

			$errors  = $result->get_errors();
			$message = !empty($errors) ? $errors[0] : __('Could not generate author suggestions.', 'ai-post-scheduler');
			return new WP_Error('author_suggestions_failed', $message);
		}

		$data = $result->get_data();
		return is_array($data) ? $data : array();
	}
}
