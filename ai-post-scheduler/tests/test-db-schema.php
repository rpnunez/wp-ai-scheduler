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
		AIPS_DB_Manager::install_tables();
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
		AIPS_DB_Manager::install_tables();
		
		// Verify the composite index still exists after dbDelta
		$indexes = $wpdb->get_results("SHOW INDEX FROM {$table_name} WHERE Key_name = 'is_active_next_run'");
		
		$this->assertNotEmpty($indexes, 'Composite index should exist after dbDelta');
		$this->assertCount(2, $indexes, 'Composite index should have 2 columns');
	}

	/**
	 * Test that aips_templates table has description column.
	 */
	public function test_templates_table_has_description_column() {
		global $wpdb;
		$table_name = $wpdb->prefix . 'aips_templates';
		
		// Get columns
		$columns = $wpdb->get_results("SHOW COLUMNS FROM {$table_name}");
		$column_names = array_map(function($col) {
			return $col->Field;
		}, $columns);
		
		// Verify description column exists
		$this->assertContains(
			'description',
			$column_names,
			"Column 'description' should exist in aips_templates table"
		);
		
		// Get specific column details
		$description_column = null;
		foreach ($columns as $col) {
			if ($col->Field === 'description') {
				$description_column = $col;
				break;
			}
		}
		
		// Verify column properties
		$this->assertNotNull($description_column, 'Description column should be found');
		$this->assertEquals('YES', $description_column->Null, 'Description column should be nullable');
		$this->assertStringContainsString('text', strtolower($description_column->Type), 'Description column should be TEXT type');
	}

	/**
	 * Test that templates can be saved with description field.
	 */
	public function test_templates_can_save_description() {
		global $wpdb;
		$table_name = $wpdb->prefix . 'aips_templates';
		
		// Insert a template with description
		$result = $wpdb->insert($table_name, array(
			'name' => 'Test Template',
			'description' => 'This is a test template description',
			'prompt_template' => 'Write a blog post about testing',
			'post_status' => 'draft',
			'is_active' => 1,
		));
		
		$this->assertNotFalse($result, 'Template should be inserted successfully');
		
		// Retrieve the template
		$template = $wpdb->get_row($wpdb->prepare(
			"SELECT * FROM {$table_name} WHERE name = %s",
			'Test Template'
		));
		
		$this->assertNotNull($template, 'Template should be retrieved');
		$this->assertEquals('This is a test template description', $template->description, 'Description should match');
		
		// Clean up
		$wpdb->query($wpdb->prepare("DELETE FROM {$table_name} WHERE id = %d", $template->id));
	}

	/**
	 * Test that templates can be saved without description (NULL).
	 */
	public function test_templates_can_save_without_description() {
		global $wpdb;
		$table_name = $wpdb->prefix . 'aips_templates';
		
		// Insert a template without description
		$result = $wpdb->insert($table_name, array(
			'name' => 'Test Template No Description',
			'prompt_template' => 'Write a blog post about testing',
			'post_status' => 'draft',
			'is_active' => 1,
		));
		
		$this->assertNotFalse($result, 'Template should be inserted successfully without description');
		
		// Retrieve the template
		$template = $wpdb->get_row($wpdb->prepare(
			"SELECT * FROM {$table_name} WHERE name = %s",
			'Test Template No Description'
		));
		
		$this->assertNotNull($template, 'Template should be retrieved');
		$this->assertNull($template->description, 'Description should be NULL when not provided');
		
		// Clean up
		$wpdb->query($wpdb->prepare("DELETE FROM {$table_name} WHERE id = %d", $template->id));
	}

	/**
	 * Test that aips_activity table exists and has correct structure.
	 */
	public function test_activity_table_exists() {
		global $wpdb;
		$table_name = $wpdb->prefix . 'aips_activity';
		
		// Check table exists
		$table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$table_name}'");
		$this->assertEquals($table_name, $table_exists, 'aips_activity table should exist');
		
		// Get columns
		$columns = $wpdb->get_results("SHOW COLUMNS FROM {$table_name}");
		$column_names = array_map(function($col) {
			return $col->Field;
		}, $columns);
		
		// Expected columns
		$expected_columns = array(
			'id',
			'event_type',
			'event_status',
			'schedule_id',
			'post_id',
			'template_id',
			'message',
			'metadata',
			'created_at',
		);
		
		foreach ($expected_columns as $expected_column) {
			$this->assertContains(
				$expected_column,
				$column_names,
				"Column '{$expected_column}' should exist in aips_activity table"
			);
		}
	}

	/**
	 * Test that aips_activity table has expected indexes.
	 */
	public function test_activity_table_has_indexes() {
		global $wpdb;
		$table_name = $wpdb->prefix . 'aips_activity';
		
		// Get all indexes for the table
		$indexes = $wpdb->get_results("SHOW INDEX FROM {$table_name}");
		
		// Extract unique index names
		$index_names = array_unique(array_map(function($index) {
			return $index->Key_name;
		}, $indexes));
		
		// Expected indexes
		$expected_indexes = array(
			'PRIMARY',
			'event_type',
			'event_status',
			'schedule_id',
			'post_id',
			'template_id',
			'created_at',
		);
		
		foreach ($expected_indexes as $expected_index) {
			$this->assertContains(
				$expected_index,
				$index_names,
				"Index '{$expected_index}' should exist on aips_activity table"
			);
		}
	}
}
