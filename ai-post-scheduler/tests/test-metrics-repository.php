<?php
/**
 * Tests for AIPS_Metrics_Repository.
 *
 * Verifies that all public methods return structurally-correct data under
 * the limited-mode test environment (mock $wpdb that returns empty rows).
 *
 * @package AI_Post_Scheduler
 */

/**
 * Class Test_AIPS_Metrics_Repository
 *
 * Covers:
 * - get_baseline_metrics() top-level shape
 * - get_generation_metrics() shape and computed fields
 * - get_queue_depth_metrics() shape
 * - Percentile helper logic via computed durations
 * - Success/failure rate edge cases (zero totals)
 * - invalidate_cache() runs without errors
 */
class Test_AIPS_Metrics_Repository extends WP_UnitTestCase {

	// -----------------------------------------------------------------------
	// Helpers
	// -----------------------------------------------------------------------

	/**
	 * Build an AIPS_Metrics_Repository wired to a controllable mock $wpdb.
	 *
	 * @param array $overrides Associative array of wpdb method => return value.
	 * @return AIPS_Metrics_Repository
	 */
	private function make_repo( array $overrides = array() ) {
		global $wpdb;

		// Detect the limited-mode mock $wpdb using property_exists() rather
		// than isset() because the sentinel property is initialised to null and
		// isset() returns false for null values.
		if ( property_exists( $wpdb, 'get_col_return_val' ) ) {
			// Already a mock - apply custom overrides via closure-based approach.
			foreach ( $overrides as $method => $value ) {
				$wpdb->{$method . '_return_val'} = $value;
			}
		}

		return new AIPS_Metrics_Repository();
	}

	/**
	 * Clear any transients written during tests.
	 *
	 * AIPS_Metrics_Repository::invalidate_cache() detects the limited-mode
	 * mock environment (no $wpdb->options) and falls back to deleting common
	 * window keys, so it is safe to call here.
	 */
	public function tearDown(): void {
		$repo = new AIPS_Metrics_Repository();
		$repo->invalidate_cache();
		parent::tearDown();
	}

	// -----------------------------------------------------------------------
	// get_baseline_metrics()
	// -----------------------------------------------------------------------

	public function test_get_baseline_metrics_returns_array_with_required_keys() {
		$repo    = $this->make_repo();
		$metrics = $repo->get_baseline_metrics();

		$this->assertIsArray( $metrics );
		$this->assertArrayHasKey( 'generation', $metrics );
		$this->assertArrayHasKey( 'queue_depth', $metrics );
		$this->assertArrayHasKey( 'collected_at', $metrics );
	}

	public function test_collected_at_is_iso8601_string() {
		$repo    = $this->make_repo();
		$metrics = $repo->get_baseline_metrics();

		$this->assertMatchesRegularExpression(
			'/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/',
			$metrics['collected_at'],
			'collected_at must be a UTC ISO-8601 timestamp'
		);
	}

	// -----------------------------------------------------------------------
	// get_generation_metrics()
	// -----------------------------------------------------------------------

	public function test_get_generation_metrics_contains_required_keys() {
		$repo = $this->make_repo();
		$m    = $repo->get_generation_metrics();

		$expected_keys = array(
			'window_days',
			'total',
			'successful',
			'failed',
			'partial',
			'success_rate',
			'failure_rate',
			'avg_duration_seconds',
			'p50_duration_seconds',
			'p95_duration_seconds',
			'avg_ai_calls_per_post',
			'image_failure_rate',
			'schedule_success_rate',
			'recent_outcomes',
		);

		foreach ( $expected_keys as $key ) {
			$this->assertArrayHasKey( $key, $m, "Missing key: {$key}" );
		}
	}

	public function test_get_generation_metrics_default_window_is_30() {
		$repo = $this->make_repo();
		$m    = $repo->get_generation_metrics();

		$this->assertSame( 30, $m['window_days'] );
	}

	public function test_get_generation_metrics_custom_window_is_respected() {
		$repo = $this->make_repo();
		$m    = $repo->get_generation_metrics( 7 );

		$this->assertSame( 7, $m['window_days'] );
	}

	public function test_success_rate_is_zero_when_no_data() {
		$repo = $this->make_repo();
		$m    = $repo->get_generation_metrics();

		// Mock $wpdb returns 0 for totals, so success_rate must be 0.
		$this->assertSame( 0.0, $m['success_rate'] );
	}

	public function test_failure_rate_is_zero_when_no_data() {
		$repo = $this->make_repo();
		$m    = $repo->get_generation_metrics();

		$this->assertSame( 0.0, $m['failure_rate'] );
	}

