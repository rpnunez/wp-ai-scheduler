<?php
if (!defined('ABSPATH')) {
    exit;
}

class AIPS_System_Status {

    /**
     * Minimum success rate (%) considered healthy for scheduler runs and
     * post-generation outcomes.  At or above this threshold → 'ok'.
     */
    const METRIC_OK_THRESHOLD = 90;

    /**
     * Minimum success rate (%) considered a warning level.
     * Between METRIC_WARN_THRESHOLD and METRIC_OK_THRESHOLD → 'warning'.
     * Below METRIC_WARN_THRESHOLD → 'error'.
     */
    const METRIC_WARN_THRESHOLD = 70;

    /**
     * Maximum image-generation failure rate (%) considered healthy → 'ok'.
     */
    const IMAGE_FAIL_OK_THRESHOLD = 10;

    /**
     * Image-generation failure rate (%) upper bound for 'warning' level.
     * Above this → 'error'.
     */
    const IMAGE_FAIL_WARN_THRESHOLD = 30;

    /**
     * Number of stuck jobs at or above which the status is 'warning'.
     */
    const QUEUE_STUCK_WARN_THRESHOLD = 1;

    /**
     * Number of stuck jobs at or above which the status escalates to 'error'.
     */
    const QUEUE_STUCK_ERROR_THRESHOLD = 5;

    /**
     * Retry-saturation percentage at or above which the status is 'warning' (0–100).
     */
    const QUEUE_RETRY_WARN_THRESHOLD = 20;

    /**
     * Retry-saturation percentage above which the status escalates to 'error'.
     */
    const QUEUE_RETRY_ERROR_THRESHOLD = 50;

    public function render_page() {
        $system_info = $this->get_system_info();
        $data_management = $this->get_data_management();

        if ( $data_management ) {
            $export_formats = $data_management->get_export_formats();
            $import_formats = $data_management->get_import_formats();
        } else {
            $export_formats = array();
            $import_formats = array();
        }

        include AIPS_PLUGIN_DIR . 'templates/admin/system-status.php';
    }

    /**
     * Get the AIPS_Data_Management instance without causing duplicate hook registrations.
     *
     * @return AIPS_Data_Management|null
     */
    private function get_data_management() {
        if ( ! class_exists( 'AIPS_Data_Management' ) ) {
            return null;
        }

        // Prefer a shared/global instance if the plugin exposes one.
        global $aips_data_management;
        if ( isset( $aips_data_management ) && $aips_data_management instanceof AIPS_Data_Management ) {
            return $aips_data_management;
        }

        // Fallback to a singleton accessor if available.
        if ( method_exists( 'AIPS_Data_Management', 'get_instance' ) ) {
            return AIPS_Data_Management::get_instance();
        }

        // As a last resort, create a new instance.
        return new AIPS_Data_Management();
    }
    public function get_system_info() {
        return array(
            'environment'        => $this->check_environment(),
            'plugin'             => $this->check_plugin(),
            'database'           => $this->check_database(),
            'filesystem'         => $this->check_filesystem(),
            'cron'               => $this->check_cron(),
            'scheduler health'   => $this->check_scheduler_health(),
            'queue health'       => $this->check_queue_health(),
            'generation metrics' => $this->check_generation_metrics(),
            'notifications'      => $this->check_notifications(),
            'logs'               => $this->check_logs(),
        );
    }

