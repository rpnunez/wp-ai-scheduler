<?php
/**
 * Tests for AIPS_Authors_Controller::ajax_save_author()
 *
 * Verifies that newly created authors have their initial next-run timestamps
 * set to the current time so the first scheduled execution is not skipped.
 *
 * @package AI_Post_Scheduler
 */

class AIPS_Authors_Controller_Save_Test extends WP_UnitTestCase {

	private $admin_user_id;
	private $subscriber_user_id;

	public function setUp(): void {
		parent::setUp();

		$this->admin_user_id      = $this->factory->user->create(array('role' => 'administrator'));
		$this->subscriber_user_id = $this->factory->user->create(array('role' => 'subscriber'));

		$_REQUEST['nonce'] = wp_create_nonce('aips_ajax_nonce');
	}

	public function tearDown(): void {
		$_POST    = array();
		$_REQUEST = array();

		parent::tearDown();
	}

	// =========================================================================
	// Helpers
	// =========================================================================

	/**
	 * Build a wpdb proxy that captures data from insert() and update() calls
	 * on the aips_authors table (ignores aips_author_topics* tables).
	 *
	 * The returned proxy stores captured data in its public $inserted and
	 * $updated properties.
	 *
	 * @return object Anonymous-class proxy wrapping global $wpdb.
	 */
	private function make_capturing_wpdb() {
		global $wpdb;
		$delegate = $wpdb;

		return new class($delegate) {
			/** @var object Delegate wpdb instance. */
			private $delegate;

			/** @var string Table prefix forwarded from delegate. */
			public $prefix;

			/** @var int Last insert ID, forwarded from delegate. */
			public $insert_id = 0;

			/** @var array|null Data array passed to the most recent insert() on aips_authors. */
			public $inserted = null;

			/** @var array|null Data array passed to the most recent update() on aips_authors. */
			public $updated = null;

			public function __construct($delegate) {
				$this->delegate = $delegate;
				$this->prefix   = $delegate->prefix;
			}

			public function insert($table, $data, $format = null) {
				if ($table === $this->prefix . 'aips_authors') {
					$this->inserted = $data;
				}
				$result          = $this->delegate->insert($table, $data, $format);
				$this->insert_id = $this->delegate->insert_id;
				return $result;
			}

			public function update($table, $data, $where, $format = null, $where_format = null) {
				if ($table === $this->prefix . 'aips_authors') {
					$this->updated = $data;
				}
				return $this->delegate->update($table, $data, $where, $format, $where_format);
			}

			/* --- Forward all other wpdb methods to the delegate --- */

			public function prepare($query, ...$args) {
				return $this->delegate->prepare($query, ...$args);
			}

			public function get_row($query, $output = OBJECT, $y = 0) {
				return $this->delegate->get_row($query, $output, $y);
			}

			public function get_results($query, $output = OBJECT) {
				return $this->delegate->get_results($query, $output);
			}

			public function get_var($query, $x = 0, $y = 0) {
				return $this->delegate->get_var($query, $x, $y);
			}

			public function delete($table, $where, $where_format = null) {
				return $this->delegate->delete($table, $where, $where_format);
			}

			public function query($query) {
				return $this->delegate->query($query);
			}

			public function get_charset_collate() {
				return $this->delegate->get_charset_collate();
			}

			public function get_col($query = null, $x = 0) {
				return $this->delegate->get_col($query, $x);
			}

			public function esc_like($text) {
				return $this->delegate->esc_like($text);
			}
		};
	}

	/**
	 * Capture JSON output produced by a controller AJAX method.
	 *
	 * @param callable $callable Controller method to invoke.
	 * @return array Decoded response array.
	 */
	private function capture_ajax(callable $callable) {
		ob_start();
		try {
			$callable();
		} catch (WPAjaxDieContinueException $e) {
			// Expected after wp_send_json_*.
		}
		return json_decode(ob_get_clean(), true);
	}

	// =========================================================================
	// New author: initial next_run timestamps
	// =========================================================================

