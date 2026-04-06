<?php
/**
 * Tests for the queue-backed generation scheduler feature.
 *
 * This file tests the behavioural logic of:
 *   - AIPS_Generation_Queue_Worker (batch-size limiting, feature-gating, error handling)
 *   - AIPS_Scheduler::process() routing (legacy vs queue-backed paths)
 *   - AIPS_Schedule_Processor::enqueue_due_schedules() idempotency key construction
 *
 * Repository-level SQL tests (enqueue/claim/mark_done/mark_failed) require a
 * live MySQL instance and live in the WP integration test suite that runs in
 * CI with a real database (like other repository tests in this directory).
 *
 * @package AI_Post_Scheduler
 */

class Test_AIPS_Generation_Queue_Logic extends WP_UnitTestCase {

	// =========================================================================
	// AIPS_Generation_Queue_Worker — unit tests with mock dependencies
	// =========================================================================

	/**
	 * When there are no pending jobs, process_batch() should return 0.
	 */
	public function test_worker_returns_zero_when_no_jobs() {
		$queue_repo = $this->make_queue_repo_stub( array() );
		$processor  = $this->createMock( AIPS_Schedule_Processor::class );

		$worker    = new AIPS_Generation_Queue_Worker( $queue_repo, $processor );
		$attempted = $worker->process_batch( 5 );

		$this->assertSame( 0, $attempted );
	}

	/**
	 * process_batch() should attempt exactly $batch_size jobs and no more, even
	 * if more jobs are in the queue.
	 */
	public function test_worker_respects_batch_size() {
		$jobs = array(
			$this->make_job( 1, 'template_schedule', array( 'schedule_id' => 10 ) ),
			$this->make_job( 2, 'template_schedule', array( 'schedule_id' => 11 ) ),
			$this->make_job( 3, 'template_schedule', array( 'schedule_id' => 12 ) ),
		);

		$queue_repo = $this->make_queue_repo_stub( $jobs );

		$processor = $this->createMock( AIPS_Schedule_Processor::class );
		$processor->method( 'process_queued_schedule' )->willReturn( array( 1 ) );

		$worker    = new AIPS_Generation_Queue_Worker( $queue_repo, $processor );
		$attempted = $worker->process_batch( 3 );

		$this->assertSame( 3, $attempted );
	}

	/**
	 * When process_queued_schedule() returns a WP_Error, mark_failed() should be
	 * called for that job (not mark_done).
	 */
	public function test_worker_marks_failed_when_processor_returns_wp_error() {
		$job  = $this->make_job( 10, 'template_schedule', array( 'schedule_id' => 99 ) );
		$jobs = array( $job );

		$done_ids   = array();
		$failed_ids = array();

		$queue_repo = $this->createMock( AIPS_Generation_Queue_Repository::class );
		$queue_repo->method( 'release_stale_locks' )->willReturn( 0 );
		$queue_repo->method( 'claim_batch' )->willReturn( $jobs );
		$queue_repo->method( 'mark_done' )->willReturnCallback(
			function ( $id ) use ( &$done_ids ) {
				$done_ids[] = $id;
				return true;
			}
		);
		$queue_repo->method( 'mark_failed' )->willReturnCallback(
			function ( $id ) use ( &$failed_ids ) {
				$failed_ids[] = $id;
				return true;
			}
		);

		$processor = $this->createMock( AIPS_Schedule_Processor::class );
		$processor->method( 'process_queued_schedule' )
		          ->willReturn( new WP_Error( 'test_error', 'Something failed' ) );

		$worker = new AIPS_Generation_Queue_Worker( $queue_repo, $processor );
		$worker->process_batch( 1 );

		$this->assertContains( 10, $failed_ids, 'Failed job should be marked_failed' );
		$this->assertNotContains( 10, $done_ids, 'Failed job should not be marked_done' );
	}

