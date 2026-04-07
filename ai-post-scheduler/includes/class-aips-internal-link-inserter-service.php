<?php
/**
 * Internal Link Inserter Service
 *
 * Uses AI to identify the best locations to insert a specific internal link
 * into a post's content, and applies the chosen insertion.
 *
 * @package AI_Post_Scheduler
 * @since 2.4.0
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Internal_Link_Inserter_Service
 *
 * Provides two operations:
 *  1. find_insertion_locations() — calls AI to identify up to
 *     NUM_LOCATIONS_TO_REQUEST ideal positions
 *     within a post's content to insert a given internal link.
 *  2. apply_insertion() — performs the actual string replacement in the post and
 *     marks the linked suggestion as inserted.
 */
class AIPS_Internal_Link_Inserter_Service {

	/**
	 * Marker used in AI plain-text replacements to denote the exact words
	 * that should become the hyperlink.
	 *
	 * @var string Regex pattern for the link marker.
	 */
	const LINK_MARKER_PATTERN = '/\[\[(.*?)\]\]/s';

	/**
	 * Default number of insertion locations to request from the AI.
	 */
	const NUM_LOCATIONS_TO_REQUEST = 3;

	/**
	 * Hard upper bound for AI max_tokens requests.
	 */
	const MAX_TOKENS_LIMIT = 10000;

	/**
	 * Character-to-token estimate used for dynamic max_tokens calculation.
	 */
	const CHARS_PER_TOKEN_ESTIMATE = 4;

	/**
	 * Base response-token buffer for JSON envelope + metadata text.
	 */
	const BASE_RESPONSE_TOKENS_BUFFER = 180;

	/**
	 * Additional response-token buffer per requested location object.
	 */
	const RESPONSE_TOKENS_PER_LOCATION = 220;

	/**
	 * @var AIPS_Internal_Links_Repository
	 */
	private $links_repo;

	/**
	 * @var AIPS_AI_Service
	 */
	private $ai_service;

	/**
	 * @var AIPS_Logger
	 */
	private $logger;

	/**
	 * Initialize the service.
	 *
	 * @param AIPS_Internal_Links_Repository|null $links_repo Links repository.
	 * @param AIPS_AI_Service|null                $ai_service AI service.
	 * @param AIPS_Logger|null                    $logger     Logger instance.
	 */
	public function __construct(
		$links_repo = null,
		$ai_service = null,
		$logger = null
	) {
		$this->links_repo = $links_repo ?: new AIPS_Internal_Links_Repository();
		$this->ai_service = $ai_service ?: new AIPS_AI_Service();
		$this->logger     = $logger     ?: new AIPS_Logger();
	}

	// -------------------------------------------------------------------------
	// Public API
	// -------------------------------------------------------------------------

	/**
	 * Ask AI to identify up to NUM_LOCATIONS_TO_REQUEST best positions for
	 * inserting an internal link.
	 *
	 * Returns an array of 0 to NUM_LOCATIONS_TO_REQUEST location objects, each
	 * containing:
	 *   - reason              (string) Why this location is relevant.
	 *   - match_snippet       (string) ~15-word excerpt of the original content.
	 *   - replacement_snippet (string) The same excerpt in plain text with the
	 *     exact link words wrapped in [[double square brackets]].
	 *
	 * @param int $suggestion_id Internal link suggestion row ID.
	 * @return array|WP_Error Array of location objects, or WP_Error on failure.
	 */
	public function find_insertion_locations($suggestion_id) {
		$suggestion = $this->links_repo->get_by_id(absint($suggestion_id));

		if (!$suggestion) {
			return new WP_Error(
				'suggestion_not_found',
				__('Suggestion not found.', 'ai-post-scheduler')
			);
		}

		$source_post = get_post($suggestion->source_post_id);
		$target_post = get_post($suggestion->target_post_id);

		if (!$source_post) {
			return new WP_Error(
				'source_not_found',
				__('Source post not found.', 'ai-post-scheduler')
			);
		}

		if (!$target_post) {
			return new WP_Error(
				'target_not_found',
				__('Target post not found.', 'ai-post-scheduler')
			);
		}

		$post_content       = $source_post->post_content;
		$plain_text_content = $this->normalize_content($post_content);

		if (empty($plain_text_content)) {
			return new WP_Error(
				'empty_content',
				__('The source post has no content to link from.', 'ai-post-scheduler')
			);
		}

		$plain_text_content = $this->truncate_prompt_content($plain_text_content, 1400);

		$target_url  = get_permalink($target_post->ID);
		$anchor_text = !empty($suggestion->anchor_text) ? $suggestion->anchor_text : $target_post->post_title;
		$post_title  = $target_post->post_title;

		$prompt = $this->build_prompt($plain_text_content, $post_title, $anchor_text, $target_url, self::NUM_LOCATIONS_TO_REQUEST);
		$max_tokens = $this->calculate_max_tokens($prompt, self::NUM_LOCATIONS_TO_REQUEST);

		$this->logger->log(
			sprintf(
				'Finding insertion locations for suggestion #%d (source=%d, target=%d)',
				(int) $suggestion->id,
				(int) $suggestion->source_post_id,
				(int) $suggestion->target_post_id
			),
			'info'
		);
		$ai_result = $this->ai_service->generate_json_from_text(
			$prompt,
			array(
				'max_tokens'  => $max_tokens,
			)
		);

		if (is_wp_error($ai_result)) {
			$this->logger->log(
				sprintf(
					'Internal link inserter: JSON parse failed for suggestion #%d — %s',
					(int) $suggestion->id,
					$ai_result->get_error_message()
				),
				'error'
			);

			return $ai_result;
		}

		$locations = $this->validate_locations($ai_result);

		$locations = array_slice($locations, 0, self::NUM_LOCATIONS_TO_REQUEST);

		if (empty($locations)) {
			$this->logger->log(
				sprintf(
					'Internal link inserter: suggestion #%d returned 0 valid insertion locations.',
					(int) $suggestion->id
				),
				'info'
			);
		}

		return $locations;
	}

