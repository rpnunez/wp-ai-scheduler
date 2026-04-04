<?php
/**
 * Tests for AIPS_Bulk_Generator_Service and AIPS_Bulk_Generation_Result
 *
 * Covers:
 *  - Happy path: all items succeed
 *  - All-failure path
 *  - Partial success path
 *  - Hard limit exceeded (was_limited, no history container)
 *  - Soft limit truncation (was_limited, history created)
 *  - Zero filter value falls back to default
 *  - error_formatter option applied to errors array
 *  - History type / trigger preserved in service call
 *  - Result object fields populated correctly
 *
 * @package AI_Post_Scheduler
 */

// ---------------------------------------------------------------------------
// Stubs used throughout this file
// ---------------------------------------------------------------------------

/**
 * No-op history container stub — records calls without touching the database.
 */
class Test_Stub_History_Container {
	/** @var array Log of all record() calls. */
	public $log_entries = array();

	/** @var string|null 'success' or 'failure' once completed. */
	public $completed = null;

	/** @var string|null Action passed to record_user_action(). */
	public $user_action = null;

	public function record( $log_type, $message, $input = null, $output = null, $context = array() ) {
		$this->log_entries[] = array( 'log_type' => $log_type, 'message' => $message );
		return true;
	}

	public function record_user_action( $action, $message, $data = array() ) {
		$this->user_action   = $action;
		$this->log_entries[] = array( 'log_type' => 'user_action', 'message' => $message );
		return true;
	}

	public function record_error( $message, $details = array(), $wp_error = null ) {
		$this->log_entries[] = array( 'log_type' => 'error', 'message' => $message );
		return true;
	}

	public function complete_success( $data = array() ) {
		$this->completed = 'success';
		return true;
	}

	public function complete_failure( $message, $data = array() ) {
		$this->completed = 'failure';
		return true;
	}
}

/**
 * Stub history service — creates Test_Stub_History_Container instances and
 * tracks all containers created during a test so assertions can inspect them.
 */
class Test_Stub_History_Service {
	/** @var array[] Meta about each container created: ['type', 'metadata', 'container']. */
	public $containers_created = array();

	public function create( $type, $metadata = array() ) {
		$container                  = new Test_Stub_History_Container();
		$this->containers_created[] = array(
			'type'      => $type,
			'metadata'  => $metadata,
			'container' => $container,
		);
		return $container;
	}
}

// ---------------------------------------------------------------------------
// Test case
// ---------------------------------------------------------------------------

class Test_AIPS_Bulk_Generator_Service extends WP_UnitTestCase {

	/** @var Test_Stub_History_Service */
	private $history_service;

	/** @var AIPS_Bulk_Generator_Service */
	private $service;

	public function setUp(): void {
		parent::setUp();
		$this->history_service = new Test_Stub_History_Service();
		$this->service         = new AIPS_Bulk_Generator_Service( $this->history_service );
	}

	public function tearDown(): void {
		remove_all_filters( 'aips_bulk_run_now_limit' );
		remove_all_filters( 'aips_custom_limit_filter' );
		parent::tearDown();
	}

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	/**
	 * Build a $generate_fn that always returns a fixed post ID (success).
	 *
	 * @param int $post_id
	 * @return callable
	 */
	private function fn_success( int $post_id ) {
		return function () use ( $post_id ) {
			return $post_id;
		};
	}

	/**
	 * Build a $generate_fn that always returns a WP_Error (failure).
	 *
	 * @param string $code
	 * @param string $message
	 * @return callable
	 */
	private function fn_failure( string $code = 'gen_failed', string $message = 'AI call failed' ) {
		return function () use ( $code, $message ) {
			return new WP_Error( $code, $message );
		};
	}

	/**
	 * Build a $generate_fn that alternates success/failure by call index.
	 * Odd-indexed calls (0-based) fail; even-indexed calls succeed.
	 *
	 * @param int $start_post_id Base post ID for successes.
	 * @return callable
	 */
	private function fn_alternating( int $start_post_id = 10 ) {
		$call = 0;
		return function () use ( &$call, $start_post_id ) {
			$current = $call++;
			if ( $current % 2 === 0 ) {
				return $start_post_id + $current;
			}
			return new WP_Error( 'gen_failed', 'Generation error on call ' . $current );
		};
	}