    /**
     * Check notifications configuration and runtime diagnostics.
     *
     * @return array<string, array<string, mixed>>
     */
    private function check_notifications() {
        $repository = class_exists('AIPS_Notifications_Repository') ? new AIPS_Notifications_Repository() : null;
        $recipient_list = (string) get_option('aips_review_notifications_email', get_option('admin_email'));
        $recipient_count = 0;
        if (!empty($recipient_list)) {
            $parts = preg_split('/\s*,\s*/', $recipient_list);
            $parts = is_array($parts) ? array_filter($parts) : array();
            $recipient_count = count($parts);
        }

        $daily_marker = (string) get_option('aips_notif_daily_digest_last_sent', '');
        $weekly_marker = (string) get_option('aips_notif_weekly_summary_last_sent', '');
        $monthly_marker = (string) get_option('aips_notif_monthly_report_last_sent', '');

        $new_cron = wp_next_scheduled('aips_notification_rollups');
        $legacy_cron = wp_next_scheduled('aips_send_review_notifications');

        $counts_24h = array();
        $unread_count = 0;
        if ($repository instanceof AIPS_Notifications_Repository) {
            $counts_24h = $repository->get_type_counts_for_window(DAY_IN_SECONDS);
            $unread_count = (int) $repository->count_unread();
        }

        $top_types = array();
        if (!empty($counts_24h)) {
            arsort($counts_24h);
            $counts_24h = array_slice($counts_24h, 0, 8, true);
            foreach ($counts_24h as $type => $count) {
                $top_types[] = sprintf('%s: %d', $type, (int) $count);
            }
        }

        return array(
            'recipients' => array(
                'label'  => __('Notification Recipients', 'ai-post-scheduler'),
                'value'  => $recipient_count > 0 ? sprintf(_n('%d recipient configured', '%d recipients configured', $recipient_count, 'ai-post-scheduler'), $recipient_count) : __('No recipients configured', 'ai-post-scheduler'),
                'status' => $recipient_count > 0 ? 'ok' : 'warning',
                'details'=> $recipient_count > 0 ? array($recipient_list) : array(),
            ),
            'rollup_markers' => array(
                'label'  => __('Rollup Send Markers', 'ai-post-scheduler'),
                'value'  => __('Available', 'ai-post-scheduler'),
                'status' => 'info',
                'details'=> array(
                    sprintf(__('Daily marker: %s', 'ai-post-scheduler'), $daily_marker ? $daily_marker : __('not set', 'ai-post-scheduler')),
                    sprintf(__('Weekly marker: %s', 'ai-post-scheduler'), $weekly_marker ? $weekly_marker : __('not set', 'ai-post-scheduler')),
                    sprintf(__('Monthly marker: %s', 'ai-post-scheduler'), $monthly_marker ? $monthly_marker : __('not set', 'ai-post-scheduler')),
                ),
            ),
            'rollup_cron' => array(
                'label'  => __('Rollup Cron Hook', 'ai-post-scheduler'),
                'value'  => $new_cron ? date('Y-m-d H:i:s', $new_cron) : __('Not Scheduled', 'ai-post-scheduler'),
                'status' => $new_cron ? 'ok' : 'warning',
            ),
            'legacy_rollup_cron' => array(
                'label'  => __('Legacy Rollup Hook (Compatibility)', 'ai-post-scheduler'),
                'value'  => $legacy_cron ? date('Y-m-d H:i:s', $legacy_cron) : __('Not Scheduled', 'ai-post-scheduler'),
                'status' => $legacy_cron ? 'info' : 'ok',
            ),
            'unread_notifications' => array(
                'label'  => __('Unread DB Notifications', 'ai-post-scheduler'),
                'value'  => (string) $unread_count,
                'status' => $unread_count > 0 ? 'info' : 'ok',
            ),
            'recent_notification_types' => array(
                'label'  => __('Last 24h Notification Volume', 'ai-post-scheduler'),
                'value'  => empty($top_types) ? __('No notifications in the last 24 hours', 'ai-post-scheduler') : sprintf(__('%d type(s) active', 'ai-post-scheduler'), count($top_types)),
                'status' => empty($top_types) ? 'info' : 'ok',
                'details'=> $top_types,
            ),
        );
    }

    /**
     * Count how many times a given cron hook appears across all scheduled
     * timestamps in the WP cron table (including all arg variants).
     *
     * @param string $hook  The cron hook name.
     * @return int
     */
    private function count_cron_hook_instances( $hook ) {
        $cron_array = _get_cron_array();
        if ( ! is_array( $cron_array ) ) {
            return 0;
        }

        $count = 0;
        foreach ( $cron_array as $timestamp => $hooks ) {
            if ( isset( $hooks[ $hook ] ) && is_array( $hooks[ $hook ] ) ) {
                $count += count( $hooks[ $hook ] );
            }
        }

        return $count;
    }

    /**
     * Get all scheduled timestamps for a given cron hook.
     *
     * @param string $hook  The cron hook name.
     * @return int[]  Array of Unix timestamps.
     */
    private function get_cron_hook_timestamps( $hook ) {
        $cron_array = _get_cron_array();
        if ( ! is_array( $cron_array ) ) {
            return array();
        }

        $timestamps = array();
        foreach ( $cron_array as $timestamp => $hooks ) {
            if ( isset( $hooks[ $hook ] ) ) {
                // Count one entry per arg variant at this timestamp.
                $arg_count = is_array( $hooks[ $hook ] ) ? count( $hooks[ $hook ] ) : 1;
                for ( $i = 0; $i < $arg_count; $i++ ) {
                    $timestamps[] = (int) $timestamp;
                }
            }
        }

        sort( $timestamps );

        return $timestamps;
    }

