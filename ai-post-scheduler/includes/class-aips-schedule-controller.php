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
        add_action('wp_ajax_aips_get_unified_schedule_history', array($this, 'ajax_get_unified_schedule_history'));
    }

    public function ajax_save_schedule() {
        check_ajax_referer('aips_ajax_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied.', 'ai-post-scheduler')));
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
            wp_send_json_error(array('message' => __('Please select a template.', 'ai-post-scheduler')));
        }

        $interval_calculator = new AIPS_Interval_Calculator();
        if (!$interval_calculator->is_valid_frequency($data['frequency'])) {
            wp_send_json_error(array('message' => __('Invalid frequency selected.', 'ai-post-scheduler')));
        }

        $id = $this->scheduler->save_schedule($data);

        if ($id) {
            $row_tokens = $this->build_schedule_row_tokens($id);
            wp_send_json_success(array(
                'message'     => __('Schedule saved successfully.', 'ai-post-scheduler'),
                'schedule_id' => $id,
                'row'         => $row_tokens,
                'is_update'   => !empty($data['id']),
            ));
        } else {
            wp_send_json_error(array('message' => __('Failed to save schedule.', 'ai-post-scheduler')));
        }
    }

    /**
     * Build the template token data for a single schedule row in the unified table.
     *
     * Returns an associative array whose keys match the `{{tokens}}` in the
     * `aips-tmpl-unified-schedule-row` client-side template.  Complex
     * conditional markup (type badge, status badge, action buttons, last/next
     * run cells) is pre-rendered here as safe HTML strings so the JS side can
     * use `AIPS.Templates.renderRaw()` without double-escaping issues.
     *
     * @param int $schedule_id The ID of the saved schedule.
     * @return array Token map, or empty array when the schedule cannot be found.
     */
    private function build_schedule_row_tokens($schedule_id) {
        $repository = new AIPS_Schedule_Repository();
        $schedule   = $repository->get_by_id($schedule_id);

        // Guard against null or incomplete objects (e.g. from test mocks).
        if (!$schedule || !isset($schedule->template_id, $schedule->frequency, $schedule->is_active)) {
            return array();
        }

        $is_active   = (int) $schedule->is_active;
        $status      = $is_active ? 'active' : 'inactive';
        $type        = AIPS_Unified_Schedule_Service::TYPE_TEMPLATE;
        $row_key     = esc_attr($type . ':' . $schedule->id);
        $date_format = get_option('date_format') . ' ' . get_option('time_format');

        // Status badge
        switch ($status) {
            case 'failed':
                $badge_cls  = 'aips-badge-error';
                $icon_cls   = 'dashicons-warning';
                $status_lbl = __('Failed', 'ai-post-scheduler');
                break;
            case 'inactive':
                $badge_cls  = 'aips-badge-neutral';
                $icon_cls   = 'dashicons-minus';
                $status_lbl = __('Paused', 'ai-post-scheduler');
                break;
            default:
                $badge_cls  = 'aips-badge-success';
                $icon_cls   = 'dashicons-yes-alt';
                $status_lbl = __('Active', 'ai-post-scheduler');
        }

        $status_badge_html = sprintf(
            '<span class="aips-badge %s"><span class="dashicons %s"></span> %s</span>',
            esc_attr($badge_cls),
            esc_attr($icon_cls),
            esc_html($status_lbl)
        );

        // Type badge
        $type_badge_html = '<span class="aips-badge aips-badge-type-template">' . esc_html__('Post Generation', 'ai-post-scheduler') . '</span>';

        // Frequency label
        $schedules_list    = wp_get_schedules();
        $frequency         = !empty($schedule->frequency) ? $schedule->frequency : '';
        $frequency_label   = isset($schedules_list[$frequency])
            ? $schedules_list[$frequency]['display']
            : ucfirst(str_replace('_', ' ', $frequency));

        // Determine display title (mirrors AIPS_Unified_Schedule_Service logic)
        $template_name = !empty($schedule->template_name)
            ? $schedule->template_name
            : $this->get_template_name((int) $schedule->template_id);
        $title = !empty($schedule->title) ? $schedule->title
            : ($template_name ?: sprintf(__('Schedule #%d', 'ai-post-scheduler'), $schedule->id));

        // Subtitle (template name)
        $subtitle     = $template_name ?: __('Unknown Template', 'ai-post-scheduler');
        $subtitle_html = sprintf(
            '<div class="cell-meta">%s</div>',
            esc_html($subtitle)
        );

        // Last run cell
        $last_run_ts = !empty($schedule->last_run) ? strtotime($schedule->last_run) : 0;
        if ($last_run_ts) {
            $last_run_html = sprintf(
                '<div class="cell-meta">%s</div><div class="cell-meta aips-muted" style="font-size:11px;">%s</div>',
                esc_html(date_i18n($date_format, $last_run_ts)),
                esc_html__('Generated post from template', 'ai-post-scheduler')
            );
        } else {
            $last_run_html = sprintf('<div class="cell-meta aips-muted">%s</div>', esc_html__('Never', 'ai-post-scheduler'));
        }

        // Next run cell
        $next_run_ts = !empty($schedule->next_run) ? strtotime($schedule->next_run) : 0;
        if ($next_run_ts) {
            $next_run_html = sprintf(
                '<div class="cell-meta">%s</div><div class="cell-meta aips-muted" style="font-size:11px;">%s</div>',
                esc_html(date_i18n($date_format, $next_run_ts)),
                esc_html__('Expected output: generated post', 'ai-post-scheduler')
            );
            if ($next_run_ts < time() && $is_active) {
                $next_run_html .= sprintf(
                    '<div class="cell-meta" style="color:var(--aips-warning);font-size:11px;">%s</div>',
                    esc_html__('Due — runs on next cron trigger', 'ai-post-scheduler')
                );
            }
        } else {
            $next_run_html = sprintf('<div class="cell-meta aips-muted">%s</div>', esc_html__('—', 'ai-post-scheduler'));
        }

        // Action buttons (edit + run now + delete)
        $actions_html = sprintf(
            '<button class="aips-btn aips-btn-sm aips-btn-ghost aips-edit-schedule"
                aria-label="%s" title="%s"
                data-schedule-id="%d"
                data-template-id="%d"
                data-title="%s"
                data-frequency="%s"
                data-next-run="%s"
                data-is-active="%d">
                <span class="dashicons dashicons-edit"></span>
            </button>',
            esc_attr__('Edit schedule', 'ai-post-scheduler'),
            esc_attr__('Edit', 'ai-post-scheduler'),
            absint($schedule->id),
            absint($schedule->template_id),
            esc_attr($title),
            esc_attr($frequency),
            esc_attr($schedule->next_run ?? ''),
            $is_active
        );
        $actions_html .= sprintf(
            '<button class="aips-btn aips-btn-sm aips-btn-ghost aips-unified-run-now"
                data-id="%d" data-type="%s"
                aria-label="%s" title="%s">
                <span class="dashicons dashicons-controls-play"></span>
            </button>',
            absint($schedule->id),
            esc_attr($type),
            esc_attr__('Run now', 'ai-post-scheduler'),
            esc_attr__('Run Now', 'ai-post-scheduler')
        );
        $actions_html .= sprintf(
            '<button class="aips-btn aips-btn-sm aips-btn-danger aips-delete-schedule"
                data-id="%d"
                aria-label="%s" title="%s">
                <span class="dashicons dashicons-trash"></span>
            </button>',
            absint($schedule->id),
            esc_attr__('Delete schedule', 'ai-post-scheduler'),
            esc_attr__('Delete', 'ai-post-scheduler')
        );

        return array(
            'id'               => absint($schedule->id),
            'type'             => esc_attr($type),
            'rowKey'           => $row_key,
            'isActive'         => $is_active,
            'title'            => esc_attr($title),
            'titleDisplay'     => esc_html($title),
            'ariaSelectLabel'  => esc_attr(sprintf(__('Select: %s', 'ai-post-scheduler'), $title)),
            'subtitleHtml'     => $subtitle_html,
            'typeBadgeHtml'    => $type_badge_html,
            'cronHook'         => esc_html('aips_generate_scheduled_posts'),
            'frequencyLabel'   => esc_html($frequency_label),
            'lastRunHtml'      => $last_run_html,
            'nextRunHtml'      => $next_run_html,
            'statsCount'       => 0,
            'statsLabel'       => esc_html__('posts generated', 'ai-post-scheduler'),
            'statusBadgeHtml'  => $status_badge_html,
            'toggleChecked'    => $is_active ? 'checked' : '',
            'actionsHtml'      => $actions_html,
        );
    }

    /**
     * Retrieve the display name for a template by its ID.
     *
     * @param int $template_id
     * @return string Template name, or empty string if not found.
     */
    private function get_template_name($template_id) {
        if (!$template_id) {
            return '';
        }
        $repository = new AIPS_Template_Repository();
        $template   = $repository->get_by_id($template_id);
        return ($template && !empty($template->name)) ? (string) $template->name : '';
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

        // If schedule_id is provided, use the scheduler to run the schedule logic
        $schedule_id = isset($_POST['schedule_id']) ? absint($_POST['schedule_id']) : 0;
        if ($schedule_id) {
            $result = $this->scheduler->run_schedule_now($schedule_id);
            if (is_wp_error($result)) {
                wp_send_json_error(array('message' => $result->get_error_message()));
            } else {
                $post_ids = is_array($result) ? $result : array($result);
                $first_post_id = !empty($post_ids) ? $post_ids[0] : 0;
                $edit_url = $first_post_id ? esc_url_raw(get_edit_post_link($first_post_id, 'raw')) : '';

                $msg = sprintf(
                    _n('%d post generated successfully!', '%d posts generated successfully!', count($post_ids), 'ai-post-scheduler'),
                    count($post_ids)
                );

                 wp_send_json_success(array(
                    'message' => $msg,
                    'post_ids' => $post_ids,
                    'edit_url' => $edit_url
                ));
            }
            return;
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
            'edit_url' => !empty($post_ids) ? esc_url_raw(get_edit_post_link($post_ids[0], 'raw')) : ''
        ));
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

    /**
     * AJAX handler to fetch the history log for a specific schedule.
     *
     * Returns all activity-type history entries associated with the schedule's
     * persistent lifecycle history container.
     */
    public function ajax_get_schedule_history() {
        check_ajax_referer('aips_ajax_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied.', 'ai-post-scheduler')));
        }

        $schedule_id = isset($_POST['schedule_id']) ? absint($_POST['schedule_id']) : 0;
        if (!$schedule_id) {
            wp_send_json_error(array('message' => __('Invalid schedule ID.', 'ai-post-scheduler')));
        }

        $schedule_repository = new AIPS_Schedule_Repository();
        $schedule = $schedule_repository->get_by_id($schedule_id);

        if (!$schedule) {
            wp_send_json_error(array('message' => __('Schedule not found.', 'ai-post-scheduler')));
        }

        if (empty($schedule->schedule_history_id)) {
            wp_send_json_success(array('entries' => array()));
        }

        $history_repository = new AIPS_History_Repository();
        $logs = $history_repository->get_logs_by_history_id(
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

        wp_send_json_success(array('entries' => $entries));
    }

    /**
     * AJAX: Run any schedule type immediately.
     *
     * Expects POST: id (int), type (string).
     */
    public function ajax_unified_run_now() {
        check_ajax_referer('aips_ajax_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied.', 'ai-post-scheduler')));
        }

        $id   = isset($_POST['id']) ? absint($_POST['id']) : 0;
        $type = isset($_POST['type']) ? sanitize_key(wp_unslash($_POST['type'])) : '';

        if (!$id || empty($type)) {
            wp_send_json_error(array('message' => __('Invalid parameters.', 'ai-post-scheduler')));
        }

        $service = new AIPS_Unified_Schedule_Service();
        $result  = $service->run_now($id, $type);

        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
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

            wp_send_json_success(array(
                'message'  => $msg,
                'post_ids' => $post_ids,
                'post_id'  => $first_post_id, // keep post_id for backward compatibility
                'edit_url' => $edit_url,
            ));
        } elseif ($type === AIPS_Unified_Schedule_Service::TYPE_AUTHOR_TOPIC) {
            $count = is_array($result) ? count($result) : 0;
            wp_send_json_success(array(
                'message' => sprintf(
                    _n('%d topic generated successfully!', '%d topics generated successfully!', $count, 'ai-post-scheduler'),
                    $count
                ),
            ));
        } elseif ($type === AIPS_Unified_Schedule_Service::TYPE_AUTHOR_POST) {
            $post_id  = is_int($result) ? $result : 0;
            $edit_url = $post_id ? esc_url_raw(get_edit_post_link($post_id, 'raw')) : '';
            wp_send_json_success(array(
                'message'  => __('Post generated successfully from author topic!', 'ai-post-scheduler'),
                'post_id'  => $post_id,
                'edit_url' => $edit_url,
            ));
        } else {
            wp_send_json_success(array('message' => __('Schedule executed successfully.', 'ai-post-scheduler')));
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
            wp_send_json_error(array('message' => __('Permission denied.', 'ai-post-scheduler')));
        }

        $id        = isset($_POST['id']) ? absint($_POST['id']) : 0;
        $type      = isset($_POST['type']) ? sanitize_key(wp_unslash($_POST['type'])) : '';
        $is_active = isset($_POST['is_active']) ? absint($_POST['is_active']) : 0;

        if (!$id || empty($type)) {
            wp_send_json_error(array('message' => __('Invalid parameters.', 'ai-post-scheduler')));
        }

        $service = new AIPS_Unified_Schedule_Service();
        $result  = $service->toggle($id, $type, $is_active);

        if ($result !== false) {
            $label = $is_active
                ? __('Schedule activated.', 'ai-post-scheduler')
                : __('Schedule paused.', 'ai-post-scheduler');
            wp_send_json_success(array('message' => $label, 'is_active' => $is_active));
        } else {
            wp_send_json_error(array('message' => __('Failed to update schedule.', 'ai-post-scheduler')));
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
            wp_send_json_error(array('message' => __('Permission denied.', 'ai-post-scheduler')));
        }

        $items     = isset($_POST['items']) && is_array($_POST['items']) ? $_POST['items'] : array();
        $is_active = isset($_POST['is_active']) ? absint($_POST['is_active']) : 0;

        if (empty($items)) {
            wp_send_json_error(array('message' => __('No items provided.', 'ai-post-scheduler')));
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

        wp_send_json_success(array(
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
            wp_send_json_error(array('message' => __('Permission denied.', 'ai-post-scheduler')));
        }

        $items = isset($_POST['items']) && is_array($_POST['items']) ? $_POST['items'] : array();

        if (empty($items)) {
            wp_send_json_error(array('message' => __('No items provided.', 'ai-post-scheduler')));
        }

        $max_bulk = apply_filters('aips_unified_bulk_run_now_limit', 5);
        if (count($items) > $max_bulk) {
            wp_send_json_error(array(
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
            wp_send_json_error(array(
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

        wp_send_json_success(array(
            'message' => $message,
            'success' => $success,
            'errors'  => $errors,
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
            wp_send_json_error(array('message' => __('Permission denied.', 'ai-post-scheduler')));
        }

        $id   = isset($_POST['id']) ? absint($_POST['id']) : 0;
        $type = isset($_POST['type']) ? sanitize_key(wp_unslash($_POST['type'])) : '';

        if (!$id || empty($type)) {
            wp_send_json_error(array('message' => __('Invalid parameters.', 'ai-post-scheduler')));
        }

        $service = new AIPS_Unified_Schedule_Service();
        $entries = $service->get_history($id, $type);

        wp_send_json_success(array('entries' => $entries));
    }
}
