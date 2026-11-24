<?php
/**
 * Tests for scripts/devstacktips-setup.php (ID-driven) — full seed parity and
 * the "Hosting & Server Management" template & daily schedule.
 *
 * @package AI_Post_Scheduler
 */

if (!defined('AIPS_DEVSTACKTIPS_CONFIG_SETUP_NO_RUN')) {
	define('AIPS_DEVSTACKTIPS_CONFIG_SETUP_NO_RUN', true);
}

require_once dirname(__DIR__) . '/scripts/devstacktips-setup.php';

class Test_DevStackTips_Setup_Id_Driven extends WP_UnitTestCase {

	/** @var string */
	private $backup_dir;

	public function setUp(): void {
		parent::setUp();
		delete_option(AIPS_Config_Binding::OPTION);
		aips_test_clear_config_tables();

		if (!taxonomy_exists('aips_source_group')) {
			register_taxonomy('aips_source_group', array(), array('public' => false));
		}

		$this->backup_dir = trailingslashit(sys_get_temp_dir()) . 'aips-id-parity-' . wp_generate_password(8, false, false);
	}

	public function tearDown(): void {
		foreach ((array) glob($this->backup_dir . '/*.json') as $file) {
			@unlink($file);
		}
		@rmdir($this->backup_dir);
		parent::tearDown();
	}

	/**
	 * @param array $extra
	 * @return array{0:bool,1:string}
	 */
	private function apply(array $extra = array()) {
		ob_start();
		$ok     = (new AIPS_DevStackTips_Config_Setup(array_merge(array('apply', 'backup-dir=' . $this->backup_dir), $extra)))->run();
		$output = (string) ob_get_clean();

		return array($ok, $output);
	}

	/**
	 * @param string $name
	 * @return object|null
	 */
	private function find_template($name) {
		$repo = new AIPS_Template_Repository();
		foreach ($repo->get_all() as $row) {
			if ($row->name === $name) {
				return $repo->get_by_id((int) $row->id);
			}
		}
		return null;
	}

	/**
	 * @param string $title
	 * @return object|null
	 */
	private function find_schedule($title) {
		$repo = new AIPS_Schedule_Repository();
		foreach ($repo->get_all(false) as $row) {
			if ($row->title === $title) {
				return $repo->get_by_id((int) $row->id);
			}
		}
		return null;
	}

	public function test_bare_install_apply_seeds_all_types_and_hosting_template() {
		list($ok, $output) = $this->apply();
		$this->assertTrue($ok, $output);

		// Categories (7)
		foreach (AIPS_DevStackTips_Definitions::categories() as $cat) {
			$term = get_term_by('slug', $cat['slug'], 'category');
			$this->assertNotFalse($term, 'Missing category: ' . $cat['slug']);
		}

		// Voices (8), Sections (34), Structures (11), Post Slices (4)
		$this->assertCount(8, (new AIPS_Voices_Repository())->get_all());
		$this->assertCount(11, (new AIPS_Article_Structure_Repository())->get_all());
		$this->assertCount(4, (new AIPS_Post_Slices_Repository())->get_all(false));
		$section_repo = new AIPS_Prompt_Section_Repository();
		foreach (AIPS_DevStackTips_Definitions::sections() as $section) {
			$this->assertNotNull($section_repo->get_by_key($section['section_key']), 'Missing section: ' . $section['section_key']);
		}

		// Source groups (4) and Sources (13)
		foreach (AIPS_DevStackTips_Definitions::source_groups() as $group) {
			$this->assertNotFalse(get_term_by('slug', $group['slug'], 'aips_source_group'), 'Missing source group: ' . $group['slug']);
		}
		$this->assertCount(13, (new AIPS_Sources_Repository())->get_all());

		// Campaigns (12), Templates (12), Authors (8), Schedules (12)
		$this->assertCount(12, (new AIPS_Campaigns_Repository())->get_all());
		$this->assertCount(12, (new AIPS_Template_Repository())->get_all());
		$this->assertCount(8, (new AIPS_Authors_Repository())->get_all());
		$this->assertCount(12, (new AIPS_Schedule_Repository())->get_all(false));

		// Bindings stamped
		$map = get_option(AIPS_Config_Binding::OPTION, array());
		$this->assertArrayHasKey('campaigns:hosting-infrastructure', $map);
		$this->assertArrayHasKey('templates:hosting-server-management', $map);
		$this->assertArrayHasKey('schedules:hosting-server-management-daily-2pm', $map);
		$this->assertArrayHasKey('authors:security-expert', $map);

		// Hosting & Server Management template assertions
		$hosting = $this->find_template('Hosting & Server Management');
		$this->assertNotNull($hosting);
		$this->assertSame(1, (int) $hosting->post_quantity);
		$this->assertSame(1, (int) $hosting->generate_featured_image);
		$this->assertSame('ai_prompt', $hosting->featured_image_source);
		$this->assertSame(0, (int) $hosting->include_sources);
		$this->assertSame('hosting,web-hosting,vps,lamp,server-management', $hosting->post_tags);
		$this->assertStringContainsString('LAMP stack', (string) $hosting->prompt_template);
		$this->assertStringContainsString('server rack', (string) $hosting->image_prompt);
		$this->assertStringContainsString('configure-apache-virtual-hosts', (string) $hosting->slug_prompt);

		// Seed settings populated when missing
		$this->assertNotEmpty(get_option('aips_default_article_structure_id'));
		$this->assertNotEmpty(get_option('aips_default_category'));
		$this->assertSame(3, (int) get_option('aips_max_post_images_limit'));
	}

