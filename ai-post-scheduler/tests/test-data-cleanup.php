<?php
/**
 * Tests for AIPS_Data_Cleanup
 *
 * Verifies that the `before_delete_post` hook correctly orchestrates the
 * cascade-deletion of history containers, history logs, and author topic
 * logs when a WordPress post is permanently deleted.
 *
 * @package AI_Post_Scheduler
 */

class Test_AIPS_Data_Cleanup extends WP_UnitTestCase {

	public function tearDown(): void {
		// Remove the before_delete_post actions registered during tests so
		// they don't accumulate and interfere with each other.
		if (isset($GLOBALS['aips_test_hooks']['actions']['before_delete_post'])) {
			unset($GLOBALS['aips_test_hooks']['actions']['before_delete_post']);
		}
		parent::tearDown();
	}

	// ------------------------------------------------------------------
	// Spy implementations
	// ------------------------------------------------------------------

	/**
	 * Build a spy AIPS_History_Repository.
	 *
	 * @param int[] $ids_to_return What get_ids_by_post_id() should return.
	 * @return AIPS_History_Repository
	 */
	private function make_history_spy($ids_to_return = array()) {
		return new class($ids_to_return) extends AIPS_History_Repository {
			public $calls = array(
				'get_ids_by_post_id'         => array(),
				'delete_logs_by_history_ids' => array(),
				'delete_by_post_id'          => array(),
			);
			private $stub_ids;

			public function __construct($ids) {
				$this->stub_ids = $ids;
			}

			public function get_ids_by_post_id($post_id) {
				$this->calls['get_ids_by_post_id'][] = (int) $post_id;
				return $this->stub_ids;
			}

			public function delete_logs_by_history_ids(array $history_ids) {
				$this->calls['delete_logs_by_history_ids'][] = $history_ids;
				return count($history_ids);
			}

			public function delete_by_post_id($post_id) {
				$this->calls['delete_by_post_id'][] = (int) $post_id;
				return 1;
			}
		};
	}

	/**
	 * Build a spy AIPS_Author_Topic_Logs_Repository.
	 *
	 * @return AIPS_Author_Topic_Logs_Repository
	 */
	private function make_logs_spy() {
		return new class extends AIPS_Author_Topic_Logs_Repository {
			public $calls = array(
				'delete_by_post_id' => array(),
			);

			public function __construct() {}

			public function delete_by_post_id($post_id) {
				$this->calls['delete_by_post_id'][] = (int) $post_id;
				return 1;
			}
		};
	}

	// ------------------------------------------------------------------
	// Constructor / hook registration
	// ------------------------------------------------------------------

	/**
	 * @test
	 * The constructor registers on_before_delete_post with before_delete_post,
	 * and the integration test confirms the callback fires when the action does.
	 */
	public function test_constructor_registers_before_delete_post_hook() {
		new AIPS_Data_Cleanup($this->make_history_spy(), $this->make_logs_spy());

		$registered = isset($GLOBALS['aips_test_hooks']['actions']['before_delete_post']);
		$this->assertTrue($registered, 'before_delete_post hook should be registered.');
	}

	// ------------------------------------------------------------------
	// on_before_delete_post – normal flow
	// ------------------------------------------------------------------

	/**
	 * @test
	 * When a post is deleted and it has history containers, the method:
	 *   1. Fetches the container IDs via get_ids_by_post_id.
	 *   2. Deletes history logs via delete_logs_by_history_ids.
	 *   3. Deletes the history containers via delete_by_post_id.
	 *   4. Deletes topic logs via delete_by_post_id on the topic logs repo.
	 */
	public function test_on_before_delete_post_with_history_containers() {
		$post_id       = 42;
		$container_ids = array(10, 20, 30);

		$history_spy = $this->make_history_spy($container_ids);
		$logs_spy    = $this->make_logs_spy();

		$cleanup = new AIPS_Data_Cleanup($history_spy, $logs_spy);
		$cleanup->on_before_delete_post($post_id);

		// get_ids_by_post_id called with the correct post ID.
		$this->assertSame(array($post_id), $history_spy->calls['get_ids_by_post_id']);

		// delete_logs_by_history_ids called once with the returned container IDs.
		$this->assertCount(1, $history_spy->calls['delete_logs_by_history_ids']);
		$this->assertSame($container_ids, $history_spy->calls['delete_logs_by_history_ids'][0]);

		// History containers deleted.
		$this->assertSame(array($post_id), $history_spy->calls['delete_by_post_id']);

		// Author topic logs deleted.
		$this->assertSame(array($post_id), $logs_spy->calls['delete_by_post_id']);
	}

