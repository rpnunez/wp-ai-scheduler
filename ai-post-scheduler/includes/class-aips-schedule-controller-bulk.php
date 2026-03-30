<?php
/**
 * Bulk actions controller for the Schedule feature.
 *
 * @package AI_Post_Scheduler
 */

if (!defined('ABSPATH')) {
    exit;
}

class AIPS_Schedule_Controller_Bulk {

    private $scheduler;

    public function __construct($scheduler = null) {
        $this->scheduler = $scheduler ?: new AIPS_Scheduler();

        add_action('wp_ajax_aips_bulk_delete_schedules', array($this, 'ajax_bulk_delete_schedules'));
        add_action('wp_ajax_aips_bulk_toggle_schedules', array($this, 'ajax_bulk_toggle_schedules'));
        add_action('wp_ajax_aips_bulk_run_now_schedules', array($this, 'ajax_bulk_run_now_schedules'));
        add_action('wp_ajax_aips_get_schedules_post_count', array($this, 'ajax_get_schedules_post_count'));
    }

    public function ajax_bulk_delete_schedules() {
        check_ajax_referer('aips_ajax_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied.', 'ai-post-scheduler')));
        }

        $ids = isset($_POST['ids']) && is_array($_POST['ids']) ? array_map('absint', $_POST['ids']) : array();
        $ids = array_filter($ids);

        if (empty($ids)) {
            wp_send_json_error(array('message' => __('No schedule IDs provided.', 'ai-post-scheduler')));
        }

        $repository = new AIPS_Schedule_Repository();
        $deleted = $repository->delete_bulk($ids);

        if ($deleted !== false) {
            wp_send_json_success(array(
                'message' => sprintf(
                    _n('%d schedule deleted successfully.', '%d schedules deleted successfully.', $deleted, 'ai-post-scheduler'),
                    $deleted
                ),
                'deleted' => $deleted,
            ));
        } else {
            wp_send_json_error(array('message' => __('Failed to delete schedules.', 'ai-post-scheduler')));
        }
    }

    public function ajax_bulk_toggle_schedules() {
        check_ajax_referer('aips_ajax_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied.', 'ai-post-scheduler')));
        }

        $ids = isset($_POST['ids']) && is_array($_POST['ids']) ? array_map('absint', $_POST['ids']) : array();
        $ids = array_filter($ids);
        $is_active = isset($_POST['is_active']) ? absint($_POST['is_active']) : 0;

        if (empty($ids)) {
            wp_send_json_error(array('message' => __('No schedule IDs provided.', 'ai-post-scheduler')));
        }

        $repository = new AIPS_Schedule_Repository();
        $updated = $repository->set_active_bulk($ids, $is_active);

        if ($updated !== false) {
            $count = (int) $updated ?: count($ids);
            $action_label = $is_active ? __('activated', 'ai-post-scheduler') : __('paused', 'ai-post-scheduler');
            wp_send_json_success(array(
                'message' => sprintf(
                    /* translators: 1: number of schedules, 2: action label (activated/paused) */
                    _n('%1$d schedule %2$s successfully.', '%1$d schedules %2$s successfully.', $count, 'ai-post-scheduler'),
                    $count,
                    $action_label
                ),
                'updated' => $updated,
                'is_active' => $is_active,
            ));
        } else {
            wp_send_json_error(array('message' => __('Failed to update schedules.', 'ai-post-scheduler')));
        }
    }

    public function ajax_bulk_run_now_schedules() {
        check_ajax_referer('aips_ajax_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied.', 'ai-post-scheduler')));
        }

        $ids = isset($_POST['ids']) && is_array($_POST['ids']) ? array_map('absint', $_POST['ids']) : array();
        $ids = array_filter($ids);

        if (empty($ids)) {
            wp_send_json_error(array('message' => __('No schedule IDs provided.', 'ai-post-scheduler')));
        }

        $max_bulk_run = apply_filters('aips_bulk_run_now_limit', 5);
        if (count($ids) > $max_bulk_run) {
            wp_send_json_error(array(
                'message' => sprintf(
                    /* translators: 1: selected count, 2: maximum allowed */
                    __('Too many schedules selected (%1$d). Please select no more than %2$d at a time to avoid timeouts.', 'ai-post-scheduler'),
                    count($ids),
                    $max_bulk_run
                ),
            ));
        }

        $post_ids = array();
        $errors = array();

        foreach ($ids as $schedule_id) {
            $result = $this->scheduler->run_schedule_now($schedule_id);
            if (is_wp_error($result)) {
                $errors[] = sprintf(
                    /* translators: 1: schedule ID, 2: error message */
                    __('Schedule #%1$d: %2$s', 'ai-post-scheduler'),
                    $schedule_id,
                    $result->get_error_message()
                );
            } else {
                $schedule_post_ids = is_array($result) ? $result : array($result);
                $post_ids = array_merge($post_ids, $schedule_post_ids);
            }
        }

        if (empty($post_ids) && !empty($errors)) {
            wp_send_json_error(array(
                'message' => __('All schedule runs failed.', 'ai-post-scheduler'),
                'errors'  => $errors,
            ));
        }

        $message = sprintf(
            _n('%d post generated successfully!', '%d posts generated successfully!', count($post_ids), 'ai-post-scheduler'),
            count($post_ids)
        );

        if (!empty($errors)) {
            $message .= ' ' . sprintf(
                _n('(%d failed)', '(%d failed)', count($errors), 'ai-post-scheduler'),
                count($errors)
            );
        }

        wp_send_json_success(array(
            'message'  => $message,
            'post_ids' => $post_ids,
            'errors'   => $errors,
        ));
    }

    public function ajax_get_schedules_post_count() {
        check_ajax_referer('aips_ajax_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied.', 'ai-post-scheduler')));
        }

        $ids = isset($_POST['ids']) && is_array($_POST['ids']) ? array_map('absint', $_POST['ids']) : array();
        $ids = array_filter($ids);

        if (empty($ids)) {
            wp_send_json_success(array('count' => 0));
        }

        $repository = new AIPS_Schedule_Repository();
        $count = $repository->get_post_count_for_schedules($ids);

        wp_send_json_success(array('count' => $count));
    }
}
