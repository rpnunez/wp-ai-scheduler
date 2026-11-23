<?php
/**
 * Tests for scripts/devstacktips-setup.php (ID-driven) — Slice 5 seeding.
 *
 * @package AI_Post_Scheduler
 */

if (!defined('AIPS_DEVSTACKTIPS_CONFIG_SETUP_NO_RUN')) {
	define('AIPS_DEVSTACKTIPS_CONFIG_SETUP_NO_RUN', true);
}

require_once dirname(__DIR__) . '/scripts/devstacktips-setup.php';

class Test_AIPS_DevStackTips_Config_Setup_Seed extends WP_UnitTestCase {

	/** @var string */
	private $backup_dir;

	public function setUp(): void {
		parent::setUp();
		delete_option(AIPS_Config_Binding::OPTION);
		aips_test_clear_config_tables();

		if (!taxonomy_exists('aips_source_group')) {
			register_taxonomy('aips_source_group', array(), array('public' => false));
		}

		$this->backup_dir = trailingslashit(sys_get_temp_dir()) . 'aips-id-seed-' . wp_generate_password(8, false, false);
	}

	public function tearDown(): void {
		foreach ((array) glob($this->backup_dir . '/*.json') as $file) {
			@unlink($file);
		}
		@rmdir($this->backup_dir);
		parent::tearDown();
	}

	/**
	 * Find one plan entry by its stable key.
	 *
	 * @param array  $plan
	 * @param string $key
	 * @return array|null
	 */
	private function entry(array $plan, $key) {
		foreach ($plan as $entry) {
			if ($entry['key'] === $key) {
				return $entry;
			}
		}
		return null;
	}

	/**
	 * Run apply and capture output.
	 *
	 * @param array $extra
	 * @return array{0:bool,1:string}
	 */
	private function apply(array $extra = array()) {
		ob_start();
		$ok     = (new AIPS_DevStackTips_Config_Setup(array_merge(array('apply', 'backup-dir=' . $this->backup_dir), $extra)))->run();
		$output = (string) ob_get_clean();

		return array($ok, $output);
	}

	public function test_plan_marks_missing_v3_source_groups_and_sources_for_creation() {
		$plan = (new AIPS_DevStackTips_Config_Setup(array()))->plan();

		$group = $this->entry($plan, 'source_groups:backend-architecture');
		$this->assertNotNull($group);
		$this->assertSame('create', $group['action']);
		$this->assertNull($group['id']);
		$this->assertSame('backend-architecture', $group['changes']['slug']['new']);

		$source = $this->entry($plan, 'sources:martin-fowler');
		$this->assertNotNull($source);
		$this->assertSame('create', $source['action']);
		$this->assertNull($source['id']);
		$this->assertSame('https://martinfowler.com/feed.atom', $source['changes']['url']['new']);
		$this->assertSame('backend-architecture', $source['changes']['group_slug']['new']);
	}

	public function test_plan_marks_existing_source_groups_and_sources_as_noop() {
		$term = wp_insert_term('Backend & Architecture', 'aips_source_group', array('slug' => 'backend-architecture'));
		$this->assertIsArray($term);

		$repo = new AIPS_Sources_Repository();
		$id   = $repo->create(array(
			'url'       => 'https://martinfowler.com/feed.atom',
			'label'     => 'Martin Fowler',
			'is_active' => 1,
		));
		$this->assertNotFalse($id);

		$plan = (new AIPS_DevStackTips_Config_Setup(array()))->plan();

		$group = $this->entry($plan, 'source_groups:backend-architecture');
		$this->assertNotNull($group);
		$this->assertSame('noop', $group['action']);
		$this->assertSame((int) $term['term_id'], $group['id']);

		$source = $this->entry($plan, 'sources:martin-fowler');
		$this->assertNotNull($source);
		$this->assertSame('noop', $source['action']);
		$this->assertSame((int) $id, $source['id']);
	}

