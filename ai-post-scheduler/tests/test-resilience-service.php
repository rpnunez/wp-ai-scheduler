<?php
/**
 * Test case for AIPS_Resilience_Service retry lifecycle improvements.
 *
 * Validates:
 * - execute_with_retry: non_retryable_codes aborts the loop immediately
 * - execute_safely: record_success / record_failure called once per operation
 *   based on the final outcome (not per-attempt)
 * - Callbacks must NOT call record_success/record_failure themselves
 * - generate_json fallback is invoked after retries are exhausted, not inside loop
 *
 * @package AI_Post_Scheduler
 * @since 1.11.0
 */
class Test_AIPS_Resilience_Service extends WP_UnitTestCase {

	// -----------------------------------------------------------------------
	// Helpers
	// -----------------------------------------------------------------------

	/**
	 * Create a resilience service with retry enabled (max 3 attempts, 0-delay).
	 *
	 * @param AIPS_Logger|null $logger Optional logger; uses a real AIPS_Logger when null.
	 */
	private function make_resilience_service( $logger = null ) {
		$config = $this->getMockBuilder( 'AIPS_Config' )
			->disableOriginalConstructor()
			->onlyMethods( array( 'get_retry_config', 'get_circuit_breaker_config', 'get_rate_limit_config' ) )
			->getMock();

		$config->method( 'get_retry_config' )->willReturn( array(
			'enabled'       => true,
			'max_attempts'  => 3,
			'initial_delay' => 0,
			'jitter'        => false,
		) );
		$config->method( 'get_circuit_breaker_config' )->willReturn( array(
			'enabled'           => false, // Disabled so tests focus on retry logic
			'failure_threshold' => 5,
			'timeout'           => 60,
		) );
		$config->method( 'get_rate_limit_config' )->willReturn( array(
			'enabled' => false,
			'requests' => 60,
			'period'   => 60,
		) );

		$logger = $logger ?: new AIPS_Logger();
		return new AIPS_Resilience_Service( $logger, $config );
	}

	// -----------------------------------------------------------------------
	// execute_with_retry tests
	// -----------------------------------------------------------------------

	/**
	 * Callback that always fails should exhaust max_attempts.
	 */
	public function test_execute_with_retry_exhausts_all_attempts_on_permanent_failure() {
		$service      = $this->make_resilience_service();
		$call_count   = 0;

		$result = $service->execute_with_retry(
			function () use ( &$call_count ) {
				$call_count++;
				return new WP_Error( 'transient_error', 'Temporary failure' );
			},
			'text',
			'test prompt',
			array()
		);

		$this->assertInstanceOf( 'WP_Error', $result );
		$this->assertEquals( 3, $call_count, 'Should attempt exactly max_attempts (3) times' );
	}

	/**
	 * Callback that succeeds on second attempt should return result and stop retrying.
	 */
	public function test_execute_with_retry_stops_on_success() {
		$service    = $this->make_resilience_service();
		$call_count = 0;

		$result = $service->execute_with_retry(
			function () use ( &$call_count ) {
				$call_count++;
				if ( $call_count < 2 ) {
					return new WP_Error( 'transient_error', 'Temporary failure' );
				}
				return 'success_value';
			},
			'text',
			'test prompt',
			array()
		);

		$this->assertEquals( 'success_value', $result );
		$this->assertEquals( 2, $call_count, 'Should stop after first success' );
	}

	/**
	 * Non-retryable error code should abort the loop after the first attempt.
	 */
	public function test_execute_with_retry_aborts_on_non_retryable_code() {
		$service    = $this->make_resilience_service();
		$call_count = 0;

		$result = $service->execute_with_retry(
			function () use ( &$call_count ) {
				$call_count++;
				return new WP_Error( 'permanent_error', 'This will never succeed' );
			},
			'text',
			'test prompt',
			array(),
			array( 'non_retryable_codes' => array( 'permanent_error' ) )
		);

		$this->assertInstanceOf( 'WP_Error', $result );
		$this->assertEquals( 'permanent_error', $result->get_error_code() );
		$this->assertEquals( 1, $call_count, 'Should attempt only once when error code is non-retryable' );
	}

	/**
	 * Non-retryable codes that do not match the actual error should not block retries.
	 */
	public function test_execute_with_retry_retries_when_non_retryable_code_does_not_match() {
		$service    = $this->make_resilience_service();
		$call_count = 0;

		$result = $service->execute_with_retry(
			function () use ( &$call_count ) {
				$call_count++;
				return new WP_Error( 'transient_error', 'Retry me' );
			},
			'text',
			'test prompt',
			array(),
			array( 'non_retryable_codes' => array( 'permanent_error' ) )
		);

		$this->assertInstanceOf( 'WP_Error', $result );
		$this->assertEquals( 3, $call_count, 'Should exhaust all attempts when code is not in non_retryable_codes' );
	}

	/**
	 * Multiple non-retryable codes are supported.
	 */
	public function test_execute_with_retry_supports_multiple_non_retryable_codes() {
		$service    = $this->make_resilience_service();
		$call_count = 0;

		$result = $service->execute_with_retry(
			function () use ( &$call_count ) {
				$call_count++;
				return new WP_Error( 'second_permanent', 'Also permanent' );
			},
			'text',
			'test prompt',
			array(),
			array( 'non_retryable_codes' => array( 'first_permanent', 'second_permanent' ) )
		);

		$this->assertInstanceOf( 'WP_Error', $result );
		$this->assertEquals( 1, $call_count, 'Should abort on any matching non-retryable code' );
	}

