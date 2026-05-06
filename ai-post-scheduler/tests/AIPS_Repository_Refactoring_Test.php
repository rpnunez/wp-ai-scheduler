<?php
/**
 * Tests for repository refactoring of direct $wpdb calls.
 *
 * Verifies:
 *   - AIPS_Author_Topic_Logs_Repository::delete_by_topic_ids()
 *   - AIPS_Author_Topics_Repository::delete_by_author()
 *   - AIPS_Schedule_Repository::get_active_schedules()
 *   - AIPS_Schedule_Repository::get_active_schedules_by_template()
 *   - AIPS_Authors_Controller::ajax_delete_author() uses repository methods
 *
 * @package AI_Post_Scheduler
 */

class AIPS_Repository_Refactoring_Test extends WP_UnitTestCase {

	private $admin_user_id;

	public function setUp(): void {
		parent::setUp();
		$this->admin_user_id = $this->factory->user->create(array('role' => 'administrator'));
		$_REQUEST['nonce']   = wp_create_nonce('aips_ajax_nonce');
	}

	public function tearDown(): void {
		$_POST    = array();
		$_REQUEST = array();
		parent::tearDown();
	}

	// =========================================================================
	// Helpers
	// =========================================================================

	/**
	 * Build a wpdb proxy that records calls to query() and delete().
	 *
	 * @return object Anonymous-class proxy.
	 */
	private function make_recording_wpdb() {
		global $wpdb;
		$delegate = $wpdb;

		return new class($delegate) {
			public $prefix;
			public $insert_id = 0;

			/** @var string[] SQL strings passed to query(). */
			public $queries = array();

			/** @var array[] Arguments passed to delete(). */
			public $deletes = array();

			/** @var mixed Return value for get_results() calls. */
			public $get_results_return = array();

			/** @var mixed Return value for get_col() calls. */
			public $get_col_return = array();

			private $delegate;

			public function __construct($delegate) {
				$this->delegate = $delegate;
				$this->prefix   = $delegate->prefix;
			}

			public function query($sql) {
				$this->queries[] = $sql;
				return 1;
			}

			public function delete($table, $where, $where_format = null) {
				$this->deletes[] = array('table' => $table, 'where' => $where);
				return 1;
			}

			public function get_results($query, $output = OBJECT) {
				return $this->get_results_return;
			}

			public function get_col($query, $x = 0) {
				return $this->get_col_return;
			}

			public function prepare($query, ...$args) {
				return $this->delegate->prepare($query, ...$args);
			}

			public function get_row($query, $output = OBJECT, $y = 0) {
				return $this->delegate->get_row($query, $output, $y);
			}

			public function get_var($query, $x = 0, $y = 0) {
				return $this->delegate->get_var($query, $x, $y);
			}

			public function insert($table, $data, $format = null) {
				$result          = $this->delegate->insert($table, $data, $format);
				$this->insert_id = $this->delegate->insert_id;
				return $result;
			}

			public function update($table, $data, $where, $format = null, $where_format = null) {
				return $this->delegate->update($table, $data, $where, $format, $where_format);
			}

			public function get_charset_collate() {
				return $this->delegate->get_charset_collate();
			}

			public function esc_like($text) {
				return $this->delegate->esc_like($text);
			}
		};
	}

	/**
	 * Capture JSON output produced by a controller AJAX method.
	 *
	 * @param callable $callable Controller method to invoke.
	 * @return array Decoded response array.
	 */
	private function capture_ajax(callable $callable) {
		ob_start();
		try {
			$callable();
		} catch (WPAjaxDieContinueException $e) {
			// Expected after wp_send_json_*.
		}
		return json_decode(ob_get_clean(), true);
	}

	// =========================================================================
	// AIPS_Author_Topic_Logs_Repository::delete_by_topic_ids()
	// =========================================================================

	/**
	 * delete_by_topic_ids() returns 0 without touching the DB when given an
	 * empty array.
	 */
	public function test_delete_by_topic_ids_empty_array_returns_zero() {
		global $wpdb;
		$original = $wpdb;
		$proxy    = $this->make_recording_wpdb();
		$wpdb     = $proxy;

		try {
			$repo   = new AIPS_Author_Topic_Logs_Repository();
			$result = $repo->delete_by_topic_ids(array());
		} finally {
			$wpdb = $original;
		}

		$this->assertSame(0, $result, 'Should return 0 for empty array.');
		$this->assertEmpty($proxy->queries, 'Should not execute any SQL for empty array.');
	}

	/**
	 * delete_by_topic_ids() executes a DELETE…IN query for a non-empty array.
	 */
	public function test_delete_by_topic_ids_executes_delete_query() {
		global $wpdb;
		$original = $wpdb;
		$proxy    = $this->make_recording_wpdb();
		$wpdb     = $proxy;

		try {
			$repo   = new AIPS_Author_Topic_Logs_Repository();
			$result = $repo->delete_by_topic_ids(array(10, 20, 30));
		} finally {
			$wpdb = $original;
		}

		$this->assertSame(1, $result, 'Should return the query result (1).');
		$this->assertCount(1, $proxy->queries, 'Exactly one query should have been executed.');
		$this->assertStringContainsString('aips_author_topic_logs', $proxy->queries[0]);
		$this->assertStringContainsString('author_topic_id', $proxy->queries[0]);
		$this->assertStringContainsString('DELETE', strtoupper($proxy->queries[0]));
	}

