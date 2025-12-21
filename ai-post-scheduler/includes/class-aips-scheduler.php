<?php
if (!defined('ABSPATH')) {
    exit;
}

class AIPS_Scheduler {
    
    private $schedule_table;
    private $templates_table;
    private $interval_calculator;
    
    public function __construct() {
        global $wpdb;
        $this->schedule_table = $wpdb->prefix . 'aips_schedule';
        $this->templates_table = $wpdb->prefix . 'aips_templates';
        $this->interval_calculator = new AIPS_Interval_Calculator();
        
        add_action('aips_generate_scheduled_posts', array($this, 'process_scheduled_posts'));
        add_filter('cron_schedules', array($this, 'add_cron_intervals'));
    }
    
    /**
     * Get all available scheduling intervals.
     *
     * @return array Associative array of intervals.
     */
    public function get_intervals() {
        return $this->interval_calculator->get_intervals();
    }

    /**
     * Add custom cron intervals to WordPress.
     *
     * @param array $schedules Existing WordPress cron schedules.
     * @return array Modified schedules array.
     */
    public function add_cron_intervals($schedules) {
        return $this->interval_calculator->merge_with_wp_schedules($schedules);
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

        if (isset($data['next_run'])) {
            $next_run = sanitize_text_field($data['next_run']);
        } else {
            $next_run = $this->calculate_next_run($frequency, isset($data['start_time']) ? $data['start_time'] : null);
        }
        
        $schedule_data = array(
            'template_id' => absint($data['template_id']),
            'frequency' => $frequency,
            'next_run' => $next_run,
            'is_active' => isset($data['is_active']) ? 1 : 0,
            'topic' => isset($data['topic']) ? sanitize_text_field($data['topic']) : '',
        );
        
        $format = array('%d', '%s', '%s', '%d', '%s');

        if (!empty($data['id'])) {
            $wpdb->update(
                $this->schedule_table,
                $schedule_data,
                array('id' => absint($data['id'])),
                $format,
                array('%d')
            );
            return absint($data['id']);
        } else {
            $wpdb->insert(
                $this->schedule_table,
                $schedule_data,
                $format
            );
            return $wpdb->insert_id;
        }
    }
    
    public function delete_schedule($id) {
        global $wpdb;
        return $wpdb->delete($this->schedule_table, array('id' => $id), array('%d'));
    }

    public function toggle_active($id, $is_active) {
        global $wpdb;
        return $wpdb->update(
            $this->schedule_table,
            array('is_active' => $is_active),
            array('id' => $id),
            array('%d'),
            array('%d')
        );
    }
    
    /**
     * Calculate the next run time for a schedule.
     *
     * @param string      $frequency  The frequency identifier.
     * @param string|null $start_time Optional start time.
     * @return string The next run time in MySQL datetime format.
     */
    public function calculate_next_run($frequency, $start_time = null) {
        return $this->interval_calculator->calculate_next_run($frequency, $start_time);
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
                // If it's a one-time schedule and successful, delete it
                $wpdb->delete($this->schedule_table, array('id' => $schedule->schedule_id), array('%d'));
                $logger->log('One-time schedule completed and deleted', 'info', array('schedule_id' => $schedule->schedule_id));
            } else {
                // Otherwise calculate next run
                $next_run = $this->calculate_next_run($schedule->frequency);

                $wpdb->update(
                    $this->schedule_table,
                    array(
                        'last_run' => current_time('mysql'),
                        'next_run' => $next_run,
                    ),
                    array('id' => $schedule->schedule_id),
                    array('%s', '%s'),
                    array('%d')
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
    }
}
