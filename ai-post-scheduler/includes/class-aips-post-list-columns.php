<?php
/**
 * WordPress Post List Columns & Orphan Filter Integration
 *
 * Adds sortable Internal Links column and Orphan Content filter view
 * to native WordPress edit.php and edit.php?post_type=page tables.
 *
 * @package AI_Post_Scheduler
 * @since 3.7.0
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Post_List_Columns
 */
class AIPS_Post_List_Columns {

	/**
	 * Column identifier.
	 */
	const COLUMN_KEY = 'aips_internal_links';

	/**
	 * Query argument for orphan filter view.
	 */
	const ORPHAN_QUERY_ARG = 'aips_orphan';

	/**
	 * Initialize hooks for post and page list tables.
	 */
	public function __construct() {
		// Register columns on posts and pages
		add_filter('manage_posts_columns', array($this, 'add_column'));
		add_filter('manage_pages_columns', array($this, 'add_column'));

		// Render column content
		add_action('manage_posts_custom_column', array($this, 'render_column'), 10, 2);
		add_action('manage_pages_custom_column', array($this, 'render_column'), 10, 2);

		// Batch prime link counts for the posts in current query (0 N+1 queries)
		add_filter('the_posts', array($this, 'prime_post_counts'), 10, 2);

		// Sortable columns
		add_filter('manage_edit-post_sortable_columns', array($this, 'register_sortable_column'));
		add_filter('manage_edit-page_sortable_columns', array($this, 'register_sortable_column'));

		// Quick filter view: Orphans (N)
		add_filter('views_edit-post', array($this, 'add_orphan_view_filter'));
		add_filter('views_edit-page', array($this, 'add_orphan_view_filter'));

		// Modify SQL query when filtering orphans or sorting by internal links
		add_filter('posts_clauses', array($this, 'modify_posts_clauses'), 10, 2);

		// Enqueue inline styles on edit tables
		add_action('admin_print_styles-edit.php', array($this, 'print_column_styles'));
	}

	/**
	 * Determine whether this feature is active for the current screen.
	 *
	 * @param string $post_type Optional post type.
	 * @return bool
	 */
	protected function is_supported_post_type($post_type = '') {
		if (empty($post_type)) {
			$screen = get_current_screen();
			$post_type = $screen ? $screen->post_type : 'post';
		}
		return in_array($post_type, array('post', 'page'), true);
	}

	/**
	 * Add Internal Links column header.
	 *
	 * @param array $columns Existing table columns.
	 * @return array Modified columns.
	 */
	public function add_column(array $columns) {
		if (!current_user_can('edit_posts')) {
			return $columns;
		}

		$new_columns = array();
		$inserted    = false;

		foreach ($columns as $key => $label) {
			$new_columns[$key] = $label;
			// Insert after tags or categories or author
			if (!$inserted && in_array($key, array('tags', 'categories', 'author'), true)) {
				$new_columns[self::COLUMN_KEY] = __('Internal Links', 'ai-post-scheduler');
				$inserted = true;
			}
		}

		if (!$inserted) {
			$new_columns[self::COLUMN_KEY] = __('Internal Links', 'ai-post-scheduler');
		}

		return $new_columns;
	}

	/**
	 * Render column cell markup.
	 *
	 * @param string $column_name Column key.
	 * @param int    $post_id     Post ID.
	 * @return void
	 */
	public function render_column($column_name, $post_id) {
		if (self::COLUMN_KEY !== $column_name) {
			return;
		}

		AIPS_SEO_Link_Metrics_Component::render_post_badge((int) $post_id);
	}

	/**
	 * Batch prime link counts for all posts returned in the main query.
	 *
	 * @param array    $posts Array of WP_Post objects.
	 * @param WP_Query $query Main query object.
	 * @return array Unchanged posts array.
	 */
	public function prime_post_counts(array $posts, $query) {
		if (!is_admin() || empty($posts)) {
			return $posts;
		}

		$post_ids = array();
		foreach ($posts as $p) {
			if ($p instanceof WP_Post) {
				$post_ids[] = (int) $p->ID;
			}
		}

		if (!empty($post_ids)) {
			AIPS_SEO_Link_Metrics_Component::prime_batch_counts($post_ids);
		}

		return $posts;
	}

	/**
	 * Register internal links column as sortable.
	 *
	 * @param array $sortable Existing sortable columns.
	 * @return array
	 */
	public function register_sortable_column(array $sortable) {
		$sortable[self::COLUMN_KEY] = self::COLUMN_KEY;
		return $sortable;
	}

	/**
	 * Add Orphans view filter link to the table views bar.
	 *
	 * @param array $views Existing view links.
	 * @return array
	 */
	public function add_orphan_view_filter(array $views) {
		if (!current_user_can('edit_posts')) {
			return $views;
		}

		$screen    = get_current_screen();
		$post_type = $screen ? $screen->post_type : 'post';
		$count     = AIPS_SEO_Link_Metrics_Component::get_orphan_count($post_type);

		$is_active = !empty($_GET[self::ORPHAN_QUERY_ARG]);
		$class     = $is_active ? 'class="current" aria-current="page"' : '';
		$url       = add_query_arg(
			array(
				'post_type'            => $post_type,
				self::ORPHAN_QUERY_ARG => '1',
			),
			admin_url('edit.php')
		);

		$views['aips_orphans'] = sprintf(
			'<a href="%1$s" %2$s>%3$s <span class="count">(%4$s)</span></a>',
			esc_url($url),
			$class,
			esc_html__('⚠️ Orphans', 'ai-post-scheduler'),
			number_format_i18n($count)
		);

		return $views;
	}

	/**
	 * Modify query clauses for orphan filtering and sort ordering.
	 *
	 * @param array    $clauses Query clauses.
	 * @param WP_Query $query   Query instance.
	 * @return array
	 */
	public function modify_posts_clauses(array $clauses, $query) {
		if (!is_admin() || !$query->is_main_query()) {
			return $clauses;
		}

		global $wpdb;
		$links_table = $wpdb->prefix . 'aips_content_links';

		// Handle Orphan Filter View
		if (!empty($_GET[self::ORPHAN_QUERY_ARG])) {
			$clauses['join']  .= " LEFT JOIN {$links_table} AS aips_orphan_check ON ({$wpdb->posts}.ID = aips_orphan_check.target_id)";
			$clauses['where'] .= " AND aips_orphan_check.id IS NULL";
		}

		// Handle Sortable Internal Links Column
		if (isset($_GET['orderby']) && self::COLUMN_KEY === $_GET['orderby']) {
			$order = (isset($_GET['order']) && 'asc' === strtolower($_GET['order'])) ? 'ASC' : 'DESC';
			$clauses['join']    .= " LEFT JOIN (SELECT target_id, COUNT(*) AS in_count FROM {$links_table} GROUP BY target_id) AS aips_sort_in ON ({$wpdb->posts}.ID = aips_sort_in.target_id)";
			$clauses['orderby']  = "COALESCE(aips_sort_in.in_count, 0) {$order}, {$wpdb->posts}.post_date DESC";
		}

		return $clauses;
	}

	/**
	 * Print column CSS styles on edit.php screen.
	 *
	 * @return void
	 */
	public function print_column_styles() {
		AIPS_SEO_Link_Metrics_Component::render_inline_styles();
	}
}
