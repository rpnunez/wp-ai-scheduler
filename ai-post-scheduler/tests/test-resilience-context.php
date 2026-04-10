<?php
/**
 * Tests for AIPS_Resilience_Service context-scoped circuit breaker and rate limiter.
 *
 * Covers:
 *   - Context isolation: two different contexts do not share CB or RL state
 *   - Consistent keying: same context always produces the same transient key
 *   - Global fallback: null context uses the global (site-wide) key
 *   - execute_safely() threads context through CB and RL
 *
 * @package AI_Post_Scheduler
 */

class Test_AIPS_Resilience_Context extends WP_UnitTestCase {

	// -----------------------------------------------------------------------
	// Helpers
	// -----------------------------------------------------------------------

	/**
	 * Wipe all resilience transients from the fake options store.
	 * Called between tests to guarantee a clean slate.
	 */
	private function flush_resilience_transients() {
		if ( ! isset( $GLOBALS['aips_test_options'] ) || ! is_array( $GLOBALS['aips_test_options'] ) ) {
			return;
		}
		foreach ( array_keys( $GLOBALS['aips_test_options'] ) as $key ) {
			if (
				strpos( $key, '_transient_aips_circuit_breaker_state_' ) === 0 ||
				strpos( $key, '_transient_aips_rate_limiter_requests_' ) === 0
			) {
				unset( $GLOBALS['aips_test_options'][ $key ] );
			}
		}
	}

	/**
	 * Build a service with circuit breaker enabled (low threshold) and RL disabled.
	 *
	 * @param int $threshold CB failure threshold.
	 * @return AIPS_Resilience_Service
	 */
	private function make_cb_service( $threshold = 3 ) {
		$GLOBALS['aips_test_options'] = array(
			'aips_enable_circuit_breaker'    => true,
			'aips_circuit_breaker_threshold' => $threshold,
			'aips_circuit_breaker_timeout'   => 300,
			'aips_enable_retry'              => false,
			'aips_enable_rate_limiting'      => false,
		);

		return new AIPS_Resilience_Service();
	}

	/**
	 * Build a service with rate limiter enabled and CB disabled.
	 *
	 * @param int $max_requests Maximum requests per period.
	 * @param int $period       Period length in seconds.
	 * @return AIPS_Resilience_Service
	 */
	private function make_rl_service( $max_requests = 2, $period = 60 ) {
		$GLOBALS['aips_test_options'] = array(
			'aips_enable_circuit_breaker' => false,
			'aips_enable_retry'           => false,
			'aips_enable_rate_limiting'   => true,
			'aips_rate_limit_requests'    => $max_requests,
			'aips_rate_limit_period'      => $period,
		);

		return new AIPS_Resilience_Service();
	}

	/**
	 * Build a service with both CB and RL enabled.
	 *
	 * @param int $threshold   CB failure threshold.
	 * @param int $max_requests RL max requests per period.
	 * @return AIPS_Resilience_Service
	 */
	private function make_full_service( $threshold = 10, $max_requests = 5 ) {
		$GLOBALS['aips_test_options'] = array(
			'aips_enable_circuit_breaker'    => true,
			'aips_circuit_breaker_threshold' => $threshold,
			'aips_circuit_breaker_timeout'   => 300,
			'aips_enable_retry'              => false,
			'aips_enable_rate_limiting'      => true,
			'aips_rate_limit_requests'       => $max_requests,
			'aips_rate_limit_period'         => 60,
		);

		return new AIPS_Resilience_Service();
	}

	// -----------------------------------------------------------------------
	// Consistent keying — same context always maps to the same transient
	// -----------------------------------------------------------------------

	/**
	 * The same context array (regardless of key order) must always produce
	 * the same transient content.
	 */
	public function test_consistent_key_for_same_context() {
		$this->flush_resilience_transients();

		$service = $this->make_cb_service( 10 );
		$ctx     = array( 'type' => 'schedule', 'id' => 42 );

		// Record a failure with context A.
		$service->record_failure( '', $ctx );

		// Read status using the same context — must show 1 failure.
		$status = $service->get_circuit_breaker_status( $ctx );
		$this->assertSame( 1, $status['failures'], 'Same context should always read the same state' );
	}

