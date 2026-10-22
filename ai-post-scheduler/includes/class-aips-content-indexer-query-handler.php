<?php
/**
 * Content Indexer Query Handler
 *
 * Handles AJAX query / read endpoints for Content Indexer.
 *
 * @package AI_Post_Scheduler
 * @since 3.7.19
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Content_Indexer_Query_Handler
 */
class AIPS_Content_Indexer_Query_Handler {

	/**
	 * @var AIPS_Content_Indexer_Service
	 */
	private $indexer_service;

	/**
	 * @var AIPS_Related_Posts_Service
	 */
	private $related_service;

	/**
	 * @var AIPS_Embeddings_Repository
	 */
	private $embeddings_repo;

	/**
	 * @var AIPS_Config
	 */
	private $config;

	/**
	 * @var AIPS_Similarity_Evaluator
	 */
	private $similarity_evaluator;

	/**
	 * @var AIPS_Embeddings_Rate_Limiter
	 */
	private $rate_limiter;

	public function __construct(
		?AIPS_Content_Indexer_Service $indexer_service = null,
		?AIPS_Related_Posts_Service $related_service = null,
		?AIPS_Embeddings_Repository $embeddings_repo = null,
		?AIPS_Config $config = null,
		?AIPS_Similarity_Evaluator $similarity_evaluator = null,
		?AIPS_Embeddings_Rate_Limiter $rate_limiter = null
	) {
		$container = AIPS_Container::get_instance();
		$this->indexer_service      = $indexer_service ?: ($container->has(AIPS_Content_Indexer_Service::class) ? $container->make(AIPS_Content_Indexer_Service::class) : new AIPS_Content_Indexer_Service());
		$this->related_service      = $related_service ?: ($container->has(AIPS_Related_Posts_Service::class) ? $container->make(AIPS_Related_Posts_Service::class) : new AIPS_Related_Posts_Service());
		$this->embeddings_repo      = $embeddings_repo ?: ($container->has(AIPS_Embeddings_Repository::class) ? $container->make(AIPS_Embeddings_Repository::class) : new AIPS_Embeddings_Repository());
		$this->config               = $config ?: AIPS_Config::get_instance();
		$this->similarity_evaluator = $similarity_evaluator ?: ($container->has(AIPS_Similarity_Evaluator::class) ? $container->make(AIPS_Similarity_Evaluator::class) : new AIPS_Similarity_Evaluator());
		$this->rate_limiter         = $rate_limiter ?: ($container->has(AIPS_Embeddings_Rate_Limiter::class) ? $container->make(AIPS_Embeddings_Rate_Limiter::class) : new AIPS_Embeddings_Rate_Limiter());
	}

	public function get_status() {
		AIPS_Ajax_Guard::check_admin();

		$post_types  = (array) $this->config->get_option('aips_indexer_post_types', array('post'));
		$status      = $this->indexer_service->get_indexing_status($post_types);
		$stats       = $this->embeddings_repo->get_stats();
		$stored_dims = $this->embeddings_repo->get_stored_dimensions();
		$active_dims = (int) $this->config->get_option('aips_embeddings_dimensions', 1536);

		$dimension_mismatch = (!empty($stored_dims) && (count($stored_dims) > 1 || !in_array($active_dims, $stored_dims, true)));

		AIPS_Ajax_Response::success(array(
			'embeddings_enabled' => (bool) $this->config->get_option('aips_embeddings_enabled', true),
			'status'             => $status,
			'stats'              => $stats,
			'stored_dimensions'  => $stored_dims,
			'active_dimensions'  => $active_dims,
			'dimension_mismatch' => $dimension_mismatch,
			'cooldown'           => $this->rate_limiter->get_cooldown_status(),
			'queue_status'       => $this->indexer_service->get_queue_status(),
		));
	}

	public function get_graph() {
		AIPS_Ajax_Guard::check_admin();

		$post_id        = isset($_POST['post_id']) ? absint($_POST['post_id']) : 0;
		$source_type    = isset($_POST['source_type']) ? sanitize_key($_POST['source_type']) : 'post';
		$limit          = isset($_POST['limit']) ? absint($_POST['limit']) : 15;
		$min_similarity = isset($_POST['min_similarity']) ? (float) $_POST['min_similarity'] : 0.50;

		if ($post_id <= 0) {
			if ('topic' === $source_type) {
				$indexed_topics = $this->embeddings_repo->get_all_topics_for_similarity();
				if (!empty($indexed_topics)) {
					$post_id = (int) $indexed_topics[0]->object_id;
				}
			} else {
				$post_types  = (array) $this->config->get_option('aips_indexer_post_types', array('post'));
				$indexed_ids = $this->embeddings_repo->get_all_for_similarity('post', $post_types, 'publish');
				if (!empty($indexed_ids)) {
					$post_id = (int) $indexed_ids[0]->object_id;
				}
			}
		}

		if ($post_id <= 0) {
			AIPS_Ajax_Response::error(__('No indexed items found to visualize.', 'ai-post-scheduler'));
		}

		$graph = $this->related_service->get_graph_data_for_post($post_id, $limit, $min_similarity, $source_type);

		AIPS_Ajax_Response::success(array(
			'post_id'     => $post_id,
			'source_type' => $source_type,
			'graph'       => $graph,
		));
	}