	/**
	 * Apply a specific insertion to the source post content.
	 *
	 * Performs a single occurrence replacement of match_snippet with
	 * replacement_snippet in the source post's HTML content. The AI-provided
	 * replacement is plain text and uses [[double square brackets]] to mark the
	 * exact words that should become the link; the service constructs the final
	 * anchor HTML during apply.
	 *
	 * @param int    $suggestion_id      Internal link suggestion row ID.
	 * @param string $match_snippet      Exact text excerpt to find in the post content.
	 * @param string $replacement_snippet Text to substitute in place of match_snippet.
	 * @return true|WP_Error True on success, WP_Error on failure.
	 */
	public function apply_insertion($suggestion_id, $match_snippet, $replacement_snippet) {
		$suggestion_id = absint($suggestion_id);
		$suggestion    = $this->links_repo->get_by_id($suggestion_id);

		if (!$suggestion) {
			return new WP_Error(
				'suggestion_not_found',
				__('Suggestion not found.', 'ai-post-scheduler')
			);
		}

		$source_post = get_post($suggestion->source_post_id);

		if (!$source_post) {
			return new WP_Error(
				'source_not_found',
				__('Source post not found.', 'ai-post-scheduler')
			);
		}

		$post_content = $source_post->post_content;
		$target_post  = get_post($suggestion->target_post_id);

		if (!$target_post) {
			return new WP_Error(
				'target_not_found',
				__('Target post not found.', 'ai-post-scheduler')
			);
		}

		$anchor_text      = !empty($suggestion->anchor_text) ? $suggestion->anchor_text : $target_post->post_title;
		$target_url       = get_permalink($target_post->ID);
		$link_html        = $this->build_anchor_html($target_url, $anchor_text);
		$replacement_html = $this->build_replacement_html($replacement_snippet, $link_html, $anchor_text);

		if (is_wp_error($replacement_html)) {
			return $replacement_html;
		}

		$html_snippet = $this->find_snippet_in_html($match_snippet, $post_content);

		if ($html_snippet === null) {
			return new WP_Error(
				'snippet_not_found',
				__('The selected text was not found in the current post HTML content. Please choose another suggestion.', 'ai-post-scheduler')
			);
		}

		$new_content = implode(
			$replacement_html,
			explode($html_snippet, $post_content, 2)
		);

		$update_args = array(
			'ID'           => $source_post->ID,
			'post_content' => $new_content,
		);

		$result = wp_update_post($update_args, true);

		if (is_wp_error($result)) {
			return $result;
		}

		$this->links_repo->update_status($suggestion_id, 'inserted');

		$this->logger->log(
			sprintf(
				'Internal link inserter: link applied for suggestion #%d into post #%d',
				$suggestion_id,
				(int) $source_post->ID
			),
			'info'
		);

		return true;
	}

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	/**
	 * Convert raw post HTML content to a clean plain-text representation
	 * safe to embed in an AI prompt.
	 *
	 * Removes HTML tags, decodes entities, collapses redundant whitespace, and
	 * strips non-printable control characters (everything below U+0020 except
	 * space, tab, LF, and CR).
	 *
	 * @param string $post_content Raw post HTML.
	 * @return string Sanitized plain text.
	 */
	private function normalize_content($post_content) {
		$text = wp_strip_all_tags($post_content);
		$text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
		$text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $text);
		$text = str_replace("\r\n", "\n", $text);
		$text = preg_replace('/\n{3,}/', "\n\n", $text);

