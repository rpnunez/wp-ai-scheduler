<?php
/**
 * SEO Provider Interface
 *
 * Defines the contract that any SEO plugin provider adapter (Yoast SEO,
 * Rank Math, Native Meta, etc.) must implement so AIPS_SEO_Manager can
 * discover available providers and write generated canonical SEO metadata
 * into third-party plugin fields or native postmeta.
 *
 * @package AI_Post_Scheduler
 * @since 2.10.0
 */

if (!defined('ABSPATH')) {
	exit;
}

interface AIPS_SEO_Provider_Interface {

	/**
	 * Unique, stable identifier for this SEO provider (e.g. 'yoast', 'rank_math', 'native').
	 *
	 * @return string
	 */
	public function get_id();

	/**
	 * Human-readable label shown in the admin UI (e.g. 'Yoast SEO', 'Rank Math SEO').
	 *
	 * @return string
	 */
	public function get_label();

	/**
	 * Whether the target SEO plugin is active and available on this WordPress instance.
	 *
	 * @return bool
	 */
	public function is_available();

	/**
	 * List of canonical SEO fields supported by this provider.
	 *
	 * Canonical field keys:
	 * - 'focus_keyword'
	 * - 'secondary_keywords'
	 * - 'seo_title'
	 * - 'meta_description'
	 * - 'og_title'
	 * - 'og_description'
	 * - 'twitter_title'
	 * - 'twitter_description'
	 * - 'canonical_url'
	 * - 'robots_index'
	 * - 'robots_follow'
	 *
	 * @return array<int, string>
	 */
	public function get_supported_fields();

	/**
	 * Write canonical SEO metadata to the post via the provider's specific APIs or meta keys.
	 *
	 * @param int   $post_id  Target WordPress post ID.
	 * @param array $seo_data Canonical SEO data array.
	 * @return bool|WP_Error True on success, WP_Error on failure.
	 */
	public function write_post_seo($post_id, array $seo_data);

	/**
	 * Read current SEO metadata from the post formatted into the canonical array structure.
	 *
	 * @param int $post_id Target WordPress post ID.
	 * @return array|null Canonical SEO data array or null if no provider data found.
	 */
	public function read_post_seo($post_id);

	/**
	 * Remove provider-specific SEO metadata from the post.
	 *
	 * @param int $post_id Target WordPress post ID.
	 * @return bool|WP_Error True on success, WP_Error on failure.
	 */
	public function delete_post_seo($post_id);
}
