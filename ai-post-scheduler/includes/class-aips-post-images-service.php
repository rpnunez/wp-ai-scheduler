<?php
/**
 * Post Images Service
 *
 * Validates post image configuration submitted from the Template and Author
 * forms before it reaches the repository, and keeps generated image rows in
 * step with the post content they describe.
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

	/**
	 * Build the pattern that finds a generated image's figure in post content.
	 *
	 * The lookahead ends the ID match, so row 12 cannot match a figure for row 123.
	 *
	 * @param int $generated_id Generated image row ID.
	 * @return string Regular expression.
	 */
	public static function get_figure_pattern($generated_id) {
		return '/<figure\b[^>]*\bdata-aips-image-id=["\']?' . absint($generated_id) . '(?=["\'\s>])[^>]*>.*?<\/figure>/is';
	}

	/**
	 * Update generated image rows to match saved post content.
	 *
	 * The content decides which image a row points to, so a regeneration only
	 * takes effect once its figure is saved. The row keeps its recorded source
	 * and prompt, which the figure does not carry.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $content Saved post content.
	 * @return void
	 */
	public function sync_generated_rows_from_content($post_id, $content) {
		$generated_repo = new AIPS_Post_Images_Generated_Repository();

		foreach ($generated_repo->get_by_post_id($post_id) as $row) {
			if (!preg_match(self::get_figure_pattern($row->id), $content, $figure)
				|| !preg_match('/\bwp-image-(\d+)\b/', $figure[0], $attachment)) {
				continue;
			}

			$attachment_id = (int) $attachment[1];
			if ($attachment_id === (int) $row->attachment_id && 'completed' === $row->status) {
				continue;
			}

			$image_url = wp_get_attachment_url($attachment_id);
			if (!$image_url) {
				continue;
			}

			$generated_repo->update($row->id, array(
				'attachment_id' => $attachment_id,
				'image_url'     => $image_url,
				'status'        => 'completed',
				'error_message' => '',
			));
		}
	}
}
