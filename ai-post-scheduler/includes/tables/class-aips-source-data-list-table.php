<?php
/**
 * Source Data List Table Class
 *
 * Renders the Manage Source Data page (fetched content snapshots for one source,
 * or for all sources in the global view) as a WP_List_Table subclass. Gains native
 * pagination and Screen Options (snapshots per page and column show/hide); the
 * History-style filter bar lives in templates/admin/source-data.php and submits a
 * GET form whose values this class reads.
 *
 * @package AI_Post_Scheduler
 * @since   4.x
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Source_Data_List_Table
 */
class AIPS_Source_Data_List_Table extends AIPS_List_Table {

	/**
	 * Screen Options per-page user-meta key. Shared with the registration in
	 * AIPS_Admin_Menu::setup_source_data_screen_options().
	 *
	 * @var string
	 */
	const PER_PAGE_OPTION = 'aips_source_data_per_page';

	/**
	 * Default snapshots shown per page.
	 *
	 * @var int
	 */
	const PER_PAGE_DEFAULT = 20;

	/**
	 * @var AIPS_Sources_Data_Repository
	 */
	protected $repository;

	/**
	 * Source being managed; 0 means the global (all sources) view.
	 *
	 * @var int
	 */
	protected $source_id = 0;

	/**
	 * Whether the table lists snapshots across all sources.
	 *
	 * @var bool
	 */
	protected $is_global_view = true;

	/**
	 * Total snapshots matching the active filters across all pages.
	 *
	 * @var int
	 */
	protected $total_items = 0;

	/**
	 * Active filters (keys match AIPS_Sources_Data_Repository filters).
	 *
	 * @var array<string,mixed>
	 */
	protected $filters = array();

	/**
	 * Active search term.
	 *
	 * @var string
	 */
	protected $search = '';

	/**
	 * Pre-resolved date/time format for the Fetch Date/Time column.
	 *
	 * @var string
	 */
	protected $datetime_format = '';

	/**
	 * Constructor.
	 *
	 * @param array<string,mixed> $args Configuration. Supports 'source_id' (0 = all
	 *                                  sources) and 'repository' for dependency injection.
	 */
	public function __construct($args = array()) {
		$this->source_id      = isset($args['source_id']) ? absint($args['source_id']) : 0;
		$this->is_global_view = $this->source_id <= 0;
		$this->repository     = isset($args['repository']) ? $args['repository'] : new AIPS_Sources_Data_Repository();

		$this->datetime_format = get_option('date_format') . ' ' . get_option('time_format');

		unset($args['source_id'], $args['repository']);

		parent::__construct(wp_parse_args($args, array(
			'singular'  => 'snapshot',
			'plural'    => 'snapshots',
			'page_slug' => 'aips-source-data',
			'ajax'      => false,
		)));

		$this->filters = $this->read_filters();
		$this->search  = isset($_GET['s']) ? sanitize_text_field(wp_unslash($_GET['s'])) : '';
	}

	/**
	 * Columns rendered by the table.
	 *
	 * @return array<string,string>
	 */
	public function get_columns() {
		$columns = array();

		if ($this->is_global_view) {
			$columns['source'] = esc_html__('Source', 'ai-post-scheduler');
		}

		$columns['title']   = esc_html__('Page Title', 'ai-post-scheduler');
		$columns['url']     = esc_html__('URL', 'ai-post-scheduler');
		$columns['status']  = esc_html__('Fetch Status', 'ai-post-scheduler');
		$columns['fetched'] = esc_html__('Fetch Date/Time', 'ai-post-scheduler');
		$columns['http']    = esc_html__('HTTP', 'ai-post-scheduler');
		$columns['used']    = esc_html__('Used', 'ai-post-scheduler');
		$columns['size']    = esc_html__('Size', 'ai-post-scheduler');
		$columns['actions'] = esc_html__('Actions', 'ai-post-scheduler');

		return $columns;
	}

	/**
	 * Columns offered as show/hide toggles in Screen Options.
	 *
	 * @return array<string,string>
	 */
	public function get_hideable_columns() {
		return array(
			'http' => esc_html__('HTTP', 'ai-post-scheduler'),
			'used' => esc_html__('Used', 'ai-post-scheduler'),
			'size' => esc_html__('Size', 'ai-post-scheduler'),
		);
	}

	/**
	 * Primary (always-visible) column.
	 *
	 * @return string
	 */
	protected function get_default_primary_column_name() {
		return 'title';
	}

	/**
	 * No bulk actions on this page.
	 *
	 * @return array<string,string>
	 */
	protected function get_bulk_actions() {
		return array();
	}

	/**
	 * Append the legacy table classes so existing admin CSS applies.
	 *
	 * @return string[]
	 */
	protected function get_table_classes() {
		$classes   = parent::get_table_classes();
		$classes[] = 'aips-table';
		$classes[] = 'aips-source-data-table';
		return $classes;
	}

