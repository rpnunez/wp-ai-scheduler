<?php
/**
 * Test AIPS_Post_Audit_Service and staging revision lifecycle.
 *
 * @package AI_Post_Scheduler
 */

class Test_AIPS_Post_Audit extends WP_UnitTestCase {

	/**
	 * @var AIPS_Post_Audit_Service
	 */
	private $service;

	protected function setUp(): void {
		parent::setUp();
		AIPS_Mock_AI_Provider::reset();
		AIPS_Config::get_instance()->set_option('aips_ai_provider', 'mock');
		AIPS_AI_Provider_Factory::reset_cache();

		$this->service = new AIPS_Post_Audit_Service();
	}

	/**
	 * Create a published post whose modified date is in the past.
	 *
	 * @param int $days_ago Days since last modification.
	 * @return int Post ID.
	 */
	private function create_stale_post(int $days_ago = 200): int {
		$past_time = gmdate('Y-m-d H:i:s', time() - ($days_ago * DAY_IN_SECONDS));

		return (int) wp_insert_post(array(
			'post_title'        => 'Original Outdated Article',
			'post_content'      => '<p>Old content from ' . $days_ago . ' days ago.</p>',
			'post_status'       => 'publish',
			'post_date'         => $past_time,
			'post_modified'     => $past_time,
			'post_modified_gmt' => $past_time,
		));
	}

	public function test_stale_post_detection_and_staging_lifecycle() {
		$post_id = $this->create_stale_post();
		$this->assertGreaterThan(0, $post_id);

		$stale_ids = wp_list_pluck($this->service->find_stale_posts(180, 10), 'ID');
		$this->assertContains($post_id, $stale_ids);

		// Create staging revision.
		$revision_id = $this->service->create_staging_revision($post_id);
		$this->assertNotWPError($revision_id);
		$this->assertGreaterThan(0, $revision_id);
		$this->assertSame('1', get_post_meta($post_id, AIPS_Post_Audit_Service::META_HAS_PENDING, true));
		$this->assertSame('1', get_post_meta($revision_id, AIPS_Post_Audit_Service::META_IS_STAGING, true));
		$this->assertSame((string) $post_id, get_post_meta($revision_id, AIPS_Post_Audit_Service::META_TARGET_POST_ID, true));

		// The pipeline draft is reused rather than duplicated: exactly one
		// staging draft should exist for this target.
		$this->assertCount(1, get_posts(array(
			'post_type'   => 'post',
			'post_status' => 'draft',
			'meta_key'    => AIPS_Post_Audit_Service::META_TARGET_POST_ID,
			'meta_value'  => (string) $post_id,
		)));

		// A post with a pending revision is no longer a scan candidate.
		$this->assertNotContains($post_id, wp_list_pluck($this->service->find_stale_posts(180, 10), 'ID'));

		// Approve and merge staging revision.
		$approved = $this->service->approve_revision($revision_id);
		$this->assertTrue($approved);

		$this->assertSame('0', get_post_meta($post_id, AIPS_Post_Audit_Service::META_HAS_PENDING, true));
		$this->assertNotEmpty(get_post_meta($post_id, AIPS_Post_Audit_Service::META_LAST_REFRESHED_AT, true));
		$this->assertNull(get_post($revision_id)); // Deleted after merge.
	}

	public function test_immutable_post_is_never_refreshed() {
		$post_id = $this->create_stale_post();

		$this->service->set_immutable($post_id, true);
		$this->assertTrue($this->service->is_immutable($post_id));

		// Excluded from the candidate query.
		$this->assertNotContains($post_id, wp_list_pluck($this->service->find_stale_posts(180, 10), 'ID'));

		// Rejected even when targeted directly.
		$result = $this->service->create_staging_revision($post_id);
		$this->assertWPError($result);
		$this->assertSame('immutable_post', $result->get_error_code());
		$this->assertSame('', get_post_meta($post_id, AIPS_Post_Audit_Service::META_HAS_PENDING, true));

		// Un-marking restores eligibility.
		$this->service->set_immutable($post_id, false);
		$this->assertFalse($this->service->is_immutable($post_id));
		$this->assertContains($post_id, wp_list_pluck($this->service->find_stale_posts(180, 10), 'ID'));
	}

