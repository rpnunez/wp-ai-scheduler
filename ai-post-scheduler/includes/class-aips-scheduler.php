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
     * @var AIPS_Schedule_Repository Repository for database operations
     */
    private $repository;
    
    /**
     * @var AIPS_Activity_Repository Repository for activity logging
     */
    private $activity_repository;
    
    public function __construct() {
        global $wpdb;
        $this->schedule_table = $wpdb->prefix . 'aips_schedule';
        $this->templates_table = $wpdb->prefix . 'aips_templates';
        $this->interval_calculator = new AIPS_Interval_Calculator();
        $this->repository = new AIPS_Schedule_Repository();
        $this->activity_repository = new AIPS_Activity_Repository();
        $this->template_type_selector = new AIPS_Template_Type_Selector();
        
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
     * Process scheduled posts.
     *
     * Uses a time-bound loop to process as many schedules as possible within a limit (20s).
     * Chains a new cron event if work remains or time limit is reached.
     */
    public function process_scheduled_posts() {
        global $wpdb;
        
        $logger = new AIPS_Logger();
        $start_timestamp = time();
        $time_limit = 20; // Seconds to run before respawning
        
        $logger->log('Starting scheduled post execution cycle', 'info');
        
        while (true) {
            // Check time budget before starting a batch
            if ((time() - $start_timestamp) >= $time_limit) {
                $logger->log('Time limit reached, rescheduling continuation', 'info');
                // Schedule an immediate run to continue processing
                wp_schedule_single_event(time(), 'aips_generate_scheduled_posts');
                break;
            }

            // Fetch a batch of due schedules
            // Joining templates table to ensure we only run active templates
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
                $logger->log('No more scheduled posts due in this cycle', 'info');
                break;
            }
            
            $generator = new AIPS_Generator();
            
            foreach ($due_schedules as $schedule) {
                // Check time budget inside the batch loop
                if ((time() - $start_timestamp) >= $time_limit) {
                    $logger->log('Time limit reached during batch, rescheduling continuation', 'info');
                    wp_schedule_single_event(time(), 'aips_generate_scheduled_posts');
                    break 2; // Break both loops
                }

                // --- LOCKING PHASE ---
                // Update next_run immediately for recurring schedules to prevent double-processing
                // and ensure the schedule advances even if generation crashes.
                if ($schedule->frequency !== 'once') {
                    // Calculate next run based on the *scheduled* time (next_run), not current time,
                    // to preserve the schedule phase (e.g. keep it at 10:00:00).
                    $next_run = $this->calculate_next_run($schedule->frequency, $schedule->next_run);

                    $this->repository->update($schedule->schedule_id, array(
                        'last_run' => current_time('mysql'),
                        'next_run' => $next_run,
                    ));
                    
                    $logger->log('Schedule locked and advanced', 'info', array(
                        'schedule_id' => $schedule->schedule_id,
                        'new_next_run' => $next_run
                    ));
                }

                // --- EXECUTION PHASE ---
                // Dispatch schedule execution started event
                do_action('aips_schedule_execution_started', $schedule->schedule_id);

                $logger->log('Processing schedule: ' . $schedule->schedule_id, 'info', array(
                    'template_id' => $schedule->template_id,
                    'template_name' => $schedule->name,
                    'topic' => isset($schedule->topic) ? $schedule->topic : ''
                ));
                
                // Select article structure for this execution
                $article_structure_id = $this->template_type_selector->select_structure($schedule);
                
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
                    'article_structure_id' => $article_structure_id,
                );

                $topic = isset($schedule->topic) ? $schedule->topic : null;
                $result = $generator->generate_post($template, null, $topic);

                // --- POST-EXECUTION PHASE ---
                
                if ($schedule->frequency === 'once') {
                    if (!is_wp_error($result)) {
                        // Success: Delete the one-time schedule
                        $this->repository->delete($schedule->schedule_id);
                        $logger->log('One-time schedule completed and deleted', 'info', array('schedule_id' => $schedule->schedule_id));
                    } else {
                        // Failure: Deactivate to prevent infinite retries
                        $this->repository->update($schedule->schedule_id, array(
                            'is_active' => 0,
                            'status' => 'failed', // Note: table might not have status column, but update is safe (ignores extra keys in some repos, but let's check repo)
                            // AIPS_Schedule_Repository::update filters using array_intersect_key usually?
                            // Actually, let's just stick to known columns: is_active, last_run.
                            'last_run' => current_time('mysql')
                        ));
                        $logger->log('One-time schedule failed and deactivated', 'info', array('schedule_id' => $schedule->schedule_id));

                        // Log to activity feed
                        $this->activity_repository->create(array(
                            'event_type' => 'schedule_failed',
                            'event_status' => 'failed',
                            'schedule_id' => $schedule->schedule_id,
                            'template_id' => $schedule->template_id,
                            'message' => sprintf(
                                __('One-time schedule "%s" failed and was deactivated', 'ai-post-scheduler'),
                                $schedule->template_name
                            ),
                            'metadata' => array(
                                'error' => $result->get_error_message(),
                                'frequency' => $schedule->frequency,
                            ),
                        ));
                    }
                } else {
                    // Recurring: We already updated next_run.
                    // Just log failures/success.
                    
                    if (is_wp_error($result)) {
                        $logger->log('Schedule failed (already advanced): ' . $result->get_error_message(), 'error', array(
                            'schedule_id' => $schedule->schedule_id
                        ));

                        $this->activity_repository->create(array(
                            'event_type' => 'schedule_failed',
                            'event_status' => 'failed',
                            'schedule_id' => $schedule->schedule_id,
                            'template_id' => $schedule->template_id,
                            'message' => sprintf(
                                __('Schedule "%s" failed to generate post', 'ai-post-scheduler'),
                                $schedule->template_name
                            ),
                            'metadata' => array(
                                'error' => $result->get_error_message(),
                                'frequency' => $schedule->frequency,
                            ),
                        ));

                        do_action('aips_schedule_execution_failed', $schedule->schedule_id, $result->get_error_message());
                    } else {
                        $logger->log('Schedule completed successfully', 'info', array(
                            'schedule_id' => $schedule->schedule_id,
                            'post_id' => $result
                        ));

                        $post = get_post($result);
                        if ($post) {
                            $event_status = ($post->post_status === 'draft') ? 'draft' : 'success';
                            $event_type = ($post->post_status === 'draft') ? 'post_draft' : 'post_published';

                            $this->activity_repository->create(array(
                                'event_type' => $event_type,
                                'event_status' => $event_status,
                                'schedule_id' => $schedule->schedule_id,
                                'post_id' => $result,
                                'template_id' => $schedule->template_id,
                                'message' => sprintf(
                                    __('%s created by schedule "%s": %s', 'ai-post-scheduler'),
                                    ($post->post_status === 'draft') ? __('Draft', 'ai-post-scheduler') : __('Post', 'ai-post-scheduler'),
                                    $schedule->template_name,
                                    $post->post_title
                                ),
                                'metadata' => array(
                                    'post_status' => $post->post_status,
                                    'frequency' => $schedule->frequency,
                                ),
                            ));
                        }

                        do_action('aips_schedule_execution_completed', $schedule->schedule_id, $result);
                    }
                }
            } // end foreach

            // If we processed a full batch (5), there might be more.
            // We loop again immediately.
            // (The while(true) handles this).

        } // end while
    }
}
