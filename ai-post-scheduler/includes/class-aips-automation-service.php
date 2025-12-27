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

        // Hook into generation completion event fired by the generator.
        // The documented event signature is: do_action( 'aips_post_generation_completed', $data, $context ).
        add_action( 'aips_post_generation_completed', array( $this, 'check_performance_thresholds' ), 10, 2 );
    }

    /**
     * Check performance thresholds for a template after a post has been generated.
     *
     * This method is designed to be flexible about the arguments it receives so it can
     * work with both:
     * - Legacy calls: ($post_id, $template_id)
     * - Event payloads: ($data, $context) from aips_post_generation_completed.
     *
     * @param mixed $arg1 Post ID or data array.
     * @param mixed $arg2 Template ID, context array, or null.
     */
    public function check_performance_thresholds( $arg1, $arg2 = null ) {
        $template_id = null;

        // Backwards compatibility: second argument is a numeric template ID.
        if ( is_numeric( $arg2 ) ) {
            $template_id = (int) $arg2;
        } elseif ( is_array( $arg1 ) && isset( $arg1['template_id'] ) ) {
            // Event payload style: template_id provided in the first argument.
            $template_id = (int) $arg1['template_id'];
        } elseif ( is_array( $arg2 ) && isset( $arg2['template_id'] ) ) {
            // Event payload style: template_id provided in the second argument.
            $template_id = (int) $arg2['template_id'];
        }

        if ( ! $template_id ) {
            return;
        }

        $settings = get_option('aips_automation_settings', array());

        if (empty($settings['disable_low_performance'])) {
            return;
        }

        $threshold = isset($settings['low_performance_threshold']) ? (int)$settings['low_performance_threshold'] : 30;
        $min_gens = isset($settings['min_generations_threshold']) ? (int)$settings['min_generations_threshold'] : 5;

        $stats = $this->history_repo->get_stats($template_id);

        if (!$stats || $stats['total'] < $min_gens) {
            return;
        }

        // Use the pre-calculated success_rate from history stats for threshold comparison.
        $success_rate = $stats['success_rate'];

        if ($success_rate < $threshold) {
            $this->template_repo->update($template_id, array('is_active' => 0));

            $logger = new AIPS_Logger();
            $logger->log("Template ID $template_id automatically disabled due to low success rate ({$success_rate}%)", 'warning');
        }
    }
}
