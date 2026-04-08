<?php
if (!defined('ABSPATH')) {
    exit;
}

class AIPS_Seeder_Admin {

    private $service;

    public function __construct() {
        add_action('wp_ajax_aips_process_seeder', array($this, 'ajax_process_seeder'));

        $this->service = new AIPS_Seeder_Service();
    }


    public function ajax_process_seeder() {
        check_ajax_referer('aips_ajax_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied.', 'ai-post-scheduler')));
        }

        $type = isset($_POST['type']) ? sanitize_text_field(wp_unslash($_POST['type'])) : '';
        $count = isset($_POST['count']) ? absint($_POST['count']) : 0;
        $keywords = isset($_POST['keywords']) ? sanitize_textarea_field(wp_unslash($_POST['keywords'])) : '';

        if (empty($type)) {
            wp_send_json_error(array('message' => __('Missing type.', 'ai-post-scheduler')));
        }

        // Increase timeout for AI generation
        if (function_exists('set_time_limit')) {
            set_time_limit(300);
        }

        $result = $this->service->seed($type, $count, $keywords);

        if ($result['success']) {
            do_action('aips_seeder_completed', array(
                'type'    => $type,
                'count'   => $count,
                'message' => isset($result['message']) ? $result['message'] : __('Seeder completed.', 'ai-post-scheduler'),
                'user_id' => get_current_user_id(),
            ));

            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }
}
