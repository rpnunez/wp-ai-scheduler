<?php
/**
 * Pending Review List Table
 *
 * Renders the Content → Pending Review tab as a WP_List_Table subclass so it
 * gains native WordPress Screen Options: a free-form "Drafts per page" control
 * and show/hide toggles for the non-essential columns (Type, Created). The Post
 * and Actions columns are always shown.
 *
 * Bulk actions keep using the existing AJAX toolbar (admin-post-review.js) rather
 * than WP_List_Table's native bulk form, so get_bulk_actions() is intentionally
 * empty; the per-row markup emitted here matches the selectors that JS binds to.
 *
 * @package AI_Post_Scheduler
 * @since   4.x
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Pending_Review_List_Table
 */
class AIPS_Pending_Review_List_Table extends AIPS_List_Table {

	/**
	 * Screen Options per-page user-meta key. Shared with the registration in
	 * AIPS_Admin_Menu::setup_content_screen_options() so saving and reading agree.
	 *
	 * @var string
	 */
	const PER_PAGE_OPTION = 'aips_pending_review_per_page';

	/**
	 * Default drafts shown per page.
	 *
	 * @var int
	 */
	const PER_PAGE_DEFAULT = 20;

	/**
	 * @var AIPS_Post_Review_Repository
	 */
	protected $repository;

	/**
	 * Controller used for shared formatting helpers (format_source()).
	 *
	 * @var AIPS_Generated_Posts_Controller|null
	 */
	protected $controller;

	/**
	 * Total matching drafts across all pages (for the footer count badge).
	 *
	 * @var int
	 */
	protected $total_items = 0;

	/**
	 * Pre-resolved date/time format for the Created column.
	 *
	 * @var string
	 */
	protected $datetime_format = '';

