<?php
/**
 * Regression tests for the background-process review fixes: tick watchdog,
 * binary vectors that look like JSON, message length, quota reserve vs the queue
 * threshold, tick hook registration, and direction-independent duplicate pairs.
 *
 * @package AI_Post_Scheduler
 */

/**
 * Minimal managed process used to observe the tick loop.
 */
class AIPS_Test_Watchdog_Process extends AIPS_Managed_Background_Process {

	/** @var int|false Next scheduled tick as seen from inside a slice. */
	public static $seen_inside_slice = false;

	/** @var string 'continue', 'done' or 'throw'. */
	public static $mode = 'continue';

	public function get_key(): string {
		return 'test_watchdog';
	}

	public function get_label(): string {
		return 'Test watchdog process';
	}

	public function get_description(): string {
		return 'Test process.';
	}

	protected function count_remaining(): int {
		return 5;
	}

	protected function process_slice( int $cursor, int $limit, array $options ): array {
		self::$seen_inside_slice = wp_next_scheduled( self::TICK_HOOK, array( 'test_watchdog' ) );

		if ( 'throw' === self::$mode ) {
			throw new RuntimeException( str_repeat( 'x', 700 ) );
		}

		return array(
			'processed' => 1,
			'failed'    => 0,
			'cursor'    => $cursor + 1,
			'done'      => ( 'done' === self::$mode ),
		);
	}
}

class Test_AIPS_Background_Process_Review_Fixes extends WP_UnitTestCase {

	/** @var AIPS_Background_Process_Repository */
	private $runs;

	public function setUp(): void {
		parent::setUp();
		AIPS_DB_Manager::install_tables();

		$this->runs = new AIPS_Background_Process_Repository();
		AIPS_Test_Watchdog_Process::$mode              = 'continue';
		AIPS_Test_Watchdog_Process::$seen_inside_slice = false;
		wp_clear_scheduled_hook( AIPS_Managed_Background_Process::TICK_HOOK, array( 'test_watchdog' ) );
	}

	public function tearDown(): void {
		global $wpdb;
		$wpdb->query( 'DELETE FROM ' . $wpdb->prefix . 'aips_background_processes' );
		$wpdb->query( 'DELETE FROM ' . $wpdb->prefix . 'aips_relationships' );
		$wpdb->query( 'DELETE FROM ' . $wpdb->prefix . 'aips_embeddings' );
		wp_clear_scheduled_hook( AIPS_Managed_Background_Process::TICK_HOOK, array( 'test_watchdog' ) );
		delete_option( 'aips_embeddings_daily_limit' );
		delete_option( 'aips_embeddings_weekly_limit' );
		delete_option( 'aips_embeddings_monthly_limit' );
		delete_option( 'aips_embeddings_rate_limits_enabled' );
		parent::tearDown();
	}

	// -------------------------------------------------------------------------
	// 1. Tick watchdog
	// -------------------------------------------------------------------------

	public function test_a_watchdog_tick_is_armed_while_a_slice_runs_and_replaced_afterwards() {
		$process = new AIPS_Test_Watchdog_Process( $this->runs );
		$process->start();

		// WP-Cron removes the event before running it.
		wp_clear_scheduled_hook( AIPS_Managed_Background_Process::TICK_HOOK, array( 'test_watchdog' ) );

		$process->tick();

		$this->assertNotFalse( AIPS_Test_Watchdog_Process::$seen_inside_slice, 'A slice that never returns would otherwise leave no tick queued.' );
		$this->assertGreaterThan( time() + 300, AIPS_Test_Watchdog_Process::$seen_inside_slice, 'The watchdog fires well after a normal slice.' );

		$next = wp_next_scheduled( AIPS_Managed_Background_Process::TICK_HOOK, array( 'test_watchdog' ) );
		$this->assertNotFalse( $next );
		$this->assertLessThan( time() + 60, $next, 'The watchdog is replaced by the real next tick.' );
		$this->assertSame( 'running', $process->get_snapshot()['status'] );
	}

