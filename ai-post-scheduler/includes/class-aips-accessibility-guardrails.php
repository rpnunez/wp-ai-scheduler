<?php
if (!defined('ABSPATH')) {
	exit;
}

/**
 * Accessibility guardrails checks for generated post content.
 */
class AIPS_Accessibility_Guardrails {

	/**
	 * Analyze generated HTML content for accessibility/readability risks.
	 *
	 * @param string $content Generated post content.
	 * @return array<string,mixed>
	 */
	public function analyze($content) {
		$content = is_string($content) ? $content : '';
		$results = array(
			'heading_hierarchy_ok' => true,
			'multiple_h1_count' => 0,
			'missing_alt_images' => 0,
			'long_paragraphs' => 0,
			'long_sentences' => 0,
			'non_descriptive_links' => 0,
			'invalid_links' => 0,
			'excessive_line_breaks' => 0,
			'plain_language_score' => 100,
			'plain_language_target' => 60,
			'warnings' => array(),
		);

		if ($content === '') {
			$results['warnings'][] = __('Generated content is empty; accessibility checks skipped.', 'ai-post-scheduler');
			$results['heading_hierarchy_ok'] = false;
			$results['plain_language_score'] = 0;
			return $results;
		}

		$results = $this->check_heading_hierarchy($content, $results);
		$results = $this->check_heading_h1_usage($content, $results);
		$results = $this->check_image_alt_text($content, $results);
		$results = $this->check_paragraph_readability($content, $results);
		$results = $this->check_sentence_readability($content, $results);
		$results = $this->check_link_text($content, $results);
		$results = $this->check_link_href($content, $results);
		$results = $this->check_excessive_line_breaks($content, $results);
		$results = $this->check_plain_language_score($content, $results);

		return $results;
	}

	private function check_heading_hierarchy($content, $results) {
		preg_match_all('/<h([1-6])\b[^>]*>/i', $content, $matches);
		$levels = array_map('intval', isset($matches[1]) ? $matches[1] : array());

		$previous = 0;
		foreach ($levels as $level) {
			if ($previous > 0 && ($level - $previous) > 1) {
				$results['heading_hierarchy_ok'] = false;
				$results['warnings'][] = __('Heading hierarchy skips levels (for example H2 to H4).', 'ai-post-scheduler');
				break;
			}
			$previous = $level;
		}

		return $results;
	}

	private function check_heading_h1_usage($content, $results) {
		preg_match_all('/<h1\b[^>]*>/i', $content, $matches);
		$count = isset($matches[0]) ? count($matches[0]) : 0;
		$results['multiple_h1_count'] = $count;

		if ($count > 1) {
			$results['warnings'][] = sprintf(
				/* translators: %d: number of H1 headings. */
				__('Multiple H1 headings detected (%d). Prefer a single H1 per post.', 'ai-post-scheduler'),
				$count
			);
		}

		return $results;
	}

	private function check_image_alt_text($content, $results) {
		preg_match_all('/<img\b[^>]*>/i', $content, $matches);
		$images = isset($matches[0]) ? $matches[0] : array();
		$missing_alt = 0;

		foreach ($images as $image_tag) {
			if (!preg_match('/\balt\s*=\s*(["\"]).*?\1/i', $image_tag)) {
				$missing_alt++;
			}
		}

		$results['missing_alt_images'] = $missing_alt;
		if ($missing_alt > 0) {
			$results['warnings'][] = sprintf(
				/* translators: %d: number of images missing alt text. */
				__('Add descriptive alt text for %d image(s).', 'ai-post-scheduler'),
				$missing_alt
			);
		}

		return $results;
	}

	private function check_paragraph_readability($content, $results) {
		preg_match_all('/<p\b[^>]*>(.*?)<\/p>/is', $content, $matches);
		$paragraphs = isset($matches[1]) ? $matches[1] : array();
		$long_paragraphs = 0;

		foreach ($paragraphs as $paragraph) {
			$text = trim(wp_strip_all_tags($paragraph));
			if ($text === '') {
				continue;
			}

			$word_count = str_word_count($text);
			if ($word_count > 120) {
				$long_paragraphs++;
			}
		}

		$results['long_paragraphs'] = $long_paragraphs;
		if ($long_paragraphs > 0) {
			$results['warnings'][] = sprintf(
				/* translators: %d: number of long paragraphs. */
				__('Shorten %d long paragraph(s) to improve readability.', 'ai-post-scheduler'),
				$long_paragraphs
			);
		}

		return $results;
	}

