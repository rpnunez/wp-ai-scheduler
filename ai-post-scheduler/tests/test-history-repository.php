<?php
/**
 * Tests for AIPS_History_Repository
 *
 * @package AI_Post_Scheduler
 */

class AIPS_History_Repository_Test extends WP_UnitTestCase {

	private $repository;
	private $template_repository;
	private $test_template_id;
	private $test_history_ids = array();

	public function setUp(): void {
		parent::setUp();
		$this->repository = new AIPS_History_Repository();
		$this->template_repository = new AIPS_Template_Repository();
		
		// Create test template
		$template_data = array(
			'name' => 'Test Template',
			'prompt_template' => 'Test prompt',
			'post_type' => 'post',
			'post_status' => 'draft',
			'is_active' => 1,
		);
		$this->test_template_id = $this->template_repository->save($template_data);
		
		// Create test history entries
		$this->create_test_history_entries();
	}

	private function create_test_history_entries() {
		global $wpdb;
		$table = $wpdb->prefix . 'aips_history';
		
		// Create 3 test entries
		for ($i = 1; $i <= 3; $i++) {
			$wpdb->insert(
				$table,
				array(
					'template_id' => $this->test_template_id,
					'post_id' => null,
					'status' => 'completed',
					'generated_title' => 'Test Title ' . $i,
					'generated_content' => 'Test content ' . $i . ' with more details',
					'prompt' => 'Test prompt ' . $i . ' with full context',
					'error_message' => null,
					'created_at' => current_time('mysql'),
				),
				array('%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s')
			);
			$this->test_history_ids[] = $wpdb->insert_id;
		}
	}

	public function tearDown(): void {
		global $wpdb;
		$history_table = $wpdb->prefix . 'aips_history';
		$template_table = $wpdb->prefix . 'aips_templates';
		
		// Clean up test data
		foreach ($this->test_history_ids as $id) {
			$wpdb->delete($history_table, array('id' => $id), array('%d'));
		}
		
		$wpdb->delete($template_table, array('id' => $this->test_template_id), array('%d'));
		
		parent::tearDown();
	}

	/**
	 * Test that get_history returns all fields when fields='all'
	 */
	public function test_get_history_returns_all_fields() {
		$result = $this->repository->get_history(array(
			'fields' => 'all',
			'per_page' => 10,
			'page' => 1,
		));
		
		$this->assertIsArray($result);
		$this->assertArrayHasKey('items', $result);
		$this->assertGreaterThan(0, count($result['items']));
		
		// Check that all fields are present
		$item = $result['items'][0];
		$this->assertObjectHasProperty('id', $item);
		$this->assertObjectHasProperty('post_id', $item);
		$this->assertObjectHasProperty('template_id', $item);
		$this->assertObjectHasProperty('status', $item);
		$this->assertObjectHasProperty('generated_title', $item);
		$this->assertObjectHasProperty('generated_content', $item);
		$this->assertObjectHasProperty('prompt', $item);
		$this->assertObjectHasProperty('error_message', $item);
		$this->assertObjectHasProperty('created_at', $item);
		$this->assertObjectHasProperty('template_name', $item);
	}

	/**
	 * Test that get_history returns only list fields when fields='list'
	 */
	public function test_get_history_returns_only_list_fields() {
		$result = $this->repository->get_history(array(
			'fields' => 'list',
			'per_page' => 10,
			'page' => 1,
		));
		
		$this->assertIsArray($result);
		$this->assertArrayHasKey('items', $result);
		$this->assertGreaterThan(0, count($result['items']));
		
		// Check that only list fields are present
		$item = $result['items'][0];
		$this->assertObjectHasProperty('id', $item);
		$this->assertObjectHasProperty('post_id', $item);
		$this->assertObjectHasProperty('template_id', $item);
		$this->assertObjectHasProperty('status', $item);
		$this->assertObjectHasProperty('generated_title', $item);
		$this->assertObjectHasProperty('created_at', $item);
		$this->assertObjectHasProperty('error_message', $item);
		$this->assertObjectHasProperty('template_name', $item);
		
		// Check that full content fields are NOT present
		$this->assertObjectNotHasProperty('generated_content', $item);
		$this->assertObjectNotHasProperty('prompt', $item);
	}

