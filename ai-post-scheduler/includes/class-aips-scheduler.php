<?php
if (!defined('ABSPATH')) {
    exit;
}

class AIPS_Scheduler {
    
    private $schedule_table;
    private $templates_table;
    
    public function __construct() {
        global $wpdb;
        $this->schedule_table = $wpdb->prefix . 'aips_schedule';
        $this->templates_table = $wpdb->prefix . 'aips_templates';
        
        add_action('aips_generate_scheduled_posts', array($this, 'process_scheduled_posts'));
        add_filter('cron_schedules', array($this, 'add_cron_intervals'));
        
        add_action('wp_ajax_aips_save_schedule', array($this, 'ajax_save_schedule'));
        add_action('wp_ajax_aips_delete_schedule', array($this, 'ajax_delete_schedule'));
        add_action('wp_ajax_aips_toggle_schedule', array($this, 'ajax_toggle_schedule'));
        add_action('wp_ajax_aips_run_now', array($this, 'ajax_run_now'));
    }
    
    public function get_intervals() {
        $intervals = array();

        $intervals['hourly'] = array(
            'interval' => 3600,
            'display' => __('Hourly', 'ai-post-scheduler')
        );

        $intervals['every_4_hours'] = array(
            'interval' => 14400,
            'display' => __('Every 4 Hours', 'ai-post-scheduler')
        );

        $intervals['every_6_hours'] = array(
            'interval' => 21600,
            'display' => __('Every 6 Hours', 'ai-post-scheduler')
        );
        
        $intervals['every_12_hours'] = array(
            'interval' => 43200,
            'display' => __('Every 12 Hours', 'ai-post-scheduler')
        );
        
        $intervals['daily'] = array(
            'interval' => 86400,
            'display' => __('Daily', 'ai-post-scheduler')
        );

        $intervals['weekly'] = array(
            'interval' => 604800,
            'display' => __('Once Weekly', 'ai-post-scheduler')
        );

        $intervals['bi_weekly'] = array(
            'interval' => 1209600,
            'display' => __('Every 2 Weeks', 'ai-post-scheduler')
        );

        $intervals['monthly'] = array(
            'interval' => 2592000,
            'display' => __('Monthly', 'ai-post-scheduler')
        );

        $intervals['once'] = array(
            'interval' => 86400, // Default to daily interval, but handled specially
            'display' => __('Once', 'ai-post-scheduler')
        );

        $days = array('Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday');
        foreach ($days as $day) {
            $intervals['every_' . strtolower($day)] = array(
                'interval' => 604800,
                'display' => sprintf(__('Every %s', 'ai-post-scheduler'), $day)
            );
        }
        
        return $intervals;
    }

    public function add_cron_intervals($schedules) {
        $intervals = $this->get_intervals();

        // Merge our intervals into WP schedules
        // Note: 'hourly' is a default WP interval, so we might overlap, but that's fine.
        foreach ($intervals as $key => $data) {
            if (!isset($schedules[$key])) {
                $schedules[$key] = $data;
            }
        }

        return $schedules;
    }
    
    public function get_all_schedules() {
        global $wpdb;
        
        return $wpdb->get_results("
            SELECT s.*, t.name as template_name 
            FROM {$this->schedule_table} s 
            LEFT JOIN {$this->templates_table} t ON s.template_id = t.id 
            ORDER BY s.next_run ASC
        ");
    }
    
    public function get_schedule($id) {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->schedule_table} WHERE id = %d", $id));
    }
    
