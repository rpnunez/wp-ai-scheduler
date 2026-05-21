<?php
/**
 * Mock post review repository for limited-mode tests.
 *
 * @package AI_Post_Scheduler
 */

if (!defined('ABSPATH')) {
	exit;
}

class AIPS_Post_Review_Repository_Mock {

	/**
	 * @var string
	 */
	private $table_name;

	public function __construct() {
		global $wpdb;
		$this->table_name = $wpdb->prefix . 'aips_history';
	}

	/**
	 * Build draft post results from test-store rows.
	 *
	 * @param array $args Query arguments.
	 * @return array
	 */
	public function get_draft_posts($args = array()) {
		global $aips_test_db_rows, $test_posts, $aips_test_meta;

		$args = wp_parse_args($args, array(
			'per_page' => 20,
			'page' => 1,
			'search' => '',
			'template_id' => 0,
			'orderby' => 'created_at',
			'order' => 'DESC',
		));

		$rows = isset($aips_test_db_rows[$this->table_name]) ? array_values($aips_test_db_rows[$this->table_name]) : array();
		$items = array();

		foreach ($rows as $row) {
			if (!isset($row['status']) || 'completed' !== $row['status'] || empty($row['post_id'])) {
				continue;
			}

			$post_id = (int) $row['post_id'];
			$post = isset($test_posts[$post_id]) ? $test_posts[$post_id] : get_post($post_id);

			if (!$post || 'draft' !== $post->post_status) {
				continue;
			}

			if (!empty($args['template_id']) && (int) $row['template_id'] !== (int) $args['template_id']) {
				continue;
			}

			$search = isset($args['search']) ? (string) $args['search'] : '';
			if ('' !== $search) {
				$haystack = strtolower((string) (isset($row['generated_title']) ? $row['generated_title'] : '') . ' ' . (string) $post->post_title);
				if (false === strpos($haystack, strtolower($search))) {
					continue;
				}
			}

			$item = (object) array_merge($row, array(
				'post_title' => isset($post->post_title) ? $post->post_title : '',
				'post_modified' => isset($post->post_modified) ? $post->post_modified : '',
				'wp_post_author' => isset($post->post_author) ? $post->post_author : 0,
				'quality_score' => isset($aips_test_meta[$post_id]['aips_quality_score']) ? (string) $aips_test_meta[$post_id]['aips_quality_score'] : '',
				'quality_flags' => isset($aips_test_meta[$post_id]['aips_quality_flags']) ? (string) $aips_test_meta[$post_id]['aips_quality_flags'] : '',
				'review_required' => isset($aips_test_meta[$post_id]['aips_review_required']) ? (string) $aips_test_meta[$post_id]['aips_review_required'] : '',
				'review_required_reason' => isset($aips_test_meta[$post_id]['aips_review_required_reason']) ? (string) $aips_test_meta[$post_id]['aips_review_required_reason'] : '',
				'review_state' => isset($aips_test_meta[$post_id]['aips_review_state']) ? (string) $aips_test_meta[$post_id]['aips_review_state'] : '',
			));

			$items[] = $item;
		}

		$orderby = in_array($args['orderby'], array('created_at', 'completed_at', 'post_title'), true) ? $args['orderby'] : 'created_at';
		$order = strtoupper($args['order']) === 'ASC' ? 1 : -1;

		usort($items, function($a, $b) use ($orderby, $order) {
			$a_value = isset($a->{$orderby}) ? $a->{$orderby} : '';
			$b_value = isset($b->{$orderby}) ? $b->{$orderby} : '';

			return $order * strcmp((string) $a_value, (string) $b_value);
		});

		$total = count($items);
		$per_page = (int) $args['per_page'];
		$page = max(1, (int) $args['page']);

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

	/**
	 * Count draft posts.
	 *
	 * @return int
	 */
	public function get_draft_count() {
		$result = $this->get_draft_posts(array(
			'per_page' => -1,
			'page' => 1,
			'search' => '',
			'template_id' => 0,
			'orderby' => 'created_at',
			'order' => 'DESC',
		));

		return (int) $result['total'];
	}
}
