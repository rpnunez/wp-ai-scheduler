<?php
/**
 * Internal Link Suggestions Service
 *
 * Builds ranked internal link suggestions for draft review flows and
 * applies accepted links into post content.
 *
 * @package AI_Post_Scheduler
 */

if (!defined('ABSPATH')) {
	exit;
}

class AIPS_Internal_Link_Suggestions_Service {

	/**
	 * Meta key for persisted review suggestion state.
	 */
	const META_KEY = '_aips_internal_link_suggestions';

	/**
	 * Build or fetch suggestion state for a draft post.
	 *
	 * @param int   $post_id Post ID.
	 * @param bool  $force_regenerate Whether to force fresh generation.
	 * @param array $args Generator arguments.
	 * @return array
	 */
	public function get_or_generate_suggestions($post_id, $force_regenerate = false, $args = array()) {
		$post_id = absint($post_id);
		if (!$post_id) {
			return array();
		}

		$state = get_post_meta($post_id, self::META_KEY, true);
		if (!is_array($state)) {
			$state = array(
				'suggestions' => array(),
				'updated_at' => 0,
			);
		}

		if (!$force_regenerate && !empty($state['suggestions'])) {
			return $state['suggestions'];
		}

		$accepted_urls = array();
		if (!empty($state['suggestions']) && is_array($state['suggestions'])) {
			foreach ($state['suggestions'] as $suggestion) {
				if (isset($suggestion['status'], $suggestion['target_url']) && 'accepted' === $suggestion['status']) {
					$accepted_urls[] = esc_url_raw($suggestion['target_url']);
				}
			}
		}

		$fresh = $this->generate_suggestions($post_id, $args, $accepted_urls);

		if (!empty($accepted_urls)) {
			$accepted_rows = array();
			foreach ($state['suggestions'] as $suggestion) {
				if ('accepted' === (isset($suggestion['status']) ? $suggestion['status'] : '') && !empty($suggestion['target_url'])) {
					$accepted_rows[] = $suggestion;
				}
			}
			$fresh = array_merge($accepted_rows, $fresh);
		}

		$state = array(
			'suggestions' => $fresh,
			'updated_at' => time(),
		);

		update_post_meta($post_id, self::META_KEY, $state);

		return $fresh;
	}

	/**
	 * Set suggestion decision and optionally apply link.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $suggestion_id Suggestion id.
	 * @param string $decision pending|accepted|rejected.
	 * @return array
	 */
	public function set_suggestion_decision($post_id, $suggestion_id, $decision) {
		$post_id = absint($post_id);
		$decision = sanitize_key($decision);
		if (!$post_id || !in_array($decision, array('pending', 'accepted', 'rejected'), true)) {
			return array('success' => false, 'message' => __('Invalid suggestion request.', 'ai-post-scheduler'));
		}

		$state = get_post_meta($post_id, self::META_KEY, true);
		if (!is_array($state) || empty($state['suggestions']) || !is_array($state['suggestions'])) {
			return array('success' => false, 'message' => __('No suggestions found for this post.', 'ai-post-scheduler'));
		}

		$matched_index = -1;
		foreach ($state['suggestions'] as $index => $suggestion) {
			if (!empty($suggestion['id']) && (string) $suggestion['id'] === (string) $suggestion_id) {
				$matched_index = $index;
				break;
			}
		}

		if ($matched_index < 0) {
			return array('success' => false, 'message' => __('Suggestion not found.', 'ai-post-scheduler'));
		}

		$state['suggestions'][$matched_index]['status'] = $decision;

		$applied = false;
		if ('accepted' === $decision) {
			$applied = $this->apply_suggestion_to_post($post_id, $state['suggestions'][$matched_index]);
			$state['suggestions'][$matched_index]['applied'] = $applied ? 1 : 0;
		}

		$state['updated_at'] = time();
		update_post_meta($post_id, self::META_KEY, $state);

		return array(
			'success' => true,
			'applied' => $applied,
			'suggestions' => $state['suggestions'],
		);
	}

	/**
	 * Generate fresh suggestions for a post.
	 *
	 * @param int   $post_id Draft post ID.
	 * @param array $args Settings.
	 * @param array $excluded_urls URLs that should not be re-suggested.
	 * @return array
	 */
	public function generate_suggestions($post_id, $args = array(), $excluded_urls = array()) {
		$post = get_post($post_id);
		if (!$post || 'draft' !== $post->post_status) {
			return array();
		}

		$defaults = array(
			'limit' => 5,
			'min_confidence' => 35,
			'max_candidates' => 100,
		);
		$args = wp_parse_args($args, $defaults);
		$excluded_urls = array_filter(array_map('esc_url_raw', (array) $excluded_urls));

		$candidate_posts = get_posts(array(
			'post_type' => $post->post_type,
			'post_status' => 'publish',
			'posts_per_page' => (int) $args['max_candidates'],
			'post__not_in' => array((int) $post_id),
			'orderby' => 'date',
			'order' => 'DESC',
			'suppress_filters' => false,
		));

		if (empty($candidate_posts)) {
			return array();
		}

		$ranked = $this->rank_candidate_posts($post, $candidate_posts, array(
			'limit' => (int) $args['limit'],
			'min_confidence' => (int) $args['min_confidence'],
			'excluded_urls' => $excluded_urls,
		));

		return $ranked;
	}

