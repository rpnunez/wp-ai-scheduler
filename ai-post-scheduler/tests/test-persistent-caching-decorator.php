<?php
/**
 * Integration tests for persistent caching decorators.
 *
 * Verifies that AIPS_Caching_Template_Repository and
 * AIPS_Caching_Schedule_Repository:
 *  - reduce calls to the inner repository on repeated reads (cache-hit path)
 *  - invalidate the cache after write operations (create / update / delete)
 *  - pass non-cacheable reads straight through to the inner repository
 *  - are wired into the container when wp_using_ext_object_cache() is true
 *  - fall back to the concrete repository when no persistent cache is present
 *
 * @package AI_Post_Scheduler
 * @since   2.4.0
 */

// ---------------------------------------------------------------------------
// Spy / counter inner repositories
// ---------------------------------------------------------------------------

/**
 * Spy implementation of AIPS_Template_Repository_Interface.
 *
 * Counts calls to each method and returns configurable fixture data so
 * tests can verify that the decorator only calls the inner repo once
 * per cache-miss.
 */
class Spy_Template_Repository implements AIPS_Template_Repository_Interface {

	/** @var int */
	public $get_all_calls = 0;
	/** @var int */
	public $get_by_id_calls = 0;
	/** @var int */
	public $search_calls = 0;
	/** @var int */
	public $create_calls = 0;
	/** @var int */
	public $update_calls = 0;
	/** @var int */
	public $delete_calls = 0;
	/** @var int */
	public $set_active_calls = 0;

	/** @var mixed */
	public $get_all_return = array();
	/** @var mixed */
	public $get_by_id_return = null;
	/** @var mixed */
	public $create_return = 1;
	/** @var mixed */
	public $update_return = true;
	/** @var mixed */
	public $delete_return = true;
	/** @var mixed */
	public $set_active_return = true;

	public function get_all($active_only = false) {
		$this->get_all_calls++;
		return $this->get_all_return;
	}

	public function get_by_id($id) {
		$this->get_by_id_calls++;
		return $this->get_by_id_return;
	}

	public function search($search_term) {
		$this->search_calls++;
		return array();
	}

	public function create($data) {
		$this->create_calls++;
		return $this->create_return;
	}

	public function update($id, $data) {
		$this->update_calls++;
		return $this->update_return;
	}

	public function delete($id) {
		$this->delete_calls++;
		return $this->delete_return;
	}

	public function set_active($id, $is_active) {
		$this->set_active_calls++;
		return $this->set_active_return;
	}

	public function count_by_status() {
		return array( 'total' => 0, 'active' => 0 );
	}

	public function name_exists($name, $exclude_id = 0) {
		return false;
	}
}

/**
 * Spy implementation of AIPS_Schedule_Repository_Interface.
 *
 * Minimal stub – only the methods exercised by the decorator tests.
 */
class Spy_Schedule_Repository implements AIPS_Schedule_Repository_Interface {

	/** @var int */
	public $get_all_calls = 0;
	/** @var int */
	public $get_by_id_calls = 0;
	/** @var int */
	public $create_calls = 0;
	/** @var int */
	public $update_calls = 0;
	/** @var int */
	public $delete_calls = 0;
	/** @var int */
	public $update_last_run_calls = 0;

	/** @var mixed */
	public $get_all_return = array();
	/** @var mixed */
	public $get_by_id_return = null;

	public function get_all($active_only = false) {
		$this->get_all_calls++;
		return $this->get_all_return;
	}

	public function get_by_id($id) {
		$this->get_by_id_calls++;
		return $this->get_by_id_return;
	}

	public function get_due_schedules($current_time = null, $limit = 5) {
		return array();
	}

	public function create($data) {
		$this->create_calls++;
		return 1;
	}

	public function update($id, $data) {
		$this->update_calls++;
		return true;
	}

	public function delete($id) {
		$this->delete_calls++;
		return true;
	}

	public function update_last_run($id, $timestamp = null) {
		$this->update_last_run_calls++;
		return true;
	}

	public function set_active($id, $is_active) {
		return true;
	}

	public function update_batch_progress($id, $completed, $total, $last_index, $post_ids = array()) {
		return true;
	}

	public function clear_batch_progress($id) {
		return true;
	}

	public function update_run_state($id, array $state) {
		return true;
	}

	public function delete_bulk(array $ids) {
		return count($ids);
	}

	public function set_active_bulk(array $ids, $is_active) {
		return count($ids);
	}

	public function get_post_count_for_schedules(array $ids) {
		return 0;
	}
}

// ---------------------------------------------------------------------------
// Helper: build a cache instance backed by in-memory wp_cache stubs
// ---------------------------------------------------------------------------

