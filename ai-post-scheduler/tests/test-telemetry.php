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

		if (!defined('ABSPATH') || !file_exists(ABSPATH . 'wp-admin/includes/upgrade.php')) {
			$this->markTestSkipped('WordPress upgrade helpers are unavailable in limited PHPUnit mode.');
		}

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
		$telemetry->add_event('cache', array('type' => 'cache_miss', 'key' => 'my_key'));
		$telemetry->add_event('classes', array('type' => 'class_init',  'class' => 'AIPS_Config'));

		$ref = new ReflectionProperty('AIPS_Telemetry', 'events');
		$ref->setAccessible(true);
		$events = $ref->getValue($telemetry);

		$this->assertCount(2, $events);
		$this->assertSame('cache_miss', $events[0]['type']);
		$this->assertSame('class_init',  $events[1]['type']);
		$this->assertSame('cache', $events[0]['_bucket']);
		$this->assertSame('classes', $events[1]['_bucket']);
	}

	/**
	 * Verify the legacy add_event(array()) form remains supported.
	 *
	 * @return void
	 */
	public function test_add_event_legacy_signature_is_supported() {
		$telemetry = AIPS_Telemetry::instance();
		$telemetry->add_event(array('type' => 'cache_get', 'hit' => true));

		$ref = new ReflectionProperty('AIPS_Telemetry', 'events');
		$ref->setAccessible(true);
		$events = $ref->getValue($telemetry);

		$this->assertCount(1, $events);
		$this->assertSame('cache', $events[0]['_bucket']);
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
		$telemetry->add_event('cache', array('type' => 'cache_get', 'hit' => false));
		$telemetry->add_event('classes', array('type' => 'class_initialized', 'class' => 'AIPS_Config'));
		$telemetry->flush();

		global $wpdb;
		$table = $wpdb->prefix . 'aips_telemetry';
		$row   = $wpdb->get_row("SELECT * FROM {$table} ORDER BY id DESC LIMIT 1", ARRAY_A);

		$this->assertNotNull($row, 'A row should have been inserted.');
		$this->assertNotEmpty($row['payload']);

		$payload = json_decode($row['payload'], true);
		$this->assertArrayHasKey('events',         $payload);
		$this->assertArrayHasKey('event_summary',  $payload);
		$this->assertArrayHasKey('cache_summary',  $payload);
		$this->assertArrayHasKey('query_summary',  $payload);
		$this->assertArrayHasKey('num_queries',    $payload);
		$this->assertArrayHasKey('peak_memory_mb', $payload);
		$this->assertArrayHasKey('elapsed_ms',     $payload);
		$this->assertArrayHasKey('request_type',   $payload);
		$this->assertSame(2, $payload['event_summary']['total']);
		$this->assertSame(1, $payload['cache_summary']['misses']);
		$this->assertCount(2, $payload['events'], 'Events array must contain the two events that were added.');
		$this->assertSame($payload['request_type'], $row['type']);
		$this->assertStringContainsString('cache', $row['event_categories']);
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
			'type'              => 'admin',
			'page'              => 'admin:dashboard',
			'event_categories'  => 'cache,classes',
			'user_id'           => 1,
			'request_method'    => 'GET',
			'num_queries'       => 5,
			'total_events'      => 7,
			'cache_calls'       => 4,
			'cache_hits'        => 3,
			'cache_misses'      => 1,
			'slow_query_count'  => 1,
			'duplicate_query_count' => 2,
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
			'type'              => 'admin',
			'page'              => 'admin:dashboard',
			'event_categories'  => 'classes',
			'user_id'           => 1,
			'request_method'    => 'GET',
			'num_queries'       => 5,
			'total_events'      => 1,
			'cache_calls'       => 0,
			'cache_hits'        => 0,
			'cache_misses'      => 0,
			'slow_query_count'  => 0,
			'duplicate_query_count' => 0,
			'peak_memory_bytes' => 8388608,
			'elapsed_ms'        => 42.0,
			'payload'           => wp_json_encode(array()),
			'inserted_at'       => current_time('mysql'),
		));

		$rows = $this->repo->get_page(10, 0);
		$this->assertCount(1, $rows);
		$this->assertSame('admin', $rows[0]['type']);
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
			'type'              => 'ajax',
			'page'              => 'ajax:aips_test',
			'event_categories'  => 'query',
			'user_id'           => 0,
			'request_method'    => 'POST',
			'num_queries'       => 3,
			'total_events'      => 0,
			'cache_calls'       => 0,
			'cache_hits'        => 0,
			'cache_misses'      => 0,
			'slow_query_count'  => 0,
			'duplicate_query_count' => 0,
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
	 * Repository::get_row() returns the full stored row for an existing ID.
	 *
	 * @return void
	 */
	public function test_repository_get_row_returns_full_row() {
		$id = $this->repo->insert(array(
			'type'              => 'admin',
			'page'              => 'admin:aips-telemetry',
			'event_categories'  => 'cache,performance',
			'user_id'           => 7,
			'request_method'    => 'GET',
			'num_queries'       => 11,
			'total_events'      => 3,
			'cache_calls'       => 2,
			'cache_hits'        => 1,
			'cache_misses'      => 1,
			'slow_query_count'  => 1,
			'duplicate_query_count' => 2,
			'peak_memory_bytes' => 4194304,
			'elapsed_ms'        => 21.5,
			'payload'           => wp_json_encode(array('events' => array(array('type' => 'example')))),
			'inserted_at'       => current_time('mysql'),
		));

		$row = $this->repo->get_row($id);

		$this->assertIsArray($row);
		$this->assertSame('admin', $row['type']);
		$this->assertSame('admin:aips-telemetry', $row['page']);
		$this->assertSame('cache,performance', $row['event_categories']);
		$this->assertSame('2', (string) $row['duplicate_query_count']);
		$this->assertSame('GET', $row['request_method']);
		$this->assertArrayHasKey('payload', $row);
	}

	/**
	 * Verify filtered counts and pages honour the new filter fields.
	 *
	 * @return void
	 */
	public function test_repository_filters_by_type_category_method_and_issues() {
		global $wpdb;
		$wpdb->query("TRUNCATE TABLE {$wpdb->prefix}aips_telemetry");

		$inserted_at = current_time('mysql');

		$this->repo->insert(array(
			'type'              => 'admin',
			'page'              => 'admin:aips-telemetry',
			'event_categories'  => 'cache,performance',
			'user_id'           => 1,
			'request_method'    => 'GET',
			'num_queries'       => 8,
			'total_events'      => 4,
			'cache_calls'       => 3,
			'cache_hits'        => 1,
			'cache_misses'      => 2,
			'slow_query_count'  => 1,
			'duplicate_query_count' => 0,
			'peak_memory_bytes' => 1048576,
			'elapsed_ms'        => 20,
			'payload'           => wp_json_encode(array()),
			'inserted_at'       => $inserted_at,
		));

		$this->repo->insert(array(
			'type'              => 'ajax',
			'page'              => 'ajax:heartbeat',
			'event_categories'  => 'classes',
			'user_id'           => 0,
			'request_method'    => 'POST',
			'num_queries'       => 2,
			'total_events'      => 1,
			'cache_calls'       => 0,
			'cache_hits'        => 0,
			'cache_misses'      => 0,
			'slow_query_count'  => 0,
			'duplicate_query_count' => 0,
			'peak_memory_bytes' => 1048576,
			'elapsed_ms'        => 10,
			'payload'           => wp_json_encode(array()),
			'inserted_at'       => $inserted_at,
		));

		$today = date_i18n('Y-m-d', current_time('timestamp'));
		$filters = array(
			'type' => 'admin',
			'event_category' => 'cache',
			'request_method' => 'GET',
			'page_search' => 'telemetry',
			'issues_only' => true,
		);

		$this->assertSame(1, $this->repo->count_filtered($today, $today, $filters));

		$rows = $this->repo->get_filtered_page($today, $today, $filters, 25, 0);
		$this->assertCount(1, $rows);
		$this->assertSame('admin:aips-telemetry', $rows[0]['page']);
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

	/**
	 * Verify is_enabled() guards against re-entrant option lookups.
	 *
	 * This regression test simulates a pre_option callback that re-enters
	 * AIPS_Telemetry::is_enabled() while the outer call is in-flight.
	 *
	 * @return void
	 */
	public function test_is_enabled_reentrant_guard_prevents_recursion() {
		$calls = 0;

		$filter = function($pre_option, $option_name, $default) use (&$calls) {
			$calls++;

			// The nested call must short-circuit via the re-entrancy guard instead
			// of recursing through get_option() again.
			$this->assertFalse(AIPS_Telemetry::is_enabled());

			return 1;
		};

		add_filter('pre_option_aips_enable_telemetry', $filter, 10, 3);

		try {
			$this->assertTrue(AIPS_Telemetry::is_enabled());
			$this->assertSame(1, $calls, 'The pre_option filter should run exactly once.');
		} finally {
			remove_filter('pre_option_aips_enable_telemetry', $filter, 10);
		}
	}
}
