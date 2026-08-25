<?php
/**
 * Rank Math SEO Provider Adapter
 *
 * Implements AIPS_SEO_Provider_Interface for Rank Math SEO. Reads and writes
 * canonical SEO metadata into Rank Math custom post meta fields.
 *
 * @package AI_Post_Scheduler
 * @since 2.10.0
 */

if (!defined('ABSPATH')) {
	exit;
}

class AIPS_SEO_Provider_RankMath implements AIPS_SEO_Provider_Interface {

	/**
	 * Identifier for Rank Math SEO provider.
	 *
	 * @return string
	 */
	public function get_id() {
		return 'rank_math';
	}

	/**
	 * Display label for Rank Math SEO provider.
	 *
	 * @return string
	 */
	public function get_label() {
		return __('Rank Math SEO', 'ai-post-scheduler');
	}

	/**
	 * Check whether Rank Math SEO is active.
	 *
	 * @return bool
	 */
	public function is_available() {
		return defined('RANK_MATH_VERSION') || class_exists('RankMath');
	}

	/**
	 * List of canonical SEO fields supported by Rank Math.
	 *
	 * @return array<int, string>
	 */
	public function get_supported_fields() {
		return array(
			'focus_keyword',
			'secondary_keywords',
			'seo_title',
			'meta_description',
			'og_title',
			'og_description',
			'twitter_title',
			'twitter_description',
			'canonical_url',
			'robots_index',
			'robots_follow',
		);
	}

	/**
	 * Write canonical SEO metadata into Rank Math post meta.
	 *
	 * @param int   $post_id  Target post ID.
	 * @param array $seo_data Canonical SEO data array.
	 * @return bool|WP_Error
	 */
	public function write_post_seo($post_id, array $seo_data) {
		$post_id = absint($post_id);

		if (!$post_id || !get_post($post_id)) {
			return new WP_Error('invalid_post', __('Invalid post ID.', 'ai-post-scheduler'));
		}

		if (!$this->is_available()) {
			return new WP_Error('provider_unavailable', __('Rank Math SEO is not active on this site.', 'ai-post-scheduler'));
		}

		// SEO Title
		if (isset($seo_data['seo_title'])) {
			update_post_meta($post_id, 'rank_math_title', sanitize_text_field($seo_data['seo_title']));
		}

		// Meta Description
		if (isset($seo_data['meta_description'])) {
			update_post_meta($post_id, 'rank_math_description', sanitize_textarea_field($seo_data['meta_description']));
		}

		// Focus Keywords (Primary + Secondary combined into comma-separated list)
		$keywords = array();
		if (!empty($seo_data['focus_keyword'])) {
			$keywords[] = trim(sanitize_text_field($seo_data['focus_keyword']));
		}
		if (!empty($seo_data['secondary_keywords']) && is_array($seo_data['secondary_keywords'])) {
			foreach ($seo_data['secondary_keywords'] as $sec_kw) {
				$clean = trim(sanitize_text_field($sec_kw));
				if (!empty($clean) && !in_array($clean, $keywords, true)) {
					$keywords[] = $clean;
				}
			}
		}
		if (!empty($keywords)) {
			update_post_meta($post_id, 'rank_math_focus_keyword', implode(', ', $keywords));
		}

		// OpenGraph Title & Description
		if (isset($seo_data['og_title'])) {
			update_post_meta($post_id, 'rank_math_facebook_title', sanitize_text_field($seo_data['og_title']));
		}
		if (isset($seo_data['og_description'])) {
			update_post_meta($post_id, 'rank_math_facebook_description', sanitize_textarea_field($seo_data['og_description']));
		}

		// Twitter Title & Description
		if (isset($seo_data['twitter_title'])) {
			update_post_meta($post_id, 'rank_math_twitter_title', sanitize_text_field($seo_data['twitter_title']));
		}
		if (isset($seo_data['twitter_description'])) {
			update_post_meta($post_id, 'rank_math_twitter_description', sanitize_textarea_field($seo_data['twitter_description']));
		}

		// Canonical URL
		if (isset($seo_data['canonical_url'])) {
			update_post_meta($post_id, 'rank_math_canonical_url', esc_url_raw($seo_data['canonical_url']));
		}

		// Schema.org Structured Data
		if (!empty($seo_data['schema']) && is_array($seo_data['schema'])) {
			update_post_meta($post_id, 'rank_math_rich_snippet', 'article');
			update_post_meta($post_id, '_aips_rank_math_custom_schema', $seo_data['schema']);
		}

		// Robots Meta (Array format for Rank Math)
		$robots = array();
		if (isset($seo_data['robots_index'])) {
			$robots[] = ($seo_data['robots_index'] === 'noindex') ? 'noindex' : 'index';
		}
		if (isset($seo_data['robots_follow'])) {
			$robots[] = ($seo_data['robots_follow'] === 'nofollow') ? 'nofollow' : 'follow';
		}
		if (!empty($robots)) {
			update_post_meta($post_id, 'rank_math_robots', $robots);
		}

		/**
		 * Fires after Rank Math metadata is updated programmatically.
		 *
		 * @param int $post_id Post ID.
		 */
		do_action('rank_math/post/update_meta', $post_id);

		return true;
	}

