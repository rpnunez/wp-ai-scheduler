<?php
/**
 * Tests for AI Edit Controller
 *
 * @package AI_Post_Scheduler
 */

class Test_AIPS_AI_Edit_Controller extends WP_UnitTestCase {
	
	private $controller;
	private $service;
	private $history_repository;
	private $template_repository;
	
	public function setUp(): void {
		parent::setUp();
		$this->history_repository = new AIPS_History_Repository();
		$this->template_repository = new AIPS_Template_Repository();
		$this->service = new AIPS_Component_Regeneration_Service();
		$this->controller = new AIPS_AI_Edit_Controller();
		
		// Set up admin user for permission checks
		wp_set_current_user($this->factory->user->create(array('role' => 'administrator')));
	}
	
	public function tearDown(): void {
		parent::tearDown();
	}

	private function sync_request_from_post() {
		$_REQUEST = $_POST;
	}
	
	/**
	 * Test that the controller can be instantiated
	 */
	public function test_controller_instantiation() {
		$this->assertInstanceOf('AIPS_AI_Edit_Controller', $this->controller);
	}
	
	/**
	 * Test that AJAX actions are registered
	 */
	public function test_ajax_actions_registered() {
		$this->assertTrue(has_action('wp_ajax_aips_get_post_components'));
		$this->assertTrue(has_action('wp_ajax_aips_regenerate_component'));
		$this->assertTrue(has_action('wp_ajax_aips_regenerate_all_components'));
		$this->assertTrue(has_action('wp_ajax_aips_save_post_components'));
		$this->assertTrue(has_action('wp_ajax_aips_reset_component_overrides'));
	}

	/**
	 * Test get_post_components requires proper nonce
	 */
	public function test_get_post_components_requires_nonce() {
		$_POST = array(
			'action' => 'aips_get_post_components',
			'post_id' => 1,
			'history_id' => 1,
		);
		
		// Should fail without nonce
		$exception_thrown = false;
		ob_start();
		try {
			$this->controller->ajax_get_post_components();
		} catch (WPAjaxDieStopException $e) {
			// Expected - nonce check failed with wp_die
			$exception_thrown = true;
		} catch (WPAjaxDieContinueException $e) {
			// Also acceptable - nonce check failed with wp_send_json_error
			$exception_thrown = true;
		}
		ob_end_clean();
		
		$this->assertTrue($exception_thrown, 'Nonce validation should have thrown an exception');
	}
	
	/**
	 * Test get_post_components requires valid post ID
	 */
	public function test_get_post_components_requires_valid_post() {
		$post_id = $this->factory->post->create(array(
			'post_title' => 'Test Post',
			'post_content' => 'Test content',
			'post_excerpt' => 'Test excerpt',
		));

		$template = (object) array(
			'id' => 1,
			'name' => 'Injected Template',
			'prompt_template' => 'Test prompt template',
			'title_prompt' => 'Injected title prompt',
			'post_status' => 'draft',
			'post_type' => 'post',
			'post_category' => 0,
			'post_author' => get_current_user_id(),
		);

		$mock_service = $this->getMockBuilder( 'AIPS_Component_Regeneration_Service' )
			->disableOriginalConstructor()
			->onlyMethods( array( 'get_generation_context' ) )
			->getMock();

		$mock_service->method( 'get_generation_context' )
			->willReturn(
				array(
					'history_id' => 101,
					'post_id' => $post_id,
					'context_type' => 'template',
					'context_name' => 'Injected Template',
					'generation_context' => new AIPS_Template_Context( $template, null, 'Injected Topic' ),
				)
			);

		$controller = new AIPS_AI_Edit_Controller( $mock_service );
		
		// Set up request with valid nonce
		$_POST = array(
			'action' => 'aips_get_post_components',
			'post_id' => $post_id,
			'history_id' => 101,
			'nonce' => wp_create_nonce('aips_ajax_nonce'),
		);
		$this->sync_request_from_post();
		
		// This should succeed (just checking it doesn't throw an error)
		ob_start();
		try {
			$controller->ajax_get_post_components();
		} catch (WPAjaxDieContinueException $e) {
			// wp_send_json_success throws this
			$output = ob_get_clean();
			$response = json_decode($output, true);
			
			$this->assertTrue($response['success']);
			$this->assertArrayHasKey('components', $response['data']);
			$this->assertArrayHasKey('title', $response['data']['components']);
			$this->assertArrayHasKey('excerpt', $response['data']['components']);
			$this->assertArrayHasKey('content', $response['data']['components']);
			$this->assertArrayHasKey('featured_image', $response['data']['components']);
			return;
		} catch (Exception $e) {
			ob_end_clean();
			$this->fail('Should not throw exception: ' . $e->getMessage());
		}
		ob_end_clean();
		$this->fail('Should have thrown WPAjaxDieContinueException');
	}

	/**
	 * Test get_post_components resolves history_id via repository when omitted in request.
	 */
	public function test_get_post_components_resolves_history_id_when_omitted() {
		$post_id = $this->factory->post->create(array(
			'post_title' => 'Auto Resolved Post',
			'post_content' => 'Auto resolved content',
			'post_excerpt' => 'Auto resolved excerpt',
		));

		$template = (object) array(
			'id' => 2,
			'name' => 'Auto Template',
			'prompt_template' => 'Prompt template',
			'title_prompt' => 'Title prompt',
			'post_status' => 'draft',
			'post_type' => 'post',
			'post_category' => 0,
			'post_author' => get_current_user_id(),
		);

		$mock_service = $this->getMockBuilder('AIPS_Component_Regeneration_Service')
			->disableOriginalConstructor()
			->onlyMethods(array('get_generation_context'))
			->getMock();

		$mock_service->method('get_generation_context')
			->with(303)
			->willReturn(
				array(
					'history_id' => 303,
					'post_id' => $post_id,
					'context_type' => 'template',
					'context_name' => 'Auto Template',
					'generation_context' => new AIPS_Template_Context($template, null, 'Auto Topic'),
				)
			);

		$mock_repo = $this->createMock('AIPS_History_Repository_Interface');
		$mock_repo->method('get_by_post_id')
			->with($post_id)
			->willReturn((object) array('id' => 303, 'post_id' => $post_id));

		$controller = new AIPS_AI_Edit_Controller($mock_service, $mock_repo);

		$_POST = array(
			'action' => 'aips_get_post_components',
			'post_id' => $post_id,
			'nonce' => wp_create_nonce('aips_ajax_nonce'),
		);
		$this->sync_request_from_post();

		ob_start();
		try {
			$controller->ajax_get_post_components();
		} catch (WPAjaxDieContinueException $e) {
			$output = ob_get_clean();
			$response = json_decode($output, true);

			$this->assertTrue($response['success']);
			$this->assertSame(303, $response['data']['history_id']);
			$this->assertSame($post_id, $response['data']['post_id']);
			return;
		} catch (Exception $e) {
			ob_end_clean();
			$this->fail('Should not throw exception: ' . $e->getMessage());
		}
		ob_end_clean();
		$this->fail('Should have thrown WPAjaxDieContinueException');
	}
	