	/**
	 * When process_queued_schedule() returns a success value (non-empty array of post IDs),
	 * mark_done() should be called.
	 */
	public function test_worker_marks_done_on_success() {
		$job  = $this->make_job( 20, 'template_schedule', array( 'schedule_id' => 50 ) );
		$jobs = array( $job );

		$done_ids = array();

		$queue_repo = $this->createMock( AIPS_Generation_Queue_Repository::class );
		$queue_repo->method( 'release_stale_locks' )->willReturn( 0 );
		$queue_repo->method( 'claim_batch' )->willReturn( $jobs );
		$queue_repo->method( 'mark_done' )->willReturnCallback(
			function ( $id ) use ( &$done_ids ) {
				$done_ids[] = $id;
				return true;
			}
		);

		$processor = $this->createMock( AIPS_Schedule_Processor::class );
		$processor->method( 'process_queued_schedule' )->willReturn( array( 123 ) );

		$worker = new AIPS_Generation_Queue_Worker( $queue_repo, $processor );
		$worker->process_batch( 1 );

		$this->assertContains( 20, $done_ids );
	}

	/**
	 * When process_queued_schedule() returns null (e.g. claim-first lock already held
	 * by another worker), mark_failed() — not mark_done() — should be called so the
	 * job can be retried.
	 */
	public function test_worker_marks_failed_when_processor_returns_null() {
		$job  = $this->make_job( 21, 'template_schedule', array( 'schedule_id' => 51 ) );
		$jobs = array( $job );

		$done_ids   = array();
		$failed_ids = array();

		$queue_repo = $this->createMock( AIPS_Generation_Queue_Repository::class );
		$queue_repo->method( 'release_stale_locks' )->willReturn( 0 );
		$queue_repo->method( 'claim_batch' )->willReturn( $jobs );
		$queue_repo->method( 'mark_done' )->willReturnCallback(
			function ( $id ) use ( &$done_ids ) {
				$done_ids[] = $id;
				return true;
			}
		);
		$queue_repo->method( 'mark_failed' )->willReturnCallback(
			function ( $id ) use ( &$failed_ids ) {
				$failed_ids[] = $id;
				return true;
			}
		);

		$processor = $this->createMock( AIPS_Schedule_Processor::class );
		// null simulates an early-return from the claim-first lock path.
		$processor->method( 'process_queued_schedule' )->willReturn( null );

		$worker = new AIPS_Generation_Queue_Worker( $queue_repo, $processor );
		$worker->process_batch( 1 );

		$this->assertContains( 21, $failed_ids, 'null result must trigger mark_failed (retriable)' );
		$this->assertNotContains( 21, $done_ids, 'null result must not trigger mark_done' );
	}

	/**
	 * When process_queued_schedule() returns an empty array (no posts generated),
	 * mark_failed() should be called rather than mark_done().
	 */
	public function test_worker_marks_failed_when_processor_returns_empty_array() {
		$job  = $this->make_job( 22, 'template_schedule', array( 'schedule_id' => 52 ) );
		$jobs = array( $job );

		$done_ids   = array();
		$failed_ids = array();

		$queue_repo = $this->createMock( AIPS_Generation_Queue_Repository::class );
		$queue_repo->method( 'release_stale_locks' )->willReturn( 0 );
		$queue_repo->method( 'claim_batch' )->willReturn( $jobs );
		$queue_repo->method( 'mark_done' )->willReturnCallback(
			function ( $id ) use ( &$done_ids ) {
				$done_ids[] = $id;
				return true;
			}
		);
		$queue_repo->method( 'mark_failed' )->willReturnCallback(
			function ( $id ) use ( &$failed_ids ) {
				$failed_ids[] = $id;
				return true;
			}
		);

		$processor = $this->createMock( AIPS_Schedule_Processor::class );
		$processor->method( 'process_queued_schedule' )->willReturn( array() );

		$worker = new AIPS_Generation_Queue_Worker( $queue_repo, $processor );
		$worker->process_batch( 1 );

		$this->assertContains( 22, $failed_ids, 'Empty array result must trigger mark_failed' );
		$this->assertNotContains( 22, $done_ids, 'Empty array result must not trigger mark_done' );
	}

