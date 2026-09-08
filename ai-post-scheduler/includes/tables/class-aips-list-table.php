<?php
/**
 * AIPS List Table Base Class
 *
 * Abstract foundation extending WordPress core WP_List_Table to provide
 * standardized semantic markup, pagination, search, views, and bulk actions
 * across all admin hubs.
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

		parent::__construct(wp_parse_args($args, array(
			'singular' => 'item',
			'plural'   => 'items',
			'ajax'     => false,
			'screen'   => !empty($this->screen_id) ? $this->screen_id : null,
		)));
	}

	/**
	 * Prepares data items for table display.
	 *
	 * Must be implemented by child classes to populate `$this->items` and call `$this->set_pagination_args()`.
	 *
	 * @return void
	 */
	abstract public function prepare_items();

	/**
	 * Render table empty state when no records are found.
	 *
	 * @return void
	 */
	public function no_items() {
		echo '<div class="aips-list-table-empty">';
		AIPS_Admin_UI_Primitives::render_empty_state(array(
			'icon'        => 'dashicons-database',
			'title'       => sprintf(__('No %s Found', 'ai-post-scheduler'), esc_html($this->_args['plural'])),
			'message'     => __('There are currently no items matching your criteria.', 'ai-post-scheduler'),
		));
		echo '</div>';
	}

	/**
	 * Display the list table with wrapper form and security nonces.
	 *
	 * @return void
	 */
	public function display_page() {
		$form_id = 'aips-' . sanitize_key($this->_args['plural']) . '-form';
		?>
		<div class="aips-list-table-wrap">
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