	/**
	 * Constructor.
	 *
	 * @param array<string,mixed> $args Configuration. Supports 'controller' and
	 *                                  'repository' for dependency injection.
	 */
	public function __construct($args = array()) {
		$this->controller = isset($args['controller']) ? $args['controller'] : null;
		$this->repository = isset($args['repository']) ? $args['repository'] : new AIPS_Post_Review_Repository();

		$this->datetime_format = get_option('date_format') . ' ' . get_option('time_format');

		parent::__construct(wp_parse_args($args, array(
			'singular'  => 'draft',
			'plural'    => 'drafts',
			'page_slug' => 'aips-generated-posts',
			'tab_slug'  => 'aips-pending-review',
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
			'cb'      => '<input type="checkbox" />',
			'title'   => esc_html__('Post', 'ai-post-scheduler'),
			'context' => esc_html__('Context', 'ai-post-scheduler'),
			'type'    => esc_html__('Type', 'ai-post-scheduler'),
			'created' => esc_html__('Created', 'ai-post-scheduler'),
			'actions' => esc_html__('Actions', 'ai-post-scheduler'),
		);
	}

	/**
	 * Columns offered as show/hide toggles in Screen Options.
	 *
	 * Post (primary) and Actions are essential and deliberately omitted so they
	 * are always visible; the checkbox column is never toggleable.
	 *
	 * @return array<string,string>
	 */
	public function get_hideable_columns() {
		return array(
			'context' => esc_html__('Context', 'ai-post-scheduler'),
			'type'    => esc_html__('Type', 'ai-post-scheduler'),
			'created' => esc_html__('Created', 'ai-post-scheduler'),
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
	 * Bulk actions handled by the custom AJAX toolbar, not the native form.
	 *
	 * @return array<string,string>
	 */
	protected function get_bulk_actions() {
		return array();
	}

	/**
	 * Append the table class the admin-post-review CSS targets.
	 *
	 * @return string[]
	 */
	protected function get_table_classes() {
		$classes   = parent::get_table_classes();
		$classes[] = 'aips-post-review-table';
		return $classes;
	}

	/**
	 * The tab template renders pagination in its own toolbar (like the History
	 * table), so the default top/bottom tablenav is suppressed.
	 *
	 * @param string $which 'top' or 'bottom'.
	 * @return void
	 */
	protected function display_tablenav($which) {
	}

	/**
	 * Total matching drafts across all pages.
	 *
	 * @return int
	 */
	public function get_total_items() {
		return (int) $this->total_items;
	}

	/**
	 * Load the current page of draft posts, honouring filters and the per-page
	 * Screen Option.
	 *
	 * @return void
	 */
	public function prepare_items() {
		$this->_column_headers = $this->get_column_info();

		$per_page     = max(1, (int) $this->get_items_per_page(self::PER_PAGE_OPTION, self::PER_PAGE_DEFAULT));
		$current_page = $this->get_pagenum();

		$search      = isset($_REQUEST['s']) ? sanitize_text_field(wp_unslash($_REQUEST['s'])) : '';
		$template_id = isset($_GET['template_id']) ? absint($_GET['template_id']) : 0;
		$post_type   = isset($_GET['post_type']) ? sanitize_key(wp_unslash($_GET['post_type'])) : '';

		$query = array(
			'page'        => $current_page,
			'per_page'    => $per_page,
			'search'      => $search,
			'template_id' => $template_id,
			'post_type'   => $post_type,
		);
		$result = $this->repository->get_draft_posts($query);

		// If the requested page overshot the end (e.g. after bulk actions emptied
		// it), fall back to the last page instead of showing an empty page.
		if (empty($result['items']) && $current_page > 1 && !empty($result['pages'])) {
			$query['page'] = (int) $result['pages'];
			$result        = $this->repository->get_draft_posts($query);
		}

		$items = !empty($result['items']) ? $result['items'] : array();
		foreach ($items as $item) {
			$item->created_at_formatted = AIPS_DateTime::formatRelativeOrAbsolute($item->created_at, $this->datetime_format);
		}

		$this->items       = $items;
		$this->total_items = isset($result['total']) ? (int) $result['total'] : 0;

		$this->set_pagination_args(array(
			'total_items' => $this->total_items,
			'per_page'    => $per_page,
			'total_pages' => $per_page > 0 ? (int) ceil($this->total_items / $per_page) : 1,
		));
	}

	/**
	 * Wrap each row with the post/history data attributes the JS relies on.
	 *
	 * @param object $item Draft row object.
	 * @return void
	 */
	public function single_row($item) {
		printf(
			'<tr data-post-id="%d" data-history-id="%d">',
			absint($item->post_id),
			absint($item->id)
		);
		$this->single_row_columns($item);
		echo '</tr>';
	}

	/**
	 * Row selection checkbox.
	 *
	 * @param object $item Draft row object.
	 * @return string
	 */
	protected function column_cb($item) {
		return sprintf(
			'<input type="checkbox" class="aips-post-checkbox" value="%1$d" data-post-id="%1$d" data-history-id="%2$d" aria-label="%3$s" />',
			absint($item->post_id),
			absint($item->id),
			esc_attr__('Select post', 'ai-post-scheduler')
		);
	}

	/**
	 * Post title column: edit link and preview link.
	 *
	 * @param object $item Draft row object.
	 * @return string
	 */
	protected function column_title($item) {
		$title = $item->post_title ?: ($item->generated_title ?: __('Untitled', 'ai-post-scheduler'));

		$html  = sprintf(
			'<a href="%s" class="cell-primary" target="_blank">%s</a>',
			esc_url(get_edit_post_link($item->post_id)),
			esc_html($title)
		);
		$html .= sprintf(
			'<a href="%s" class="aips-view-post-link" target="_blank" rel="noopener" title="%s" aria-label="%s"><span class="dashicons dashicons-external" aria-hidden="true"></span></a>',
			esc_url(get_preview_post_link($item->post_id)),
			esc_attr__('View post', 'ai-post-scheduler'),
			esc_attr__('View post', 'ai-post-scheduler')
		);

		return $html;
	}

	/**
	 * Context column: the author/topic/template source line.
	 *
	 * @param object $item Draft row object.
	 * @return string
	 */
	protected function column_context($item) {
		$source = ($this->controller && method_exists($this->controller, 'format_source'))
			? $this->controller->format_source($item)
			: '';

		return '<span class="aips-cell-source">' . esc_html($source) . '</span>';
	}

	/**
	 * Post type badge column.
	 *
	 * @param object $item Draft row object.
	 * @return string
	 */
	protected function column_type($item) {
		// The wp_posts type is authoritative: history rows written before the
		// post_type column existed have it NULL.
		$item_post_type = !empty($item->wp_post_type) ? $item->wp_post_type : ($item->post_type ?? '');
		$post_type_obj  = !empty($item_post_type) ? get_post_type_object($item_post_type) : null;
		$label          = $post_type_obj ? $post_type_obj->labels->singular_name : ($item_post_type ?: '—');

		return '<span class="aips-badge aips-badge-neutral">' . esc_html($label) . '</span>';
	}

	/**
	 * Created-at column.
	 *
	 * @param object $item Draft row object.
	 * @return string
	 */
	protected function column_created($item) {
		$formatted = isset($item->created_at_formatted)
			? $item->created_at_formatted
			: AIPS_DateTime::formatRelativeOrAbsolute($item->created_at, $this->datetime_format);

		return '<div class="cell-meta">' . esc_html($formatted) . '</div>';
	}

	/**
	 * Row actions column: publish button + overflow menu.
	 *
	 * Markup mirrors the former tab-pending-review.php template so the existing
	 * delegated handlers (admin-post-review.js + admin.js overflow menus) bind
	 * without change.
	 *
	 * @param object $item Draft row object.
	 * @return string
	 */
	protected function column_actions($item) {
		$post_id    = absint($item->post_id);
		$history_id = absint($item->id);
		$menu_id    = 'aips-review-row-actions-' . $post_id;

		$history_url = AIPS_Admin_Menu_Helper::get_page_url('history', array_filter(array(
			'history_id' => $history_id ? $history_id : 0,
			'post_id'    => $post_id ? $post_id : 0,
		)));

		ob_start();
		?>
		<div class="cell-actions">
			<div class="aips-row-action-group">
				<button type="button"
					class="aips-btn aips-btn-sm aips-btn-primary aips-publish-post"
					data-post-id="<?php echo esc_attr($post_id); ?>"
					title="<?php esc_attr_e('Publish this post', 'ai-post-scheduler'); ?>">
					<span class="dashicons dashicons-upload"></span>
					<?php esc_html_e('Publish', 'ai-post-scheduler'); ?>
				</button>
				<button type="button" class="aips-btn aips-btn-sm aips-btn-secondary aips-row-action-overflow-toggle"
					aria-haspopup="true"
					aria-expanded="false"
					aria-controls="<?php echo esc_attr($menu_id); ?>"
					title="<?php esc_attr_e('More actions', 'ai-post-scheduler'); ?>">
					<span class="screen-reader-text"><?php esc_html_e('More actions', 'ai-post-scheduler'); ?></span>
				</button>
			</div>
			<div id="<?php echo esc_attr($menu_id); ?>" class="aips-row-action-menu" hidden>
				<button type="button" class="aips-row-action-item aips-edit-post"
					data-edit-url="<?php echo esc_url(get_edit_post_link($post_id)); ?>"
					title="<?php esc_attr_e('Edit this post', 'ai-post-scheduler'); ?>">
					<span class="dashicons dashicons-edit"></span>
					<span><?php esc_html_e('Edit', 'ai-post-scheduler'); ?></span>
				</button>
				<button type="button" class="aips-row-action-item aips-preview-post"
					data-post-id="<?php echo esc_attr($post_id); ?>"
					title="<?php esc_attr_e('Preview this post', 'ai-post-scheduler'); ?>">
					<span class="dashicons dashicons-visibility"></span>
					<span><?php esc_html_e('Preview', 'ai-post-scheduler'); ?></span>
				</button>
				<button type="button" class="aips-row-action-item aips-ai-edit-btn"
					data-post-id="<?php echo esc_attr($post_id); ?>"
					data-history-id="<?php echo esc_attr($history_id); ?>"
					title="<?php esc_attr_e('AI Edit - Regenerate components', 'ai-post-scheduler'); ?>">
					<span class="dashicons dashicons-admin-customizer"></span>
					<span><?php esc_html_e('AI Edit', 'ai-post-scheduler'); ?></span>
				</button>
				<a class="aips-row-action-item aips-open-history-modal"
					href="<?php echo esc_url($history_url); ?>"
					data-history-id="<?php echo esc_attr($history_id); ?>"
					data-post-id="<?php echo esc_attr($post_id); ?>"
					title="<?php esc_attr_e('View history for this post', 'ai-post-scheduler'); ?>">
					<span class="dashicons dashicons-backup"></span>
					<span><?php esc_html_e('View History', 'ai-post-scheduler'); ?></span>
				</a>
				<button type="button" class="aips-row-action-item aips-regenerate-post"
					data-history-id="<?php echo esc_attr($history_id); ?>"
					data-post-id="<?php echo esc_attr($post_id); ?>"
					title="<?php esc_attr_e('Regenerate this post', 'ai-post-scheduler'); ?>">
					<span class="dashicons dashicons-update"></span>
					<span><?php esc_html_e('Re-generate', 'ai-post-scheduler'); ?></span>
				</button>
			</div>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * Default fallback for any unmapped column.
	 *
	 * @param object $item        Draft row object.
	 * @param string $column_name Column key.
	 * @return string
	 */
	protected function column_default($item, $column_name) {
		return isset($item->$column_name) ? esc_html($item->$column_name) : '';
	}
}
