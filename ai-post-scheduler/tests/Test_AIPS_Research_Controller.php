<?php
/**
 * AJAX contract tests for AIPS_Research_Controller.
 *
 * Verifies authentication, nonces, input validation, response shape,
 * lifecycle state transitions (research topics, retrieval, scheduling, deletion),
 * gap analysis, and sources research.
 *
 * @package AI_Post_Scheduler
 */

class Test_AIPS_Research_Controller extends WP_UnitTestCase {

	/**
	 * @var int
	 */
	private $admin_user_id;

	/**
	 * @var int
	 */
	private $subscriber_user_id;

	/**
	 * @var AIPS_Research_Controller
	 */
	private $controller;

	/**
	 * @var AIPS_Trending_Topics_Repository
	 */
	private $repository;

	public function setUp(): void {
		parent::setUp();

		$this->admin_user_id = $this->factory->user->create(array('role' => 'administrator'));
		$this->subscriber_user_id = $this->factory->user->create(array('role' => 'subscriber'));

		wp_set_current_user($this->admin_user_id);

		$_POST = array();
		$_GET = array();
		$_REQUEST = array();

		$this->repository = new AIPS_Trending_Topics_Repository();
		$this->controller = new AIPS_Research_Controller(null, $this->repository);
	}

	public function tearDown(): void {
		$_POST = array();
		$_GET = array();
		$_REQUEST = array();

		AIPS_Container::get_instance()->clear();
		AI_Post_Scheduler::get_instance()->register_container_bindings();

		parent::tearDown();
	}

	/**
	 * Helper to capture JSON response from an AJAX action.
	 */
	private function capture_ajax(callable $callable): array {
		ob_start();

		try {
			$callable();
		} catch (WPAjaxDieContinueException $e) {
			// Expected.
		} catch (WPAjaxDieStopException $e) {
			// Expected.
		}

		$output = ob_get_clean();
		$decoded = json_decode($output, true);

		return is_array($decoded) ? $decoded : array('raw' => $output);
	}

	/**
	 * Test that endpoints fail on invalid nonce.
	 */
	public function test_endpoints_fail_on_invalid_nonce() {
		$_POST = array('nonce' => 'invalid_nonce_value');
		$_REQUEST = $_POST;

		$response = $this->capture_ajax(array($this->controller, 'ajax_research_topics'));
		$this->assertFalse($response['success']);
		$this->assertSame('Invalid nonce.', $response['data']['message']);

		$response = $this->capture_ajax(array($this->controller, 'ajax_get_trending_topics'));
		$this->assertFalse($response['success']);
		$this->assertSame('Invalid nonce.', $response['data']['message']);

		$response = $this->capture_ajax(array($this->controller, 'ajax_schedule_trending_topics'));
		$this->assertFalse($response['success']);
		$this->assertSame('Invalid nonce.', $response['data']['message']);
	}

	/**
	 * Test that endpoints fail on unauthorized user.
	 */
	public function test_endpoints_fail_on_unauthorized_user() {
		wp_set_current_user($this->subscriber_user_id);

		$_POST = array('nonce' => wp_create_nonce('aips_ajax_nonce'));
		$_REQUEST = $_POST;

		$response = $this->capture_ajax(array($this->controller, 'ajax_research_topics'));
		$this->assertFalse($response['success']);
		$this->assertSame('permission_denied', $response['data']['code']);

		$response = $this->capture_ajax(array($this->controller, 'ajax_get_trending_topics'));
		$this->assertFalse($response['success']);
		$this->assertSame('permission_denied', $response['data']['code']);

		$response = $this->capture_ajax(array($this->controller, 'ajax_schedule_trending_topics'));
		$this->assertFalse($response['success']);
		$this->assertSame('permission_denied', $response['data']['code']);
	}