	/**
	 * Key order in the context array must not matter — ksort normalisation ensures
	 * that array('id' => 42, 'type' => 'schedule') hashes the same as
	 * array('type' => 'schedule', 'id' => 42).
	 */
	public function test_key_order_does_not_affect_context_hash() {
		$this->flush_resilience_transients();

		$service = $this->make_cb_service( 10 );
		$ctx_a   = array( 'type' => 'schedule', 'id' => 99 );
		$ctx_b   = array( 'id' => 99, 'type' => 'schedule' ); // keys reversed

		$service->record_failure( '', $ctx_a );

		// Reading with reversed-key context must see the same state.
		$status = $service->get_circuit_breaker_status( $ctx_b );
		$this->assertSame( 1, $status['failures'], 'Key order should not affect context hash' );
	}

	/**
	 * Null values in a context array are stripped before hashing; supplying them
	 * should yield the same key as omitting them.
	 */
	public function test_null_values_stripped_from_context() {
		$this->flush_resilience_transients();

		$service = $this->make_cb_service( 10 );
		$ctx_clean = array( 'type' => 'generator', 'id' => 7 );
		$ctx_nulls = array( 'type' => 'generator', 'id' => 7, 'extra' => null );

		$service->record_failure( '', $ctx_clean );

		$status = $service->get_circuit_breaker_status( $ctx_nulls );
		$this->assertSame( 1, $status['failures'], 'Null values must be stripped before hashing' );
	}

	// -----------------------------------------------------------------------
	// Context isolation — circuit breaker
	// -----------------------------------------------------------------------

	/**
	 * Failures recorded for context A must not affect context B's CB state.
	 */
	public function test_circuit_breaker_isolation_between_contexts() {
		$this->flush_resilience_transients();

		$service  = $this->make_cb_service( 2 );
		$ctx_a    = array( 'type' => 'schedule', 'id' => 1 );
		$ctx_b    = array( 'type' => 'schedule', 'id' => 2 );

		// Drive context A to the open state.
		$service->record_failure( '', $ctx_a );
		$service->record_failure( '', $ctx_a );

		$status_a = $service->get_circuit_breaker_status( $ctx_a );
		$status_b = $service->get_circuit_breaker_status( $ctx_b );

		$this->assertSame( 'open', $status_a['state'], 'Context A circuit should be open' );
		$this->assertSame( 'closed', $status_b['state'], 'Context B circuit should remain closed' );
	}

	/**
	 * check_circuit_breaker() for context B must return true even when context A is open.
	 */
	public function test_check_circuit_breaker_allows_requests_for_isolated_context() {
		$this->flush_resilience_transients();

		$service = $this->make_cb_service( 1 );
		$ctx_a   = array( 'type' => 'schedule', 'id' => 10 );
		$ctx_b   = array( 'type' => 'schedule', 'id' => 20 );

		// Open context A's circuit.
		$service->record_failure( '', $ctx_a );

		$this->assertFalse(
			$service->check_circuit_breaker( $ctx_a ),
			'Context A circuit breaker should block requests'
		);
		$this->assertTrue(
			$service->check_circuit_breaker( $ctx_b ),
			'Context B circuit breaker should allow requests'
		);
	}

	/**
	 * reset_circuit_breaker() for context A must not affect context B.
	 */
	public function test_reset_circuit_breaker_is_context_scoped() {
		$this->flush_resilience_transients();

		$service = $this->make_cb_service( 1 );
		$ctx_a   = array( 'type' => 'schedule', 'id' => 30 );
		$ctx_b   = array( 'type' => 'schedule', 'id' => 40 );

		// Open both circuits.
		$service->record_failure( '', $ctx_a );
		$service->record_failure( '', $ctx_b );

		$this->assertSame( 'open', $service->get_circuit_breaker_status( $ctx_a )['state'] );
		$this->assertSame( 'open', $service->get_circuit_breaker_status( $ctx_b )['state'] );

		// Reset only context A.
		$service->reset_circuit_breaker( $ctx_a );

		$this->assertSame( 'closed', $service->get_circuit_breaker_status( $ctx_a )['state'],
			'Context A should be closed after reset' );
		$this->assertSame( 'open', $service->get_circuit_breaker_status( $ctx_b )['state'],
			'Context B should remain open after context A reset' );
	}

	/**
	 * record_success() for context A must not reset context B's failure count.
	 */
	public function test_record_success_is_context_scoped() {
		$this->flush_resilience_transients();

		$service = $this->make_cb_service( 5 );
		$ctx_a   = array( 'type' => 'generator', 'id' => 1 );
		$ctx_b   = array( 'type' => 'generator', 'id' => 2 );

		$service->record_failure( '', $ctx_b );
		$service->record_failure( '', $ctx_b );

		// Record success for context A — should not touch context B.
		$service->record_success( $ctx_a );

		$status_b = $service->get_circuit_breaker_status( $ctx_b );
		$this->assertSame( 2, $status_b['failures'], 'Context B failures must not be affected by success recorded for A' );
	}

