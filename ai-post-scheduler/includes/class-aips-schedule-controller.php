<?php
if (!defined('ABSPATH')) {
    exit;
}

class AIPS_Schedule_Controller {

    private $scheduler;

    /**
     * @var AIPS_Schedule_Repository_Interface
     */
    private $schedule_repository;

    /**
     * @var AIPS_History_Repository_Interface
     */
    private $history_repository;

    public function __construct($scheduler = null, ?AIPS_Schedule_Repository_Interface $schedule_repository = null, ?AIPS_History_Repository_Interface $history_repository = null) {
        $container = AIPS_Container::get_instance();
        $this->scheduler           = $scheduler ?: new AIPS_Scheduler();
        $this->schedule_repository = $schedule_repository ?: ($container->has(AIPS_Schedule_Repository_Interface::class) ? $container->make(AIPS_Schedule_Repository_Interface::class) : new AIPS_Schedule_Repository());
        $this->history_repository  = $history_repository ?: ($container->has(AIPS_History_Repository_Interface::class) ? $container->make(AIPS_History_Repository_Interface::class) : new AIPS_History_Repository());

        add_action('wp_ajax_aips_save_schedule', array($this, 'ajax_save_schedule'));
        add_action('wp_ajax_aips_delete_schedule', array($this, 'ajax_delete_schedule'));
        add_action('wp_ajax_aips_toggle_schedule', array($this, 'ajax_toggle_schedule'));
        add_action('wp_ajax_aips_run_now', array($this, 'ajax_run_now'));
        add_action('wp_ajax_aips_bulk_delete_schedules', array($this, 'ajax_bulk_delete_schedules'));
        add_action('wp_ajax_aips_bulk_toggle_schedules', array($this, 'ajax_bulk_toggle_schedules'));
        add_action('wp_ajax_aips_bulk_run_now_schedules', array($this, 'ajax_bulk_run_now_schedules'));
        add_action('wp_ajax_aips_get_schedules_post_count', array($this, 'ajax_get_schedules_post_count'));
        add_action('wp_ajax_aips_get_schedule_history', array($this, 'ajax_get_schedule_history'));

        // Unified schedule endpoints (all types)
        add_action('wp_ajax_aips_unified_run_now', array($this, 'ajax_unified_run_now'));
        add_action('wp_ajax_aips_unified_toggle', array($this, 'ajax_unified_toggle'));
        add_action('wp_ajax_aips_unified_bulk_toggle', array($this, 'ajax_unified_bulk_toggle'));
        add_action('wp_ajax_aips_unified_bulk_run_now', array($this, 'ajax_unified_bulk_run_now'));
        add_action('wp_ajax_aips_unified_bulk_delete', array($this, 'ajax_unified_bulk_delete'));
        add_action('wp_ajax_aips_get_unified_schedule_history', array($this, 'ajax_get_unified_schedule_history'));
    }

    public function ajax_save_schedule() {
        check_ajax_referer('aips_ajax_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            AIPS_Ajax_Response::permission_denied();
        }

        $data = array(
            'id' => isset($_POST['schedule_id']) ? absint($_POST['schedule_id']) : 0,
            'template_id' => isset($_POST['template_id']) ? absint($_POST['template_id']) : 0,
            'title' => isset($_POST['schedule_title']) ? sanitize_text_field(wp_unslash($_POST['schedule_title'])) : '',
            'frequency' => isset($_POST['frequency']) ? sanitize_text_field(wp_unslash($_POST['frequency'])) : 'daily',
            'start_time' => isset($_POST['start_time']) ? sanitize_text_field(wp_unslash($_POST['start_time'])) : null,
            'is_active' => isset($_POST['is_active']) && 1 === absint($_POST['is_active']) ? 1 : 0,
            'topic' => isset($_POST['topic']) ? sanitize_text_field(wp_unslash($_POST['topic'])) : '',
            'article_structure_id' => isset($_POST['article_structure_id']) && $_POST['article_structure_id'] !== '' ? absint($_POST['article_structure_id']) : null,
            'rotation_pattern' => isset($_POST['rotation_pattern']) && $_POST['rotation_pattern'] !== '' ? sanitize_text_field(wp_unslash($_POST['rotation_pattern'])) : null,
        );

        if (empty($data['template_id'])) {
            AIPS_Ajax_Response::error(__('Please select a template.', 'ai-post-scheduler'));
        }

        $interval_calculator = new AIPS_Interval_Calculator();
        if (!$interval_calculator->is_valid_frequency($data['frequency'])) {
            AIPS_Ajax_Response::error(__('Invalid frequency selected.', 'ai-post-scheduler'));
        }

        $id = $this->scheduler->save_schedule($data);

        if ($id) {
            AIPS_Ajax_Response::success(array(
                'message' => __('Schedule saved successfully.', 'ai-post-scheduler'),
                'schedule_id' => $id
            ));
        } else {
            AIPS_Ajax_Response::error(__('Failed to save schedule.', 'ai-post-scheduler'));
        }
    }

