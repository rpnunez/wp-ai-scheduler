<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * AIPS_Dashboard_Controller
 *
 * Handles the rendering and logic for the plugin dashboard page.
 * Separates view rendering from settings registration.
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
        $container = AIPS_Container::get_instance();

        // Use container for registered services
        $history_repo = $container->make(AIPS_History_Repository_Interface::class);
        $schedule_repo = $container->make(AIPS_Schedule_Repository_Interface::class);
        $template_repo = $container->make(AIPS_Template_Repository::class);

        // Repository classes (not in container)
        $post_review_repo = new AIPS_Post_Review_Repository();
        $author_topics_repo = new AIPS_Author_Topics_Repository();

        // Get stats
        $history_stats = $history_repo->get_stats();
        $schedule_counts = $schedule_repo->count_by_status();
        $template_counts = $template_repo->count_by_status();
        $topic_counts = $author_topics_repo->get_global_status_counts();

        $total_generated = $history_stats['completed'];
        $pending_scheduled = $schedule_counts['active'];
        $total_templates = $template_counts['active'];
        $failed_count = $history_stats['failed'];
        $partial_generations = $history_repo->get_partial_generations(array('per_page' => -1))['total'] ?? 0;
        $pending_reviews = $post_review_repo->get_draft_count();
        $topics_in_queue = isset($topic_counts['approved']) ? $topic_counts['approved'] : 0;

        // Get recent history
        $recent_posts_data = $history_repo->get_history(array(
            'per_page' => 5,
            'fields'   => 'list' // Optimize payload by excluding longtext fields
        ));
        $recent_posts = $recent_posts_data['items'];

        // Get upcoming schedules
        $upcoming = $schedule_repo->get_upcoming(5);

        include AIPS_PLUGIN_DIR . 'templates/admin/dashboard.php';
    }
}
