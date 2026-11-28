<?php
/**
 * Planner Topics Component
 *
 * Owns bulk planner topic brainstorming prompt assembly, AI generation,
 * JSON decoding, and line-by-line fallback parsing for AIPS_Planner_Topic_Strategy.
 *
 * @package AI_Post_Scheduler
 * @since   3.7.25
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Planner_Topics_Component
 */
class AIPS_Planner_Topics_Component implements AIPS_Generation_Component_Interface {

	/**
	 * @var AIPS_Generator|null
	 */
	private $generator;

	/**
	 * Constructor.
	 *
	 * @param AIPS_Generator|null $generator Optional generator instance.
	 */
	public function __construct(?AIPS_Generator $generator = null) {
		$this->generator = $generator;
	}

	/**
	 * Resolve generator lazily.
	 *
	 * @return AIPS_Generator
	 */
	private function get_generator(): AIPS_Generator {
		if ($this->generator === null) {
			$this->generator = new AIPS_Generator();
		}

		return $this->generator;
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
	public function get_strategy_key(): string {
		return 'planner_topics';
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
		return true;
	}

	/**
	 * {@inheritdoc}
	 */
	public function build_prompt(AIPS_Generation_Context $context, ?AIPS_AI_Conversation $session = null): string {
		$niche = ($context instanceof AIPS_Planner_Topic_Generation_Context) ? $context->get_niche() : (string) $context->get_topic();
		$count = ($context instanceof AIPS_Planner_Topic_Generation_Context) ? $context->get_count() : 10;

		$prompt  = "Generate a list of {$count} unique, engaging blog post titles/topics about '{$niche}'. \n";
		$prompt .= "Return ONLY a valid JSON array of strings. Do not include any other text, markdown formatting, or numbering. \n";
		$prompt .= 'Example: ["Topic 1", "Topic 2", "Topic 3"]';

		return $prompt;
	}

	/**
	 * {@inheritdoc}
	 */
	public function generate(AIPS_Generation_Context $context, ?AIPS_AI_Conversation $session = null, array $options = array()) {
		$generator = $this->get_generator();
		if (!$generator->is_available()) {
			return new WP_Error('ai_unavailable', __('AI Engine is not available.', 'ai-post-scheduler'));
		}

		$prompt = $this->build_prompt($context, $session);
		$result = $generator->generate_content($prompt, array('temperature' => 0.7), 'planner_topics');

		if (is_wp_error($result)) {
			return $result;
		}

		$raw_result = (string) $result;
		if ('' === trim($raw_result)) {
			return new WP_Error('empty_ai_response', __('AI did not return any topics. Please try again.', 'ai-post-scheduler'));
		}

		$topics = $this->sanitize_value($raw_result);
		if (empty($topics)) {
			$excerpt = substr(trim($raw_result), 0, 100);
			return new WP_Error(
				'parse_failed',
				sprintf(
					/* translators: %s: truncated AI response excerpt */
					__('Failed to parse AI response. Raw response: %s', 'ai-post-scheduler'),
					$excerpt
				)
			);
		}

		return $topics;
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
		return $this->generate($context, null, $options);
	}

	/**
	 * {@inheritdoc}
	 */
	public function apply($target, $value): bool {
		return is_array($value) && !empty($value);
	}

	/**
	 * {@inheritdoc}
	 */
	public function reconcile_status($target_id): bool {
		return true;
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
		if (is_array($value)) {
			return array_values(array_filter(array_map('trim', array_map('strval', $value))));
		}

		$json_str = trim((string) $value);
		$json_str = preg_replace('/^```json/', '', $json_str);
		$json_str = preg_replace('/^```/', '', $json_str);
		$json_str = preg_replace('/```$/', '', $json_str);
		$json_str = trim((string) $json_str);

		$decoded = AIPS_JSON_Extractor::decode_json_response($json_str);
		$topics  = is_wp_error($decoded) ? null : $decoded;

		if (!is_array($topics)) {
			$topics = array_values(array_filter(array_map('trim', explode("\n", $json_str))));
		}

		return is_array($topics) ? $topics : array();
	}
}
