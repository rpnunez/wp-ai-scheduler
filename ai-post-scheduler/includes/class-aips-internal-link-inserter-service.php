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
 *  1. find_insertion_locations() — calls AI to identify up to 3 ideal positions
 *     within a post's content to insert a given internal link.
 *  2. apply_insertion() — performs the actual string replacement in the post and
 *     marks the linked suggestion as inserted.
 */
class AIPS_Internal_Link_Inserter_Service {

	/**
	 * Marker used in AI plain-text replacements to denote the exact words
	 * that should become the hyperlink.
	 *
	 * @var string
	 */
	const LINK_MARKER_PATTERN = '/\[\[(.*?)\]\]/s';

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
	 * Ask AI to identify up to 3 best positions for inserting an internal link.
	 *
	 * Returns an array of up to 3 location objects, each containing:
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

		$plain_text_content = $this->truncate_prompt_content($plain_text_content, 4000);

		$target_url  = get_permalink($target_post->ID);
		$anchor_text = !empty($suggestion->anchor_text) ? $suggestion->anchor_text : $target_post->post_title;
		$post_title  = $target_post->post_title;

		$prompt = $this->build_prompt($plain_text_content, $post_title, $anchor_text, $target_url);

        $this->logger->log(
            sprintf(
                'Finding insertion locations for suggestion #%d (source=%d, target=%d)',
                (int) $suggestion->id,
                (int) $suggestion->source_post_id,
                (int) $suggestion->target_post_id
            ),
            'info'
        );

		$this->logger->log(
			sprintf(
				'Prompt sent to AI for suggestion #%d: %s',
                (int) $suggestion->id,
                $prompt
            ),
            'debug'
        );

		$ai_result = $this->ai_service->generate_json(
			$prompt,
			array(
				'max_tokens'  => 700,
				'temperature' => 0.2,
			)
		);

        $this->logger->log(
            sprintf(
                'AI service returned result for suggestion #%d: %s',
                (int) $suggestion->id,
                is_wp_error($ai_result) ? 'Error: ' . $ai_result->get_error_message() : print_r($ai_result, true)
            ),
            is_wp_error($ai_result) ? 'error' : 'debug'
        );

		// Fallback path: if structured JSON mode fails, retry with a smaller text
		// generation request and parse it locally.
		if (is_wp_error($ai_result)) {
			$fallback_prompt = $this->build_fallback_prompt($plain_text_content, $post_title, $anchor_text, $target_url);

            $this->logger->log(
                sprintf(
                    'Retrying with fallback prompt for suggestion #%d due to error: %s',
                    (int) $suggestion->id,
                    $ai_result->get_error_message()
                ),
                'warning'
            );

			$raw_text = $this->ai_service->generate_text(
				$fallback_prompt,
				array(
					'max_tokens'  => 500,
					'temperature' => 0.1,
				)
			);

            $this->logger->log(
                sprintf(
                    'Fallback text generation result for suggestion #%d: %s',
                    (int) $suggestion->id,
                    is_wp_error($raw_text) ? 'Error: ' . $raw_text->get_error_message() : print_r($raw_text, true)
                ),
                is_wp_error($raw_text) ? 'error' : 'debug'
            );

			if (!is_wp_error($raw_text)) {
				$ai_result = $this->parse_json_response($raw_text);
			}
		}

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

		if (empty($locations)) {
			return new WP_Error(
				'no_valid_locations',
				__('The AI did not return any valid insertion locations.', 'ai-post-scheduler')
			);
		}

		return array_slice($locations, 0, 3);
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

		$anchor_text = !empty($suggestion->anchor_text) ? $suggestion->anchor_text : $target_post->post_title;
		$target_url  = get_permalink($target_post->ID);
		$link_html   = $this->build_anchor_html($target_url, $anchor_text);
		$replacement_html = $this->build_replacement_html($replacement_snippet, $link_html, $anchor_text);

		if (is_wp_error($replacement_html)) {
			return $replacement_html;
		}

		// Verify the snippet actually exists in the current content.
		// Since the AI worked from plain-text content, we require that the same
		// snippet is also present in the current raw HTML content so we can insert
		// the anchor without flattening or rewriting the post formatting.
		if (strpos($post_content, $match_snippet) === false) {
			return new WP_Error(
				'snippet_not_found',
				__('The selected text was not found in the current post HTML content. Please choose another suggestion.', 'ai-post-scheduler')
			);
		}

		$new_content = implode(
			$replacement_html,
			explode($match_snippet, $post_content, 2)
		);

		$update_args = array(
			'ID'           => $source_post->ID,
			'post_content' => $new_content,
		);

		$result = wp_update_post($update_args, true);

		if (is_wp_error($result)) {
			return $result;
		}

		// Mark the suggestion as inserted.
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
		// 1. Strip Gutenberg block comments and HTML tags.
		$text = wp_strip_all_tags($post_content);

		// 2. Decode HTML entities so the AI sees human-readable text.
		$text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');

