<?php
/**
 * Tests for AIPS_List_Table, AIPS_Authors_List_Table, and AIPS_Author_Topics_List_Table.
 *
 * @package AI_Post_Scheduler
 */

class Test_AIPS_List_Table extends WP_UnitTestCase {

	/**
	 * Don't leak the tab selection into other tests, even when an assertion fails.
	 */
	public function tearDown(): void {
		unset($_GET['topic_status']);
		parent::tearDown();
	}

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
		$this->assertStringContainsString('No authors found', $output);
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
		$this->assertArrayNotHasKey('structure', $columns);
		$this->assertArrayNotHasKey('status', $columns, 'Status is implied by the default Pending tab.');
		$this->assertArrayHasKey('created_at', $columns);
		$this->assertArrayHasKey('actions', $columns);
	}

	/**
	 * Status column is only shown on the mixed "All" tab.
	 */
	public function test_author_topics_status_column_only_on_all_tab() {
		foreach (array('pending', 'approved', 'rejected', 'posts_generated') as $view) {
			$_GET['topic_status'] = $view;
			$table = new AIPS_Author_Topics_List_Table(array('author_id' => 1));
			$this->assertArrayNotHasKey('status', $table->get_columns(), $view);
			$this->assertArrayNotHasKey('structure', $table->get_columns(), $view);
		}

		$_GET['topic_status'] = 'all';
		$table = new AIPS_Author_Topics_List_Table(array('author_id' => 1));
		$this->assertArrayHasKey('status', $table->get_columns());
		$this->assertArrayNotHasKey('structure', $table->get_columns());
	}

	/**
	 * Screen Options must be able to offer Status regardless of the active tab.
	 */
	public function test_author_topics_all_columns_includes_status() {
		$_GET['topic_status'] = 'approved';
		$table = new AIPS_Author_Topics_List_Table(array('author_id' => 1));
		$this->assertArrayHasKey('status', $table->get_all_columns());
	}

	/**
	 * Hover row actions depend on the topic status.
	 */
	public function test_author_topics_row_actions_by_status() {
		$table = new AIPS_Author_Topics_List_Table(array('author_id' => 1));
		$method = new ReflectionMethod($table, 'column_topic');
		$method->setAccessible(true);

		$render = function ($status) use ($table, $method) {
			return $method->invoke($table, (object) array('id' => 5, 'topic' => 'T', 'status' => $status));
		};

		$pending = $render('pending');
		$this->assertStringNotContainsString('class="aips-reject-topic"', $pending);

		$rejected = $render('rejected');
		$this->assertStringNotContainsString('class="aips-reject-topic"', $rejected);
		$this->assertStringContainsString('class="aips-approve-topic"', $rejected);

		$approved = $render('approved');
		$this->assertStringContainsString('class="aips-reject-topic"', $approved);

		$this->assertStringNotContainsString('aips-generate-post-now', $pending);
		$this->assertStringNotContainsString('aips-generate-post-now', $rejected);
		$this->assertStringContainsString('aips-generate-post-now', $approved);
	}

	/**
	 * Test Author Topics list table bulk actions definition.
	 */
	public function test_author_topics_list_table_bulk_actions() {
		$_GET['topic_status'] = 'pending';
		$table = new AIPS_Author_Topics_List_Table(array('author_id' => 1));
		
		// Use reflection to call protected get_bulk_actions
		$reflection = new ReflectionClass($table);
		$method = $reflection->getMethod('get_bulk_actions');
		$method->setAccessible(true);
		$bulk_actions = $method->invoke($table);

		$this->assertIsArray($bulk_actions);
		$this->assertArrayHasKey('approve', $bulk_actions);
		$this->assertArrayHasKey('approve_feedback', $bulk_actions);
		$this->assertArrayHasKey('reject', $bulk_actions);
		$this->assertArrayHasKey('reject_feedback', $bulk_actions);
		$this->assertArrayHasKey('generate_posts', $bulk_actions);
		$this->assertArrayHasKey('delete', $bulk_actions);
	}
}
