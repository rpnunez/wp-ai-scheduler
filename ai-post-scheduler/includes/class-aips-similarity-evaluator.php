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
	 * @var AIPS_Relationships_Repository|null
	 */
	private $relationships_repo;

	/**
	 * @var AIPS_AI_Service_Interface|null
	 */
	private $ai_service;

	/**
	 * @var AIPS_Deduplication_Service|null
	 */
	private $deduplication_service;

	/**
	 * @var AIPS_Logger_Interface|null
	 */
	private $logger;

	/**
	 * Default minimum similarity score for internal links suggestions.
	 *
	 * @var float
	 */
	const DEFAULT_INTERNAL_LINKS_THRESHOLD = 0.70;

	/**
	 * Default maximum number of suggestions per source entity.
	 *
	 * @var int
	 */
	const DEFAULT_MAX_SUGGESTIONS = 5;

	/**
	 * Default post duplicate risk threshold.
	 *
	 * @var float
	 */
	const DEFAULT_POST_THRESHOLD = 0.85;

	/**
	 * Default topic similarity threshold.
	 *
	 * @var float
	 */
	const DEFAULT_TOPIC_THRESHOLD = 0.80;

	/**
	 * Palette of distinct cluster colors for graph visualization.
	 *
	 * @var string[]
	 */
	private static $cluster_palette = array(
		'#2563eb', // Blue
		'#7c3aed', // Violet
		'#059669', // Emerald
		'#d97706', // Amber
		'#dc2626', // Red
		'#0891b2', // Cyan
		'#4f46e5', // Indigo
		'#db2777', // Pink
		'#65a30d', // Lime
		'#ea580c', // Orange
		'#9333ea', // Purple
		'#0d9488', // Teal
	);

	/**
	 * Constructor.
	 *
	 * @param AIPS_Config|null                $config                Config instance.
	 * @param AIPS_Embeddings_Repository|null $embeddings_repo       Embeddings repository.
	 * @param AIPS_Embeddings_Service|null    $embeddings_service    Embeddings service.
	 * @param AIPS_Relationships_Repository|null $relationships_repo Relationships repository.
	 * @param AIPS_AI_Service_Interface|null $ai_service             AI service.
	 * @param AIPS_Deduplication_Service|null $deduplication_service Deduplication service.
	 * @param AIPS_Logger_Interface|null      $logger                Logger instance.
	 */
	public function __construct(
		?AIPS_Config $config = null,
		?AIPS_Embeddings_Repository $embeddings_repo = null,
		?AIPS_Embeddings_Service $embeddings_service = null,
		?AIPS_Relationships_Repository $relationships_repo = null,
		?AIPS_AI_Service_Interface $ai_service = null,
		?AIPS_Deduplication_Service $deduplication_service = null,
		?AIPS_Logger_Interface $logger = null
	) {
		$container                   = class_exists('AIPS_Container') ? AIPS_Container::get_instance() : null;
		$this->config                = $config ?: ($container && $container->has(AIPS_Config::class) ? $container->make(AIPS_Config::class) : AIPS_Config::get_instance());
		$this->embeddings_repo       = $embeddings_repo;
		$this->embeddings_service    = $embeddings_service;
		$this->relationships_repo    = $relationships_repo;
		$this->ai_service            = $ai_service;
		$this->deduplication_service = $deduplication_service;
		$this->logger                = $logger;
	}

	/**
	 * Lazy getter for embeddings repository.
	 *
	 * @return AIPS_Embeddings_Repository
	 */
	public function get_embeddings_repository(): AIPS_Embeddings_Repository {
		if ($this->embeddings_repo === null) {
			$container = AIPS_Container::get_instance();
			$this->embeddings_repo = $container->has(AIPS_Embeddings_Repository::class)
				? $container->make(AIPS_Embeddings_Repository::class)
				: new AIPS_Embeddings_Repository();
		}
		return $this->embeddings_repo;
	}

	/**
	 * Lazy getter for embeddings service.
	 *
	 * @return AIPS_Embeddings_Service|null
	 */
	public function get_embeddings_service(): ?AIPS_Embeddings_Service {
		if ($this->embeddings_service === null) {
			$container = AIPS_Container::get_instance();
			$this->embeddings_service = $container->has(AIPS_Embeddings_Service::class)
				? $container->make(AIPS_Embeddings_Service::class)
				: null;
		}
		return $this->embeddings_service;
	}

	/**
	 * Lazy getter for relationships repository.
	 *
	 * @return AIPS_Relationships_Repository
	 */
	public function get_relationships_repository(): AIPS_Relationships_Repository {
		if ($this->relationships_repo === null) {
			$container = AIPS_Container::get_instance();
			$this->relationships_repo = $container->has(AIPS_Relationships_Repository::class)
				? $container->make(AIPS_Relationships_Repository::class)
				: new AIPS_Relationships_Repository();
		}
		return $this->relationships_repo;
	}

	/**
	 * Lazy getter for AI service.
	 *
	 * @return AIPS_AI_Service_Interface
	 */
	public function get_ai_service(): AIPS_AI_Service_Interface {
		if ($this->ai_service === null) {
			$container = AIPS_Container::get_instance();
			$this->ai_service = $container->has(AIPS_AI_Service_Interface::class)
				? $container->make(AIPS_AI_Service_Interface::class)
				: new AIPS_AI_Service();
		}
		return $this->ai_service;
	}

	/**
	 * Lazy getter for deduplication service.
	 *
	 * @return AIPS_Deduplication_Service
	 */
	public function get_deduplication_service(): AIPS_Deduplication_Service {
		if ($this->deduplication_service === null) {
			$container = AIPS_Container::get_instance();
			$this->deduplication_service = $container->has(AIPS_Deduplication_Service::class)
				? $container->make(AIPS_Deduplication_Service::class)
				: new AIPS_Deduplication_Service($this->config, $this->get_embeddings_repository(), $this->get_embeddings_service(), $this->get_relationships_repository(), $this);
		}
		return $this->deduplication_service;
	}

	/**
	 * Lazy getter for logger.
	 *
	 * @return AIPS_Logger_Interface
	 */
	public function get_logger(): AIPS_Logger_Interface {
		if ($this->logger === null) {
			$container = AIPS_Container::get_instance();
			$this->logger = $container->has(AIPS_Logger_Interface::class)
				? $container->make(AIPS_Logger_Interface::class)
				: new AIPS_Logger();
		}
		return $this->logger;
	}

	/**
	 * Get the configured or default threshold for a given context.
	 *
	 * @param string $context Context ('post', 'topic', 'internal_links', etc.).
	 * @return float Similarity threshold (0.0 to 1.0).
	 */
	public function get_default_threshold(string $context = 'post'): float {
		if ($context === 'topic') {
			$raw = $this->config->get_option('aips_topic_similarity_threshold', self::DEFAULT_TOPIC_THRESHOLD);
			return is_numeric($raw) ? (float) $raw : self::DEFAULT_TOPIC_THRESHOLD;
		}

		if ($context === 'internal_link' || $context === 'internal_links') {
			$raw = $this->config->get_option('aips_indexer_similarity_threshold', self::DEFAULT_INTERNAL_LINKS_THRESHOLD);
			return is_numeric($raw) ? (float) $raw : self::DEFAULT_INTERNAL_LINKS_THRESHOLD;
		}

		$raw = $this->config->get_option('aips_deduplication_threshold', self::DEFAULT_POST_THRESHOLD);
		return is_numeric($raw) ? (float) $raw : self::DEFAULT_POST_THRESHOLD;
	}

	/**
	 * Get the default maximum suggestions limit.
	 *
	 * @return int
	 */
	public function get_default_max_suggestions(): int {
		return self::DEFAULT_MAX_SUGGESTIONS;
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
	 * @param string $context Evaluation context ('post', 'topic', 'author', 'internal_links').
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
		$score     = max(0.0, min(1.0, (float) $score));
		$pct       = (int) round($score * 100);
		$threshold = $this->get_default_threshold($context);

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
	 * Mathematical Foundation:
	 * Given two vectors A and B of equal dimension n:
	 *   Cosine Similarity = (A · B) / (||A|| * ||B||)
	 *                     = (Σ A_i * B_i) / (sqrt(Σ A_i^2) * sqrt(Σ B_i^2))
	 *
	 * Algorithm Steps:
	 * 1. Validate that both vectors have non-zero and matching dimensions.
	 * 2. In a single linear scan (O(n)), calculate the dot product and the
	 *    sum of squares (norms) for both vectors to maximize cache locality.
	 * 3. Multiply the square roots of the vector norms to compute the denominator.
	 * 4. Apply an epsilon check ($divisor < 1e-9) to prevent division-by-zero
	 *    or extreme distortion for zero-magnitude vectors.
	 * 5. Clamp the final quotient to [0.0, 1.0] to eliminate floating-point
	 *    precision artifacts (e.g., 1.0000000000000002).
	 *
	 * @param array $vec_a First vector (float[]).
	 * @param array $vec_b Second vector (float[]).
	 * @return float Cosine similarity score clamped between 0.0 and 1.0.
	 */
	public function cosine_similarity(array $vec_a, array $vec_b): float {
		$count_a = count($vec_a);
		$count_b = count($vec_b);

		// Step 1: Dimension validation guard
		if ($count_a === 0 || $count_a !== $count_b) {
			return 0.0;
		}

		$dot_product = 0.0;
		$norm_a      = 0.0;
		$norm_b      = 0.0;

		// Step 2: Accumulate dot product and L2 norms in a single linear pass
		for ($i = 0; $i < $count_a; $i++) {
			$val_a        = (float) $vec_a[$i];
			$val_b        = (float) $vec_b[$i];
			$dot_product += $val_a * $val_b;
			$norm_a      += $val_a * $val_a;
			$norm_b      += $val_b * $val_b;
		}

		// Step 3: Compute Euclidean norm product divisor
		$divisor = sqrt($norm_a) * sqrt($norm_b);

		// Step 4: Division-by-zero epsilon guard
		if ($divisor < 1e-9) {
			return 0.0;
		}

		// Step 5: Normalize and clamp within unit range [0.0, 1.0]
		$sim = $dot_product / $divisor;
		return (float) max(0.0, min(1.0, $sim));
	}

	/**
	 * Find top matching candidates for a source vector.
	 *
	 * Centralizes candidate vector extraction, on-the-fly binary/JSON decoding,
	 * pairwise cosine similarity calculation, threshold filtering, risk classification,
	 * and top-N ranking. Eliminates duplicate comparison loops across Deduplication,
	 * Content Indexer, Related Posts, and Topic Expansion services.
	 *
	 * Algorithm Steps:
	 * ----------------
	 * 1. Early Return: Validate non-empty source vector and candidates.
	 * 2. Polymorphic Candidate Unpacking:
	 *    Candidates may arrive as stdClass database rows (e.g. from $wpdb queries)
	 *    or associative arrays. Keys may vary (e.g., `object_id` vs `id`,
	 *    `topic_title` vs `title`). This loop normalizes them into unified local variables.
	 * 3. Lazy Vector Decoding:
	 *    If the candidate's embedding is still a packed binary MEDIUMBLOB or JSON
	 *    string, it is passed to AIPS_Embeddings_Repository::decode_embedding()
	 *    which leverages 3-tier caching (memory -> object cache -> transient).
	 * 4. Dimensionality Consistency Guard:
	 *    Verifies that candidate vector length matches the source vector length.
	 *    Mismatched vectors (e.g. from different AI models) are skipped safely.
	 * 5. Cosine Similarity & Threshold Gate:
	 *    Calculates exact mathematical cosine similarity. Candidates failing
	 *    $min_threshold are immediately filtered out.
	 * 6. Standardized Evaluation DTO:
	 *    Attaches risk labels, badge classes, and percentage conversions via evaluate_similarity().
	 * 7. Descending Rank & Limit Slicing:
	 *    Sorts all qualifying matches descending by similarity and returns the top $limit items.
	 *
	 * @param array  $source_vector Source vector array (float[]).
	 * @param array  $candidates    List of candidate records or arrays containing embeddings.
	 * @param float  $min_threshold Minimum similarity threshold to include (0.0 to 1.0).
	 * @param int    $limit         Maximum number of top matches to return.
	 * @param string $context       Context for evaluation ('post', 'topic').
	 * @return array<int, array<string, mixed>> Sorted list of matches with IDs, titles, scores, and evaluation DTOs.
	 */
	public function find_top_matches(
		array $source_vector,
		array $candidates,
		float $min_threshold = 0.0,
		int $limit = 5,
		string $context = 'post'
	): array {
		// Step 1: Input sanity check
		if (empty($source_vector) || empty($candidates)) {
			return array();
		}

		$dimensions = count($source_vector);
		$matches    = array();

		// Step 2-5: Iterate, unpack, decode, and score candidates
		foreach ($candidates as $cand) {
			$cand_vec = array();
			$cand_id  = 0;
			$title    = '';
			$type     = $context;

			// Step 2: Polymorphic unpacking for objects vs arrays
			if (is_object($cand)) {
				$cand_id = isset($cand->object_id) ? (int) $cand->object_id : (isset($cand->id) ? (int) $cand->id : 0);
				$type    = isset($cand->object_type) ? (string) $cand->object_type : $context;
				$title   = isset($cand->title) ? (string) $cand->title : (isset($cand->topic_title) ? (string) $cand->topic_title : '');

				// Step 3: Lazy decoding via repository cache
				if (isset($cand->embedding)) {
					$emb_repo = $this->get_embeddings_repository();
					$cand_vec = is_array($cand->embedding)
						? $cand->embedding
						: ($emb_repo ? $emb_repo->decode_embedding($cand->embedding, $type, $cand_id, isset($cand->content_hash) ? $cand->content_hash : '') : array());
				}
			} elseif (is_array($cand)) {
				$cand_id = isset($cand['object_id']) ? (int) $cand['object_id'] : (isset($cand['id']) ? (int) $cand['id'] : 0);
				$type    = isset($cand['object_type']) ? (string) $cand['object_type'] : (isset($cand['type']) ? (string) $cand['type'] : $context);
				$title   = isset($cand['title']) ? (string) $cand['title'] : (isset($cand['topic_title']) ? (string) $cand['topic_title'] : '');

				// Step 3: Lazy decoding via repository cache
				if (isset($cand['embedding'])) {
					$emb_repo = $this->get_embeddings_repository();
					$cand_vec = is_array($cand['embedding'])
						? $cand['embedding']
						: ($emb_repo ? $emb_repo->decode_embedding($cand['embedding'], $type, $cand_id, isset($cand['content_hash']) ? $cand['content_hash'] : '') : array());
				}
			}

			// Step 4: Dimension consistency guard
			if (empty($cand_vec) || count($cand_vec) !== $dimensions) {
				continue;
			}

			// Step 5: Cosine similarity calculation & threshold filter
			$sim = $this->cosine_similarity($source_vector, $cand_vec);
			if ($sim >= $min_threshold) {
				// Step 6: Attach standardized evaluation metadata
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

		// Step 7: Sort descending by similarity score
		usort($matches, function ($a, $b) {
			return $b['similarity'] <=> $a['similarity'];
		});

		// Step 8: Return top N results
		return array_slice($matches, 0, max(1, $limit));
	}

	/**
	 * Evaluate author topic auto-approval rules and return the decision and metadata.
	 *
	 * Decouples auto-approval logic from AIPS_Author_Topics_Generator and centralizes
	 * quality score thresholds, dual-boundary semantic gating, and fallback resolution.
	 *
	 * Decision Policies:
	 * ------------------
	 * 1. 'all':
	 *    Unconditionally approves all topics generated for this author.
	 * 2. 'score':
	 *    Evaluates the topic's LLM-assigned quality score against the author's
	 *    `auto_approval_min_score` threshold (default 70).
	 * 3. 'similarity' / 'embeddings':
	 *    Applies Dual-Boundary Semantic Gating using vector embeddings:
	 *    a) Upper Boundary (Cannibalization Guard):
	 *       If duplicate similarity to published posts or existing topics meets or
	 *       exceeds `auto_approval_max_similarity` (default 0.85 / 85%), the topic
	 *       is marked as an auto-rejected duplicate.
	 *    b) Lower Boundary (Niche Relevance Floor):
	 *       If cosine similarity against the author's composite persona baseline
	 *       vector falls below `auto_approval_min_relevance` (default 0.70 / 70%),
	 *       the topic fails to qualify due to off-topic drifting.
	 *    c) Acceptance Corridor:
	 *       Topics falling inside the corridor (relevance >= 70% AND duplicate < 85%)
	 *       are automatically approved.
	 * 4. Fallback Routing:
	 *    When a topic does not qualify:
	 *    - 'pending': Topic is held in pending review for human editorial sign-off.
	 *    - 'rejected': Topic is rejected immediately.
	 *    - 'smart_split': Cannibalization duplicates are rejected immediately to keep
	 *      the queue clean, while borderline low-relevance topics stay in 'pending'.
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
		// Step 1: Decode existing topic metadata
		$meta = isset($topic['metadata']) && is_string($topic['metadata'])
			? json_decode($topic['metadata'], true)
			: (isset($topic['metadata']) && is_array($topic['metadata']) ? $topic['metadata'] : array());
		if (!is_array($meta)) {
			$meta = array();
		}

		// Step 2: Extract author policy configuration with defensive defaults.
		// Property names match the aips_authors columns / AIPS_Authors_Controller
		// (topic_auto_approval_*), not the unprefixed auto_approval_* names this
		// used to read — those never existed on a real author object, so this
		// always fell through to the 'none' branch regardless of the author's
		// configured mode.
		$mode           = !empty($author->topic_auto_approval_mode) ? (string) $author->topic_auto_approval_mode : 'none';
		$min_score      = isset($author->topic_auto_approval_min_score) ? (int) $author->topic_auto_approval_min_score : 70;
		// No dedicated relevance column; similarity/embeddings mode derives it from
		// the score field, the same way AIPS_Author_Topics_Generator::apply_auto_approval_rules() does.
		$min_relevance  = isset($author->topic_auto_approval_min_score) ? max(0.01, min(1.0, (float) $author->topic_auto_approval_min_score / 100)) : 0.70;
		$max_similarity = isset($author->topic_auto_approval_max_similarity) ? (float) $author->topic_auto_approval_max_similarity : 0.85;
		$fallback       = !empty($author->topic_auto_approval_fallback) ? (string) $author->topic_auto_approval_fallback : 'pending';

		$qualifies        = false;
		$is_dup_rejection = false;
		$reason           = '';
		$note             = '';

		// Step 3: Evaluate approval based on configured policy mode
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

				$embeddings_svc = $this->get_embeddings_service();
				if (!empty($author_baseline_vec) && !empty($topic_title) && $embeddings_svc && $embeddings_svc->is_enabled()) {
					$tvec = $embeddings_svc->generate_embedding($topic_title);
					if (!is_wp_error($tvec) && is_array($tvec) && count($tvec) === count($author_baseline_vec)) {
						$rel_sim = $this->cosine_similarity($tvec, $author_baseline_vec);
					}
				}

				$meta['auto_approval_relevance']      = round($rel_sim, 4);
				$meta['auto_approval_duplicate_sim']  = round($dup_sim, 4);
				$meta['auto_approval_min_relevance']  = round($min_relevance, 4);
				$meta['auto_approval_max_similarity'] = round($max_similarity, 4);

				// Step 3c: Dual-boundary semantic evaluation
				if ($dup_sim >= $max_similarity) {
					// Upper boundary breached: Cannibalization duplicate
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
					// Lower boundary breached: Off-niche drift
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
					// Inside the quality corridor: Auto-approved
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

		// Step 4: Resolve final decision and apply fallback routing
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
	 * Algorithm Steps:
	 * 1. Verify post ID exists and post object is valid.
	 * 2. Check if post has a stored vector in `aips_embeddings`.
	 *    If unindexed, return 'unindexed' risk status with appropriate badge styling.
	 * 3. Retrieve precomputed top duplicate relationships from `aips_relationships`.
	 * 4. Scan candidate duplicate pairs, discard deleted target posts, and track
	 *    the maximum pairwise cosine similarity.
	 * 5. Format each duplicate match with URL, edit link, similarity percentage,
	 *    and risk tier styling.
	 * 6. Construct overall post evaluation summary for the WP Posts list table column
	 *    and the block/Classic editor sidebars.
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

		$raw_duplicates  = array();
		$embeddings_repo = $this->get_embeddings_repository();
		if ($embeddings_repo) {
			$emb = $embeddings_repo->get_by_post_id($post_id);
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

			$relationships_repo = $this->get_relationships_repository();
			if ($relationships_repo) {
				$raw_duplicates = $relationships_repo->get_top_duplicates($post_id, 5);
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


	/**
	 * Find similar topics to a given topic.
	 *
	 * @param int    $topic_id  The topic ID to find similar topics for.
	 * @param int    $author_id Author ID to limit search to.
	 * @param int    $limit     Maximum number of similar topics to return.
	 * @param string $status    Optional. Filter by status (pending, approved, rejected).
	 * @return array Array of similar topics with similarity scores.
	 */
	public function find_similar_topics(int $topic_id, int $author_id, int $limit = 5, ?string $status = null): array {
		$emb_svc = $this->get_embeddings_service();
		if (!$emb_svc) {
			return array();
		}

		$target_embedding = $emb_svc->get_topic_embedding($topic_id);

		if (!$target_embedding) {
			if (!$emb_svc->is_enabled()) {
				return array();
			}
			$res = $emb_svc->compute_topic_embedding($topic_id);
			if (is_wp_error($res)) {
				return array();
			}
			$target_embedding = $emb_svc->get_topic_embedding($topic_id);
		}

		if (!$target_embedding) {
			return array();
		}

		$topics_repo = new AIPS_Author_Topics_Repository();
		$all_topics  = $topics_repo->get_by_author($author_id, $status);

		$candidates = array();
		foreach ($all_topics as $candidate_topic) {
			if ((int) $candidate_topic->id === $topic_id) {
				continue;
			}

			$embedding = $emb_svc->get_topic_embedding((int) $candidate_topic->id);
			if (!$embedding && $emb_svc->is_enabled()) {
				$emb_svc->compute_topic_embedding((int) $candidate_topic->id);
				$embedding = $emb_svc->get_topic_embedding((int) $candidate_topic->id);
			}

			if ($embedding) {
				$candidates[] = array(
					'id'        => (int) $candidate_topic->id,
					'embedding' => $embedding,
					'data'      => array(
						'topic_title' => $candidate_topic->topic_title,
						'status'      => $candidate_topic->status,
					),
				);
			}
		}

		$raw_threshold = $this->config->get_option('aips_topic_similarity_threshold', 0.80);
		$threshold     = is_numeric($raw_threshold) ? min(1.0, max(0.1, (float) $raw_threshold)) : 0.80;
		$matches       = $this->find_top_matches($target_embedding, $candidates, $threshold, $limit, 'topic');

		return array_map(function($m) {
			return array(
				'id'         => $m['id'],
				'similarity' => $m['similarity'],
				'data'       => isset($m['candidate']['data']) ? $m['candidate']['data'] : array(),
				'evaluation' => $m['evaluation'],
			);
		}, $matches);
	}

	/**
	 * Suggest related topics for an author based on approved topics.
	 *
	 * @param int $author_id Author ID.
	 * @param int $limit     Number of suggestions to return.
	 * @return array Array of suggested topics with scores.
	 */
	public function suggest_related_topics(int $author_id, int $limit = 10): array {
		$topics_repo     = new AIPS_Author_Topics_Repository();
		$approved_topics = $topics_repo->get_by_author($author_id, 'approved');
		$pending_topics  = $topics_repo->get_by_author($author_id, 'pending');

		if (empty($approved_topics) || empty($pending_topics)) {
			return array();
		}

		$emb_svc     = $this->get_embeddings_service();
		$suggestions = array();

		foreach ($pending_topics as $pending_topic) {
			$pending_embedding = $emb_svc ? $emb_svc->get_topic_embedding((int) $pending_topic->id) : null;
			if (!$pending_embedding && $emb_svc && $emb_svc->is_enabled()) {
				$emb_svc->compute_topic_embedding((int) $pending_topic->id);
				$pending_embedding = $emb_svc->get_topic_embedding((int) $pending_topic->id);
			}

			if (!$pending_embedding) {
				continue;
			}

			$max_similarity = $this->calculate_max_similarity_for_topic($pending_embedding, $approved_topics, $emb_svc);

			if ($max_similarity > 0) {
				$eval          = $this->evaluate_similarity($max_similarity, 'topic');
				$suggestions[] = array(
					'topic_id'         => (int) $pending_topic->id,
					'topic_title'      => $pending_topic->topic_title,
					'similarity_score' => $max_similarity,
					'similarity_pct'   => $eval['percentage'],
					'percentage'       => $eval['percentage'],
					'risk_tier'        => $eval['risk_tier'],
					'risk_label'       => $eval['risk_label'],
					'badge_class'      => $eval['topic_badge_class'],
					'is_duplicate'     => $eval['is_duplicate'],
				);
			}
		}

		usort($suggestions, function($a, $b) {
			return $b['similarity_score'] <=> $a['similarity_score'];
		});

		return array_slice($suggestions, 0, $limit);
	}

	/**
	 * Calculate the maximum similarity score for a pending topic embedding against approved topics.
	 *
	 * Iterates over approved candidate topics, lazily computes/retrieves their vector embeddings,
	 * and returns the highest pairwise cosine similarity score found.
	 *
	 * @param array                        $pending_embedding Vector array for the pending topic.
	 * @param array<int, object>           $approved_topics   Array of approved topic database objects.
	 * @param AIPS_Embeddings_Service|null $emb_svc           Optional embeddings service instance.
	 * @return float The highest cosine similarity score found (0.0 to 1.0).
	 */
	public function calculate_max_similarity_for_topic(array $pending_embedding, array $approved_topics, ?AIPS_Embeddings_Service $emb_svc = null): float {
		$emb_svc        = $emb_svc ?: $this->get_embeddings_service();
		$max_similarity = 0.0;

		foreach ($approved_topics as $approved_topic) {
			$approved_embedding = $emb_svc ? $emb_svc->get_topic_embedding((int) $approved_topic->id) : null;
			if (!$approved_embedding && $emb_svc && $emb_svc->is_enabled()) {
				$emb_svc->compute_topic_embedding((int) $approved_topic->id);
				$approved_embedding = $emb_svc->get_topic_embedding((int) $approved_topic->id);
			}

			if ($approved_embedding) {
				$sim = $this->cosine_similarity($pending_embedding, $approved_embedding);
				if ($sim > $max_similarity) {
					$max_similarity = $sim;
				}
			}
		}

		return (float) $max_similarity;
	}

	/**
	 * Get expanded context from approved topics for prompt enhancement.
	 *
	 * @param int $author_id     Author ID.
	 * @param int $topic_id      Current topic ID.
	 * @param int $context_limit Number of similar approved topics to include.
	 * @return string Enhanced context string for prompts.
	 */
	public function get_expanded_context(int $author_id, int $topic_id, int $context_limit = 5): string {
		$similar_topics = $this->find_similar_topics($topic_id, $author_id, $context_limit, 'approved');

		if (empty($similar_topics)) {
			return '';
		}

		$context_parts = array();
		foreach ($similar_topics as $similar) {
			if (!empty($similar['data']['topic_title'])) {
				$context_parts[] = $similar['data']['topic_title'];
			}
		}

		if (empty($context_parts)) {
			return '';
		}

		return "Related approved topics:\n- " . implode("\n- ", $context_parts);
	}

	/**
	 * Process a batch of approved topics for embeddings generation.
	 *
	 * Uses ID-based pagination to avoid slow OFFSET queries.
	 *
	 * @param int $author_id         Author ID.
	 * @param int $batch_size        Number of topics to process in this batch. Default 20.
	 * @param int $last_processed_id Last processed topic ID for pagination. Default 0.
	 * @return array Array with keys: success, failed, skipped, last_processed_id, done, processed_count.
	 */
	public function process_approved_embeddings_batch(int $author_id, int $batch_size = 20, int $last_processed_id = 0): array {
		$batch_size  = max(1, min(100, $batch_size));
		$topics_repo = new AIPS_Author_Topics_Repository();
		$emb_svc     = $this->get_embeddings_service();

		$history_service = AIPS_Container::get_instance()->has(AIPS_History_Service_Interface::class)
			? AIPS_Container::get_instance()->make(AIPS_History_Service_Interface::class)
			: new AIPS_History_Service();

		$history = $history_service->find_incomplete('author_embeddings', array('author_id' => $author_id))
			?: $history_service->create('author_embeddings', array('author_id' => $author_id, 'creation_method' => 'author_embeddings'));

		$topics = $topics_repo->get_approved_for_generation($author_id, $batch_size, $last_processed_id);

		$stats = array(
			'success'           => 0,
			'failed'            => 0,
			'skipped'           => 0,
			'last_processed_id' => $last_processed_id,
			'done'              => empty($topics),
			'processed_count'   => 0,
		);

		if (empty($topics)) {
			return $stats;
		}

		$rate_limiter = $emb_svc ? $emb_svc->get_rate_limiter() : null;

		foreach ($topics as $topic) {
			$stats['last_processed_id'] = (int) $topic->id;
			$stats['processed_count']++;

			$existing_embedding = $emb_svc ? $emb_svc->get_topic_embedding((int) $topic->id) : null;
			if ($existing_embedding) {
				$stats['skipped']++;
				continue;
			}

			$res = $emb_svc ? $emb_svc->compute_topic_embedding((int) $topic->id) : new WP_Error('no_emb_svc', 'Embeddings service unavailable');
			if (is_wp_error($res)) {
				$stats['failed']++;
				if ($rate_limiter && $rate_limiter->is_rate_limit_or_exhaustion_error($res)) {
					break;
				}
			} else {
				$stats['success']++;
			}
		}

		$stats['done'] = (count($topics) < $batch_size) || ($rate_limiter && $rate_limiter->is_in_cooldown());

		return $stats;
	}

	// -------------------------------------------------------------------------
	// Post clusters, orphans, pillars and content gaps
	// (consolidated from the former AIPS_Post_Clusters_Service)
	// -------------------------------------------------------------------------

	/**
	 * Detect organic Post Clusters using connected community graph traversal.
	 *
	 * @param float|null $threshold Similarity threshold (defaults to option value).
	 * @return array Detected post clusters with metrics and member posts.
	 */
	public function detect_post_clusters(?float $threshold = null): array {
		if ($threshold === null) {
			$threshold = (float) $this->config->get_option('aips_indexer_post_cluster_threshold', 0.65);
		}

		$post_types = (array) $this->config->get_option('aips_indexer_post_types', array('post'));
		$candidates = $this->get_embeddings_repository()->get_all_for_similarity('post', $post_types, 'publish');

		if (empty($candidates)) {
			return array();
		}

		// Decode post vectors
		$posts = array();
		foreach ($candidates as $row) {
			$vec = $this->get_embeddings_repository()->decode_embedding($row->embedding);
			if (!empty($vec)) {
				$pid = (int) $row->object_id;
				$posts[$pid] = array(
					'id'        => $pid,
					'title'     => get_the_title($pid),
					'post_type' => $row->post_type,
					'url'       => get_permalink($pid),
					'edit_url'  => get_edit_post_link($pid, ''),
					'embedding' => $vec,
				);
			}
		}

		$post_ids = array_keys($posts);
		$total    = count($post_ids);
		if ($total < 2) {
			return array();
		}

		// Build similarity adjacency graph
		$adjacency = array();
		foreach ($post_ids as $pid) {
			$adjacency[$pid] = array();
		}

		for ($i = 0; $i < $total; $i++) {
			$id_a = $post_ids[$i];
			$vec_a = $posts[$id_a]['embedding'];

			for ($j = $i + 1; $j < $total; $j++) {
				$id_b = $post_ids[$j];
				$vec_b = $posts[$id_b]['embedding'];

				if (count($vec_a) === count($vec_b)) {
					$sim = $this->cosine_similarity($vec_a, $vec_b);
					if ((float) $sim >= $threshold) {
						$adjacency[$id_a][$id_b] = (float) $sim;
						$adjacency[$id_b][$id_a] = (float) $sim;
					}
				}
			}
		}

		// Connected component detection via BFS
		$visited    = array();
		$components = array();

		foreach ($post_ids as $pid) {
			if (!empty($visited[$pid])) {
				continue;
			}

			$component = array();
			$queue     = array($pid);
			$visited[$pid] = true;

			while (!empty($queue)) {
				$curr = array_shift($queue);
				$component[] = $curr;

				if (!empty($adjacency[$curr])) {
					foreach ($adjacency[$curr] as $neighbor_id => $sim_val) {
						if (empty($visited[$neighbor_id])) {
							$visited[$neighbor_id] = true;
							$queue[] = $neighbor_id;
						}
					}
				}
			}

			// Retain components with 2 or more posts as clusters
			if (count($component) >= 2) {
				$components[] = $component;
			}
		}

		// Sort clusters descending by size
		usort($components, function ($a, $b) {
			return count($b) <=> count($a);
		});

		$saved_clusters = (array) $this->config->get_option('aips_post_clusters', array());
		$clusters       = array();
		$color_index    = 0;
		$palette_count  = count(self::$cluster_palette);

		foreach ($components as $index => $comp_ids) {
			$cluster_key = 'cluster_' . ($index + 1);

			// Compute centroid vector and cohesion score
			$dim = count($posts[$comp_ids[0]]['embedding']);
			$centroid = array_fill(0, $dim, 0.0);
			$pair_sims = array();

			foreach ($comp_ids as $cid) {
				for ($d = 0; $d < $dim; $d++) {
					$centroid[$d] += $posts[$cid]['embedding'][$d];
				}
			}
			for ($d = 0; $d < $dim; $d++) {
				$centroid[$d] /= count($comp_ids);
			}

			// Cohesion = average similarity between members
			for ($a = 0; $a < count($comp_ids); $a++) {
				for ($b = $a + 1; $b < count($comp_ids); $b++) {
					$ida = $comp_ids[$a];
					$idb = $comp_ids[$b];
					if (isset($adjacency[$ida][$idb])) {
						$pair_sims[] = $adjacency[$ida][$idb];
					}
				}
			}
			$cohesion = !empty($pair_sims) ? (array_sum($pair_sims) / count($pair_sims)) : $threshold;

			// Determine pillar post: saved preference, or highest degree centrality
			$pillar_id        = 0;
			$pillar_confirmed = false;
			if (isset($saved_clusters[$cluster_key]['pillar_id']) && in_array((int) $saved_clusters[$cluster_key]['pillar_id'], $comp_ids, true)) {
				$pillar_id = (int) $saved_clusters[$cluster_key]['pillar_id'];
				// Chosen by a person (Topic Clusters or Silos), not by degree.
				$pillar_confirmed = !empty($saved_clusters[$cluster_key]['pillar_confirmed']);
			} else {
				// Highest connection degree
				$best_degree = -1;
				foreach ($comp_ids as $cid) {
					$deg = count($adjacency[$cid]);
					if ($deg > $best_degree) {
						$best_degree = $deg;
						$pillar_id = $cid;
					}
				}
			}

			// Cluster name: saved name or fallback
			$name = isset($saved_clusters[$cluster_key]['name']) && !empty($saved_clusters[$cluster_key]['name'])
				? $saved_clusters[$cluster_key]['name']
				: sprintf(__('Post Cluster #%d: %s', 'ai-post-scheduler'), $index + 1, $posts[$pillar_id]['title']);

			$color = self::$cluster_palette[$color_index % $palette_count];
			$color_index++;

			// Format member posts list
			$member_posts = array();
			foreach ($comp_ids as $cid) {
				$member_posts[] = array(
					'id'        => $cid,
					'title'     => $posts[$cid]['title'],
					'post_type' => $posts[$cid]['post_type'],
					'url'       => $posts[$cid]['url'],
					'edit_url'  => $posts[$cid]['edit_url'],
					'is_pillar' => ($cid === $pillar_id),
					// How close the post is to the cluster's overall topic (0-1).
					'topic_score' => round((float) $this->cosine_similarity($posts[$cid]['embedding'], $centroid), 4),
				);
			}

			$clusters[$cluster_key] = array(
				'id'           => $cluster_key,
				'name'         => $name,
				'pillar_id'    => $pillar_id,
				'pillar_title' => $posts[$pillar_id]['title'],
				'pillar_confirmed' => $pillar_confirmed,
				'color'        => $color,
				'post_count'   => count($comp_ids),
				'cohesion_pct' => round($cohesion * 100, 1),
				'member_ids'   => $comp_ids,
				'posts'        => $member_posts,
			);

			// Sync pillar_spoke relationships for the designated pillar
			$this->sync_pillar_spokes($pillar_id, $comp_ids, $adjacency);
		}

		// Persist updated clusters metadata
		$this->config->set_option('aips_post_clusters', $clusters);

		return $clusters;
	}

	/**
	 * Identify orphan posts lacking semantic density or incoming internal links.
	 *
	 * @param float|null $threshold
	 * @param string     $sensitivity 'hybrid', 'semantic', or 'links'.
	 * @return array List of orphan post records with classification tags.
	 */
	public function get_orphan_posts(?float $threshold = null, string $sensitivity = 'hybrid'): array {
		if ($threshold === null) {
			$threshold = (float) $this->config->get_option('aips_indexer_post_cluster_threshold', 0.65);
		}

		$post_types = (array) $this->config->get_option('aips_indexer_post_types', array('post'));
		$candidates = $this->get_embeddings_repository()->get_all_for_similarity('post', $post_types, 'publish');

		if (empty($candidates)) {
			return array();
		}

		$posts = array();
		foreach ($candidates as $row) {
			$vec = $this->get_embeddings_repository()->decode_embedding($row->embedding);
			if (!empty($vec)) {
				$pid = (int) $row->object_id;
				$posts[$pid] = array(
					'id'        => $pid,
					'title'     => get_the_title($pid),
					'post_type' => $row->post_type,
					'url'       => get_permalink($pid),
					'edit_url'  => get_edit_post_link($pid, ''),
					'embedding' => $vec,
					'neighbors' => 0,
				);
			}
		}

		$post_ids = array_keys($posts);
		$total    = count($post_ids);

		// Calculate semantic neighbors
		for ($i = 0; $i < $total; $i++) {
			$id_a = $post_ids[$i];
			$vec_a = $posts[$id_a]['embedding'];

			for ($j = $i + 1; $j < $total; $j++) {
				$id_b = $post_ids[$j];
				$vec_b = $posts[$id_b]['embedding'];

				if (count($vec_a) === count($vec_b)) {
					$sim = $this->cosine_similarity($vec_a, $vec_b);
					if ((float) $sim >= $threshold) {
						$posts[$id_a]['neighbors']++;
						$posts[$id_b]['neighbors']++;
					}
				}
			}
		}

		// Prefer the link index (real <a href> links, one batched query); fall back
		// to the content LIKE scan only while the index has not been built.
		$link_index     = new AIPS_Link_Index_Repository();
		$use_link_index = (new AIPS_Link_Index_Service($link_index))->is_built();
		$link_counts    = $use_link_index ? $link_index->get_counts_for_posts(array_keys($posts)) : array();

		$orphans = array();
		foreach ($posts as $pid => $pdata) {
			$is_island = ($pdata['neighbors'] <= 1);
			$incoming_links = $use_link_index
				? (isset($link_counts[$pid]) ? $link_counts[$pid]['inbound'] : 0)
				: $this->count_incoming_internal_links($pid);
			$is_unlinked = ($incoming_links === 0);

			$qualifies = false;
			$orphan_type = '';

			if ($sensitivity === 'semantic' && $is_island) {
				$qualifies = true;
				$orphan_type = 'semantic_island';
			} elseif ($sensitivity === 'links' && $is_unlinked) {
				$qualifies = true;
				$orphan_type = 'unlinked_post';
			} elseif ($sensitivity === 'hybrid' && ($is_island || $is_unlinked)) {
				$qualifies = true;
				if ($is_island && $is_unlinked) {
					$orphan_type = 'isolated_and_unlinked';
				} elseif ($is_island) {
					$orphan_type = 'semantic_island';
				} else {
					$orphan_type = 'unlinked_post';
				}
			}

			if ($qualifies) {
				$orphans[] = array(
					'id'             => $pid,
					'title'          => $pdata['title'],
					'post_type'      => $pdata['post_type'],
					'url'            => $pdata['url'],
					'edit_url'       => $pdata['edit_url'],
					'neighbors'      => $pdata['neighbors'],
					'incoming_links' => $incoming_links,
					'orphan_type'    => $orphan_type,
				);
			}
		}

		return $orphans;
	}

	/**
	 * Designate a post as cluster pillar and update relational sync.
	 *
	 * @param string $cluster_id
	 * @param int    $post_id
	 * @return bool
	 */
	public function set_pillar_post(string $cluster_id, int $post_id): bool {
		$clusters = (array) $this->config->get_option('aips_post_clusters', array());
		if (!isset($clusters[$cluster_id])) {
			return false;
		}

		$post_id = absint($post_id);
		if (!in_array($post_id, array_map('intval', isset($clusters[$cluster_id]['member_ids']) ? (array) $clusters[$cluster_id]['member_ids'] : array()), true)) {
			return false;
		}

		$clusters[$cluster_id]['pillar_id']        = $post_id;
		$clusters[$cluster_id]['pillar_title']     = get_the_title($post_id);
		$clusters[$cluster_id]['pillar_confirmed'] = true;

		if (!empty($clusters[$cluster_id]['posts'])) {
			foreach ($clusters[$cluster_id]['posts'] as &$p) {
				$p['is_pillar'] = ((int) $p['id'] === $post_id);
			}
			unset($p);
		}

		$this->config->set_option('aips_post_clusters', $clusters);

		// Sync pillar_spoke relationships
		$member_ids = isset($clusters[$cluster_id]['member_ids']) ? (array) $clusters[$cluster_id]['member_ids'] : array();
		if (!empty($member_ids)) {
			$targets = array();
			foreach ($member_ids as $mid) {
				if ((int) $mid === $post_id) {
					continue;
				}
				$targets[] = array(
					'target_type' => 'post',
					'target_id'   => (int) $mid,
					'similarity'  => 0.75,
				);
			}
			$this->get_relationships_repository()->sync_for_source('post', $post_id, $targets, 'pillar_spoke');
		}

		return true;
	}

	/**
	 * Rename a post cluster.
	 *
	 * @param string $cluster_id
	 * @param string $new_name
	 * @return bool
	 */
	public function rename_post_cluster(string $cluster_id, string $new_name): bool {
		$clusters = (array) $this->config->get_option('aips_post_clusters', array());
		if (!isset($clusters[$cluster_id])) {
			return false;
		}

		$clusters[$cluster_id]['name'] = sanitize_text_field(wp_unslash($new_name));
		$this->config->set_option('aips_post_clusters', $clusters);
		return true;
	}

	/**
	 * Generate AI content gap suggestions for an orphan post or cluster.
	 *
	 * Dispatches prompt to AIPS_AI_Service (provider-agnostic: works across AI Engine and WP AI Connectors).
	 *
	 * @param int         $post_id
	 * @param string|null $cluster_id
	 * @return array|WP_Error Array of 3-5 suggestions with titles, rationales, and briefs.
	 */
	public function generate_gap_suggestions(int $post_id, ?string $cluster_id = null) {
		$post = get_post($post_id);
		if (!$post) {
			return new WP_Error('post_not_found', __('Post not found.', 'ai-post-scheduler'));
		}

		$post_title   = $post->post_title;
		$post_excerpt = !empty($post->post_excerpt) ? $post->post_excerpt : wp_trim_words($post->post_content, 40);

		$cluster_name = '';
		if (!empty($cluster_id)) {
			$clusters = (array) $this->config->get_option('aips_post_clusters', array());
			if (isset($clusters[$cluster_id]['name'])) {
				$cluster_name = $clusters[$cluster_id]['name'];
			}
		}

		$prompt = "You are an expert SEO editorial strategist.\n";
		$prompt .= "Analyze this article to identify content gaps and missing bridge topics:\n";
		$prompt .= "Article Title: \"{$post_title}\"\n";
		$prompt .= "Excerpt / Summary: \"{$post_excerpt}\"\n";
		if (!empty($cluster_name)) {
			$prompt .= "Post Cluster Theme: \"{$cluster_name}\"\n";
		}
		$prompt .= "\nGenerate 3 to 5 high-value complementary article topic suggestions that would:\n";
		$prompt .= "1. Form strong internal link bridge connections to this article.\n";
		$prompt .= "2. Cover subtopics, FAQs, or implementation details that are currently missing.\n";
		$prompt .= "3. Strengthen topical authority.\n\n";
		$prompt .= "Format your response as a valid JSON array of objects with keys: \"title\", \"rationale\", \"brief\". Do not include any other commentary or markdown formatting.";

		$response = $this->get_ai_service()->generate_text($prompt, array(
			'temperature' => 0.7,
			'max_tokens'  => 1200,
		));

		if (is_wp_error($response)) {
			return $response;
		}

		// Clean JSON response
		$raw = trim($response);
		$raw = preg_replace('/^```(?:json)?\s*/i', '', $raw);
		$raw = preg_replace('/\s*```$/i', '', $raw);

		$parsed = json_decode($raw, true);
		if (!is_array($parsed) || empty($parsed)) {
			// Fallback mock structure if raw json parsing fails
			$parsed = array(
				array(
					'title'     => sprintf(__('Complete Guide to %s: Best Practices & Strategies', 'ai-post-scheduler'), $post_title),
					'rationale' => __('Comprehensive foundational coverage that bridges technical concepts.', 'ai-post-scheduler'),
					'brief'     => __('Explore key principles, architectural patterns, and actionable takeaways.', 'ai-post-scheduler'),
				),
				array(
					'title'     => sprintf(__('Common Pitfalls When Implementing %s', 'ai-post-scheduler'), $post_title),
					'rationale' => __('Targets high-intent troubleshooting queries and provides natural internal link opportunities.', 'ai-post-scheduler'),
					'brief'     => __('Detail failure modes, prevention checklists, and resolution workflows.', 'ai-post-scheduler'),
				),
				array(
					'title'     => sprintf(__('Advanced Applications & Case Studies of %s', 'ai-post-scheduler'), $post_title),
					'rationale' => __('Deepens topical depth for advanced readers and builds authority.', 'ai-post-scheduler'),
					'brief'     => __('Real-world case studies, benchmark data, and specialized implementations.', 'ai-post-scheduler'),
				),
			);
		}

		return $parsed;
	}

	/**
	 * Sync pillar_spoke relationships for a pillar post.
	 *
	 * @param int   $pillar_id
	 * @param int[] $member_ids
	 * @param array $adjacency
	 * @return void
	 */
	private function sync_pillar_spokes(int $pillar_id, array $member_ids, array $adjacency): void {
		$targets = array();
		foreach ($member_ids as $mid) {
			if ((int) $mid === $pillar_id) {
				continue;
			}
			$sim = isset($adjacency[$pillar_id][$mid]) ? (float) $adjacency[$pillar_id][$mid] : 0.70;
			$targets[] = array(
				'target_type' => 'post',
				'target_id'   => (int) $mid,
				'similarity'  => $sim,
			);
		}

		$this->get_relationships_repository()->sync_for_source('post', $pillar_id, $targets, 'pillar_spoke');
	}

	/**
	 * Count incoming internal links to a post across published content.
	 *
	 * @param int $post_id
	 * @return int
	 */
	private function count_incoming_internal_links(int $post_id): int {
		$permalink = get_permalink($post_id);
		if (empty($permalink)) {
			return 0;
		}

		$path = wp_parse_url($permalink, PHP_URL_PATH);
		$search_term = !empty($path) ? $path : $permalink;

		return $this->get_relationships_repository()->count_incoming_internal_links($post_id, $search_term);
	}
}
