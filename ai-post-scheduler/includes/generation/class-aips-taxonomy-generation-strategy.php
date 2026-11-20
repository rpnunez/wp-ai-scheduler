<?php
/**
 * Taxonomy Generation Strategy
 *
 * Implements AIPS_Pipeline_Executable_Strategy_Interface for generating and
 * persisting category/tag taxonomy suggestions (migrating
 * AIPS_Taxonomy_Controller::generate_taxonomy_items() onto AIPS_Generation_Pipeline).
 *
 * @package AI_Post_Scheduler
 * @since   3.7.25
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Taxonomy_Generation_Strategy
 */
class AIPS_Taxonomy_Generation_Strategy implements AIPS_Pipeline_Executable_Strategy_Interface {

	/** Pipeline stage constants for Taxonomy Generation. */
	const STAGE_ANALYSIS    = 'analysis';
	const STAGE_SUGGESTIONS = 'suggestions';
	const STAGE_PERSISTENCE = 'persistence';

	/**
	 * @var AIPS_Generation_Component_Registry
	 */
	private $registry;

	/**
	 * @var AIPS_AI_Service_Interface
	 */
	private $ai_service;

	/**
	 * @var object Taxonomy repository
	 */
	private $repository;

	/**
	 * @var AIPS_Prompt_Builder_Taxonomy
	 */
	private $prompt_builder;

	/**
	 * @var WP_Error|null
	 */
	private $last_error = null;

	/**
	 * Constructor.
	 *
	 * @param AIPS_Generation_Component_Registry|null $registry       Optional component registry.
	 * @param AIPS_AI_Service_Interface|null          $ai_service     Optional AI service.
	 * @param object|null                             $repository     Optional taxonomy repository.
	 * @param AIPS_Prompt_Builder_Taxonomy|null       $prompt_builder Optional taxonomy prompt builder.
	 */
	public function __construct(
		?AIPS_Generation_Component_Registry $registry = null,
		?AIPS_AI_Service_Interface $ai_service = null,
		$repository = null,
		$prompt_builder = null
	) {
		$container            = AIPS_Container::get_instance();
		$this->ai_service     = $ai_service ?: ($container->has(AIPS_AI_Service_Interface::class) ? $container->make(AIPS_AI_Service_Interface::class) : new AIPS_AI_Service());
		$this->repository     = $repository ?: new AIPS_Taxonomy_Repository();
		$this->prompt_builder = $prompt_builder ?: new AIPS_Prompt_Builder_Taxonomy();

		if ($registry !== null) {
			$this->registry = $registry;
		} else {
			$this->registry = new AIPS_Generation_Component_Registry();
			$this->registry->register_default_taxonomy_components($this->ai_service, $this->prompt_builder);
		}
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_key(): string {
		return 'taxonomy';
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_label(): string {
		return __('Taxonomy Suggestions', 'ai-post-scheduler');
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_stages(): array {
		return array(
			self::STAGE_ANALYSIS,
			self::STAGE_SUGGESTIONS,
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

		$component = $this->registry->get('taxonomy_suggestions', $this->get_key());
		if (!($component instanceof AIPS_Taxonomy_Suggestion_Component)) {
			$component = new AIPS_Taxonomy_Suggestion_Component($this->ai_service, $this->prompt_builder);
		}

		$names = $component->generate($context);
		if (is_wp_error($names)) {
			$this->last_error = $names;
			return AIPS_Generation_Result::from_wp_error($names, microtime(true) - $start);
		}

		$taxonomy_type     = ($context instanceof AIPS_Taxonomy_Generation_Context) ? $context->get_taxonomy_type() : 'category';
		$post_ids          = ($context instanceof AIPS_Taxonomy_Generation_Context) ? $context->get_post_ids() : array();
		$generation_prompt = ($context instanceof AIPS_Taxonomy_Generation_Context) ? $context->get_generation_prompt() : '';
		$generated_items   = array();

		foreach ((array) $names as $name) {
			$item_id = $this->repository->insert(array(
				'name'              => $name,
				'taxonomy_type'     => $taxonomy_type,
				'status'            => 'pending',
				'base_post_ids'     => implode(',', $post_ids),
				'generation_prompt' => $generation_prompt,
			));

			if ($item_id) {
				$generated_items[] = $this->repository->get_by_id($item_id);
			}
		}

		return AIPS_Generation_Result::from_data(
			$generated_items,
			AIPS_Generation_Result::STATUS_COMPLETED,
			array('taxonomy_suggestions' => true),
			array(),
			0,
			microtime(true) - $start
		);
	}

	/**
	 * {@inheritdoc}
	 */
	public function execute_preview_run(AIPS_Generation_Context $context) {
		$component = $this->registry->get('taxonomy_suggestions', $this->get_key());
		if (!($component instanceof AIPS_Taxonomy_Suggestion_Component)) {
			$component = new AIPS_Taxonomy_Suggestion_Component($this->ai_service, $this->prompt_builder);
		}

		return $component->generate($context);
	}
}
