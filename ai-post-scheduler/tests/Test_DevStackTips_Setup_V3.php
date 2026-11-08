<?php
/**
 * Tests for scripts/setup-devstacktips-content.php (v3).
 *
 * @package AI_Post_Scheduler
 */

if (!defined('AIPS_DEVSTACKTIPS_SETUP_NO_RUN')) {
	define('AIPS_DEVSTACKTIPS_SETUP_NO_RUN', true);
}

require_once dirname(__DIR__) . '/scripts/setup-devstacktips-content.php';

class Test_DevStackTips_Setup_V3 extends WP_UnitTestCase {

	/**
	 * @var string
	 */
	private $backup_dir;

	public function setUp(): void {
		parent::setUp();

		if (!taxonomy_exists('aips_source_group')) {
			register_taxonomy('aips_source_group', array(), array('public' => false));
		}

		$this->backup_dir = trailingslashit(sys_get_temp_dir()) . 'aips-dst-test-' . wp_generate_password(8, false, false);
	}

	public function tearDown(): void {
		foreach ((array) glob($this->backup_dir . '/*.json') as $file) {
			@unlink($file);
		}
		@rmdir($this->backup_dir);

		parent::tearDown();
	}

	/**
	 * Run the setup with captured output.
	 *
	 * @param array $args
	 * @return array{0:bool,1:string}
	 */
	private function run_setup(array $args) {
		ob_start();
		$ok = (new AIPS_DevStackTips_Setup($args))->run();
		$output = (string) ob_get_clean();

		return array($ok, $output);
	}

	/**
	 * Apply into the test backup directory.
	 *
	 * @param array $extra
	 * @return array{0:bool,1:string}
	 */
	private function apply(array $extra = array()) {
		return $this->run_setup(array_merge(array('apply', 'backup-dir=' . $this->backup_dir), $extra));
	}

	/**
	 * @param string $name
	 * @return object|null
	 */
	private function find_template($name) {
		foreach ((new AIPS_Template_Repository())->get_all() as $row) {
			if ($row->name === $name) {
				return (new AIPS_Template_Repository())->get_by_id((int) $row->id);
			}
		}

		return null;
	}

	/**
	 * @param string $name
	 * @return object|null
	 */
	private function find_author($name) {
		foreach ((new AIPS_Authors_Repository())->get_all() as $row) {
			if ($row->name === $name) {
				return (new AIPS_Authors_Repository())->get_by_id((int) $row->id);
			}
		}

		return null;
	}

	/**
	 * @param string $repo_class
	 * @param string $name
	 * @return int
	 */
	private function find_id_by_name($repo_class, $name) {
		foreach ((new $repo_class())->get_all() as $row) {
			if ($row->name === $name) {
				return (int) $row->id;
			}
		}

		return 0;
	}

	public function test_dry_run_writes_nothing() {
		$templates_before = count((new AIPS_Template_Repository())->get_all());
		$authors_before = count((new AIPS_Authors_Repository())->get_all());
		$limit_before = get_option('aips_max_post_images_limit', false);

		list($ok, $output) = $this->run_setup(array());

		$this->assertTrue($ok, $output);
		$this->assertStringContainsString('DRY RUN', $output);
		$this->assertStringContainsString('[PLAN] Would create template: Security Guide', $output);
		$this->assertSame($templates_before, count((new AIPS_Template_Repository())->get_all()));
		$this->assertSame($authors_before, count((new AIPS_Authors_Repository())->get_all()));
		$this->assertSame($limit_before, get_option('aips_max_post_images_limit', false));
		$this->assertFalse(is_dir($this->backup_dir));
	}

	public function test_apply_creates_and_configures_v3_fields() {
		list($ok, $output) = $this->apply();

		$this->assertTrue($ok, $output);

		$template = $this->find_template('Security Guide');
		$this->assertNotNull($template);
		$this->assertSame(1, (int) $template->generate_slug);
		$this->assertNotSame('', (string) $template->slug_prompt);
		$this->assertSame(1, (int) $template->generate_featured_image);
		$this->assertSame('ai_prompt', $template->featured_image_source);
		$this->assertSame($this->find_id_by_name('AIPS_Voices_Repository', 'Senior Backend Mentor'), (int) $template->voice_id);

		$group = get_term_by('slug', 'security-news', 'aips_source_group');
		$this->assertNotFalse($group);
		$this->assertSame(1, (int) $template->include_sources);
		$this->assertSame(array((int) $group->term_id), array_map('intval', json_decode($template->source_group_ids, true)));

		$structure_id = $this->find_id_by_name('AIPS_Article_Structure_Repository', 'Security Best Practices');
		$schedule_structure = 0;
		foreach ((new AIPS_Schedule_Repository())->get_all(false) as $schedule) {
			if ('Core Wednesday - Security First' === $schedule->title) {
				$schedule_structure = (int) (new AIPS_Schedule_Repository())->get_by_id((int) $schedule->id)->article_structure_id;
			}
		}
		$this->assertSame($structure_id, $schedule_structure);

		$author = $this->find_author('Security Expert');
		$this->assertNotNull($author);
		$this->assertSame('score', $author->topic_auto_approval_mode);
		$this->assertSame(85, (int) $author->topic_auto_approval_min_score);
		$this->assertSame('daily', $author->post_generation_frequency);
		$this->assertSame(1, (int) $author->scheduled_post_generation_quantity);
		$this->assertSame(1, (int) $author->generate_slug);
		$this->assertSame(1, (int) $author->generate_featured_image);

		$architecture = $this->find_template('Architecture Pattern Deep Dive');
		$images = (new AIPS_Post_Images_Repository())->get_content_images_for_entity('template', (int) $architecture->id);
		$this->assertCount(2, $images);
		$this->assertSame('after_section_2', $images[0]->placement);
		$this->assertSame('ai_prompt', $images[0]->source);

		$this->assertSame(3, (int) get_option('aips_max_post_images_limit'));
		$this->assertSame(array('ai_prompt', 'media_library'), get_option('aips_allowed_image_sources'));

		$this->assertNotEmpty(glob($this->backup_dir . '/devstacktips-v3-backup-*.json'));
	}