    /**
     * Scheduler health checks.
     *
     * Reports cron registration status, expected vs. actual cron event counts,
     * per-hook diagnostics, queue-depth surrogates, and the
     * success/failure rate for scheduled generation runs over the last 30 days.
     *
     * @return array<string, array<string, mixed>>
     */
    private function check_scheduler_health() {
        $checks = array();

        // --- Per-hook cron diagnostics ---
        $cron_events        = AI_Post_Scheduler::get_cron_events();
        $expected_total     = count( $cron_events );
        $actual_total       = 0;
        $hooks_missing      = array();
        $hooks_duplicate    = array();
        $hook_details       = array();

        foreach ( $cron_events as $hook => $config ) {
            $label      = isset( $config['label'] ) ? $config['label'] : $hook;
            $schedule   = isset( $config['schedule'] ) ? $config['schedule'] : '';
            $actual     = $this->count_cron_hook_instances( $hook );
            $timestamps = $this->get_cron_hook_timestamps( $hook );
            $actual_total += $actual;

            if ( $actual === 0 ) {
                $hooks_missing[] = $label;
            } elseif ( $actual > 1 ) {
                $hooks_duplicate[] = $label;
            }

            // Build detail lines for this hook.
            $detail_lines = array();
            $detail_lines[] = sprintf(
                __( 'Hook: %s | Expected: 1 | Actual: %d | Schedule: %s', 'ai-post-scheduler' ),
                $hook,
                $actual,
                $schedule
            );

            if ( ! empty( $timestamps ) ) {
                $next = $timestamps[0];
                $detail_lines[] = sprintf(
                    __( 'Next run: %s (%s)', 'ai-post-scheduler' ),
                    wp_date( 'Y-m-d H:i:s', $next ),
                    human_time_diff( $next, time() ) . ( $next > time() ? ' from now' : ' ago' )
                );

                if ( count( $timestamps ) > 1 ) {
                    $all_times = array_map( function( $ts ) {
                        return wp_date( 'Y-m-d H:i:s', $ts );
                    }, $timestamps );
                    $detail_lines[] = sprintf(
                        __( 'All scheduled times (%d): %s', 'ai-post-scheduler' ),
                        count( $timestamps ),
                        implode( ', ', $all_times )
                    );
                }
            } else {
                $detail_lines[] = __( 'Not currently scheduled in WP-Cron.', 'ai-post-scheduler' );
            }

            $hook_key = 'cron_hook_' . sanitize_key( $hook );
            if ( $actual === 0 ) {
                $hook_status = 'error';
                $hook_value  = __( 'Not scheduled', 'ai-post-scheduler' );
            } elseif ( $actual === 1 ) {
                $hook_status = 'ok';
                $hook_value  = empty( $timestamps )
                    ? __( 'Scheduled (1)', 'ai-post-scheduler' )
                    : sprintf( __( 'Scheduled (1) — next: %s', 'ai-post-scheduler' ), wp_date( 'Y-m-d H:i:s', $timestamps[0] ) );
            } else {
                $hook_status = 'error';
                $hook_value  = sprintf(
                    __( '%d duplicate events — expected 1. Use "Flush WP-Cron Events" to fix.', 'ai-post-scheduler' ),
                    $actual
                );
            }

            $checks[ $hook_key ] = array(
                'label'   => sprintf( __( 'Cron: %s', 'ai-post-scheduler' ), $label ),
                'value'   => $hook_value,
                'status'  => $hook_status,
                'details' => $detail_lines,
            );
        }

        // --- Cron summary ---
        $summary_status = 'ok';
        if ( ! empty( $hooks_missing ) || ! empty( $hooks_duplicate ) ) {
            $summary_status = 'error';
        }
        $summary_parts = array(
            sprintf( __( 'Expected: %d', 'ai-post-scheduler' ), $expected_total ),
            sprintf( __( 'Actual: %d', 'ai-post-scheduler' ), $actual_total ),
        );
        if ( ! empty( $hooks_missing ) ) {
            $summary_parts[] = sprintf( __( 'Missing: %s', 'ai-post-scheduler' ), implode( ', ', $hooks_missing ) );
        }
        if ( ! empty( $hooks_duplicate ) ) {
            $summary_parts[] = sprintf( __( 'Duplicates: %s', 'ai-post-scheduler' ), implode( ', ', $hooks_duplicate ) );
        }

        $checks['cron_summary'] = array(
            'label'  => __( 'WP-Cron Event Summary', 'ai-post-scheduler' ),
            'value'  => implode( ' | ', $summary_parts ),
            'status' => $summary_status,
        );

        // --- Metrics from repository ---
        if ( ! class_exists( 'AIPS_Metrics_Repository' ) ) {
            return $checks;
        }

        $metrics_repo = new AIPS_Metrics_Repository();
        $queue        = $metrics_repo->get_queue_depth_metrics();
        $generation   = $metrics_repo->get_generation_metrics( 30 );

        // Queue depth surrogates
        $checks['active_schedules'] = array(
            'label'  => __( 'Active Schedules', 'ai-post-scheduler' ),
            'value'  => (string) $queue['active_schedules'],
            'status' => $queue['active_schedules'] > 0 ? 'ok' : 'info',
        );

        $checks['approved_topics'] = array(
            'label'  => __( 'Approved Topics in Queue', 'ai-post-scheduler' ),
            'value'  => (string) $queue['approved_topics'],
            'status' => $queue['approved_topics'] > 0 ? 'info' : 'ok',
        );

        // Scheduled-run success rate
        if ( $generation['schedule_success_rate'] >= 0 ) {
            $schedule_rate   = $generation['schedule_success_rate'];
            $schedule_status = $schedule_rate >= self::METRIC_OK_THRESHOLD ? 'ok' : ( $schedule_rate >= self::METRIC_WARN_THRESHOLD ? 'warning' : 'error' );
            $checks['schedule_success_rate'] = array(
                'label'  => __( 'Schedule Run Success Rate (30d)', 'ai-post-scheduler' ),
                'value'  => $schedule_rate . '%',
                'status' => $schedule_status,
            );
        } else {
            $checks['schedule_success_rate'] = array(
                'label'  => __( 'Schedule Run Success Rate (30d)', 'ai-post-scheduler' ),
                'value'  => __( 'No scheduled runs recorded', 'ai-post-scheduler' ),
                'status' => 'info',
            );
        }

        return $checks;
    }

