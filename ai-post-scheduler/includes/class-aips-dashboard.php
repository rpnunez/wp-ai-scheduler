<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class AIPS_Dashboard
 *
 * Handles the rendering of the dashboard page.
 *
 * @package AI_Post_Scheduler
 */
class AIPS_Dashboard {

    /**
     * @var AIPS_History_Repository
     */
    private $history_repository;

    /**
     * @var AIPS_Schedule_Repository
     */
    private $schedule_repository;

    /**
     * @var AIPS_Template_Repository
     */
    private $template_repository;

    /**
     * @var wpdb
     */
    private $wpdb;

    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->history_repository = new AIPS_History_Repository();
        $this->schedule_repository = new AIPS_Schedule_Repository();
        $this->template_repository = new AIPS_Template_Repository();
    }

    /**
     * Render the main dashboard page.
     *
     * Fetches statistics and recent activity from repositories to display
     * on the dashboard template.
     *
     * @return void
     */
    public function render_page() {
        $stats = $this->history_repository->get_stats();

        $total_generated = $stats['completed'];
        $failed_count = $stats['failed'];

        $schedule_stats = $this->schedule_repository->count_by_status();
        $pending_scheduled = $schedule_stats['active'];

        $template_stats = $this->template_repository->count_by_status();
        $total_templates = $template_stats['active'];

        $recent_posts = $this->history_repository->get_recent(5);
        $upcoming = $this->schedule_repository->get_upcoming(5);

        // Ensure variables expected by the template are available
        // The template expects:
        // $total_generated, $pending_scheduled, $total_templates, $failed_count, $recent_posts, $upcoming

        include AIPS_PLUGIN_DIR . 'templates/admin/dashboard.php';
    }
}