	/**
	 * Run the service with 3 items and return the result.
	 *
	 * @param callable $generate_fn
	 * @param array    $options
	 * @return AIPS_Bulk_Generation_Result
	 */
	private function run_three_items( callable $generate_fn, array $options = array() ): AIPS_Bulk_Generation_Result {
		return $this->service->run( array( 1, 2, 3 ), $generate_fn, $options );
	}

	// -------------------------------------------------------------------------
	// Result object field tests
	// -------------------------------------------------------------------------

	/**
	 * All items succeed: success_count = 3, failed_count = 0, errors empty.
	 */
	public function test_all_success_populates_result_correctly() {
		$result = $this->run_three_items( $this->fn_success( 42 ) );

		$this->assertInstanceOf( AIPS_Bulk_Generation_Result::class, $result );
		$this->assertSame( 3, $result->success_count );
		$this->assertSame( 0, $result->failed_count );
		$this->assertEmpty( $result->errors );
		$this->assertCount( 3, $result->post_ids );
		$this->assertFalse( $result->was_limited );
	}

	/**
	 * All items fail: success_count = 0, failed_count = 3, errors = 3 entries.
	 */
	public function test_all_failures_populates_result_correctly() {
		$result = $this->run_three_items( $this->fn_failure() );

		$this->assertSame( 0, $result->success_count );
		$this->assertSame( 3, $result->failed_count );
		$this->assertCount( 3, $result->errors );
		$this->assertEmpty( $result->post_ids );
		$this->assertFalse( $result->was_limited );
	}

	/**
	 * Alternating success/failure (items 1 & 3 succeed, item 2 fails for 3 items).
	 */
	public function test_partial_success_populates_result_correctly() {
		// Items: 1→success(10), 2→fail, 3→success(12)
		$result = $this->run_three_items( $this->fn_alternating( 10 ) );

		$this->assertSame( 2, $result->success_count );
		$this->assertSame( 1, $result->failed_count );
		$this->assertCount( 1, $result->errors );
		$this->assertCount( 2, $result->post_ids );
		$this->assertFalse( $result->was_limited );
	}

	// -------------------------------------------------------------------------
	// Hard limit tests
	// -------------------------------------------------------------------------

	/**
	 * When count(items) > limit in hard mode, return immediately with was_limited=true
	 * and failed_count = total submitted (no history container created).
	 */
	public function test_hard_limit_exceeded_returns_limited_result() {
		// Default limit = 5; submit 6 items.
		$result = $this->service->run(
			array( 1, 2, 3, 4, 5, 6 ),
			$this->fn_success( 99 )
		);

		$this->assertTrue( $result->was_limited );
		$this->assertSame( 6, $result->failed_count );
		$this->assertSame( 0, $result->success_count );
		$this->assertSame( 5, $result->max_bulk );
		$this->assertEmpty( $this->history_service->containers_created,
			'No history container should be created in hard-limit mode.' );
	}

	/**
	 * A filter returning 0 must fall back to the default (5) in hard mode.
	 */
	public function test_zero_filter_value_falls_back_to_default_in_hard_mode() {
		add_filter( 'aips_bulk_run_now_limit', function () { return 0; } );

		$result = $this->service->run(
			array( 1, 2, 3, 4, 5, 6 ),
			$this->fn_success( 99 )
		);

		$this->assertTrue( $result->was_limited );
		$this->assertSame( 5, $result->max_bulk );
	}

	/**
	 * Exactly at the limit (items == max_bulk) must NOT trigger was_limited.
	 */
	public function test_exactly_at_limit_does_not_trigger_hard_limit() {
		// Default limit = 5; submit exactly 5 items.
		$result = $this->service->run(
			array( 1, 2, 3, 4, 5 ),
			$this->fn_success( 42 )
		);

		$this->assertFalse( $result->was_limited );
		$this->assertSame( 5, $result->success_count );
	}