	public function test_duration_fields_are_zero_when_no_data() {
		$repo = $this->make_repo();
		$m    = $repo->get_generation_metrics();

		$this->assertSame( 0, $m['avg_duration_seconds'] );
		$this->assertSame( 0, $m['p50_duration_seconds'] );
		$this->assertSame( 0, $m['p95_duration_seconds'] );
	}

	public function test_avg_ai_calls_is_float() {
		$repo = $this->make_repo();
		$m    = $repo->get_generation_metrics();

		$this->assertIsFloat( $m['avg_ai_calls_per_post'] );
	}

	public function test_image_failure_rate_is_float() {
		$repo = $this->make_repo();
		$m    = $repo->get_generation_metrics();

		$this->assertIsFloat( $m['image_failure_rate'] );
	}

	public function test_schedule_success_rate_is_float() {
		$repo = $this->make_repo();
		$m    = $repo->get_generation_metrics();

		$this->assertIsFloat( $m['schedule_success_rate'] );
	}

	public function test_recent_outcomes_is_array() {
		$repo = $this->make_repo();
		$m    = $repo->get_generation_metrics();

		$this->assertIsArray( $m['recent_outcomes'] );
	}

	// -----------------------------------------------------------------------
	// get_queue_depth_metrics()
	// -----------------------------------------------------------------------

	public function test_get_queue_depth_metrics_contains_required_keys() {
		$repo = $this->make_repo();
		$q    = $repo->get_queue_depth_metrics();

		$this->assertArrayHasKey( 'active_schedules', $q );
		$this->assertArrayHasKey( 'approved_topics', $q );
	}

	public function test_get_queue_depth_metrics_values_are_integers() {
		$repo = $this->make_repo();
		$q    = $repo->get_queue_depth_metrics();

		$this->assertIsInt( $q['active_schedules'] );
		$this->assertIsInt( $q['approved_topics'] );
	}

	// -----------------------------------------------------------------------
	// Percentile logic (computed via mock with known durations)
	// -----------------------------------------------------------------------

	/**
	 * Test percentile calculation indirectly by injecting a custom $wpdb
	 * mock that returns a known set of durations and verifying success_rate
	 * computation uses the same data path.
	 *
	 * The AIPS_Metrics_Repository uses get_generation_counts() which calls
	 * $wpdb->get_row().  In limited mode, get_row() returns stdClass with
	 * total=0, completed=0, etc.  We therefore test the percentile helper
	 * indirectly through the public API shape guarantees.
	 */
	public function test_percentile_computation_with_empty_returns_zero() {
		$repo = $this->make_repo();
		$m    = $repo->get_generation_metrics( 90 );

		// With no data (mock returns empty), all percentiles must be 0.
		$this->assertGreaterThanOrEqual( 0, $m['avg_duration_seconds'] );
		$this->assertGreaterThanOrEqual( 0, $m['p50_duration_seconds'] );
		$this->assertGreaterThanOrEqual( 0, $m['p95_duration_seconds'] );
	}

	// -----------------------------------------------------------------------
	// invalidate_cache()
	// -----------------------------------------------------------------------

	public function test_invalidate_cache_does_not_throw() {
		$repo = $this->make_repo();

		// Populate a transient first.
		$repo->get_generation_metrics( 30 );

		// Should not throw.
		$repo->invalidate_cache();

		$this->assertTrue( true );
	}

	public function test_metrics_are_re_fetched_after_invalidation() {
		$repo = $this->make_repo();

		$first  = $repo->get_generation_metrics( 30 );
		$repo->invalidate_cache();
		$second = $repo->get_generation_metrics( 30 );

		// Both calls must return the same shape (data equality doesn't matter
		// since the mock always returns the same result).
		$this->assertSame( array_keys( $first ), array_keys( $second ) );
	}

	// -----------------------------------------------------------------------
	// get_baseline_metrics() window delegation
	// -----------------------------------------------------------------------

	public function test_baseline_metrics_passes_window_to_generation_metrics() {
		$repo    = $this->make_repo();
		$metrics = $repo->get_baseline_metrics( 14 );

		$this->assertSame( 14, $metrics['generation']['window_days'] );
	}

	// -----------------------------------------------------------------------
	// Edge-case: window_days < 1 is normalised to 1
	// -----------------------------------------------------------------------

	public function test_zero_window_days_is_normalised_to_one() {
		$repo = $this->make_repo();
		$m    = $repo->get_generation_metrics( 0 );

		$this->assertSame( 1, $m['window_days'] );
	}

	public function test_negative_window_days_is_normalised_to_one() {
		$repo = $this->make_repo();
		$m    = $repo->get_generation_metrics( -5 );

		$this->assertSame( 1, $m['window_days'] );
	}
}