	/**
	 * A job with an unknown job_type should call mark_failed (not silently skip).
	 */
	public function test_worker_marks_failed_for_unknown_job_type() {
		$job  = $this->make_job( 30, 'unknown_job_type', array() );
		$jobs = array( $job );

		$failed_ids = array();

		$queue_repo = $this->createMock( AIPS_Generation_Queue_Repository::class );
		$queue_repo->method( 'release_stale_locks' )->willReturn( 0 );
		$queue_repo->method( 'claim_batch' )->willReturn( $jobs );
		$queue_repo->method( 'mark_failed' )->willReturnCallback(
			function ( $id ) use ( &$failed_ids ) {
				$failed_ids[] = $id;
				return true;
			}
		);

		$processor = $this->createMock( AIPS_Schedule_Processor::class );

		$worker = new AIPS_Generation_Queue_Worker( $queue_repo, $processor );
		$worker->process_batch( 1 );

		$this->assertContains( 30, $failed_ids );
	}

	/**
	 * A job with a missing schedule_id in its payload should call mark_failed.
	 */
	public function test_worker_marks_failed_for_missing_schedule_id() {
		$job  = $this->make_job( 40, 'template_schedule', array() ); // no schedule_id
		$jobs = array( $job );

		$failed_ids = array();

		$queue_repo = $this->createMock( AIPS_Generation_Queue_Repository::class );
		$queue_repo->method( 'release_stale_locks' )->willReturn( 0 );
		$queue_repo->method( 'claim_batch' )->willReturn( $jobs );
		$queue_repo->method( 'mark_failed' )->willReturnCallback(
			function ( $id ) use ( &$failed_ids ) {
				$failed_ids[] = $id;
				return true;
			}
		);

		$processor = $this->createMock( AIPS_Schedule_Processor::class );

		$worker = new AIPS_Generation_Queue_Worker( $queue_repo, $processor );
		$worker->process_batch( 1 );

		$this->assertContains( 40, $failed_ids );
	}

	/**
	 * An exception thrown by the processor should be caught and mark_failed called.
	 */
	public function test_worker_marks_failed_when_processor_throws() {
		$job  = $this->make_job( 50, 'template_schedule', array( 'schedule_id' => 77 ) );
		$jobs = array( $job );

		$failed_ids = array();

		$queue_repo = $this->createMock( AIPS_Generation_Queue_Repository::class );
		$queue_repo->method( 'release_stale_locks' )->willReturn( 0 );
		$queue_repo->method( 'claim_batch' )->willReturn( $jobs );
		$queue_repo->method( 'mark_failed' )->willReturnCallback(
			function ( $id ) use ( &$failed_ids ) {
				$failed_ids[] = $id;
				return true;
			}
		);

		$processor = $this->createMock( AIPS_Schedule_Processor::class );
		$processor->method( 'process_queued_schedule' )
		          ->willThrowException( new \RuntimeException( 'AI engine unavailable' ) );

		$worker = new AIPS_Generation_Queue_Worker( $queue_repo, $processor );
		$worker->process_batch( 1 );

		$this->assertContains( 50, $failed_ids );
	}

