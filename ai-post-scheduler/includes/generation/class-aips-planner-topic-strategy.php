<?php
/**
 * Planner Topic Generation Strategy
 *
 * Implements AIPS_Pipeline_Executable_Strategy_Interface for bulk planner
 * topic brainstorming (migrating AIPS_Planner::ajax_generate_topics() onto
 * AIPS_Generation_Pipeline).
 *
 * @package AI_Post_Scheduler
 * @since   3.7.25
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Planner_Topic_Strategy
 */
class AIPS_Planner_Topic_Strategy implements AIPS_Pipeline_Executable_Strategy_Interface {

	/** Pipeline stage constants for Planner Topic Generation. */
	const STAGE_IDEATION = 'ideation';

	/**
	 * @var AIPS_Generation_Component_Registry
	 */
	private $registry;

	/**
	 * @var AIPS_Generator|null
	 */
	private $generator;

	/**
	 * @var WP_Error|null
	 */
	private $last_error = null;

	/**
	 * Constructor.
	 *
	 * @param AIPS_Generation_Component_Registry|null $registry  Optional component registry.
	 * @param AIPS_Generator|null                     $generator Optional generator instance.
	 */
	public function __construct(
		?AIPS_Generation_Component_Registry $registry = null,
		?AIPS_Generator $generator = null
	) {
		$this->generator = $generator;

		if ($registry !== null) {
			$this->registry = $registry;
		} else {
			$this->registry = new AIPS_Generation_Component_Registry();
			$this->registry->register(new AIPS_Planner_Topics_Component($this->generator));
		}
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_key(): string {
		return 'planner_topics';
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_label(): string {
		return __('Planner Topics', 'ai-post-scheduler');
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_stages(): array {
		return array(self::STAGE_IDEATION);
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

		$component = $this->registry->get('planner_topics', $this->get_key());
		if (!($component instanceof AIPS_Planner_Topics_Component)) {
			$component = new AIPS_Planner_Topics_Component($this->generator);
		}

		$topics = $component->generate($context);
		if (is_wp_error($topics)) {
			$this->last_error = $topics;
			return AIPS_Generation_Result::from_wp_error($topics, microtime(true) - $start);
		}

		return AIPS_Generation_Result::from_data(
			$topics,
			AIPS_Generation_Result::STATUS_COMPLETED,
			array('planner_topics' => true),
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
			return $this->last_error ?: new WP_Error('planner_topics_failed', __('Failed to generate planner topics.', 'ai-post-scheduler'));
		}

		return $result->get_data();
	}
}
