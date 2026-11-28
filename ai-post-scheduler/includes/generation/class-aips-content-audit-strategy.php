<?php
/**
 * Content Audit Generation Strategy
 *
 * Implements AIPS_Pipeline_Executable_Strategy_Interface for Content Auditor
 * AI evaluation passes (migrating AIPS_Content_Auditor_Engine and
 * AIPS_Content_Auditor onto AIPS_Generation_Pipeline).
 *
 * @package AI_Post_Scheduler
 * @since   3.7.25
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Content_Audit_Strategy
 */
class AIPS_Content_Audit_Strategy implements AIPS_Pipeline_Executable_Strategy_Interface {

	/** Pipeline stage constants for Content Audit. */
	const STAGE_AUDIT = 'audit';

	/**
	 * @var AIPS_Generation_Component_Registry
	 */
	private $registry;

	/**
	 * @var AIPS_AI_Service_Interface
	 */
	private $ai_service;

	/**
	 * @var WP_Error|null
	 */
	private $last_error = null;

	/**
	 * Constructor.
	 *
	 * @param AIPS_Generation_Component_Registry|null $registry   Optional component registry.
	 * @param AIPS_AI_Service_Interface|null          $ai_service Optional AI service.
	 */
	public function __construct(
		?AIPS_Generation_Component_Registry $registry = null,
		?AIPS_AI_Service_Interface $ai_service = null
	) {
		$container        = AIPS_Container::get_instance();
		$this->ai_service = $ai_service ?: ($container->has(AIPS_AI_Service_Interface::class) ? $container->make(AIPS_AI_Service_Interface::class) : new AIPS_AI_Service());

		if ($registry !== null) {
			$this->registry = $registry;
		} else {
			$this->registry = new AIPS_Generation_Component_Registry();
			$this->registry->register(new AIPS_Content_Audit_Pass_Component($this->ai_service));
		}
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_key(): string {
		return 'content_audit';
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_label(): string {
		return __('Content Audit', 'ai-post-scheduler');
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_stages(): array {
		return array(self::STAGE_AUDIT);
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

		$component = $this->registry->get('content_audit_pass', $this->get_key());
		if (!($component instanceof AIPS_Content_Audit_Pass_Component)) {
			$component = new AIPS_Content_Audit_Pass_Component($this->ai_service);
		}

		$data = $component->generate($context);
		if (is_wp_error($data)) {
			$this->last_error = $data;
			return AIPS_Generation_Result::from_wp_error($data, microtime(true) - $start);
		}

		return AIPS_Generation_Result::from_data(
			$data,
			AIPS_Generation_Result::STATUS_COMPLETED,
			array('content_audit_pass' => true),
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
			return $this->last_error ?: new WP_Error('content_audit_failed', __('Content audit pass failed.', 'ai-post-scheduler'));
		}

		return $result->get_data();
	}
}