	/**
	 * Total snapshots matching the active filters across all pages.
	 *
	 * @return int
	 */
	public function get_total_items() {
		return (int) $this->total_items;
	}

	/**
	 * Active structured filters.
	 *
	 * @return array<string,mixed>
	 */
	public function get_filters() {
		return $this->filters;
	}

	/**
	 * Active search term.
	 *
	 * @return string
	 */
	public function get_search() {
		return $this->search;
	}

	/**
	 * Whether any filter or search is currently applied.
	 *
	 * @return bool
	 */
	public function has_active_filters() {
		return '' !== $this->search
			|| '' !== $this->filters['fetch_status']
			|| $this->filters['http_status_class'] > 0
			|| '' !== $this->filters['fetched_after']
			|| '' !== $this->filters['fetched_before']
			|| $this->filters['min_char_count'] > 0
			|| $this->filters['max_char_count'] > 0
			|| !empty($this->filters['search_body_text'])
			|| ($this->is_global_view && $this->filters['source_id'] > 0);
	}

	/**
	 * Read the structured filters from the request.
	 *
	 * @return array<string,mixed>
	 */
	protected function read_filters() {
		return array(
			'fetch_status'      => isset($_GET['fetch_status']) ? sanitize_key(wp_unslash($_GET['fetch_status'])) : '',
			'http_status_class' => isset($_GET['http_status_class']) ? absint(wp_unslash($_GET['http_status_class'])) : 0,
			'fetched_after'     => isset($_GET['fetched_after']) ? sanitize_text_field(wp_unslash($_GET['fetched_after'])) : '',
			'fetched_before'    => isset($_GET['fetched_before']) ? sanitize_text_field(wp_unslash($_GET['fetched_before'])) : '',
			'min_char_count'    => isset($_GET['min_char_count']) ? absint(wp_unslash($_GET['min_char_count'])) : 0,
			'max_char_count'    => isset($_GET['max_char_count']) ? absint(wp_unslash($_GET['max_char_count'])) : 0,
			'search_body_text'  => !empty($_GET['search_body_text']),
			'source_id'         => isset($_GET['filter_source_id']) ? absint(wp_unslash($_GET['filter_source_id'])) : 0,
		);
	}

	/**
	 * Load the current page of snapshots, honouring filters and the per-page
	 * Screen Option.
	 *
	 * @return void
	 */
	public function prepare_items() {
		$this->_column_headers = $this->get_column_info();

		$per_page     = max(1, (int) $this->get_items_per_page(self::PER_PAGE_OPTION, self::PER_PAGE_DEFAULT));
		$current_page = $this->get_pagenum();

		$result = $this->query($per_page, $current_page);

		// If the requested page overshot the end (e.g. after deleting rows), fall
		// back to the last page instead of showing an empty page.
		if (empty($result['items']) && $current_page > 1 && !empty($result['pages'])) {
			$result = $this->query($per_page, (int) $result['pages']);
		}

		$this->items       = !empty($result['items']) ? $result['items'] : array();
		$this->total_items = isset($result['total']) ? (int) $result['total'] : 0;

		$this->set_pagination_args(array(
			'total_items' => $this->total_items,
			'per_page'    => $per_page,
			'total_pages' => (int) ceil($this->total_items / $per_page),
		));
	}

	/**
	 * Query one page of snapshots from the repository.
	 *
	 * @param int $per_page Rows per page.
	 * @param int $page     Page number.
	 * @return array{items:array,total:int,pages:int,current_page:int,per_page:int}
	 */
	protected function query($per_page, $page) {
		if ($this->is_global_view) {
			return $this->repository->get_paginated($this->search, $per_page, $page, $this->filters);
		}

		return $this->repository->get_paginated_by_source_id($this->source_id, $this->search, $per_page, $page, $this->filters);
	}

	/**
	 * Emit the row with the data attribute the View/Delete handlers read.
	 *
	 * @param object $item Snapshot row.
	 * @return void
	 */
	public function single_row($item) {
		printf('<tr data-source-data-id="%d">', isset($item->id) ? absint($item->id) : 0);
		$this->single_row_columns($item);
		echo '</tr>';
	}

	/**
	 * Source column (global view only).
	 *
	 * @param object $item Snapshot row.
	 * @return string
	 */
	protected function column_source($item) {
		$label = !empty($item->source_label)
			? $item->source_label
			: (!empty($item->source_url) ? $item->source_url : sprintf(__('Source #%d', 'ai-post-scheduler'), (int) $item->source_id));

		$html = '<strong>' . esc_html($label) . '</strong>';
		if (!empty($item->source_url)) {
			$html .= '<div class="cell-meta">' . esc_html($item->source_url) . '</div>';
		}

		return $html;
	}

