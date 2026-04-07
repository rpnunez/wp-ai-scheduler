<?php
/**
 * Tests for AIPS_History::ajax_reload_history()
 *
 * Covers the new structured JSON response shape introduced when the history
 * flow was migrated to AIPS.Templates DOM rendering:
 *  - Top-level response keys: items, pagination, paged, stats
 *  - Per-item fields: id, post_id, generated_title, status, error_message,
 *    template_label, template_id, topic_id, created_at_formatted, edit_url, post_url
 *  - Pagination fields: total, pages, current_page, items_label
 *  - Stats fields: total, completed, failed, success_rate
 *  - template_label is pre-formatted server-side (i18n-safe)
 *  - Permission check (non-admin must be denied)
 *
 * @package AI_Post_Scheduler
 */

class Test_AIPS_History_Ajax_Reload extends WP_UnitTestCase {

	/** @var AIPS_History */
	private $handler;

	/** @var int */
	private $admin_user_id;

	/** @var array<int> History row IDs created in setUp */
	private $test_history_ids = array();

	/** @var int Template ID created in setUp */
	private $test_template_id;

	public function setUp(): void {
		parent::setUp();

		// Create an admin user and set as current.
		$this->admin_user_id = $this->factory->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $this->admin_user_id );

		$this->handler = new AIPS_History();

		// Create a template so template_name can be resolved.
		$template_repo = new AIPS_Template_Repository();
		$this->test_template_id = $template_repo->create( array(
			'name'            => 'Reload Test Template',
			'prompt_template' => 'Test prompt',
			'post_type'       => 'post',
			'post_status'     => 'draft',
			'is_active'       => 1,
			'post_category'   => 1,
			'post_tags'       => '',
			'post_author'     => 1,
			'system_prompt'   => '',
		) );

