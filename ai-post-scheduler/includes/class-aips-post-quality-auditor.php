<?php
/**
 * Deterministic post quality auditor.
 *
 * @package AI_Post_Scheduler
 * @since 2.6.0
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Post_Quality_Auditor
 */
class AIPS_Post_Quality_Auditor {

	/**
	 * Audit generated post quality using deterministic heuristics.
	 *
	 * @param array $data Audit input.
	 * @return array
	 */
	public function audit($data) {
		$title = isset($data['title']) ? trim(wp_strip_all_tags((string) $data['title'])) : '';
		$content = isset($data['content']) ? trim(wp_strip_all_tags((string) $data['content'])) : '';
		$excerpt = isset($data['excerpt']) ? trim(wp_strip_all_tags((string) $data['excerpt'])) : '';
		$focus_keyword = isset($data['focus_keyword']) ? trim((string) $data['focus_keyword']) : '';
		$meta_description = isset($data['meta_description']) ? trim(wp_strip_all_tags((string) $data['meta_description'])) : '';
		$component_statuses = isset($data['component_statuses']) && is_array($data['component_statuses']) ? $data['component_statuses'] : array();
		$image_attempted = !empty($data['image_attempted']);
		$generation_incomplete = !empty($data['generation_incomplete']);

		$score = 100;
		$flags = array();
		$critical_flags = array();

		if ($title === '') {
			$score -= 40;
			$flags[] = 'missing_title';
			$critical_flags[] = 'missing_title';
		} elseif (strlen($title) < 20) {
			$score -= 10;
			$flags[] = 'weak_title';
		}

		if ($content === '') {
			$score -= 40;
			$flags[] = 'missing_content';
			$critical_flags[] = 'missing_content';
		} elseif (str_word_count($content) < 250) {
			$score -= 15;
			$flags[] = 'thin_content';
		}

		if ($excerpt === '') {
			$score -= 10;
			$flags[] = 'missing_excerpt';
		}

		if ($image_attempted && array_key_exists('featured_image', $component_statuses) && empty($component_statuses['featured_image'])) {
			$score -= 10;
			$flags[] = 'missing_featured_image';
		}

		if ($generation_incomplete || in_array(false, $component_statuses, true)) {
			$score -= 20;
			$flags[] = 'partial_generation';
			$critical_flags[] = 'partial_generation';
		}

		if ($title !== '' && preg_match('/\(\d+\)$/', $title)) {
			$score -= 5;
			$flags[] = 'duplicate_title_suffix';
		}

		if ($focus_keyword === '') {
			$score -= 5;
			$flags[] = 'missing_focus_keyword';
		}

		if ($meta_description === '') {
			$score -= 5;
			$flags[] = 'missing_meta_description';
		}

		$flags = array_values(array_unique($flags));
		$critical_flags = array_values(array_unique($critical_flags));

		return array(
			'score' => max(0, (int) $score),
			'flags' => $flags,
			'critical_flags' => $critical_flags,
			'has_critical_flags' => !empty($critical_flags),
		);
	}
}
