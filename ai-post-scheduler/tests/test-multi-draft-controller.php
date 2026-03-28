<?php
/**
 * Tests for AIPS_Multi_Draft_Controller
 *
 * Covers the two AJAX endpoints:
 *   - ajax_generate_variants  (nonce, permissions, input validation)
 *   - ajax_apply_merged_draft (nonce, permissions, input validation, post update, sanitization)
 *
 * @package AI_Post_Scheduler
 */

class Test_AIPS_Multi_Draft_Controller extends WP_UnitTestCase {

	/** @var AIPS_Multi_Draft_Controller */
	private $controller;

	/** @var int Admin user ID. */
	private $admin_user_id;

	/** @var int Subscriber user ID (no edit_posts). */
	private $subscriber_user_id;

	/**
	 * Set up test fixtures: instantiate the controller and create test users.
	 */
	public function setUp(): void {
		parent::setUp();

		$this->controller = new AIPS_Multi_Draft_Controller();

		$this->admin_user_id      = $this->factory->user->create( array( 'role' => 'administrator' ) );
		$this->subscriber_user_id = $this->factory->user->create( array( 'role' => 'subscriber' ) );

		wp_set_current_user( $this->admin_user_id );
	}

	// -----------------------------------------------------------------------
	// Instantiation
	// -----------------------------------------------------------------------

	/**
	 * @covers AIPS_Multi_Draft_Controller::__construct
	 */
	public function test_controller_instantiation() {
		$this->assertInstanceOf( 'AIPS_Multi_Draft_Controller', $this->controller );
	}

	// -----------------------------------------------------------------------
	// AJAX hook registration
	// -----------------------------------------------------------------------

	/**
	 * @covers AIPS_Multi_Draft_Controller::__construct
	 */
	public function test_ajax_actions_registered() {
		$this->assertTrue( has_action( 'wp_ajax_aips_generate_variants' ) !== false );
		$this->assertTrue( has_action( 'wp_ajax_aips_apply_merged_draft' ) !== false );
	}

	// -----------------------------------------------------------------------
	// get_max_variants
	// -----------------------------------------------------------------------

	/**
	 * @covers AIPS_Multi_Draft_Controller::get_max_variants
	 */
	public function test_get_max_variants_returns_default() {
		delete_option( 'aips_multi_draft_max_variants' );
		$this->assertEquals( 3, AIPS_Multi_Draft_Controller::get_max_variants() );
	}

	/**
	 * @covers AIPS_Multi_Draft_Controller::get_max_variants
	 */
	public function test_get_max_variants_clamps_to_min_two() {
		update_option( 'aips_multi_draft_max_variants', 1 );
		$this->assertEquals( 2, AIPS_Multi_Draft_Controller::get_max_variants() );
	}

	/**
	 * @covers AIPS_Multi_Draft_Controller::get_max_variants
	 */
	public function test_get_max_variants_clamps_to_max_three() {
		update_option( 'aips_multi_draft_max_variants', 99 );
		$this->assertEquals( 3, AIPS_Multi_Draft_Controller::get_max_variants() );
	}

	// -----------------------------------------------------------------------
	// ajax_generate_variants — security and input validation
	// -----------------------------------------------------------------------

	/**
	 * Invalid nonce must abort the request.
	 *
	 * @covers AIPS_Multi_Draft_Controller::ajax_generate_variants
	 */
	public function test_generate_variants_requires_valid_nonce() {
		global $wpdb;
		if ( property_exists( $wpdb, 'get_col_return_val' ) ) {
			$this->markTestSkipped( 'Nonce abort test requires WP environment (history lookup unavailable in limited mode).' );
		}

		$_POST = array(
			'action'    => 'aips_generate_variants',
			'nonce'     => 'bad_nonce',
			'post_id'   => 1,
			'history_id' => 1,
		);

		$exception_thrown = false;
		ob_start();
		try {
			$this->controller->ajax_generate_variants();
		} catch ( WPAjaxDieStopException $e ) {
			$exception_thrown = true;
		} catch ( WPAjaxDieContinueException $e ) {
			$exception_thrown = true;
		}
		ob_end_clean();

		$this->assertTrue( $exception_thrown, 'Invalid nonce should abort the request.' );
	}

	/**
	 * Subscriber (no edit_posts cap) must be denied.
	 *
	 * @covers AIPS_Multi_Draft_Controller::ajax_generate_variants
	 */
	public function test_generate_variants_requires_edit_posts_cap() {
		wp_set_current_user( $this->subscriber_user_id );

		$_POST = array(
			'action'    => 'aips_generate_variants',
			'nonce'     => wp_create_nonce( 'aips_ajax_nonce' ),
			'post_id'   => 1,
			'history_id' => 1,
		);

		ob_start();
		try {
			$this->controller->ajax_generate_variants();
		} catch ( WPAjaxDieContinueException $e ) {
			// Expected from wp_send_json_error.
		}
		$output   = ob_get_clean();
		$response = json_decode( $output, true );

		$this->assertFalse( $response['success'] );
		$this->assertStringContainsString( 'Permission denied', $response['data']['message'] );
	}

