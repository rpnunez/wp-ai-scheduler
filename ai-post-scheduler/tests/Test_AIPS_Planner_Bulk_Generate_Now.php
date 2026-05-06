<?php
/**
 * Tests for AIPS_Planner::ajax_bulk_generate_now()
 *
 * Covers:
 *  - Nonce validation failure
 *  - Permission (capability) failure
 *  - Missing topics / template_id input validation
 *  - Empty topics after sanitization
 *  - Bulk limit enforcement (default and zero-fallback)
 *  - AI Engine unavailable early exit
 *  - All-failure response shape (wp_send_json_error with errors key)
 *  - Full-success response shape (post_ids + empty errors)
 *  - Partial-success response shape (post_ids + non-empty errors)
 *
 * @package AI_Post_Scheduler
 */

/**
 * Testable subclass that allows injecting a mock generator and template.
 */
class Test_AIPS_Planner_Testable extends AIPS_Planner {

	/** @var object|null Mock generator to return from make_generator(). */
	public $mock_generator = null;

	/** @var object|null Mock template to return from get_template_by_id(). */
	public $mock_template = null;

	protected function make_generator() {
		return $this->mock_generator ?: parent::make_generator();
	}

	protected function get_template_by_id( $template_id ) {
		return $this->mock_template;
	}
}

class Test_AIPS_Planner_Bulk_Generate_Now extends WP_UnitTestCase {

	/** @var Test_AIPS_Planner_Testable */
	private $planner;

	public function setUp(): void {
		parent::setUp();

		$this->planner = new Test_AIPS_Planner_Testable();

		$_POST    = array();
		$_REQUEST = array();
	}

	public function tearDown(): void {
		$_POST    = array();
		$_REQUEST = array();

		parent::tearDown();
	}

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	/**
	 * Set up valid $_POST data including a correct nonce.
	 *
	 * @param array $overrides Additional $_POST keys.
	 */
	private function set_valid_post( array $overrides = array() ) {
		$nonce             = wp_create_nonce('aips_ajax_nonce');
		$_POST['nonce']    = $nonce;
		$_REQUEST['nonce'] = $nonce;
		$_POST             = array_merge($_POST, $overrides);
	}

	/**
	 * Grant admin privileges to the current virtual user.
	 */
	private function set_admin_user() {
		global $current_user_id, $test_users;
		if (!isset($test_users)) {
			$test_users = array();
		}
		$current_user_id = 1;
		$test_users[1]   = 'administrator';
	}

	/**
	 * Grant subscriber (non-admin) privileges to the current virtual user.
	 */
	private function set_subscriber_user() {
		global $current_user_id, $test_users;
		if (!isset($test_users)) {
			$test_users = array();
		}
		$current_user_id = 2;
		$test_users[2]   = 'subscriber';
	}

	/**
	 * Build a minimal template stub object.
	 *
	 * @return object
	 */
	private function make_template() {
		return (object) array(
			'id'              => 1,
			'name'            => 'Test Template',
			'prompt_template' => 'Write about {topic}',
			'is_active'       => 1,
		);
	}

	/**
	 * Build a mock generator that reports the given availability and returns
	 * the provided value (post ID or WP_Error) for every generate_post() call.
	 *
	 * @param bool          $available
	 * @param int|WP_Error  $return_value
	 * @return object
	 */
	private function make_generator( $available, $return_value ) {
		return new class($available, $return_value) {
			private $available;
			private $return_value;
			public function __construct( $available, $return_value ) {
				$this->available    = $available;
				$this->return_value = $return_value;
			}
			public function is_available() { return $this->available; }
			public function generate_post( $template, $author, $topic ) { return $this->return_value; }
		};
	}

	/**
	 * Invoke $callable, capture JSON output, and return the decoded array.
	 *
	 * @param callable $callable
	 * @return array
	 */
	private function capture_json( $callable ) {
		ob_start();
		try {
			$callable();
		} catch ( WPAjaxDieContinueException $e ) {
			// Expected path when wp_send_json_* is called.
		}
		$output = ob_get_clean();
		$decoded = json_decode($output, true);
		$this->assertIsArray($decoded, 'Response must be valid JSON. Got: ' . $output);
		return $decoded;
	}

