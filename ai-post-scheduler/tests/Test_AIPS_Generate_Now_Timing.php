<?php
/**
 * Tests for AIPS_Author_Post_Generator::generate_now() timing / post-meta behaviour.
 *
 * Verifies that:
 * - On a successful generation the `_aips_post_generation_total_time` post meta
 *   is stored on the returned post ID.
 * - On a failed generation (WP_Error return) no meta is written.
 *
 * @package AI_Post_Scheduler
 */

class Test_AIPS_Generate_Now_Timing extends WP_UnitTestCase {

	/** @var AIPS_Author_Post_Generator */
	private $post_generator;

	/** @var ReflectionClass */
	private $reflection;

	public function setUp(): void {
		parent::setUp();

		global $aips_test_meta;
		$aips_test_meta = array();

		$this->post_generator = new AIPS_Author_Post_Generator();
		$this->reflection     = new ReflectionClass($this->post_generator);
	}

	public function tearDown(): void {
		global $aips_test_meta;
		$aips_test_meta = array();

		parent::tearDown();
	}

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	/**
	 * Inject a value into a private/protected property via reflection.
	 *
	 * @param string $name  Property name.
	 * @param mixed  $value Value to inject.
	 */
	private function inject($name, $value) {
		$prop = $this->reflection->getProperty($name);
		$prop->setAccessible(true);
		$prop->setValue($this->post_generator, $value);
	}

	/**
	 * Build the standard set of mock dependencies that let generate_now()
	 * resolve a topic and author then delegate to a generator mock.
	 *
	 * @param int|WP_Error $generator_return Value the mock generator returns for generate_post().
	 */
	private function inject_mocks($generator_return) {
		$topics_repo = new class {
			public function get_by_id($id) {
				return (object) array(
					'id'          => $id,
					'author_id'   => 1,
					'topic_title' => 'Test topic',
					'status'      => 'approved',
				);
			}
		};

		$authors_repo = new class {
			public function get_by_id($id) {
				return (object) array(
					'id'         => $id,
					'name'       => 'Test Author',
					'field_niche' => 'Technology',
					'post_status' => 'draft',
					'post_category' => 1,
					'post_tags'  => '',
					'post_author' => 1,
					'generate_featured_image' => 0,
					'featured_image_source'   => 'ai_prompt',
					'article_structure_id'    => null,
					'is_active'  => 1,
				);
			}
		};

		$logs_repo = new class {
			public function log_post_generation($topic_id, $post_id, $metadata) {}
		};

		$history_service = new class {
			public function create($type, $data = array()) {
				return new class {
					public function get_id() { return 1; }
					public function with_session($ctx) { return $this; }
					public function record($type, $msg, $a = null, $b = null, $c = array()) {}
					public function record_error($msg, $a = array(), $e = null) {}
					public function complete_success($data = array()) {}
					public function complete_failure($msg, $data = array()) {}
				};
			}
		};

		$expansion_service = new class {
			public function get_expanded_context($author_id, $topic_id, $limit = 5) {
				return '';
			}
		};

		$interval_calculator = new class {
			public function calculate_next_run($freq) {
				return gmdate('Y-m-d H:i:s', strtotime('+1 day'));
			}
		};

		$return_val = $generator_return;
		$generator = new class($return_val) {
			private $return_val;
			public function __construct($return_val) { $this->return_val = $return_val; }
			public function generate_post($ctx) { return $this->return_val; }
			public function is_available() { return true; }
		};

		$logger = new class {
			public function log($msg, $level = 'info', $ctx = array()) {}
		};

		$this->inject('topics_repository',   $topics_repo);
		$this->inject('authors_repository',  $authors_repo);
		$this->inject('logs_repository',     $logs_repo);
		$this->inject('history_service',     $history_service);
		$this->inject('expansion_service',   $expansion_service);
		$this->inject('interval_calculator', $interval_calculator);
		$this->inject('generator',           $generator);
		$this->inject('logger',              $logger);
	}

	// -------------------------------------------------------------------------
	// Tests
	// -------------------------------------------------------------------------

	/**
	 * When generation succeeds, _aips_post_generation_total_time must be stored.
	 */
	public function test_generate_now_stores_elapsed_time_on_success() {
		global $aips_test_meta;

		$post_id = 42;
		$this->inject_mocks($post_id);

		$result = $this->post_generator->generate_now(1);

		$this->assertSame($post_id, $result, 'generate_now should return the post ID on success');
		$this->assertArrayHasKey($post_id, $aips_test_meta, 'Post meta should be written for the returned post ID');
		$this->assertArrayHasKey(
			'_aips_post_generation_total_time',
			$aips_test_meta[$post_id],
			'_aips_post_generation_total_time meta key should be present'
		);
		$this->assertGreaterThanOrEqual(
			0,
			$aips_test_meta[$post_id]['_aips_post_generation_total_time'],
			'Recorded time should be a non-negative number'
		);
	}

	/**
	 * When generation fails (WP_Error), _aips_post_generation_total_time must NOT be stored.
	 */
	public function test_generate_now_does_not_store_time_on_failure() {
		global $aips_test_meta;

		$error = new WP_Error('generation_failed', 'Something went wrong');
		$this->inject_mocks($error);

		$result = $this->post_generator->generate_now(1);

		$this->assertInstanceOf('WP_Error', $result, 'generate_now should return a WP_Error on failure');
		$this->assertEmpty($aips_test_meta, '_aips_post_generation_total_time should not be written on failure');
	}

	/**
	 * When the topic is not found, generate_now returns a WP_Error and writes no meta.
	 */
	public function test_generate_now_returns_error_when_topic_not_found() {
		global $aips_test_meta;

		$topics_repo = new class {
			public function get_by_id($id) { return null; }
		};
		$this->inject('topics_repository', $topics_repo);

		$result = $this->post_generator->generate_now(999);

		$this->assertInstanceOf('WP_Error', $result);
		$this->assertSame('invalid_topic', $result->get_error_code());
		$this->assertEmpty($aips_test_meta, 'No meta should be written when topic is not found');
	}

	/**
	 * When the author is not found, generate_now returns a WP_Error and writes no meta.
	 */
	public function test_generate_now_returns_error_when_author_not_found() {
		global $aips_test_meta;

		$topics_repo = new class {
			public function get_by_id($id) {
				return (object) array('id' => $id, 'author_id' => 99, 'topic_title' => 'Test', 'status' => 'approved');
			}
		};
		$authors_repo = new class {
			public function get_by_id($id) { return null; }
		};

		$this->inject('topics_repository',  $topics_repo);
		$this->inject('authors_repository', $authors_repo);

		$result = $this->post_generator->generate_now(1);

		$this->assertInstanceOf('WP_Error', $result);
		$this->assertSame('invalid_author', $result->get_error_code());
		$this->assertEmpty($aips_test_meta, 'No meta should be written when author is not found');
	}
}
