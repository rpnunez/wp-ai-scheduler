<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class AIPS_Scheduler
 *
 * Handles schedule management and execution.
 * Uses AIPS_Schedule_Repository for database operations.
 *
 * @package AI_Post_Scheduler
 * @since 1.0.0
 */
class AIPS_Scheduler {
    
    /**
     * @var AIPS_Schedule_Repository Schedule repository instance
     */
    private $repository;
    
    /**
     * @var AIPS_Interval_Calculator Interval calculator instance
     */
    private $interval_calculator;
    
    /**
     * Initialize scheduler.
     */
    public function __construct() {
        $this->repository = new AIPS_Schedule_Repository();
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
    
    /**
     * Get all schedules with template information.
     *
     * @return array Array of schedule objects.
     */
    public function get_all_schedules() {
        return $this->repository->get_all_schedules();
    }
    
    /**
     * Get a specific schedule by ID.
     *
     * @param int $id Schedule ID.
     * @return object|null Schedule object or null if not found.
     */
    public function get_schedule($id) {
        return $this->repository->find($id);
    }
    
    /**
     * Save a schedule (create or update).
     *
     * @param array $data Schedule data.
     * @return int|false Schedule ID or false on failure.
     */
    public function save_schedule($data) {
        $frequency = sanitize_text_field($data['frequency']);

        if (isset($data['next_run'])) {
            $next_run = sanitize_text_field($data['next_run']);
        } else {
            $next_run = $this->calculate_next_run($frequency, isset($data['start_time']) ? $data['start_time'] : null);
        }
        
        $data['next_run'] = $next_run;
        
        return $this->repository->save($data);
    }
    
    /**
     * Delete a schedule by ID.
     *
     * @param int $id Schedule ID.
     * @return int|false Number of rows deleted or false on error.
     */
    public function delete_schedule($id) {
        return $this->repository->delete($id);
    }

    /**
     * Toggle schedule active status.
     *
     * @param int $id        Schedule ID.
     * @param int $is_active Active status (0 or 1).
     * @return int|false Number of rows affected or false on error.
     */
    public function toggle_active($id, $is_active) {
        return $this->repository->toggle_active($id, $is_active);
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
    
    /**
     * Process scheduled posts that are due.
     *
     * Executes generation for all active schedules that are past their next_run time.
     */
    public function process_scheduled_posts() {
        $logger = new AIPS_Logger();
        $logger->log('Starting scheduled post generation', 'info');
        
        $due_schedules = $this->repository->get_due_schedules();
        
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
                
                $this->repository->update_run_times(
                    $schedule->schedule_id,
                    current_time('mysql'),
                    $next_run
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
