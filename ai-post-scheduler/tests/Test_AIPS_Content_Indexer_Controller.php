<?php
/**
 * Tests for AIPS_Content_Indexer_Controller.
 *
 * @package AI_Post_Scheduler
 */

class Test_AIPS_Content_Indexer_Controller extends WP_UnitTestCase {

	/** @var AIPS_Content_Indexer_Controller */
	private $controller;

	public function setUp(): void {
		parent::setUp();
		$this->controller = new AIPS_Content_Indexer_Controller();
	}

	public function tearDown(): void {
		delete_option( 'aips_indexer_verbose_history' );
		$_POST = array();
		$_REQUEST = array();
		wp_set_current_user( 0 );
		parent::tearDown();
	}

	private function capture_ajax_response( callable $callable ) {
		ob_start();
		try {
			$callable();
		} catch ( WPAjaxDieContinueException $e ) {
			// Expected after wp_send_json_*.
		} catch ( WPAjaxDieStopException $e ) {
			// Expected after wp_send_json_*.
		}

		return json_decode( ob_get_clean(), true );
	}

	/**
	 * Test controller registers expected AJAX actions in WordPress.
	 */
	public function test_ajax_hooks_registered() {
		$actions = array(
			'wp_ajax_aips_indexer_get_status',
			'wp_ajax_aips_indexer_get_clear_preview',
			'wp_ajax_aips_indexer_clear_index',
			'wp_ajax_aips_indexer_get_graph',
			'wp_ajax_aips_indexer_run_cannibalization_audit',
			'wp_ajax_aips_indexer_save_settings',
			'wp_ajax_aips_indexer_search_posts',
			'wp_ajax_aips_indexer_fetch_meow_environments',
		);

		foreach ( $actions as $action ) {
			$this->assertGreaterThan(
				0,
				has_action( $action ),
				"Action {$action} should be registered by controller constructor"
			);
		}
	}

	public function test_ajax_save_settings_updates_verbose_history_flag() {
		$admin_user_id = $this->factory->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $admin_user_id );

		$_POST = array(
			'nonce'           => wp_create_nonce( 'aips_ajax_nonce' ),
			'verbose_history' => 'true',
		);
		$_REQUEST = $_POST;

		$response = $this->capture_ajax_response( array( $this->controller, 'ajax_save_settings' ) );

		$this->assertTrue( $response['success'] );
		$this->assertTrue( (bool) get_option( 'aips_indexer_verbose_history' ) );

		$_POST['verbose_history'] = 'false';
		$_REQUEST = $_POST;
		$response = $this->capture_ajax_response( array( $this->controller, 'ajax_save_settings' ) );

