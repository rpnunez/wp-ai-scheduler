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
     * @var AIPS_Schedule_Repository_Interface
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
     * @var AIPS_History_Service_Interface
     */
    private $history_service;

    /**
     * @var AIPS_History_Repository_Interface
     */
    private $history_repository;

    /**
     * @var AIPS_Interval_Calculator
     */
    private $interval_calculator;

    /**
     * @var AIPS_Template_Type_Selector
     */
    private $template_type_selector;

    /**
     * @var AIPS_Logger_Interface
     */
    private $logger;

    /**
     * @var AIPS_Generation_Execution_Runner
     */
    private $runner;

    /**
     * @var AIPS_Schedule_Result_Handler
     */
    private $result_handler;

    /**
     * Constructor.
     *
     * @param AIPS_Schedule_Repository_Interface|null $repository
     * @param AIPS_Template_Repository|null         $template_repository
     * @param AIPS_Generator|null                   $generator
     * @param AIPS_History_Service_Interface|null   $history_service
     * @param AIPS_Template_Type_Selector|null      $template_type_selector
     * @param AIPS_Logger_Interface|null            $logger
     * @param AIPS_Generation_Execution_Runner|null $runner
     * @param AIPS_Schedule_Result_Handler|null     $result_handler
     */
    public function __construct(
        ?AIPS_Schedule_Repository_Interface $repository = null,
        $template_repository = null,
        $generator = null,
        ?AIPS_History_Service_Interface $history_service = null,
        $template_type_selector = null,
        ?AIPS_Logger_Interface $logger = null,
        $runner = null,
        $result_handler = null
    ) {
        $container = AIPS_Container::get_instance();
        $this->repository = $repository ?: ($container->has(AIPS_Schedule_Repository_Interface::class) ? $container->make(AIPS_Schedule_Repository_Interface::class) : new AIPS_Schedule_Repository());
        $this->template_repository = $template_repository ?: new AIPS_Template_Repository();
        $this->generator = $generator ?: new AIPS_Generator();
        $this->history_repository = $container->has(AIPS_History_Repository_Interface::class) ? $container->make(AIPS_History_Repository_Interface::class) : new AIPS_History_Repository();
        $this->history_service = $history_service ?: ($container->has(AIPS_History_Service_Interface::class) ? $container->make(AIPS_History_Service_Interface::class) : new AIPS_History_Service($this->history_repository));
        $this->interval_calculator = new AIPS_Interval_Calculator();
        $this->template_type_selector = $template_type_selector ?: new AIPS_Template_Type_Selector();
        $this->logger = $logger ?: ($container->has(AIPS_Logger_Interface::class) ? $container->make(AIPS_Logger_Interface::class) : new AIPS_Logger());
        $this->runner = $runner ?: new AIPS_Generation_Execution_Runner($this->history_service, $this->logger);
        $this->result_handler = $result_handler ?: new AIPS_Schedule_Result_Handler($this->repository, $this->history_service, $this->history_repository, $this->logger);
    }

    /**
     * Set dependencies (useful for testing or late injection).
     */
    public function set_generator($generator) {
        $this->generator = $generator;
    }

    public function set_repository(AIPS_Schedule_Repository_Interface $repository) {
        $this->repository = $repository;
    }

    public function set_template_repository($repository) {
        $this->template_repository = $repository;
    }

    public function set_runner($runner) {
        $this->runner = $runner;
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
     * @param int      $schedule_id      The schedule ID.
     * @param int|null $quantity_override Optional number of posts to generate, overriding the template's post_quantity.
     * @return int|WP_Error Post ID on success, or WP_Error on failure.
     */
    public function process_single_schedule($schedule_id, $quantity_override = null) {
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

        // Generate a correlation ID for this manual run and reset it when done.
        AIPS_Correlation_ID::generate();

        try {
            $result = $this->execute_schedule_logic($schedule_with_template, true, $quantity_override);
        } finally {
            AIPS_Correlation_ID::reset();
        }
        return $result;
    }

    /**
     * Execute a schedule with claim-first locking.
     *
     * LOCKING STRATEGY — claim-first (load-shedding):
     * `next_run` is advanced to the next interval *before* the generation
     * work begins.  This ensures that a second cron worker that overlaps
     * with this one will see a future `next_run` and skip the schedule,
     * preventing duplicate post generation.  The trade-off is intentional:
     * if the process crashes mid-execution the schedule will not retry until
     * the next calculated interval.  For template schedules (which run
     * frequently) missing one run is safer than producing duplicate posts.
     *
     * This asymmetry is deliberate — see generate_post_for_author() in
     * AIPS_Author_Post_Generator for the contrasting "advance-after"
     * strategy used by the coarser per-author schedule.
     *
     * @param object $schedule Schedule object (merged with template data).
     */
    private function execute_schedule_with_lock($schedule) {
        $this->runner->run(
            function() use ($schedule) {
                $original_next_run = $schedule->next_run;

                if ($schedule->frequency === 'once') {
                    // For one-time schedules, "claim" it by pushing next_run forward.
                    // If the process crashes it will be retried in 1 hour.
                    // On success it will be deleted by handle_post_execution_cleanup().
                    $new_next_run = wp_date('Y-m-d H:i:s', (int) current_datetime()->getTimestamp() + HOUR_IN_SECONDS);
                } else {
                    // Calculate next run using original next_run to preserve phase.
                    $new_next_run = $this->interval_calculator->calculate_next_run($schedule->frequency, $original_next_run);
                }

                // Update next_run immediately to lock this schedule from concurrent runs.
                $lock_result = $this->repository->update($schedule->schedule_id, array(
                    'next_run' => $new_next_run
                ));

                if ($lock_result === false) {
                    $this->logger->log('Failed to acquire lock for schedule ' . $schedule->schedule_id, 'error');
                    do_action('aips_scheduler_error', array(
                        'schedule_id'     => $schedule->schedule_id,
                        'template_id'     => $schedule->template_id,
                        'schedule_name'   => !empty($schedule->name) ? $schedule->name : __('Scheduled run', 'ai-post-scheduler'),
                        'error_code'      => 'lock_acquisition_failed',
                        'error_message'   => __('Failed to acquire execution lock for schedule.', 'ai-post-scheduler'),
                        'frequency'       => $schedule->frequency,
                        'creation_method' => 'scheduled',
                        'correlation_id'  => AIPS_Correlation_ID::get(),
                        'url'             => AIPS_Admin_Menu_Helper::get_page_url('schedule'),
                        'dedupe_key'      => 'scheduler_lock_' . absint($schedule->schedule_id),
                        'dedupe_window'   => 900,
                    ));
                    // Early return; the runner's finally block will reset the correlation ID.
                    return;
                }

                $this->execute_schedule_logic($schedule, false);
            },
            'schedule_execution',
            array('schedule_id' => $schedule->schedule_id),
            function(\Throwable $e, $correlation_id) use ($schedule) {
                // Catch-all for unexpected exceptions. The runner has already recorded
                // this to the history service; fire the site-level system error action
                // so operators are notified via the admin bar / notification system.
                do_action('aips_system_error', array(
                    'title'          => __('Schedule processing exception', 'ai-post-scheduler'),
                    'error_code'     => 'schedule_processing_exception',
                    'error_message'  => $e->getMessage(),
                    'schedule_id'    => $schedule->schedule_id,
                    'template_id'    => isset($schedule->template_id) ? $schedule->template_id : 0,
                    'schedule_name'  => !empty($schedule->name) ? $schedule->name : __('Scheduled run', 'ai-post-scheduler'),
                    'correlation_id' => $correlation_id,
                    'url'            => AIPS_Admin_Menu_Helper::get_page_url('schedule'),
                    'dedupe_key'     => 'system_schedule_exception_' . absint($schedule->schedule_id),
                    'dedupe_window'  => 1800,
                ));
            }
        );
    }

    /**
     * Core logic to execute a schedule.
     *
     * @param object   $schedule         Schedule object (merged with template).
     * @param bool     $is_manual        Whether this is a manual execution.
     * @param int|null $quantity_override Optional number of posts to generate, overriding the template's post_quantity.
     * @return int|WP_Error Post ID or WP_Error.
     */
    private function execute_schedule_logic($schedule, $is_manual = false, $quantity_override = null) {
        if (!$is_manual) {
            // Dispatch schedule execution started event
            do_action('aips_schedule_execution_started', $schedule->schedule_id);

            $this->logger->log('Processing schedule: ' . $schedule->schedule_id, 'info', array(
                'template_id' => $schedule->template_id,
                'template_name' => $schedule->name,
                'topic' => isset($schedule->topic) ? $schedule->topic : ''
            ));
        }

        // Explicitly fetch the template to ensure we have the most up-to-date post_quantity
        $actual_template_model = $this->template_repository->get_by_id($schedule->template_id);
        $template_post_quantity = ($actual_template_model && isset($actual_template_model->post_quantity)) ? $actual_template_model->post_quantity : 1;

        // Use caller-supplied override, or fall back to the template's post_quantity, defaulting to 1.
        $raw_quantity = $quantity_override ?? ($template_post_quantity ?? 1);
        $post_quantity = max(1, absint($raw_quantity));

        // Select article structure for this execution
        $article_structure_id = $this->template_type_selector->select_structure($schedule);

        // Prepare context for logging
        $event_type = $is_manual ? 'manual_schedule_started' : 'schedule_executed';
        $log_activity_msg = $is_manual
            ? sprintf(__('Manual execution of schedule "%s" started', 'ai-post-scheduler'), $schedule->name)
            : sprintf(__('Schedule "%s" started execution', 'ai-post-scheduler'), $schedule->name);

        // Load the schedule's persistent lifecycle history container (or create one if missing)
        $history = $this->result_handler->get_or_create_schedule_history($schedule->schedule_id);

        if ($history) {
            $history->record(
                'activity',
                $log_activity_msg,
                array(
                    'event_type' => $event_type,
                    'event_status' => 'success',
                ),
                null,
                array(
                    'schedule_id'    => $schedule->schedule_id,
                    'template_id'    => $schedule->template_id,
                    'frequency'      => $schedule->frequency,
                    'topic'          => isset($schedule->topic) ? $schedule->topic : '',
                    'article_structure_id' => $article_structure_id,
                )
            );
        } else {
            // If the history container could not be created/loaded, avoid fatal errors and log a warning.
            if (isset($this->logger)) {
                $this->logger->log(
                    'Failed to initialize schedule history container.',
                    'warning',
                    array(
                        'schedule_id'    => $schedule->schedule_id,
                        'template_id'    => $schedule->template_id,
                        'frequency'      => $schedule->frequency,
                        'topic'          => isset($schedule->topic) ? $schedule->topic : '',
                        'article_structure_id' => $article_structure_id,
                        'event_type'     => $event_type,
                        'is_manual'      => $is_manual,
                    )
                );
            }
        }

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
        $creation_method = $is_manual ? 'manual' : 'scheduled';
        
        // Create context with creation_method
        $context = new AIPS_Template_Context($template, null, $topic, $creation_method);

        $successful_post_ids = array();
        $errors = array();

        // ── Resumable batch progress ────────────────────────────────────────
        // Determine where to start the loop.  When a previous automated run
        // was interrupted mid-batch, batch_progress holds the last completed
        // state.  We resume from last_index+1 so we never re-generate posts
        // that already exist, and we count previously completed posts toward
        // the total so the batch finishes at the right size.
        //
        // Manual runs always start from index 0 and clear any stale progress
        // cursor left by a previous automated run so the next cron run is
        // not affected by the manual execution.
        $start_index       = 0;
        $prior_completed   = 0;

        if ($is_manual) {
            // Clear any stale progress left by a previous interrupted automated run
            // so the next scheduled execution starts a fresh batch.
            $this->repository->clear_batch_progress($schedule->schedule_id);
        } elseif (!empty($schedule->batch_progress)) {
            $saved = json_decode($schedule->batch_progress, true);
            if (
                is_array($saved) &&
                isset($saved['completed'], $saved['total'], $saved['last_index'])
            ) {
                $saved_completed = (int) $saved['completed'];
                $saved_total     = (int) $saved['total'];
                $saved_last_index = (int) $saved['last_index'];
                $is_valid_cursor = (
                    $saved_total === $post_quantity &&
                    $saved_completed >= 0 &&
                    $saved_completed < $post_quantity &&
                    $saved_last_index >= 0 &&
                    $saved_last_index < $post_quantity &&
                    $saved_last_index >= ($saved_completed - 1) &&
                    $saved_last_index <= $saved_completed
                );

                if ($is_valid_cursor) {
                    // When the cursor includes the IDs of already-generated posts,
                    // use count(post_ids) as the authoritative completed count.
                    // This prevents re-generating the last post if the process
                    // crashed after creation but before the cursor was updated.
                    $saved_post_ids = isset($saved['post_ids']) && is_array($saved['post_ids'])
                        ? array_map('absint', $saved['post_ids'])
                        : array();

                    if (!empty($saved_post_ids)) {
                        // New cursor format: post_ids is the authoritative source.
                        // Even if $saved_completed disagrees (e.g. a crash wrote the
                        // post but not the cursor), count(post_ids) reflects the true
                        // number of already-created posts.
                        $successful_post_ids = $saved_post_ids;
                        $prior_completed     = count($saved_post_ids);
                        $start_index         = count($saved_post_ids);
                    } else {
                        // Legacy cursor (no post_ids): resume from the recorded index.
                        $prior_completed = $saved_completed;
                        $start_index     = $saved_last_index + 1;
                    }
                } else {
                    // Ignore and clear inconsistent saved progress so the
                    // schedule can restart instead of getting stuck on an
                    // impossible resume cursor.
                    $this->repository->clear_batch_progress($schedule->schedule_id);
                }
            } else {
                // Malformed progress payloads should not block future runs.
                $this->repository->clear_batch_progress($schedule->schedule_id);
            }
        }

        for ($i = $start_index; $i < $post_quantity; $i++) {
            $result = $this->generator->generate_post($context);
            if (is_wp_error($result)) {
                $errors[] = $result;
                // Persist the current run state so operators and future
                // circuit-breaker logic can inspect what happened.
                if (!$is_manual) {
                    $completed_so_far = $prior_completed + count($successful_post_ids);
                    $this->repository->update_run_state($schedule->schedule_id, array(
                        'status'        => $completed_so_far > 0 ? 'partial' : 'failed',
                        'error_code'    => $result->get_error_code(),
                        'error_message' => $result->get_error_message(),
                        'completed'     => $completed_so_far,
                        'total'         => $post_quantity,
                        'timestamp'     => gmdate('c'),
                    ));
                }
                // Stop the batch so batch_progress is preserved for resumption.
                break;
            } else {
                $successful_post_ids[] = $result;
                // Persist progress after every successful generation so that a
                // mid-batch crash still records how far we got.  Storing the
                // accumulated post IDs makes the cursor atomic with creation:
                // if a crash occurs after the post is created but before this
                // write lands, the next run uses count(post_ids) as the start
                // index instead of last_index+1, preventing duplicate posts.
                if (!$is_manual && $post_quantity > 1) {
                    $completed_so_far = $prior_completed + count($successful_post_ids);
                    $this->repository->update_batch_progress(
                        $schedule->schedule_id,
                        $completed_so_far,
                        $post_quantity,
                        $i,
                        $successful_post_ids
                    );
                }
            }
        }

        // Determine whether the full batch finished without any errors.
        $total_completed = $prior_completed + count($successful_post_ids);
        $batch_finished  = empty($errors) && $total_completed >= $post_quantity;

        if (!$is_manual) {
            if ($batch_finished) {
                // All posts generated — clear batch progress and persist a
                // success run_state to indicate a clean completion.
                $this->repository->clear_batch_progress($schedule->schedule_id);
                $this->repository->update_run_state($schedule->schedule_id, array(
                    'status'    => 'success',
                    'completed' => $total_completed,
                    'total'     => $post_quantity,
                    'timestamp' => gmdate('c'),
                ));
            }
        }

        // ── Build the overall result ─────────────────────────────────────────
        // A partial batch (some posts generated, then one failed) is treated as
        // incomplete — NOT as a success.  This prevents one-time schedules from
        // being deleted and recurring schedules from being logged as successful
        // when only a subset of the requested posts were produced.
        $manual_success = $is_manual && !empty($successful_post_ids) && empty($errors);

        if ($batch_finished || $manual_success) {
            // Full success (all posts generated) or manual run with no errors.
            $overall_result = $successful_post_ids;
        } elseif (!empty($errors)) {
            // Batch failed or was partially interrupted — surface the error.
            // Previously generated $successful_post_ids are preserved in the DB;
            // batch_progress holds the resumption cursor for the next run.
            if (!empty($successful_post_ids)) {
                // Partial: some posts were created before the error.
                // Return a WP_Error that carries context about the partial success
                // so handle_execution_failure can log it properly.
                $overall_result = new WP_Error(
                    'batch_partially_failed',
                    sprintf(
                        /* translators: 1: completed count, 2: total requested, 3: original error */
                        __('%1$d of %2$d posts generated before error: %3$s', 'ai-post-scheduler'),
                        $total_completed,
                        $post_quantity,
                        $errors[0]->get_error_message()
                    )
                );
            } else {
                // Nothing generated — return the original error verbatim.
                $overall_result = $errors[0];
            }
        } else {
            $overall_result = new WP_Error('no_posts_generated', __('No posts were generated.', 'ai-post-scheduler'));
        }

        // Handle Post-Execution Logic (Cleanup/Updates)
        if (!$is_manual) {
            $this->result_handler->handle_post_execution_cleanup($schedule, $overall_result);
        } else {
            $this->template_type_selector->invalidate_count_cache($schedule->schedule_id);

            // For successful manual runs, record last_run so the Schedules page
            // reflects the execution instead of staying frozen on "Past due".
            // For once-schedules, also deactivate: next_run is still in the past
            // so the cron would otherwise fire it again on the next trigger.
            if (!is_wp_error($overall_result)) {
                $this->repository->update_last_run($schedule->schedule_id, current_time('mysql'));
                if ($schedule->frequency === 'once') {
                    $this->repository->update($schedule->schedule_id, array(
                        'is_active' => 0,
                        'status'    => 'completed',
                    ));
                }
            }
        }

        // Handle Logging and Events based on Result
        if (is_wp_error($overall_result)) {
            $this->result_handler->handle_execution_failure($schedule, $overall_result, $history, $is_manual);
        } else {
            $this->result_handler->handle_execution_success($schedule, $overall_result, $history, $is_manual);
        }

        return $overall_result;
    }

}
