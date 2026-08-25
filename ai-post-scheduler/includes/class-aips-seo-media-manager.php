<?php
/**
 * SEO Media Manager
 *
 * Optimizes WordPress media library attachments and post images.
 * Generates descriptive Alt text, clean titles, captions, and descriptions
 * using multimodal vision AI or contextual text generation.
 *
 * @package AI_Post_Scheduler
 * @since 2.10.0
 */

if (!defined('ABSPATH')) {
	exit;
}

class AIPS_SEO_Media_Manager {

	/**
	 * @var AIPS_AI_Service_Interface
	 */
	private $ai_service;

	/**
	 * @var AIPS_Logger
	 */
	private $logger;

	/**
	 * @param AIPS_AI_Service_Interface|null $ai_service Optional AI service.
	 * @param AIPS_Logger|null               $logger     Optional logger.
	 */
	public function __construct($ai_service = null, $logger = null) {
		$container = AIPS_Container::get_instance();
		$this->ai_service = $ai_service ?: ($container->has(AIPS_AI_Service_Interface::class) ? $container->make(AIPS_AI_Service_Interface::class) : new AIPS_AI_Service());
		$this->logger     = $logger ?: new AIPS_Logger();
	}

	/**
	 * Optimize a single media attachment.
	 *
	 * @param int   $attachment_id Attachment ID.
	 * @param array $options       Options {
	 *     @type string $mode             'vision' or 'text'.
	 *     @type string $focus_keyword    Primary focus keyword to align with.
	 *     @type string $post_title       Associated post title.
	 *     @type string $custom_prompt    Custom prompt instructions.
	 *     @type array  $fields           Subset of fields to write: ['alt', 'title', 'caption', 'description'].
	 * }
	 * @return array {
	 *     @type bool   $success
	 *     @type array  $data Generated values.
	 *     @type string $error
	 * }
	 */
	public function optimize_attachment($attachment_id, array $options = array()) {
		$attachment_id = absint($attachment_id);
		$attachment = get_post($attachment_id);

		if (!$attachment || $attachment->post_type !== 'attachment') {
			return array(
				'success' => false,
				'data'    => array(),
				'error'   => __('Attachment not found.', 'ai-post-scheduler'),
			);
		}

		if (!wp_attachment_is_image($attachment_id)) {
			return array(
				'success' => false,
				'data'    => array(),
				'error'   => __('Specified attachment is not an image.', 'ai-post-scheduler'),
			);
		}

		$mode          = isset($options['mode']) && $options['mode'] === 'vision' ? 'vision' : 'text';
		$focus_keyword = isset($options['focus_keyword']) ? sanitize_text_field($options['focus_keyword']) : '';
		$post_title    = isset($options['post_title']) ? sanitize_text_field($options['post_title']) : '';
		$custom_prompt = isset($options['custom_prompt']) ? sanitize_text_field($options['custom_prompt']) : '';
		$target_fields = !empty($options['fields']) && is_array($options['fields']) ? $options['fields'] : array('alt', 'title', 'caption', 'description');

		$image_url = wp_get_attachment_url($attachment_id);
		$filename  = basename(get_attached_file($attachment_id) ?: '');

		// Build prompt
		$prompt = $this->build_image_seo_prompt($filename, $post_title, $focus_keyword, $custom_prompt, $target_fields);

		// Call AI
		$response = $this->ai_service->generate_json($prompt);

		if (is_wp_error($response) || !is_array($response)) {
			$err = is_wp_error($response) ? $response->get_error_message() : __('Failed to generate image SEO from AI.', 'ai-post-scheduler');
			$this->logger->log(sprintf('Media SEO failed for attachment %d: %s', $attachment_id, $err), 'warning');
			return array(
				'success' => false,
				'data'    => array(),
				'error'   => $err,
			);
		}

		$generated = array();

		// 1. Alt Text
		if (in_array('alt', $target_fields, true) && !empty($response['alt_text'])) {
			$alt_text = sanitize_text_field($response['alt_text']);
			update_post_meta($attachment_id, '_wp_attachment_image_alt', $alt_text);
			$generated['alt_text'] = $alt_text;
		}

		$post_update = array('ID' => $attachment_id);
		$needs_update = false;

		// 2. Image Title
		if (in_array('title', $target_fields, true) && !empty($response['title'])) {
			$post_update['post_title'] = sanitize_text_field($response['title']);
			$generated['title']        = $post_update['post_title'];
			$needs_update = true;
		}

		// 3. Caption (Excerpt)
		if (in_array('caption', $target_fields, true) && !empty($response['caption'])) {
			$post_update['post_excerpt'] = sanitize_textarea_field($response['caption']);
			$generated['caption']        = $post_update['post_excerpt'];
			$needs_update = true;
		}

		// 4. Description (Content)
		if (in_array('description', $target_fields, true) && !empty($response['description'])) {
			$post_update['post_content'] = wp_kses_post($response['description']);
			$generated['description']    = $post_update['post_content'];
			$needs_update = true;
		}

		if ($needs_update) {
			wp_update_post($post_update);
		}

		// Mark as AI SEO optimized
		update_post_meta($attachment_id, '_aips_image_seo_optimized_at', AIPS_DateTime::now()->timestamp());

		do_action('aips_media_seo_optimized', $attachment_id, $generated, $options);

		return array(
			'success' => true,
			'data'    => $generated,
			'error'   => null,
		);
	}