    /**
     * Queue health checks.
     *
     * Surfaces backlog, stuck-job signals, retry saturation, and circuit-breaker
     * state so operators can identify queued work that is failing to make progress.
     *
     * @return array<string, array<string, mixed>>
     */
    private function check_queue_health() {
        if ( ! class_exists( 'AIPS_Metrics_Repository' ) ) {
            return array(
                'unavailable' => array(
                    'label'  => __( 'Queue Health', 'ai-post-scheduler' ),
                    'value'  => __( 'Metrics repository not available', 'ai-post-scheduler' ),
                    'status' => 'info',
                ),
            );
        }

        $metrics_repo = new AIPS_Metrics_Repository();
        $qh           = $metrics_repo->get_queue_health_metrics();

        $checks = array();

        // --- Pending / partial backlog ---
        $backlog_total  = $qh['pending_count'] + $qh['partial_count'];
        $backlog_status = $backlog_total === 0 ? 'ok' : 'info';
        $checks['queue_backlog'] = array(
            'label'   => __( 'Queue Backlog', 'ai-post-scheduler' ),
            'value'   => sprintf(
                /* translators: 1: pending job count, 2: partial job count */
                __( '%1$d pending, %2$d partial', 'ai-post-scheduler' ),
                $qh['pending_count'],
                $qh['partial_count']
            ),
            'status'  => $backlog_status,
            'details' => $backlog_total > 0 ? array(
                __( 'Pending jobs are waiting to run; partial jobs started but did not complete.', 'ai-post-scheduler' ),
                __( 'If counts are unexpectedly high, check WP-Cron is running and AI Engine is reachable.', 'ai-post-scheduler' ),
            ) : array(),
        );

        // --- Stuck jobs ---
        $stuck        = $qh['stuck_count'];
        $stuck_status = 'ok';
        if ( $stuck >= self::QUEUE_STUCK_ERROR_THRESHOLD ) {
            $stuck_status = 'error';
        } elseif ( $stuck >= self::QUEUE_STUCK_WARN_THRESHOLD ) {
            $stuck_status = 'warning';
        }

        $stuck_value = $stuck > 0
            ? sprintf(
                /* translators: 1: count of stuck jobs, 2: age in minutes of the oldest stuck job */
                _n(
                    '%1$d job stuck (oldest: %2$d min)',
                    '%1$d jobs stuck (oldest: %2$d min)',
                    $stuck,
                    'ai-post-scheduler'
                ),
                $stuck,
                $qh['oldest_stuck_age_minutes'] ?? 0
            )
            : __( 'None', 'ai-post-scheduler' );

        $stuck_details = $stuck > 0 ? array(
            sprintf(
                /* translators: %d: threshold in minutes */
                __( 'A job is considered stuck when it remains in pending/partial status for more than %d minutes.', 'ai-post-scheduler' ),
                AIPS_Metrics_Repository::STUCK_JOB_THRESHOLD_MINUTES
            ),
            __( 'To recover: check the History log for correlation IDs, verify AI Engine is responding, then use Flush WP-Cron Events if cron events are missing.', 'ai-post-scheduler' ),
        ) : array();

        $checks['stuck_jobs'] = array(
            'label'   => __( 'Stuck Jobs', 'ai-post-scheduler' ),
            'value'   => $stuck_value,
            'status'  => $stuck_status,
            'details' => $stuck_details,
        );

        // --- Retry saturation (failure rate over last 24 h) ---
        if ( $qh['retry_saturation_pct'] >= 0 ) {
            $sat     = $qh['retry_saturation_pct'];
            $sat_status = 'ok';
            if ( $sat > self::QUEUE_RETRY_ERROR_THRESHOLD ) {
                $sat_status = 'error';
            } elseif ( $sat >= self::QUEUE_RETRY_WARN_THRESHOLD ) {
                $sat_status = 'warning';
            }

            $checks['retry_saturation'] = array(
                'label'   => __( 'Retry Saturation (24h failure rate)', 'ai-post-scheduler' ),
                'value'   => $sat . '%',
                'status'  => $sat_status,
                'details' => array(
                    sprintf(
                        /* translators: %d: number of failed jobs in last 24 hours */
                        __( 'Failed jobs in last 24 h: %d', 'ai-post-scheduler' ),
                        $qh['failed_24h']
                    ),
                    __( 'A high failure rate often indicates API quota exhaustion, rate limits, or AI Engine configuration issues.', 'ai-post-scheduler' ),
                ),
            );
        } else {
            $checks['retry_saturation'] = array(
                'label'  => __( 'Retry Saturation (24h failure rate)', 'ai-post-scheduler' ),
                'value'  => __( 'No completed/failed jobs in last 24 h', 'ai-post-scheduler' ),
                'status' => 'info',
            );
        }

        // --- Circuit-breaker state ---
        $cb       = $qh['circuit_breaker'];
        $cb_state = isset( $cb['state'] ) ? $cb['state'] : 'unknown';

        if ( $cb_state === 'open' ) {
            $cb_status = 'error';
            $cb_value  = __( 'OPEN — AI requests are blocked', 'ai-post-scheduler' );
        } elseif ( $cb_state === 'half_open' ) {
            $cb_status = 'warning';
            $cb_value  = __( 'HALF-OPEN — probing AI availability', 'ai-post-scheduler' );
        } elseif ( $cb_state === 'closed' ) {
            $cb_status = 'ok';
            $cb_value  = __( 'Closed (healthy)', 'ai-post-scheduler' );
        } else {
            $cb_status = 'info';
            $cb_value  = __( 'Unknown (circuit breaker may be disabled)', 'ai-post-scheduler' );
        }

        $cb_details = array();
        if ( isset( $cb['failures'] ) ) {
            $cb_details[] = sprintf(
                /* translators: %d: consecutive failure count */
                __( 'Consecutive failures: %d', 'ai-post-scheduler' ),
                (int) $cb['failures']
            );
        }
        if ( $cb_state === 'open' || $cb_state === 'half_open' ) {
            $cb_details[] = __( 'To reset: use "Reset Circuit Breaker" on this page, or navigate to Settings → AI Engine.', 'ai-post-scheduler' );
        }

        $checks['circuit_breaker'] = array(
            'label'   => __( 'Circuit Breaker', 'ai-post-scheduler' ),
            'value'   => $cb_value,
            'status'  => $cb_status,
            'details' => $cb_details,
        );

        return $checks;
    }

