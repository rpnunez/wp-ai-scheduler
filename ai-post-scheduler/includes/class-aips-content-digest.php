<?php
/**
 * Bounded article context for stateless follow-up prompts.
 *
 * @package AI_Post_Scheduler
 * @since 3.4.2
 */

if (!defined('ABSPATH')) {
	exit;
}

class AIPS_Content_Digest {

	const DEFAULT_MAX_CHARS = 12000;

	/**
	 * Build a deterministic, bounded representation of an article.
	 *
	 * Short articles are returned unchanged for backward compatibility. Long
	 * articles retain their beginning, heading outline, and conclusion so title
	 * and excerpt requests are not biased toward the introduction alone.
	 *
	 * @param string $content   Article content.
	 * @param int    $max_chars Maximum digest size.
	 * @return string
	 */
	public function build($content, $max_chars = self::DEFAULT_MAX_CHARS) {
		$content = trim((string) $content);
		$max_chars = max(1000, (int) $max_chars);

		if (mb_strlen($content) <= $max_chars) {
			return $content;
		}

		$plain_text = trim(preg_replace('/\s+/u', ' ', wp_strip_all_tags($content)));
		if (mb_strlen($plain_text) <= $max_chars) {
			return $plain_text;
		}

		$headings = $this->extract_headings($content);
		$outline = empty($headings) ? '' : "ARTICLE OUTLINE:\n- " . implode("\n- ", $headings) . "\n\n";

		$max_outline_chars = (int) floor($max_chars * 0.4);
		if (mb_strlen($outline) > $max_outline_chars) {
			$outline = mb_substr($outline, 0, $max_outline_chars) . "...\n\n";
		}

		$available = max(200, $max_chars - mb_strlen($outline) - 80);
		if (mb_strlen($plain_text) <= $available) {
			return $plain_text;
		}

		$head_length = (int) floor($available * 0.6);
		$tail_length = $available - $head_length;

		return "ARTICLE BEGINNING:\n"
			. mb_substr($plain_text, 0, $head_length)
			. "\n\n"
			. $outline
			. "ARTICLE CONCLUSION:\n"
			. mb_substr($plain_text, -$tail_length);
	}

	/**
	 * Extract a compact, deduplicated heading outline.
	 *
	 * @param string $content Article HTML.
	 * @return string[]
	 */
	private function extract_headings($content) {
		preg_match_all('/<h[2-6][^>]*>(.*?)<\/h[2-6]>/is', $content, $matches);
		$headings = array();

		foreach (isset($matches[1]) ? $matches[1] : array() as $heading) {
			$heading = trim(preg_replace('/\s+/u', ' ', html_entity_decode(wp_strip_all_tags($heading), ENT_QUOTES, 'UTF-8')));
			if ($heading !== '' && !in_array($heading, $headings, true)) {
				$headings[] = mb_substr($heading, 0, 200);
			}
			if (count($headings) >= 20) {
				break;
			}
		}

		return $headings;
	}
}
