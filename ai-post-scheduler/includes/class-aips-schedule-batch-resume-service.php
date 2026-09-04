<?php
/**
 * Schedule batch resume service.
 *
 * Re-dispatches large-batch schedule runs that were stopped part-way through
 * by the "Prevent AI Generation" setting.
 *
 * @package AI_Post_Scheduler
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class AIPS_Schedule_Batch_Resume_Service
 *
 * When a large batch is terminated mid-flight, the slice that was running
 * writes a resume cursor into the schedule's run_state: the terminal status,
 * a `resumable` flag, and the absolute index the next slice should start at.
 * Every remaining slice already queued for that run then no-ops, because
 * AIPS_Schedule_Processor treats the terminated run_state as terminal.
 *
 * This service reads those cursors back and dispatches the remaining posts as
 * a fresh set of slices, offset so the schedule finishes at its original total
 * rather than generating the whole batch again.
 *
 * @since 3.5.0
 */
class AIPS_Schedule_Batch_Resume_Service {

    /**
     * @var AIPS_Schedule_Repository_Interface
     */
    private $repository;

    /**
     * @var AIPS_Batch_Queue_Service
     */
    private $batch_service;

    /**
     * @var AIPS_Schedule_Result_Handler
     */
    private $result_handler;

    /**
     * @var AIPS_Logger_Interface
     */
    private $logger;

    /**
     * @var AIPS_Config
     */
    private $config;

    /**
     * @param AIPS_Schedule_Repository_Interface $repository
     * @param AIPS_Batch_Queue_Service           $batch_service
     * @param AIPS_Schedule_Result_Handler       $result_handler
     * @param AIPS_Logger_Interface              $logger
     * @param AIPS_Config|null                   $config
     */
    public function __construct(
        $repository,
        $batch_service,
        $result_handler,
        $logger,
        $config = null
    ) {
        $this->repository     = $repository;
        $this->batch_service  = $batch_service;
        $this->result_handler = $result_handler;
        $this->logger         = $logger;
        $this->config         = $config ?: AIPS_Config::get_instance();
    }

