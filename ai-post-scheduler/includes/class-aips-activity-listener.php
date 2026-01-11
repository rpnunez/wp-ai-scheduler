<?php
/**
 * Activity Listener
 *
 * Listens to schedule execution events and logs them to the Activity Repository.
 * Decouples the Scheduler from the Activity Logging concerns.
 *
 * @package AI_Post_Scheduler
 * @since 1.6.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class AIPS_Activity_Listener {

    /**
     * @var AIPS_Activity_Repository
     */
    private $repository;

    /**
     * @var AIPS_Schedule_Repository
     */
    private $schedule_repository;

    /**
     * @var AIPS_Template_Repository
     */
    private $template_repository;

    public function __construct() {
        $this->repository = new AIPS_Activity_Repository();
        $this->schedule_repository = new AIPS_Schedule_Repository();
        $this->template_repository = new AIPS_Template_Repository();

        add_action('aips_schedule_execution_failed', array($this, 'on_schedule_failed'), 10, 2);
        add_action('aips_schedule_execution_completed', array($this, 'on_schedule_completed'), 10, 2);
    }

    /**
     * Handle schedule execution failure.
     *
     * @param int    $schedule_id
     * @param string $error_message
     */
    public function on_schedule_failed($schedule_id, $error_message) {
        $schedule = $this->schedule_repository->get_by_id($schedule_id);

        if (!$schedule) {
            return;
        }

        $template = $this->template_repository->get($schedule->template_id);
        $template_name = $template ? $template->name : __('Unknown Template', 'ai-post-scheduler');

        // Check if it was a one-time schedule that was deactivated (special case logic usually in Scheduler,
        // but we can infer or genericize the message here).
        // For now, we just log the failure.

        $this->repository->create(array(
            'event_type' => 'schedule_failed',
            'event_status' => 'failed',
            'schedule_id' => $schedule_id,
            'template_id' => $schedule->template_id,
            'message' => sprintf(
                __('Schedule "%s" failed to generate post', 'ai-post-scheduler'),
                $template_name
            ),
            'metadata' => array(
                'error' => $error_message,
                'frequency' => $schedule->frequency,
            ),
        ));
    }

    /**
     * Handle schedule execution completion.
     *
     * @param int $schedule_id
     * @param int $post_id
     */
    public function on_schedule_completed($schedule_id, $post_id) {
        $schedule = $this->schedule_repository->get_by_id($schedule_id);

        if (!$schedule) {
            return;
        }

        $template = $this->template_repository->get($schedule->template_id);
        $template_name = $template ? $template->name : __('Unknown Template', 'ai-post-scheduler');

        $post = get_post($post_id);
        if ($post) {
            $event_status = ($post->post_status === 'draft') ? 'draft' : 'success';
            $event_type = ($post->post_status === 'draft') ? 'post_draft' : 'post_published';

            $this->repository->create(array(
                'event_type' => $event_type,
                'event_status' => $event_status,
                'schedule_id' => $schedule_id,
                'post_id' => $post_id,
                'template_id' => $schedule->template_id,
                'message' => sprintf(
                    __('%s created by schedule "%s": %s', 'ai-post-scheduler'),
                    ($post->post_status === 'draft') ? __('Draft', 'ai-post-scheduler') : __('Post', 'ai-post-scheduler'),
                    $template_name,
                    $post->post_title
                ),
                'metadata' => array(
                    'post_status' => $post->post_status,
                    'frequency' => $schedule->frequency,
                ),
            ));
        }
    }
}
