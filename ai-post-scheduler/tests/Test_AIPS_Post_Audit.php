<?php
/**
 * Test AIPS_Post_Audit_Service and staging revision lifecycle.
 *
 * @package AI_Post_Scheduler
 */

class Test_AIPS_Post_Audit extends WP_UnitTestCase {

	protected function setUp(): void {
		parent::setUp();
		AIPS_Mock_AI_Provider::reset();
		AIPS_Config::get_instance()->set_option('aips_ai_provider', 'mock');
		AIPS_AI_Provider_Factory::reset_cache();
	}

	public function test_stale_post_detection_and_staging_lifecycle() {
		// Create a mock published post dated 200 days ago
		$past_time = gmdate('Y-m-d H:i:s', time() - (200 * DAY_IN_SECONDS));
		$post_id = wp_insert_post(array(
			'post_title'        => 'Original Outdated Article',
			'post_content'      => '<p>Old content from 200 days ago.</p>',
			'post_status'       => 'publish',
			'post_date'         => $past_time,
			'post_modified'     => $past_time,
			'post_modified_gmt' => $past_time,
		));

		$this->assertGreaterThan(0, $post_id);

		$service = new AIPS_Post_Audit_Service();
		$stale = $service->find_stale_posts(180, 10);
		$stale_ids = wp_list_pluck($stale, 'ID');
		$this->assertContains($post_id, $stale_ids);

		// Create staging revision
		$revision_id = $service->create_staging_revision($post_id);
		$this->assertNotWPError($revision_id);
		$this->assertGreaterThan(0, $revision_id);
		$this->assertSame('1', get_post_meta($post_id, '_aips_has_pending_revision', true));

		// Approve and merge staging revision
		$approved = $service->approve_revision($revision_id);
		$this->assertTrue($approved);

		$updated_post = get_post($post_id);
		$this->assertSame('0', get_post_meta($post_id, '_aips_has_pending_revision', true));
		$this->assertNotEmpty(get_post_meta($post_id, '_aips_last_refreshed_at', true));
		$this->assertNull(get_post($revision_id)); // Deleted after merge
	}
}
