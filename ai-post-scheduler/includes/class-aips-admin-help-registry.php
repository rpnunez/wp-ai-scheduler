<?php
/**
 * Admin Help Registry Class
 *
 * Manages WordPress native contextual help tabs and help sidebars (WP_Screen::add_help_tab
 * and WP_Screen::set_help_sidebar) across all AI Post Scheduler admin screens.
 *
 * @package AI_Post_Scheduler
 * @since   3.7.20
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Admin_Help_Registry
 */
class AIPS_Admin_Help_Registry {

	/**
	 * Path to help partials directory.
	 *
	 * @var string|null
	 */
	private static $help_dir = null;

	/**
	 * Custom registered screen help tabs.
	 *
	 * @var array<string, array<int, array<string, mixed>>>
	 */
	private static $custom_tabs = array();

	/**
	 * Initialize help registry hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_action('current_screen', array(__CLASS__, 'setup_screen_help'));
	}

	/**
	 * Get absolute path to the admin help partials directory.
	 *
	 * @return string
	 */
	public static function get_help_dir() {
		if (null === self::$help_dir) {
			self::$help_dir = defined('AIPS_PLUGIN_DIR')
				? AIPS_PLUGIN_DIR . 'templates/admin/help/'
				: dirname(__DIR__) . '/templates/admin/help/';
		}
		return self::$help_dir;
	}

	/**
	 * Determine whether the current screen belongs to AI Post Scheduler.
	 *
	 * @param WP_Screen|null $screen Current WP_Screen object.
	 * @return bool True if screen belongs to AIPS, false otherwise.
	 */
	public static function is_aips_screen($screen = null) {
		if (!$screen instanceof WP_Screen) {
			return false;
		}

		$screen_id = $screen->id;
		return (
			false !== strpos($screen_id, 'aips') ||
			false !== strpos($screen_id, 'ai-post-scheduler')
		);
	}

	/**
	 * Safely render a help partial template and return its buffered HTML.
	 *
	 * @param string               $partial_name Filename within templates/admin/help/.
	 * @param array<string, mixed> $args         Optional variables passed to template.
	 * @return string Rendered HTML content.
	 */
	public static function render_partial($partial_name, $args = array()) {
		$file = self::get_help_dir() . $partial_name;
		if (!file_exists($file)) {
			return '';
		}

		ob_start();
		// Expose $args to partial scope.
		extract($args, EXTR_SKIP); // phpcs:ignore WordPress.PHP.DontExtract.extract_extract
		include $file;
		return (string) ob_get_clean();
	}

	/**
	 * Register a custom help tab for a specific screen ID.
	 *
	 * @param string               $screen_id Target screen ID.
	 * @param array<string, mixed> $tab_args  Help tab configuration (id, title, content, callback).
	 * @return void
	 */
	public static function register_tab($screen_id, $tab_args) {
		if (!isset(self::$custom_tabs[$screen_id])) {
			self::$custom_tabs[$screen_id] = array();
		}
		self::$custom_tabs[$screen_id][] = $tab_args;
	}

	/**
	 * Setup native contextual help tabs on current screen.
	 *
	 * @param WP_Screen|null $screen Current screen instance.
	 * @return void
	 */
	public static function setup_screen_help($screen = null) {
		if (!$screen instanceof WP_Screen) {
			$screen = function_exists('get_current_screen') ? get_current_screen() : null;
		}

		if (!self::is_aips_screen($screen)) {
			return;
		}

		$screen_id   = $screen->id;
		$page        = isset($_GET['page']) ? sanitize_key(wp_unslash($_GET['page'])) : '';
		$active_tab  = isset($_GET['tab']) ? sanitize_key(wp_unslash($_GET['tab'])) : '';

		$help_tabs = self::get_default_help_tabs($screen_id, $page, $active_tab);

		// Allow filtering of help tabs per screen.
		$help_tabs = apply_filters('aips_screen_help_tabs', $help_tabs, $screen_id, $page, $active_tab);

		if (!empty($help_tabs) && is_array($help_tabs)) {
			foreach ($help_tabs as $tab) {
				if (!empty($tab['id']) && !empty($tab['title'])) {
					$screen->add_help_tab($tab);
				}
			}
		}

		// Register any custom tabs added dynamically.
		if (!empty(self::$custom_tabs[$screen_id])) {
			foreach (self::$custom_tabs[$screen_id] as $custom_tab) {
				$screen->add_help_tab($custom_tab);
			}
		}

		// Render and set the standard contextual help sidebar.
		$sidebar_html = self::render_partial('sidebar-help.php');
		if (!empty($sidebar_html)) {
			$sidebar_html = apply_filters('aips_screen_help_sidebar', $sidebar_html, $screen_id, $page, $active_tab);
			$screen->set_help_sidebar($sidebar_html);
		}
	}

	/**
	 * Resolve default contextual help tabs based on active screen and tab.
	 *
	 * @param string $screen_id Current screen identifier.
	 * @param string $page      Current $_GET['page'] parameter.
	 * @param string $tab       Current $_GET['tab'] parameter.
	 * @return array<int, array<string, mixed>> List of help tab configurations.
	 */
	public static function get_default_help_tabs($screen_id, $page, $tab) {
		$tabs = array();

		// Screen: Templates or Content Hub with Templates
		if (
			false !== strpos($screen_id, 'templates') ||
			'aips-templates' === $page ||
			'templates' === $tab
		) {
			$tabs[] = array(
				'id'      => 'aips_template_tokens',
				'title'   => __('Dynamic Tokens', 'ai-post-scheduler'),
				'content' => self::render_partial('template-tokens.php'),
			);
		}

		// Screen: Schedules or Automations Hub
		if (
			false !== strpos($screen_id, 'schedule') ||
			false !== strpos($screen_id, 'automations') ||
			'aips-schedules' === $page ||
			'aips-schedule' === $page ||
			'schedules' === $tab
		) {
			$tabs[] = array(
				'id'      => 'aips_schedule_cadence',
				'title'   => __('Cadence & Concurrency', 'ai-post-scheduler'),
				'content' => self::render_partial('schedule-cadence.php'),
			);
		}

		// Screen: Settings Hub
		if (
			false !== strpos($screen_id, 'settings') ||
			'aips-settings' === $page
		) {
			$tabs[] = array(
				'id'      => 'aips_settings_engine',
				'title'   => __('AI Engine & Providers', 'ai-post-scheduler'),
				'content' => self::render_partial('settings-engine.php'),
			);
		}

		return $tabs;
	}
}
