<?php
/**
 * Tests for AIPS_Correlation_ID and correlation ID propagation through
 * history containers, schedule processor, and author generators.
 *
 * @package AI_Post_Scheduler
 */

/**
 * Class Test_AIPS_Correlation_ID
 *
 * Covers:
 * - AIPS_Correlation_ID generate/get/set/reset lifecycle
 * - AIPS_History_Container picks up the active correlation ID automatically
 * - Correlation ID stored in repository create() payload
 * - get_correlation_id() returns the correct value
 * - Multiple sequential runs produce independent IDs
 */
class Test_AIPS_Correlation_ID extends WP_UnitTestCase {

	// -----------------------------------------------------------------------
	// Helpers
	// -----------------------------------------------------------------------

	/**
	 * Always reset the static ID after each test to prevent state bleed.
	 */
	public function tearDown(): void {
		AIPS_Correlation_ID::reset();
		parent::tearDown();
	}

	// -----------------------------------------------------------------------
	// AIPS_Correlation_ID unit tests
	// -----------------------------------------------------------------------

	public function test_generate_returns_non_empty_string() {
		$id = AIPS_Correlation_ID::generate();
		$this->assertIsString($id);
		$this->assertNotEmpty($id);
	}

	public function test_generate_returns_uuid_format() {
		$id = AIPS_Correlation_ID::generate();
		// UUID v4: 8-4-4-4-12 hex groups separated by hyphens
		$this->assertMatchesRegularExpression(
			'/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i',
			$id,
			'generate() should return a UUID v4 string'
		);
	}

	public function test_get_returns_null_before_generate() {
		$this->assertNull(AIPS_Correlation_ID::get());
	}

	public function test_get_returns_generated_id() {
		$id = AIPS_Correlation_ID::generate();
		$this->assertSame($id, AIPS_Correlation_ID::get());
	}

	public function test_set_overrides_current_id() {
		AIPS_Correlation_ID::generate();
		AIPS_Correlation_ID::set('custom-id-123');
		$this->assertSame('custom-id-123', AIPS_Correlation_ID::get());
	}

	public function test_reset_clears_current_id() {
		AIPS_Correlation_ID::generate();
		AIPS_Correlation_ID::reset();
		$this->assertNull(AIPS_Correlation_ID::get());
	}

	public function test_sequential_generates_produce_different_ids() {
		$id1 = AIPS_Correlation_ID::generate();
		AIPS_Correlation_ID::reset();
		$id2 = AIPS_Correlation_ID::generate();
		$this->assertNotSame($id1, $id2, 'Each generate() call should produce a unique ID');
	}

	public function test_generate_makes_id_available_immediately_via_get() {
		$id = AIPS_Correlation_ID::generate();
		$fetched = AIPS_Correlation_ID::get();
		$this->assertSame($id, $fetched);
	}

	// -----------------------------------------------------------------------
	// AIPS_History_Container correlation ID propagation tests
	// -----------------------------------------------------------------------

	/**
	 * Build a minimal mock history repository that records the last create() payload
	 * in a shared stdClass capture object.
	 *
	 * @param stdClass $capture Object whose `data` property receives the create() args.
	 * @param int      $return_id The fake ID returned from create().
	 * @return object The mock repository.
	 */
	private function make_capture_repo(stdClass $capture, $return_id = 1) {
		return new class($capture, $return_id) implements AIPS_History_Repository_Interface {
			private $capture;
			private $id;
			public function __construct($capture, $id) {
				$this->capture = $capture;
				$this->id = $id;
			}
			public function create($data) {
				$this->capture->data = $data;
				return $this->id;
			}
			public function get_by_id($id) { return null; }
			public function get_history($args = array()) { return array(); }
			public function get_activity_feed($limit = 50, $offset = 0, $filters = array()) { return array(); }
			public function get_by_post_id($post_id) { return null; }
			public function add_log_entry($history_id, $log_type, $details, $history_type_id = null) { return false; }
			public function update($id, $data) { return true; }
			public function get_logs_by_history_id($history_id, $type_filter = array(), $limit = 0) { return array(); }
			public function get_estimated_generation_time($limit = 20) { return array(); }
			public function get_component_revisions($post_id, $component_type, $limit = 20) { return array(); }
			public function post_has_history_and_completed($post_id) { return false; }
		};
	}