	public function test_no_tick_is_left_behind_when_the_run_finishes() {
		AIPS_Test_Watchdog_Process::$mode = 'done';
		$process                          = new AIPS_Test_Watchdog_Process( $this->runs );
		$process->start();
		wp_clear_scheduled_hook( AIPS_Managed_Background_Process::TICK_HOOK, array( 'test_watchdog' ) );

		$process->tick();

		$this->assertFalse( wp_next_scheduled( AIPS_Managed_Background_Process::TICK_HOOK, array( 'test_watchdog' ) ) );
		$this->assertSame( 'completed', $process->get_snapshot()['last_status'] );
	}

	public function test_a_failing_slice_ends_the_run_with_a_clipped_message_and_no_tick() {
		AIPS_Test_Watchdog_Process::$mode = 'throw';
		$process                          = new AIPS_Test_Watchdog_Process( $this->runs );
		$process->start();
		wp_clear_scheduled_hook( AIPS_Managed_Background_Process::TICK_HOOK, array( 'test_watchdog' ) );

		$process->tick();

		$latest = $this->runs->get_latest( 'test_watchdog' );
		$this->assertSame( 'failed', $latest->status, 'A long error message must not stop the status from being written.' );
		$this->assertLessThanOrEqual( AIPS_Background_Process_Repository::MESSAGE_MAX_LENGTH, mb_strlen( $latest->message ) );
		$this->assertFalse( wp_next_scheduled( AIPS_Managed_Background_Process::TICK_HOOK, array( 'test_watchdog' ) ) );
	}

	public function test_the_tick_hook_is_registered_outside_cron_too() {
		$this->assertNotFalse( has_action( AIPS_Managed_Background_Process::TICK_HOOK ) );
	}

	// -------------------------------------------------------------------------
	// 7. Message length
	// -------------------------------------------------------------------------

	public function test_long_messages_are_clipped_for_transitions_and_progress() {
		$id   = $this->runs->create( 'demo', 10 );
		$long = str_repeat( 'é', 800 );

		$this->assertTrue( $this->runs->add_progress( $id, 3, 0, 7, 0, $long ), 'Progress is saved even with an oversize message.' );
		$run = $this->runs->get( $id );
		$this->assertSame( 3, $run->processed );
		$this->assertLessThanOrEqual( AIPS_Background_Process_Repository::MESSAGE_MAX_LENGTH, mb_strlen( $run->message ) );

		$this->assertTrue( $this->runs->transition( $id, 'failed', array( 'running' ), $long ) );
		$run = $this->runs->get( $id );
		$this->assertSame( 'failed', $run->status );
		$this->assertLessThanOrEqual( AIPS_Background_Process_Repository::MESSAGE_MAX_LENGTH, mb_strlen( $run->message ) );
	}

	// -------------------------------------------------------------------------
	// 3. Binary vectors whose first byte looks like JSON
	// -------------------------------------------------------------------------

	/**
	 * A packed float32 whose first byte is '[' (0x5B): the float is 0x3F80005B, about 1.0.
	 *
	 * @return string
	 */
	private function packed_vector_starting_with( int $first_byte ): string {
		return pack( 'C4', $first_byte, 0x00, 0x80, 0x3F ) . pack( 'f', 0.5 );
	}

	public function test_builder_decodes_binary_vectors_that_start_like_json() {
		foreach ( array( 0x5B, 0x7B ) as $first_byte ) {
			$vector = AIPS_Relationship_Builder::decode_vector( $this->packed_vector_starting_with( $first_byte ) );

			$this->assertCount( 2, $vector, sprintf( 'First byte 0x%02X', $first_byte ) );
			$this->assertEqualsWithDelta( 1.0, $vector[0], 0.001 );
			$this->assertEqualsWithDelta( 0.5, $vector[1], 0.0001 );
		}

		$this->assertSame( array( 1.0, 2.0 ), AIPS_Relationship_Builder::decode_vector( '[1,2]' ), 'Real JSON still decodes as JSON.' );
		$this->assertSame( array(), AIPS_Relationship_Builder::decode_vector( '[1,2,3' ), 'Broken JSON that is not whole floats is rejected.' );
	}