	/**
	 * Missing post_id must return an error.
	 *
	 * @covers AIPS_Multi_Draft_Controller::ajax_generate_variants
	 */
	public function test_generate_variants_requires_post_id() {
		$_POST = array(
			'action'     => 'aips_generate_variants',
			'nonce'      => wp_create_nonce( 'aips_ajax_nonce' ),
			'history_id' => 1,
		);

		ob_start();
		try {
			$this->controller->ajax_generate_variants();
		} catch ( WPAjaxDieContinueException $e ) {
			// Expected.
		}
		$output   = ob_get_clean();
		$response = json_decode( $output, true );

		$this->assertFalse( $response['success'] );
		$this->assertStringContainsString( 'Invalid request', $response['data']['message'] );
	}

	/**
	 * Missing history_id must return an error.
	 *
	 * @covers AIPS_Multi_Draft_Controller::ajax_generate_variants
	 */
	public function test_generate_variants_requires_history_id() {
		$post_id = $this->factory->post->create();

		$_POST = array(
			'action'  => 'aips_generate_variants',
			'nonce'   => wp_create_nonce( 'aips_ajax_nonce' ),
			'post_id' => $post_id,
		);

		ob_start();
		try {
			$this->controller->ajax_generate_variants();
		} catch ( WPAjaxDieContinueException $e ) {
			// Expected.
		}
		$output   = ob_get_clean();
		$response = json_decode( $output, true );

		$this->assertFalse( $response['success'] );
		$this->assertStringContainsString( 'Invalid request', $response['data']['message'] );
	}

	/**
	 * A user who cannot edit a specific post (e.g. another author's post) must be denied.
	 *
	 * @covers AIPS_Multi_Draft_Controller::ajax_generate_variants
	 */
	public function test_generate_variants_denies_user_without_edit_post_cap() {
		global $wpdb;
		if ( property_exists( $wpdb, 'get_col_return_val' ) ) {
			$this->markTestSkipped( 'Per-post capability check requires WP environment (history lookup unavailable in limited mode).' );
		}

		$other_user_id = $this->factory->user->create( array( 'role' => 'author' ) );
		$post_id       = $this->factory->post->create( array( 'post_author' => $other_user_id ) );

		// Another author who did not create this post.
		$current_author_id = $this->factory->user->create( array( 'role' => 'author' ) );
		wp_set_current_user( $current_author_id );

		$_POST = array(
			'action'     => 'aips_generate_variants',
			'nonce'      => wp_create_nonce( 'aips_ajax_nonce' ),
			'post_id'    => $post_id,
			'history_id' => 1,
		);

		ob_start();
		try {
			$this->controller->ajax_generate_variants();
		} catch ( WPAjaxDieContinueException $e ) {
			// Expected.
		}
		$output   = ob_get_clean();
		$response = json_decode( $output, true );

		$this->assertFalse( $response['success'] );
		$this->assertStringContainsString( 'permission', strtolower( $response['data']['message'] ) );
	}

	// -----------------------------------------------------------------------
	// ajax_apply_merged_draft — security and input validation
	// -----------------------------------------------------------------------

	/**
	 * Invalid nonce must abort the request.
	 *
	 * @covers AIPS_Multi_Draft_Controller::ajax_apply_merged_draft
	 */
	public function test_apply_merged_draft_requires_valid_nonce() {
		global $wpdb;
		if ( property_exists( $wpdb, 'get_col_return_val' ) ) {
			$this->markTestSkipped( 'Nonce abort test requires WP environment.' );
		}

		$_POST = array(
			'action'  => 'aips_apply_merged_draft',
			'nonce'   => 'bad_nonce',
			'post_id' => 1,
		);

		$exception_thrown = false;
		ob_start();
		try {
			$this->controller->ajax_apply_merged_draft();
		} catch ( WPAjaxDieStopException $e ) {
			$exception_thrown = true;
		} catch ( WPAjaxDieContinueException $e ) {
			$exception_thrown = true;
		}
		ob_end_clean();

		$this->assertTrue( $exception_thrown, 'Invalid nonce should abort the request.' );
	}

