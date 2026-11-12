<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Shared success/failure rate arithmetic for generation history records.
 *
 * A history row is only meaningful for rate reporting once it has reached a
 * terminal state. Rows still in flight (`pending`, `processing`, `queued`)
 * inflate a raw COUNT(*) and drive the reported rate toward zero, which is why
 * a dashboard showing 62 completed and 12 failed could report 2.6%.
 *
 * Terminal outcomes are: completed, failed, partial.
 */
class AIPS_Outcome_Rate {

    /**
     * Number of records that reached a terminal outcome.
     *
     * @param int $completed Completed count.
     * @param int $failed    Failed count.
     * @param int $partial   Partial count.
     * @return int
     */
    public static function resolved($completed, $failed, $partial = 0) {
        return max(0, (int) $completed) + max(0, (int) $failed) + max(0, (int) $partial);
    }

    /**
     * Percentage of resolved records that completed successfully.
     *
     * Partial generations count as unsuccessful: they produced no usable post.
     *
     * @param int   $completed Completed count.
     * @param int   $failed    Failed count.
     * @param int   $partial   Partial count.
     * @param float $when_none Value returned when nothing has resolved yet.
     * @return float Percentage rounded to one decimal place.
     */
    public static function success_rate($completed, $failed, $partial = 0, $when_none = 0.0) {
        $resolved = self::resolved($completed, $failed, $partial);

        if ($resolved <= 0) {
            return (float) $when_none;
        }

        return round((max(0, (int) $completed) / $resolved) * 100, 1);
    }

    /**
     * Percentage of resolved records that failed outright.
     *
     * @param int   $completed Completed count.
     * @param int   $failed    Failed count.
     * @param int   $partial   Partial count.
     * @param float $when_none Value returned when nothing has resolved yet.
     * @return float Percentage rounded to one decimal place.
     */
    public static function failure_rate($completed, $failed, $partial = 0, $when_none = 0.0) {
        $resolved = self::resolved($completed, $failed, $partial);

        if ($resolved <= 0) {
            return (float) $when_none;
        }

        return round((max(0, (int) $failed) / $resolved) * 100, 1);
    }
}
