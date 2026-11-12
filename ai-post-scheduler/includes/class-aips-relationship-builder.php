<?php
/**
 * Relationship Builder
 *
 * Computes the "related posts" neighbours of one or more posts by streaming
 * every stored embedding past all of them in a single pass.
 *
 * The earlier approach loaded and decoded every vector for each post it indexed
 * (O(N) decodes per post, so O(N^2) per run), kept every decoded vector in
 * memory and wrote a transient per vector. Here, a batch of S source posts
 * costs one pass over the N stored vectors:
 *
 *   - vectors are read in small pages and decoded straight from the stored
 *     float32 blob (no transients, no per-vector caches), so memory stays flat;
 *   - every vector is unit-normalised once, so cosine similarity is a plain
 *     dot product;
 *   - only the best K matches per source are kept.
 *
 * Decode cost is paid once per batch instead of once per post, which is why
 * callers should hand over as many sources as fit in their time budget.
 *
 * @package AI_Post_Scheduler
 * @since 3.7.12
 */

if (!defined('ABSPATH')) {
	exit;
}

class AIPS_Relationship_Builder {

	/**
	 * Stored vectors read per database page.
	 */
	const PAGE_SIZE = 250;

	/**
	 * Relation type written for post-to-post neighbours.
	 */
	const RELATION_TYPE = 'related_post';

	/**
	 * Rough seconds to read, decode and normalise one stored vector (1536 dimensions).
	 * Paid once per pass whatever the number of sources.
	 */
	const SECONDS_PER_CANDIDATE = 0.0002;

	/**
	 * Rough seconds for one source/candidate dot product (1536 dimensions).
	 */
	const SECONDS_PER_PAIR = 0.00006;

	/**
	 * @var AIPS_Embeddings_Repository
	 */
	private $embeddings_repo;

	/**
	 * @var AIPS_Relationships_Repository
	 */
	private $relationships_repo;

	/**
	 * @var AIPS_Config
	 */
	private $config;

	/**
	 * @var int Stored vectors read per database page.
	 */
	private $page_size = self::PAGE_SIZE;

	/**
	 * @param AIPS_Embeddings_Repository|null   $embeddings_repo    Embeddings repository.
	 * @param AIPS_Relationships_Repository|null $relationships_repo Relationships repository.
	 * @param AIPS_Config|null                  $config             Config.
	 */
	public function __construct(
		?AIPS_Embeddings_Repository $embeddings_repo = null,
		?AIPS_Relationships_Repository $relationships_repo = null,
		?AIPS_Config $config = null
	) {
		$container = AIPS_Container::get_instance();

		$this->embeddings_repo    = $embeddings_repo ?: ($container->has(AIPS_Embeddings_Repository::class) ? $container->make(AIPS_Embeddings_Repository::class) : new AIPS_Embeddings_Repository());
		$this->relationships_repo = $relationships_repo ?: ($container->has(AIPS_Relationships_Repository::class) ? $container->make(AIPS_Relationships_Repository::class) : new AIPS_Relationships_Repository());
		$this->config             = $config ?: AIPS_Config::get_instance();
	}

	/**
	 * Override the database page size (smaller pages use less memory).
	 *
	 * @param int $page_size Vectors per page (at least 1).
	 * @return void
	 */
	public function set_page_size(int $page_size): void {
		$this->page_size = max(1, $page_size);
	}

	/**
	 * Post types whose embeddings are compared (the indexer post types setting).
	 *
	 * @return string[]
	 */
	public function get_post_types(): array {
		$types = array_values(array_filter(array_map('sanitize_key', (array) $this->config->get_option('aips_indexer_post_types', array('post')))));

		return !empty($types) ? $types : array('post');
	}

	/**
	 * How many stored vectors a pass compares each source against.
	 *
	 * @return int
	 */
	public function count_candidates(): int {
		return $this->embeddings_repo->count_similarity_candidates($this->get_post_types(), 'publish');
	}

	/**
	 * How many sources fit in a time budget at the current library size.
	 *
	 * @param float $seconds Time budget for one pass.
	 * @param int   $max     Upper bound.
	 * @return int At least 1.
	 */
	public function get_batch_size_for_budget(float $seconds = 12.0, int $max = 50): int {
		$candidates = $this->count_candidates();
		$per_source = max(0.01, $candidates * self::SECONDS_PER_PAIR);
		$fixed      = $candidates * self::SECONDS_PER_CANDIDATE;

		return max(1, min($max, (int) floor(max(0.0, $seconds - $fixed) / $per_source)));
	}

	/**
	 * Rough seconds to compute relationships for $sources posts.
	 *
	 * @param int $sources Number of source posts.
	 * @return float
	 */
	public function estimate_seconds(int $sources): float {
		$candidates = $this->count_candidates();
		$per_source = max(0.01, $candidates * self::SECONDS_PER_PAIR);
		$per_pass   = $candidates * self::SECONDS_PER_CANDIDATE;
		$passes     = (int) ceil($sources / max(1, $this->get_batch_size_for_budget()));

		return $sources * $per_source + $passes * $per_pass;
	}

