<?php
/**
 * Sources List Table Class
 *
 * Renders the Automations → Sources tab as a WP_List_Table subclass so it gains
 * server-side filtering (group, status, search), native pagination, and Screen
 * Options (sources per page and column show/hide).
 *
 * Bulk actions keep using the existing toolbar in templates/admin/sources.php, so
 * get_bulk_actions() is intentionally empty; the per-row markup emitted here matches
 * the selectors that admin-sources.js binds to.
 *
 * @package AI_Post_Scheduler
 * @since   4.x
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Sources_List_Table
 */
class AIPS_Sources_List_Table extends AIPS_List_Table {

	/**
	 * Screen Options per-page user-meta key. Shared with the registration in
	 * AIPS_Admin_Menu::setup_automations_screen_options().
	 *
	 * @var string
	 */
	const PER_PAGE_OPTION = 'aips_sources_per_page';

	/**
	 * Default sources shown per page.
	 *
	 * @var int
	 */
	const PER_PAGE_DEFAULT = 20;

	/**
	 * @var AIPS_Sources_Repository
	 */
	protected $repository;

	/**
	 * @var AIPS_Sources_Data_Repository
	 */
	protected $data_repository;

	/**
	 * Source group terms (id/name) available for the group filter.
	 *
	 * @var array<int,object>
	 */
	protected $source_groups = array();

	/**
	 * Map of group term ID => group name.
	 *
	 * @var array<int,string>
	 */
	protected $group_name_map = array();

	/**
	 * Map of source ID => group term IDs.
	 *
	 * @var array<int,int[]>
	 */
	protected $term_ids_map = array();

	/**
	 * Map of source ID => archived content count.
	 *
	 * @var array<int,int>
	 */
	protected $content_count_map = array();

	/**
	 * Total sources in the table before any filtering.
	 *
	 * @var int
	 */
	protected $unfiltered_total = 0;

	/**
	 * Total sources matching the active filters across all pages.
	 *
	 * @var int
	 */
	protected $total_items = 0;

	/**
	 * Active filter values read from the request.
	 *
	 * @var array{group:int,status:string,search:string}
	 */
	protected $filters = array(
		'group'  => 0,
		'status' => '',
		'search' => '',
	);

	/**
	 * Pre-resolved date/time format for the last-fetched tooltip.
	 *
	 * @var string
	 */
	protected $datetime_format = '';

	/**
	 * Constructor.
	 *
	 * @param array<string,mixed> $args Configuration. Supports 'repository' and
	 *                                  'data_repository' for dependency injection.
	 */
	public function __construct($args = array()) {
		$this->repository      = isset($args['repository']) ? $args['repository'] : new AIPS_Sources_Repository();
		$this->data_repository = isset($args['data_repository']) ? $args['data_repository'] : new AIPS_Sources_Data_Repository();

		$this->datetime_format = get_option('date_format') . ' ' . get_option('time_format');

		unset($args['repository'], $args['data_repository']);

		parent::__construct(wp_parse_args($args, array(
			'singular'  => 'source',
			'plural'    => 'sources',
			'page_slug' => 'aips-automations',
			'tab_slug'  => 'sources',
			'ajax'      => false,
		)));
	}

	/**
	 * Columns rendered by the table.
	 *
	 * @return array<string,string>
	 */
	public function get_columns() {
		return array(
			'cb'      => '<input type="checkbox" id="aips-sources-cb-all" />',
			'url'     => esc_html__('URL', 'ai-post-scheduler'),
			'label'   => esc_html__('Label', 'ai-post-scheduler'),
			'groups'  => esc_html__('Groups', 'ai-post-scheduler'),
			'content' => esc_html__('Content', 'ai-post-scheduler'),
			'actions' => esc_html__('Actions', 'ai-post-scheduler'),
		);
	}

