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
	 * Test that review workflow tables exist.
	 */
	public function test_review_workflow_tables_exist() {
		global $wpdb;

		$items_table    = $wpdb->prefix . 'aips_post_review_items';
		$stage_table    = $wpdb->prefix . 'aips_post_review_stage_data';
		$comments_table = $wpdb->prefix . 'aips_post_review_comments';

		$this->assertEquals($items_table, $wpdb->get_var("SHOW TABLES LIKE '{$items_table}'"), 'Items table should exist');
		$this->assertEquals($stage_table, $wpdb->get_var("SHOW TABLES LIKE '{$stage_table}'"), 'Stage table should exist');
		$this->assertEquals($comments_table, $wpdb->get_var("SHOW TABLES LIKE '{$comments_table}'"), 'Comments table should exist');
	}

	/**
	 * Test that items table has unique index on post_id.
	 */
	public function test_review_workflow_items_unique_post_id() {
		global $wpdb;
		$table = $wpdb->prefix . 'aips_post_review_items';

		$indexes = $wpdb->get_results("SHOW INDEX FROM {$table} WHERE Key_name = 'post_id'");
		$this->assertNotEmpty($indexes, 'Index post_id should exist');

		$unique = false;
		foreach ($indexes as $idx) {
			if ((int) $idx->Non_unique === 0) {
				$unique = true;
				break;
			}
		}

		$this->assertTrue($unique, 'post_id index should be unique');
	}

	/**
	 * Test that stage table has unique composite index (review_item_id, stage_key).
	 */
	public function test_review_workflow_stage_unique_composite_index() {
		global $wpdb;
		$table = $wpdb->prefix . 'aips_post_review_stage_data';

		$indexes = $wpdb->get_results("SHOW INDEX FROM {$table} WHERE Key_name = 'review_item_stage'");
		$this->assertNotEmpty($indexes, 'Composite index review_item_stage should exist');

		$this->assertCount(2, $indexes, 'Composite index should have 2 columns');
		$cols = array();
		foreach ($indexes as $idx) {
			$cols[(int) $idx->Seq_in_index] = $idx->Column_name;
		}

		$this->assertEquals('review_item_id', $cols[1], 'First column should be review_item_id');
		$this->assertEquals('stage_key', $cols[2], 'Second column should be stage_key');
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
	 * Test that aips_schedule table has the new health & progress columns.
	 */
	public function test_schedule_table_has_health_and_progress_columns() {
		global $wpdb;
		$table_name = $wpdb->prefix . 'aips_schedule';

		$columns = $wpdb->get_results( "SHOW COLUMNS FROM {$table_name}" );
		$column_names = array_map( function ( $col ) {
			return $col->Field;
		}, $columns );

		$expected = array( 'schedule_type', 'circuit_state', 'run_state', 'batch_progress' );

		foreach ( $expected as $col ) {
			$this->assertContains( $col, $column_names, "Column '{$col}' should exist in aips_schedule table" );
		}
	}

	/**
	 * Test default values for new schedule columns.
	 */
	public function test_schedule_health_columns_default_values() {
		global $wpdb;
		$table_name = $wpdb->prefix . 'aips_schedule';

		// Need a template to satisfy the FK-like NOT NULL constraint.
		$wpdb->insert(
			$wpdb->prefix . 'aips_templates',
			array(
				'name'            => 'Schema Default Test Template',
				'prompt_template' => 'Write about defaults',
				'is_active'       => 1,
			)
		);
		$template_id = (int) $wpdb->insert_id;

		$wpdb->insert(
			$table_name,
			array(
				'template_id' => $template_id,
				'frequency'   => 'daily',
				'next_run'    => '2030-01-01 00:00:00',
				'is_active'   => 1,
			)
		);
		$schedule_id = (int) $wpdb->insert_id;

		$row = $wpdb->get_row( $wpdb->prepare(
			"SELECT schedule_type, circuit_state, run_state, batch_progress FROM {$table_name} WHERE id = %d",
			$schedule_id
		) );

		$this->assertNotNull( $row, 'Inserted schedule should be retrievable' );
		$this->assertEquals( 'post_generation', $row->schedule_type, 'schedule_type should default to post_generation' );
		$this->assertEquals( 'closed', $row->circuit_state, 'circuit_state should default to closed' );
		$this->assertNull( $row->run_state, 'run_state should default to NULL' );
		$this->assertNull( $row->batch_progress, 'batch_progress should default to NULL' );

		// Clean up
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$table_name} WHERE id = %d", $schedule_id ) );
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}aips_templates WHERE id = %d", $template_id ) );
	}

	/**
	 * Test that batch_progress and run_state can be stored and retrieved.
	 */
	public function test_schedule_batch_progress_and_run_state_persist() {
		global $wpdb;
		$table_name = $wpdb->prefix . 'aips_schedule';

		$wpdb->insert(
			$wpdb->prefix . 'aips_templates',
			array(
				'name'            => 'Batch Progress Test Template',
				'prompt_template' => 'Write about batch',
				'is_active'       => 1,
			)
		);
		$template_id = (int) $wpdb->insert_id;

		$wpdb->insert(
			$table_name,
			array(
				'template_id' => $template_id,
				'frequency'   => 'daily',
				'next_run'    => '2030-01-01 00:00:00',
				'is_active'   => 1,
			)
		);
		$schedule_id = (int) $wpdb->insert_id;

		$progress  = wp_json_encode( array( 'completed' => 3, 'total' => 10, 'last_index' => 2, 'post_ids' => array( 101, 102, 103 ) ) );
		$run_state = wp_json_encode( array(
			'status'        => 'partial',
			'error_code'    => 'ai_timeout',
			'error_message' => 'AI service timeout',
			'completed'     => 3,
			'total'         => 10,
			'timestamp'     => '2030-01-01T00:00:00+00:00',
		) );

		$wpdb->update(
			$table_name,
			array(
				'batch_progress' => $progress,
				'run_state'      => $run_state,
			),
			array( 'id' => $schedule_id )
		);

		$row = $wpdb->get_row( $wpdb->prepare(
			"SELECT batch_progress, run_state FROM {$table_name} WHERE id = %d",
			$schedule_id
		) );

		$this->assertNotNull( $row );

		$decoded_progress = json_decode( $row->batch_progress, true );
		$this->assertIsArray( $decoded_progress, 'batch_progress should be valid JSON' );
		$this->assertEquals( 3, $decoded_progress['completed'] );
		$this->assertEquals( 10, $decoded_progress['total'] );
		$this->assertEquals( 2, $decoded_progress['last_index'] );
		$this->assertArrayHasKey( 'post_ids', $decoded_progress, 'batch_progress should contain post_ids' );
		$this->assertEquals( array( 101, 102, 103 ), $decoded_progress['post_ids'], 'batch_progress.post_ids should round-trip correctly' );

		$decoded_state = json_decode( $row->run_state, true );
		$this->assertIsArray( $decoded_state, 'run_state should be valid JSON' );
		$this->assertEquals( 'partial', $decoded_state['status'], 'run_state.status should be stored' );
		$this->assertEquals( 'ai_timeout', $decoded_state['error_code'], 'run_state.error_code should be stored' );
		$this->assertEquals( 'AI service timeout', $decoded_state['error_message'], 'run_state.error_message should be stored' );
		$this->assertEquals( 3, $decoded_state['completed'], 'run_state.completed should be stored' );
		$this->assertEquals( 10, $decoded_state['total'], 'run_state.total should be stored' );

		// Clean up
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$table_name} WHERE id = %d", $schedule_id ) );
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}aips_templates WHERE id = %d", $template_id ) );
	}
}
