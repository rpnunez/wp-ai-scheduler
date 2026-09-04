<?php
/**
 * Related Posts Service
 *
 * High-performance querying, caching, HTML rendering, and graph generation
 * for semantic related posts across the site and generation pipelines.
 *
 * @package AI_Post_Scheduler
 * @since 3.0.0
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Related_Posts_Service
 *
 * Service for retrieving, formatting, and rendering semantic related posts.
 */
class AIPS_Related_Posts_Service {

	/**
	 * @var AIPS_Relationships_Repository
	 */
	private $relationships_repo;

	/**
	 * @var AIPS_Embeddings_Repository
	 */
	private $embeddings_repo;

	/**
	 * @var AIPS_Embeddings_Service
	 */
	private $embeddings_service;

	/**
	 * @var AIPS_Config
	 */
	private $config;

	/**
	 * Cache group name.
	 */
	const CACHE_GROUP = 'aips_related_posts';

	/**
	 * Initialize service.
	 */
	public function __construct(
		?AIPS_Relationships_Repository $relationships_repo = null,
		?AIPS_Embeddings_Repository $embeddings_repo = null,
		?AIPS_Embeddings_Service $embeddings_service = null,
		?AIPS_Config $config = null
	) {
		$container = AIPS_Container::get_instance();

		$this->relationships_repo = $relationships_repo ?: ($container->has(AIPS_Relationships_Repository::class) ? $container->make(AIPS_Relationships_Repository::class) : new AIPS_Relationships_Repository());
		$this->embeddings_repo    = $embeddings_repo    ?: ($container->has(AIPS_Embeddings_Repository::class) ? $container->make(AIPS_Embeddings_Repository::class) : new AIPS_Embeddings_Repository());
		$this->embeddings_service = $embeddings_service ?: ($container->has(AIPS_Embeddings_Service::class) ? $container->make(AIPS_Embeddings_Service::class) : new AIPS_Embeddings_Service());
		$this->config             = $config             ?: AIPS_Config::get_instance();
	}

	/**
	 * Retrieve related posts for a given post ID.
	 *
	 * Uses a multi-tiered retrieval strategy:
	 * 1. WordPress Object Cache / transient layer
	 * 2. Persistent precomputed relationships table (fast-path)
	 * 3. On-demand in-memory vector comparison (fallback)
	 *
	 * @param int   $post_id WordPress post ID.
	 * @param array $args    Optional overrides (count, min_similarity, post_types).
	 * @return array Array of related post objects with similarity scores.
	 */
	public function get_related_posts($post_id, array $args = array()) {
		$post_id = absint($post_id);
		if ($post_id <= 0) {
			return array();
		}

		$defaults = array(
			'count'          => (int) $this->config->get_option('aips_related_posts_count', 4),
			'min_similarity' => (float) $this->config->get_option('aips_indexer_similarity_threshold', 0.65),
			'post_types'     => (array) $this->config->get_option('aips_indexer_post_types', array('post')),
			'exclude_ids'    => array($post_id),
		);

		$parsed_args = wp_parse_args($args, $defaults);
		$cache_key   = 'related_' . $post_id . '_' . md5(wp_json_encode($parsed_args));

		$cached = wp_cache_get($cache_key, self::CACHE_GROUP);
		if (false !== $cached && is_array($cached)) {
			return $cached;
		}

		// Tier 1: Query precomputed relationships
		$rel_rows = $this->relationships_repo->get_related(
			'post',
			$post_id,
			$parsed_args['count'] * 2,
			$parsed_args['min_similarity'],
			'related_post'
		);

		$related_posts = array();
		$found_ids     = array();

		foreach ($rel_rows as $row) {
			$target_id = (int) $row->target_id;
			if (in_array($target_id, $parsed_args['exclude_ids'], true)) {
				continue;
			}

			$post = get_post($target_id);
			if (!$post || 'publish' !== $post->post_status) {
				continue;
			}

			if (!empty($parsed_args['post_types']) && !in_array($post->post_type, $parsed_args['post_types'], true)) {
				continue;
			}

			$related_posts[] = array(
				'id'         => $target_id,
				'post'       => $post,
				'title'      => $post->post_title,
				'url'        => get_permalink($post->ID),
				'similarity' => (float) $row->similarity,
				'date'       => $post->post_date,
				'post_type'  => $post->post_type,
			);

			$found_ids[] = $target_id;

			if (count($related_posts) >= $parsed_args['count']) {
				break;
			}
		}

		// Tier 2: If precomputed count is insufficient, fall back to on-the-fly vector comparison
		if (count($related_posts) < $parsed_args['count']) {
			$source_emb = $this->embeddings_repo->get_by_post_id($post_id);
			if ($source_emb && !empty($source_emb->embedding)) {
				$source_vec = json_decode($source_emb->embedding, true);
				if (is_array($source_vec)) {
					$all_candidates = $this->embeddings_repo->get_all_for_similarity('post', $parsed_args['post_types'], 'publish');
					$candidate_vecs = array();

					foreach ($all_candidates as $cand) {
						$cid = (int) $cand->object_id;
						if ($cid === $post_id || in_array($cid, $found_ids, true)) {
							continue;
						}
						$cvec = json_decode($cand->embedding, true);
						if (is_array($cvec) && count($cvec) === count($source_vec)) {
							$candidate_vecs[] = array(
								'id'        => $cid,
								'embedding' => $cvec,
							);
						}
					}

					$neighbors = $this->embeddings_service->find_nearest_neighbors(
						$source_vec,
						$candidate_vecs,
						$parsed_args['count'] - count($related_posts)
					);

					foreach ($neighbors as $n) {
						if ($n['similarity'] < $parsed_args['min_similarity']) {
							continue;
						}

						$nid  = (int) $n['id'];
						$post = get_post($nid);
						if ($post && 'publish' === $post->post_status) {
							$related_posts[] = array(
								'id'         => $nid,
								'post'       => $post,
								'title'      => $post->post_title,
								'url'        => get_permalink($post->ID),
								'similarity' => (float) $n['similarity'],
								'date'       => $post->post_date,
								'post_type'  => $post->post_type,
							);
						}
					}
				}
			}
		}

		wp_cache_set($cache_key, $related_posts, self::CACHE_GROUP, HOUR_IN_SECONDS * 12);

		return $related_posts;
	}