    /**
     * Resume every schedule holding a resumable batch cursor.
     *
     * @return array{resumed: int, finished: int, skipped: int, failed: int}
     */
    public function resume_all() {
        $summary = array(
            'resumed'  => 0,
            'finished' => 0,
            'skipped'  => 0,
            'failed'   => 0,
        );

        // Defensive: never re-dispatch while the setting that stopped these runs
        // is still enabled, or the new slices would terminate immediately and
        // overwrite their own resume cursors.
        if ($this->config->is_scheduled_ai_generation_prevented()) {
            $this->logger->log(
                'Batch resume: skipped because AI generation is still prevented.',
                'info'
            );

            return $summary;
        }

        // Merge schedules that have run_state or batch_progress into a unique set
        $schedules_by_id = array();

        $with_run_state = $this->repository->get_schedules_with_run_state();
        foreach ($with_run_state as $schedule) {
            $schedules_by_id[(int) $schedule->id] = $schedule;
        }

        $with_bp = array();
        if (method_exists($this->repository, 'get_schedules_with_batch_progress')) {
            $with_bp = $this->repository->get_schedules_with_batch_progress();
        }
        foreach ($with_bp as $schedule) {
            $schedules_by_id[(int) $schedule->id] = $schedule;
        }

        // Additional diagnostics counters
        $metrics = array(
            'scanned' => 0,
            'skipped_invalid' => 0,
            'synthesized_from_batch_progress' => 0,
        );

        foreach ($schedules_by_id as $schedule) {
            $metrics['scanned']++;
            $schedule_id = isset($schedule->id) ? (int) $schedule->id : 0;

            $run_state = $this->decode_run_state($schedule);

            // If run_state isn't resumable, try to build a resume cursor from
            // the batch_progress payload as a fallback. Validate batch_progress
            // carefully before trusting it.
            $is_resumable = $this->is_resumable($run_state);

            if (!$is_resumable && !empty($schedule->batch_progress)) {
                $bp_raw = $schedule->batch_progress;
                $bp = json_decode($bp_raw, true);

                if (!is_array($bp)) {
                    $this->logger->log(
                        'Batch resume: schedule ' . $schedule_id . ' has invalid batch_progress JSON; skipping.',
                        'warning',
                        array('schedule_id' => $schedule_id)
                    );
                    $metrics['skipped_invalid']++;
                } else {
                    // Validate fields
                    $has_total = isset($bp['total']);
                    $has_completed = isset($bp['completed']);

                    if (!$has_total || !$has_completed) {
                        $this->logger->log(
                            'Batch resume: schedule ' . $schedule_id . ' batch_progress missing required fields; skipping.',
                            'warning',
                            array('schedule_id' => $schedule_id, 'batch_progress' => $bp)
                        );
                        $metrics['skipped_invalid']++;
                    } else {
                        $completed = max(0, (int) $bp['completed']);
                        $total = max(0, (int) $bp['total']);

                        // Basic invariants
                        if ($total < 1 || $completed < 0 || $completed > $total) {
                            $this->logger->log(
                                'Batch resume: schedule ' . $schedule_id . ' batch_progress has invalid numeric values; skipping.',
                                'warning',
                                array('schedule_id' => $schedule_id, 'completed' => $completed, 'total' => $total)
                            );
                            $metrics['skipped_invalid']++;
                        } else {
                            // If post_ids is present, sanity-check its length
                            if (isset($bp['post_ids']) && is_array($bp['post_ids'])) {
                                $post_ids_count = count($bp['post_ids']);
                                if ($post_ids_count !== $completed) {
                                    $this->logger->log(
                                        'Batch resume: schedule ' . $schedule_id . ' batch_progress post_ids length mismatch with completed count.',
                                        'warning',
                                        array('schedule_id' => $schedule_id, 'post_ids_count' => $post_ids_count, 'completed' => $completed)
                                    );
                                    // Not fatal — proceed but record diagnostic
                                }
                            }

                            if ($total > 0 && $completed < $total) {
                                // Construct a synthetic run_state that indicates the run was
                                // terminated and is resumable. This lets existing resume
                                // logic handle dispatching the remaining slices.
                                $run_state = array(
                                    'resumable' => true,
                                    'status' => AIPS_History_Event_Status::TERMINATED,
                                    'resume_index' => $completed,
                                    'total' => $total,
                                    'correlation_id' => isset($bp['correlation_id']) ? (string) $bp['correlation_id'] : (string) AIPS_Correlation_ID::get(),
                                );

                                $is_resumable = true;
                                $metrics['synthesized_from_batch_progress']++;

                                $this->logger->log(
                                    'Batch resume: schedule ' . $schedule_id . ' synthesized resumable cursor from batch_progress.',
                                    'info',
                                    array('schedule_id' => $schedule_id, 'completed' => $completed, 'total' => $total)
                                );
                            }
                        }
                    }
                }
            }

            if (!$is_resumable) {
                $summary['skipped']++;
                continue;
            }

            $outcome = $this->resume_schedule($schedule, $run_state);
            $summary[$outcome]++;
        }

        // Emit final metrics alongside the summary to aid diagnosing resume behaviour.
        $this->logger->log(
            sprintf(
                'Batch resume summary: resumed=%d finished=%d skipped=%d failed=%d scanned=%d synthesized=%d skipped_invalid=%d',
                $summary['resumed'],
                $summary['finished'],
                $summary['skipped'],
                $summary['failed'],
                $metrics['scanned'],
                $metrics['synthesized_from_batch_progress'],
                $metrics['skipped_invalid']
            ),
            'info'
        );

        if ($summary['resumed'] > 0 || $summary['finished'] > 0) {
            $this->logger->log(
                sprintf(
                    'Batch resume: %d schedule(s) resumed, %d already complete, %d skipped, %d failed.',
                    $summary['resumed'],
                    $summary['finished'],
                    $summary['skipped'],
                    $summary['failed']
                ),
                'info'
            );
        }

        return $summary;
    }

