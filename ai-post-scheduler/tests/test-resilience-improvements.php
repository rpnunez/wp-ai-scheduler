<?php
/**
 * Tests for AIPS_Resilience_Service improvements:
 *   - Selective retries (non-retryable error codes abort retry loop)
 *   - Immediate circuit opening for IMMEDIATE_OPEN_CODES
 *   - Error-code extraction helper
 *
 * @package AI_Post_Scheduler
 */

class Test_AIPS_Resilience_Improvements extends WP_UnitTestCase {

// -----------------------------------------------------------------------
// Helpers
// -----------------------------------------------------------------------

/**
 * Create a resilience service with circuit breaker ENABLED and a low threshold.
 *
 * @param int $threshold Circuit breaker failure threshold.
 * @return AIPS_Resilience_Service
 */
private function make_cb_enabled_service( $threshold = 3 ) {
$GLOBALS['aips_test_options'] = array(
'aips_enable_circuit_breaker'    => true,
'aips_circuit_breaker_threshold' => $threshold,
'aips_circuit_breaker_timeout'   => 300,
'aips_enable_retry'              => true,
'aips_retry_max_attempts'        => 3,
'aips_retry_initial_delay'       => 0,
'aips_retry_jitter'              => false,
'aips_enable_rate_limiting'      => false,
);

// Delete any persisted circuit-breaker state from previous test
delete_transient( 'aips_circuit_breaker_state' );

return new AIPS_Resilience_Service();
}

/**
 * Create a resilience service with retry ENABLED but circuit breaker DISABLED.
 *
 * @param int $max_attempts Number of retry attempts.
 * @return AIPS_Resilience_Service
 */
private function make_retry_service( $max_attempts = 3 ) {
$GLOBALS['aips_test_options'] = array(
'aips_enable_circuit_breaker' => false,
'aips_enable_retry'           => true,
'aips_retry_max_attempts'     => $max_attempts,
'aips_retry_initial_delay'    => 0,
'aips_retry_jitter'           => false,
'aips_enable_rate_limiting'   => false,
);

delete_transient( 'aips_circuit_breaker_state' );

return new AIPS_Resilience_Service();
}

// -----------------------------------------------------------------------
// extract_error_code_from_message
// -----------------------------------------------------------------------

public function test_extract_error_code_finds_non_retryable_code_in_plain_message() {
$code = AIPS_Resilience_Service::extract_error_code_from_message(
'OpenAI error: invalid_api_key — check your credentials.'
);
$this->assertSame( 'invalid_api_key', $code );
}

public function test_extract_error_code_finds_immediate_open_code_in_plain_message() {
$code = AIPS_Resilience_Service::extract_error_code_from_message(
'You have exceeded your current quota (insufficient_quota). Please check your plan.'
);
$this->assertSame( 'insufficient_quota', $code );
}

public function test_extract_error_code_returns_empty_string_for_unknown_message() {
$code = AIPS_Resilience_Service::extract_error_code_from_message(
'Unexpected network error. Please retry later.'
);
$this->assertSame( '', $code );
}

public function test_extract_error_code_parses_json_error_block() {
$message = 'Error 400: {"error":{"message":"Invalid API key","type":"invalid_request_error","code":"invalid_api_key"}}';
$code    = AIPS_Resilience_Service::extract_error_code_from_message( $message );
$this->assertSame( 'invalid_api_key', $code );
}

public function test_extract_error_code_parses_json_error_type_when_no_code() {
$message = 'Error 400: {"error":{"message":"Context limit","type":"context_length_exceeded"}}';
$code    = AIPS_Resilience_Service::extract_error_code_from_message( $message );
$this->assertSame( 'context_length_exceeded', $code );
}

public function test_extract_error_code_is_case_insensitive() {
$code = AIPS_Resilience_Service::extract_error_code_from_message(
'Error: Context_Length_Exceeded for this request.'
);
$this->assertSame( 'context_length_exceeded', $code );
}

// -----------------------------------------------------------------------
// NON_RETRYABLE_CODES constant
// -----------------------------------------------------------------------

public function test_non_retryable_codes_constant_is_not_empty() {
$this->assertNotEmpty( AIPS_Resilience_Service::NON_RETRYABLE_CODES );
}

public function test_non_retryable_codes_contains_invalid_api_key() {
$this->assertContains( 'invalid_api_key', AIPS_Resilience_Service::NON_RETRYABLE_CODES );
}

public function test_non_retryable_codes_contains_context_length_exceeded() {
$this->assertContains( 'context_length_exceeded', AIPS_Resilience_Service::NON_RETRYABLE_CODES );
}

// -----------------------------------------------------------------------
// IMMEDIATE_OPEN_CODES constant
// -----------------------------------------------------------------------

public function test_immediate_open_codes_contains_insufficient_quota() {
$this->assertContains( 'insufficient_quota', AIPS_Resilience_Service::IMMEDIATE_OPEN_CODES );
}

// -----------------------------------------------------------------------
// Selective retry behaviour
// -----------------------------------------------------------------------

public function test_execute_with_retry_calls_function_once_for_non_retryable_error() {
$service = $this->make_retry_service( 3 );
$calls   = 0;

$result = $service->execute_with_retry(
function() use ( &$calls ) {
$calls++;
return new WP_Error( 'invalid_api_key', 'Bad API key' );
},
'text',
'prompt',
array()
);

$this->assertInstanceOf( WP_Error::class, $result );
$this->assertSame( 1, $calls, 'Should not retry a non-retryable error' );
}

public function test_execute_with_retry_retries_for_retryable_error() {
$service = $this->make_retry_service( 3 );
$calls   = 0;

$result = $service->execute_with_retry(
function() use ( &$calls ) {
$calls++;
return new WP_Error( 'generation_failed', 'Temporary failure' );
},
'text',
'prompt',
array()
);

$this->assertInstanceOf( WP_Error::class, $result );
$this->assertSame( 3, $calls, 'Should retry up to max_attempts for a retryable error' );
}

public function test_execute_with_retry_returns_success_on_subsequent_attempt() {
$service = $this->make_retry_service( 3 );
$calls   = 0;

$result = $service->execute_with_retry(
function() use ( &$calls ) {
$calls++;
if ( $calls < 2 ) {
return new WP_Error( 'generation_failed', 'Transient failure' );
}
return 'Generated content';
},
'text',
'prompt',
array()
);

$this->assertSame( 'Generated content', $result );
$this->assertSame( 2, $calls );
}

// -----------------------------------------------------------------------
// Immediate circuit opening
// -----------------------------------------------------------------------

public function test_record_failure_with_insufficient_quota_opens_circuit_immediately() {
$service = $this->make_cb_enabled_service( 10 ); // high threshold

$service->record_failure( 'insufficient_quota' );

$status = $service->get_circuit_breaker_status();
$this->assertSame( 'open', $status['state'], 'Circuit should open immediately for insufficient_quota' );
}

public function test_record_failure_without_error_code_respects_threshold() {
$service = $this->make_cb_enabled_service( 3 );

// 1st and 2nd failures — circuit should remain closed
$service->record_failure( '' );
$status = $service->get_circuit_breaker_status();
$this->assertSame( 'closed', $status['state'] );

$service->record_failure( '' );
$status = $service->get_circuit_breaker_status();
$this->assertSame( 'closed', $status['state'] );

// 3rd failure — threshold reached, circuit should open
$service->record_failure( '' );
$status = $service->get_circuit_breaker_status();
$this->assertSame( 'open', $status['state'] );
}

public function test_record_failure_with_non_retryable_non_immediate_code_respects_threshold() {
$service = $this->make_cb_enabled_service( 5 );

// A non-retryable code that is NOT in IMMEDIATE_OPEN_CODES should not
// bypass the threshold check
$service->record_failure( 'invalid_api_key' );
$status = $service->get_circuit_breaker_status();
$this->assertNotSame( 'open', $status['state'],
'invalid_api_key is not in IMMEDIATE_OPEN_CODES so threshold should govern' );
}

// -----------------------------------------------------------------------
// Notification action fired on circuit-open
// -----------------------------------------------------------------------

public function test_circuit_breaker_opened_action_fires_when_circuit_transitions_to_open() {
$service  = $this->make_cb_enabled_service( 1 );
$received = null;

add_action( 'aips_circuit_breaker_opened', function( $payload ) use ( &$received ) {
$received = $payload;
} );

$service->record_failure( '' );

remove_all_actions( 'aips_circuit_breaker_opened' );

$this->assertNotNull( $received, 'aips_circuit_breaker_opened action should have fired' );
$this->assertArrayHasKey( 'failures', $received );
}

public function test_circuit_breaker_opened_action_fires_only_once_per_transition() {
$service = $this->make_cb_enabled_service( 1 );
$count   = 0;

add_action( 'aips_circuit_breaker_opened', function() use ( &$count ) {
$count++;
} );

// First record_failure opens the circuit → action fires
$service->record_failure( '' );
// Second record_failure keeps circuit open → action should NOT fire again
$service->record_failure( '' );

remove_all_actions( 'aips_circuit_breaker_opened' );

$this->assertSame( 1, $count, 'Action should fire exactly once per open transition' );
}

// -----------------------------------------------------------------------
// Rate-limit notification action
// -----------------------------------------------------------------------

public function test_rate_limit_reached_action_fires_when_limit_exceeded() {
$GLOBALS['aips_test_options'] = array(
'aips_enable_circuit_breaker' => false,
'aips_enable_retry'           => false,
'aips_enable_rate_limiting'   => true,
'aips_rate_limit_requests'    => 2,
'aips_rate_limit_period'      => 60,
);
delete_transient( 'aips_rate_limiter_requests' );

$service  = new AIPS_Resilience_Service();
$received = null;

add_action( 'aips_rate_limit_reached', function( $payload ) use ( &$received ) {
$received = $payload;
} );

$service->check_rate_limit(); // 1st — allowed
$service->check_rate_limit(); // 2nd — allowed (hits limit)
$service->check_rate_limit(); // 3rd — blocked, fires action

remove_all_actions( 'aips_rate_limit_reached' );
delete_transient( 'aips_rate_limiter_requests' );

$this->assertNotNull( $received, 'aips_rate_limit_reached action should have fired' );
$this->assertArrayHasKey( 'max_requests', $received );
}
}
