<?php
/**
 * Multisite Network Admin Menu Controller
 *
 * Registers the Network Admin menus and handles network-level settings saves.
 *
 * @package AI_Post_Scheduler
 * @since 3.8.0
 */

if (!defined('ABSPATH')) {
	exit;
}

class AIPS_Network_Admin_Menu {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action('network_admin_menu', array($this, 'register_menu'));
		add_action('admin_init', array($this, 'handle_save_settings'));
		add_action('network_admin_notices', array($this, 'render_notices'));
	}

	/**
	 * Register Network Admin navigation items.
	 *
	 * @return void
	 */
	public function register_menu(): void {
		add_menu_page(
			__('AI Post Scheduler', 'ai-post-scheduler'),
			__('AI Post Scheduler', 'ai-post-scheduler'),
			'manage_network_options',
			'aips-network-settings',
			array($this, 'render_settings_page'),
			'dashicons-schedule',
			30
		);

		add_submenu_page(
			'aips-network-settings',
			__('Network Settings', 'ai-post-scheduler'),
			__('Network Settings', 'ai-post-scheduler'),
			'manage_network_options',
			'aips-network-settings',
			array($this, 'render_settings_page')
		);

		add_submenu_page(
			'aips-network-settings',
			__('Network Sites', 'ai-post-scheduler'),
			__('Network Sites', 'ai-post-scheduler'),
			'manage_network_options',
			'aips-network-sites',
			array($this, 'render_sites_page')
		);
	}

	/**
	 * Handle network settings POST submission.
	 *
	 * @return void
	 */
	public function handle_save_settings(): void {
		if (!isset($_POST['aips_network_settings_submit'])) {
			return;
		}

		if (!check_admin_referer('aips_network_settings_save', 'aips_network_settings_nonce')) {
			wp_die(esc_html__('Security check failed.', 'ai-post-scheduler'));
		}

		if (!current_user_can('manage_network_options')) {
			wp_die(esc_html__('You do not have permission to modify network settings.', 'ai-post-scheduler'));
		}

		$raw = array(
			'ai_provider'                  => sanitize_key($_POST['ai_provider'] ?? ''),
			'ai_model'                     => sanitize_text_field($_POST['ai_model'] ?? ''),
			'ai_env_id'                    => sanitize_text_field($_POST['ai_env_id'] ?? ''),
			'wp_ai_connector_mode'         => sanitize_key($_POST['wp_ai_connector_mode'] ?? 'all'),
			'wp_ai_connector_failover'     => !empty($_POST['wp_ai_connector_failover']),
			'temperature'                  => floatval($_POST['temperature'] ?? 0.7),
			'max_tokens_limit'             => absint($_POST['max_tokens_limit'] ?? 16000),
			'allow_site_ai_override'       => !empty($_POST['allow_site_ai_override']),
			'share_network_ai_credentials' => !empty($_POST['share_network_ai_credentials']),
			'restrict_db_tools'            => !empty($_POST['restrict_db_tools']),
			'prevent_ai_generation'        => !empty($_POST['prevent_ai_generation']),
			'daily_quota_per_site'         => absint($_POST['daily_quota_per_site'] ?? 0),
			'monthly_quota_per_site'       => absint($_POST['monthly_quota_per_site'] ?? 0),
		);

		AIPS_Network_Config::update_settings($raw);

		wp_safe_redirect(add_query_arg(
			array(
				'page'    => 'aips-network-settings',
				'updated' => '1',
			),
			network_admin_url('admin.php')
		));
		exit;
	}

	/**
	 * Render network admin notices.
	 *
	 * @return void
	 */
	public function render_notices(): void {
		$screen = get_current_screen();
		if (!$screen || strpos($screen->id, 'aips-network') === false) {
			return;
		}

		if (isset($_GET['updated']) && $_GET['updated'] === '1') {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Network settings saved successfully.', 'ai-post-scheduler') . '</p></div>';
		}
	}

	/**
	 * Render the Network Settings view.
	 *
	 * @return void
	 */
	public function render_settings_page(): void {
		if (!current_user_can('manage_network_options')) {
			wp_die(esc_html__('You do not have permission to access this page.', 'ai-post-scheduler'));
		}

		$settings = AIPS_Network_Config::get_settings();
		$template_path = AIPS_PLUGIN_DIR . 'templates/admin/network-settings.php';

		if (file_exists($template_path)) {
			include $template_path;
		}
	}

	/**
	 * Render the Network Sites list view.
	 *
	 * @return void
	 */
	public function render_sites_page(): void {
		if (!current_user_can('manage_network_options')) {
			wp_die(esc_html__('You do not have permission to access this page.', 'ai-post-scheduler'));
		}

		$list_table = new AIPS_Network_Sites_List_Table();
		$list_table->prepare_items();

		$template_path = AIPS_PLUGIN_DIR . 'templates/admin/network-sites.php';
		if (file_exists($template_path)) {
			include $template_path;
		}
	}
}
