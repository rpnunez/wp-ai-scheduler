<?php
/**
 * Test AIPS_Editor_Controller
 *
 * @package AI_Post_Scheduler
 */

class Test_AIPS_Editor_Controller extends WP_UnitTestCase {

	/**
	 * @var AIPS_Editor_Controller
	 */
	private $controller;

	/**
	 * @var AIPS_Editor_Directive_Service
	 */
	private $directive_service;

	/**
	 * @var AIPS_Editor_Link_Weave_Service
	 */
	private $link_weave_service;

	/**
	 * @var int
	 */
	private $admin_user_id;

	public function setUp(): void {
		parent::setUp();

		$this->admin_user_id = $this->factory->user->create(array(
			'role' => 'administrator',
		));
		wp_set_current_user($this->admin_user_id);

		// Mock AI Service that returns predictable text
		$mock_ai = $this->getMockBuilder('AIPS_AI_Service_Interface')->getMock();
		$mock_ai->method('is_available')->willReturn(true);
		$mock_ai->method('generate_text')->willReturnCallback(function ($prompt) {
			if (strpos($prompt, 'JSON array of 3 strings') !== false || strpos($prompt, 'exactly 3') !== false) {
				return '["Candidate 1", "Candidate 2", "Candidate 3"]';
			}
			if (strpos($prompt, 'modified_html') !== false) {
				return '{"modified_html": "Rewritten with <a href=\"https://example.com/test\">internal link</a>.", "inserted_links": [{"url": "https://example.com/test", "title": "Test Post", "anchor": "internal link"}]}';
			}
			return 'Rewritten content block punchy and engaged.';
		});

		$this->directive_service  = new AIPS_Editor_Directive_Service($mock_ai);
		$this->link_weave_service = new AIPS_Editor_Link_Weave_Service($mock_ai);
		$this->controller         = new AIPS_Editor_Controller($this->directive_service, $this->link_weave_service);
	}

	public function tearDown(): void {
		$_POST    = array();
		$_REQUEST = array();
		wp_set_current_user(0);
		parent::tearDown();
	}

	private function sync_request_from_post(): void {
		$_REQUEST = $_POST;
	}

	public function test_apply_directive_requires_valid_nonce() {
		$post_id = $this->factory->post->create();

		$_POST = array(
			'action'  => 'aips_editor_apply_directive',
			'nonce'   => 'invalid_nonce',
			'post_id' => $post_id,
			'content' => 'Sample content',
		);
		$this->sync_request_from_post();

		ob_start();
		try {
			$this->controller->ajax_apply_directive();
		} catch (WPAjaxDieContinueException $e) {
			// Expected die from wp_send_json_error
		}
		$output   = ob_get_clean();
		$response = json_decode($output, true);

		$this->assertFalse($response['success']);
		$this->assertStringContainsString('Invalid security token', $response['data']['message']);
	}

	public function test_apply_directive_checks_edit_post_capability() {
		$post_id = $this->factory->post->create();

		// Switch to subscriber user without edit_post permission
		$subscriber_id = $this->factory->user->create(array('role' => 'subscriber'));
		wp_set_current_user($subscriber_id);

		$_POST = array(
			'action'  => 'aips_editor_apply_directive',
			'nonce'   => wp_create_nonce('aips_ajax_nonce'),
			'post_id' => $post_id,
			'content' => 'Sample content',
		);
		$this->sync_request_from_post();

		ob_start();
		try {
			$this->controller->ajax_apply_directive();
		} catch (WPAjaxDieContinueException $e) {
			// Expected
		}
		$output   = ob_get_clean();
		$response = json_decode($output, true);

		$this->assertFalse($response['success']);
		$this->assertStringContainsStringIgnoringCase('permission', $response['data']['message']);
	}

