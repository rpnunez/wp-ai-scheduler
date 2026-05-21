<?php
/**
 * Tests for review publishing guard.
 *
 * @package AI_Post_Scheduler
 */

class Test_AIPS_Review_Publishing_Guard extends WP_UnitTestCase {

	public function setUp(): void {
		parent::setUp();
		global $aips_test_meta;
		$aips_test_meta = array();
	}

	public function test_blocks_direct_publish_when_review_is_not_approved() {
		$post_id = 501;
		update_post_meta($post_id, 'aips_review_required', 'true');
		update_post_meta($post_id, 'aips_review_state', 'needs_review');

		$guard = new AIPS_Review_Publishing_Guard();
		$data = $guard->guard_post_data(
			array(
				'ID' => $post_id,
				'post_status' => 'publish',
			),
			array('ID' => $post_id)
		);

		$this->assertSame('draft', $data['post_status']);
	}

	public function test_allows_publish_when_review_is_approved() {
		$post_id = 502;
		update_post_meta($post_id, 'aips_review_required', 'true');
		update_post_meta($post_id, 'aips_review_state', 'approved');

		$guard = new AIPS_Review_Publishing_Guard();
		$data = $guard->guard_post_data(
			array(
				'ID' => $post_id,
				'post_status' => 'publish',
			),
			array('ID' => $post_id)
		);

		$this->assertSame('publish', $data['post_status']);
	}
}