	/**
	 * Rank candidate posts for internal linking.
	 *
	 * @param object $draft_post Draft post object.
	 * @param array  $candidate_posts Candidate post objects.
	 * @param array  $args Ranking args.
	 * @return array
	 */
	public function rank_candidate_posts($draft_post, $candidate_posts, $args = array()) {
		$defaults = array(
			'limit' => 5,
			'min_confidence' => 35,
			'excluded_urls' => array(),
		);
		$args = wp_parse_args($args, $defaults);

		$context_title_tokens = $this->extract_keywords(isset($draft_post->post_title) ? $draft_post->post_title : '');
		$context_content_tokens = $this->extract_keywords(wp_strip_all_tags(isset($draft_post->post_content) ? $draft_post->post_content : ''));
		$context_tokens = array_unique(array_merge($context_title_tokens, $context_content_tokens));

		if (empty($context_tokens)) {
			return array();
		}

		$rows = array();
		$seen_signatures = array();
		$excluded_urls = array_filter(array_map('esc_url_raw', (array) $args['excluded_urls']));

		foreach ((array) $candidate_posts as $candidate) {
			$candidate_id = isset($candidate->ID) ? (int) $candidate->ID : 0;
			if (!$candidate_id) {
				continue;
			}

			$target_url = get_permalink($candidate_id);
			$target_url = esc_url_raw($target_url);
			if (!$target_url || in_array($target_url, $excluded_urls, true)) {
				continue;
			}

			$target_title = isset($candidate->post_title) ? (string) $candidate->post_title : '';
			$target_content = wp_strip_all_tags(isset($candidate->post_content) ? $candidate->post_content : '');
			$title_tokens = $this->extract_keywords($target_title);
			$content_tokens = $this->extract_keywords($target_content);

			$title_overlap = array_values(array_intersect($context_tokens, $title_tokens));
			$content_overlap = array_values(array_intersect($context_tokens, $content_tokens));
			$total_overlap = array_unique(array_merge($title_overlap, $content_overlap));
			if (empty($total_overlap)) {
				continue;
			}

			$title_similarity = 0;
			if (!empty($draft_post->post_title) && !empty($target_title)) {
				similar_text(mb_strtolower($draft_post->post_title), mb_strtolower($target_title), $title_similarity);
			}

			$confidence = (count($title_overlap) * 20) + (min(6, count($content_overlap)) * 8) + ($title_similarity * 0.35);
			$confidence = (int) max(1, min(99, round($confidence)));
			if ($confidence < (int) $args['min_confidence']) {
				continue;
			}

			$anchor = $this->build_anchor_text($target_title, $total_overlap);
			if ('' === $anchor) {
				continue;
			}

			$signature = strtolower($target_url . '|' . $anchor);
			if (isset($seen_signatures[$signature])) {
				continue;
			}
			$seen_signatures[$signature] = true;

			$rows[] = array(
				'id' => md5($candidate_id . '|' . $anchor . '|' . $target_url),
				'target_post_id' => $candidate_id,
				'target_url' => $target_url,
				'anchor_text' => $anchor,
				'confidence' => $confidence,
				'relevance_terms' => array_slice($total_overlap, 0, 4),
				'status' => 'pending',
			);
		}

		usort($rows, function($a, $b) {
			return (int) $b['confidence'] <=> (int) $a['confidence'];
		});

		return array_slice($rows, 0, (int) $args['limit']);
	}

	/**
	 * Apply a single suggestion to draft post content.
	 *
	 * @param int   $post_id Post ID.
	 * @param array $suggestion Suggestion row.
	 * @return bool
	 */
	public function apply_suggestion_to_post($post_id, $suggestion) {
		$post = get_post($post_id);
		if (!$post || 'draft' !== $post->post_status) {
			return false;
		}

		$anchor_text = isset($suggestion['anchor_text']) ? sanitize_text_field($suggestion['anchor_text']) : '';
		$target_url = isset($suggestion['target_url']) ? esc_url_raw($suggestion['target_url']) : '';
		if ('' === $anchor_text || '' === $target_url) {
			return false;
		}

		$content = (string) $post->post_content;
		if (false !== stripos($content, 'href="' . $target_url . '"') || false !== stripos($content, "href='" . $target_url . "'")) {
			return false;
		}

		$updated_content = $this->insert_first_link($content, $anchor_text, $target_url);
		if ($updated_content === $content) {
			return false;
		}

		$updated_content = wp_kses_post($updated_content);
		$result = wp_update_post(array(
			'ID' => (int) $post_id,
			'post_content' => $updated_content,
		), true);

		return !is_wp_error($result);
	}

