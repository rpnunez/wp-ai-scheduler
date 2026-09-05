<?php
/**
 * Link Graph Service
 *
 * Provides graph analysis, link parsing, multi-hop depth calculation,
 * and SEO metric evaluations across the internal link network.
 *
 * @package AI_Post_Scheduler
 * @since 3.7.0
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Link_Graph_Service
 */
class AIPS_Link_Graph_Service {

	/**
	 * Content Links Repository.
	 *
	 * @var AIPS_Content_Links_Repository
	 */
	protected $links_repo;

	/**
	 * Editor Registry.
	 *
	 * @var AIPS_Editor_Registry
	 */
	protected $editor_registry;

	/**
	 * Memoized adjacency graph for request cache.
	 *
	 * @var array|null
	 */
	protected $graph_cache = null;

	/**
	 * Memoized reverse adjacency graph for request cache.
	 *
	 * @var array|null
	 */
	protected $reverse_graph_cache = null;

	/**
	 * Initialize the service.
	 *
	 * @param AIPS_Content_Links_Repository|null $links_repo Optional repository.
	 * @param AIPS_Editor_Registry|null          $editor_registry Optional editor registry.
	 */
	public function __construct($links_repo = null, $editor_registry = null) {
		$container             = AIPS_Container::get_instance();
		$this->links_repo      = $links_repo ?: ($container->has(AIPS_Content_Links_Repository::class) ? $container->make(AIPS_Content_Links_Repository::class) : new AIPS_Content_Links_Repository());
		$this->editor_registry = $editor_registry ?: ($container->has(AIPS_Editor_Registry::class) ? $container->make(AIPS_Editor_Registry::class) : new AIPS_Editor_Registry());
	}

	/**
	 * Parse arbitrary HTML content to detect internal links and map them to post IDs.
	 *
	 * @param string $html      HTML content to parse.
	 * @param int    $source_id Source post ID (to prevent self-links).
	 * @return array List of detected link items.
	 */
	public function parse_content_for_internal_links($html, $source_id = 0) {
		if (empty($html) || !is_string($html)) {
			return array();
		}

		$detected_links = array();
		$site_url       = get_site_url();
		$home_url       = get_home_url();
		$site_host      = strtolower((string) wp_parse_url($site_url, PHP_URL_HOST));
		$home_host      = strtolower((string) wp_parse_url($home_url, PHP_URL_HOST));
		$allowed_hosts  = array_unique(array_filter(array(
			$site_host,
			$home_host,
			preg_replace('/^www\./', '', $site_host),
			preg_replace('/^www\./', '', $home_host),
		)));

		// Match <a> tags
		if (!preg_match_all('/<a\s+[^>]*href=[\'"]([^\'"]+)[\'"][^>]*>(.*?)<\/a>/is', $html, $matches, PREG_SET_ORDER)) {
			return array();
		}

		$seen_targets    = array();
		// First pass: resolve target IDs and record the metadata we'll need in the
		// second pass so a single _prime_post_caches() covers every get_post() and
		// get_permalink() lookup, avoiding an N+1 for link-heavy documents.
		$resolved = array();

		foreach ($matches as $match) {
			$raw_url     = trim($match[1]);
			$anchor_text = trim(wp_strip_all_tags($match[2]));

			// Skip anchor hashes, mailto, tel, javascript
			if (empty($raw_url) || $raw_url[0] === '#' || preg_match('/^(mailto:|tel:|javascript:)/i', $raw_url)) {
				continue;
			}

			// Check if internal
			$url_host    = strtolower((string) wp_parse_url($raw_url, PHP_URL_HOST));
			$clean_uhost = preg_replace('/^www\./', '', $url_host);
			$is_internal = false;

			if (empty($url_host) && $raw_url[0] === '/') {
				$is_internal = true;
				$full_url    = home_url($raw_url);
			} elseif (!empty($url_host) && (in_array($url_host, $allowed_hosts, true) || in_array($clean_uhost, $allowed_hosts, true))) {
				$is_internal = true;
				$full_url    = $raw_url;
			}

			if (!$is_internal) {
				continue;
			}

			// Resolve internal post ID
			$target_id = url_to_postid($full_url);

			// Fallback: check if homepage or page_on_front
			if ($target_id <= 0) {
				$clean_path = trim((string) wp_parse_url($full_url, PHP_URL_PATH), '/');
				if (empty($clean_path)) {
					$target_id = (int) get_option('page_on_front');
				} else {
					// Check by slug
					$slug = basename($clean_path);
					$post_by_slug = get_page_by_path($slug, OBJECT, array('post', 'page'));
					if ($post_by_slug) {
						$target_id = (int) $post_by_slug->ID;
					} else {
						// Additional fallback by post_name query
						$matching = get_posts(array(
							'name'           => $slug,
							'post_type'      => array('post', 'page'),
							'post_status'    => 'publish',
							'posts_per_page' => 1,
							'fields'         => 'ids',
						));
						if (!empty($matching)) {
							$target_id = (int) $matching[0];
						}
					}
				}
			}

			if ($target_id <= 0 || $target_id === $source_id) {
				continue;
			}

			// Avoid duplicate edges from the same source to the same target in one document
			if (isset($seen_targets[$target_id])) {
				continue;
			}
			$seen_targets[$target_id] = true;

			$resolved[] = array(
				'target_id'   => $target_id,
				'anchor_text' => $anchor_text,
				'full_url'    => $full_url,
			);
		}

		if (empty($resolved)) {
			return array();
		}

		if (function_exists('_prime_post_caches')) {
			_prime_post_caches(array_column($resolved, 'target_id'), false, false);
		}

		foreach ($resolved as $entry) {
			$target_id   = $entry['target_id'];
			$target_post = get_post($target_id);
			$post_type   = $target_post ? $target_post->post_type : 'post';

			$detected_links[] = array(
				'target_id'   => $target_id,
				'anchor_text' => $entry['anchor_text'],
				'link_url'    => get_permalink($target_id) ?: $entry['full_url'],
				'post_type'   => $post_type,
			);
		}

		return $detected_links;
	}