	/**
	 * Test regenerate_component validates component type
	 */
	public function test_regenerate_component_validates_type() {
		$post_id = $this->factory->post->create();
		$history_id = $this->history_repository->create(array(
			'template_id' => 1,
			'post_id' => $post_id,
			'status' => 'completed',
		));
		
		$_POST = array(
			'action' => 'aips_regenerate_component',
			'post_id' => $post_id,
			'history_id' => $history_id,
			'component' => 'invalid_component',
			'nonce' => wp_create_nonce('aips_ajax_nonce'),
		);
		$this->sync_request_from_post();
		
		ob_start();
		try {
			$this->controller->ajax_regenerate_component();
		} catch (WPAjaxDieContinueException $e) {
			// Expected exception from wp_send_json_error
		}
		$output = ob_get_clean();
		$response = json_decode($output, true);
		
		$this->assertFalse($response['success']);
		$this->assertStringContainsString('Invalid component', $response['data']['message']);
	}
	
	/**
	 * Test save_post_components requires edit permission
	 */
	public function test_save_post_components_requires_permission() {
		// Switch to a user without edit permission
		wp_set_current_user($this->factory->user->create(array('role' => 'subscriber')));
		
		$post_id = $this->factory->post->create();
		
		$_POST = array(
			'action' => 'aips_save_post_components',
			'post_id' => $post_id,
			'components' => array('title' => 'New Title'),
			'nonce' => wp_create_nonce('aips_ajax_nonce'),
		);
		$this->sync_request_from_post();
		
		ob_start();
		try {
			$this->controller->ajax_save_post_components();
		} catch (WPAjaxDieContinueException $e) {
			// Expected exception from wp_send_json_error
		}
		$output = ob_get_clean();
		$response = json_decode($output, true);
		
		$this->assertFalse($response['success']);
		$this->assertStringContainsString('Permission denied', $response['data']['message']);
	}
	
	/**
	 * Test save_post_components updates post correctly
	 */
	public function test_save_post_components_updates_post() {
		$post_id = $this->factory->post->create(array(
			'post_title' => 'Old Title',
			'post_excerpt' => 'Old excerpt',
			'post_content' => 'Old content',
		));

		update_post_meta($post_id, AIPS_Post_Manager::META_GENERATION_COMPONENT_STATUSES, wp_json_encode(array(
			'post_title'     => false,
			'post_excerpt'   => false,
			'featured_image' => true,
			'post_content'   => false,
		)));
		update_post_meta($post_id, AIPS_Post_Manager::META_GENERATION_INCOMPLETE, 'true');
		update_post_meta($post_id, AIPS_Post_Manager::META_GENERATION_HAD_PARTIAL, 'true');
		
		$_POST = array(
			'action' => 'aips_save_post_components',
			'post_id' => $post_id,
			'components' => array(
				'title' => 'AI Generated Post: New Title',
				'excerpt' => 'New excerpt',
				'content' => 'New content',
			),
			'nonce' => wp_create_nonce('aips_ajax_nonce'),
		);
		$this->sync_request_from_post();
		
		ob_start();
		try {
			$this->controller->ajax_save_post_components();
		} catch (WPAjaxDieContinueException $e) {
			// Expected exception from wp_send_json_success
		}
		$output = ob_get_clean();
		$response = json_decode($output, true);
		
		$this->assertTrue($response['success']);
		
		// Verify post was updated
		$updated_post = get_post($post_id);
		$this->assertEquals('AI Generated Post: New Title', $updated_post->post_title);
		$this->assertEquals('New excerpt', $updated_post->post_excerpt);
		$this->assertEquals('New content', $updated_post->post_content);
		$this->assertSame('false', get_post_meta($post_id, AIPS_Post_Manager::META_GENERATION_INCOMPLETE, true));
		$this->assertSame('true', get_post_meta($post_id, AIPS_Post_Manager::META_GENERATION_HAD_PARTIAL, true));

		$statuses = json_decode((string) get_post_meta($post_id, AIPS_Post_Manager::META_GENERATION_COMPONENT_STATUSES, true), true);
		$this->assertIsArray($statuses);
		$this->assertTrue($statuses['post_title']);
		$this->assertTrue($statuses['post_excerpt']);
		$this->assertTrue($statuses['post_content']);
		$this->assertTrue($statuses['featured_image']);
	}
	
	/**
	 * Test save_post_components sanitizes input
	 */
	public function test_save_post_components_sanitizes_input() {
		$post_id = $this->factory->post->create();
		
		$_POST = array(
			'action' => 'aips_save_post_components',
			'post_id' => $post_id,
			'components' => array(
				'title' => '<script>alert("xss")</script>Safe Title',
				'excerpt' => '<script>alert("xss")</script>Safe excerpt',
				'content' => '<p>Safe content</p><script>alert("xss")</script>',
			),
			'nonce' => wp_create_nonce('aips_ajax_nonce'),
		);
		$this->sync_request_from_post();
		
		ob_start();
		try {
			$this->controller->ajax_save_post_components();
		} catch (WPAjaxDieContinueException $e) {
			// Expected exception from wp_send_json_success
		}
		$output = ob_get_clean();
		$response = json_decode($output, true);
		
		$this->assertTrue($response['success']);
		
		// Verify malicious content was removed
		$updated_post = get_post($post_id);
		$this->assertStringNotContainsString('<script>', $updated_post->post_title);
		$this->assertStringNotContainsString('<script>', $updated_post->post_excerpt);
		
		// Content should allow safe HTML
		$this->assertStringContainsString('<p>Safe content</p>', $updated_post->post_content);
		$this->assertStringNotContainsString('<script>', $updated_post->post_content);
	}

