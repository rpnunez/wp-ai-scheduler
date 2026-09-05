#!/usr/bin/env php
<?php
/**
 * Seed Link Graph Posts Generator (Multi-Run Dynamic Crawler)
 *
 * Populates WordPress with real English articles dynamically discovered from
 * the English Wikipedia REST API, constructs multi-tier bidirectional link
 * graphs across repeated runs, and triggers semantic vector indexing.
 *
 * Options:
 *   --count=N       Number of new posts to generate (default: 10)
 *   --topic="Topic" Seed topic keyword to crawl (default: rotates through tech pool)
 *   --orphans=N     Number of newly created posts to leave as orphans (default: ~20%)
 *
 * @package AI_Post_Scheduler
 * @since 3.7.0
 */

if (php_sapi_name() !== 'cli') {
	die("This script must be run from the command line.\n");
}

// Bootstrap WordPress
$wp_load_candidates = array(
	'/var/www/html/wp-load.php',
	dirname(__DIR__, 4) . '/wp-load.php',
	dirname(__DIR__, 3) . '/wp-load.php',
	dirname(__DIR__, 2) . '/wp-load.php',
);

$wp_loaded = false;
foreach ($wp_load_candidates as $candidate) {
	if (file_exists($candidate)) {
		require_once $candidate;
		$wp_loaded = true;
		break;
	}
}

if (!$wp_loaded) {
	die("Error: Could not locate wp-load.php to boot WordPress.\n");
}

// Ensure database tables exist
require_once ABSPATH . 'wp-admin/includes/upgrade.php';
$install_res = AIPS_DB_Manager::install_tables();
if (is_wp_error($install_res)) {
	echo "DB Init Error: " . $install_res->get_error_message() . "\n";
} else {
	echo "DB Tables verified.\n";
}

// Ensure .htaccess rewrite rules are active for clean /wp-json/ endpoints
$htaccess_path = ABSPATH . '.htaccess';
if (!file_exists($htaccess_path)) {
	$rules = "# BEGIN WordPress\n<IfModule mod_rewrite.c>\nRewriteEngine On\nRewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]\nRewriteBase /\nRewriteRule ^index\.php$ - [L]\nRewriteCond %{REQUEST_FILENAME} !-f\nRewriteCond %{REQUEST_FILENAME} !-d\nRewriteRule . /index.php [L]\n</IfModule>\n# END WordPress\n";
	@file_put_contents($htaccess_path, $rules);
	flush_rewrite_rules(false);
}

// Parse CLI options
$options = getopt('', array('count::', 'topic::', 'orphans::'));
$count   = isset($options['count']) ? max(1, min(50, (int) $options['count'])) : 10;
$orphans = isset($options['orphans']) ? max(0, min($count, (int) $options['orphans'])) : (int) ceil($count * 0.20);

$topic_pool = array(
	'Artificial intelligence',
	'Machine learning',
	'Graph theory',
	'Vector database',
	'Information retrieval',
	'Computer network',
	'Data structure',
	'Algorithm',
	'Natural language processing',
	'Search engine optimization',
	'World Wide Web',
	'Knowledge graph',
	'Software architecture',
	'Database index',
	'Semantic Web',
);

// If topic not provided, choose a random seed or rotating topic
$seed_topic = !empty($options['topic']) ? trim($options['topic']) : $topic_pool[array_rand($topic_pool)];

echo "\n=======================================================\n";
echo "  AIPS Dynamic SEO Link Graph & Multi-Run Content Seeder\n";
echo "=======================================================\n";
echo "Target Batch Size : {$count} posts\n";
echo "Seed Topic Domain : '{$seed_topic}'\n";
echo "Allocated Orphans : {$orphans} posts\n\n";

// Function to perform HTTP GET to Wikipedia API
function wiki_http_get($url) {
	$ctx = stream_context_create(array(
		'http' => array(
			'timeout' => 6,
			'header'  => "User-Agent: AIPS-LinkGraphSeeder/2.0 (WordPress Dev Test; contact@example.com)\r\n"
		)
	));
	$res = @file_get_contents($url, false, $ctx);
	if (!$res) {
		return null;
	}
	return json_decode($res, true);
}

// Fetch candidate articles from Wikipedia
echo "Step 1: Crawling Wikipedia for real articles...\n";

// 1. Search Wikipedia for articles in this topic
$search_url = 'https://en.wikipedia.org/w/api.php?action=query&list=search&srsearch=' . urlencode($seed_topic) . '&format=json&srlimit=' . ($count * 3);
$search_res = wiki_http_get($search_url);

$titles = array();
if (!empty($search_res['query']['search'])) {
	foreach ($search_res['query']['search'] as $item) {
		if (!empty($item['title']) && strpos($item['title'], ':') === false) {
			$titles[] = $item['title'];
		}
	}
}

// 2. Also fetch related pages from REST API for extra semantic depth
$related_url = 'https://en.wikipedia.org/api/rest_v1/page/related/' . urlencode(str_replace(' ', '_', $seed_topic));
$related_res = wiki_http_get($related_url);
if (!empty($related_res['pages'])) {
	foreach ($related_res['pages'] as $page) {
		if (!empty($page['title']) && strpos($page['title'], ':') === false) {
			$titles[] = str_replace('_', ' ', $page['title']);
		}
	}
}

