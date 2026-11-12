<?php
/**
 * Tests for AIPS_Sources_List_Table and AIPS_Source_Data_List_Table.
 *
 * @package AI_Post_Scheduler
 */

class Test_AIPS_Sources_List_Tables extends WP_UnitTestCase {

	/** @var AIPS_Sources_Repository */
	private $sources_repo;

	/** @var AIPS_Sources_Data_Repository */
	private $data_repo;

	/** @var int[] */
	private $source_ids = array();

	public function set_up(): void {
		parent::set_up();
		AIPS_DB_Manager::install_tables();
		wp_set_current_user(self::factory()->user->create(array('role' => 'administrator')));
		$this->sources_repo = new AIPS_Sources_Repository();
		$this->data_repo    = new AIPS_Sources_Data_Repository();
	}

	public function tear_down(): void {
		foreach ($this->source_ids as $id) {
			$this->sources_repo->delete($id);
		}
		$this->source_ids = array();
		$_GET = array();
		$_REQUEST = array();
		wp_set_current_user(0);
		parent::tear_down();
	}

	private function make_source(array $data): int {
		$id = $this->sources_repo->create($data);
		$this->source_ids[] = $id;
		return $id;
	}

	private function invoke(object $table, string $method, ...$args) {
		$ref = new ReflectionMethod($table, $method);
		$ref->setAccessible(true);
		return $ref->invoke($table, ...$args);
	}

	public function test_sources_table_columns_and_primary() {
		$table = new AIPS_Sources_List_Table();
		$this->assertInstanceOf(AIPS_List_Table::class, $table);
		$this->assertSame(array('cb', 'url', 'label', 'groups', 'content', 'actions'), array_keys($table->get_columns()));
		$this->assertSame('url', $this->invoke($table, 'get_default_primary_column_name'));
		$this->assertSame(array('label', 'groups', 'content'), array_keys($table->get_hideable_columns()));
	}

	public function test_sources_table_filters_by_status_and_search() {
		$active   = $this->make_source(array('url' => 'https://alpha.example.com', 'label' => 'Alpha Feed'));
		$inactive = $this->make_source(array('url' => 'https://beta.example.com', 'label' => 'Beta Feed', 'is_active' => 0));

		$_GET['status'] = 'inactive';
		$table = new AIPS_Sources_List_Table();
		$table->prepare_items();
		$ids = array_map(function ($row) { return (int) $row->id; }, $table->items);
		$this->assertContains($inactive, $ids);
		$this->assertNotContains($active, $ids);
		$this->assertTrue($table->has_active_filters());

		$_GET = array('s' => 'alpha');
		$table = new AIPS_Sources_List_Table();
		$table->prepare_items();
		$ids = array_map(function ($row) { return (int) $row->id; }, $table->items);
		$this->assertContains($active, $ids);
		$this->assertNotContains($inactive, $ids);
	}

	public function test_sources_table_ignores_invalid_status_filter() {
		$_GET['status'] = 'bogus';
		$table = new AIPS_Sources_List_Table();
		$table->prepare_items();
		$this->assertSame('', $table->get_filters()['status']);
		$this->assertFalse($table->has_active_filters());
	}

	public function test_sources_row_markup_keeps_edit_modal_data_and_button_group() {
		$id = $this->make_source(array('url' => 'https://gamma.example.com', 'label' => 'Gamma', 'description' => 'Notes'));
		$table = new AIPS_Sources_List_Table();
		$table->prepare_items();

		$item = null;
		foreach ($table->items as $row) {
			if ((int) $row->id === $id) {
				$item = $row;
			}
		}
		$this->assertNotNull($item);

		ob_start();
		$table->single_row($item);
		$html = ob_get_clean();

		$this->assertStringContainsString('data-source-id="' . $id . '"', $html);
		$this->assertStringContainsString('data-url="https://gamma.example.com"', $html);
		$this->assertStringContainsString('data-label="Gamma"', $html);
		$this->assertStringContainsString('data-term-ids=', $html);
		$this->assertStringContainsString('aips-row-action-group', $html);
		$this->assertStringContainsString('aips-edit-source', $html);
		$this->assertStringContainsString('aips-toggle-source', $html);
		$this->assertStringContainsString('aips-delete-source', $html);
		$this->assertStringContainsString('aips-fetch-source-now', $html);
		$this->assertStringContainsString('Manage Data', $html);
		// Status badge replaces the old schedule chip in the label cell.
		$this->assertStringContainsString('Active', $this->invoke($table, 'column_label', $item));
	}

