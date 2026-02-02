<?php
/**
 * Test case for History Template Variable Handling
 *
 * Tests that the history.php template correctly handles
 * AIPS_History objects passed as variables.
 */

class Test_History_Template extends WP_UnitTestCase {

    private $history_instance;
    private $original_wpdb;
    private $wpdb_mock;

    public function setUp(): void {
        parent::setUp();

        global $wpdb;
        $this->original_wpdb = $wpdb;

        $this->wpdb_mock = $this->getMockBuilder('stdClass')
            ->setMethods(array('prepare', 'get_results', 'get_var', 'esc_like', 'get_row', 'query', 'insert', 'update', 'delete'))
            ->getMock();

        $this->wpdb_mock->prefix = 'wp_';

        // Mock methods
        $this->wpdb_mock->method('prepare')->will($this->returnCallback(function($query, ...$args) {
             // Handle array arg if passed as single argument
             if (isset($args[0]) && is_array($args[0])) {
                 $args = $args[0];
             }
             return $query;
        }));

        $this->wpdb_mock->method('get_results')->willReturn(array());

        $this->wpdb_mock->method('get_row')->willReturn((object) array(
            'total' => 0,
            'completed' => 0,
            'failed' => 0,
            'processing' => 0,
            'log' => array(),
            'id' => 1,
            'template_id' => 1,
            'status' => 'completed',
            'created_at' => current_time('mysql'),
            'generated_title' => 'Test Title',
            'post_id' => 1
        ));

        $this->wpdb_mock->method('get_var')->willReturn(0);

        $wpdb = $this->wpdb_mock;

        $this->history_instance = new AIPS_History();
    }

    public function tearDown(): void {
        global $wpdb;
        $wpdb = $this->original_wpdb;
        parent::tearDown();
    }

    /**
     * Test that the template can handle $stats as an AIPS_History object
     */
    public function test_history_template_handles_stats_as_object() {
        // Setup: Pass $stats as an AIPS_History object (simulating the bug condition)
        $stats = $this->history_instance;
        
        // Capture output
        ob_start();
        
        // Include the template with $stats as an AIPS_History object
        // This should NOT throw a fatal error after the fix
        try {
            include AIPS_PLUGIN_DIR . 'templates/admin/history.php';
            $output = ob_get_clean();
            
            // If we got here without an exception, the fix works
            $this->assertIsString($output);
            
            // Check that the output contains expected elements
            $this->assertStringContainsString('aips-history-tab', $output);
            
        } catch (Throwable $e) {
            ob_end_clean();
            $this->fail('Template threw an error when $stats is an AIPS_History object: ' . $e->getMessage());
        }
    }

    /**
     * Test that the template works normally when variables are correct
     */
    public function test_history_template_with_correct_variables() {
        // Setup: Use correct variable types
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
        $status_filter = '';
        $search_query = '';
        
        // Capture output
        ob_start();
        
        try {
            include AIPS_PLUGIN_DIR . 'templates/admin/history.php';
            $output = ob_get_clean();
            
            // Check that the output is generated
            $this->assertIsString($output);
            $this->assertStringContainsString('aips-history-tab', $output);
            
        } catch (Throwable $e) {
            ob_end_clean();
            $this->fail('Template threw an error with correct variables: ' . $e->getMessage());
        }
    }

    /**
     * Test that the template can handle $history as an AIPS_History object
     */
    public function test_history_template_handles_history_as_object() {
        // Setup: Pass $history as an AIPS_History object
        $history = $this->history_instance;
        
        // Capture output
        ob_start();
        
        try {
            include AIPS_PLUGIN_DIR . 'templates/admin/history.php';
            $output = ob_get_clean();
            
            // If we got here without an exception, it works
            $this->assertIsString($output);
            $this->assertStringContainsString('aips-history-tab', $output);
            
        } catch (Throwable $e) {
            ob_end_clean();
            $this->fail('Template threw an error when $history is an AIPS_History object: ' . $e->getMessage());
        }
    }

    /**
     * Test that the template can handle both $history and $stats as AIPS_History objects
     */
    public function test_history_template_handles_both_as_objects() {
        // Setup: Pass both as AIPS_History objects
        $history = $this->history_instance;
        $stats = $this->history_instance;
        
        // Capture output
        ob_start();
        
        try {
            include AIPS_PLUGIN_DIR . 'templates/admin/history.php';
            $output = ob_get_clean();
            
            // If we got here without an exception, it works
            $this->assertIsString($output);
            $this->assertStringContainsString('aips-history-tab', $output);
            
        } catch (Throwable $e) {
            ob_end_clean();
            $this->fail('Template threw an error when both variables are AIPS_History objects: ' . $e->getMessage());
        }
    }
}
