<?php
/**
 * Tests for AIPS_DB_Datetime_Migrator
 *
 * @package AI_Post_Scheduler
 */

class Test_AIPS_DB_Datetime_Migrator extends WP_UnitTestCase {

	public function test_get_datetime_column_map_structure() {
		$map = AIPS_DB_Datetime_Migrator::get_datetime_column_map();

		$this->assertIsArray($map);
		$this->assertArrayHasKey('aips_history', $map);
		$this->assertArrayHasKey('aips_templates', $map);
		$this->assertArrayHasKey('aips_schedule', $map);
		$this->assertArrayHasKey('aips_authors', $map);

		// History must have created_at and completed_at
		$cols = array_column($map['aips_history'], 0);
		$this->assertContains('created_at', $cols);
		$this->assertContains('completed_at', $cols);
	}

	public function test_convert_datetime_column_to_bigint_nonexistent_table() {
		$result = AIPS_DB_Datetime_Migrator::convert_datetime_column_to_bigint('nonexistent_table_xyz', 'some_col');
		$this->assertFalse($result);
	}

	public function test_convert_datetime_column_to_bigint_already_bigint() {
		global $wpdb;
		$table = $wpdb->prefix . 'aips_history';

		// created_at is already BIGINT in modern schema
		$result = AIPS_DB_Datetime_Migrator::convert_datetime_column_to_bigint($table, 'created_at');
		$this->assertFalse($result);
	}
}
