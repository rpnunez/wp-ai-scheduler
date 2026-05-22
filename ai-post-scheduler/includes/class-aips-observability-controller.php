<?php
/**
 * Observability Controller
 *
 * @package AI_Post_Scheduler
 * @since   2.5.0
 */

if (!defined('ABSPATH')) {
	exit;
}

class AIPS_Observability_Controller {

	const PAGE_SLUG = 'aips-observability';
	const TAB_HEALTH = 'health';
	const TAB_OPERATIONS = 'operations';
	const TAB_TELEMETRY = 'telemetry';

	/**
	 * Render the shared observability shell and active tab.
	 *
	 * @return void
	 */
	public function render_page() {
		if (!current_user_can('manage_options')) {
			wp_die(esc_html__('You do not have permission to access this page.', 'ai-post-scheduler'));
		}

		$current_tab = $this->get_current_tab();
		$tabs        = $this->get_tabs();
		$notices     = $this->get_contextual_notices($current_tab);

		include AIPS_PLUGIN_DIR . 'templates/admin/observability.php';
	}

	/**
	 * Return the active tab from the request.
	 *
	 * @return string
	 */
	private function get_current_tab() {
		$tab = isset($_GET['tab']) ? sanitize_key(wp_unslash($_GET['tab'])) : self::TAB_HEALTH;
		$allowed_tabs = array_keys($this->get_tabs());

		if (!in_array($tab, $allowed_tabs, true)) {
			return self::TAB_HEALTH;
		}

		return $tab;
	}

	/**
	 * Build the nav tab metadata.
	 *
	 * @return array<string, array<string, string|bool>>
	 */
	private function get_tabs() {
		$telemetry_enabled = (bool) AIPS_Config::get_instance()->get_option('aips_enable_telemetry');

		return array(
			self::TAB_HEALTH => array(
				'label' => __('Health', 'ai-post-scheduler'),
				'url'   => AIPS_Admin_Menu_Helper::get_page_url('observability', array('tab' => self::TAB_HEALTH)),
			),
			self::TAB_OPERATIONS => array(
				'label' => __('Operations', 'ai-post-scheduler'),
				'url'   => AIPS_Admin_Menu_Helper::get_page_url('observability', array('tab' => self::TAB_OPERATIONS)),
			),
			self::TAB_TELEMETRY => array(
				'label'    => __('Telemetry', 'ai-post-scheduler'),
				'url'      => AIPS_Admin_Menu_Helper::get_page_url('observability', array('tab' => self::TAB_TELEMETRY)),
				'disabled' => !$telemetry_enabled,
			),
		);
	}

	/**
	 * Build shell notices for the current tab.
	 *
	 * @param string $current_tab Active tab.
	 * @return array<int, array<string, string>>
	 */
	private function get_contextual_notices($current_tab) {
		$notices = array();
		$telemetry_enabled = (bool) AIPS_Config::get_instance()->get_option('aips_enable_telemetry');

		if (self::TAB_TELEMETRY === $current_tab && !$telemetry_enabled) {
			$notices[] = array(
				'type'    => 'warning',
				'message' => __('Telemetry is currently disabled. Enable it in Settings before using this tab.', 'ai-post-scheduler'),
			);
		}

		if (self::TAB_OPERATIONS === $current_tab && !$telemetry_enabled) {
			$notices[] = array(
				'type'    => 'warning',
				'message' => __('Telemetry is disabled, so retry and performance insights are limited to history data.', 'ai-post-scheduler'),
			);
		}

		return $notices;
	}

	/**
	 * Render the active tab body inside the shared shell.
	 *
	 * @param string $current_tab Active tab.
	 * @return void
	 */
	private function render_tab_content($current_tab) {
		switch ($current_tab) {
			case self::TAB_OPERATIONS:
				$controller = new AIPS_Operations_Insights_Controller();
				$controller->render_page(array('embedded' => true));
				return;

			case self::TAB_TELEMETRY:
				if (!AIPS_Config::get_instance()->get_option('aips_enable_telemetry')) {
					echo '<div class="aips-content-panel"><div class="aips-panel-body"><p>' .
						esc_html__('Telemetry is disabled for this site. Open Settings to enable request-level telemetry first.', 'ai-post-scheduler') .
					'</p></div></div>';
					return;
				}

				$controller = new AIPS_Telemetry_Controller();
				$controller->render_page(array('embedded' => true));
				return;

			case self::TAB_HEALTH:
			default:
				$status_handler = new AIPS_System_Status();
				$status_handler->render_page(array('embedded' => true));
				return;
		}
	}
}
