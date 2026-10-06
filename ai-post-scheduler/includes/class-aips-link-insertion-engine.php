<?php
/**
 * Link Insertion Engine
 *
 * HTML-safe primitives for placing internal links into post content and
 * undoing them:
 *
 *  - find_phrase_occurrences() finds whole-word, case-insensitive matches of
 *    candidate anchor phrases inside a single text node, never inside tags,
 *    attributes, HTML comments / Gutenberg block delimiters, shortcodes or
 *    "skip zones" (existing links, headings, code, buttons, ...).
 *  - insert() wraps one occurrence in an <a> element without touching any
 *    surrounding markup and returns unique before/after snippets.
 *  - revert() swaps an after-snippet back to its before-snippet, reporting
 *    'not_found' or 'ambiguous' when the post changed since insertion.
 *
 * The engine is pure: it never reads or writes posts.
 *
 * @package AI_Post_Scheduler
 * @since 3.7.4
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Link_Insertion_Engine
 */
class AIPS_Link_Insertion_Engine {

	/**
	 * Revert succeeded.
	 */
	const REVERT_OK = 'ok';

	/**
	 * The inserted snippet is no longer present in the content.
	 */
	const REVERT_NOT_FOUND = 'not_found';

	/**
	 * The inserted snippet appears more than once, so it cannot be reverted safely.
	 */
	const REVERT_AMBIGUOUS = 'ambiguous';

	/**
	 * Bytes of context kept on each side of an insertion in its snippets.
	 */
	const SNIPPET_CONTEXT = 80;

	/**
	 * Upper bound on snippet context when widening for uniqueness.
	 */
	const SNIPPET_CONTEXT_MAX = 2000;

	/**
	 * Attribute recording the suggestion/insertion an anchor came from.
	 */
	const MARKER_ATTRIBUTE = 'data-aips-link';

	/**
	 * HTML elements whose text must never receive a link.
	 *
	 * @var string[]
	 */
	private static $skip_elements = array(
		'a', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'code', 'pre', 'kbd', 'samp',
		'script', 'style', 'textarea', 'button', 'select', 'option', 'label',
		'figcaption', 'caption', 'noscript', 'template', 'svg', 'math', 'iframe', 'object',
	);

	/**
	 * Gutenberg blocks whose content must never receive a link.
	 *
	 * @var string[]
	 */
	private static $skip_blocks = array(
		'core/heading', 'core/code', 'core/preformatted', 'core/html', 'core/shortcode',
		'core/buttons', 'core/button', 'core/navigation', 'core/table-of-contents',
		'core/verse', 'core/embed', 'core/file', 'core/search',
	);

	/**
	 * Find whole-word occurrences of phrases in linkable text.
	 *
	 * Supported options:
	 * - skip_first_paragraph bool Ignore text inside the first <p>. Default false.
	 * - limit                int  Maximum occurrences to return (0 = no limit). Default 0.
	 *
	 * Each occurrence is an array with keys:
	 * - phrase          string The phrase that matched (as passed in).
	 * - text            string Matched text, entity-decoded.
	 * - raw             string Matched text exactly as it appears in the HTML.
	 * - offset          int    Byte offset of the raw match in $html.
	 * - length          int    Byte length of the raw match.
	 * - paragraph_index int    Zero-based index of the enclosing <p>, or -1 outside paragraphs.
	 *
	 * Occurrences are ordered by offset; phrases earlier in $phrases win when
	 * matches overlap.
	 *
	 * @param string   $html    Post content.
	 * @param string[] $phrases Candidate anchor phrases.
	 * @param array    $options Options.
	 * @return array[]
	 */
	public function find_phrase_occurrences(string $html, array $phrases, array $options = array()): array {
		$phrases = array_values(array_filter(array_map('trim', array_map('strval', $phrases)), 'strlen'));
		if ($html === '' || empty($phrases)) {
			return array();
		}

		$skip_first_paragraph = !empty($options['skip_first_paragraph']);
		$limit                = isset($options['limit']) ? max(0, (int) $options['limit']) : 0;
		$shortcode_ranges     = $this->shortcode_ranges($html);

		$patterns = array();
		foreach ($phrases as $phrase) {
			$patterns[$phrase] = '/(?<![\p{L}\p{N}_])' . preg_quote(html_entity_decode($phrase, ENT_QUOTES | ENT_HTML5, 'UTF-8'), '/') . '(?![\p{L}\p{N}_])/iu';
		}

		$found = array();
		foreach ($this->linkable_text_nodes($html) as $node) {
			if ($skip_first_paragraph && $node['paragraph_index'] === 0) {
				continue;
			}

			list($decoded, $segments) = $this->decode_with_map($node['text']);
			if ($decoded === '') {
				continue;
			}

			foreach ($patterns as $phrase => $pattern) {
				if (!preg_match_all($pattern, $decoded, $matches, PREG_OFFSET_CAPTURE)) {
					continue;
				}

				foreach ($matches[0] as $match) {
					$start = $this->map_offset($segments, $match[1]);
					$end   = $this->map_offset($segments, $match[1] + strlen($match[0]));
					if ($start === null || $end === null) {
						continue;
					}

					$offset = $node['offset'] + $start;
					$length = $end - $start;

					if ($this->overlaps_ranges($offset, $length, $shortcode_ranges)) {
						continue;
					}

					$found[] = array(
						'phrase'          => $phrase,
						'text'            => $match[0],
						'raw'             => substr($html, $offset, $length),
						'offset'          => $offset,
						'length'          => $length,
						'paragraph_index' => $node['paragraph_index'],
					);
				}
			}
		}

		return $this->resolve_overlaps($found, $phrases, $limit);
	}