		return trim($text);
	}

	/**
	 * Find a plain-text snippet in an HTML string.
	 *
	 * Tries an exact match first (fast path for content without inline HTML).
	 * Falls back to a word-aware regex that tolerates inline HTML tags
	 * (e.g. <strong>, <em>, <a>) interspersed between words, as can occur when
	 * the AI-generated snippet is derived from the strip_tags version of the
	 * post but the stored content has inline formatting.
	 *
	 * Block-level tag boundaries (p, div, blockquote, h1–h6, ul, ol, li, etc.)
	 * are NOT crossed; those indicate a different paragraph and the snippet
	 * should not span them.
	 *
	 * @param string $match_snippet Plain-text snippet from the AI.
	 * @param string $post_content  Raw post HTML.
	 * @return string|null The actual substring from $post_content that matches,
	 *                     or null if the snippet cannot be located.
	 */
	private function find_snippet_in_html($match_snippet, $post_content) {
		// Fast path: verbatim match in the HTML.
		if (strpos($post_content, $match_snippet) !== false) {
			return $match_snippet;
		}

		// Slow path: split the snippet into words and build a regex that
		// allows inline HTML tags (but not block-level boundaries) between them.
		$words = preg_split('/\s+/', trim($match_snippet), -1, PREG_SPLIT_NO_EMPTY);

		if (empty($words)) {
			return null;
		}

		// Guard against ReDoS from excessively long snippets.
		// AI snippets should be ~15 words; 60 is a very generous upper bound.
		if (count($words) > 60) {
			return null;
		}

		$escaped_words = array_map(
			function ($w) {
				return preg_quote($w, '/');
			},
			$words
		);

		// Pattern that matches whitespace or opening/closing inline tags between
		// two consecutive words. The negative lookahead excludes block-level tags
		// (p, div, blockquote, h1–h6, ul, ol, li, table rows/cells, br, hr,
		// pre, figure, figcaption) so the match cannot span paragraph boundaries.
		// Any other tag name (e.g. strong, em, a, span) is permitted.
		$block_tags       = 'p|div|blockquote|h[1-6]|ul|ol|li|table|tr|td|th|br|hr|pre|figure|figcaption';
		$inline_between   = '(?:\s|</?(?!(?:' . $block_tags . ')\b)[a-zA-Z][^>]*>)*';

		$pattern = '/' . implode($inline_between, $escaped_words) . '/siu';

		if (preg_match($pattern, $post_content, $matches)) {
			return $matches[0];
		}

		return null;
	}

	/**
	 * Truncate prompt content to keep the AI request and response bounded.
	 *
	 * @param string $content Normalized plain-text content.
	 * @param int    $limit   Maximum characters to include.
	 * @return string
	 */
	private function truncate_prompt_content($content, $limit = 4000) {
		if (strlen($content) <= $limit) {
			return $content;
		}

		$truncated = substr($content, 0, $limit);
		$boundary  = max(strrpos($truncated, '. '), strrpos($truncated, "\n"));

		if ($boundary !== false && $boundary > (int) ($limit * 0.7)) {
			$truncated = substr($truncated, 0, $boundary + 1);
		}

		return trim($truncated);
	}

	/**
	 * Build the AI prompt for finding insertion locations.
	 *
	 * @param string $plain_text_content Normalized plain-text post content.
	 * @param string $post_title         Target post title.
	 * @param string $anchor_text        Suggested anchor text for the link.
	 * @param string $target_url         Target post URL.
     * @param int    $num_locations      Number of insertion locations to request.
	 * @return string Prompt string.
	 */
	private function build_prompt($plain_text_content, $post_title, $anchor_text, $target_url, $num_locations = 1) {
		return sprintf(
			/* translators: internal use only — AI prompt, not shown to end users */
			"Return ONLY a valid JSON array. No markdown. No prose.\n"
			. "Task: Find %d insertion locations for an internal link in the text below.\n"
			. "Each array item must be an object with exactly these keys: reason, match_snippet, replacement_snippet.\n"
			. "Rules:\n"
			. "1) Return %d objects when possible. If not possible, return fewer. If none, return [].\n"
			. "2) reason must be under 8 words.\n"
			. "3) match_snippet must be an exact substring from the text.\n"
			. "4) replacement_snippet must be the same excerpt as match_snippet, but with ONLY the link words wrapped as [[%s]].\n"
			. "5) Do not change wording outside the wrapped link words.\n"
			. "6) No HTML. No extra keys.\n\n"
			. "Target post title: %s\n"
			. "Anchor text: %s\n"
			. "URL: %s\n\n"
			. "Text:\n%s",
			$num_locations,
			$num_locations,
			$anchor_text,
			$post_title,
			$anchor_text,
			$target_url,
			$plain_text_content
		);
	}

	/**
	 * Calculate max_tokens dynamically from prompt size plus a response buffer.
	 *
	 * @param string $prompt        Prompt string sent to the AI.
	 * @param int    $num_locations Number of requested location objects.
	 * @return int
	 */
	private function calculate_max_tokens($prompt, $num_locations) {
		$prompt_chars  = strlen((string) $prompt);
		$prompt_tokens = (int) ceil($prompt_chars / self::CHARS_PER_TOKEN_ESTIMATE);
		$response_tokens = self::BASE_RESPONSE_TOKENS_BUFFER + (max(1, (int) $num_locations) * self::RESPONSE_TOKENS_PER_LOCATION);

		$calculated = $prompt_tokens + $response_tokens;

		return (int) min(max(256, $calculated), self::MAX_TOKENS_LIMIT);
	}

	/**
	 * Validate and normalize the raw AI response array.
	 *
	 * @param mixed $raw Raw value from generate_json_from_text().
	 * @return array[] Validated location objects.
	 */
	private function validate_locations($raw) {
		if (!is_array($raw)) {
			return array();
		}

		$locations = array();

		foreach ($raw as $item) {
			if (!is_array($item)) {
				continue;
			}

			$match_snippet       = isset($item['match_snippet']) ? $item['match_snippet'] : (isset($item['matchSnippet']) ? $item['matchSnippet'] : '');
			$replacement_snippet = isset($item['replacement_snippet']) ? $item['replacement_snippet'] : (isset($item['replacementSnippet']) ? $item['replacementSnippet'] : '');

			$match_snippet       = trim($match_snippet);
			$replacement_snippet = trim($replacement_snippet);

			if (empty($match_snippet) || empty($replacement_snippet)) {
				continue;
			}

			if (strpos($replacement_snippet, '<') !== false || strpos($replacement_snippet, '>') !== false) {
				continue;
			}

			if (!$this->extract_marked_anchor_text($replacement_snippet)) {
				continue;
			}

			$locations[] = array(
				'reason'              => isset($item['reason']) ? sanitize_text_field($item['reason']) : '',
				'match_snippet'       => $match_snippet,
				'replacement_snippet' => $replacement_snippet,
			);
		}

		return $locations;
	}

	/**
	 * Extract the marked anchor text from a plain-text replacement snippet.
	 *
	 * @param string $replacement_snippet Replacement snippet with [[marker]].
	 * @return string|false Marked text, or false if invalid.
	 */
	private function extract_marked_anchor_text($replacement_snippet) {
		if (!preg_match_all(self::LINK_MARKER_PATTERN, $replacement_snippet, $matches) || count($matches[1]) !== 1) {
			return false;
		}

		$marked_text = trim($matches[1][0]);

		return $marked_text === '' ? false : $marked_text;
	}

	/**
	 * Build the final anchor HTML for insertion.
	 *
	 * @param string $target_url  Target post permalink.
	 * @param string $anchor_text Anchor text to display.
	 * @return string
	 */
	private function build_anchor_html($target_url, $anchor_text) {
		return sprintf(
			'<a href="%s">%s</a>',
			esc_url($target_url),
			esc_html($anchor_text)
		);
	}

	/**
	 * Convert a plain-text replacement snippet into final HTML replacement.
	 *
	 * @param string $replacement_snippet AI plain-text replacement.
	 * @param string $link_html           Final anchor HTML.
	 * @param string $expected_anchor     Expected anchor text.
	 * @return string|WP_Error
	 */
	private function build_replacement_html($replacement_snippet, $link_html, $expected_anchor) {
		$marked_text = $this->extract_marked_anchor_text($replacement_snippet);

		if ($marked_text === false) {
			return new WP_Error(
				'invalid_replacement_snippet',
				__('The selected insertion is missing the required link marker.', 'ai-post-scheduler')
			);
		}

		if ($this->normalize_content($marked_text) !== $this->normalize_content($expected_anchor)) {
			return new WP_Error(
				'invalid_anchor_text',
				__('The selected insertion did not preserve the expected anchor text.', 'ai-post-scheduler')
			);
		}

		return preg_replace(self::LINK_MARKER_PATTERN, $link_html, $replacement_snippet, 1);
	}
}
