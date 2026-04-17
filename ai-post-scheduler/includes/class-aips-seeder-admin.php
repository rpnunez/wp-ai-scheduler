<?php
if (!defined('ABSPATH')) {
    exit;
}

class AIPS_Seeder_Admin {

    private $service;

    public function __construct() {
        add_action('admin_enqueue_scripts', array($this, 'enqueue_assets'));
        add_action('wp_ajax_aips_process_seeder', array($this, 'ajax_process_seeder'));

        $this->service = new AIPS_Seeder_Service();
    }

    public function enqueue_assets($hook) {
        if (strpos($hook, 'aips-seeder') === false) {
            return;
        }

        wp_enqueue_script(
            'aips-admin-seeder',
            AIPS_PLUGIN_URL . 'assets/js/admin-seeder.js',
            array('jquery', 'aips-admin-script'), // Depends on core admin script
            AIPS_VERSION,
            true
        );
    }

    public function ajax_process_seeder() {
        if ( ! check_ajax_referer('aips_ajax_nonce', 'nonce', false) ) {
            AIPS_Ajax_Response::error(__('Invalid nonce.', 'ai-post-scheduler'));
        }

        if (!current_user_can('manage_options')) {
            AIPS_Ajax_Response::permission_denied();
        }

        $type = isset($_POST['type']) ? sanitize_text_field(wp_unslash($_POST['type'])) : '';
        $count = isset($_POST['count']) ? absint($_POST['count']) : 0;
        $keywords = isset($_POST['keywords']) ? sanitize_textarea_field(wp_unslash($_POST['keywords'])) : '';

        if (empty($type)) {
            AIPS_Ajax_Response::error(__('Missing type.', 'ai-post-scheduler'));
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

            AIPS_Ajax_Response::success($result);
        } else {
            AIPS_Ajax_Response::error($result);
        }
    }
}