	/**
	 * Whether the content already links to a URL.
	 *
	 * Comparison ignores scheme, a leading "www.", a trailing slash and any
	 * fragment.
	 *
	 * @param string $html Post content.
	 * @param string $url  URL to look for.
	 * @return bool
	 */
	public function has_link_to(string $html, string $url): bool {
		$needle = $this->comparable_url($url);
		if ($needle === '' || stripos($html, '<a') === false) {
			return false;
		}

		if (!preg_match_all('/<a\b(?:[^>"\']|"[^"]*"|\'[^\']*\')*?\bhref\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s"\'=<>`]+))/i', $html, $matches, PREG_SET_ORDER)) {
			return false;
		}

		foreach ($matches as $match) {
			$href = '';
			foreach (array(1, 2, 3) as $group) {
				if (isset($match[$group]) && $match[$group] !== '') {
					$href = $match[$group];
					break;
				}
			}

			$href = html_entity_decode($href, ENT_QUOTES | ENT_HTML5, 'UTF-8');
			if ($href !== '' && $href[0] === '/' && (strlen($href) < 2 || $href[1] !== '/')) {
				$href = untrailingslashit(home_url()) . $href;
			}

			if ($this->comparable_url($href) === $needle) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Wrap one occurrence in a link.
	 *
	 * Supported attributes:
	 * - rel    string Space-separated rel values (e.g. "nofollow").
	 * - target string Link target (e.g. "_blank"); adds rel="noopener".
	 * - marker string|int Value for the data-aips-link attribute.
	 *
	 * @param string $html       Post content the occurrence was found in.
	 * @param array  $occurrence Occurrence from find_phrase_occurrences().
	 * @param string $url        Link URL.
	 * @param array  $attributes Optional attributes.
	 * @return array|WP_Error Array with content, before_snippet and after_snippet.
	 */
	public function insert(string $html, array $occurrence, string $url, array $attributes = array()) {
		$offset = isset($occurrence['offset']) ? (int) $occurrence['offset'] : -1;
		$length = isset($occurrence['length']) ? (int) $occurrence['length'] : 0;
		$raw    = isset($occurrence['raw']) ? (string) $occurrence['raw'] : '';

		if ($offset < 0 || $length <= 0 || $raw === '' || substr($html, $offset, $length) !== $raw) {
			return new WP_Error('aips_stale_occurrence', __('The content changed since the link location was found.', 'ai-post-scheduler'));
		}

		$href = esc_url($url);
		if ($href === '') {
			return new WP_Error('aips_invalid_link_url', __('The link URL is not valid.', 'ai-post-scheduler'));
		}

		$anchor  = '<a href="' . $href . '"' . $this->build_attributes($attributes) . '>' . $raw . '</a>';
		$content = substr($html, 0, $offset) . $anchor . substr($html, $offset + $length);

		list($before, $after) = $this->unique_snippets($html, $content, $offset, $length, strlen($anchor));

		return array(
			'content'        => $content,
			'before_snippet' => $before,
			'after_snippet'  => $after,
		);
	}

	/**
	 * Undo an insertion by swapping its after-snippet back to its before-snippet.
	 *
	 * @param string $html           Current post content.
	 * @param string $before_snippet Snippet recorded before insertion.
	 * @param string $after_snippet  Snippet recorded after insertion.
	 * @return array Array with status (ok|not_found|ambiguous) and content
	 *               (unchanged unless status is ok).
	 */
	public function revert(string $html, string $before_snippet, string $after_snippet): array {
		$count = ($after_snippet === '') ? 0 : substr_count($html, $after_snippet);

		if ($count === 0) {
			return array('status' => self::REVERT_NOT_FOUND, 'content' => $html);
		}

		if ($count > 1) {
			return array('status' => self::REVERT_AMBIGUOUS, 'content' => $html);
		}

		$position = strpos($html, $after_snippet);

		return array(
			'status'  => self::REVERT_OK,
			'content' => substr($html, 0, $position) . $before_snippet . substr($html, $position + strlen($after_snippet)),
		);
	}

	/**
	 * Walk the HTML and collect text nodes outside every skip zone.
	 *
	 * @param string $html Post content.
	 * @return array[] Nodes with text, offset and paragraph_index.
	 */
	private function linkable_text_nodes(string $html): array {
		$token_pattern = '/<!--.*?-->|<\/?[a-zA-Z][^\s\/>]*(?:[^>"\']|"[^"]*"|\'[^\']*\')*>/s';
		if (!preg_match_all($token_pattern, $html, $tokens, PREG_OFFSET_CAPTURE)) {
			return array(array('text' => $html, 'offset' => 0, 'paragraph_index' => -1));
		}

		$nodes           = array();
		$element_depth   = array();
		$block_depth     = 0;
		$paragraph_count = 0;
		$paragraph_index = -1;
		$cursor          = 0;

		foreach ($tokens[0] as $token) {
			list($markup, $offset) = $token;

			if ($offset > $cursor && $block_depth === 0 && array_sum($element_depth) === 0) {
				$nodes[] = array(
					'text'            => substr($html, $cursor, $offset - $cursor),
					'offset'          => $cursor,
					'paragraph_index' => $paragraph_index,
				);
			}
			$cursor = $offset + strlen($markup);

			if (strpos($markup, '<!--') === 0) {
				$block_depth = $this->track_block_comment($markup, $block_depth);
				continue;
			}

			if (!preg_match('/^<(\/?)([a-zA-Z][^\s\/>]*)/', $markup, $parts)) {
				continue;
			}

			$is_closing   = ($parts[1] === '/');
			$name         = strtolower($parts[2]);
			$self_closing = (substr(rtrim($markup, "> \t\n\r"), -1) === '/');

			if ($name === 'p') {
				if ($is_closing) {
					$paragraph_index = -1;
				} else {
					$paragraph_index = $paragraph_count++;
				}
				continue;
			}

			if (!in_array($name, self::$skip_elements, true) || $self_closing) {
				continue;
			}

			if ($is_closing) {
				if (!empty($element_depth[$name])) {
					$element_depth[$name]--;
				}
			} else {
				$element_depth[$name] = (isset($element_depth[$name]) ? $element_depth[$name] : 0) + 1;
			}
		}

		if ($cursor < strlen($html) && $block_depth === 0 && array_sum($element_depth) === 0) {
			$nodes[] = array(
				'text'            => substr($html, $cursor),
				'offset'          => $cursor,
				'paragraph_index' => $paragraph_index,
			);
		}

		return $nodes;
	}

	/**
	 * Update the skip-block depth for a Gutenberg block delimiter comment.
	 *
	 * @param string $comment     Full HTML comment.
	 * @param int    $block_depth Current depth inside skipped blocks.
	 * @return int New depth.
	 */
	private function track_block_comment(string $comment, int $block_depth): int {
		if (!preg_match('/^<!--\s+(\/)?wp:([a-z][a-z0-9_-]*(?:\/[a-z][a-z0-9_-]*)?)(.*?)(\/)?-->$/s', $comment, $parts)) {
			return $block_depth;
		}

		$name = $parts[2];
		if (strpos($name, '/') === false) {
			$name = 'core/' . $name;
		}

		/**
		 * Filter the Gutenberg blocks whose content never receives inserted links.
		 *
		 * @param string[] $blocks Block names (e.g. 'core/heading').
		 */
		$skip_blocks = (array) apply_filters('aips_link_insertion_skip_blocks', self::$skip_blocks);

		if (!in_array($name, $skip_blocks, true) || !empty($parts[4])) {
			return $block_depth;
		}

		return ($parts[1] === '/') ? max(0, $block_depth - 1) : $block_depth + 1;
	}

	/**
	 * Decode a raw text node, keeping a map back to raw byte offsets.
	 *
	 * @param string $raw Raw text node.
	 * @return array{0:string, 1:array[]} Decoded text and segments of
	 *               [decoded_start, raw_start, decoded_length, raw_length, is_entity].
	 */
	private function decode_with_map(string $raw): array {
		$segments = array();
		$decoded  = '';
		$cursor   = 0;

		if (preg_match_all('/&(?:#[0-9]+|#x[0-9a-fA-F]+|[a-zA-Z][a-zA-Z0-9]*);/', $raw, $entities, PREG_OFFSET_CAPTURE)) {
			foreach ($entities[0] as $entity) {
				list($text, $offset) = $entity;

				if ($offset > $cursor) {
					$plain      = substr($raw, $cursor, $offset - $cursor);
					$segments[] = array(strlen($decoded), $cursor, strlen($plain), strlen($plain), false);
					$decoded   .= $plain;
				}

				$char = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
				if ($char === $text) {
					// Unknown entity: keep it verbatim as plain text.
					$segments[] = array(strlen($decoded), $offset, strlen($text), strlen($text), false);
				} else {
					$segments[] = array(strlen($decoded), $offset, strlen($char), strlen($text), true);
				}
				$decoded .= $char;
				$cursor   = $offset + strlen($text);
			}
		}

		if ($cursor < strlen($raw)) {
			$plain      = substr($raw, $cursor);
			$segments[] = array(strlen($decoded), $cursor, strlen($plain), strlen($plain), false);
			$decoded   .= $plain;
		}

		return array($decoded, $segments);
	}

	/**
	 * Map a decoded byte offset to a raw byte offset.
	 *
	 * @param array[] $segments Segments from decode_with_map().
	 * @param int     $decoded_offset Offset in the decoded string.
	 * @return int|null Raw offset, or null when the offset splits an entity.
	 */
	private function map_offset(array $segments, int $decoded_offset): ?int {
		foreach ($segments as $segment) {
			list($d_start, $r_start, $d_len, $r_len, $is_entity) = $segment;

			if ($decoded_offset === $d_start) {
				return $r_start;
			}

			if ($decoded_offset > $d_start && $decoded_offset < $d_start + $d_len) {
				return $is_entity ? null : $r_start + ($decoded_offset - $d_start);
			}

			if ($decoded_offset === $d_start + $d_len) {
				$end = $r_start + $r_len;
			}
		}

		return isset($end) ? $end : ($decoded_offset === 0 ? 0 : null);
	}

	/**
	 * Byte ranges covered by registered shortcodes (tag plus any enclosed content).
	 *
	 * @param string $html Post content.
	 * @return array[] List of [start, end) ranges.
	 */
	private function shortcode_ranges(string $html): array {
		if (strpos($html, '[') === false || !function_exists('get_shortcode_regex')) {
			return array();
		}

		global $shortcode_tags;
		if (empty($shortcode_tags)) {
			return array();
		}

		if (!preg_match_all('/' . get_shortcode_regex() . '/s', $html, $matches, PREG_OFFSET_CAPTURE)) {
			return array();
		}

		$ranges = array();
		foreach ($matches[0] as $match) {
			$ranges[] = array($match[1], $match[1] + strlen($match[0]));
		}

		return $ranges;
	}

	/**
	 * Whether [offset, offset + length) overlaps any range.
	 *
	 * @param int     $offset Start offset.
	 * @param int     $length Length.
	 * @param array[] $ranges Ranges from shortcode_ranges().
	 * @return bool
	 */
	private function overlaps_ranges(int $offset, int $length, array $ranges): bool {
		foreach ($ranges as $range) {
			if ($offset < $range[1] && $offset + $length > $range[0]) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Sort matches by offset and drop overlaps, preferring earlier phrases.
	 *
	 * @param array[]  $found   Raw matches.
	 * @param string[] $phrases Phrases in priority order.
	 * @param int      $limit   Maximum results (0 = no limit).
	 * @return array[]
	 */
	private function resolve_overlaps(array $found, array $phrases, int $limit): array {
		$priority = array_flip($phrases);

		usort($found, function ($a, $b) use ($priority) {
			$by_phrase = $priority[$a['phrase']] <=> $priority[$b['phrase']];
			return $by_phrase !== 0 ? $by_phrase : ($a['offset'] <=> $b['offset']);
		});

		$kept = array();
		foreach ($found as $candidate) {
			foreach ($kept as $existing) {
				if ($candidate['offset'] < $existing['offset'] + $existing['length'] && $candidate['offset'] + $candidate['length'] > $existing['offset']) {
					continue 2;
				}
			}
			$kept[] = $candidate;
		}

		usort($kept, function ($a, $b) {
			return $a['offset'] <=> $b['offset'];
		});

		return $limit > 0 ? array_slice($kept, 0, $limit) : $kept;
	}

	/**
	 * Build escaped attributes for an inserted anchor.
	 *
	 * @param array $attributes Attributes passed to insert().
	 * @return string Leading-space attribute string.
	 */
	private function build_attributes(array $attributes): string {
		$rel = array();
		if (!empty($attributes['rel'])) {
			$rel = preg_split('/\s+/', strtolower(trim((string) $attributes['rel'])));
		}

		$out = '';
		if (!empty($attributes['target'])) {
			$out  .= ' target="' . esc_attr((string) $attributes['target']) . '"';
			$rel[] = 'noopener';
		}

		$rel = array_values(array_unique(array_filter($rel)));
		if (!empty($rel)) {
			$out .= ' rel="' . esc_attr(implode(' ', $rel)) . '"';
		}

		if (isset($attributes['marker']) && $attributes['marker'] !== '') {
			$out .= ' ' . self::MARKER_ATTRIBUTE . '="' . esc_attr((string) $attributes['marker']) . '"';
		}

		return $out;
	}

	/**
	 * Build before/after snippets around an edit, widened until each is
	 * unique in its document.
	 *
	 * @param string $before_html Original content.
	 * @param string $after_html  Content after insertion.
	 * @param int    $offset      Edit offset (same in both documents).
	 * @param int    $old_length  Length of replaced text in the original.
	 * @param int    $new_length  Length of replacement text.
	 * @return array{0:string, 1:string}
	 */
	private function unique_snippets(string $before_html, string $after_html, int $offset, int $old_length, int $new_length): array {
		$context = self::SNIPPET_CONTEXT;

		do {
			$start  = max(0, $offset - $context);
			$prefix = $offset - $start;
			$suffix_before = min($context, strlen($before_html) - ($offset + $old_length));

			$before = substr($before_html, $start, $prefix + $old_length + $suffix_before);
			$after  = substr($after_html, $start, $prefix + $new_length + $suffix_before);

			$unique = substr_count($before_html, $before) === 1 && substr_count($after_html, $after) === 1;
			$whole  = ($start === 0 && $offset + $old_length + $suffix_before >= strlen($before_html));

			$context *= 2;
		} while (!$unique && !$whole && $context <= self::SNIPPET_CONTEXT_MAX);

		return array($before, $after);
	}

	/**
	 * Reduce a URL to host + path for loose equality checks.
	 *
	 * @param string $url URL.
	 * @return string
	 */
	private function comparable_url(string $url): string {
		$url = strtolower(trim($url));
		$url = (string) preg_replace('/#.*$/', '', $url);
		$url = (string) preg_replace('#^(https?:)?//(www\.)?#', '', $url);

		return untrailingslashit($url);
	}
}