	public function test_sources_content_cell_uses_icon_with_tooltip() {
		$table = new AIPS_Sources_List_Table();
		$ok    = $this->invoke($table, 'column_content', (object) array('id' => 1, 'last_fetch_status' => 'success'));
		$this->assertStringContainsString('dashicons-yes-alt', $ok);
		$this->assertStringContainsString('title="Last attempt succeeded', $ok);

		$bad = $this->invoke($table, 'column_content', (object) array('id' => 1, 'last_fetch_status' => 'failed', 'last_fetch_error' => 'Timeout'));
		$this->assertStringContainsString('dashicons-dismiss', $bad);
		$this->assertStringContainsString('Last attempt failed: Timeout', $bad);
		$this->assertStringNotContainsString('>Last attempt', $bad, 'Text label must not be rendered, only the tooltip.');
	}

	public function test_source_data_table_columns_order_and_removed_columns() {
		$table = new AIPS_Source_Data_List_Table(array('source_id' => 5));
		$this->assertSame(array('title', 'url', 'status', 'fetched', 'http', 'used', 'size', 'actions'), array_keys($table->get_columns()));
		$this->assertSame('Fetch Date/Time', wp_strip_all_tags($table->get_columns()['fetched']));
		$this->assertArrayNotHasKey('snippet', $table->get_columns());
		$this->assertArrayNotHasKey('chars', $table->get_columns());
		$this->assertSame('title', $this->invoke($table, 'get_default_primary_column_name'));

		$global = new AIPS_Source_Data_List_Table();
		$this->assertSame('source', array_keys($global->get_columns())[0]);
	}

	public function test_source_data_table_reads_filters_and_paginates() {
		$id = $this->make_source(array('url' => 'https://delta.example.com', 'label' => 'Delta'));
		$this->data_repo->insert_if_new($id, array('url' => 'https://delta.example.com/a', 'page_title' => 'First Page', 'extracted_text' => 'Alpha content'));
		$this->data_repo->insert_if_new($id, array('url' => 'https://delta.example.com/b', 'page_title' => 'Second Page', 'extracted_text' => 'Beta content'));

		$_GET['fetch_status'] = 'success';
		$_GET['min_char_count'] = '3';
		$table = new AIPS_Source_Data_List_Table(array('source_id' => $id));
		$filters = $table->get_filters();
		$this->assertSame('success', $filters['fetch_status']);
		$this->assertSame(3, $filters['min_char_count']);
		$this->assertTrue($table->has_active_filters());

		$table->prepare_items();
		$this->assertSame(2, $table->get_total_items());
		$this->assertCount(2, $table->items);

		$_GET = array('s' => 'Second');
		$table = new AIPS_Source_Data_List_Table(array('source_id' => $id));
		$table->prepare_items();
		$this->assertSame(1, $table->get_total_items());
	}

	public function test_source_data_row_actions_are_in_one_group_without_td_flex_class() {
		$table = new AIPS_Source_Data_List_Table(array('source_id' => 5));
		$html  = $this->invoke($table, 'column_actions', (object) array('id' => 9));

		$this->assertSame(1, substr_count($html, 'aips-row-action-group'));
		$this->assertStringContainsString('aips-view-source-data', $html);
		$this->assertStringContainsString('aips-delete-source-data', $html);
		$this->assertStringNotContainsString('cell-actions', $html);

		ob_start();
		$table->single_row((object) array('id' => 9, 'url' => 'https://x.test', 'page_title' => 'T', 'fetch_status' => 'success'));
		$this->assertStringContainsString('data-source-data-id="9"', ob_get_clean());
	}

	public function test_source_data_table_has_no_snippet_or_char_cells() {
		$table = new AIPS_Source_Data_List_Table(array('source_id' => 5));
		$this->assertFalse(method_exists($table, 'column_content'));
		$this->assertFalse(method_exists($table, 'column_char_count'));
	}
}
