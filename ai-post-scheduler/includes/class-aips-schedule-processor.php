<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * AIPS_Schedule_Processor
 *
 * Responsible for executing scheduled post generations.
 * This class handles the logic for processing due schedules, locking them,
 * and coordinating the post generation process.
 *
 * @package AI_Post_Scheduler
 * @since 1.6.0
 */
class AIPS_Schedule_Processor {

    /**
     * @var AIPS_Schedule_Repository
     */
    private $repository;

    /**
     * @var AIPS_Template_Repository
     */
    private $template_repository;

    /**
     * @var AIPS_Generator
     */
    private $generator;

    /**
     * @var AIPS_History_Service
     */
    private $history_service;

    /**
     * @var AIPS_Interval_Calculator
     */
    private $interval_calculator;

    /**
     * @var AIPS_Template_Type_Selector
     */
    private $template_type_selector;

    /**
     * @var AIPS_Logger
     */
    private $logger;

    /**
     * Constructor.
     *
     * @param AIPS_Schedule_Repository|null    $repository
     * @param AIPS_Template_Repository|null    $template_repository
     * @param AIPS_Generator|null              $generator
     * @param AIPS_History_Service|null        $history_service
     * @param AIPS_Template_Type_Selector|null $template_type_selector
     * @param AIPS_Logger|null                 $logger
     */
    public function __construct(
        $repository = null,
        $template_repository = null,
        $generator = null,
        $history_service = null,
        $template_type_selector = null,
        $logger = null
    ) {
        $this->repository = $repository ?: new AIPS_Schedule_Repository();
        $this->template_repository = $template_repository ?: new AIPS_Template_Repository();
        $this->generator = $generator ?: new AIPS_Generator();
        $this->history_service = $history_service ?: new AIPS_History_Service();
        $this->interval_calculator = new AIPS_Interval_Calculator();
        $this->template_type_selector = $template_type_selector ?: new AIPS_Template_Type_Selector();
        $this->logger = $logger ?: new AIPS_Logger();
    }

    /**
     * Set dependencies (useful for testing or late injection).
     */
    public function set_generator($generator) {
        $this->generator = $generator;
    }

    public function set_repository($repository) {
        $this->repository = $repository;
    }

    public function set_template_repository($repository) {
        $this->template_repository = $repository;
    }

    /**
     * Process all schedules that are due to run.
     *
     * @return void
     */
    public function process_due_schedules() {
        $this->logger->log('Starting scheduled post generation', 'info');

        // Use the updated repository method that handles the join and limit
        $due_schedules = $this->repository->get_due_schedules(current_time('mysql'), 5);

        if (empty($due_schedules)) {
            $this->logger->log('No scheduled posts due', 'info');
            return;
        }

        foreach ($due_schedules as $schedule) {
            $this->execute_schedule_with_lock($schedule);
        }
    }

    /**
     * Run a specific schedule immediately.
     *
     * @param int $schedule_id The schedule ID.
     * @return int|WP_Error Post ID on success, or WP_Error on failure.
     */
    public function process_single_schedule($schedule_id) {
        $schedule = $this->repository->get_by_id($schedule_id);

        if (!$schedule) {
            return new WP_Error('schedule_not_found', __('Schedule not found.', 'ai-post-scheduler'));
        }

        $template_data = $this->template_repository->get_by_id($schedule->template_id);

        if (!$template_data) {
            return new WP_Error('template_not_found', __('Template not found.', 'ai-post-scheduler'));
        }

        // Merge schedule data with template data to create a unified object
        // similar to what the JOIN query produces for process_due_schedules
        $schedule_with_template = (object) array_merge((array) $template_data, (array) $schedule);

        // Ensure schedule_id is set correctly (s.id alias)
        $schedule_with_template->schedule_id = $schedule->id;
        $schedule_with_template->name = $template_data->name; // ensure template name is preserved

        return $this->execute_schedule_logic($schedule_with_template, true);
    }

