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
		// Create a test post and history
		$post_id = $this->factory->post->create(array(
			'post_title' => 'Test Post',
			'post_content' => 'Test content',
			'post_excerpt' => 'Test excerpt',
		));
		
		// Create template
		$template_id = $this->template_repository->create(array(
			'name' => 'Test Template',
			'system_prompt' => 'Test prompt',
			'user_prompt' => 'Test user prompt',
			'is_active' => 1,
		));
		
		// Create history record
		$history_id = $this->history_repository->create(array(
			'template_id' => $template_id,
			'post_id' => $post_id,
			'status' => 'completed',
		));
		
		// Set up request with valid nonce
		$_POST = array(
			'action' => 'aips_get_post_components',
			'post_id' => $post_id,
			'history_id' => $history_id,
			'nonce' => wp_create_nonce('aips_ajax_nonce'),
		);
		
		// This should succeed (just checking it doesn't throw an error)
		ob_start();
		try {
			$this->controller->ajax_get_post_components();
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
		
		$_POST = array(
			'action' => 'aips_save_post_components',
			'post_id' => $post_id,
			'components' => array(
				'title' => 'New Title',
				'excerpt' => 'New excerpt',
				'content' => 'New content',
			),
			'nonce' => wp_create_nonce('aips_ajax_nonce'),
		);
		
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
		$this->assertEquals('New Title', $updated_post->post_title);
		$this->assertEquals('New excerpt', $updated_post->post_excerpt);
		$this->assertEquals('New content', $updated_post->post_content);
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
			'ai_response',
			array(
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

		$attachment_id = $this->factory->post->create(array(
			'post_type' => 'attachment',
			'post_mime_type' => 'image/jpeg',
			'post_title' => 'Image Attachment',
			'post_status' => 'inherit',
		));

		$history_id = $this->history_repository->create(array(
			'post_id' => $post_id,
			'status' => 'completed',
		));

		$revision_id = $this->history_repository->add_log_entry(
			$history_id,
			'ai_response',
			array(
				'message' => 'Image Snapshot',
				'output' => array(
					'attachment_id' => $attachment_id,
					'url' => 'https://example.test/image.jpg',
				),
				'context' => array(
					'component' => 'featured_image',
					'post_id' => $post_id,
				),
			),
			AIPS_History_Type::AI_RESPONSE
		);

		$_POST = array(
			'action' => 'aips_restore_component_revision',
			'post_id' => $post_id,
			'component_type' => 'featured_image',
			'revision_id' => $revision_id,
			'nonce' => wp_create_nonce('aips_ajax_nonce'),
		);

		ob_start();
		try {
			$this->controller->ajax_restore_component_revision();
		} catch (WPAjaxDieContinueException $e) {
			// Expected.
		}
		$output = ob_get_clean();
		$response = json_decode($output, true);

		$this->assertTrue($response['success']);
		$this->assertEquals('featured_image', $response['data']['component']);
		$this->assertIsArray($response['data']['value']);
		$this->assertEquals($attachment_id, $response['data']['value']['attachment_id']);
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
			'ai_response',
			array(
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

		ob_start();
		try {
			$this->controller->ajax_restore_component_revision();
		} catch (WPAjaxDieContinueException $e) {
			// Expected.
		}
		ob_end_clean();

		$revisions = $this->history_repository->get_component_revisions($post_id, 'title', 10);

		$this->assertNotEmpty($revisions);
		$this->assertEquals('Manual Draft Title', $revisions[0]['value']);
		$this->assertEquals('manual_edit', $revisions[0]['source']);
		$this->assertEquals('pre_restore_manual', $revisions[0]['reason']);
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
}