$titles = array_values(array_unique($titles));

// Query already existing post titles in WordPress to avoid duplicates
global $wpdb;
$existing_titles_raw = $wpdb->get_col("SELECT post_title FROM {$wpdb->posts} WHERE post_status = 'publish' AND post_type = 'post'");
$existing_titles     = array_map('strtolower', $existing_titles_raw ?: array());

$articles_to_create = array();

foreach ($titles as $t) {
	if (count($articles_to_create) >= $count) {
		break;
	}
	if (in_array(strtolower($t), $existing_titles, true)) {
		continue;
	}

	// Fetch full summary extract
	$sum_url = 'https://en.wikipedia.org/api/rest_v1/page/summary/' . urlencode(str_replace(' ', '_', $t));
	$summary = wiki_http_get($sum_url);

	if (!empty($summary['extract']) && strlen($summary['extract']) > 120) {
		$articles_to_create[] = array(
			'title'       => $summary['title'] ?? $t,
			'description' => $summary['description'] ?? '',
			'content'     => '<p>' . esc_html($summary['extract']) . '</p>',
			'wiki_slug'   => $summary['titles']['canonical'] ?? str_replace(' ', '_', $t),
		);
		echo "  ✓ Found: {$t}\n";
	}
}

// Fallback pool in case Wikipedia network is blocked/offline in container
if (count($articles_to_create) < $count) {
	echo "  (Using offline fallback topics to satisfy target count)\n";
	$offline_library = array(
		array('title' => 'Algorithmic Complexity and Big-O Traversal', 'content' => '<p>Computational complexity theory focuses on classifying computational problems according to their resource usage, and relating these classes to each other. A computational problem is a task solved by a computer.</p><p>Big-O notation characterizes functions according to their growth rates: different functions with the same growth rate may be represented using the same O notation.</p>'),
		array('title' => 'Hierarchical Clustering and Dendrogram Graphs', 'content' => '<p>Hierarchical clustering is a method of cluster analysis which seeks to build a hierarchy of clusters. Strategies for hierarchical clustering generally fall into two categories: agglomerative and divisive.</p><p>In data mining, dendrograms visualize topological distances between nested information nodes.</p>'),
		array('title' => 'Decentralized Consensus and Distributed Hash Tables', 'content' => '<p>A distributed hash table (DHT) is a distributed system that provides a lookup service similar to a hash table: key-value pairs are stored in a DHT, and any participating node can efficiently retrieve the value associated with a given key.</p><p>DHTs form the backbone of modern peer-to-peer data distribution and decentralized content addressing.</p>'),
		array('title' => 'Vector Quantization and Product Compression', 'content' => '<p>Vector quantization (VQ) is a classical quantization technique from signal processing that allows the modeling of probability density functions by the distribution of prototype vectors.</p><p>High-dimensional embedding databases utilize product quantization to compress floating point representations while retaining cosine similarity fidelity.</p>'),
		array('title' => 'Semantic Web Ontologies and Knowledge Representation', 'content' => '<p>The Semantic Web is an extension of the World Wide Web through standards set by the World Wide Web Consortium (W3C). The standards promote common data formats and exchange protocols on the Web.</p><p>Ontologies provide formal descriptions of concepts within a domain and the relationships between them.</p>'),
		array('title' => 'Heuristic Search and A-Star Pathfinding Algorithms', 'content' => '<p>A-star is a graph traversal and path search algorithm, which is used in many fields of computer science due to its completeness, optimality, and optimal efficiency.</p><p>It uses a distance-plus-cost heuristic function to determine the order in which the search visits nodes in the tree.</p>'),
	);

	foreach ($offline_library as $off) {
		if (count($articles_to_create) >= $count) {
			break;
		}
		if (!in_array(strtolower($off['title']), $existing_titles, true)) {
			$articles_to_create[] = array(
				'title'       => $off['title'],
				'description' => 'Technical computing reference',
				'content'     => $off['content'],
				'wiki_slug'   => sanitize_title($off['title']),
			);
			echo "  ✓ Added: {$off['title']}\n";
		}
	}
}

echo "\nStep 2: Inserting New Posts into WordPress...\n";
$newly_created_ids = array();

foreach ($articles_to_create as $item) {
	$post_id = wp_insert_post(array(
		'post_title'   => $item['title'],
		'post_content' => $item['content'],
		'post_status'  => 'publish',
		'post_type'    => 'post',
		'post_author'  => 1,
	));

	if ($post_id && !is_wp_error($post_id)) {
		$newly_created_ids[] = (int) $post_id;
		echo "  - Created Post #{$post_id}: {$item['title']}\n";
	}
}

// Retrieve previously existing published posts in the DB (excluding current batch)
$all_existing_ids = $wpdb->get_col("SELECT ID FROM {$wpdb->posts} WHERE post_status = 'publish' AND post_type = 'post' ORDER BY ID ASC");
$all_existing_ids = array_map('intval', $all_existing_ids ?: array());
$prior_post_ids   = array_values(array_diff($all_existing_ids, $newly_created_ids));