/**
 * Create a fresh AIPS_Cache instance using the WP Object Cache driver.
 *
 * The wp_cache_* stubs registered in bootstrap.php use a global
 * $wp_object_cache_storage array, so resetting that global between tests
 * gives us a clean cache for each test.
 *
 * @param string $base_group Base group prefix for the driver.
 * @return AIPS_Cache
 */
function make_wp_object_cache( $base_group = 'aips' ) {
	return new AIPS_Cache( new AIPS_Cache_Wp_Object_Cache_Driver( $base_group ) );
}

// ---------------------------------------------------------------------------
// AIPS_Caching_Template_Repository tests
// ---------------------------------------------------------------------------

class Test_Caching_Template_Repository extends WP_UnitTestCase {

	/** @var Spy_Template_Repository */
	private $spy;

	/** @var AIPS_Caching_Template_Repository */
	private $decorator;

	public function setUp(): void {
		parent::setUp();
		// Reset the in-memory wp_cache store.
		global $wp_object_cache_storage;
		$wp_object_cache_storage = array();

		$this->spy      = new Spy_Template_Repository();
		$this->decorator = new AIPS_Caching_Template_Repository(
			$this->spy,
			make_wp_object_cache( 'aips_templates' )
		);
	}

	public function tearDown(): void {
		global $wp_object_cache_storage;
		$wp_object_cache_storage = array();
		parent::tearDown();
	}

	// ------------------------------------------------------------------
	// get_all() caching
	// ------------------------------------------------------------------

	public function test_get_all_calls_inner_repo_once_on_cache_miss() {
		$this->spy->get_all_return = array( (object) array( 'id' => 1 ) );

		$this->decorator->get_all();
		$this->decorator->get_all(); // Should be served from cache.

		$this->assertEquals( 1, $this->spy->get_all_calls, 'Inner repo should be called only once for get_all().' );
	}

	public function test_get_all_active_only_uses_separate_cache_key() {
		$this->spy->get_all_return = array( (object) array( 'id' => 1 ) );

		$this->decorator->get_all( false );
		$this->decorator->get_all( true );

		$this->assertEquals( 2, $this->spy->get_all_calls, 'Each active_only variant should have its own cache entry.' );

		// Second calls for each variant should hit cache.
		$this->decorator->get_all( false );
		$this->decorator->get_all( true );
		$this->assertEquals( 2, $this->spy->get_all_calls, 'No additional DB calls after cache is warm.' );
	}

	public function test_get_all_returns_correct_data() {
		$rows = array( (object) array( 'id' => 5, 'name' => 'Test' ) );
		$this->spy->get_all_return = $rows;

		$result = $this->decorator->get_all();

		$this->assertEquals( $rows, $result );
	}

	// ------------------------------------------------------------------
	// get_by_id() caching
	// ------------------------------------------------------------------

	public function test_get_by_id_calls_inner_repo_once_on_cache_miss() {
		$this->spy->get_by_id_return = (object) array( 'id' => 7, 'name' => 'My Template' );

		$this->decorator->get_by_id( 7 );
		$this->decorator->get_by_id( 7 ); // Should be served from cache.

		$this->assertEquals( 1, $this->spy->get_by_id_calls, 'Inner repo should be called only once for get_by_id().' );
	}

	public function test_get_by_id_does_not_cache_null_result() {
		$this->spy->get_by_id_return = null;

		$this->decorator->get_by_id( 99 );
		$this->decorator->get_by_id( 99 );

		// Null = record not found; always re-query so we detect newly created records.
		$this->assertEquals( 2, $this->spy->get_by_id_calls, 'Null results must not be cached.' );
	}

	public function test_get_by_id_returns_correct_data() {
		$row = (object) array( 'id' => 3, 'name' => 'Alpha' );
		$this->spy->get_by_id_return = $row;

		$result = $this->decorator->get_by_id( 3 );

		$this->assertEquals( $row, $result );
	}

	// ------------------------------------------------------------------
	// Cache invalidation on write
	// ------------------------------------------------------------------

	public function test_create_invalidates_cache() {
		$this->spy->get_all_return = array( (object) array( 'id' => 1 ) );

		$this->decorator->get_all(); // Warm the cache.
		$this->assertEquals( 1, $this->spy->get_all_calls );

		$this->decorator->create( array( 'name' => 'New Template' ) );

		$this->decorator->get_all(); // Cache should be empty; inner repo called again.
		$this->assertEquals( 2, $this->spy->get_all_calls, 'Cache should be flushed after create().' );
	}

	public function test_update_invalidates_cache() {
		$this->spy->get_by_id_return = (object) array( 'id' => 2 );
		$this->decorator->get_by_id( 2 ); // Warm the cache.

		$this->decorator->update( 2, array( 'name' => 'Updated' ) );

		$this->decorator->get_by_id( 2 ); // Should re-query.
		$this->assertEquals( 2, $this->spy->get_by_id_calls, 'Cache should be flushed after update().' );
	}

