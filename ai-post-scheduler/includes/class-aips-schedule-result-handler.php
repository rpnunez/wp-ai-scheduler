<?php
/**
 * AIPS_Schedule_Result_Handler
 *
 * Handles post-execution logic for scheduled post generations:
 * cleanup, failure logging, success logging, and history container logic.
 *
 * @package AI_Post_Scheduler
 */

if (!defined('ABSPATH')) {
    exit;
}

class AIPS_Schedule_Result_Handler {

    /**
     * @var AIPS_Schedule_Repository_Interface
     */
    private $repository;

    /**
     * @var AIPS_History_Service_Interface
     */
    private $history_service;

    /**
     * @var AIPS_History_Repository_Interface
     */
    private $history_repository;

    /**
     * @var AIPS_Logger_Interface
     */
    private $logger;

    /**
     * Constructor.
     *
     * @param AIPS_Schedule_Repository_Interface $repository
     * @param AIPS_History_Service_Interface $history_service
     * @param AIPS_History_Repository_Interface $history_repository
     * @param AIPS_Logger_Interface $logger
     */
    public function __construct(
        $repository,
        $history_service,
        $history_repository,
        $logger
    ) {
        $this->repository = $repository;
        $this->history_service = $history_service;
        $this->history_repository = $history_repository;
        $this->logger = $logger;
    }

    /**
     * Handle cleanup after automated execution (delete one-time, update recurring).
     *
     * @param object $schedule
     * @param mixed  $result Array of post IDs on success, WP_Error on failure.
     */
    public function handle_post_execution_cleanup($schedule, $result) {
        $is_success = !is_wp_error($result);

        if ($schedule->frequency === 'once') {
            if ($is_success) {
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

                // Log to the schedule's persistent lifecycle history container
                $fail_history = $this->get_or_create_schedule_history($schedule->schedule_id);
                if ($fail_history) {
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
                            'error' => $result->get_error_message(),
                            'frequency' => $schedule->frequency,
                        )
                    );
                }
            }
        } else {
            // For recurring schedules, we ONLY update last_run here.
            // next_run was already updated at the start (Claim-First).
            $this->repository->update_last_run($schedule->schedule_id, current_time('mysql'));
        }
    }

    /**
     * Handle failure logging.
     *
     * @param object $schedule
     * @param WP_Error $result
     * @param object $history
     * @param bool $is_manual
     */
    public function handle_execution_failure($schedule, $result, $history, $is_manual) {
        $error_msg = $result->get_error_message();
        $history_id = (is_object($history) && method_exists($history, 'get_id')) ? $history->get_id() : 0;

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
            do_action('aips_scheduler_error', array(
                'schedule_id'    => $schedule->schedule_id,
                'template_id'    => $schedule->template_id,
                'schedule_name'  => $schedule->name,
                'error_code'     => $result->get_error_code(),
                'error_message'  => $error_msg,
                'frequency'      => $schedule->frequency,
                'history_id'     => $history_id,
                'correlation_id' => class_exists('AIPS_Correlation_ID') ? AIPS_Correlation_ID::get() : '',
                'creation_method'=> 'scheduled',
                'url'            => class_exists('AIPS_Admin_Menu_Helper') ? AIPS_Admin_Menu_Helper::get_page_url('schedule') : '',
                'dedupe_key'     => 'scheduler_failure_' . absint($schedule->schedule_id) . '_' . sanitize_key($result->get_error_code()),
                'dedupe_window'  => 900,
            ));

            // Dispatch schedule execution failed event
            do_action('aips_schedule_execution_failed', $schedule->schedule_id, $error_msg);
        }
    }

    /**
     * Handle success logging.
     *
     * @param object $schedule
     * @param mixed $result
     * @param object $history
     * @param bool $is_manual
     */
    public function handle_execution_success($schedule, $result, $history, $is_manual) {
        // Handle $result as an array of post IDs (or a single ID for safety/legacy callers)
        $post_ids = is_array($result) ? $result : array($result);

        $this->logger->log('Schedule completed successfully', 'info', array(
            'schedule_id' => $schedule->schedule_id,
            'post_ids' => $post_ids
        ));

        // For logging, we'll base the status on the first post generated, or summarize
        $first_post_id = !empty($post_ids) ? $post_ids[0] : 0;
        $post = get_post($first_post_id);

        if ($post) {
            $event_status = ($post->post_status === 'draft') ? 'draft' : 'success';
            $event_type = ($post->post_status === 'draft') ? 'post_draft' : 'post_published';

            if ($is_manual) {
                $event_type = 'manual_schedule_completed';
                $event_status = 'success';
            }

            $post_title_summary = $post->post_title;
            if (count($post_ids) > 1) {
                $post_title_summary .= ' ' . sprintf(__('(and %d more)', 'ai-post-scheduler'), count($post_ids) - 1);
            }

            // Update the history record
            $history->record(
                'activity',
                sprintf(
                    __('%s created by schedule "%s": %s', 'ai-post-scheduler'),
                    (count($post_ids) > 1) ? __('Posts', 'ai-post-scheduler') : (($post->post_status === 'draft') ? __('Draft', 'ai-post-scheduler') : __('Post', 'ai-post-scheduler')),
                    $schedule->name,
                    $post_title_summary
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
            do_action('aips_schedule_execution_completed', $schedule->schedule_id, $result, $schedule);
        }
    }

    /**
     * Load the schedule's persistent lifecycle history container, or create one if missing.
     *
     * @param int $schedule_id Schedule ID.
     * @return AIPS_History_Container|null Container instance or null on failure.
     */
    public function get_or_create_schedule_history($schedule_id) {
        $schedule = $this->repository->get_by_id($schedule_id);

        if (!$schedule) {
            return null;
        }

        if (!empty($schedule->schedule_history_id)) {
            $container = class_exists('AIPS_History_Container') ? AIPS_History_Container::load_existing($this->history_repository, $schedule->schedule_history_id) : null;
            if ($container) {
                return $container;
            }
        }

        // No existing container — create one and attach it to the schedule
        $container = $this->history_service->create('schedule_lifecycle', array(
            'schedule_id'     => $schedule_id,
            'creation_method' => 'schedule_lifecycle',
        ));

        if ($container && $container->get_id()) {
            $this->repository->update($schedule_id, array(
                'schedule_history_id' => $container->get_id(),
            ));
        }

        return $container;
    }
}