	/**
	 * Index all internal links in a post and synchronize to database.
	 *
	 * Utilizes active editor adapter when content is not explicitly provided.
	 *
	 * @param int         $post_id Post ID.
	 * @param string|null $content Optional content override.
	 * @return array Detected link items.
	 */
	public function index_post_links($post_id, $content = null) {
		$post_id = absint($post_id);
		if ($post_id <= 0) {
			return array();
		}

		if ($content === null) {
			$adapter = $this->editor_registry ? $this->editor_registry->get_active_adapter_for_post($post_id) : null;
			if ($adapter) {
				$links = $adapter->extract_links($post_id);
			} else {
				$post = get_post($post_id);
				$links = $post ? $this->parse_content_for_internal_links($post->post_content, $post_id) : array();
			}
		} else {
			$links = $this->parse_content_for_internal_links($content, $post_id);
		}

		$this->links_repo->sync_post_links($post_id, $links);

		// Invalidate graph caches
		$this->graph_cache         = null;
		$this->reverse_graph_cache = null;

		return $links;
	}

	/**
	 * Calculate SEO Link Metrics for a specific post.
	 *
	 * @param int $post_id Post ID.
	 * @return array Metrics dictionary.
	 */
	public function calculate_post_seo_metrics($post_id) {
		$post_id = absint($post_id);
		if ($post_id <= 0) {
			return array(
				'inbound_count'  => 0,
				'outbound_count' => 0,
				'depth_level'    => 0,
				'is_orphan'      => true,
				'equity_tier'    => 'orphan',
			);
		}

		$inbound_count  = $this->links_repo->get_inbound_count($post_id);
		$outbound_count = $this->links_repo->get_outbound_count($post_id);
		$depth_level    = $this->calculate_graph_depth($post_id);
		$is_orphan      = ($inbound_count === 0);

		if ($is_orphan) {
			$equity_tier = 'orphan';
		} elseif ($inbound_count <= 2) {
			$equity_tier = 'low';
		} elseif ($inbound_count <= 5) {
			$equity_tier = 'moderate';
		} else {
			$equity_tier = 'high_hub';
		}

		return array(
			'post_id'        => $post_id,
			'inbound_count'  => $inbound_count,
			'outbound_count' => $outbound_count,
			'depth_level'    => $depth_level,
			'is_orphan'      => $is_orphan,
			'equity_tier'    => $equity_tier,
		);
	}

	/**
	 * Compute crawl depth from site root for all reachable posts in a single BFS pass.
	 *
	 * @param int|null $root_id Optional root post ID.
	 * @return array<int, int> Map of post_id => depth.
	 */
	public function get_all_graph_depths($root_id = null) {
		if ($root_id === null) {
			$root_id = (int) get_option('page_on_front');
			if ($root_id <= 0) {
				// Fallback: earliest published post as de facto pillar
				$root_posts = get_posts(array(
					'post_type'        => array('post', 'page'),
					'post_status'      => 'publish',
					'posts_per_page'   => 1,
					'orderby'          => 'ID',
					'order'            => 'ASC',
					'fields'           => 'ids',
					'no_found_rows'    => true,
					'suppress_filters' => true,
				));
				$root_id = !empty($root_posts) ? (int) $root_posts[0] : 0;
			}
		}

		if ($root_id <= 0) {
			return array();
		}

		$adj    = $this->get_adjacency_list();
		$depths = array($root_id => 0);
		$queue  = new SplQueue();
		$queue->enqueue($root_id);

		while (!$queue->isEmpty()) {
			$curr_id = $queue->dequeue();
			$curr_d  = $depths[$curr_id];

			if (isset($adj[$curr_id])) {
				foreach ($adj[$curr_id] as $neighbor) {
					if (!isset($depths[$neighbor])) {
						$depths[$neighbor] = $curr_d + 1;
						$queue->enqueue($neighbor);
					}
				}
			}
		}

		return $depths;
	}