	/**
	 * Test save_post_components preserves backslashes in title, excerpt, and content
	 */
	public function test_save_post_components_preserves_backslashes() {
		$post_id = $this->factory->post->create(array(
			'post_title' => 'Initial Title',
			'post_excerpt' => 'Initial excerpt',
			'post_content' => 'Initial content',
		));

		$backslash_title = 'Title with \\path\\to\\file and LaTeX \\alpha';
		$backslash_excerpt = 'Excerpt with C:\\Windows\\System32 and \\n newline symbol';
		$backslash_content = '<p>Formula $\\frac{a}{b}$ and directory C:\\xampp\\htdocs and \\\\escaped</p>';

		// In WordPress runtime, $_POST is magic-quoted (slashed) by wp_magic_quotes().
		// We simulate the real POST payload by passing wp_slash().
		$_POST = array(
			'action' => 'aips_save_post_components',
			'post_id' => $post_id,
			'components' => wp_slash(array(
				'title' => $backslash_title,
				'excerpt' => $backslash_excerpt,
				'content' => $backslash_content,
			)),
			'nonce' => wp_create_nonce('aips_ajax_nonce'),
		);
		$this->sync_request_from_post();

		$hook_called = false;
		$captured_sanitized = array();
		add_action('aips_post_components_updated', function($id, $components, $sanitized) use (&$hook_called, &$captured_sanitized) {
			$hook_called = true;
			$captured_sanitized = $sanitized;
		}, 10, 3);

		ob_start();
		try {
			$this->controller->ajax_save_post_components();
		} catch (WPAjaxDieContinueException $e) {
			// Expected exception from wp_send_json_success
		}
		$output = ob_get_clean();
		$response = json_decode($output, true);

		$this->assertTrue($response['success']);

		$updated_post = get_post($post_id);
		$this->assertEquals($backslash_title, $updated_post->post_title);
		$this->assertEquals($backslash_excerpt, $updated_post->post_excerpt);
		$this->assertEquals($backslash_content, $updated_post->post_content);

		$this->assertTrue($hook_called);
		$this->assertEquals($backslash_title, $captured_sanitized['title']);
		$this->assertEquals($backslash_excerpt, $captured_sanitized['excerpt']);
		$this->assertEquals($backslash_content, $captured_sanitized['content']);
	}

	/**
	 * Test save_post_components preserves backslashes across repeated saves
	 */
	public function test_save_post_components_preserves_backslashes_across_multiple_saves() {
		$post_id = $this->factory->post->create();

		$backslash_title = 'Regex \\d+\\.\\w+';
		$backslash_excerpt = 'Path C:\\Projects\\AI';
		$backslash_content = '<p>JSON snippet: {\\"key\\": \\"value\\"} and LaTeX: \\sum_{i=1}^n</p>';

		for ($i = 0; $i < 3; $i++) {
			$_POST = array(
				'action' => 'aips_save_post_components',
				'post_id' => $post_id,
				'components' => wp_slash(array(
					'title' => $backslash_title,
					'excerpt' => $backslash_excerpt,
					'content' => $backslash_content,
				)),
				'nonce' => wp_create_nonce('aips_ajax_nonce'),
			);
			$this->sync_request_from_post();

			ob_start();
			try {
				$this->controller->ajax_save_post_components();
			} catch (WPAjaxDieContinueException $e) {
				// Expected
			}
			$output = ob_get_clean();
			$response = json_decode($output, true);
			$this->assertTrue($response['success']);

			$saved = get_post($post_id);
			$this->assertEquals($backslash_title, $saved->post_title);
			$this->assertEquals($backslash_excerpt, $saved->post_excerpt);
			$this->assertEquals($backslash_content, $saved->post_content);
		}
	}

	/**
	 * Test restore_component_revision preserves backslashes
	 */
	public function test_restore_component_revision_preserves_backslashes() {
		$post_id = $this->factory->post->create(array(
			'post_title' => 'Original Title',
			'post_content' => 'Original content',
		));

		$backslash_content = '<p>Math: $\\sqrt{x^2 + y^2}$ and path C:\\Windows</p>';

		$history_id = $this->history_repository->create(array(
			'post_id' => $post_id,
			'status' => 'completed',
		));

		$revision_id = $this->history_repository->add_log_entry(
			$history_id,
			array(
				'log_subtype' => 'ai_response',
				'message' => 'Content Snapshot',
				'output' => array(
					'value' => $backslash_content,
				),
				'context' => array(
					'component' => 'content',
					'post_id' => $post_id,
				),
			),
			AIPS_History_Type::AI_RESPONSE
		);

		$_POST = array(
			'action' => 'aips_restore_component_revision',
			'post_id' => $post_id,
			'component' => 'content',
			'revision_id' => $revision_id,
			'nonce' => wp_create_nonce('aips_ajax_nonce'),
		);
		$this->sync_request_from_post();

		ob_start();
		try {
			$this->controller->ajax_restore_component_revision();
		} catch (WPAjaxDieContinueException $e) {
			// Expected exception from wp_send_json_success
		}
		$output = ob_get_clean();
		$response = json_decode($output, true);

		$this->assertTrue($response['success']);

		$updated_post = get_post($post_id);
		$this->assertEquals($backslash_content, $updated_post->post_content);
	}

