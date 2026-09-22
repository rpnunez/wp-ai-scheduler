<?php
/**
 * Similarity Evaluator
 *
 * Centralized service for semantic similarity calculations, risk classification,
 * candidate vector matching, post duplicate risk evaluation, and author topic auto-approval.
 *
 * @package AI_Post_Scheduler
 * @since 3.7.0
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Similarity_Evaluator
 */
class AIPS_Similarity_Evaluator {

	/**
	 * @var AIPS_Config
	 */
	private $config;

	/**
	 * @var AIPS_Embeddings_Repository|null
	 */
	private $embeddings_repo;

	/**
	 * @var AIPS_Embeddings_Service|null
	 */
	private $embeddings_service;

	/**
	 * Constructor.
	 *
	 * @param AIPS_Config|null                $config             Config instance.
	 * @param AIPS_Embeddings_Repository|null $embeddings_repo    Embeddings repository.
	 * @param AIPS_Embeddings_Service|null    $embeddings_service Embeddings service.
	 */
	public function __construct(
		?AIPS_Config $config = null,
		?AIPS_Embeddings_Repository $embeddings_repo = null,
		?AIPS_Embeddings_Service $embeddings_service = null
	) {
		$container = AIPS_Container::get_instance();
		$this->config = $config ?: ($container->has(AIPS_Config::class) ? $container->make(AIPS_Config::class) : AIPS_Config::get_instance());
		$this->embeddings_repo = $embeddings_repo ?: ($container->has(AIPS_Embeddings_Repository::class) ? $container->make(AIPS_Embeddings_Repository::class) : null);
		$this->embeddings_service = $embeddings_service ?: ($container->has(AIPS_Embeddings_Service::class) ? $container->make(AIPS_Embeddings_Service::class) : null);
	}

	/**
	 * Normalize a raw similarity score and return a standardized evaluation payload.
	 *
	 * Risk tiers:
	 * - critical: score >= 0.90
	 * - high:     score >= 0.80
	 * - medium:   score >= 0.65
	 * - low:      score >= 0.50
	 * - clean:    score < 0.50
	 *
	 * @param float  $score   Cosine similarity score (0.0 to 1.0).
	 * @param string $context Evaluation context ('post', 'topic', 'author').
	 * @return array{
	 *     score: float,
	 *     percentage: int,
	 *     risk_tier: string,
	 *     risk_level: string,
	 *     risk_label: string,
	 *     badge_class: string,
	 *     topic_badge_class: string,
	 *     is_duplicate: bool,
	 *     threshold: float
	 * } Standardized evaluation DTO.
	 */
	public function evaluate_similarity(float $score, string $context = 'post'): array {
		$score = max(0.0, min(1.0, (float) $score));
		$pct   = (int) round($score * 100);

		if ($context === 'topic') {
			$raw_thresh = $this->config->get_option('aips_topic_similarity_threshold', 0.80);
			$threshold  = is_numeric($raw_thresh) ? (float) $raw_thresh : 0.80;
		} else {
			$raw_thresh = $this->config->get_option('aips_deduplication_threshold', 0.85);
			$threshold  = is_numeric($raw_thresh) ? (float) $raw_thresh : 0.85;
		}

		if ($score >= 0.90) {
			$tier        = 'critical';
			$label       = __('Critical Risk', 'ai-post-scheduler');
			$badge_class = 'aips-risk-critical';
		} elseif ($score >= 0.80) {
			$tier        = 'high';
			$label       = __('High Risk', 'ai-post-scheduler');
			$badge_class = 'aips-risk-high';
		} elseif ($score >= 0.65) {
			$tier        = 'medium';
			$label       = __('Moderate Risk', 'ai-post-scheduler');
			$badge_class = 'aips-risk-medium';
		} elseif ($score >= 0.50) {
			$tier        = 'low';
			$label       = __('Low Risk', 'ai-post-scheduler');
			$badge_class = 'aips-risk-low';
		} else {
			$tier        = 'clean';
			$label       = __('Clean', 'ai-post-scheduler');
			$badge_class = 'aips-risk-clean';
		}

		// Topic-specific inline badge CSS
		if ($pct > 75) {
			$topic_badge_class = 'aips-topic-similarity-high';
		} elseif ($pct > 50) {
			$topic_badge_class = 'aips-topic-similarity-medium';
		} else {
			$topic_badge_class = 'aips-topic-similarity-low';
		}

		return array(
			'score'             => $score,
			'percentage'        => $pct,
			'risk_tier'         => $tier,
			'risk_level'        => $tier,
			'risk_label'        => $label,
			'badge_class'       => $badge_class,
			'topic_badge_class' => $topic_badge_class,
			'is_duplicate'      => ($score >= $threshold),
			'threshold'         => $threshold,
		);
	}

