<?php
/**
 * Unit tests for AIPS_Telemetry and AIPS_Telemetry_Repository.
 *
 * Validates that events are buffered in-memory, that flush() inserts the
 * expected row (when enabled), and that the repository can read it back.
 *
 * @package AI_Post_Scheduler
 * @since   2.4.0
 */

class Test_AIPS_Telemetry extends WP_UnitTestCase {

	/**
	 * @var AIPS_Telemetry_Repository
	 */
	private $repo;

	/**
	 * Set up fresh state before each test.
	 *
	 * @return void
	 */
	public function setUp(): void {
		parent::setUp();

		// Ensure the telemetry table exists.
		AIPS_DB_Manager::install_tables();

		$this->repo = new AIPS_Telemetry_Repository();

		// Reset the singleton so each test starts with a clean buffer.
		$ref = new ReflectionProperty('AIPS_Telemetry', 'instance');
		$ref->setAccessible(true);
		$ref->setValue(null, null);

		// Enable telemetry for the duration of each test.
		update_option('aips_enable_telemetry', 1);
		AIPS_Config::get_instance()->flush_option_cache();
	}

	/**
	 * Restore options and reset state after each test.
	 *
	 * @return void
	 */
	public function tearDown(): void {
		delete_option('aips_enable_telemetry');
		AIPS_Config::get_instance()->flush_option_cache();

		// Reset the singleton.
		$ref = new ReflectionProperty('AIPS_Telemetry', 'instance');
		$ref->setAccessible(true);
		$ref->setValue(null, null);

		parent::tearDown();
	}

	// -----------------------------------------------------------------------
	// AIPS_Telemetry unit tests
	// -----------------------------------------------------------------------

	/**
	 * Verify that add_event() stores entries in the internal buffer.
	 *
	 * @return void
	 */
	public function test_add_event_populates_buffer() {
		$telemetry = AIPS_Telemetry::instance();
		$telemetry->add_event(array('type' => 'cache_miss', 'key' => 'my_key'));
		$telemetry->add_event(array('type' => 'class_init',  'class' => 'AIPS_Config'));

		$ref = new ReflectionProperty('AIPS_Telemetry', 'events');
		$ref->setAccessible(true);
		$events = $ref->getValue($telemetry);

		$this->assertCount(2, $events);
		$this->assertSame('cache_miss', $events[0]['type']);
		$this->assertSame('class_init',  $events[1]['type']);
	}

	/**
	 * Verify that add_event() does nothing when given an empty array.
	 *
	 * @return void
	 */
	public function test_add_event_ignores_empty_data() {
		$telemetry = AIPS_Telemetry::instance();
		$telemetry->add_event(array());

		$ref = new ReflectionProperty('AIPS_Telemetry', 'events');
		$ref->setAccessible(true);
		$this->assertCount(0, $ref->getValue($telemetry));
	}

	/**
	 * Verify that flush() inserts a row when telemetry is enabled.
	 *
	 * @return void
	 */
	public function test_flush_inserts_row_when_enabled() {
		$before = $this->repo->count();

		$telemetry = AIPS_Telemetry::instance();
		$telemetry->add_event(array('type' => 'test_event'));
		$telemetry->flush();

		$after = $this->repo->count();
		$this->assertSame($before + 1, $after, 'flush() must insert exactly one row.');
	}

	/**
	 * Verify that flush() does NOT insert a row when telemetry is disabled.
	 *
	 * @return void
	 */
	public function test_flush_skips_when_disabled() {
		update_option('aips_enable_telemetry', 0);
		AIPS_Config::get_instance()->flush_option_cache();

		$before = $this->repo->count();

		$telemetry = AIPS_Telemetry::instance();
		$telemetry->add_event(array('type' => 'test_event'));
		$telemetry->flush();

		$after = $this->repo->count();
		$this->assertSame($before, $after, 'flush() must skip insertion when telemetry is disabled.');
	}