	/**
	 * Test get_component_revisions supports legacy component_type payload.
	 */
	public function test_get_component_revisions_accepts_component_type() {
		$post_id = $this->factory->post->create(array(
			'post_title' => 'Revision Target',
		));

		$history_id = $this->history_repository->create(array(
			'post_id' => $post_id,
			'status' => 'completed',
		));

		$this->history_repository->add_log_entry(
			$history_id,
			array(
				'log_subtype' => 'ai_response',
				'message' => 'Snapshot',
				'output' => array('value' => 'Previous Title'),
				'context' => array(
					'component' => 'title',
					'post_id' => $post_id,
				),
			),
			AIPS_History_Type::AI_RESPONSE
		);

		$_POST = array(
			'action' => 'aips_get_component_revisions',
			'post_id' => $post_id,
			'component_type' => 'title',
			'nonce' => wp_create_nonce('aips_ajax_nonce'),
		);
		$this->sync_request_from_post();

		ob_start();
		try {
			$this->controller->ajax_get_component_revisions();
		} catch (WPAjaxDieContinueException $e) {
			// Expected.
		}
		$output = ob_get_clean();
		$response = json_decode($output, true);

		$this->assertTrue($response['success']);
		$this->assertGreaterThanOrEqual(1, $response['data']['total']);
		$this->assertEquals('Previous Title', $response['data']['revisions'][0]['value']);
	}

	/**
	 * Test restore_component_revision supports legacy component_type payload.
	 */
	public function test_restore_component_revision_accepts_component_type() {
		$post_id = $this->factory->post->create(array(
			'post_title' => 'Revision Restore Target',
		));

		$history_id = $this->history_repository->create(array(
			'post_id' => $post_id,
			'status' => 'completed',
		));

		update_post_meta($post_id, AIPS_Post_Manager::META_GENERATION_COMPONENT_STATUSES, wp_json_encode(array(
			'post_title'     => false,
			'post_excerpt'   => true,
			'featured_image' => true,
			'post_content'   => true,
		)));
		update_post_meta($post_id, AIPS_Post_Manager::META_GENERATION_INCOMPLETE, 'true');
		update_post_meta($post_id, AIPS_Post_Manager::META_GENERATION_HAD_PARTIAL, 'true');

		$revision_id = $this->history_repository->add_log_entry(
			$history_id,
			array(
				'log_subtype' => 'ai_response',
				'message' => 'Title Snapshot',
				'output' => array(
					'value' => 'Restored Title',
				),
				'context' => array(
					'component' => 'title',
					'post_id' => $post_id,
				),
			),
			AIPS_History_Type::AI_RESPONSE
		);

		$_POST = array(
			'action' => 'aips_restore_component_revision',
			'post_id' => $post_id,
			'component_type' => 'title',
			'revision_id' => $revision_id,
			'nonce' => wp_create_nonce('aips_ajax_nonce'),
		);
		$this->sync_request_from_post();

		ob_start();
		try {
			$this->controller->ajax_restore_component_revision();
		} catch (WPAjaxDieContinueException $e) {
			// Expected.
		}
		$output = ob_get_clean();
		$response = json_decode($output, true);

		$this->assertTrue($response['success']);
		$this->assertEquals('title', $response['data']['component']);
		$this->assertEquals('Restored Title', $response['data']['value']);
		$this->assertEquals('Restored Title', get_post($post_id)->post_title);
		$this->assertSame('false', get_post_meta($post_id, AIPS_Post_Manager::META_GENERATION_INCOMPLETE, true));
		$this->assertSame('true', get_post_meta($post_id, AIPS_Post_Manager::META_GENERATION_HAD_PARTIAL, true));

		$statuses = json_decode((string) get_post_meta($post_id, AIPS_Post_Manager::META_GENERATION_COMPONENT_STATUSES, true), true);
		$this->assertIsArray($statuses);
		$this->assertTrue($statuses['post_title']);
	}

	/**
	 * Invalid featured-image revision payloads should be rejected instead of
	 * silently falling back to a no-op restore.
	 */
	public function test_restore_component_revision_rejects_invalid_featured_image_payload() {
		$post_id = $this->factory->post->create(array(
			'post_title' => 'Featured Image Restore Target',
		));

		$current_attachment_id = $this->factory->post->create(array(
			'post_type'      => 'attachment',
			'post_mime_type' => 'image/jpeg',
			'post_status'    => 'inherit',
		));
		update_post_meta($post_id, '_thumbnail_id', $current_attachment_id);

		$history_id = $this->history_repository->create(array(
			'post_id' => $post_id,
			'status' => 'completed',
		));

		$revision_id = $this->history_repository->add_log_entry(
			$history_id,
			array(
				'log_subtype' => 'ai_response',
				'message' => 'Image Snapshot',
				'output' => array(
					'url' => 'https://example.com/broken.jpg',
				),
				'context' => array(
					'component' => 'featured_image',
					'post_id' => $post_id,
				),
			),
			AIPS_History_Type::AI_RESPONSE
		);

		update_post_meta($post_id, AIPS_Post_Manager::META_GENERATION_COMPONENT_STATUSES, wp_json_encode(array(
			'post_title'     => true,
			'post_excerpt'   => true,
			'featured_image' => false,
			'post_content'   => true,
		)));
		update_post_meta($post_id, AIPS_Post_Manager::META_GENERATION_INCOMPLETE, 'true');
		update_post_meta($post_id, AIPS_Post_Manager::META_GENERATION_HAD_PARTIAL, 'true');

		$_POST = array(
			'action' => 'aips_restore_component_revision',
			'post_id' => $post_id,
			'component' => 'featured_image',
			'revision_id' => $revision_id,
			'nonce' => wp_create_nonce('aips_ajax_nonce'),
		);
		$this->sync_request_from_post();

		ob_start();
		try {
			$this->controller->ajax_restore_component_revision();
		} catch (WPAjaxDieContinueException $e) {
			// Expected.
		}
		$output = ob_get_clean();
		$response = json_decode($output, true);

		$this->assertFalse($response['success']);
		$this->assertStringContainsString('Invalid featured image revision data', $response['data']['message']);
		$this->assertSame($current_attachment_id, get_post_thumbnail_id($post_id));
		$this->assertSame('true', get_post_meta($post_id, AIPS_Post_Manager::META_GENERATION_INCOMPLETE, true));
	}

