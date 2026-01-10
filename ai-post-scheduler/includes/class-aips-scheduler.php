<?php
if (!defined('ABSPATH')) {
    exit;
}

class AIPS_Scheduler {
    
    private $schedule_table;
    private $templates_table;
    private $single_schedule_hook = 'aips_run_single_schedule';
    private $interval_calculator;
    private $template_type_selector;
    
    /**
     * @var AIPS_Schedule_Repository Repository for database operations
     */
    private $repository;
    
    /**
     * @var AIPS_Activity_Repository Repository for activity logging
     */
    private $activity_repository;
    
    /**
     * Initialize the scheduler.
     *
     * @param AIPS_Interval_Calculator|null $interval_calculator  Optional interval calculator.
     * @param AIPS_Schedule_Repository|null $repository           Optional schedule repository.
     * @param AIPS_Activity_Repository|null $activity_repository  Optional activity repository.
     * @param AIPS_Template_Type_Selector|null $template_type_selector Optional template selector.
     */
    public function __construct($interval_calculator = null, $repository = null, $activity_repository = null, $template_type_selector = null) {
        global $wpdb;
        $this->schedule_table = $wpdb->prefix . 'aips_schedule';
        $this->templates_table = $wpdb->prefix . 'aips_templates';
        $this->interval_calculator = $interval_calculator ? $interval_calculator : new AIPS_Interval_Calculator();
        $this->repository = $repository ? $repository : new AIPS_Schedule_Repository();
        $this->activity_repository = $activity_repository ? $activity_repository : new AIPS_Activity_Repository();
        $this->template_type_selector = $template_type_selector ? $template_type_selector : new AIPS_Template_Type_Selector();
        
        add_action('aips_generate_scheduled_posts', array($this, 'process_scheduled_posts'));
        add_action($this->single_schedule_hook, array($this, 'run_single_schedule'), 10, 1);
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
        $next_run = '';

        if (isset($data['next_run'])) {
            $next_run = sanitize_text_field($data['next_run']);
        } else {
            // Use start_time as the initial run time if provided, otherwise start now.
            // Using calculate_next_run here would skip the first interval (e.g., scheduling for "Tomorrow" if "Start Time" is "Now").
            $start_time = isset($data['start_time']) && !empty($data['start_time'])
                ? $data['start_time']
                : current_time('mysql');

            // Ensure proper MySQL format (handling 'T' from datetime-local inputs)
            $next_run = date('Y-m-d H:i:s', strtotime($start_time));
        }
        
        $schedule_data = array(
            'template_id' => absint($data['template_id']),
            'frequency' => $frequency,
            'next_run' => $next_run,
            'is_active' => isset($data['is_active']) ? 1 : 0,
            'topic' => isset($data['topic']) ? sanitize_text_field($data['topic']) : '',
            'article_structure_id' => isset($data['article_structure_id']) ? absint($data['article_structure_id']) : null,
            'rotation_pattern' => isset($data['rotation_pattern']) ? sanitize_text_field($data['rotation_pattern']) : null,
        );

        $schedule_id = 0;
        if (!empty($data['id'])) {
            $this->repository->update(absint($data['id']), $schedule_data);
            $schedule_id = absint($data['id']);
        } else {
            $schedule_id = $this->repository->create($schedule_data);
        }

        // Queue a single WP-Cron event for this schedule to avoid manual catch-up logic.
        if ($schedule_id) {
            if (!empty($schedule_data['is_active'])) {
                $this->schedule_single_event($schedule_id, $next_run);
            } else {
                $this->clear_scheduled_event($schedule_id);
            }
        }

        return $schedule_id;
    }

    public function save_schedule_bulk($schedules) {
        return $this->repository->create_bulk($schedules);
    }
    
    public function delete_schedule($id) {
        $this->clear_scheduled_event($id);
        return $this->repository->delete($id);
    }

