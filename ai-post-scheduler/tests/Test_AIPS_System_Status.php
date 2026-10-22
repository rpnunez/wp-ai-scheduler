<?php
/**
 * Tests for AIPS_System_Status (display-level helpers).
 *
 * @package AI_Post_Scheduler
 */

class Test_AIPS_System_Status extends WP_UnitTestCase {

	public function test_condense_database_checks_collapses_all_ok_tables_to_summary_row() {
		$status = new AIPS_System_Status();

		$checks = array();
		for ($i = 1; $i <= 27; $i++) {
			$checks['aips_table_' . $i] = array(
				'label'  => 'Table: aips_table_' . $i,
				'value'  => 'OK',
				'status' => 'ok',
			);
		}

		$condensed = $status->condense_database_checks($checks);

		$this->assertCount(1, $condensed);
		$this->assertArrayHasKey('tables_summary', $condensed);
		$this->assertSame('ok', $condensed['tables_summary']['status']);
		$this->assertStringContainsString('27', $condensed['tables_summary']['value']);
	}

	public function test_condense_database_checks_keeps_failing_rows_with_summary() {
		$status = new AIPS_System_Status();

		$checks = array(
			'aips_ok_table' => array(
				'label'  => 'Table: aips_ok_table',
				'value'  => 'OK',
				'status' => 'ok',
			),
			'aips_broken_table' => array(
				'label'  => 'Table: aips_broken_table',
				'value'  => 'Missing',
				'status' => 'error',
			),
		);

		$condensed = $status->condense_database_checks($checks);

		$this->assertCount(2, $condensed);
		$this->assertArrayHasKey('aips_broken_table', $condensed);
		$this->assertSame('error', $condensed['aips_broken_table']['status']);
		$this->assertArrayHasKey('tables_summary', $condensed);
		$this->assertSame('warning', $condensed['tables_summary']['status']);
		$this->assertStringContainsString('1 of 2', $condensed['tables_summary']['value']);
	}

	public function test_condense_database_checks_handles_empty_input() {
		$status = new AIPS_System_Status();

		$this->assertSame(array(), $status->condense_database_checks(array()));
	}
}