	public function test_every_check_appends_an_audit_log_entry() {
		$post_id = $this->create_stale_post();

		$this->assertSame(array(), $this->service->get_audit_log($post_id));

		$this->service->set_immutable($post_id, true);
		$this->service->create_staging_revision($post_id); // Skipped: immutable.
		$this->service->set_immutable($post_id, false);
		$revision_id = $this->service->create_staging_revision($post_id); // Created.

		$log = $this->service->get_audit_log($post_id);
		$this->assertCount(2, $log);
		$this->assertSame(AIPS_Post_Audit_Service::RESULT_SKIPPED_IMMUTABLE, $log[0]['result']);
		$this->assertSame(AIPS_Post_Audit_Service::RESULT_REVISION_CREATED, $log[1]['result']);
		$this->assertSame((int) $revision_id, $log[1]['revision_id']);
		$this->assertNotEmpty($log[1]['checked_at']);

		$this->assertSame(2, (int) get_post_meta($post_id, AIPS_Post_Audit_Service::META_AUDIT_COUNT, true));
		$this->assertNotEmpty(get_post_meta($post_id, AIPS_Post_Audit_Service::META_LAST_AUDITED_AT, true));

		// Approving records a third entry on the live post.
		$this->service->approve_revision($revision_id);
		$log  = $this->service->get_audit_log($post_id);
		$last = end($log);
		$this->assertSame(AIPS_Post_Audit_Service::RESULT_APPROVED, $last['result']);
	}

	public function test_audit_log_is_trimmed_to_configured_limit() {
		$post_id = $this->create_stale_post();
		AIPS_Config::get_instance()->set_option('aips_post_refresher_audit_log_limit', 3);

		for ($i = 0; $i < 6; $i++) {
			$this->service->record_audit_check($post_id, AIPS_Post_Audit_Service::RESULT_FAILED, array(
				'message' => 'entry ' . $i,
			));
		}

		$log  = $this->service->get_audit_log($post_id);
		$last = end($log);
		$this->assertCount(3, $log);
		$this->assertSame('entry 5', $last['message']);
	}

	public function test_settings_drive_scan_defaults() {
		$config = AIPS_Config::get_instance();
		$config->set_option('aips_post_refresher_stale_days', 30);
		$config->set_option('aips_post_refresher_batch_limit', 1);

		$this->create_stale_post(40);
		$this->create_stale_post(50);

		// Batch limit of 1 caps the candidate list even though two posts qualify.
		$this->assertCount(1, $this->service->find_stale_posts());

		// A 40-day-old post is stale under the configured 30-day window but not
		// under the 180-day default.
		$config->set_option('aips_post_refresher_batch_limit', 10);
		$this->assertCount(2, $this->service->find_stale_posts());
		$this->assertCount(0, $this->service->find_stale_posts(365));
	}

	public function test_approve_and_reject_refuse_non_revision_posts() {
		$ordinary_post_id = (int) wp_insert_post(array(
			'post_title'  => 'An ordinary draft',
			'post_status' => 'draft',
		));

		$approved = $this->service->approve_revision($ordinary_post_id);
		$this->assertWPError($approved);
		$this->assertSame('not_a_revision', $approved->get_error_code());

		$rejected = $this->service->reject_revision($ordinary_post_id);
		$this->assertWPError($rejected);
		$this->assertSame('not_a_revision', $rejected->get_error_code());

		// The post must survive both refused calls.
		$this->assertNotNull(get_post($ordinary_post_id));
	}

	public function test_reject_clears_pending_flags_and_deletes_draft() {
		$post_id     = $this->create_stale_post();
		$revision_id = $this->service->create_staging_revision($post_id);
		$this->assertNotWPError($revision_id);

		$this->assertTrue($this->service->reject_revision($revision_id));
		$this->assertNull(get_post($revision_id));
		$this->assertSame('0', get_post_meta($post_id, AIPS_Post_Audit_Service::META_HAS_PENDING, true));
		$this->assertSame('', get_post_meta($post_id, AIPS_Post_Audit_Service::META_PENDING_ID, true));
		$log  = $this->service->get_audit_log($post_id);
		$last = end($log);
		$this->assertSame(AIPS_Post_Audit_Service::RESULT_REJECTED, $last['result']);
	}

	public function test_scheduled_scan_is_a_noop_while_disabled() {
		$this->create_stale_post();
		AIPS_Config::get_instance()->set_option('aips_post_refresher_enabled', 0);

		$this->assertNull($this->service->run_scheduled_scan());

		AIPS_Config::get_instance()->set_option('aips_post_refresher_enabled', 1);
		$result = $this->service->run_scheduled_scan();

		$this->assertIsArray($result);
		$this->assertSame(1, $result['scanned']);
		$this->assertSame(1, $result['created']);
	}
}