    public function save_schedule($data) {
        global $wpdb;
        
        $frequency = sanitize_text_field($data['frequency']);
        $next_run = $this->calculate_next_run($frequency, isset($data['start_time']) ? $data['start_time'] : null);
        
        $schedule_data = array(
            'template_id' => absint($data['template_id']),
            'frequency' => $frequency,
            'next_run' => $next_run,
            'is_active' => isset($data['is_active']) ? 1 : 0,
        );
        
        if (!empty($data['id'])) {
            $wpdb->update(
                $this->schedule_table,
                $schedule_data,
                array('id' => absint($data['id'])),
                array('%d', '%s', '%s', '%d'),
                array('%d')
            );
            return absint($data['id']);
        } else {
            $wpdb->insert(
                $this->schedule_table,
                $schedule_data,
                array('%d', '%s', '%s', '%d')
            );
            return $wpdb->insert_id;
        }
    }
    
    public function delete_schedule($id) {
        global $wpdb;
        return $wpdb->delete($this->schedule_table, array('id' => $id), array('%d'));
    }
    
    private function calculate_next_run($frequency, $start_time = null) {
        $base_time = $start_time ? strtotime($start_time) : current_time('timestamp');
        
        if ($base_time < current_time('timestamp')) {
            $base_time = current_time('timestamp');
        }
        
        switch ($frequency) {
            case 'hourly':
                $next = strtotime('+1 hour', $base_time);
                break;
            case 'every_4_hours':
                $next = strtotime('+4 hours', $base_time);
                break;
            case 'every_6_hours':
                $next = strtotime('+6 hours', $base_time);
                break;
            case 'every_12_hours':
                $next = strtotime('+12 hours', $base_time);
                break;
            case 'daily':
                $next = strtotime('+1 day', $base_time);
                break;
            case 'weekly':
                $next = strtotime('+1 week', $base_time);
                break;
            case 'bi_weekly':
                $next = strtotime('+2 weeks', $base_time);
                break;
            case 'monthly':
                $next = strtotime('+1 month', $base_time);
                break;
            default:
                if (strpos($frequency, 'every_') === 0) {
                    $day = ucfirst(str_replace('every_', '', $frequency));
                    $valid_days = array('Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday');

                    if (in_array($day, $valid_days)) {
                        // Calculate next occurrence of the day while preserving time
                        $next = strtotime("next $day", $base_time);
                        // Reset the time to the base_time's time
                        $next = strtotime(date('H:i:s', $base_time), $next);
                    } else {
                        $next = strtotime('+1 day', $base_time);
                    }
                } else {
                    $next = strtotime('+1 day', $base_time);
                }
        }
        
        return date('Y-m-d H:i:s', $next);
    }
    
