<?php
if (!defined('ABSPATH')) {
    exit;
}

class AIPS_Seeder_Admin {

    private $service;

    public function __construct() {
        add_action('admin_menu', array($this, 'add_menu_page'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_assets'));
        add_action('wp_ajax_aips_process_seeder', array($this, 'ajax_process_seeder'));

        $this->service = new AIPS_Seeder_Service();
    }

    public function add_menu_page() {
        add_submenu_page(
            'ai-post-scheduler',
            __('Seeder', 'ai-post-scheduler'),
            __('Seeder', 'ai-post-scheduler'),
            'manage_options',
            'aips-seeder',
            array($this, 'render_page')
        );
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

        // Localize strings for the seeder UI
        $l10n = array(
            'enterQuantity' => __('Please enter at least one quantity.', 'ai-post-scheduler'),
            'confirmMessage' => __('This will generate dummy data in your database. Are you sure?', 'ai-post-scheduler'),
            'startingSeeder' => __('Starting Seeder...', 'ai-post-scheduler'),
            'allDone' => __('All Done!', 'ai-post-scheduler'),
            'generating' => __('Generating %count% %label%...', 'ai-post-scheduler'),
            'completedDefault' => __('Completed', 'ai-post-scheduler'),
            'unknownError' => __('Unknown error', 'ai-post-scheduler'),
            'ajaxErrorPrefix' => __('AJAX Error: ', 'ai-post-scheduler'),
        );

        wp_localize_script('aips-admin-seeder', 'aipsSeederL10n', $l10n);
    }

    public function render_page() {
        include AIPS_PLUGIN_DIR . 'templates/admin/seeder.php';
    }

    public function ajax_process_seeder() {
        check_ajax_referer('aips_ajax_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied.', 'ai-post-scheduler')));
        }

        $type = isset($_POST['type']) ? sanitize_text_field($_POST['type']) : '';
        $count = isset($_POST['count']) ? absint($_POST['count']) : 0;
        $keywords = isset($_POST['keywords']) ? sanitize_textarea_field($_POST['keywords']) : '';

        if (empty($type)) {
            wp_send_json_error(array('message' => 'Missing type.'));
        }

        // Increase timeout for AI generation
        if (function_exists('set_time_limit')) {
            set_time_limit(300);
        }

        $result = $this->service->seed($type, $count, $keywords);

        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }
}
