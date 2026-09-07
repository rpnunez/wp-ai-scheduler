<?php
/**
 * Deduplication Service
 *
 * 360° Semantic Deduplication and Content Cannibalization Shield.
 * Evaluates candidate topics against both existing topics and published articles,
 * provides pre-generation duplicate gatekeeping, and site-wide cannibalization audits.
 *
 * @package AI_Post_Scheduler
 * @since 3.0.0
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Deduplication_Service
 *
 * Service for semantic duplicate detection across topics, posts, and CPTs.
 */
class AIPS_Deduplication_Service {

	/**
	 * @var AIPS_Embeddings_Repository
	 */
	private $embeddings_repo;

	/**
	 * @var AIPS_Relationships_Repository
	 */
	private $relationships_repo;

	/**
	 * @var AIPS_Embeddings_Service
	 */
	private $embeddings_service;

	/**
	 * @var AIPS_Config
	 */
	private $config;

	/**
	 * @var AIPS_Logger_Interface
	 */
	private $logger;

	/**
	 * Initialize deduplication service.
	 */
	public function __construct(
		?AIPS_Embeddings_Repository $embeddings_repo = null,
		?AIPS_Relationships_Repository $relationships_repo = null,
		?AIPS_Embeddings_Service $embeddings_service = null,
		?AIPS_Config $config = null,
		?AIPS_Logger_Interface $logger = null
	) {
		$container = AIPS_Container::get_instance();

		$this->embeddings_repo    = $embeddings_repo    ?: ($container->has(AIPS_Embeddings_Repository::class) ? $container->make(AIPS_Embeddings_Repository::class) : new AIPS_Embeddings_Repository());
		$this->relationships_repo = $relationships_repo ?: ($container->has(AIPS_Relationships_Repository::class) ? $container->make(AIPS_Relationships_Repository::class) : new AIPS_Relationships_Repository());
		$this->embeddings_service = $embeddings_service ?: ($container->has(AIPS_Embeddings_Service::class) ? $container->make(AIPS_Embeddings_Service::class) : new AIPS_Embeddings_Service());
		$this->config             = $config             ?: AIPS_Config::get_instance();
		$this->logger             = $logger             ?: ($container->has(AIPS_Logger_Interface::class) ? $container->make(AIPS_Logger_Interface::class) : new AIPS_Logger());
	}

	/**
	 * Evaluate candidate topics for duplicates against existing topics AND published posts.
	 *
	 * @param array    $topics    Array of topic data arrays.
	 * @param int|null $author_id Optional author ID filter.
	 * @return array Augmented topic arrays with metadata and adjusted scores.
	 */
	public function evaluate_topics_for_duplicates(array $topics, ?int $author_id = null) {
		if (empty($topics) || !$this->embeddings_service->is_embeddings_supported()) {
			return $topics;
		}

		$threshold = (float) $this->config->get_option('aips_deduplication_threshold', 0.85);

		// 1. Gather existing published post embeddings
		$post_types      = (array) $this->config->get_option('aips_indexer_post_types', array('post'));
		$post_embeddings = $this->embeddings_repo->get_all_for_similarity('post', $post_types, 'publish');

		$candidate_posts = array();
		foreach ($post_embeddings as $p_row) {
			$vec = json_decode($p_row->embedding, true);
			if (is_array($vec)) {
				$candidate_posts[] = array(
					'id'        => (int) $p_row->object_id,
					'type'      => 'post',
					'title'     => get_the_title((int) $p_row->object_id),
					'embedding' => $vec,
				);
			}
		}

		// 2. Gather existing topic embeddings
		$topic_embeddings = $this->embeddings_repo->get_all_for_similarity('topic');
		$candidate_topics = array();
		foreach ($topic_embeddings as $t_row) {
			$vec = json_decode($t_row->embedding, true);
			if (is_array($vec)) {
				$candidate_topics[] = array(
					'id'        => (int) $t_row->object_id,
					'type'      => 'topic',
					'title'     => '', // filled if matched
					'embedding' => $vec,
				);
			}
		}

		$all_candidates = array_merge($candidate_posts, $candidate_topics);

		foreach ($topics as &$topic) {
			$text = isset($topic['topic_title']) ? (string) $topic['topic_title'] : '';
			if (empty($text)) {
				continue;
			}

			$embedding = $this->embeddings_service->generate_embedding($text);
			if (is_wp_error($embedding) || !is_array($embedding)) {
				continue;
			}

			$best_similarity = 0.0;
			$best_match      = '';
			$best_type       = '';
			$best_id         = 0;

			foreach ($all_candidates as $cand) {
				if (count($cand['embedding']) !== count($embedding)) {
					continue;
				}

				$sim = $this->embeddings_service->calculate_similarity($embedding, $cand['embedding']);
				if (!is_wp_error($sim) && $sim > $best_similarity) {
					$best_similarity = (float) $sim;
					$best_type       = $cand['type'];
					$best_id         = $cand['id'];
					$best_match      = !empty($cand['title']) ? $cand['title'] : "{$cand['type']} #{$cand['id']}";
				}
			}

			$metadata = isset($topic['metadata']) ? json_decode($topic['metadata'], true) : array();
			if (!is_array($metadata)) {
				$metadata = array();
			}

			$metadata['embedding'] = $embedding;

			if ($best_similarity >= $threshold) {
				$metadata['potential_duplicate']  = true;
				$metadata['duplicate_similarity'] = round($best_similarity, 4);
				$metadata['duplicate_match']      = $best_match;
				$metadata['duplicate_type']       = $best_type;
				$metadata['duplicate_id']         = $best_id;

				// Lower score for potential duplicate
				$topic['score'] = max(0, ((int) (isset($topic['score']) ? $topic['score'] : 50)) - 20);
			} else {
				$metadata['potential_duplicate'] = false;
			}

			$topic['metadata'] = wp_json_encode($metadata);
		}
		unset($topic);

		return $topics;
	}

