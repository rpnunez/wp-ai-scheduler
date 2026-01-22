<?php
/**
 * Test case for History Template Variable Handling
 *
 * Tests that the history.php template correctly handles
 * \AIPS\Controllers\History objects passed as variables.
 */

class Test_History_Template extends WP_UnitTestCase {

    private $history_instance;

    public function setUp(): void {
        parent::setUp();
        $this->history_instance = new \AIPS\Controllers\History();
    }

    /**
     * Test that the template can handle $stats as a \AIPS\Controllers\History object
     */
    public function test_history_template_handles_stats_as_object() {
        // Setup: Pass $stats as a \AIPS\Controllers\History object (simulating the bug condition)
        $stats = $this->history_instance;
        
        // Capture output
        ob_start();
        
        // Include the template with $stats as a \AIPS\Controllers\History object
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
            $this->fail('Template threw an error when $stats is a \AIPS\Controllers\History object: ' . $e->getMessage());
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
     * Test that the template can handle $history as a \AIPS\Controllers\History object
     */
    public function test_history_template_handles_history_as_object() {
        // Setup: Pass $history as a \AIPS\Controllers\History object
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
            $this->fail('Template threw an error when $history is a \AIPS\Controllers\History object: ' . $e->getMessage());
        }
    }

    /**
     * Test that the template can handle both $history and $stats as \AIPS\Controllers\History objects
     */
    public function test_history_template_handles_both_as_objects() {
        // Setup: Pass both as \AIPS\Controllers\History objects
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
            $this->fail('Template threw an error when both variables are \AIPS\Controllers\History objects: ' . $e->getMessage());
        }
    }
}
