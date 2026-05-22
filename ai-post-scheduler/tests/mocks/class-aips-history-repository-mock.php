<?php
/**
 * Mock history repository for limited-mode tests.
 *
 * @package AI_Post_Scheduler
 */

if (!defined('ABSPATH')) {
	exit;
}

class AIPS_History_Repository_Mock {

	/**
	 * @var string
	 */
	private $table_name;

	public function __construct() {
		global $wpdb;
		$this->table_name = $wpdb->prefix . 'aips_history';
	}

	/**
	 * Build partial generation results from test-store rows.
	 *
	 * @param array $args Query arguments.
	 * @return array
	 */
	public function get_partial_generations($args = array()) {
		global $aips_test_db_rows, $aips_test_meta, $test_posts;

		$args = wp_parse_args($args, array(
			'per_page' => 20,
			'page' => 1,
			'search' => '',
			'template_id' => 0,
			'author_id' => 0,
		));

		$rows = isset($aips_test_db_rows[$this->table_name]) ? array_values($aips_test_db_rows[$this->table_name]) : array();
		$items = array();

		foreach ($rows as $row) {
			if (!isset($row['status']) || 'completed' !== $row['status'] || empty($row['post_id'])) {
				continue;
			}

			$post_id = (int) $row['post_id'];
			$post = isset($test_posts[$post_id]) ? $test_posts[$post_id] : get_post($post_id);
			$is_incomplete = isset($aips_test_meta[$post_id]['aips_post_generation_incomplete']) ? (string) $aips_test_meta[$post_id]['aips_post_generation_incomplete'] : '';
			$had_partial = isset($aips_test_meta[$post_id]['aips_post_generation_had_partial']) ? (string) $aips_test_meta[$post_id]['aips_post_generation_had_partial'] : '';

			if ('true' !== $is_incomplete && 'true' !== $had_partial) {
				continue;
			}

			if (!empty($args['template_id']) && (int) $row['template_id'] !== (int) $args['template_id']) {
				continue;
			}

			if (!empty($args['author_id']) && (int) $row['author_id'] !== (int) $args['author_id']) {
				continue;
			}

			$search = isset($args['search']) ? (string) $args['search'] : '';
			if ('' !== $search) {
				$haystack = strtolower((string) (isset($row['generated_title']) ? $row['generated_title'] : '') . ' ' . (string) $post->post_title);
				if (false === strpos($haystack, strtolower($search))) {
					continue;
				}
			}

			$items[] = (object) array_merge($row, array(
				'post_title' => isset($post->post_title) ? $post->post_title : '',
				'post_status' => isset($post->post_status) ? $post->post_status : 'draft',
				'post_modified' => isset($post->post_modified) ? $post->post_modified : '',
				'post_date' => isset($post->post_date) ? $post->post_date : '',
				'is_currently_incomplete' => $is_incomplete,
				'component_statuses' => isset($aips_test_meta[$post_id]['aips_post_generation_component_statuses']) ? $aips_test_meta[$post_id]['aips_post_generation_component_statuses'] : '',
			));
		}

		$total = count($items);
		$per_page = isset($args['per_page']) ? (int) $args['per_page'] : 20;
		$page = max(1, isset($args['page']) ? (int) $args['page'] : 1);

		if ($per_page > 0) {
			$offset = ($page - 1) * $per_page;
			$items = array_slice($items, $offset, $per_page);
		}

		return array(
			'items' => $items,
			'total' => $total,
			'pages' => $per_page > 0 ? (int) ceil($total / $per_page) : 1,
			'current_page' => $page,
		);
	}
}