    /**
     * Resume a single schedule's interrupted batch.
     *
     * @param object $schedule  Schedule row.
     * @param array  $run_state Decoded run_state payload.
     * @return string One of 'resumed', 'finished', 'skipped', 'failed'.
     */
    private function resume_schedule($schedule, array $run_state) {
        $schedule_id = (int) $schedule->id;
        $total       = isset($run_state['total']) ? max(0, (int) $run_state['total']) : 0;
        $completed   = isset($run_state['resume_index']) ? max(0, (int) $run_state['resume_index']) : 0;
        $remaining   = $total - $completed;

        if ($total < 1) {
            $this->logger->log(
                'Batch resume: schedule ' . $schedule_id . ' has no recorded total — clearing resume cursor.',
                'warning'
            );
            $this->clear_cursor($schedule_id, $run_state, 'failed');

            return 'skipped';
        }

        if ($remaining < 1) {
            // The cursor already covers the full batch; nothing left to generate.
            $this->clear_cursor($schedule_id, $run_state, 'success');

            return 'finished';
        }

        $correlation_id = isset($run_state['correlation_id']) && $run_state['correlation_id'] !== ''
            ? (string) $run_state['correlation_id']
            : (string) AIPS_Correlation_ID::get();

        // The original run's remaining slices are still queued. They no-op only
        // while run_state stays terminal, and this method is about to clear that,
        // so they must go before the replacement slices are dispatched.
        $this->batch_service->clear_pending_slices($schedule_id);

        $dispatch_summary = $this->batch_service->dispatch(
            $schedule_id,
            $remaining,
            AIPS_DateTime::now()->timestamp(),
            $correlation_id,
            array(
                'index_offset'   => $completed,
                'total_override' => $total,
            )
        );

        if (is_wp_error($dispatch_summary)) {
            $this->logger->log(
                'Batch resume: dispatch failed for schedule ' . $schedule_id . ' — ' . $dispatch_summary->get_error_message(),
                'error',
                array('schedule_id' => $schedule_id)
            );

            return 'failed';
        }

        // Clear the terminal status so the newly queued slices are allowed to run.
        $this->repository->update_run_state($schedule_id, array(
            'status'         => 'batch_processing',
            'completed'      => $completed,
            'total'          => $total,
            'dispatched_at'  => AIPS_DateTime::now()->timestamp(),
            'correlation_id' => $correlation_id,
            'timestamp'      => AIPS_DateTime::now()->toIso8601(),
        ));

        $message = sprintf(
            /* translators: 1: number of posts remaining, 2: total posts in the batch. */
            __('Resumed terminated batch: %1$d of %2$d posts remaining.', 'ai-post-scheduler'),
            $remaining,
            $total
        );

        $this->logger->log(
            'Batch resume: schedule ' . $schedule_id . ' — ' . $message,
            'info',
            array('schedule_id' => $schedule_id)
        );

        $history = $this->result_handler->get_or_create_schedule_history($schedule_id);

        if ($history) {
            $history->record(
                'activity',
                $message,
                array(
                    'event_type'   => AIPS_History_Event_Type::BATCH_RESUMED,
                    'event_status' => AIPS_History_Event_Status::RUNNING,
                ),
                null,
                array(
                    'schedule_id'   => $schedule_id,
                    'template_id'   => isset($schedule->template_id) ? (int) $schedule->template_id : 0,
                    'completed'     => $completed,
                    'total'         => $total,
                    'remaining'     => $remaining,
                    'correlation_id' => $correlation_id,
                )
            );
        }

        return 'resumed';
    }

    /**
     * Drop the resume cursor from a run_state without re-dispatching.
     *
     * @param int    $schedule_id Schedule ID.
     * @param array  $run_state   Decoded run_state payload.
     * @param string $status      Terminal status to leave behind.
     * @return void
     */
    private function clear_cursor($schedule_id, array $run_state, $status) {
        unset($run_state['resumable'], $run_state['resume_index']);

        $run_state['status']    = $status;
        $run_state['timestamp'] = AIPS_DateTime::now()->toIso8601();

        $this->repository->update_run_state($schedule_id, $run_state);
    }

    /**
     * Determine whether a run_state payload carries a usable resume cursor.
     *
     * @param array $run_state Decoded run_state payload.
     * @return bool
     */
    private function is_resumable(array $run_state) {
        return !empty($run_state['resumable'])
            && isset($run_state['status'])
            && $run_state['status'] === AIPS_History_Event_Status::TERMINATED;
    }

    /**
     * Decode a schedule row's run_state JSON.
     *
     * @param object $schedule Schedule row.
     * @return array
     */
    private function decode_run_state($schedule) {
        if (empty($schedule->run_state)) {
            return array();
        }

        $decoded = json_decode($schedule->run_state, true);

        return is_array($decoded) ? $decoded : array();
    }
}
