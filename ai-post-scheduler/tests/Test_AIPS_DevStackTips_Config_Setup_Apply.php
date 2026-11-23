<?php
/**
 * Tests for scripts/devstacktips-setup.php (ID-driven) — apply mode.
 *
 * @package AI_Post_Scheduler
 */

if (!defined('AIPS_DEVSTACKTIPS_CONFIG_SETUP_NO_RUN')) {
	define('AIPS_DEVSTACKTIPS_CONFIG_SETUP_NO_RUN', true);
}

require_once dirname(__DIR__) . '/scripts/devstacktips-setup.php';

class Test_AIPS_DevStackTips_Config_Setup_Apply extends WP_UnitTestCase {

	/** @var string */
	private $backup_dir;

	public function setUp(): void {
		parent::setUp();
		delete_option(AIPS_Config_Binding::OPTION);
		aips_test_clear_config_tables();

		if (!taxonomy_exists('aips_source_group')) {
			register_taxonomy('aips_source_group', array(), array('public' => false));
		}

		$this->backup_dir = trailingslashit(sys_get_temp_dir()) . 'aips-id-apply-' . wp_generate_password(8, false, false);
	}

	public function tearDown(): void {
		foreach ((array) glob($this->backup_dir . '/*.json') as $file) {
			@unlink($file);
		}
		@rmdir($this->backup_dir);
		parent::tearDown();
	}

	/**
	 * @return array{0:bool,1:string}
	 */
	private function apply(array $extra = array()) {
		ob_start();
		$ok     = (new AIPS_DevStackTips_Config_Setup(array_merge(array('apply', 'backup-dir=' . $this->backup_dir), $extra)))->run();
		$output = (string) ob_get_clean();

		return array($ok, $output);
	}

	/**
	 * Create the live "Security Guide" template and its dependencies.
	 *
	 * @return int Template ID.
	 */
	private function seed_security_guide($image_prompt = '') {
		(new AIPS_Voices_Repository())->create(array('name' => 'Senior Backend Mentor', 'is_active' => 1));
		(new AIPS_Article_Structure_Repository())->create(array(
			'name'           => 'Security Best Practices',
			'description'    => 'x',
			'structure_data' => wp_json_encode(array('sections' => array(), 'prompt_template' => '')),
			'is_active'      => 1,
		));
		wp_insert_term('Security News', 'aips_source_group', array('slug' => 'security-news'));

		return (int) (new AIPS_Template_Repository())->create(array(
			'name'                    => 'Security Guide',
			'prompt_template'         => 'About {{topic}}',
			'post_status'             => 'draft',
			'post_type'               => 'post',
			'generate_featured_image' => 0,
			'generate_slug'           => 0,
			'featured_image_source'   => 'ai_prompt',
			'image_prompt'            => $image_prompt,
			'is_active'               => 1,
		));
	}

	public function test_apply_updates_template_fields_by_id() {
		$template_id = $this->seed_security_guide();
		$voice_id    = (int) $this->find_id('AIPS_Voices_Repository', 'Senior Backend Mentor');
		$group       = get_term_by('slug', 'security-news', 'aips_source_group');

		list($ok, $output) = $this->apply();
		$this->assertTrue($ok, $output);

		$t = (new AIPS_Template_Repository())->get_by_id($template_id);
		$this->assertSame(1, (int) $t->generate_slug);
		$this->assertNotSame('', (string) $t->slug_prompt);
		$this->assertSame(1, (int) $t->generate_featured_image);
		$this->assertSame('ai_prompt', $t->featured_image_source);
		$this->assertSame($voice_id, (int) $t->voice_id);
		$this->assertStringContainsString('{{topic}}', (string) $t->image_prompt);
		$this->assertSame(1, (int) $t->include_sources);
		$this->assertSame(array((int) $group->term_id), array_map('intval', json_decode($t->source_group_ids, true)));

		$this->assertNotEmpty(glob($this->backup_dir . '/devstacktips-id-backup-*.json'));
	}

	public function test_apply_is_idempotent() {
		$this->seed_security_guide();

		list($ok) = $this->apply();
		$this->assertTrue($ok);

		list($ok, $output) = $this->apply();
		$this->assertTrue($ok, $output);
		$this->assertStringContainsString('APPLIED: 0 change(s)', $output);
		$this->assertStringNotContainsString('[SET] template Security Guide', $output);
	}

	public function test_apply_preserves_hand_written_image_prompt() {
		$id = $this->seed_security_guide('Hand written image prompt');

		list($ok) = $this->apply();
		$this->assertTrue($ok);

		$this->assertSame('Hand written image prompt', (new AIPS_Template_Repository())->get_by_id($id)->image_prompt);
	}

	public function test_apply_backs_up_old_values() {
		$id = $this->seed_security_guide();

		$this->apply();

		$files  = glob($this->backup_dir . '/devstacktips-id-backup-*.json');
		$backup = json_decode((string) file_get_contents($files[0]), true);

		$this->assertSame('devstacktips-id-driven', $backup['profile']);
		$this->assertArrayHasKey('template', $backup['rows']);
		$row = $backup['rows']['template'][(string) $id];
		$this->assertArrayHasKey('generate_slug', $row);
		$this->assertArrayHasKey('voice_id', $row); // old value recorded (was null/0)
		$this->assertArrayHasKey('aips_max_post_images_limit', $backup['options']);
	}

	public function test_apply_persists_bindings() {
		$id = $this->seed_security_guide();

		$this->apply();

		$map = get_option(AIPS_Config_Binding::OPTION, array());
		$this->assertArrayHasKey('templates:security-guide', $map);
		$this->assertSame($id, (int) $map['templates:security-guide']);
	}

	public function test_apply_refuses_backup_dir_inside_wordpress() {
		$id = $this->seed_security_guide();

		ob_start();
		$ok = (new AIPS_DevStackTips_Config_Setup(array('apply', 'backup-dir=' . ABSPATH)))->run();
		$output = (string) ob_get_clean();

		$this->assertFalse($ok);
		$this->assertStringContainsString('Refusing to write backups inside the WordPress directory', $output);
		// Nothing was written.
		$this->assertSame(0, (int) (new AIPS_Template_Repository())->get_by_id($id)->generate_slug);
	}

	/**
	 * @return int
	 */
	private function find_id($repo_class, $name) {
		foreach ((new $repo_class())->get_all() as $row) {
			if ($row->name === $name) {
				return (int) $row->id;
			}
		}
		return 0;
	}
}