	/**
	 * When saving a new author (no author_id), both *_next_run fields must be
	 * set to the current time so the first execution is not skipped.
	 */
	public function test_new_author_next_run_set_to_now() {
		wp_set_current_user($this->admin_user_id);

		global $wpdb;
		$original_wpdb = $wpdb;

		// Replace global $wpdb with the capturing proxy before instantiating
		// the controller, so the repository inside it picks up the proxy.
		$proxy = $this->make_capturing_wpdb();
		$wpdb  = $proxy;

		try {
			$controller = new AIPS_Authors_Controller();

			$before = current_time('mysql');

			$_POST = array(
				'nonce'                      => wp_create_nonce('aips_ajax_nonce'),
				'name'                       => 'Test Author New',
				'field_niche'                => 'Technology',
				'topic_generation_frequency' => 'weekly',
				'post_generation_frequency'  => 'daily',
			);

			$response = $this->capture_ajax(array($controller, 'ajax_save_author'));

			$after = current_time('mysql');
		} finally {
			// Always restore original wpdb, even if an exception is thrown.
			$wpdb = $original_wpdb;
		}
		$this->assertTrue($response['success'], 'Expected success response for new author save.');

		$this->assertNotNull(
			$proxy->inserted,
			'Expected $wpdb->insert() to be called for a new author.'
		);

		$topic_next_run = $proxy->inserted['topic_generation_next_run'];
		$post_next_run  = $proxy->inserted['post_generation_next_run'];

		// Both timestamps must fall within the window [$before, $after].
		$this->assertGreaterThanOrEqual(
			$before,
			$topic_next_run,
			'topic_generation_next_run should not be before the test start time.'
		);
		$this->assertLessThanOrEqual(
			$after,
			$topic_next_run,
			'topic_generation_next_run should not be after the test end time.'
		);
		$this->assertGreaterThanOrEqual(
			$before,
			$post_next_run,
			'post_generation_next_run should not be before the test start time.'
		);
		$this->assertLessThanOrEqual(
			$after,
			$post_next_run,
			'post_generation_next_run should not be after the test end time.'
		);

		// Both timestamps must be identical because they come from a single $now.
		$this->assertEquals(
			$topic_next_run,
			$post_next_run,
			'topic_generation_next_run and post_generation_next_run must be identical for a new author.'
		);
	}

	/**
	 * Updating an existing author must NOT include *_next_run fields in the
	 * update payload (those fields belong to the scheduler, not the editor).
	 */
	public function test_existing_author_next_run_not_included_in_update() {
		wp_set_current_user($this->admin_user_id);

		global $wpdb;
		$original_wpdb = $wpdb;

		$proxy = $this->make_capturing_wpdb();
		$wpdb  = $proxy;

		try {
			$controller = new AIPS_Authors_Controller();

			$_POST = array(
				'nonce'                      => wp_create_nonce('aips_ajax_nonce'),
				'author_id'                  => 42,
				'name'                       => 'Test Author Existing',
				'field_niche'                => 'Science',
				'topic_generation_frequency' => 'weekly',
				'post_generation_frequency'  => 'daily',
			);

			$response = $this->capture_ajax(array($controller, 'ajax_save_author'));
		} finally {
			// Always restore original wpdb, even if an exception is thrown.
			$wpdb = $original_wpdb;
		}

		$this->assertTrue($response['success'], 'Expected success response for author update.');

		$this->assertNotNull(
			$proxy->updated,
			'Expected $wpdb->update() to be called when updating an existing author.'
		);

		$this->assertArrayNotHasKey(
			'topic_generation_next_run',
			$proxy->updated,
			'topic_generation_next_run must not be overwritten during an update.'
		);
		$this->assertArrayNotHasKey(
			'post_generation_next_run',
			$proxy->updated,
			'post_generation_next_run must not be overwritten during an update.'
		);
	}

	// =========================================================================
	// Permission and validation guards
	// =========================================================================

	/**
	 * Non-admin users must receive a permission-denied error.
	 */
	public function test_save_author_permission_denied() {
		wp_set_current_user($this->subscriber_user_id);

		global $wpdb;
		$original_wpdb = $wpdb;
		$proxy = $this->make_capturing_wpdb();
		$wpdb  = $proxy;
		$controller = new AIPS_Authors_Controller();
		$wpdb = $original_wpdb;

		$_POST = array(
			'nonce'       => wp_create_nonce('aips_ajax_nonce'),
			'name'        => 'Test Author Subscriber',
			'field_niche' => 'Health',
		);

		$response = $this->capture_ajax(array($controller, 'ajax_save_author'));

		$this->assertFalse($response['success']);
		$this->assertEquals('Permission denied.', $response['data']['message']);
	}

	/**
	 * Missing required fields (name and field_niche) must return an error.
	 */
	public function test_save_author_missing_required_fields() {
		wp_set_current_user($this->admin_user_id);

		global $wpdb;
		$original_wpdb = $wpdb;
		$proxy = $this->make_capturing_wpdb();
		$wpdb  = $proxy;
		$controller = new AIPS_Authors_Controller();
		$wpdb = $original_wpdb;

		$_POST = array(
			'nonce' => wp_create_nonce('aips_ajax_nonce'),
			'name'  => '',
		);

		$response = $this->capture_ajax(array($controller, 'ajax_save_author'));

		$this->assertFalse($response['success']);
	}
}
