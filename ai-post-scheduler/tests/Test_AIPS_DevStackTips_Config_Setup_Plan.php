<?php
/**
 * Tests for scripts/devstacktips-setup.php (ID-driven) — plan mode.
 *
 * @package AI_Post_Scheduler
 */

if (!defined('AIPS_DEVSTACKTIPS_CONFIG_SETUP_NO_RUN')) {
	define('AIPS_DEVSTACKTIPS_CONFIG_SETUP_NO_RUN', true);
}

require_once dirname(__DIR__) . '/scripts/devstacktips-setup.php';

class Test_AIPS_DevStackTips_Config_Setup_Plan extends WP_UnitTestCase {

	public function setUp(): void {
		parent::setUp();
		delete_option(AIPS_Config_Binding::OPTION);

		if (!taxonomy_exists('aips_source_group')) {
			register_taxonomy('aips_source_group', array(), array('public' => false));
		}

		// Start from a clean config slate. wp_tests can carry committed rows from
		// a prior run (e.g. the legacy setup script's apply tests), and this plan
		// resolves definitions by name — a leftover same-named object would be
		// resolved instead of the one a test creates. DELETE is transactional, so
		// this rolls back with the rest of the test.
		aips_test_clear_config_tables();
	}

	/**
	 * Find one plan entry by its stable key.
	 */
	private function entry(array $plan, $key) {
		foreach ($plan as $entry) {
			if ($entry['key'] === $key) {
				return $entry;
			}
		}
		return null;
	}

	public function test_absent_template_is_reported_not_updated() {
		$plan = (new AIPS_DevStackTips_Config_Setup(array()))->plan();

		$entry = $this->entry($plan, 'templates:security-guide');
		$this->assertNotNull($entry);
		$this->assertSame('absent', $entry['action']);
		$this->assertNull($entry['id']);
	}

	public function test_existing_template_plans_managed_field_updates() {
		$voice_id = (int) (new AIPS_Voices_Repository())->create(array('name' => 'Senior Backend Mentor', 'is_active' => 1));
		(new AIPS_Article_Structure_Repository())->create(array(
			'name'           => 'Security Best Practices',
			'description'    => 'x',
			'structure_data' => wp_json_encode(array('sections' => array(), 'prompt_template' => '')),
			'is_active'      => 1,
		));
		wp_insert_term('Security News', 'aips_source_group', array('slug' => 'security-news'));
		$group = get_term_by('slug', 'security-news', 'aips_source_group');

		$template_id = (int) (new AIPS_Template_Repository())->create(array(
			'name'                    => 'Security Guide',
			'prompt_template'         => 'About {{topic}}',
			'post_status'             => 'draft',
			'post_type'               => 'post',
			'generate_featured_image' => 0,
			'featured_image_source'   => 'ai_prompt',
			'image_prompt'            => '',
			'is_active'               => 1,
		));

		$plan  = (new AIPS_DevStackTips_Config_Setup(array()))->plan();
		$entry = $this->entry($plan, 'templates:security-guide');

		$this->assertNotNull($entry);
		$this->assertSame('update', $entry['action']);
		$this->assertSame($template_id, $entry['id']);

		$changes = $entry['changes'];
		$this->assertArrayHasKey('generate_slug', $changes);
		$this->assertSame(1, $changes['generate_slug']['new']);
		$this->assertArrayHasKey('voice_id', $changes);
		$this->assertSame($voice_id, $changes['voice_id']['new']);
		$this->assertArrayHasKey('title_prompt', $changes);
		$this->assertArrayHasKey('image_prompt', $changes); // live prompt was empty, so it is filled
		$this->assertArrayHasKey('source_group_ids', $changes);
		$this->assertSame(array((int) $group->term_id), array_map('intval', json_decode($changes['source_group_ids']['new'], true)));
		$this->assertEmpty($entry['warnings']);
	}

	public function test_template_without_voice_warns_but_still_plans() {
		(new AIPS_Template_Repository())->create(array(
			'name'                    => 'Security Guide',
			'prompt_template'         => 'About {{topic}}',
			'post_status'             => 'draft',
			'post_type'               => 'post',
			'generate_featured_image' => 0,
			'featured_image_source'   => 'ai_prompt',
			'image_prompt'            => 'Hand written prompt',
			'is_active'               => 1,
		));

		$plan  = (new AIPS_DevStackTips_Config_Setup(array()))->plan();
		$entry = $this->entry($plan, 'templates:security-guide');

		$this->assertSame('update', $entry['action']);
		$this->assertNotEmpty($entry['warnings']); // voice + source group missing
		// A hand-written image prompt is preserved (not in the change set).
		$this->assertArrayNotHasKey('image_prompt', $entry['changes']);
	}

	public function test_existing_author_plans_v2_policy_and_category() {
		$cat    = wp_insert_term('Security', 'category');
		$author = (int) (new AIPS_Authors_Repository())->create(array(
			'name'                  => 'Security Expert',
			'field_niche'           => 'security',
			'post_category'         => null,
			'image_prompt'          => '',
			'topic_auto_approval_mode' => 'manual',
			'is_active'             => 1,
		));

		$plan  = (new AIPS_DevStackTips_Config_Setup(array()))->plan();
		$entry = $this->entry($plan, 'authors:security-expert');

		$this->assertNotNull($entry);
		$this->assertSame('update', $entry['action']);
		$this->assertSame($author, $entry['id']);
		$this->assertSame('score', $entry['changes']['topic_auto_approval_mode']['new']);
		$this->assertSame(85, $entry['changes']['topic_auto_approval_min_score']['new']);
		$this->assertSame((int) $cat['term_id'], $entry['changes']['post_category']['new']);
	}

	public function test_settings_are_planned_when_different() {
		update_option('aips_max_post_images_limit', 99);

		$plan  = (new AIPS_DevStackTips_Config_Setup(array()))->plan();
		$entry = $this->entry($plan, 'aips_max_post_images_limit');

		$this->assertNotNull($entry);
		$this->assertSame('update', $entry['action']);
		$this->assertSame(3, $entry['changes']['aips_max_post_images_limit']['new']);
	}

	public function test_settings_noop_when_already_correct() {
		update_option('aips_max_post_images_limit', 3);

		$plan  = (new AIPS_DevStackTips_Config_Setup(array()))->plan();
		$entry = $this->entry($plan, 'aips_max_post_images_limit');

		$this->assertSame('noop', $entry['action']);
	}

	public function test_run_prints_plan_and_writes_nothing() {
		$before = get_option('aips_max_post_images_limit', '__unset__');

		ob_start();
		$ok = (new AIPS_DevStackTips_Config_Setup(array('plan')))->run();
		$output = (string) ob_get_clean();

		$this->assertTrue($ok);
		$this->assertStringContainsString('PLAN (dry run', $output);
		$this->assertSame($before, get_option('aips_max_post_images_limit', '__unset__'));
		$this->assertSame(array(), get_option(AIPS_Config_Binding::OPTION, array()));
	}
}
