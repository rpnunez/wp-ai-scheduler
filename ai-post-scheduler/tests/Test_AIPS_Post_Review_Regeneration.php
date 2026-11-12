<?php
/**
 * Failure paths for review-queue regeneration deletes.
 *
 * A vetoed or failed wp_delete_post() must leave the history container
 * pointing at the original draft with its previous status.
 *
 * @package AI_Post_Scheduler
 */

class Test_AIPS_Post_Review_Regeneration extends WP_UnitTestCase {

	/** @var AIPS_Post_Review */
	private $review;

	/** @var AIPS_History_Repository */
	private $history;

	/** @var int */
	private $admin_id;

	/** @var int[] */
	private $post_ids = array();

	/** @var int[] */
	private $history_ids = array();

	/** @var int[] */
	private $template_ids = array();

	public function setUp(): void {
		parent::setUp();
		$this->review    = AIPS_Post_Review::instance();
		$this->history   = new AIPS_History_Repository();
		$this->admin_id  = self::factory()->user->create(array('role' => 'administrator'));
		wp_set_current_user($this->admin_id);
	}

	public function tearDown(): void {
		global $wpdb;

		remove_all_filters('pre_delete_post');

		foreach ($this->post_ids as $post_id) {
			wp_delete_post($post_id, true);
		}
		foreach ($this->history_ids as $history_id) {
			$wpdb->delete($wpdb->prefix . 'aips_history_log', array('history_id' => $history_id), array('%d'));
			$wpdb->delete($wpdb->prefix . 'aips_history', array('id' => $history_id), array('%d'));
		}
		foreach ($this->template_ids as $template_id) {
			$wpdb->delete($wpdb->prefix . 'aips_templates', array('id' => $template_id), array('%d'));
		}

		$_POST    = array();
		$_REQUEST = array();

		parent::tearDown();
	}

	/**
	 * Draft plus a completed generation-run history row in the review queue.
	 *
	 * @return array{post_id:int,history_id:int,template_id:int}
	 */
	private function make_review_item() {
		$templates = new AIPS_Template_Repository();
		$template_id = $templates->create(array(
			'name'            => 'Regeneration template ' . uniqid(),
			'prompt_template' => 'Write a post',
			'post_status'     => 'draft',
			'post_type'       => 'post',
			'is_active'       => 1,
		));
		$this->template_ids[] = (int) $template_id;

		$post_id = wp_insert_post(array(
			'post_title'   => 'Review draft ' . uniqid(),
			'post_content' => 'Body',
			'post_status'  => 'draft',
			'post_type'    => 'post',
			'post_author'  => $this->admin_id,
		));
		$this->post_ids[] = (int) $post_id;

		$history_id = $this->history->create(array(
			'post_id'         => $post_id,
			'template_id'     => $template_id,
			'status'          => 'completed',
			'creation_method' => 'scheduled',
			'generated_title' => 'Generated title',
			'error_message'   => 'earlier error',
			'record_kind'     => AIPS_History_Record_Kind::GENERATION,
			'completed_at'    => time(),
		));
		$this->history_ids[] = (int) $history_id;

		return array(
			'post_id'     => (int) $post_id,
			'history_id'  => (int) $history_id,
			'template_id' => (int) $template_id,
		);
	}

	/**
	 * @param callable $callable AJAX handler.
	 * @return array
	 */
	private function capture_ajax(callable $callable) {
		$_REQUEST = array_merge($_REQUEST, $_POST);
		ob_start();
		try {
			$callable();
		} catch (WPAjaxDieContinueException $e) {
			// Expected after wp_send_json_*.
		}

		$decoded = json_decode(ob_get_clean(), true);
		return is_array($decoded) ? $decoded : array();
	}

	public function test_vetoed_deletion_restores_history_association() {
		$item = $this->make_review_item();
		$history_item = $this->history->get_by_id($item['history_id']);

		add_filter('pre_delete_post', '__return_false');

		$result = $this->review->delete_draft_for_regeneration(
			$item['history_id'],
			$history_item,
			$item['post_id']
		);

		remove_filter('pre_delete_post', '__return_false');

		$this->assertInstanceOf(WP_Error::class, $result);
		$this->assertSame('delete_failed', $result->get_error_code());
		$this->assertSame('draft', get_post_status($item['post_id']), 'The vetoed draft remains a draft.');

		$restored = $this->history->get_by_id($item['history_id']);
		$this->assertSame('completed', $restored->status);
		$this->assertSame($item['post_id'], (int) $restored->post_id);
		$this->assertSame('earlier error', $restored->error_message);
	}

	public function test_successful_regeneration_delete_clears_the_association() {
		$item = $this->make_review_item();
		$history_item = $this->history->get_by_id($item['history_id']);

		$result = $this->review->delete_draft_for_regeneration(
			$item['history_id'],
			$history_item,
			$item['post_id']
		);

		$this->assertTrue($result);
		$this->assertNull(get_post($item['post_id']));

		$updated = $this->history->get_by_id($item['history_id']);
		$this->assertSame('pending', $updated->status);
		$this->assertEmpty($updated->post_id);
		$this->assertEmpty($updated->error_message);
	}

	public function test_single_regenerate_ajax_restores_history_when_delete_is_vetoed() {
		$item = $this->make_review_item();

		add_filter('pre_delete_post', '__return_false');
		$_POST['nonce']      = wp_create_nonce('aips_ajax_nonce');
		$_POST['history_id'] = $item['history_id'];

		$response = $this->capture_ajax(array($this->review, 'ajax_regenerate_post'));

		remove_filter('pre_delete_post', '__return_false');

		$this->assertFalse($response['success']);
		$this->assertSame('draft', get_post_status($item['post_id']));

		$restored = $this->history->get_by_id($item['history_id']);
		$this->assertSame('completed', $restored->status);
		$this->assertSame($item['post_id'], (int) $restored->post_id);
		$this->assertSame('earlier error', $restored->error_message);
	}

	public function test_bulk_regenerate_ajax_restores_history_when_delete_is_vetoed() {
		$item = $this->make_review_item();

		// The bulk harness logs array items without an error_formatter.
		$this->setExpectedIncorrectUsage('AIPS_Bulk_Generator_Service::run');

		add_filter('pre_delete_post', '__return_false');
		$_POST['nonce'] = wp_create_nonce('aips_ajax_nonce');
		$_POST['items'] = array(
			array(
				'post_id'    => $item['post_id'],
				'history_id' => $item['history_id'],
			),
		);

		$response = $this->capture_ajax(array($this->review, 'ajax_bulk_regenerate_posts'));

		remove_filter('pre_delete_post', '__return_false');

		$this->assertFalse($response['success']);
		$this->assertSame(1, (int) $response['data']['failed_count']);
		$this->assertSame('draft', get_post_status($item['post_id']));

		$restored = $this->history->get_by_id($item['history_id']);
		$this->assertSame('completed', $restored->status);
		$this->assertSame($item['post_id'], (int) $restored->post_id);
		$this->assertSame('earlier error', $restored->error_message);
	}
}
