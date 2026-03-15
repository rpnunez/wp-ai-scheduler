<?php
/**
 * Tests for AIPS_Data_Cleanup
 *
 * Verifies that the `before_delete_post` and `aips_before_delete_author` hooks
 * correctly orchestrate cascade-deletion of related plugin records.
 *
 * @package AI_Post_Scheduler
 */

class Test_AIPS_Data_Cleanup extends WP_UnitTestCase {

	public function tearDown(): void {
		// Remove hooks registered during tests so they don't accumulate.
		foreach (array('before_delete_post', 'aips_before_delete_author') as $hook) {
			if (isset($GLOBALS['aips_test_hooks']['actions'][$hook])) {
				unset($GLOBALS['aips_test_hooks']['actions'][$hook]);
			}
		}
		parent::tearDown();
	}

	// ------------------------------------------------------------------
	// Spy implementations
	// ------------------------------------------------------------------

	/**
	 * Build a spy AIPS_History_Repository.
	 *
	 * @param int[] $post_ids_return   What get_ids_by_post_id() should return.
	 * @param int[] $author_ids_return What get_deletable_ids_by_author_id() should return.
	 * @return AIPS_History_Repository
	 */
	private function make_history_spy($post_ids_return = array(), $author_ids_return = array()) {
		return new class($post_ids_return, $author_ids_return) extends AIPS_History_Repository {
			public $calls = array(
				'get_ids_by_post_id'              => array(),
				'get_deletable_ids_by_author_id'  => array(),
				'delete_logs_by_history_ids'      => array(),
				'delete_by_post_id'               => array(),
				'delete_bulk'                     => array(),
			);
			private $stub_post_ids;
			private $stub_author_ids;

			public function __construct($post_ids, $author_ids) {
				$this->stub_post_ids   = $post_ids;
				$this->stub_author_ids = $author_ids;
			}

			public function get_ids_by_post_id($post_id) {
				$this->calls['get_ids_by_post_id'][] = (int) $post_id;
				return $this->stub_post_ids;
			}

			public function get_deletable_ids_by_author_id($author_id) {
				$this->calls['get_deletable_ids_by_author_id'][] = (int) $author_id;
				return $this->stub_author_ids;
			}

			public function delete_logs_by_history_ids(array $history_ids) {
				$this->calls['delete_logs_by_history_ids'][] = $history_ids;
				return count($history_ids);
			}

			public function delete_by_post_id($post_id) {
				$this->calls['delete_by_post_id'][] = (int) $post_id;
				return 1;
			}

			public function delete_bulk($ids) {
				$this->calls['delete_bulk'][] = $ids;
				return count($ids);
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
				'delete_by_post_id'  => array(),
				'delete_by_topic_ids' => array(),
			);

			public function __construct() {}

			public function delete_by_post_id($post_id) {
				$this->calls['delete_by_post_id'][] = (int) $post_id;
				return 1;
			}

			public function delete_by_topic_ids(array $topic_ids) {
				$this->calls['delete_by_topic_ids'][] = $topic_ids;
				return count($topic_ids);
			}
		};
	}

	/**
	 * Build a spy AIPS_Author_Topics_Repository.
	 *
	 * @param array $topics_to_return Objects to return from get_by_author().
	 * @return AIPS_Author_Topics_Repository
	 */
	private function make_topics_spy($topics_to_return = array()) {
		return new class($topics_to_return) extends AIPS_Author_Topics_Repository {
			public $calls = array(
				'get_by_author'    => array(),
				'delete_by_author' => array(),
			);
			private $stub_topics;

			public function __construct($topics) {
				$this->stub_topics = $topics;
			}

			public function get_by_author($author_id, $status = null) {
				$this->calls['get_by_author'][] = (int) $author_id;
				return $this->stub_topics;
			}

			public function delete_by_author($author_id) {
				$this->calls['delete_by_author'][] = (int) $author_id;
				return count($this->stub_topics);
			}
		};
	}

	/**
	 * Build a spy AIPS_Feedback_Repository.
	 *
	 * @return AIPS_Feedback_Repository
	 */
	private function make_feedback_spy() {
		return new class extends AIPS_Feedback_Repository {
			public $calls = array(
				'delete_by_topic_ids' => array(),
			);

			public function __construct() {}

			public function delete_by_topic_ids(array $topic_ids) {
				$this->calls['delete_by_topic_ids'][] = $topic_ids;
				return count($topic_ids);
			}
		};
	}