	public function test_apply_seeds_missing_source_groups_and_sources() {
		global $wpdb;

		list($ok, $output) = $this->apply();
		$this->assertTrue($ok, $output);

		// Source groups created
		$backend_group = get_term_by('slug', 'backend-architecture', 'aips_source_group');
		$this->assertNotNull($backend_group);
		$this->assertSame('Backend & Architecture', $backend_group->name);

		$ai_group = get_term_by('slug', 'ai-dev-tooling', 'aips_source_group');
		$this->assertNotNull($ai_group);
		$this->assertSame('AI & Dev Tooling', $ai_group->name);

		// All 8 sources created
		$sources_repo = new AIPS_Sources_Repository();
		$all_sources  = $sources_repo->get_all();
		$this->assertCount(8, $all_sources);

		$expected = AIPS_DevStackTips_Definitions::sources();
		$by_name  = array();
		foreach ($all_sources as $s) {
			$by_name[$s->label] = $s;
		}

		foreach ($expected as $def) {
			$this->assertArrayHasKey($def['name'], $by_name);
			$row = $by_name[$def['name']];
			$this->assertSame($def['url'], $row->url);
			$this->assertSame(1, (int) $row->is_active);

			// Term mapping
			$target_group_term_id = ('backend-architecture' === $def['group_slug']) ? (int) $backend_group->term_id : (int) $ai_group->term_id;
			$terms = $wpdb->get_col($wpdb->prepare("SELECT term_id FROM {$wpdb->prefix}aips_source_group_terms WHERE source_id = %d", (int) $row->id));
			$this->assertContains((string) $target_group_term_id, array_map('strval', $terms));
		}

		// Bindings persisted
		$map = get_option(AIPS_Config_Binding::OPTION, array());
		$this->assertArrayHasKey('source_groups:backend-architecture', $map);
		$this->assertSame((int) $backend_group->term_id, (int) $map['source_groups:backend-architecture']);
		$this->assertArrayHasKey('sources:martin-fowler', $map);
		$this->assertSame((int) $by_name['Martin Fowler']->id, (int) $map['sources:martin-fowler']);

		// Backup records created objects
		$files  = glob($this->backup_dir . '/devstacktips-id-backup-*.json');
		$this->assertNotEmpty($files);
		$backup = json_decode((string) file_get_contents($files[0]), true);
		$this->assertArrayHasKey('created', $backup);
		$this->assertCount(10, $backup['created']); // 2 groups + 8 sources
	}

	public function test_apply_seeding_is_idempotent() {
		list($ok, $output1) = $this->apply();
		$this->assertTrue($ok, $output1);

		list($ok, $output2) = $this->apply();
		$this->assertTrue($ok, $output2);
		$this->assertStringContainsString('APPLIED: 0 change(s)', $output2);
		$this->assertStringNotContainsString('[CREATE]', $output2);

		// Source count unchanged
		$this->assertCount(8, (new AIPS_Sources_Repository())->get_all());
	}

	public function test_apply_resolves_templates_referencing_v3_source_groups() {
		(new AIPS_Voices_Repository())->create(array('name' => 'Hands-On Tutorial Coach', 'is_active' => 1));
		(new AIPS_Article_Structure_Repository())->create(array(
			'name'           => 'Tool / Workflow Explainer',
			'description'    => 'x',
			'structure_data' => wp_json_encode(array('sections' => array(), 'prompt_template' => '')),
			'is_active'      => 1,
		));

		$template_id = (int) (new AIPS_Template_Repository())->create(array(
			'name'                    => 'Developer Tooling',
			'prompt_template'         => 'About {{topic}}',
			'post_status'             => 'draft',
			'post_type'               => 'post',
			'generate_featured_image' => 0,
			'generate_slug'           => 0,
			'featured_image_source'   => 'ai_prompt',
			'image_prompt'            => '',
			'is_active'               => 1,
		));

		list($ok, $output) = $this->apply();
		$this->assertTrue($ok, $output);

		$ai_group = get_term_by('slug', 'ai-dev-tooling', 'aips_source_group');
		$this->assertNotNull($ai_group);

		$t = (new AIPS_Template_Repository())->get_by_id($template_id);
		$this->assertSame(1, (int) $t->include_sources);
		$this->assertSame(array((int) $ai_group->term_id), array_map('intval', json_decode($t->source_group_ids, true)));

		// Should not have warned that the source group was missing
		$this->assertStringNotContainsString('source group not present yet (source_groups:ai-dev-tooling)', $output);
	}
}
