<?php
/**
 * Tests for post review approval metadata.
 *
 * @package AI_Post_Scheduler
 */

class Test_AIPS_Post_Review_Approval extends WP_UnitTestCase {

	public function setUp(): void {
		parent::setUp();
		global $aips_test_meta;
		$aips_test_meta = array();
	}

	public function test_mark_post_approved_for_review_sets_reviewer_metadata() {
		$post_id = 601;

		$review = new AIPS_Post_Review();
		$review->mark_post_approved_for_review($post_id);

		$this->assertSame('approved', get_post_meta($post_id, 'aips_review_state', true));
		$this->assertSame('true', get_post_meta($post_id, 'aips_review_required', true));
		$this->assertSame(1, get_post_meta($post_id, 'aips_reviewed_by', true));
		$this->assertGreaterThan(0, get_post_meta($post_id, 'aips_reviewed_at', true));
	}
}
