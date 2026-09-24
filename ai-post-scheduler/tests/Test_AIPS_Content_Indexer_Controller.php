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
			'wp_ajax_aips_indexer_process_batch',
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
}
