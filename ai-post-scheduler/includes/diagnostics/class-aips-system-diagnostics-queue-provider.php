<?php
if (!defined('ABSPATH')) {
    return;
}

/**
 * AIPS_System_Diagnostics_Queue_Provider
 */
class AIPS_System_Diagnostics_Queue_Provider implements AIPS_System_Diagnostic_Provider_Interface {

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

    /**
     * @return array<string, mixed>
     */
    public function get_diagnostics(): array {
        return array(
            'queue health'       => $this->check_queue_health(),
            'generation metrics' => $this->check_generation_metrics(),
            'resilience'         => $this->check_resilience(),
        );
    }

















}