	/**
	 * Calculate graph crawl depth using Breadth-First Search (BFS) from site root.
	 *
	 * Level 1: Directly linked from Root/Homepage or designated pillar post.
	 * Level 2: 2 hops away from Root.
	 * Level 3+: 3 or more hops away.
	 * 0: Post is the root itself.
	 * 99: Disconnected / unreachable from Root (orphan cluster).
	 *
	 * @param int      $post_id Post ID.
	 * @param int|null $root_id Optional root ID.
	 * @return int Depth level.
	 */
	public function calculate_graph_depth($post_id, $root_id = null) {
		$post_id = absint($post_id);
		if ($post_id <= 0) {
			return 0;
		}

		$depths = $this->get_all_graph_depths($root_id);
		if (isset($depths[$post_id])) {
			return $depths[$post_id];
		}

		return 99; // Unreachable from root
	}

	/**
	 * Detect cross-link relationship between two posts.
	 *
	 * Identifies:
	 * - Direct link (source -> target)
	 * - Reciprocal link (target -> source)
	 * - 2-hop link (source -> intermediate -> target)
	 * - Shared co-citation (some third article X links to both source and target)
	 *
	 * @param int $source_id Source post ID.
	 * @param int $target_id Target post ID.
	 * @return array Cross-link descriptor.
	 */
	public function get_cross_link_relationship($source_id, $target_id) {
		$source_id = absint($source_id);
		$target_id = absint($target_id);

		if ($source_id <= 0 || $target_id <= 0 || $source_id === $target_id) {
			return array(
				'is_direct'      => false,
				'is_reciprocal'  => false,
				'is_two_hop'     => false,
				'is_co_cited'    => false,
				'hop_distance'   => 0,
				'co_cited_by'    => array(),
			);
		}

		$adj = $this->get_adjacency_list();

		$is_direct     = isset($adj[$source_id]) && in_array($target_id, $adj[$source_id], true);
		$is_reciprocal = isset($adj[$target_id]) && in_array($source_id, $adj[$target_id], true);

		// Check 2-hop
		$is_two_hop = false;
		if (isset($adj[$source_id])) {
			foreach ($adj[$source_id] as $mid) {
				if (isset($adj[$mid]) && in_array($target_id, $adj[$mid], true)) {
					$is_two_hop = true;
					break;
				}
			}
		}

		// Check co-citation (parents linking to both)
		$rev_adj = $this->get_reverse_adjacency_list();
		$source_parents = $rev_adj[$source_id] ?? array();
		$target_parents = $rev_adj[$target_id] ?? array();
		$common_parents = array_values(array_intersect($source_parents, $target_parents));

		$hop_distance = $is_direct ? 1 : ($is_two_hop ? 2 : 0);

		return array(
			'is_direct'     => $is_direct,
			'is_reciprocal' => $is_reciprocal,
			'is_two_hop'    => $is_two_hop,
			'is_co_cited'   => !empty($common_parents),
			'hop_distance'  => $hop_distance,
			'co_cited_by'   => $common_parents,
		);
	}

	/**
	 * Retrieve adjacency list of directed edges.
	 *
	 * @return array Map of source_id => array of target_ids.
	 */
	protected function get_adjacency_list() {
		if ($this->graph_cache !== null) {
			return $this->graph_cache;
		}

		$edges = $this->links_repo->get_all_directed_edges();
		$adj   = array();

		foreach ($edges as $edge) {
			$s = (int) $edge['source_id'];
			$t = (int) $edge['target_id'];
			if (!isset($adj[$s])) {
				$adj[$s] = array();
			}
			$adj[$s][] = $t;
		}

		$this->graph_cache = $adj;
		return $this->graph_cache;
	}

	/**
	 * Retrieve reverse adjacency list (target_id => array of source_ids).
	 *
	 * @return array Map of target_id => array of source_ids.
	 */
	protected function get_reverse_adjacency_list() {
		if ($this->reverse_graph_cache !== null) {
			return $this->reverse_graph_cache;
		}

		// Derive from the forward adjacency list rather than re-scanning the edges
		// table. get_adjacency_list() memoizes its result, so on a request that has
		// already asked for the forward map this avoids a second full-table SELECT.
		$adj     = $this->get_adjacency_list();
		$rev_adj = array();

		foreach ($adj as $source_id => $targets) {
			$s = (int) $source_id;
			foreach ($targets as $target_id) {
				$t = (int) $target_id;
				if (!isset($rev_adj[$t])) {
					$rev_adj[$t] = array();
				}
				$rev_adj[$t][] = $s;
			}
		}

		$this->reverse_graph_cache = $rev_adj;
		return $this->reverse_graph_cache;
	}
}