	public function search_posts() {
		AIPS_Ajax_Guard::check_admin();

		$query      = isset($_POST['q']) ? sanitize_text_field($_POST['q']) : '';
		$post_types = (array) $this->config->get_option('aips_indexer_post_types', array('post'));

		$args = array(
			'post_type'      => $post_types,
			'post_status'    => 'publish',
			'posts_per_page' => 15,
		);

		if (!empty($query)) {
			$args['s'] = $query;
		}

		$posts = get_posts($args);

		$results = array();
		if (!empty($posts)) {
			$post_ids    = wp_list_pluck($posts, 'ID');
			$indexed_map = $this->embeddings_repo->get_by_post_ids($post_ids);

			foreach ($posts as $p) {
				$is_indexed = isset($indexed_map[$p->ID]);
				$results[]  = array(
					'id'         => $p->ID,
					'title'      => $p->post_title,
					'label'      => $p->post_title . " ({$p->post_type} #{$p->ID})",
					'post_type'  => $p->post_type,
					'is_indexed' => $is_indexed,
				);
			}
		}

		AIPS_Ajax_Response::success(array('results' => $results));
	}

	public function fetch_meow_environments() {
		AIPS_Ajax_Guard::check_admin();

		$meow = new AIPS_Meow_AI_Provider();
		if (!$meow->is_available()) {
			AIPS_Ajax_Response::error(__('Meow Apps AI Engine is not available.', 'ai-post-scheduler'));
		}

		$environments = $meow->get_embeddings_environments();

		AIPS_Ajax_Response::success(array(
			'environments' => $environments,
			'count'        => count($environments),
		));
	}

	public function get_post_clusters() {
		AIPS_Ajax_Guard::check_admin();

		$threshold = isset($_POST['threshold']) ? (float) $_POST['threshold'] : (float) $this->config->get_option('aips_indexer_post_cluster_threshold', 0.65);
		$min_size  = isset($_POST['min_size']) ? max(2, absint($_POST['min_size'])) : 2;
		$clusters  = $this->similarity_evaluator->detect_post_clusters($threshold, $min_size);

		$clustered_ids = array();
		foreach ($clusters as $c) {
			$clustered_ids = array_merge($clustered_ids, isset($c['member_ids']) ? (array) $c['member_ids'] : array());
		}
		$flagged        = $this->similarity_evaluator->get_orphan_posts($threshold, 'hybrid', $clustered_ids);
		$orphans        = array();
		$unlinked_posts = 0;
		foreach ($flagged as $row) {
			$type = isset($row['orphan_type']) ? $row['orphan_type'] : '';
			if ($type !== 'unlinked_post') {
				$orphans[] = $row;
			}
			if ($type === 'unlinked_post' || $type === 'isolated_and_unlinked') {
				$unlinked_posts++;
			}
		}

		$total_clusters  = count($clusters);
		$clustered_posts = 0;
		$cohesion_sum    = 0.0;
		foreach ($clusters as $c) {
			$clustered_posts += isset($c['post_count']) ? (int) $c['post_count'] : (isset($c['posts']) ? count($c['posts']) : 0);
			$cohesion_sum    += isset($c['cohesion_pct']) ? (float) $c['cohesion_pct'] : 0.0;
		}
		$avg_cohesion = $total_clusters > 0 ? round($cohesion_sum / $total_clusters, 1) : 0;
		$orphan_count = count($orphans);
		$cluster_meta = $this->similarity_evaluator->get_last_cluster_meta();

		AIPS_Ajax_Response::success(array(
			'clusters'  => array_values($clusters),
			'orphans'   => $orphans,
			'count'     => $total_clusters,
			'truncated' => !empty($cluster_meta['truncated']),
			'meta'      => $cluster_meta,
			'stats'     => array(
				'total_clusters'  => $total_clusters,
				'clustered_posts' => $clustered_posts,
				'avg_cohesion'    => $avg_cohesion,
				'orphan_posts'    => $orphan_count,
				'unlinked_posts'  => $unlinked_posts,
			),
		));
	}
}
