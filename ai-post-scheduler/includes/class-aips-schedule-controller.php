<?php
if (!defined('ABSPATH')) {
    exit;
}

class AIPS_Schedule_Controller {

    private $scheduler;

    public function __construct() {
        $this->scheduler = new AIPS_Scheduler();

        add_action('wp_ajax_aips_save_schedule', array($this, 'ajax_save_schedule'));
        add_action('wp_ajax_aips_delete_schedule', array($this, 'ajax_delete_schedule'));
        add_action('wp_ajax_aips_toggle_schedule', array($this, 'ajax_toggle_schedule'));
        add_action('wp_ajax_aips_run_now', array($this, 'ajax_run_now'));
    }

    public function ajax_save_schedule() {
        check_ajax_referer('aips_ajax_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied.', 'ai-post-scheduler')));
        }

        // SECURITY: Get configurable limits
        $limit_frequency = get_option('aips_limit_frequency', 50);
        $limit_start_time = get_option('aips_limit_start_time', 50);
        $limit_topic = get_option('aips_limit_topic', 1000);
        $limit_rotation_pattern = get_option('aips_limit_rotation_pattern', 100);

        $data = array(
            'id' => isset($_POST['schedule_id']) ? absint($_POST['schedule_id']) : 0,
            'template_id' => isset($_POST['template_id']) ? absint($_POST['template_id']) : 0,
            // SECURITY: Add length limit to prevent potential DoS or DB truncation issues
            'frequency' => isset($_POST['frequency']) ? mb_substr(sanitize_text_field($_POST['frequency']), 0, $limit_frequency) : 'daily',
            'start_time' => isset($_POST['start_time']) ? mb_substr(sanitize_text_field($_POST['start_time']), 0, $limit_start_time) : null,
            'is_active' => isset($_POST['is_active']) ? 1 : 0,
            // SECURITY: Limit topic length to reasonable size (configurable)
            'topic' => isset($_POST['topic']) ? mb_substr(sanitize_text_field($_POST['topic']), 0, $limit_topic) : '',
            'article_structure_id' => isset($_POST['article_structure_id']) && $_POST['article_structure_id'] !== '' ? absint($_POST['article_structure_id']) : null,
            'rotation_pattern' => isset($_POST['rotation_pattern']) && $_POST['rotation_pattern'] !== '' ? mb_substr(sanitize_text_field($_POST['rotation_pattern']), 0, $limit_rotation_pattern) : null,
        );

        if (empty($data['template_id'])) {
            wp_send_json_error(array('message' => __('Please select a template.', 'ai-post-scheduler')));
        }

        $id = $this->scheduler->save_schedule($data);

        if ($id) {
            wp_send_json_success(array(
                'message' => __('Schedule saved successfully.', 'ai-post-scheduler'),
                'schedule_id' => $id
            ));
        } else {
            wp_send_json_error(array('message' => __('Failed to save schedule.', 'ai-post-scheduler')));
        }
    }

    public function ajax_delete_schedule() {
        check_ajax_referer('aips_ajax_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied.', 'ai-post-scheduler')));
        }

        $id = isset($_POST['schedule_id']) ? absint($_POST['schedule_id']) : 0;

        if (!$id) {
            wp_send_json_error(array('message' => __('Invalid schedule ID.', 'ai-post-scheduler')));
        }

        if ($this->scheduler->delete_schedule($id)) {
            wp_send_json_success(array('message' => __('Schedule deleted successfully.', 'ai-post-scheduler')));
        } else {
            wp_send_json_error(array('message' => __('Failed to delete schedule.', 'ai-post-scheduler')));
        }
    }

    public function ajax_toggle_schedule() {
        check_ajax_referer('aips_ajax_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied.', 'ai-post-scheduler')));
        }

        $id = isset($_POST['schedule_id']) ? absint($_POST['schedule_id']) : 0;
        $is_active = isset($_POST['is_active']) ? absint($_POST['is_active']) : 0;

        if (!$id) {
            wp_send_json_error(array('message' => __('Invalid schedule ID.', 'ai-post-scheduler')));
        }

        // We use the new toggle_active method in Scheduler
        if (method_exists($this->scheduler, 'toggle_active')) {
             $result = $this->scheduler->toggle_active($id, $is_active);
        } else {
             // Fallback
             global $wpdb;
             $table_name = $wpdb->prefix . 'aips_schedule';
             $result = $wpdb->update(
                $table_name,
                array('is_active' => $is_active),
                array('id' => $id),
                array('%d'),
                array('%d')
            );
        }

        if ($result !== false) {
            wp_send_json_success(array('message' => __('Schedule updated.', 'ai-post-scheduler')));
        } else {
            wp_send_json_error(array('message' => __('Failed to update schedule.', 'ai-post-scheduler')));
        }
    }

    public function ajax_run_now() {
        check_ajax_referer('aips_ajax_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied.', 'ai-post-scheduler')));
        }

        $template_id = isset($_POST['template_id']) ? absint($_POST['template_id']) : 0;

        if (!$template_id) {
            wp_send_json_error(array('message' => __('Invalid template ID.', 'ai-post-scheduler')));
        }

        $templates = new AIPS_Templates();
        $template = $templates->get($template_id);

        if (!$template) {
            wp_send_json_error(array('message' => __('Template not found.', 'ai-post-scheduler')));
        }

        $voice = null;
        if (!empty($template->voice_id)) {
            $voices = new AIPS_Voices();
            $voice = $voices->get($template->voice_id);
        }

        $quantity = $template->post_quantity ?: 1;
        $post_ids = array();

        $generator = new AIPS_Generator();

        for ($i = 0; $i < $quantity; $i++) {
            $result = $generator->generate_post($template, $voice);

            if (is_wp_error($result)) {
                wp_send_json_error(array('message' => $result->get_error_message()));
            }

            $post_ids[] = $result;
        }

        $message = sprintf(
            __('%d post(s) generated successfully!', 'ai-post-scheduler'),
            count($post_ids)
        );

        wp_send_json_success(array(
            'message' => $message,
            'post_ids' => $post_ids,
            'edit_url' => !empty($post_ids) ? get_edit_post_link($post_ids[0], 'raw') : ''
        ));
    }
}