	/**
	 * Optimize all images associated with a post (featured image + in-body images).
	 *
	 * @param int   $post_id Target post ID.
	 * @param array $options Options.
	 * @return array Multi-image results.
	 */
	public function optimize_post_images($post_id, array $options = array()) {
		$post_id = absint($post_id);
		$post = get_post($post_id);

		if (!$post) {
			return array('success' => false, 'optimized_count' => 0, 'error' => __('Post not found.', 'ai-post-scheduler'));
		}

		$options['post_title'] = $post->post_title;
		$seo_data = get_post_meta($post_id, '_aips_seo_data', true);
		if (!empty($seo_data) && is_array($seo_data) && !empty($seo_data['focus_keyword'])) {
			$options['focus_keyword'] = $seo_data['focus_keyword'];
		}

		$attachment_ids = array();

		// 1. Featured image
		$thumb_id = get_post_thumbnail_id($post_id);
		if ($thumb_id) {
			$attachment_ids[] = absint($thumb_id);
		}

		// 2. In-content images (wp-image-123)
		if (preg_match_all('/wp-image-(\d+)/', $post->post_content, $matches)) {
			foreach ($matches[1] as $img_id) {
				$img_id = absint($img_id);
				if ($img_id && !in_array($img_id, $attachment_ids, true)) {
					$attachment_ids[] = $img_id;
				}
			}
		}

		$results = array();
		$success_count = 0;

		foreach ($attachment_ids as $att_id) {
			$res = $this->optimize_attachment($att_id, $options);
			$results[$att_id] = $res;
			if (!empty($res['success'])) {
				$success_count++;
			}
		}

		return array(
			'success'         => true,
			'optimized_count' => $success_count,
			'total'           => count($attachment_ids),
			'results'         => $results,
		);
	}

