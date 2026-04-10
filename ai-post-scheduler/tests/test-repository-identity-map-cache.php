<?php
/**
 * Tests for in-request identity-map caching in AIPS_Template_Repository,
 * AIPS_Schedule_Repository, and AIPS_Voices_Repository.
 *
 * Each repository now holds a named AIPS_Cache (array driver) that caches
 * read results for the lifetime of the request and is flushed after any
 * write operation.
 *
 * @package AI_Post_Scheduler
 * @since 2.4.0
 */

// ---------------------------------------------------------------------------
// Shared wpdb call-counting proxy
// ---------------------------------------------------------------------------

/**
 * Minimal wpdb proxy that records calls to get_row() and get_results() so
 * tests can assert how many DB round-trips were made.
 */
class Mock_WPDB_Query_Counter {

	public $prefix    = 'wp_';
	public $insert_id = 0;

	/** @var int How many get_row() calls were made. */
	public $get_row_calls = 0;

	/** @var int How many get_results() calls were made. */
	public $get_results_calls = 0;

	/** @var mixed Fixed return value for get_row(). */
	public $get_row_return = null;

	/** @var mixed Fixed return value for get_results(). */
	public $get_results_return = array();

	public function prepare( $query, ...$args ) {
		if ( empty( $args ) ) {
			return $query;
		}
		if ( count( $args ) === 1 && is_array( $args[0] ) ) {
			$args = $args[0];
		}
		foreach ( $args as $arg ) {
			$query = preg_replace( '/%[sd]/', is_numeric( $arg ) ? $arg : "'$arg'", $query, 1 );
		}
		return $query;
	}

	public function get_row( $query, $output = OBJECT, $y = 0 ) {
		$this->get_row_calls++;
		return $this->get_row_return;
	}

	public function get_results( $query, $output = OBJECT ) {
		$this->get_results_calls++;
		return $this->get_results_return;
	}

	public function insert( $table, $data, $format = null ) {
		$this->insert_id++;
		return true;
	}

	public function update( $table, $data, $where, $format = null, $where_format = null ) {
		return 1;
	}

	public function delete( $table, $where, $where_format = null ) {
		return 1;
	}

	public function query( $sql ) {
		return 1;
	}

	public function esc_like( $text ) {
		return addcslashes( $text, '_%\\' );
	}
}

// ---------------------------------------------------------------------------
// AIPS_Template_Repository caching tests
// ---------------------------------------------------------------------------

class Test_Template_Repository_Cache extends WP_UnitTestCase {

	/** @var Mock_WPDB_Query_Counter */
	private $mock_wpdb;

	/** @var mixed Original global $wpdb. */
	private $original_wpdb;

	public function setUp(): void {
		parent::setUp();
		AIPS_Cache_Factory::reset();

		global $wpdb;
		$this->original_wpdb = $wpdb;
		$this->mock_wpdb     = new Mock_WPDB_Query_Counter();
		$wpdb                = $this->mock_wpdb;
	}

	public function tearDown(): void {
		global $wpdb;
		$wpdb = $this->original_wpdb;
		AIPS_Cache_Factory::reset();
		parent::tearDown();
	}

	// -----------------------------------------------------------------------
	// get_by_id() caching
	// -----------------------------------------------------------------------

	public function test_get_by_id_hits_db_on_first_call() {
		$this->mock_wpdb->get_row_return = (object) array( 'id' => 7, 'name' => 'My Template' );
		$repo = new AIPS_Template_Repository();

		$result = $repo->get_by_id( 7 );

		$this->assertEquals( 1, $this->mock_wpdb->get_row_calls );
		$this->assertEquals( 'My Template', $result->name );
	}

	public function test_get_by_id_returns_cached_result_on_second_call() {
		$this->mock_wpdb->get_row_return = (object) array( 'id' => 7, 'name' => 'My Template' );
		$repo = new AIPS_Template_Repository();

		$repo->get_by_id( 7 );
		$repo->get_by_id( 7 );

		$this->assertEquals( 1, $this->mock_wpdb->get_row_calls, 'DB should be queried only once for the same ID.' );
	}

	public function test_get_by_id_null_result_is_not_cached() {
		$this->mock_wpdb->get_row_return = null;
		$repo = new AIPS_Template_Repository();

		$repo->get_by_id( 99 );
		$repo->get_by_id( 99 );

		$this->assertEquals( 2, $this->mock_wpdb->get_row_calls, 'Null results (not found) should not be cached.' );
	}

	// -----------------------------------------------------------------------
	// get_all() caching
	// -----------------------------------------------------------------------

	public function test_get_all_hits_db_on_first_call() {
		$this->mock_wpdb->get_results_return = array(
			(object) array( 'id' => 1, 'name' => 'A' ),
			(object) array( 'id' => 2, 'name' => 'B' ),
		);
		$repo = new AIPS_Template_Repository();

		$result = $repo->get_all();

		$this->assertEquals( 1, $this->mock_wpdb->get_results_calls );
		$this->assertCount( 2, $result );
	}