	/**
	 * Test restore_component_revision snapshots a manual draft before overwrite.
	 */
	public function test_restore_component_revision_captures_manual_snapshot() {
		$post_id = $this->factory->post->create(array(
			'post_title' => 'Persisted Title',
		));

		$history_id = $this->history_repository->create(array(
			'post_id' => $post_id,
			'status' => 'completed',
		));

		$revision_id = $this->history_repository->add_log_entry(
			$history_id,
			array(
				'log_subtype' => 'ai_response',
				'message' => 'Original AI Title',
				'output' => array('value' => 'Original AI Title'),
				'context' => array(
					'component' => 'title',
					'post_id' => $post_id,
				),
			),
			AIPS_History_Type::AI_RESPONSE
		);

		$_POST = array(
			'action' => 'aips_restore_component_revision',
			'post_id' => $post_id,
			'component' => 'title',
			'revision_id' => $revision_id,
			'current_value' => 'Manual Draft Title',
			'current_source' => 'manual_edit',
			'current_reason' => 'pre_restore_manual',
			'nonce' => wp_create_nonce('aips_ajax_nonce'),
		);
		$this->sync_request_from_post();

		ob_start();
		try {
			$this->controller->ajax_restore_component_revision();
		} catch (WPAjaxDieContinueException $e) {
			// Expected.
		}
		ob_end_clean();

		$revisions = $this->history_repository->get_component_revisions($post_id, 'title', 10);

		$this->assertNotEmpty($revisions);
		$manual_snapshot = null;
		foreach ( $revisions as $revision ) {
			if ( 'manual_edit' === $revision['source'] && 'pre_restore_manual' === $revision['reason'] ) {
				$manual_snapshot = $revision;
				break;
			}
		}

		$this->assertNotNull( $manual_snapshot );
		$this->assertEquals( 'Manual Draft Title', $manual_snapshot['value'] );
	}

	// -----------------------------------------------------------------------
	// Delegation tests: constructor injection boundary
	// -----------------------------------------------------------------------

	/**
	 * Constructor accepts an injected AIPS_Component_Regeneration_Service so
	 * the real service is never instantiated in tests that mock it.
	 */
	public function test_constructor_accepts_injected_service() {
		$mock_service = $this->getMockBuilder( 'AIPS_Component_Regeneration_Service' )
			->disableOriginalConstructor()
			->getMock();

		$controller = new AIPS_AI_Edit_Controller( $mock_service );

		$this->assertInstanceOf( 'AIPS_AI_Edit_Controller', $controller );
	}

	/**
	 * Constructor accepts an injected AIPS_History_Repository so the real
	 * repository is never instantiated in tests that mock it.
	 */
	public function test_constructor_accepts_injected_history_repository() {
		$mock_history_repo = $this->getMockBuilder( 'AIPS_History_Repository' )
			->disableOriginalConstructor()
			->getMock();

		$controller = new AIPS_AI_Edit_Controller( null, $mock_history_repo );

		$this->assertInstanceOf( 'AIPS_AI_Edit_Controller', $controller );
	}

	/**
	 * Both dependencies can be injected simultaneously; controller remains
	 * functional after construction with fully mocked collaborators.
	 */
	public function test_constructor_accepts_both_injected_dependencies() {
		$mock_service = $this->getMockBuilder( 'AIPS_Component_Regeneration_Service' )
			->disableOriginalConstructor()
			->getMock();

		$mock_history_repo = $this->getMockBuilder( 'AIPS_History_Repository' )
			->disableOriginalConstructor()
			->getMock();

		$controller = new AIPS_AI_Edit_Controller( $mock_service, $mock_history_repo );

		$this->assertInstanceOf( 'AIPS_AI_Edit_Controller', $controller );
	}

	/**
	 * Test that a WP_Error from get_generation_context in ajax_get_post_components
	 * returns a generic message to the client, not the internal error details.
	 */
	public function test_get_post_components_wp_error_returns_generic_message() {
		$mock_service = $this->getMockBuilder( 'AIPS_Component_Regeneration_Service' )
			->disableOriginalConstructor()
			->getMock();

		$wp_error = new WP_Error( 'db_query_failed', 'Internal DB error details that must not leak' );
		$mock_service->method( 'get_generation_context' )
			->willReturn( $wp_error );

		$post_id = $this->factory->post->create();
		$nonce   = wp_create_nonce( 'aips_ajax_nonce' );

		$_POST    = array(
			'action'     => 'aips_get_post_components',
			'post_id'    => $post_id,
			'history_id' => 1,
			'nonce'      => $nonce,
		);
		$_REQUEST = $_POST;

		$controller = new AIPS_AI_Edit_Controller( $mock_service );

		ob_start();
		try {
			$controller->ajax_get_post_components();
		} catch ( WPAjaxDieContinueException $e ) {
			// Expected.
		}
		$output = ob_get_clean();
		$response = json_decode( $output, true );

		$this->assertFalse( $response['success'] );
		$this->assertStringNotContainsString( 'Internal DB error details', $response['data']['message'] );
		$this->assertStringContainsString( 'Failed to retrieve generation context', $response['data']['message'] );
	}

	/**
	 * Test that a WP_Error from get_generation_context in ajax_regenerate_component
	 * returns a generic message to the client, not the internal error details.
	 */
	public function test_regenerate_component_wp_error_returns_generic_message() {
		$mock_service = $this->getMockBuilder( 'AIPS_Component_Regeneration_Service' )
			->disableOriginalConstructor()
			->getMock();

		$wp_error = new WP_Error( 'db_query_failed', 'Internal DB error details that must not leak' );
		$mock_service->method( 'get_generation_context' )
			->willReturn( $wp_error );

		$post_id = $this->factory->post->create();
		$nonce   = wp_create_nonce( 'aips_ajax_nonce' );

		$_POST    = array(
			'action'     => 'aips_regenerate_component',
			'post_id'    => $post_id,
			'history_id' => 1,
			'component'  => 'title',
			'nonce'      => $nonce,
		);
		$_REQUEST = $_POST;

		$controller = new AIPS_AI_Edit_Controller( $mock_service );

		ob_start();
		try {
			$controller->ajax_regenerate_component();
		} catch ( WPAjaxDieContinueException $e ) {
			// Expected.
		}
		$output = ob_get_clean();
		$response = json_decode( $output, true );

		$this->assertFalse( $response['success'] );
		$this->assertStringNotContainsString( 'Internal DB error details', $response['data']['message'] );
		$this->assertStringContainsString( 'Failed to retrieve generation context', $response['data']['message'] );
	}