	/**
	 * Audit the media library to find missing Alt text and unoptimized media.
	 *
	 * @param array $args Query arguments {
	 *     @type int    $limit  Items per page.
	 *     @type int    $offset Page offset.
	 *     @type string $filter 'missing_alt', 'missing_all', or 'all'.
	 * }
	 * @return array
	 */
	public function audit_media_library(array $args = array()) {
		global $wpdb;

		$limit  = isset($args['limit']) ? max(1, min(100, absint($args['limit']))) : 20;
		$offset = isset($args['offset']) ? absint($args['offset']) : 0;
		$filter = isset($args['filter']) ? sanitize_key($args['filter']) : 'all';

		$where_extra = '';
		if ($filter === 'missing_alt') {
			$where_extra = "AND (m.meta_value IS NULL OR m.meta_value = '')";
		}

		$query = "SELECT p.ID, p.post_title, p.post_excerpt, p.post_content, p.post_date, m.meta_value as alt_text, opt.meta_value as optimized_at
			FROM {$wpdb->posts} p
			LEFT JOIN {$wpdb->postmeta} m ON (p.ID = m.post_id AND m.meta_key = '_wp_attachment_image_alt')
			LEFT JOIN {$wpdb->postmeta} opt ON (p.ID = opt.post_id AND opt.meta_key = '_aips_image_seo_optimized_at')
			WHERE p.post_type = 'attachment' AND p.post_mime_type LIKE 'image/%' $where_extra
			ORDER BY p.ID DESC
			LIMIT %d OFFSET %d";

		$rows = $wpdb->get_results($wpdb->prepare($query, $limit, $offset));

		$count_query = "SELECT COUNT(p.ID) FROM {$wpdb->posts} p
			LEFT JOIN {$wpdb->postmeta} m ON (p.ID = m.post_id AND m.meta_key = '_wp_attachment_image_alt')
			WHERE p.post_type = 'attachment' AND p.post_mime_type LIKE 'image/%' $where_extra";
		$total_filtered = (int) $wpdb->get_var($count_query);

		$total_images = (int) $wpdb->get_var("SELECT COUNT(ID) FROM {$wpdb->posts} WHERE post_type = 'attachment' AND post_mime_type LIKE 'image/%'");
		$missing_alt_total = (int) $wpdb->get_var("SELECT COUNT(p.ID) FROM {$wpdb->posts} p
			LEFT JOIN {$wpdb->postmeta} m ON (p.ID = m.post_id AND m.meta_key = '_wp_attachment_image_alt')
			WHERE p.post_type = 'attachment' AND p.post_mime_type LIKE 'image/%' AND (m.meta_value IS NULL OR m.meta_value = '')");

		$items = array();
		if (is_array($rows)) {
			foreach ($rows as $row) {
				$thumb_url = wp_get_attachment_image_url($row->ID, 'thumbnail');
				$items[] = array(
					'id'           => (int) $row->ID,
					'title'        => $row->post_title,
					'alt_text'     => $row->alt_text ?: '',
					'caption'      => $row->post_excerpt ?: '',
					'description'  => $row->post_content ?: '',
					'thumb_url'    => $thumb_url ?: '',
					'optimized_at' => $row->optimized_at ? (int) $row->optimized_at : 0,
					'date'         => $row->post_date,
				);
			}
		}

		return array(
			'items'             => $items,
			'total'             => $total_filtered,
			'total_library'     => $total_images,
			'missing_alt_total' => $missing_alt_total,
			'limit'             => $limit,
			'offset'            => $offset,
		);
	}

	/**
	 * Build prompt for image SEO generation.
	 *
	 * @param string $filename      Image filename.
	 * @param string $post_title    Associated post title.
	 * @param string $focus_keyword Focus keyword.
	 * @param string $custom_prompt Custom prompt.
	 * @param array  $fields        Target fields.
	 * @return string
	 */
	private function build_image_seo_prompt($filename, $post_title = '', $focus_keyword = '', $custom_prompt = '', $fields = array()) {
		$sections = array();
		$sections[] = __('You are an expert accessibility and Image SEO specialist. Generate high-quality, descriptive, accessibility-compliant, and search-optimized image metadata.', 'ai-post-scheduler');

		if (!empty($filename)) {
			$sections[] = sprintf("IMAGE FILENAME:\n%s", $filename);
		}

		if (!empty($post_title)) {
			$sections[] = sprintf("ASSOCIATED ARTICLE TITLE:\n%s", $post_title);
		}

		if (!empty($focus_keyword)) {
			$sections[] = sprintf("TARGET FOCUS KEYWORD:\n%s", $focus_keyword);
		}

		if (!empty($custom_prompt)) {
			$sections[] = sprintf("CUSTOM INSTRUCTIONS:\n%s", $custom_prompt);
		}

		$sections[] = "IMAGE FIELD GUIDELINES:\n"
			. "1. alt_text: Accurate, descriptive text for screen readers & Google Images (80-120 chars). Naturally incorporate target keyword if relevant.\n"
			. "2. title: Clean, human-readable title without hyphens, underscores, or file extensions.\n"
			. "3. caption: Concise editorial caption suitable for displaying under the image.\n"
			. "4. description: Informative multi-sentence description providing full contextual details.";

		$sections[] = "RESPOND WITH EXACTLY THIS JSON FORMAT:\n{\n"
			. '  "alt_text": "Descriptive alt text for accessibility and search",\n'
			. '  "title": "Clean Image Title",\n'
			. '  "caption": "Brief editorial caption",\n'
			. '  "description": "Comprehensive descriptive text explaining image context"\n'
			. '}';

		return implode("\n\n", $sections);
	}
}
