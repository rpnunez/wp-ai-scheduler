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
     * @var AIPS_History_Repository Repository for history operations
     */
    private $history_repository;

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
        $this->history_repository = new AIPS_History_Repository();
        $this->history_service = new AIPS_History_Service($this->history_repository);
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
            $schedule_id = absint($data['id']);
            $existing = $this->repository->get_by_id($schedule_id);
            $this->repository->update($schedule_id, $schedule_data);

            // Record update event in the schedule's persistent history container
            $history_container = $this->get_or_create_schedule_history($schedule_id, $existing);
            if ($history_container) {
                $user = wp_get_current_user();
                $user_label = ($user && $user->ID) ? $user->user_login : __('Unknown user', 'ai-post-scheduler');

                // Detect frequency change for a descriptive message
                $old_frequency = $existing ? $existing->frequency : '';
                if ($old_frequency && $old_frequency !== $frequency) {
                    $message = sprintf(
                        /* translators: 1: old frequency, 2: new frequency, 3: user login */
                        __('Schedule interval updated from "%1$s" to "%2$s" by %3$s', 'ai-post-scheduler'),
                        $old_frequency,
                        $frequency,
                        $user_label
                    );
                } else {
                    $message = sprintf(
                        /* translators: 1: user login */
                        __('Schedule updated by %s', 'ai-post-scheduler'),
                        $user_label
                    );
                }

                $history_container->record(
                    'activity',
                    $message,
                    array(
                        'event_type' => 'schedule_updated',
                        'event_status' => 'success',
                    ),
                    null,
                    array(
                        'schedule_id' => $schedule_id,
                        'user_id' => ($user ? $user->ID : 0),
                        'frequency' => $frequency,
                        'old_frequency' => $old_frequency,
                    )
                );
            }

            return $schedule_id;
        } else {
            $new_id = $this->repository->create($schedule_data);

            if ($new_id) {
                // Create a new persistent history container for this schedule
                $history_container = $this->history_service->create('schedule_lifecycle', array(
                    'schedule_id' => $new_id,
                ));

                if ($history_container && $history_container->get_id()) {
                    // Persist the history container ID on the schedule record
                    $this->repository->update($new_id, array(
                        'schedule_history_id' => $history_container->get_id(),
                    ));

                    $user = wp_get_current_user();
                    $user_label = ($user && $user->ID) ? $user->user_login : __('Unknown user', 'ai-post-scheduler');

                    $history_container->record(
                        'activity',
                        sprintf(
                            /* translators: 1: frequency, 2: user login */
                            __('Schedule created to run %1$s by %2$s', 'ai-post-scheduler'),
                            $frequency,
                            $user_label
                        ),
                        array(
                            'event_type' => 'schedule_created',
                            'event_status' => 'success',
                        ),
                        null,
                        array(
                            'schedule_id' => $new_id,
                            'user_id' => ($user ? $user->ID : 0),
                            'frequency' => $frequency,
                        )
                    );
                }
            }

            return $new_id;
        }
    }

    /**
     * Load or create a persistent schedule lifecycle history container.
     *
     * If the schedule already has a schedule_history_id, load that container.
     * Otherwise create a new one and attach it.
     *
     * @param int         $schedule_id  Schedule ID.
     * @param object|null $schedule     Optional existing schedule record (avoids extra DB query).
     * @return AIPS_History_Container|null Container instance or null on failure.
     */
    private function get_or_create_schedule_history($schedule_id, $schedule = null) {
        if ($schedule === null) {
            $schedule = $this->repository->get_by_id($schedule_id);
        }

        if (!$schedule) {
            return null;
        }

        $history_repository = $this->history_repository;

        if (!empty($schedule->schedule_history_id)) {
            $container = AIPS_History_Container::load_existing($history_repository, $schedule->schedule_history_id);
            if ($container) {
                return $container;
            }
        }

        // No existing container — create one and attach it
        $container = $this->history_service->create('schedule_lifecycle', array(
            'schedule_id' => $schedule_id,
        ));

        if ($container && $container->get_id()) {
            $this->repository->update($schedule_id, array(
                'schedule_history_id' => $container->get_id(),
            ));
        }

        return $container;
    }

    public function save_schedule_bulk($schedules) {
        return $this->repository->create_bulk($schedules);
    }
    
    public function delete_schedule($id) {
        return $this->repository->delete($id);
    }

    public function toggle_active($id, $is_active) {
        $existing = $this->repository->get_by_id($id);
        $result = $this->repository->set_active($id, $is_active);

        if ($result !== false) {
            // Record enable/disable event in the schedule's persistent history container
            $history_container = $this->get_or_create_schedule_history($id, $existing);
            if ($history_container) {
                $user = wp_get_current_user();
                $user_label = ($user && $user->ID) ? $user->user_login : __('Unknown user', 'ai-post-scheduler');
                $action_label = $is_active ? __('enabled', 'ai-post-scheduler') : __('disabled', 'ai-post-scheduler');

                $history_container->record(
                    'activity',
                    sprintf(
                        /* translators: 1: enabled/disabled, 2: user login */
                        __('Schedule %1$s by %2$s', 'ai-post-scheduler'),
                        $action_label,
                        $user_label
                    ),
                    array(
                        'event_type' => $is_active ? 'schedule_enabled' : 'schedule_disabled',
                        'event_status' => 'success',
                    ),
                    null,
                    array(
                        'schedule_id' => $id,
                        'user_id' => ($user ? $user->ID : 0),
                        'is_active' => (int) $is_active,
                    )
                );
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
     * Run a specific schedule immediately.
     *
     * @param int      $schedule_id      The schedule ID.
     * @param int|null $quantity_override Optional number of posts to generate, overriding the template's post_quantity.
     * @return int|WP_Error Post ID on success, or WP_Error on failure.
     */
    public function run_schedule_now($schedule_id, $quantity_override = null) {
        return $this->processor->process_single_schedule($schedule_id, $quantity_override);
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
