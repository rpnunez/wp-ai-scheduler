<?php
/**
 * Class Test_Voices
 *
 * @package AI_Post_Scheduler
 */

class Test_Voices extends WP_UnitTestCase {
    private $voices;

    public function setUp(): void {
        parent::setUp();

        // Mock the global wpdb if it doesn't support the methods we need
        // (Though bootstrap.php provides a basic mock)
        global $wpdb;
        if (!method_exists($wpdb, 'insert')) {
            // Re-mock if necessary (bootstrap one should be fine though)
        }

        // Instantiate the class
        $this->voices = new AIPS_Voices();

        // Mock current user as admin
        wp_set_current_user(1);
        global $test_users;
        $test_users[1] = 'administrator';
    }

    public function tearDown(): void {
        parent::tearDown();
    }

    /**
     * Test cloning a voice successfully
     */
    public function test_ajax_clone_voice_success() {
        // Mock get method to return a dummy voice
        $original_voice = (object) array(
            'id' => 123,
            'name' => 'Original Voice',
            'title_prompt' => 'Title prompt',
            'content_instructions' => 'Content instructions',
            'excerpt_instructions' => 'Excerpt instructions',
            'is_active' => 1
        );

        // We need to mock the `get` method of AIPS_Voices or the $wpdb->get_row
        // Since AIPS_Voices uses $wpdb directly, we can mock $wpdb results
        global $wpdb;

        // Mock get_row for get()
        $wpdb->last_query_result_row = $original_voice;

        // We need to subclass or mock AIPS_Voices to override `get` if we can't easily mock wpdb->get_row behaviors sequence
        // For this test environment, let's try to mock the methods we rely on by creating a partial mock of the class if possible,
        // OR rely on the bootstrap mock behavior.

        // The bootstrap mock for get_row returns null. We need to improve that or override it.
        // Let's create a subclass for testing to override DB interaction
        $voices_mock = $this->getMockBuilder('AIPS_Voices')
                            ->onlyMethods(['get', 'save'])
                            ->getMock();

        $voices_mock->method('get')
                    ->with(123)
                    ->willReturn($original_voice);

        $voices_mock->method('save')
                    ->willReturn(124); // Return new ID

        // We need to replace the instance in the AJAX handler, but the handler is an object method.
        // So we can directly call the method on our mock object, bypassing the hook system for unit testing the logic.

        // Setup $_POST
        $_POST['voice_id'] = 123;
        $_POST['nonce'] = wp_create_nonce('aips_ajax_nonce'); // Matches bootstrap mock
        $_REQUEST['nonce'] = $_POST['nonce'];

        // Capture output
        try {
            $voices_mock->ajax_clone_voice();
        } catch (WPAjaxDieContinueException $e) {
            // Expected
        }

        // Verify output
        $this->expectOutputRegex('/"success":true/');
        $this->expectOutputRegex('/"voice_id":124/');
    }

    /**
     * Test cloning with invalid ID
     */
    public function test_ajax_clone_voice_invalid_id() {
        $voices = new AIPS_Voices();

        $_POST['voice_id'] = 0;
        $_POST['nonce'] = wp_create_nonce('aips_ajax_nonce');
        $_REQUEST['nonce'] = $_POST['nonce'];

        try {
            $voices->ajax_clone_voice();
        } catch (WPAjaxDieContinueException $e) {
            // Expected
        }

        $this->expectOutputRegex('/"success":false/');
        $this->expectOutputRegex('/Invalid voice ID/');
    }

    /**
     * Test cloning non-existent voice
     */
    public function test_ajax_clone_voice_not_found() {
        $voices_mock = $this->getMockBuilder('AIPS_Voices')
                            ->onlyMethods(['get'])
                            ->getMock();

        $voices_mock->method('get')
                    ->willReturn(null);

        $_POST['voice_id'] = 999;
        $_POST['nonce'] = wp_create_nonce('aips_ajax_nonce');
        $_REQUEST['nonce'] = $_POST['nonce'];

        try {
            $voices_mock->ajax_clone_voice();
        } catch (WPAjaxDieContinueException $e) {
            // Expected
        }

        $this->expectOutputRegex('/"success":false/');
        $this->expectOutputRegex('/Voice not found/');
    }

    /**
     * Test permissions
     */
    public function test_ajax_clone_voice_permissions() {
        // Mock non-admin user
        global $test_users, $current_user_id;
        $current_user_id = 2;
        $test_users[2] = 'subscriber';

        $voices = new AIPS_Voices();

        $_POST['voice_id'] = 123;
        $_POST['nonce'] = wp_create_nonce('aips_ajax_nonce');
        $_REQUEST['nonce'] = $_POST['nonce'];

        try {
            $voices->ajax_clone_voice();
        } catch (WPAjaxDieContinueException $e) {
            // Expected
        }

        $this->expectOutputRegex('/"success":false/');
        $this->expectOutputRegex('/Permission denied/');

        // Restore admin
        $current_user_id = 1;
    }
}