	/** Build stub topic objects with given IDs. */
	private function make_topic_stubs(array $ids) {
		return array_map(fn($id) => (object) array('id' => $id), $ids);
	}

	// ------------------------------------------------------------------
	// Constructor / hook registration
	// ------------------------------------------------------------------

	/**
	 * @test
	 * The constructor registers both hooks.
	 */
	public function test_constructor_registers_hooks() {
		new AIPS_Data_Cleanup(
			$this->make_history_spy(),
			$this->make_logs_spy(),
			$this->make_topics_spy(),
			$this->make_feedback_spy()
		);

		$this->assertTrue(
			isset($GLOBALS['aips_test_hooks']['actions']['before_delete_post']),
			'before_delete_post hook should be registered.'
		);
		$this->assertTrue(
			isset($GLOBALS['aips_test_hooks']['actions']['aips_before_delete_author']),
			'aips_before_delete_author hook should be registered.'
		);
	}

	// ------------------------------------------------------------------
	// on_before_delete_post – normal flow
	// ------------------------------------------------------------------

	/**
	 * @test
	 * Full post-deletion cascade: containers fetched → logs deleted → containers
	 * deleted → topic_logs deleted.
	 */
	public function test_on_before_delete_post_with_history_containers() {
		$post_id       = 42;
		$container_ids = array(10, 20, 30);

		$history_spy = $this->make_history_spy($container_ids);
		$logs_spy    = $this->make_logs_spy();

		$cleanup = new AIPS_Data_Cleanup($history_spy, $logs_spy, $this->make_topics_spy(), $this->make_feedback_spy());
		$cleanup->on_before_delete_post($post_id);

		$this->assertSame(array($post_id), $history_spy->calls['get_ids_by_post_id']);
		$this->assertCount(1, $history_spy->calls['delete_logs_by_history_ids']);
		$this->assertSame($container_ids, $history_spy->calls['delete_logs_by_history_ids'][0]);
		$this->assertSame(array($post_id), $history_spy->calls['delete_by_post_id']);
		$this->assertSame(array($post_id), $logs_spy->calls['delete_by_post_id']);
	}

	/**
	 * @test
	 * When a post has no containers, delete_logs_by_history_ids is skipped.
	 */
	public function test_on_before_delete_post_skips_log_deletion_when_no_containers() {
		$history_spy = $this->make_history_spy(array());
		$logs_spy    = $this->make_logs_spy();

		$cleanup = new AIPS_Data_Cleanup($history_spy, $logs_spy, $this->make_topics_spy(), $this->make_feedback_spy());
		$cleanup->on_before_delete_post(99);

		$this->assertEmpty($history_spy->calls['delete_logs_by_history_ids']);
		$this->assertSame(array(99), $history_spy->calls['delete_by_post_id']);
		$this->assertSame(array(99), $logs_spy->calls['delete_by_post_id']);
	}

	/**
	 * @test
	 * A zero post_id is invalid; no deletions should occur.
	 */
	public function test_on_before_delete_post_ignores_zero_post_id() {
		$history_spy = $this->make_history_spy();
		$logs_spy    = $this->make_logs_spy();

		$cleanup = new AIPS_Data_Cleanup($history_spy, $logs_spy, $this->make_topics_spy(), $this->make_feedback_spy());
		$cleanup->on_before_delete_post(0);

		$this->assertEmpty($history_spy->calls['get_ids_by_post_id']);
		$this->assertEmpty($history_spy->calls['delete_logs_by_history_ids']);
		$this->assertEmpty($history_spy->calls['delete_by_post_id']);
		$this->assertEmpty($logs_spy->calls['delete_by_post_id']);
	}

	/**
	 * @test
	 * Firing before_delete_post via do_action() triggers the handler.
	 */
	public function test_do_action_triggers_post_cleanup() {
		$post_id = 55;

		$history_spy = $this->make_history_spy(array(1, 2));
		$logs_spy    = $this->make_logs_spy();

		new AIPS_Data_Cleanup($history_spy, $logs_spy, $this->make_topics_spy(), $this->make_feedback_spy());
		do_action('before_delete_post', $post_id);

		$this->assertContains($post_id, $history_spy->calls['get_ids_by_post_id']);
		$this->assertContains($post_id, $history_spy->calls['delete_by_post_id']);
		$this->assertContains($post_id, $logs_spy->calls['delete_by_post_id']);
	}