	/**
	 * Test input validation on research endpoints.
	 */
	public function test_input_validation() {
		$nonce = wp_create_nonce('aips_ajax_nonce');

		// 1. Research topics without niche
		$_POST = array('nonce' => $nonce);
		$_REQUEST = $_POST;
		$response = $this->capture_ajax(array($this->controller, 'ajax_research_topics'));
		$this->assertFalse($response['success']);
		$this->assertSame('Niche is required.', $response['data']['message']);

		// 2. Delete trending topic without topic_id
		$response = $this->capture_ajax(array($this->controller, 'ajax_delete_trending_topic'));
		$this->assertFalse($response['success']);
		$this->assertSame('Topic ID is required.', $response['data']['message']);

		// 3. Delete bulk without topic_ids
		$response = $this->capture_ajax(array($this->controller, 'ajax_delete_trending_topic_bulk'));
		$this->assertFalse($response['success']);
		$this->assertSame('Topic IDs are required.', $response['data']['message']);

		// 4. Schedule without required fields
		$response = $this->capture_ajax(array($this->controller, 'ajax_schedule_trending_topics'));
		$this->assertFalse($response['success']);
		$this->assertSame('Missing required fields.', $response['data']['message']);

		// 5. Schedule with invalid frequency
		$_POST = array(
			'nonce'       => $nonce,
			'topic_ids'   => array(1),
			'template_id' => 1,
			'start_date'  => '2026-11-01 10:00:00',
			'frequency'   => 'invalid_freq',
		);
		$_REQUEST = $_POST;
		$response = $this->capture_ajax(array($this->controller, 'ajax_schedule_trending_topics'));
		$this->assertFalse($response['success']);

		// 6. Gap analysis without niche
		$_POST = array('nonce' => $nonce);
		$_REQUEST = $_POST;
		$response = $this->capture_ajax(array($this->controller, 'ajax_perform_gap_analysis'));
		$this->assertFalse($response['success']);
		$this->assertSame('Niche is required.', $response['data']['message']);

		// 7. Topics from gap without gap_topic or niche
		$response = $this->capture_ajax(array($this->controller, 'ajax_generate_topics_from_gap'));
		$this->assertFalse($response['success']);
		$this->assertSame('Gap topic and niche are required.', $response['data']['message']);

		// 8. Research from sources without term_ids
		$response = $this->capture_ajax(array($this->controller, 'ajax_research_from_sources'));
		$this->assertFalse($response['success']);
		$this->assertSame('At least one source group is required.', $response['data']['message']);
	}

	/**
	 * Test research execution and topic retrieval workflow.
	 */
	public function test_research_topics_and_get_trending_topics() {
		$mock_service = $this->getMockBuilder('AIPS_Research_Service')
			->disableOriginalConstructor()
			->onlyMethods(array('research_trending_topics', 'get_top_topics'))
			->getMock();

		$mock_topics = array(
			array(
				'topic'            => 'AI in Healthcare 2026',
				'score'            => 85,
				'volume'           => 1200,
				'difficulty'       => 'medium',
				'keywords'         => array('ai', 'health'),
				'suggested_titles' => array('How AI transforms healthcare'),
			),
		);

		$mock_service->method('research_trending_topics')->willReturn($mock_topics);
		$mock_service->method('get_top_topics')->willReturn($mock_topics);

		$mock_repo = $this->getMockBuilder('AIPS_Trending_Topics_Repository')
			->disableOriginalConstructor()
			->onlyMethods(array('save_research_batch', 'get_all', 'get_generated_post_counts', 'get_stats', 'get_niche_list'))
			->getMock();

		$mock_repo->method('save_research_batch')->willReturn(1);
		$mock_repo->method('get_all')->willReturn(array(
			array(
				'id'       => 101,
				'topic'    => 'AI in Healthcare 2026',
				'keywords' => json_encode(array('ai', 'health')),
				'status'   => 'new',
			),
		));
		$mock_repo->method('get_generated_post_counts')->willReturn(array(101 => 0));
		$mock_repo->method('get_stats')->willReturn(array('total_topics' => 1));
		$mock_repo->method('get_niche_list')->willReturn(array('Healthcare AI'));

		$ctrl = new AIPS_Research_Controller($mock_service, $mock_repo);
		$nonce = wp_create_nonce('aips_ajax_nonce');

		// 1. Research topics
		$_POST = array(
			'nonce' => $nonce,
			'niche' => 'Healthcare AI',
			'count' => 1,
		);
		$_REQUEST = $_POST;
		$response = $this->capture_ajax(array($ctrl, 'ajax_research_topics'));
		$this->assertTrue($response['success']);
		$this->assertArrayHasKey('topics', $response['data']);
		$this->assertArrayHasKey('top_topics', $response['data']);
		$this->assertSame(1, $response['data']['saved_count']);
		$this->assertSame('Healthcare AI', $response['data']['niche']);

		// 2. Get trending topics
		$_POST = array(
			'nonce' => $nonce,
			'niche' => 'Healthcare AI',
		);
		$_REQUEST = $_POST;
		$response = $this->capture_ajax(array($ctrl, 'ajax_get_trending_topics'));
		$this->assertTrue($response['success']);
		$this->assertArrayHasKey('topics', $response['data']);
		$this->assertArrayHasKey('stats', $response['data']);
		$this->assertArrayHasKey('niches', $response['data']);
		$this->assertCount(1, $response['data']['topics']);
	}

