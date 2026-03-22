<?php
/**
 * Story Package helper.
 *
 * Centralizes template configuration, prompt metadata, and persistence helpers
 * for coordinated editorial packages generated alongside a post.
 *
 * @package AI_Post_Scheduler
 * @since 2.1.0
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Story_Package
 */
class AIPS_Story_Package {

	/**
	 * Post meta key used to store the latest generated package artifacts.
	 */
	const POST_META_KEY = 'aips_story_package_artifacts';

	/**
	 * Get all supported story package outputs.
	 *
	 * @return array
	 */
	public static function get_output_definitions() {
		return array(
			'full_article' => array(
				'label' => __('Full Article', 'ai-post-scheduler'),
				'description' => __('The main post title, excerpt, and article body that WordPress publishes.', 'ai-post-scheduler'),
				'component' => 'story_package_full_article',
				'format' => 'article',
			),
			'seo_title_dek' => array(
				'label' => __('SEO Title + Dek', 'ai-post-scheduler'),
				'description' => __('A search-focused title and supporting dek/subheading for editorial review.', 'ai-post-scheduler'),
				'component' => 'story_package_seo_title_dek',
				'format' => 'text',
			),
			'social_posts' => array(
				'label' => __('Social Posts', 'ai-post-scheduler'),
				'description' => __('Platform-ready social copy variations built from the same story angle.', 'ai-post-scheduler'),
				'component' => 'story_package_social_posts',
				'format' => 'text',
			),
			'newsletter_summary' => array(
				'label' => __('Newsletter Summary', 'ai-post-scheduler'),
				'description' => __('A concise email/newsletter version of the same story.', 'ai-post-scheduler'),
				'component' => 'story_package_newsletter_summary',
				'format' => 'text',
			),
			'faq_box' => array(
				'label' => __('FAQ Box', 'ai-post-scheduler'),
				'description' => __('Frequently asked questions and answers derived from the article context.', 'ai-post-scheduler'),
				'component' => 'story_package_faq_box',
				'format' => 'text',
			),
			'pull_quotes' => array(
				'label' => __('Pull Quotes', 'ai-post-scheduler'),
				'description' => __('Memorable pull quotes editors can drop into layouts or social cards.', 'ai-post-scheduler'),
				'component' => 'story_package_pull_quotes',
				'format' => 'text',
			),
			'meta_description' => array(
				'label' => __('Meta Description', 'ai-post-scheduler'),
				'description' => __('A search snippet-ready meta description.', 'ai-post-scheduler'),
				'component' => 'story_package_meta_description',
				'format' => 'text',
			),
			'featured_image_brief' => array(
				'label' => __('Featured Image Brief', 'ai-post-scheduler'),
				'description' => __('A creative brief describing the hero image treatment for the story.', 'ai-post-scheduler'),
				'component' => 'story_package_featured_image_brief',
				'format' => 'text',
			),
		);
	}

	/**
	 * Normalize a configured story package output list.
	 *
	 * @param mixed $outputs Raw configured outputs.
	 * @return array
	 */
	public static function normalize_outputs($outputs) {
		$definitions = self::get_output_definitions();
		$normalized = array();

		if (is_string($outputs) && '' !== $outputs) {
			$decoded = json_decode($outputs, true);
			if (is_array($decoded)) {
				$outputs = $decoded;
			} else {
				$outputs = array_map('trim', explode(',', $outputs));
			}
		}

		if (!is_array($outputs)) {
			$outputs = array();
		}

		foreach ($outputs as $output) {
			$key = sanitize_key($output);
			if (isset($definitions[$key])) {
				$normalized[] = $key;
			}
		}

		$normalized = array_values(array_unique($normalized));

		if (!in_array('full_article', $normalized, true)) {
			array_unshift($normalized, 'full_article');
		}

		return $normalized;
	}

	/**
	 * Normalize a template's story package configuration.
	 *
	 * @param array|object $template Template configuration.
	 * @return array
	 */
	public static function normalize_template_config($template) {
		$enabled = false;
		$outputs = array('full_article');

		if (is_array($template)) {
			$enabled = !empty($template['story_package_enabled']);
			if (isset($template['story_package_outputs'])) {
				$outputs = self::normalize_outputs($template['story_package_outputs']);
			}
		} elseif (is_object($template)) {
			$enabled = !empty($template->story_package_enabled);
			if (isset($template->story_package_outputs)) {
				$outputs = self::normalize_outputs($template->story_package_outputs);
			}
		}

		return array(
			'enabled' => (bool) $enabled,
			'outputs' => $outputs,
		);
	}