	/**
	 * Columns offered as show/hide toggles in Screen Options.
	 *
	 * URL (primary) and Actions are essential and deliberately omitted so they are
	 * always visible; the checkbox column is never toggleable.
	 *
	 * @return array<string,string>
	 */
	public function get_hideable_columns() {
		return array(
			'label'   => esc_html__('Label', 'ai-post-scheduler'),
			'groups'  => esc_html__('Groups', 'ai-post-scheduler'),
			'content' => esc_html__('Content', 'ai-post-scheduler'),
		);
	}

	/**
	 * Primary (always-visible) column.
	 *
	 * @return string
	 */
	protected function get_default_primary_column_name() {
		return 'url';
	}

	/**
	 * Bulk actions are handled by the custom toolbar, not the native form.
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
		$classes[] = 'aips-sources-table';
		return $classes;
	}

	/**
	 * Total sources matching the active filters across all pages.
	 *
	 * @return int
	 */
	public function get_total_items() {
		return (int) $this->total_items;
	}

	/**
	 * Total sources before filtering.
	 *
	 * @return int
	 */
	public function get_unfiltered_total() {
		return (int) $this->unfiltered_total;
	}

	/**
	 * Source group terms for the group filter dropdown.
	 *
	 * @return array<int,object>
	 */
	public function get_source_groups() {
		return $this->source_groups;
	}

	/**
	 * Map of group term ID => group name.
	 *
	 * @return array<int,string>
	 */
	public function get_group_name_map() {
		return $this->group_name_map;
	}

	/**
	 * Active filter values.
	 *
	 * @return array{group:int,status:string,search:string}
	 */
	public function get_filters() {
		return $this->filters;
	}

	/**
	 * Whether any filter is currently applied.
	 *
	 * @return bool
	 */
	public function has_active_filters() {
		return $this->filters['group'] > 0 || '' !== $this->filters['status'] || '' !== $this->filters['search'];
	}

	/**
	 * Read the filter values from the request.
	 *
	 * @return array{group:int,status:string,search:string}
	 */
	protected function read_filters() {
		$status = isset($_GET['status']) ? sanitize_key(wp_unslash($_GET['status'])) : '';
		if (!in_array($status, array('active', 'inactive'), true)) {
			$status = '';
		}

		return array(
			'group'  => isset($_GET['group']) ? absint(wp_unslash($_GET['group'])) : 0,
			'status' => $status,
			'search' => isset($_REQUEST['s']) ? sanitize_text_field(wp_unslash($_REQUEST['s'])) : '',
		);
	}

	/**
	 * Load the current page of sources, honouring filters and the per-page
	 * Screen Option.
	 *
	 * @return void
	 */
	public function prepare_items() {
		$this->_column_headers = $this->get_column_info();
		$this->filters         = $this->read_filters();

		$groups = get_terms(array(
			'taxonomy'   => 'aips_source_group',
			'hide_empty' => false,
		));
		$this->source_groups  = is_wp_error($groups) ? array() : $groups;
		$this->group_name_map = array();
		foreach ($this->source_groups as $group) {
			$this->group_name_map[(int) $group->term_id] = $group->name;
		}

		$all_sources            = $this->repository->get_all(false);
		$this->unfiltered_total = count($all_sources);

		$all_ids                = array_map(function ($source) {
			return (int) $source->id;
		}, $all_sources);
		$this->term_ids_map     = $this->repository->get_term_ids_for_sources($all_ids);

		$filtered = array();
		foreach ($all_sources as $source) {
			if ($this->matches_filters($source)) {
				$filtered[] = $source;
			}
		}

		$per_page     = max(1, (int) $this->get_items_per_page(self::PER_PAGE_OPTION, self::PER_PAGE_DEFAULT));
		$current_page = $this->get_pagenum();
		$total_items  = count($filtered);
		$total_pages  = (int) ceil($total_items / $per_page);

		// If the requested page overshot the end (e.g. after deleting rows), fall
		// back to the last page instead of showing an empty page.
		if ($total_pages > 0 && $current_page > $total_pages) {
			$current_page = $total_pages;
		}

		$this->items       = array_slice($filtered, ($current_page - 1) * $per_page, $per_page);
		$this->total_items = $total_items;

		$page_ids                = array_map(function ($source) {
			return (int) $source->id;
		}, $this->items);
		$this->content_count_map = !empty($page_ids) ? $this->data_repository->get_counts_by_source_ids($page_ids) : array();

		$this->set_pagination_args(array(
			'total_items' => $total_items,
			'per_page'    => $per_page,
			'total_pages' => $total_pages,
		));
	}

