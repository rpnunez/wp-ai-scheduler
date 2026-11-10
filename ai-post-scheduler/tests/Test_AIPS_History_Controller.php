<?php
/**
 * Test case for AIPS_History_Controller AJAX request boundary.
 *
 * Verifies authentication, nonces, parameter validation, lifecycle hooks,
 * and response structures across all History endpoints.
 *
 * @package AI_Post_Scheduler
 */

class Test_AIPS_History_Controller extends WP_UnitTestCase {

    /**
     * @var AIPS_History_Controller
     */
    private $controller;

    /**
     * @var AIPS_History_Repository
     */
    private $repository;

    /**
     * @var int
     */
    private $admin_user_id;

    /**
     * @var int
     */
    private $subscriber_user_id;

    /**
     * @var int[]
     */
    private $inserted_ids = array();

    public function setUp(): void {
        parent::setUp();

        AIPS_DB_Manager::install_tables();

        $this->repository = AIPS_Container::get_instance()->make(AIPS_History_Repository_Interface::class);
        $this->controller = AIPS_Container::get_instance()->make(AIPS_History_Controller::class);

        $this->admin_user_id = $this->factory->user->create(array('role' => 'administrator'));
        $this->subscriber_user_id = $this->factory->user->create(array('role' => 'subscriber'));
        $this->inserted_ids = array();
    }

    public function tearDown(): void {
        global $wpdb;
        $table = $wpdb->prefix . 'aips_history';
        foreach ($this->inserted_ids as $id) {
            $wpdb->delete($table, array('id' => $id), array('%d'));
        }
        $this->inserted_ids = array();

        $_POST = array();
        $_GET = array();
        $_REQUEST = array();
        wp_set_current_user(0);

        parent::tearDown();
    }

    /**
     * Helper to invoke controller AJAX methods and capture the JSON response.
     *
     * @param string $method Controller method name.
     * @return array|null Decoded JSON response.
     */
    private function call_ajax(string $method): ?array {
        $_REQUEST = $_POST;
        ob_start();
        try {
            $this->controller->$method();
        } catch (WPAjaxDieContinueException $e) {
            // Expected.
        } catch (WPAjaxDieStopException $e) {
            // Expected.
        }

        $output = trim((string) ob_get_clean());
        if ($output === '') {
            return null;
        }

        return json_decode(strtok($output, "\r\n"), true);
    }

    /**
     * Create a test history entry.
     *
     * @param array $args Overrides.
     * @return int Created history ID.
     */
    private function create_history_entry(array $args = array()): int {
        global $wpdb;
        $defaults = array(
            'uuid'              => wp_generate_uuid4(),
            'post_type'         => 'post',
            'template_id'       => 1,
            'record_kind'       => 'generation',
            'status'            => 'completed',
            'creation_method'   => 'manual',
            'generated_title'   => 'Test Generation Title',
            'generated_content' => '<p>Generated content body</p>',
            'prompt'            => 'Generate a test post',
            'created_at'        => time(),
            'completed_at'      => time() + 5,
        );

        $data = array_merge($defaults, $args);
        $wpdb->insert($wpdb->prefix . 'aips_history', $data);
        $id = (int) $wpdb->insert_id;
        $this->inserted_ids[] = $id;

        return $id;
    }

    /**
     * Test that unauthenticated / non-admin users receive 403 / permission denied.
     *
     * @dataProvider endpoint_provider
     */
    public function test_endpoints_require_manage_options_capability(string $action, string $method) {
        wp_set_current_user($this->subscriber_user_id);

        $_POST = array(
            'nonce' => wp_create_nonce('aips_ajax_nonce'),
        );
        $_REQUEST = $_POST;

        $response = $this->call_ajax($method);

        $this->assertIsArray($response);
        $this->assertFalse($response['success'], "Action {$action} should fail for non-admin");
        $this->assertStringContainsString('permission', strtolower($response['data']['message'] ?? ''));
    }

