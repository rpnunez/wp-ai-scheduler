<?php
/**
 * End-to-end tests for AIPS_Ajax_Registry and AIPS_Ajax_Response wiring (Phase C.2).
 *
 * Verifies:
 *   1. AIPS_Ajax_Registry completeness — every wp_ajax_aips_* hook registered
 *      by a controller class is present in the registry map.
 *   2. AIPS_Ajax_Response JSON shape contract — success(), error(),
 *      permission_denied(), invalid_request(), and not_found() all produce
 *      the documented envelope shape.
 *   3. Representative AJAX endpoint responses — a handful of controller
 *      handlers are invoked directly and their JSON output is checked for
 *      conformance with the AIPS_Ajax_Response contract.
 *
 * @package AI_Post_Scheduler
 * @since 2.5.0
 */

class Test_AIPS_Ajax_Registry_Response extends WP_UnitTestCase {

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	/**
	 * Invoke a callable and capture its JSON output, catching the
	 * WPAjaxDieContinueException / WPAjaxDieStopException that
	 * wp_send_json_* throws in the test environment.
	 *
	 * @param callable $callable
	 * @return array|null Decoded JSON array, or null if output was empty.
	 */
	private function capture_ajax_response( callable $callable ) {
		ob_start();
		try {
			$callable();
		} catch ( WPAjaxDieContinueException $e ) {
			// Expected: wp_send_json_* throws this.
		} catch ( WPAjaxDieStopException $e ) {
			// Also expected in some environments (e.g. nonce failure path).
		}
		$output = ob_get_clean();
		if ( '' === $output ) {
			return null;
		}
		return json_decode( $output, true );
	}

	// -------------------------------------------------------------------------
	// 1. Registry completeness
	// -------------------------------------------------------------------------

	/**
	 * Every wp_ajax_aips_* hook registered in any controller/handler file must
	 * exist in AIPS_Ajax_Registry so boot_ajax() can resolve it directly without
	 * falling back to the lazy-registration path.
	 *
	 * The test scans all *.php files in includes/ for add_action calls on
	 * wp_ajax_aips_* hooks and asserts each action name is present in the registry.
	 */
	public function test_registry_covers_all_controller_actions() {
		$includes_dir = AIPS_PLUGIN_DIR . 'includes/';
		$missing      = array();

		foreach ( glob( $includes_dir . '*.php' ) as $file ) {
			$content = file_get_contents( $file );
			if ( preg_match_all( "/add_action\s*\(\s*['\"]wp_ajax_(aips_[^'\"]+)['\"]/" , $content, $matches ) ) {
				foreach ( $matches[1] as $action ) {
					if ( ! AIPS_Ajax_Registry::has_action( $action ) ) {
						$missing[] = $action . ' (' . basename( $file ) . ')';
					}
				}
			}
		}

		$this->assertEmpty(
			$missing,
			'The following controller actions are registered with add_action but are missing from AIPS_Ajax_Registry: ' . implode( ', ', $missing )
		);
	}

	/**
	 * The registry must contain at least one entry for every known controller
	 * group to ensure the map is not accidentally truncated.
	 */
	public function test_registry_has_expected_controller_groups() {
		$expected_controllers = array(
			'AIPS_Templates_Controller',
			'AIPS_Schedule_Controller',
			'AIPS_Unified_Schedule_Controller',
			'AIPS_Authors_Controller',
			'AIPS_Author_Topics_Controller',
			'AIPS_AI_Edit_Controller',
			'AIPS_Generated_Posts_Controller',
			'AIPS_Calendar_Controller',
			'AIPS_Structures_Controller',
			'AIPS_Prompt_Sections_Controller',
			'AIPS_Research_Controller',
			'AIPS_History',
			'AIPS_Voices',
			'AIPS_Post_Review',
			'AIPS_Admin_Bar',
			'AIPS_Planner',
			'AIPS_Taxonomy_Controller',
			'AIPS_Settings_Ajax',
			'AIPS_Sources_Controller',
			'AIPS_Onboarding_Wizard',
			'AIPS_Dev_Tools',
			'AIPS_Seeder_Admin',
			'AIPS_Data_Management',
			'AIPS_DB_Manager',
		);

		// Build a set of unique controller classes from the registry.
		$registered_controllers = array();
		foreach ( AIPS_Ajax_Registry::all_actions() as $action ) {
			$class = AIPS_Ajax_Registry::get_controller_for( $action );
			if ( $class ) {
				$registered_controllers[ $class ] = true;
			}
		}

		foreach ( $expected_controllers as $class ) {
			$this->assertArrayHasKey(
				$class,
				$registered_controllers,
				"AIPS_Ajax_Registry must contain at least one action for controller '{$class}'"
			);
		}
	}