	/**
	 * Test that fields='list' reduces memory usage by excluding large fields
	 */
	public function test_get_history_list_excludes_large_content_fields() {
		// Get with all fields
		$all_result = $this->repository->get_history(array(
			'fields' => 'all',
			'per_page' => 10,
			'page' => 1,
		));
		
		// Get with list fields
		$list_result = $this->repository->get_history(array(
			'fields' => 'list',
			'per_page' => 10,
			'page' => 1,
		));
		
		// Both should have items
		$this->assertGreaterThan(0, count($all_result['items']));
		$this->assertGreaterThan(0, count($list_result['items']));
		
		// All result should have prompt and generated_content
		$all_item = $all_result['items'][0];
		$this->assertObjectHasProperty('prompt', $all_item);
		$this->assertObjectHasProperty('generated_content', $all_item);
		$this->assertNotEmpty($all_item->prompt);
		$this->assertNotEmpty($all_item->generated_content);
		
		// List result should NOT have these fields
		$list_item = $list_result['items'][0];
		$this->assertObjectNotHasProperty('prompt', $list_item);
		$this->assertObjectNotHasProperty('generated_content', $list_item);
	}

	/**
	 * Test that fields parameter defaults to 'all' when not specified
	 */
	public function test_get_history_defaults_to_all_fields() {
		$result = $this->repository->get_history(array(
			'per_page' => 10,
			'page' => 1,
		));
		
		$this->assertIsArray($result);
		$this->assertArrayHasKey('items', $result);
		$this->assertGreaterThan(0, count($result['items']));
		
		// Check that all fields are present (default behavior)
		$item = $result['items'][0];
		$this->assertObjectHasProperty('generated_content', $item);
		$this->assertObjectHasProperty('prompt', $item);
	}

	/**
	 * Test that both field modes return correct template_name
	 */
	public function test_get_history_returns_template_name_in_both_modes() {
		// Test with all fields
		$all_result = $this->repository->get_history(array(
			'fields' => 'all',
			'template_id' => $this->test_template_id,
			'per_page' => 10,
			'page' => 1,
		));
		
		$this->assertGreaterThan(0, count($all_result['items']));
		$this->assertEquals('Test Template', $all_result['items'][0]->template_name);
		
		// Test with list fields
		$list_result = $this->repository->get_history(array(
			'fields' => 'list',
			'template_id' => $this->test_template_id,
			'per_page' => 10,
			'page' => 1,
		));
		
		$this->assertGreaterThan(0, count($list_result['items']));
		$this->assertEquals('Test Template', $list_result['items'][0]->template_name);
	}

	/**
	 * Test that date_from filters out records created before the given date.
	 */
	public function test_get_history_date_from_excludes_older_records() {
		global $wpdb;
		$table = $wpdb->prefix . 'aips_history';

		// Insert an "old" record dated three days ago.
		$old_date = date('Y-m-d H:i:s', strtotime('-3 days'));
		$wpdb->insert(
			$table,
			array(
				'template_id'      => $this->test_template_id,
				'post_id'          => null,
				'status'           => 'completed',
				'generated_title'  => 'Old Record',
				'generated_content'=> 'Old content',
				'prompt'           => 'Old prompt',
				'error_message'    => null,
				'created_at'       => $old_date,
			),
			array('%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s')
		);
		$old_id = $wpdb->insert_id;
		$this->test_history_ids[] = $old_id;

		// Filter from yesterday — the old record should not appear.
		$yesterday = date('Y-m-d', strtotime('-1 day'));
		$result    = $this->repository->get_history(array(
			'date_from' => $yesterday,
			'per_page'  => 100,
			'page'      => 1,
		));

		$ids = array_column($result['items'], 'id');
		$this->assertNotContains($old_id, $ids, 'Record created 3 days ago should be excluded by date_from.');
	}

