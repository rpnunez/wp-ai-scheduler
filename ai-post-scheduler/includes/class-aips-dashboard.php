<?php
if (!defined('ABSPATH')) {
    exit;
}

class AIPS_Dashboard {

    private $history_repo;
    private $template_repo;

    public function __construct() {
        // We only instantiate repos if they exist, to avoid fatals if something is wrong.
        // But assumed they exist as per check.
        $this->history_repo = new AIPS_History_Repository();
        $this->template_repo = new AIPS_Template_Repository();

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
        $template_performance = $this->get_template_performance();

        // Fetch Automation Settings
        $automation_settings = get_option('aips_automation_settings', array(
            'disable_low_performance' => 0,
            'low_performance_threshold' => 30, // %
            'min_generations_threshold' => 5,
            'auto_retry_failed' => 1,
            'retry_limit' => 3
        ));

        // Suggestions
        $suggestions = $this->get_suggestions($stats);

        return compact('stats', 'template_performance', 'automation_settings', 'suggestions');
    }

    public function render_page() {
        // Set the active tab to dashboard so main.php renders it correctly
        $active_tab = 'dashboard';

        // Include main wrapper. Main wrapper will call get_dashboard_data() inside the tab logic.
        include AIPS_PLUGIN_DIR . 'templates/admin/main.php';
    }

    public function get_template_performance() {
        global $wpdb;
        $table_history = $wpdb->prefix . 'aips_history';
        $table_templates = $wpdb->prefix . 'aips_templates';

        // Get success rates per template
        $results = $wpdb->get_results("
            SELECT
                t.id,
                t.name,
                COUNT(h.id) as total,
                SUM(CASE WHEN h.status = 'completed' THEN 1 ELSE 0 END) as completed,
                SUM(CASE WHEN h.status = 'failed' THEN 1 ELSE 0 END) as failed
            FROM $table_templates t
            LEFT JOIN $table_history h ON t.id = h.template_id
            GROUP BY t.id
            HAVING total > 0
            ORDER BY completed DESC
        ");

        $performance = array();
        foreach ($results as $row) {
            $total = (int)$row->total;
            $completed = (int)$row->completed;
            $rate = $total > 0 ? round(($completed / $total) * 100, 1) : 0;

            $performance[] = array(
                'id' => $row->id,
                'name' => $row->name,
                'total' => $total,
                'completed' => $completed,
                'failed' => (int)$row->failed,
                'success_rate' => $rate
            );
        }

        // Sort by success rate descending
        usort($performance, function($a, $b) {
            return $b['success_rate'] <=> $a['success_rate'];
        });

        return $performance;
    }

    public function get_suggestions($stats) {
        $suggestions = array();

        if ($stats['failed'] > 0) {
            $suggestions[] = array(
                'type' => 'warning',
                'message' => sprintf(__('You have %d failed generations. Check logs to diagnose.', 'ai-post-scheduler'), $stats['failed']),
                'action' => 'logs'
            );
        }

        // Mock suggestion
        $suggestions[] = array(
            'type' => 'info',
            'message' => __('Trending research suggests posting at 2 PM boosts engagement.', 'ai-post-scheduler'),
            'action' => 'research'
        );

        return $suggestions;
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

        $settings = array(
            'disable_low_performance' => isset($_POST['disable_low_performance']) ? 1 : 0,
            'low_performance_threshold' => isset($_POST['low_performance_threshold']) ? absint($_POST['low_performance_threshold']) : 30,
            'min_generations_threshold' => isset($_POST['min_generations_threshold']) ? absint($_POST['min_generations_threshold']) : 5,
            'auto_retry_failed' => isset($_POST['auto_retry_failed']) ? 1 : 0,
            'retry_limit' => isset($_POST['retry_limit']) ? absint($_POST['retry_limit']) : 3,
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

        delete_transient('aips_history_stats');
        $stats = $this->history_repo->get_stats();

        wp_send_json_success(array(
            'stats' => $stats,
            'message' => __('Statistics refreshed.', 'ai-post-scheduler')
        ));
    }
}
