<?php
/**
 * Post Consolidation Component
 *
 * Synthesizes two WordPress posts (keep + retire) into a single canonical
 * HTML article using AIPS_Prompt_Builder_Consolidation.
 *
 * @package AI_Post_Scheduler
 * @since   3.7.25
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Post_Consolidation_Component
 */
class AIPS_Post_Consolidation_Component implements AIPS_Generation_Component_Interface {

	/**
	 * @var AIPS_AI_Service_Interface
	 */
	private $ai_service;

	/**
	 * @var AIPS_Prompt_Builder_Consolidation
	 */
	private $prompt_builder;

	/**
	 * Constructor.
	 *
	 * @param AIPS_AI_Service_Interface|null         $ai_service     Optional AI service.
	 * @param AIPS_Prompt_Builder_Consolidation|null $prompt_builder Optional consolidation prompt builder.
	 */
	public function __construct(
		?AIPS_AI_Service_Interface $ai_service = null,
		?AIPS_Prompt_Builder_Consolidation $prompt_builder = null
	) {
		$container            = AIPS_Container::get_instance();
		$this->ai_service     = $ai_service ?: ($container->has(AIPS_AI_Service_Interface::class) ? $container->make(AIPS_AI_Service_Interface::class) : new AIPS_AI_Service());
		$this->prompt_builder = $prompt_builder ?: new AIPS_Prompt_Builder_Consolidation();
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_key(): string {
		return 'post_consolidation';
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_label(): string {
		return __('Post Consolidation', 'ai-post-scheduler');
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_strategy_key(): string {
		return 'consolidation';
	}

	/**
	 * {@inheritdoc}
	 */
	public function is_enabled(AIPS_Generation_Context $context): bool {
		return $context instanceof AIPS_Consolidation_Generation_Context;
	}

	/**
	 * {@inheritdoc}
	 */
	public function is_required(): bool {
		return true;
	}

	/**
	 * {@inheritdoc}
	 */
	public function build_prompt(AIPS_Generation_Context $context, ?AIPS_AI_Conversation $session = null): string {
		if (!($context instanceof AIPS_Consolidation_Generation_Context)) {
			return '';
		}

		$keep   = $context->get_keep_post();
		$retire = $context->get_retire_post();

		return $this->prompt_builder->build($keep, $retire, (string) get_permalink($retire), $context->get_instructions());
	}

	/**
	 * {@inheritdoc}
	 */
	public function generate(AIPS_Generation_Context $context, ?AIPS_AI_Conversation $session = null, array $options = array()) {
		$prompt = $this->build_prompt($context, $session);
		if ($prompt === '') {
			return new WP_Error('invalid_consolidation_context', __('Valid keep and retire posts are required for consolidation.', 'ai-post-scheduler'));
		}

		$response = $this->ai_service->generate_text($prompt, array(
			'temperature' => 0.4,
			'max_tokens'  => 8000,
		));

		if (is_wp_error($response)) {
			return $response;
		}

		$html = $this->sanitize_value($response);
		if (trim(wp_strip_all_tags($html)) === '') {
			return new WP_Error('aips_consolidation_empty_merge', __('The AI returned an empty article. Try again.', 'ai-post-scheduler'));
		}

		return $html;
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
	public function generate_variants($target_id, AIPS_Generation_Context $context, int $count = 3, array $options = array()) {
		return array();
	}

	/**
	 * {@inheritdoc}
	 */
	public function apply($target, $value): bool {
		$post_id = is_object($target) && isset($target->ID) ? absint($target->ID) : absint($target);
		if ($post_id <= 0 || !is_string($value)) {
			return false;
		}

		$updated = wp_update_post(array(
			'ID'           => $post_id,
			'post_content' => wp_slash($this->sanitize_value($value)),
		), true);

		return !is_wp_error($updated) && (int) $updated === $post_id;
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
	public function supports_ai_edit(): bool {
		return false;
	}

	/**
	 * {@inheritdoc}
	 */
	public function sanitize_value($value) {
		$html = trim((string) $value);
		$html = preg_replace('/^```(?:html)?\s*/i', '', $html);
		$html = preg_replace('/\s*```\s*$/', '', $html);
		// The kept post already has a title: drop a leading H1 the model repeats it in.
		$html = preg_replace('/^\s*<h1\b[^>]*>.*?<\/h1>\s*/is', '', (string) $html);
		// wp_kses_post() strips these tags but keeps their text, which would leave raw CSS/JS visible.
		$html = preg_replace('/<(script|style|iframe)\b[^>]*>.*?<\/\1\s*>/is', '', (string) $html);
		return trim(wp_kses_post(trim((string) $html)));
	}
}
