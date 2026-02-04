<?php
/**
 * Tests for AIPS_Voices
 *
 * @package AI_Post_Scheduler
 */

class Test_Voices extends WP_UnitTestCase {

    private $voices;
    private $admin_user_id;
    private $subscriber_user_id;

    public function setUp(): void {
        parent::setUp();

        // Create test users
        $this->admin_user_id = $this->factory->user->create(array('role' => 'administrator'));
        $this->subscriber_user_id = $this->factory->user->create(array('role' => 'subscriber'));

        // Initialize Voices
        $this->voices = new AIPS_Voices();

        // Set up nonce
        $_REQUEST['nonce'] = wp_create_nonce('aips_ajax_nonce');
    }

    public function tearDown(): void {
        $_POST = array();
        $_REQUEST = array();
        parent::tearDown();
    }

    public function test_ajax_clone_voice_success() {
        wp_set_current_user($this->admin_user_id);

        // Use partial mock to isolate from DB calls
        $voices_mock = $this->createPartialMock(AIPS_Voices::class, ['get', 'save']);

        // Mock get to return a voice
        $mock_voice = (object) array(
            'id' => 1,
            'name' => 'Original Voice',
            'title_prompt' => 'Title Prompt',
            'content_instructions' => 'Content Instructions',
            'excerpt_instructions' => 'Excerpt Instructions',
            'is_active' => 1
        );
        $voices_mock->method('get')->with(1)->willReturn($mock_voice);

        // Mock save to return new ID
        // Also verify that save is called with correct data
        $expected_data = array(
            'name' => 'Original Voice (Copy)',
            'title_prompt' => 'Title Prompt',
            'content_instructions' => 'Content Instructions',
            'excerpt_instructions' => 'Excerpt Instructions',
            'is_active' => 1
        );

        $voices_mock->method('save')
            ->with($this->callback(function($data) use ($expected_data) {
                return $data['name'] === $expected_data['name'] &&
                       $data['title_prompt'] === $expected_data['title_prompt'];
            }))
            ->willReturn(2);

        // Set POST data
        $_POST['voice_id'] = 1;

        // Execute
        $this->expectOutputRegex('/.*Voice cloned successfully.*/');
        try {
            $voices_mock->ajax_clone_voice();
        } catch (WPAjaxDieContinueException $e) {
            // Expected
        }

        $output = $this->getActualOutput();
        $response = json_decode($output, true);

        $this->assertTrue($response['success']);
        $this->assertEquals(2, $response['data']['voice_id']);
    }

    public function test_ajax_clone_voice_permission_denied() {
        wp_set_current_user($this->subscriber_user_id);
        $_POST['voice_id'] = 1;

        $this->expectOutputRegex('/.*Permission denied.*/');
        try {
            $this->voices->ajax_clone_voice();
        } catch (WPAjaxDieContinueException $e) {
            // Expected
        }
    }

    public function test_ajax_clone_voice_invalid_id() {
        wp_set_current_user($this->admin_user_id);
        $_POST['voice_id'] = 0;

        $this->expectOutputRegex('/.*Invalid voice ID.*/');
        try {
            $this->voices->ajax_clone_voice();
        } catch (WPAjaxDieContinueException $e) {
            // Expected
        }
    }

    public function test_ajax_clone_voice_not_found() {
        wp_set_current_user($this->admin_user_id);

        // Use partial mock
        $voices_mock = $this->createPartialMock(AIPS_Voices::class, ['get']);

        // Mock get to return null (not found)
        $voices_mock->method('get')->with(999)->willReturn(null);

        $_POST['voice_id'] = 999;

        $this->expectOutputRegex('/.*Voice not found.*/');
        try {
            $voices_mock->ajax_clone_voice();
        } catch (WPAjaxDieContinueException $e) {
            // Expected
        }
    }
}
