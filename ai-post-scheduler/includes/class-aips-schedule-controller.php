<?php
if (!defined('ABSPATH')) {
    exit;
}

class AIPS_Schedule_Controller {

    private $scheduler;

    public function __construct($scheduler = null) {
        $this->scheduler = $scheduler ?: new AIPS_Scheduler();

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

        $data = array(
            'id' => isset($_POST['schedule_id']) ? absint($_POST['schedule_id']) : 0,
            'template_id' => isset($_POST['template_id']) ? absint($_POST['template_id']) : 0,
            'frequency' => isset($_POST['frequency']) ? sanitize_text_field($_POST['frequency']) : 'daily',
            'start_time' => isset($_POST['start_time']) ? sanitize_text_field($_POST['start_time']) : null,
            'is_active' => isset($_POST['is_active']) ? 1 : 0,
            'topic' => isset($_POST['topic']) ? sanitize_text_field($_POST['topic']) : '',
            'article_structure_id' => isset($_POST['article_structure_id']) && $_POST['article_structure_id'] !== '' ? absint($_POST['article_structure_id']) : null,
            'rotation_pattern' => isset($_POST['rotation_pattern']) && $_POST['rotation_pattern'] !== '' ? sanitize_text_field($_POST['rotation_pattern']) : null,
        );

        if (empty($data['template_id'])) {
            wp_send_json_error(array('message' => __('Please select a template.', 'ai-post-scheduler')));
        }

        $interval_calculator = new AIPS_Interval_Calculator();
        if (!$interval_calculator->is_valid_frequency($data['frequency'])) {
            wp_send_json_error(array('message' => __('Invalid frequency selected.', 'ai-post-scheduler')));
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

        $result = $this->scheduler->toggle_active($id, $is_active);

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

        $schedule_id = isset($_POST['schedule_id']) ? absint($_POST['schedule_id']) : 0;
        $template_id = isset($_POST['template_id']) ? absint($_POST['template_id']) : 0;
        $topic = isset($_POST['topic']) ? sanitize_text_field($_POST['topic']) : '';

        // If schedule_id provided, get details from schedule
        if ($schedule_id) {
            $schedule = $this->scheduler->get_schedule($schedule_id);
            if (!$schedule) {
                wp_send_json_error(array('message' => __('Schedule not found.', 'ai-post-scheduler')));
            }
            $template_id = $schedule->template_id;

            // Override topic if set in schedule
            if (!empty($schedule->topic)) {
                $topic = $schedule->topic;
            }
        }

        if (!$template_id) {
            wp_send_json_error(array('message' => __('Invalid template ID.', 'ai-post-scheduler')));
        }

        $templates = new AIPS_Templates();
        $template = $templates->get($template_id);

        if (!$template) {
            wp_send_json_error(array('message' => __('Template not found.', 'ai-post-scheduler')));
        }

        // Apply schedule-specific overrides to template
        if ($schedule_id && isset($schedule)) {
            // If schedule has specific structure, use it
            if (!empty($schedule->article_structure_id)) {
                $template->article_structure_id = $schedule->article_structure_id;
            }
            // If rotation pattern, select structure
            else if (!empty($schedule->rotation_pattern)) {
                $selector = new AIPS_Template_Type_Selector();
                $template->article_structure_id = $selector->select_structure($schedule);
            }

            // Force quantity to 1 for schedule runs
            $template->post_quantity = 1;
        }

        $voice = null;
        if (!empty($template->voice_id)) {
            $voices = new AIPS_Voices();
            $voice = $voices->get($template->voice_id);
        }

        $quantity = $template->post_quantity ?: 1;

        // SECURITY: Enforce a hard limit for immediate execution to prevent PHP timeouts
        // and potential API rate limiting issues.
        $max_run_now = 5;
        $capped = false;
        if ($quantity > $max_run_now) {
            $quantity = $max_run_now;
            $capped = true;
        }

        $post_ids = array();
        $errors = array();

        $generator = new AIPS_Generator();

        // Enforce hard limit of 5 to prevent timeouts (Bolt)
        if ($quantity > 5) {
            $quantity = 5;
        }

        for ($i = 0; $i < $quantity; $i++) {
            $result = $generator->generate_post($template, $voice, $topic);

            if (is_wp_error($result)) {
                $errors[] = $result->get_error_message();
            } else {
                $post_ids[] = $result;
            }
        }

        if (empty($post_ids) && !empty($errors)) {
            // All attempts failed
            $error_msg = count($errors) > 1
                ? __('All generation attempts failed.', 'ai-post-scheduler')
                : $errors[0];
            wp_send_json_error(array('message' => $error_msg, 'errors' => $errors));
        }

        $message = sprintf(
            __('%d post(s) generated successfully!', 'ai-post-scheduler'),
            count($post_ids)
        );

        if ($capped) {
            $message .= ' ' . sprintf(
                __('(Limited to %d for manual run)', 'ai-post-scheduler'),
                $max_run_now
            );
        }

        if (!empty($errors)) {
            $message .= ' ' . sprintf(
                __('(%d failed attempts)', 'ai-post-scheduler'),
                count($errors)
            );
        }

        wp_send_json_success(array(
            'message' => $message,
            'post_ids' => $post_ids,
            'errors' => $errors,
            'edit_url' => !empty($post_ids) ? get_edit_post_link($post_ids[0], 'raw') : ''
        ));
    }
}
