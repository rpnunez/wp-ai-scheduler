<?php
if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Post_Repository
 *
 * Repository for WordPress post lookup operations used by generation flows.
 */
class AIPS_Post_Repository {

	/**
	 * @var wpdb
	 */
	private $wpdb;

	public function __construct() {
		global $wpdb;
		$this->wpdb = $wpdb;
	}

	/**
	 * Find existing generated post by exact title and post type.
	 *
	 * @param string $title Exact title.
	 * @param string $post_type Post type.
	 * @return int|null Existing post ID when found.
	 */
	public function find_existing_generated_post($title, $post_type = 'post') {
		$post_id = $this->wpdb->get_var($this->wpdb->prepare(
			"SELECT ID FROM {$this->wpdb->posts}
			WHERE post_type = %s
			AND post_title = %s
			AND post_status NOT IN ('trash', 'auto-draft', 'inherit')
			LIMIT 1",
			$post_type,
			$title
		));

		return !empty($post_id) ? (int) $post_id : null;
	}

}
