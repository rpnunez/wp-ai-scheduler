<?php
if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Diagnostics_Controller
 *
 * Coordinates the Diagnostics admin page and its feature-gated tabs.
 *
 * @package AI_Post_Scheduler
 */
class AIPS_Diagnostics_Controller {

	/**
	 * Diagnostics page slug.
	 */
	public const PAGE_SLUG = 'aips-diagnostics';

	/**
	 * Default Diagnostics tab key.
	 */
	private const DEFAULT_TAB = 'system-info';

	/**
	 * Render the Diagnostics page.
	 *
	 * @return void
	 */
	public function render_page() {
		if (!current_user_can('manage_options')) {
			wp_die(esc_html__('You do not have permission to access this page.', 'ai-post-scheduler'));
		}

		$tabs                   = $this->get_tabs();
		$active_tab             = self::get_active_tab_key();
		$page_context           = $this->get_page_context($active_tab);
		$diagnostics_controller = $this;

		include AIPS_PLUGIN_DIR . 'templates/admin/diagnostics.php';
	}

	/**
	 * Build contextual page context object for the active tab.
	 *
	 * @param string $active_tab Active tab key.
	 * @return AIPS_Admin_Page_Context
	 */
	public function get_page_context($active_tab) {
		$summary_items = array();

		try {
			if ('system-info' === $active_tab) {
				$php_v = defined('PHP_VERSION') ? PHP_VERSION : phpversion();
				$summary_items = array(
					array('label' => __('PHP Version', 'ai-post-scheduler'), 'value' => $php_v, 'type' => 'neutral', 'icon' => 'dashicons-info'),
					array('label' => __('WP Version', 'ai-post-scheduler'), 'value' => get_bloginfo('version'), 'type' => 'neutral', 'icon' => 'dashicons-wordpress'),
				);
			} elseif ('health' === $active_tab) {
				$summary_items = array(
					array('label' => __('System Status', 'ai-post-scheduler'), 'value' => __('Operational', 'ai-post-scheduler'), 'type' => 'success', 'icon' => 'dashicons-yes-alt'),
				);
			} elseif ('telemetry' === $active_tab) {
				$summary_items = array(
					array('label' => __('Telemetry Stream', 'ai-post-scheduler'), 'value' => __('Active', 'ai-post-scheduler'), 'type' => 'info', 'icon' => 'dashicons-performance'),
				);
			}
		} catch (\Throwable $e) {
			// Fail-safe: empty summary items
		}

		return AIPS_Admin_Page_Context::resolve(
			self::PAGE_SLUG,
			$active_tab,
			null,
			array(
				'summary_items' => $summary_items,
			)
		);
	}

	/**
	 * Get available Diagnostics tabs.
	 *
	 * @return array<string, array{label:string, icon:string, description:string}>
	 */
	public function get_tabs() {
		$tabs = array(
			'system-info' => array(
				'label'       => __('System Info', 'ai-post-scheduler'),
				'icon'        => 'dashicons-info',
				'description' => __('Environment & server specifications', 'ai-post-scheduler'),
			),
			'health' => array(
				'label'       => __('System Health & Tools', 'ai-post-scheduler'),
				'icon'        => 'dashicons-admin-tools',
				'description' => __('Maintenance, recovery & cache rebuild', 'ai-post-scheduler'),
			),
			'operations' => array(
				'label'       => __('Operational Status', 'ai-post-scheduler'),
				'icon'        => 'dashicons-dashboard',
				'description' => __('Scheduler, queue & pipeline metrics', 'ai-post-scheduler'),
			),
		);

		if (self::is_tab_available('telemetry')) {
			$tabs['telemetry'] = array(
				'label'       => __('Telemetry', 'ai-post-scheduler'),
				'icon'        => 'dashicons-chart-area',
				'description' => __('Performance metrics & queries', 'ai-post-scheduler'),
			);
		}

		if (self::is_tab_available('cache-monitor')) {
			$tabs['cache-monitor'] = array(
				'label'       => __('Cache Monitor', 'ai-post-scheduler'),
				'icon'        => 'dashicons-performance',
				'description' => __('Object cache & transient stats', 'ai-post-scheduler'),
			);
		}

		$tabs['stress-test'] = array(
			'label'       => __('Stress Test', 'ai-post-scheduler'),
			'icon'        => 'dashicons-superhero',
			'description' => __('AI provider load & reliability', 'ai-post-scheduler'),
		);

		$tabs['insights'] = array(
			'label'       => __('Insights', 'ai-post-scheduler'),
			'icon'        => 'dashicons-lightbulb',
			'description' => __('Generation analytics & trends', 'ai-post-scheduler'),
		);

		// The Seeder UI has been moved into the Dev Tools tab. Dev Tools is still
		// gated by developer mode.
		if (self::is_tab_available('dev-tools')) {
			$tabs['dev-tools'] = array(
				'label'       => __('Dev Tools', 'ai-post-scheduler'),
				'icon'        => 'dashicons-admin-tools',
				'description' => __('Seeder & developer utilities', 'ai-post-scheduler'),
			);
		}

		return $tabs;
	}

