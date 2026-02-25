<?php
/**
 * Test Author Topics Repository Pagination
 *
 * @package AI_Post_Scheduler
 */

class AIPS_Author_Topics_Repository_Pagination_Test extends WP_UnitTestCase {

	private $original_wpdb;
	private $mock_wpdb;
	private $repository;

	public function setUp(): void {
		parent::setUp();

		// Save original wpdb
		if (isset($GLOBALS['wpdb'])) {
			$this->original_wpdb = $GLOBALS['wpdb'];
		}

		// Create mock wpdb
		$this->mock_wpdb = $this->getMockBuilder('stdClass')
			->setMethods(array('prepare', 'get_results', 'get_var', 'esc_like'))
			->getMock();

		$this->mock_wpdb->prefix = 'wp_';

		// Set up default behaviors
		$this->mock_wpdb->expects($this->any())
			->method('esc_like')
			->will($this->returnCallback(function($text) { return $text; }));

		$GLOBALS['wpdb'] = $this->mock_wpdb;

		$this->repository = new AIPS_Author_Topics_Repository();
	}

	public function tearDown(): void {
		// Restore original wpdb
		if (isset($this->original_wpdb)) {
			$GLOBALS['wpdb'] = $this->original_wpdb;
		}
		parent::tearDown();
	}

	public function test_get_by_author_pagination() {
		// Expect prepare to be called with SQL containing LIMIT and OFFSET
		$this->mock_wpdb->expects($this->once())
			->method('prepare')
			->with(
				$this->stringContains('LIMIT %d OFFSET %d'),
				$this->callback(function($args) {
					// Verify args contains limit and offset
					// The args array structure depends on how prepare is called (array vs variable args)
					// In the repo: $this->wpdb->prepare($sql, $query_args) where $query_args is an array
					// So $args here will be [sql, query_args_array] ?? No, prepare signature is ($query, ...$args)
					// But WP 3.5+ supports prepare($query, $args_array).
					// The MockWPDB in bootstrap supports ...$args.
					// PHPUnit mock will receive the arguments passed to prepare.

					// The repository calls: $this->wpdb->prepare($sql, $query_args)
					// So the second argument should be the array of values.

					$params = $args; // In repo: $query_args is the second arg to prepare

					// We expect: [author_id, limit, offset]
					return isset($params[1]) && $params[1] == 10 && $params[2] == 20;
				})
			)
			->will($this->returnValue('SELECT * FROM wp_aips_author_topics ... LIMIT 10 OFFSET 20'));

		$this->mock_wpdb->expects($this->once())
			->method('get_results')
			->willReturn(array());

		$this->repository->get_by_author(1, array('limit' => 10, 'offset' => 20));
	}

	public function test_get_by_author_search() {
		// Expect prepare to be called with SQL containing LIKE
		$this->mock_wpdb->expects($this->once())
			->method('prepare')
			->with(
				$this->stringContains('topic_title LIKE %s'),
				$this->callback(function($params) {
					// We expect: [author_id, %search%, %search%]
					return isset($params[1]) && strpos($params[1], 'term') !== false;
				})
			)
			->will($this->returnValue('SELECT ... LIKE ...'));

		$this->repository->get_by_author(1, array('search' => 'term'));
	}

	public function test_count_by_author() {
		$this->mock_wpdb->expects($this->once())
			->method('prepare')
			->will($this->returnArgument(0)); // Return the SQL string

		$this->mock_wpdb->expects($this->once())
			->method('get_var')
			->with(
				$this->stringContains('SELECT COUNT(*)')
			)
			->willReturn(5);

		$count = $this->repository->count_by_author(1);
		$this->assertEquals(5, $count);
	}
}
