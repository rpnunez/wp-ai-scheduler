<?php
/**
 * Generation Pipeline Engine
 *
 * Universal stage-driven execution engine that runs any
 * AIPS_Generation_Strategy_Interface with its registered components and
 * produces a strongly-typed AIPS_Generation_Result DTO.
 *
 * @package AI_Post_Scheduler
 * @since   3.7.25
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Generation_Pipeline
 */
class AIPS_Generation_Pipeline {

	/**
	 * @var AIPS_Generation_Strategy_Interface
	 */
	private $strategy;

	/**
	 * @var AIPS_Generation_Component_Registry
	 */
	private $registry;

	/**
	 * Constructor.
	 *
	 * @param AIPS_Generation_Strategy_Interface $strategy Strategy defining stages and components.
	 * @param AIPS_Generation_Component_Registry $registry Component registry.
	 */
	public function __construct(
		AIPS_Generation_Strategy_Interface $strategy,
		AIPS_Generation_Component_Registry $registry
	) {
		$this->strategy = $strategy;
		$this->registry = $registry;
	}

	/**
	 * Return the active strategy.
	 *
	 * @return AIPS_Generation_Strategy_Interface
	 */
	public function get_strategy(): AIPS_Generation_Strategy_Interface {
		return $this->strategy;
	}

	/**
	 * Return the component registry.
	 *
	 * @return AIPS_Generation_Component_Registry
	 */
	public function get_registry(): AIPS_Generation_Component_Registry {
		return $this->registry;
	}

	/**
	 * Execute the generation pipeline for the given context.
	 *
	 * @param AIPS_Generation_Context $context Generation context.
	 * @return AIPS_Generation_Result
	 */
	public function execute(AIPS_Generation_Context $context): AIPS_Generation_Result {
		$this->strategy->before_pipeline($context);

		if ($this->strategy instanceof AIPS_Post_Generation_Strategy) {
			$result = $this->strategy->execute_run($context);
			return $this->strategy->after_pipeline($context, $result);
		}

		// Generic component execution loop for non-post strategies (Phases 2-3).
		$start              = microtime(true);
		$components         = $this->strategy->get_components($context);
		$outputs            = array();
		$component_statuses = array();
		$errors             = array();
		$required_failed    = false;

		foreach ($components as $component) {
			$key = $component->get_key();
			if (!$component->is_enabled($context)) {
				continue;
			}

			$value = $component->generate($context, null, array('outputs' => $outputs));
			if (is_wp_error($value)) {
				$component_statuses[$key] = false;
				$errors[]                 = $value->get_error_message();
				if ($component->is_required()) {
					$required_failed = true;
					break;
				}
				continue;
			}

			$outputs[$key]            = $component->sanitize_value($value);
			$component_statuses[$key] = true;
		}

		$elapsed = microtime(true) - $start;

		if ($required_failed) {
			$result = AIPS_Generation_Result::failure($errors, $elapsed, 0, $component_statuses);
		} elseif (!empty($errors)) {
			$result = AIPS_Generation_Result::from_data(
				$outputs,
				AIPS_Generation_Result::STATUS_PARTIAL,
				$component_statuses,
				$errors,
				0,
				$elapsed
			);
		} else {
			$result = AIPS_Generation_Result::from_data(
				$outputs,
				AIPS_Generation_Result::STATUS_COMPLETED,
				$component_statuses,
				array(),
				0,
				$elapsed
			);
		}

		return $this->strategy->after_pipeline($context, $result);
	}

	/**
	 * Execute a non-persistent preview run through the active strategy.
	 *
	 * @param AIPS_Generation_Context $context Generation context.
	 * @return array|WP_Error
	 */
	public function execute_preview(AIPS_Generation_Context $context) {
		$this->strategy->before_pipeline($context);

		if ($this->strategy instanceof AIPS_Post_Generation_Strategy) {
			return $this->strategy->execute_preview_run($context);
		}

		$preview = array();
		foreach ($this->strategy->get_components($context) as $component) {
			if (!$component->is_enabled($context)) {
				continue;
			}

			$value = $component->generate($context, null, array('preview' => true, 'outputs' => $preview));
			if (is_wp_error($value)) {
				return $value;
			}
			$preview[$component->get_key()] = $component->sanitize_value($value);
		}

		return $preview;
	}
}
