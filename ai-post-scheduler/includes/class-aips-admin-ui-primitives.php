<?php
/**
 * Admin UI Primitives Helper Class
 *
 * Provides standardized, reusable render helpers and components for the WordPress admin
 * UI to ensure visual consistency, accessibility, and clean token-based layouts across all 8 hubs.
 *
 * @package AI_Post_Scheduler
 * @since   3.7.0
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Admin_UI_Primitives
 *
 * Centralizes UI component rendering for admin pages.
 */
class AIPS_Admin_UI_Primitives {

	/**
	 * Path to partials directory.
	 *
	 * @var string|null
	 */
	private static $partials_dir = null;

	/**
	 * Get the absolute path to the admin partials directory.
	 *
	 * @return string
	 */
	public static function get_partials_dir() {
		if (null === self::$partials_dir) {
			self::$partials_dir = defined('AIPS_PLUGIN_DIR')
				? AIPS_PLUGIN_DIR . 'templates/admin/partials/'
				: dirname(__DIR__, 2) . '/templates/admin/partials/';
		}
		return self::$partials_dir;
	}

	/**
	 * Safely include a partial template with arguments and optional callback.
	 *
	 * @param string        $partial_name Partial filename without directory.
	 * @param array         $args         Component configuration.
	 * @param callable|null $content_callback Optional callback.
	 * @return void
	 */
	public static function include_partial($partial_name, $args = array(), $content_callback = null) {
		$partial = self::get_partials_dir() . $partial_name;
		if (file_exists($partial)) {
			include $partial;
		} else {
			if (class_exists('AIPS_Logger')) {
				AIPS_Logger::log('UI Primitive partial not found: ' . $partial_name, 'warning');
			}
			echo '<div class="notice notice-error aips-error-fallback"><p>' . sprintf(esc_html__('UI component %s could not be loaded.', 'ai-post-scheduler'), esc_html($partial_name)) . '</p></div>';
		}
	}

	/**
	 * Render a complete Hub Shell (Wrap + Header + Rail Navigation + Main Stage).
	 *
	 * @param array<string, mixed>|AIPS_Admin_Page_Context $args Shell configuration parameters:
	 *  - 'wrap_class': (string) CSS class for the outermost wrap. Default 'wrap aips-wrap'.
	 *  - 'header': (array|AIPS_Admin_Page_Context) Parameters passed to `render_page_header()`.
	 *  - 'rail': (array) Parameters passed to `render_rail()`.
	 *  - 'content': (string) Raw HTML or template output for the main content stage.
	 *  - 'content_callback': (callable|null) Optional callback to render main stage content.
	 * @param callable|null                                $content_callback Optional callback if not provided in $args.
	 * @return void
	 */
	public static function render_hub_shell($args = array(), $content_callback = null) {
		self::include_partial('admin-hub-shell.php', $args, $content_callback);
	}

	/**
	 * Render a Breadcrumb Navigation Trail.
	 *
	 * @param array<int, array{label:string, url?:string, icon?:string}> $breadcrumbs Array of breadcrumb items.
	 * @return void
	 */
	public static function render_breadcrumbs($breadcrumbs = array()) {
		self::include_partial('admin-breadcrumbs.php', (array) $breadcrumbs);
	}

	/**
	 * Render a standardized Admin Page Header with Contextual Breadcrumbs, Summary Chips, and Actions.
	 *
	 * @param array<string, mixed>|AIPS_Admin_Page_Context $args Header configuration:
	 *  - 'title': (string) Page / Hub title (e.g. 'Content', 'Studio', 'Automations').
	 *  - 'context_title': (string) Optional active section/tab/view title (e.g. 'Schedules').
	 *  - 'icon': (string) Dashicon slug (e.g. 'dashicons-admin-post').
	 *  - 'icon_color': (string) Optional icon color style/token.
	 *  - 'description': (string) Short descriptive subtitle.
	 *  - 'breadcrumbs': (array) Optional array of breadcrumb items.
	 *  - 'summary_items': (array) Optional array of micro-metric summary chips.
	 *  - 'badges': (array) Optional array of badge definitions for status/counts.
	 *  - 'actions': (array) Array of action button arrays (label, icon, url/id, class, type, data_attrs).
	 * @return void
	 */
	public static function render_page_header($args = array()) {
		if ($args instanceof AIPS_Admin_Page_Context) {
			$args = $args->to_header_args();
		}
		self::include_partial('admin-page-header.php', (array) $args);
	}

	/**
	 * Render a Vertical Navigation Rail Sidebar.
	 *
	 * @param array<string, mixed> $args Rail configuration:
	 *  - 'aria_label': (string) Navigation aria label.
	 *  - 'items': (array) List of rail items:
	 *      - 'key': (string) Tab slug or ID.
	 *      - 'label': (string) Primary item label.
	 *      - 'icon': (string) Dashicon slug.
	 *      - 'description': (string) Short subtitle.
	 *      - 'url': (string) Optional direct URL (defaults to '#tab_slug').
	 *      - 'badge': (string|int) Optional badge counter or label.
	 *      - 'badge_class': (string) Optional badge CSS class.
	 *      - 'active': (bool) Whether the item is currently selected.
	 *      - 'special': (bool) Highlight as special accent item.
	 * @return void
	 */
	public static function render_rail($args = array()) {
		self::include_partial('admin-rail.php', (array) $args);
	}