	/**
	 * Subscriber (no edit_posts cap) must be denied.
	 *
	 * @covers AIPS_Multi_Draft_Controller::ajax_apply_merged_draft
	 */
	public function test_apply_merged_draft_requires_edit_posts_cap() {
		wp_set_current_user( $this->subscriber_user_id );

		$_POST = array(
			'action'     => 'aips_apply_merged_draft',
			'nonce'      => wp_create_nonce( 'aips_ajax_nonce' ),
			'post_id'    => 1,
			'components' => array( 'title' => 'Hi' ),
		);

		ob_start();
		try {
			$this->controller->ajax_apply_merged_draft();
		} catch ( WPAjaxDieContinueException $e ) {
			// Expected.
		}
		$output   = ob_get_clean();
		$response = json_decode( $output, true );

		$this->assertFalse( $response['success'] );
		$this->assertStringContainsString( 'Permission denied', $response['data']['message'] );
	}

	/**
	 * Missing post_id must return an error.
	 *
	 * @covers AIPS_Multi_Draft_Controller::ajax_apply_merged_draft
	 */
	public function test_apply_merged_draft_requires_post_id() {
		$_POST = array(
			'action'     => 'aips_apply_merged_draft',
			'nonce'      => wp_create_nonce( 'aips_ajax_nonce' ),
			'components' => array( 'title' => 'Hello' ),
		);

		ob_start();
		try {
			$this->controller->ajax_apply_merged_draft();
		} catch ( WPAjaxDieContinueException $e ) {
			// Expected.
		}
		$output   = ob_get_clean();
		$response = json_decode( $output, true );

		$this->assertFalse( $response['success'] );
		$this->assertStringContainsString( 'Invalid request', $response['data']['message'] );
	}

	/**
	 * Empty components array must return an error.
	 *
	 * @covers AIPS_Multi_Draft_Controller::ajax_apply_merged_draft
	 */
	public function test_apply_merged_draft_requires_non_empty_components() {
		$post_id = $this->factory->post->create();

		$_POST = array(
			'action'     => 'aips_apply_merged_draft',
			'nonce'      => wp_create_nonce( 'aips_ajax_nonce' ),
			'post_id'    => $post_id,
			'components' => array(),
		);

		ob_start();
		try {
			$this->controller->ajax_apply_merged_draft();
		} catch ( WPAjaxDieContinueException $e ) {
			// Expected.
		}
		$output   = ob_get_clean();
		$response = json_decode( $output, true );

		$this->assertFalse( $response['success'] );
		$this->assertStringContainsString( 'Invalid request', $response['data']['message'] );
	}

	/**
	 * A components array with only empty values must return a "No components selected" error.
	 *
	 * @covers AIPS_Multi_Draft_Controller::ajax_apply_merged_draft
	 */
	public function test_apply_merged_draft_requires_non_empty_component_values() {
		$post_id = $this->factory->post->create();

		$_POST = array(
			'action'     => 'aips_apply_merged_draft',
			'nonce'      => wp_create_nonce( 'aips_ajax_nonce' ),
			'post_id'    => $post_id,
			'components' => array(
				'title'   => '',
				'content' => '',
			),
		);

		ob_start();
		try {
			$this->controller->ajax_apply_merged_draft();
		} catch ( WPAjaxDieContinueException $e ) {
			// Expected.
		}
		$output   = ob_get_clean();
		$response = json_decode( $output, true );

		$this->assertFalse( $response['success'] );
		$this->assertStringContainsString( 'No components selected', $response['data']['message'] );
	}

	/**
	 * A valid request must update the post and return success.
	 *
	 * @covers AIPS_Multi_Draft_Controller::ajax_apply_merged_draft
	 */
	public function test_apply_merged_draft_updates_post_successfully() {
		$post_id = $this->factory->post->create( array(
			'post_title'   => 'Original Title',
			'post_excerpt' => 'Original excerpt',
			'post_content' => 'Original content',
		) );

		$_POST = array(
			'action'     => 'aips_apply_merged_draft',
			'nonce'      => wp_create_nonce( 'aips_ajax_nonce' ),
			'post_id'    => $post_id,
			'components' => array(
				'title'   => 'Merged Title',
				'excerpt' => 'Merged excerpt',
				'content' => '<p>Merged content</p>',
			),
		);

		ob_start();
		try {
			$this->controller->ajax_apply_merged_draft();
		} catch ( WPAjaxDieContinueException $e ) {
			// Expected from wp_send_json_success.
		}
		$output   = ob_get_clean();
		$response = json_decode( $output, true );

		$this->assertTrue( $response['success'] );
		$this->assertStringContainsString( 'successfully', $response['data']['message'] );

		$updated = get_post( $post_id );
		$this->assertEquals( 'Merged Title', $updated->post_title );
		$this->assertEquals( 'Merged excerpt', $updated->post_excerpt );
		$this->assertEquals( '<p>Merged content</p>', $updated->post_content );

		// Response should list the updated components.
		$this->assertContains( 'title', $response['data']['updated_components'] );
		$this->assertContains( 'excerpt', $response['data']['updated_components'] );
		$this->assertContains( 'content', $response['data']['updated_components'] );
	}

