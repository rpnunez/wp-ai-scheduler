<?php
/**
 * Tests for AIPS_Content_Auditor_Controller
 *
 * @package AI_Post_Scheduler
 */
class Test_AIPS_Content_Auditor_Controller extends WP_Ajax_UnitTestCase {

	/**
	 * @var AIPS_Content_Auditor_Controller
	 */
	private $controller;

	/**
	 * @var PHPUnit\Framework\MockObject\MockObject
	 */
	private $mock_scanner;

	/**
	 * @var PHPUnit\Framework\MockObject\MockObject
	 */
	private $mock_engine;

	/**
	 * @var PHPUnit\Framework\MockObject\MockObject
	 */
	private $mock_repository;

	public function setUp(): void {
		parent::setUp();

		$this->mock_scanner    = $this->createMock(AIPS_Content_Auditor_Scanner::class);
		$this->mock_engine     = $this->createMock(AIPS_Content_Auditor_Engine::class);
		$this->mock_repository = $this->createMock(AIPS_Content_Auditor_Repository::class);

		$this->controller = new AIPS_Content_Auditor_Controller(
			$this->mock_scanner,
			$this->mock_engine,
			$this->mock_repository
		);

		// Set admin user permissions
		$admin_id = $this->factory->user->create(array('role' => 'administrator'));
		wp_set_current_user($admin_id);
	}

	public function test_ajax_scan_step_success() {
		$_POST['nonce']  = wp_create_nonce('aips_ajax_nonce');
		$_POST['limit']  = 50;
		$_POST['offset'] = 0;

		$mock_fps = array(
			array('id' => 1, 'title' => 'Post 1'),
			array('id' => 2, 'title' => 'Post 2'),
		);

		$this->mock_scanner->expects($this->once())
			->method('scan_library')
			->with(50, 0)
			->willReturn($mock_fps);

		try {
			$this->_handleAjax('aips_auditor_scan_step');
		} catch (WPAjaxDieContinueException $e) {
			// Expected AJAX exit
		}

		$response = json_decode($this->_last_response, true);
		$this->assertTrue($response['success']);
		$this->assertSame(2, $response['data']['count']);
		$this->assertSame(25, $response['data']['progress']);
		$this->assertSame('scan_complete', $response['data']['step']);
	}

	public function test_ajax_graph_step_success() {
		$_POST['nonce']        = wp_create_nonce('aips_ajax_nonce');
		$_POST['fingerprints'] = array(
			array('id' => 1, 'title' => 'Post 1', 'categories' => array('DevOps')),
		);

		$this->mock_scanner->expects($this->once())
			->method('build_link_graph')
			->willReturn(array('orphan_count' => 0));

		$this->mock_scanner->expects($this->once())
			->method('build_entity_clusters')
			->willReturn(array('total_posts' => 1));

		try {
			$this->_handleAjax('aips_auditor_graph_step');
		} catch (WPAjaxDieContinueException $e) {
		}

		$response = json_decode($this->_last_response, true);
		$this->assertTrue($response['success']);
		$this->assertSame(50, $response['data']['progress']);
		$this->assertSame('graph_complete', $response['data']['step']);
	}

	public function test_ajax_analyze_step_gaps() {
		$_POST['nonce']  = wp_create_nonce('aips_ajax_nonce');
		$_POST['niche']  = 'Cloud Computing';
		$_POST['module'] = 'gaps';

		$this->mock_engine->expects($this->once())
			->method('analyze_topic_gaps')
			->willReturn(array('gaps' => array(array('missing_topic' => 'Serverless')), 'gap_count' => 1));

		try {
			$this->_handleAjax('aips_auditor_analyze_step');
		} catch (WPAjaxDieContinueException $e) {
		}

		$response = json_decode($this->_last_response, true);
		$this->assertTrue($response['success']);
		$this->assertSame('gaps', $response['data']['module']);
		$this->assertSame(75, $response['data']['progress']);
		$this->assertSame(1, $response['data']['result']['gap_count']);
	}

	public function test_ajax_synthesize_step_success() {
		$_POST['nonce']   = wp_create_nonce('aips_ajax_nonce');
		$_POST['niche']   = 'Cloud Computing';
		$_POST['modules'] = array(
			'gaps' => array('gap_count' => 1),
		);

		$this->mock_engine->expects($this->once())
			->method('synthesize_overall_health')
			->willReturn(array('overall_score' => 88));

		$this->mock_repository->expects($this->once())
			->method('save')
			->willReturn(42);

		try {
			$this->_handleAjax('aips_auditor_synthesize_step');
		} catch (WPAjaxDieContinueException $e) {
		}

		$response = json_decode($this->_last_response, true);
		$this->assertTrue($response['success']);
		$this->assertSame(42, $response['data']['audit_id']);
		$this->assertSame(100, $response['data']['progress']);
		$this->assertSame(88, $response['data']['report']['health_scorecard']['overall_score']);
	}

	public function test_ajax_get_latest_success() {
		$_POST['nonce'] = wp_create_nonce('aips_ajax_nonce');
		$_POST['niche'] = 'Cloud Computing';

		$this->mock_repository->expects($this->once())
			->method('get_latest')
			->with('Cloud Computing')
			->willReturn(array('id' => 10, 'overall_score' => 92));

		try {
			$this->_handleAjax('aips_auditor_get_latest');
		} catch (WPAjaxDieContinueException $e) {
		}

		$response = json_decode($this->_last_response, true);
		$this->assertTrue($response['success']);
		$this->assertSame(10, $response['data']['audit']['id']);
		$this->assertSame(92, $response['data']['audit']['overall_score']);
	}

	public function test_ajax_delete_audit_success() {
		$_POST['nonce'] = wp_create_nonce('aips_ajax_nonce');
		$_POST['id']    = 15;

		$this->mock_repository->expects($this->once())
			->method('delete')
			->with(15)
			->willReturn(true);

		try {
			$this->_handleAjax('aips_auditor_delete_audit');
		} catch (WPAjaxDieContinueException $e) {
		}

		$response = json_decode($this->_last_response, true);
		$this->assertTrue($response['success']);
	}

	public function test_ajax_nonce_failure() {
		$_POST['nonce'] = 'invalid_nonce';

		try {
			$this->_handleAjax('aips_auditor_scan_step');
		} catch (WPAjaxDieContinueException $e) {
		}

		$response = json_decode($this->_last_response, true);
		$this->assertFalse($response['success']);
		$this->assertSame('Invalid nonce.', $response['data']['message']);
	}
}
