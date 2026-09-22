<?php
/**
 * Post Clusters Service
 *
 * Discovers organic post clusters via connected community graph detection,
 * identifies orphan content, manages pillar/spoke relationships, and drives
 * AI-assisted content gap recommendations.
 *
 * @package AI_Post_Scheduler
 * @since 3.6.7
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Post_Clusters_Service
 */
class AIPS_Post_Clusters_Service {

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
	 * @var AIPS_AI_Service_Interface
	 */
	private $ai_service;

	/**
	 * @var AIPS_Config
	 */
	private $config;

	/**
	 * @var AIPS_Logger_Interface
	 */
	private $logger;

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
	 * @param AIPS_Embeddings_Repository|null    $embeddings_repo
	 * @param AIPS_Relationships_Repository|null $relationships_repo
	 * @param AIPS_Embeddings_Service|null       $embeddings_service
	 * @param AIPS_AI_Service_Interface|null     $ai_service
	 * @param AIPS_Config|null                   $config
	 * @param AIPS_Logger_Interface|null         $logger
	 */
	public function __construct(
		?AIPS_Embeddings_Repository $embeddings_repo = null,
		?AIPS_Relationships_Repository $relationships_repo = null,
		?AIPS_Embeddings_Service $embeddings_service = null,
		?AIPS_AI_Service_Interface $ai_service = null,
		?AIPS_Config $config = null,
		?AIPS_Logger_Interface $logger = null
	) {
		$container = AIPS_Container::get_instance();
		$this->embeddings_repo    = $embeddings_repo ?: ($container->has(AIPS_Embeddings_Repository::class) ? $container->make(AIPS_Embeddings_Repository::class) : new AIPS_Embeddings_Repository());
		$this->relationships_repo = $relationships_repo ?: ($container->has(AIPS_Relationships_Repository::class) ? $container->make(AIPS_Relationships_Repository::class) : new AIPS_Relationships_Repository());
		$this->embeddings_service = $embeddings_service ?: ($container->has(AIPS_Embeddings_Service::class) ? $container->make(AIPS_Embeddings_Service::class) : new AIPS_Embeddings_Service());
		$this->ai_service         = $ai_service ?: ($container->has(AIPS_AI_Service_Interface::class) ? $container->make(AIPS_AI_Service_Interface::class) : new AIPS_AI_Service());
		$this->config             = $config ?: AIPS_Config::get_instance();
		$this->logger             = $logger ?: ($container->has(AIPS_Logger_Interface::class) ? $container->make(AIPS_Logger_Interface::class) : new AIPS_Logger());
	}

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
		$candidates = $this->embeddings_repo->get_all_for_similarity('post', $post_types, 'publish');

		if (empty($candidates)) {
			return array();
		}

		// Decode post vectors
		$posts = array();
		foreach ($candidates as $row) {
			$vec = $this->embeddings_repo->decode_embedding($row->embedding);
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
					$sim = $this->embeddings_service->calculate_similarity($vec_a, $vec_b);
					if (!is_wp_error($sim) && (float) $sim >= $threshold) {
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
			$pillar_id = 0;
			if (isset($saved_clusters[$cluster_key]['pillar_id']) && in_array((int) $saved_clusters[$cluster_key]['pillar_id'], $comp_ids, true)) {
				$pillar_id = (int) $saved_clusters[$cluster_key]['pillar_id'];
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
				);
			}

			$clusters[$cluster_key] = array(
				'id'           => $cluster_key,
				'name'         => $name,
				'pillar_id'    => $pillar_id,
				'pillar_title' => $posts[$pillar_id]['title'],
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
		$candidates = $this->embeddings_repo->get_all_for_similarity('post', $post_types, 'publish');

		if (empty($candidates)) {
			return array();
		}

		$posts = array();
		foreach ($candidates as $row) {
			$vec = $this->embeddings_repo->decode_embedding($row->embedding);
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
					$sim = $this->embeddings_service->calculate_similarity($vec_a, $vec_b);
					if (!is_wp_error($sim) && (float) $sim >= $threshold) {
						$posts[$id_a]['neighbors']++;
						$posts[$id_b]['neighbors']++;
					}
				}
			}
		}

		$orphans = array();
		foreach ($posts as $pid => $pdata) {
			$is_island = ($pdata['neighbors'] <= 1);
			$incoming_links = $this->count_incoming_internal_links($pid);
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
		$clusters[$cluster_id]['pillar_id']    = $post_id;
		$clusters[$cluster_id]['pillar_title'] = get_the_title($post_id);

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
			$this->relationships_repo->sync_for_source('post', $post_id, $targets, 'pillar_spoke');
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

		$response = $this->ai_service->generate_content($prompt, array(
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

		$this->relationships_repo->sync_for_source('post', $pillar_id, $targets, 'pillar_spoke');
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

		return $this->relationships_repo->count_incoming_internal_links($post_id, $search_term);
	}
}
