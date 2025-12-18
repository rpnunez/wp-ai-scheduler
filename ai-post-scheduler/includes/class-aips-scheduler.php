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
    
    public function add_cron_intervals($schedules) {
        $schedules['every_6_hours'] = array(
            'interval' => 21600,
            'display' => __('Every 6 Hours', 'ai-post-scheduler')
        );
        
        $schedules['every_12_hours'] = array(
            'interval' => 43200,
            'display' => __('Every 12 Hours', 'ai-post-scheduler')
        );
        
        $schedules['weekly'] = array(
            'interval' => 604800,
            'display' => __('Once Weekly', 'ai-post-scheduler')
        );
        
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
            default:
                $next = strtotime('+1 day', $base_time);
        }
        
        return date('Y-m-d H:i:s', $next);
    }
    
    public function process_scheduled_posts() {
        global $wpdb;
        
        $logger = new AIPS_Logger();
        $logger->log('Starting scheduled post generation', 'info');
        
        $due_schedules = $wpdb->get_results($wpdb->prepare("
            SELECT s.*, t.* 
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
        
        foreach ($due_schedules as $schedule) {
            $logger->log('Processing schedule: ' . $schedule->id, 'info', array(
                'template_id' => $schedule->template_id,
                'template_name' => $schedule->name
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
            );
            
            $result = $generator->generate_post($template);
            
            $next_run = $this->calculate_next_run($schedule->frequency);
            
            $wpdb->update(
                $this->schedule_table,
                array(
                    'last_run' => current_time('mysql'),
                    'next_run' => $next_run,
                ),
                array('id' => $schedule->id),
                array('%s', '%s'),
                array('%d')
            );
            
            if (is_wp_error($result)) {
                $logger->log('Schedule failed: ' . $result->get_error_message(), 'error', array(
                    'schedule_id' => $schedule->id
                ));
            } else {
                $logger->log('Schedule completed successfully', 'info', array(
                    'schedule_id' => $schedule->id,
                    'post_id' => $result
                ));
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
