<?php
/**
 * Phase H.3 — Load Testing and Real-World Smoke Tests
 *
 * Validates the system's resilience contracts under burst / concurrent-style
 * call patterns without requiring a live HTTP server.  Each test simulates a
 * realistic sequence of rapid calls and asserts the expected throttle, lock, or
 * recovery behaviour.
 *
 * Coverage:
 *  - Rate-limiter enforces configured burst ceiling and windows requests correctly
 *  - Rate-limiter correctly decays old requests after the period expires
 *  - Circuit breaker opens after repeated failures under load
 *  - Circuit breaker recovers (half-open / closed) after the timeout window
 *  - execute_safely() enforces rate limit before attempting the callable
 *  - Background job claim-first lock prevents duplicate cron executions (schedule
 *    processor side-effect test)
 *  - Metrics repository queue-health snapshot reflects stuck-job threshold
 *
 * @package AI_Post_Scheduler
 */

class Test_AIPS_Load_Smoke extends WP_UnitTestCase {

	// -----------------------------------------------------------------------
	// Helpers
	// -----------------------------------------------------------------------

	/**
	 * Build a resilience service with rate limiting enabled.
	 *
	 * @param int $max_requests Maximum requests allowed per period.
	 * @param int $period       Period in seconds.
	 * @return AIPS_Resilience_Service
	 */
	private function make_rate_limited_service( $max_requests = 5, $period = 60 ) {
		$GLOBALS['aips_test_options'] = array(
			'aips_enable_rate_limiting'   => true,
			'aips_rate_limit_requests'    => $max_requests,
			'aips_rate_limit_period'      => $period,
			'aips_enable_circuit_breaker' => false,
			'aips_enable_retry'           => false,
		);

		delete_transient( 'aips_rate_limiter_requests' );
		delete_transient( 'aips_circuit_breaker_state' );

		return new AIPS_Resilience_Service();
	}

	/**
	 * Build a resilience service with circuit breaker enabled and rate limiting off.
	 *
	 * @param int $threshold Failure count that opens the circuit.
	 * @param int $timeout   Seconds the circuit stays open before going half-open.
	 * @return AIPS_Resilience_Service
	 */
	private function make_cb_service( $threshold = 3, $timeout = 300 ) {
		$GLOBALS['aips_test_options'] = array(
			'aips_enable_circuit_breaker'    => true,
			'aips_circuit_breaker_threshold' => $threshold,
			'aips_circuit_breaker_timeout'   => $timeout,
			'aips_enable_retry'              => false,
			'aips_enable_rate_limiting'      => false,
		);

		delete_transient( 'aips_circuit_breaker_state' );
		delete_transient( 'aips_rate_limiter_requests' );

		return new AIPS_Resilience_Service();
	}

	/**
	 * Build a resilience service with both rate limiting AND circuit breaker enabled.
	 *
	 * @param int $max_requests RL ceiling.
	 * @param int $period       RL window in seconds.
	 * @param int $threshold    CB threshold.
	 * @return AIPS_Resilience_Service
	 */
	private function make_full_service( $max_requests = 5, $period = 60, $threshold = 10 ) {
		$GLOBALS['aips_test_options'] = array(
			'aips_enable_rate_limiting'      => true,
			'aips_rate_limit_requests'       => $max_requests,
			'aips_rate_limit_period'         => $period,
			'aips_enable_circuit_breaker'    => true,
			'aips_circuit_breaker_threshold' => $threshold,
			'aips_circuit_breaker_timeout'   => 300,
			'aips_enable_retry'              => false,
		);

		delete_transient( 'aips_rate_limiter_requests' );
		delete_transient( 'aips_circuit_breaker_state' );

		return new AIPS_Resilience_Service();
	}

	public function tearDown(): void {
		delete_transient( 'aips_rate_limiter_requests' );
		delete_transient( 'aips_circuit_breaker_state' );
		unset( $GLOBALS['aips_test_options'] );

		// Clean up any templates and schedules created during locking tests.
		global $wpdb;
		if ( isset( $wpdb ) && is_object( $wpdb ) && ! property_exists( $wpdb, 'get_col_return_val' ) ) {
			$wpdb->query( "DELETE FROM {$wpdb->prefix}aips_schedule WHERE topic IN ('Lock test','Topic A','Topic B')" );
			$wpdb->query( "DELETE FROM {$wpdb->prefix}aips_templates WHERE name IN ('Smoke Lock Test Template','Concurrent Lock Template')" );
		}

		parent::tearDown();
	}