	// ------------------------------------------------------------------
	// on_before_delete_author – normal flow
	// ------------------------------------------------------------------

	/**
	 * @test
	 * Full author-deletion cascade:
	 *   feedback deleted → topic_logs deleted → topics deleted →
	 *   deletable history containers fetched → history logs deleted →
	 *   history containers deleted.
	 */
	public function test_on_before_delete_author_full_cascade() {
		$author_id   = 7;
		$topic_ids   = array(101, 102);
		$history_ids = array(200, 201);

		$history_spy  = $this->make_history_spy(array(), $history_ids);
		$logs_spy     = $this->make_logs_spy();
		$topics_spy   = $this->make_topics_spy($this->make_topic_stubs($topic_ids));
		$feedback_spy = $this->make_feedback_spy();

		$cleanup = new AIPS_Data_Cleanup($history_spy, $logs_spy, $topics_spy, $feedback_spy);
		$cleanup->on_before_delete_author($author_id);

		// Topics fetched for the author.
		$this->assertSame(array($author_id), $topics_spy->calls['get_by_author']);

		// Feedback deleted for those topic IDs.
		$this->assertCount(1, $feedback_spy->calls['delete_by_topic_ids']);
		$this->assertSame($topic_ids, $feedback_spy->calls['delete_by_topic_ids'][0]);

		// Topic logs deleted for those topic IDs.
		$this->assertCount(1, $logs_spy->calls['delete_by_topic_ids']);
		$this->assertSame($topic_ids, $logs_spy->calls['delete_by_topic_ids'][0]);

		// Topics deleted by author.
		$this->assertSame(array($author_id), $topics_spy->calls['delete_by_author']);

		// Deletable history container IDs fetched.
		$this->assertSame(array($author_id), $history_spy->calls['get_deletable_ids_by_author_id']);

		// History logs deleted for those containers.
		$this->assertCount(1, $history_spy->calls['delete_logs_by_history_ids']);
		$this->assertSame($history_ids, $history_spy->calls['delete_logs_by_history_ids'][0]);

		// History containers deleted.
		$this->assertCount(1, $history_spy->calls['delete_bulk']);
		$this->assertSame($history_ids, $history_spy->calls['delete_bulk'][0]);
	}

	/**
	 * @test
	 * Author with no topics: feedback/topic-log/topic deletions are skipped;
	 * history cleanup still runs.
	 */
	public function test_on_before_delete_author_no_topics() {
		$author_id = 8;

		$history_spy  = $this->make_history_spy(array(), array(500));
		$logs_spy     = $this->make_logs_spy();
		$topics_spy   = $this->make_topics_spy(array()); // no topics
		$feedback_spy = $this->make_feedback_spy();

		$cleanup = new AIPS_Data_Cleanup($history_spy, $logs_spy, $topics_spy, $feedback_spy);
		$cleanup->on_before_delete_author($author_id);

		// No feedback or topic-log deletions when there are no topics.
		$this->assertEmpty($feedback_spy->calls['delete_by_topic_ids']);
		$this->assertEmpty($logs_spy->calls['delete_by_topic_ids']);

		// delete_by_author still called on topics repo.
		$this->assertSame(array($author_id), $topics_spy->calls['delete_by_author']);

		// History cleanup still runs.
		$this->assertSame(array($author_id), $history_spy->calls['get_deletable_ids_by_author_id']);
		$this->assertCount(1, $history_spy->calls['delete_bulk']);
	}

	/**
	 * @test
	 * Author with no deletable history containers: history log/bulk deletions
	 * are skipped (no unnecessary queries).
	 */
	public function test_on_before_delete_author_no_deletable_history() {
		$author_id = 9;

		$history_spy  = $this->make_history_spy(array(), array()); // no deletable history
		$logs_spy     = $this->make_logs_spy();
		$topics_spy   = $this->make_topics_spy($this->make_topic_stubs(array(300)));
		$feedback_spy = $this->make_feedback_spy();

		$cleanup = new AIPS_Data_Cleanup($history_spy, $logs_spy, $topics_spy, $feedback_spy);
		$cleanup->on_before_delete_author($author_id);

		// History log and bulk deletion skipped.
		$this->assertEmpty($history_spy->calls['delete_logs_by_history_ids']);
		$this->assertEmpty($history_spy->calls['delete_bulk']);
	}

