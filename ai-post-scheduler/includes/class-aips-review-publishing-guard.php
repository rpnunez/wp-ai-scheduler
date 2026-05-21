<?php
/**
 * Guard against publishing generated posts that still require review.
 *
 * @package AI_Post_Scheduler
 * @since 2.6.0
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Review_Publishing_Guard
 */
class AIPS_Review_Publishing_Guard {

	/**
	 * Register the publish-state guard.
	 */
	public function __construct() {
		add_filter('wp_insert_post_data', array($this, 'guard_post_data'), 10, 2);
	}

	/**
	 * Prevent direct publication until review has been approved.
	 *
	 * @param array $data    Sanitized post data.
	 * @param array $postarr Raw post data.
	 * @return array
	 */
	public function guard_post_data($data, $postarr) {
		$post_id = isset($postarr['ID']) ? absint($postarr['ID']) : 0;
		$post_status = isset($data['post_status']) ? (string) $data['post_status'] : '';
		$post_type = isset($data['post_type']) ? (string) $data['post_type'] : '';

		if ($post_id <= 0 || 'publish' !== $post_status || ('' !== $post_type && 'post' !== $post_type)) {
			return $data;
		}

		$review_required = (string) get_post_meta($post_id, 'aips_review_required', true);
		$review_state = (string) get_post_meta($post_id, 'aips_review_state', true);

		if ('true' === $review_required && 'approved' !== $review_state) {
			$data['post_status'] = 'draft';
		}

		return $data;
	}
}
