<?php
/**
 * Tests for AIPS_Batch_Queue_Service
 *
 * Covers:
 *  - Large-batch threshold detection (needs_batch_queue)
 *  - Batch configuration calculation (calculate_config)
 *  - Dispatch behaviour (wp_schedule_single_event calls, args, timing)
 *
 * @package AI_Post_Scheduler
 */

class Test_AIPS_Batch_Queue_Service extends WP_UnitTestCase {

	/** @var AIPS_Batch_Queue_Service */
	private $service;

	public function setUp(): void {
		parent::setUp();
		// Ensure the single-event store is clean before each test.
		unset($GLOBALS['aips_test_single_events']);
		$this->service = new AIPS_Batch_Queue_Service();
	}

	public function tearDown(): void {
		// Remove any filters added by individual tests.
		remove_all_filters('aips_large_batch_threshold');
		remove_all_filters('aips_batch_max_jobs');
		remove_all_filters('aips_batch_queue_window_seconds');
		// Clear any wp_schedule_single_event calls recorded during the test.
		unset($GLOBALS['aips_test_single_events']);
		parent::tearDown();
	}

	// -----------------------------------------------------------------------
	// needs_batch_queue
	// -----------------------------------------------------------------------

	/** Below the default threshold (5), no batch queue is needed. */
	public function test_needs_batch_queue_below_threshold_returns_false() {
		$this->assertFalse($this->service->needs_batch_queue(1));
		$this->assertFalse($this->service->needs_batch_queue(3));
		$this->assertFalse($this->service->needs_batch_queue(4));
	}

	/** At or above the default threshold (5), a batch queue is required. */
	public function test_needs_batch_queue_at_threshold_returns_true() {
		$this->assertTrue($this->service->needs_batch_queue(5));
		$this->assertTrue($this->service->needs_batch_queue(10));
		$this->assertTrue($this->service->needs_batch_queue(20));
	}

	/** The threshold can be raised via filter. */
	public function test_needs_batch_queue_respects_threshold_filter() {
		add_filter('aips_large_batch_threshold', function() { return 10; });

		$this->assertFalse($this->service->needs_batch_queue(9));
		$this->assertTrue($this->service->needs_batch_queue(10));
		$this->assertTrue($this->service->needs_batch_queue(20));
	}

	/** The threshold is never lowered below 2 to avoid routing single-post schedules. */
	public function test_needs_batch_queue_threshold_minimum_is_2() {
		add_filter('aips_large_batch_threshold', function() { return 0; });

		$this->assertFalse($this->service->needs_batch_queue(1));
		$this->assertTrue($this->service->needs_batch_queue(2));
	}

	// -----------------------------------------------------------------------
	// calculate_config
	// -----------------------------------------------------------------------

	/** 20 posts with defaults: 10 batches of 2, 600 s window, 60-ish s apart. */
	public function test_calculate_config_20_posts_defaults() {
		$config = $this->service->calculate_config(20);

		$this->assertSame(10, $config['num_batches']);
		$this->assertSame(2, $config['posts_per_batch']);
		$this->assertSame(600, $config['window_seconds']);
		// interval = 600 / (10 - 1) ≈ 66.67
		$this->assertEqualsWithDelta(66.67, $config['interval_seconds'], 0.1);
	}

	/** 5 posts with defaults: 3 batches of 2 (last batch gets 1). */
	public function test_calculate_config_5_posts_defaults() {
		$config = $this->service->calculate_config(5);

		// ceil(5/2) = 3 batches; posts_per_batch = ceil(5/3) = 2
		$this->assertSame(3, $config['num_batches']);
		$this->assertSame(2, $config['posts_per_batch']);
	}

	/** Single resulting batch when quantity requires only 1 job. */
	public function test_calculate_config_2_posts() {
		$config = $this->service->calculate_config(2);

		$this->assertSame(1, $config['num_batches']);
		$this->assertSame(2, $config['posts_per_batch']);
		$this->assertSame(0.0, $config['interval_seconds']); // no spread when 1 batch
	}

	/** max_batches filter caps the number of jobs. */
	public function test_calculate_config_respects_max_batches_filter() {
		add_filter('aips_batch_max_jobs', function() { return 3; });

		$config = $this->service->calculate_config(20);

		$this->assertSame(3, $config['num_batches']);
		// posts_per_batch = ceil(20/3) = 7
		$this->assertSame(7, $config['posts_per_batch']);
	}

	/** window filter changes spread duration. */
	public function test_calculate_config_respects_window_filter() {
		add_filter('aips_batch_queue_window_seconds', function() { return 300; });

		$config = $this->service->calculate_config(20);

		$this->assertSame(300, $config['window_seconds']);
	}

