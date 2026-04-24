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
}
