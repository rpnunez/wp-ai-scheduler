<?php
if (!defined('ABSPATH')) {
    exit;
}

class AIPS_Scheduler {
    
    private $schedule_table;
    private $templates_table;
    private $interval_calculator;
    
    /**
     * @var AIPS_Schedule_Repository Repository for database operations
     */
    private $repository;
    
    public function __construct() {
        global $wpdb;
        $this->schedule_table = $wpdb->prefix . 'aips_schedule';
        $this->templates_table = $wpdb->prefix . 'aips_templates';
        $this->interval_calculator = new AIPS_Interval_Calculator();
        $this->repository = new AIPS_Schedule_Repository();
        
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
        return $this->repository->get_all();
    }
    
    public function get_schedule($id) {
        return $this->repository->get_by_id($id);
    }
    
    public function save_schedule($data) {
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

        if (!empty($data['id'])) {
            $this->repository->update(absint($data['id']), $schedule_data);
            return absint($data['id']);
        } else {
            return $this->repository->create($schedule_data);
        }
    }
    
    public function delete_schedule($id) {
        return $this->repository->delete($id);
    }

    public function toggle_active($id, $is_active) {
        return $this->repository->set_active($id, $is_active);
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
                $this->repository->delete($schedule->schedule_id);
                $logger->log('One-time schedule completed and deleted', 'info', array('schedule_id' => $schedule->schedule_id));
            } else {
                // Otherwise calculate next run
                $next_run = $this->calculate_next_run($schedule->frequency);

                $this->repository->update($schedule->schedule_id, array(
                    'last_run' => current_time('mysql'),
                    'next_run' => $next_run,
                ));
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
