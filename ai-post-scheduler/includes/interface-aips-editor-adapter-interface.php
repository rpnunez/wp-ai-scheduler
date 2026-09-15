<?php
/**
 * Editor Adapter Interface
 *
 * Contract for integrating WordPress content and page builders (Gutenberg,
 * Elementor, Divi, WPBakery, etc.) with AIPS semantic links and link graph.
 *
 * @package AI_Post_Scheduler
 * @since 3.7.0
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Interface AIPS_Editor_Adapter_Interface
 */
interface AIPS_Editor_Adapter_Interface {

	/**
	 * Unique identifier for this editor adapter (e.g. 'gutenberg', 'elementor').
	 *
	 * @return string
	 */
	public function get_id();

	/**
	 * Human-readable display label.
	 *
	 * @return string
	 */
	public function get_name();

	/**
	 * Check if this editor adapter is active for a given post.
	 *
	 * @param int $post_id Post ID.
	 * @return bool
	 */
	public function is_active_for_post($post_id);

	/**
	 * Extract searchable plain text / HTML content from the post for vector indexing.
	 *
	 * @param int $post_id Post ID.
	 * @return string
	 */
	public function extract_content($post_id);

	/**
	 * Extract outbound internal links from the post content or builder structure.
	 *
	 * @param int $post_id Post ID.
	 * @return array List of link items.
	 */
	public function extract_links($post_id);

	/**
	 * Enqueue assets required for this builder's editor screen.
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	public function enqueue_editor_assets($post_id);

	/**
	 * Return list of supported features for this builder.
	 *
	 * @return array
	 */
	public function get_supported_features();
}