	/**
	 * Build a default package payload from artifacts.
	 *
	 * @param array $outputs Selected outputs.
	 * @param array $artifacts Artifact payloads keyed by output slug.
	 * @return array
	 */
	public static function build_package_payload($outputs, $artifacts) {
		$outputs = self::normalize_outputs($outputs);
		$definitions = self::get_output_definitions();
		$payload_artifacts = array();

		foreach ($outputs as $output_key) {
			$definition = isset($definitions[$output_key]) ? $definitions[$output_key] : array();
			$artifact = isset($artifacts[$output_key]) && is_array($artifacts[$output_key]) ? $artifacts[$output_key] : array();

			$payload_artifacts[$output_key] = array_merge(
				array(
					'key' => $output_key,
					'label' => isset($definition['label']) ? $definition['label'] : $output_key,
					'component' => isset($definition['component']) ? $definition['component'] : $output_key,
					'format' => isset($definition['format']) ? $definition['format'] : 'text',
					'content' => '',
					'generated_at' => current_time('mysql'),
				),
				$artifact
			);
		}

		return array(
			'version' => 1,
			'outputs' => $outputs,
			'artifacts' => $payload_artifacts,
			'generated_at' => current_time('mysql'),
		);
	}

	/**
	 * Persist a package payload for a post.
	 *
	 * @param int   $post_id Post ID.
	 * @param array $package Package payload.
	 * @return bool
	 */
	public static function save_post_package($post_id, $package) {
		$post_id = absint($post_id);
		if (!$post_id) {
			return false;
		}

		return false !== update_post_meta($post_id, self::POST_META_KEY, wp_json_encode($package));
	}

	/**
	 * Read a package payload for a post.
	 *
	 * @param int $post_id Post ID.
	 * @return array
	 */
	public static function get_post_package($post_id) {
		$post_id = absint($post_id);
		if (!$post_id) {
			return array();
		}

		$raw = get_post_meta($post_id, self::POST_META_KEY, true);
		if (empty($raw)) {
			return array();
		}

		if (is_array($raw)) {
			return $raw;
		}

		$decoded = json_decode((string) $raw, true);

		return is_array($decoded) ? $decoded : array();
	}

	/**
	 * Check if a post has stored package artifacts.
	 *
	 * @param int $post_id Post ID.
	 * @return bool
	 */
	public static function post_has_package($post_id) {
		$package = self::get_post_package($post_id);
		return !empty($package['artifacts']) && is_array($package['artifacts']);
	}

	/**
	 * Update a single artifact inside an existing package payload.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $artifact_key Artifact key.
	 * @param array  $artifact Artifact payload.
	 * @return array Updated package payload.
	 */
	public static function update_post_artifact($post_id, $artifact_key, $artifact) {
		$package = self::get_post_package($post_id);
		$definitions = self::get_output_definitions();
		$artifact_key = sanitize_key($artifact_key);

		if (!isset($definitions[$artifact_key])) {
			return $package;
		}

		if (empty($package)) {
			$package = self::build_package_payload(array($artifact_key), array());
		}

		$outputs = isset($package['outputs']) ? self::normalize_outputs($package['outputs']) : array('full_article');
		if (!in_array($artifact_key, $outputs, true)) {
			$outputs[] = $artifact_key;
		}

		$package['outputs'] = self::normalize_outputs($outputs);
		if (!isset($package['artifacts']) || !is_array($package['artifacts'])) {
			$package['artifacts'] = array();
		}

		$package['artifacts'][$artifact_key] = array_merge(
			array(
				'key' => $artifact_key,
				'label' => $definitions[$artifact_key]['label'],
				'component' => $definitions[$artifact_key]['component'],
				'format' => $definitions[$artifact_key]['format'],
				'generated_at' => current_time('mysql'),
			),
			is_array($artifact) ? $artifact : array('content' => $artifact)
		);

		$package['generated_at'] = current_time('mysql');
		self::save_post_package($post_id, $package);

		return $package;
	}
}
