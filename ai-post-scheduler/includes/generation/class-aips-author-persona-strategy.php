<?php
/**
 * Author Persona Generation Strategy
 *
 * Implements AIPS_Pipeline_Executable_Strategy_Interface for generating
 * AI-powered Author Persona suggestions (migrating AIPS_Author_Suggestions_Service
 * onto AIPS_Generation_Pipeline).
 *
 * @package AI_Post_Scheduler
 * @since   3.7.25
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Author_Persona_Strategy
 */
class AIPS_Author_Persona_Strategy implements AIPS_Pipeline_Executable_Strategy_Interface {

	/** Pipeline stage constants for Author Persona Generation. */
	const STAGE_INTAKE           = 'intake';
	const STAGE_PERSONA_IDEATION = 'persona_ideation';
	const STAGE_NORMALIZATION    = 'normalization';
	const STAGE_FINALIZATION     = 'finalization';

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
	 * @var AIPS_Author_Persona_Component
	 */
	private $persona_component;

	/**
	 * @var WP_Error|null Last WP_Error encountered during a failed run
	 */
	private $last_error = null;

	/**
	 * Constructor.
	 *
	 * @param AIPS_Generation_Component_Registry|null $registry        Optional component registry.
	 * @param AIPS_AI_Service_Interface|null          $ai_service      Optional AI service.
	 * @param AIPS_Logger_Interface|null              $logger          Optional logger.
	 * @param AIPS_History_Service_Interface|null     $history_service Optional history service.
	 * @param AIPS_Prompt_Builder_Authors|null        $prompt_builder  Optional authors prompt builder.
	 */
	public function __construct(
		?AIPS_Generation_Component_Registry $registry = null,
		?AIPS_AI_Service_Interface $ai_service = null,
		?AIPS_Logger_Interface $logger = null,
		?AIPS_History_Service_Interface $history_service = null,
		$prompt_builder = null
	) {
		$container             = AIPS_Container::get_instance();
		$this->ai_service      = $ai_service ?: ($container->has(AIPS_AI_Service_Interface::class) ? $container->make(AIPS_AI_Service_Interface::class) : new AIPS_AI_Service());
		$this->logger          = $logger ?: ($container->has(AIPS_Logger_Interface::class) ? $container->make(AIPS_Logger_Interface::class) : new AIPS_Logger());
		$this->history_service = $history_service ?: ($container->has(AIPS_History_Service_Interface::class) ? $container->make(AIPS_History_Service_Interface::class) : new AIPS_History_Service());

		$this->persona_component = new AIPS_Author_Persona_Component($this->ai_service, $prompt_builder);

		if ($registry !== null) {
			$this->registry = $registry;
			if (!$this->registry->has('author_persona', $this->get_key())) {
				$this->registry->register($this->persona_component);
			}
		} else {
			$this->registry = new AIPS_Generation_Component_Registry();
			$this->registry->register($this->persona_component);
		}
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_key(): string {
		return 'author_personas';
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_label(): string {
		return __('Author Personas', 'ai-post-scheduler');
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_stages(): array {
		return array(
			self::STAGE_INTAKE,
			self::STAGE_PERSONA_IDEATION,
			self::STAGE_NORMALIZATION,
			self::STAGE_FINALIZATION,
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

		$inputs = ($context instanceof AIPS_Author_Persona_Generation_Context) ? $context->get_inputs() : array();
		$count  = ($context instanceof AIPS_Author_Persona_Generation_Context) ? $context->get_count() : 3;

		// Stage 1: Intake (fall back to site-wide content settings).
		$site_ctx   = AIPS_Site_Context::get();
		$site_niche = !empty($inputs['site_niche']) ? sanitize_text_field($inputs['site_niche']) : $site_ctx['niche'];

		if (empty($site_niche)) {
			$error            = new WP_Error('missing_niche', __('Site niche is required to generate author suggestions.', 'ai-post-scheduler'));
			$this->last_error = $error;
			return AIPS_Generation_Result::from_wp_error($error, microtime(true) - $start);
		}

		$merged_inputs = array_merge(
			array(
				'target_audience' => $site_ctx['target_audience'],
				'content_goals'   => $site_ctx['content_goals'],
				'brand_voice'     => $site_ctx['brand_voice'],
				'site_url'        => '',
			),
			$inputs,
			array('site_niche' => $site_niche)
		);

		$history = $this->history_service->create(
			'author_suggestion',
			array(
				'niche' => $site_niche,
				'count' => $count,
			)
		);
		$history_id = ($history && method_exists($history, 'get_id') && $history->get_id()) ? (int) $history->get_id() : 0;

		// Stage 2: Persona Ideation.
		$prompt = $this->persona_component->build_prompt_from_inputs($merged_inputs, $count);

		if ($history) {
			$history->record(
				'activity',
				sprintf('Generating %d author suggestion(s) for niche: %s', $count, $site_niche),
				array('count' => $count, 'niche' => $site_niche)
			);

			$history->record(
				'ai_request',
				'Author suggestion prompt sent to AI',
				array(
					'prompt'  => $prompt,
					'options' => array('temperature' => 0.8),
				)
			);
		}

		$this->logger->log(
			"Generating {$count} author suggestion(s) for niche: {$site_niche}",
			'info',
			array('niche' => $site_niche, 'count' => $count)
		);

		$response = $this->persona_component->generate($context, null, array('prompt' => $prompt));
		if (is_wp_error($response)) {
			$this->last_error = $response;
			$error_msg        = $response->get_error_message();

			if ($history) {
				$history->record_error(
					'AI call failed during author suggestion generation',
					array('error_code' => $response->get_error_code()),
					$response
				);
				$history->complete_failure($error_msg);
			}

			$this->logger->log('Author suggestions AI call failed: ' . $error_msg, 'error');
			return AIPS_Generation_Result::from_wp_error($response, microtime(true) - $start, $history_id);
		}

		if ($history) {
			$history->record(
				'ai_response',
				'AI response received for author suggestions',
				null,
				$response
			);
		}

		// Stage 3: Normalization.
		$suggestions = is_array($response) ? $this->persona_component->parse_suggestions($response, $count) : array();

		if (empty($suggestions)) {
			$error            = new WP_Error('no_suggestions_parsed', __('Could not parse author suggestions from the AI response. Please try again.', 'ai-post-scheduler'));
			$this->last_error = $error;

			if ($history) {
				$history->record_error('No author suggestions could be parsed from the AI response.');
				$history->complete_failure('No suggestions parsed');
			}

			$this->logger->log('No author suggestions parsed from AI response.', 'warning');
			return AIPS_Generation_Result::from_wp_error($error, microtime(true) - $start, $history_id);
		}

		// Stage 4: Finalization.
		$suggestion_count = count($suggestions);
		$elapsed          = microtime(true) - $start;

		if ($history) {
			$history->record(
				'activity',
				sprintf('Successfully generated %d author suggestion(s).', $suggestion_count),
				null,
				array('count' => $suggestion_count)
			);
			$history->complete_success(array('count' => $suggestion_count));
		}

		$this->logger->log(
			'Generated ' . $suggestion_count . ' author suggestion(s).',
			'info',
			array('count' => $suggestion_count)
		);

		return AIPS_Generation_Result::from_data(
			$suggestions,
			AIPS_Generation_Result::STATUS_COMPLETED,
			array('author_persona' => true),
			array(),
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
			return $this->last_error ?: new WP_Error('persona_generation_failed', __('Failed to generate author suggestions.', 'ai-post-scheduler'));
		}

		return $result->get_data();
	}
}
