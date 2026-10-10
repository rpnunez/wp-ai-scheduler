<?php
/**
 * Auto-link Policy
 *
 * Decides what bulk auto-linking does with one internal link suggestion:
 * apply it automatically, queue it for human review, or skip it. Pure logic
 * over the "Internal Link Automation" settings; callers supply the counts
 * (links already in the post, links added this run, ...).
 *
 * @package AI_Post_Scheduler
 * @since 3.7.4
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Autolink_Policy
 */
class AIPS_Autolink_Policy {

	/**
	 * Insert the link automatically.
	 */
	const DECISION_APPLY = 'apply';

	/**
	 * Queue the link for human review.
	 */
	const DECISION_REVIEW = 'review';

	/**
	 * Do not insert the link.
	 */
	const DECISION_SKIP = 'skip';

	/**
	 * @var AIPS_Config
	 */
	private $config;

	/**
	 * @var array|null Memoized normalized settings.
	 */
	private $settings = null;

	/**
	 * @param AIPS_Config|null $config Config instance.
	 */
	public function __construct(?AIPS_Config $config = null) {
		$this->config = $config ?: AIPS_Config::get_instance();
	}

	/**
	 * Whether bulk auto-linking is enabled.
	 *
	 * @return bool
	 */
	public function is_enabled(): bool {
		return (bool) $this->config->get_option('aips_autolink_enabled', false);
	}

	/**
	 * Normalized auto-link settings.
	 *
	 * Thresholds are clamped to 0.50 - 1.00 and the review threshold never
	 * exceeds the auto-apply threshold; limits are at least 1.
	 *
	 * @return array{auto_apply_threshold:float, review_threshold:float, max_links_per_post:int, max_total_internal_per_post:int, max_inbound_per_target:int, skip_first_paragraph:bool, rel:string, target_blank:bool}
	 */
	public function get_settings(): array {
		if ($this->settings !== null) {
			return $this->settings;
		}

		$auto   = $this->clamp_threshold($this->config->get_option('aips_autolink_auto_apply_threshold', 0.85), 0.85);
		$review = $this->clamp_threshold($this->config->get_option('aips_autolink_review_threshold', 0.70), 0.70);

		$rel = (string) $this->config->get_option('aips_autolink_rel', '');
		if (!in_array($rel, array('nofollow', 'sponsored', 'ugc'), true)) {
			$rel = '';
		}

		$this->settings = array(
			'auto_apply_threshold'        => $auto,
			'review_threshold'            => min($review, $auto),
			'max_links_per_post'          => max(1, (int) $this->config->get_option('aips_autolink_max_links_per_post', 3)),
			'max_total_internal_per_post' => max(1, (int) $this->config->get_option('aips_autolink_max_total_internal_per_post', 15)),
			'max_inbound_per_target'      => max(1, (int) $this->config->get_option('aips_autolink_max_inbound_per_target', 5)),
			'skip_first_paragraph'        => (bool) $this->config->get_option('aips_autolink_skip_first_paragraph', true),
			'rel'                         => $rel,
			'target_blank'                => (bool) $this->config->get_option('aips_autolink_target_blank', false),
		);

		return $this->settings;
	}