    public function toggle_active($id, $is_active) {
        $result = $this->repository->set_active($id, $is_active);

        if ($result !== false) {
            if ($is_active) {
                $schedule = $this->repository->get_by_id($id);
                if ($schedule && !empty($schedule->next_run)) {
                    $this->schedule_single_event($id, $schedule->next_run);
                }
            } else {
                $this->clear_scheduled_event($id);
            }
        }

        return $result;
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
     * Process a single schedule run when triggered by WP-Cron.
     *
     * @param int $schedule_id Schedule identifier.
     * @return void
     */
    public function run_single_schedule($schedule_id) {
        $logger = new AIPS_Logger();
        $generator = new AIPS_Generator();

        $schedule = $this->load_schedule_with_template($schedule_id);

        if (!$schedule || (isset($schedule->is_active) && !$schedule->is_active)) {
            $this->clear_scheduled_event($schedule_id);
            return;
        }

        $this->handle_schedule($schedule, $generator, $logger);
    }

    public function process_scheduled_posts() {
        global $wpdb;
        
        $logger = new AIPS_Logger();
        $logger->log('Starting scheduled post generation', 'info');
        $generator = new AIPS_Generator();
        
        $due_schedules = $wpdb->get_results($wpdb->prepare("
            SELECT t.*, s.*, s.id AS schedule_id
            FROM {$this->schedule_table} s 
            INNER JOIN {$this->templates_table} t ON s.template_id = t.id 
            WHERE s.is_active = 1 
            AND s.next_run <= %s 
            AND t.is_active = 1
            ORDER BY s.next_run ASC
            LIMIT 5
        ", current_time('mysql')));
        
        if (empty($due_schedules)) {
            $logger->log('No scheduled posts due', 'info');
            return;
        }
        
        foreach ($due_schedules as $schedule) {
            $this->handle_schedule($schedule, $generator, $logger);
        }
    }

    /**
     * Schedule a single WP-Cron event for a schedule.
     *
     * @param int    $schedule_id Schedule identifier.
     * @param string $next_run    Next run datetime in MySQL format.
     * @return void
     */
    public function schedule_single_event($schedule_id, $next_run) {
        if (!function_exists('wp_schedule_single_event') || !function_exists('wp_next_scheduled') || !function_exists('wp_unschedule_event') || empty($next_run)) {
            return;
        }

        $timestamp = strtotime($next_run);

        // Guard against invalid or past timestamps; use a short delay to avoid immediate re-entry.
        if (!$timestamp || $timestamp <= current_time('timestamp')) {
            $timestamp = current_time('timestamp') + 60;
        }

        $existing = wp_next_scheduled($this->single_schedule_hook, array($schedule_id));

        if ($existing && $existing !== $timestamp) {
            wp_unschedule_event($existing, $this->single_schedule_hook, array($schedule_id));
        }

        if (!$existing || $existing !== $timestamp) {
            wp_schedule_single_event($timestamp, $this->single_schedule_hook, array($schedule_id));
        }
    }

    /**
     * Clear any queued WP-Cron events for a schedule.
     *
     * @param int $schedule_id Schedule identifier.
     * @return void
     */
    public function clear_scheduled_event($schedule_id) {
        if (!function_exists('wp_next_scheduled') || !function_exists('wp_unschedule_event')) {
            return;
        }

        $existing = wp_next_scheduled($this->single_schedule_hook, array($schedule_id));

        if ($existing) {
            wp_unschedule_event($existing, $this->single_schedule_hook, array($schedule_id));
        }
    }

    /**
     * Execute a schedule run and reschedule the next occurrence.
     *
     * @param object         $schedule  Schedule row with template data.
     * @param AIPS_Generator $generator Generator instance.
     * @param AIPS_Logger    $logger    Logger instance.
     * @return void
     */
    private function handle_schedule($schedule, $generator, $logger) {
        if (!$schedule || (isset($schedule->is_active) && !$schedule->is_active)) {
            return;
        }

        $template_name = isset($schedule->template_name) ? $schedule->template_name : (isset($schedule->name) ? $schedule->name : '');

        // Dispatch schedule execution started event
        do_action('aips_schedule_execution_started', $schedule->schedule_id);
        
        $logger->log('Processing schedule: ' . $schedule->schedule_id, 'info', array(
            'template_id' => $schedule->template_id,
            'template_name' => $template_name,
            'topic' => isset($schedule->topic) ? $schedule->topic : ''
        ));
        
        // Select article structure for this execution
        $article_structure_id = $this->template_type_selector->select_structure($schedule);
        
        $template = (object) array(
            'id' => $schedule->template_id,
            'name' => $template_name,
            'prompt_template' => $schedule->prompt_template,
            'title_prompt' => $schedule->title_prompt,
            'post_status' => $schedule->post_status,
            'post_category' => $schedule->post_category,
            'post_tags' => $schedule->post_tags,
            'post_author' => $schedule->post_author,
            'post_quantity' => 1, // Schedules always run one at a time per interval
            'generate_featured_image' => isset($schedule->generate_featured_image) ? $schedule->generate_featured_image : 0,
            'image_prompt' => isset($schedule->image_prompt) ? $schedule->image_prompt : '',
            'article_structure_id' => $article_structure_id,
        );
        
        $topic = isset($schedule->topic) ? $schedule->topic : null;
        $result = $generator->generate_post($template, null, $topic);
        
        if ($schedule->frequency === 'once') {
            if (!is_wp_error($result)) {
                // If it's a one-time schedule and successful, delete it
                $this->repository->delete($schedule->schedule_id);
                $this->clear_scheduled_event($schedule->schedule_id);
                $logger->log('One-time schedule completed and deleted', 'info', array('schedule_id' => $schedule->schedule_id));
            } else {
                // If failed, deactivate it and set status to 'failed' to prevent infinite daily retries
                $this->repository->update($schedule->schedule_id, array(
                    'is_active' => 0,
                    'status' => 'failed',
                    'last_run' => current_time('mysql')
                ));
                $this->clear_scheduled_event($schedule->schedule_id);
                $logger->log('One-time schedule failed and deactivated', 'info', array('schedule_id' => $schedule->schedule_id));
                
                // Log to activity feed
                $this->activity_repository->create(array(
                    'event_type' => 'schedule_failed',
                    'event_status' => 'failed',
                    'schedule_id' => $schedule->schedule_id,
                    'template_id' => $schedule->template_id,
                    'message' => sprintf(
                        __('One-time schedule "%s" failed and was deactivated', 'ai-post-scheduler'),
                        $template_name
                    ),
                    'metadata' => array(
                        'error' => $result->get_error_message(),
                        'frequency' => $schedule->frequency,
                    ),
                ));
            }
        } else {
            // Otherwise calculate next run, passing existing next_run as start_time to preserve phase
            $next_run = $this->calculate_next_run($schedule->frequency, $schedule->next_run);

            $this->repository->update($schedule->schedule_id, array(
                'last_run' => current_time('mysql'),
                'next_run' => $next_run,
            ));

            $this->schedule_single_event($schedule->schedule_id, $next_run);
        }
        
        if (is_wp_error($result)) {
            $logger->log('Schedule failed: ' . $result->get_error_message(), 'error', array(
                'schedule_id' => $schedule->schedule_id
            ));
            
            // Log recurring schedule failures to activity feed
            if ($schedule->frequency !== 'once') {
                $this->activity_repository->create(array(
                    'event_type' => 'schedule_failed',
                    'event_status' => 'failed',
                    'schedule_id' => $schedule->schedule_id,
                    'template_id' => $schedule->template_id,
                    'message' => sprintf(
                        __('Schedule "%s" failed to generate post', 'ai-post-scheduler'),
                        $template_name
                    ),
                    'metadata' => array(
                        'error' => $result->get_error_message(),
                        'frequency' => $schedule->frequency,
                    ),
                ));
            }
            
            // Dispatch schedule execution failed event
            do_action('aips_schedule_execution_failed', $schedule->schedule_id, $result->get_error_message());
        } else {
            $logger->log('Schedule completed successfully', 'info', array(
                'schedule_id' => $schedule->schedule_id,
                'post_id' => $result
            ));
            
            // Get the post to check its status
            $post = get_post($result);
            if ($post) {
                $event_status = ($post->post_status === 'draft') ? 'draft' : 'success';
                $event_type = ($post->post_status === 'draft') ? 'post_draft' : 'post_published';
                
                // Log to activity feed
                $this->activity_repository->create(array(
                    'event_type' => $event_type,
                    'event_status' => $event_status,
                    'schedule_id' => $schedule->schedule_id,
                    'post_id' => $result,
                    'template_id' => $schedule->template_id,
                    'message' => sprintf(
                        __('%s created by schedule "%s": %s', 'ai-post-scheduler'),
                        ($post->post_status === 'draft') ? __('Draft', 'ai-post-scheduler') : __('Post', 'ai-post-scheduler'),
                        $template_name,
                        $post->post_title
                    ),
                    'metadata' => array(
                        'post_status' => $post->post_status,
                        'frequency' => $schedule->frequency,
                    ),
                ));
            }
            
            // Dispatch schedule execution completed event
            do_action('aips_schedule_execution_completed', $schedule->schedule_id, $result);
        }
    }

    /**
     * Load a schedule with its template fields for execution.
     *
     * @param int $schedule_id Schedule identifier.
     * @return object|null Schedule row or null when missing.
     */
    private function load_schedule_with_template($schedule_id) {
        global $wpdb;

        return $wpdb->get_row($wpdb->prepare("
            SELECT t.*, s.*, s.id AS schedule_id
            FROM {$this->schedule_table} s 
            INNER JOIN {$this->templates_table} t ON s.template_id = t.id 
            WHERE s.id = %d
            AND s.is_active = 1
            AND t.is_active = 1
            LIMIT 1
        ", absint($schedule_id)));
    }
}