	// -----------------------------------------------------------------------
	// Context isolation — rate limiter
	// -----------------------------------------------------------------------

	/**
	 * Rate-limit window for context A must be independent of context B.
	 */
	public function test_rate_limiter_isolation_between_contexts() {
		$this->flush_resilience_transients();

		$service = $this->make_rl_service( 2, 60 );
		$ctx_a   = array( 'type' => 'schedule', 'id' => 1 );
		$ctx_b   = array( 'type' => 'schedule', 'id' => 2 );

		// Exhaust context A's rate limit.
		$service->check_rate_limit( $ctx_a ); // 1st
		$service->check_rate_limit( $ctx_a ); // 2nd — hits limit

		// Context A must now be rate-limited.
		$allowed_a = $service->check_rate_limit( $ctx_a );
		$this->assertFalse( $allowed_a, 'Context A should be rate-limited' );

		// Context B should still be allowed.
		$allowed_b = $service->check_rate_limit( $ctx_b );
		$this->assertTrue( $allowed_b, 'Context B should not be rate-limited' );
	}

	/**
	 * reset_rate_limiter() for context A must not affect context B.
	 */
	public function test_reset_rate_limiter_is_context_scoped() {
		$this->flush_resilience_transients();

		$service = $this->make_rl_service( 1, 60 );
		$ctx_a   = array( 'type' => 'schedule', 'id' => 50 );
		$ctx_b   = array( 'type' => 'schedule', 'id' => 60 );

		// Exhaust both contexts.
		$service->check_rate_limit( $ctx_a ); // 1st — allowed, hits limit
		$service->check_rate_limit( $ctx_b ); // 1st — allowed, hits limit

		$this->assertFalse( $service->check_rate_limit( $ctx_a ), 'Context A should be rate-limited' );
		$this->assertFalse( $service->check_rate_limit( $ctx_b ), 'Context B should be rate-limited' );

		// Reset context A only.
		$service->reset_rate_limiter( $ctx_a );

		$this->assertTrue( $service->check_rate_limit( $ctx_a ), 'Context A should be allowed after reset' );
		$this->assertFalse( $service->check_rate_limit( $ctx_b ), 'Context B should remain rate-limited' );
	}

	/**
	 * get_rate_limiter_status() reports per-context state.
	 */
	public function test_get_rate_limiter_status_is_context_scoped() {
		$this->flush_resilience_transients();

		$service = $this->make_rl_service( 3, 60 );
		$ctx_a   = array( 'type' => 'generator', 'id' => 5 );
		$ctx_b   = array( 'type' => 'generator', 'id' => 6 );

		// Two requests on context A.
		$service->check_rate_limit( $ctx_a );
		$service->check_rate_limit( $ctx_a );

		$status_a = $service->get_rate_limiter_status( $ctx_a );
		$status_b = $service->get_rate_limiter_status( $ctx_b );

		$this->assertSame( 2, $status_a['current_requests'], 'Context A should show 2 requests' );
		$this->assertSame( 0, $status_b['current_requests'], 'Context B should show 0 requests' );
	}

	// -----------------------------------------------------------------------
	// Global fallback — null context uses the global (site-wide) key
	// -----------------------------------------------------------------------

	/**
	 * Calling methods with no context (null) and with the explicit global context
	 * must read/write the same transient — they are the same thing.
	 */
	public function test_null_context_and_global_context_share_state() {
		$this->flush_resilience_transients();

		$service         = $this->make_cb_service( 10 );
		$global_context  = array( 'type' => 'global', 'id' => 'site' );

		// Record failure with no context (null = global).
		$service->record_failure( '', null );

		// Read with explicit global context — must see the same failure.
		$status = $service->get_circuit_breaker_status( $global_context );
		$this->assertSame( 1, $status['failures'],
			'null and explicit global context must share the same state' );
	}

	/**
	 * Global context state is isolated from named context state.
	 */
	public function test_global_context_is_isolated_from_named_contexts() {
		$this->flush_resilience_transients();

		$service = $this->make_cb_service( 5 );
		$ctx     = array( 'type' => 'schedule', 'id' => 99 );

		// Record failures for a specific schedule.
		$service->record_failure( '', $ctx );
		$service->record_failure( '', $ctx );
		$service->record_failure( '', $ctx );

		// Global (null) context should remain pristine.
		$global_status = $service->get_circuit_breaker_status( null );
		$this->assertSame( 0, $global_status['failures'],
			'Global context failures must be independent of named context failures' );
		$this->assertSame( 'closed', $global_status['state'],
			'Global context circuit must remain closed when a named context fails' );
	}

