<?php
/**
 * Tests for AIPS_Article_Structure_Repository
 *
 * @package AI_Post_Scheduler
 */

class AIPS_Article_Structure_Repository_Test extends WP_UnitTestCase {
	
	private $repository;
	
	public function setUp(): void {
		parent::setUp();
		$this->repository = new AIPS_Article_Structure_Repository();
	}
	
	public function tearDown(): void {
		// Clean up test data
		global $wpdb;
		$table_name = $wpdb->prefix . 'aips_article_structures';
		$wpdb->query("DELETE FROM $table_name WHERE name LIKE 'Test%'");
		parent::tearDown();
	}
	
	public function test_create_structure() {
		$data = array(
			'name' => 'Test Structure',
			'description' => 'Test Description',
			'structure_data' => wp_json_encode(array('sections' => array('intro', 'body', 'conclusion'))),
			'is_active' => 1,
			'is_default' => 0,
		);
		
		$id = $this->repository->create($data);
		
		$this->assertIsInt($id);
		$this->assertGreaterThan(0, $id);
	}
	
	public function test_get_by_id() {
		$data = array(
			'name' => 'Test Structure 2',
			'description' => 'Test Description 2',
			'structure_data' => wp_json_encode(array('sections' => array('intro'))),
			'is_active' => 1,
		);
		
		$id = $this->repository->create($data);
		$structure = $this->repository->get_by_id($id);
		
		$this->assertNotNull($structure);
		$this->assertEquals('Test Structure 2', $structure->name);
		$this->assertEquals('Test Description 2', $structure->description);
	}
	
	public function test_get_all_structures() {
		$data1 = array(
			'name' => 'Test Structure A',
			'description' => 'Description A',
			'structure_data' => wp_json_encode(array('sections' => array('intro'))),
			'is_active' => 1,
		);
		
		$data2 = array(
			'name' => 'Test Structure B',
			'description' => 'Description B',
			'structure_data' => wp_json_encode(array('sections' => array('intro'))),
			'is_active' => 0,
		);
		
		$this->repository->create($data1);
		$this->repository->create($data2);
		
		$all = $this->repository->get_all();
		$active_only = $this->repository->get_all(true);
		
		$this->assertGreaterThanOrEqual(2, count($all));
		
		// Check that active_only doesn't include inactive
		$found_inactive = false;
		foreach ($active_only as $structure) {
			if ($structure->name === 'Test Structure B') {
				$found_inactive = true;
				break;
			}
		}
		$this->assertFalse($found_inactive);
	}
	
	public function test_get_default_structure() {
		// First unset any existing defaults
		global $wpdb;
		$table_name = $wpdb->prefix . 'aips_article_structures';
		$wpdb->query("UPDATE $table_name SET is_default = 0");
		
		$data = array(
			'name' => 'Test Default Structure',
			'description' => 'Test Default',
			'structure_data' => wp_json_encode(array('sections' => array('intro'))),
			'is_active' => 1,
			'is_default' => 1,
		);
		
		$id = $this->repository->create($data);
		$default = $this->repository->get_default();
		
		$this->assertNotNull($default);
		$this->assertEquals(1, $default->is_default);
	}
	
	public function test_set_default_unsets_others() {
		// Create two structures
		$data1 = array(
			'name' => 'Test Structure Default 1',
			'description' => 'First Default',
			'structure_data' => wp_json_encode(array('sections' => array('intro'))),
			'is_active' => 1,
			'is_default' => 1,
		);
		
		$data2 = array(
			'name' => 'Test Structure Default 2',
			'description' => 'Second Default',
			'structure_data' => wp_json_encode(array('sections' => array('intro'))),
			'is_active' => 1,
		);
		
		$id1 = $this->repository->create($data1);
		$id2 = $this->repository->create($data2);
		
		// Set second as default
		$this->repository->set_default($id2);
		
		// Check that first is no longer default
		$structure1 = $this->repository->get_by_id($id1);
		$structure2 = $this->repository->get_by_id($id2);
		
		$this->assertEquals(0, $structure1->is_default);
		$this->assertEquals(1, $structure2->is_default);
	}
	
	public function test_update_structure() {
		$data = array(
			'name' => 'Test Update Structure',
			'description' => 'Original Description',
			'structure_data' => wp_json_encode(array('sections' => array('intro'))),
			'is_active' => 1,
		);
		
		$id = $this->repository->create($data);
		
		$update = array(
			'name' => 'Test Updated Structure',
			'description' => 'Updated Description',
		);
		
		$result = $this->repository->update($id, $update);
		$this->assertTrue($result);
		
		$structure = $this->repository->get_by_id($id);
		$this->assertEquals('Test Updated Structure', $structure->name);
		$this->assertEquals('Updated Description', $structure->description);
	}
	
	public function test_delete_structure() {
		$data = array(
			'name' => 'Test Delete Structure',
			'description' => 'To Be Deleted',
			'structure_data' => wp_json_encode(array('sections' => array('intro'))),
			'is_active' => 1,
		);
		
		$id = $this->repository->create($data);
		$result = $this->repository->delete($id);
		
		$this->assertTrue($result);
		
		$structure = $this->repository->get_by_id($id);
		$this->assertNull($structure);
	}
	
	public function test_name_exists() {
		$data = array(
			'name' => 'Test Unique Name',
			'description' => 'Test',
			'structure_data' => wp_json_encode(array('sections' => array('intro'))),
			'is_active' => 1,
		);
		
		$id = $this->repository->create($data);
		
		$this->assertTrue($this->repository->name_exists('Test Unique Name'));
		$this->assertFalse($this->repository->name_exists('Nonexistent Name'));
		$this->assertFalse($this->repository->name_exists('Test Unique Name', $id));
	}
	
	public function test_count_by_status() {
		// Clean slate
		global $wpdb;
		$table_name = $wpdb->prefix . 'aips_article_structures';
		$wpdb->query("DELETE FROM $table_name WHERE name LIKE 'Test Count%'");
		
		$data1 = array(
			'name' => 'Test Count Active',
			'description' => 'Active',
			'structure_data' => wp_json_encode(array('sections' => array('intro'))),
			'is_active' => 1,
		);
		
		$data2 = array(
			'name' => 'Test Count Inactive',
			'description' => 'Inactive',
			'structure_data' => wp_json_encode(array('sections' => array('intro'))),
			'is_active' => 0,
		);
		
		$this->repository->create($data1);
		$this->repository->create($data2);
		
		$counts = $this->repository->count_by_status();
		
		$this->assertIsArray($counts);
		$this->assertArrayHasKey('total', $counts);
		$this->assertArrayHasKey('active', $counts);
		$this->assertGreaterThanOrEqual(2, $counts['total']);
	}
}
