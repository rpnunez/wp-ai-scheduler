<?php
/**
 * AIPS List Table Base Class
 *
 * Abstract foundation extending WordPress core WP_List_Table to provide
 * standardized semantic markup, pagination, Screen Options, search, views,
 * bulk actions, progressive disclosure drawers, and filter persistence across all admin hubs.
 *
 * @package AI_Post_Scheduler
 * @since   3.7.0
 */

if (!defined('ABSPATH')) {
	exit;
}

if (!class_exists('WP_List_Table')) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * Class AIPS_List_Table
 */
abstract class AIPS_List_Table extends WP_List_Table {

	/**
	 * Unique screen identifier.
	 *
	 * @var string
	 */
	protected $screen_id = '';

	/**
	 * Current page slug.
	 *
	 * @var string
	 */
	protected $page_slug = '';

	/**
	 * Current active tab slug.
	 *
	 * @var string
	 */
	protected $tab_slug = '';

	/**
	 * Whether Screen Options filter has been initialized.
	 *
	 * @var bool
	 */
	private static $screen_options_initialized = false;

	/**
	 * Constructor.
	 *
	 * @param array<string, mixed> $args Configuration arguments.
	 */
	public function __construct($args = array()) {
		if (isset($args['page_slug'])) {
			$this->page_slug = sanitize_key($args['page_slug']);
		}
		if (isset($args['tab_slug'])) {
			$this->tab_slug = sanitize_key($args['tab_slug']);
		}
		if (isset($args['screen_id'])) {
			$this->screen_id = sanitize_key($args['screen_id']);
		}

		self::init_screen_options();

		parent::__construct(wp_parse_args($args, array(
			'singular' => 'item',
			'plural'   => 'items',
			'ajax'     => false,
			'screen'   => !empty($this->screen_id) ? $this->screen_id : null,
		)));
	}

	/**
	 * Initialize WordPress Screen Options hooks.
	 *
	 * @return void
	 */
	public static function init_screen_options() {
		if (self::$screen_options_initialized) {
			return;
		}
		self::$screen_options_initialized = true;

		add_filter('set-screen-option', array(__CLASS__, 'save_screen_options'), 10, 3);
	}

	/**
	 * Filter callback to persist AIPS Screen Options in user metadata.
	 *
	 * @param mixed  $status Option status.
	 * @param string $option Option name.
	 * @param mixed  $value  Option value submitted.
	 * @return mixed Sanitized integer or original status.
	 */
	public static function save_screen_options($status, $option, $value) {
		if (strpos($option, 'aips_') === 0) {
			return (int) $value;
		}
		return $status;
	}

	/**
	 * Register Screen Options (items per page & column visibility) for an admin screen.
	 *
	 * @param string               $screen_id      Screen identifier.
	 * @param array<string,string> $columns        Optional column key/label array.
	 * @param array<string,mixed>  $per_page_args  Optional arguments for per_page option.
	 * @return void
	 */
	public static function register_screen_options($screen_id, $columns = array(), $per_page_args = array()) {
		self::init_screen_options();

		$screen = get_current_screen();
		if (!$screen) {
			return;
		}

		if (!empty($columns)) {
			register_column_headers($screen_id, $columns);
		}

		$clean_id = sanitize_key(str_replace('-', '_', $screen_id));
		$defaults = array(
			'label'   => __('Items per page', 'ai-post-scheduler'),
			'default' => 20,
			'option'  => 'aips_' . $clean_id . '_per_page',
		);

		add_screen_option('per_page', wp_parse_args($per_page_args, $defaults));
	}

	/**
	 * Get the current per_page option value for this table, reading from user meta Screen Options.
	 *
	 * @param int $default Default fallback items per page.
	 * @return int Items per page.
	 */
	public function get_per_page($default = 20) {
		$screen_key = !empty($this->screen_id) ? $this->screen_id : $this->_args['plural'];
		$clean_id   = sanitize_key(str_replace('-', '_', $screen_key));
		$option     = 'aips_' . $clean_id . '_per_page';
		$per_page   = (int) $this->get_items_per_page($option, $default);

		return ($per_page > 0) ? $per_page : $default;
	}

	/**
	 * Prepares data items for table display.
	 *
	 * Subclasses should override this method to populate `$this->items` and call `$this->set_pagination_args()`.
	 *
	 * @return void
	 */
	public function prepare_items() {
		$this->_column_headers = $this->get_column_info();
	}

	/**
	 * Get column headers, hidden columns, sortable columns, and primary column.
	 *
	 * Ensures columns and user-toggled hidden columns are properly loaded from
	 * WordPress Screen Options.
	 *
	 * @return array<int, mixed>
	 */
	protected function get_column_info() {
		if (isset($this->_column_headers)) {
			return $this->_column_headers;
		}

		$columns  = $this->get_columns();
		$hidden   = !empty($this->screen) ? get_hidden_columns($this->screen) : array();
		$sortable = $this->get_sortable_columns();
		$primary  = $this->get_default_primary_column_name();

		$this->_column_headers = array($columns, $hidden, $sortable, $primary);

		return $this->_column_headers;
	}

