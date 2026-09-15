<?php
/**
 * Tests for AIPS_Post_History_UI
 *
 * Covers the fix for a bug where the "View AI History" link was hidden on
 * every post type other than 'post', even when AIPS actually generated
 * that post (e.g. a custom-post-type "Product Review").
 *
 * @package AI_Post_Scheduler
 */

class Test_AIPS_Post_History_UI extends WP_UnitTestCase {

	public function setUp(): void {
		parent::setUp();

		register_post_type('aips_ui_test_cpt', array(
			'public' => true,
			'label'  => 'AIPS UI Test CPT',
		));
	}

	public function tearDown(): void {
		unregister_post_type('aips_ui_test_cpt');
		parent::tearDown();
	}

	public function test_row_action_shown_for_cpt_post_with_history() {
		$post_id = self::factory()->post->create(array('post_type' => 'aips_ui_test_cpt'));
		$post = get_post($post_id);

		$repo = new AIPS_Test_Stub_History_Repository();
		$repo->history_by_post[$post_id] = (object) array('id' => 42);

		$ui = new AIPS_Post_History_UI($repo);

		$admin_id = self::factory()->user->create(array('role' => 'administrator'));
		wp_set_current_user($admin_id);

		$actions = $ui->add_post_row_action(array(), $post);

		$this->assertArrayHasKey('aips_history', $actions);
	}

	public function test_row_action_hidden_for_cpt_post_without_history() {
		$post_id = self::factory()->post->create(array('post_type' => 'aips_ui_test_cpt'));
		$post = get_post($post_id);

		$repo = new AIPS_Test_Stub_History_Repository();
		// No history registered for this post_id.

		$ui = new AIPS_Post_History_UI($repo);

		$admin_id = self::factory()->user->create(array('role' => 'administrator'));
		wp_set_current_user($admin_id);

		$actions = $ui->add_post_row_action(array(), $post);

		$this->assertArrayNotHasKey('aips_history', $actions);
	}

	public function test_row_action_hidden_for_non_admin() {
		$post_id = self::factory()->post->create(array('post_type' => 'aips_ui_test_cpt'));
		$post = get_post($post_id);

		$repo = new AIPS_Test_Stub_History_Repository();
		$repo->history_by_post[$post_id] = (object) array('id' => 42);

		$ui = new AIPS_Post_History_UI($repo);

		$subscriber_id = self::factory()->user->create(array('role' => 'subscriber'));
		wp_set_current_user($subscriber_id);

		$actions = $ui->add_post_row_action(array(), $post);

		$this->assertArrayNotHasKey('aips_history', $actions);
	}
}

if (!class_exists('AIPS_Test_Stub_History_Repository', false)) {
	class AIPS_Test_Stub_History_Repository implements AIPS_History_Repository_Interface {
		public $history_by_post = array();

		public function get_history($args = array()) {
			return array('items' => array(), 'total' => 0);
		}
		public function get_activity_feed($limit = 50, $offset = 0, $filters = array()) {
			return array();
		}
		public function get_by_id($id) {
			return null;
		}
		public function get_by_post_id($post_id) {
			return isset($this->history_by_post[$post_id]) ? $this->history_by_post[$post_id] : null;
		}
		public function count_completed_for_schedule($schedule) {
			return 0;
		}
		public function invalidate_schedule_completed_count_cache($schedule_id) {
		}
		public function add_log_entry($history_id, $details, $history_type_id = null) {
			return false;
		}
		public function create($data) {
			return false;
		}
		public function update($id, $data) {
			return false;
		}
		public function get_logs_by_history_id($history_id, $type_filter = array(), $limit = 0) {
			return array();
		}
		public function get_estimated_generation_time($limit = 20) {
			return array();
		}
		public function get_component_revisions($post_id, $component_type, $limit = 20) {
			return array();
		}
		public function post_has_history_and_completed($post_id) {
			return isset($this->history_by_post[$post_id]);
		}
	}
}