echo "\nStep 3: Weaving Bidirectional Link Topology...\n";

// Designate orphans from the new batch
$orphan_ids    = array_slice($newly_created_ids, 0, $orphans);
$connected_ids = array_slice($newly_created_ids, $orphans);

// 3A: Inbound links TO connected new posts (from prior posts or from each other)
$source_pool = !empty($prior_post_ids) ? $prior_post_ids : $connected_ids;

foreach ($connected_ids as $target_id) {
	// Pick 1 or 2 source posts from the pool
	$sources = (array) array_rand(array_flip($source_pool), min(count($source_pool), rand(1, 2)));

	foreach ($sources as $source_id) {
		if ($source_id === $target_id) {
			continue;
		}

		$src_post     = get_post($source_id);
		$target_url   = get_permalink($target_id);
		$target_title = get_the_title($target_id);

		if ($src_post && strpos($src_post->post_content, $target_url) === false) {
			$link_html = "\n\n<p>Contextual Exploration: <a href=\"{$target_url}\">{$target_title}</a></p>";
			wp_update_post(array(
				'ID'           => $source_id,
				'post_content' => $src_post->post_content . $link_html,
			));
		}
	}
}

// 3B: Outbound links FROM all new posts (even orphans can have outbound links)
foreach ($newly_created_ids as $new_id) {
	$candidates_for_outbound = array_diff($all_existing_ids, array($new_id));
	if (empty($candidates_for_outbound)) {
		continue;
	}

	$target_ids_picked = (array) array_rand(array_flip($candidates_for_outbound), min(count($candidates_for_outbound), rand(1, 2)));
	$new_post          = get_post($new_id);
	if (!$new_post) {
		// Skip if the freshly created post is not retrievable (object-cache miss,
		// filter, or transient failure). Missing a single outbound weave is
		// preferable to aborting the seeder mid-run.
		continue;
	}
	$outbound_html     = "\n\n<h3>Related Topological Readings</h3>\n<ul>\n";

	foreach ($target_ids_picked as $t_picked_id) {
		$t_url   = get_permalink($t_picked_id);
		$t_title = get_the_title($t_picked_id);
		$outbound_html .= "<li>Explore: <a href=\"{$t_url}\">{$t_title}</a></li>\n";
	}
	$outbound_html .= "</ul>\n";

	wp_update_post(array(
		'ID'           => $new_id,
		'post_content' => $new_post->post_content . $outbound_html,
	));
}

echo "  ✓ Wove cross-links: " . count($connected_ids) . " connected, " . count($orphan_ids) . " designated orphans.\n";

echo "\nStep 4: Synchronizing Graph Index & Vector Embeddings...\n";
$container     = AIPS_Container::get_instance();
$graph_service = $container->has(AIPS_Link_Graph_Service::class) ? $container->make(AIPS_Link_Graph_Service::class) : new AIPS_Link_Graph_Service();
$indexer       = $container->has(AIPS_Content_Indexer_Service::class) ? $container->make(AIPS_Content_Indexer_Service::class) : null;

// Re-index all posts touched in this run
$touched_ids = array_unique(array_merge($newly_created_ids, $source_pool));
foreach ($touched_ids as $pid) {
	$graph_service->index_post_links($pid);
	if ($indexer) {
		$post = get_post($pid);
		if ($post) {
			$indexer->on_post_save($pid, $post);
		}
	}
}
echo "  ✓ Directed link edges and vector embeddings successfully synced.\n";

echo "\nStep 5: Current Batch Verification Summary:\n";
echo str_repeat('-', 95) . "\n";
printf("%-6s | %-42s | %-8s | %-9s | %-7s | %-12s\n", "ID", "Post Title", "Inbound", "Outbound", "Depth", "Status");
echo str_repeat('-', 95) . "\n";

foreach ($newly_created_ids as $pid) {
	$metrics   = $graph_service->calculate_post_seo_metrics($pid);
	$depth_str = ($metrics['depth_level'] === 99) ? '∞' : ('L' . $metrics['depth_level']);
	$status    = $metrics['is_orphan'] ? '⚠️ ORPHAN' : 'Connected';

	printf(
		"%-6d | %-42s | %-8d | %-9d | %-7s | %-12s\n",
		$pid,
		substr(html_entity_decode(get_the_title($pid), ENT_QUOTES, 'UTF-8'), 0, 42),
		$metrics['inbound_count'],
		$metrics['outbound_count'],
		$depth_str,
		$status
	);
}
echo str_repeat('-', 95) . "\n";

$total_posts_in_network = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_status = 'publish' AND post_type = 'post'");
$total_edges_in_graph   = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}aips_content_links");

echo "\n✓ Seeder execution complete!\n";
echo "Total Published Posts in Network : {$total_posts_in_network}\n";
echo "Total Directed Edges in Graph    : {$total_edges_in_graph}\n";
echo "Test in Gutenberg at             : http://localhost:8080/wp-admin/\n\n";
