<?php
/**
 * Topic Freshness Research Component
 *
 * Evaluates topic timeliness and seasonal relevance based on current year,
 * temporal indicators, and seasonal keywords within AIPS_Research_Generation_Strategy.
 *
 * @package AI_Post_Scheduler
 * @since   3.7.25
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Topic_Freshness_Component
 */
class AIPS_Topic_Freshness_Component implements AIPS_Generation_Component_Interface {

	/**
	 * {@inheritdoc}
	 */
	public function get_key(): string {
		return 'topic_freshness';
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_label(): string {
		return __('Topic Freshness', 'ai-post-scheduler');
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
			return $context->get_mode() === AIPS_Research_Generation_Context::MODE_FRESHNESS;
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
		$topic = isset($options['topic']) && is_array($options['topic'])
			? $options['topic']
			: (($context instanceof AIPS_Research_Generation_Context) ? $context->get_topic_payload() : array());

		return $this->analyze($topic);
	}

	/**
	 * Analyze topic freshness based on temporal and seasonal indicators.
	 *
	 * @param array $topic Topic data containing 'topic' and optional 'keywords'.
	 * @return array{score:int,indicators:string[],is_fresh:bool}
	 */
	public function analyze(array $topic): array {
		$freshness_score = 50;
		$indicators      = array();

		$topic_text = isset($topic['topic']) ? strtolower((string) $topic['topic']) : '';
		$keywords   = isset($topic['keywords']) && is_array($topic['keywords']) ? array_map('strtolower', $topic['keywords']) : array();
		$all_text   = $topic_text . ' ' . implode(' ', $keywords);

		$current_year = AIPS_DateTime::now()->toDisplay('Y');
		if (strpos($all_text, $current_year) !== false) {
			$freshness_score += 20;
			$indicators[]     = "Mentions current year ({$current_year})";
		}

		$temporal_words = array('now', 'today', 'current', 'latest', 'new', 'trending', 'this year');
		foreach ($temporal_words as $word) {
			if (strpos($all_text, $word) !== false) {
				$freshness_score += 10;
				$indicators[]     = "Contains temporal indicator: {$word}";
				break;
			}
		}

		$current_month   = (int) AIPS_DateTime::now()->toDisplay('n');
		$seasonal_months = array(
			array(
				'months' => array(12, 1, 2),
				'terms'  => array('winter', 'holiday', 'christmas', 'new year'),
			),
			array(
				'months' => array(3, 4, 5),
				'terms'  => array('spring', 'easter', 'tax'),
			),
			array(
				'months' => array(6, 7, 8),
				'terms'  => array('summer', 'vacation', 'back to school'),
			),
			array(
				'months' => array(9, 10, 11),
				'terms'  => array('fall', 'autumn', 'halloween', 'thanksgiving', 'black friday'),
			),
		);

		foreach ($seasonal_months as $group) {
			if (in_array($current_month, $group['months'], true)) {
				foreach ($group['terms'] as $term) {
					if (strpos($all_text, $term) !== false) {
						$freshness_score += 15;
						$indicators[]     = "Seasonally relevant: {$term}";
						break 2;
					}
				}
			}
		}

		$freshness_score = min(100, $freshness_score);

		return array(
			'score'      => $freshness_score,
			'indicators' => $indicators,
			'is_fresh'   => $freshness_score >= 70,
		);
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
		return is_array($value);
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
		return is_array($value) ? $value : array();
	}
}
