<?php
/**
 * Tests for AIPS_Pending_Review_List_Table — columns, Screen Options surface,
 * and per-page pagination honoured from the repository.
 *
 * @package AI_Post_Scheduler
 */

class Test_AIPS_Pending_Review_List_Table extends WP_UnitTestCase {

	/** @var int[] */
	private $post_ids = array();

	/** @var int[] */
	private $history_ids = array();

	public function tearDown(): void {
		global $wpdb;

		foreach ($this->post_ids as $post_id) {
			wp_delete_post($post_id, true);
		}
		foreach ($this->history_ids as $history_id) {
			$wpdb->delete($wpdb->prefix . 'aips_history', array('id' => $history_id), array('%d'));
		}
		unset($_REQUEST['paged'], $_GET['paged']);

		parent::tearDown();
	}

	private function make_review_draft() {
		global $wpdb;

		$post_id = wp_insert_post(array(
			'post_title'  => 'Draft ' . uniqid(),
			'post_status' => 'draft',
			'post_type'   => 'post',
		));
		$this->post_ids[] = (int) $post_id;

		$wpdb->insert($wpdb->prefix . 'aips_history', array(
			'post_id'         => $post_id,
			'status'          => 'completed',
			'generated_title' => 'Generated Title',
			'creation_method' => 'scheduled',
			'created_at'      => time(),
			'completed_at'    => time(),
			'record_kind'     => AIPS_History_Record_Kind::for_creation_method('scheduled', array('post_id' => $post_id)),
		));
		$this->history_ids[] = (int) $wpdb->insert_id;

		return (int) $post_id;
	}

	public function test_columns_definition() {
		$table   = new AIPS_Pending_Review_List_Table();
		$columns = $table->get_columns();

		foreach (array('cb', 'title', 'type', 'created', 'actions') as $key) {
			$this->assertArrayHasKey($key, $columns);
		}
	}

	public function test_only_non_essential_columns_are_hideable() {
		$table    = new AIPS_Pending_Review_List_Table();
		$hideable = $table->get_hideable_columns();

		$this->assertSame(array('type', 'created'), array_keys($hideable), 'Only Type and Created are toggleable.');
		$this->assertArrayNotHasKey('title', $hideable, 'Post title is always shown.');
		$this->assertArrayNotHasKey('actions', $hideable, 'Actions is always shown.');
		$this->assertArrayNotHasKey('cb', $hideable, 'The checkbox column is never toggleable.');
	}

	public function test_per_page_screen_option_limits_the_page() {
		$this->make_review_draft();
		$this->make_review_draft();
		$this->make_review_draft();

		$force_two = static function () {
			return 2;
		};
		add_filter(AIPS_Pending_Review_List_Table::PER_PAGE_OPTION, $force_two);

		$table = new AIPS_Pending_Review_List_Table();
		$table->prepare_items();

		remove_filter(AIPS_Pending_Review_List_Table::PER_PAGE_OPTION, $force_two);

		$this->assertCount(2, $table->items, 'Page shows only per_page items.');
		$this->assertGreaterThanOrEqual(3, $table->get_total_items(), 'Total counts every matching draft.');
		$this->assertSame(2, (int) $table->get_pagination_arg('total_pages'), '3 drafts at 2/page is 2 pages.');
	}

	public function test_row_actions_markup_matches_js_contract() {
		$table  = new AIPS_Pending_Review_List_Table();
		$method = new ReflectionMethod($table, 'column_actions');
		$method->setAccessible(true);

		$html = $method->invoke($table, (object) array('post_id' => 42, 'id' => 7));

		$this->assertStringContainsString('aips-publish-post', $html);
		$this->assertStringContainsString('aips-row-action-overflow-toggle', $html);
		$this->assertStringContainsString('aips-review-row-actions-42', $html);
		$this->assertStringContainsString('data-post-id="42"', $html);
	}

	public function test_row_checkbox_carries_post_and_history_ids() {
		$table  = new AIPS_Pending_Review_List_Table();
		$method = new ReflectionMethod($table, 'column_cb');
		$method->setAccessible(true);

		$html = $method->invoke($table, (object) array('post_id' => 42, 'id' => 7));

		$this->assertStringContainsString('aips-post-checkbox', $html);
		$this->assertStringContainsString('value="42"', $html);
		$this->assertStringContainsString('data-post-id="42"', $html);
		$this->assertStringContainsString('data-history-id="7"', $html);
	}
}