	// -----------------------------------------------------------------------
	// execute_safely — circuit-breaker accounting tests
	// -----------------------------------------------------------------------

	/**
	 * Helper: build a resilience service with the circuit breaker ENABLED.
	 *
	 * @param int              $threshold CB failure threshold.
	 * @param AIPS_Logger|null $logger    Optional logger; uses a real AIPS_Logger when null.
	 */
	private function make_resilience_service_with_circuit_breaker( $threshold = 3, $logger = null ) {
		$config = $this->getMockBuilder( 'AIPS_Config' )
			->disableOriginalConstructor()
			->onlyMethods( array( 'get_retry_config', 'get_circuit_breaker_config', 'get_rate_limit_config' ) )
			->getMock();

		$config->method( 'get_retry_config' )->willReturn( array(
			'enabled'       => true,
			'max_attempts'  => 3,
			'initial_delay' => 0,
			'jitter'        => false,
		) );
		$config->method( 'get_circuit_breaker_config' )->willReturn( array(
			'enabled'           => true,
			'failure_threshold' => $threshold,
			'timeout'           => 60,
		) );
		$config->method( 'get_rate_limit_config' )->willReturn( array(
			'enabled' => false,
			'requests' => 60,
			'period'   => 60,
		) );

		$logger = $logger ?: new AIPS_Logger();
		return new AIPS_Resilience_Service( $logger, $config );
	}

	/**
	 * A successful call should record_success once (not per retry attempt).
	 * After success the circuit breaker failure count should be 0.
	 */
	public function test_execute_safely_records_success_once_after_eventual_success() {
		$service    = $this->make_resilience_service_with_circuit_breaker( 3 );
		$call_count = 0;

		// Fail once then succeed — simulates a transient failure
		$result = $service->execute_safely(
			function () use ( &$call_count ) {
				$call_count++;
				if ( $call_count === 1 ) {
					return new WP_Error( 'transient', 'Temp failure' );
				}
				return 'ok';
			},
			'text',
			'prompt',
			array()
		);

		$this->assertEquals( 'ok', $result );
		$status = $service->get_circuit_breaker_status();
		$this->assertEquals( 0, $status['failures'],
			'Circuit-breaker failure count must be 0 after an operation that ultimately succeeded — per-attempt transient failures must NOT inflate the counter' );
		$this->assertEquals( 'closed', $status['state'] );
	}

	/**
	 * A failing call (all retries exhausted) should record_failure exactly once.
	 */
	public function test_execute_safely_records_failure_once_after_all_retries_exhausted() {
		// Threshold of 5 ensures one operation failure does not open the circuit
		$service = $this->make_resilience_service_with_circuit_breaker( 5 );

		$service->execute_safely(
			function () {
				return new WP_Error( 'gen_failed', 'Always fails' );
			},
			'text',
			'prompt',
			array()
		);

		$status = $service->get_circuit_breaker_status();
		$this->assertEquals( 1, $status['failures'],
			'Circuit-breaker should record exactly one failure per execute_safely call, regardless of how many retry attempts were made internally' );
	}

	/**
	 * A blocked call (circuit-breaker open) should NOT record an additional failure.
	 */
	public function test_execute_safely_does_not_record_failure_when_circuit_is_open() {
		$service = $this->make_resilience_service_with_circuit_breaker( 2 );

		// Exhaust the threshold to open the circuit
		$service->execute_safely( function () { return new WP_Error( 'e', 'fail' ); }, 'text', 'p', array() );
		$service->execute_safely( function () { return new WP_Error( 'e', 'fail' ); }, 'text', 'p', array() );

		$status_before = $service->get_circuit_breaker_status();
		$this->assertEquals( 'open', $status_before['state'], 'Circuit should be open after threshold failures' );
		$failures_before = $status_before['failures'];

		// This call should be blocked; failure count must not increase further
		$blocked_result = $service->execute_safely( function () { return 'unreachable'; }, 'text', 'p', array() );

		$this->assertInstanceOf( 'WP_Error', $blocked_result );
		$this->assertEquals( 'circuit_breaker_open', $blocked_result->get_error_code() );

		$status_after = $service->get_circuit_breaker_status();
		$this->assertEquals( $failures_before, $status_after['failures'],
			'Blocked calls must not add to the failure counter' );
	}

	// -----------------------------------------------------------------------
	// execute_safely — retry_opts forwarding
	// -----------------------------------------------------------------------

	/**
	 * Non-retryable codes passed to execute_safely are forwarded to the retry loop.
	 */
	public function test_execute_safely_forwards_non_retryable_codes() {
		$service    = $this->make_resilience_service();
		$call_count = 0;

		$result = $service->execute_safely(
			function () use ( &$call_count ) {
				$call_count++;
				return new WP_Error( 'perm', 'Permanent' );
			},
			'text',
			'prompt',
			array(),
			array( 'non_retryable_codes' => array( 'perm' ) )
		);

		$this->assertInstanceOf( 'WP_Error', $result );
		$this->assertEquals( 1, $call_count, 'execute_safely must forward non_retryable_codes so the loop aborts immediately' );
	}
}
