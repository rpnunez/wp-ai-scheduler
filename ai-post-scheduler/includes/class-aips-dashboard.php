<?php
if (!defined('ABSPATH')) {
    exit;
}

class AIPS_Dashboard {

    const REFRESH_INTERVALS = array(1, 3, 5, 30);

    private $history_repo;
    private $analytics_service;

    public function __construct() {
        $this->history_repo = new AIPS_History_Repository();
        $this->analytics_service = new AIPS_Analytics_Service();

        add_action('wp_ajax_aips_get_logs', array($this, 'ajax_get_logs'));
        add_action('wp_ajax_aips_save_automation', array($this, 'ajax_save_automation'));
        add_action('wp_ajax_aips_refresh_stats', array($this, 'ajax_refresh_stats'));
    }

    /**
     * Prepare all data needed for the dashboard view.
     *
     * @return array
     */
    public function get_dashboard_data() {
        // Fetch Global Stats
        $stats = $this->history_repo->get_stats();

        // Fetch Template Performance
        $template_performance = $this->analytics_service->get_template_performance();

        // Fetch Automation Settings
        /**
         * Automation Settings Configuration
         *
         * @var array $automation_settings
         * @key bool disable_low_performance  If true, templates falling below threshold are auto-disabled.
         * @key int  low_performance_threshold Percentage (0-100) success rate trigger for deactivation.
         * @key int  min_generations_threshold Minimum number of posts before evaluating performance stats.
         * @key bool auto_retry_failed        If true, failed generations are automatically retried.
         * @key int  retry_limit              Maximum number of retry attempts per failed post.
         */
        $automation_settings = get_option('aips_automation_settings', array(
            'disable_low_performance' => 0,
            'low_performance_threshold' => 30, // %
            'min_generations_threshold' => 5,
            'auto_retry_failed' => 1,
            'retry_limit' => 3
        ));

        // Suggestions
        $suggestions = $this->analytics_service->get_suggestions($stats);

        return compact('stats', 'template_performance', 'automation_settings', 'suggestions');
    }

    public function render_page() {
        // Set the active tab to dashboard so main.php renders it correctly
        $active_tab = 'dashboard';

        // Include main wrapper. Main wrapper will call get_dashboard_data() inside the tab logic.
        include AIPS_PLUGIN_DIR . 'templates/admin/main.php';
    }

    public function ajax_get_logs() {
        check_ajax_referer('aips_ajax_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied.', 'ai-post-scheduler')));
        }

        $logger = new AIPS_Logger();
        $logs = $logger->get_logs(200);
        $logs = array_reverse($logs);

        wp_send_json_success(array('logs' => $logs));
    }

    public function ajax_save_automation() {
        check_ajax_referer('aips_ajax_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied.', 'ai-post-scheduler')));
        }

        $disable_low_performance_raw      = isset($_POST['disable_low_performance']) ? sanitize_text_field(wp_unslash($_POST['disable_low_performance'])) : '';
        $auto_retry_failed_raw            = isset($_POST['auto_retry_failed']) ? sanitize_text_field(wp_unslash($_POST['auto_retry_failed'])) : '';
        $low_performance_threshold_raw    = isset($_POST['low_performance_threshold']) ? wp_unslash($_POST['low_performance_threshold']) : '';
        $min_generations_threshold_raw    = isset($_POST['min_generations_threshold']) ? wp_unslash($_POST['min_generations_threshold']) : '';
        $retry_limit_raw                  = isset($_POST['retry_limit']) ? wp_unslash($_POST['retry_limit']) : '';

        $disable_low_performance   = !empty($disable_low_performance_raw) ? 1 : 0;
        $auto_retry_failed         = !empty($auto_retry_failed_raw) ? 1 : 0;
        $low_performance_threshold = $low_performance_threshold_raw !== '' ? absint($low_performance_threshold_raw) : 30;
        $min_generations_threshold = $min_generations_threshold_raw !== '' ? absint($min_generations_threshold_raw) : 5;
        $retry_limit               = $retry_limit_raw !== '' ? absint($retry_limit_raw) : 3;

        $settings = array(
            'disable_low_performance'     => $disable_low_performance,
            'low_performance_threshold'   => $low_performance_threshold,
            'min_generations_threshold'   => $min_generations_threshold,
            'auto_retry_failed'           => $auto_retry_failed,
            'retry_limit'                 => $retry_limit,
        );

        update_option('aips_automation_settings', $settings);
        update_option('aips_retry_max_attempts', $settings['retry_limit']);

        wp_send_json_success(array('message' => __('Automation settings saved.', 'ai-post-scheduler')));
    }

    public function ajax_refresh_stats() {
        check_ajax_referer('aips_ajax_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied.', 'ai-post-scheduler')));
        }

        // Only force refresh if explicitly requested, otherwise rely on repository cache invalidation
        if (isset($_POST['force_refresh']) && $_POST['force_refresh'] === 'true') {
            delete_transient('aips_history_stats');
        }

        $data = $this->get_dashboard_data();

        wp_send_json_success(array(
            'data' => $data,
            'message' => __('Statistics refreshed.', 'ai-post-scheduler')
        ));
    }
}
