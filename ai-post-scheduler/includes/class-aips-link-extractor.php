<?php
/**
 * Link Extractor
 *
 * Finds the <a href> links rendered in a post's HTML content. Links inside
 * HTML comments (including Gutenberg block delimiter JSON), <script>,
 * <style> and <template> are ignored, as are non-navigational hrefs such as
 * fragments, mailto:, tel: and javascript:.
 *
 * @package AI_Post_Scheduler
 * @since 3.7.4
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Link_Extractor
 *
 * Pure HTML parsing: no URL classification or database access. Pair it with
 * AIPS_Link_Url_Resolver to decide which links are internal.
 */
class AIPS_Link_Extractor {

	/**
	 * Attribute that marks an anchor as inserted by AI Post Scheduler.
	 */
	const AIPS_MARKER_ATTRIBUTE = 'data-aips-link';

	/**
	 * href schemes that never point at a page.
	 *
	 * @var string[]
	 */
	private static $skipped_schemes = array('mailto:', 'tel:', 'sms:', 'javascript:', 'data:', 'callto:', 'fax:');

	/**
	 * Extract links from HTML content.
	 *
	 * Each returned link is an array with keys:
	 * - href             string Decoded href value, trimmed.
	 * - anchor_text      string Visible text of the anchor (tags stripped, whitespace collapsed).
	 * - rel              string Lower-cased rel attribute.
	 * - is_nofollow      bool   Whether rel contains nofollow.
	 * - inserted_by_aips bool   Whether the anchor carries the data-aips-link marker.
	 * - position         int    Zero-based order of the link in the content.
	 *
	 * @param string $html Post content.
	 * @return array[]
	 */
	public function extract(string $html): array {
		if ($html === '' || stripos($html, '<a') === false) {
			return array();
		}

		$html = $this->strip_ignored_regions($html);

		// Opening <a> tag whose attributes may contain quoted ">" characters,
		// followed by its content up to the closing tag.
		$pattern = '/<a\b((?:[^>"\']|"[^"]*"|\'[^\']*\')*)>(.*?)<\/a\s*>/is';

		if (!preg_match_all($pattern, $html, $matches, PREG_SET_ORDER)) {
			return array();
		}

		$links    = array();
		$position = 0;

		foreach ($matches as $match) {
			$attributes = $this->parse_attributes($match[1]);

			if (!isset($attributes['href'])) {
				continue;
			}

			$href = trim(html_entity_decode($attributes['href'], ENT_QUOTES | ENT_HTML5, 'UTF-8'));
			if (!$this->is_navigational_href($href)) {
				continue;
			}

			$rel = isset($attributes['rel']) ? strtolower(trim(preg_replace('/\s+/', ' ', $attributes['rel']))) : '';

			$links[] = array(
				'href'             => $href,
				'anchor_text'      => $this->anchor_text($match[2]),
				'rel'              => $rel,
				'is_nofollow'      => in_array('nofollow', explode(' ', $rel), true),
				'inserted_by_aips' => array_key_exists(self::AIPS_MARKER_ATTRIBUTE, $attributes),
				'position'         => $position++,
			);
		}

		return $links;
	}

	/**
	 * Remove regions whose markup must never be treated as links.
	 *
	 * @param string $html Raw HTML.
	 * @return string
	 */
	private function strip_ignored_regions(string $html): string {
		$html = preg_replace('/<!--.*?-->/s', ' ', $html);
		$html = preg_replace('/<(script|style|template)\b[^>]*>.*?<\/\1\s*>/is', ' ', (string) $html);

		return (string) $html;
	}

	/**
	 * Parse an HTML attribute string into a lower-cased name => raw value map.
	 *
	 * Handles double-quoted, single-quoted, unquoted and value-less
	 * attributes. The first occurrence of a repeated attribute wins, as in
	 * browsers.
	 *
	 * @param string $attribute_string Everything between "<a" and ">".
	 * @return array<string, string>
	 */
	private function parse_attributes(string $attribute_string): array {
		$attributes = array();
		$pattern    = '/([^\s"\'>\/=]+)(?:\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s"\'=<>`]+)))?/';

		if (!preg_match_all($pattern, $attribute_string, $matches, PREG_SET_ORDER)) {
			return $attributes;
		}

		foreach ($matches as $match) {
			$name = strtolower($match[1]);
			if (isset($attributes[$name])) {
				continue;
			}

			$value = '';
			foreach (array(2, 3, 4) as $group) {
				if (isset($match[$group]) && $match[$group] !== '') {
					$value = $match[$group];
					break;
				}
			}

			$attributes[$name] = $value;
		}

		return $attributes;
	}

	/**
	 * Whether an href points at a page (as opposed to a fragment or a scheme
	 * such as mailto:).
	 *
	 * @param string $href Decoded href.
	 * @return bool
	 */
	private function is_navigational_href(string $href): bool {
		if ($href === '' || $href[0] === '#' || $href[0] === '?') {
			return false;
		}

		$lower = strtolower(preg_replace('/\s+/', '', $href));
		foreach (self::$skipped_schemes as $scheme) {
			if (strpos($lower, $scheme) === 0) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Visible anchor text: tags stripped, entities decoded, whitespace collapsed.
	 *
	 * @param string $inner_html Anchor inner HTML.
	 * @return string
	 */
	private function anchor_text(string $inner_html): string {
		$text = wp_strip_all_tags($inner_html);
		$text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
		$text = preg_replace('/[\s\x{00A0}]+/u', ' ', $text);

		return trim((string) $text);
	}
}
