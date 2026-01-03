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
    
    public function __construct() {
        global $wpdb;
        $this->schedule_table = $wpdb->prefix . 'aips_schedule';
        $this->templates_table = $wpdb->prefix . 'aips_templates';
        $this->interval_calculator = new AIPS_Interval_Calculator();
        $this->repository = new AIPS_Schedule_Repository();
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
        
        // Handle Advanced Rules and Schedule Type
        $schedule_type = isset($data['schedule_type']) ? sanitize_text_field($data['schedule_type']) : 'simple';
        $advanced_rules = isset($data['advanced_rules']) ? $data['advanced_rules'] : null;

        if (is_array($advanced_rules)) {
            $advanced_rules = json_encode($advanced_rules); // Store as JSON string
        } else if ($advanced_rules === '') {
            $advanced_rules = null;
        }

        $schedule_data = array(
            'template_id' => absint($data['template_id']),
            'frequency' => $frequency,
            'next_run' => $next_run,
            'is_active' => isset($data['is_active']) ? 1 : 0,
            'topic' => isset($data['topic']) ? sanitize_text_field($data['topic']) : '',
            'article_structure_id' => isset($data['article_structure_id']) ? absint($data['article_structure_id']) : null,
            'rotation_pattern' => isset($data['rotation_pattern']) ? sanitize_text_field($data['rotation_pattern']) : null,
            'schedule_type' => $schedule_type,
            'advanced_rules' => $advanced_rules,
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
     * @param array|null  $rules      Optional rules for custom schedules.
     * @return string The next run time in MySQL datetime format.
     */
    public function calculate_next_run($frequency, $start_time = null, $rules = null) {
        return $this->interval_calculator->calculate_next_run($frequency, $start_time, $rules);
    }
    
    public function process_scheduled_posts() {
        global $wpdb;
        
        $logger = new AIPS_Logger();
        $logger->log('Starting scheduled post generation', 'info');
        
        // Fetch Due Schedules
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
        
        $generator = new AIPS_Generator();
        $queue_table = $wpdb->prefix . 'aips_schedule_queue';

        // Track execution metrics for monitoring
        $execution_start = microtime(true);
        $max_execution_time = 50; // Leave buffer before PHP timeout (usually 60s)
        $total_generated = 0;
        $total_failed = 0;

        foreach ($due_schedules as $schedule) {
            // Check if we're approaching time limit - break to avoid timeout
            $elapsed_time = microtime(true) - $execution_start;
            if ($elapsed_time > $max_execution_time) {
                $logger->log('Approaching execution time limit. Deferring remaining schedules to next run.', 'warning', array(
                    'elapsed_seconds' => round($elapsed_time, 2),
                    'schedules_processed' => $total_generated + $total_failed,
                    'remaining_schedules' => count($due_schedules)
                ));
                break;
            }

            // Dispatch schedule execution started event
            do_action('aips_schedule_execution_started', $schedule->schedule_id);
            
            // 1. Determine Post Quantity
            // Use Template's quantity if set, otherwise default to 1.
            $quantity_to_generate = isset($schedule->post_quantity) && intval($schedule->post_quantity) > 0
                ? intval($schedule->post_quantity)
                : 1;

            // Limit catch-up operations: If schedule is way behind, cap generation to avoid extended processing
            $max_batch_size = 10; // Maximum posts per schedule run to prevent timeout
            $schedule_behind_seconds = strtotime(current_time('mysql')) - strtotime($schedule->next_run);
            
            if ($quantity_to_generate > $max_batch_size) {
                $logger->log('Large batch detected. Capping generation to prevent timeout.', 'warning', array(
                    'schedule_id' => $schedule->schedule_id,
                    'requested_quantity' => $quantity_to_generate,
                    'capped_quantity' => $max_batch_size,
                    'behind_hours' => round($schedule_behind_seconds / 3600, 1)
                ));
                $quantity_to_generate = $max_batch_size;
            }

            $logger->log('Processing schedule: ' . $schedule->schedule_id, 'info', array(
                'template_id' => $schedule->template_id,
                'batch_size' => $quantity_to_generate,
                'type' => isset($schedule->schedule_type) ? $schedule->schedule_type : 'simple',
                'behind_hours' => round($schedule_behind_seconds / 3600, 1)
            ));

            $generated_count = 0;
            $failed_count = 0;
            $last_result = null;

            // 2. Loop for Batch Generation
            for ($i = 0; $i < $quantity_to_generate; $i++) {

                // 3. Determine Topic (Queue vs Static vs None)
                $topic = null;
                $queue_item_id = null;

                // Check Queue first
                $queued_topic = $wpdb->get_row($wpdb->prepare("
                    SELECT id, topic FROM {$queue_table}
                    WHERE schedule_id = %d AND status = 'pending'
                    ORDER BY id ASC LIMIT 1
                ", $schedule->schedule_id));

                if ($queued_topic) {
                    $topic = $queued_topic->topic;
                    $queue_item_id = $queued_topic->id;
                    $logger->log("Using queued topic: $topic", 'info');
                } else if (!empty($schedule->topic)) {
                    // Fallback to static topic from Schedule definition
                    $topic = $schedule->topic;
                }

                // If no topic found and we are relying on a queue (implied by empty static topic?),
                // do we stop? Or generate generic?
                // Logic: If static topic is empty, and queue is empty, we generate a generic post
                // ONLY IF it's not strictly a "Queue Runner".
                // But for now, let's assume if no topic, we let the generator handle it (it might generate random).

                // 4. Select Article Structure
                $article_structure_id = $this->template_type_selector->select_structure($schedule);

                // 5. Determine Post Status (Review Workflow)
                $post_status = $schedule->post_status;
                if (!empty($schedule->review_required)) {
                    $post_status = 'pending'; // Force pending if review required
                }

                $template = (object) array(
                    'id' => $schedule->template_id,
                    'name' => $schedule->name,
                    'prompt_template' => $schedule->prompt_template,
                    'title_prompt' => $schedule->title_prompt,
                    'post_status' => $post_status,
                    'post_category' => $schedule->post_category,
                    'post_tags' => $schedule->post_tags,
                    'post_author' => $schedule->post_author,
                    'post_quantity' => 1, // Passed to generator as 1 because we are looping manually here
                    'generate_featured_image' => isset($schedule->generate_featured_image) ? $schedule->generate_featured_image : 0,
                    'image_prompt' => isset($schedule->image_prompt) ? $schedule->image_prompt : '',
                    'article_structure_id' => $article_structure_id,
                );

                $result = $generator->generate_post($template, null, $topic);
                $last_result = $result;

                if (is_wp_error($result)) {
                    $failed_count++;
                    $logger->log('Post generation failed: ' . $result->get_error_message(), 'error');
                } else {
                    $generated_count++;
                    // Mark queue item as processed
                    if ($queue_item_id) {
                        $wpdb->update($queue_table,
                            array('status' => 'processed'),
                            array('id' => $queue_item_id),
                            array('%s'),
                            array('%d')
                        );
                    }

                    do_action('aips_schedule_execution_completed', array(
                        'schedule_id' => $schedule->schedule_id,
                        'post_id' => $result,
                        'metadata' => array('topic' => $topic),
                        'timestamp' => current_time('mysql'),
                    ), 'schedule_execution');
                }
            }
            
            // Track totals for monitoring
            $total_generated += $generated_count;
            $total_failed += $failed_count;
            
            // 6. Calculate Next Run
            if ($schedule->frequency === 'once' && $failed_count === 0) {
                // One-time schedule done
                $this->repository->delete($schedule->schedule_id);
                $logger->log('One-time schedule completed and deleted', 'info', array('schedule_id' => $schedule->schedule_id));
            } else {
                // Calculate next run
                $rules = !empty($schedule->advanced_rules) ? json_decode($schedule->advanced_rules, true) : null;
                $next_run = $this->calculate_next_run($schedule->frequency, $schedule->next_run, $rules);

                $this->repository->update($schedule->schedule_id, array(
                    'last_run' => current_time('mysql'),
                    'next_run' => $next_run,
                ));
            }
            
            if (is_wp_error($result)) {
                $logger->log('Schedule failed: ' . $result->get_error_message(), 'error', array(
                    'schedule_id' => $schedule->schedule_id
                ));
                
                // Dispatch schedule execution failed event
                do_action('aips_schedule_execution_failed', $schedule->schedule_id, $result->get_error_message());
            } else {
                $logger->log('Schedule completed successfully', 'info', array(
                    'schedule_id' => $schedule->schedule_id,
                    'post_id' => $result
                ));
                
                // Dispatch schedule execution completed event
                do_action('aips_schedule_execution_completed', $schedule->schedule_id, $result);
            }
        }

        // Log execution summary for monitoring
        $execution_duration = microtime(true) - $execution_start;
        $logger->log('Scheduled post generation completed', 'info', array(
            'duration_seconds' => round($execution_duration, 2),
            'schedules_processed' => count($due_schedules),
            'total_generated' => $total_generated,
            'total_failed' => $total_failed,
            'memory_peak_mb' => round(memory_get_peak_usage(true) / 1024 / 1024, 2)
        ));
    }
}