	public function test_apply_directive_returns_single_candidate() {
		$post_id = $this->factory->post->create(array('post_title' => 'Article One'));

		$_POST = array(
			'action'           => 'aips_editor_apply_directive',
			'nonce'            => wp_create_nonce('aips_ajax_nonce'),
			'post_id'          => $post_id,
			'content'          => 'This is wordy and dull text.',
			'directive_preset' => 'punchy',
			'variants_count'   => 1,
		);
		$this->sync_request_from_post();

		ob_start();
		try {
			$this->controller->ajax_apply_directive();
		} catch (WPAjaxDieContinueException $e) {
			// Expected
		}
		$output   = ob_get_clean();
		$response = json_decode($output, true);

		$this->assertTrue($response['success']);
		$this->assertCount(1, $response['data']['variants']);
		$this->assertSame('punchy', $response['data']['preset']);
		$this->assertStringContainsString('Rewritten content', $response['data']['variants'][0]);
	}

	public function test_apply_directive_returns_three_variants() {
		$post_id = $this->factory->post->create(array('post_title' => 'Article Two'));

		$_POST = array(
			'action'           => 'aips_editor_apply_directive',
			'nonce'            => wp_create_nonce('aips_ajax_nonce'),
			'post_id'          => $post_id,
			'content'          => 'Original paragraph.',
			'directive_preset' => 'expand',
			'variants_count'   => 3,
		);
		$this->sync_request_from_post();

		ob_start();
		try {
			$this->controller->ajax_apply_directive();
		} catch (WPAjaxDieContinueException $e) {
			// Expected
		}
		$output   = ob_get_clean();
		$response = json_decode($output, true);

		$this->assertTrue($response['success']);
		$this->assertCount(3, $response['data']['variants']);
		$this->assertSame('Candidate 1', $response['data']['variants'][0]);
		$this->assertSame('Candidate 2', $response['data']['variants'][1]);
		$this->assertSame('Candidate 3', $response['data']['variants'][2]);
	}

	public function test_weave_internal_links_returns_modified_html() {
		$post_id = $this->factory->post->create(array('post_title' => 'Current Post'));
		// Create another published post to serve as link candidate
		$this->factory->post->create(array(
			'post_title'  => 'Candidate Published Post',
			'post_status' => 'publish',
		));

		$_POST = array(
			'action'  => 'aips_editor_weave_internal_links',
			'nonce'   => wp_create_nonce('aips_ajax_nonce'),
			'post_id' => $post_id,
			'content' => 'Paragraph that needs a contextual link.',
		);
		$this->sync_request_from_post();

		ob_start();
		try {
			$this->controller->ajax_weave_internal_links();
		} catch (WPAjaxDieContinueException $e) {
			// Expected
		}
		$output   = ob_get_clean();
		$response = json_decode($output, true);

		$this->assertTrue($response['success']);
		$this->assertArrayHasKey('modified_html', $response['data']);
		$this->assertStringContainsString('<a href=', $response['data']['modified_html']);
		$this->assertNotEmpty($response['data']['inserted_links']);
	}

	public function test_generate_component_variants_returns_title_candidates() {
		$post_id = $this->factory->post->create(array(
			'post_title'   => 'Base Title',
			'post_content' => 'Some descriptive content.',
		));

		$_POST = array(
			'action'    => 'aips_editor_generate_component_variants',
			'nonce'     => wp_create_nonce('aips_ajax_nonce'),
			'post_id'   => $post_id,
			'component' => 'title',
		);
		$this->sync_request_from_post();

		ob_start();
		try {
			$this->controller->ajax_generate_component_variants();
		} catch (WPAjaxDieContinueException $e) {
			// Expected
		}
		$output   = ob_get_clean();
		$response = json_decode($output, true);

		$this->assertTrue($response['success']);
		$this->assertSame('title', $response['data']['component']);
		$this->assertCount(3, $response['data']['variants']);
	}

	public function test_generate_component_variants_rejects_invalid_component() {
		$post_id = $this->factory->post->create();

		$_POST = array(
			'action'    => 'aips_editor_generate_component_variants',
			'nonce'     => wp_create_nonce('aips_ajax_nonce'),
			'post_id'   => $post_id,
			'component' => 'unsupported_component',
		);
		$this->sync_request_from_post();

		ob_start();
		try {
			$this->controller->ajax_generate_component_variants();
		} catch (WPAjaxDieContinueException $e) {
			// Expected
		}
		$output   = ob_get_clean();
		$response = json_decode($output, true);

		$this->assertFalse($response['success']);
		$this->assertStringContainsString('Invalid component type', $response['data']['message']);
	}
}