		$this->create_test_history_entries();
	}

	public function tearDown(): void {
		$repo = new AIPS_History_Repository();

		if ( ! empty( $this->test_history_ids ) ) {
			$repo->delete_bulk( $this->test_history_ids );
		}

		if ( $this->test_template_id ) {
			global $wpdb;
			$wpdb->delete( $wpdb->prefix . 'aips_templates', array( 'id' => $this->test_template_id ), array( '%d' ) );
		}

		unset( $_POST );
		parent::tearDown();
	}

	// ------------------------------------------------------------------
	// Helpers
	// ------------------------------------------------------------------

	/**
	 * Insert test history rows using the repository to comply with the SQL ownership rule.
	 * Direct `$wpdb->insert()` is avoided — all persistence goes through the repository.
	 */
	private function create_test_history_entries(): void {
		$repo = new AIPS_History_Repository();

		// Row 1: completed, with template.
		$id1 = $repo->create( array(
			'template_id'     => $this->test_template_id,
			'status'          => 'completed',
			'generated_title' => 'Reload Test Title 1',
		) );
		if ( $id1 ) {
			$this->test_history_ids[] = (int) $id1;
		}

		// Row 2: failed, with a non-existent template (deleted template scenario).
		$id2 = $repo->create( array(
			'template_id'   => 999999,
			'status'        => 'failed',
			'generated_title' => 'Reload Test Title 2',
			'error_message' => 'Test error message',
		) );
		if ( $id2 ) {
			$this->test_history_ids[] = (int) $id2;
		}
	}

	/**
	 * Call ajax_reload_history() and return the decoded JSON response.
	 *
	 * @param array $post_data POST params to pass.
	 * @return array Decoded response array.
	 */
	private function call_ajax( array $post_data ): array {
		$_POST             = $post_data;
		$_POST['nonce']    = wp_create_nonce( 'aips_ajax_nonce' );
		$_REQUEST['nonce'] = $_POST['nonce'];

		try {
			$this->handler->ajax_reload_history();
		} catch ( WPAjaxDieContinueException $e ) {
			// Expected path for wp_send_json_*.
		} catch ( WPAjaxDieStopException $e ) {
			// Nonce failure path.
		}

		$output  = $this->getActualOutput();
		$decoded = json_decode( $output, true );
		$this->assertIsArray( $decoded, 'Response must be valid JSON.' );

		return $decoded;
	}

	// ------------------------------------------------------------------
	// Permission check
	// ------------------------------------------------------------------

	/**
	 * @test
	 * Non-admin users must receive a permission-denied error.
	 */
	public function test_ajax_reload_history_denies_non_admin(): void {
		wp_set_current_user( 0 );

		$response = $this->call_ajax( array() );

		$this->assertFalse( $response['success'] );
		$this->assertStringContainsString( 'Permission denied', $response['data']['message'] );
	}

	// ------------------------------------------------------------------
	// Top-level response shape
	// ------------------------------------------------------------------

	/**
	 * @test
	 * Successful call must return the expected top-level keys.
	 */
	public function test_ajax_reload_history_returns_expected_top_level_keys(): void {
		$response = $this->call_ajax( array() );

		$this->assertTrue( $response['success'] );
		$this->assertArrayHasKey( 'items',      $response['data'] );
		$this->assertArrayHasKey( 'pagination', $response['data'] );
		$this->assertArrayHasKey( 'paged',      $response['data'] );
		$this->assertArrayHasKey( 'stats',      $response['data'] );
	}

	/**
	 * @test
	 * items must be an array.
	 */
	public function test_ajax_reload_history_items_is_array(): void {
		$response = $this->call_ajax( array() );

		$this->assertIsArray( $response['data']['items'] );
	}

	// ------------------------------------------------------------------
	// Per-item field shape
	// ------------------------------------------------------------------

	/**
	 * @test
	 * Each item must carry all required fields.
	 */
	public function test_ajax_reload_history_item_fields_are_present(): void {
		$response = $this->call_ajax( array() );
		$items    = $response['data']['items'];

		$this->assertNotEmpty( $items, 'Expected at least one item.' );

		$required = array(
			'id',
			'post_id',
			'generated_title',
			'status',
			'error_message',
			'template_label',
			'template_id',
			'topic_id',
			'created_at_formatted',
			'edit_url',
			'post_url',
		);

		foreach ( $items as $item ) {
			foreach ( $required as $field ) {
				$this->assertArrayHasKey(
					$field,
					$item,
					"Item is missing required field: $field"
				);
			}
		}
	}

	/**
	 * @test
	 * id must be an integer.
	 */
	public function test_ajax_reload_history_item_id_is_integer(): void {
		$response = $this->call_ajax( array() );
		$items    = $response['data']['items'];

		$this->assertNotEmpty( $items );

		foreach ( $items as $item ) {
			$this->assertIsInt( $item['id'], 'item.id must be an integer.' );
			$this->assertGreaterThan( 0, $item['id'] );
		}
	}

	/**
	 * @test
	 * status must be a non-empty string.
	 */
	public function test_ajax_reload_history_item_status_is_string(): void {
		$response = $this->call_ajax( array() );

		foreach ( $response['data']['items'] as $item ) {
			$this->assertIsString( $item['status'] );
			$this->assertNotEmpty( $item['status'] );
		}
	}

	/**
	 * @test
	 * created_at_formatted must be a non-empty string.
	 */
	public function test_ajax_reload_history_item_created_at_formatted_is_string(): void {
		$response = $this->call_ajax( array() );

		foreach ( $response['data']['items'] as $item ) {
			$this->assertIsString( $item['created_at_formatted'] );
			$this->assertNotEmpty( $item['created_at_formatted'] );
		}
	}

	// ------------------------------------------------------------------
	// template_label: i18n-safe server-side formatting
	// ------------------------------------------------------------------

	/**
	 * @test
	 * An item linked to an existing template must use the template name as its label.
	 */
	public function test_ajax_reload_history_template_label_uses_name_for_existing_template(): void {
		$response = $this->call_ajax( array() );

		// Row 1 was created with the real template.
		$item = null;
		foreach ( $response['data']['items'] as $candidate ) {
			if ( $candidate['template_id'] === $this->test_template_id ) {
				$item = $candidate;
				break;
			}
		}

		$this->assertNotNull( $item, 'Could not find item with the test template ID.' );
		$this->assertSame( 'Reload Test Template', $item['template_label'] );
	}

	/**
	 * @test
	 * An item whose template no longer exists should have a pre-formatted deleted label
	 * with the template ID substituted server-side (no %d placeholder in the output).
	 */
	public function test_ajax_reload_history_template_label_formats_deleted_template_server_side(): void {
		$response = $this->call_ajax( array() );

		// Row 2 uses template_id 999999 which does not exist.
		$item = null;
		foreach ( $response['data']['items'] as $candidate ) {
			if ( $candidate['template_id'] === 999999 ) {
				$item = $candidate;
				break;
			}
		}

		$this->assertNotNull( $item, 'Could not find item with the deleted template ID.' );

		// The label must contain the numeric ID and must NOT contain a raw %d placeholder.
		$this->assertStringContainsString( '999999', $item['template_label'] );
		$this->assertStringNotContainsString( '%d', $item['template_label'] );
	}

	// ------------------------------------------------------------------
	// Pagination shape
	// ------------------------------------------------------------------

	/**
	 * @test
	 * pagination must contain the required keys with correct types.
	 */
	public function test_ajax_reload_history_pagination_has_required_keys(): void {
		$response   = $this->call_ajax( array() );
		$pagination = $response['data']['pagination'];

		$this->assertArrayHasKey( 'total',        $pagination );
		$this->assertArrayHasKey( 'pages',        $pagination );
		$this->assertArrayHasKey( 'current_page', $pagination );
		$this->assertArrayHasKey( 'items_label',  $pagination );

		$this->assertIsInt( $pagination['total'] );
		$this->assertIsInt( $pagination['pages'] );
		$this->assertIsInt( $pagination['current_page'] );
		$this->assertIsString( $pagination['items_label'] );
	}

	/**
	 * @test
	 * items_label must embed the numeric total and must NOT contain a raw %d placeholder.
	 */
	public function test_ajax_reload_history_items_label_is_preformatted(): void {
		$response   = $this->call_ajax( array() );
		$pagination = $response['data']['pagination'];

		$this->assertNotEmpty( $pagination['items_label'] );
		$this->assertStringContainsString( (string) $pagination['total'], $pagination['items_label'] );
		$this->assertStringNotContainsString( '%d', $pagination['items_label'] );
	}

	/**
	 * @test
	 * current_page defaults to 1 when no paged param is supplied.
	 */
	public function test_ajax_reload_history_default_page_is_one(): void {
		$response = $this->call_ajax( array() );

		$this->assertSame( 1, $response['data']['pagination']['current_page'] );
		$this->assertSame( 1, $response['data']['paged'] );
	}

	/**
	 * @test
	 * When paged=2 is requested the response reflects that page number.
	 */
	public function test_ajax_reload_history_respects_paged_param(): void {
		$response = $this->call_ajax( array( 'paged' => 2 ) );

		$this->assertTrue( $response['success'] );
		$this->assertSame( 2, $response['data']['paged'] );
	}

	// ------------------------------------------------------------------
	// Stats shape
	// ------------------------------------------------------------------

	/**
	 * @test
	 * stats must contain total, completed, failed, success_rate with numeric types.
	 */
	public function test_ajax_reload_history_stats_has_required_keys(): void {
		$response = $this->call_ajax( array() );
		$stats    = $response['data']['stats'];

		$this->assertArrayHasKey( 'total',        $stats );
		$this->assertArrayHasKey( 'completed',    $stats );
		$this->assertArrayHasKey( 'failed',       $stats );
		$this->assertArrayHasKey( 'success_rate', $stats );

		$this->assertIsInt( $stats['total'] );
		$this->assertIsInt( $stats['completed'] );
		$this->assertIsInt( $stats['failed'] );
		$this->assertIsNumeric( $stats['success_rate'] );
	}

	/**
	 * @test
	 * Stats totals must be non-negative integers.
	 */
	public function test_ajax_reload_history_stats_values_are_non_negative(): void {
		$response = $this->call_ajax( array() );
		$stats    = $response['data']['stats'];

		$this->assertGreaterThanOrEqual( 0, $stats['total'] );
		$this->assertGreaterThanOrEqual( 0, $stats['completed'] );
		$this->assertGreaterThanOrEqual( 0, $stats['failed'] );
		$this->assertGreaterThanOrEqual( 0.0, (float) $stats['success_rate'] );
	}

	// ------------------------------------------------------------------
	// Filtering
	// ------------------------------------------------------------------

	/**
	 * @test
	 * Filtering by status=failed must return only failed items.
	 */
	public function test_ajax_reload_history_status_filter_returns_matching_items(): void {
		$response = $this->call_ajax( array( 'status' => 'failed' ) );

		$this->assertTrue( $response['success'] );

		foreach ( $response['data']['items'] as $item ) {
			$this->assertSame( 'failed', $item['status'] );
		}
	}

	/**
	 * @test
	 * Filtering by status=completed must return only completed items.
	 */
	public function test_ajax_reload_history_completed_filter_returns_only_completed(): void {
		$response = $this->call_ajax( array( 'status' => 'completed' ) );

		$this->assertTrue( $response['success'] );

		foreach ( $response['data']['items'] as $item ) {
			$this->assertSame( 'completed', $item['status'] );
		}
	}
}
