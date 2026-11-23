<?php
/**
 * Tests for AIPS_Config_Reader.
 *
 * @package AI_Post_Scheduler
 */

class Test_AIPS_Config_Reader extends WP_UnitTestCase {

	/** @var AIPS_Config_Reader */
	private $reader;

	public function setUp(): void {
		parent::setUp();
		$this->reader = new AIPS_Config_Reader($GLOBALS['wpdb']);

		if (!taxonomy_exists('aips_source_group')) {
			register_taxonomy('aips_source_group', array(), array('public' => false));
		}
	}

	public function test_config_entity_types_match_catalog_keys() {
		$this->assertSame(array_keys(AIPS_Config_Reader::CONFIG_TABLES), $this->reader->config_entity_types());
	}

	public function test_table_for_resolves_type_and_suffix_and_rejects_unknown() {
		$prefix = $GLOBALS['wpdb']->prefix;

		$this->assertSame($prefix . 'aips_templates', $this->reader->table_for('templates'));
		$this->assertSame($prefix . 'aips_templates', $this->reader->table_for('aips_templates'));
		$this->assertNull($this->reader->table_for('not_a_real_entity'));
	}

	public function test_read_table_returns_created_row_with_id() {
		$voice_id = (new AIPS_Voices_Repository())->create(array(
			'name'         => 'Config Reader Voice',
			'title_prompt' => 'A title prompt.',
			'is_active'    => 1,
		));
		$this->assertNotFalse($voice_id);

		$rows = $this->reader->read_table('voices');
		$names = wp_list_pluck($rows, 'name');
		$this->assertContains('Config Reader Voice', $names);

		$match = null;
		foreach ($rows as $row) {
			if ((int) $row['id'] === (int) $voice_id) {
				$match = $row;
				break;
			}
		}
		$this->assertNotNull($match, 'Created voice is readable by its ID.');
		$this->assertArrayHasKey('title_prompt', $match);
	}

	public function test_read_table_unknown_type_is_empty() {
		$this->assertSame(array(), $this->reader->read_table('not_a_real_entity'));
	}

	public function test_read_table_object_output() {
		$voice_id = (new AIPS_Voices_Repository())->create(array('name' => 'Obj Voice', 'is_active' => 1));
		$rows = $this->reader->read_table('voices', OBJECT);
		$this->assertNotEmpty($rows);
		$this->assertIsObject($rows[0]);
		$this->assertContains((int) $voice_id, array_map('intval', wp_list_pluck($rows, 'id')));
	}

	public function test_read_all_includes_present_tables_in_catalog_order() {
		(new AIPS_Voices_Repository())->create(array('name' => 'Read All Voice', 'is_active' => 1));

		$all = $this->reader->read_all();

		// Only existing tables, and their keys follow catalog order.
		$catalog_order = array_values(array_intersect(array_keys(AIPS_Config_Reader::CONFIG_TABLES), array_keys($all)));
		$this->assertSame($catalog_order, array_keys($all));

		$this->assertArrayHasKey('voices', $all);
		$this->assertContains('Read All Voice', wp_list_pluck($all['voices'], 'name'));
	}

	public function test_read_terms_returns_category() {
		$term = wp_insert_term('Config Reader Cat', 'category');
		$this->assertIsArray($term);

		$terms = $this->reader->read_terms('category');
		$this->assertContains('Config Reader Cat', wp_list_pluck($terms, 'name'));

		$match = null;
		foreach ($terms as $row) {
			if ((int) $row['term_id'] === (int) $term['term_id']) {
				$match = $row;
				break;
			}
		}
		$this->assertNotNull($match);
		$this->assertSame('category', $match['taxonomy']);
		$this->assertArrayHasKey('slug', $match);
	}
}