	/**
	 * Test that date_to filters out records created after the given date.
	 */
	public function test_get_history_date_to_excludes_future_records() {
		global $wpdb;
		$table = $wpdb->prefix . 'aips_history';

		// Insert a "future" record dated two days from now.
		$future_date = date('Y-m-d H:i:s', strtotime('+2 days'));
		$wpdb->insert(
			$table,
			array(
				'template_id'      => $this->test_template_id,
				'post_id'          => null,
				'status'           => 'completed',
				'generated_title'  => 'Future Record',
				'generated_content'=> 'Future content',
				'prompt'           => 'Future prompt',
				'error_message'    => null,
				'created_at'       => $future_date,
			),
			array('%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s')
		);
		$future_id = $wpdb->insert_id;
		$this->test_history_ids[] = $future_id;

		// Filter up to today — the future record should not appear.
		$today  = date('Y-m-d');
		$result = $this->repository->get_history(array(
			'date_to'  => $today,
			'per_page' => 100,
			'page'     => 1,
		));

		$ids = array_column($result['items'], 'id');
		$this->assertNotContains($future_id, $ids, 'Record created in the future should be excluded by date_to.');
	}

	/**
	 * Test that combining date_from and date_to returns only records in the window.
	 */
	public function test_get_history_date_range_returns_only_window_records() {
		global $wpdb;
		$table = $wpdb->prefix . 'aips_history';

		$old_date    = date('Y-m-d H:i:s', strtotime('-10 days'));
		$future_date = date('Y-m-d H:i:s', strtotime('+10 days'));

		$wpdb->insert(
			$table,
			array(
				'template_id'      => $this->test_template_id,
				'post_id'          => null,
				'status'           => 'completed',
				'generated_title'  => 'Out of Range Old',
				'generated_content'=> 'content',
				'prompt'           => 'prompt',
				'error_message'    => null,
				'created_at'       => $old_date,
			),
			array('%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s')
		);
		$old_id = $wpdb->insert_id;
		$this->test_history_ids[] = $old_id;

		$wpdb->insert(
			$table,
			array(
				'template_id'      => $this->test_template_id,
				'post_id'          => null,
				'status'           => 'completed',
				'generated_title'  => 'Out of Range Future',
				'generated_content'=> 'content',
				'prompt'           => 'prompt',
				'error_message'    => null,
				'created_at'       => $future_date,
			),
			array('%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s')
		);
		$future_id = $wpdb->insert_id;
		$this->test_history_ids[] = $future_id;

		// Window: yesterday to today.
		$date_from = date('Y-m-d', strtotime('-1 day'));
		$date_to   = date('Y-m-d');

		$result = $this->repository->get_history(array(
			'date_from' => $date_from,
			'date_to'   => $date_to,
			'per_page'  => 100,
			'page'      => 1,
		));

		$ids = array_column($result['items'], 'id');
		$this->assertNotContains($old_id,    $ids, 'Record from 10 days ago should be excluded by date range.');
		$this->assertNotContains($future_id, $ids, 'Record from 10 days in the future should be excluded by date range.');
	}

	/**
	 * Test that an invalid date_from format is silently ignored (no WHERE clause applied).
	 */
	public function test_get_history_invalid_date_format_is_ignored() {
		$result_no_filter = $this->repository->get_history(array(
			'per_page' => 100,
			'page'     => 1,
		));

		$result_bad_filter = $this->repository->get_history(array(
			'date_from' => 'not-a-date',
			'date_to'   => '01/01/2026',
			'per_page'  => 100,
			'page'      => 1,
		));

		// Both queries should return the same total — bad formats must be discarded silently.
		$this->assertEquals(
			$result_no_filter['total'],
			$result_bad_filter['total'],
			'Invalid date formats should be ignored, returning the same result set as no date filter.'
		);
	}

	/**
	 * Test that get_templates_for_filter returns only templates with history records.
	 */
	public function test_get_templates_for_filter_returns_correct_templates() {
		$templates = $this->repository->get_templates_for_filter();

		$this->assertIsArray($templates);
		$this->assertNotEmpty($templates, 'Should return at least one template since we inserted history records.');

		$ids = array_column($templates, 'id');
		$this->assertContains(
			(string) $this->test_template_id,
			array_map('strval', $ids),
			'The test template should appear in the filter list.'
		);

		// Verify the shape of each returned object.
		foreach ($templates as $tmpl) {
			$this->assertObjectHasProperty('id',   $tmpl);
			$this->assertObjectHasProperty('name', $tmpl);
		}
	}
}
