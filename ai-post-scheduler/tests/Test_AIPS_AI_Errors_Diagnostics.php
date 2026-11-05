<?php
/**
 * Test AI Errors Diagnostics Tab and Repository Queries.
 *
 * @package AI_Post_Scheduler
 */

class Test_AIPS_AI_Errors_Diagnostics extends WP_UnitTestCase {

	/**
	 * @var AIPS_History_Repository
	 */
	private $repository;

	/**
	 * @var AIPS_Diagnostics_Controller
	 */
	private $controller;

	/**
	 * Set up test dependencies and clean tables.
	 */
	public function setUp(): void {
		parent::setUp();

		$this->repository = new AIPS_History_Repository();
		$this->controller = new AIPS_Diagnostics_Controller( $this->repository );

		global $wpdb;
		$wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}aips_history_log" );
		$wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}aips_history" );
	}

	/**
	 * Test AIPS_Ajax_Registry registration for AI error actions.
	 */
	public function test_ajax_registry_registration() {
		$this->assertTrue( AIPS_Ajax_Registry::has_action( 'aips_get_ai_errors' ) );
		$this->assertSame( 'AIPS_Diagnostics_Controller', AIPS_Ajax_Registry::get_controller_for( 'aips_get_ai_errors' ) );

		$this->assertTrue( AIPS_Ajax_Registry::has_action( 'aips_get_ai_error_details' ) );
		$this->assertSame( 'AIPS_Diagnostics_Controller', AIPS_Ajax_Registry::get_controller_for( 'aips_get_ai_error_details' ) );
	}

	/**
	 * Test Diagnostics tab availability and metadata.
	 */
	public function test_diagnostics_tab_registration() {
		$tabs = $this->controller->get_tabs();
		$this->assertArrayHasKey( 'ai-errors', $tabs );
		$this->assertSame( 'AI Errors', $tabs['ai-errors']['label'] );
		$this->assertTrue( AIPS_Diagnostics_Controller::is_tab_available( 'ai-errors' ) );
	}

	/**
	 * Test get_ai_errors returns empty structure when no records exist.
	 */
	public function test_get_ai_errors_empty() {
		$results = $this->repository->get_ai_errors();

		$this->assertIsArray( $results );
		$this->assertArrayHasKey( 'items', $results );
		$this->assertArrayHasKey( 'total', $results );
		$this->assertSame( 0, $results['total'] );
		$this->assertEmpty( $results['items'] );
	}

	/**
	 * Test get_ai_errors filters AI call errors vs general errors correctly.
	 */
	public function test_get_ai_errors_scope_filtering() {
		$history_id = $this->repository->create( array(
			'generated_title' => 'Test Article',
			'creation_method' => 'scheduled',
			'status'          => 'failed',
			'created_at'      => AIPS_DateTime::now()->timestamp(),
		) );

		// 1. AI Error log
		$ai_details = array(
			'log_subtype' => 'error',
			'message'     => 'AI generation failed for content: Rate limit reached (429)',
			'input'       => array(
				'prompt'  => 'Write a blog post about PHP 8.2',
				'options' => array( 'model' => 'gpt-4o' ),
			),
			'context'     => array(
				'component' => 'content',
				'error'     => 'Rate limit reached (429)',
			),
		);
		$this->repository->add_log_entry( $history_id, $ai_details, AIPS_History_Type::ERROR );

		// 2. General non-AI Error log
		$general_error_details = array(
			'log_subtype' => 'error',
			'message'     => 'Database connection timed out during taxonomy term assignment',
			'context'     => array(
				'component' => 'system',
			),
		);
		$this->repository->add_log_entry( $history_id, $general_error_details, AIPS_History_Type::ERROR );

		// Default scope: 'ai_only' should return only the AI generation failure
		$ai_only_results = $this->repository->get_ai_errors( array( 'error_scope' => 'ai_only' ) );
		$this->assertSame( 1, $ai_only_results['total'] );
		$this->assertSame( 'content', $ai_only_results['items'][0]['component'] );
		$this->assertStringContainsString( 'Rate limit reached', $ai_only_results['items'][0]['error_message'] );

		// Scope 'all' should return both error records
		$all_results = $this->repository->get_ai_errors( array( 'error_scope' => 'all' ) );
		$this->assertSame( 2, $all_results['total'] );
	}

	/**
	 * Test date range and component filtering.
	 */
	public function test_get_ai_errors_date_and_component_filtering() {
		$history_id = $this->repository->create( array(
			'generated_title' => 'WordPress Caching Guide',
			'creation_method' => 'scheduled',
			'status'          => 'failed',
			'created_at'      => 1700000000,
		) );

		global $wpdb;

		// Entry 1: Content error on timestamp 1700000500
		$log1 = array(
			'log_subtype' => 'error',
			'message'     => 'AI generation failed for content: Server error',
			'context'     => array( 'component' => 'content', 'error' => 'Server error' ),
		);
		$wpdb->insert(
			$wpdb->prefix . 'aips_history_log',
			array(
				'history_id'      => $history_id,
				'history_type_id' => AIPS_History_Type::ERROR,
				'details'         => wp_json_encode( $log1 ),
				'timestamp'       => 1700000500,
			)
		);

		// Entry 2: Title error on timestamp 1700050000
		$log2 = array(
			'log_subtype' => 'error',
			'message'     => 'AI generation failed for title: Timeout',
			'context'     => array( 'component' => 'title', 'error' => 'Timeout' ),
		);
		$wpdb->insert(
			$wpdb->prefix . 'aips_history_log',
			array(
				'history_id'      => $history_id,
				'history_type_id' => AIPS_History_Type::ERROR,
				'details'         => wp_json_encode( $log2 ),
				'timestamp'       => 1700050000,
			)
		);

		// Component filter: title only
		$title_results = $this->repository->get_ai_errors( array( 'component' => 'title' ) );
		$this->assertSame( 1, $title_results['total'] );
		$this->assertSame( 'title', $title_results['items'][0]['component'] );

		// Date filter: before 1700020000 (should only get entry 1)
		$date_results = $this->repository->get_ai_errors( array(
			'date_from' => 1700000000,
			'date_to'   => 1700020000,
		) );
		$this->assertSame( 1, $date_results['total'] );
		$this->assertSame( 'content', $date_results['items'][0]['component'] );
	}

	/**
	 * Test get_ai_error_by_id retrieves full payload.
	 */
	public function test_get_ai_error_by_id() {
		$history_id = $this->repository->create( array(
			'generated_title' => 'Deep Learning in 2026',
			'creation_method' => 'manual',
			'status'          => 'failed',
			'created_at'      => AIPS_DateTime::now()->timestamp(),
		) );

		$details = array(
			'log_subtype' => 'error',
			'message'     => 'AI generation failed for content: Model overloaded',
			'input'       => array(
				'prompt'  => 'Generate an outline for deep learning.',
				'options' => array( 'model' => 'claude-3-5-sonnet', 'temperature' => 0.7 ),
			),
			'context'     => array(
				'component' => 'content',
				'error'     => 'Model overloaded',
			),
		);
		$log_id = $this->repository->add_log_entry( $history_id, $details, AIPS_History_Type::ERROR );

		$record = $this->repository->get_ai_error_by_id( $log_id );

		$this->assertNotNull( $record );
		$this->assertSame( $log_id, $record['id'] );
		$this->assertSame( $history_id, $record['history_id'] );
		$this->assertSame( 'content', $record['component'] );
		$this->assertSame( 'Model overloaded', $record['error_message'] );
		$this->assertSame( 'Generate an outline for deep learning.', $record['prompt'] );
		$this->assertSame( 'claude-3-5-sonnet', $record['options']['model'] );
		$this->assertSame( 'Deep Learning in 2026', $record['post_title'] );
	}

	/**
	 * Test ajax_get_ai_errors permissions and success response.
	 */
	public function test_ajax_get_ai_errors_authorized() {
		$admin_id = $this->factory->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $admin_id );

		$_POST['nonce']       = wp_create_nonce( 'aips_ajax_nonce' );
		$_POST['error_scope'] = 'ai_only';

		try {
			ob_start();
			$this->controller->ajax_get_ai_errors();
		} catch ( \WPDieException $e ) {
			// Expected when AIPS_Ajax_Response sends JSON and exits
		}
		$response_json = ob_get_clean();
		$response = json_decode( $response_json, true );

		$this->assertTrue( $response['success'] );
		$this->assertArrayHasKey( 'items', $response['data'] );
		$this->assertArrayHasKey( 'total', $response['data'] );
	}

	/**
	 * Test deferred join pagination and search matching.
	 */
	public function test_get_ai_errors_pagination_and_search() {
		$h1 = $this->repository->create( array(
			'generated_title' => 'React Optimization Tips',
			'creation_method' => 'manual',
			'status'          => 'failed',
			'created_at'      => 1700000100,
		) );
		$h2 = $this->repository->create( array(
			'generated_title' => 'NodeJS Performance Secrets',
			'creation_method' => 'scheduled',
			'status'          => 'failed',
			'created_at'      => 1700000200,
		) );
		$h3 = $this->repository->create( array(
			'generated_title' => 'Python Concurrency Patterns',
			'creation_method' => 'manual',
			'status'          => 'failed',
			'created_at'      => 1700000300,
		) );

		global $wpdb;
		// Insert 3 AI error logs with distinct timestamps
		for ( $i = 1; $i <= 3; $i++ ) {
			$hid = ${'h' . $i};
			$details = array(
				'log_subtype' => 'error',
				'message'     => "AI generation failed for content: Error number {$i}",
				'context'     => array( 'component' => 'content', 'error' => "Error number {$i}" ),
			);
			$wpdb->insert(
				$wpdb->prefix . 'aips_history_log',
				array(
					'history_id'      => $hid,
					'history_type_id' => AIPS_History_Type::ERROR,
					'details'         => wp_json_encode( $details ),
					'timestamp'       => 1700000000 + ( $i * 100 ),
				)
			);
		}

		// Test pagination: page 1 of 2 per page
		$page1 = $this->repository->get_ai_errors( array(
			'page'     => 1,
			'per_page' => 2,
		) );
		$this->assertSame( 3, $page1['total'] );
		$this->assertSame( 2, $page1['total_pages'] );
		$this->assertCount( 2, $page1['items'] );
		// Newest first (h3 has timestamp 1700000300)
		$this->assertSame( 'Python Concurrency Patterns', $page1['items'][0]['post_title'] );

		// Test pagination: page 2
		$page2 = $this->repository->get_ai_errors( array(
			'page'     => 2,
			'per_page' => 2,
		) );
		$this->assertCount( 1, $page2['items'] );
		$this->assertSame( 'React Optimization Tips', $page2['items'][0]['post_title'] );

		// Test search by container title
		$search_title = $this->repository->get_ai_errors( array(
			'search' => 'NodeJS',
		) );
		$this->assertSame( 1, $search_title['total'] );
		$this->assertSame( 'NodeJS Performance Secrets', $search_title['items'][0]['post_title'] );

		// Test search by error message
		$search_msg = $this->repository->get_ai_errors( array(
			'search' => 'Error number 1',
		) );
		$this->assertSame( 1, $search_msg['total'] );
		$this->assertSame( 'React Optimization Tips', $search_msg['items'][0]['post_title'] );
	}

	/**
	 * Test 3.7.20 DB migration adds history_type_ts index.
	 */
	public function test_migration_adds_index() {
		global $wpdb;
		$table = $wpdb->prefix . 'aips_history_log';

		$migrations = new AIPS_DB_Migrations();
		$method = new ReflectionMethod( $migrations, 'migrate_to_3_7_20' );
		$method->setAccessible( true );
		$result = $method->invoke( $migrations );

		$this->assertTrue( $result );

		$has_index = $wpdb->get_row( "SHOW INDEX FROM `{$table}` WHERE Key_name = 'history_type_ts'" );
		$this->assertNotNull( $has_index );
	}
}
