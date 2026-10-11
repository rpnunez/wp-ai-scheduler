<?php
/**
 * Author Topic Approval Policy
 *
 * Single resolver for the effective topic auto-approval policy of an author.
 * Authors set to "inherit" (or with no mode) use the global Settings → Authors
 * options; otherwise the author's own columns apply. Legacy value spellings are
 * normalized so every caller sees one vocabulary.
 *
 * @package AI_Post_Scheduler
 * @since 3.7.12
 */

if (!defined('ABSPATH')) {
	exit;
}

class AIPS_Author_Topic_Approval_Policy {

	const MODES     = array('manual', 'all', 'score', 'similarity');
	const FALLBACKS = array('pending', 'rejected', 'smart_split');

	const DEFAULT_MIN_SCORE      = 70;
	const DEFAULT_MIN_RELEVANCE  = 65;
	const DEFAULT_MAX_SIMILARITY = 0.85;

	/**
	 * Normalize a mode value to the canonical vocabulary.
	 *
	 * @param mixed $mode          Raw value.
	 * @param bool  $allow_inherit Whether 'inherit' is a valid result.
	 * @return string One of MODES (or 'inherit'); unknown values become 'manual'.
	 */
	public static function normalize_mode($mode, $allow_inherit = false) {
		$mode = sanitize_key((string) $mode);

		if ($mode === 'embeddings') {
			$mode = 'similarity';
		} elseif ($mode === 'none') {
			$mode = 'manual';
		}

		if ($allow_inherit && $mode === 'inherit') {
			return 'inherit';
		}

		return in_array($mode, self::MODES, true) ? $mode : 'manual';
	}

	/**
	 * Normalize a fallback value to the canonical vocabulary.
	 *
	 * @param mixed $fallback Raw value.
	 * @return string One of FALLBACKS; unknown values become 'pending' (safe).
	 */
	public static function normalize_fallback($fallback) {
		$fallback = sanitize_key((string) $fallback);

		if ($fallback === 'reject') {
			$fallback = 'rejected';
		}

		return in_array($fallback, self::FALLBACKS, true) ? $fallback : 'pending';
	}

	/**
	 * Resolve the effective policy for an author.
	 *
	 * `min_relevance` is returned as a fraction (0.01–1.0); the stored value
	 * is a percentage (1–100).
	 *
	 * @param object $author Author row.
	 * @return array{mode:string,min_score:int,min_relevance:float,max_similarity:float,fallback:string,source:string}
	 */
	public static function resolve(object $author): array {
		$author_mode = empty($author->topic_auto_approval_mode)
			? 'inherit'
			: self::normalize_mode($author->topic_auto_approval_mode, true);

		if ($author_mode === 'inherit') {
			$config = AIPS_Config::get_instance();

			return self::build(
				self::normalize_mode($config->get_option('aips_author_topic_auto_approval_mode', 'manual')),
				$config->get_option('aips_author_topic_auto_approval_min_score', self::DEFAULT_MIN_SCORE),
				$config->get_option('aips_author_topic_auto_approval_min_relevance', self::DEFAULT_MIN_RELEVANCE),
				$config->get_option('aips_author_topic_auto_approval_max_similarity', self::DEFAULT_MAX_SIMILARITY),
				$config->get_option('aips_author_topic_auto_approval_fallback', 'smart_split'),
				'global'
			);
		}

		return self::build(
			$author_mode,
			isset($author->topic_auto_approval_min_score) ? $author->topic_auto_approval_min_score : self::DEFAULT_MIN_SCORE,
			isset($author->topic_auto_approval_min_relevance) ? $author->topic_auto_approval_min_relevance : self::DEFAULT_MIN_RELEVANCE,
			isset($author->topic_auto_approval_max_similarity) ? $author->topic_auto_approval_max_similarity : self::DEFAULT_MAX_SIMILARITY,
			!empty($author->topic_auto_approval_fallback) ? $author->topic_auto_approval_fallback : 'pending',
			'author'
		);
	}

	/**
	 * Build a clamped, normalized policy array.
	 *
	 * @return array
	 */
	private static function build($mode, $min_score, $min_relevance_pct, $max_similarity, $fallback, $source): array {
		return array(
			'mode'           => $mode,
			'min_score'      => max(1, min(100, (int) $min_score)),
			'min_relevance'  => max(1, min(100, (int) $min_relevance_pct)) / 100,
			'max_similarity' => max(0.50, min(0.99, (float) $max_similarity)),
			'fallback'       => self::normalize_fallback($fallback),
			'source'         => $source,
		);
	}

	/**
	 * Human-readable summary of the current global policy.
	 *
	 * @return string Plain text (escape on output).
	 */
	public static function describe_global(): string {
		$policy = self::resolve((object) array('topic_auto_approval_mode' => 'inherit'));

		switch ($policy['mode']) {
			case 'all':
				$summary = __('Auto-approve all topics', 'ai-post-scheduler');
				break;
			case 'score':
				/* translators: %d: minimum quality score */
				$summary = sprintf(__('Approve topics with quality score of at least %d', 'ai-post-scheduler'), $policy['min_score']);
				break;
			case 'similarity':
				$summary = sprintf(
					/* translators: 1: minimum niche relevance percent, 2: maximum duplicate similarity percent */
					__('Semantic gate (relevance at least %1$d%%, duplicate similarity below %2$d%%)', 'ai-post-scheduler'),
					(int) round($policy['min_relevance'] * 100),
					(int) round($policy['max_similarity'] * 100)
				);
				break;
			default:
				return __('Manual review (topics stay pending)', 'ai-post-scheduler');
		}

		$fallbacks = array(
			'pending'     => __('others stay pending', 'ai-post-scheduler'),
			'rejected'    => __('others are rejected', 'ai-post-scheduler'),
			'smart_split' => __('duplicates are rejected, others stay pending', 'ai-post-scheduler'),
		);

		return $summary . '; ' . $fallbacks[$policy['fallback']];
	}
}
