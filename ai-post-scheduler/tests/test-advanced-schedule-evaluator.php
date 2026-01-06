<?php

/**
 * Tests for AIPS_Advanced_Schedule_Evaluator.
 */
class AIPS_Advanced_Schedule_Evaluator_Test extends WP_UnitTestCase {
	/**
	 * @var AIPS_Advanced_Schedule_Evaluator
	 */
	private $evaluator;

	public function setUp(): void {
		parent::setUp();
		$this->evaluator = new AIPS_Advanced_Schedule_Evaluator();
	}

	public function test_sanitize_rules_returns_defaults_for_empty_payload() {
		$rules = $this->evaluator->sanitize_rules('');
		$this->assertEquals('all', $rules['mode']);
		$this->assertEquals(array(), $rules['conditions']);
	}

	public function test_matches_time_and_day_combination() {
		$rules = array(
			'mode' => 'all',
			'conditions' => array(
				array(
					'type' => 'time_between',
					'start' => '08:00',
					'end' => '10:00',
				),
				array(
					'type' => 'days_of_week',
					'days' => array('monday', 'wednesday'),
				),
			),
		);

		$monday_nine_am = strtotime('2025-01-06 09:00:00');
		$tuesday_nine_am = strtotime('2025-01-07 09:00:00');

		$this->assertTrue($this->evaluator->matches($rules, $monday_nine_am));
		$this->assertFalse($this->evaluator->matches($rules, $tuesday_nine_am));
	}

	public function test_exclude_month_day_condition_blocks_matches() {
		$rules = array(
			'mode' => 'all',
			'conditions' => array(
				array(
					'type' => 'exclude_month_days',
					'days' => array(15),
				),
			),
		);

		$allowed = strtotime('2025-01-14 12:00:00');
		$blocked = strtotime('2025-01-15 12:00:00');

		$this->assertTrue($this->evaluator->matches($rules, $allowed));
		$this->assertFalse($this->evaluator->matches($rules, $blocked));
	}

	public function test_calculate_next_run_finds_upcoming_window() {
		$rules = array(
			'mode' => 'all',
			'conditions' => array(
				array(
					'type' => 'days_of_week',
					'days' => array('monday'),
				),
				array(
					'type' => 'time_between',
					'start' => '08:00',
					'end' => '09:00',
				),
			),
		);

		$sunday_evening = strtotime('2025-01-05 21:00:00');
		$next_run = $this->evaluator->calculate_next_run($rules, date('Y-m-d H:i:s', $sunday_evening));

		$this->assertStringContainsString('08:00', $next_run);
		$this->assertEquals(strtolower(date('l', strtotime($next_run))), 'monday');
	}
}
