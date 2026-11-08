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
		unset($_REQUEST['paged'], $_GET['paged'], $_GET['review_per_page']);

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

		foreach (array('cb', 'title', 'context', 'type', 'created', 'actions') as $key) {
			$this->assertArrayHasKey($key, $columns);
		}
	}

	public function test_per_page_default_is_twelve() {
		$this->assertSame(12, AIPS_Pending_Review_List_Table::PER_PAGE_DEFAULT);
	}

	public function test_columns_are_ordered_with_actions_last() {
		$table = new AIPS_Pending_Review_List_Table();

		$this->assertSame(
			array('cb', 'title', 'context', 'type', 'created', 'actions'),
			array_keys($table->get_columns())
		);
	}

	public function test_only_non_essential_columns_are_hideable() {
		$table    = new AIPS_Pending_Review_List_Table();
		$hideable = $table->get_hideable_columns();

		$this->assertSame(array('context', 'type', 'created'), array_keys($hideable), 'Only Context, Type and Created are toggleable.');
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

	public function test_cards_switch_limit_expands_list_query() {
		$this->make_review_draft();
		$this->make_review_draft();
		$this->make_review_draft();

		$force_two = static function () {
			return 2;
		};
		add_filter(AIPS_Pending_Review_List_Table::PER_PAGE_OPTION, $force_two);
		$_GET['review_per_page'] = '3';
		$table = new AIPS_Pending_Review_List_Table();
		$table->prepare_items();
		remove_filter(AIPS_Pending_Review_List_Table::PER_PAGE_OPTION, $force_two);

		$this->assertCount(3, $table->items);
		$this->assertSame(3, (int) $table->get_pagination_arg('per_page'));
	}

	public function test_bounded_per_page_keeps_values_above_the_old_cap_and_rejects_the_rest() {
		$this->assertSame(1, AIPS_Pending_Review_List_Table::bounded_per_page(0));
		$this->assertSame(12, AIPS_Pending_Review_List_Table::bounded_per_page(12));
		$this->assertSame(80, AIPS_Pending_Review_List_Table::bounded_per_page(80));
		$this->assertSame(
			AIPS_Pending_Review_List_Table::PER_PAGE_MAX,
			AIPS_Pending_Review_List_Table::bounded_per_page(AIPS_Pending_Review_List_Table::PER_PAGE_MAX)
		);
		$this->assertSame(
			AIPS_Pending_Review_List_Table::PER_PAGE_MAX,
			AIPS_Pending_Review_List_Table::bounded_per_page(999999)
		);
	}

	public function test_review_per_page_query_is_capped() {
		$force_eighty = static function () {
			return 80;
		};
		add_filter(AIPS_Pending_Review_List_Table::PER_PAGE_OPTION, $force_eighty);

		try {
			$table = new AIPS_Pending_Review_List_Table();
			$table->prepare_items();
			$this->assertSame(80, (int) $table->get_pagination_arg('per_page'), 'Screen Options above 50 are kept.');

			$_GET['review_per_page'] = '90';
			$table = new AIPS_Pending_Review_List_Table();
			$table->prepare_items();
			$this->assertSame(90, (int) $table->get_pagination_arg('per_page'), 'A larger Cards-to-List page size is kept under the cap.');

			$_GET['review_per_page'] = '999999';
			$table = new AIPS_Pending_Review_List_Table();
			$table->prepare_items();
			$this->assertSame(
				AIPS_Pending_Review_List_Table::PER_PAGE_MAX,
				(int) $table->get_pagination_arg('per_page'),
				'review_per_page cannot raise the SQL limit without a ceiling.'
			);
		} finally {
			remove_filter(AIPS_Pending_Review_List_Table::PER_PAGE_OPTION, $force_eighty);
			unset($_GET['review_per_page']);
		}

		$force_huge = static function () {
			return 5000;
		};
		add_filter(AIPS_Pending_Review_List_Table::PER_PAGE_OPTION, $force_huge);
		try {
			$table = new AIPS_Pending_Review_List_Table();
			$table->prepare_items();
			$this->assertSame(AIPS_Pending_Review_List_Table::PER_PAGE_MAX, (int) $table->get_pagination_arg('per_page'));
		} finally {
			remove_filter(AIPS_Pending_Review_List_Table::PER_PAGE_OPTION, $force_huge);
		}
	}

	public function test_ajax_per_page_uses_the_same_cap() {
		$admin_id = $this->factory->user->create(array('role' => 'administrator'));
		wp_set_current_user($admin_id);

		$review = AIPS_Post_Review::instance();
		$property = new ReflectionProperty($review, 'repository');
		$property->setAccessible(true);
		$original = $property->getValue($review);
		$spy = new class extends AIPS_Post_Review_Repository {
			/** @var int|null */
			public $last_per_page = null;

			public function get_draft_posts($args = array()) {
				$this->last_per_page = isset($args['per_page']) ? (int) $args['per_page'] : null;
				return array(
					'items'        => array(),
					'total'        => 0,
					'pages'        => 1,
					'current_page' => 1,
				);
			}
		};
		$property->setValue($review, $spy);

		try {
			$this->request_draft_page('80');
			$this->assertSame(80, $spy->last_per_page, 'Cards AJAX still honors a Screen Options size above 50.');

			$this->request_draft_page('999999');
			$this->assertSame(AIPS_Pending_Review_List_Table::PER_PAGE_MAX, $spy->last_per_page);
		} finally {
			$property->setValue($review, $original);
			wp_set_current_user(0);
			unset($_POST['nonce'], $_POST['per_page'], $_POST['page'], $_REQUEST['nonce'], $_REQUEST['per_page'], $_REQUEST['page']);
		}
	}

	/**
	 * Invoke the draft-list AJAX handler and swallow the JSON wp_die.
	 *
	 * @param string $per_page Requested page size.
	 * @return void
	 */
	private function request_draft_page($per_page) {
		$_POST['nonce'] = $_REQUEST['nonce'] = wp_create_nonce('aips_ajax_nonce');
		$_POST['page'] = $_REQUEST['page'] = '1';
		$_POST['per_page'] = $_REQUEST['per_page'] = $per_page;

		ob_start();
		try {
			AIPS_Post_Review::instance()->ajax_get_draft_posts();
		} catch (WPDieException $e) {
			// wp_send_json_success exits through wp_die.
		} finally {
			if (ob_get_level() > 0) {
				ob_end_clean();
			}
		}
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
		$this->assertStringContainsString('aips-open-history-modal', $html);
		$this->assertStringContainsString('View History', $html);
		$this->assertStringNotContainsString('aips-view-session', $html, 'View Session was removed from the row menu.');
	}

	public function test_context_column_renders_source_line() {
		$controller = new class {
			public function format_source($item) {
				return 'Author: Jane - Topic X';
			}
		};
		$table  = new AIPS_Pending_Review_List_Table(array('controller' => $controller));
		$method = new ReflectionMethod($table, 'column_context');
		$method->setAccessible(true);

		$html = $method->invoke($table, (object) array('post_id' => 42, 'id' => 7));

		$this->assertStringContainsString('aips-cell-source', $html);
		$this->assertStringContainsString('Author: Jane - Topic X', $html);
	}

	public function test_default_tablenav_is_suppressed_and_pagination_renders() {
		$this->make_review_draft();

		$table = new AIPS_Pending_Review_List_Table();
		$table->prepare_items();

		ob_start();
		$table->display();
		$display = ob_get_clean();

		$this->assertStringNotContainsString('tablenav', $display, 'Pagination is rendered in the tab toolbar, not by display().');

		ob_start();
		$table->pagination('top');
		$pagination = ob_get_clean();

		$this->assertStringContainsString('displaying-num', $pagination);
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