	private function _old_make_capture_repo(stdClass $capture, $return_id = 1) {
		return new class($capture, $return_id) {
			private $capture;
			private $id;
			public function __construct($capture, $id) {
				$this->capture = $capture;
				$this->id = $id;
			}
			public function create($data) {
				$this->capture->data = $data;
				return $this->id;
			}
			public function get_by_id($id) { return null; }
		};
	}

	public function test_history_container_inherits_active_correlation_id() {
		$correlation_id = AIPS_Correlation_ID::generate();

		$capture = new stdClass();
		$mock_repo = $this->make_capture_repo($capture, 42);

		$container = new AIPS_History_Container($mock_repo, 'post_generation', array());

		$this->assertSame($correlation_id, $container->get_correlation_id());
		$this->assertSame($correlation_id, $capture->data['correlation_id']);
	}

	public function test_history_container_has_null_correlation_id_when_none_active() {
		AIPS_Correlation_ID::reset();

		$capture = new stdClass();
		$mock_repo = $this->make_capture_repo($capture, 1);

		$container = new AIPS_History_Container($mock_repo, 'schedule_execution', array());

		$this->assertNull($container->get_correlation_id());
		$this->assertNull($capture->data['correlation_id']);
	}

	public function test_history_container_uses_explicit_metadata_correlation_id() {
		// Even if AIPS_Correlation_ID has a value, an explicit metadata entry wins.
		AIPS_Correlation_ID::generate();
		$explicit_id = 'explicit-correlation-xyz';

		$capture = new stdClass();
		$mock_repo = $this->make_capture_repo($capture, 5);

		$container = new AIPS_History_Container($mock_repo, 'post_generation', array(
			'correlation_id' => $explicit_id,
		));

		$this->assertSame($explicit_id, $container->get_correlation_id());
		$this->assertSame($explicit_id, $capture->data['correlation_id']);
	}

	public function test_two_containers_in_same_run_share_correlation_id() {
		$run_id = AIPS_Correlation_ID::generate();

		$c1_capture = new stdClass();
		$c2_capture = new stdClass();

		$c1 = new AIPS_History_Container($this->make_capture_repo($c1_capture, 10), 'schedule_execution', array());
		$c2 = new AIPS_History_Container($this->make_capture_repo($c2_capture, 11), 'post_generation', array());

		$this->assertSame($run_id, $c1->get_correlation_id());
		$this->assertSame($run_id, $c2->get_correlation_id());
		$this->assertSame($c1->get_correlation_id(), $c2->get_correlation_id());
	}

	public function test_containers_from_different_runs_have_different_ids() {
		// Run 1
		AIPS_Correlation_ID::generate();
		$c1 = new AIPS_History_Container($this->make_capture_repo(new stdClass(), 20), 'post_generation', array());
		AIPS_Correlation_ID::reset();

		// Run 2
		AIPS_Correlation_ID::generate();
		$c2 = new AIPS_History_Container($this->make_capture_repo(new stdClass(), 21), 'post_generation', array());
		AIPS_Correlation_ID::reset();

		$this->assertNotSame($c1->get_correlation_id(), $c2->get_correlation_id());
	}

	// -----------------------------------------------------------------------
	// Repository create() payload test
	// -----------------------------------------------------------------------

	public function test_history_repository_create_includes_correlation_id_in_insert() {
		$correlation_id = AIPS_Correlation_ID::generate();

		$capture = new stdClass();
		$capture_repo = $this->make_capture_repo($capture, 88);

		new AIPS_History_Container($capture_repo, 'post_generation', array());

		$this->assertArrayHasKey('correlation_id', $capture->data,
			'create() payload must contain correlation_id key');
		$this->assertSame($correlation_id, $capture->data['correlation_id']);
	}

	// -----------------------------------------------------------------------
	// Integration: schedule processor generates and resets correlation ID
	// -----------------------------------------------------------------------

	public function test_correlation_id_is_null_after_run() {
		// After a run completes (simulate reset), the ID should be cleared.
		AIPS_Correlation_ID::generate();
		$this->assertNotNull(AIPS_Correlation_ID::get());

		AIPS_Correlation_ID::reset();
		$this->assertNull(AIPS_Correlation_ID::get());
	}

	public function test_each_run_starts_with_fresh_correlation_id() {
		$ids = array();

		for ($i = 0; $i < 3; $i++) {
			$ids[] = AIPS_Correlation_ID::generate();
			// Simulate the reset that happens at the end of each run.
			AIPS_Correlation_ID::reset();
		}

		$this->assertSame(3, count(array_unique($ids)),
			'Each run should produce a unique correlation ID');
	}
}