	// -------------------------------------------------------------------------
	// Soft limit tests
	// -------------------------------------------------------------------------

	/**
	 * In soft mode, items are truncated (not rejected) and was_limited=true.
	 * History is still created for the truncated batch.
	 */
	public function test_soft_limit_truncates_items_and_sets_was_limited() {
		$result = $this->service->run(
			array( 1, 2, 3, 4, 5, 6, 7 ),
			$this->fn_success( 42 ),
			array( 'limit_mode' => 'soft' )
		);

		// Only 5 items processed (default limit = 5).
		$this->assertTrue( $result->was_limited );
		$this->assertSame( 5, $result->success_count );
		$this->assertSame( 0, $result->failed_count );
		$this->assertSame( 5, $result->max_bulk );
		// History container must have been created for the truncated batch.
		$this->assertNotEmpty( $this->history_service->containers_created );
	}

	/**
	 * Soft-mode with a custom filter name should respect the correct filter.
	 */
	public function test_soft_limit_custom_filter_name() {
		add_filter( 'aips_custom_limit_filter', function () { return 2; } );

		$result = $this->service->run(
			array( 1, 2, 3, 4 ),
			$this->fn_success( 42 ),
			array(
				'limit_filter' => 'aips_custom_limit_filter',
				'limit_mode'   => 'soft',
			)
		);

		$this->assertTrue( $result->was_limited );
		$this->assertSame( 2, $result->success_count );
		$this->assertSame( 2, $result->max_bulk );
	}

	// -------------------------------------------------------------------------
	// Custom filter name (hard mode)
	// -------------------------------------------------------------------------

	/**
	 * The limit_filter option is used instead of the default filter name.
	 */
	public function test_custom_limit_filter_used_in_hard_mode() {
		add_filter( 'aips_custom_limit_filter', function () { return 3; } );

		// 4 items, limit 3 → hard reject.
		$result = $this->service->run(
			array( 1, 2, 3, 4 ),
			$this->fn_success( 99 ),
			array( 'limit_filter' => 'aips_custom_limit_filter' )
		);

		$this->assertTrue( $result->was_limited );
		$this->assertSame( 3, $result->max_bulk );
	}

	// -------------------------------------------------------------------------
	// error_formatter option
	// -------------------------------------------------------------------------

	/**
	 * When error_formatter is provided, it formats each error string.
	 */
	public function test_error_formatter_applied_to_errors() {
		$result = $this->service->run(
			array( 10, 20 ),
			$this->fn_failure( 'gen_failed', 'API error' ),
			array(
				'error_formatter' => function ( $item, $msg ) {
					return 'Topic ID ' . $item . ': ' . $msg;
				},
			)
		);

		$this->assertCount( 2, $result->errors );
		$this->assertStringContainsString( 'Topic ID 10', $result->errors[0] );
		$this->assertStringContainsString( 'API error', $result->errors[0] );
		$this->assertStringContainsString( 'Topic ID 20', $result->errors[1] );
	}

	/**
	 * Without error_formatter, errors fall back to "{item}: {message}" format.
	 */
	public function test_default_error_format_used_when_no_formatter() {
		$result = $this->service->run(
			array( 5 ),
			$this->fn_failure( 'gen_failed', 'Something broke' )
		);

		$this->assertCount( 1, $result->errors );
		$this->assertStringContainsString( '5', $result->errors[0] );
		$this->assertStringContainsString( 'Something broke', $result->errors[0] );
	}

	/**
	 * When $item is an array and no error_formatter is provided, the default
	 * formatter must NOT trigger "Array to string conversion" and must produce
	 * a useful JSON-encoded identifier rather than the literal word "Array".
	 */
	public function test_default_error_format_uses_json_encode_for_array_item() {
		$items = array( array( 'id' => 7, 'topic' => 'AI News' ) );

		$result = $this->service->run(
			$items,
			$this->fn_failure( 'gen_failed', 'Something broke' )
		);

		$this->assertCount( 1, $result->errors );
		// Must contain the error message.
		$this->assertStringContainsString( 'Something broke', $result->errors[0] );
		// Must NOT contain the literal "Array:" which indicates strval() was used.
		$this->assertStringNotContainsString( 'Array:', $result->errors[0] );
		// Must include the JSON-encoded item so it is actually informative.
		$this->assertStringContainsString( '"id":7', $result->errors[0] );
	}

