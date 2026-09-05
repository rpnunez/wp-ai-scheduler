<?php
/**
 * Gutenberg Editor Adapter
 *
 * Implements AIPS_Editor_Adapter_Interface for the WordPress Block Editor.
 *
 * @package AI_Post_Scheduler
 * @since 3.7.0
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Gutenberg_Editor_Adapter
 */
class AIPS_Gutenberg_Editor_Adapter implements AIPS_Editor_Adapter_Interface {

	/**
	 * Unique identifier.
	 *
	 * @return string
	 */
	public function get_id() {
		return 'gutenberg';
	}

	/**
	 * Display label.
	 *
	 * @return string
	 */
	public function get_name() {
		return __('Gutenberg Block Editor', 'ai-post-scheduler');
	}

	/**
	 * Check if active for post.
	 *
	 * @param int $post_id Post ID.
	 * @return bool
	 */
	public function is_active_for_post($post_id) {
		// If post has no third-party page builder meta, it uses Gutenberg / standard editor
		$elementor_mode = get_post_meta($post_id, '_elementor_edit_mode', true);
		return empty($elementor_mode);
	}

	/**
	 * Extract searchable text content from the post.
	 *
	 * @param int $post_id Post ID.
	 * @return string
	 */
	public function extract_content($post_id) {
		$post = get_post($post_id);
		return $post ? (string) $post->post_content : '';
	}

	/**
	 * Extract internal links from post content.
	 *
	 * @param int $post_id Post ID.
	 * @return array
	 */
	public function extract_links($post_id) {
		$content = $this->extract_content($post_id);
		$container = AIPS_Container::get_instance();
		$service = $container->has(AIPS_Link_Graph_Service::class) ? $container->make(AIPS_Link_Graph_Service::class) : new AIPS_Link_Graph_Service();
		return $service->parse_content_for_internal_links($content, $post_id);
	}

	/**
	 * Enqueue assets for Gutenberg editor screen.
	 *
	 * Handled via AIPS_Admin_Assets::enqueue_block_editor_assets().
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	public function enqueue_editor_assets($post_id) {
		// Hooked into 'enqueue_block_editor_assets'
	}

	/**
	 * Supported feature flags.
	 *
	 * @return array
	 */
	public function get_supported_features() {
		return array(
			'realtime_suggestions',
			'anchor_insertion',
			'graph_badges',
			'undo_redo',
			'active_block_detection',
		);
	}
}