	/**
	 * @test
	 * When a post has no associated history containers, delete_logs_by_history_ids
	 * should NOT be called (avoids an unnecessary query).
	 */
	public function test_on_before_delete_post_skips_log_deletion_when_no_containers() {
		$history_spy = $this->make_history_spy(array());
		$logs_spy    = $this->make_logs_spy();

		$cleanup = new AIPS_Data_Cleanup($history_spy, $logs_spy);
		$cleanup->on_before_delete_post(99);

		$this->assertEmpty(
			$history_spy->calls['delete_logs_by_history_ids'],
			'delete_logs_by_history_ids should not be called when there are no containers.'
		);

		// Container and topic-log deletes should still be attempted.
		$this->assertSame(array(99), $history_spy->calls['delete_by_post_id']);
		$this->assertSame(array(99), $logs_spy->calls['delete_by_post_id']);
	}

	/**
	 * @test
	 * A zero post_id is treated as invalid and results in no deletions.
	 */
	public function test_on_before_delete_post_ignores_zero_post_id() {
		$history_spy = $this->make_history_spy();
		$logs_spy    = $this->make_logs_spy();

		$cleanup = new AIPS_Data_Cleanup($history_spy, $logs_spy);
		$cleanup->on_before_delete_post(0);

		$this->assertEmpty($history_spy->calls['get_ids_by_post_id']);
		$this->assertEmpty($history_spy->calls['delete_logs_by_history_ids']);
		$this->assertEmpty($history_spy->calls['delete_by_post_id']);
		$this->assertEmpty($logs_spy->calls['delete_by_post_id']);
	}

	// ------------------------------------------------------------------
	// Integration: hook triggers handler via do_action
	// ------------------------------------------------------------------

	/**
	 * @test
	 * Firing `before_delete_post` via do_action() triggers the handler.
	 */
	public function test_do_action_triggers_cleanup() {
		$post_id = 55;

		$history_spy = $this->make_history_spy(array(1, 2));
		$logs_spy    = $this->make_logs_spy();

		new AIPS_Data_Cleanup($history_spy, $logs_spy);

		do_action('before_delete_post', $post_id);

		$this->assertContains($post_id, $history_spy->calls['get_ids_by_post_id']);
		$this->assertContains($post_id, $history_spy->calls['delete_by_post_id']);
		$this->assertContains($post_id, $logs_spy->calls['delete_by_post_id']);
	}

	// ------------------------------------------------------------------
	// Repository: AIPS_History_Repository – new methods
	// ------------------------------------------------------------------

	/**
	 * @test
	 * get_ids_by_post_id returns an array (empty when wpdb returns nothing).
	 */
	public function test_history_repository_get_ids_by_post_id_returns_array() {
		$repo   = new AIPS_History_Repository();
		$result = $repo->get_ids_by_post_id(9999);
		$this->assertIsArray($result);
	}

	/**
	 * @test
	 * get_ids_by_post_id returns an empty array without querying for an invalid post ID.
	 */
	public function test_history_repository_get_ids_by_post_id_returns_empty_for_invalid_id() {
		$repo   = new AIPS_History_Repository();
		$this->assertSame(array(), $repo->get_ids_by_post_id(0));
		$this->assertSame(array(), $repo->get_ids_by_post_id(-5));
	}

	/**
	 * @test
	 * delete_logs_by_history_ids returns 0 for an empty array.
	 */
	public function test_history_repository_delete_logs_by_history_ids_empty_input() {
		$repo   = new AIPS_History_Repository();
		$result = $repo->delete_logs_by_history_ids(array());
		$this->assertSame(0, $result);
	}

	/**
	 * @test
	 * delete_by_post_id delegates to $wpdb and returns a non-false result.
	 */
	public function test_history_repository_delete_by_post_id() {
		$repo   = new AIPS_History_Repository();
		$result = $repo->delete_by_post_id(1);
		$this->assertNotFalse($result);
	}

	// ------------------------------------------------------------------
	// Repository: AIPS_Author_Topic_Logs_Repository – new method
	// ------------------------------------------------------------------

	/**
	 * @test
	 * delete_by_post_id on the topic logs repo delegates to $wpdb->delete.
	 */
	public function test_topic_logs_repository_delete_by_post_id() {
		$repo   = new AIPS_Author_Topic_Logs_Repository();
		$result = $repo->delete_by_post_id(1);
		$this->assertNotFalse($result);
	}
}