    public function ajax_delete_schedule() {
        check_ajax_referer('aips_ajax_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            AIPS_Ajax_Response::permission_denied();
        }

        $id = isset($_POST['schedule_id']) ? absint($_POST['schedule_id']) : 0;

        if (!$id) {
            AIPS_Ajax_Response::error(__('Invalid schedule ID.', 'ai-post-scheduler'));
        }

        if ($this->schedule_repository->delete($id)) {
            AIPS_Ajax_Response::success(array(), __('Schedule deleted successfully.', 'ai-post-scheduler'));
        } else {
            AIPS_Ajax_Response::error(__('Failed to delete schedule.', 'ai-post-scheduler'));
        }
    }

    public function ajax_toggle_schedule() {
        check_ajax_referer('aips_ajax_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            AIPS_Ajax_Response::permission_denied();
        }

        $id = isset($_POST['schedule_id']) ? absint($_POST['schedule_id']) : 0;
        $is_active = isset($_POST['is_active']) ? absint($_POST['is_active']) : 0;

        if (!$id) {
            AIPS_Ajax_Response::error(__('Invalid schedule ID.', 'ai-post-scheduler'));
        }

        $result = $this->scheduler->toggle_active($id, $is_active);

        if ($result !== false) {
            AIPS_Ajax_Response::success(array(), __('Schedule updated.', 'ai-post-scheduler'));
        } else {
            AIPS_Ajax_Response::error(__('Failed to update schedule.', 'ai-post-scheduler'));
        }
    }