	/**
	 * Render an Action Toolbar (Buttons, Search, Filter Dropdowns).
	 *
	 * @param array<string, mixed> $args Toolbar configuration:
	 *  - 'class': (string) Additional CSS class.
	 *  - 'actions': (array) Array of button definitions.
	 *  - 'search': (array) Search input definition (id, name, placeholder, value).
	 *  - 'filters': (array) Filter dropdown definitions.
	 * @return void
	 */
	public static function render_action_toolbar($args = array()) {
		self::include_partial('admin-action-toolbar.php', (array) $args);
	}

	/**
	 * Render a Content Card / Panel.
	 *
	 * @param array<string, mixed> $args Card configuration:
	 *  - 'id': (string) HTML ID attribute.
	 *  - 'class': (string) Additional CSS classes.
	 *  - 'title': (string) Card title.
	 *  - 'icon': (string) Optional title dashicon.
	 *  - 'description': (string) Optional subtitle.
	 *  - 'badge': (array) Optional status badge array.
	 *  - 'actions': (array) Array of card header action buttons.
	 *  - 'body': (string) HTML body content (if callback not provided).
	 *  - 'body_class': (string) Additional class for panel body.
	 *  - 'footer': (string) Optional card footer content.
	 * @param callable|null        $body_callback Optional callback to render card body.
	 * @return void
	 */
	public static function render_card($args = array(), $body_callback = null) {
		self::include_partial('admin-card.php', (array) $args, $body_callback);
	}

	/**
	 * Render an Empty State message with icon, title, description, and action CTA.
	 *
	 * @param array<string, mixed> $args Empty state configuration:
	 *  - 'icon': (string) Dashicon slug (e.g. 'dashicons-schedule').
	 *  - 'title': (string) Heading message.
	 *  - 'message': (string) Explanation or instruction.
	 *  - 'cta_label': (string) Button text.
	 *  - 'cta_url': (string) Button URL.
	 *  - 'cta_id': (string) Optional button ID.
	 *  - 'cta_icon': (string) Optional button icon.
	 *  - 'cta_class': (string) Optional button CSS class (defaults to 'aips-btn aips-btn-primary').
	 *  - 'secondary_cta': (array) Optional secondary action button.
	 * @return void
	 */
	public static function render_empty_state($args = array()) {
		self::include_partial('admin-empty-state.php', (array) $args);
	}

	/**
	 * Render a Status Badge.
	 *
	 * @param array<string, mixed> $args Badge parameters:
	 *  - 'label': (string) Badge label text.
	 *  - 'type': (string) Status type: 'success', 'warning', 'error', 'danger', 'info', 'neutral', 'secondary'.
	 *  - 'icon': (string) Optional dashicon slug.
	 *  - 'class': (string) Additional CSS class.
	 * @return string HTML string of badge.
	 */
	public static function render_status_badge($args = array()) {
		$label = isset($args['label']) ? $args['label'] : '';
		$type  = isset($args['type']) ? $args['type'] : 'neutral';
		$icon  = isset($args['icon']) ? $args['icon'] : '';
		$class = isset($args['class']) ? ' ' . $args['class'] : '';

		$type_class_map = array(
			'success'   => 'aips-badge-success',
			'warning'   => 'aips-badge-warning',
			'error'     => 'aips-badge-danger',
			'danger'    => 'aips-badge-danger',
			'info'      => 'aips-badge-info',
			'secondary' => 'aips-badge-secondary',
			'neutral'   => 'aips-badge-neutral',
		);

		$badge_class = isset($type_class_map[$type]) ? $type_class_map[$type] : 'aips-badge-neutral';

		$html = '<span class="aips-badge ' . esc_attr($badge_class . $class) . '">';
		if (!empty($icon)) {
			$html .= '<span class="dashicons ' . esc_attr($icon) . '" aria-hidden="true"></span> ';
		}
		$html .= esc_html($label);
		$html .= '</span>';

		return $html;
	}

	/**
	 * Render an Error Fallback box for failed sections or catch blocks.
	 *
	 * @param array<string, mixed> $args Error parameters:
	 *  - 'title': (string) Error headline.
	 *  - 'message': (string) Detailed error message.
	 *  - 'retry_url': (string) Optional retry link URL.
	 *  - 'retry_label': (string) Optional retry button label.
	 *  - 'details': (string) Optional technical diagnostics (stack trace, exception details).
	 * @return void
	 */
	public static function render_error_fallback($args = array()) {
		self::include_partial('admin-error-fallback.php', (array) $args);
	}
}
