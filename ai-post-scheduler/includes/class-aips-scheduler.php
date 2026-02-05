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
    
    public function __construct() {
        global $wpdb;
        $this->schedule_table = $wpdb->prefix . 'aips_schedule';
        $this->templates_table = $wpdb->prefix . 'aips_templates';
        $this->interval_calculator = new AIPS_Interval_Calculator();
        $this->repository = new AIPS_Schedule_Repository();
        $this->template_repository = new AIPS_Template_Repository();
        $this->history_service = new AIPS_History_Service();
        $this->template_type_selector = new AIPS_Template_Type_Selector();
        
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
    }

    /**
     * Set a custom repository instance (dependency injection).
     *
     * @param AIPS_Schedule_Repository $repository
     */
    public function set_repository($repository) {
        $this->repository = $repository;
    }

    /**
     * Set a custom template repository instance (dependency injection).
     *
     * @param AIPS_Template_Repository $repository
     */
    public function set_template_repository($repository) {
        $this->template_repository = $repository;
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
        $schedule = $this->repository->get_by_id($schedule_id);

        if (!$schedule) {
            return new WP_Error('schedule_not_found', __('Schedule not found.', 'ai-post-scheduler'));
        }

        $template = $this->template_repository->get_by_id($schedule->template_id);

        if (!$template) {
            return new WP_Error('template_not_found', __('Template not found.', 'ai-post-scheduler'));
        }

        // Apply schedule overrides to template
        $template->post_quantity = 1; // Force quantity to 1 for manual run

        // Select article structure for this execution
        $article_structure_id = $this->template_type_selector->select_structure($schedule);
        if ($article_structure_id) {
            $template->article_structure_id = $article_structure_id;
        }

        $generator = $this->generator ?: new AIPS_Generator();
        $topic = isset($schedule->topic) ? $schedule->topic : null;

        // Log manual execution using History Container
        $history = $this->history_service->create('manual_schedule_execution', array(
            'schedule_id' => $schedule_id,
            'user_id' => get_current_user_id()
        ));

        $history->record(
            'activity',
            sprintf(
                __('Manual execution of schedule "%s" started', 'ai-post-scheduler'),
                $template->name
            ),
            array(
                'event_type' => 'manual_schedule_started',
                'event_status' => 'success',
            ),
            null,
            array(
                'schedule_id' => $schedule_id,
                'template_id' => $template->id,
                'topic' => $topic
            )
        );

        // Generate the post
        $result = $generator->generate_post($template, null, $topic);

        if (is_wp_error($result)) {
             $history->record(
                'activity',
                sprintf(
                    __('Manual execution of schedule "%s" failed: %s', 'ai-post-scheduler'),
                    $template->name,
                    $result->get_error_message()
                ),
                array(
                    'event_type' => 'manual_schedule_failed',
                    'event_status' => 'failed',
                ),
                null,
                array(
                    'schedule_id' => $schedule_id,
                    'error' => $result->get_error_message()
                )
            );
            return $result;
        }

        // Log success
        $post = get_post($result);
        if ($post) {
             $history->record(
                'activity',
                sprintf(
                    __('Manual execution of schedule "%s" completed. Post: %s', 'ai-post-scheduler'),
                    $template->name,
                    $post->post_title
                ),
                array(
                    'event_type' => 'manual_schedule_completed',
                    'event_status' => 'success',
                ),
                null,
                array(
                    'schedule_id' => $schedule_id,
                    'post_id' => $result,
                    'post_status' => $post->post_status
                )
            );
        }

        // Invalidate cache for rotation logic
        $this->template_type_selector->invalidate_count_cache($schedule_id);

        return $result;
    }

    public function process_scheduled_posts() {
        global $wpdb;
        
        $logger = new AIPS_Logger();
        $logger->log('Starting scheduled post generation', 'info');
        
        $due_schedules = $wpdb->get_results($wpdb->prepare("
            SELECT s.*
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
        
        $generator = $this->generator ?: new AIPS_Generator();
        
        foreach ($due_schedules as $schedule) {
            try {
                // Retrieve full template object via repository
                $template = $this->template_repository->get_by_id($schedule->template_id);

                if (!$template) {
                     $logger->log('Template not found for schedule ' . $schedule->id, 'error');
                     continue;
                }

                // Claim-First Locking Strategy (Hunter)
                // Immediately calculate and update next_run to lock this schedule from concurrent processes.

                $original_next_run = $schedule->next_run;
                $new_next_run = null;

                if ($schedule->frequency === 'once') {
                    // For one-time schedules, "claim" it by pushing next_run forward (e.g., 1 hour)
                    // If the process crashes, it will be retried in 1 hour.
                    // If it succeeds, it will be deleted.
                    $new_next_run = date('Y-m-d H:i:s', current_time('timestamp') + HOUR_IN_SECONDS);
                } else {
                     // Calculate next run using original next_run to preserve phase
                     $new_next_run = $this->calculate_next_run($schedule->frequency, $original_next_run);
                }

                // Update next_run immediately to lock this schedule from concurrent runs
                $lock_result = $this->repository->update($schedule->id, array(
                    'next_run' => $new_next_run
                ));

                if ($lock_result === false) {
                    $logger->log('Failed to acquire lock for schedule ' . $schedule->id, 'error');
                    continue; // Skip generation if we couldn't lock
                }

                // Dispatch schedule execution started event
                do_action('aips_schedule_execution_started', $schedule->id);
                
                $logger->log('Processing schedule: ' . $schedule->id, 'info', array(
                    'template_id' => $schedule->template_id,
                    'template_name' => $template->name,
                    'topic' => isset($schedule->topic) ? $schedule->topic : ''
                ));
                
                // NEW: Select article structure for this execution
                $article_structure_id = $this->template_type_selector->select_structure($schedule);
                
                // Log schedule execution using History Container
                $history = $this->history_service->create('schedule_execution', array(
                    'schedule_id' => $schedule->id,
                ));
                $history->record(
                    'activity',
                    sprintf(
                        __('Schedule "%s" started execution', 'ai-post-scheduler'),
                        $template->name
                    ),
                    array(
                        'event_type' => 'schedule_executed',
                        'event_status' => 'success',
                    ),
                    null,
                    array(
                        'schedule_id' => $schedule->id,
                        'template_id' => $template->id,
                        'frequency' => $schedule->frequency,
                        'topic' => isset($schedule->topic) ? $schedule->topic : '',
                        'article_structure_id' => $article_structure_id,
                    )
                );

                // Apply schedule overrides to template
                $template->post_quantity = 1; // Schedules always run one at a time per interval

                if ($article_structure_id) {
                    $template->article_structure_id = $article_structure_id;
                }

                $topic = isset($schedule->topic) ? $schedule->topic : null;
                $result = $generator->generate_post($template, null, $topic);

                if ($schedule->frequency === 'once') {
                    if (!is_wp_error($result)) {
                        // If it's a one-time schedule and successful, delete it
                        $this->repository->delete($schedule->id);
                        $logger->log('One-time schedule completed and deleted', 'info', array('schedule_id' => $schedule->id));
                    } else {
                        // If failed, deactivate it and set status to 'failed' to prevent infinite daily retries
                        $this->repository->update($schedule->id, array(
                            'is_active' => 0,
                            'status' => 'failed',
                            'last_run' => current_time('mysql')
                        ));
                        $logger->log('One-time schedule failed and deactivated', 'info', array('schedule_id' => $schedule->id));

                        // Log using History Container
                        $fail_history = $this->history_service->create('schedule_execution', array(
                            'schedule_id' => $schedule->id,
                        ));
                        $fail_history->record(
                            'activity',
                            sprintf(
                                __('One-time schedule "%s" failed and was deactivated', 'ai-post-scheduler'),
                                $template->name
                            ),
                            array(
                                'event_type' => 'schedule_failed',
                                'event_status' => 'failed',
                            ),
                            null,
                            array(
                                'schedule_id' => $schedule->id,
                                'template_id' => $template->id,
                                'error' => $result->get_error_message(),
                                'frequency' => $schedule->frequency,
                            )
                        );
                    }
                } else {
                    // For recurring schedules, we ONLY update last_run here.
                    // next_run was already updated at the start (Claim-First).
                    $this->repository->update_last_run($schedule->id, current_time('mysql'));
                }

                if (is_wp_error($result)) {
                    $logger->log('Schedule failed: ' . $result->get_error_message(), 'error', array(
                        'schedule_id' => $schedule->id
                    ));

                    // Log recurring schedule failures using History Container
                    if ($schedule->frequency !== 'once') {
                        $recurring_fail_history = $this->history_service->create('schedule_execution', array(
                            'schedule_id' => $schedule->id,
                        ));
                        $recurring_fail_history->record(
                            'activity',
                            sprintf(
                                __('Schedule "%s" failed to generate post', 'ai-post-scheduler'),
                                $template->name
                            ),
                            array(
                                'event_type' => 'schedule_failed',
                                'event_status' => 'failed',
                            ),
                            null,
                            array(
                                'schedule_id' => $schedule->id,
                                'template_id' => $template->id,
                                'error' => $result->get_error_message(),
                                'frequency' => $schedule->frequency,
                            )
                        );
                    }
                    
                    // Dispatch schedule execution failed event
                    do_action('aips_schedule_execution_failed', $schedule->id, $result->get_error_message());
                } else {
                    $logger->log('Schedule completed successfully', 'info', array(
                        'schedule_id' => $schedule->id,
                        'post_id' => $result
                    ));

                    // Get the post to check its status
                    $post = get_post($result);
                    if ($post) {
                        $event_status = ($post->post_status === 'draft') ? 'draft' : 'success';
                        $event_type = ($post->post_status === 'draft') ? 'post_draft' : 'post_published';

                        // Log using History Container
                        $success_history = $this->history_service->create('schedule_execution', array(
                            'schedule_id' => $schedule->id,
                            'post_id' => $result,
                        ));
                        $success_history->record(
                            'activity',
                            sprintf(
                                __('%s created by schedule "%s": %s', 'ai-post-scheduler'),
                                ($post->post_status === 'draft') ? __('Draft', 'ai-post-scheduler') : __('Post', 'ai-post-scheduler'),
                                $template->name,
                                $post->post_title
                            ),
                            array(
                                'event_type' => $event_type,
                                'event_status' => $event_status,
                            ),
                            null,
                            array(
                                'schedule_id' => $schedule->id,
                                'post_id' => $result,
                                'template_id' => $template->id,
                                'post_status' => $post->post_status,
                                'frequency' => $schedule->frequency,
                            )
                        );
                    }

                    // Dispatch schedule execution completed event
                    do_action('aips_schedule_execution_completed', $schedule->id, $result);

                    // Invalidate the schedule execution count cache (Bolt)
                    // This ensures rotation logic uses fresh counts on next run,
                    // and only after a successful post generation.
                    $this->template_type_selector->invalidate_count_cache($schedule->id);
                }
            } catch (Throwable $e) {
                // Catch any unexpected exceptions to prevent the cron job from crashing,
                // allowing subsequent schedules in the batch to be processed.
                $logger->log('Critical error processing schedule ' . $schedule->id . ': ' . $e->getMessage(), 'error', array(
                    'trace' => $e->getTraceAsString()
                ));
            }
        }
    }
}