	public function test_get_all_returns_cached_result_on_second_call() {
		$this->mock_wpdb->get_results_return = array( (object) array( 'id' => 1, 'name' => 'A' ) );
		$repo = new AIPS_Template_Repository();

		$repo->get_all();
		$repo->get_all();

		$this->assertEquals( 1, $this->mock_wpdb->get_results_calls, 'DB should be queried only once for get_all().' );
	}

	public function test_get_all_active_only_uses_separate_cache_key() {
		$this->mock_wpdb->get_results_return = array( (object) array( 'id' => 1, 'name' => 'A' ) );
		$repo = new AIPS_Template_Repository();

		$repo->get_all( false );
		$repo->get_all( true );

		$this->assertEquals( 2, $this->mock_wpdb->get_results_calls, 'All-templates and active-only queries use separate cache keys.' );
	}

	// -----------------------------------------------------------------------
	// Cache invalidation on mutations
	// -----------------------------------------------------------------------

	public function test_cache_is_flushed_after_create() {
		$this->mock_wpdb->get_results_return = array( (object) array( 'id' => 1, 'name' => 'A' ) );
		$repo = new AIPS_Template_Repository();

		$repo->get_all(); // Populate cache.
		$repo->create( array(
			'name'           => 'New',
			'prompt_template' => 'prompt',
			'post_status'    => 'draft',
			'post_category'  => 0,
		) );
		$repo->get_all(); // Should re-query.

		$this->assertEquals( 2, $this->mock_wpdb->get_results_calls, 'Cache should be flushed after create().' );
	}

	public function test_cache_is_flushed_after_update() {
		$row = (object) array( 'id' => 5, 'name' => 'Old' );
		$this->mock_wpdb->get_row_return = $row;
		$repo = new AIPS_Template_Repository();

		$repo->get_by_id( 5 ); // Populate cache.
		$repo->update( 5, array( 'name' => 'Updated' ) );
		$repo->get_by_id( 5 ); // Should re-query.

		$this->assertEquals( 2, $this->mock_wpdb->get_row_calls, 'Cache should be flushed after update().' );
	}

	public function test_cache_is_flushed_after_delete() {
		$this->mock_wpdb->get_results_return = array( (object) array( 'id' => 1, 'name' => 'A' ) );
		$repo = new AIPS_Template_Repository();

		$repo->get_all(); // Populate cache.
		$repo->delete( 1 );
		$repo->get_all(); // Should re-query.

		$this->assertEquals( 2, $this->mock_wpdb->get_results_calls, 'Cache should be flushed after delete().' );
	}

	// -----------------------------------------------------------------------
	// Named cache is shared across instances
	// -----------------------------------------------------------------------

	public function test_named_cache_shared_across_repository_instances() {
		$row = (object) array( 'id' => 3, 'name' => 'Shared' );
		$this->mock_wpdb->get_row_return = $row;

		$repo_a = new AIPS_Template_Repository();
		$repo_b = new AIPS_Template_Repository();

		$repo_a->get_by_id( 3 ); // Warms the named cache.
		$repo_b->get_by_id( 3 ); // Should be served from the same named cache.

		$this->assertEquals( 1, $this->mock_wpdb->get_row_calls, 'Both instances share the named cache; DB should be queried only once.' );
	}
}

// ---------------------------------------------------------------------------
// AIPS_Schedule_Repository caching tests
// ---------------------------------------------------------------------------

class Test_Schedule_Repository_Cache extends WP_UnitTestCase {

	/** @var Mock_WPDB_Query_Counter */
	private $mock_wpdb;

	/** @var mixed Original global $wpdb. */
	private $original_wpdb;

	public function setUp(): void {
		parent::setUp();
		AIPS_Cache_Factory::reset();

		global $wpdb;
		$this->original_wpdb = $wpdb;
		$this->mock_wpdb     = new Mock_WPDB_Query_Counter();
		$wpdb                = $this->mock_wpdb;
	}

	public function tearDown(): void {
		global $wpdb;
		$wpdb = $this->original_wpdb;
		AIPS_Cache_Factory::reset();
		parent::tearDown();
	}

	public function test_get_by_id_cached_after_first_call() {
		$this->mock_wpdb->get_row_return = (object) array( 'id' => 10, 'template_id' => 1, 'frequency' => 'daily' );
		$repo = new AIPS_Schedule_Repository();

		$repo->get_by_id( 10 );
		$repo->get_by_id( 10 );

		$this->assertEquals( 1, $this->mock_wpdb->get_row_calls );
	}

	public function test_get_all_cached_after_first_call() {
		$this->mock_wpdb->get_results_return = array( (object) array( 'id' => 1 ) );
		$repo = new AIPS_Schedule_Repository();

		$repo->get_all();
		$repo->get_all();

		$this->assertEquals( 1, $this->mock_wpdb->get_results_calls );
	}

	public function test_get_active_schedules_cached_after_first_call() {
		$this->mock_wpdb->get_results_return = array( (object) array( 'template_id' => 1, 'frequency' => 'daily' ) );
		$repo = new AIPS_Schedule_Repository();

		$repo->get_active_schedules();
		$repo->get_active_schedules();

		$this->assertEquals( 1, $this->mock_wpdb->get_results_calls );
	}