	/**
	 * Test that ajax_regenerate_component captures the original persisted post component
	 * as a revision snapshot before generating a new draft, even without manual_edit.
	 */
	public function test_regenerate_component_captures_persisted_snapshot_before_generation() {
		$post_id = $this->factory->post->create(array(
			'post_title'   => 'Original Post Title',
			'post_excerpt' => 'Storing JWTs in localStorage or relying on SameSite=None...',
			'post_content' => 'Original Content Body',
		));

		$history_id = $this->history_repository->create(array(
			'post_id' => $post_id,
			'status'  => 'completed',
		));

		$mock_service = $this->getMockBuilder('AIPS_Component_Regeneration_Service')
			->disableOriginalConstructor()
			->onlyMethods(array('get_generation_context', 'regenerate_excerpt', 'capture_component_revision'))
			->getMock();

		$mock_service->method('get_generation_context')->willReturn(array(
			'post_id'            => $post_id,
			'history_id'         => $history_id,
			'generation_context' => $this->createMock('AIPS_Generation_Context'),
		));

		$mock_service->method('regenerate_excerpt')->willReturn('Decoupled single-page applications...');

		$captured_value = null;
		$captured_source = null;
		$mock_service->method('capture_component_revision')->willReturnCallback(function ($p_id, $h_id, $comp, $val, $source, $reason) use (&$captured_value, &$captured_source) {
			$captured_value = $val;
			$captured_source = $source;
			return true;
		});

		$controller = new AIPS_AI_Edit_Controller($mock_service, $this->history_repository);

		$_POST = array(
			'action'         => 'aips_regenerate_component',
			'post_id'        => $post_id,
			'history_id'     => $history_id,
			'component'      => 'excerpt',
			'current_value'  => 'Storing JWTs in localStorage or relying on SameSite=None...',
			'current_source' => 'persisted',
			'current_reason' => 'pre_regenerate_current',
			'nonce'          => wp_create_nonce('aips_ajax_nonce'),
		);
		$this->sync_request_from_post();

		ob_start();
		try {
			$controller->ajax_regenerate_component();
		} catch (WPAjaxDieContinueException $e) {
			// Expected.
		}
		$output = ob_get_clean();
		$response = json_decode($output, true);

		$this->assertTrue($response['success']);
		$this->assertEquals('Decoupled single-page applications...', $response['data']['new_value']);
		$this->assertEquals('Storing JWTs in localStorage or relying on SameSite=None...', $captured_value);
		$this->assertEquals('persisted', $captured_source);
	}

	/**
	 * Test that ajax_regenerate_component falls back to reading the post's current value
	 * from the database when current_value is not passed in the request.
	 */
	public function test_regenerate_component_falls_back_to_post_value_when_current_value_omitted() {
		$post_id = $this->factory->post->create(array(
			'post_title'   => 'Fallback Title Test',
			'post_excerpt' => 'Database Persisted Excerpt',
		));

		$history_id = $this->history_repository->create(array(
			'post_id' => $post_id,
			'status'  => 'completed',
		));

		$mock_service = $this->getMockBuilder('AIPS_Component_Regeneration_Service')
			->disableOriginalConstructor()
			->onlyMethods(array('get_generation_context', 'regenerate_excerpt', 'capture_component_revision'))
			->getMock();

		$mock_service->method('get_generation_context')->willReturn(array(
			'post_id'            => $post_id,
			'history_id'         => $history_id,
			'generation_context' => $this->createMock('AIPS_Generation_Context'),
		));

		$mock_service->method('regenerate_excerpt')->willReturn('New AI Excerpt');

		$captured_value = null;
		$mock_service->method('capture_component_revision')->willReturnCallback(function ($p_id, $h_id, $comp, $val, $source, $reason) use (&$captured_value) {
			$captured_value = $val;
			return true;
		});

		$controller = new AIPS_AI_Edit_Controller($mock_service, $this->history_repository);

		$_POST = array(
			'action'     => 'aips_regenerate_component',
			'post_id'    => $post_id,
			'history_id' => $history_id,
			'component'  => 'excerpt',
			'nonce'      => wp_create_nonce('aips_ajax_nonce'),
		);
		$this->sync_request_from_post();

		ob_start();
		try {
			$controller->ajax_regenerate_component();
		} catch (WPAjaxDieContinueException $e) {
			// Expected.
		}
		$output = ob_get_clean();
		$response = json_decode($output, true);

		$this->assertTrue($response['success']);
		$this->assertEquals('Database Persisted Excerpt', $captured_value);
	}

	/**
	 * Test that ajax_regenerate_component skips capturing a snapshot if the identical
	 * value is already present in recent revisions (deduplication).
	 */
	public function test_regenerate_component_deduplicates_identical_snapshots() {
		$post_id = $this->factory->post->create(array(
			'post_title'   => 'Deduplication Test',
			'post_excerpt' => 'Existing Excerpt Value',
		));

		$history_id = $this->history_repository->create(array(
			'post_id' => $post_id,
			'status'  => 'completed',
		));

		// Pre-populate revision with the same excerpt value
		$this->history_repository->add_log_entry(
			$history_id,
			array(
				'log_subtype' => 'ai_response',
				'message'     => 'Snapshot',
				'output'      => array('value' => 'Existing Excerpt Value'),
				'context'     => array(
					'component' => 'excerpt',
					'post_id'   => $post_id,
				),
			),
			AIPS_History_Type::AI_RESPONSE
		);

		$mock_service = $this->getMockBuilder('AIPS_Component_Regeneration_Service')
			->disableOriginalConstructor()
			->onlyMethods(array('get_generation_context', 'regenerate_excerpt', 'capture_component_revision'))
			->getMock();

		$mock_service->method('get_generation_context')->willReturn(array(
			'post_id'            => $post_id,
			'history_id'         => $history_id,
			'generation_context' => $this->createMock('AIPS_Generation_Context'),
		));

		$mock_service->method('regenerate_excerpt')->willReturn('Freshly Generated Excerpt');

		// capture_component_revision should NEVER be called because the value is already in revisions
		$mock_service->expects($this->never())->method('capture_component_revision');

		$controller = new AIPS_AI_Edit_Controller($mock_service, $this->history_repository);

		$_POST = array(
			'action'         => 'aips_regenerate_component',
			'post_id'        => $post_id,
			'history_id'     => $history_id,
			'component'      => 'excerpt',
			'current_value'  => 'Existing Excerpt Value',
			'current_source' => 'persisted',
			'nonce'          => wp_create_nonce('aips_ajax_nonce'),
		);
		$this->sync_request_from_post();

		ob_start();
		try {
			$controller->ajax_regenerate_component();
		} catch (WPAjaxDieContinueException $e) {
			// Expected.
		}
		$output = ob_get_clean();
		$response = json_decode($output, true);

		$this->assertTrue($response['success']);
		$this->assertEquals('Freshly Generated Excerpt', $response['data']['new_value']);
	}