	// -------------------------------------------------------------------------
	// History interaction
	// -------------------------------------------------------------------------

	/**
	 * History container is created with the correct history_type.
	 */
	public function test_history_type_passed_to_history_service() {
		$this->service->run(
			array( 1 ),
			$this->fn_success( 42 ),
			array( 'history_type' => 'my_custom_bulk_type' )
		);

		$this->assertCount( 1, $this->history_service->containers_created );
		$this->assertSame( 'my_custom_bulk_type', $this->history_service->containers_created[0]['type'] );
	}

	/**
	 * History container metadata contains trigger_name.
	 */
	public function test_trigger_name_appears_in_history_metadata() {
		$this->service->run(
			array( 1 ),
			$this->fn_success( 42 ),
			array( 'trigger_name' => 'ajax_my_handler' )
		);

		$meta = $this->history_service->containers_created[0]['metadata'];
		$this->assertSame( 'ajax_my_handler', $meta['trigger'] );
	}

	/**
	 * record_user_action is called with the user_action option value.
	 */
	public function test_user_action_recorded_in_history() {
		$this->service->run(
			array( 1 ),
			$this->fn_success( 42 ),
			array( 'user_action' => 'do_the_thing' )
		);

		$container = $this->history_service->containers_created[0]['container'];
		$this->assertSame( 'do_the_thing', $container->user_action );
	}

	/**
	 * On full success the history container is completed with complete_success().
	 */
	public function test_history_completed_as_success_on_all_success() {
		$this->service->run( array( 1 ), $this->fn_success( 42 ) );

		$container = $this->history_service->containers_created[0]['container'];
		$this->assertSame( 'success', $container->completed );
	}

	/**
	 * When any item fails, the history container is completed with complete_failure().
	 */
	public function test_history_completed_as_failure_on_partial_fail() {
		$this->run_three_items( $this->fn_alternating( 10 ) );

		$container = $this->history_service->containers_created[0]['container'];
		$this->assertSame( 'failure', $container->completed );
	}

	/**
	 * No history container is created when hard limit rejects the batch.
	 */
	public function test_no_history_created_on_hard_limit_rejection() {
		$this->service->run(
			array( 1, 2, 3, 4, 5, 6 ),
			$this->fn_success( 99 )
		);

		$this->assertEmpty( $this->history_service->containers_created );
	}

	// -------------------------------------------------------------------------
	// post_ids field
	// -------------------------------------------------------------------------

	/**
	 * Returned post_ids match the values returned by a successful generate_fn.
	 */
	public function test_post_ids_collected_correctly() {
		$calls = 0;
		$result = $this->service->run(
			array( 'a', 'b', 'c' ),
			function () use ( &$calls ) {
				return ++$calls * 100;
			}
		);

		$this->assertSame( array( 100, 200, 300 ), $result->post_ids );
	}

	/**
	 * Failed items do not contribute to post_ids.
	 */
	public function test_failed_items_not_in_post_ids() {
		$result = $this->service->run(
			array( 1, 2, 3 ),
			$this->fn_failure()
		);

		$this->assertEmpty( $result->post_ids );
	}

	// -------------------------------------------------------------------------
	// max_bulk field
	// -------------------------------------------------------------------------

	/**
	 * max_bulk on the result reflects the effective limit applied.
	 */
	public function test_max_bulk_reflects_effective_limit() {
		add_filter( 'aips_bulk_run_now_limit', function () { return 7; } );

		$result = $this->service->run( array( 1 ), $this->fn_success( 42 ) );

		$this->assertSame( 7, $result->max_bulk );
	}
}
