<?php
/**
 * Tests for the per-schedule circuit breaker in AIPS_Schedule_Processor.
 *
 * Drives the automated (non-manual) path via AIPS_Scheduler::process() with real
 * DB rows and only the generator mocked — mirroring Test_AIPS_Scheduler_Resilience —
 * so the enforcement (skip while open) and trip (open after N consecutive failures)
 * behaviours are exercised end-to-end.
 *
 * @package AI_Post_Scheduler
 */

class Test_AIPS_Schedule_Processor_Circuit extends WP_UnitTestCase {

	/** @var AIPS_Scheduler */
	private $scheduler;

	/** @var AIPS_Template_Repository */
	private $template_repo;

	/** @var AIPS_Schedule_Repository */
	private $schedule_repo;

	/** @var int */
	private $template_id;

	public function setUp(): void {
		parent::setUp();
		$this->scheduler     = new AIPS_Scheduler();
		$this->template_repo = new AIPS_Template_Repository();
		$this->schedule_repo = new AIPS_Schedule_Repository();

		$this->template_id = (int) $this->template_repo->create(array(
			'name'            => 'Circuit Processor Template',
			'prompt_template' => 'Write about {{topic}}',
			'post_status'     => 'publish',
			'post_category'   => 1,
			'is_active'       => 1,
			'post_quantity'   => 1,
		));
	}

	/**
	 * Read a schedule row directly (bypassing the repository read cache).
	 *
	 * @param int $id
	 * @return object|null
	 */
	private function raw_schedule( $id ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}aips_schedule WHERE id = %d", $id ) );
	}

	/**
	 * An open circuit still within its cooldown window must skip generation entirely.
	 */
	public function test_open_circuit_within_cooldown_skips_generation() {
		$schedule_id = (int) $this->schedule_repo->create(array(
			'template_id'   => $this->template_id,
			'frequency'     => 'daily',
			'next_run'      => time() - HOUR_IN_SECONDS, // overdue -> due
			'is_active'     => 1,
			'topic'         => 'Circuit Topic',
			'circuit_state' => 'open',
			// Opened just now: well within the default 1h cooldown.
			'run_state'     => wp_json_encode(array(
				'consecutive_failures' => 5,
				'circuit_opened_at'    => time(),
			)),
		));

		// The generator must NEVER be invoked while the circuit is open + cooling down.
		$mock_generator = $this->getMockBuilder('AIPS_Generator')
			->disableOriginalConstructor()
			->onlyMethods(array('generate_post'))
			->getMock();
		$mock_generator->expects($this->never())->method('generate_post');

		$this->scheduler->set_generator($mock_generator);
		$this->scheduler->process();

		// Circuit remains open (skip does not alter it).
		$row = $this->raw_schedule($schedule_id);
		$this->assertNotNull($row);
		$this->assertEquals('open', $row->circuit_state);
	}

	/**
	 * A pure-failure run that reaches the consecutive-failure threshold trips the
	 * circuit to 'open' and records the failure count in run_state.
	 */
	public function test_pure_failure_at_threshold_trips_circuit_open() {
		$threshold = AIPS_Schedule_Processor::CIRCUIT_FAILURE_THRESHOLD;

		$schedule_id = (int) $this->schedule_repo->create(array(
			'template_id'   => $this->template_id,
			'frequency'     => 'daily',
			'next_run'      => time() - HOUR_IN_SECONDS,
			'is_active'     => 1,
			'topic'         => 'Circuit Topic',
			'circuit_state' => 'closed',
			// One short of the threshold, so this run's failure trips it.
			'run_state'     => wp_json_encode(array(
				'consecutive_failures' => $threshold - 1,
			)),
		));

		// Generator returns a WP_Error (a pure failure — zero posts generated).
		$mock_generator = $this->getMockBuilder('AIPS_Generator')
			->disableOriginalConstructor()
			->onlyMethods(array('generate_post'))
			->getMock();
		$mock_generator->method('generate_post')
			->willReturn(new WP_Error('gen_failed', 'Simulated generation failure'));

		$this->scheduler->set_generator($mock_generator);
		$this->scheduler->process();

		$row = $this->raw_schedule($schedule_id);
		$this->assertNotNull($row);
		$this->assertEquals('open', $row->circuit_state, 'Circuit should trip open at the failure threshold.');

		$run_state = json_decode($row->run_state, true);
		$this->assertIsArray($run_state);
		$this->assertEquals($threshold, (int) $run_state['consecutive_failures']);
		$this->assertGreaterThan(0, (int) $run_state['circuit_opened_at'], 'circuit_opened_at should be stamped on trip.');
	}

	/**
	 * A single pre-threshold failure must NOT trip the circuit; it just increments
	 * the consecutive-failure counter.
	 */
	public function test_single_failure_below_threshold_does_not_trip() {
		$schedule_id = (int) $this->schedule_repo->create(array(
			'template_id'   => $this->template_id,
			'frequency'     => 'daily',
			'next_run'      => time() - HOUR_IN_SECONDS,
			'is_active'     => 1,
			'topic'         => 'Circuit Topic',
			'circuit_state' => 'closed',
			'run_state'     => null,
		));

		$mock_generator = $this->getMockBuilder('AIPS_Generator')
			->disableOriginalConstructor()
			->onlyMethods(array('generate_post'))
			->getMock();
		$mock_generator->method('generate_post')
			->willReturn(new WP_Error('gen_failed', 'Simulated generation failure'));

		$this->scheduler->set_generator($mock_generator);
		$this->scheduler->process();

		$row = $this->raw_schedule($schedule_id);
		$this->assertNotNull($row);
		$this->assertEquals('closed', $row->circuit_state, 'A single failure must not trip the circuit.');

		$run_state = json_decode($row->run_state, true);
		$this->assertIsArray($run_state);
		$this->assertEquals(1, (int) $run_state['consecutive_failures']);
	}

	public function tearDown(): void {
		global $wpdb;
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}aips_schedule WHERE template_id = %d", $this->template_id ) );
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}aips_templates WHERE id = %d", $this->template_id ) );
		parent::tearDown();
	}
}
