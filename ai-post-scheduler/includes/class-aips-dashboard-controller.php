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

    /** Maximum number of days allowed in the dashboard date filter. */
    const MAX_DATE_RANGE_DAYS = 365;

    /**
     * Render the main dashboard page.
     *
     * Fetches statistics, recent activity, upcoming schedule data, and chart
     * series data from the database to pass to the dashboard template.
     *
     * @return void
     */
    public function render_page() {
        global $wpdb;

        // Use repositories instead of direct SQL
        $history_repo        = new AIPS_History_Repository();
        $schedule_repo       = new AIPS_Schedule_Repository();
        $template_repo       = new AIPS_Template_Repository();
        $post_review_repo    = new AIPS_Post_Review_Repository();
        $author_topics_repo  = new AIPS_Author_Topics_Repository();

        // 1. Parse Date Range Input (Default: 1st of current month to current day in site timezone)
        $site_now = AIPS_DateTime::now()->toSiteTimezone();
        $default_from = $site_now->format('Y-m-01');
        $default_to = $site_now->format('Y-m-d');

        $date_from_input = isset($_GET['date_from']) ? sanitize_text_field($_GET['date_from']) : '';
        $date_to_input = isset($_GET['date_to']) ? sanitize_text_field($_GET['date_to']) : '';

        $date_from = $this->normalize_dashboard_date_input($date_from_input, $default_from);
        $date_to = $this->normalize_dashboard_date_input($date_to_input, $default_to);

        // Convert local date strings to UTC Unix timestamps for DB queries
        $timezone = wp_timezone();
        try {
            $date_from_obj = new DateTimeImmutable($date_from . ' 00:00:00', $timezone);
            $date_to_obj = new DateTimeImmutable($date_to . ' 23:59:59', $timezone);

            if ($date_from_obj > $date_to_obj) {
                $date_from = $default_from;
                $date_to = $default_to;
                $date_from_obj = new DateTimeImmutable($date_from . ' 00:00:00', $timezone);
                $date_to_obj = new DateTimeImmutable($date_to . ' 23:59:59', $timezone);
            }

            if ($date_from_obj->diff($date_to_obj)->days >= self::MAX_DATE_RANGE_DAYS) {
                $date_from_obj = $date_to_obj->sub(new DateInterval('P' . (self::MAX_DATE_RANGE_DAYS - 1) . 'D'))->setTime(0, 0, 0);
                $date_from = $date_from_obj->format('Y-m-d');
            }

            $from_ts = $date_from_obj->getTimestamp();
            $to_ts = $date_to_obj->getTimestamp();
        } catch (Exception $e) {
            $date_from = $default_from;
            $date_to = $default_to;
            $date_from_obj = new DateTimeImmutable($date_from . ' 00:00:00', $timezone);
            $date_to_obj = new DateTimeImmutable($date_to . ' 23:59:59', $timezone);
            $from_ts = $date_from_obj->getTimestamp();
            $to_ts = $date_to_obj->getTimestamp();
        }

        // Define DB table names
        $table_history = $wpdb->prefix . 'aips_history';
        $table_history_log = $wpdb->prefix . 'aips_history_log';
        $table_author_topics = $wpdb->prefix . 'aips_author_topics';
        $table_schedule = $wpdb->prefix . 'aips_schedule';
        $table_templates = $wpdb->prefix . 'aips_templates';
        $table_authors = $wpdb->prefix . 'aips_authors';

        $auxiliary_methods = array('schedule_lifecycle', 'template_lifecycle', 'campaign_lifecycle');
        $auxiliary_placeholders = implode(', ', array_fill(0, count($auxiliary_methods), '%s'));

        // 2. Fetch Date-Range-Scoped Summary Stats
        $history_stats_query = $wpdb->prepare(
            "SELECT
                COUNT(*) as total,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
                SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed,
                SUM(CASE WHEN status = 'processing' THEN 1 ELSE 0 END) as processing,
                SUM(CASE WHEN status = 'partial' THEN 1 ELSE 0 END) as partial
             FROM {$table_history}
             WHERE COALESCE(creation_method, '') NOT IN ({$auxiliary_placeholders})
               AND NOT (creation_method IS NULL AND template_id IS NULL AND topic_id IS NULL AND post_id IS NULL AND author_id IS NULL)
               AND created_at >= %d AND created_at <= %d",
            array_merge($auxiliary_methods, array($from_ts, $to_ts))
        );
        $history_stats_row = $wpdb->get_row($history_stats_query);

        $total_in_period = isset($history_stats_row->total) ? (int) $history_stats_row->total : 0;
        $completed_in_period = isset($history_stats_row->completed) ? (int) $history_stats_row->completed : 0;
        $failed_in_period = isset($history_stats_row->failed) ? (int) $history_stats_row->failed : 0;
        $partial_in_period = isset($history_stats_row->partial) ? (int) $history_stats_row->partial : 0;
        $success_rate_in_period = $total_in_period > 0 ? round(($completed_in_period / $total_in_period) * 100, 1) : 100.0;

        // Schedules Executed in period
        $schedules_run_in_period = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT correlation_id)
             FROM {$table_history}
             WHERE created_at >= %d AND created_at <= %d
               AND creation_method IN ('scheduled', 'author_topic_gen', 'author_post_gen', 'batch_job')",
            $from_ts,
            $to_ts
        ));

        // Topics Generated in period
        $topics_created_stats = $wpdb->get_row($wpdb->prepare(
            "SELECT
                COUNT(*) as total,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved,
                SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected
             FROM {$table_author_topics}
             WHERE generated_at >= %d AND generated_at <= %d",
            $from_ts,
            $to_ts
        ));
        $topics_created_in_period = isset($topics_created_stats->total) ? (int) $topics_created_stats->total : 0;
        $topics_pending_in_period = isset($topics_created_stats->pending) ? (int) $topics_created_stats->pending : 0;

        // AI Calls and Errors in period
        $ai_stats_row = $wpdb->get_row($wpdb->prepare(
            "SELECT
                SUM(CASE WHEN hl.log_type = 'ai_request' THEN 1 ELSE 0 END) as ai_calls,
                SUM(CASE WHEN hl.log_type = 'error' AND hl.details LIKE '%%AI generation failed%%' THEN 1 ELSE 0 END) as ai_errors
             FROM {$table_history_log} hl
             INNER JOIN {$table_history} h ON hl.history_id = h.id
             WHERE h.created_at >= %d AND h.created_at <= %d",
            $from_ts,
            $to_ts
        ));
        $ai_calls_in_period = isset($ai_stats_row->ai_calls) ? (int) $ai_stats_row->ai_calls : 0;
        $ai_errors_in_period = isset($ai_stats_row->ai_errors) ? (int) $ai_stats_row->ai_errors : 0;
        $ai_error_rate_in_period = $ai_calls_in_period > 0 ? round(($ai_errors_in_period / $ai_calls_in_period) * 100, 1) : 0.0;

        // 3. Outlook: What's going to happen in the next month
        $next_month_start = AIPS_DateTime::now()->timestamp();
        $next_month_end = AIPS_DateTime::now()->advance('+30 days')->timestamp();
        $upcoming_runs_count = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table_schedule}
             WHERE is_active = 1 AND next_run >= %d AND next_run <= %d",
            $next_month_start,
            $next_month_end
        ));

        $unified_service = new AIPS_Unified_Schedule_Service();
        $all_schedules    = $unified_service->get_all('', false);
        $upcoming_schedules = array();
        foreach ($all_schedules as $s) {
            if (!empty($s['is_active']) && !empty($s['next_run']) && $s['next_run'] >= $next_month_start) {
                $upcoming_schedules[] = $s;
            }
        }
        usort($upcoming_schedules, function($a, $b) {
            return (int) $a['next_run'] - (int) $b['next_run'];
        });
        $upcoming_5 = array_slice($upcoming_schedules, 0, 5);

        $date_format = get_option('date_format');
        $time_format = get_option('time_format');
        foreach ($upcoming_5 as &$item) {
            $item['next_run_formatted'] = $this->format_next_run(
                isset($item['next_run']) ? $item['next_run'] : '',
                $date_format,
                $time_format
            );
        }
        unset($item);

        // 4. Detail lists/tables inside range
        // Generated Posts
        $recent_posts_query = $wpdb->prepare(
            "SELECT h.id, h.uuid, h.correlation_id, h.post_id, h.template_id, h.campaign_id, h.topic_id, h.status, h.generated_title, h.created_at, h.completed_at, h.creation_method,
                    t.name as template_name
             FROM {$table_history} h
             LEFT JOIN {$table_templates} t ON h.template_id = t.id
             WHERE h.created_at >= %d AND h.created_at <= %d
               AND COALESCE(h.creation_method, '') NOT IN ({$auxiliary_placeholders})
               AND NOT (h.creation_method IS NULL AND h.template_id IS NULL AND h.topic_id IS NULL AND h.post_id IS NULL AND h.author_id IS NULL)
             ORDER BY h.created_at DESC
             LIMIT 10",
            array_merge(array($from_ts, $to_ts), $auxiliary_methods)
        );
        $recent_posts = $wpdb->get_results($recent_posts_query);
        foreach ($recent_posts as $item) {
            $item->created_at_formatted = AIPS_DateTime::formatRelativeOrAbsolute(
                $item->created_at,
                $date_format
            );
        }

        // Generated Author Topics
        $recent_topics = $wpdb->get_results($wpdb->prepare(
            "SELECT t.*, a.name as author_name
             FROM {$table_author_topics} t
             LEFT JOIN {$table_authors} a ON t.author_id = a.id
             WHERE t.generated_at >= %d AND t.generated_at <= %d
             ORDER BY t.generated_at DESC
             LIMIT 10",
            $from_ts,
            $to_ts
        ));
        foreach ($recent_topics as $item) {
            $item->generated_at_formatted = AIPS_DateTime::formatRelativeOrAbsolute(
                $item->generated_at,
                $date_format
            );
        }

        // Generated Posts via Individual Author Topic
        $posts_by_topic = $wpdb->get_results($wpdb->prepare(
            "SELECT h.post_id, h.generated_title, h.completed_at, t.topic_title, a.name as author_name
             FROM {$table_history} h
             INNER JOIN {$table_author_topics} t ON h.topic_id = t.id
             LEFT JOIN {$table_authors} a ON h.author_id = a.id
             WHERE h.status = 'completed' AND h.created_at >= %d AND h.created_at <= %d
             ORDER BY h.completed_at DESC
             LIMIT 10",
            $from_ts,
            $to_ts
        ));
        foreach ($posts_by_topic as $item) {
            $item->completed_at_formatted = AIPS_DateTime::formatRelativeOrAbsolute(
                $item->completed_at,
                $date_format
            );
        }

        // Schedules Executed
        $executed_schedules = $wpdb->get_results($wpdb->prepare(
            "SELECT h.id, h.uuid, h.status, h.completed_at, h.created_at, h.creation_method,
                    s.title as schedule_title, t.name as template_name, a.name as author_name
             FROM {$table_history} h
             LEFT JOIN {$table_schedule} s ON (h.template_id = s.template_id OR h.author_id = s.author_id)
             LEFT JOIN {$table_templates} t ON h.template_id = t.id
             LEFT JOIN {$table_authors} a ON h.author_id = a.id
             WHERE h.created_at >= %d AND h.created_at <= %d
               AND h.creation_method IN ('scheduled', 'author_topic_gen', 'author_post_gen', 'batch_job')
             ORDER BY h.created_at DESC
             LIMIT 10",
            $from_ts,
            $to_ts
        ));
        foreach ($executed_schedules as $item) {
            $item->created_at_formatted = AIPS_DateTime::formatRelativeOrAbsolute(
                $item->created_at,
                $date_format
            );
        }

        // 5. Daily analytics data for charts (within the selected range)
        $daily_gens_raw = $wpdb->get_results($wpdb->prepare(
            "SELECT
                DATE(FROM_UNIXTIME(created_at)) AS day,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) AS completed,
                SUM(CASE WHEN status = 'failed'    THEN 1 ELSE 0 END) AS failed,
                COUNT(*) AS total
             FROM {$table_history}
             WHERE created_at >= %d AND created_at <= %d
               AND COALESCE(creation_method, '') NOT IN ({$auxiliary_placeholders})
               AND NOT (creation_method IS NULL AND template_id IS NULL AND topic_id IS NULL AND post_id IS NULL AND author_id IS NULL)
             GROUP BY day
             ORDER BY day ASC",
            array_merge(array($from_ts, $to_ts), $auxiliary_methods)
        ));
        $daily_gens_map = array();
        foreach ($daily_gens_raw as $row) {
            $daily_gens_map[$row->day] = array(
                'completed' => (int) $row->completed,
                'failed'    => (int) $row->failed,
                'total'     => (int) $row->total
            );
        }

        $daily_topics_raw = $wpdb->get_results($wpdb->prepare(
            "SELECT DATE(FROM_UNIXTIME(generated_at)) AS day, COUNT(*) AS total
             FROM {$table_author_topics}
             WHERE generated_at >= %d AND generated_at <= %d
             GROUP BY day
             ORDER BY day ASC",
            $from_ts,
            $to_ts
        ));
        $daily_topics_map = array();
        foreach ($daily_topics_raw as $row) {
            $daily_topics_map[$row->day] = (int) $row->total;
        }

        $daily_ai_stats = $wpdb->get_results($wpdb->prepare(
            "SELECT DATE(FROM_UNIXTIME(hl.timestamp)) AS day,
                    SUM(CASE WHEN hl.log_type = 'ai_request' THEN 1 ELSE 0 END) AS ai_calls,
                    SUM(CASE WHEN hl.log_type = 'error' AND hl.details LIKE '%%AI generation failed%%' THEN 1 ELSE 0 END) AS ai_errors
             FROM {$table_history_log} hl
             INNER JOIN {$table_history} h ON hl.history_id = h.id
             WHERE h.created_at >= %d AND h.created_at <= %d
             GROUP BY day
             ORDER BY day ASC",
            $from_ts,
            $to_ts
        ));
        $daily_ai_map = array();
        foreach ($daily_ai_stats as $row) {
            $daily_ai_map[$row->day] = $row;
        }

        // Build sorted, complete daily entries with no gaps
        $chart_labels     = array();
        $chart_completed  = array();
        $chart_failed     = array();
        $chart_error_rate = array();
        $chart_topics     = array();
        $chart_ai_calls   = array();
        $chart_ai_errors  = array();

        $start_date = new DateTime($date_from);
        $end_date = new DateTime($date_to);
        $interval = new DateInterval('P1D');
        $date_range = new DatePeriod($start_date, $interval, $end_date->modify('+1 day'));

        foreach ($date_range as $date) {
            $day_key = $date->format('Y-m-d');
            $day_label = $date->format('M j');

            $chart_labels[] = $day_label;

            $gen = isset($daily_gens_map[$day_key]) ? $daily_gens_map[$day_key] : array('completed' => 0, 'failed' => 0, 'total' => 0);
            $completed = $gen['completed'];
            $failed = $gen['failed'];
            $total = $gen['total'];

            $chart_completed[]  = $completed;
            $chart_failed[]     = $failed;
            $chart_error_rate[] = $total > 0 ? round(($failed / $total) * 100, 1) : 0;
            $chart_topics[]     = isset($daily_topics_map[$day_key]) ? $daily_topics_map[$day_key] : 0;

            if (isset($daily_ai_map[$day_key])) {
                $chart_ai_calls[]  = (int) $daily_ai_map[$day_key]->ai_calls;
                $chart_ai_errors[] = (int) $daily_ai_map[$day_key]->ai_errors;
            } else {
                $chart_ai_calls[]  = 0;
                $chart_ai_errors[] = 0;
            }
        }

        $chart_data = array(
            'labels'    => $chart_labels,
            'completed' => $chart_completed,
            'failed'    => $chart_failed,
            'errorRate' => $chart_error_rate,
            'topics'    => $chart_topics,
            'aiCalls'   => $chart_ai_calls,
            'aiErrors'  => $chart_ai_errors,
        );

        // Keep global counts for template header status counters (optional dashboard stats widgets)
        $history_stats_all      = $history_repo->get_stats();
        $schedule_counts        = $schedule_repo->count_by_status();
        $template_counts        = $template_repo->count_by_status();
        $topic_counts           = $author_topics_repo->get_global_status_counts();
        $pending_reviews        = $post_review_repo->get_draft_count();
        $topics_in_queue        = isset($topic_counts['approved']) ? $topic_counts['approved'] : 0;
        $partial_generations    = $history_repo->get_partial_generations(array('per_page' => -1))['total'] ?? 0;

        include AIPS_PLUGIN_DIR . 'templates/admin/dashboard.php';
    }

    /**
     * Normalize a dashboard date filter value to a valid YYYY-MM-DD date.
     *
     * @param string $value Raw date filter value.
     * @param string $fallback Fallback date in YYYY-MM-DD format.
     * @return string
     */
    private function normalize_dashboard_date_input($value, $fallback) {
        if (empty($value) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return $fallback;
        }

        list($year, $month, $day) = array_map('intval', explode('-', $value));

        return checkdate($month, $day, $year) ? $value : $fallback;
    }

    /**
     * Format a next_run timestamp as a human-readable relative string.
     *
     * Returns a relative label ("in 2 hours", "in 1 day and 3 hours", etc.) for
     * events within the next 30 days, and an absolute date/time string otherwise.
     *
     * next_run is stored as a Unix timestamp.
     *
     * @param int|string $next_run Timestamp.
     * @param string $date_format WordPress date_format option value.
     * @param string $time_format WordPress time_format option value.
     * @return string
     */
    private function format_next_run( $next_run, $date_format, $time_format ) {
        if ( empty( $next_run ) ) {
            return '—';
        }

        if ( ! is_numeric( $next_run ) ) {
            return '—';
        }

        $run_at = AIPS_DateTime::fromTimestampOrNull( (int) $next_run );
        if ( null === $run_at ) {
            return '—';
        }
        $run_ts = $run_at->timestamp();

        $now_ts = AIPS_DateTime::now()->timestamp(); // UTC
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