	/**
	 * Retrieve related articles for an arbitrary topic/prompt text (used during AI Post Generation).
	 *
	 * @param string $topic_text     Topic title or keyword prompt.
	 * @param int    $limit          Max related articles to retrieve.
	 * @param float  $min_similarity Similarity threshold.
	 * @return array Array of ['id' => int, 'title' => string, 'url' => string, 'similarity' => float]
	 */
	public function get_related_posts_for_topic($topic_text, $limit = 3, $min_similarity = 0.55) {
		$topic_text = trim((string) $topic_text);
		if (empty($topic_text)) {
			return array();
		}

		$embedding = $this->embeddings_service->generate_embedding($topic_text);
		if (is_wp_error($embedding) || !is_array($embedding)) {
			return array();
		}

		$post_types     = (array) $this->config->get_option('aips_indexer_post_types', array('post'));
		$all_candidates = $this->embeddings_repo->get_all_for_similarity('post', $post_types, 'publish');

		$candidate_vecs = array();
		foreach ($all_candidates as $cand) {
			$cvec = json_decode($cand->embedding, true);
			if (is_array($cvec) && count($cvec) === count($embedding)) {
				$candidate_vecs[] = array(
					'id'        => (int) $cand->object_id,
					'embedding' => $cvec,
				);
			}
		}

		if (empty($candidate_vecs)) {
			return array();
		}

		$neighbors = $this->embeddings_service->find_nearest_neighbors($embedding, $candidate_vecs, $limit * 2);
		$results   = array();

		foreach ($neighbors as $n) {
			if ($n['similarity'] < $min_similarity) {
				continue;
			}

			$post = get_post((int) $n['id']);
			if ($post && 'publish' === $post->post_status) {
				$results[] = array(
					'id'         => (int) $post->ID,
					'title'      => $post->post_title,
					'url'        => get_permalink($post->ID),
					'similarity' => round((float) $n['similarity'], 4),
				);
			}

			if (count($results) >= $limit) {
				break;
			}
		}

		return $results;
	}

	/**
	 * Retrieve interactive graph data payload for a post.
	 *
	 * @param int   $post_id        Post ID.
	 * @param int   $limit          Max neighbors.
	 * @param float $min_similarity Threshold.
	 * @return array Graph structure {nodes: array, edges: array}.
	 */
	public function get_graph_data_for_post($post_id, $limit = 15, $min_similarity = 0.50) {
		return $this->relationships_repo->get_graph_data('post', $post_id, $limit, $min_similarity);
	}

	/**
	 * Render HTML markup for related posts.
	 *
	 * @param int   $post_id WordPress post ID.
	 * @param array $args    Display configuration.
	 * @return string HTML output.
	 */
	public function render_related_posts_html($post_id, array $args = array()) {
		$defaults = array(
			'heading'         => $this->config->get_option('aips_related_posts_heading', __('Related Articles', 'ai-post-scheduler')),
			'layout'          => $this->config->get_option('aips_related_posts_layout', 'grid'),
			'count'           => (int) $this->config->get_option('aips_related_posts_count', 4),
			'show_thumbnails' => (bool) $this->config->get_option('aips_related_posts_show_thumbnails', true),
			'show_excerpts'   => (bool) $this->config->get_option('aips_related_posts_show_excerpts', true),
			'class'           => '',
		);

		$parsed = wp_parse_args($args, $defaults);
		$items  = $this->get_related_posts($post_id, $parsed);

		if (empty($items)) {
			return '';
		}

		$layout_class = ('list' === $parsed['layout']) ? 'aips-related-list' : 'aips-related-grid';
		$custom_class = sanitize_html_class($parsed['class']);

		ob_start();
		?>
		<div class="aips-related-posts-container <?php echo esc_attr("{$layout_class} {$custom_class}"); ?>">
			<?php if (!empty($parsed['heading'])) : ?>
				<h3 class="aips-related-posts-heading"><?php echo esc_html($parsed['heading']); ?></h3>
			<?php endif; ?>

			<div class="aips-related-posts-items">
				<?php foreach ($items as $item) : 
					$post = $item['post'];
				?>
					<article class="aips-related-post-card">
						<?php if ($parsed['show_thumbnails'] && has_post_thumbnail($post->ID)) : ?>
							<div class="aips-related-post-thumb">
								<a href="<?php echo esc_url($item['url']); ?>" tabindex="-1" aria-hidden="true">
									<?php echo get_the_post_thumbnail($post->ID, 'medium', array('class' => 'aips-related-img')); ?>
								</a>
							</div>
						<?php endif; ?>

						<div class="aips-related-post-content">
							<h4 class="aips-related-post-title">
								<a href="<?php echo esc_url($item['url']); ?>">
									<?php echo esc_html($item['title']); ?>
								</a>
							</h4>

							<?php if ($parsed['show_excerpts']) : 
								$excerpt = !empty($post->post_excerpt) ? $post->post_excerpt : wp_trim_words($post->post_content, 18);
							?>
								<p class="aips-related-post-excerpt">
									<?php echo esc_html($excerpt); ?>
								</p>
							<?php endif; ?>
						</div>
					</article>
				<?php endforeach; ?>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}
}
