<?php
/**
 * Tests for AIPS_Stress_Test_Data_Cleaner
 *
 * @package AI_Post_Scheduler
 */

class Test_AIPS_Stress_Test_Data_Cleaner extends WP_UnitTestCase {

	/**
	 * @var AIPS_Stress_Test_Data_Cleaner
	 */
	private $cleaner;

	public function setUp(): void {
		parent::setUp();
		$this->cleaner = new AIPS_Stress_Test_Data_Cleaner();
	}

	public function test_count_and_cleanup_test_data() {
		// Create a test post marked with TEST_POST_META
		$post_id = wp_insert_post(array(
			'post_title'   => 'Stress Test Fixture Post',
			'post_content' => 'Dummy fixture content.',
			'post_status'  => 'publish',
		));
		update_post_meta($post_id, AIPS_Stress_Test_Data_Cleaner::TEST_POST_META, 1);

		// Create a test attachment marked with TEST_ATTACHMENT_META
		$attachment_id = wp_insert_attachment(array(
			'post_title'     => 'Stress Test Fixture Attachment',
			'post_mime_type' => 'image/png',
			'post_status'    => 'inherit',
		));
		update_post_meta($attachment_id, AIPS_Stress_Test_Data_Cleaner::TEST_ATTACHMENT_META, 1);

		// Assert cleaner discovers them
		$counts = $this->cleaner->count_test_data();
		$this->assertGreaterThanOrEqual(1, $counts['posts']);
		$this->assertGreaterThanOrEqual(1, $counts['attachments']);

		// Clean up
		$result = $this->cleaner->cleanup_test_data();
		$this->assertGreaterThanOrEqual(1, $result['posts']);
		$this->assertGreaterThanOrEqual(1, $result['attachments']);

		// Verify deleted
		$this->assertNull(get_post($post_id));
		$this->assertNull(get_post($attachment_id));
	}
}
