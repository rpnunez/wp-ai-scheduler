<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class AIPS_Schedule_Controller
 *
 * Coordinates schedule AJAX requests by delegating to dedicated query, mutation,
 * and job execution handlers.
 *
 * @package AI_Post_Scheduler
 */
class AIPS_Schedule_Controller {

    /**
     * @var AIPS_Scheduler
     */
    private $scheduler;

    /**
     * @var AIPS_Schedule_Repository_Interface
     */
    private $schedule_repository;

    /**
     * @var AIPS_History_Repository_Interface
     */
    private $history_repository;

    /**
     * @var AIPS_Schedule_Query_Handler
     */
    private $query_handler;

    /**
     * @var AIPS_Schedule_Mutation_Handler
     */
    private $mutation_handler;

    /**
     * @var AIPS_Schedule_Job_Handler
     */
    private $job_handler;

    public function __construct($scheduler = null, ?AIPS_Schedule_Repository_Interface $schedule_repository = null, ?AIPS_History_Repository_Interface $history_repository = null) {
        $container = AIPS_Container::get_instance();
        $this->scheduler           = $scheduler ?: $container->makeIfExists(AIPS_Scheduler::class, function() {
            return new AIPS_Scheduler();
        });
        $this->schedule_repository = $schedule_repository ?: $container->makeIfExists(AIPS_Schedule_Repository_Interface::class, function() {
            return new AIPS_Schedule_Repository();
        });
        $this->history_repository  = $history_repository ?: $container->makeIfExists(AIPS_History_Repository_Interface::class, function() {
            return new AIPS_History_Repository();
        });

        $this->query_handler    = new AIPS_Schedule_Query_Handler($this->schedule_repository, $this->history_repository);
        $this->mutation_handler = new AIPS_Schedule_Mutation_Handler($this->scheduler, $this->schedule_repository);
        $this->job_handler      = new AIPS_Schedule_Job_Handler($this->scheduler, $this->schedule_repository, $this->history_repository);

        // Canonical hook registration
        AIPS_Ajax_Registry::register_controller_actions($this);
    }

    // --- Query endpoints ---

    public function ajax_get_schedule_status_read_model() {
        return $this->query_handler->get_schedule_status_read_model();
    }

    public function ajax_get_schedules_post_count() {
        return $this->query_handler->get_schedules_post_count();
    }

    public function ajax_get_schedule_history() {
        return $this->query_handler->get_schedule_history();
    }

    public function ajax_get_unified_schedule_history() {
        return $this->query_handler->get_unified_schedule_history();
    }

    // --- Mutation endpoints ---

    public function ajax_save_schedule() {
        return $this->mutation_handler->save_schedule();
    }

    public function ajax_delete_schedule() {
        return $this->mutation_handler->delete_schedule();
    }

    public function ajax_toggle_schedule() {
        return $this->mutation_handler->toggle_schedule();
    }

    public function ajax_bulk_delete_schedules() {
        return $this->mutation_handler->bulk_delete_schedules();
    }

    public function ajax_bulk_toggle_schedules() {
        return $this->mutation_handler->bulk_toggle_schedules();
    }

    public function ajax_unified_toggle() {
        return $this->mutation_handler->unified_toggle();
    }

    public function ajax_unified_bulk_toggle() {
        return $this->mutation_handler->unified_bulk_toggle();
    }

    public function ajax_unified_bulk_delete() {
        return $this->mutation_handler->unified_bulk_delete();
    }

    public function ajax_reset_schedule_circuit() {
        return $this->mutation_handler->reset_schedule_circuit();
    }

    // --- Job endpoints ---

    public function ajax_run_now() {
        return $this->job_handler->run_now();
    }

    public function ajax_bulk_run_now_schedules() {
        return $this->job_handler->bulk_run_now_schedules();
    }

    public function ajax_unified_run_now() {
        return $this->job_handler->unified_run_now();
    }

    public function ajax_unified_bulk_run_now() {
        return $this->job_handler->unified_bulk_run_now();
    }

    public function ajax_resume_schedule_batch() {
        return $this->job_handler->resume_schedule_batch();
    }
}
