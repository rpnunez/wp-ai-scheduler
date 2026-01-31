<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class AIPS_Dashboard_Controller
 *
 * Handles the rendering of the dashboard page.
 *
 * @package AI_Post_Scheduler
 */
class AIPS_Dashboard_Controller {

    /**
     * Render the main dashboard page.
     *
     * Fetches statistics and recent activity from the database to display
     * on the dashboard template.
     *
     * @return void
     */
    public function render_page() {
        // Use repositories instead of direct SQL
        $history_repo = new AIPS_History_Repository();
        $schedule_repo = new AIPS_Schedule_Repository();
        $template_repo = new AIPS_Template_Repository();

        // Get stats
        $history_stats = $history_repo->get_stats();
        $schedule_counts = $schedule_repo->count_by_status();
        $template_counts = $template_repo->count_by_status();

        $total_generated = $history_stats['completed'];
        $pending_scheduled = $schedule_counts['active'];
        $total_templates = $template_counts['active'];
        $failed_count = $history_stats['failed'];

        // Get recent history
        $recent_posts_data = $history_repo->get_history(array('per_page' => 5));
        $recent_posts = $recent_posts_data['items'];

        // Get upcoming schedules
        $upcoming = $schedule_repo->get_upcoming(5);

        include AIPS_PLUGIN_DIR . 'templates/admin/dashboard.php';
    }
}