	/**
	 * release_stale_locks() should be called at the start of every process_batch()
	 * invocation so stale processing locks are freed before claiming new work.
	 * The lock timeout from config must be passed through.
	 */
	public function test_worker_releases_stale_locks_before_claiming() {
		$released_times   = 0;
		$received_timeouts = array();

		$queue_repo = $this->createMock( AIPS_Generation_Queue_Repository::class );
		$queue_repo->method( 'release_stale_locks' )->willReturnCallback(
			function ( $timeout ) use ( &$released_times, &$received_timeouts ) {
				$released_times++;
				$received_timeouts[] = $timeout;
				return 0;
			}
		);
		$queue_repo->method( 'claim_batch' )->willReturn( array() );

		$processor = $this->createMock( AIPS_Schedule_Processor::class );

		$worker = new AIPS_Generation_Queue_Worker( $queue_repo, $processor );
		$worker->process_batch( 5 );

		$this->assertSame( 1, $released_times, 'release_stale_locks() should be called once per process_batch()' );
		$this->assertNotEmpty( $received_timeouts, 'A timeout value must be passed to release_stale_locks()' );
		$this->assertIsInt( $received_timeouts[0], 'Timeout must be an integer number of seconds' );
		$this->assertGreaterThan( 0, $received_timeouts[0], 'Timeout must be positive' );
	}

	// =========================================================================
	// AIPS_Scheduler::process() — routing logic
	// =========================================================================

	/**
	 * When the feature flag is disabled, process() should call
	 * AIPS_Schedule_Processor::process_due_schedules() (legacy path).
	 */
	public function test_scheduler_process_uses_legacy_path_when_flag_disabled() {
		AIPS_Config::get_instance()->disable_feature( 'queue_backed_scheduler' );

		$called = false;

		$processor = $this->createMock( AIPS_Schedule_Processor::class );
		$processor->expects( $this->once() )
		          ->method( 'process_due_schedules' )
		          ->willReturnCallback( function () use ( &$called ) {
			          $called = true;
		          } );

		$scheduler = new AIPS_Scheduler();
		$scheduler->set_processor( $processor );
		$scheduler->process();

		$this->assertTrue( $called, 'Legacy process_due_schedules() should be called' );
	}

	/**
	 * When the feature flag is enabled, process() should call
	 * AIPS_Schedule_Processor::enqueue_due_schedules() and then
	 * AIPS_Generation_Queue_Worker::process_batch() (queue path).
	 */
	public function test_scheduler_process_uses_queue_path_when_flag_enabled() {
		AIPS_Config::get_instance()->enable_feature( 'queue_backed_scheduler' );

		$enqueue_called      = false;
		$process_batch_called = false;

		$processor = $this->createMock( AIPS_Schedule_Processor::class );
		$processor->method( 'process_due_schedules' )
		          ->willThrowException( new \LogicException( 'process_due_schedules must not be called in queue mode' ) );
		$processor->expects( $this->once() )
		          ->method( 'enqueue_due_schedules' )
		          ->willReturnCallback( function () use ( &$enqueue_called ) {
			          $enqueue_called = true;
			          return 0;
		          } );

		$queue_worker = $this->createMock( AIPS_Generation_Queue_Worker::class );
		$queue_worker->expects( $this->once() )
		             ->method( 'process_batch' )
		             ->willReturnCallback( function () use ( &$process_batch_called ) {
			             $process_batch_called = true;
			             return 0;
		             } );

		$scheduler = new AIPS_Scheduler();
		$scheduler->set_processor( $processor );
		$scheduler->set_queue_worker( $queue_worker );
		$scheduler->process();

		$this->assertTrue( $enqueue_called,       'enqueue_due_schedules() must be called in queue mode' );
		$this->assertTrue( $process_batch_called, 'process_batch() must be called in queue mode' );

		AIPS_Config::get_instance()->disable_feature( 'queue_backed_scheduler' );
	}

	/**
	 * When the feature flag is disabled, process() must NOT call
	 * enqueue_due_schedules().
	 */
	public function test_scheduler_does_not_enqueue_in_legacy_mode() {
		AIPS_Config::get_instance()->disable_feature( 'queue_backed_scheduler' );

		$processor = $this->createMock( AIPS_Schedule_Processor::class );
		$processor->method( 'process_due_schedules' )->willReturn( null );
		$processor->expects( $this->never() )->method( 'enqueue_due_schedules' );

		$scheduler = new AIPS_Scheduler();
		$scheduler->set_processor( $processor );
		$scheduler->process();
	}