	/**
	 * Read current Rank Math metadata for a post into canonical format.
	 *
	 * @param int $post_id Target post ID.
	 * @return array|null
	 */
	public function read_post_seo($post_id) {
		$post_id = absint($post_id);

		if (!$post_id) {
			return null;
		}

		$title = get_post_meta($post_id, 'rank_math_title', true);
		$desc = get_post_meta($post_id, 'rank_math_description', true);
		$kw_str = get_post_meta($post_id, 'rank_math_focus_keyword', true);
		$og_title = get_post_meta($post_id, 'rank_math_facebook_title', true);
		$og_desc = get_post_meta($post_id, 'rank_math_facebook_description', true);
		$tw_title = get_post_meta($post_id, 'rank_math_twitter_title', true);
		$tw_desc = get_post_meta($post_id, 'rank_math_twitter_description', true);
		$canonical = get_post_meta($post_id, 'rank_math_canonical_url', true);
		$robots = get_post_meta($post_id, 'rank_math_robots', true);

		if (empty($title) && empty($desc) && empty($kw_str) && empty($og_title)) {
			return null;
		}

		$kw_parts = !empty($kw_str) ? array_map('trim', explode(',', $kw_str)) : array();
		$focus_kw = !empty($kw_parts) ? array_shift($kw_parts) : '';
		$secondary_kw = !empty($kw_parts) ? $kw_parts : array();

		$robots_arr = is_array($robots) ? $robots : (is_string($robots) ? explode(',', $robots) : array());
		$is_noindex = in_array('noindex', $robots_arr, true);
		$is_nofollow = in_array('nofollow', $robots_arr, true);

		return array(
			'focus_keyword'       => (string) $focus_kw,
			'secondary_keywords'  => $secondary_kw,
			'seo_title'           => (string) $title,
			'meta_description'    => (string) $desc,
			'og_title'            => (string) $og_title,
			'og_description'      => (string) $og_desc,
			'twitter_title'       => (string) $tw_title,
			'twitter_description' => (string) $tw_desc,
			'canonical_url'       => (string) $canonical,
			'robots_index'        => $is_noindex ? 'noindex' : 'index',
			'robots_follow'       => $is_nofollow ? 'nofollow' : 'follow',
		);
	}

	/**
	 * Delete Rank Math metadata for a post.
	 *
	 * @param int $post_id Target post ID.
	 * @return bool|WP_Error
	 */
	public function delete_post_seo($post_id) {
		$post_id = absint($post_id);

		if (!$post_id) {
			return new WP_Error('invalid_post', __('Invalid post ID.', 'ai-post-scheduler'));
		}

		$keys = array(
			'rank_math_title',
			'rank_math_description',
			'rank_math_focus_keyword',
			'rank_math_facebook_title',
			'rank_math_facebook_description',
			'rank_math_twitter_title',
			'rank_math_twitter_description',
			'rank_math_canonical_url',
			'rank_math_robots',
		);

		foreach ($keys as $key) {
			delete_post_meta($post_id, $key);
		}

		return true;
	}
}