    /**
     * Test that invalid nonce is rejected across all endpoints.
     *
     * @dataProvider endpoint_provider
     */
    public function test_endpoints_reject_invalid_nonce(string $action, string $method) {
        wp_set_current_user($this->admin_user_id);

        $_POST = array(
            'nonce' => 'invalid_nonce_token',
        );
        $_REQUEST = $_POST;

        $response = $this->call_ajax($method);

        $this->assertIsArray($response);
        $this->assertFalse($response['success'], "Action {$action} should reject invalid nonce");
        $this->assertSame('Invalid nonce.', $response['data']['message'] ?? '');
    }

    public function endpoint_provider(): array {
        return array(
            array('aips_bulk_delete_history', 'ajax_bulk_delete_history'),
            array('aips_clear_history', 'ajax_clear_history'),
            array('aips_get_history_details', 'ajax_get_history_details'),
            array('aips_get_history_logs', 'ajax_get_history_logs'),
            array('aips_get_history_modal_html', 'ajax_get_history_modal_html'),
            array('aips_reload_history', 'ajax_reload_history'),
            array('aips_retry_generation', 'ajax_retry_generation'),
        );
    }

    /**
     * Test bulk delete validation and execution.
     */
    public function test_bulk_delete_history_validation_and_action_hooks() {
        wp_set_current_user($this->admin_user_id);

        // 1. Empty IDs
        $_POST = array(
            'nonce' => wp_create_nonce('aips_ajax_nonce'),
            'ids'   => array(),
        );
        $res = $this->call_ajax('ajax_bulk_delete_history');
        $this->assertFalse($res['success']);
        $this->assertSame('No items selected.', $res['data']['message']);

        // 2. Valid deletion
        $id1 = $this->create_history_entry();
        $id2 = $this->create_history_entry();

        $before_hook_called = false;
        $after_hook_called = false;
        $deleted_ids_received = array();

        add_action('aips_history_before_delete', function($ids) use (&$before_hook_called, &$deleted_ids_received) {
            $before_hook_called = true;
            $deleted_ids_received = $ids;
        });

        add_action('aips_history_deleted', function($ids) use (&$after_hook_called) {
            $after_hook_called = true;
        });

        $_POST = array(
            'nonce' => wp_create_nonce('aips_ajax_nonce'),
            'ids'   => array($id1, $id2),
        );

        $res = $this->call_ajax('ajax_bulk_delete_history');
        $this->assertTrue($res['success']);
        $this->assertTrue($before_hook_called);
        $this->assertTrue($after_hook_called);
        $this->assertSame(array($id1, $id2), $deleted_ids_received);

        // Verify rows were deleted
        $this->assertNull($this->repository->get_by_id($id1));
        $this->assertNull($this->repository->get_by_id($id2));
    }

    /**
     * Test clear history closing stalled processing runs and deleting entries.
     */
    public function test_clear_history_processing_and_status_filtering() {
        wp_set_current_user($this->admin_user_id);

        // Create stalled processing run (created 1 hour ago with no activity)
        $stalled_id = $this->create_history_entry(array(
            'status'          => 'processing',
            'creation_method' => 'manual',
            'created_at'      => time() - 3600,
            'completed_at'    => 0,
        ));

        $_POST = array(
            'nonce'  => wp_create_nonce('aips_ajax_nonce'),
            'status' => 'processing',
        );

        $res = $this->call_ajax('ajax_clear_history');
        $this->assertTrue($res['success']);
        $this->assertArrayHasKey('closed', $res['data']);
        $this->assertArrayHasKey('stats', $res['data']);

        // Verify status was changed to terminated
        $item = $this->repository->get_by_id($stalled_id);
        $this->assertSame('terminated', $item->status);
    }