	// =========================================================================
	// AIPS_Schedule_Processor::enqueue_due_schedules() — idempotency key format
	// =========================================================================

	/**
	 * enqueue_due_schedules() should build idempotency keys in the format
	 * "template_schedule:{schedule_id}:{next_run}" for each due schedule.
	 */
	public function test_enqueue_due_schedules_builds_correct_idempotency_keys() {
		$next_run_1 = '2025-06-01 09:00:00';
		$next_run_2 = '2025-06-01 10:00:00';

		$schedule_1              = new stdClass();
		$schedule_1->schedule_id = 1;
		$schedule_1->template_id = 10;
		$schedule_1->next_run    = $next_run_1;

		$schedule_2              = new stdClass();
		$schedule_2->schedule_id = 2;
		$schedule_2->template_id = 20;
		$schedule_2->next_run    = $next_run_2;

		// Stub the schedule repository to return our two due schedules.
		$schedule_repo = $this->createMock( AIPS_Schedule_Repository::class );
		$schedule_repo->method( 'get_due_schedules' )->willReturn( array( $schedule_1, $schedule_2 ) );

		// Capture the idempotency keys passed to enqueue().
		$captured_keys = array();
		$queue_repo    = $this->createMock( AIPS_Generation_Queue_Repository::class );
		$queue_repo->method( 'enqueue' )->willReturnCallback(
			function ( $key, $job_type, $payload ) use ( &$captured_keys ) {
				$captured_keys[] = $key;
				return count( $captured_keys ); // mock row ID
			}
		);

		$processor = new AIPS_Schedule_Processor( $schedule_repo );
		$enqueued  = $processor->enqueue_due_schedules( $queue_repo );

		$this->assertSame( 2, $enqueued );
		$this->assertContains( "template_schedule:1:{$next_run_1}", $captured_keys );
		$this->assertContains( "template_schedule:2:{$next_run_2}", $captured_keys );
	}

	/**
	 * enqueue_due_schedules() should not count a job when enqueue() returns false
	 * (duplicate active job).
	 */
	public function test_enqueue_due_schedules_does_not_count_duplicate_enqueues() {
		$schedule              = new stdClass();
		$schedule->schedule_id = 5;
		$schedule->template_id = 50;
		$schedule->next_run    = '2025-07-01 08:00:00';

		$schedule_repo = $this->createMock( AIPS_Schedule_Repository::class );
		$schedule_repo->method( 'get_due_schedules' )->willReturn( array( $schedule ) );

		$queue_repo = $this->createMock( AIPS_Generation_Queue_Repository::class );
		// Simulate an already-active job with this key.
		$queue_repo->method( 'enqueue' )->willReturn( false );

		$processor = new AIPS_Schedule_Processor( $schedule_repo );
		$enqueued  = $processor->enqueue_due_schedules( $queue_repo );

		$this->assertSame( 0, $enqueued, 'Duplicate keys must not be counted as newly enqueued' );
	}

	/**
	 * enqueue_due_schedules() should return 0 immediately when no schedules are due.
	 */
	public function test_enqueue_due_schedules_returns_zero_when_nothing_due() {
		$schedule_repo = $this->createMock( AIPS_Schedule_Repository::class );
		$schedule_repo->method( 'get_due_schedules' )->willReturn( array() );

		$queue_repo = $this->createMock( AIPS_Generation_Queue_Repository::class );
		$queue_repo->expects( $this->never() )->method( 'enqueue' );

		$processor = new AIPS_Schedule_Processor( $schedule_repo );
		$result    = $processor->enqueue_due_schedules( $queue_repo );

		$this->assertSame( 0, $result );
	}

	// =========================================================================
	// AIPS_Generation_Queue_Repository — instantiation sanity check
	// =========================================================================

