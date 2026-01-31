<?php
/**
 * Test case for History Export to CSV
 */

class Test_History_Export extends WP_UnitTestCase {

    private $history_instance;

    public function setUp(): void {
        parent::setUp();
        $this->history_instance = new AIPS_History();
    }

    /**
     * Test that the export action is registered.
     */
    public function test_export_action_hooked() {
        // Check if action is hooked
        // Note: The object instance in add_action is $this->history_instance created in constructor.
        // But WP hooks store the specific instance.
        // Since we create a NEW instance in setUp, has_action might not find it if the constructor runs once globally?
        // No, AIPS_History is instantiated in setUp.
        // Wait, AIPS_History constructor adds the action.

        // has_action( 'hook_name', 'callback' )
        // callback is array( instance, method )

        $priority = has_action('wp_ajax_aips_export_history', array($this->history_instance, 'ajax_export_history'));
        $this->assertEquals(10, $priority);
    }

    /**
     * Test that the output content type would be CSV.
     * Note: We cannot execute the full method because of exit() and header(),
     * but we can verified the method exists and is callable.
     */
    public function test_method_exists() {
        $this->assertTrue(method_exists($this->history_instance, 'ajax_export_history'));
    }
}