	/**
	 * Only the fields present in components should be updated; others must stay untouched.
	 *
	 * @covers AIPS_Multi_Draft_Controller::ajax_apply_merged_draft
	 */
	public function test_apply_merged_draft_updates_only_provided_components() {
		$post_id = $this->factory->post->create( array(
			'post_title'   => 'Keep This Title',
			'post_excerpt' => 'Keep This Excerpt',
			'post_content' => 'Replace This Content',
		) );

		$_POST = array(
			'action'     => 'aips_apply_merged_draft',
			'nonce'      => wp_create_nonce( 'aips_ajax_nonce' ),
			'post_id'    => $post_id,
			'components' => array(
				'content' => '<p>New content</p>',
			),
		);

		ob_start();
		try {
			$this->controller->ajax_apply_merged_draft();
		} catch ( WPAjaxDieContinueException $e ) {
			// Expected.
		}
		$output   = ob_get_clean();
		$response = json_decode( $output, true );

		$this->assertTrue( $response['success'] );

		$updated = get_post( $post_id );
		// Title and excerpt should remain unchanged.
		$this->assertEquals( 'Keep This Title', $updated->post_title );
		$this->assertEquals( 'Keep This Excerpt', $updated->post_excerpt );
		$this->assertEquals( '<p>New content</p>', $updated->post_content );

		$this->assertContains( 'content', $response['data']['updated_components'] );
		$this->assertNotContains( 'title', $response['data']['updated_components'] );
		$this->assertNotContains( 'excerpt', $response['data']['updated_components'] );
	}

	/**
	 * Title input must be sanitized (script tags stripped).
	 *
	 * @covers AIPS_Multi_Draft_Controller::ajax_apply_merged_draft
	 */
	public function test_apply_merged_draft_sanitizes_title() {
		$post_id = $this->factory->post->create();

		$_POST = array(
			'action'     => 'aips_apply_merged_draft',
			'nonce'      => wp_create_nonce( 'aips_ajax_nonce' ),
			'post_id'    => $post_id,
			'components' => array(
				'title' => '<script>alert("xss")</script>Clean Title',
			),
		);

		ob_start();
		try {
			$this->controller->ajax_apply_merged_draft();
		} catch ( WPAjaxDieContinueException $e ) {
			// Expected.
		}
		$output   = ob_get_clean();
		$response = json_decode( $output, true );

		$this->assertTrue( $response['success'] );

		$updated = get_post( $post_id );
		$this->assertStringNotContainsString( '<script>', $updated->post_title );
		$this->assertStringContainsString( 'Clean Title', $updated->post_title );
	}

	/**
	 * Content input allows safe HTML but strips dangerous tags.
	 *
	 * @covers AIPS_Multi_Draft_Controller::ajax_apply_merged_draft
	 */
	public function test_apply_merged_draft_sanitizes_content() {
		$post_id = $this->factory->post->create();

		$_POST = array(
			'action'     => 'aips_apply_merged_draft',
			'nonce'      => wp_create_nonce( 'aips_ajax_nonce' ),
			'post_id'    => $post_id,
			'components' => array(
				'content' => '<p>Safe</p><script>alert("xss")</script>',
			),
		);

		ob_start();
		try {
			$this->controller->ajax_apply_merged_draft();
		} catch ( WPAjaxDieContinueException $e ) {
			// Expected.
		}
		$output   = ob_get_clean();
		$response = json_decode( $output, true );

		$this->assertTrue( $response['success'] );

		$updated = get_post( $post_id );
		$this->assertStringContainsString( '<p>Safe</p>', $updated->post_content );
		$this->assertStringNotContainsString( '<script>', $updated->post_content );
	}

	/**
	 * The aips_post_components_updated action must fire when a draft is applied.
	 *
	 * @covers AIPS_Multi_Draft_Controller::ajax_apply_merged_draft
	 */
	public function test_apply_merged_draft_fires_action_hook() {
		$post_id          = $this->factory->post->create();
		$action_fired     = false;
		$captured_post_id = null;

		add_action( 'aips_post_components_updated', function( $pid ) use ( &$action_fired, &$captured_post_id ) {
			$action_fired     = true;
			$captured_post_id = $pid;
		} );

		$_POST = array(
			'action'     => 'aips_apply_merged_draft',
			'nonce'      => wp_create_nonce( 'aips_ajax_nonce' ),
			'post_id'    => $post_id,
			'components' => array(
				'title' => 'Hook Test Title',
			),
		);

		ob_start();
		try {
			$this->controller->ajax_apply_merged_draft();
		} catch ( WPAjaxDieContinueException $e ) {
			// Expected.
		}
		ob_end_clean();

		$this->assertTrue( $action_fired );
		$this->assertEquals( $post_id, $captured_post_id );
	}
}
