<?php
/**
 * Post Images Service
 *
 * Validates post image configuration submitted from the Template and Author
 * forms before it reaches the repository.
 *
 * @package AI_Post_Scheduler
 * @since 3.7.21
 */

if (!defined('ABSPATH')) {
	exit;
}

class AIPS_Post_Images_Service {

	/**
	 * Clean raw submitted image rows and apply the configured limit.
	 *
	 * @param mixed $raw_rows Raw, slashed rows from $_POST.
	 * @return array Sanitized rows numbered from 1 in submitted order.
	 */
	public function normalize_submitted_rows($raw_rows) {
		if (!is_array($raw_rows)) {
			return array();
		}

		$rows = array_filter(wp_unslash($raw_rows), 'is_array');
		$rows = array_slice(array_values($rows), 0, AIPS_Config::get_instance()->get_max_post_images_limit());

		$normalized = array();
		foreach ($rows as $index => $row) {
			$placement = isset($row['placement']) ? sanitize_key($row['placement']) : 'auto';

			$normalized[] = array(
				'id'                => isset($row['id']) ? absint($row['id']) : 0,
				'image_order'       => $index + 1,
				'source'            => $this->normalize_source(isset($row['source']) ? $row['source'] : ''),
				'image_prompt'      => isset($row['image_prompt']) ? wp_kses_post($row['image_prompt']) : '',
				'unsplash_keywords' => isset($row['unsplash_keywords']) ? sanitize_textarea_field($row['unsplash_keywords']) : '',
				'media_ids'         => isset($row['media_ids']) ? sanitize_text_field($row['media_ids']) : '',
				'placement'         => preg_match('/^after_section_[1-5]$/', $placement) ? $placement : 'auto',
				'caption'           => isset($row['caption']) ? sanitize_text_field($row['caption']) : '',
			);
		}

		return $normalized;
	}

	/**
	 * Restrict an image source to those enabled in Settings.
	 *
	 * @param string $source Requested source.
	 * @return string The source when allowed, otherwise the first allowed source.
	 */
	public function normalize_source($source) {
		$source = sanitize_key((string) $source);
		$allowed = AIPS_Config::get_instance()->get_allowed_image_sources();

		if (in_array($source, $allowed, true)) {
			return $source;
		}

		return !empty($allowed) ? reset($allowed) : 'ai_prompt';
	}
}