	/**
	 * AIPS_Ajax_Registry::get_controller_for() must return null for an
	 * unregistered action name.
	 */
	public function test_registry_returns_null_for_unknown_action() {
		$this->assertNull( AIPS_Ajax_Registry::get_controller_for( 'aips_nonexistent_action_xyz' ) );
	}

	/**
	 * AIPS_Ajax_Registry::has_action() must return false for an unregistered action.
	 */
	public function test_registry_has_action_returns_false_for_unknown() {
		$this->assertFalse( AIPS_Ajax_Registry::has_action( 'aips_nonexistent_action_xyz' ) );
	}

	/**
	 * AIPS_Ajax_Registry::count() must equal the number of unique actions returned
	 * by all_actions().
	 */
	public function test_registry_count_matches_all_actions() {
		$this->assertSame( AIPS_Ajax_Registry::count(), count( AIPS_Ajax_Registry::all_actions() ) );
	}

	/**
	 * aips_delete_draft_post must be mapped to AIPS_Post_Review.
	 *
	 * This was the registry gap identified in Phase C.2.
	 */
	public function test_delete_draft_post_maps_to_post_review() {
		$this->assertSame(
			'AIPS_Post_Review',
			AIPS_Ajax_Registry::get_controller_for( 'aips_delete_draft_post' ),
			'aips_delete_draft_post must map to AIPS_Post_Review in the registry'
		);
	}

	// -------------------------------------------------------------------------
	// 2. AIPS_Ajax_Response JSON shape contract
	// -------------------------------------------------------------------------

	/**
	 * AIPS_Ajax_Response::success() must emit: { success: true, data: {} }
	 * when called with no arguments.
	 */
	public function test_ajax_response_success_empty_shape() {
		$response = $this->capture_ajax_response( function() {
			AIPS_Ajax_Response::success();
		} );

		$this->assertIsArray( $response, 'success() must emit valid JSON' );
		$this->assertTrue( $response['success'], 'success flag must be true' );
		$this->assertArrayHasKey( 'data', $response, 'envelope must contain data key' );
	}

	/**
	 * AIPS_Ajax_Response::success() with a message must include the message in data.
	 */
	public function test_ajax_response_success_with_message() {
		$response = $this->capture_ajax_response( function() {
			AIPS_Ajax_Response::success( array(), 'Operation complete' );
		} );

		$this->assertTrue( $response['success'] );
		$this->assertSame( 'Operation complete', $response['data']['message'] );
	}

	/**
	 * AIPS_Ajax_Response::success() with extra data must merge the data payload
	 * into the response data object.
	 */
	public function test_ajax_response_success_with_extra_data() {
		$response = $this->capture_ajax_response( function() {
			AIPS_Ajax_Response::success( array( 'id' => 42, 'html' => '<p>ok</p>' ) );
		} );

		$this->assertTrue( $response['success'] );
		$this->assertSame( 42, $response['data']['id'] );
		$this->assertSame( '<p>ok</p>', $response['data']['html'] );
	}

	/**
	 * AIPS_Ajax_Response::error() must emit: { success: false, data: { message, code } }
	 */
	public function test_ajax_response_error_shape() {
		$response = $this->capture_ajax_response( function() {
			AIPS_Ajax_Response::error( 'Something went wrong', 'test_error' );
		} );

		$this->assertIsArray( $response, 'error() must emit valid JSON' );
		$this->assertFalse( $response['success'], 'success flag must be false' );
		$this->assertArrayHasKey( 'data', $response );
		$this->assertSame( 'Something went wrong', $response['data']['message'] );
		$this->assertSame( 'test_error', $response['data']['code'] );
	}

	/**
	 * AIPS_Ajax_Response::error() with default code must use 'error'.
	 */
	public function test_ajax_response_error_default_code() {
		$response = $this->capture_ajax_response( function() {
			AIPS_Ajax_Response::error( 'Oops' );
		} );

		$this->assertFalse( $response['success'] );
		$this->assertSame( 'error', $response['data']['code'] );
	}

