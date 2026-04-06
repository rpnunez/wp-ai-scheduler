<?php
/**
 * Tests for Review Workflow Repository
 *
 * @package AI_Post_Scheduler
 */

class Test_AIPS_Review_Workflow_Repository extends WP_UnitTestCase {

	/** @var AIPS_Review_Workflow_Repository */
	private $repository;

	/** @var int[] */
	private $post_ids = array();

	/** @var int[] */
	private $history_ids = array();

	public function setUp(): void {
		parent::setUp();
		AIPS_DB_Manager::install_tables();
		$this->repository = new AIPS_Review_Workflow_Repository();
	}

	public function tearDown(): void {
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

	private function create_test_post_with_history($status = 'draft') {
		$post_id = wp_insert_post(array(
			'post_title'   => 'Workflow Test ' . uniqid(),
			'post_content' => 'Hello',
			'post_status'  => $status,
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

		return array($post_id, $history_id);
	}

	public function test_get_or_create_item_creates_stage_rows() {
		list($post_id, $history_id) = $this->create_test_post_with_history('draft');

		$review_item_id = $this->repository->get_or_create_item_for_post($post_id, $history_id, array('template_id' => 1));
		$this->assertGreaterThan(0, $review_item_id);

		$item = $this->repository->get_item_row($review_item_id);
		$this->assertEquals('brief', $item->stage);
		$this->assertEquals('pending', $item->stage_state);
		$this->assertEquals('open', $item->closed_state);

		$stages = $this->repository->get_stage_rows($review_item_id);
		foreach (AIPS_Review_Workflow_Repository::get_stages() as $stage_key) {
			$this->assertArrayHasKey($stage_key, $stages, "Stage row missing for {$stage_key}");
		}
	}

	public function test_toggle_checklist_item_persists() {
		list($post_id, $history_id) = $this->create_test_post_with_history('draft');
		$review_item_id = $this->repository->get_or_create_item_for_post($post_id, $history_id, array('template_id' => 1));

		$updated = $this->repository->toggle_checklist_item($review_item_id, 'brief', 'audience', true);
		$this->assertArrayHasKey('audience', $updated);
		$this->assertTrue($updated['audience']);

		$rows = $this->repository->get_stage_rows($review_item_id);
		$decoded = json_decode((string) $rows['brief']->checklist_state, true);
		$this->assertTrue((bool) $decoded['audience']);
	}

	public function test_approve_stage_advances_to_next() {
		list($post_id, $history_id) = $this->create_test_post_with_history('draft');
		$review_item_id = $this->repository->get_or_create_item_for_post($post_id, $history_id, array('template_id' => 1));

		$ok = $this->repository->set_stage_state($review_item_id, 'brief', 'approved', 'ok', 0, true);
		$this->assertTrue($ok);

		$item = $this->repository->get_item_row($review_item_id);
		$this->assertEquals('outline', $item->stage, 'Should advance to outline');
	}

	public function test_request_changes_does_not_advance() {
		list($post_id, $history_id) = $this->create_test_post_with_history('draft');
		$review_item_id = $this->repository->get_or_create_item_for_post($post_id, $history_id, array('template_id' => 1));

		$ok = $this->repository->set_stage_state($review_item_id, 'brief', 'changes_requested', 'needs edits', 0, false);
		$this->assertTrue($ok);

		$item = $this->repository->get_item_row($review_item_id);
		$this->assertEquals('brief', $item->stage, 'Should remain on brief');
		$this->assertEquals('changes_requested', $item->stage_state, 'Item stage_state should reflect current stage state');
	}
}

