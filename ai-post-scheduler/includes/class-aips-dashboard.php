<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class AIPS_Dashboard
 *
 * Handles the rendering and logic for the main dashboard page.
 *
 * @package AI_Post_Scheduler
 */
class AIPS_Dashboard {

    /**
     * @var AIPS_History_Repository
     */
    private $history_repo;

    /**
     * @var AIPS_Schedule_Repository
     */
    private $schedule_repo;

    /**
     * @var AIPS_Template_Repository
     */
    private $template_repo;

    /**
     * Initialize the dashboard handler.
     *
     * @param AIPS_History_Repository|null  $history_repo
     * @param AIPS_Schedule_Repository|null $schedule_repo
     * @param AIPS_Template_Repository|null $template_repo
     */
    public function __construct($history_repo = null, $schedule_repo = null, $template_repo = null) {
        $this->history_repo = $history_repo ?: new AIPS_History_Repository();
        $this->schedule_repo = $schedule_repo ?: new AIPS_Schedule_Repository();
        $this->template_repo = $template_repo ?: new AIPS_Template_Repository();
    }

    /**
     * Render the main dashboard page.
     *
     * Fetches statistics and recent activity from the database to display
     * on the dashboard template.
     *
     * @return void
     */
    public function render_page() {
        // Get stats
        $history_stats = $this->history_repo->get_stats();
        $schedule_counts = $this->schedule_repo->count_by_status();
        $template_counts = $this->template_repo->count_by_status();

        $total_generated = $history_stats['completed'];
        $pending_scheduled = $schedule_counts['active'];
        $total_templates = $template_counts['active'];
        $failed_count = $history_stats['failed'];

        // Get recent history
        $recent_posts_data = $this->history_repo->get_history(array('per_page' => 5));
        $recent_posts = $recent_posts_data['items'];

        // Get upcoming schedules
        $upcoming = $this->schedule_repo->get_upcoming(5);

        include AIPS_PLUGIN_DIR . 'templates/admin/dashboard.php';
    }
}
