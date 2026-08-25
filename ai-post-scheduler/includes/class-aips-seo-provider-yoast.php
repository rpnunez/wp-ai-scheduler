<?php
/**
 * Yoast SEO Provider Adapter
 *
 * Implements AIPS_SEO_Provider_Interface for Yoast SEO. Reads and writes
 * canonical SEO metadata to Yoast's custom post meta fields and triggers
 * Yoast's Indexable synchronization when active.
 *
 * @package AI_Post_Scheduler
 * @since 2.10.0
 */

if (!defined('ABSPATH')) {
	exit;
}

class AIPS_SEO_Provider_Yoast implements AIPS_SEO_Provider_Interface {

	/**
	 * Identifier for Yoast SEO provider.
	 *
	 * @return string
	 */
	public function get_id() {
		return 'yoast';
	}

	/**
	 * Display label for Yoast SEO provider.
	 *
	 * @return string
	 */
	public function get_label() {
		return __('Yoast SEO', 'ai-post-scheduler');
	}

	/**
	 * Check whether Yoast SEO is active.
	 *
	 * @return bool
	 */
	public function is_available() {
		return defined('WPSEO_VERSION') || class_exists('WPSEO_Meta') || function_exists('YoastSEO');
	}

	/**
	 * List of canonical SEO fields supported by Yoast SEO.
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
	 * Write canonical SEO metadata into Yoast SEO post meta.
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
			return new WP_Error('provider_unavailable', __('Yoast SEO is not active on this site.', 'ai-post-scheduler'));
		}

		// SEO Title
		if (isset($seo_data['seo_title'])) {
			$title = sanitize_text_field($seo_data['seo_title']);
			$this->set_meta_value('title', '_yoast_wpseo_title', $title, $post_id);
		}

		// Meta Description
		if (isset($seo_data['meta_description'])) {
			$desc = sanitize_textarea_field($seo_data['meta_description']);
			$this->set_meta_value('metadesc', '_yoast_wpseo_metadesc', $desc, $post_id);
		}

		// Focus Keyword (primary)
		if (isset($seo_data['focus_keyword'])) {
			$keyword = sanitize_text_field($seo_data['focus_keyword']);
			$this->set_meta_value('focuskw', '_yoast_wpseo_focuskw', $keyword, $post_id);
		}

		// OpenGraph Title
		if (isset($seo_data['og_title'])) {
			$og_title = sanitize_text_field($seo_data['og_title']);
			$this->set_meta_value('opengraph-title', '_yoast_wpseo_opengraph-title', $og_title, $post_id);
		}

		// OpenGraph Description
		if (isset($seo_data['og_description'])) {
			$og_desc = sanitize_textarea_field($seo_data['og_description']);
			$this->set_meta_value('opengraph-description', '_yoast_wpseo_opengraph-description', $og_desc, $post_id);
		}

		// Twitter Title
		if (isset($seo_data['twitter_title'])) {
			$tw_title = sanitize_text_field($seo_data['twitter_title']);
			$this->set_meta_value('twitter-title', '_yoast_wpseo_twitter-title', $tw_title, $post_id);
		}

		// Twitter Description
		if (isset($seo_data['twitter_description'])) {
			$tw_desc = sanitize_textarea_field($seo_data['twitter_description']);
			$this->set_meta_value('twitter-description', '_yoast_wpseo_twitter-description', $tw_desc, $post_id);
		}

		// Canonical URL
		if (!empty($seo_data['canonical_url'])) {
			$canonical = esc_url_raw($seo_data['canonical_url']);
			$this->set_meta_value('canonical', '_yoast_wpseo_canonical', $canonical, $post_id);
		}

		// Robots Index (Yoast: 1 = noindex, 2 = index)
		if (isset($seo_data['robots_index'])) {
			$noindex_val = ($seo_data['robots_index'] === 'noindex') ? 1 : 2;
			$this->set_meta_value('meta-robots-noindex', '_yoast_wpseo_meta-robots-noindex', $noindex_val, $post_id);
		}

		// Robots Follow (Yoast: 1 = nofollow, 0 = follow)
		if (isset($seo_data['robots_follow'])) {
			$nofollow_val = ($seo_data['robots_follow'] === 'nofollow') ? 1 : 0;
			$this->set_meta_value('meta-robots-nofollow', '_yoast_wpseo_meta-robots-nofollow', $nofollow_val, $post_id);
		}

		// Sync Yoast Indexables if available
		$this->refresh_indexables($post_id);

		return true;
	}

	/**
	 * Read current Yoast SEO metadata for a post into canonical format.
	 *
	 * @param int $post_id Target post ID.
	 * @return array|null
	 */
	public function read_post_seo($post_id) {
		$post_id = absint($post_id);

		if (!$post_id) {
			return null;
		}

		$title = get_post_meta($post_id, '_yoast_wpseo_title', true);
		$desc = get_post_meta($post_id, '_yoast_wpseo_metadesc', true);
		$keyword = get_post_meta($post_id, '_yoast_wpseo_focuskw', true);
		$og_title = get_post_meta($post_id, '_yoast_wpseo_opengraph-title', true);
		$og_desc = get_post_meta($post_id, '_yoast_wpseo_opengraph-description', true);
		$tw_title = get_post_meta($post_id, '_yoast_wpseo_twitter-title', true);
		$tw_desc = get_post_meta($post_id, '_yoast_wpseo_twitter-description', true);
		$canonical = get_post_meta($post_id, '_yoast_wpseo_canonical', true);
		$noindex = get_post_meta($post_id, '_yoast_wpseo_meta-robots-noindex', true);
		$nofollow = get_post_meta($post_id, '_yoast_wpseo_meta-robots-nofollow', true);

		// If all meta fields are empty, no Yoast data is present.
		if (empty($title) && empty($desc) && empty($keyword) && empty($og_title)) {
			return null;
		}

		return array(
			'focus_keyword'       => (string) $keyword,
			'secondary_keywords'  => array(),
			'seo_title'           => (string) $title,
			'meta_description'    => (string) $desc,
			'og_title'            => (string) $og_title,
			'og_description'      => (string) $og_desc,
			'twitter_title'       => (string) $tw_title,
			'twitter_description' => (string) $tw_desc,
			'canonical_url'       => (string) $canonical,
			'robots_index'        => ((int) $noindex === 1) ? 'noindex' : 'index',
			'robots_follow'       => ((int) $nofollow === 1) ? 'nofollow' : 'follow',
		);
	}

