<?php
/**
 * Content Auditor Scanner
 *
 * Scans the WordPress content library to extract structured post fingerprints,
 * internal link graphs, entity clusters, content decay signals, and cannibalization candidates.
 *
 * @package AI_Post_Scheduler
 * @since 3.4.2
 */

if (!defined('ABSPATH')) {
	exit;
}

class AIPS_Content_Auditor_Scanner {

	/**
	 * Default minimum word count below which content is flagged as thin.
	 *
	 * @var int
	 */
	const THIN_CONTENT_THRESHOLD = 600;

	/**
	 * Default age in days beyond which content is flagged as potentially decaying/stale.
	 *
	 * @var int
	 */
	const CONTENT_DECAY_DAYS = 180;

	/**
	 * Standard English stop words for keyphrase extraction.
	 *
	 * @var string[]
	 */
	private static $stop_words = array(
		'a', 'about', 'above', 'after', 'again', 'against', 'all', 'am', 'an', 'and',
		'any', 'are', 'as', 'at', 'be', 'because', 'been', 'before', 'being', 'below',
		'between', 'both', 'but', 'by', 'can', 'could', 'did', 'do', 'does', 'doing',
		'down', 'during', 'each', 'few', 'for', 'from', 'further', 'had', 'has', 'have',
		'having', 'he', 'her', 'here', 'hers', 'herself', 'him', 'himself', 'his', 'how',
		'i', 'if', 'in', 'into', 'is', 'it', 'its', 'itself', 'just', 'me', 'more',
		'most', 'my', 'myself', 'no', 'nor', 'not', 'now', 'of', 'off', 'on', 'once',
		'only', 'or', 'other', 'our', 'ours', 'ourselves', 'out', 'over', 'own', 'same',
		'she', 'should', 'so', 'some', 'such', 'than', 'that', 'the', 'their', 'theirs',
		'them', 'themselves', 'then', 'there', 'these', 'they', 'this', 'those', 'through',
		'to', 'too', 'under', 'until', 'up', 'very', 'was', 'we', 'were', 'what', 'when',
		'where', 'which', 'while', 'who', 'whom', 'why', 'with', 'would', 'you', 'your',
		'yours', 'yourself', 'yourselves', 'guide', 'ultimate', 'best', 'top', 'how',
	);

	/**
	 * Scan the WordPress post library and extract comprehensive fingerprints.
	 *
	 * @param int   $limit Maximum posts to scan (default 200).
	 * @param int   $offset Offset for batching (default 0).
	 * @param array $args Optional query override parameters.
	 * @return array Array of structured post fingerprints.
	 */
	public function scan_library($limit = 200, $offset = 0, array $args = array()) {
		$default_args = array(
			'post_type'      => 'post',
			'post_status'    => 'publish',
			'posts_per_page' => max(1, (int) $limit),
			'offset'         => max(0, (int) $offset),
			'orderby'        => 'date',
			'order'          => 'DESC',
		);

		$query_args = wp_parse_args($args, $default_args);
		$query      = new WP_Query($query_args);
		$results    = array();

		if ($query->have_posts()) {
			if (!empty($query->posts) && function_exists('_prime_post_caches')) {
				$post_ids = array_unique(array_filter(array_map('intval', wp_list_pluck($query->posts, 'ID'))));
				if (!empty($post_ids)) {
					_prime_post_caches($post_ids, true, true);
				}
			}

			foreach ($query->posts as $post) {
				$fingerprint = $this->extract_post_fingerprint($post);
				if (!empty($fingerprint)) {
					$results[] = $fingerprint;
				}
			}
		}

		return $results;
	}