	public function test_embeddings_repository_decodes_binary_vectors_that_start_like_json() {
		$repo = new AIPS_Embeddings_Repository();

		foreach ( array( 0x5B, 0x7B ) as $first_byte ) {
			$vector = $repo->decode_embedding( $this->packed_vector_starting_with( $first_byte ) );

			$this->assertCount( 2, $vector, sprintf( 'First byte 0x%02X', $first_byte ) );
			$this->assertEqualsWithDelta( 1.0, $vector[0], 0.001 );
		}

		$this->assertSame( array( 1.0, 2.0 ), $repo->decode_embedding( '[1,2]' ) );
	}

	// -------------------------------------------------------------------------
	// 6. Reserve vs the queue's "approaching quota" threshold
	// -------------------------------------------------------------------------

	public function test_bulk_jobs_stop_before_the_queue_worker_pauses_itself() {
		update_option( 'aips_embeddings_rate_limits_enabled', true );
		update_option( 'aips_embeddings_daily_limit', 50 );
		update_option( 'aips_embeddings_weekly_limit', 0 );
		update_option( 'aips_embeddings_monthly_limit', 0 );

		$limiter = new AIPS_Embeddings_Rate_Limiter( AIPS_Config::get_instance() );
		$limiter->reset_history();
		$limiter->record_usage( 40 );

		$ratio = AIPS_Embeddings_Background_Process::QUOTA_RESERVE_RATIO;

		$this->assertSame( 0, $limiter->get_remaining_allowance( $ratio ), 'The bulk job has used its share.' );
		$this->assertFalse( $limiter->is_approaching_quota( 0.90 ), 'The queue worker is still free to index new posts.' );

		$limiter->reset_history();
	}

	// -------------------------------------------------------------------------
	// 8. Duplicate pairs found whichever direction they were stored in
	// -------------------------------------------------------------------------

	public function test_duplicate_pairs_are_found_when_the_newer_post_is_the_source() {
		$repo  = new AIPS_Relationships_Repository();
		$older = wp_insert_post( array( 'post_title' => 'Older', 'post_status' => 'publish', 'post_type' => 'post' ) );
		$newer = wp_insert_post( array( 'post_title' => 'Newer', 'post_status' => 'publish', 'post_type' => 'post' ) );

		// Stored from the newer post's side only: higher ID -> lower ID.
		$repo->upsert( 'post', $newer, 'post', $older, 0.95, 'related_post' );

		$pairs = $repo->get_top_duplicate_pairs( 0.90, 10, 'posts' );

		$this->assertCount( 1, $pairs );
		$this->assertSame( min( $older, $newer ), (int) $pairs[0]->source_id );
		$this->assertSame( max( $older, $newer ), (int) $pairs[0]->target_id );
		$this->assertEquals( 0.95, (float) $pairs[0]->similarity );
	}

	public function test_a_pair_stored_in_both_directions_is_reported_once_with_the_best_similarity() {
		$repo = new AIPS_Relationships_Repository();
		$a    = wp_insert_post( array( 'post_title' => 'A', 'post_status' => 'publish', 'post_type' => 'post' ) );
		$b    = wp_insert_post( array( 'post_title' => 'B', 'post_status' => 'publish', 'post_type' => 'post' ) );

		$repo->upsert( 'post', $a, 'post', $b, 0.91, 'related_post' );
		$repo->upsert( 'post', $b, 'post', $a, 0.93, 'related_post' );

		$pairs = $repo->get_top_duplicate_pairs( 0.90, 10, 'posts' );

		$this->assertCount( 1, $pairs );
		$this->assertEquals( 0.93, (float) $pairs[0]->similarity );
	}
}
