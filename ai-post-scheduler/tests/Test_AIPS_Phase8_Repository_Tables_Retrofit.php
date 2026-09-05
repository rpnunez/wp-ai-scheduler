<?php
/**
 * Regression test for the Phase 8 (#1944) table() retrofit.
 *
 * The 12 repositories that were already on AIPS_Cacheable_Repository before the
 * migration have now also adopted AIPS_Repository_Tables so they resolve table
 * names through the shared table() accessor instead of hardcoded $wpdb->prefix
 * strings. This test locks that in.
 *
 * @package AI_Post_Scheduler
 */

class Test_AIPS_Phase8_Repository_Tables_Retrofit extends WP_UnitTestCase {

	/**
	 * @dataProvider retrofitted_repository_provider
	 */
	public function test_repository_uses_both_shared_traits($class) {
		$this->assertTrue(class_exists($class), "$class should be autoloadable.");

		$traits = class_uses($class);

		$this->assertContains('AIPS_Cacheable_Repository', $traits, "$class should use AIPS_Cacheable_Repository.");
		$this->assertContains('AIPS_Repository_Tables', $traits, "$class should use AIPS_Repository_Tables.");
	}

	public function retrofitted_repository_provider() {
		return array(
			array('AIPS_Article_Structure_Repository'),
			array('AIPS_Author_Topics_Repository'),
			array('AIPS_Authors_Repository'),
			array('AIPS_Campaigns_Repository'),
			array('AIPS_Dashboard_Repository'),
			array('AIPS_History_Repository'),
			array('AIPS_Metrics_Repository'),
			array('AIPS_Post_Slices_Repository'),
			array('AIPS_Prompt_Section_Repository'),
			array('AIPS_Schedule_Repository'),
			array('AIPS_Template_Repository'),
			array('AIPS_Voices_Repository'),
		);
	}
}
