<?php
/**
 * Tests for AIPS_Template_Repository
 *
 * @package AI_Post_Scheduler
 */

class AIPS_Template_Repository_Test extends WP_UnitTestCase {
	
	private $repository;
	
	public function setUp(): void {
		parent::setUp();
		$this->repository = new AIPS_Template_Repository();
	}
	
	public function tearDown(): void {
		// Clean up test data
		global $wpdb;
		$table_name = AIPS_DB_Manager::get_table_name('templates');
		$wpdb->query("DELETE FROM $table_name WHERE name LIKE 'Test%'");
		parent::tearDown();
	}
	
	public function test_constructor_uses_db_manager() {
		// This test verifies that the constructor properly uses AIPS_DB_Manager::get_table_name()
		// by checking that the repository can perform basic operations
		$this->assertInstanceOf('AIPS_Template_Repository', $this->repository);
		
		// Verify we can call get_all without errors
		$templates = $this->repository->get_all();
		$this->assertIsArray($templates);
	}
	
	public function test_create_template() {
		$data = array(
			'name' => 'Test Template',
			'prompt_template' => 'Write a blog post about {{topic}}',
			'title_prompt' => 'Generate a title for {{topic}}',
			'post_status' => 'draft',
			'post_category' => 1,
			'is_active' => 1,
		);
		
		$id = $this->repository->create($data);
		
		$this->assertIsInt($id);
		$this->assertGreaterThan(0, $id);
	}
	
	public function test_get_by_id() {
		$data = array(
			'name' => 'Test Template 2',
			'prompt_template' => 'Write about {{topic}}',
			'post_status' => 'draft',
			'post_category' => 1,
		);
		
		$id = $this->repository->create($data);
		$template = $this->repository->get_by_id($id);
		
		$this->assertNotNull($template);
		$this->assertEquals('Test Template 2', $template->name);
		$this->assertEquals($id, $template->id);
	}
	
	public function test_get_all() {
		// Create some test templates
		$this->repository->create(array(
			'name' => 'Test Template Active',
			'prompt_template' => 'Test',
			'post_status' => 'draft',
			'post_category' => 1,
			'is_active' => 1,
		));
		
		$this->repository->create(array(
			'name' => 'Test Template Inactive',
			'prompt_template' => 'Test',
			'post_status' => 'draft',
			'post_category' => 1,
			'is_active' => 0,
		));
		
		$all_templates = $this->repository->get_all();
		$active_only = $this->repository->get_all(true);
		
		$this->assertGreaterThanOrEqual(2, count($all_templates));
		$this->assertGreaterThanOrEqual(1, count($active_only));
		
		// Verify all templates in active_only are active
		foreach ($active_only as $template) {
			if (strpos($template->name, 'Test') === 0) {
				$this->assertEquals(1, $template->is_active);
			}
		}
	}
	
	public function test_update_template() {
		$data = array(
			'name' => 'Test Template Update',
			'prompt_template' => 'Original',
			'post_status' => 'draft',
			'post_category' => 1,
		);
		
		$id = $this->repository->create($data);
		
		$result = $this->repository->update($id, array(
			'prompt_template' => 'Updated',
		));
		
		$this->assertTrue($result);
		
		$template = $this->repository->get_by_id($id);
		$this->assertEquals('Updated', $template->prompt_template);
	}
	
	public function test_delete_template() {
		$data = array(
			'name' => 'Test Template Delete',
			'prompt_template' => 'Test',
			'post_status' => 'draft',
			'post_category' => 1,
		);
		
		$id = $this->repository->create($data);
		$result = $this->repository->delete($id);
		
		$this->assertTrue($result);
		
		$template = $this->repository->get_by_id($id);
		$this->assertNull($template);
	}
}
