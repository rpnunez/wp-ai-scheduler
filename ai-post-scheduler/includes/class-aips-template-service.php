<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Template Service
 *
 * Provides higher-level operations around templates by composing
 * repositories and utility classes.
 */
class AIPS_Template_Service {

    private $template_repo;
    private $schedule_repo;
    private $history_repo;
    private $interval_calculator;

    public function __construct($template_repo = null, $schedule_repo = null, $history_repo = null, $interval_calculator = null) {
        $this->template_repo = $template_repo ?: new AIPS_Template_Repository();
        $this->schedule_repo = $schedule_repo ?: new AIPS_Schedule_Repository();
        $this->history_repo = $history_repo ?: new AIPS_History_Repository();
        $this->interval_calculator = $interval_calculator ?: new AIPS_Interval_Calculator();
    }

    public function get_all($active_only = false) {
        return $this->template_repo->get_all($active_only);
    }

    public function get($id) {
        return $this->template_repo->get_by_id($id);
    }

    public function save($data) {
        // Delegate straight to repository which already sanitizes data.
        if (!empty($data['id'])) {
            return $this->template_repo->update(absint($data['id']), $data) ? absint($data['id']) : false;
        }
        return $this->template_repo->create($data);
    }

    public function delete($id) {
        return $this->template_repo->delete($id);
    }

    public function get_pending_stats($template_id) {
        // Use schedule repository to fetch active schedules for a template
        $schedules = $this->schedule_repo->get_by_template($template_id);

        $stats = array('today' => 0, 'week' => 0, 'month' => 0);
        if (empty($schedules)) {
            return $stats;
        }

        $now = current_time('timestamp');
        $today_end = strtotime('today 23:59:59', $now);
        $week_end = strtotime('+7 days', $now);
        $month_end = strtotime('+30 days', $now);

        foreach ($schedules as $schedule) {
            if (empty($schedule->is_active)) {
                continue;
            }

            $cursor = strtotime($schedule->next_run);
            $frequency = $schedule->frequency;

            $max_iterations = 100;
            $i = 0;

            while ($cursor <= $month_end && $i < $max_iterations) {
                if ($cursor <= $today_end) {
                    $stats['today']++;
                }

                if ($cursor <= $week_end) {
                    $stats['week']++;
                }

                if ($cursor <= $month_end) {
                    $stats['month']++;
                } else {
                    break;
                }

                if ($frequency === 'once') {
                    break;
                }

                $next = $this->interval_calculator->calculate_next_run($frequency, date('Y-m-d H:i:s', $cursor));
                $cursor = strtotime($next);
                $i++;
            }
        }

        return $stats;
    }

    public function get_all_pending_stats() {
        $cached_stats = get_transient('aips_pending_schedule_stats');
        if ($cached_stats !== false) {
            return $cached_stats;
        }

        // Fetch minimal columns for active schedules
        $schedules = $this->schedule_repo->get_active_minimal();

        $stats = array();
        if (empty($schedules)) {
            set_transient('aips_pending_schedule_stats', $stats, HOUR_IN_SECONDS);
            return $stats;
        }

        $now = current_time('timestamp');
        $today_end = strtotime('today 23:59:59', $now);
        $week_end = strtotime('+7 days', $now);
        $month_end = strtotime('+30 days', $now);

        foreach ($schedules as $schedule) {
            $tid = $schedule->template_id;
            if (!isset($stats[$tid])) {
                $stats[$tid] = array('today' => 0, 'week' => 0, 'month' => 0);
            }

            $cursor = strtotime($schedule->next_run);
            $frequency = $schedule->frequency;

            $max_iterations = 100;
            $i = 0;

            while ($cursor <= $month_end && $i < $max_iterations) {
                if ($cursor <= $today_end) {
                    $stats[$tid]['today']++;
                }

                if ($cursor <= $week_end) {
                    $stats[$tid]['week']++;
                }

                if ($cursor <= $month_end) {
                    $stats[$tid]['month']++;
                } else {
                    break;
                }

                if ($frequency === 'once') {
                    break;
                }

                $next = $this->interval_calculator->calculate_next_run($frequency, date('Y-m-d H:i:s', $cursor));
                $cursor = strtotime($next);
                $i++;
            }
        }

        set_transient('aips_pending_schedule_stats', $stats, HOUR_IN_SECONDS);
        return $stats;
    }

    /**
     * Return the next run timestamp for a given frequency and base time.
     * This mirrors the legacy helper used by AIPS_Templates.
     *
     * @param string $frequency
     * @param int|string $base_time Unix timestamp or date string
     * @return int Unix timestamp of next run
     */
    public function calculate_next_run($frequency, $base_time) {
        $timestamp = is_numeric($base_time) ? intval($base_time) : strtotime($base_time);
        $next = $this->interval_calculator->calculate_next_run($frequency, date('Y-m-d H:i:s', $timestamp));
        return strtotime($next);
    }

}