	public function test_hosting_schedule_next_run_is_next_1400_in_site_timezone() {
		list($ok, $output) = $this->apply();
		$this->assertTrue($ok, $output);

		$schedule = $this->find_schedule('Hosting & Server Management - Daily 2PM');
		$this->assertNotNull($schedule);
		$this->assertSame('daily', $schedule->frequency);
		$this->assertSame(1, (int) $schedule->is_active);
		$this->assertSame('active', $schedule->status);

		$tz       = wp_timezone();
		$next_run = is_numeric($schedule->next_run)
			? (new DateTimeImmutable('@' . (int) $schedule->next_run))->setTimezone($tz)
			: new DateTimeImmutable((string) $schedule->next_run, $tz);
		$now      = new DateTimeImmutable('now', $tz);

		$this->assertSame('14:00', $next_run->format('H:i'));
		$this->assertGreaterThan($now->getTimestamp(), $next_run->getTimestamp());
		$this->assertLessThanOrEqual(86400, $next_run->getTimestamp() - $now->getTimestamp());
	}

	public function test_second_apply_is_idempotent_and_plan_shows_no_creates() {
		list($ok) = $this->apply();
		$this->assertTrue($ok);

		list($ok2, $output2) = $this->apply();
		$this->assertTrue($ok2, $output2);
		$this->assertStringContainsString('APPLIED: 0 change(s)', $output2);
		$this->assertStringNotContainsString('[CREATE]', $output2);

		$plan = (new AIPS_DevStackTips_Config_Setup(array('plan')))->plan();
		foreach ($plan as $entry) {
			$this->assertNotSame('create', $entry['action'], 'Unexpected create in plan for ' . $entry['key']);
			$this->assertNotSame('update', $entry['action'], 'Unexpected update in plan for ' . $entry['key']);
		}
	}

	public function test_rename_survival_resolves_by_binding_without_duplicate() {
		list($ok) = $this->apply();
		$this->assertTrue($ok);

		$hosting = $this->find_template('Hosting & Server Management');
		$this->assertNotNull($hosting);

		$repo = new AIPS_Template_Repository();
		$repo->update((int) $hosting->id, array('name' => 'Hosting & Server Management (Renamed)'));

		list($ok2, $output2) = $this->apply();
		$this->assertTrue($ok2, $output2);

		// Still 12 templates — no duplicate created.
		$this->assertCount(12, $repo->get_all());
		$reloaded = $repo->get_by_id((int) $hosting->id);
		$this->assertSame('Hosting & Server Management (Renamed)', $reloaded->name);
	}
}
