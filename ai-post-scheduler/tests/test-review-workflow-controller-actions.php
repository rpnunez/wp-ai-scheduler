<?php
/**
 * Tests for Review Workflow Controller actions (schedule/publish).
 *
 * @package AI_Post_Scheduler
 */

class Test_AIPS_Review_Workflow_Controller_Actions extends WP_UnitTestCase {

	/** @var int */
	private $admin_user_id;

	/** @var AIPS_Review_Workflow_Controller */
	private $controller;

	/** @var AIPS_Review_Workflow_Repository */
	private $repository;

	/** @var int[] */
	private $post_ids = array();

	/** @var int[] */
	private $history_ids = array();

	public function setUp(): void {
		parent::setUp();
		AIPS_DB_Manager::install_tables();
		$this->admin_user_id = $this->factory->user->create( array( 'role' => 'administrator' ) );
		$this->repository    = new AIPS_Review_Workflow_Repository();
		$this->controller    = new AIPS_Review_Workflow_Controller( $this->repository );
	}

	public function tearDown(): void {
		$_POST    = array();
		$_REQUEST = array();

		foreach ($this->post_ids as $post_id) {
			wp_delete_post($post_id, true);
		}

		global $wpdb;
		$history_table = $wpdb->prefix . 'aips_history';
		foreach ($this->history_ids as $hid) {
			$wpdb->delete($history_table, array('id' => $hid), array('%d'));
		}

		parent::tearDown();
	}

	private function call_ajax(callable $callable) {
		ob_start();
		try {
			$callable();
		} catch (WPAjaxDieContinueException $e) {
			// Expected.
		}
		$out = ob_get_clean();
		return json_decode($out, true);
	}

	private function create_workflow_item_at_ready() {
		$post_id = wp_insert_post(array(
			'post_title'   => 'Ready Post ' . uniqid(),
			'post_content' => 'Hello',
			'post_status'  => 'draft',
			'post_type'    => 'post',
		));
		$this->post_ids[] = $post_id;

		global $wpdb;
		$history_table = $wpdb->prefix . 'aips_history';
		$wpdb->insert($history_table, array(
			'post_id'        => $post_id,
			'template_id'    => 1,
			'status'         => 'completed',
			'generated_title'=> 'Generated',
			'generated_content' => 'Generated content',
			'created_at'     => current_time('mysql'),
			'completed_at'   => current_time('mysql'),
		));
		$history_id = (int) $wpdb->insert_id;
		$this->history_ids[] = $history_id;

		$review_item_id = $this->repository->get_or_create_item_for_post($post_id, $history_id, array('template_id' => 1));
		$this->repository->set_stage($review_item_id, 'ready');

		return array($post_id, $review_item_id);
	}

	public function test_ajax_schedule_sets_future_status_and_closes_item() {
		wp_set_current_user($this->admin_user_id);
		list($post_id, $review_item_id) = $this->create_workflow_item_at_ready();

		$dt = new DateTimeImmutable('now', wp_timezone());
		$dt = $dt->modify('+2 hours');
		$schedule_at = $dt->format('Y-m-d\\TH:i');

		$_POST = array(
			'nonce'         => wp_create_nonce('aips_ajax_nonce'),
			'review_item_id'=> $review_item_id,
			'schedule_at'   => $schedule_at,
		);
		$_REQUEST = $_POST;

		$response = $this->call_ajax(array($this->controller, 'ajax_schedule'));
		$this->assertTrue($response['success']);

		$post = get_post($post_id);
		$this->assertEquals('future', $post->post_status);

		$item = $this->repository->get_item_row($review_item_id);
		$this->assertEquals('scheduled', $item->closed_state);
	}

	public function test_ajax_publish_now_sets_publish_status_and_closes_item() {
		wp_set_current_user($this->admin_user_id);
		list($post_id, $review_item_id) = $this->create_workflow_item_at_ready();

		$_POST = array(
			'nonce'         => wp_create_nonce('aips_ajax_nonce'),
			'review_item_id'=> $review_item_id,
		);
		$_REQUEST = $_POST;

		$response = $this->call_ajax(array($this->controller, 'ajax_publish_now'));
		$this->assertTrue($response['success']);

		$post = get_post($post_id);
		$this->assertEquals('publish', $post->post_status);

		$item = $this->repository->get_item_row($review_item_id);
		$this->assertEquals('published', $item->closed_state);
	}
}