    /**
     * Execute a schedule with locking mechanism.
     *
     * @param object $schedule Schedule object (merged with template).
     */
    private function execute_schedule_with_lock($schedule) {
        try {
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
                 $new_next_run = $this->interval_calculator->calculate_next_run($schedule->frequency, $original_next_run);
            }

            // Update next_run immediately to lock this schedule from concurrent runs
            $lock_result = $this->repository->update($schedule->schedule_id, array(
                'next_run' => $new_next_run
            ));

            if ($lock_result === false) {
                $this->logger->log('Failed to acquire lock for schedule ' . $schedule->schedule_id, 'error');
                return; // Skip generation if we couldn't lock
            }

            // Execute the core logic
            $this->execute_schedule_logic($schedule, false);

        } catch (Throwable $e) {
            // Catch any unexpected exceptions to prevent the cron job from crashing,
            // allowing subsequent schedules in the batch to be processed.
            $this->logger->log('Critical error processing schedule ' . $schedule->schedule_id . ': ' . $e->getMessage(), 'error', array(
                'trace' => $e->getTraceAsString()
            ));
        }
    }

    /**
     * Core logic to execute a schedule.
     *
     * @param object $schedule  Schedule object (merged with template).
     * @param bool   $is_manual Whether this is a manual execution.
     * @return int|WP_Error Post ID or WP_Error.
     */
    private function execute_schedule_logic($schedule, $is_manual = false) {
        if (!$is_manual) {
            // Dispatch schedule execution started event
            do_action('aips_schedule_execution_started', $schedule->schedule_id);

            $this->logger->log('Processing schedule: ' . $schedule->schedule_id, 'info', array(
                'template_id' => $schedule->template_id,
                'template_name' => $schedule->name,
                'topic' => isset($schedule->topic) ? $schedule->topic : ''
            ));
        }

        // Apply schedule overrides to template logic
        $post_quantity = 1; // Always 1 for schedule executions

        // Select article structure for this execution
        $article_structure_id = $this->template_type_selector->select_structure($schedule);

        // Prepare context for logging
        $event_type = $is_manual ? 'manual_schedule_started' : 'schedule_executed';
        $log_activity_msg = $is_manual
            ? sprintf(__('Manual execution of schedule "%s" started', 'ai-post-scheduler'), $schedule->name)
            : sprintf(__('Schedule "%s" started execution', 'ai-post-scheduler'), $schedule->name);

        // Log schedule execution using History Container
        $history = $this->history_service->create($is_manual ? 'manual_schedule_execution' : 'schedule_execution', array(
            'schedule_id' => $schedule->schedule_id,
            'user_id' => $is_manual ? get_current_user_id() : null,
        ));

        $history->record(
            'activity',
            $log_activity_msg,
            array(
                'event_type' => $event_type,
                'event_status' => 'success',
            ),
            null,
            array(
                'schedule_id' => $schedule->schedule_id,
                'template_id' => $schedule->template_id,
                'frequency' => $schedule->frequency,
                'topic' => isset($schedule->topic) ? $schedule->topic : '',
                'article_structure_id' => $article_structure_id,
            )
        );

        // Construct Template Object for Generator
        // The generator expects an object with specific properties
        $template = (object) array(
            'id' => $schedule->template_id,
            'name' => $schedule->name,
            'prompt_template' => isset($schedule->prompt_template) ? $schedule->prompt_template : '',
            'title_prompt' => isset($schedule->title_prompt) ? $schedule->title_prompt : '',
            'post_status' => isset($schedule->post_status) ? $schedule->post_status : 'draft',
            'post_category' => isset($schedule->post_category) ? $schedule->post_category : null,
            'post_tags' => isset($schedule->post_tags) ? $schedule->post_tags : '',
            'post_author' => isset($schedule->post_author) ? $schedule->post_author : null,
            'post_quantity' => $post_quantity,
            'generate_featured_image' => isset($schedule->generate_featured_image) ? $schedule->generate_featured_image : 0,
            'image_prompt' => isset($schedule->image_prompt) ? $schedule->image_prompt : '',
            'article_structure_id' => $article_structure_id,
        );

        // Allow schedule to override certain template properties if they exist in schedule object (from join)
        // Currently the schedule table doesn't have post_status etc override columns, but if it did, they would be in $schedule
        // The only override is 'topic'

        $topic = isset($schedule->topic) ? $schedule->topic : null;
        $result = $this->generator->generate_post($template, null, $topic);

        // Handle Post-Execution Logic (Cleanup/Updates)
        if (!$is_manual) {
            $this->handle_post_execution_cleanup($schedule, $result);
        } else {
             // For manual runs, we invalidate cache but don't delete one-time schedules automatically?
             // The original code for run_schedule_now didn't delete one-time schedules or update next_run.
             // It just invalidated the cache.
             $this->template_type_selector->invalidate_count_cache($schedule->schedule_id);
        }

        // Handle Logging and Events based on Result
        if (is_wp_error($result)) {
            $this->handle_execution_failure($schedule, $result, $history, $is_manual);
        } else {
            $this->handle_execution_success($schedule, $result, $history, $is_manual);
        }

        return $result;
    }

    /**
     * Handle cleanup after automated execution (delete one-time, update recurring).
     *
     * @param object $schedule
     * @param mixed  $result
     */
    private function handle_post_execution_cleanup($schedule, $result) {
        if ($schedule->frequency === 'once') {
            if (!is_wp_error($result)) {
                // If it's a one-time schedule and successful, delete it
                $this->repository->delete($schedule->schedule_id);
                $this->logger->log('One-time schedule completed and deleted', 'info', array('schedule_id' => $schedule->schedule_id));
            } else {
                // If failed, deactivate it and set status to 'failed' to prevent infinite daily retries
                $this->repository->update($schedule->schedule_id, array(
                    'is_active' => 0,
                    'status' => 'failed',
                    'last_run' => current_time('mysql')
                ));
                $this->logger->log('One-time schedule failed and deactivated', 'info', array('schedule_id' => $schedule->schedule_id));

                // Log separate failure history
                $fail_history = $this->history_service->create('schedule_execution', array(
                    'schedule_id' => $schedule->schedule_id,
                ));
                $fail_history->record(
                    'activity',
                    sprintf(
                        __('One-time schedule "%s" failed and was deactivated', 'ai-post-scheduler'),
                        $schedule->name
                    ),
                    array(
                        'event_type' => 'schedule_failed',
                        'event_status' => 'failed',
                    ),
                    null,
                    array(
                        'schedule_id' => $schedule->schedule_id,
                        'template_id' => $schedule->template_id,
                        'error' => is_wp_error($result) ? $result->get_error_message() : 'Unknown error',
                        'frequency' => $schedule->frequency,
                    )
                );
            }
        } else {
            // For recurring schedules, we ONLY update last_run here.
            // next_run was already updated at the start (Claim-First).
            $this->repository->update_last_run($schedule->schedule_id, current_time('mysql'));
        }
    }

    /**
     * Handle failure logging.
     */
    private function handle_execution_failure($schedule, $result, $history, $is_manual) {
        $error_msg = $result->get_error_message();

        $this->logger->log('Schedule failed: ' . $error_msg, 'error', array(
            'schedule_id' => $schedule->schedule_id
        ));

        // Update the history record
        $history->record(
            'activity',
            sprintf(
                $is_manual ? __('Manual execution of schedule "%s" failed: %s', 'ai-post-scheduler') : __('Schedule "%s" failed to generate post: %s', 'ai-post-scheduler'),
                $schedule->name,
                $error_msg
            ),
            array(
                'event_type' => $is_manual ? 'manual_schedule_failed' : 'schedule_failed',
                'event_status' => 'failed',
            ),
            null,
            array(
                'schedule_id' => $schedule->schedule_id,
                'template_id' => $schedule->template_id,
                'error' => $error_msg,
                'frequency' => $schedule->frequency,
            )
        );

        if (!$is_manual) {
            // Dispatch schedule execution failed event
            do_action('aips_schedule_execution_failed', $schedule->schedule_id, $error_msg);
        }
    }

    /**
     * Handle success logging.
     */
    private function handle_execution_success($schedule, $result, $history, $is_manual) {
        $this->logger->log('Schedule completed successfully', 'info', array(
            'schedule_id' => $schedule->schedule_id,
            'post_id' => $result
        ));

        // Get the post to check its status
        $post = get_post($result);
        if ($post) {
            $event_status = ($post->post_status === 'draft') ? 'draft' : 'success';
            $event_type = ($post->post_status === 'draft') ? 'post_draft' : 'post_published';

            if ($is_manual) {
                $event_type = 'manual_schedule_completed';
                $event_status = 'success';
            }

            // Update the history record
            $history->record(
                'activity',
                sprintf(
                    __('%s created by schedule "%s": %s', 'ai-post-scheduler'),
                    ($post->post_status === 'draft') ? __('Draft', 'ai-post-scheduler') : __('Post', 'ai-post-scheduler'),
                    $schedule->name,
                    $post->post_title
                ),
                array(
                    'event_type' => $event_type,
                    'event_status' => $event_status,
                ),
                null,
                array(
                    'schedule_id' => $schedule->schedule_id,
                    'post_id' => $result,
                    'template_id' => $schedule->template_id,
                    'post_status' => $post->post_status,
                    'frequency' => $schedule->frequency,
                )
            );
        }

        if (!$is_manual) {
            // Dispatch schedule execution completed event
            do_action('aips_schedule_execution_completed', $schedule->schedule_id, $result);

            // Invalidate the schedule execution count cache (Bolt)
            $this->template_type_selector->invalidate_count_cache($schedule->schedule_id);
        }
    }
}