	/**
	 * Whether a source passes the active group/status/search filters.
	 *
	 * @param object $source Source row.
	 * @return bool
	 */
	protected function matches_filters($source) {
		$src_id = (int) $source->id;

		if ($this->filters['group'] > 0) {
			$term_ids = isset($this->term_ids_map[$src_id]) ? array_map('intval', $this->term_ids_map[$src_id]) : array();
			if (!in_array($this->filters['group'], $term_ids, true)) {
				return false;
			}
		}

		if ('active' === $this->filters['status'] && empty($source->is_active)) {
			return false;
		}
		if ('inactive' === $this->filters['status'] && !empty($source->is_active)) {
			return false;
		}

		if ('' !== $this->filters['search']) {
			$needle   = function_exists('mb_strtolower') ? mb_strtolower($this->filters['search']) : strtolower($this->filters['search']);
			$haystack = implode(' ', array(
				isset($source->label) ? (string) $source->label : '',
				isset($source->url) ? (string) $source->url : '',
				isset($source->description) ? (string) $source->description : '',
			));
			$haystack = function_exists('mb_strtolower') ? mb_strtolower($haystack) : strtolower($haystack);
			if (false === strpos($haystack, $needle)) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Emit the row with the data attributes the edit modal reads.
	 *
	 * @param object $item Source row.
	 * @return void
	 */
	public function single_row($item) {
		$src_id   = (int) $item->id;
		$term_ids = isset($this->term_ids_map[$src_id]) ? $this->term_ids_map[$src_id] : array();

		printf(
			'<tr data-source-id="%1$s" data-url="%2$s" data-label="%3$s" data-description="%4$s" data-active="%5$s" data-fetch-interval="%6$s" data-term-ids="%7$s">',
			esc_attr($item->id),
			esc_attr($item->url),
			esc_attr($item->label),
			esc_attr($item->description),
			esc_attr($item->is_active),
			esc_attr(isset($item->fetch_interval) ? $item->fetch_interval : ''),
			esc_attr(wp_json_encode($term_ids))
		);
		$this->single_row_columns($item);
		echo '</tr>';
	}

	/**
	 * Row selection checkbox.
	 *
	 * @param object $item Source row.
	 * @return string
	 */
	protected function column_cb($item) {
		return sprintf(
			'<input type="checkbox" class="aips-source-cb" value="%1$d" aria-label="%2$s" />',
			absint($item->id),
			esc_attr__('Select source', 'ai-post-scheduler')
		);
	}

	/**
	 * URL column (primary).
	 *
	 * @param object $item Source row.
	 * @return string
	 */
	protected function column_url($item) {
		return sprintf(
			'<a href="%1$s" class="cell-primary" target="_blank" rel="noopener noreferrer">%2$s</a>',
			esc_url($item->url),
			esc_html($item->url)
		);
	}

	/**
	 * Label column: label text plus the Active/Inactive status badge.
	 *
	 * @param object $item Source row.
	 * @return string
	 */
	protected function column_label($item) {
		$label = !empty($item->label) ? $item->label : '—';

		if (!empty($item->is_active)) {
			$badge = $this->render_status_badge(__('Active', 'ai-post-scheduler'), 'success', 'dashicons-yes-alt');
		} else {
			$badge = $this->render_status_badge(__('Inactive', 'ai-post-scheduler'), 'neutral', 'dashicons-minus');
		}

		return '<span class="aips-source-label-text">' . esc_html($label) . '</span> <span class="aips-source-status-badge">' . $badge . '</span>';
	}

	/**
	 * Groups column.
	 *
	 * @param object $item Source row.
	 * @return string
	 */
	protected function column_groups($item) {
		$src_id   = (int) $item->id;
		$term_ids = isset($this->term_ids_map[$src_id]) ? $this->term_ids_map[$src_id] : array();

		$html = '';
		foreach ($term_ids as $term_id) {
			$term_id = (int) $term_id;
			if (isset($this->group_name_map[$term_id])) {
				$html .= '<span class="aips-badge aips-badge-neutral aips-source-group-badge">' . esc_html($this->group_name_map[$term_id]) . '</span>';
			}
		}

		if ('' === $html) {
			$html = '<span class="cell-meta">' . esc_html__('No groups: this source cannot be selected for generation.', 'ai-post-scheduler') . '</span>';
		}

		return $html;
	}

	/**
	 * Content column: icon-only last-attempt indicator plus the archived content count.
	 *
	 * @param object $item Source row.
	 * @return string
	 */
	protected function column_content($item) {
		$src_id          = (int) $item->id;
		$status          = isset($item->last_fetch_status) ? (string) $item->last_fetch_status : '';
		$last_error      = isset($item->last_fetch_error) ? (string) $item->last_fetch_error : '';
		$last_fetched_at = isset($item->last_fetched_at) ? $item->last_fetched_at : '';
		$count           = isset($this->content_count_map[$src_id]) ? (int) $this->content_count_map[$src_id] : 0;
		$interval        = isset($item->fetch_interval) ? (string) $item->fetch_interval : '';

		$when = '';
		if (is_numeric($last_fetched_at) && (int) $last_fetched_at > 0) {
			$when = wp_date($this->datetime_format, (int) $last_fetched_at);
		} elseif (is_string($last_fetched_at) && '' !== $last_fetched_at && '0000-00-00 00:00:00' !== $last_fetched_at) {
			$when = $last_fetched_at;
		}

		$html = '<div class="aips-source-content-cell">';

		if ('failed' === $status) {
			$tip = __('Last attempt failed', 'ai-post-scheduler');
			if ('' !== $last_error) {
				$tip .= ': ' . $last_error;
			}
			if ('' !== $when) {
				$tip .= ' (' . $when . ')';
			}
			$html .= '<span class="aips-source-fetch-indicator aips-source-fetch-failed" title="' . esc_attr($tip) . '" aria-label="' . esc_attr($tip) . '"><span class="dashicons dashicons-dismiss" aria-hidden="true"></span></span>';
		} elseif ('success' === $status) {
			$tip = __('Last attempt succeeded', 'ai-post-scheduler');
			if ('' !== $when) {
				$tip .= ' (' . $when . ')';
			}
			$html .= '<span class="aips-source-fetch-indicator aips-source-fetch-success" title="' . esc_attr($tip) . '" aria-label="' . esc_attr($tip) . '"><span class="dashicons dashicons-yes-alt" aria-hidden="true"></span></span>';
		}

		if ($count > 0) {
			$html .= '<span class="aips-badge aips-badge-success"' . ('' !== $when ? ' title="' . esc_attr($when) . '"' : '') . '>';
			$html .= '<span class="dashicons dashicons-archive" aria-hidden="true"></span> ';
			$html .= esc_html(sprintf(
				/* translators: %d = number of archived content snapshots */
				_n('%d content', '%d content', $count, 'ai-post-scheduler'),
				$count
			));
			$html .= '</span>';
		} elseif ('pending' === $status) {
			$html .= $this->render_status_badge(__('Pending', 'ai-post-scheduler'), 'neutral', 'dashicons-clock');
		} else {
			$html .= '<span class="cell-meta">' . ('' !== $interval ? esc_html__('Scheduled', 'ai-post-scheduler') : '—') . '</span>';
		}

		$html .= '</div>';

		return $html;
	}

	/**
	 * Actions column: Edit | Activate/Deactivate | Delete button group, then
	 * Fetch Now and Manage Data, all on one line.
	 *
	 * @param object $item Source row.
	 * @return string
	 */
	protected function column_actions($item) {
		$id        = absint($item->id);
		$is_active = !empty($item->is_active);
		$data_url  = AIPS_Admin_Menu_Helper::get_page_url('aips-source-data', array('source_id' => $id));
		$toggle    = $is_active ? __('Deactivate', 'ai-post-scheduler') : __('Activate', 'ai-post-scheduler');

		$html  = '<div class="cell-actions aips-source-actions">';
		$html .= '<div class="aips-row-action-group">';
		$html .= '<button type="button" class="aips-btn aips-btn-sm aips-btn-secondary aips-edit-source" data-id="' . esc_attr($id) . '" title="' . esc_attr__('Edit', 'ai-post-scheduler') . '"><span class="dashicons dashicons-edit" aria-hidden="true"></span><span class="screen-reader-text">' . esc_html__('Edit', 'ai-post-scheduler') . '</span></button>';
		$html .= '<button type="button" class="aips-btn aips-btn-sm aips-btn-secondary aips-toggle-source" data-id="' . esc_attr($id) . '" data-active="' . esc_attr($item->is_active) . '" title="' . esc_attr($toggle) . '"><span class="dashicons ' . ($is_active ? 'dashicons-hidden' : 'dashicons-visibility') . '" aria-hidden="true"></span><span class="screen-reader-text">' . esc_html($toggle) . '</span></button>';
		$html .= '<button type="button" class="aips-btn aips-btn-sm aips-btn-danger aips-delete-source" data-id="' . esc_attr($id) . '" title="' . esc_attr__('Delete', 'ai-post-scheduler') . '"><span class="dashicons dashicons-trash" aria-hidden="true"></span><span class="screen-reader-text">' . esc_html__('Delete', 'ai-post-scheduler') . '</span></button>';
		$html .= '</div>';
		$html .= '<button type="button" class="aips-btn aips-btn-sm aips-btn-secondary aips-fetch-source-now" data-id="' . esc_attr($id) . '" title="' . esc_attr__('Fetch content now', 'ai-post-scheduler') . '"><span class="dashicons dashicons-download" aria-hidden="true"></span><span class="screen-reader-text">' . esc_html__('Fetch Now', 'ai-post-scheduler') . '</span></button>';
		$html .= '<a class="aips-btn aips-btn-sm aips-btn-secondary" href="' . esc_url($data_url) . '" title="' . esc_attr__('Manage fetched source data', 'ai-post-scheduler') . '"><span class="dashicons dashicons-archive" aria-hidden="true"></span><span>' . esc_html__('Manage Data', 'ai-post-scheduler') . '</span></a>';
		$html .= '</div>';

		return $html;
	}

	/**
	 * Empty state: no sources at all vs. no sources matching the filters.
	 *
	 * @return void
	 */
	public function no_items() {
		if ($this->has_active_filters()) {
			AIPS_Admin_UI_Primitives::render_empty_state(array(
				'icon'    => 'dashicons-search',
				'title'   => __('No Sources Found', 'ai-post-scheduler'),
				'message' => __('No sources match your filters.', 'ai-post-scheduler'),
			));
			return;
		}

		?>
		<div class="aips-empty-state" id="aips-sources-empty">
			<div class="dashicons dashicons-admin-links aips-empty-state-icon" aria-hidden="true"></div>
			<h3 class="aips-empty-state-title"><?php esc_html_e('No Trusted Sources Yet', 'ai-post-scheduler'); ?></h3>
			<p class="aips-empty-state-description"><?php esc_html_e('Add URLs that the AI should reference when generating post content. Assign them to Source Groups so Authors and Templates can selectively include them in their prompts.', 'ai-post-scheduler'); ?></p>
			<div class="aips-empty-state-actions">
				<button type="button" class="aips-btn aips-btn-primary" id="aips-add-source-empty-btn">
					<?php esc_html_e('Add Your First Source', 'ai-post-scheduler'); ?>
				</button>
			</div>
		</div>
		<?php
	}
}