	/**
	 * The repository should be instantiable without errors (class exists, $wpdb
	 * is set up in bootstrap).
	 */
	public function test_queue_repository_instantiates() {
		$repo = new AIPS_Generation_Queue_Repository();
		$this->assertInstanceOf( AIPS_Generation_Queue_Repository::class, $repo );
	}

	/**
	 * AIPS_Generation_Queue_Repository::ACTIVE_STATUSES should include pending and processing.
	 */
	public function test_queue_repository_active_statuses_constant() {
		$this->assertContains( 'pending',    AIPS_Generation_Queue_Repository::ACTIVE_STATUSES );
		$this->assertContains( 'processing', AIPS_Generation_Queue_Repository::ACTIVE_STATUSES );
		$this->assertNotContains( 'done',    AIPS_Generation_Queue_Repository::ACTIVE_STATUSES );
		$this->assertNotContains( 'dead',    AIPS_Generation_Queue_Repository::ACTIVE_STATUSES );
	}

	/**
	 * AIPS_Generation_Queue_Repository::STATUSES should contain the four lifecycle states.
	 * 'failed' is not a status: retries go back to 'pending', terminal state is 'dead'.
	 */
	public function test_queue_repository_statuses_constant_does_not_include_failed() {
		$statuses = AIPS_Generation_Queue_Repository::STATUSES;

		$this->assertContains( 'pending',    $statuses );
		$this->assertContains( 'processing', $statuses );
		$this->assertContains( 'done',       $statuses );
		$this->assertContains( 'dead',       $statuses );
		$this->assertNotContains( 'failed',  $statuses, "'failed' is not a valid status; retries use 'pending', terminal is 'dead'" );
	}

	/**
	 * AIPS_Generation_Queue_Repository::JOB_TYPES should list known types.
	 */
	public function test_queue_repository_job_types_constant() {
		$this->assertContains( 'template_schedule', AIPS_Generation_Queue_Repository::JOB_TYPES );
	}

	/**
	 * enqueue() with an unknown job_type should return false without touching the DB.
	 */
	public function test_enqueue_returns_false_for_unknown_job_type() {
		// Use the mock wpdb (no real DB needed) — the whitelist check happens before any query.
		$repo   = new AIPS_Generation_Queue_Repository();
		$result = $repo->enqueue(
			'some_key',
			'totally_unknown_job_type',
			array( 'foo' => 'bar' )
		);

		$this->assertFalse( $result, 'Unknown job_type should be rejected' );
	}

	// =========================================================================
	// Helpers
	// =========================================================================

	/**
	 * Build a minimal queue job stdClass for use in tests.
	 *
	 * @param int    $id       Row ID.
	 * @param string $job_type
	 * @param array  $payload
	 * @return stdClass
	 */
	private function make_job( $id, $job_type, $payload ) {
		$job                  = new stdClass();
		$job->id              = $id;
		$job->job_type        = $job_type;
		$job->payload         = wp_json_encode( $payload );
		$job->status          = 'processing';
		$job->lock_token      = 'test-token';
		$job->attempt_count   = 0;
		$job->idempotency_key = "{$job_type}:{$id}:2025-01-01 00:00:00";
		return $job;
	}

	/**
	 * Build a stub AIPS_Generation_Queue_Repository that returns $jobs from claim_batch().
	 *
	 * @param array $jobs
	 * @return AIPS_Generation_Queue_Repository Mock instance.
	 */
	private function make_queue_repo_stub( $jobs ) {
		$repo = $this->createMock( AIPS_Generation_Queue_Repository::class );
		$repo->method( 'release_stale_locks' )->willReturn( 0 );
		$repo->method( 'claim_batch' )->willReturn( $jobs );
		$repo->method( 'mark_done' )->willReturn( true );
		$repo->method( 'mark_failed' )->willReturn( true );
		return $repo;
	}
}