	// -------------------------------------------------------------------------
	// Security tests
	// -------------------------------------------------------------------------

	/**
	 * An invalid nonce must terminate the request before any output is produced.
	 */
	public function test_invalid_nonce_throws_exception() {
		$this->set_admin_user();
		$_POST['nonce']    = 'bad_nonce';
		$_REQUEST['nonce'] = 'bad_nonce';

		$exception_thrown = false;
		ob_start();
		try {
			$this->planner->ajax_bulk_generate_now();
		} catch ( WPAjaxDieStopException $e ) {
			$exception_thrown = true;
		} catch ( WPAjaxDieContinueException $e ) {
			$exception_thrown = true;
		}
		ob_end_clean();

		$this->assertTrue($exception_thrown, 'Request must be terminated when nonce is invalid');
	}

	/**
	 * A non-admin user must receive a permission denied error.
	 */
	public function test_permission_denied_for_non_admin() {
		$this->set_subscriber_user();
		$this->set_valid_post(array(
			'topics'      => array('Topic A'),
			'template_id' => 1,
		));

		$response = $this->capture_json(array($this->planner, 'ajax_bulk_generate_now'));

		$this->assertFalse($response['success']);
		$this->assertStringContainsStringIgnoringCase('permission', $response['data']['message']);
	}

	// -------------------------------------------------------------------------
	// Input validation tests
	// -------------------------------------------------------------------------

	/**
	 * Omitting topics must return an error.
	 */
	public function test_missing_topics_returns_error() {
		$this->set_admin_user();
		$this->set_valid_post(array('template_id' => 1));

		$response = $this->capture_json(array($this->planner, 'ajax_bulk_generate_now'));

		$this->assertFalse($response['success']);
		$this->assertArrayHasKey('message', $response['data']);
	}

	/**
	 * Omitting template_id must return an error.
	 */
	public function test_missing_template_id_returns_error() {
		$this->set_admin_user();
		$this->set_valid_post(array('topics' => array('Topic A')));

		$response = $this->capture_json(array($this->planner, 'ajax_bulk_generate_now'));

		$this->assertFalse($response['success']);
		$this->assertArrayHasKey('message', $response['data']);
	}

	/**
	 * Topics that are empty strings after sanitization must be filtered out,
	 * leaving no valid topics and causing a missing-required-fields error.
	 */
	public function test_empty_topics_after_sanitize_returns_error() {
		$this->set_admin_user();
		$this->set_valid_post(array(
			'topics'      => array('', '   '),
			'template_id' => 1,
		));

		$response = $this->capture_json(array($this->planner, 'ajax_bulk_generate_now'));

		$this->assertFalse($response['success']);
		$this->assertArrayHasKey('message', $response['data']);
	}

	// -------------------------------------------------------------------------
	// Bulk limit tests
	// -------------------------------------------------------------------------

	/**
	 * Selecting more topics than the default limit (5) must return an error
	 * that includes the count of selected topics.
	 */
	public function test_bulk_limit_exceeded_returns_error() {
		$this->set_admin_user();
		$this->set_valid_post(array(
			'topics'      => array('T1', 'T2', 'T3', 'T4', 'T5', 'T6'),
			'template_id' => 1,
		));

		$response = $this->capture_json(array($this->planner, 'ajax_bulk_generate_now'));

		$this->assertFalse($response['success']);
		$this->assertStringContainsString('6', $response['data']['message']);
	}

