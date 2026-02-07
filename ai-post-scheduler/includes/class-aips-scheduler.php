<?php
if (!defined('ABSPATH')) {
    exit;
}

class AIPS_Scheduler {
    
    private $schedule_table;
    private $templates_table;
    private $interval_calculator;
    private $template_type_selector;
    
    /**
     * @var AIPS_Generator|null Generator instance (for dependency injection)
     */
    private $generator;

    /**
     * @var AIPS_Schedule_Repository Repository for database operations
     */
    private $repository;

    /**
     * @var AIPS_Template_Repository Repository for templates
     */
    private $template_repository;
    
    /**
     * @var AIPS_History_Service Service for history logging
     */
    private $history_service;

    /**
     * @var AIPS_Schedule_Processor Processor for executing schedules
     */
    private $processor;
    
    public function __construct() {
        global $wpdb;
        $this->schedule_table = $wpdb->prefix . 'aips_schedule';
        $this->templates_table = $wpdb->prefix . 'aips_templates';
        $this->interval_calculator = new AIPS_Interval_Calculator();
        $this->repository = new AIPS_Schedule_Repository();
        $this->template_repository = new AIPS_Template_Repository();
        $this->history_service = new AIPS_History_Service();
        $this->template_type_selector = new AIPS_Template_Type_Selector();
        
        // Instantiate the processor with dependencies
        // We pass the generator if it's already set (which it isn't in __construct usually)
        // or let the processor instantiate its own.
        // For consistency with current dependency injection pattern, we instantiate the processor
        // and rely on setters or internal defaults.
        $this->processor = new AIPS_Schedule_Processor(
            $this->repository,
            $this->template_repository,
            null, // Generator will be lazy loaded or set via set_generator
            $this->history_service,
            $this->template_type_selector
        );

        add_action('aips_generate_scheduled_posts', array($this, 'process_scheduled_posts'));
        add_filter('cron_schedules', array($this, 'add_cron_intervals'));
    }

    /**
     * Set a custom generator instance (dependency injection).
     *
     * @param AIPS_Generator $generator
     */
    public function set_generator($generator) {
        $this->generator = $generator;
        if ($this->processor) {
            $this->processor->set_generator($generator);
        }
    }

    /**
     * Set a custom repository instance (dependency injection).
     *
     * @param AIPS_Schedule_Repository $repository
     */
    public function set_repository($repository) {
        $this->repository = $repository;
        if ($this->processor) {
            $this->processor->set_repository($repository);
        }
    }

    /**
     * Set a custom template repository instance (dependency injection).
     *
     * @param AIPS_Template_Repository $repository
     */
    public function set_template_repository($repository) {
        $this->template_repository = $repository;
        if ($this->processor) {
            $this->processor->set_template_repository($repository);
        }
    }

    /**
     * Set a custom processor instance (dependency injection).
     *
     * @param AIPS_Schedule_Processor $processor
     */
    public function set_processor($processor) {
        $this->processor = $processor;
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
            // Use start_time as the initial run time if provided, otherwise start now.
            $start_time = isset($data['start_time']) && !empty($data['start_time'])
                ? $data['start_time']
                : current_time('mysql');

            // Default behavior: reset next_run to start_time
            $next_run = date('Y-m-d H:i:s', strtotime($start_time));

            // Hunter: Fix for schedule reset bug.
            // When updating a schedule, if the proposed start_time is in the past (likely the original start date populated in the form)
            // and the schedule is already running in the future, we should NOT reset the timeline to the past.
            if (!empty($data['id'])) {
                $existing_schedule = $this->repository->get_by_id(absint($data['id']));
                if ($existing_schedule) {
                    $start_timestamp = strtotime($start_time);
                    $existing_next_run_timestamp = strtotime($existing_schedule->next_run);
                    $now_timestamp = current_time('timestamp');

                    // Heuristic: If start_time is significantly in the past (older than 1 min ago to allow for 'now')
                    // and the existing schedule is healthy (next_run is in the future), preserve the existing schedule.
                    if ($start_timestamp < ($now_timestamp - 60) && $existing_next_run_timestamp > $now_timestamp) {
                        $next_run = $existing_schedule->next_run;
                    }
                }
            }
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

        if (!empty($data['id'])) {
            $this->repository->update(absint($data['id']), $schedule_data);
            return absint($data['id']);
        } else {
            return $this->repository->create($schedule_data);
        }
    }

    public function save_schedule_bulk($schedules) {
        return $this->repository->create_bulk($schedules);
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
    
    /**
     * Run a specific schedule immediately.
     *
     * @param int $schedule_id The schedule ID.
     * @return int|WP_Error Post ID on success, or WP_Error on failure.
     */
    public function run_schedule_now($schedule_id) {
        return $this->processor->process_single_schedule($schedule_id);
    }

    /**
     * Process scheduled posts that are due.
     *
     * Delegates to the Schedule Processor.
     */
    public function process_scheduled_posts() {
        $this->processor->process_due_schedules();
    }
}