	/**
	 * Extract a structured content fingerprint for a single post.
	 *
	 * @param WP_Post|int|object $post Post object or post ID.
	 * @return array Structured post fingerprint array.
	 */
	public function extract_post_fingerprint($post) {
		if (is_numeric($post)) {
			$post = get_post($post);
		}

		if (!$post || !isset($post->ID)) {
			return array();
		}

		$post_id   = (int) $post->ID;
		$title     = get_the_title($post_id);
		$slug      = isset($post->post_name) ? (string) $post->post_name : '';
		$permalink = function_exists('get_permalink') ? (string) get_permalink($post_id) : '/' . $slug . '/';
		$raw_body  = isset($post->post_content) ? (string) $post->post_content : '';
		$clean_body = trim(preg_replace('/\s+/u', ' ', wp_strip_all_tags($raw_body)));

		$word_count = str_word_count($clean_body);
		$char_count = mb_strlen($clean_body);

		// Taxonomies
		$categories = array();
		$category_ids = array();
		$terms = get_the_category($post_id);
		if (!empty($terms) && is_array($terms)) {
			foreach ($terms as $term) {
				if (isset($term->name)) {
					$categories[] = $term->name;
					$category_ids[] = (int) $term->term_id;
				}
			}
		}

		$tags = array();
		$tag_ids = array();
		$post_tags = get_the_tags($post_id);
		if (!empty($post_tags) && is_array($post_tags)) {
			foreach ($post_tags as $tag) {
				if (isset($tag->name)) {
					$tags[] = $tag->name;
					$tag_ids[] = (int) $tag->term_id;
				}
			}
		}

		// Dates & Freshness
		$published_date = isset($post->post_date) ? (string) $post->post_date : '';
		$modified_date  = isset($post->post_modified) ? (string) $post->post_modified : $published_date;
		$modified_ts    = strtotime($modified_date) ?: time();
		$age_days       = (int) max(0, floor((time() - $modified_ts) / DAY_IN_SECONDS));

		// Headings extraction
		$headings = $this->extract_headings($raw_body);

		// Outbound internal links
		$internal_links = $this->extract_internal_links($raw_body);

		// Keyphrase & Entity extraction
		$keyphrases = $this->extract_keyphrases($title, $headings);

		return array(
			'id'                      => $post_id,
			'title'                   => $title,
			'slug'                    => $slug,
			'url'                     => $permalink,
			'categories'              => $categories,
			'category_ids'            => $category_ids,
			'tags'                    => $tags,
			'tag_ids'                 => $tag_ids,
			'word_count'              => $word_count,
			'char_count'              => $char_count,
			'published_date'          => $published_date,
			'modified_date'           => $modified_date,
			'age_days'                => $age_days,
			'is_thin'                 => $word_count < self::THIN_CONTENT_THRESHOLD,
			'is_decayed'              => $age_days >= self::CONTENT_DECAY_DAYS,
			'headings'                => $headings,
			'outbound_internal_links' => $internal_links,
			'outbound_link_count'     => count($internal_links),
			'keyphrases'              => $keyphrases,
		);
	}

	/**
	 * Build a bidirectional link graph across all post fingerprints.
	 *
	 * Computes inbound internal links, identifies orphan content (0 inbound links),
	 * and evaluates silo link health.
	 *
	 * @param array $fingerprints Array of structured post fingerprints.
	 * @return array Enriched link graph data including orphan posts and silo metrics.
	 */
	public function build_link_graph(array $fingerprints) {
		$url_to_id = array();
		$slug_to_id = array();
		$inbound_map = array();

		// Index post identifiers
		foreach ($fingerprints as $fp) {
			$post_id = (int) $fp['id'];
			$inbound_map[$post_id] = array();

			if (!empty($fp['url'])) {
				$clean_url = $this->normalize_url_path($fp['url']);
				$url_to_id[$clean_url] = $post_id;
			}

			if (!empty($fp['slug'])) {
				$slug_to_id[$fp['slug']] = $post_id;
			}
		}

		// Map outbound links to target post IDs
		foreach ($fingerprints as $fp) {
			$source_id = (int) $fp['id'];
			$outbound = isset($fp['outbound_internal_links']) ? (array) $fp['outbound_internal_links'] : array();

			foreach ($outbound as $target_url) {
				$target_path = $this->normalize_url_path($target_url);
				$target_id   = null;

				if (isset($url_to_id[$target_path])) {
					$target_id = $url_to_id[$target_path];
				} else {
					// Check by slug path segment
					$path_trimmed = trim($target_path, '/');
					$segments     = explode('/', $path_trimmed);
					$last_seg     = end($segments);
					if (isset($slug_to_id[$last_seg])) {
						$target_id = $slug_to_id[$last_seg];
					}
				}

				if ($target_id && $target_id !== $source_id) {
					if (!in_array($source_id, $inbound_map[$target_id], true)) {
						$inbound_map[$target_id][] = $source_id;
					}
				}
			}
		}

		// Identify orphans & link stats
		$orphan_post_ids = array();
		$total_internal_connections = 0;
		$category_links = array();

		foreach ($fingerprints as &$fp) {
			$pid = (int) $fp['id'];
			$inbound_sources = isset($inbound_map[$pid]) ? $inbound_map[$pid] : array();
			$fp['inbound_internal_links'] = $inbound_sources;
			$fp['inbound_link_count']     = count($inbound_sources);
			$fp['is_orphan']              = count($inbound_sources) === 0;

			if ($fp['is_orphan']) {
				$orphan_post_ids[] = $pid;
			}

			$total_internal_connections += count($inbound_sources);

			// Aggregate category links
			$cats = !empty($fp['categories']) ? $fp['categories'] : array('Uncategorized');
			foreach ($cats as $cat) {
				if (!isset($category_links[$cat])) {
					$category_links[$cat] = array(
						'post_count' => 0,
						'inbound_links' => 0,
						'outbound_links' => 0,
						'orphan_count' => 0,
					);
				}
				$category_links[$cat]['post_count']++;
				$category_links[$cat]['inbound_links'] += count($inbound_sources);
				$category_links[$cat]['outbound_links'] += $fp['outbound_link_count'];
				if ($fp['is_orphan']) {
					$category_links[$cat]['orphan_count']++;
				}
			}
		}
		unset($fp);

		return array(
			'fingerprints'               => $fingerprints,
			'orphan_post_ids'            => $orphan_post_ids,
			'orphan_count'               => count($orphan_post_ids),
			'total_internal_connections' => $total_internal_connections,
			'category_silo_health'       => $category_links,
		);
	}