    public function ajax_run_now() {
        check_ajax_referer('aips_ajax_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            AIPS_Ajax_Response::permission_denied();
        }

        // If schedule_id is provided, use the scheduler to run the schedule logic
        $schedule_id = isset($_POST['schedule_id']) ? absint($_POST['schedule_id']) : 0;
        if ($schedule_id) {
            $result = $this->scheduler->run_schedule_now($schedule_id);
            if (is_wp_error($result)) {
                AIPS_Ajax_Response::error(array('message' => $result->get_error_message()));
            } else {
                $post_ids = is_array($result) ? $result : array($result);
                $first_post_id = !empty($post_ids) ? $post_ids[0] : 0;
                $edit_url = $first_post_id ? esc_url_raw(get_edit_post_link($first_post_id, 'raw')) : '';

                $msg = sprintf(
                    _n('%d post generated successfully!', '%d posts generated successfully!', count($post_ids), 'ai-post-scheduler'),
                    count($post_ids)
                );

                 AIPS_Ajax_Response::success(array(
                    'message' => $msg,
                    'post_ids' => $post_ids,
                    'edit_url' => $edit_url
                ));
            }
            return;
        }

        $template_id = isset($_POST['template_id']) ? absint($_POST['template_id']) : 0;

        if (!$template_id) {
            AIPS_Ajax_Response::error(__('Invalid template ID.', 'ai-post-scheduler'));
        }

        $templates = new AIPS_Templates();
        $template = $templates->get($template_id);

        if (!$template) {
            AIPS_Ajax_Response::error(__('Template not found.', 'ai-post-scheduler'));
        }

        $voice = null;
        if (!empty($template->voice_id)) {
            $voices = new AIPS_Voices();
            $voice = $voices->get($template->voice_id);
        }

        $quantity = $template->post_quantity ?: 1;

        // SECURITY: Enforce a hard limit for immediate execution to prevent PHP timeouts
        // and potential API rate limiting issues.
        $max_run_now = 2;
        $capped = false;
        if ($quantity > $max_run_now) {
            $quantity = $max_run_now;
            $capped = true;
        }

        $post_ids = array();
        $errors = array();

        $generator = new AIPS_Generator();
        $topic = isset($_POST['topic']) ? sanitize_text_field(wp_unslash($_POST['topic'])) : '';

        for ($i = 0; $i < $quantity; $i++) {
            $result = $generator->generate_post($template, $voice, $topic);

            if ($result->is_failure()) {
                $errors[] = !empty($result->errors) ? implode(', ', $result->errors) : __('Generation failed', 'ai-post-scheduler');
            } else {
                $post_ids[] = $result->post_id;
            }
        }

        if (empty($post_ids) && !empty($errors)) {
            // All attempts failed
            $error_msg = count($errors) > 1
                ? __('All generation attempts failed.', 'ai-post-scheduler')
                : $errors[0];
            AIPS_Ajax_Response::error(array('message' => $error_msg, 'errors' => $errors));
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

        AIPS_Ajax_Response::success(array(
            'message' => $message,
            'post_ids' => $post_ids,
            'errors' => $errors,
            'edit_url' => !empty($post_ids) ? esc_url_raw(get_edit_post_link($post_ids[0], 'raw')) : ''
        ));
    }

    public function ajax_bulk_delete_schedules() {
        check_ajax_referer('aips_ajax_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            AIPS_Ajax_Response::permission_denied();
        }

        $ids = isset($_POST['ids']) && is_array($_POST['ids']) ? array_map('absint', $_POST['ids']) : array();
        $ids = array_filter($ids);

        if (empty($ids)) {
            AIPS_Ajax_Response::error(__('No schedule IDs provided.', 'ai-post-scheduler'));
        }

        $deleted = $this->schedule_repository->delete_bulk($ids);

        if ($deleted !== false) {
            AIPS_Ajax_Response::success(array(
                'message' => sprintf(
                    _n('%d schedule deleted successfully.', '%d schedules deleted successfully.', $deleted, 'ai-post-scheduler'),
                    $deleted
                ),
                'deleted' => $deleted,
            ));
        } else {
            AIPS_Ajax_Response::error(__('Failed to delete schedules.', 'ai-post-scheduler'));
        }
    }

    public function ajax_bulk_toggle_schedules() {
        check_ajax_referer('aips_ajax_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            AIPS_Ajax_Response::permission_denied();
        }

        $ids = isset($_POST['ids']) && is_array($_POST['ids']) ? array_map('absint', $_POST['ids']) : array();
        $ids = array_filter($ids);
        $is_active = isset($_POST['is_active']) ? absint($_POST['is_active']) : 0;

        if (empty($ids)) {
            AIPS_Ajax_Response::error(__('No schedule IDs provided.', 'ai-post-scheduler'));
        }

        $updated = $this->schedule_repository->set_active_bulk($ids, $is_active);

        if ($updated !== false) {
            $count = (int) $updated ?: count($ids);
            $action_label = $is_active ? __('activated', 'ai-post-scheduler') : __('paused', 'ai-post-scheduler');
            AIPS_Ajax_Response::success(array(
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
            AIPS_Ajax_Response::error(__('Failed to update schedules.', 'ai-post-scheduler'));
        }
    }

    public function ajax_bulk_run_now_schedules() {
        check_ajax_referer('aips_ajax_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            AIPS_Ajax_Response::permission_denied();
        }

        $ids = isset($_POST['ids']) && is_array($_POST['ids']) ? array_map('absint', $_POST['ids']) : array();
        $ids = array_filter($ids);

        if (empty($ids)) {
            AIPS_Ajax_Response::error(__('No schedule IDs provided.', 'ai-post-scheduler'));
        }

        $max_bulk_run = apply_filters('aips_bulk_run_now_limit', 5);
        if (count($ids) > $max_bulk_run) {
            AIPS_Ajax_Response::error(array(
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
            AIPS_Ajax_Response::error(array(
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

        AIPS_Ajax_Response::success(array(
            'message'  => $message,
            'post_ids' => $post_ids,
            'errors'   => $errors,
        ));
    }

    public function ajax_get_schedules_post_count() {
        check_ajax_referer('aips_ajax_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            AIPS_Ajax_Response::permission_denied();
        }

        $ids = isset($_POST['ids']) && is_array($_POST['ids']) ? array_map('absint', $_POST['ids']) : array();
        $ids = array_filter($ids);

        if (empty($ids)) {
            AIPS_Ajax_Response::success(array('count' => 0));
        }

        $count = $this->schedule_repository->get_post_count_for_schedules($ids);

        AIPS_Ajax_Response::success(array('count' => $count));
    }

    /**
     * AJAX handler to fetch the history log for a specific schedule.
     *
     * Returns all activity-type history entries associated with the schedule's
     * persistent lifecycle history container.
     */
    public function ajax_get_schedule_history() {
        check_ajax_referer('aips_ajax_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            AIPS_Ajax_Response::permission_denied();
        }

        $schedule_id = isset($_POST['schedule_id']) ? absint($_POST['schedule_id']) : 0;
        if (!$schedule_id) {
            AIPS_Ajax_Response::error(__('Invalid schedule ID.', 'ai-post-scheduler'));
        }

        $schedule = $this->schedule_repository->get_by_id($schedule_id);

        if (!$schedule) {
            AIPS_Ajax_Response::error(__('Schedule not found.', 'ai-post-scheduler'));
        }

        if (empty($schedule->schedule_history_id)) {
            AIPS_Ajax_Response::success(array('entries' => array()));
        }

        $logs = $this->history_repository->get_logs_by_history_id(
            absint($schedule->schedule_history_id),
            array(AIPS_History_Type::ACTIVITY, AIPS_History_Type::ERROR)
        );

        $entries = array();
        foreach ($logs as $log) {
            $details = array();

            if (!empty($log->details)) {
                $decoded_details = json_decode($log->details, true);
                if (is_array($decoded_details)) {
                    $details = $decoded_details;
                }
            }

            $input = array();
            if (isset($details['input']) && is_array($details['input'])) {
                $input = $details['input'];
            }

            $entries[] = array(
                'id' => absint($log->id),
                'timestamp' => esc_html($log->timestamp),
                'log_type' => esc_html($log->log_type),
                'history_type_id' => absint($log->history_type_id),
                'message' => isset($details['message']) ? esc_html($details['message']) : '',
                'event_type' => isset($input['event_type']) ? esc_html($input['event_type']) : '',
                'event_status' => isset($input['event_status']) ? esc_html($input['event_status']) : '',
                'context' => (isset($details['context']) && is_array($details['context'])) ? $details['context'] : array(),
            );
        }

        AIPS_Ajax_Response::success(array('entries' => $entries));
    }

    /**
     * AJAX: Run any schedule type immediately.
     *
     * Expects POST: id (int), type (string).
     */
    public function ajax_unified_run_now() {
        check_ajax_referer('aips_ajax_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            AIPS_Ajax_Response::permission_denied();
        }

        $id   = isset($_POST['id']) ? absint($_POST['id']) : 0;
        $type = isset($_POST['type']) ? sanitize_key(wp_unslash($_POST['type'])) : '';

        if (!$id || empty($type)) {
            AIPS_Ajax_Response::error(__('Invalid parameters.', 'ai-post-scheduler'));
        }

        $service = new AIPS_Unified_Schedule_Service();
        $result  = $service->run_now($id, $type);

        if (is_wp_error($result)) {
            AIPS_Ajax_Response::error(array('message' => $result->get_error_message()));
        }

        // Format the success message based on type.
        if ($type === AIPS_Unified_Schedule_Service::TYPE_TEMPLATE) {
            $post_ids = is_array($result) ? $result : array($result);
            $first_post_id = !empty($post_ids) ? $post_ids[0] : 0;
            $edit_url = $first_post_id ? esc_url_raw(get_edit_post_link($first_post_id, 'raw')) : '';

            $msg = sprintf(
                _n('Schedule executed — %d post generated successfully!', 'Schedule executed — %d posts generated successfully!', count($post_ids), 'ai-post-scheduler'),
                count($post_ids)
            );

            AIPS_Ajax_Response::success(array(
                'message'  => $msg,
                'post_ids' => $post_ids,
                'post_id'  => $first_post_id, // keep post_id for backward compatibility
                'edit_url' => $edit_url,
            ));
        } elseif ($type === AIPS_Unified_Schedule_Service::TYPE_AUTHOR_TOPIC) {
            $count = is_array($result) ? count($result) : 0;
            AIPS_Ajax_Response::success(array(
                'message' => sprintf(
                    _n('%d topic generated successfully!', '%d topics generated successfully!', $count, 'ai-post-scheduler'),
                    $count
                ),
            ));
        } elseif ($type === AIPS_Unified_Schedule_Service::TYPE_AUTHOR_POST) {
            $post_id  = is_int($result) ? $result : 0;
            $edit_url = $post_id ? esc_url_raw(get_edit_post_link($post_id, 'raw')) : '';
            AIPS_Ajax_Response::success(array(
                'message'  => __('Post generated successfully from author topic!', 'ai-post-scheduler'),
                'post_id'  => $post_id,
                'edit_url' => $edit_url,
            ));
        } else {
            AIPS_Ajax_Response::success(array(), __('Schedule executed successfully.', 'ai-post-scheduler'));
        }
    }

    /**
     * AJAX: Toggle active status for any schedule type.
     *
     * Expects POST: id (int), type (string), is_active (0|1).
     */
    public function ajax_unified_toggle() {
        check_ajax_referer('aips_ajax_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            AIPS_Ajax_Response::permission_denied();
        }

        $id        = isset($_POST['id']) ? absint($_POST['id']) : 0;
        $type      = isset($_POST['type']) ? sanitize_key(wp_unslash($_POST['type'])) : '';
        $is_active = isset($_POST['is_active']) ? absint($_POST['is_active']) : 0;

        if (!$id || empty($type)) {
            AIPS_Ajax_Response::error(__('Invalid parameters.', 'ai-post-scheduler'));
        }

        $service = new AIPS_Unified_Schedule_Service();
        $result  = $service->toggle($id, $type, $is_active);

        if ($result !== false) {
            $label = $is_active
                ? __('Schedule activated.', 'ai-post-scheduler')
                : __('Schedule paused.', 'ai-post-scheduler');
            AIPS_Ajax_Response::success(array('message' => $label, 'is_active' => $is_active));
        } else {
            AIPS_Ajax_Response::error(__('Failed to update schedule.', 'ai-post-scheduler'));
        }
    }

    /**
     * AJAX: Bulk pause or resume multiple schedules of mixed types.
     *
     * Expects POST: items (array of {id, type}), is_active (0|1).
     */
    public function ajax_unified_bulk_toggle() {
        check_ajax_referer('aips_ajax_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            AIPS_Ajax_Response::permission_denied();
        }

        $items     = isset($_POST['items']) && is_array($_POST['items']) ? $_POST['items'] : array();
        $is_active = isset($_POST['is_active']) ? absint($_POST['is_active']) : 0;

        if (empty($items)) {
            AIPS_Ajax_Response::error(__('No items provided.', 'ai-post-scheduler'));
        }

        $service = new AIPS_Unified_Schedule_Service();
        $updated = 0;
        $errors  = array();

        foreach ($items as $item) {
            $id   = isset($item['id']) ? absint($item['id']) : 0;
            $type = isset($item['type']) ? sanitize_key($item['type']) : '';
            if (!$id || empty($type)) {
                continue;
            }
            $result = $service->toggle($id, $type, $is_active);
            if ($result !== false) {
                $updated++;
            } else {
                $errors[] = $id;
            }
        }

        $action_label = $is_active
            ? __('activated', 'ai-post-scheduler')
            : __('paused', 'ai-post-scheduler');

        AIPS_Ajax_Response::success(array(
            'message' => sprintf(
                /* translators: 1: count, 2: action */
                _n('%1$d schedule %2$s.', '%1$d schedules %2$s.', $updated, 'ai-post-scheduler'),
                $updated,
                $action_label
            ),
            'updated'   => $updated,
            'errors'    => $errors,
            'is_active' => $is_active,
        ));
    }

    /**
     * AJAX: Bulk run-now for multiple schedules of mixed types.
     *
     * Expects POST: items (array of {id, type}).
     */
    public function ajax_unified_bulk_run_now() {
        check_ajax_referer('aips_ajax_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            AIPS_Ajax_Response::permission_denied();
        }

        $items = isset($_POST['items']) && is_array($_POST['items']) ? $_POST['items'] : array();

        if (empty($items)) {
            AIPS_Ajax_Response::error(__('No items provided.', 'ai-post-scheduler'));
        }

        $max_bulk = apply_filters('aips_unified_bulk_run_now_limit', 5);
        if (count($items) > $max_bulk) {
            AIPS_Ajax_Response::error(array(
                'message' => sprintf(
                    /* translators: 1: selected count, 2: max allowed */
                    __('Too many schedules selected (%1$d). Please select no more than %2$d at a time.', 'ai-post-scheduler'),
                    count($items),
                    $max_bulk
                ),
            ));
        }

        $service  = new AIPS_Unified_Schedule_Service();
        $success  = 0;
        $errors   = array();

        foreach ($items as $item) {
            $id   = isset($item['id']) ? absint($item['id']) : 0;
            $type = isset($item['type']) ? sanitize_key($item['type']) : '';
            if (!$id || empty($type)) {
                continue;
            }

            $result = $service->run_now($id, $type);
            if (is_wp_error($result)) {
                $errors[] = sprintf(
                    /* translators: 1: ID, 2: error */
                    __('ID %1$d (%2$s): %3$s', 'ai-post-scheduler'),
                    $id,
                    $type,
                    $result->get_error_message()
                );
            } else {
                $success++;
            }
        }

        if ($success === 0 && !empty($errors)) {
            AIPS_Ajax_Response::error(array(
                'message' => __('All scheduled runs failed.', 'ai-post-scheduler'),
                'errors'  => $errors,
            ));
        }

        $message = sprintf(
            _n('%d schedule ran successfully!', '%d schedules ran successfully!', $success, 'ai-post-scheduler'),
            $success
        );
        if (!empty($errors)) {
            $message .= ' ' . sprintf(
                _n('(%d failed)', '(%d failed)', count($errors), 'ai-post-scheduler'),
                count($errors)
            );
        }

        AIPS_Ajax_Response::success(array(
            'message' => $message,
            'success' => $success,
            'errors'  => $errors,
        ));
    }

    /**
     * AJAX: Bulk delete schedules of mixed types.
     *
     * Expects POST: items (array of {id, type}).
     * Only template schedules are deletable.
     */
    public function ajax_unified_bulk_delete() {
        check_ajax_referer('aips_ajax_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            AIPS_Ajax_Response::permission_denied();
        }

        $items = isset($_POST['items']) && is_array($_POST['items']) ? $_POST['items'] : array();

        if (empty($items)) {
            AIPS_Ajax_Response::error(__('No items provided.', 'ai-post-scheduler'));
        }

        $service       = new AIPS_Unified_Schedule_Service();
        $deleted_count = 0;
        $deleted_items = array();
        $failed_items  = array();

        foreach ($items as $item) {
            $id   = isset($item['id']) ? absint($item['id']) : 0;
            $type = isset($item['type']) ? sanitize_key($item['type']) : '';

            if (!$id || empty($type)) {
                continue;
            }

            $result = $service->delete($id, $type);
            if (is_wp_error($result)) {
                $failed_items[] = array(
                    'id'      => $id,
                    'type'    => $type,
                    'message' => $result->get_error_message(),
                );
            } else {
                $deleted_count++;
                $deleted_items[] = array(
                    'id'   => $id,
                    'type' => $type,
                );
            }
        }

        if ($deleted_count === 0) {
            AIPS_Ajax_Response::error(array(
                'message'      => __('No selected schedules could be deleted.', 'ai-post-scheduler'),
                'deleted'      => 0,
                'deleted_items'=> array(),
                'failed_items' => $failed_items,
            ));
        }

        $message = sprintf(
            _n('%d schedule deleted successfully.', '%d schedules deleted successfully.', $deleted_count, 'ai-post-scheduler'),
            $deleted_count
        );

        if (!empty($failed_items)) {
            $message .= ' ' . sprintf(
                _n('(%d could not be deleted)', '(%d could not be deleted)', count($failed_items), 'ai-post-scheduler'),
                count($failed_items)
            );
        }

        AIPS_Ajax_Response::success(array(
            'message'      => $message,
            'deleted'      => $deleted_count,
            'deleted_items'=> $deleted_items,
            'failed_items' => $failed_items,
        ));
    }

    /**
     * AJAX: Get run-history for any schedule type.
     *
     * Expects POST: id (int), type (string).
     */
    public function ajax_get_unified_schedule_history() {
        check_ajax_referer('aips_ajax_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            AIPS_Ajax_Response::permission_denied();
        }

        $id    = isset($_POST['id']) ? absint($_POST['id']) : 0;
        $type  = isset($_POST['type']) ? sanitize_key(wp_unslash($_POST['type'])) : '';
        $limit = isset($_POST['limit']) ? absint($_POST['limit']) : 0;

        if (!$id || empty($type)) {
            AIPS_Ajax_Response::error(__('Invalid parameters.', 'ai-post-scheduler'));
        }

        $service = new AIPS_Unified_Schedule_Service();
        $entries = $service->get_history($id, $type, $limit);

        AIPS_Ajax_Response::success(array('entries' => $entries));
    }
}