	/**
	 * Test that ajax_regenerate_all_components captures persisted baseline values
	 * for all components before regeneration.
	 */
	public function test_regenerate_all_components_captures_persisted_snapshots() {
		$post_id = $this->factory->post->create(array(
			'post_title'   => 'All Components Original Title',
			'post_excerpt' => 'All Components Original Excerpt',
			'post_content' => 'All Components Original Content',
		));

		$history_id = $this->history_repository->create(array(
			'post_id' => $post_id,
			'status'  => 'completed',
		));

		$mock_service = $this->getMockBuilder('AIPS_Component_Regeneration_Service')
			->disableOriginalConstructor()
			->onlyMethods(array('get_generation_context', 'regenerate_all_components', 'capture_component_revision'))
			->getMock();

		$mock_service->method('get_generation_context')->willReturn(array(
			'post_id'            => $post_id,
			'history_id'         => $history_id,
			'generation_context' => $this->createMock('AIPS_Generation_Context'),
		));

		$captured_components = array();
		$mock_service->method('capture_component_revision')->willReturnCallback(function ($p_id, $h_id, $comp, $val, $source, $reason) use (&$captured_components) {
			$captured_components[$comp] = $val;
			return true;
		});

		$mock_service->method('regenerate_all_components')->willReturn(array(
			'regenerated' => array(
				'title'   => 'Regenerated Title',
				'excerpt' => 'Regenerated Excerpt',
				'content' => 'Regenerated Content',
			),
			'skipped'     => array(),
			'errors'      => array(),
		));

		$controller = new AIPS_AI_Edit_Controller($mock_service, $this->history_repository);

		$_POST = array(
			'action'     => 'aips_regenerate_all_components',
			'post_id'    => $post_id,
			'history_id' => $history_id,
			'nonce'      => wp_create_nonce('aips_ajax_nonce'),
		);
		$this->sync_request_from_post();

		ob_start();
		try {
			$controller->ajax_regenerate_all_components();
		} catch (WPAjaxDieContinueException $e) {
			// Expected.
		}
		$output = ob_get_clean();
		$response = json_decode($output, true);

		$this->assertTrue($response['success']);
		$this->assertArrayHasKey('title', $captured_components);
		$this->assertEquals('All Components Original Title', $captured_components['title']);
		$this->assertArrayHasKey('excerpt', $captured_components);
		$this->assertEquals('All Components Original Excerpt', $captured_components['excerpt']);
		$this->assertArrayHasKey('content', $captured_components);
		$this->assertEquals('All Components Original Content', $captured_components['content']);
	}

	/**
	 * Test ajax_save_post_components records timeline activity events in history container.
	 */
	public function test_ajax_save_post_components_records_timeline_activity_events() {
		$post_id = $this->factory->post->create(array(
			'post_title'   => 'Before Title',
			'post_excerpt' => 'Before Excerpt',
		));

		$history_id = $this->history_repository->create(array(
			'post_id' => $post_id,
			'status'  => 'completed',
		));

		$_POST = array(
			'action'     => 'aips_save_post_components',
			'post_id'    => $post_id,
			'nonce'      => wp_create_nonce('aips_ajax_nonce'),
			'components' => array(
				'title'   => 'Regenerated Title Via AI Edit',
				'excerpt' => 'Regenerated Excerpt Via AI Edit',
			),
		);
		$this->sync_request_from_post();

		ob_start();
		try {
			$this->controller->ajax_save_post_components();
		} catch (WPAjaxDieContinueException $e) {
			// Expected.
		}
		$output = ob_get_clean();
		$response = json_decode($output, true);

		$this->assertTrue($response['success']);

		// Verify timeline activity events are recorded in history log
		$logs = $this->history_repository->get_logs_by_history_id($history_id);
		$this->assertNotEmpty($logs);

		$timeline_messages = array();
		foreach ($logs as $log) {
			if ((int) $log->history_type_id === AIPS_History_Type::ACTIVITY) {
				$details = json_decode($log->details, true);
				if (isset($details['message'])) {
					$timeline_messages[] = $details['message'];
				}
			}
		}

		$this->assertContains('Post Title regenerated via AI Edit', $timeline_messages);
		$this->assertContains('Post Excerpt regenerated via AI Edit', $timeline_messages);
	}

