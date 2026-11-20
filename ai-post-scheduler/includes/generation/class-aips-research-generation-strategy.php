<?php
/**
 * Research Generation Strategy
 *
 * Implements AIPS_Pipeline_Executable_Strategy_Interface for Niche Research
 * (trending topics discovery, source-grounded research, topic freshness
 * scoring, and topic comparison) through registered Research Components.
 *
 * @package AI_Post_Scheduler
 * @since   3.7.25
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Research_Generation_Strategy
 */
class AIPS_Research_Generation_Strategy implements AIPS_Pipeline_Executable_Strategy_Interface {

	/** Pipeline stage constants for Niche Research. */
	const STAGE_DISCOVERY    = 'discovery';
	const STAGE_SCORING      = 'scoring';
	const STAGE_GAP_ANALYSIS = 'gap_analysis';
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
	 * @var AIPS_Logger_Interface
	 */
	private $logger;

	/**
	 * @var WP_Error|null
	 */
	private $last_error = null;

	/**
	 * Constructor.
	 *
	 * @param AIPS_Generation_Component_Registry|null $registry   Optional component registry.
	 * @param AIPS_AI_Service_Interface|null          $ai_service Optional AI service.
	 * @param AIPS_Logger_Interface|null              $logger     Optional logger.
	 */
	public function __construct(
		?AIPS_Generation_Component_Registry $registry = null,
		?AIPS_AI_Service_Interface $ai_service = null,
		?AIPS_Logger_Interface $logger = null
	) {
		$container        = AIPS_Container::get_instance();
		$this->ai_service = $ai_service ?: ($container->has(AIPS_AI_Service_Interface::class) ? $container->make(AIPS_AI_Service_Interface::class) : new AIPS_AI_Service());
		$this->logger     = $logger ?: ($container->has(AIPS_Logger_Interface::class) ? $container->make(AIPS_Logger_Interface::class) : new AIPS_Logger());

		if ($registry !== null) {
			$this->registry = $registry;
		} else {
			$this->registry = new AIPS_Generation_Component_Registry();
			$this->registry->register_default_research_components($this->ai_service, $this->logger);
		}
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_key(): string {
		return 'research';
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_label(): string {
		return __('Niche Research', 'ai-post-scheduler');
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_stages(): array {
		return array(
			self::STAGE_DISCOVERY,
			self::STAGE_SCORING,
			self::STAGE_GAP_ANALYSIS,
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

		$mode = ($context instanceof AIPS_Research_Generation_Context)
			? $context->get_mode()
			: AIPS_Research_Generation_Context::MODE_TRENDING;

		if ($mode === AIPS_Research_Generation_Context::MODE_FRESHNESS) {
			$freshness = $this->registry->get('topic_freshness', $this->get_key());
			if (!($freshness instanceof AIPS_Topic_Freshness_Component)) {
				$freshness = new AIPS_Topic_Freshness_Component();
			}

			$analysis = $freshness->generate($context);
			return AIPS_Generation_Result::from_data(
				$analysis,
				AIPS_Generation_Result::STATUS_COMPLETED,
				array('topic_freshness' => true),
				array(),
				0,
				microtime(true) - $start
			);
		}

		if ($mode === AIPS_Research_Generation_Context::MODE_COMPARISON) {
			$comparison = $this->registry->get('topic_comparison', $this->get_key());
			if (!($comparison instanceof AIPS_Topic_Comparison_Component)) {
				$comparison = new AIPS_Topic_Comparison_Component();
			}

			$cmp = $comparison->generate($context);
			return AIPS_Generation_Result::from_data(
				$cmp,
				AIPS_Generation_Result::STATUS_COMPLETED,
				array('topic_comparison' => true),
				array(),
				0,
				microtime(true) - $start
			);
		}

		// Trending topics or source-grounded research.
		$trending = $this->registry->get('trending_topics', $this->get_key());
		if (!($trending instanceof AIPS_Trending_Topics_Component)) {
			$trending = new AIPS_Trending_Topics_Component($this->ai_service, $this->logger);
		}

		$topics = $trending->generate($context);
		if (is_wp_error($topics)) {
			$this->last_error = $topics;
			return AIPS_Generation_Result::from_wp_error($topics, microtime(true) - $start);
		}

		return AIPS_Generation_Result::from_data(
			$topics,
			AIPS_Generation_Result::STATUS_COMPLETED,
			array('trending_topics' => true),
			array(),
			0,
			microtime(true) - $start
		);
	}

	/**
	 * {@inheritdoc}
	 */
	public function execute_preview_run(AIPS_Generation_Context $context) {
		$result = $this->execute_run($context);
		if ($result->is_failure()) {
			return $this->last_error ?: new WP_Error('research_failed', __('Research generation failed.', 'ai-post-scheduler'));
		}

		return $result->get_data();
	}
}