		$this->assertTrue( $response['success'] );
		$this->assertFalse( (bool) get_option( 'aips_indexer_verbose_history' ) );
	}

	/**
	 * The verbose history control now lives on the main Settings screen
	 * (the Content Intelligence hub no longer renders a settings form).
	 */
	public function test_settings_template_exposes_verbose_history_control() {
		$ui = new AIPS_Settings_UI();

		ob_start();
		$ui->indexer_verbose_history_field_callback();
		$html = ob_get_clean();

		$this->assertStringContainsString( 'name="aips_indexer_verbose_history"', $html );
	}

	/**
	 * Cluster endpoints must resolve their AIPS_Similarity_Evaluator methods
	 * (previously a fatal "undefined method" after the clusters consolidation).
	 */
	public function test_ajax_get_post_clusters_returns_clusters_and_orphans() {
		$admin_user_id = $this->factory->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $admin_user_id );

		$evaluator = $this->createMock( AIPS_Similarity_Evaluator::class );
		$evaluator->expects( $this->once() )
			->method( 'detect_post_clusters' )
			->with( 0.7 )
			->willReturn( array( 'cluster_1' => array( 'id' => 'cluster_1' ) ) );
		$evaluator->expects( $this->once() )
			->method( 'get_orphan_posts' )
			->with( 0.7 )
			->willReturn( array( array( 'id' => 5 ) ) );

		$controller = new AIPS_Content_Indexer_Controller( null, null, null, null, null, $evaluator );

		$_POST = array(
			'nonce'     => wp_create_nonce( 'aips_ajax_nonce' ),
			'threshold' => '0.7',
		);
		$_REQUEST = $_POST;

		$response = $this->capture_ajax_response( array( $controller, 'ajax_get_post_clusters' ) );

		$this->assertTrue( $response['success'] );
		$this->assertSame( 1, $response['data']['count'] );
		$this->assertSame( 'cluster_1', $response['data']['clusters'][0]['id'] );
		$this->assertSame( 5, $response['data']['orphans'][0]['id'] );
		$this->assertArrayHasKey( 'truncated', $response['data'] );
		$this->assertArrayHasKey( 'meta', $response['data'] );
	}

	public function test_ajax_get_post_clusters_separates_unlinked_posts_from_orphans() {
		$admin_user_id = $this->factory->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $admin_user_id );

		$evaluator = $this->createMock( AIPS_Similarity_Evaluator::class );
		$evaluator->method( 'detect_post_clusters' )
			->with( 0.7, 5 )
			->willReturn( array( 'cluster_1' => array( 'id' => 'cluster_1', 'member_ids' => array( 1, 2 ), 'post_count' => 2 ) ) );
		$evaluator->expects( $this->once() )
			->method( 'get_orphan_posts' )
			->with( 0.7, 'hybrid', array( 1, 2 ) )
			->willReturn( array(
				array( 'id' => 5, 'orphan_type' => 'semantic_island' ),
				array( 'id' => 6, 'orphan_type' => 'isolated_and_unlinked' ),
				array( 'id' => 7, 'orphan_type' => 'unlinked_post' ),
			) );

		$controller = new AIPS_Content_Indexer_Controller( null, null, null, null, null, $evaluator );

		$_POST = array(
			'nonce'     => wp_create_nonce( 'aips_ajax_nonce' ),
			'threshold' => '0.7',
			'min_size'  => '5',
		);
		$_REQUEST = $_POST;

		$response = $this->capture_ajax_response( array( $controller, 'ajax_get_post_clusters' ) );

		$this->assertSame( array( 5, 6 ), wp_list_pluck( $response['data']['orphans'], 'id' ) );
		$this->assertSame( 2, $response['data']['stats']['orphan_posts'] );
		$this->assertSame( 2, $response['data']['stats']['unlinked_posts'] );
	}

	public function test_ajax_rename_post_cluster_delegates_to_evaluator() {
		$admin_user_id = $this->factory->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $admin_user_id );

		$evaluator = $this->createMock( AIPS_Similarity_Evaluator::class );
		$evaluator->expects( $this->once() )
			->method( 'rename_post_cluster' )
			->with( 'cluster_1', 'New Name' )
			->willReturn( true );

		$controller = new AIPS_Content_Indexer_Controller( null, null, null, null, null, $evaluator );

		$_POST = array(
			'nonce'       => wp_create_nonce( 'aips_ajax_nonce' ),
			'cluster_id'  => 'cluster_1',
			'custom_name' => 'New Name',
		);
		$_REQUEST = $_POST;

		$response = $this->capture_ajax_response( array( $controller, 'ajax_rename_post_cluster' ) );

		$this->assertTrue( $response['success'] );
	}

	public function test_clear_index_requires_the_confirmation_word() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$_POST    = array( 'nonce' => wp_create_nonce( 'aips_ajax_nonce' ) );
		$_REQUEST = $_POST;

		$repo = new AIPS_Embeddings_Repository();
		$repo->upsert( 'topic', 901, array( 0.1, 0.2 ), 'm', 2, 'h' );

		$response = $this->capture_ajax_response( array( $this->controller, 'ajax_clear_index' ) );

		$this->assertFalse( $response['success'] );
		$this->assertNotNull( $repo->get_by_topic_id( 901 ), 'Nothing is deleted without the confirmation word.' );
	}

	public function test_clear_index_with_confirmation_word_clears_everything_and_stops_the_run() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$_POST    = array(
			'nonce'        => wp_create_nonce( 'aips_ajax_nonce' ),
			'confirm_word' => AIPS_Content_Indexer_Controller::CLEAR_CONFIRM_WORD,
		);
		$_REQUEST = $_POST;

		$repo = new AIPS_Embeddings_Repository();
		$repo->upsert( 'topic', 902, array( 0.1, 0.2 ), 'm', 2, 'h' );

		$response = $this->capture_ajax_response( array( $this->controller, 'ajax_clear_index' ) );

		$this->assertTrue( $response['success'] );
		$this->assertNull( $repo->get_by_topic_id( 902 ) );
	}

	public function test_clear_preview_reports_row_counts() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$_POST    = array( 'nonce' => wp_create_nonce( 'aips_ajax_nonce' ) );
		$_REQUEST = $_POST;

		$repo = new AIPS_Embeddings_Repository();
		$repo->upsert( 'topic', 903, array( 0.1, 0.2 ), 'm', 2, 'h' );

		$response = $this->capture_ajax_response( array( $this->controller, 'ajax_get_clear_preview' ) );

		$this->assertTrue( $response['success'] );
		$this->assertGreaterThanOrEqual( 1, $response['data']['topics'] );
		$this->assertArrayHasKey( 'relationships', $response['data'] );
		$this->assertArrayHasKey( 'total', $response['data'] );
	}

	public function test_clear_index_is_refused_while_a_slice_is_running() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$_POST    = array(
			'nonce'        => wp_create_nonce( 'aips_ajax_nonce' ),
			'confirm_word' => AIPS_Content_Indexer_Controller::CLEAR_CONFIRM_WORD,
		);
		$_REQUEST = $_POST;

		$repo = new AIPS_Embeddings_Repository();
		$repo->upsert( 'topic', 904, array( 0.1, 0.2 ), 'm', 2, 'h' );
		set_transient( 'aips_bg_lock_content_indexing', 1, 60 );

		$response = $this->capture_ajax_response( array( $this->controller, 'ajax_clear_index' ) );
		delete_transient( 'aips_bg_lock_content_indexing' );

		$this->assertFalse( $response['success'] );
		$this->assertNotNull( $repo->get_by_topic_id( 904 ), 'Nothing is deleted while a slice could write it back.' );
	}

	public function test_clear_index_drops_pending_suggestions_but_keeps_reviewed_ones() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$_POST    = array(
			'nonce'        => wp_create_nonce( 'aips_ajax_nonce' ),
			'confirm_word' => AIPS_Content_Indexer_Controller::CLEAR_CONFIRM_WORD,
		);
		$_REQUEST = $_POST;

		$links    = new AIPS_Internal_Links_Repository();
		$pending  = $links->insert( 10, 11, 0.9 );
		$accepted = $links->insert( 10, 12, 0.8 );
		$links->update_status( $accepted, 'accepted' );

		$response = $this->capture_ajax_response( array( $this->controller, 'ajax_clear_index' ) );

		$this->assertTrue( $response['success'] );
		$this->assertNull( $links->get_by_id( $pending ) );
		$this->assertNotNull( $links->get_by_id( $accepted ) );
	}
}