	/**
	 * Test ajax_restore_component_revision records timeline activity event.
	 */
	public function test_ajax_restore_component_revision_records_timeline_activity_event() {
		$post_id = $this->factory->post->create(array(
			'post_title' => 'Current Title',
		));

		$history_id = $this->history_repository->create(array(
			'post_id' => $post_id,
			'status'  => 'completed',
		));

		$revision_id = $this->history_repository->add_log_entry(
			$history_id,
			array(
				'log_subtype' => 'ai_response',
				'message'     => 'Title Revision Snapshot',
				'output'      => array('value' => 'Restored Title Value'),
				'context'     => array('component' => 'title', 'post_id' => $post_id),
			),
			AIPS_History_Type::AI_RESPONSE
		);

		$_POST = array(
			'action'      => 'aips_restore_component_revision',
			'post_id'     => $post_id,
			'component'   => 'title',
			'revision_id' => $revision_id,
			'nonce'       => wp_create_nonce('aips_ajax_nonce'),
		);
		$this->sync_request_from_post();

		ob_start();
		try {
			$this->controller->ajax_restore_component_revision();
		} catch (WPAjaxDieContinueException $e) {
			// Expected.
		}
		$output = ob_get_clean();
		$response = json_decode($output, true);

		$this->assertTrue($response['success']);

		// Verify timeline activity log
		$logs = $this->history_repository->get_logs_by_history_id($history_id);
		$found = false;
		foreach ($logs as $log) {
			if ((int) $log->history_type_id === AIPS_History_Type::ACTIVITY) {
				$details = json_decode($log->details, true);
				if (isset($details['message']) && strpos($details['message'], 'Post Title regenerated via AI Edit') !== false) {
					$found = true;
					break;
				}
			}
		}
		$this->assertTrue($found, 'Timeline activity log for restored revision was not found.');
	}

	/**
	 * Test that ajax_regenerate_component handles directives and sticky model overrides.
	 */
	public function test_regenerate_component_handles_directives_and_sticky_model_overrides() {
		$post_id = $this->factory->post->create(array(
			'post_title'   => 'Original Test Title',
			'post_content' => 'Original test content',
		));

		$history_id = $this->history_repository->create(array(
			'post_id'         => $post_id,
			'title'           => 'Original Test Title',
			'creation_method' => 'manual',
		));

		$service_mock = $this->createMock(AIPS_Component_Regeneration_Service::class);
		$service_mock->method('get_generation_context')->willReturn(array(
			'context_type'       => 'template',
			'context_name'       => 'Test Template',
			'post_id'            => $post_id,
			'generation_context' => $this->createMock(AIPS_Generation_Context::class),
		));
		$service_mock->method('capture_component_revision')->willReturn(true);
		$service_mock->expects($this->once())
			->method('regenerate_title')
			->with($this->callback(function ($context) {
				return isset($context['custom_directive'])
					&& $context['custom_directive'] === 'Make it punchier and shorter'
					&& isset($context['directive_preset'])
					&& $context['directive_preset'] === 'punchy'
					&& isset($context['model_override'])
					&& $context['model_override'] === 'gpt-4o'
					&& isset($context['temperature_override'])
					&& $context['temperature_override'] === 0.35
					&& !empty($context['downstream_cascaded']);
			}))
			->willReturn('Regenerated Punchy Title');

		$controller = new AIPS_AI_Edit_Controller($service_mock, $this->history_repository);

		$_POST = array(
			'action'               => 'aips_regenerate_component',
			'post_id'              => $post_id,
			'history_id'           => $history_id,
			'component'            => 'title',
			'current_value'        => 'Original Test Title',
			'custom_directive'     => 'Make it punchier and shorter',
			'directive_preset'     => 'punchy',
			'model_override'       => 'gpt-4o',
			'temperature_override' => '0.35',
			'downstream_cascaded'  => 'true',
			'nonce'                => wp_create_nonce('aips_ajax_nonce'),
		);
		$this->sync_request_from_post();

		ob_start();
		try {
			$controller->ajax_regenerate_component();
		} catch (WPAjaxDieContinueException $e) {
			// Expected.
		}
		$output = ob_get_clean();
		$response = json_decode($output, true);

		$this->assertTrue($response['success']);
		$this->assertSame('Regenerated Punchy Title', $response['data']['new_value']);
		$this->assertSame('gpt-4o', $response['data']['model']);
		$this->assertSame(0.35, $response['data']['temperature']);

		// Verify overrides stuck in post meta
		$overrides = get_post_meta($post_id, AIPS_Post_Manager::META_AI_EDIT_OVERRIDES, true);
		$this->assertIsArray($overrides);
		$this->assertArrayHasKey('title', $overrides);
		$this->assertSame('gpt-4o', $overrides['title']['model']);
		$this->assertSame(0.35, $overrides['title']['temperature']);
	}

	/**
	 * Test that ajax_reset_component_overrides resets post meta and returns defaults.
	 */
	public function test_ajax_reset_component_overrides() {
		$post_id = $this->factory->post->create(array(
			'post_title' => 'Post With Overrides',
		));

		update_post_meta($post_id, AIPS_Post_Manager::META_AI_EDIT_OVERRIDES, array(
			'title' => array(
				'model'       => 'gpt-4o',
				'temperature' => 0.2,
			),
			'excerpt' => array(
				'model'       => 'claude-3-5-sonnet-20241022',
				'temperature' => 0.8,
			),
		));

		$_POST = array(
			'action'    => 'aips_reset_component_overrides',
			'post_id'   => $post_id,
			'component' => 'title',
			'nonce'     => wp_create_nonce('aips_ajax_nonce'),
		);
		$this->sync_request_from_post();

		ob_start();
		try {
			$this->controller->ajax_reset_component_overrides();
		} catch (WPAjaxDieContinueException $e) {
			// Expected.
		}
		$output = ob_get_clean();
		$response = json_decode($output, true);

		$this->assertTrue($response['success']);
		$this->assertArrayHasKey('default_temperature', $response['data']);

		// Verify title override was removed but excerpt remains
		$overrides = get_post_meta($post_id, AIPS_Post_Manager::META_AI_EDIT_OVERRIDES, true);
		$this->assertIsArray($overrides);
		$this->assertArrayNotHasKey('title', $overrides);
		$this->assertArrayHasKey('excerpt', $overrides);
		$this->assertSame('claude-3-5-sonnet-20241022', $overrides['excerpt']['model']);

		// Reset excerpt as well; post meta should now be deleted completely
		$_POST['component'] = 'excerpt';
		$this->sync_request_from_post();

		ob_start();
		try {
			$this->controller->ajax_reset_component_overrides();
		} catch (WPAjaxDieContinueException $e) {
			// Expected.
		}
		$output = ob_get_clean();
		$response = json_decode($output, true);

		$this->assertTrue($response['success']);
		$overrides_after = get_post_meta($post_id, AIPS_Post_Manager::META_AI_EDIT_OVERRIDES, true);
		$this->assertEmpty($overrides_after);
	}
}