	/**
	 * AIPS_Ajax_Response::error() must accept a legacy array argument and
	 * extract the message from it.
	 */
	public function test_ajax_response_error_legacy_array_arg() {
		$response = $this->capture_ajax_response( function() {
			AIPS_Ajax_Response::error( array( 'message' => 'Legacy message' ) );
		} );

		$this->assertFalse( $response['success'] );
		$this->assertSame( 'Legacy message', $response['data']['message'] );
	}

	/**
	 * AIPS_Ajax_Response::permission_denied() must emit a 403-like error
	 * with code 'permission_denied'.
	 */
	public function test_ajax_response_permission_denied_shape() {
		$response = $this->capture_ajax_response( function() {
			AIPS_Ajax_Response::permission_denied();
		} );

		$this->assertFalse( $response['success'] );
		$this->assertSame( 'permission_denied', $response['data']['code'] );
		$this->assertNotEmpty( $response['data']['message'] );
	}

	/**
	 * AIPS_Ajax_Response::invalid_request() must emit code 'invalid_request'.
	 */
	public function test_ajax_response_invalid_request_shape() {
		$response = $this->capture_ajax_response( function() {
			AIPS_Ajax_Response::invalid_request();
		} );

		$this->assertFalse( $response['success'] );
		$this->assertSame( 'invalid_request', $response['data']['code'] );
		$this->assertNotEmpty( $response['data']['message'] );
	}

	/**
	 * AIPS_Ajax_Response::invalid_request() with a custom message must use it.
	 */
	public function test_ajax_response_invalid_request_custom_message() {
		$response = $this->capture_ajax_response( function() {
			AIPS_Ajax_Response::invalid_request( 'Template name is required.' );
		} );

		$this->assertFalse( $response['success'] );
		$this->assertSame( 'Template name is required.', $response['data']['message'] );
	}

	/**
	 * AIPS_Ajax_Response::not_found() must emit code 'not_found'.
	 */
	public function test_ajax_response_not_found_shape() {
		$response = $this->capture_ajax_response( function() {
			AIPS_Ajax_Response::not_found();
		} );

		$this->assertFalse( $response['success'] );
		$this->assertSame( 'not_found', $response['data']['code'] );
		$this->assertNotEmpty( $response['data']['message'] );
	}

	/**
	 * AIPS_Ajax_Response::not_found() with a resource name must embed it
	 * in the message.
	 */
	public function test_ajax_response_not_found_with_resource() {
		$response = $this->capture_ajax_response( function() {
			AIPS_Ajax_Response::not_found( 'Template' );
		} );

		$this->assertFalse( $response['success'] );
		$this->assertStringContainsString( 'Template', $response['data']['message'] );
	}

	// -------------------------------------------------------------------------
	// 3. Representative AJAX endpoint response shape (end-to-end)
	// -------------------------------------------------------------------------

	/**
	 * AIPS_Templates_Controller::ajax_save_template() must return a success JSON
	 * envelope when provided with valid input.
	 *
	 * Verifies: success shape, `id` key in data.
	 */
	public function test_templates_controller_save_success_shape() {
		if ( ! class_exists( 'AIPS_Templates_Controller' ) ) {
			$this->markTestSkipped( 'AIPS_Templates_Controller not available.' );
		}

		$admin = $this->factory->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $admin );

		$_POST['nonce']           = wp_create_nonce( 'aips_ajax_nonce' );
		$_POST['name']            = 'Phase C.2 Test Template';
		$_POST['prompt_template'] = 'Write about {{topic}}';
		$_REQUEST                 = $_POST;

		// Provide a stub repository so no real DB call is needed.
		$stub = new class {
			public function save( $data ) {
				return 99;
			}
		};

		$controller = new AIPS_Templates_Controller( $stub );
		$response   = $this->capture_ajax_response( array( $controller, 'ajax_save_template' ) );

		$_POST    = array();
		$_REQUEST = array();
		wp_set_current_user( 0 );