	/**
	 * Get the active Diagnostics tab key for the current request.
	 *
	 * @return string
	 */
	public static function get_active_tab_key() {
		$active_tab = filter_input(INPUT_GET, 'tab', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
		$active_tab = $active_tab ? sanitize_key($active_tab) : self::DEFAULT_TAB;

		// Backward compatibility for previous Diagnostics tab keys.
		if ('operations-insights' === $active_tab) {
			$active_tab = 'insights';
		} elseif ('status' === $active_tab) {
			$active_tab = 'system-info';
		}

		if (!self::is_tab_available($active_tab)) {
			return self::DEFAULT_TAB;
		}

		return $active_tab;
	}

	/**
	 * Determine whether a Diagnostics tab is currently available.
	 *
	 * @param string $tab Tab key.
	 * @return bool
	 */
	public static function is_tab_available($tab) {
		// Keep in step with get_tabs(): a tab listed there but missing here is
		// rejected by get_active_tab_key() and silently falls back to the default.
		if (in_array($tab, array('system-info', 'health', 'operations', 'status', 'insights', 'stress-test'), true)) {
			return true;
		}

		if ('telemetry' === $tab) {
			// Use the runtime guard which prevents re-entrant option lookups.
			return (bool) (class_exists('AIPS_Telemetry') && AIPS_Telemetry::is_enabled());
		}

		if ('cache-monitor' === $tab) {
			$config = AIPS_Config::get_instance();
			return (bool) $config->get_option('aips_cache_monitor_enabled') && $config->is_cache_system_enabled();
		}

		if ('dev-tools' === $tab) {
			return (bool) AIPS_Config::get_instance()->get_option('aips_developer_mode');
		}

		return false;
	}

	/**
	 * Get the admin URL for a Diagnostics tab.
	 *
	 * @param string $tab Tab key.
	 * @return string
	 */
	public function get_tab_url($tab) {
		return add_query_arg(
			array(
				'page' => self::PAGE_SLUG,
				'tab'  => $tab,
			),
			admin_url('admin.php')
		);
	}

	/**
	 * Render content for the requested Diagnostics tab.
	 *
	 * @param string $active_tab Active tab key.
	 * @return void
	 */
	public function render_tab_content($active_tab) {
		switch ($active_tab) {
			case 'system-info':
				AIPS_Admin_Menu_Helper::safe_render(function() {
					include AIPS_PLUGIN_DIR . 'templates/admin/system-info.php';
				}, __('System Info', 'ai-post-scheduler'), true);
				break;
			case 'health':
				AIPS_Admin_Menu_Helper::safe_render(function() {
					include AIPS_PLUGIN_DIR . 'templates/admin/system-health.php';
				}, __('System Health & Tools', 'ai-post-scheduler'), true);
				break;
			case 'operations':
				AIPS_Admin_Menu_Helper::safe_render(function() {
					include AIPS_PLUGIN_DIR . 'templates/admin/operational-status.php';
				}, __('Operational Status', 'ai-post-scheduler'), true);
				break;
			case 'operations-insights':
			case 'insights':
				AIPS_Admin_Menu_Helper::safe_render(function() {
					$this->render_operations_insights_tab();
				}, __('Operations Insights', 'ai-post-scheduler'), true);
				break;
			case 'cache-monitor':
				AIPS_Admin_Menu_Helper::safe_render(function() {
					$this->render_cache_monitor_tab();
				}, __('Cache Monitor', 'ai-post-scheduler'), true);
				break;
			case 'stress-test':
				AIPS_Admin_Menu_Helper::safe_render(function() {
					$this->render_stress_test_tab();
				}, __('Stress Test', 'ai-post-scheduler'), true);
				break;
			case 'telemetry':
				AIPS_Admin_Menu_Helper::safe_render(function() {
					$this->render_telemetry_tab();
				}, __('Telemetry', 'ai-post-scheduler'), true);
				break;
			case 'dev-tools':
				AIPS_Admin_Menu_Helper::safe_render(function() {
					$this->render_dev_tools_tab();
				}, __('Dev Tools', 'ai-post-scheduler'), true);
				break;
			case 'status':
			default:
				AIPS_Admin_Menu_Helper::safe_render(function() {
					include AIPS_PLUGIN_DIR . 'templates/admin/system-info.php';
				}, __('System Info', 'ai-post-scheduler'), true);
				break;
		}
	}

	/**
	 * Render the System Status tab.
	 *
	 * @return void
	 */
	private function render_status_tab() {
		$status_handler = new AIPS_System_Status();
		$status_handler->render_page();
	}

	/**
	 * Render the Operations Insights tab.
	 *
	 * @return void
	 */
	private function render_operations_insights_tab() {
		$controller = new AIPS_Operations_Insights_Controller();
		$controller->render_page();
	}

	/**
	 * Render the Telemetry tab.
	 *
	 * @return void
	 */
	private function render_telemetry_tab() {
		$controller = new AIPS_Telemetry_Controller();
		$controller->render_page();
	}

	/**
	 * Render the Cache Monitor tab.
	 *
	 * @return void
	 */
	private function render_cache_monitor_tab() {
		$controller = new AIPS_Cache_Monitor_Controller();
		$controller->render_page();
	}

	/**
	 * Render the Stress Test tab.
	 *
	 * @return void
	 */
	private function render_stress_test_tab() {
		// If the Stress Test tab requested the history view, render the history
		// inline so it behaves like a contextual sub-view (similar to Author
		// Topics -> Author Topics behavior).
		$view = filter_input(INPUT_GET, 'view', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
		if ($view && 'history' === sanitize_key($view)) {
			$this->render_stress_test_history_tab();
			return;
		}

		$controller = new AIPS_Stress_Test_Controller();
		$controller->render_page();
	}

	/**
	 * Render the Stress Test History tab.
	 *
	 * @return void
	 */
	private function render_stress_test_history_tab() {
		$service = new AIPS_Stress_Test_Service();
		$runs    = $service->get_run_history(30);
		include AIPS_PLUGIN_DIR . 'templates/admin/tab-stress-test-history.php';
	}

	/**
	 * Render the Dev Tools tab.
	 *
	 * @return void
	 */
	private function render_dev_tools_tab() {
		$dev_tools = new AIPS_Dev_Tools();
		$dev_tools->render_page();
	}
}