	private function check_sentence_readability($content, $results) {
		$text = trim(wp_strip_all_tags($content));
		if ($text === '') {
			return $results;
		}

		$sentences = preg_split('/[.!?]+/', $text);
		$sentences = array_values(array_filter(array_map('trim', $sentences)));

		$long_sentences = 0;
		foreach ($sentences as $sentence) {
			$words = preg_split('/\s+/', $sentence);
			$words = array_values(array_filter($words));
			if (count($words) > 30) {
				$long_sentences++;
			}
		}

		$results['long_sentences'] = $long_sentences;
		if ($long_sentences > 0) {
			$results['warnings'][] = sprintf(
				/* translators: %d: number of long sentences. */
				__('Shorten %d long sentence(s) to improve readability.', 'ai-post-scheduler'),
				$long_sentences
			);
		}

		return $results;
	}

	private function check_link_text($content, $results) {
		preg_match_all('/<a\b[^>]*>(.*?)<\/a>/is', $content, $matches);
		$anchors = isset($matches[1]) ? $matches[1] : array();
		$bad = 0;

		foreach ($anchors as $anchor_text) {
			$text = strtolower(trim(wp_strip_all_tags($anchor_text)));
			if ($text === '') {
				$bad++;
				continue;
			}

			if (preg_match('/^(click here|here|learn more|read more|more)$/i', $text)) {
				$bad++;
				continue;
			}
		}

		$results['non_descriptive_links'] = $bad;
		if ($bad > 0) {
			$results['warnings'][] = sprintf(
				/* translators: %d: number of links with non-descriptive text. */
				__('Update %d link(s) with more descriptive text (avoid "click here").', 'ai-post-scheduler'),
				$bad
			);
		}

		return $results;
	}

	private function check_link_href($content, $results) {
		preg_match_all('/<a\b[^>]*>/i', $content, $matches);
		$tags = isset($matches[0]) ? $matches[0] : array();
		$invalid = 0;

		foreach ($tags as $tag) {
			if (!preg_match('/\bhref\s*=\s*(["\'])(.*?)\1/i', $tag, $href_matches)) {
				$invalid++;
				continue;
			}

			$href = trim((string) $href_matches[2]);
			if ($href === '' || $href === '#' || stripos($href, 'javascript:') === 0) {
				$invalid++;
				continue;
			}
		}

		$results['invalid_links'] = $invalid;
		if ($invalid > 0) {
			$results['warnings'][] = sprintf(
				/* translators: %d: number of invalid links. */
				__('Fix %d invalid link(s) (missing or placeholder href).', 'ai-post-scheduler'),
				$invalid
			);
		}

		return $results;
	}

	private function check_excessive_line_breaks($content, $results) {
		$count = 0;

		// Count occurrences of 3+ consecutive <br> tags.
		if (preg_match_all('/(?:<br\s*\/?>\s*){3,}/i', $content, $matches)) {
			$count = isset($matches[0]) ? count($matches[0]) : 0;
		}

		$results['excessive_line_breaks'] = $count;
		if ($count > 0) {
			$results['warnings'][] = sprintf(
				/* translators: %d: number of excessive line break sequences. */
				__('Replace %d excessive line-break block(s) with semantic paragraphs or lists.', 'ai-post-scheduler'),
				$count
			);
		}

		return $results;
	}

	private function check_plain_language_score($content, $results) {
		$text = trim(wp_strip_all_tags($content));
		if ($text === '') {
			$results['plain_language_score'] = 0;
			return $results;
		}

		$sentences = preg_split('/[.!?]+/', $text);
		$sentences = array_values(array_filter(array_map('trim', $sentences)));
		$sentence_count = max(count($sentences), 1);
		$words = preg_split('/\s+/', $text);
		$words = array_values(array_filter($words));
		$word_count = max(count($words), 1);
		$long_word_count = 0;

		foreach ($words as $word) {
			$word = preg_replace('/[^\p{L}\p{N}]/u', '', $word);
			if (mb_strlen((string) $word) >= 7) {
				$long_word_count++;
			}
		}

		$avg_sentence_length = $word_count / $sentence_count;
		$long_word_ratio = $long_word_count / $word_count;
		$score = 100 - (int) round(($avg_sentence_length * 1.2) + ($long_word_ratio * 80));
		$score = max(0, min(100, $score));
		$results['plain_language_score'] = $score;

		if ($score < (int) $results['plain_language_target']) {
			$results['warnings'][] = sprintf(
				/* translators: 1: plain language score, 2: target score. */
				__('Plain-language score is %1$d; target is %2$d or higher.', 'ai-post-scheduler'),
				$score,
				(int) $results['plain_language_target']
			);
		}

		return $results;
	}
}
