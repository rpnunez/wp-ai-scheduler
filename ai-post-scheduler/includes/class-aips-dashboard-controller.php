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
        // Pass include_stats=false to skip expensive aggregate COUNT queries since the dashboard
        // only needs schedule metadata (title, type, next_run) for the upcoming list.
        $unified_service  = new AIPS_Unified_Schedule_Service();
        $all_schedules    = $unified_service->get_all( '', false );
        $upcoming         = array_slice( array_filter( $all_schedules, function ( $s ) {
            return ! empty( $s['is_active'] );
        } ), 0, 7 );

        // Build chart data (last 14 days).
        $days              = self::CHART_DAYS;
        $daily_generations = $history_repo->get_daily_generation_counts( $days );
        $daily_topics      = $author_topics_repo->get_daily_topic_counts( $days );

        // Build a complete ordered label set for the date range.
        // Use UTC-based timestamps with wp_date()/wp_timezone() so day boundaries
        // are calculated in the site timezone, matching DATE(created_at) SQL buckets.
        $now_ts           = current_time( 'timestamp', true );
        $timezone         = wp_timezone();
        $chart_labels     = array();
        $chart_completed  = array();
        $chart_failed     = array();
        $chart_error_rate = array();
        $chart_topics     = array();

        for ( $i = $days - 1; $i >= 0; $i-- ) {
            $day_ts    = $now_ts - ( $i * DAY_IN_SECONDS );
            $day_key   = wp_date( 'Y-m-d', $day_ts, $timezone );
            $day_label = wp_date( 'M j', $day_ts, $timezone );

            $chart_labels[]  = $day_label;

            $gen       = isset( $daily_generations[ $day_key ] ) ? $daily_generations[ $day_key ] : array( 'completed' => 0, 'failed' => 0, 'total' => 0 );
            $completed = (int) $gen['completed'];
            $failed    = (int) $gen['failed'];
            $total     = isset( $gen['total'] ) ? (int) $gen['total'] : 0;

            $chart_completed[]  = $completed;
            $chart_failed[]     = $failed;
            $chart_error_rate[] = $total > 0 ? round( ( $failed / $total ) * 100, 1 ) : 0;
            $chart_topics[]     = isset( $daily_topics[ $day_key ] ) ? (int) $daily_topics[ $day_key ] : 0;
        }

        $chart_data = array(
            'labels'    => $chart_labels,
            'completed' => $chart_completed,
            'failed'    => $chart_failed,
            'errorRate' => $chart_error_rate,
            'topics'    => $chart_topics,
        );

        // Pre-format next_run for each upcoming item using relative time.
        // get_option() is called once here, not inside the template loop.
        $date_format = get_option( 'date_format' );
        $time_format = get_option( 'time_format' );
        foreach ( $upcoming as &$item ) {
            $item['next_run_formatted'] = $this->format_next_run(
                isset( $item['next_run'] ) ? $item['next_run'] : '',
                $date_format,
                $time_format
            );
        }
        unset( $item );

        include AIPS_PLUGIN_DIR . 'templates/admin/dashboard.php';
    }

    /**
     * Format a next_run MySQL datetime as a human-readable relative string.
     *
     * Returns a relative label ("in 2 hours", "in 1 day and 3 hours", etc.) for
     * events within the next 30 days, and an absolute date/time string otherwise.
     *
     * next_run is stored as a site-local MySQL datetime.  We convert it to a UTC
     * timestamp via get_gmt_from_date() so that subsequent comparisons with
     * current_time('timestamp', true) are consistent, and wp_date() output
     * correctly applies the site timezone offset once (not twice).
     *
     * @param string $next_run    MySQL datetime string (site-local).
     * @param string $date_format WordPress date_format option value.
     * @param string $time_format WordPress time_format option value.
     * @return string
     */
    private function format_next_run( $next_run, $date_format, $time_format ) {
        if ( empty( $next_run ) ) {
            return '—';
        }

        // Convert site-local datetime to a true UTC timestamp.
        $next_run_gmt = get_gmt_from_date( $next_run );
        $run_ts       = strtotime( $next_run_gmt );
        if ( false === $run_ts ) {
            return '—';
        }

        $now_ts = current_time( 'timestamp', true ); // UTC
        $diff   = $run_ts - $now_ts;

        // Already in the past or within a minute — show absolute.
        if ( $diff <= 60 ) {
            return wp_date( $date_format . ' ' . $time_format, $run_ts );
        }

        $minutes = (int) floor( $diff / 60 );
        $hours   = (int) floor( $diff / HOUR_IN_SECONDS );
        $days    = (int) floor( $diff / DAY_IN_SECONDS );

        // More than 30 days: show absolute.
        if ( $days >= 30 ) {
            return wp_date( $date_format . ' ' . $time_format, $run_ts );
        }

        // 2+ days.
        if ( $days >= 2 ) {
            $rem_hours = (int) floor( ( $diff - $days * DAY_IN_SECONDS ) / HOUR_IN_SECONDS );
            if ( $rem_hours > 0 ) {
                /* translators: 1: number of days, 2: number of hours */
                return sprintf( __( 'in %1$d days and %2$d hours', 'ai-post-scheduler' ), $days, $rem_hours );
            }
            /* translators: %d: number of days */
            return sprintf( __( 'in %d days', 'ai-post-scheduler' ), $days );
        }

        // Exactly 1 day range (24–47 hours).
        if ( $hours >= 24 ) {
            $rem_hours = (int) floor( ( $diff - DAY_IN_SECONDS ) / HOUR_IN_SECONDS );
            if ( $rem_hours > 0 ) {
                /* translators: %d: number of hours */
                return sprintf( __( 'in 1 day and %d hours', 'ai-post-scheduler' ), $rem_hours );
            }
            return __( 'in 1 day', 'ai-post-scheduler' );
        }

        // 2+ hours.
        if ( $hours >= 2 ) {
            $rem_mins = (int) floor( ( $diff - $hours * HOUR_IN_SECONDS ) / 60 );
            if ( $rem_mins >= 15 ) {
                /* translators: 1: number of hours, 2: number of minutes */
                return sprintf( __( 'in %1$d hours and %2$d minutes', 'ai-post-scheduler' ), $hours, $rem_mins );
            }
            /* translators: %d: number of hours */
            return sprintf( __( 'in %d hours', 'ai-post-scheduler' ), $hours );
        }

        // 1 hour range.
        if ( $hours === 1 ) {
            $rem_mins = $minutes - 60;
            if ( $rem_mins >= 15 ) {
                /* translators: %d: number of minutes */
                return sprintf( __( 'in 1 hour and %d minutes', 'ai-post-scheduler' ), $rem_mins );
            }
            return __( 'in 1 hour', 'ai-post-scheduler' );
        }

        // Under 1 hour.
        if ( $minutes === 1 ) {
            return __( 'in 1 minute', 'ai-post-scheduler' );
        }
        /* translators: %d: number of minutes */
        return sprintf( __( 'in %d minutes', 'ai-post-scheduler' ), $minutes );
    }
}