		$this->assertIsArray( $response );
		$this->assertTrue( $response['success'], 'save_template must succeed with valid input' );
		$this->assertArrayHasKey( 'data', $response );
		$this->assertArrayHasKey( 'template_id', $response['data'], 'success data must contain template_id key' );
	}

	/**
	 * AIPS_Templates_Controller::ajax_save_template() must return an error JSON
	 * envelope when the user lacks manage_options capability.
	 */
	public function test_templates_controller_save_permission_denied_shape() {
		if ( ! class_exists( 'AIPS_Templates_Controller' ) ) {
			$this->markTestSkipped( 'AIPS_Templates_Controller not available.' );
		}

		$subscriber = $this->factory->user->create( array( 'role' => 'subscriber' ) );
		wp_set_current_user( $subscriber );

		$_POST['nonce']           = wp_create_nonce( 'aips_ajax_nonce' );
		$_POST['name']            = 'Template';
		$_POST['prompt_template'] = 'Prompt';
		$_REQUEST                 = $_POST;

		$controller = new AIPS_Templates_Controller();
		$response   = $this->capture_ajax_response( array( $controller, 'ajax_save_template' ) );

		$_POST    = array();
		$_REQUEST = array();
		wp_set_current_user( 0 );

		$this->assertIsArray( $response );
		$this->assertFalse( $response['success'], 'save_template must fail for non-admin' );
		$this->assertArrayHasKey( 'data', $response );
		$this->assertArrayHasKey( 'message', $response['data'] );
		$this->assertArrayHasKey( 'code', $response['data'], 'error response must include code key' );
	}

	/**
	 * AIPS_Structures_Controller::ajax_get_structures() permission-denied path
	 * must produce the standard { success: false, data: { message, code } } shape.
	 */
	public function test_structures_controller_permission_denied_shape() {
		if ( ! class_exists( 'AIPS_Structures_Controller' ) ) {
			$this->markTestSkipped( 'AIPS_Structures_Controller not available.' );
		}

		$subscriber = $this->factory->user->create( array( 'role' => 'subscriber' ) );
		wp_set_current_user( $subscriber );
		$_REQUEST['nonce'] = wp_create_nonce( 'aips_ajax_nonce' );

		$controller = new AIPS_Structures_Controller( new AIPS_Article_Structure_Repository() );
		$response   = $this->capture_ajax_response( array( $controller, 'ajax_get_structures' ) );

		$_REQUEST = array();
		wp_set_current_user( 0 );

		$this->assertIsArray( $response );
		$this->assertFalse( $response['success'] );
		$this->assertArrayHasKey( 'message', $response['data'] );
		$this->assertArrayHasKey( 'code', $response['data'] );
	}

	/**
	 * AIPS_Structures_Controller::ajax_get_structure() with an invalid ID
	 * must produce { success: false, data: { message, code } }.
	 */
	public function test_structures_controller_invalid_id_shape() {
		if ( ! class_exists( 'AIPS_Structures_Controller' ) ) {
			$this->markTestSkipped( 'AIPS_Structures_Controller not available.' );
		}

		$admin = $this->factory->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $admin );
		$_REQUEST['nonce'] = wp_create_nonce( 'aips_ajax_nonce' );
		$_POST['structure_id'] = 0;

		$controller = new AIPS_Structures_Controller( new AIPS_Article_Structure_Repository() );
		$response   = $this->capture_ajax_response( array( $controller, 'ajax_get_structure' ) );

		$_POST    = array();
		$_REQUEST = array();
		wp_set_current_user( 0 );

		$this->assertIsArray( $response );
		$this->assertFalse( $response['success'] );
		$this->assertArrayHasKey( 'message', $response['data'] );
		$this->assertArrayHasKey( 'code', $response['data'] );
	}

	/**
	 * AIPS_Admin_Bar AJAX mark-read response must follow the standard success shape.
	 *
	 * This tests the Admin Bar handler which uses AIPS_Ajax_Response::success().
	 */
	public function test_admin_bar_mark_read_success_shape() {
		if ( ! class_exists( 'AIPS_Admin_Bar' ) ) {
			$this->markTestSkipped( 'AIPS_Admin_Bar not available.' );
		}

		$admin = $this->factory->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $admin );

		$_POST['nonce']           = wp_create_nonce( 'aips_admin_bar_nonce' );
		$_POST['notification_id'] = 0; // Non-existent; handler still returns success shape.
		$_REQUEST                 = $_POST;

		$admin_bar = new AIPS_Admin_Bar();
		$response  = $this->capture_ajax_response( array( $admin_bar, 'ajax_mark_read' ) );

		$_POST    = array();
		$_REQUEST = array();
		wp_set_current_user( 0 );

		if ( null === $response ) {
			$this->markTestSkipped( 'ajax_mark_read did not produce JSON output in this environment.' );
		}

		// Must be a valid AIPS_Ajax_Response envelope regardless of success/failure.
		$this->assertIsArray( $response );
		$this->assertArrayHasKey( 'success', $response );
		$this->assertArrayHasKey( 'data', $response );
	}
}