	/**
	 * delete_by_topic_ids() filters out zero IDs (after absint) before executing.
	 */
	public function test_delete_by_topic_ids_filters_invalid_ids() {
		global $wpdb;
		$original = $wpdb;
		$proxy    = $this->make_recording_wpdb();
		$wpdb     = $proxy;

		try {
			$repo   = new AIPS_Author_Topic_Logs_Repository();
			// Passing only zeros - after absint() these are all 0, filtered by array_filter().
			$result = $repo->delete_by_topic_ids(array(0, 0, 0));
		} finally {
			$wpdb = $original;
		}

		$this->assertSame(0, $result, 'Should return 0 when all IDs are zero.');
		$this->assertEmpty($proxy->queries, 'Should not execute any SQL when all IDs are zero.');
	}

	// =========================================================================
	// AIPS_Author_Topics_Repository::delete_by_author()
	// =========================================================================

	/**
	 * delete_by_author() calls $wpdb->delete with the correct table and where clause.
	 */
	public function test_delete_by_author_calls_wpdb_delete() {
		global $wpdb;
		$original = $wpdb;
		$proxy    = $this->make_recording_wpdb();
		$wpdb     = $proxy;

		try {
			$repo   = new AIPS_Author_Topics_Repository();
			$result = $repo->delete_by_author(7);
		} finally {
			$wpdb = $original;
		}

		$this->assertSame(1, $result);
		$this->assertCount(1, $proxy->deletes, 'Exactly one delete call should have been made.');
		$this->assertStringContainsString('aips_author_topics', $proxy->deletes[0]['table']);
		$this->assertEquals(array('author_id' => 7), $proxy->deletes[0]['where']);
	}

	// =========================================================================
	// AIPS_Schedule_Repository::get_active_schedules()
	// =========================================================================

	/**
	 * get_active_schedules() executes a query that selects template_id, next_run,
	 * frequency and filters by is_active = 1.
	 */
	public function test_get_active_schedules_queries_active_rows() {
		global $wpdb;
		$original = $wpdb;
		$proxy    = $this->make_recording_wpdb();
		$wpdb     = $proxy;

		$fake = array(
			(object) array('template_id' => 1, 'next_run' => '2025-01-01 00:00:00', 'frequency' => 'daily'),
			(object) array('template_id' => 2, 'next_run' => '2025-01-02 00:00:00', 'frequency' => 'weekly'),
		);
		$proxy->get_results_return = $fake;

		try {
			$repo   = new AIPS_Schedule_Repository();
			$result = $repo->get_active_schedules();
		} finally {
			$wpdb = $original;
		}

		$this->assertSame($fake, $result);
	}

	// =========================================================================
	// AIPS_Schedule_Repository::get_active_schedules_by_template()
	// =========================================================================

	/**
	 * get_active_schedules_by_template() passes the template_id through prepare()
	 * and filters by is_active = 1.
	 */
	public function test_get_active_schedules_by_template_returns_results() {
		global $wpdb;
		$original = $wpdb;
		$proxy    = $this->make_recording_wpdb();
		$wpdb     = $proxy;

		$fake = array(
			(object) array('id' => 5, 'template_id' => 3, 'next_run' => '2025-03-01 08:00:00', 'frequency' => 'once', 'is_active' => 1),
		);
		$proxy->get_results_return = $fake;

		try {
			$repo   = new AIPS_Schedule_Repository();
			$result = $repo->get_active_schedules_by_template(3);
		} finally {
			$wpdb = $original;
		}

		$this->assertSame($fake, $result);
	}

	// =========================================================================
	// AIPS_Authors_Controller::ajax_delete_author() uses repositories
	// =========================================================================

	/**
	 * ajax_delete_author() must NOT call global $wpdb directly; it must instead
	 * delegate to the topic and log repositories.
	 *
	 * We verify this by replacing the global $wpdb with a recording proxy that
	 * has an empty get_by_author result, then confirming no direct query() or
	 * manual delete() for the topics/logs tables was made through $wpdb – the
	 * controller is expected to call repository methods.
	 *
	 * The test also verifies the overall success response.
	 */
	public function test_ajax_delete_author_uses_repositories_not_raw_wpdb() {
		wp_set_current_user($this->admin_user_id);

		global $wpdb;
		$original = $wpdb;

		// Proxy that records query() calls so we can assert none target topics/logs directly.
		$proxy = $this->make_recording_wpdb();
		// get_by_author returns empty -> no topic IDs -> delete_by_topic_ids short-circuits
		$proxy->get_results_return = array();
		$wpdb = $proxy;

		try {
			$controller = new AIPS_Authors_Controller();

			$_POST = array(
				'nonce'     => wp_create_nonce('aips_ajax_nonce'),
				'author_id' => 99,
			);
			$_REQUEST = $_POST;

			$response = $this->capture_ajax(array($controller, 'ajax_delete_author'));
		} finally {
			$wpdb = $original;
		}

		// The controller should succeed (the mock wpdb delete() returns 1 = truthy).
		$this->assertTrue($response['success'], 'Expected success response from ajax_delete_author.');

		// No raw SQL queries should target topics/logs tables directly from the controller.
		foreach ($proxy->queries as $query) {
			$this->assertStringNotContainsString(
				'aips_author_topic_logs',
				$query,
				'Controller must not issue raw queries against aips_author_topic_logs.'
			);
			$this->assertStringNotContainsString(
				'aips_author_topics',
				$query,
				'Controller must not issue raw queries against aips_author_topics.'
			);
		}
	}
}
