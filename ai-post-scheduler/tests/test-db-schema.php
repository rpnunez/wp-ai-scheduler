<?php
/**
 * Tests for Database Schema
 *
 * @package AI_Post_Scheduler
 */

class Test_AIPS_DB_Schema extends WP_UnitTestCase {
	
	public function setUp(): void {
		parent::setUp();
		
		// Install tables to ensure they exist
		\AIPS\Helpers\DBHelper::install_tables();
	}
	
	/**
	 * Test that aips_schedule table has the composite index is_active_next_run.
	 */
	public function test_schedule_table_has_composite_index() {
		global $wpdb;
		$table_name = $wpdb->prefix . 'aips_schedule';
		
		// Get indexes for the table
		$indexes = $wpdb->get_results("SHOW INDEX FROM {$table_name}");
		
		$this->assertNotEmpty($indexes, 'Table should have indexes');
		
		// Look for the composite index
		$composite_index_found = false;
		$index_columns = array();
		
		foreach ($indexes as $index) {
			if ($index->Key_name === 'is_active_next_run') {
				$composite_index_found = true;
				$index_columns[$index->Seq_in_index] = $index->Column_name;
			}
		}
		
		$this->assertTrue($composite_index_found, 'Composite index is_active_next_run should exist');
		
		// Verify the index contains the correct columns in the correct order
		$this->assertArrayHasKey(1, $index_columns, 'First column should exist in index');
		$this->assertArrayHasKey(2, $index_columns, 'Second column should exist in index');
		$this->assertEquals('is_active', $index_columns[1], 'First column should be is_active');
		$this->assertEquals('next_run', $index_columns[2], 'Second column should be next_run');
	}
	
	/**
	 * Test that all expected indexes exist on aips_schedule table.
	 */
	public function test_schedule_table_has_all_indexes() {
		global $wpdb;
		$table_name = $wpdb->prefix . 'aips_schedule';
		
		// Get all indexes for the table
		$indexes = $wpdb->get_results("SHOW INDEX FROM {$table_name}");
		
		// Extract unique index names
		$index_names = array_unique(array_map(function($index) {
			return $index->Key_name;
		}, $indexes));
		
		// Expected indexes
		$expected_indexes = array(
			'PRIMARY',
			'template_id',
			'article_structure_id',
			'next_run',
			'is_active_next_run',
		);
		
		foreach ($expected_indexes as $expected_index) {
			$this->assertContains(
				$expected_index,
				$index_names,
				"Index '{$expected_index}' should exist on aips_schedule table"
			);
		}
	}
	
	/**
	 * Test that the aips_schedule table structure is correct.
	 */
	public function test_schedule_table_structure() {
		global $wpdb;
		$table_name = $wpdb->prefix . 'aips_schedule';
		
		// Check table exists
		$table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$table_name}'");
		$this->assertEquals($table_name, $table_exists, 'aips_schedule table should exist');
		
		// Get columns
		$columns = $wpdb->get_results("SHOW COLUMNS FROM {$table_name}");
		$column_names = array_map(function($col) {
			return $col->Field;
		}, $columns);
		
		// Expected columns
		$expected_columns = array(
			'id',
			'template_id',
			'article_structure_id',
			'rotation_pattern',
			'frequency',
			'topic',
			'next_run',
			'last_run',
			'is_active',
			'created_at',
		);
		
		foreach ($expected_columns as $expected_column) {
			$this->assertContains(
				$expected_column,
				$column_names,
				"Column '{$expected_column}' should exist in aips_schedule table"
			);
		}
	}
	
	/**
	 * Test that the composite index improves query performance (conceptual).
	 * This test verifies the index can be used in queries.
	 */
	public function test_composite_index_query_usage() {
		global $wpdb;
		$table_name = $wpdb->prefix . 'aips_schedule';
		
		// Insert test data
		$wpdb->insert($table_name, array(
			'template_id' => 1,
			'frequency' => 'daily',
			'next_run' => current_time('mysql'),
			'is_active' => 1,
		));
		
		$wpdb->insert($table_name, array(
			'template_id' => 2,
			'frequency' => 'weekly',
			'next_run' => current_time('mysql'),
			'is_active' => 0,
		));
		
		// Query that should use the composite index
		$query = $wpdb->prepare(
			"SELECT * FROM {$table_name} WHERE is_active = %d AND next_run <= %s",
			1,
			current_time('mysql')
		);
		
		$results = $wpdb->get_results($query);
		
		// Verify we can query using the indexed columns
		$this->assertIsArray($results);
		$this->assertCount(1, $results);
		$this->assertEquals(1, $results[0]->is_active);
		
		// Clean up
		$wpdb->query("DELETE FROM {$table_name}");
	}
	
	/**
	 * Test dbDelta can update existing tables with new index.
	 */
	public function test_dbdelta_adds_new_index() {
		global $wpdb;
		$table_name = $wpdb->prefix . 'aips_schedule';
		
		// Re-run install_tables (this uses dbDelta)
		\AIPS\Helpers\DBHelper::install_tables();
		
		// Verify the composite index still exists after dbDelta
		$indexes = $wpdb->get_results("SHOW INDEX FROM {$table_name} WHERE Key_name = 'is_active_next_run'");
		
		$this->assertNotEmpty($indexes, 'Composite index should exist after dbDelta');
		$this->assertCount(2, $indexes, 'Composite index should have 2 columns');
	}
}
