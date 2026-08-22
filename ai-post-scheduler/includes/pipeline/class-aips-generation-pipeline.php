<?php
/**
 * Generation Pipeline Orchestrator
 *
 * Coordinates execution of composable generation stages via an onion/middleware
 * architecture. Supports hook-based extension, individual stage profiling, and
 * graceful error degradation.
 *
 * @package AI_Post_Scheduler
 * @since 3.5.0
 */

if (!defined('ABSPATH')) {
	exit;
}

class AIPS_Generation_Pipeline {

	/**
	 * @var array<string, AIPS_Generation_Stage_Interface>
	 */
	private $stages = array();

	/**
	 * @var AIPS_Logger_Interface|null
	 */
	private $logger;

	/**
	 * Constructor.
	 *
	 * @param array<AIPS_Generation_Stage_Interface> $stages Optional initial stage list.
	 * @param AIPS_Logger_Interface|null             $logger Optional logger.
	 */
	public function __construct(array $stages = array(), ?AIPS_Logger_Interface $logger = null) {
		$container = AIPS_Container::get_instance();
		$this->logger = $logger ?: ($container->has(AIPS_Logger_Interface::class) ? $container->make(AIPS_Logger_Interface::class) : new AIPS_Logger());

		if (!empty($stages)) {
			foreach ($stages as $stage) {
				$this->add_stage($stage);
			}
		} else {
			$this->register_default_stages();
		}
	}

	/**
	 * Register default pipeline stages.
	 *
	 * @return void
	 */
	public function register_default_stages(): void {
		$this->stages = array();

		$this->add_stage(new AIPS_Context_Preparation_Stage());
		$this->add_stage(new AIPS_Knowledge_Retrieval_Stage());
		$this->add_stage(new AIPS_Outline_Stage());
		$this->add_stage(new AIPS_Drafting_Stage());
		$this->add_stage(new AIPS_SEO_Stage());
		$this->add_stage(new AIPS_Linking_Stage());
		$this->add_stage(new AIPS_Review_Preparation_Stage());
	}

	/**
	 * Add a stage to the pipeline.
	 *
	 * @param AIPS_Generation_Stage_Interface $stage Stage instance.
	 * @return self
	 */
	public function add_stage(AIPS_Generation_Stage_Interface $stage): self {
		$this->stages[$stage->get_id()] = $stage;
		return $this;
	}

	/**
	 * Remove a stage by ID.
	 *
	 * @param string $stage_id Stage ID.
	 * @return self
	 */
	public function remove_stage(string $stage_id): self {
		unset($this->stages[$stage_id]);
		return $this;
	}

	/**
	 * Check if a stage exists.
	 *
	 * @param string $stage_id Stage ID.
	 * @return bool
	 */
	public function has_stage(string $stage_id): bool {
		return isset($this->stages[$stage_id]);
	}

	/**
	 * Get ordered list of active stages for given context.
	 *
	 * @param AIPS_Generation_Context $context Generation context.
	 * @return array<AIPS_Generation_Stage_Interface>
	 */
	public function get_ordered_stages(AIPS_Generation_Context $context): array {
		$stages = array_values($this->stages);

		/**
		 * Filter generation pipeline stages before execution.
		 *
		 * @param array<AIPS_Generation_Stage_Interface> $stages  Active stages.
		 * @param AIPS_Generation_Context                $context Context.
		 */
		$filtered = apply_filters('aips_generation_pipeline_stages', $stages, $context);

		if (!is_array($filtered)) {
			$filtered = $stages;
		}

		usort($filtered, function (AIPS_Generation_Stage_Interface $a, AIPS_Generation_Stage_Interface $b) {
			return $a->get_priority() <=> $b->get_priority();
		});

		return $filtered;
	}

	/**
	 * Execute the pipeline for a given context and initial payload.
	 *
	 * @param AIPS_Generation_Context               $context Generation context.
	 * @param AIPS_Generation_Pipeline_Payload|null $payload Initial payload or null for empty.
	 * @return AIPS_Generation_Pipeline_Payload
	 */
	public function execute(
		AIPS_Generation_Context $context,
		?AIPS_Generation_Pipeline_Payload $payload = null
	): AIPS_Generation_Pipeline_Payload {
		if ($payload === null) {
			$payload = new AIPS_Generation_Pipeline_Payload(array(
				'title'       => (string) $context->get_topic(),
				'post_status' => (string) $context->get_post_status(),
				'categories'  => (array) $context->get_post_category(),
			));
		}

		$stages = $this->get_ordered_stages($context);

		// Build middleware runner starting from the terminal callable.
		$runner = function (AIPS_Generation_Pipeline_Payload $p) {
			return $p;
		};

		// Wrap stages in reverse order.
		for ($i = count($stages) - 1; $i >= 0; $i--) {
			$stage = $stages[$i];

			$runner = function (AIPS_Generation_Pipeline_Payload $current_payload) use ($stage, $context, $runner) {
				if (!$stage->should_run($context, $current_payload)) {
					return $runner($current_payload);
				}

				$stage_id = $stage->get_id();
				$start = microtime(true);

				do_action('aips_pipeline_stage_before', $stage_id, $stage, $context, $current_payload);

				try {
					$result_payload = $stage->process($context, $current_payload, $runner);
				} catch (Throwable $e) {
					$this->logger->error("Pipeline stage {$stage_id} failed: " . $e->getMessage(), array(
						'exception' => $e,
						'stage'     => $stage_id,
					));
					$current_payload->add_error($e->getMessage());
					$result_payload = $current_payload;
				}

				$elapsed = microtime(true) - $start;
				$result_payload->record_stage_timing($stage_id, $elapsed);

				do_action('aips_pipeline_stage_after', $stage_id, $stage, $context, $result_payload);

				return $result_payload;
			};
		}

		return $runner($payload);
	}
}
