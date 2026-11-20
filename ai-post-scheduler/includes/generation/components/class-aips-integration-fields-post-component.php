<?php
/**
 * Integration Fields Post Component
 *
 * Owns batched JSON generation (with per-field text fallback) for custom
 * integration fields (ACF, WooCommerce, Meta Box, etc.) during the
 * STAGE_INTEGRATIONS phase of post generation.
 *
 * @package AI_Post_Scheduler
 * @since   3.7.25
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Integration_Fields_Post_Component
 */
class AIPS_Integration_Fields_Post_Component extends AIPS_Abstract_Post_Component {

	/**
	 * @var AIPS_AI_Service_Interface|null
	 */
	private $ai_service;

	/**
	 * @var AIPS_Integration_Field_Prompt_Builder|null
	 */
	private $prompt_builder;

	/**
	 * Constructor.
	 *
	 * @param AIPS_Generator|null                        $generator      Optional generator instance.
	 * @param AIPS_AI_Service_Interface|null             $ai_service     Optional AI service.
	 * @param AIPS_Integration_Field_Prompt_Builder|null $prompt_builder Optional field prompt builder.
	 */
	public function __construct(
		?AIPS_Generator $generator = null,
		?AIPS_AI_Service_Interface $ai_service = null,
		?AIPS_Integration_Field_Prompt_Builder $prompt_builder = null
	) {
		parent::__construct($generator);
		$this->ai_service     = $ai_service;
		$this->prompt_builder = $prompt_builder;
	}

	/**
	 * Resolve AI service lazily.
	 *
	 * @return AIPS_AI_Service_Interface
	 */
	private function get_ai_service(): AIPS_AI_Service_Interface {
		if ($this->ai_service === null) {
			$container        = AIPS_Container::get_instance();
			$this->ai_service = $container->has(AIPS_AI_Service_Interface::class)
				? $container->make(AIPS_AI_Service_Interface::class)
				: new AIPS_AI_Service();
		}

		return $this->ai_service;
	}

	/**
	 * Resolve prompt builder lazily.
	 *
	 * @return AIPS_Integration_Field_Prompt_Builder
	 */
	private function get_prompt_builder(): AIPS_Integration_Field_Prompt_Builder {
		if ($this->prompt_builder === null) {
			$this->prompt_builder = new AIPS_Integration_Field_Prompt_Builder();
		}

		return $this->prompt_builder;
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_key(): string {
		return 'post_integration_fields';
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_label(): string {
		return __('Integration Fields', 'ai-post-scheduler');
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_stage(): string {
		return self::STAGE_INTEGRATIONS;
	}

	/**
	 * {@inheritdoc}
	 */
	public function is_enabled(AIPS_Generation_Context $context): bool {
		return true;
	}

	/**
	 * {@inheritdoc}
	 */
	public function is_required(): bool {
		return false;
	}

	/**
	 * {@inheritdoc}
	 */
	public function supports_ai_edit(): bool {
		return false;
	}

	/**
	 * {@inheritdoc}
	 */
	public function build_prompt(AIPS_Generation_Context $context, ?AIPS_AI_Conversation $session = null): string {
		return '';
	}

	/**
	 * Generate a batch of integration field values in one JSON call.
	 *
	 * @param array                   $generatable List of {mapping, shape, field_def}.
	 * @param AIPS_Generation_Context $context     Generation context.
	 * @return array|WP_Error Decoded JSON map or WP_Error.
	 */
	public function generate_batch(array $generatable, AIPS_Generation_Context $context) {
		$prompt = $this->get_prompt_builder()->build_batch($generatable, $context);
		return $this->get_ai_service()->generate_json($prompt);
	}

	/**
	 * Generate a single integration field value via generate_text().
	 *
	 * @param array                   $field_def     Field definition.
	 * @param AIPS_Generation_Context $context       Generation context.
	 * @param string                  $custom_prompt Optional custom prompt.
	 * @return string|WP_Error
	 */
	public function generate_single_field(array $field_def, AIPS_Generation_Context $context, string $custom_prompt = '') {
		$prompt = $this->get_prompt_builder()->build($field_def, $context, $custom_prompt);
		return $this->get_ai_service()->generate_text($prompt);
	}

	/**
	 * {@inheritdoc}
	 */
	public function generate(AIPS_Generation_Context $context, ?AIPS_AI_Conversation $session = null, array $options = array()) {
		if (!empty($options['generatable']) && is_array($options['generatable'])) {
			return $this->generate_batch($options['generatable'], $context);
		}

		return array();
	}

	/**
	 * {@inheritdoc}
	 */
	public function regenerate($target_id, AIPS_Generation_Context $context, ?AIPS_AI_Conversation $session = null, string $directive = '', array $options = array()) {
		return $this->generate($context, $session, $options);
	}

	/**
	 * {@inheritdoc}
	 */
	public function apply_to_post(int $post_id, $value): bool {
		return $post_id > 0 && is_array($value);
	}

	/**
	 * {@inheritdoc}
	 */
	public function reconcile_status($target_id): bool {
		return absint($target_id) > 0;
	}

	/**
	 * {@inheritdoc}
	 */
	public function sanitize_value($value) {
		return is_array($value) ? $value : array();
	}
}