    public function process_scheduled_posts() {
        global $wpdb;
        
        $logger = new AIPS_Logger();
        $logger->log('Starting scheduled post generation', 'info');
        
        $due_schedules = $wpdb->get_results($wpdb->prepare("
            SELECT s.id AS schedule_id, s.*, t.*
            FROM {$this->schedule_table} s 
            INNER JOIN {$this->templates_table} t ON s.template_id = t.id 
            WHERE s.is_active = 1 
            AND s.next_run <= %s 
            AND t.is_active = 1
            ORDER BY s.next_run ASC
        ", current_time('mysql')));
        
        if (empty($due_schedules)) {
            $logger->log('No scheduled posts due', 'info');
            return;
        }
        
        $generator = new AIPS_Generator();
        $current_time = current_time('mysql');
        
        // Batch arrays for bulk operations
        $schedules_to_delete = array();
        $schedules_to_update = array();
        
        foreach ($due_schedules as $schedule) {
            $logger->log('Processing schedule: ' . $schedule->schedule_id, 'info', array(
                'template_id' => $schedule->template_id,
                'template_name' => $schedule->name,
                'topic' => isset($schedule->topic) ? $schedule->topic : ''
            ));
            
            $template = (object) array(
                'id' => $schedule->template_id,
                'name' => $schedule->name,
                'prompt_template' => $schedule->prompt_template,
                'title_prompt' => $schedule->title_prompt,
                'post_status' => $schedule->post_status,
                'post_category' => $schedule->post_category,
                'post_tags' => $schedule->post_tags,
                'post_author' => $schedule->post_author,
                'post_quantity' => 1, // Schedules always run one at a time per interval
                'generate_featured_image' => isset($schedule->generate_featured_image) ? $schedule->generate_featured_image : 0,
                'image_prompt' => isset($schedule->image_prompt) ? $schedule->image_prompt : '',
            );
            
            $topic = isset($schedule->topic) ? $schedule->topic : null;
            $result = $generator->generate_post($template, null, $topic);
            
            if ($schedule->frequency === 'once' && !is_wp_error($result)) {
                // Mark for deletion
                $schedules_to_delete[] = $schedule->schedule_id;
                $logger->log('One-time schedule completed and marked for deletion', 'info', array('schedule_id' => $schedule->schedule_id));
            } else {
                // Calculate next run and mark for update
                $next_run = $this->calculate_next_run($schedule->frequency);
                $schedules_to_update[] = array(
                    'id' => $schedule->schedule_id,
                    'last_run' => $current_time,
                    'next_run' => $next_run
                );
            }
            
            if (is_wp_error($result)) {
                $logger->log('Schedule failed: ' . $result->get_error_message(), 'error', array(
                    'schedule_id' => $schedule->schedule_id
                ));
            } else {
                $logger->log('Schedule completed successfully', 'info', array(
                    'schedule_id' => $schedule->schedule_id,
                    'post_id' => $result
                ));
            }
        }
        
        // Bulk delete completed one-time schedules
        if (!empty($schedules_to_delete)) {
            $ids_placeholder = implode(',', array_fill(0, count($schedules_to_delete), '%d'));
            $wpdb->query($wpdb->prepare(
                "DELETE FROM {$this->schedule_table} WHERE id IN ($ids_placeholder)",
                $schedules_to_delete
            ));
        }
        
        // Bulk update recurring schedules using CASE statement for true bulk update
        if (!empty($schedules_to_update)) {
            $ids = array();
            $last_run_cases = array();
            $next_run_cases = array();
            
            foreach ($schedules_to_update as $update_data) {
                // Ensure ID is an integer for security
                $id = (int) $update_data['id'];
                $ids[] = $id;
                
                // Escape values using esc_sql for use in CASE statements
                $last_run_escaped = esc_sql($update_data['last_run']);
                $next_run_escaped = esc_sql($update_data['next_run']);
                
                // Build CASE clauses with properly escaped values
                $last_run_cases[] = sprintf("WHEN %d THEN '%s'", $id, $last_run_escaped);
                $next_run_cases[] = sprintf("WHEN %d THEN '%s'", $id, $next_run_escaped);
            }
            
            if (!empty($ids)) {
                // IDs are already cast to integers, safe to use directly
                $ids_list = implode(',', $ids);
                $last_run_case = implode(' ', $last_run_cases);
                $next_run_case = implode(' ', $next_run_cases);
                
                $wpdb->query("
                    UPDATE {$this->schedule_table}
                    SET 
                        last_run = CASE id {$last_run_case} END,
                        next_run = CASE id {$next_run_case} END
                    WHERE id IN ($ids_list)
                ");
            }
        }
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
        );
        
        if (empty($data['template_id'])) {
            wp_send_json_error(array('message' => __('Please select a template.', 'ai-post-scheduler')));
        }
        
        $id = $this->save_schedule($data);
        
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
        
        if ($this->delete_schedule($id)) {
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
        
        global $wpdb;
        
        $id = isset($_POST['schedule_id']) ? absint($_POST['schedule_id']) : 0;
        $is_active = isset($_POST['is_active']) ? absint($_POST['is_active']) : 0;
        
        if (!$id) {
            wp_send_json_error(array('message' => __('Invalid schedule ID.', 'ai-post-scheduler')));
        }
        
        $result = $wpdb->update(
            $this->schedule_table,
            array('is_active' => $is_active),
            array('id' => $id),
            array('%d'),
            array('%d')
        );
        
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
