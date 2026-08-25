<?php
/**
 * Native / Fallback SEO Provider Adapter
 *
 * Implements AIPS_SEO_Provider_Interface for sites without a third-party
 * SEO plugin. Stores canonical SEO metadata in post meta (_aips_seo_data)
 * and can render standard HTML meta tags in wp_head as a fallback.
 *
 * @package AI_Post_Scheduler
 * @since 2.10.0
 */

if (!defined('ABSPATH')) {
	exit;
}

class AIPS_SEO_Provider_Native implements AIPS_SEO_Provider_Interface {

	const META_KEY = '_aips_seo_data';

	/**
	 * Identifier for native provider.
	 *
	 * @return string
	 */
	public function get_id() {
		return 'native';
	}

	/**
	 * Display label for native provider.
	 *
	 * @return string
	 */
	public function get_label() {
		return __('Native Custom Fields / Fallback', 'ai-post-scheduler');
	}

	/**
	 * Native provider is always available.
	 *
	 * @return bool
	 */
	public function is_available() {
		return true;
	}

	/**
	 * All canonical fields are supported.
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
	 * Write canonical SEO metadata to native post meta.
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

		$cleaned = $this->sanitize_seo_data($seo_data);
		$cleaned['provider_written'] = 'native';
		$cleaned['updated_at'] = AIPS_DateTime::now()->timestamp();

		update_post_meta($post_id, self::META_KEY, $cleaned);

		return true;
	}

	/**
	 * Read canonical SEO metadata from native post meta.
	 *
	 * @param int $post_id Target post ID.
	 * @return array|null
	 */
	public function read_post_seo($post_id) {
		$post_id = absint($post_id);

		if (!$post_id) {
			return null;
		}

		$data = get_post_meta($post_id, self::META_KEY, true);

		if (!is_array($data) || empty($data)) {
			return null;
		}

		return $data;
	}

	/**
	 * Delete native SEO metadata for a post.
	 *
	 * @param int $post_id Target post ID.
	 * @return bool|WP_Error
	 */
	public function delete_post_seo($post_id) {
		$post_id = absint($post_id);

		if (!$post_id) {
			return new WP_Error('invalid_post', __('Invalid post ID.', 'ai-post-scheduler'));
		}

		delete_post_meta($post_id, self::META_KEY);

		return true;
	}

	/**
	 * Render fallback HTML meta tags in wp_head for single posts.
	 *
	 * @return void
	 */
	public function render_frontend_head() {
		if (!AIPS_Config::get_instance()->get_option('aips_seo_enable_head_output', false)) {
			return;
		}

		if (!is_singular()) {
			return;
		}

		$post_id = get_the_ID();
		if (!$post_id) {
			return;
		}

		$seo_data = $this->read_post_seo($post_id);
		if (empty($seo_data)) {
			return;
		}

		echo "\n<!-- AIPS SEO Meta Tags -->\n";

		if (!empty($seo_data['meta_description'])) {
			echo '<meta name="description" content="' . esc_attr($seo_data['meta_description']) . '" />' . "\n";
		}

		if (!empty($seo_data['robots_index']) && $seo_data['robots_index'] === 'noindex') {
			$robots = 'noindex';
			if (!empty($seo_data['robots_follow'])) {
				$robots .= ', ' . esc_attr($seo_data['robots_follow']);
			}
			echo '<meta name="robots" content="' . esc_attr($robots) . '" />' . "\n";
		}

		if (!empty($seo_data['og_title'])) {
			echo '<meta property="og:title" content="' . esc_attr($seo_data['og_title']) . '" />' . "\n";
		}

		if (!empty($seo_data['og_description'])) {
			echo '<meta property="og:description" content="' . esc_attr($seo_data['og_description']) . '" />' . "\n";
		}

		if (!empty($seo_data['twitter_title'])) {
			echo '<meta name="twitter:title" content="' . esc_attr($seo_data['twitter_title']) . '" />' . "\n";
		}

		if (!empty($seo_data['twitter_description'])) {
			echo '<meta name="twitter:description" content="' . esc_attr($seo_data['twitter_description']) . '" />' . "\n";
		}

		if (!empty($seo_data['canonical_url'])) {
			echo '<link rel="canonical" href="' . esc_url($seo_data['canonical_url']) . '" />' . "\n";
		}

		echo "<!-- /AIPS SEO Meta Tags -->\n\n";
	}

	/**
	 * Sanitize canonical SEO data array.
	 *
	 * @param array $seo_data Raw SEO data.
	 * @return array Sanitized SEO data.
	 */
	public function sanitize_seo_data(array $seo_data) {
		$secondary = array();
		if (!empty($seo_data['secondary_keywords']) && is_array($seo_data['secondary_keywords'])) {
			foreach ($seo_data['secondary_keywords'] as $kw) {
				$clean = sanitize_text_field(trim((string) $kw));
				if (!empty($clean)) {
					$secondary[] = $clean;
				}
			}
		}

		return array(
			'focus_keyword'       => isset($seo_data['focus_keyword']) ? sanitize_text_field($seo_data['focus_keyword']) : '',
			'secondary_keywords'  => $secondary,
			'seo_title'           => isset($seo_data['seo_title']) ? sanitize_text_field($seo_data['seo_title']) : '',
			'meta_description'    => isset($seo_data['meta_description']) ? sanitize_textarea_field($seo_data['meta_description']) : '',
			'og_title'            => isset($seo_data['og_title']) ? sanitize_text_field($seo_data['og_title']) : '',
			'og_description'      => isset($seo_data['og_description']) ? sanitize_textarea_field($seo_data['og_description']) : '',
			'twitter_title'       => isset($seo_data['twitter_title']) ? sanitize_text_field($seo_data['twitter_title']) : '',
			'twitter_description' => isset($seo_data['twitter_description']) ? sanitize_textarea_field($seo_data['twitter_description']) : '',
			'canonical_url'       => !empty($seo_data['canonical_url']) ? esc_url_raw($seo_data['canonical_url']) : '',
			'robots_index'        => (isset($seo_data['robots_index']) && $seo_data['robots_index'] === 'noindex') ? 'noindex' : 'index',
			'robots_follow'       => (isset($seo_data['robots_follow']) && $seo_data['robots_follow'] === 'nofollow') ? 'nofollow' : 'follow',
		);
	}
}
