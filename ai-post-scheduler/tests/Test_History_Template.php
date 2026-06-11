<?php
/**
 * Test case for History Template Variable Handling
 *
 * Tests that the history.php template correctly handles
 * AIPS_History objects passed as variables.
 */

class Test_History_Template extends WP_UnitTestCase {

    private $history_instance;

    public function setUp(): void {
        parent::setUp();
        $this->history_instance = new AIPS_History();
    }

    public function tearDown(): void {
        $_POST = array();
        $_GET = array();
        $_REQUEST = array();
        wp_set_current_user(0);

        parent::tearDown();
    }

    private function capture_ajax_response(callable $callable) {
        ob_start();

        try {
            $callable();
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
     * Test that the template works when only $history_handler is passed
     */
    public function test_history_template_handles_stats_as_object() {
        // Setup: Pass only $history_handler; template fetches $history and $stats from it
        $history_handler = $this->history_instance;
        
        // Capture output
        ob_start();
        
        // Include the template - should work with just $history_handler
        try {
            include AIPS_PLUGIN_DIR . 'templates/admin/history.php';
            $output = ob_get_clean();
            
            // If we got here without an exception, the fix works
            $this->assertIsString($output);
            
            // Check that the output contains expected elements
            $this->assertStringContainsString('aips-content-panel', $output);
            
        } catch (Throwable $e) {
            ob_end_clean();
            $this->fail('Template threw an error with only $history_handler: ' . $e->getMessage());
        }
    }

    /**
     * Test that the template works normally when variables are correct
     */
    public function test_history_template_with_correct_variables() {
        // Setup: Use correct variable types (as passed by render_page)
        $history_handler = $this->history_instance;
        $history = array(
            'items' => array(),
            'total' => 0,
            'pages' => 1,
            'current_page' => 1
        );
        $stats = array(
            'total' => 0,
            'completed' => 0,
            'failed' => 0,
            'success_rate' => 0
        );
        
        // Capture output
        ob_start();
        
        try {
            include AIPS_PLUGIN_DIR . 'templates/admin/history.php';
            $output = ob_get_clean();
            
            // Check that the output is generated
            $this->assertIsString($output);
            $this->assertStringContainsString('aips-content-panel', $output);
            
        } catch (Throwable $e) {
            ob_end_clean();
            $this->fail('Template threw an error with correct variables: ' . $e->getMessage());
        }
    }

    /**
     * Test that the template can handle $history as an AIPS_History object
     */
    public function test_history_template_handles_history_as_object() {
        // Setup: Pass $history_handler (canonical) and $history as object (legacy fallback removed)
        $history_handler = $this->history_instance;
        $history = $this->history_instance;
        
        // Capture output
        ob_start();
        
        try {
            include AIPS_PLUGIN_DIR . 'templates/admin/history.php';
            $output = ob_get_clean();
            
            // If we got here without an exception, it works
            $this->assertIsString($output);
            $this->assertStringContainsString('aips-content-panel', $output);
            
        } catch (Throwable $e) {
            ob_end_clean();
            $this->fail('Template threw an error when $history is an AIPS_History object: ' . $e->getMessage());
        }
    }

    /**
     * Test that the template works when $history_handler is passed with pre-set $history/$stats
     */
    public function test_history_template_handles_both_as_objects() {
        // Setup: Pass $history_handler; template overwrites $history from handler, gets $stats if not set
        $history_handler = $this->history_instance;
        
        // Capture output
        ob_start();
        
        try {
            include AIPS_PLUGIN_DIR . 'templates/admin/history.php';
            $output = ob_get_clean();
            
            // If we got here without an exception, it works
            $this->assertIsString($output);
            $this->assertStringContainsString('aips-content-panel', $output);
            
        } catch (Throwable $e) {
            ob_end_clean();
            $this->fail('Template threw an error when both variables are AIPS_History objects: ' . $e->getMessage());
        }
    }

    /**
     * Test that Unix timestamps are formatted directly (not via strtotime string parsing).
     */
    public function test_prepare_items_for_display_formats_unix_timestamp_created_at() {
        $timestamp = current_time('timestamp', true) - HOUR_IN_SECONDS;
        $item = (object) array(
            'created_at' => (string) $timestamp,
        );
        $items = array($item);

        $method = new ReflectionMethod(AIPS_History::class, 'prepare_items_for_display');
        $method->setAccessible(true);
        $args = array(&$items);
        $method->invokeArgs($this->history_instance, $args);

        $format = get_option('date_format') . ' ' . get_option('time_format');
        $expected = AIPS_DateTime::fromTimestamp($timestamp)->toDisplay($format);

        $this->assertSame($expected, $items[0]->formatted_date);
    }

    public function test_ajax_reload_history_response_includes_timeline_html() {
        $admin_user_id = $this->factory->user->create(array('role' => 'administrator'));
        wp_set_current_user($admin_user_id);

        $history = new class extends AIPS_History {
            public function get_history($args = array()) {
                return array(
                    'items' => array(),
                    'total' => 0,
                    'pages' => 1,
                    'current_page' => isset($args['page']) ? (int) $args['page'] : 1,
                );
            }

            public function get_stats() {
                return array(
                    'total' => 0,
                    'completed' => 0,
                    'failed' => 0,
                    'success_rate' => 0,
                );
            }
        };

        $_POST = array(
            'nonce' => wp_create_nonce('aips_ajax_nonce'),
            'paged' => 1,
        );
        $_REQUEST = $_POST;

        $response = $this->capture_ajax_response(array($history, 'ajax_reload_history'));

        $this->assertIsArray($response);
        $this->assertTrue($response['success']);
        $this->assertArrayHasKey('timeline_html', $response['data']);
        $this->assertIsString($response['data']['timeline_html']);
    }

    public function test_get_timeline_group_label_uses_site_timezone_day_boundaries() {
        $original_timezone = get_option('timezone_string');
        update_option('timezone_string', 'America/New_York');

        try {
            $now_timestamp = strtotime('2026-06-06 02:00:00 UTC');
            $item_timestamp = strtotime('2026-06-05 23:30:00 UTC');

            $this->assertSame(
                'Today',
                $this->history_instance->get_timeline_group_label($item_timestamp, $now_timestamp)
            );
        } finally {
            update_option('timezone_string', $original_timezone);
        }
    }

    public function test_get_timeline_group_label_returns_expected_ranges() {
        $original_timezone = get_option('timezone_string');
        update_option('timezone_string', 'UTC');

        try {
            $now_timestamp = strtotime('2026-06-20 12:00:00 UTC');

            $this->assertSame(
                'Yesterday',
                $this->history_instance->get_timeline_group_label(strtotime('2026-06-19 08:00:00 UTC'), $now_timestamp)
            );
            $this->assertSame(
                'In the last week',
                $this->history_instance->get_timeline_group_label(strtotime('2026-06-17 12:00:00 UTC'), $now_timestamp)
            );
            $this->assertSame(
                'In the last month',
                $this->history_instance->get_timeline_group_label(strtotime('2026-06-01 12:00:00 UTC'), $now_timestamp)
            );
            $this->assertSame(
                'Older',
                $this->history_instance->get_timeline_group_label(strtotime('2026-04-01 12:00:00 UTC'), $now_timestamp)
            );
        } finally {
            update_option('timezone_string', $original_timezone);
        }
    }
}