	public function test_delete_invalidates_cache() {
		$this->spy->get_all_return = array( (object) array( 'id' => 4 ) );
		$this->decorator->get_all(); // Warm the cache.

		$this->decorator->delete( 4 );

		$this->decorator->get_all(); // Should re-query.
		$this->assertEquals( 2, $this->spy->get_all_calls, 'Cache should be flushed after delete().' );
	}

	public function test_set_active_invalidates_cache() {
		$this->spy->get_all_return = array( (object) array( 'id' => 5 ) );
		$this->decorator->get_all();

		$this->decorator->set_active( 5, false );

		$this->decorator->get_all();
		$this->assertEquals( 2, $this->spy->get_all_calls, 'Cache should be flushed after set_active().' );
	}

	// ------------------------------------------------------------------
	// Pass-through methods
	// ------------------------------------------------------------------

	public function test_search_is_not_cached() {
		$this->decorator->search( 'foo' );
		$this->decorator->search( 'foo' );

		$this->assertEquals( 2, $this->spy->search_calls, 'search() should always call the inner repo.' );
	}

	public function test_implements_interface() {
		$this->assertInstanceOf( AIPS_Template_Repository_Interface::class, $this->decorator );
	}
}

// ---------------------------------------------------------------------------
// AIPS_Caching_Schedule_Repository tests
// ---------------------------------------------------------------------------

class Test_Caching_Schedule_Repository extends WP_UnitTestCase {

	/** @var Spy_Schedule_Repository */
	private $spy;

	/** @var AIPS_Caching_Schedule_Repository */
	private $decorator;

	public function setUp(): void {
		parent::setUp();
		global $wp_object_cache_storage;
		$wp_object_cache_storage = array();

		$this->spy      = new Spy_Schedule_Repository();
		$this->decorator = new AIPS_Caching_Schedule_Repository(
			$this->spy,
			make_wp_object_cache( 'aips_schedules' )
		);
	}

	public function tearDown(): void {
		global $wp_object_cache_storage;
		$wp_object_cache_storage = array();
		parent::tearDown();
	}

	// ------------------------------------------------------------------
	// get_all() caching
	// ------------------------------------------------------------------

	public function test_get_all_calls_inner_repo_once_on_cache_miss() {
		$this->spy->get_all_return = array( (object) array( 'id' => 1 ) );

		$this->decorator->get_all();
		$this->decorator->get_all();

		$this->assertEquals( 1, $this->spy->get_all_calls );
	}

	public function test_get_all_returns_correct_data() {
		$rows = array( (object) array( 'id' => 10, 'template_name' => 'Daily' ) );
		$this->spy->get_all_return = $rows;

		$result = $this->decorator->get_all();

		$this->assertEquals( $rows, $result );
	}

	// ------------------------------------------------------------------
	// get_by_id() caching
	// ------------------------------------------------------------------

	public function test_get_by_id_calls_inner_repo_once_on_cache_miss() {
		$this->spy->get_by_id_return = (object) array( 'id' => 3 );

		$this->decorator->get_by_id( 3 );
		$this->decorator->get_by_id( 3 );

		$this->assertEquals( 1, $this->spy->get_by_id_calls );
	}

	public function test_get_by_id_does_not_cache_null_result() {
		$this->spy->get_by_id_return = null;

		$this->decorator->get_by_id( 50 );
		$this->decorator->get_by_id( 50 );

		$this->assertEquals( 2, $this->spy->get_by_id_calls, 'Null results must not be cached.' );
	}

	// ------------------------------------------------------------------
	// Cache invalidation on write
	// ------------------------------------------------------------------

	public function test_create_invalidates_cache() {
		$this->spy->get_all_return = array( (object) array( 'id' => 1 ) );
		$this->decorator->get_all();

		$this->decorator->create( array( 'template_id' => 1 ) );

		$this->decorator->get_all();
		$this->assertEquals( 2, $this->spy->get_all_calls, 'Cache should be flushed after create().' );
	}

	public function test_update_invalidates_cache() {
		$this->spy->get_by_id_return = (object) array( 'id' => 2 );
		$this->decorator->get_by_id( 2 );

		$this->decorator->update( 2, array( 'is_active' => 0 ) );

		$this->decorator->get_by_id( 2 );
		$this->assertEquals( 2, $this->spy->get_by_id_calls, 'Cache should be flushed after update().' );
	}

	public function test_delete_invalidates_cache() {
		$this->spy->get_all_return = array( (object) array( 'id' => 3 ) );
		$this->decorator->get_all();

		$this->decorator->delete( 3 );

		$this->decorator->get_all();
		$this->assertEquals( 2, $this->spy->get_all_calls, 'Cache should be flushed after delete().' );
	}

