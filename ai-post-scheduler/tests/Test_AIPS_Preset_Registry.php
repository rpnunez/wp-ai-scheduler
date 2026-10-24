<?php
/**
 * Tests for AIPS_Preset_Registry and AIPS_Preset_Installer.
 *
 * @package AI_Post_Scheduler
 */

class Test_AIPS_Preset_Registry extends WP_UnitTestCase {

	public function test_get_presets_returns_expected_niches() {
		$presets = AIPS_Preset_Registry::get_presets();
		$this->assertCount(4, $presets);

		$expected_keys = array('news_updates', 'how_to_guides', 'product_reviews', 'thought_leadership');
		foreach ($expected_keys as $key) {
			$this->assertArrayHasKey($key, $presets);
			$preset = $presets[$key];
			$this->assertNotEmpty($preset['name']);
			$this->assertNotEmpty($preset['voice']);
			$this->assertNotEmpty($preset['sections']);
			$this->assertNotEmpty($preset['structure']);
			$this->assertNotEmpty($preset['author']);
			$this->assertNotEmpty($preset['template']);
			$this->assertNotEmpty($preset['schedule']);
		}
	}

	public function test_install_and_remove_preset_lifecycle() {
		$installer = new AIPS_Preset_Installer();

		// Clean state
		delete_option(AIPS_Preset_Installer::INSTALLED_PRESETS_OPTION);
		delete_option(AIPS_Preset_Installer::ENTITIES_OPTION_PREFIX . 'how_to_guides');

		$this->assertFalse($installer->is_installed('how_to_guides'));

		// Install
		$result = $installer->install('how_to_guides', true);
		$this->assertIsArray($result);
		$this->assertNotEmpty($result['voice_ids']);
		$this->assertNotEmpty($result['structure_ids']);
		$this->assertNotEmpty($result['author_ids']);
		$this->assertNotEmpty($result['template_ids']);
		$this->assertNotEmpty($result['schedule_ids']);
		$this->assertNotEmpty($result['topic_ids']);

		$this->assertTrue($installer->is_installed('how_to_guides'));

		// Second install without remove should fail
		$fail = $installer->install('how_to_guides');
		$this->assertWPError($fail);
		$this->assertEquals('already_installed', $fail->get_error_code());

		// Usage stats
		$stats = $installer->get_usage_stats('how_to_guides');
		$this->assertEquals(0, $stats['post_count']);
		$this->assertEquals(0, $stats['schedules_run']);

		// Clean remove
		$removed = $installer->remove('how_to_guides');
		$this->assertTrue($removed);
		$this->assertFalse($installer->is_installed('how_to_guides'));
	}

	public function test_remove_preset_blocked_when_posts_generated() {
		global $wpdb;

		$installer = new AIPS_Preset_Installer();
		delete_option(AIPS_Preset_Installer::INSTALLED_PRESETS_OPTION);
		delete_option(AIPS_Preset_Installer::ENTITIES_OPTION_PREFIX . 'news_updates');

		$result = $installer->install('news_updates');
		$this->assertIsArray($result);
		$template_id = $result['template_ids'][0];

		// Simulate a generated post in aips_history
		$history_table = $wpdb->prefix . 'aips_history';
		$wp_post_id = self::factory()->post->create(array('post_title' => 'Generated News Post'));

		$wpdb->insert($history_table, array(
			'template_id'     => $template_id,
			'post_id'         => $wp_post_id,
			'status'          => 'completed',
			'creation_method' => 'manual',
			'generated_title' => 'Generated News Post',
			'created_at'      => AIPS_DateTime::now()->timestamp(),
		));

		$usage = $installer->get_usage_stats('news_updates');
		$this->assertGreaterThan(0, $usage['post_count']);

		// Attempt removal without force - MUST fail with WP_Error
		$attempt = $installer->remove('news_updates', false);
		$this->assertWPError($attempt);
		$this->assertEquals('preset_in_use', $attempt->get_error_code());
		$this->assertTrue($installer->is_installed('news_updates'));

		// Force removal should succeed
		$forced = $installer->remove('news_updates', true);
		$this->assertTrue($forced);
		$this->assertFalse($installer->is_installed('news_updates'));
	}
}
