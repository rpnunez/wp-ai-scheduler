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

    /** Number of days used for chart history. */
    const CHART_DAYS = 14;

    /**
     * Render the main dashboard page.
     *
     * Fetches statistics, recent activity, upcoming schedule data, and chart
     * series data from the database to pass to the dashboard template.
     *
     * @return void
     */
    public function render_page() {
        // Use repositories instead of direct SQL
        $history_repo        = new AIPS_History_Repository();
        $schedule_repo       = new AIPS_Schedule_Repository();
        $template_repo       = new AIPS_Template_Repository();
        $post_review_repo    = new AIPS_Post_Review_Repository();
        $author_topics_repo  = new AIPS_Author_Topics_Repository();

        // Get stats
        $history_stats    = $history_repo->get_stats();
        $schedule_counts  = $schedule_repo->count_by_status();
        $template_counts  = $template_repo->count_by_status();
        $topic_counts     = $author_topics_repo->get_global_status_counts();

        $total_generated    = $history_stats['completed'];
        $pending_scheduled  = $schedule_counts['active'];
        $total_templates    = $template_counts['active'];
        $failed_count       = $history_stats['failed'];
        $partial_generations = $history_repo->get_partial_generations(array('per_page' => -1))['total'] ?? 0;
        $pending_reviews    = $post_review_repo->get_draft_count();
        $topics_in_queue    = isset($topic_counts['approved']) ? $topic_counts['approved'] : 0;

        // Get recent history
        $recent_posts_data = $history_repo->get_history(array(
            'per_page' => 5,
            'fields'   => 'list',
        ));
        $recent_posts = $recent_posts_data['items'];

        // Upcoming Scheduled Activity — sourced from all schedule types, matching Schedules page.
        $unified_service  = new AIPS_Unified_Schedule_Service();
        $all_schedules    = $unified_service->get_all();
        $upcoming         = array_slice( array_filter( $all_schedules, function ( $s ) {
            return ! empty( $s['is_active'] );
        } ), 0, 7 );

        // Build chart data (last 14 days).
        $days              = self::CHART_DAYS;
        $daily_generations = $history_repo->get_daily_generation_counts( $days );
        $daily_topics      = $author_topics_repo->get_daily_topic_counts( $days );

        // Build a complete ordered label set for the date range.
        $now_ts      = current_time( 'timestamp' );
        $chart_labels       = array();
        $chart_completed    = array();
        $chart_failed       = array();
        $chart_error_rate   = array();
        $chart_topics       = array();

        for ( $i = $days - 1; $i >= 0; $i-- ) {
            $day_ts   = $now_ts - ( $i * DAY_IN_SECONDS );
            $day_key  = date( 'Y-m-d', $day_ts );
            $day_label = date_i18n( 'M j', $day_ts );

            $chart_labels[]  = $day_label;

            $gen     = isset( $daily_generations[ $day_key ] ) ? $daily_generations[ $day_key ] : array( 'completed' => 0, 'failed' => 0, 'total' => 0 );
            $total   = max( 1, (int) $gen['total'] );
            $completed = (int) $gen['completed'];
            $failed    = (int) $gen['failed'];

            $chart_completed[] = $completed;
            $chart_failed[]    = $failed;
            $chart_error_rate[] = round( ( $failed / $total ) * 100, 1 );
            $chart_topics[]    = isset( $daily_topics[ $day_key ] ) ? (int) $daily_topics[ $day_key ] : 0;
        }

        $chart_data = array(
            'labels'    => $chart_labels,
            'completed' => $chart_completed,
            'failed'    => $chart_failed,
            'errorRate' => $chart_error_rate,
            'topics'    => $chart_topics,
        );

        include AIPS_PLUGIN_DIR . 'templates/admin/dashboard.php';
    }
}
