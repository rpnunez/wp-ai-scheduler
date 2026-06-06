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
	private const DEFAULT_TAB = 'status';

	/**
	 * Render the Diagnostics page.
	 *
	 * @return void
	 */
	public function render_page() {
		if (!current_user_can('manage_options')) {
			wp_die(esc_html__('You do not have permission to access this page.', 'ai-post-scheduler'));
		}

		$tabs = $this->get_tabs();
		$active_tab = self::get_active_tab_key();
		$diagnostics_controller = $this;

		include AIPS_PLUGIN_DIR . 'templates/admin/diagnostics.php';
	}

	/**
	 * Get available Diagnostics tabs.
	 *
	 * @return array<string, array{label:string}>
	 */
	public function get_tabs() {
		$tabs = array(
			'status' => array(
				'label' => __('System Status', 'ai-post-scheduler'),
			),
		);

		if (self::is_tab_available('telemetry')) {
			$tabs['telemetry'] = array(
				'label' => __('Telemetry', 'ai-post-scheduler'),
			);
		}

		if (self::is_tab_available('cache-monitor')) {
			$tabs['cache-monitor'] = array(
				'label' => __('Cache Monitor', 'ai-post-scheduler'),
			);
		}

		$tabs['insights'] = array(
			'label' => __('Insights', 'ai-post-scheduler'),
		);

		$tabs['seeder'] = array(
			'label' => __('Seeder', 'ai-post-scheduler'),
		);

		if (self::is_tab_available('dev-tools')) {
			$tabs['dev-tools'] = array(
				'label' => __('Dev Tools', 'ai-post-scheduler'),
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

		// Backward compatibility for previous Diagnostics tab key.
		if ('operations-insights' === $active_tab) {
			$active_tab = 'insights';
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
		if (in_array($tab, array('status', 'seeder', 'insights', 'cache-monitor'), true)) {
			return true;
		}

		if ('telemetry' === $tab) {
			return (bool) AIPS_Config::get_instance()->get_option('aips_enable_telemetry');
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
			case 'seeder':
				$this->render_seeder_tab();
				break;
				case 'operations-insights':
			case 'insights':
				$this->render_operations_insights_tab();
				break;
			case 'cache-monitor':
				$this->render_cache_monitor_tab();
				break;
			case 'telemetry':
				$this->render_telemetry_tab();
				break;
			case 'dev-tools':
				$this->render_dev_tools_tab();
				break;
			case 'status':
			default:
				$this->render_status_tab();
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
		$status_handler->render_page(true);
	}

	/**
	 * Render the Seeder tab.
	 *
	 * @return void
	 */
	private function render_seeder_tab() {
		$seeder_admin = new AIPS_Seeder_Admin();
		$seeder_admin->render_page(true);
	}

	/**
	 * Render the Operations Insights tab.
	 *
	 * @return void
	 */
	private function render_operations_insights_tab() {
		$controller = new AIPS_Operations_Insights_Controller();
		$controller->render_page(true);
	}

	/**
	 * Render the Telemetry tab.
	 *
	 * @return void
	 */
	private function render_telemetry_tab() {
		$controller = new AIPS_Telemetry_Controller();
		$controller->render_page(true);
	}

	/**
	 * Render the Cache Monitor tab.
	 *
	 * @return void
	 */
	private function render_cache_monitor_tab() {
		$controller = new AIPS_Cache_Monitor_Controller();
		$controller->render_page(true);
	}

	/**
	 * Render the Dev Tools tab.
	 *
	 * @return void
	 */
	private function render_dev_tools_tab() {
		$dev_tools = new AIPS_Dev_Tools();
		$dev_tools->render_page(true);
	}
}