	/**
	 * Delete Yoast SEO metadata for a post.
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
			'_yoast_wpseo_title',
			'_yoast_wpseo_metadesc',
			'_yoast_wpseo_focuskw',
			'_yoast_wpseo_opengraph-title',
			'_yoast_wpseo_opengraph-description',
			'_yoast_wpseo_twitter-title',
			'_yoast_wpseo_twitter-description',
			'_yoast_wpseo_canonical',
			'_yoast_wpseo_meta-robots-noindex',
			'_yoast_wpseo_meta-robots-nofollow',
		);

		foreach ($keys as $key) {
			delete_post_meta($post_id, $key);
		}

		$this->refresh_indexables($post_id);

		return true;
	}

	/**
	 * Helper to set value via WPSEO_Meta::set_value() when available,
	 * falling back to direct update_post_meta().
	 *
	 * @param string $yoast_key Short Yoast key (e.g. 'title').
	 * @param string $meta_key  Full WordPress meta key (e.g. '_yoast_wpseo_title').
	 * @param mixed  $value     Value to set.
	 * @param int    $post_id   Target post ID.
	 * @return void
	 */
	private function set_meta_value($yoast_key, $meta_key, $value, $post_id) {
		if (class_exists('WPSEO_Meta') && method_exists('WPSEO_Meta', 'set_value')) {
			WPSEO_Meta::set_value($yoast_key, $value, $post_id);
		} else {
			update_post_meta($post_id, $meta_key, $value);
		}
	}

	/**
	 * Refresh Yoast Indexable table cache if Indexables repository is loaded.
	 *
	 * @param int $post_id Target post ID.
	 * @return void
	 */
	private function refresh_indexables($post_id) {
		if (function_exists('YoastSEO')) {
			try {
				$meta = YoastSEO()->meta->for_post($post_id);
				if ($meta && is_object($meta) && method_exists($meta, 'save')) {
					$meta->save();
				}
			} catch (Exception $e) {
				// Silently handle any indexable sync exception
			}
		}
	}
}
