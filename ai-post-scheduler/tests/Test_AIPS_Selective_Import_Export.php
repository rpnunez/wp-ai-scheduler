<?php
/**
 * Tests for AIPS_Selective_Exporter and AIPS_Selective_Importer.
 *
 * @package AI_Post_Scheduler
 */

class Test_AIPS_Selective_Import_Export extends WP_UnitTestCase {

	public function test_selective_export_data_structure_and_dependencies() {
		$voices_repo = AIPS_Voices_Repository::instance();
		$tpl_repo    = AIPS_Template_Repository::instance();
		$sch_repo    = AIPS_Schedule_Repository::instance();

		// Create a chain of dependencies: Schedule -> Template -> Voice
		$voice_id = $voices_repo->create(array(
			'name'                 => 'Export Test Voice',
			'title_prompt'         => 'Test title',
			'content_instructions' => 'Test instructions',
			'is_active'            => 1,
		));
		$this->assertNotFalse($voice_id);

		$tpl_id = $tpl_repo->create(array(
			'name'            => 'Export Test Template',
			'prompt_template' => 'Write about {{topic}}',
			'voice_id'        => $voice_id,
			'post_status'     => 'draft',
			'post_type'       => 'post',
		));
		$this->assertNotFalse($tpl_id);

		$sch_id = $sch_repo->create(array(
			'title'         => 'Export Test Schedule',
			'template_id'   => $tpl_id,
			'frequency'     => 'daily',
			'schedule_type' => 'post_generation',
		));
		$this->assertNotFalse($sch_id);

		$exporter = new AIPS_Selective_Exporter();

		// Export ONLY 'schedules'
		$package = $exporter->export_data(array('schedules'));

		$this->assertEquals('2.0', $package['schema_version']);
		$this->assertArrayHasKey('schedules', $package['entities']);
		// Automatic dependency resolution must have pulled in the referenced template and voice!
		$this->assertArrayHasKey('templates', $package['entities']);
		$this->assertArrayHasKey('voices', $package['entities']);

		$exported_sch_ids = wp_list_pluck($package['entities']['schedules'], 'id');
		$exported_tpl_ids = wp_list_pluck($package['entities']['templates'], 'id');
		$exported_voc_ids = wp_list_pluck($package['entities']['voices'], 'id');

		$this->assertContains((string) $sch_id, array_map('strval', $exported_sch_ids));
		$this->assertContains((string) $tpl_id, array_map('strval', $exported_tpl_ids));
		$this->assertContains((string) $voice_id, array_map('strval', $exported_voc_ids));

		// Check JSON export string
		$json = $exporter->export_json(array('schedules'));
		$this->assertJson($json);
	}

	public function test_selective_import_with_conflict_copy_and_id_remapping() {
		$voices_repo = AIPS_Voices_Repository::instance();
		$tpl_repo    = AIPS_Template_Repository::instance();

		$voice_id = $voices_repo->create(array(
			'name'                 => 'Original Remap Voice',
			'title_prompt'         => 'Title prompt',
			'content_instructions' => 'Content instructions',
			'is_active'            => 1,
		));

		$tpl_id = $tpl_repo->create(array(
			'name'            => 'Original Remap Template',
			'prompt_template' => 'Write about {{topic}}',
			'voice_id'        => $voice_id,
			'post_status'     => 'draft',
			'post_type'       => 'post',
		));

		$exporter = new AIPS_Selective_Exporter();
		$package  = $exporter->export_data(array('templates', 'voices'));

		$importer = new AIPS_Selective_Importer();
		$report   = $importer->import($package['entities'], array('templates', 'voices'), 'copy');

		$this->assertNotEmpty($report['imported']);

		// Verify new copies exist and the new template references the new voice ID
		$all_templates = $tpl_repo->get_all();
		$copied_template = null;
		foreach ($all_templates as $t) {
			if (strpos($t->name, 'Original Remap Template (Imported') !== false) {
				$copied_template = $t;
				break;
			}
		}

		$this->assertNotNull($copied_template, 'Copied template should exist with renamed title');
		$this->assertNotEquals($voice_id, $copied_template->voice_id, 'Foreign key voice_id should be remapped to the newly imported voice ID');
	}

	public function test_selective_import_with_conflict_skip() {
		$voices_repo = AIPS_Voices_Repository::instance();

		$voice_id = $voices_repo->create(array(
			'name'                 => 'Existing Skip Voice',
			'title_prompt'         => 'Title prompt',
			'content_instructions' => 'Content instructions',
			'is_active'            => 1,
		));

		$exporter = new AIPS_Selective_Exporter();
		$package  = $exporter->export_data(array('voices'));

		$importer = new AIPS_Selective_Importer();
		$report   = $importer->import($package['entities'], array('voices'), 'skip');

		$this->assertNotEmpty($report['skipped']);
	}
}
