<?php
/**
 * Tests for AIPS_Post_Review::publish_review_post() — the shared single/bulk/
 * background publish routine introduced with the faster bulk-publish work.
 *
 * @package AI_Post_Scheduler
 */

class Test_AIPS_Pending_Review_Publish extends WP_UnitTestCase {

	/** @var AIPS_Post_Review */
	private $review;

	/** @var int[] */
	private $post_ids = array();

	/** @var int[] */
	private $history_ids = array();

	public function setUp(): void {
		parent::setUp();
		$this->review = AIPS_Post_Review::instance();

		// publish_review_post() checks current_user_can('publish_post', $id).
		wp_set_current_user(self::factory()->user->create(array('role' => 'administrator')));
	}

	public function tearDown(): void {
		global $wpdb;

		foreach ($this->post_ids as $post_id) {
			wp_delete_post($post_id, true);
		}
		foreach ($this->history_ids as $history_id) {
			$wpdb->delete($wpdb->prefix . 'aips_history', array('id' => $history_id), array('%d'));
		}

		parent::tearDown();
	}

	/**
	 * Create a draft post with a completed generation-run history row so it is
	 * recognised as sitting in the review queue.
	 *
	 * @param string $post_status Initial post status.
	 * @return int Post ID.
	 */
	private function make_review_draft($post_status = 'draft') {
		global $wpdb;

		$post_id = wp_insert_post(array(
			'post_title'   => 'Draft ' . uniqid(),
			'post_content' => 'Body content',
			'post_status'  => $post_status,
			'post_type'    => 'post',
		));
		$this->post_ids[] = (int) $post_id;

		$wpdb->insert($wpdb->prefix . 'aips_history', array(
			'post_id'         => $post_id,
			'status'          => 'completed',
			'generated_title' => 'Generated Title',
			'creation_method' => 'scheduled',
			'created_at'      => time(),
			'completed_at'    => time(),
			'record_kind'     => AIPS_History_Record_Kind::for_creation_method('scheduled', array('post_id' => $post_id)),
		));
		$this->history_ids[] = (int) $wpdb->insert_id;

		return (int) $post_id;
	}

	public function test_publishes_draft_and_fires_action() {
		$post_id = $this->make_review_draft();

		$fired = array();
		$listener = function ($id) use (&$fired) {
			$fired[] = (int) $id;
		};
		add_action('aips_post_review_published', $listener);

		$result = $this->review->publish_review_post($post_id);

		remove_action('aips_post_review_published', $listener);

		$this->assertSame($post_id, $result, 'Returns the published post ID on success.');
		$this->assertSame('publish', get_post_status($post_id), 'Post is transitioned to publish.');
		$this->assertSame(array($post_id), $fired, 'aips_post_review_published fires exactly once.');
	}

	public function test_rejects_non_draft_post() {
		$post_id = $this->make_review_draft('publish');

		$result = $this->review->publish_review_post($post_id);

		$this->assertInstanceOf(WP_Error::class, $result);
		$this->assertSame('aips_not_draft', $result->get_error_code());
	}

	public function test_rejects_draft_not_in_review_queue() {
		// Draft with no history row at all.
		$post_id = wp_insert_post(array(
			'post_title'  => 'Orphan draft',
			'post_status' => 'draft',
			'post_type'   => 'post',
		));
		$this->post_ids[] = (int) $post_id;

		$result = $this->review->publish_review_post($post_id);

		$this->assertInstanceOf(WP_Error::class, $result);
		$this->assertSame('aips_not_in_review_queue', $result->get_error_code());
		$this->assertSame('draft', get_post_status($post_id), 'A rejected post stays a draft.');
	}

	public function test_rejects_invalid_post_id() {
		$result = $this->review->publish_review_post(0);

		$this->assertInstanceOf(WP_Error::class, $result);
		$this->assertSame('aips_invalid_post_id', $result->get_error_code());
	}

	public function test_async_threshold_setting_defaults_to_30() {
		$this->assertSame(
			30,
			(int) AIPS_Config::get_instance()->get_option('aips_bulk_publish_async_threshold'),
			'The bulk-publish background threshold defaults to 30.'
		);
	}

	public function test_async_threshold_sanitizer_clamps_to_range() {
		$ui = new AIPS_Settings_UI();

		$this->assertSame(30, $ui->sanitize_bulk_publish_async_threshold('30'));
		$this->assertSame(2, $ui->sanitize_bulk_publish_async_threshold(1), 'Values below 2 clamp up to 2.');
		$this->assertSame(2, $ui->sanitize_bulk_publish_async_threshold(-1), 'absint() makes -1 positive (1), which then clamps up to 2.');
		$this->assertSame(500, $ui->sanitize_bulk_publish_async_threshold(99999), 'Values above 500 clamp down to 500.');
	}
}