	/**
	 * A filter returning 0 must fall back to the default limit (5), so
	 * 6 selected topics still triggers the limit error.
	 */
	public function test_zero_bulk_limit_falls_back_to_default() {
		$this->set_admin_user();

		add_filter('aips_bulk_run_now_limit', function() { return 0; });

		$this->set_valid_post(array(
			'topics'      => array('T1', 'T2', 'T3', 'T4', 'T5', 'T6'),
			'template_id' => 1,
		));

		$response = $this->capture_json(array($this->planner, 'ajax_bulk_generate_now'));

		remove_all_filters('aips_bulk_run_now_limit');

		$this->assertFalse($response['success']);
		$this->assertArrayHasKey('message', $response['data']);
	}

	// -------------------------------------------------------------------------
	// AI Engine availability test
	// -------------------------------------------------------------------------

	/**
	 * When AI Engine is unavailable the handler must return an error before
	 * attempting any generation.
	 */
	public function test_ai_engine_unavailable_returns_error() {
		$this->set_admin_user();

		$this->planner->mock_generator = $this->make_generator(false, 0);
		$this->planner->mock_template  = $this->make_template();

		$this->set_valid_post(array(
			'topics'      => array('Topic A'),
			'template_id' => 1,
		));

		$response = $this->capture_json(array($this->planner, 'ajax_bulk_generate_now'));

		$this->assertFalse($response['success']);
		$this->assertStringContainsStringIgnoringCase('AI Engine', $response['data']['message']);
	}

	// -------------------------------------------------------------------------
	// Response shape tests
	// -------------------------------------------------------------------------

	/**
	 * Full success: every topic generates a post.
	 * Response must be success=true with non-empty post_ids and empty errors.
	 */
	public function test_full_success_response_shape() {
		$this->set_admin_user();

		$this->planner->mock_generator = $this->make_generator(true, 42);
		$this->planner->mock_template  = $this->make_template();

		$this->set_valid_post(array(
			'topics'      => array('Topic A', 'Topic B'),
			'template_id' => 1,
		));

		$response = $this->capture_json(array($this->planner, 'ajax_bulk_generate_now'));

		$this->assertTrue($response['success']);
		$this->assertIsArray($response['data']['post_ids']);
		$this->assertCount(2, $response['data']['post_ids']);
		$this->assertIsArray($response['data']['errors']);
		$this->assertEmpty($response['data']['errors']);
		$this->assertArrayHasKey('message', $response['data']);
	}

	/**
	 * All failures: every generate_post call returns WP_Error.
	 * Response must be success=false with a non-empty errors array.
	 */
	public function test_all_failures_returns_error_with_errors_key() {
		$this->set_admin_user();

		$this->planner->mock_generator = $this->make_generator(
			true,
			new WP_Error('gen_failed', 'AI call failed')
		);
		$this->planner->mock_template = $this->make_template();

		$this->set_valid_post(array(
			'topics'      => array('Topic A', 'Topic B'),
			'template_id' => 1,
		));

		$response = $this->capture_json(array($this->planner, 'ajax_bulk_generate_now'));

		$this->assertFalse($response['success']);
		$this->assertArrayHasKey('errors', $response['data']);
		$this->assertNotEmpty($response['data']['errors']);
	}

	/**
	 * Partial success: alternating success / failure per topic.
	 * Response must be success=true with both non-empty post_ids and errors.
	 */
	public function test_partial_success_response_shape() {
		$this->set_admin_user();

		// Override with a generator that alternates success/failure.
		$this->planner->mock_generator = new class {
			private $call = 0;
			public function is_available() { return true; }
			public function generate_post( $template, $author, $topic ) {
				$this->call++;
				if ($this->call % 2 === 0) {
					return new WP_Error('gen_failed', 'Generation error');
				}
				return $this->call * 10;
			}
		};
		$this->planner->mock_template = $this->make_template();

		$this->set_valid_post(array(
			'topics'      => array('Topic A', 'Topic B'),
			'template_id' => 1,
		));

		$response = $this->capture_json(array($this->planner, 'ajax_bulk_generate_now'));

		$this->assertTrue($response['success']);
		$this->assertNotEmpty($response['data']['post_ids']);
		$this->assertNotEmpty($response['data']['errors']);
	}
}