	public function test_second_apply_is_idempotent_and_keeps_hand_edits() {
		list($ok) = $this->apply();
		$this->assertTrue($ok);

		$template = $this->find_template('Security Guide');
		(new AIPS_Template_Repository())->update((int) $template->id, array(
			'prompt_template' => 'Hand-edited prompt about {{topic}}.',
			'image_prompt'    => 'Hand-written image prompt',
			'post_quantity'   => 4,
		));

		$templates_before = count((new AIPS_Template_Repository())->get_all());

		list($ok, $output) = $this->apply();

		$this->assertTrue($ok, $output);
		$this->assertSame($templates_before, count((new AIPS_Template_Repository())->get_all()));
		$this->assertStringNotContainsString('[SET] Template Security Guide', $output);
		$this->assertStringContainsString('[OK] Template Security Guide: already up to date', $output);

		$after = $this->find_template('Security Guide');
		$this->assertSame('Hand-edited prompt about {{topic}}.', $after->prompt_template);
		$this->assertSame('Hand-written image prompt', $after->image_prompt);
		$this->assertSame(4, (int) $after->post_quantity);
	}

	public function test_drafts_flag_sets_template_status() {
		list($ok) = $this->apply();
		$this->assertTrue($ok);

		$template = $this->find_template('Framework Comparison');
		(new AIPS_Template_Repository())->update((int) $template->id, array('post_status' => 'publish'));

		list($ok, $output) = $this->apply(array('drafts'));
		$this->assertTrue($ok, $output);
		$this->assertSame('draft', $this->find_template('Framework Comparison')->post_status);

		list($ok, $output) = $this->apply(array('publish'));
		$this->assertTrue($ok, $output);
		$this->assertSame('publish', $this->find_template('Framework Comparison')->post_status);
	}

	public function test_rollback_restores_fields_and_deletes_created_objects() {
		$limit_before = get_option('aips_max_post_images_limit', false);

		// A pre-existing template that v3 only modifies.
		$campaign_id = (new AIPS_Campaigns_Repository())->create_campaign(array(
			'name'          => 'Developer Tooling',
			'content_goal'  => 'Existing campaign',
			'campaign_mode' => 'template',
			'is_active'     => 1,
			'is_archived'   => 0,
		));
		$existing_id = (new AIPS_Template_Repository())->create(array(
			'name'                    => 'Developer Tooling',
			'prompt_template'         => 'Existing prompt {{topic}}',
			'title_prompt'            => 'Existing title prompt',
			'post_status'             => 'publish',
			'post_type'               => 'post',
			'generate_featured_image' => 0,
			'featured_image_source'   => 'unsplash',
			'campaign_id'             => $campaign_id,
			'is_active'               => 1,
		));

		list($ok, $output) = $this->apply();
		$this->assertTrue($ok, $output);
		$this->assertSame('ai_prompt', (new AIPS_Template_Repository())->get_by_id($existing_id)->featured_image_source);

		$this->assertSame(1, preg_match('/Backup file: (\S+\.json)/', $output, $matches), $output);

		list($ok, $output) = $this->run_setup(array('rollback', $matches[1]));
		$this->assertTrue($ok, $output);

		$restored = (new AIPS_Template_Repository())->get_by_id($existing_id);
		$this->assertNotNull($restored);
		$this->assertSame('unsplash', $restored->featured_image_source);
		$this->assertSame(0, (int) $restored->generate_featured_image);
		$this->assertSame(0, (int) $restored->generate_slug);
		$this->assertSame('Existing title prompt', $restored->title_prompt);
		$this->assertEmpty($restored->voice_id);

		$this->assertNull($this->find_template('Security Guide'));
		$this->assertFalse(get_term_by('slug', 'backend-architecture', 'aips_source_group'));
		$this->assertSame($limit_before, get_option('aips_max_post_images_limit', false));
	}

	public function test_apply_refuses_backup_dir_inside_wordpress() {
		$templates_before = count((new AIPS_Template_Repository())->get_all());

		list($ok, $output) = $this->run_setup(array('apply', 'backup-dir=' . ABSPATH));

		$this->assertFalse($ok);
		$this->assertMatchesRegularExpression('/Refusing to write backups inside the WordPress directory|Backup directory is not writable/', $output);
		$this->assertSame($templates_before, count((new AIPS_Template_Repository())->get_all()));
	}
}