	/**
	 * Compute and store the top related posts for each source post.
	 *
	 * Sources without a stored embedding are skipped and keep whatever they had.
	 *
	 * @param int[]      $post_ids Source post IDs.
	 * @param int        $top_k    Neighbours kept per source.
	 * @param float      $min_sim  Minimum cosine similarity.
	 * @param string[]|null $post_types Candidate post types (default: indexer setting).
	 * @return array<int,int> Source post ID => neighbours saved.
	 */
	public function compute_for_posts(array $post_ids, int $top_k = 15, float $min_sim = 0.50, ?array $post_types = null): array {
		$post_ids = array_values(array_unique(array_filter(array_map('absint', $post_ids))));
		if (empty($post_ids)) {
			return array();
		}

		$top_k      = max(1, $top_k);
		$post_types = $post_types !== null ? $post_types : $this->get_post_types();

		// Sources: decode and normalise once each.
		$sources = array();
		$rows    = $this->embeddings_repo->get_by_post_ids($post_ids);
		foreach ($post_ids as $id) {
			if (!isset($rows[$id])) {
				continue;
			}

			$vector = self::normalize(self::decode_vector($rows[$id]->embedding));
			if ($vector !== null) {
				$sources[$id] = $vector;
			}
		}

		if (empty($sources)) {
			return array();
		}

		$lists  = array_fill_keys(array_keys($sources), array());
		$floors = array_fill_keys(array_keys($sources), $min_sim);

		// Candidates: stream one page at a time.
		$after_id = 0;
		while (true) {
			$page = $this->embeddings_repo->get_similarity_candidates_page($post_types, 'publish', $after_id, $this->page_size);
			if (empty($page)) {
				break;
			}

			foreach ($page as $row) {
				$candidate_id = (int) $row->object_id;
				$after_id     = max($after_id, $candidate_id);

				$candidate = self::normalize(self::decode_vector($row->embedding));
				if ($candidate === null) {
					continue;
				}

				$dimensions = count($candidate);

				foreach ($sources as $source_id => $source) {
					if ($source_id === $candidate_id || count($source) !== $dimensions) {
						continue;
					}

					$similarity = self::dot($source, $candidate, $dimensions);
					if ($similarity < $floors[$source_id]) {
						continue;
					}

					$lists[$source_id][] = array($similarity, $candidate_id);

					// Keep the working list small; once K are held, anything below the Kth is noise.
					if (count($lists[$source_id]) >= $top_k * 4) {
						$lists[$source_id]  = self::top($lists[$source_id], $top_k);
						$floors[$source_id] = max($min_sim, $lists[$source_id][count($lists[$source_id]) - 1][0]);
					}
				}
			}

			if (count($page) < $this->page_size) {
				break;
			}

			unset($page);
		}

		$saved = array();
		foreach ($sources as $source_id => $unused) {
			$targets = array();
			foreach (self::top($lists[$source_id], $top_k) as $match) {
				$targets[] = array(
					'target_type' => 'post',
					'target_id'   => $match[1],
					'similarity'  => (float) $match[0],
				);
			}

			$this->relationships_repo->sync_for_source('post', $source_id, $targets, self::RELATION_TYPE);
			$saved[$source_id] = count($targets);
		}

		return $saved;
	}

	/**
	 * Decode a stored vector: packed float32, or legacy JSON.
	 *
	 * Deliberately bypasses AIPS_Embeddings_Repository::decode_embedding(), which
	 * caches every vector in memory and in a transient.
	 *
	 * @param mixed $raw Stored value.
	 * @return float[] Zero-indexed vector, or empty when unreadable.
	 */
	public static function decode_vector($raw): array {
		if (is_array($raw)) {
			return array_map('floatval', array_values($raw));
		}

		if (!is_string($raw) || $raw === '') {
			return array();
		}

		// Legacy JSON vectors start with '[' or '{'. A packed float32 vector can start with
		// the same byte (about 1 in 130), so JSON is only used when it really parses, and
		// anything else is read as binary when it is a whole number of floats.
		$trimmed = ltrim($raw);
		if ($trimmed !== '' && ($trimmed[0] === '[' || $trimmed[0] === '{')) {
			$decoded = json_decode($trimmed, true);
			if (is_array($decoded)) {
				return array_map('floatval', array_values($decoded));
			}
		}

		if (strlen($raw) % 4 !== 0) {
			return array();
		}

		$unpacked = @unpack('f*', $raw);

		return is_array($unpacked) ? array_values($unpacked) : array();
	}

	/**
	 * Scale a vector to unit length.
	 *
	 * @param float[] $vector Vector.
	 * @return float[]|null Null for an empty or zero-length vector.
	 */
	public static function normalize(array $vector): ?array {
		$count = count($vector);
		if ($count === 0) {
			return null;
		}

		$sum = 0.0;
		for ($i = 0; $i < $count; $i++) {
			$sum += $vector[$i] * $vector[$i];
		}

		if ($sum < 1e-18) {
			return null;
		}

		$inverse = 1.0 / sqrt($sum);
		for ($i = 0; $i < $count; $i++) {
			$vector[$i] *= $inverse;
		}

		return $vector;
	}

	/**
	 * Dot product of two equal-length vectors (cosine similarity when both are unit length).
	 *
	 * @param float[] $a Vector.
	 * @param float[] $b Vector.
	 * @param int     $n Length.
	 * @return float Clamped to [0, 1].
	 */
	public static function dot(array $a, array $b, int $n): float {
		$sum = 0.0;
		for ($i = 0; $i < $n; $i++) {
			$sum += $a[$i] * $b[$i];
		}

		return (float) max(0.0, min(1.0, $sum));
	}

	/**
	 * Best $k [similarity, id] pairs, highest similarity first (ties by lower ID).
	 *
	 * @param array[] $matches List of [similarity, id].
	 * @param int     $k       Number to keep.
	 * @return array[]
	 */
	private static function top(array $matches, int $k): array {
		usort($matches, function ($x, $y) {
			if ($x[0] === $y[0]) {
				return $x[1] <=> $y[1];
			}
			return $y[0] <=> $x[0];
		});

		return array_slice($matches, 0, $k);
	}
}
