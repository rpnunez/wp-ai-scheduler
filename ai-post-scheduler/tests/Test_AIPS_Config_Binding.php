<?php
/**
 * Tests for AIPS_Config_Binding.
 *
 * @package AI_Post_Scheduler
 */

class Test_AIPS_Config_Binding extends WP_UnitTestCase {

	/** @var AIPS_Config_Binding */
	private $binding;

	public function setUp(): void {
		parent::setUp();
		delete_option(AIPS_Config_Binding::OPTION);
		$this->binding = new AIPS_Config_Binding(new AIPS_Config_Reader($GLOBALS['wpdb']));
	}

	public function test_make_key_slugifies_and_rejects_empty() {
		$this->assertSame('templates:beginner-how-to', AIPS_Config_Binding::make_key('templates', 'Beginner How-To'));
		$this->assertNull(AIPS_Config_Binding::make_key('templates', '   '));
	}

	public function test_discover_binds_by_name_and_flags_collisions() {
		$repo = new AIPS_Voices_Repository();
		$a = (int) $repo->create(array('name' => 'Unique Voice', 'is_active' => 1));
		$b = (int) $repo->create(array('name' => 'Dupe Voice', 'is_active' => 1));
		$c = (int) $repo->create(array('name' => 'Dupe Voice', 'is_active' => 1));

		$result = $this->binding->discover();

		$this->assertArrayHasKey('voices:unique-voice', $result['bindings']);
		$this->assertSame($a, $result['bindings']['voices:unique-voice']);

		// Collision: both "Dupe Voice" rows slug to the same key.
		$this->assertArrayHasKey('voices:dupe-voice', $result['collisions']);
		$this->assertContains($b, $result['collisions']['voices:dupe-voice']);
		$this->assertContains($c, $result['collisions']['voices:dupe-voice']);
	}

	public function test_sync_persists_and_resolves() {
		$id = (int) (new AIPS_Voices_Repository())->create(array('name' => 'Persisted Voice', 'is_active' => 1));

		$report = $this->binding->sync();

		$this->assertArrayHasKey('voices:persisted-voice', $report['added']);
		$this->assertSame($id, $this->binding->resolve('voices:persisted-voice'));

		// Reloaded instance sees the same persisted binding.
		$fresh = new AIPS_Config_Binding(new AIPS_Config_Reader($GLOBALS['wpdb']));
		$this->assertSame($id, $fresh->resolve('voices:persisted-voice'));
	}

	public function test_sync_never_overwrites_existing_key_so_rename_survives() {
		$repo = new AIPS_Voices_Repository();
		$id   = (int) $repo->create(array('name' => 'Original Name', 'is_active' => 1));

		$this->binding->sync();
		$this->assertSame($id, $this->binding->resolve('voices:original-name'));

		// Rename the object on the "site".
		$repo->update($id, array('name' => 'Renamed Voice'));

		$report = $this->binding->sync();

		// The original key still resolves to the same ID (binding survived the rename).
		$this->assertSame($id, $this->binding->resolve('voices:original-name'));
		// The new name is additionally discovered, pointing at the same ID.
		$this->assertSame($id, $this->binding->resolve('voices:renamed-voice'));
		$this->assertArrayHasKey('voices:renamed-voice', $report['added']);
	}

	public function test_sync_reports_stale_binding_when_object_deleted() {
		$repo = new AIPS_Voices_Repository();
		$id   = (int) $repo->create(array('name' => 'Doomed Voice', 'is_active' => 1));

		$this->binding->sync();
		$repo->delete($id);

		$report = $this->binding->sync();

		$this->assertContains('voices:doomed-voice', $report['stale']);
		// Still resolvable from the registry (we report, not auto-prune).
		$this->assertSame($id, $this->binding->resolve('voices:doomed-voice'));
	}

	public function test_stamp_adds_single_binding() {
		$this->binding->stamp('templates:manual', 777);
		$this->assertSame(777, $this->binding->resolve('templates:manual'));
	}

	public function test_resolve_unknown_key_is_null() {
		$this->assertNull($this->binding->resolve('templates:nope'));
	}
}