	// -----------------------------------------------------------------------
	// execute_safely() threads context through both CB and RL
	// -----------------------------------------------------------------------

	/**
	 * execute_safely() with a context must scope circuit-breaker mutations to
	 * that context, not to the global state.
	 */
	public function test_execute_safely_scopes_cb_failure_to_context() {
		$this->flush_resilience_transients();

		$service = $this->make_full_service( 10 );
		$ctx     = array( 'type' => 'schedule', 'id' => 7 );

		$service->execute_safely(
			function() {
				return new WP_Error( 'generation_failed', 'Error' );
			},
			'text',
			'prompt',
			array(),
			$ctx
		);

		// Named context should have 1 failure.
		$ctx_status = $service->get_circuit_breaker_status( $ctx );
		$this->assertSame( 1, $ctx_status['failures'],
			'Named context should record the failure' );

		// Global context should remain untouched.
		$global_status = $service->get_circuit_breaker_status( null );
		$this->assertSame( 0, $global_status['failures'],
			'Global context must not be affected by a named context failure' );
	}

	/**
	 * execute_safely() with a context records success to that context only.
	 */
	public function test_execute_safely_scopes_cb_success_to_context() {
		$this->flush_resilience_transients();

		$service = $this->make_full_service( 10 );
		$ctx     = array( 'type' => 'schedule', 'id' => 8 );

		// Prime the named context with a failure.
		$service->record_failure( '', $ctx );

		// Prime global context with failures too.
		$service->record_failure( '' );
		$service->record_failure( '' );

		// Success via execute_safely for the named context.
		$service->execute_safely(
			function() { return 'ok'; },
			'text',
			'prompt',
			array(),
			$ctx
		);

		// Named context should be reset.
		$ctx_status = $service->get_circuit_breaker_status( $ctx );
		$this->assertSame( 0, $ctx_status['failures'],
			'Named context failures should be reset after success' );

		// Global context should retain its 2 failures.
		$global_status = $service->get_circuit_breaker_status( null );
		$this->assertSame( 2, $global_status['failures'],
			'Global context must retain its failure count after named context succeeds' );
	}

	/**
	 * execute_safely() blocks requests when the provided context's CB is open,
	 * even if the global CB is closed.
	 */
	public function test_execute_safely_blocks_when_context_circuit_open() {
		$this->flush_resilience_transients();

		$service = $this->make_full_service( 1 );
		$ctx     = array( 'type' => 'schedule', 'id' => 9 );

		// Open the named context's circuit.
		$service->record_failure( '', $ctx );

		$result = $service->execute_safely(
			function() { return 'should not run'; },
			'text',
			'prompt',
			array(),
			$ctx
		);

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'circuit_breaker_open', $result->get_error_code() );
	}

	/**
	 * execute_safely() without context (global) remains unaffected by a named
	 * context circuit opening.
	 */
	public function test_execute_safely_global_not_blocked_by_named_context() {
		$this->flush_resilience_transients();

		$service = $this->make_full_service( 1 );
		$ctx     = array( 'type' => 'schedule', 'id' => 11 );

		// Open the named context's circuit.
		$service->record_failure( '', $ctx );

		// Global execute_safely should still proceed.
		$result = $service->execute_safely(
			function() { return 'global ok'; },
			'text',
			'prompt',
			array()
			// no context = global
		);

		$this->assertSame( 'global ok', $result,
			'Global execute_safely must succeed when only a named context circuit is open' );
	}

	// -----------------------------------------------------------------------
	// Hierarchical / nested context keys
	// -----------------------------------------------------------------------

	/**
	 * A richer context (generator + author + topic) produces a different key
	 * than a simpler context for the same generator.
	 */
	public function test_nested_context_keys_are_distinct() {
		$this->flush_resilience_transients();

		$service   = $this->make_cb_service( 10 );
		$ctx_gen   = array( 'type' => 'generator', 'id' => 22 );
		$ctx_topic = array(
			'type'      => 'generator_author_topics',
			'id'        => 22,
			'author_id' => 101,
			'child_id'  => 555,
		);

		$service->record_failure( '', $ctx_gen );

		// Topic-level context must not see the generator-level failure.
		$topic_status = $service->get_circuit_breaker_status( $ctx_topic );
		$this->assertSame( 0, $topic_status['failures'],
			'Nested context must not inherit parent context failures' );
	}
}