	/**
	 * Page title column (primary).
	 *
	 * @param object $item Snapshot row.
	 * @return string
	 */
	protected function column_title($item) {
		$title = isset($item->page_title) ? trim((string) $item->page_title) : '';
		if ('' === $title) {
			return '<span class="cell-meta">—</span>';
		}

		return '<span class="cell-primary">' . esc_html($title) . '</span>';
	}

	/**
	 * URL column: host linking to the full URL.
	 *
	 * @param object $item Snapshot row.
	 * @return string
	 */
	protected function column_url($item) {
		$url = isset($item->url) ? trim((string) $item->url) : '';
		if ('' === $url) {
			return '<span class="cell-meta">—</span>';
		}

		$host = wp_parse_url($url, PHP_URL_HOST);

		return sprintf(
			'<a href="%1$s" target="_blank" rel="noopener noreferrer" title="%1$s">%2$s</a>',
			esc_url($url),
			esc_html($host ? $host : $url)
		);
	}

	/**
	 * Fetch status badge column.
	 *
	 * @param object $item Snapshot row.
	 * @return string
	 */
	protected function column_status($item) {
		$status = isset($item->fetch_status) ? sanitize_key((string) $item->fetch_status) : '';
		$status = '' !== $status ? $status : 'unknown';

		$type_map = array(
			'success' => 'success',
			'pending' => 'warning',
			'failed'  => 'error',
		);

		return $this->render_status_badge(ucfirst($status), isset($type_map[$status]) ? $type_map[$status] : 'neutral');
	}

	/**
	 * Fetch date/time column.
	 *
	 * @param object $item Snapshot row.
	 * @return string
	 */
	protected function column_fetched($item) {
		$fetched_at = isset($item->fetched_at) ? (int) $item->fetched_at : 0;
		$formatted  = $fetched_at ? wp_date($this->datetime_format, $fetched_at) : __('Not recorded', 'ai-post-scheduler');

		return esc_html($formatted);
	}

	/**
	 * HTTP status badge column.
	 *
	 * @param object $item Snapshot row.
	 * @return string
	 */
	protected function column_http($item) {
		$http = isset($item->http_status) ? (int) $item->http_status : 0;

		if ($http >= 200 && $http < 300) {
			$type = 'success';
		} elseif ($http >= 400) {
			$type = 'error';
		} elseif ($http > 0) {
			$type = 'warning';
		} else {
			$type = 'neutral';
		}

		return $this->render_status_badge($http > 0 ? (string) $http : __('N/A', 'ai-post-scheduler'), $type);
	}

	/**
	 * Times-used column.
	 *
	 * @param object $item Snapshot row.
	 * @return string
	 */
	protected function column_used($item) {
		return esc_html(number_format_i18n(isset($item->num_used) ? (int) $item->num_used : 0));
	}

	/**
	 * Stored size column (extracted text + raw HTML).
	 *
	 * @param object $item Snapshot row.
	 * @return string
	 */
	protected function column_size($item) {
		$text      = isset($item->extracted_text) ? (string) $item->extracted_text : '';
		$extracted = isset($item->extracted_text_bytes) ? (int) $item->extracted_text_bytes : strlen($text);
		$raw_html  = isset($item->raw_html_bytes) ? (int) $item->raw_html_bytes : 0;
		$bytes     = $extracted + $raw_html;
		$size_kb   = $bytes > 0 ? number_format_i18n($bytes / 1024, 1) : '0.0';

		return esc_html($size_kb . ' ' . __('KB', 'ai-post-scheduler'));
	}

	/**
	 * Actions column: View and Delete in one button group.
	 *
	 * @param object $item Snapshot row.
	 * @return string
	 */
	protected function column_actions($item) {
		$id = isset($item->id) ? absint($item->id) : 0;

		$html  = '<div class="aips-row-action-group">';
		$html .= '<button type="button" class="aips-btn aips-btn-sm aips-btn-primary aips-view-source-data" data-id="' . esc_attr($id) . '"><span class="dashicons dashicons-visibility" aria-hidden="true"></span> ' . esc_html__('View', 'ai-post-scheduler') . '</button>';
		$html .= '<button type="button" class="aips-btn aips-btn-sm aips-btn-danger aips-delete-source-data" data-id="' . esc_attr($id) . '"><span class="dashicons dashicons-trash" aria-hidden="true"></span> ' . esc_html__('Delete', 'ai-post-scheduler') . '</button>';
		$html .= '</div>';

		return $html;
	}

	/**
	 * Empty state.
	 *
	 * @return void
	 */
	public function no_items() {
		AIPS_Admin_UI_Primitives::render_empty_state(array(
			'icon'    => 'dashicons-archive',
			'title'   => __('No Source Data Found', 'ai-post-scheduler'),
			'message' => $this->has_active_filters()
				? __('No saved source data matches your filters.', 'ai-post-scheduler')
				: __('No saved source data has been captured yet.', 'ai-post-scheduler'),
		));
	}
}