		// 3. Remove non-printable control characters except tab (\x09),
		//    LF (\x0A), and CR (\x0D) which are legitimate whitespace.
		$text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $text);

		// 4. Normalize line endings and collapse runs of blank lines.
		$text = str_replace("\r\n", "\n", $text);
		$text = preg_replace('/\n{3,}/', "\n\n", $text);

		return trim($text);
	}

	/**
	 * Parse and clean a raw AI text response as a JSON array.
	 *
	 * Strips markdown code fences, removes unescaped control characters inside
	 * JSON string values, then runs json_decode.
	 *
	 * @param string $raw_text Raw text response from the AI.
	 * @return array|WP_Error Parsed array or WP_Error on failure.
	 */
	private function parse_json_response($raw_text) {
		$json = trim($raw_text);

		// Strip optional markdown code fences.
		$json = preg_replace('/^```(?:json)?\s*/im', '', $json);
		$json = preg_replace('/```\s*$/im', '', $json);
		$json = trim($json);

		// Isolate the JSON array in case the AI prefixed it with prose.
		if (preg_match('/\[.*\]/s', $json, $matches)) {
			$json = $matches[0];
		}

		// Remove unescaped control characters that appear inside JSON string
		// values (the primary cause of "Control character error" parse failures).
		// Strategy: replace literal control chars (U+0000–U+001F, U+007F) with
		// their JSON escape sequences, but only when they appear inside a
		// JSON string (between double-quote delimiters). We use a character-by-
		// character walk via preg_replace_callback on every "..." token.
		$json = preg_replace_callback(
			'/"((?:[^"\\\\]|\\\\.)*)"/',
			function ($m) {
				$inner = $m[1];
				// Replace raw LF / CR / TAB with their JSON escape equivalents.
				$inner = str_replace("\n", '\n', $inner);
				$inner = str_replace("\r", '\r', $inner);
				$inner = str_replace("\t", '\t', $inner);
				// Strip remaining non-printable control characters.
				$inner = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $inner);
				return '"' . $inner . '"';
			},
			$json
		);

		$data = json_decode($json, true);

		if (json_last_error() !== JSON_ERROR_NONE) {
			return new WP_Error(
				'json_parse_error',
				sprintf(
					/* translators: %s JSON error message */
					__('Failed to parse AI response as JSON: %s', 'ai-post-scheduler'),
					json_last_error_msg()
				)
			);
		}

		if (!is_array($data)) {
			return new WP_Error(
				'unexpected_format',
				__('AI response was not a JSON array.', 'ai-post-scheduler')
			);
		}

		return $data;
	}

	/**
	 * Truncate prompt content to keep the AI request and response bounded.
	 *
	 * Prefers a sentence boundary near the limit when possible.
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
		$boundary  = max(
			strrpos($truncated, '. '),
			strrpos($truncated, "\n")
		);

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
	 * @return string Prompt string.
	 */
	private function build_prompt($plain_text_content, $post_title, $anchor_text, $target_url) {
		return sprintf(
			/* translators: internal use only — AI prompt, not shown to end users */
			"Analyze this plain-text post and identify up to 3 best link insertion locations for the post title \"%s\" using exact anchor text \"%s\" and URL \"%s\". Return ONLY a JSON array. Each object must have exactly these keys: reason, match_snippet, replacement_snippet. Keep reason concise, maximum 12 words. match_snippet must be a unique exact excerpt from the post, ideally 10 to 18 words. replacement_snippet must be the same plain-text excerpt, but wrap the exact link words in double square brackets like [[%s]]. Do NOT output HTML, markdown, code fences, comments, or any extra text. If fewer than 3 confident locations exist, return fewer objects.\n\nPlain-text post content:\n%s",
			$post_title,
			$anchor_text,
			$target_url,
			$anchor_text,
			$plain_text_content
		);
	}

	/**
	 * Build a shorter fallback prompt for text-based generation when structured
	 * JSON generation is unavailable or fails.
	 *
	 * @param string $plain_text_content Normalized plain-text post content.
	 * @param string $post_title         Target post title.
	 * @param string $anchor_text        Suggested anchor text.
	 * @param string $target_url         Target permalink.
	 * @return string
	 */
	private function build_fallback_prompt($plain_text_content, $post_title, $anchor_text, $target_url) {
		return sprintf(
			"Return ONLY valid JSON. No markdown. No prose. Find up to 3 insertion points for post \"%s\" using anchor \"%s\" and URL \"%s\". Output an array of objects with keys reason, match_snippet, replacement_snippet. Keep reason under 8 words. Use exact excerpt text. In replacement_snippet, mark only the link words with [[%s]].\n\nText:\n%s",
			$post_title,
			$anchor_text,
			$target_url,
			$anchor_text,
			$plain_text_content
		);
	}

	/**
	 * Validate and normalize the raw AI response array.
	 *
	 * Filters out entries that are missing required fields and normalises
	 * field names to the expected snake_case keys.
	 *
	 * @param mixed $raw Raw value from generate_json().
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

			// Accept both camelCase and snake_case keys that AI might return.
			$match_snippet       = isset($item['match_snippet'])       ? $item['match_snippet']       : (isset($item['matchSnippet'])       ? $item['matchSnippet']       : '');
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
				'reason'               => isset($item['reason']) ? sanitize_text_field($item['reason']) : '',
				'match_snippet'        => $match_snippet,
				'replacement_snippet'  => $replacement_snippet,
			);
		}

		return $locations;
	}

	/**
	 * Extract the marked anchor text from a plain-text replacement snippet.
	 *
	 * @param string $replacement_snippet Replacement snippet with [[marker]].
	 * @return string|false Marked text, or false if the marker contract is invalid.
	 */
	private function extract_marked_anchor_text($replacement_snippet) {
		if (!preg_match_all(self::LINK_MARKER_PATTERN, $replacement_snippet, $matches) || count($matches[1]) !== 1) {
			return false;
		}

		$marked_text = trim($matches[1][0]);

		return $marked_text === '' ? false : $marked_text;
	}

	/**
	 * Build the final anchor HTML for the insertion.
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
	 * Convert a plain-text replacement snippet into the final HTML replacement.
	 *
	 * @param string $replacement_snippet AI-provided plain-text replacement.
	 * @param string $link_html           Final anchor HTML.
	 * @param string $expected_anchor     Expected anchor text from the suggestion.
	 * @return string|WP_Error HTML replacement string or WP_Error.
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