	public function test_get_due_schedules_cached_for_same_parameters() {
		$this->mock_wpdb->get_results_return = array( (object) array( 'schedule_id' => 1 ) );
		$repo = new AIPS_Schedule_Repository();
		$time = '2025-01-01 10:00:00';

		$repo->get_due_schedules( $time, 5 );
		$repo->get_due_schedules( $time, 5 );

		$this->assertEquals( 1, $this->mock_wpdb->get_results_calls );
	}

	public function test_cache_flushed_after_delete() {
		$this->mock_wpdb->get_results_return = array( (object) array( 'id' => 1 ) );
		$repo = new AIPS_Schedule_Repository();

		$repo->get_all();
		$repo->delete( 1 );
		$repo->get_all();

		$this->assertEquals( 2, $this->mock_wpdb->get_results_calls );
	}

	public function test_cache_flushed_after_update() {
		$this->mock_wpdb->get_row_return = (object) array( 'id' => 5 );
		$repo = new AIPS_Schedule_Repository();

		$repo->get_by_id( 5 );
		$repo->update( 5, array( 'frequency' => 'weekly' ) );
		$repo->get_by_id( 5 );

		$this->assertEquals( 2, $this->mock_wpdb->get_row_calls );
	}
}

// ---------------------------------------------------------------------------
// AIPS_Voices_Repository caching tests
// ---------------------------------------------------------------------------

class Test_Voices_Repository_Cache extends WP_UnitTestCase {

	/** @var Mock_WPDB_Query_Counter */
	private $mock_wpdb;

	/** @var mixed Original global $wpdb. */
	private $original_wpdb;

	public function setUp(): void {
		parent::setUp();
		AIPS_Cache_Factory::reset();

		global $wpdb;
		$this->original_wpdb = $wpdb;
		$this->mock_wpdb     = new Mock_WPDB_Query_Counter();
		$wpdb                = $this->mock_wpdb;
	}

	public function tearDown(): void {
		global $wpdb;
		$wpdb = $this->original_wpdb;
		AIPS_Cache_Factory::reset();
		parent::tearDown();
	}

	public function test_get_all_cached_after_first_call() {
		$this->mock_wpdb->get_results_return = array(
			(object) array( 'id' => 1, 'name' => 'Voice A', 'is_active' => 1 ),
		);
		$repo = new AIPS_Voices_Repository();

		$repo->get_all();
		$repo->get_all();

		$this->assertEquals( 1, $this->mock_wpdb->get_results_calls );
	}

	public function test_get_all_active_only_uses_separate_cache_key() {
		$this->mock_wpdb->get_results_return = array( (object) array( 'id' => 1, 'name' => 'Voice A', 'is_active' => 1 ) );
		$repo = new AIPS_Voices_Repository();

		$repo->get_all( false );
		$repo->get_all( true );

		$this->assertEquals( 2, $this->mock_wpdb->get_results_calls );
	}

	public function test_get_by_id_cached_after_first_call() {
		$this->mock_wpdb->get_row_return = (object) array( 'id' => 2, 'name' => 'Voice B' );
		$repo = new AIPS_Voices_Repository();

		$repo->get_by_id( 2 );
		$repo->get_by_id( 2 );

		$this->assertEquals( 1, $this->mock_wpdb->get_row_calls );
	}

	public function test_get_by_id_null_not_cached() {
		$this->mock_wpdb->get_row_return = null;
		$repo = new AIPS_Voices_Repository();

		$repo->get_by_id( 99 );
		$repo->get_by_id( 99 );

		$this->assertEquals( 2, $this->mock_wpdb->get_row_calls );
	}

	public function test_cache_flushed_after_create() {
		$this->mock_wpdb->get_results_return = array( (object) array( 'id' => 1, 'name' => 'Voice A' ) );
		$repo = new AIPS_Voices_Repository();

		$repo->get_all();
		$repo->create( array( 'name' => 'New Voice', 'is_active' => 1 ) );
		$repo->get_all();

		$this->assertEquals( 2, $this->mock_wpdb->get_results_calls );
	}

	public function test_cache_flushed_after_update() {
		$this->mock_wpdb->get_row_return = (object) array( 'id' => 3, 'name' => 'Voice C' );
		$repo = new AIPS_Voices_Repository();

		$repo->get_by_id( 3 );
		$repo->update( 3, array( 'name' => 'Updated Voice C' ) );
		$repo->get_by_id( 3 );

		$this->assertEquals( 2, $this->mock_wpdb->get_row_calls );
	}

	public function test_cache_flushed_after_delete() {
		$this->mock_wpdb->get_results_return = array( (object) array( 'id' => 1, 'name' => 'Voice A' ) );
		$repo = new AIPS_Voices_Repository();

		$repo->get_all();
		$repo->delete( 1 );
		$repo->get_all();

		$this->assertEquals( 2, $this->mock_wpdb->get_results_calls );
	}
}