	/**
	 * Test scheduling trending topics lifecycle.
	 */
	public function test_schedule_trending_topics_lifecycle() {
		$mock_repo = $this->getMockBuilder('AIPS_Trending_Topics_Repository')
			->disableOriginalConstructor()
			->onlyMethods(array('get_by_id', 'update_status_bulk'))
			->getMock();

		$mock_repo->method('get_by_id')->willReturn(array(
			'id'    => 201,
			'topic' => 'Quantum Computing Basics',
		));
		$mock_repo->method('update_status_bulk')->willReturn(1);

		$ctrl = new AIPS_Research_Controller(null, $mock_repo);
		$nonce = wp_create_nonce('aips_ajax_nonce');

		$_POST = array(
			'nonce'       => $nonce,
			'topic_ids'   => array(201),
			'template_id' => 1,
			'start_date'  => date('Y-m-d H:i:s', time() + 3600),
			'frequency'   => 'daily',
		);
		$_REQUEST = $_POST;

		$response = $this->capture_ajax(array($ctrl, 'ajax_schedule_trending_topics'));
		$this->assertTrue($response['success']);
		$this->assertArrayHasKey('scheduled_count', $response['data']);
		$this->assertSame(1, $response['data']['scheduled_count']);
	}

	/**
	 * Test topic deletion (single and bulk).
	 */
	public function test_topic_deletion_single_and_bulk() {
		$mock_repo = $this->getMockBuilder('AIPS_Trending_Topics_Repository')
			->disableOriginalConstructor()
			->onlyMethods(array('delete', 'delete_bulk'))
			->getMock();

		$mock_repo->method('delete')->willReturn(true);
		$mock_repo->method('delete_bulk')->willReturn(2);

		$ctrl = new AIPS_Research_Controller(null, $mock_repo);
		$nonce = wp_create_nonce('aips_ajax_nonce');

		// 1. Single delete
		$_POST = array(
			'nonce'    => $nonce,
			'topic_id' => 301,
		);
		$_REQUEST = $_POST;
		$response = $this->capture_ajax(array($ctrl, 'ajax_delete_trending_topic'));
		$this->assertTrue($response['success']);
		$this->assertStringContainsString('deleted successfully', $response['data']['message']);

		// 2. Bulk delete
		$_POST = array(
			'nonce'     => $nonce,
			'topic_ids' => array(301, 302),
		);
		$_REQUEST = $_POST;
		$response = $this->capture_ajax(array($ctrl, 'ajax_delete_trending_topic_bulk'));
		$this->assertTrue($response['success']);
		$this->assertSame(2, $response['data']['count']);
	}

	/**
	 * Test gap analysis and generating topics from a gap.
	 */
	public function test_gap_analysis_and_topic_generation() {
		$mock_auditor = $this->getMockBuilder('AIPS_Content_Auditor')
			->disableOriginalConstructor()
			->onlyMethods(array('perform_gap_analysis'))
			->getMock();

		$mock_auditor->method('perform_gap_analysis')->willReturn(array(
			array('gap' => 'Missing deep dive on AI privacy', 'score' => 90),
		));

		$mock_service = $this->getMockBuilder('AIPS_Research_Service')
			->disableOriginalConstructor()
			->onlyMethods(array('research_trending_topics'))
			->getMock();

		$mock_service->method('research_trending_topics')->willReturn(array(
			array('topic' => 'AI Privacy Regulations 2026'),
		));

		$mock_repo = $this->getMockBuilder('AIPS_Trending_Topics_Repository')
			->disableOriginalConstructor()
			->onlyMethods(array('save_research_batch'))
			->getMock();

		$mock_repo->method('save_research_batch')->willReturn(1);

		$ctrl = new AIPS_Research_Controller($mock_service, $mock_repo, null, null, $mock_auditor);
		$nonce = wp_create_nonce('aips_ajax_nonce');

		// 1. Perform gap analysis
		$_POST = array(
			'nonce' => $nonce,
			'niche' => 'Technology Policy',
		);
		$_REQUEST = $_POST;
		$response = $this->capture_ajax(array($ctrl, 'ajax_perform_gap_analysis'));
		$this->assertTrue($response['success']);
		$this->assertArrayHasKey('gaps', $response['data']);
		$this->assertSame('Technology Policy', $response['data']['niche']);

		// 2. Generate topics from gap
		$_POST = array(
			'nonce'     => $nonce,
			'gap_topic' => 'Missing deep dive on AI privacy',
			'niche'     => 'Technology Policy',
		);
		$_REQUEST = $_POST;
		$response = $this->capture_ajax(array($ctrl, 'ajax_generate_topics_from_gap'));
		$this->assertTrue($response['success']);
		$this->assertSame(1, $response['data']['count']);
	}
}