	/**
	 * Decide what to do with one suggestion.
	 *
	 * Suggestion keys:
	 * - source_post_id  int   Post that would receive the link.
	 * - target_post_id  int   Post being linked to.
	 * - confidence      float 0-1 score.
	 * - has_anchor      bool  Whether a placement/anchor was found in the source. Default true.
	 * - paragraph_index int   Paragraph of the anchor (-1 when outside a <p>). Optional.
	 *
	 * Context keys (all optional, default 0/false):
	 * - source_links_to_target  bool Source already links to target.
	 * - source_internal_links   int  Internal links already in the source post.
	 * - source_new_links        int  Links added to the source during this run.
	 * - target_new_inbound      int  Inbound links added to the target during this run.
	 *
	 * @param array $suggestion Suggestion data.
	 * @param array $context    Counts for the current run.
	 * @return array{decision:string, reason:string, note:string, confidence:float}
	 */
	public function evaluate(array $suggestion, array $context = array()): array {
		$settings   = $this->get_settings();
		$source     = isset($suggestion['source_post_id']) ? (int) $suggestion['source_post_id'] : 0;
		$target     = isset($suggestion['target_post_id']) ? (int) $suggestion['target_post_id'] : 0;
		$confidence = isset($suggestion['confidence']) ? max(0.0, min(1.0, (float) $suggestion['confidence'])) : 0.0;
		$has_anchor = !array_key_exists('has_anchor', $suggestion) || !empty($suggestion['has_anchor']);

		if ($source <= 0 || $target <= 0) {
			return $this->decision(self::DECISION_SKIP, 'invalid_suggestion', __('Suggestion is missing its source or target post.', 'ai-post-scheduler'), $confidence);
		}

		if ($source === $target) {
			return $this->decision(self::DECISION_SKIP, 'self_link', __('A post cannot link to itself.', 'ai-post-scheduler'), $confidence);
		}

		if (!empty($context['source_links_to_target'])) {
			return $this->decision(self::DECISION_SKIP, 'already_linked', __('The source post already links to the target.', 'ai-post-scheduler'), $confidence);
		}

		if ($confidence < $settings['review_threshold']) {
			return $this->decision(
				self::DECISION_SKIP,
				'below_review_threshold',
				sprintf(
					/* translators: 1: confidence score, 2: review threshold */
					__('Confidence %1$.2f is below the review threshold %2$.2f.', 'ai-post-scheduler'),
					$confidence,
					$settings['review_threshold']
				),
				$confidence
			);
		}

		if ($this->context_int($context, 'source_internal_links') >= $settings['max_total_internal_per_post']) {
			return $this->decision(self::DECISION_SKIP, 'source_link_cap', __('The source post already has the maximum number of internal links.', 'ai-post-scheduler'), $confidence);
		}

		if ($this->context_int($context, 'source_new_links') >= $settings['max_links_per_post']) {
			return $this->decision(self::DECISION_SKIP, 'source_run_cap', __('This run already added the maximum number of links to the source post.', 'ai-post-scheduler'), $confidence);
		}

		if ($this->context_int($context, 'target_new_inbound') >= $settings['max_inbound_per_target']) {
			return $this->decision(self::DECISION_SKIP, 'target_run_cap', __('This run already added the maximum number of inbound links to the target post.', 'ai-post-scheduler'), $confidence);
		}

		if ($settings['skip_first_paragraph'] && isset($suggestion['paragraph_index']) && (int) $suggestion['paragraph_index'] === 0) {
			return $this->decision(self::DECISION_SKIP, 'first_paragraph', __('Links are not placed in the first paragraph.', 'ai-post-scheduler'), $confidence);
		}

		if (!$has_anchor) {
			return $this->decision(self::DECISION_REVIEW, 'no_anchor', __('No suitable anchor text was found; choose a placement manually.', 'ai-post-scheduler'), $confidence);
		}

		if ($confidence >= $settings['auto_apply_threshold']) {
			return $this->decision(self::DECISION_APPLY, 'above_auto_threshold', __('Confidence meets the auto-apply threshold.', 'ai-post-scheduler'), $confidence);
		}

		return $this->decision(self::DECISION_REVIEW, 'review_band', __('Confidence is between the review and auto-apply thresholds.', 'ai-post-scheduler'), $confidence);
	}

	/**
	 * Attributes for AIPS_Link_Insertion_Engine::insert().
	 *
	 * @return array{rel?:string, target?:string}
	 */
	public function get_link_attributes(): array {
		$settings   = $this->get_settings();
		$attributes = array();

		if ($settings['rel'] !== '') {
			$attributes['rel'] = $settings['rel'];
		}

		if ($settings['target_blank']) {
			$attributes['target'] = '_blank';
		}

		return $attributes;
	}

	/**
	 * Options for AIPS_Link_Insertion_Engine::find_phrase_occurrences().
	 *
	 * @return array{skip_first_paragraph:bool}
	 */
	public function get_engine_options(): array {
		return array(
			'skip_first_paragraph' => $this->get_settings()['skip_first_paragraph'],
		);
	}

	/**
	 * Build a decision array.
	 *
	 * @param string $decision   Decision constant.
	 * @param string $reason     Machine-readable reason code.
	 * @param string $note       Human-readable explanation.
	 * @param float  $confidence Clamped confidence.
	 * @return array
	 */
	private function decision(string $decision, string $reason, string $note, float $confidence): array {
		return array(
			'decision'   => $decision,
			'reason'     => $reason,
			'note'       => $note,
			'confidence' => $confidence,
		);
	}

	/**
	 * Clamp a threshold option to 0.50 - 1.00.
	 *
	 * @param mixed $value    Raw option value.
	 * @param float $fallback Value used when non-numeric.
	 * @return float
	 */
	private function clamp_threshold($value, float $fallback): float {
		if (!is_numeric($value)) {
			return $fallback;
		}
		return min(1.0, max(0.5, (float) $value));
	}

	/**
	 * Read a non-negative integer from the context.
	 *
	 * @param array  $context Context array.
	 * @param string $key     Key.
	 * @return int
	 */
	private function context_int(array $context, string $key): int {
		return isset($context[$key]) ? max(0, (int) $context[$key]) : 0;
	}
}
