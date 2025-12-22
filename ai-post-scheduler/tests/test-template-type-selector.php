<?php
/**
 * Tests for AIPS_Template_Type_Selector
 *
 * @package AI_Post_Scheduler
 */

class AIPS_Template_Type_Selector_Test extends WP_UnitTestCase {
	
	private $selector;
	private $structure_repo;
	
	public function setUp(): void {
		parent::setUp();
		$this->selector = new AIPS_Template_Type_Selector();
		$this->structure_repo = new AIPS_Article_Structure_Repository();
		
		// Create test structures
		$this->create_test_structures();
	}
	
	public function tearDown(): void {
		// Clean up test data
		global $wpdb;
		$table_name = $wpdb->prefix . 'aips_article_structures';
		$wpdb->query("DELETE FROM $table_name WHERE name LIKE 'Test Selector%'");
		parent::tearDown();
	}
	
	private function create_test_structures() {
		$structures = array(
			array(
				'name' => 'Test Selector Structure 1',
				'description' => 'First Structure',
				'structure_data' => wp_json_encode(array('sections' => array('intro'))),
				'is_active' => 1,
				'is_default' => 1,
			),
			array(
				'name' => 'Test Selector Structure 2',
				'description' => 'Second Structure',
				'structure_data' => wp_json_encode(array('sections' => array('intro'))),
				'is_active' => 1,
				'is_default' => 0,
			),
			array(
				'name' => 'Test Selector Structure 3',
				'description' => 'Third Structure',
				'structure_data' => wp_json_encode(array('sections' => array('intro'))),
				'is_active' => 1,
				'is_default' => 0,
			),
		);
		
		foreach ($structures as $structure) {
			$this->structure_repo->create($structure);
		}
	}
	
	public function test_select_specific_structure() {
		$structures = $this->structure_repo->get_all(true);
		$test_structure = null;
		foreach ($structures as $s) {
			if ($s->name === 'Test Selector Structure 2') {
				$test_structure = $s;
				break;
			}
		}
		
		$this->assertNotNull($test_structure);
		
		$schedule = (object) array(
			'id' => 1,
			'article_structure_id' => $test_structure->id,
			'rotation_pattern' => null,
		);
		
		$selected_id = $this->selector->select_structure($schedule);
		$this->assertEquals($test_structure->id, $selected_id);
	}
	
	public function test_select_default_when_none_specified() {
		$schedule = (object) array(
			'id' => 1,
			'article_structure_id' => null,
			'rotation_pattern' => null,
		);
		
		$selected_id = $this->selector->select_structure($schedule);
		$this->assertNotNull($selected_id);
		
		// Should select default structure
		$default = $this->structure_repo->get_default();
		$this->assertEquals($default->id, $selected_id);
	}
	
	public function test_select_random_structure() {
		$schedule = (object) array(
			'id' => 1,
			'article_structure_id' => null,
			'rotation_pattern' => 'random',
		);
		
		$selected_id = $this->selector->select_structure($schedule);
		$this->assertNotNull($selected_id);
		
		// Verify it's a valid structure
		$structure = $this->structure_repo->get_by_id($selected_id);
		$this->assertNotNull($structure);
		$this->assertEquals(1, $structure->is_active);
	}
	
	public function test_get_rotation_patterns() {
		$patterns = $this->selector->get_rotation_patterns();
		
		$this->assertIsArray($patterns);
		$this->assertArrayHasKey('sequential', $patterns);
		$this->assertArrayHasKey('random', $patterns);
		$this->assertArrayHasKey('weighted', $patterns);
		$this->assertArrayHasKey('alternating', $patterns);
	}
	
	public function test_preview_next_structure_sequential() {
		$preview = $this->selector->preview_next_structure('sequential', 0);
		
		$this->assertIsArray($preview);
		$this->assertArrayHasKey('id', $preview);
		$this->assertArrayHasKey('name', $preview);
		$this->assertArrayHasKey('description', $preview);
	}
	
	public function test_preview_next_structure_random() {
		$preview = $this->selector->preview_next_structure('random', 0);
		
		$this->assertIsArray($preview);
		$this->assertArrayHasKey('id', $preview);
		$this->assertArrayHasKey('name', $preview);
	}
	
	public function test_preview_returns_null_when_no_structures() {
		// Delete all test structures
		global $wpdb;
		$table_name = $wpdb->prefix . 'aips_article_structures';
		$wpdb->query("DELETE FROM $table_name WHERE name LIKE 'Test Selector%'");
		
		$preview = $this->selector->preview_next_structure('sequential', 0);
		$this->assertNull($preview);
	}
}