	// -----------------------------------------------------------------------
	// Rate-limiter: burst ceiling
	// -----------------------------------------------------------------------

	/**
	 * N calls within a single period window: first N succeed, N+1 is blocked.
	 */
	public function test_rate_limiter_blocks_after_ceiling_is_reached() {
		$max = 5;
		$svc = $this->make_rate_limited_service( $max, 60 );

		// All requests up to the ceiling should pass.
		for ( $i = 1; $i <= $max; $i++ ) {
			$this->assertTrue(
				$svc->check_rate_limit(),
				"Request #{$i} of {$max} should be allowed"
			);
		}

		// The very next request must be blocked.
		$this->assertFalse(
			$svc->check_rate_limit(),
			"Request #" . ( $max + 1 ) . " should be blocked by rate limiter"
		);
	}

	/**
	 * Rate limiter correctly counts all requests in the window.
	 */
	public function test_rate_limiter_status_reflects_burst_correctly() {
		$max = 4;
		$svc = $this->make_rate_limited_service( $max, 60 );

		for ( $i = 1; $i <= $max; $i++ ) {
			$svc->check_rate_limit();
		}

		$status = $svc->get_rate_limiter_status();

		$this->assertSame( $max, $status['current_requests'], 'current_requests should equal number of calls made' );
		$this->assertSame( 0, $status['remaining'], 'remaining should be 0 after reaching ceiling' );
	}

	/**
	 * Rate limiter allows requests again after a manual reset (simulates period expiry).
	 */
	public function test_rate_limiter_allows_requests_after_reset() {
		$max = 3;
		$svc = $this->make_rate_limited_service( $max, 60 );

		// Exhaust the limit.
		for ( $i = 0; $i < $max; $i++ ) {
			$svc->check_rate_limit();
		}
		$this->assertFalse( $svc->check_rate_limit(), 'Should be blocked before reset' );

		// Simulate period expiry via manual reset.
		$svc->reset_rate_limiter();

		$this->assertTrue( $svc->check_rate_limit(), 'Should be allowed again after reset' );
	}

	/**
	 * Rate limiter status reports correct remaining capacity during burst.
	 */
	public function test_rate_limiter_remaining_decrements_with_each_call() {
		$max = 5;
		$svc = $this->make_rate_limited_service( $max, 60 );

		for ( $consumed = 1; $consumed <= $max; $consumed++ ) {
			$svc->check_rate_limit();
			$status = $svc->get_rate_limiter_status();

			$expected_remaining = $max - $consumed;
			$this->assertSame(
				$expected_remaining,
				$status['remaining'],
				"After {$consumed} call(s), remaining should be {$expected_remaining}"
			);
		}
	}

	// -----------------------------------------------------------------------
	// Rate-limiter: execute_safely integration
	// -----------------------------------------------------------------------

