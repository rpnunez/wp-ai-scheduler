<?php
/**
 * Test Scheduler Run Now
 *
 * @package AI_Post_Scheduler
 */

class Test_Scheduler_Run_Now extends WP_UnitTestCase {

    public function test_run_schedule_now_success() {
        global $wpdb;

        $schedule_id = 123;
        $template_id = 456;

        // Mock schedule/template data (merged for simplicity as get_row is mocked once)
        $mock_schedule = (object) array(
            'id' => $schedule_id,
            'template_id' => $template_id,
            'template_name' => 'Test Template',
            'prompt_template' => 'Prompt',
            'title_prompt' => 'Title',
            'post_status' => 'draft',
            'post_category' => 1,
            'post_tags' => 'tag',
            'post_author' => 1,
            'frequency' => 'daily',
            'next_run' => '2024-01-01 10:00:00',
            'is_active' => 1,
            'topic' => 'Test Topic',
            'name' => 'Test Schedule',
            'article_structure_id' => null,
            'rotation_pattern' => null,
            'image_prompt' => '',
            'generate_featured_image' => 0
        );

        // Replace global $wpdb with a mock
        $wpdb_mock = $this->getMockBuilder('stdClass')
                          ->setMethods(array('get_row', 'prepare', 'update', 'insert', 'get_var'))
                          ->getMock();

        $wpdb_mock->prefix = 'wp_';
        $wpdb_mock->method('prepare')->willReturnArgument(0);

        $wpdb_mock->expects($this->any())
                  ->method('get_row')
                  ->willReturn($mock_schedule);

        $wpdb_mock->expects($this->any())
                  ->method('update')
                  ->willReturn(true);

        $original_wpdb = $wpdb;
        $wpdb = $wpdb_mock;

        // Instantiate scheduler AFTER replacing wpdb so repositories use the mock
        $scheduler = new AIPS_Scheduler();

        // Mock Generator
        $generator_mock = $this->getMockBuilder('AIPS_Generator')
                                     ->setMethods(array('generate_post'))
                                     ->getMock();
        $scheduler->set_generator($generator_mock);

        // Expect generate_post to be called with correct arguments
        $generator_mock->expects($this->once())
                             ->method('generate_post')
                             ->with(
                                 $this->callback(function($template) use ($template_id, $schedule_id) {
                                     // In this test setup, template->id comes from mock_schedule->id which is schedule_id
                                     // because we reuse the same mock object for both get_row calls
                                     return $template->id == $schedule_id && $template->post_quantity === 1;
                                 }),
                                 $this->anything(),
                                 $this->equalTo('Test Topic')
                             )
                             ->willReturn(999);

        // Execute
        $result = $scheduler->run_schedule_now($schedule_id);

        // Verify result
        $this->assertEquals(999, $result);

        // Restore global
        $wpdb = $original_wpdb;
    }

    public function test_run_schedule_now_not_found() {
        global $wpdb;

        $wpdb_mock = $this->getMockBuilder('stdClass')
                          ->setMethods(array('get_row', 'prepare'))
                          ->getMock();
        $wpdb_mock->prefix = 'wp_';
        $wpdb_mock->method('prepare')->willReturnArgument(0);
        $wpdb_mock->method('get_row')->willReturn(null); // Not found

        $original_wpdb = $wpdb;
        $wpdb = $wpdb_mock;

        $scheduler = new AIPS_Scheduler();

        $result = $scheduler->run_schedule_now(999);

        $this->assertFalse($result);

        $wpdb = $original_wpdb;
    }
}