	public function test_update_last_run_invalidates_cache() {
		$this->spy->get_by_id_return = (object) array( 'id' => 5 );
		$this->decorator->get_by_id( 5 );

		$this->decorator->update_last_run( 5 );

		$this->decorator->get_by_id( 5 );
		$this->assertEquals( 2, $this->spy->get_by_id_calls, 'Cache should be flushed after update_last_run().' );
	}

	// ------------------------------------------------------------------
	// get_due_schedules passes through (not cached)
	// ------------------------------------------------------------------

	public function test_get_due_schedules_passes_through() {
		// The spy returns empty array; just verifying the call doesn't error.
		$result = $this->decorator->get_due_schedules();
		$this->assertIsArray( $result );
	}

	public function test_implements_interface() {
		$this->assertInstanceOf( AIPS_Schedule_Repository_Interface::class, $this->decorator );
	}
}

// ---------------------------------------------------------------------------
// Container binding tests for the decorators
// ---------------------------------------------------------------------------

class Test_Caching_Decorator_Container_Bindings extends WP_UnitTestCase {

	/** @var AIPS_Container */
	private $container;

	public function setUp(): void {
		parent::setUp();
		$this->container = AIPS_Container::get_instance();
		$this->container->clear();
		AIPS_Cache_Factory::reset();
		global $wp_object_cache_storage;
		$wp_object_cache_storage = array();
	}

	public function tearDown(): void {
		$this->container->clear();
		AIPS_Cache_Factory::reset();
		global $wp_object_cache_storage;
		$wp_object_cache_storage = array();
		parent::tearDown();
	}

	/** Helper – invoke register_container_bindings() via reflection. */
	private function register_bindings() {
		$plugin     = AI_Post_Scheduler::get_instance();
		$reflection = new ReflectionClass( $plugin );
		$method     = $reflection->getMethod( 'register_container_bindings' );
		$method->setAccessible( true );
		$method->invoke( $plugin );
	}

	// ------------------------------------------------------------------
	// Without persistent cache
	// ------------------------------------------------------------------

	public function test_template_interface_resolves_to_concrete_repo_without_persistent_cache() {
		// wp_using_ext_object_cache() returns false in the test bootstrap.
		$this->register_bindings();

		$resolved = $this->container->make( AIPS_Template_Repository_Interface::class );

		$this->assertInstanceOf( AIPS_Template_Repository::class, $resolved );
		$this->assertNotInstanceOf( AIPS_Caching_Template_Repository::class, $resolved );
	}

	public function test_schedule_interface_resolves_to_concrete_repo_without_persistent_cache() {
		$this->register_bindings();

		$resolved = $this->container->make( AIPS_Schedule_Repository_Interface::class );

		$this->assertInstanceOf( AIPS_Schedule_Repository::class, $resolved );
		$this->assertNotInstanceOf( AIPS_Caching_Schedule_Repository::class, $resolved );
	}

	// ------------------------------------------------------------------
	// With persistent cache
	// ------------------------------------------------------------------

	public function test_template_interface_resolves_to_caching_decorator_with_persistent_cache() {
		// Temporarily override the wp_using_ext_object_cache() return value.
		add_filter( 'aips_test_ext_object_cache', '__return_true' );
		$this->_override_ext_object_cache( true );

		$this->register_bindings();

		$resolved = $this->container->make( AIPS_Template_Repository_Interface::class );

		$this->assertInstanceOf( AIPS_Caching_Template_Repository::class, $resolved );

		$this->_override_ext_object_cache( false );
	}

	public function test_schedule_interface_resolves_to_caching_decorator_with_persistent_cache() {
		$this->_override_ext_object_cache( true );

		$this->register_bindings();

		$resolved = $this->container->make( AIPS_Schedule_Repository_Interface::class );

		$this->assertInstanceOf( AIPS_Caching_Schedule_Repository::class, $resolved );

		$this->_override_ext_object_cache( false );
	}

	public function test_concrete_repos_always_resolve_to_singletons() {
		$this->register_bindings();

		$a = $this->container->make( AIPS_Template_Repository::class );
		$b = $this->container->make( AIPS_Template_Repository::class );

		$this->assertSame( $a, $b );
	}

	public function test_template_repository_interface_is_registered() {
		$this->register_bindings();

		$this->assertTrue( $this->container->has( AIPS_Template_Repository_Interface::class ) );
	}

	// ------------------------------------------------------------------
	// Internal helper – override wp_using_ext_object_cache() return value
	// ------------------------------------------------------------------

	/**
	 * Overrides the global that the bootstrap's wp_using_ext_object_cache()
	 * stub checks.  In the fallback bootstrap the function is defined to
	 * always return false; we use a global flag here to make it controllable.
	 *
	 * @param bool $value Desired return value.
	 */
	private function _override_ext_object_cache( $value ) {
		$GLOBALS['_aips_test_ext_object_cache'] = $value;
	}
}