	/**
	 * Insert a link at the first anchor text occurrence while preserving HTML.
	 *
	 * @param string $content Content HTML.
	 * @param string $anchor_text Anchor text.
	 * @param string $target_url URL.
	 * @return string
	 */
	private function insert_first_link($content, $anchor_text, $target_url) {
		if ('' === trim($content) || '' === trim($anchor_text) || '' === trim($target_url)) {
			return $content;
		}

		if (!class_exists('DOMDocument')) {
			$quoted = preg_quote($anchor_text, '/');
			return preg_replace('/' . $quoted . '/u', '<a href="' . esc_url($target_url) . '">' . esc_html($anchor_text) . '</a>', $content, 1);
		}

		$dom = new DOMDocument();
		$internal_errors = libxml_use_internal_errors(true);
		$loaded = $dom->loadHTML('<?xml encoding="utf-8" ?><div id="aips-link-wrap">' . $content . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
		libxml_clear_errors();
		libxml_use_internal_errors($internal_errors);

		if (!$loaded) {
			return $content;
		}

		$xpath = new DOMXPath($dom);
		$nodes = $xpath->query('//div[@id="aips-link-wrap"]//text()[normalize-space()]');
		if (!$nodes) {
			return $content;
		}

		$needle_lower = mb_strtolower($anchor_text);
		foreach ($nodes as $text_node) {
			if ($text_node->parentNode && 'a' === strtolower($text_node->parentNode->nodeName)) {
				continue;
			}

			$node_text = $text_node->nodeValue;
			$position = mb_stripos($node_text, $anchor_text);
			if (false === $position) {
				$position = mb_stripos(mb_strtolower($node_text), $needle_lower);
			}
			if (false === $position) {
				continue;
			}

			$before = mb_substr($node_text, 0, $position);
			$match = mb_substr($node_text, $position, mb_strlen($anchor_text));
			$after = mb_substr($node_text, $position + mb_strlen($anchor_text));

			$fragment = $dom->createDocumentFragment();
			if ('' !== $before) {
				$fragment->appendChild($dom->createTextNode($before));
			}

			$link = $dom->createElement('a');
			$link->setAttribute('href', esc_url_raw($target_url));
			$link->appendChild($dom->createTextNode($match));
			$fragment->appendChild($link);

			if ('' !== $after) {
				$fragment->appendChild($dom->createTextNode($after));
			}

			$text_node->parentNode->replaceChild($fragment, $text_node);

			$wrapper = $dom->getElementById('aips-link-wrap');
			return $this->get_inner_html($wrapper);
		}

		return $content;
	}

	/**
	 * Build anchor text suggestion.
	 *
	 * @param string $title Target title.
	 * @param array  $overlap_terms Shared terms.
	 * @return string
	 */
	private function build_anchor_text($title, $overlap_terms) {
		$title = trim((string) $title);
		if ('' === $title) {
			if (!empty($overlap_terms)) {
				return sanitize_text_field($overlap_terms[0]);
			}
			return '';
		}

		if (!empty($overlap_terms)) {
			foreach ($overlap_terms as $term) {
				$term = trim((string) $term);
				if ('' !== $term && false !== mb_stripos($title, $term)) {
					return sanitize_text_field($term);
				}
			}
		}

		$parts = preg_split('/\s+/', wp_strip_all_tags($title));
		$parts = array_filter(array_map('sanitize_text_field', (array) $parts));
		return implode(' ', array_slice($parts, 0, 6));
	}

	/**
	 * Extract normalized keyword tokens.
	 *
	 * @param string $text Raw text.
	 * @return array
	 */
	private function extract_keywords($text) {
		$text = mb_strtolower(wp_strip_all_tags((string) $text));
		$text = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $text);
		$parts = preg_split('/\s+/', (string) $text);

		$stop_words = array(
			'the', 'and', 'for', 'with', 'this', 'that', 'from', 'into', 'your', 'you', 'are', 'was', 'were', 'have', 'has', 'had',
			'will', 'would', 'could', 'should', 'about', 'what', 'when', 'where', 'why', 'how', 'can', 'our', 'their', 'them', 'they',
			'than', 'then', 'there', 'here', 'also', 'just', 'more', 'most', 'some', 'over', 'under', 'onto', 'out', 'per', 'via'
		);

		$tokens = array();
		foreach ((array) $parts as $token) {
			$token = trim((string) $token);
			if (mb_strlen($token) < 4) {
				continue;
			}
			if (in_array($token, $stop_words, true)) {
				continue;
			}
			$tokens[] = $token;
		}

		return array_values(array_unique($tokens));
	}

	/**
	 * Return an element's inner HTML.
	 *
	 * @param DOMNode|null $node Node.
	 * @return string
	 */
	private function get_inner_html($node) {
		if (!$node || !isset($node->ownerDocument)) {
			return '';
		}

		$html = '';
		foreach ($node->childNodes as $child) {
			$html .= $node->ownerDocument->saveHTML($child);
		}
		return $html;
	}
}