	/**
	 * Compute cosine similarity between two vector arrays.
	 *
	 * @param array $vec_a First vector (float[]).
	 * @param array $vec_b Second vector (float[]).
	 * @return float Cosine similarity score between -1.0 and 1.0 (clamped 0.0 to 1.0 for normalized embeddings).
	 */
	public function cosine_similarity(array $vec_a, array $vec_b): float {
		$count_a = count($vec_a);
		$count_b = count($vec_b);

		if ($count_a === 0 || $count_a !== $count_b) {
			return 0.0;
		}

		$dot_product = 0.0;
		$norm_a      = 0.0;
		$norm_b      = 0.0;

		for ($i = 0; $i < $count_a; $i++) {
			$val_a        = (float) $vec_a[$i];
			$val_b        = (float) $vec_b[$i];
			$dot_product += $val_a * $val_b;
			$norm_a      += $val_a * $val_a;
			$norm_b      += $val_b * $val_b;
		}

		$divisor = sqrt($norm_a) * sqrt($norm_b);
		if ($divisor < 1e-9) {
			return 0.0;
		}

		$sim = $dot_product / $divisor;
		return (float) max(0.0, min(1.0, $sim));
	}

	/**
	 * Find top matching candidates for a source vector.
	 *
	 * Deduplicates candidate iteration and scoring across Deduplication,
	 * Content Indexer, and Topic Expansion services.
	 *
	 * @param array  $source_vector Source vector array.
	 * @param array  $candidates    List of candidate records or arrays containing embeddings.
	 * @param float  $min_threshold Minimum similarity threshold to include.
	 * @param int    $limit         Maximum number of top matches to return.
	 * @param string $context      Context for evaluation ('post', 'topic').
	 * @return array<int, array<string, mixed>> Sorted list of matches.
	 */
	public function find_top_matches(
		array $source_vector,
		array $candidates,
		float $min_threshold = 0.0,
		int $limit = 5,
		string $context = 'post'
	): array {
		if (empty($source_vector) || empty($candidates)) {
			return array();
		}

		$dimensions = count($source_vector);
		$matches    = array();

		foreach ($candidates as $cand) {
			$cand_vec = array();
			$cand_id  = 0;
			$title    = '';
			$type     = $context;

			if (is_object($cand)) {
				$cand_id = isset($cand->object_id) ? (int) $cand->object_id : (isset($cand->id) ? (int) $cand->id : 0);
				$type    = isset($cand->object_type) ? (string) $cand->object_type : $context;
				$title   = isset($cand->title) ? (string) $cand->title : (isset($cand->topic_title) ? (string) $cand->topic_title : '');

				if (isset($cand->embedding)) {
					$cand_vec = is_array($cand->embedding)
						? $cand->embedding
						: ($this->embeddings_repo ? $this->embeddings_repo->decode_embedding($cand->embedding, $type, $cand_id, isset($cand->content_hash) ? $cand->content_hash : '') : array());
				}
			} elseif (is_array($cand)) {
				$cand_id = isset($cand['object_id']) ? (int) $cand['object_id'] : (isset($cand['id']) ? (int) $cand['id'] : 0);
				$type    = isset($cand['object_type']) ? (string) $cand['object_type'] : (isset($cand['type']) ? (string) $cand['type'] : $context);
				$title   = isset($cand['title']) ? (string) $cand['title'] : (isset($cand['topic_title']) ? (string) $cand['topic_title'] : '');

				if (isset($cand['embedding'])) {
					$cand_vec = is_array($cand['embedding'])
						? $cand['embedding']
						: ($this->embeddings_repo ? $this->embeddings_repo->decode_embedding($cand['embedding'], $type, $cand_id, isset($cand['content_hash']) ? $cand['content_hash'] : '') : array());
				}
			}

			if (empty($cand_vec) || count($cand_vec) !== $dimensions) {
				continue;
			}

			$sim = $this->cosine_similarity($source_vector, $cand_vec);
			if ($sim >= $min_threshold) {
				$eval = $this->evaluate_similarity($sim, $context);
				$matches[] = array(
					'id'         => $cand_id,
					'type'       => $type,
					'title'      => $title,
					'similarity' => $sim,
					'score'      => $sim,
					'evaluation' => $eval,
					'candidate'  => $cand,
				);
			}
		}

		usort($matches, function ($a, $b) {
			return $b['similarity'] <=> $a['similarity'];
		});

		return array_slice($matches, 0, max(1, $limit));
	}

