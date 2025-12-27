<?php
if (!defined('ABSPATH')) {
    exit;
}

class AIPS_Automation_Service {

    private $template_repo;
    private $history_repo;

    public function __construct() {
        $this->template_repo = new AIPS_Template_Repository();
        $this->history_repo = new AIPS_History_Repository();

        // Hook into generation completion or schedule processing
        add_action('aips_post_generated', array($this, 'check_performance_thresholds'), 10, 2);
    }

    public function check_performance_thresholds($post_id, $template_id) {
        if (!$template_id) {
            return;
        }

        $settings = get_option('aips_automation_settings', array());

        if (empty($settings['disable_low_performance'])) {
            return;
        }

        $threshold = isset($settings['low_performance_threshold']) ? (int)$settings['low_performance_threshold'] : 30;
        $min_gens = isset($settings['min_generations_threshold']) ? (int)$settings['min_generations_threshold'] : 5;

        $stats = $this->history_repo->get_detailed_template_stats($template_id);

        if (!$stats || $stats->total < $min_gens) {
            return;
        }

        $success_rate = ($stats->total > 0) ? ($stats->completed / $stats->total) * 100 : 0;

        if ($success_rate < $threshold) {
            $this->template_repo->update($template_id, array('is_active' => 0));

            $logger = new AIPS_Logger();
            $logger->log("Template ID $template_id automatically disabled due to low success rate ({$success_rate}%)", 'warning');
        }
    }
}