    /**
     * Test get history details returns complete row data and handles missing ID.
     */
    public function test_get_history_details_flow() {
        wp_set_current_user($this->admin_user_id);

        // Missing ID
        $_POST = array(
            'nonce' => wp_create_nonce('aips_ajax_nonce'),
        );
        $res = $this->call_ajax('ajax_get_history_details');
        $this->assertFalse($res['success']);
        $this->assertSame('Invalid history ID.', $res['data']['message']);

        // Non-existent ID
        $_POST['history_id'] = 99999999;
        $res = $this->call_ajax('ajax_get_history_details');
        $this->assertFalse($res['success']);
        $this->assertSame('History item not found.', $res['data']['message']);

        // Valid ID
        $id = $this->create_history_entry(array('generated_title' => 'Sample Post Title'));
        $_POST['history_id'] = $id;

        $res = $this->call_ajax('ajax_get_history_details');
        $this->assertTrue($res['success']);
        $this->assertSame($id, (int) $res['data']['id']);
        $this->assertSame('Sample Post Title', $res['data']['generated_title']);
        $this->assertSame('completed', $res['data']['status']);
    }

    /**
     * Test get history logs returns normalized view data.
     */
    public function test_get_history_logs_flow() {
        wp_set_current_user($this->admin_user_id);

        // Missing ID
        $_POST = array(
            'nonce' => wp_create_nonce('aips_ajax_nonce'),
        );
        $res = $this->call_ajax('ajax_get_history_logs');
        $this->assertFalse($res['success']);
        $this->assertSame('Invalid history ID.', $res['data']['message']);

        // Valid ID
        $id = $this->create_history_entry();
        $_POST['history_id'] = $id;

        $res = $this->call_ajax('ajax_get_history_logs');
        $this->assertTrue($res['success']);
        $this->assertArrayHasKey('container', $res['data']);
        $this->assertArrayHasKey('display_logs', $res['data']);
        $this->assertArrayHasKey('filter_counts', $res['data']);
        $this->assertArrayHasKey('timeline_data', $res['data']);
        $this->assertSame($id, (int) $res['data']['container']['id']);
    }

    /**
     * Test get history modal HTML returns pre-rendered modal HTML.
     */
    public function test_get_history_modal_html_flow() {
        wp_set_current_user($this->admin_user_id);

        $id = $this->create_history_entry();
        $_POST = array(
            'nonce'      => wp_create_nonce('aips_ajax_nonce'),
            'history_id' => $id,
        );

        $res = $this->call_ajax('ajax_get_history_modal_html');
        $this->assertTrue($res['success']);
        $this->assertArrayHasKey('modal_html', $res['data']);
        $this->assertArrayHasKey('container', $res['data']);
        $this->assertIsString($res['data']['modal_html']);
    }

    /**
     * Test reload history returns items_html, pagination_html, timeline_html, and stats.
     */
    public function test_reload_history_response_shape() {
        wp_set_current_user($this->admin_user_id);

        $this->create_history_entry();

        $_POST = array(
            'nonce' => wp_create_nonce('aips_ajax_nonce'),
            'paged' => 1,
        );

        $res = $this->call_ajax('ajax_reload_history');
        $this->assertTrue($res['success']);
        $this->assertArrayHasKey('items_html', $res['data']);
        $this->assertArrayHasKey('pagination_html', $res['data']);
        $this->assertArrayHasKey('timeline_html', $res['data']);
        $this->assertArrayHasKey('paged', $res['data']);
        $this->assertArrayHasKey('stats', $res['data']);
    }

    /**
     * Test CSV sanitization neutralizes formula injection attempts.
     */
    public function test_sanitize_csv_cell_formula_injection() {
        $this->assertSame("'=1+1", $this->controller->sanitize_csv_cell('=1+1'));
        $this->assertSame("'+cmd|' /C calc'!A0", $this->controller->sanitize_csv_cell("+cmd|' /C calc'!A0"));
        $this->assertSame("'-5+2", $this->controller->sanitize_csv_cell('-5+2'));
        $this->assertSame("'@SUM(A1:A10)", $this->controller->sanitize_csv_cell('@SUM(A1:A10)'));
        $this->assertSame("Safe Text", $this->controller->sanitize_csv_cell('Safe Text'));
    }
}