	/**
	 * @test
	 * A zero author_id is invalid; no deletions should occur.
	 */
	public function test_on_before_delete_author_ignores_zero_author_id() {
		$history_spy  = $this->make_history_spy();
		$logs_spy     = $this->make_logs_spy();
		$topics_spy   = $this->make_topics_spy();
		$feedback_spy = $this->make_feedback_spy();

		$cleanup = new AIPS_Data_Cleanup($history_spy, $logs_spy, $topics_spy, $feedback_spy);
		$cleanup->on_before_delete_author(0);

		$this->assertEmpty($topics_spy->calls['get_by_author']);
		$this->assertEmpty($feedback_spy->calls['delete_by_topic_ids']);
		$this->assertEmpty($logs_spy->calls['delete_by_topic_ids']);
		$this->assertEmpty($topics_spy->calls['delete_by_author']);
		$this->assertEmpty($history_spy->calls['get_deletable_ids_by_author_id']);
	}

	/**
	 * @test
	 * Firing aips_before_delete_author via do_action() triggers the handler.
	 */
	public function test_do_action_triggers_author_cleanup() {
		$author_id = 12;

		$history_spy  = $this->make_history_spy(array(), array(600));
		$topics_spy   = $this->make_topics_spy($this->make_topic_stubs(array(400)));
		$feedback_spy = $this->make_feedback_spy();
		$logs_spy     = $this->make_logs_spy();

		new AIPS_Data_Cleanup($history_spy, $logs_spy, $topics_spy, $feedback_spy);
		do_action('aips_before_delete_author', $author_id);

		$this->assertContains($author_id, $topics_spy->calls['get_by_author']);
		$this->assertContains($author_id, $topics_spy->calls['delete_by_author']);
		$this->assertContains($author_id, $history_spy->calls['get_deletable_ids_by_author_id']);
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
	 * get_ids_by_post_id returns an empty array for an invalid post ID.
	 */
	public function test_history_repository_get_ids_by_post_id_returns_empty_for_invalid_id() {
		$repo = new AIPS_History_Repository();
		$this->assertSame(array(), $repo->get_ids_by_post_id(0));
		$this->assertSame(array(), $repo->get_ids_by_post_id(-5));
	}

	/**
	 * @test
	 * get_ids_by_status returns an array for both filtered and unfiltered calls.
	 */
	public function test_history_repository_get_ids_by_status_returns_array() {
		$repo = new AIPS_History_Repository();

		$this->assertIsArray($repo->get_ids_by_status('completed'));
		$this->assertIsArray($repo->get_ids_by_status(''));
	}

	/**
	 * @test
	 * get_deletable_ids_by_author_id returns an array (empty when wpdb returns nothing).
	 */
	public function test_history_repository_get_deletable_ids_by_author_id_returns_array() {
		$repo   = new AIPS_History_Repository();
		$result = $repo->get_deletable_ids_by_author_id(9999);
		$this->assertIsArray($result);
	}

	/**
	 * @test
	 * get_deletable_ids_by_author_id returns empty for an invalid author ID.
	 */
	public function test_history_repository_get_deletable_ids_by_author_id_invalid() {
		$repo = new AIPS_History_Repository();
		$this->assertSame(array(), $repo->get_deletable_ids_by_author_id(0));
		$this->assertSame(array(), $repo->get_deletable_ids_by_author_id(-1));
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

	// ------------------------------------------------------------------
	// Repository: AIPS_Feedback_Repository – new method
	// ------------------------------------------------------------------

	/**
	 * @test
	 * delete_by_topic_ids returns 0 for an empty array.
	 */
	public function test_feedback_repository_delete_by_topic_ids_empty_input() {
		$repo   = new AIPS_Feedback_Repository();
		$result = $repo->delete_by_topic_ids(array());
		$this->assertSame(0, $result);
	}

	/**
	 * @test
	 * delete_by_topic_ids returns non-false for a valid input.
	 */
	public function test_feedback_repository_delete_by_topic_ids_valid_input() {
		$repo   = new AIPS_Feedback_Repository();
		$result = $repo->delete_by_topic_ids(array(1, 2, 3));
		$this->assertNotFalse($result);
	}
}