	/**
	 * Build entity clusters, decay inventories, and keyword cannibalization pairs.
	 *
	 * @param array $fingerprints Array of structured post fingerprints.
	 * @return array Consolidated entity graph and cluster diagnostics.
	 */
	public function build_entity_clusters(array $fingerprints) {
		$category_counts = array();
		$tag_counts      = array();
		$decayed_posts   = array();
		$thin_posts      = array();
		$all_keyphrases  = array();
		$total_words     = 0;

		$post_count = count($fingerprints);

		foreach ($fingerprints as $fp) {
			$total_words += (int) $fp['word_count'];

			// Categories
			$cats = !empty($fp['categories']) ? $fp['categories'] : array('Uncategorized');
			foreach ($cats as $cat) {
				$category_counts[$cat] = isset($category_counts[$cat]) ? $category_counts[$cat] + 1 : 1;
			}

			// Tags
			if (!empty($fp['tags'])) {
				foreach ($fp['tags'] as $tag) {
					$tag_counts[$tag] = isset($tag_counts[$tag]) ? $tag_counts[$tag] + 1 : 1;
				}
			}

			// Decay & Thin content
			if (!empty($fp['is_decayed'])) {
				$decayed_posts[] = array(
					'id'            => $fp['id'],
					'title'         => $fp['title'],
					'age_days'      => $fp['age_days'],
					'modified_date' => $fp['modified_date'],
					'word_count'    => $fp['word_count'],
				);
			}

			if (!empty($fp['is_thin'])) {
				$thin_posts[] = array(
					'id'         => $fp['id'],
					'title'      => $fp['title'],
					'word_count' => $fp['word_count'],
				);
			}

			// Collect keyphrases
			if (!empty($fp['keyphrases'])) {
				foreach ($fp['keyphrases'] as $kp) {
					$all_keyphrases[$kp] = isset($all_keyphrases[$kp]) ? $all_keyphrases[$kp] + 1 : 1;
				}
			}
		}

		arsort($category_counts);
		arsort($tag_counts);
		arsort($all_keyphrases);

		// Detect keyword cannibalization pairs
		$cannibalization_candidates = $this->detect_cannibalization_candidates($fingerprints);

		return array(
			'total_posts'                => $post_count,
			'total_words'                => $total_words,
			'avg_word_count'             => $post_count > 0 ? (int) round($total_words / $post_count) : 0,
			'category_distribution'      => $category_counts,
			'top_tags'                   => array_slice($tag_counts, 0, 30, true),
			'top_keyphrases'             => array_slice($all_keyphrases, 0, 40, true),
			'decayed_posts'              => $decayed_posts,
			'decay_count'                => count($decayed_posts),
			'thin_posts'                 => $thin_posts,
			'thin_count'                 => count($thin_posts),
			'cannibalization_candidates' => $cannibalization_candidates,
			'cannibalization_count'      => count($cannibalization_candidates),
		);
	}

	/**
	 * Detect pairs of posts that exhibit high topical or title overlap.
	 *
	 * @param array $fingerprints Post fingerprints.
	 * @return array List of candidate cannibalization pairs with similarity scores.
	 */
	public function detect_cannibalization_candidates(array $fingerprints) {
		$candidates = array();
		$count      = count($fingerprints);

		for ($i = 0; $i < $count; $i++) {
			$post_a = $fingerprints[$i];
			$title_a = strtolower(trim((string) $post_a['title']));
			$kp_a    = isset($post_a['keyphrases']) ? (array) $post_a['keyphrases'] : array();

			if (empty($title_a)) {
				continue;
			}

			for ($j = $i + 1; $j < $count; $j++) {
				$post_b = $fingerprints[$j];
				$title_b = strtolower(trim((string) $post_b['title']));
				$kp_b    = isset($post_b['keyphrases']) ? (array) $post_b['keyphrases'] : array();

				if (empty($title_b)) {
					continue;
				}

				// Compute title word similarity (Jaccard index)
				$words_a = array_diff(explode(' ', preg_replace('/[^\w\s]/', '', $title_a)), self::$stop_words);
				$words_b = array_diff(explode(' ', preg_replace('/[^\w\s]/', '', $title_b)), self::$stop_words);

				$intersection = count(array_intersect($words_a, $words_b));
				$union        = count(array_unique(array_merge($words_a, $words_b)));
				$similarity   = $union > 0 ? ($intersection / $union) : 0.0;

				// Keyphrase overlap
				$kp_intersection = count(array_intersect($kp_a, $kp_b));

				if ($similarity >= 0.5 || $kp_intersection >= 2) {
					$candidates[] = array(
						'post_a'             => array('id' => $post_a['id'], 'title' => $post_a['title'], 'url' => $post_a['url']),
						'post_b'             => array('id' => $post_b['id'], 'title' => $post_b['title'], 'url' => $post_b['url']),
						'similarity_score'   => round($similarity, 2),
						'shared_keyphrases'  => array_values(array_intersect($kp_a, $kp_b)),
						'recommended_action' => $similarity >= 0.7 ? 'Consolidate or 301 redirect' : 'Differentiate angle and target keyword',
					);
				}
			}
		}

		return $candidates;
	}