	/**
	 * Pre-generation gatekeeper guard: check if a topic/title is semantically too similar to an existing post.
	 *
	 * @param string $title     Title/topic to evaluate.
	 * @param string $post_type Target post type.
	 * @return array{is_duplicate: bool, similarity: float, matched_post_id: int, matched_title: string, action: string}
	 */
	public function check_pre_generation_duplicate($title, $post_type = 'post') {
		$title = trim((string) $title);
		if (empty($title) || !$this->embeddings_service->is_embeddings_supported()) {
			return array(
				'is_duplicate'    => false,
				'similarity'      => 0.0,
				'matched_post_id' => 0,
				'matched_title'   => '',
				'action'          => 'allow',
			);
		}

		$threshold = (float) $this->config->get_option('aips_deduplication_threshold', 0.85);
		$mode      = (string) $this->config->get_option('aips_deduplication_mode', 'warn'); // 'warn' or 'block'

		$embedding = $this->embeddings_service->generate_embedding($title);
		if (is_wp_error($embedding) || !is_array($embedding)) {
			return array(
				'is_duplicate'    => false,
				'similarity'      => 0.0,
				'matched_post_id' => 0,
				'matched_title'   => '',
				'action'          => 'allow',
			);
		}

		$candidates = $this->embeddings_repo->get_all_for_similarity('post', array($post_type), 'publish');
		$best_sim   = 0.0;
		$best_id    = 0;
		$best_title = '';

		foreach ($candidates as $cand) {
			$vec = json_decode($cand->embedding, true);
			if (is_array($vec) && count($vec) === count($embedding)) {
				$sim = $this->embeddings_service->calculate_similarity($embedding, $vec);
				if (!is_wp_error($sim) && $sim > $best_sim) {
					$best_sim = (float) $sim;
					$best_id  = (int) $cand->object_id;
				}
			}
		}

		$is_dup = ($best_sim >= $threshold);
		if ($is_dup && $best_id > 0) {
			$best_title = get_the_title($best_id);
		}

		$action = 'allow';
		if ($is_dup) {
			$action = ('block' === $mode) ? 'block' : 'warn';
			$this->logger->log(
				sprintf(
					'Pre-generation duplicate check: "%s" matches existing post #%d ("%s") with similarity %.2f%% (Action: %s)',
					$title,
					$best_id,
					$best_title,
					$best_sim * 100,
					$action
				),
				('block' === $mode) ? 'warning' : 'info'
			);
		}

		return array(
			'is_duplicate'    => $is_dup,
			'similarity'      => round($best_sim, 4),
			'matched_post_id' => $best_id,
			'matched_title'   => $best_title,
			'action'          => $action,
		);
	}

	/**
	 * Run a site-wide Content Cannibalization / Duplicate Post audit.
	 *
	 * @param float $threshold Minimum similarity threshold (default: 0.80).
	 * @param int   $limit     Max clusters to return.
	 * @return array List of cannibalizing post pairs with similarity scores and URLs.
	 */
	public function get_cannibalization_audit_results($threshold = 0.80, $limit = 50) {
		$threshold = (float) $threshold;
		$limit     = absint($limit);

		$pairs = $this->relationships_repo->get_top_duplicate_pairs($threshold, $limit);
		$results = array();

		foreach ($pairs as $pair) {
			$results[] = array(
				'source_id'        => (int) $pair->source_id,
				'source_title'     => $pair->source_title,
				'source_post_type' => $pair->source_post_type,
				'source_url'       => get_permalink((int) $pair->source_id),
				'source_edit_url'  => get_edit_post_link((int) $pair->source_id, ''),
				'source_date'      => $pair->source_date,
				'target_id'        => (int) $pair->target_id,
				'target_title'     => $pair->target_title,
				'target_post_type' => $pair->target_post_type,
				'target_url'       => get_permalink((int) $pair->target_id),
				'target_edit_url'  => get_edit_post_link((int) $pair->target_id, ''),
				'target_date'      => $pair->target_date,
				'similarity'       => round((float) $pair->similarity, 4),
				'similarity_pct'   => round(((float) $pair->similarity) * 100, 1),
			);
		}

		return $results;
	}
}
