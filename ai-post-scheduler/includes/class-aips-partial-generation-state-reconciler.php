<?php
/**
 * Partial Generation State Reconciler
 *
 * Keeps partial-generation metadata in sync when posts are updated.
 *
 * @package AI_Post_Scheduler
 * @since 1.7.0
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Partial_Generation_State_Reconciler
 */
class AIPS_Partial_Generation_State_Reconciler {

	/**
	 * @var AIPS_Post_Manager
	 */
	private $post_manager;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->post_manager = new AIPS_Post_Manager();

		add_action('save_post', array($this, 'on_save_post'), 20, 3);
		add_action('aips_post_components_updated', array($this, 'on_post_components_updated'), 10, 3);
	}

	/**
	 * Reconcile metadata after post updates.
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Post object.
	 * @param bool    $update  Whether this is an existing post being updated.
	 * @return void
	 */
	public function on_save_post($post_id, $post, $update) {
		if (!$update || empty($post_id) || !$post || $post->post_type !== 'post') {
			return;
		}

		if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) {
			return;
		}

		if (!metadata_exists('post', $post_id, 'aips_post_generation_component_statuses')) {
			return;
		}

		$has_generation_meta = '' !== (string) get_post_meta($post_id, 'aips_post_generation_component_statuses', true)
			|| '' !== (string) get_post_meta($post_id, 'aips_post_generation_incomplete', true)
			|| '' !== (string) get_post_meta($post_id, 'aips_post_generation_had_partial', true);

		if (!$has_generation_meta) {
			return;
		}

		$statuses = $this->post_manager->reconcile_generation_status_meta_from_post($post_id);
		if (is_array($statuses)) {
			do_action('aips_partial_generation_state_reconciled', $post_id, $statuses, 'save_post');
		}
	}

	/**
	 * Reconcile metadata after AI Edit component saves.
	 *
	 * @param int   $post_id            Post ID.
	 * @param array $updated_components Updated component names (title/excerpt/content/featured_image).
	 * @param array $components         Raw component payload from request.
	 * @return void
	 */
	public function on_post_components_updated($post_id, $updated_components, $components) {
		$post_id = absint($post_id);
		if (!$post_id) {
			return;
		}

		$updated_components = is_array($updated_components) ? $updated_components : array();
		$components = is_array($components) ? $components : array();

		$overrides = array();

		if (in_array('title', $updated_components, true) && array_key_exists('title', $components)) {
			$overrides['post_title'] = '' !== trim(wp_strip_all_tags((string) $components['title']));
		}

		if (in_array('excerpt', $updated_components, true) && array_key_exists('excerpt', $components)) {
			$overrides['post_excerpt'] = '' !== trim(wp_strip_all_tags((string) $components['excerpt']));
		}

		if (in_array('content', $updated_components, true) && array_key_exists('content', $components)) {
			$overrides['post_content'] = '' !== trim(wp_strip_all_tags((string) $components['content']));
		}

		if (array_key_exists('featured_image_id', $components)) {
			$overrides['featured_image'] = absint($components['featured_image_id']) > 0;
		}

		$statuses = $this->post_manager->reconcile_generation_status_meta_from_post($post_id, $overrides);
		if (is_array($statuses)) {
			do_action('aips_partial_generation_state_reconciled', $post_id, $statuses, 'aips_post_components_updated');
		}
	}
}