	/**
	 * Extract clean headings from HTML content.
	 *
	 * @param string $content HTML content.
	 * @return string[]
	 */
	private function extract_headings($content) {
		preg_match_all('/<h[2-4][^>]*>(.*?)<\/h[2-4]>/is', (string) $content, $matches);
		$headings = array();

		foreach (isset($matches[1]) ? $matches[1] : array() as $heading) {
			$heading = trim(preg_replace('/\s+/u', ' ', html_entity_decode(wp_strip_all_tags($heading), ENT_QUOTES, 'UTF-8')));
			if ($heading !== '' && !in_array($heading, $headings, true)) {
				$headings[] = mb_substr($heading, 0, 200);
			}
			if (count($headings) >= 25) {
				break;
			}
		}

		return $headings;
	}

	/**
	 * Extract internal links from HTML content.
	 *
	 * @param string $content HTML content.
	 * @return string[] Unique internal link URLs or relative paths.
	 */
	private function extract_internal_links($content) {
		preg_match_all('/<a\s+[^>]*href=["\']([^"\']+)["\']/i', (string) $content, $matches);
		$links = array();
		$site_host = wp_parse_url(home_url(), PHP_URL_HOST);

		foreach (isset($matches[1]) ? $matches[1] : array() as $href) {
			$href = trim($href);
			if (empty($href) || strpos($href, '#') === 0 || strpos($href, 'mailto:') === 0 || strpos($href, 'tel:') === 0 || strpos($href, 'javascript:') === 0) {
				continue;
			}

			$parsed = wp_parse_url($href);
			$is_internal = false;

			if (!isset($parsed['host'])) {
				// Relative URL
				$is_internal = true;
			} elseif ($site_host && isset($parsed['host']) && strcasecmp($parsed['host'], $site_host) === 0) {
				// Absolute matching site host
				$is_internal = true;
			}

			if ($is_internal && !in_array($href, $links, true)) {
				$links[] = $href;
			}
		}

		return $links;
	}

	/**
	 * Extract keyphrases from title and headings by filtering stop words.
	 *
	 * @param string   $title Post title.
	 * @param string[] $headings Heading strings.
	 * @return string[]
	 */
	private function extract_keyphrases($title, array $headings = array()) {
		$combined = $title . ' ' . implode(' ', $headings);
		$clean    = strtolower(preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $combined));
		$words    = array_values(array_filter(explode(' ', $clean)));

		$filtered = array();
		foreach ($words as $word) {
			$word = trim($word);
			if (mb_strlen($word) >= 3 && !in_array($word, self::$stop_words, true) && !is_numeric($word)) {
				$filtered[] = $word;
			}
		}

		// Count frequency of filtered terms
		$freq = array_count_values($filtered);
		arsort($freq);

		$top_terms = array_slice(array_keys($freq), 0, 10);

		// Also extract 2-word bi-grams
		$bigrams = array();
		$word_count = count($filtered);
		for ($i = 0; $i < $word_count - 1; $i++) {
			$bigram = $filtered[$i] . ' ' . $filtered[$i + 1];
			$bigrams[$bigram] = isset($bigrams[$bigram]) ? $bigrams[$bigram] + 1 : 1;
		}

		arsort($bigrams);
		$top_bigrams = array_slice(array_keys(array_filter($bigrams, function ($c) { return $c >= 1; })), 0, 5);

		return array_values(array_unique(array_merge($top_terms, $top_bigrams)));
	}

	/**
	 * Normalize a URL or relative link to a clean path string for comparison.
	 *
	 * @param string $url URL or path.
	 * @return string Clean normalized path.
	 */
	private function normalize_url_path($url) {
		$path = wp_parse_url((string) $url, PHP_URL_PATH);
		if (!$path) {
			$path = (string) $url;
		}
		return '/' . trim($path, '/') . '/';
	}
}