	/**
	 * Render table empty state when no records are found.
	 *
	 * @return void
	 */
	public function no_items() {
		echo '<div class="aips-list-table-empty">';
		AIPS_Admin_UI_Primitives::render_empty_state(array(
			'icon'    => 'dashicons-database',
			'title'   => sprintf(__('No %s Found', 'ai-post-scheduler'), esc_html($this->_args['plural'])),
			'message' => __('There are currently no items matching your criteria.', 'ai-post-scheduler'),
		));
		echo '</div>';
	}

	/**
	 * Optional hook for rendering expandable row details in a progressive disclosure drawer.
	 *
	 * Subclasses override this method to return HTML markup for expandable row drawers.
	 *
	 * @param object|array<string,mixed> $item Current row item.
	 * @return string HTML detail markup or empty string.
	 */
	public function single_row_details($item) {
		return '';
	}

	/**
	 * Generates content for a single row with optional progressive disclosure details drawer.
	 *
	 * @param object|array<string,mixed> $item The current item.
	 * @return void
	 */
	public function single_row($item) {
		$row_id = is_object($item) ? ($item->id ?? '') : ($item['id'] ?? '');

		echo '<tr id="' . esc_attr('aips-row-' . $row_id) . '">';
		$this->single_row_columns($item);
		echo '</tr>';

		$details = $this->single_row_details($item);
		if (!empty($details)) {
			$columns    = $this->get_columns();
			$total_cols = count($columns);
			echo '<tr class="aips-row-details" id="' . esc_attr('aips-details-' . $row_id) . '" hidden>';
			echo '<td colspan="' . esc_attr($total_cols) . '">';
			echo '<div class="aips-row-details-content">';
			echo $details; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo '</div>';
			echo '</td>';
			echo '</tr>';
		}
	}

	/**
	 * Display the list table with wrapper form, security nonces, and filter persistence attributes.
	 *
	 * @return void
	 */
	public function display_page() {
		$form_id  = 'aips-' . sanitize_key($this->_args['plural']) . '-form';
		$table_id = !empty($this->screen_id) ? $this->screen_id : $this->_args['plural'];
		?>
		<div class="aips-list-table-wrap" data-persist-filters="true" data-table-id="<?php echo esc_attr($table_id); ?>">
			<form id="<?php echo esc_attr($form_id); ?>" method="get" action="<?php echo esc_url(admin_url('admin.php')); ?>">
				<?php if (!empty($this->page_slug)) : ?>
					<input type="hidden" name="page" value="<?php echo esc_attr($this->page_slug); ?>" />
				<?php endif; ?>
				<?php if (!empty($this->tab_slug)) : ?>
					<input type="hidden" name="tab" value="<?php echo esc_attr($this->tab_slug); ?>" />
				<?php endif; ?>

				<?php
				$this->views();
				$this->search_box(
					sprintf(__('Search %s', 'ai-post-scheduler'), esc_html($this->_args['plural'])),
					'aips-' . sanitize_key($this->_args['singular']) . '-search'
				);
				$this->display();
				?>
			</form>
		</div>
		<?php
	}

	/**
	 * Render standard action button inside table cell.
	 *
	 * @param string               $label      Button label text.
	 * @param string               $class_name CSS class.
	 * @param string               $icon       Dashicon slug.
	 * @param string               $type       Button type: 'button' or 'link'.
	 * @param string               $url        URL if link.
	 * @param array<string, mixed> $attrs      Data attributes.
	 * @return string HTML button string.
	 */
	protected function render_action_button($label, $class_name = 'aips-btn aips-btn-sm aips-btn-secondary', $icon = '', $type = 'button', $url = '', $attrs = array()) {
		$attr_str = '';
		foreach ($attrs as $k => $v) {
			$attr_str .= ' ' . esc_attr($k) . '="' . esc_attr($v) . '"';
		}

		if ('link' === $type && !empty($url)) {
			$html = '<a href="' . esc_url($url) . '" class="' . esc_attr($class_name) . '"' . $attr_str . '>';
		} else {
			$html = '<button type="button" class="' . esc_attr($class_name) . '"' . $attr_str . '>';
		}

		if (!empty($icon)) {
			$html .= '<span class="dashicons ' . esc_attr($icon) . '" aria-hidden="true"></span> ';
		}
		$html .= esc_html($label);
		$html .= ('link' === $type) ? '</a>' : '</button>';

		return $html;
	}

	/**
	 * Render standard status badge.
	 *
	 * @param string $label Badge text.
	 * @param string $type  Badge type: 'success', 'warning', 'error', 'danger', 'info', 'neutral', 'secondary'.
	 * @param string $icon  Optional Dashicon slug.
	 * @return string HTML badge.
	 */
	protected function render_status_badge($label, $type = 'neutral', $icon = '') {
		return AIPS_Admin_UI_Primitives::render_status_badge(array(
			'label' => $label,
			'type'  => $type,
			'icon'  => $icon,
		));
	}
}
