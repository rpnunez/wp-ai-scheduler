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

    public function test_content_indexing_summary_reads_nested_log_metrics() {
        $container = array(
            'creation_method' => 'content_indexing',
            'template_name'   => '',
            'status'          => 'completed',
        );
        $logs = array(
            array(
                'details' => array(
                    'input' => array(
                        'dimensions'          => 1536,
                        'relationships_saved' => 0,
                    ),
                ),
            ),
        );

        $method = new ReflectionMethod(AIPS_History::class, 'analyze_history_modal_summary');
        $method->setAccessible(true);
        $summary = $method->invoke($this->history_instance, $container, $logs);

        $this->assertSame(
            'Generated 1536-dimension vector; Recomputed related posts',
            $summary['what_changed']
        );
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

    public function test_history_modal_header_actions_and_status_label_format() {
        $admin_user_id = $this->factory->user->create(array('role' => 'administrator'));
        wp_set_current_user($admin_user_id);

        $post_id = $this->factory->post->create(array(
            'post_title'  => 'Test Modal Post',
            'post_status' => 'publish',
        ));

        $history_item = (object) array(
            'id'              => 5983,
            'post_id'         => $post_id,
            'status'          => 'completed',
            'generated_title' => 'Test Modal Post',
            'creation_method' => 'scheduled',
            'actor_type'      => 'system',
            'created_at'      => '2026-11-24 11:11:00',
            'completed_at'    => '2026-11-24 11:12:37',
            'correlation_id'  => '5cea4672-f547-459f-b3dd-10ed6f91a6a7',
            'log'             => array(
                (object) array(
                    'id'              => 1,
                    'history_type_id' => 8,
                    'timestamp'       => '2026-11-24 11:11:00',
                    'details'         => wp_json_encode(array(
                        'log_subtype' => 'trigger_method',
                        'message'     => 'Triggered automatically by a scheduled run',
                    )),
                ),
            ),
        );

        $method = new ReflectionMethod(AIPS_History::class, 'prepare_history_modal_view_data');
        $method->setAccessible(true);
        $modal_view = $method->invoke($this->history_instance, $history_item, true);

        $container = $modal_view['container'];
        $this->assertSame('completed', $container['status']);
        $this->assertSame('Completed', $container['status_label']);

        // Header actions must be ordered: View Post (no post ID suffix), AI Edit, Edit, each with a dashicon.
        $actions = $container['header_actions'];
        $this->assertCount(3, $actions);
        $this->assertSame('View Post', $actions[0]['label']);
        $this->assertSame('dashicons-external', $actions[0]['icon']);
        $this->assertSame('AI Edit', $actions[1]['label']);
        $this->assertSame('dashicons-update', $actions[1]['icon']);
        $this->assertSame('Edit', $actions[2]['label']);
        $this->assertSame('dashicons-edit', $actions[2]['icon']);

        // Completed timestamp should be grouped into summary_meta alongside Created and Duration.
        $meta_labels = array_column($container['summary_meta'], 'label');
        $this->assertContains('Created', $meta_labels);
        $this->assertContains('Completed', $meta_labels);
        $this->assertContains('Duration', $meta_labels);

        $card_labels = array_column($container['detail_cards'], 'label');
        $this->assertNotContains('Completed', $card_labels);

        // Render modal content partial and verify Technical tab badge & table headers.
        $display_logs  = $modal_view['display_logs'];
        $filter_counts = $modal_view['filter_counts'];
        $timeline_data = $modal_view['timeline_data'];

        ob_start();
        include AIPS_PLUGIN_DIR . 'templates/partials/history-modal-content.php';
        $html = (string) ob_get_clean();

        $this->assertStringContainsString('<span class="aips-history-tab-badge">1</span>', $html);
        $this->assertStringContainsString('<th class="aips-history-col-timestamp">Date &amp; Time</th>', $html);
        $this->assertStringNotContainsString('aips-history-advanced-details-heading', $html);
        $this->assertStringNotContainsString('aips-history-col-logtype', $html);
        $this->assertStringNotContainsString('aips-history-log-type-code', $html);
    }
}