	/** All calculated post slices sum to the original post_quantity. */
	public function test_calculate_config_slices_cover_total() {
		foreach (array(5, 7, 10, 15, 20, 50) as $qty) {
			$config          = $this->service->calculate_config($qty);
			$posts_per_batch = $config['posts_per_batch'];
			$num_batches     = $config['num_batches'];

			$covered = 0;
			for ($b = 0; $b < $num_batches; $b++) {
				$start            = $b * $posts_per_batch;
				$this_batch_count = min($posts_per_batch, $qty - $start);
				$covered         += $this_batch_count;
			}

			$this->assertSame(
				$qty,
				$covered,
				"Coverage mismatch for post_quantity={$qty}: expected {$qty}, got {$covered}"
			);
		}
	}

	// -----------------------------------------------------------------------
	// dispatch
	// -----------------------------------------------------------------------

	/** dispatch() registers the correct number of single events. */
	public function test_dispatch_registers_correct_number_of_events() {
		$schedule_id   = 42;
		$post_quantity = 10;
		$base_ts       = time();

		$this->service->dispatch($schedule_id, $post_quantity, $base_ts, 'test-corr-id');

		$scheduled = _get_cron_array();
		$events    = array();

		foreach ($scheduled as $ts => $hooks) {
			if (isset($hooks[AIPS_Batch_Queue_Service::HOOK])) {
				foreach ($hooks[AIPS_Batch_Queue_Service::HOOK] as $key => $job) {
					if ($job['args'][0] === $schedule_id) {
						$events[] = array_merge(array('ts' => $ts), $job['args']);
					}
				}
			}
		}

		// 10 posts / 2 per batch = 5 events.
		$this->assertCount(5, $events, 'Expected 5 batch events for 10 posts with default config.');
	}

	/** The first batch fires at or very near base_timestamp (no delay). */
	public function test_dispatch_first_batch_fires_immediately() {
		$schedule_id   = 99;
		$post_quantity = 10;
		// Use a fixed future timestamp to make the assertion deterministic
		// regardless of when the test runs.
		$base_ts = mktime(12, 0, 0, 1, 1, 2030); // 2030-01-01 12:00:00 UTC

		$this->service->dispatch($schedule_id, $post_quantity, $base_ts);

		$scheduled = _get_cron_array();
		$first_ts  = null;

		foreach ($scheduled as $ts => $hooks) {
			if (!isset($hooks[AIPS_Batch_Queue_Service::HOOK])) {
				continue;
			}
			foreach ($hooks[AIPS_Batch_Queue_Service::HOOK] as $job) {
				if ($job['args'][0] === $schedule_id && $job['args'][1] === 0) {
					$first_ts = $ts;
					break 2;
				}
			}
		}

		$this->assertNotNull($first_ts, 'First batch event not found.');
		// Batch 0 should fire at exactly $base_ts (no delay); check it matches.
		$this->assertSame($base_ts, $first_ts, 'First batch event should fire at base_timestamp.');
	}

	/** Batch args include schedule_id, start_index, batch_size, total, correlation_id. */
	public function test_dispatch_event_args_are_correct() {
		$schedule_id   = 7;
		$post_quantity = 4;
		$base_ts       = time();
		$corr_id       = 'abc-123';

		// 4 posts → ceil(4/2)=2 batches
		$this->service->dispatch($schedule_id, $post_quantity, $base_ts, $corr_id);

		$scheduled = _get_cron_array();
		$found     = array();

		foreach ($scheduled as $ts => $hooks) {
			if (!isset($hooks[AIPS_Batch_Queue_Service::HOOK])) {
				continue;
			}
			foreach ($hooks[AIPS_Batch_Queue_Service::HOOK] as $job) {
				if ($job['args'][0] === $schedule_id) {
					$found[] = $job['args'];
				}
			}
		}

		$this->assertCount(2, $found, '4 posts should produce 2 batch events.');

		// Sort by start_index (arg[1]).
		usort($found, function($a, $b) { return $a[1] <=> $b[1]; });

		// Batch 0: start_index=0, batch_size=2, total=4, corr_id='abc-123'
		$this->assertSame($schedule_id, $found[0][0]);
		$this->assertSame(0,            $found[0][1]); // start_index
		$this->assertSame(2,            $found[0][2]); // batch_size
		$this->assertSame(4,            $found[0][3]); // total
		$this->assertSame($corr_id,     $found[0][4]); // correlation_id

		// Batch 1: start_index=2, batch_size=2, total=4
		$this->assertSame(2, $found[1][1]); // start_index
		$this->assertSame(2, $found[1][2]); // batch_size
	}

	/** dispatch() returns the correct summary array. */
	public function test_dispatch_returns_summary() {
		$summary = $this->service->dispatch(1, 20, time());

		$this->assertArrayHasKey('num_batches', $summary);
		$this->assertArrayHasKey('posts_per_batch', $summary);
		$this->assertArrayHasKey('window_seconds', $summary);
		$this->assertSame(10, $summary['num_batches']);
		$this->assertSame(2,  $summary['posts_per_batch']);
		$this->assertSame(600, $summary['window_seconds']);
	}
}
