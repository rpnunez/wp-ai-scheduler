<?php
/**
 * Tests for AIPS_Import_Export_Repository.
 *
 * @package AI_Post_Scheduler
 */

class Test_AIPS_Import_Export_Repository extends WP_UnitTestCase {

	/**
	 * @var AIPS_Import_Export_Repository
	 */
	private $repository;

	public function setUp(): void {
		parent::setUp();
		$this->repository = AIPS_Import_Export_Repository::instance();
	}

	public function test_instance_and_table_mapping() {
		$this->assertInstanceOf(AIPS_Import_Export_Repository::class, $this->repository);
		$this->assertStringContainsString('aips_voices', (string) $this->repository->get_table_name('voices'));
		$this->assertStringContainsString('aips_templates', (string) $this->repository->get_table_name('templates'));
		$this->assertNull($this->repository->get_table_name('invalid_table_type'));
	}

	public function test_get_preset_usage_stats_empty() {
		$stats = $this->repository->get_preset_usage_stats(array(), array(), array());
		$this->assertSame(0, $stats['post_count']);
		$this->assertSame(0, $stats['schedules_run']);
	}

	public function test_insert_and_find_and_update_row() {
		if (!$this->repository->table_exists('voices')) {
			$this->markTestSkipped('Voices table does not exist in test DB.');
		}

		$voice_name = 'Repo Test Voice ' . wp_rand(1000, 9999);
		$voice_data = array(
			'name'                 => $voice_name,
			'title_prompt'         => 'Test Title Prompt',
			'content_instructions' => 'Test Content Instructions',
			'excerpt_instructions' => 'Test Excerpt Instructions',
			'is_active'            => 1,
			'created_at'           => time(),
			'updated_at'           => time(),
		);

		// 1. Insert
		$inserted_id = $this->repository->insert_row('voices', $voice_data);
		$this->assertNotFalse($inserted_id);
		$this->assertGreaterThan(0, $inserted_id);

		// 2. Find
		$found = $this->repository->find_existing('voices', 'name', $voice_name);
		$this->assertNotNull($found);
		$this->assertEquals($inserted_id, (int) $found->id);

		// 3. Update
		$updated = $this->repository->update_row('voices', $inserted_id, array('title_prompt' => 'Updated Prompt'));
		$this->assertTrue($updated);

		// 4. Export by ID
		$rows = $this->repository->export_rows_by_ids('voices', array($inserted_id));
		$this->assertCount(1, $rows);
		$this->assertSame('Updated Prompt', $rows[0]['title_prompt']);
	}
}