    /**
     * Baseline generation performance and reliability metrics.
     *
     * Surfaces generation success/failure rates, duration percentiles, average
     * AI-call counts, and the image-generation failure rate for the last 30 days.
     *
     * @return array<string, array<string, mixed>>
     */
    private function check_generation_metrics() {
        if ( ! class_exists( 'AIPS_Metrics_Repository' ) ) {
            return array(
                'unavailable' => array(
                    'label'  => __( 'Generation Metrics', 'ai-post-scheduler' ),
                    'value'  => __( 'Metrics repository not available', 'ai-post-scheduler' ),
                    'status' => 'info',
                ),
            );
        }

        $metrics_repo = new AIPS_Metrics_Repository();
        $m            = $metrics_repo->get_generation_metrics( 30 );

        $checks = array();

        // Success / failure rates
        $success_status = $m['success_rate'] >= self::METRIC_OK_THRESHOLD ? 'ok' : ( $m['success_rate'] >= self::METRIC_WARN_THRESHOLD ? 'warning' : 'error' );
        $checks['success_rate'] = array(
            'label'  => __( 'Generation Success Rate (30d)', 'ai-post-scheduler' ),
            'value'  => $m['total'] > 0 ? $m['success_rate'] . '%' : __( 'No data', 'ai-post-scheduler' ),
            'status' => $m['total'] > 0 ? $success_status : 'info',
            'details' => $m['total'] > 0 ? array(
                sprintf( __( 'Total: %d | Completed: %d | Failed: %d | Partial: %d', 'ai-post-scheduler' ),
                    $m['total'], $m['successful'], $m['failed'], $m['partial'] ),
            ) : array(),
        );

        // Generation duration percentiles
        $checks['generation_duration'] = array(
            'label'  => __( 'Generation Duration (30d, completed)', 'ai-post-scheduler' ),
            'value'  => $m['avg_duration_seconds'] > 0
                ? sprintf( __( 'Avg %ds', 'ai-post-scheduler' ), $m['avg_duration_seconds'] )
                : __( 'No data', 'ai-post-scheduler' ),
            'status' => 'info',
            'details' => $m['avg_duration_seconds'] > 0 ? array(
                sprintf( __( 'p50: %ds | p95: %ds', 'ai-post-scheduler' ),
                    $m['p50_duration_seconds'], $m['p95_duration_seconds'] ),
            ) : array(),
        );

        // Avg AI calls per post
        $checks['avg_ai_calls'] = array(
            'label'  => __( 'Avg AI Calls per Completed Post (30d)', 'ai-post-scheduler' ),
            'value'  => $m['avg_ai_calls_per_post'] > 0
                ? (string) $m['avg_ai_calls_per_post']
                : __( 'No data', 'ai-post-scheduler' ),
            'status' => 'info',
        );

        // Image failure rate
        if ( $m['image_failure_rate'] >= 0 ) {
            $img_status = $m['image_failure_rate'] <= self::IMAGE_FAIL_OK_THRESHOLD ? 'ok' : ( $m['image_failure_rate'] <= self::IMAGE_FAIL_WARN_THRESHOLD ? 'warning' : 'error' );
            $checks['image_failure_rate'] = array(
                'label'  => __( 'Image Generation Failure Rate (30d)', 'ai-post-scheduler' ),
                'value'  => $m['image_failure_rate'] . '%',
                'status' => $img_status,
            );
        } else {
            $checks['image_failure_rate'] = array(
                'label'  => __( 'Image Generation Failure Rate (30d)', 'ai-post-scheduler' ),
                'value'  => __( 'No image-generation data', 'ai-post-scheduler' ),
                'status' => 'info',
            );
        }

        // Recent outcomes (last 10)
        $recent = $m['recent_outcomes'];
        if ( ! empty( $recent ) ) {
            $outcome_lines = array();
            foreach ( $recent as $outcome ) {
                $line = sprintf( '[%s] %s', $outcome['created_at'], strtoupper( $outcome['status'] ) );
                if ( $outcome['duration_seconds'] !== null ) {
                    $line .= sprintf( ' (%ds)', $outcome['duration_seconds'] );
                }
                if ( $outcome['error_message'] ) {
                    $line .= ' — ' . $outcome['error_message'];
                }
                $outcome_lines[] = $line;
            }

            $failed_recent = array_filter( $recent, function ( $o ) {
                return $o['status'] === 'failed';
            } );
            $recent_status = count( $failed_recent ) === 0 ? 'ok'
                : ( count( $failed_recent ) <= 2 ? 'warning' : 'error' );

            $checks['recent_outcomes'] = array(
                'label'   => __( 'Recent Generation Outcomes (last 10)', 'ai-post-scheduler' ),
                'value'   => sprintf(
                    __( '%d shown | %d failed', 'ai-post-scheduler' ),
                    count( $recent ), count( $failed_recent )
                ),
                'status'  => $recent_status,
                'details' => $outcome_lines,
            );
        } else {
            $checks['recent_outcomes'] = array(
                'label'  => __( 'Recent Generation Outcomes', 'ai-post-scheduler' ),
                'value'  => __( 'No generation history found', 'ai-post-scheduler' ),
                'status' => 'info',
            );
        }

        return $checks;
    }