	/**
	 * Verify the payload contains the expected top-level keys.
	 *
	 * @return void
	 */
	public function test_flush_payload_structure() {
		$telemetry = AIPS_Telemetry::instance();
		$telemetry->add_event(array('type' => 'cache_miss'));
		$telemetry->flush();

		global $wpdb;
		$table = $wpdb->prefix . 'aips_telemetry';
		$row   = $wpdb->get_row("SELECT * FROM {$table} ORDER BY id DESC LIMIT 1", ARRAY_A);

		$this->assertNotNull($row, 'A row should have been inserted.');
		$this->assertNotEmpty($row['payload']);

		$payload = json_decode($row['payload'], true);
		$this->assertArrayHasKey('events',         $payload);
		$this->assertArrayHasKey('num_queries',    $payload);
		$this->assertArrayHasKey('peak_memory_mb', $payload);
		$this->assertArrayHasKey('elapsed_ms',     $payload);
		$this->assertCount(1, $payload['events'], 'Events array must contain the single event that was added.');
	}

	// -----------------------------------------------------------------------
	// AIPS_Telemetry_Repository unit tests
	// -----------------------------------------------------------------------

	/**
	 * Repository::count() should return 0 initially (clean table).
	 *
	 * @return void
	 */
	public function test_repository_count_zero_initially() {
		global $wpdb;
		$wpdb->query("TRUNCATE TABLE {$wpdb->prefix}aips_telemetry");

		$this->assertSame(0, $this->repo->count());
	}

	/**
	 * Repository::insert() then count() should return 1.
	 *
	 * @return void
	 */
	public function test_repository_insert_and_count() {
		global $wpdb;
		$wpdb->query("TRUNCATE TABLE {$wpdb->prefix}aips_telemetry");

		$id = $this->repo->insert(array(
			'page'              => 'admin:dashboard',
			'user_id'           => 1,
			'request_method'    => 'GET',
			'num_queries'       => 5,
			'peak_memory_bytes' => 8388608,
			'elapsed_ms'        => 42.0,
			'payload'           => wp_json_encode(array('events' => array())),
			'inserted_at'       => current_time('mysql'),
		));

		$this->assertNotFalse($id, 'insert() must return an integer ID.');
		$this->assertSame(1, $this->repo->count());
	}

	/**
	 * Repository::get_page() should return the inserted row.
	 *
	 * @return void
	 */
	public function test_repository_get_page_returns_row() {
		global $wpdb;
		$wpdb->query("TRUNCATE TABLE {$wpdb->prefix}aips_telemetry");

		$this->repo->insert(array(
			'page'              => 'admin:dashboard',
			'user_id'           => 1,
			'request_method'    => 'GET',
			'num_queries'       => 5,
			'peak_memory_bytes' => 8388608,
			'elapsed_ms'        => 42.0,
			'payload'           => wp_json_encode(array()),
			'inserted_at'       => current_time('mysql'),
		));

		$rows = $this->repo->get_page(10, 0);
		$this->assertCount(1, $rows);
		$this->assertSame('admin:dashboard', $rows[0]['page']);
	}

	/**
	 * Repository::get_payload() returns decoded array for an existing row.
	 *
	 * @return void
	 */
	public function test_repository_get_payload_decodes_json() {
		$expected = array('events' => array(), 'num_queries' => 3);
		$id = $this->repo->insert(array(
			'page'              => 'ajax:aips_test',
			'user_id'           => 0,
			'request_method'    => 'POST',
			'num_queries'       => 3,
			'peak_memory_bytes' => 1048576,
			'elapsed_ms'        => 10.0,
			'payload'           => wp_json_encode($expected),
			'inserted_at'       => current_time('mysql'),
		));

		$payload = $this->repo->get_payload($id);
		$this->assertIsArray($payload);
		$this->assertSame(3, $payload['num_queries']);
	}

	/**
	 * Repository::get_payload() returns null for a non-existent ID.
	 *
	 * @return void
	 */
	public function test_repository_get_payload_null_for_missing_id() {
		$this->assertNull($this->repo->get_payload(999999));
	}

	/**
	 * Verify enable_telemetry default is false.
	 *
	 * @return void
	 */
	public function test_enable_telemetry_default_is_false() {
		delete_option('aips_enable_telemetry');
		AIPS_Config::get_instance()->flush_option_cache();

		$value = AIPS_Config::get_instance()->get_option('aips_enable_telemetry');
		$this->assertFalse((bool) $value, 'enable_telemetry must default to false.');
	}
}
