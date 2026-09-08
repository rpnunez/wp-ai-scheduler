<?php
/**
 * Tests for AIPS_List_Table, AIPS_Authors_List_Table, and AIPS_Author_Topics_List_Table.
 *
 * @package AI_Post_Scheduler
 */

class Test_AIPS_List_Table extends WP_UnitTestCase {

	/**
	 * Test base list table initialization and empty state rendering.
	 */
	public function test_base_list_table_initialization() {
		$table = new AIPS_Authors_List_Table();
		$this->assertInstanceOf(WP_List_Table::class, $table);
		$this->assertInstanceOf(AIPS_List_Table::class, $table);

		ob_start();
		$table->no_items();
		$output = ob_get_clean();

		$this->assertStringContainsString('aips-list-table-empty', $output);
		$this->assertStringContainsString('No authors Found', $output);
	}

	/**
	 * Test Authors list table columns definition.
	 */
	public function test_authors_list_table_columns() {
		$table = new AIPS_Authors_List_Table();
		$columns = $table->get_columns();

		$this->assertIsArray($columns);
		$this->assertArrayHasKey('cb', $columns);
		$this->assertArrayHasKey('quality', $columns);
		$this->assertArrayHasKey('name', $columns);
		$this->assertArrayHasKey('status', $columns);
		$this->assertArrayHasKey('topics', $columns);
		$this->assertArrayHasKey('posts', $columns);
		$this->assertArrayHasKey('actions', $columns);
	}

	/**
	 * Test Author Topics list table columns and views.
	 */
	public function test_author_topics_list_table() {
		$table = new AIPS_Author_Topics_List_Table(array('author_id' => 1));
		$columns = $table->get_columns();

		$this->assertIsArray($columns);
		$this->assertArrayHasKey('cb', $columns);
		$this->assertArrayHasKey('topic', $columns);
		$this->assertArrayHasKey('structure', $columns);
		$this->assertArrayHasKey('status', $columns);
		$this->assertArrayHasKey('created_at', $columns);
		$this->assertArrayHasKey('actions', $columns);
	}
}