    private function check_environment() {
        global $wp_version, $wpdb;
        return array(
            'php_version' => array(
                'label' => 'PHP Version',
                'value' => phpversion(),
                'status' => version_compare(phpversion(), '8.2', '>=') ? 'ok' : 'warning',
            ),
            'wp_version' => array(
                'label' => 'WordPress Version',
                'value' => $wp_version,
                'status' => 'ok',
            ),
            'mysql_version' => array(
                'label' => 'MySQL Version',
                'value' => $wpdb->db_version(),
                'status' => 'ok',
            ),
            'server_software' => array(
                'label' => 'Web Server',
                'value' => isset($_SERVER['SERVER_SOFTWARE']) ? $_SERVER['SERVER_SOFTWARE'] : 'Unknown',
                'status' => 'info',
            ),
        );
    }

    private function check_plugin() {
        $ai_engine_active = class_exists('Meow_MWAI_Core');
        $db_version_raw = get_option('aips_db_version', 'Unknown');
        $db_version = is_scalar($db_version_raw) ? trim((string) $db_version_raw) : 'Unknown';
        $db_version_is_valid = (bool) preg_match('/^\d+(?:\.\d+)*(?:[-+~._][0-9A-Za-z.-]+)?$/', $db_version);
        $db_version_matches = $db_version_is_valid && version_compare($db_version, AIPS_VERSION, '==');

        $db_version_details = array();
        if (!$db_version_matches) {
            $db_version_details[] = sprintf(
                /* translators: %s: stored database version */
                __('Stored database version: %s', 'ai-post-scheduler'),
                empty($db_version) ? __('Unknown', 'ai-post-scheduler') : $db_version
            );
            $db_version_details[] = sprintf(
                /* translators: %s: expected plugin database version */
                __('Expected database version for this plugin build: %s', 'ai-post-scheduler'),
                AIPS_VERSION
            );
            $db_version_details[] = __('This usually means the database schema is from a different plugin build or an upgrade did not complete.', 'ai-post-scheduler');
            $db_version_details[] = __('Try "Repair DB Tables" first. If this persists, run "Reinstall DB Tables" with backup enabled.', 'ai-post-scheduler');
        }

        return array(
            'version' => array(
                'label' => 'Plugin Version',
                'value' => AIPS_VERSION,
                'status' => 'ok',
            ),
            'db_version' => array(
                'label' => 'Database Version',
                'value' => empty($db_version) ? 'Unknown' : $db_version,
                'status' => $db_version_matches ? 'ok' : 'warning',
                'details' => $db_version_details,
            ),
            'ai_engine' => array(
                'label' => 'AI Engine Plugin',
                'value' => $ai_engine_active ? 'Active' : 'Missing',
                'status' => $ai_engine_active ? 'ok' : 'error',
            ),
        );
    }