	/**
	 * Evaluate author topic auto-approval rules and return the decision and metadata.
	 *
	 * Decouples auto-approval logic from AIPS_Author_Topics_Generator and centralizes
	 * quality score thresholds, dual-boundary semantic gating, and fallback resolution.
	 *
	 * @param array      $topic               Topic data array.
	 * @param object     $author              Author configuration object.
	 * @param array|null $author_baseline_vec Optional composite author baseline vector.
	 * @return array{
	 *     qualifies: bool,
	 *     decision: string,
	 *     reason: string,
	 *     note: string,
	 *     is_dup_rejection: bool,
	 *     meta: array<string, mixed>
	 * } Evaluation result.
	 */
	public function evaluate_author_topic_auto_approval(array $topic, object $author, ?array $author_baseline_vec = null): array {
		$meta = isset($topic['metadata']) && is_string($topic['metadata'])
			? json_decode($topic['metadata'], true)
			: (isset($topic['metadata']) && is_array($topic['metadata']) ? $topic['metadata'] : array());
		if (!is_array($meta)) {
			$meta = array();
		}

		$mode           = !empty($author->auto_approval_mode) ? (string) $author->auto_approval_mode : 'none';
		$min_score      = isset($author->auto_approval_min_score) ? (int) $author->auto_approval_min_score : 70;
		$min_relevance  = isset($author->auto_approval_min_relevance) ? (float) $author->auto_approval_min_relevance : 0.70;
		$max_similarity = isset($author->auto_approval_max_similarity) ? (float) $author->auto_approval_max_similarity : 0.85;
		$fallback       = !empty($author->auto_approval_fallback) ? (string) $author->auto_approval_fallback : 'pending';

		$qualifies        = false;
		$is_dup_rejection = false;
		$reason           = '';
		$note             = '';

		switch ($mode) {
			case 'all':
				$qualifies = true;
				$reason    = 'auto_approve_all';
				$note      = __('Auto-approved: Policy is set to auto-approve all topics.', 'ai-post-scheduler');
				break;

			case 'score':
				$score = isset($topic['score']) ? (int) $topic['score'] : 50;
				if ($score >= $min_score) {
					$qualifies = true;
					$reason    = 'quality_score_threshold_met';
					$note      = sprintf(
						/* translators: 1: topic quality score, 2: minimum required score */
						__('Auto-approved: Quality score %1$d met or exceeded minimum threshold of %2$d.', 'ai-post-scheduler'),
						$score,
						$min_score
					);
				} else {
					$qualifies = false;
					$reason    = 'quality_score_below_threshold';
					$note      = sprintf(
						/* translators: 1: topic quality score, 2: minimum required score */
						__('Did not qualify: Quality score %1$d is below minimum threshold of %2$d.', 'ai-post-scheduler'),
						$score,
						$min_score
					);
				}
				$meta['auto_approval_score']     = $score;
				$meta['auto_approval_min_score'] = $min_score;
				break;

			case 'similarity':
			case 'embeddings':
				$dup_sim = isset($meta['duplicate_similarity'])
					? (float) $meta['duplicate_similarity']
					: (!empty($meta['potential_duplicate']) ? 1.0 : 0.0);

				$rel_sim = 0.70;
				$topic_title = isset($topic['topic_title']) ? (string) $topic['topic_title'] : '';

				if (!empty($author_baseline_vec) && !empty($topic_title) && $this->embeddings_service && $this->embeddings_service->is_enabled()) {
					$tvec = $this->embeddings_service->generate_embedding($topic_title);
					if (!is_wp_error($tvec) && is_array($tvec) && count($tvec) === count($author_baseline_vec)) {
						$rel_sim = $this->cosine_similarity($tvec, $author_baseline_vec);
					}
				}

				$meta['auto_approval_relevance']      = round($rel_sim, 4);
				$meta['auto_approval_duplicate_sim']  = round($dup_sim, 4);
				$meta['auto_approval_min_relevance']  = round($min_relevance, 4);
				$meta['auto_approval_max_similarity'] = round($max_similarity, 4);

				// Dual-boundary semantic evaluation
				if ($dup_sim >= $max_similarity) {
					$qualifies        = false;
					$is_dup_rejection = true;
					$reason           = 'rejected_duplicate_cannibalization';
					$note             = sprintf(
						/* translators: 1: duplicate percentage, 2: max threshold percentage */
						__('Auto-rejected: Duplicate similarity %.1f%% met or exceeded maximum threshold of %.1f%%.', 'ai-post-scheduler'),
						$dup_sim * 100,
						$max_similarity * 100
					);
				} elseif ($rel_sim < $min_relevance) {
					$qualifies        = false;
					$is_dup_rejection = false;
					$reason           = 'low_niche_relevance';
					$note             = sprintf(
						/* translators: 1: niche relevance percentage, 2: min required percentage */
						__('Did not qualify: Niche relevance %.1f%% is below minimum required %.1f%%.', 'ai-post-scheduler'),
						$rel_sim * 100,
						$min_relevance * 100
					);
				} else {
					$qualifies = true;
					$reason    = 'semantic_dual_gate_passed';
					$note      = sprintf(
						/* translators: 1: niche relevance percentage, 2: duplicate similarity percentage */
						__('Auto-approved: Passed semantic gate (Relevance: %.1f%%, Duplicate: %.1f%%).', 'ai-post-scheduler'),
						$rel_sim * 100,
						$dup_sim * 100
					);
				}
				break;

			default:
				$qualifies = false;
				$reason    = 'manual_review_required';
				$note      = __('Held for review: Author auto-approval mode is set to manual.', 'ai-post-scheduler');
				break;
		}

		if ($qualifies) {
			$decision                     = 'approved';
			$meta['auto_approved']        = true;
			$meta['auto_approval_rule']   = $mode;
			$meta['auto_approval_reason'] = $reason;
			$meta['auto_approval_note']   = $note;
		} else {
			$should_reject = ($fallback === 'rejected') || ($fallback === 'smart_split' && $is_dup_rejection);
			$decision      = $should_reject ? 'rejected' : 'pending';

			$meta['auto_approved']        = false;
			$meta['auto_approval_rule']   = $mode;
			$meta['auto_approval_reason'] = $reason;
			$meta['auto_approval_note']   = $note;
		}

		return array(
			'qualifies'        => $qualifies,
			'decision'         => $decision,
			'reason'           => $reason,
			'note'             => $note,
			'is_dup_rejection' => $is_dup_rejection,
			'meta'             => $meta,
		);
	}

