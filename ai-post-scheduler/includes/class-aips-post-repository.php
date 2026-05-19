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

	/**
	 * Get post ID by exact title match or specific meta key/value.
	 *
	 * @param string      $title Exact post title.
	 * @param string|null $meta_key Meta key.
	 * @param string|null $meta_value Meta value.
	 * @param string      $post_type Post type.
	 * @return int|null Matching post ID.
	 */
	public function get_post_id_by_title_or_meta($title, $meta_key = null, $meta_value = null, $post_type = 'post') {
		$title = sanitize_text_field((string) $title);
		if ($title !== '') {
			$post_id = $this->find_existing_generated_post($title, $post_type);
			if ($post_id) {
				return $post_id;
			}
		}

		if (empty($meta_key) || $meta_value === null || $meta_value === '') {
			return null;
		}

		$post_id = $this->wpdb->get_var($this->wpdb->prepare(
			"SELECT p.ID
			FROM {$this->wpdb->posts} p
			INNER JOIN {$this->wpdb->postmeta} pm ON p.ID = pm.post_id
			WHERE p.post_type = %s
			AND p.post_status NOT IN ('trash', 'auto-draft', 'inherit')
			AND pm.meta_key = %s
			AND pm.meta_value = %s
			LIMIT 1",
			$post_type,
			sanitize_key($meta_key),
			(string) $meta_value
		));

		return !empty($post_id) ? (int) $post_id : null;
	}
}
