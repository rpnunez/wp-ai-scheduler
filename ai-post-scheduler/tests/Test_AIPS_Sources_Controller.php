<?php
/** Source archive endpoint security and actionable errors. */
class Test_AIPS_Sources_Controller extends WP_Ajax_UnitTestCase {
	private $controller;
	private $data_repo;

	public function set_up(): void {
		parent::set_up();
		AIPS_DB_Manager::install_tables();
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$this->controller = new AIPS_Sources_Controller();
		$this->data_repo = new AIPS_Sources_Data_Repository();
	}

	public function tear_down(): void {
		$_POST = array();
		$_REQUEST = array();
		wp_set_current_user( 0 );
		parent::tear_down();
	}

	private function call_save( array $post, bool $valid_nonce = true ): array {
		$_POST = $post;
		$_POST['nonce'] = $valid_nonce ? wp_create_nonce( 'aips_source_data_save' ) : 'invalid';
		$_REQUEST = $_POST;
		$this->_last_response = '';
		ob_start();
		try {
			$this->controller->ajax_save_source_data();
		} catch ( WPAjaxDieContinueException $e ) {
		} catch ( WPAjaxDieStopException $e ) {
		}
		$output = ob_get_clean();
		return json_decode( $this->_last_response ?: $output, true );
	}

	private function call_fetch( int $source_id ): array {
		$_POST = array( 'source_id' => $source_id, 'nonce' => wp_create_nonce( 'aips_ajax_nonce' ) );
		$_REQUEST = $_POST;
		$this->_last_response = '';
		ob_start();
		try {
			$this->controller->ajax_fetch_source_now();
		} catch ( WPAjaxDieContinueException $error ) {
		} catch ( WPAjaxDieStopException $error ) {
		}
		$output = ob_get_clean();
		return json_decode( $this->_last_response ?: $output, true );
	}

	public function test_manual_fetch_reports_worker_contention_without_fetching() {
		$repo = new AIPS_Sources_Repository();
		$id = $repo->create( array( 'url' => 'https://example.com/manual-busy' ) );
		$lease = new AIPS_Sources_Fetch_Lease();
		$owner = $lease->acquire();
		$this->assertIsString( $owner );
		try {
			$response = $this->call_fetch( $id );
			$this->assertFalse( $response['success'] );
			$this->assertStringContainsString( 'retry', $response['data']['message'] );
			$this->assertNull( $repo->get_by_id( $id )->last_fetch_attempt_at );
			$this->assertNull( $this->data_repo->get_by_source_id( $id ) );
		} finally {
			$lease->release( $owner );
			$repo->delete( $id );
		}
	}

	public function test_manual_fetch_exception_records_attempt_reconciles_schedule_and_releases_lease() {
		$repo = new AIPS_Sources_Repository();
		$id = $repo->create( array( 'url' => 'https://example.com/manual-error' ) );
		$repo->set_fetch_schedule( $id, 'hourly' );
		$throw = static function () { throw new RuntimeException( 'Fixture transport exception' ); };
		add_filter( 'pre_http_request', $throw );
		try {
			$response = $this->call_fetch( $id );
			$this->assertTrue( $response['success'] );
			$this->assertFalse( $response['data']['fetch_result']['success'] );
			$this->assertSame( 'Fixture transport exception', $response['data']['fetch_result']['error'] );
			$this->assertSame( 'failed', $repo->get_by_id( $id )->last_fetch_status );
			$this->assertSame( (int) $repo->get_by_id( $id )->next_fetch_at, wp_next_scheduled( AIPS_Sources_Cron::HOOK ) );
			$lease = new AIPS_Sources_Fetch_Lease();
			$owner = $lease->acquire();
			$this->assertIsString( $owner );
			$lease->release( $owner );
		} finally {
			remove_filter( 'pre_http_request', $throw );
			$repo->delete( $id );
			AIPS_Sources_Cron::instance()->schedule();
		}
	}

	public function test_archive_save_requires_nonce_and_capability() {
		$response = $this->call_save( array( 'data_id' => 1 ), false );
		$this->assertFalse( $response['success'] );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );
		$response = $this->call_save( array( 'data_id' => 1 ) );
		$this->assertFalse( $response['success'] );
	}

	public function test_duplicate_archive_save_returns_specific_error() {
		$this->data_repo->insert_if_new( 42, array( 'url' => 'https://example.com', 'extracted_text' => 'First' ) );
		$this->data_repo->insert_if_new( 42, array( 'url' => 'https://example.com', 'extracted_text' => 'Second' ) );
		$row = $this->data_repo->get_by_source_id( 42 );
		$response = $this->call_save( array( 'data_id' => $row->id, 'extracted_text' => 'First' ) );
		$this->assertFalse( $response['success'] );
		$this->assertSame( 'duplicate_source_content', $response['data']['code'] );
		$this->assertSame( 'Second', $this->data_repo->get_by_id( $row->id )->extracted_text );
	}

	public function test_restricted_raw_html_is_sanitized_and_pending_remains_supported() {
		$user = wp_get_current_user();
		$user->add_cap( 'unfiltered_html', false );
		$this->data_repo->insert_if_new( 42, array( 'url' => 'https://example.com', 'extracted_text' => 'Safe text' ) );
		$row = $this->data_repo->get_by_source_id( 42 );
		$response = $this->call_save( array( 'data_id' => $row->id, 'raw_html' => '<p>Allowed</p><script>alert(1)</script>', 'fetch_status' => 'pending' ) );
		$this->assertTrue( $response['success'] );
		$updated = $this->data_repo->get_by_id( $row->id );
		$this->assertStringNotContainsString( '<script', $updated->raw_html );
		$this->assertSame( 'pending', $updated->fetch_status );
	}
}