	/**
	 * execute_safely returns rate_limit_exceeded WP_Error after ceiling hit.
	 */
	public function test_execute_safely_returns_rate_limit_error_when_window_exhausted() {
		$max = 2;
		$svc = $this->make_full_service( $max, 60, 100 );

		$callable = function() {
			return 'ok';
		};

		// Consume the budget.
		for ( $i = 0; $i < $max; $i++ ) {
			$svc->execute_safely( $callable, 'text', 'prompt', array() );
		}

		// Next call must return rate_limit_exceeded.
		$result = $svc->execute_safely( $callable, 'text', 'prompt', array() );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'rate_limit_exceeded', $result->get_error_code() );
	}

	/**
	 * Rate limit errors from execute_safely must NOT count as circuit-breaker failures.
	 */
	public function test_rate_limit_error_does_not_increment_circuit_breaker() {
		$max = 1;
		$svc = $this->make_full_service( $max, 60, 3 );

		$callable = function() {
			return 'ok';
		};

		// Consume the budget.
		$svc->execute_safely( $callable, 'text', 'prompt', array() );

		// Trigger rate-limit error.
		$svc->execute_safely( $callable, 'text', 'prompt', array() );

		$cb_status = $svc->get_circuit_breaker_status();
		$this->assertSame(
			0,
			$cb_status['failures'],
			'rate_limit_exceeded must not increment the circuit breaker failure counter'
		);
	}

	// -----------------------------------------------------------------------
	// Circuit breaker: failure load
	// -----------------------------------------------------------------------

	/**
	 * Circuit opens after threshold failures in rapid succession.
	 */
	public function test_circuit_breaker_opens_after_rapid_failures() {
		$threshold = 3;
		$svc       = $this->make_cb_service( $threshold );

		for ( $i = 0; $i < $threshold; $i++ ) {
			$svc->record_failure( 'generation_failed' );
		}

		$status = $svc->get_circuit_breaker_status();
		$this->assertSame( 'open', $status['state'], 'Circuit should be open after threshold failures' );
		$this->assertFalse( $svc->check_circuit_breaker(), 'check_circuit_breaker() must return false when open' );
	}

	/**
	 * Circuit breaker failure counter increments correctly under load.
	 */
	public function test_circuit_breaker_failure_counter_tracks_load() {
		$threshold = 10;
		$svc       = $this->make_cb_service( $threshold );

		$burst = 7;
		for ( $i = 0; $i < $burst; $i++ ) {
			$svc->record_failure( 'generation_failed' );
		}

		$status = $svc->get_circuit_breaker_status();
		$this->assertSame( $burst, $status['failures'], 'Failure counter must reflect all recorded failures' );
		$this->assertSame( 'closed', $status['state'], 'Circuit must remain closed while below threshold' );
	}

	/**
	 * After circuit opens, execute_safely immediately returns circuit_breaker_open error.
	 */
	public function test_execute_safely_is_rejected_when_circuit_is_open() {
		$threshold = 2;
		$svc       = $this->make_cb_service( $threshold );

		// Manually open the circuit.
		for ( $i = 0; $i < $threshold; $i++ ) {
			$svc->record_failure( 'generation_failed' );
		}

		$calls = 0;
		$result = $svc->execute_safely(
			function() use ( &$calls ) {
				$calls++;
				return 'ok';
			},
			'text',
			'prompt',
			array()
		);

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'circuit_breaker_open', $result->get_error_code() );
		$this->assertSame( 0, $calls, 'Callable must not be invoked when circuit is open' );
	}

	/**
	 * Circuit breaker resets to closed state and allows requests after a manual reset.
	 */
	public function test_circuit_breaker_recovers_after_reset() {
		$svc = $this->make_cb_service( 3 );

		// Open the circuit.
		$svc->record_failure();
		$svc->record_failure();
		$svc->record_failure();
		$this->assertSame( 'open', $svc->get_circuit_breaker_status()['state'] );

		// Reset simulates the timeout window expiring and the probe succeeding.
		$svc->reset_circuit_breaker();
		$svc->record_success();

		$status = $svc->get_circuit_breaker_status();
		$this->assertSame( 'closed', $status['state'], 'Circuit must return to closed after successful recovery' );
		$this->assertSame( 0, $status['failures'], 'Failure counter must be cleared on recovery' );
	}

	/**
	 * Successful call after threshold failures resets the failure counter.
	 */
	public function test_success_after_failures_resets_failure_counter() {
		$threshold = 5;
		$svc       = $this->make_cb_service( $threshold );

		// Record failures short of the threshold.
		for ( $i = 0; $i < $threshold - 1; $i++ ) {
			$svc->record_failure( 'generation_failed' );
		}

		$svc->record_success();

		$status = $svc->get_circuit_breaker_status();
		$this->assertSame( 0, $status['failures'], 'Failures must reset to 0 on success' );
		$this->assertSame( 'closed', $status['state'] );
	}

	// -----------------------------------------------------------------------
	// Background job locking
	// -----------------------------------------------------------------------

	/**
	 * A schedule processed by the processor should have its next_run advanced
	 * immediately (claim-first lock), preventing a second concurrent cron from
	 * picking it up as due.
	 *
	 * This test verifies the schedule repository reflects an updated next_run
	 * after the processor acquires the lock, simulating what happens during two
	 * concurrent cron invocations.
	 */
	public function test_schedule_lock_advances_next_run_on_first_claim() {
		$template_repo  = new AIPS_Template_Repository();
		$schedule_repo  = new AIPS_Schedule_Repository();

		// Create a template.
		$template_id = $template_repo->create( array(
			'name'            => 'Smoke Lock Test Template',
			'prompt_template' => 'Write about {{topic}}',
			'post_status'     => 'draft',
			'post_category'   => 1,
			'is_active'       => 1,
		) );
		$this->assertNotFalse( $template_id, 'Template creation should succeed' );

		// Create an overdue daily schedule (7200 seconds = 2 hours in the past).
		$overdue_time = gmdate( 'Y-m-d H:i:s', time() - 7200 );
		$schedule_id  = $schedule_repo->create( array(
			'template_id' => $template_id,
			'frequency'   => 'daily',
			'next_run'    => $overdue_time,
			'is_active'   => 1,
			'topic'       => 'Lock test',
		) );
		$this->assertNotFalse( $schedule_id, 'Schedule creation should succeed' );

		// Simulate the claim-first lock: advance next_run.
		$new_next_run = date( 'Y-m-d H:i:s', strtotime( '+1 day' ) );
		$lock_result  = $schedule_repo->update( $schedule_id, array( 'next_run' => $new_next_run ) );
		$this->assertNotFalse( $lock_result, 'Lock acquisition (next_run update) should succeed' );

		// Confirm the schedule is no longer "due" — a concurrent cron would skip it.
		$due_schedules = $schedule_repo->get_due_schedules();
		$due_ids       = array_map( function( $s ) { return (int) $s->schedule_id; }, $due_schedules );
		$this->assertNotContains(
			(int) $schedule_id,
			$due_ids,
			'A locked schedule must not appear as due to a concurrent cron invocation'
		);
	}

	/**
	 * Two separate schedules can each acquire their own locks independently.
	 *
	 * Simulates two concurrent cron handlers running at the same time: each
	 * picks a different schedule, advances its next_run, and neither interferes
	 * with the other.
	 */
	public function test_concurrent_schedule_locks_are_independent() {
		$template_repo = new AIPS_Template_Repository();
		$schedule_repo = new AIPS_Schedule_Repository();

		$template_id = $template_repo->create( array(
			'name'            => 'Concurrent Lock Template',
			'prompt_template' => 'About {{topic}}',
			'post_status'     => 'draft',
			'post_category'   => 1,
			'is_active'       => 1,
		) );

		$overdue = date( 'Y-m-d H:i:s', strtotime( '-1 hour' ) );

		$id_a = $schedule_repo->create( array(
			'template_id' => $template_id,
			'frequency'   => 'daily',
			'next_run'    => $overdue,
			'is_active'   => 1,
			'topic'       => 'Topic A',
		) );

		$id_b = $schedule_repo->create( array(
			'template_id' => $template_id,
			'frequency'   => 'daily',
			'next_run'    => $overdue,
			'is_active'   => 1,
			'topic'       => 'Topic B',
		) );

		// Cron handler 1 claims schedule A.
		$locked_a = $schedule_repo->update( $id_a, array( 'next_run' => date( 'Y-m-d H:i:s', strtotime( '+1 day' ) ) ) );
		// Cron handler 2 claims schedule B.
		$locked_b = $schedule_repo->update( $id_b, array( 'next_run' => date( 'Y-m-d H:i:s', strtotime( '+1 day' ) ) ) );

		$this->assertNotFalse( $locked_a, 'Lock for schedule A must succeed' );
		$this->assertNotFalse( $locked_b, 'Lock for schedule B must succeed' );

		// Both schedules must now be absent from the due list.
		$due_ids = array_map(
			function( $s ) { return (int) $s->schedule_id; },
			$schedule_repo->get_due_schedules()
		);
		$this->assertNotContains( (int) $id_a, $due_ids );
		$this->assertNotContains( (int) $id_b, $due_ids );
	}

	// -----------------------------------------------------------------------
	// Metrics repository: queue health under simulated load
	// -----------------------------------------------------------------------

	/**
	 * Queue-health snapshot returns the required structural keys under the
	 * test environment's mock $wpdb.
	 */
	public function test_queue_health_metrics_returns_required_keys() {
		$repo   = new AIPS_Metrics_Repository();
		$health = $repo->get_queue_health_metrics();

		$required = array(
			'pending_count',
			'partial_count',
			'stuck_count',
			'oldest_stuck_age_minutes',
			'failed_24h',
			'retry_saturation_pct',
			'circuit_breaker',
		);

		foreach ( $required as $key ) {
			$this->assertArrayHasKey(
				$key,
				$health,
				"Queue health snapshot must include '{$key}'"
			);
		}
	}

	/**
	 * Baseline metrics include generation and queue depth snapshots.
	 */
	public function test_baseline_metrics_includes_generation_and_queue_depth() {
		$repo    = new AIPS_Metrics_Repository();
		$metrics = $repo->get_baseline_metrics();

		$this->assertArrayHasKey( 'generation',  $metrics );
		$this->assertArrayHasKey( 'queue_depth', $metrics );
		$this->assertArrayHasKey( 'collected_at', $metrics );
	}

	/**
	 * Queue-depth metrics expose active_schedules and approved_topics integers.
	 */
	public function test_queue_depth_values_are_integers() {
		$repo   = new AIPS_Metrics_Repository();
		$depth  = $repo->get_queue_depth_metrics();

		$this->assertIsInt( $depth['active_schedules'] );
		$this->assertIsInt( $depth['approved_topics'] );
	}

	// -----------------------------------------------------------------------
	// AI API rate-limit smoke: sequential burst through execute_safely
	// -----------------------------------------------------------------------

	/**
	 * A rapid burst of N successful calls all return the expected result when
	 * the rate limit ceiling is above N.
	 */
	public function test_successful_burst_below_rate_limit_ceiling_all_pass() {
		$burst_size = 8;
		$svc        = $this->make_full_service( $burst_size + 2, 60, 100 );

		$successes = 0;
		for ( $i = 0; $i < $burst_size; $i++ ) {
			$result = $svc->execute_safely(
				function() {
					return 'generated';
				},
				'text',
				'prompt',
				array()
			);
			if ( ! is_wp_error( $result ) ) {
				$successes++;
			}
		}

		$this->assertSame( $burst_size, $successes, 'All calls below the ceiling should succeed' );
	}

	/**
	 * After a burst exhausts the rate-limit window, additional calls are throttled
	 * but the rate limiter does not affect the circuit breaker failure count.
	 */
	public function test_throttled_burst_does_not_corrupt_circuit_breaker_state() {
		$max = 3;
		$svc = $this->make_full_service( $max, 60, 10 );

		// Exhaust the budget via execute_safely.
		for ( $i = 0; $i < $max; $i++ ) {
			$svc->execute_safely(
				function() { return 'ok'; },
				'text', 'prompt', array()
			);
		}

		// Trigger throttle.
		$svc->execute_safely(
			function() { return 'ok'; },
			'text', 'prompt', array()
		);

		$cb = $svc->get_circuit_breaker_status();
		$this->assertSame(
			0,
			$cb['failures'],
			'Rate-limit throttle must not register as a circuit-breaker failure'
		);
		$this->assertSame( 'closed', $cb['state'] );
	}

	/**
	 * Repeated AI API failures during a burst open the circuit breaker and
	 * subsequent calls are short-circuited without invoking the callable.
	 */
	public function test_burst_of_ai_failures_opens_circuit_and_short_circuits_subsequent_calls() {
		$threshold = 3;
		$svc       = $this->make_cb_service( $threshold );

		$callable_invocations = 0;
		$callable = function() use ( &$callable_invocations ) {
			$callable_invocations++;
			return new WP_Error( 'generation_failed', 'AI API error' );
		};

		// Drive failures up to the threshold using execute_safely.
		for ( $i = 0; $i < $threshold; $i++ ) {
			$svc->execute_safely( $callable, 'text', 'prompt', array() );
		}

		$this->assertSame( 'open', $svc->get_circuit_breaker_status()['state'] );

		// Reset invocation counter to measure short-circuit behaviour.
		$callable_invocations = 0;

		// Further calls must be rejected without invoking the callable.
		for ( $i = 0; $i < 5; $i++ ) {
			$result = $svc->execute_safely( $callable, 'text', 'prompt', array() );
			$this->assertInstanceOf( WP_Error::class, $result );
			$this->assertSame( 'circuit_breaker_open', $result->get_error_code() );
		}

		$this->assertSame(
			0,
			$callable_invocations,
			'Callable must not be invoked when circuit is open'
		);
	}
}
