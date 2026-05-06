<?php
/**
 * Test Bulk Schedule
 *
 * Covers both the low-level scheduler bulk-create and the Planner AJAX handler
 * that orchestrates it, focusing on the regression where each scheduled topic
 * was incorrectly offset by (index * interval) seconds instead of all sharing
 * the same user-specified next_run datetime.
 *
 * @package AI_Post_Scheduler
 */

// ---------------------------------------------------------------------------
// Testable subclass — injects a capturing mock scheduler
// ---------------------------------------------------------------------------

/**
 * Captures the $schedules array passed to save_schedule_bulk().
 */
class Test_AIPS_Mock_Scheduler {
	/** @var array|null Last schedules argument received. */
	public $last_schedules = null;

	/** @var int Number of schedules to report as saved. */
	public $save_return = 0;

	public function save_schedule_bulk( array $schedules ) {
		$this->last_schedules = $schedules;
		$this->save_return    = count($schedules);
		return $this->save_return;
	}

	/** Passthrough needed by ajax_bulk_schedule for interval look-ups. */
	public function get_intervals() {
		$calc = new AIPS_Interval_Calculator();
		return $calc->get_intervals();
	}
}

/**
 * Testable planner that exposes mock injection points.
 */
class Test_AIPS_Planner_BulkSchedule extends AIPS_Planner {
	/** @var Test_AIPS_Mock_Scheduler */
	public $mock_scheduler;

	protected function make_scheduler() {
		return $this->mock_scheduler;
	}
}

// ---------------------------------------------------------------------------
// Test class
// ---------------------------------------------------------------------------

class Test_Bulk_Schedule extends WP_UnitTestCase {

	/** @var Test_AIPS_Planner_BulkSchedule */
	private $planner;

	/** @var Test_AIPS_Mock_Scheduler */
	private $mock_scheduler;

	public function setUp(): void {
		parent::setUp();

		$this->mock_scheduler           = new Test_AIPS_Mock_Scheduler();
		$this->planner                  = new Test_AIPS_Planner_BulkSchedule();
		$this->planner->mock_scheduler  = $this->mock_scheduler;

		$_POST    = array();
		$_REQUEST = array();
	}

	public function tearDown(): void {
		$_POST    = array();
		$_REQUEST = array();

		parent::tearDown();
	}

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	private function set_valid_post( array $overrides = array() ) {
		$nonce             = wp_create_nonce('aips_ajax_nonce');
		$_POST['nonce']    = $nonce;
		$_REQUEST['nonce'] = $nonce;
		$_POST             = array_merge($_POST, $overrides);
	}

	private function set_admin_user() {
		global $current_user_id, $test_users;
		if (!isset($test_users)) {
			$test_users = array();
		}
		$current_user_id = 1;
		$test_users[1]   = 'administrator';
	}

	private function capture_json( $callable ) {
		ob_start();
		try {
			$callable();
		} catch ( WPAjaxDieContinueException $e ) {
			// Expected when wp_send_json_* is called.
		}
		$output  = ob_get_clean();
		$decoded = json_decode($output, true);
		$this->assertIsArray($decoded, 'Response must be valid JSON. Got: ' . $output);
		return $decoded;
	}

	// -------------------------------------------------------------------------
	// Low-level scheduler bulk-create (smoke test)
	// -------------------------------------------------------------------------

	/**
	 * Verify save_schedule_bulk reports the correct insert count.
	 */
	public function test_bulk_create_count() {
		$schedules = array(
			array(
				'template_id' => 1,
				'frequency'   => 'once',
				'next_run'    => '2030-01-01 10:00:00',
				'is_active'   => 1,
				'topic'       => 'Topic 1',
			),
			array(
				'template_id' => 1,
				'frequency'   => 'once',
				'next_run'    => '2030-01-01 10:00:00',
				'is_active'   => 1,
				'topic'       => 'Topic 2',
			),
		);

		$count = $this->mock_scheduler->save_schedule_bulk($schedules);
		$this->assertEquals(2, $count);
	}

	// -------------------------------------------------------------------------
	// Regression: all topics must share the same next_run
	// -------------------------------------------------------------------------

	/**
	 * Submitting multiple topics with a 'once' frequency must
	 * stagger the schedule entries using a default 'daily' interval
	 * so they don't all run simultaneously.
	 */
	public function test_ajax_bulk_schedule_once_staggers_daily_by_default() {
		$this->set_admin_user();

		$start_date = '2030-06-15 13:15:00';

		$this->set_valid_post(array(
			'topics'      => array('Topic A', 'Topic B', 'Topic C', 'Topic D', 'Topic E'),
			'template_id' => 1,
			'start_date'  => $start_date,
			'frequency'   => 'once',
		));

		$response = $this->capture_json(array($this->planner, 'ajax_bulk_schedule'));

		$this->assertTrue($response['success'], 'Expected success. Got: ' . wp_json_encode($response));
		$this->assertEquals(5, $response['data']['count']);

		$schedules = $this->mock_scheduler->last_schedules;
		$this->assertCount(5, $schedules, '5 schedule entries must be created.');

		$this->assertEquals('2030-06-15 13:15:00', $schedules[0]['next_run']);
		$this->assertEquals('2030-06-16 13:15:00', $schedules[1]['next_run']);
		$this->assertEquals('2030-06-17 13:15:00', $schedules[2]['next_run']);
		$this->assertEquals('2030-06-18 13:15:00', $schedules[3]['next_run']);
		$this->assertEquals('2030-06-19 13:15:00', $schedules[4]['next_run']);
	}

	/**
	 * Submitting multiple topics with a frequency must
	 * stagger the schedule entries using the interval calculator.
	 */
	public function test_ajax_bulk_schedule_staggers_next_run() {
		$this->set_admin_user();

		$start_date = '2030-06-15 13:15:00';

		$this->set_valid_post(array(
			'topics'      => array('Topic A', 'Topic B', 'Topic C'),
			'template_id' => 1,
			'start_date'  => $start_date,
			'frequency'   => 'daily',
		));

		$response = $this->capture_json(array($this->planner, 'ajax_bulk_schedule'));

		$this->assertTrue($response['success'], 'Expected success. Got: ' . wp_json_encode($response));
		$this->assertEquals(3, $response['data']['count']);

		$schedules = $this->mock_scheduler->last_schedules;
		$this->assertCount(3, $schedules, '3 schedule entries must be created.');

		$this->assertEquals('2030-06-15 13:15:00', $schedules[0]['next_run']);
		$this->assertEquals('2030-06-16 13:15:00', $schedules[1]['next_run']);
		$this->assertEquals('2030-06-17 13:15:00', $schedules[2]['next_run']);
	}

	/**
	 * A single topic must also receive exactly the start_date (boundary case).
	 */
	public function test_single_topic_receives_exact_start_date() {
		$this->set_admin_user();

		$start_date = '2030-04-04 13:15:00';

		$this->set_valid_post(array(
			'topics'      => array('Only Topic'),
			'template_id' => 1,
			'start_date'  => $start_date,
			'frequency'   => 'once',
		));

		$response = $this->capture_json(array($this->planner, 'ajax_bulk_schedule'));

		$this->assertTrue($response['success']);

		$schedules = $this->mock_scheduler->last_schedules;
		$this->assertCount(1, $schedules);
		$this->assertEquals($start_date, $schedules[0]['next_run']);
	}
}

