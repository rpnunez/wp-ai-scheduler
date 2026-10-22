<?php
/**
 * Normalizes and sanitizes generated content and excerpts.
 *
 * @package AI_Post_Scheduler
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Content_Normalizer
 *
 * Pure transform helper responsible for converting Markdown to HTML (if parser provided),
 * running WordPress kses sanitization, stripping redundant prepended titles, and safely
 * truncating excerpts at word boundaries.
 */
class AIPS_Content_Normalizer {

	/**
	 * Default upper bound for stored excerpts in characters.
	 */
	const MAX_STORED_EXCERPT_CHARS = 1000;

	/**
	 * Normalize generated content so post bodies are consistently stored as HTML.
	 *
	 * @param string      $content         Raw generated content.
	 * @param object|null $markdown_parser Optional markdown parser instance.
	 * @return string Sanitized HTML content.
	 */
	public function normalize_content($content, $markdown_parser = null) {
		if (!is_string($content)) {
			return '';
		}

		$normalized_content = trim($content);

		if ($normalized_content === '') {
			return '';
		}

		if ($markdown_parser && method_exists($markdown_parser, 'is_markdown') && method_exists($markdown_parser, 'contains_html') && method_exists($markdown_parser, 'parse')) {
			if ($markdown_parser->is_markdown($normalized_content) && !$markdown_parser->contains_html($normalized_content)) {
				$normalized_content = $markdown_parser->parse($normalized_content);
			}
		}

		return wp_kses_post($normalized_content);
	}

	/**
	 * Remove a prepended title block from generated content when present.
	 *
	 * Some models emit an article title at the start of content (for example as
	 * <h1>Title</h1> or Markdown "# Title"). WordPress already renders the post
	 * title separately, so keeping this heading creates a duplicated "second title".
	 *
	 * @param string $content Generated content.
	 * @return string Content without a leading title block.
	 */
	public function strip_leading_title_block($content) {
		if (!is_string($content)) {
			return '';
		}

		$cleaned = ltrim($content);

		// Remove a leading HTML <h1>...</h1> title block.
		$cleaned = preg_replace('/^<h1\b[^>]*>[\s\S]*?<\/h1>\s*/i', '', $cleaned, 1);

		// Remove a leading Markdown "# Title" block when Markdown slipped through.
		$cleaned = preg_replace('/^#\\s+[^\\n]+\\s*/u', '', $cleaned, 1);

		return ltrim((string) $cleaned);
	}

	/**
	 * Safety net for excerpt length: truncate to character limit on a clean word boundary.
	 *
	 * Uses mb_substr because a byte-wise cut can land mid-character and produce
	 * invalid UTF-8, and backs off to the last word boundary so the excerpt does
	 * not end mid-word.
	 *
	 * @param string $excerpt Excerpt text.
	 * @param int    $limit   Maximum characters. Default 1000.
	 * @return string
	 */
	public function truncate_excerpt($excerpt, $limit = self::MAX_STORED_EXCERPT_CHARS) {
		$excerpt = (string) $excerpt;
		$limit   = max(1, (int) $limit);

		if (mb_strlen($excerpt) <= $limit) {
			return $excerpt;
		}

		$truncated  = mb_substr($excerpt, 0, $limit);
		$last_space = mb_strrpos($truncated, ' ');

		// Only back off to a word boundary when doing so keeps most of the text;
		// a very long single token should still be cut rather than emptied.
		if ($last_space !== false && $last_space >= (int) ($limit * 0.6)) {
			$truncated = mb_substr($truncated, 0, $last_space);
		}

		return rtrim($truncated);
	}

	/**
	 * Clean and normalize an excerpt string (trim quotes and whitespace, then truncate).
	 *
	 * @param string $excerpt Raw excerpt string.
	 * @param int    $limit   Maximum characters. Default 1000.
	 * @return string
	 */
	public function normalize_excerpt($excerpt, $limit = self::MAX_STORED_EXCERPT_CHARS) {
		$cleaned = trim((string) $excerpt);
		$cleaned = preg_replace('/^["\']|["\']$/', '', $cleaned);
		return $this->truncate_excerpt($cleaned, $limit);
	}
}
