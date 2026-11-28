<?php
/**
 * Topic Comparison Research Component
 *
 * Compares two candidate topics for relevance based on scores and keyword
 * coverage within AIPS_Research_Generation_Strategy.
 *
 * @package AI_Post_Scheduler
 * @since   3.7.25
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Topic_Comparison_Component
 */
class AIPS_Topic_Comparison_Component implements AIPS_Generation_Component_Interface {

	/**
	 * {@inheritdoc}
	 */
	public function get_key(): string {
		return 'topic_comparison';
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_label(): string {
		return __('Topic Comparison', 'ai-post-scheduler');
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_strategy_key(): string {
		return 'research';
	}

	/**
	 * {@inheritdoc}
	 */
	public function is_enabled(AIPS_Generation_Context $context): bool {
		if ($context instanceof AIPS_Research_Generation_Context) {
			return $context->get_mode() === AIPS_Research_Generation_Context::MODE_COMPARISON;
		}

		return false;
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
	public function build_prompt(AIPS_Generation_Context $context, ?AIPS_AI_Conversation $session = null): string {
		return '';
	}

	/**
	 * {@inheritdoc}
	 */
	public function generate(AIPS_Generation_Context $context, ?AIPS_AI_Conversation $session = null, array $options = array()) {
		$pair = isset($options['comparison_topics']) && is_array($options['comparison_topics'])
			? $options['comparison_topics']
			: (($context instanceof AIPS_Research_Generation_Context) ? $context->get_comparison_topics() : array());

		$topic1 = isset($pair[0]) && is_array($pair[0]) ? $pair[0] : array();
		$topic2 = isset($pair[1]) && is_array($pair[1]) ? $pair[1] : array();

		return $this->compare($topic1, $topic2);
	}

	/**
	 * Compare two topics for relevance based on score and keyword count.
	 *
	 * @param array $topic1 First topic.
	 * @param array $topic2 Second topic.
	 * @return int Comparison result (-1, 0, 1).
	 */
	public function compare(array $topic1, array $topic2): int {
		$score1 = isset($topic1['score']) ? $topic1['score'] : 0;
		$score2 = isset($topic2['score']) ? $topic2['score'] : 0;

		if ($score1 !== $score2) {
			return $score2 <=> $score1;
		}

		$keywords1 = isset($topic1['keywords']) && is_array($topic1['keywords']) ? count($topic1['keywords']) : 0;
		$keywords2 = isset($topic2['keywords']) && is_array($topic2['keywords']) ? count($topic2['keywords']) : 0;

		return $keywords1 <=> $keywords2;
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
		return is_int($value);
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
		return (int) $value;
	}
}