    private function check_database() {
        global $wpdb;
        
        // Get expected columns from AIPS_DB_Manager (single source of truth)
        $tables = AIPS_DB_Manager::get_expected_columns();

        $results = array();

        foreach ($tables as $table_name => $columns) {
            $full_table_name = $wpdb->prefix . $table_name;
            $table_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $full_table_name)) === $full_table_name;

            if (!$table_exists) {
                $results[$table_name] = array(
                    'label' => "Table: $table_name",
                    'value' => 'Missing',
                    'status' => 'error',
                );
                continue;
            }

            $missing_columns = array();
            $db_columns = $wpdb->get_results("SHOW COLUMNS FROM $full_table_name", ARRAY_A);
            $db_column_names = array_column($db_columns, 'Field');

            foreach ($columns as $col) {
                if (!in_array($col, $db_column_names)) {
                    $missing_columns[] = $col;
                }
            }

            if (!empty($missing_columns)) {
                $results[$table_name] = array(
                    'label' => "Table: $table_name",
                    'value' => 'Missing columns: ' . implode(', ', $missing_columns),
                    'status' => 'error',
                );
            } else {
                $results[$table_name] = array(
                    'label' => "Table: $table_name",
                    'value' => 'OK',
                    'status' => 'ok',
                );
            }
        }

        return $results;
    }

    private function check_filesystem() {
        $upload_dir = wp_upload_dir();
        $log_dir = $upload_dir['basedir'] . '/aips-logs';

        $exists = file_exists($log_dir);
        $writable = wp_is_writable($log_dir);

        return array(
            'log_dir' => array(
                'label' => 'Log Directory',
                'value' => $exists ? ($writable ? 'Writable' : 'Not Writable') : 'Missing',
                'status' => ($exists && $writable) ? 'ok' : 'error',
            ),
        );
    }

    private function check_cron() {
        $cron_events = AI_Post_Scheduler::get_cron_events();

        $status = array();

        foreach ($cron_events as $event_hook => $event_config) {
            $next_run = wp_next_scheduled($event_hook);
            $status[$event_hook] = array(
                'label' => isset($event_config['label']) ? $event_config['label'] : $event_hook,
                'value' => $next_run ? date('Y-m-d H:i:s', $next_run) : 'Not Scheduled',
                'status' => $next_run ? 'ok' : 'error',
            );
        }

        return $status;
    }

    private function check_logs() {
        $logs_data = array();

        // Check AIPS logs
        $logger = new AIPS_Logger();
        $log_files = $logger->get_log_files();

        if (!empty($log_files)) {
            // Get most recent file
            usort($log_files, function($a, $b) {
                return strtotime($b['modified']) - strtotime($a['modified']);
            });
            $recent_log = $log_files[0];
            $upload_dir = wp_upload_dir();
            $log_path = $upload_dir['basedir'] . '/aips-logs/' . $recent_log['name'];

            $errors = $this->scan_file_for_errors($log_path);

            $logs_data['plugin_log'] = array(
                'label' => 'Plugin Log (' . $recent_log['name'] . ')',
                'value' => empty($errors) ? 'No recent errors' : count($errors) . ' errors found',
                'status' => empty($errors) ? 'ok' : 'warning',
                'details' => $errors
            );
        } else {
            $logs_data['plugin_log'] = array(
                'label' => 'Plugin Log',
                'value' => 'No log files found',
                'status' => 'info',
            );
        }

        // Check WP Debug Log
        if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
            $debug_log_path = WP_CONTENT_DIR . '/debug.log';
            if (is_string(WP_DEBUG_LOG)) {
                $debug_log_path = WP_DEBUG_LOG;
            }

            if (file_exists($debug_log_path)) {
                $errors = $this->scan_file_for_errors($debug_log_path, 50, true);
                $logs_data['wp_debug_log'] = array(
                    'label' => 'WP Debug Log',
                    'value' => empty($errors) ? 'No recent errors from this plugin' : count($errors) . ' errors found',
                    'status' => empty($errors) ? 'ok' : 'warning',
                    'details' => $errors
                );
            }
        }

        return $logs_data;
    }

    /**
     * Scan a log file for errors.
     *
     * Defensively reads the last N lines of a file, handling empty files and
     * filesystem read errors.
     *
     * @param string $file_path     The path to the file to scan.
     * @param int    $lines         The number of lines to read from the end.
     * @param bool   $filter_plugin Whether to filter lines containing the plugin slug.
     * @return array The extracted error lines.
     */
    private function scan_file_for_errors($file_path, $lines = 100, $filter_plugin = false) {
        if (!file_exists($file_path)) {
            return array();
        }

        $chunk_size = 1024 * 100; // Read last 100KB
        $file_size = filesize($file_path);

        if ($file_size === false || $file_size === 0) {
            return array();
        }

        $handle = fopen($file_path, 'rb');
        if ($handle === false) {
            return array();
        }

        $offset = max(0, $file_size - $chunk_size);
        fseek($handle, $offset);

        $content = fread($handle, $chunk_size);
        fclose($handle);

        if ($content === false) {
            return array();
        }

        $file_lines = explode("\n", $content);

        // If we didn't read the whole file, the first line might be partial, so skip it
        if ($offset > 0 && !empty($file_lines)) {
            array_shift($file_lines);
        }

        // Take the last $lines
        $file_lines = array_slice($file_lines, -$lines);

        $errors = array();
        foreach ($file_lines as $line) {
            $line = trim($line);
            if (empty($line)) continue;

            if ($filter_plugin && strpos($line, 'ai-post-scheduler') === false) {
                continue;
            }

            if (stripos($line, 'error') !== false || stripos($line, 'warning') !== false || stripos($line, 'fatal') !== false) {
                $errors[] = $line;
            }
        }

        return array_reverse($errors); // Most recent first
    }
}