	/**
	 * Evaluate post duplicate risk and return top duplicates with formatted evaluation DTOs.
	 *
	 * @param int $post_id WordPress post ID.
	 * @return array<string, mixed> Evaluated post duplicate risk summary.
	 */
	public function evaluate_post_duplicate_risk(int $post_id): array {
		$post = get_post($post_id);
		if (!$post) {
			return array(
				'post_id'            => $post_id,
				'max_similarity'     => 0.0,
				'max_similarity_pct' => 0,
				'overall_risk'       => 'clean',
				'overall_label'      => __('Clean', 'ai-post-scheduler'),
				'badge_class'        => 'aips-risk-clean',
				'top_duplicates'     => array(),
			);
		}

		$raw_duplicates = array();
		if ($this->embeddings_repo) {
			$emb = $this->embeddings_repo->get_by_post_id($post_id);
			if (!$emb) {
				return array(
					'post_id'            => $post_id,
					'max_similarity'     => 0.0,
					'max_similarity_pct' => 0,
					'overall_risk'       => 'unindexed',
					'overall_label'      => __('Unindexed', 'ai-post-scheduler'),
					'badge_class'        => 'aips-risk-unindexed',
					'top_duplicates'     => array(),
				);
			}

			// Check relationships table if available
			$rel_repo = AIPS_Container::get_instance()->has(AIPS_Relationships_Repository::class)
				? AIPS_Container::get_instance()->make(AIPS_Relationships_Repository::class)
				: null;

			if ($rel_repo) {
				$raw_duplicates = $rel_repo->get_top_duplicates($post_id, 5);
			}
		}

		$top_duplicates = array();
		$max_similarity = 0.0;

		foreach ($raw_duplicates as $d) {
			$m_id = (int) $d->matched_id;
			if (!get_post($m_id)) {
				continue;
			}

			$sim = (float) $d->similarity;
			if ($sim > $max_similarity) {
				$max_similarity = $sim;
			}

			$eval = $this->evaluate_similarity($sim, 'post');

			$top_duplicates[] = array(
				'post_id'        => $m_id,
				'title'          => get_the_title($m_id),
				'url'            => get_permalink($m_id),
				'edit_url'       => get_edit_post_link($m_id, ''),
				'post_date'      => get_the_date('', $m_id),
				'similarity'     => $sim,
				'similarity_pct' => $eval['percentage'],
				'risk_level'     => $eval['risk_tier'],
				'risk_label'     => $eval['risk_label'],
				'badge_class'    => $eval['badge_class'],
			);
		}

		$overall_eval = $this->evaluate_similarity($max_similarity, 'post');

		return array(
			'post_id'            => $post_id,
			'title'              => get_the_title($post_id),
			'post_type'          => $post->post_type,
			'post_date'          => get_the_date('', $post_id),
			'max_similarity'     => $max_similarity,
			'max_similarity_pct' => $overall_eval['percentage'],
			'overall_risk'       => $overall_eval['risk_tier'],
			'overall_label'      => $overall_eval['risk_label'],
			'badge_class'        => $overall_eval['badge_class'],
			'top_duplicates'     => $top_duplicates,
		);
	}
}
