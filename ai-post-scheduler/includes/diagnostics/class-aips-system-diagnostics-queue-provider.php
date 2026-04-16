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

/**
 * Queue health checks: backlog, stuck jobs, retry saturation, and circuit-breaker state.
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
$backlog_total   = $qh['pending_count'] + $qh['partial_count'];
$backlog_status  = $backlog_total === 0 ? 'ok' : 'info';
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
$sat        = $qh['retry_saturation_pct'];
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
 * Generation performance and reliability metrics (30-day window).
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
$success_status         = $m['success_rate'] >= self::METRIC_OK_THRESHOLD ? 'ok' : ( $m['success_rate'] >= self::METRIC_WARN_THRESHOLD ? 'warning' : 'error' );
$checks['success_rate'] = array(
'label'   => __( 'Generation Success Rate (30d)', 'ai-post-scheduler' ),
'value'   => $m['total'] > 0 ? $m['success_rate'] . '%' : __( 'No data', 'ai-post-scheduler' ),
'status'  => $m['total'] > 0 ? $success_status : 'info',
'details' => $m['total'] > 0 ? array(
sprintf(
__( 'Total: %d | Completed: %d | Failed: %d | Partial: %d', 'ai-post-scheduler' ),
$m['total'], $m['successful'], $m['failed'], $m['partial']
),
) : array(),
);

// Generation duration percentiles
$checks['generation_duration'] = array(
'label'   => __( 'Generation Duration (30d, completed)', 'ai-post-scheduler' ),
'value'   => $m['avg_duration_seconds'] > 0
? sprintf( __( 'Avg %ds', 'ai-post-scheduler' ), $m['avg_duration_seconds'] )
: __( 'No data', 'ai-post-scheduler' ),
'status'  => 'info',
'details' => $m['avg_duration_seconds'] > 0 ? array(
sprintf(
__( 'p50: %ds | p95: %ds', 'ai-post-scheduler' ),
$m['p50_duration_seconds'], $m['p95_duration_seconds']
),
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
$img_status                   = $m['image_failure_rate'] <= self::IMAGE_FAIL_OK_THRESHOLD ? 'ok' : ( $m['image_failure_rate'] <= self::IMAGE_FAIL_WARN_THRESHOLD ? 'warning' : 'error' );
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

/**
 * Circuit breaker and rate limiter status from the resilience service.
 *
 * @return array<string, array<string, mixed>>
 */
private function check_resilience() {
if ( ! class_exists( 'AIPS_Resilience_Service' ) ) {
return array(
'unavailable' => array(
'label'  => __( 'Resilience', 'ai-post-scheduler' ),
'value'  => __( 'Resilience service not available', 'ai-post-scheduler' ),
'status' => 'info',
),
);
}

$resilience = new AIPS_Resilience_Service();
$cb         = $resilience->get_circuit_breaker_status();
$rl         = $resilience->get_rate_limiter_status();

$checks = array();

// Circuit breaker
$cb_state    = isset( $cb['state'] ) ? $cb['state'] : 'unknown';
$cb_failures = isset( $cb['failures'] ) ? (int) $cb['failures'] : 0;
$cb_last     = isset( $cb['last_failure_time'] ) ? (int) $cb['last_failure_time'] : 0;

$cb_status_map = array(
'closed'    => 'ok',
'half_open' => 'warning',
'open'      => 'error',
);
$cb_health = isset( $cb_status_map[ $cb_state ] ) ? $cb_status_map[ $cb_state ] : 'info';

$cb_details = array(
sprintf( __( 'State: %s', 'ai-post-scheduler' ), strtoupper( $cb_state ) ),
sprintf( __( 'Failure count: %d', 'ai-post-scheduler' ), $cb_failures ),
);
if ( $cb_last > 0 ) {
$cb_details[] = sprintf( __( 'Last failure: %s', 'ai-post-scheduler' ), wp_date( 'Y-m-d H:i:s', $cb_last ) );
}

$checks['circuit_breaker'] = array(
'label'   => __( 'Circuit Breaker', 'ai-post-scheduler' ),
'value'   => sprintf( __( 'State: %s | Failures: %d', 'ai-post-scheduler' ), strtoupper( $cb_state ), $cb_failures ),
'status'  => $cb_health,
'details' => $cb_details,
'cb_open' => $cb_state === 'open',
);

// Rate limiter
if ( $rl['enabled'] ) {
$rl_remaining = (int) $rl['remaining'];
$rl_current   = (int) $rl['current_requests'];
$rl_max       = (int) $rl['max_requests'];

$rl_health = $rl_remaining > 0 ? 'ok' : 'error';

$checks['rate_limiter'] = array(
'label'  => __( 'Rate Limiter', 'ai-post-scheduler' ),
'value'  => sprintf(
/* translators: 1: current 2: max 3: remaining */
__( '%1$d/%2$d requests used (%3$d remaining)', 'ai-post-scheduler' ),
$rl_current,
$rl_max,
$rl_remaining
),
'status' => $rl_health,
);
} else {
$checks['rate_limiter'] = array(
'label'  => __( 'Rate Limiter', 'ai-post-scheduler' ),
'value'  => __( 'Disabled', 'ai-post-scheduler' ),
'status' => 'info',
);
}

return $checks;
}
}
