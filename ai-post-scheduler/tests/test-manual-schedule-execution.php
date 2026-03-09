<?php
/**
 * Test case for Manual Schedule Execution
 *
 * @package AI_Post_Scheduler
 */

class Test_AIPS_Manual_Schedule_Execution extends WP_UnitTestCase {

    private $scheduler;

    public function setUp(): void {
        parent::setUp();
        $this->scheduler = new AIPS_Scheduler();
        // Reset global state between tests.
        $GLOBALS['aips_test_scheduled_events'] = array();
        $GLOBALS['aips_test_options'] = array();
    }

    public function tearDown(): void {
        $GLOBALS['aips_test_scheduled_events'] = array();
        $GLOBALS['aips_test_options'] = array();
        parent::tearDown();
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Set the max-posts-per-run setting in the mock options store.
     */
    private function set_max_per_run($n) {
        $GLOBALS['aips_test_options']['aips_max_posts_per_run'] = $n;
    }

    /**
     * Set the stagger interval setting in the mock options store.
     */
    private function set_stagger_interval($minutes) {
        $GLOBALS['aips_test_options']['aips_stagger_interval_minutes'] = $minutes;
    }

    /**
     * Build a configured scheduler with mocked dependencies.
     *
     * @param object $schedule         The schedule object returned by the repository.
     * @param object $template         The template object returned by the repository.
     * @param int    $expected_calls   Number of generate_post() calls expected (equals immediate_qty).
     * @param mixed  $generator_return Single return value or array of per-call return values.
     *                                 Default post ID 123 for every call.
     */
    private function build_scheduler_with_mocks($schedule, $template, $expected_calls = 1, $generator_return = 123) {
        // Schedule repository mock
        $mock_schedule_repo = $this->getMockBuilder('AIPS_Schedule_Repository')
            ->onlyMethods(array('get_by_id'))
            ->getMock();

        $mock_schedule_repo->expects($this->once())
            ->method('get_by_id')
            ->with($schedule->id)
            ->willReturn($schedule);

        $this->scheduler->set_repository($mock_schedule_repo);

        // Template repository mock
        $mock_template_repo = $this->getMockBuilder('AIPS_Template_Repository')
            ->onlyMethods(array('get_by_id'))
            ->getMock();

        $mock_template_repo->expects($this->once())
            ->method('get_by_id')
            ->with($template->id)
            ->willReturn($template);

        $this->scheduler->set_template_repository($mock_template_repo);

        // Generator mock
        $mock_generator = $this->getMockBuilder('AIPS_Generator')
            ->disableOriginalConstructor()
            ->onlyMethods(array('generate_post'))
            ->getMock();

        $expectation = $mock_generator->expects($this->exactly($expected_calls))
            ->method('generate_post');

        if (is_array($generator_return)) {
            $expectation->willReturnOnConsecutiveCalls(...$generator_return);
        } else {
            $expectation->willReturn($generator_return);
        }

        $this->scheduler->set_generator($mock_generator);

        return $this->scheduler;
    }

    /**
     * Return the subset of scheduled WP Cron events for the stagger hook.
     */
    private function get_staggered_events() {
        return array_values(array_filter(
            $GLOBALS['aips_test_scheduled_events'] ?? array(),
            function ($e) {
                return $e['hook'] === 'aips_generate_staggered_post';
            }
        ));
    }

    // -------------------------------------------------------------------------
    // Stagger logic tests
    // -------------------------------------------------------------------------

    /**
     * When post_quantity (5) exceeds max_per_run (2), only 2 posts are generated
     * immediately and 3 are staggered as WP Cron jobs.
     */
    public function test_run_schedule_now_staggers_posts_exceeding_max_per_run() {
        $this->set_max_per_run(2);
        $this->set_stagger_interval(5);

        $schedule = (object) array(
            'id' => 123,
            'template_id' => 456,
            'topic' => 'Manual Topic',
            'frequency' => 'daily',
            'next_run' => '2023-01-01 00:00:00',
            'is_active' => 1,
        );

        $template = (object) array(
            'id' => 456,
            'name' => 'Manual Test Template',
            'post_quantity' => 5,
            'is_active' => 1,
        );

        // Only 2 posts generated immediately; 3 are staggered.
        $this->build_scheduler_with_mocks($schedule, $template, 2, 123);

        $result = $this->scheduler->run_schedule_now(123);

        if (is_wp_error($result)) {
            $this->fail('Unexpected WP_Error: ' . $result->get_error_message());
        }
        $this->assertIsArray($result);
        $this->assertCount(2, $result, 'Only max_per_run posts should be returned immediately.');
        $this->assertEquals(array(123, 123), $result);

        $staggered = $this->get_staggered_events();
        $this->assertCount(3, $staggered, '3 staggered Cron events should be scheduled for the remaining posts.');
        foreach ($staggered as $event) {
            $this->assertEquals(array(123), $event['args'], 'Each staggered event should carry the schedule ID.');
        }

        // Verify events are spaced by the configured stagger interval (5 min = 300 s).
        $interval_seconds = 5 * MINUTE_IN_SECONDS;
        for ($i = 1; $i < count($staggered); $i++) {
            $gap = $staggered[$i]['timestamp'] - $staggered[$i - 1]['timestamp'];
            $this->assertEquals($interval_seconds, $gap, 'Consecutive staggered events should be separated by exactly stagger_interval_minutes * 60 seconds.');
        }
    }

    /**
     * When post_quantity <= max_per_run, all posts are generated synchronously and
     * no WP Cron events are queued.
     */
    public function test_no_stagger_when_quantity_within_max_per_run() {
        $this->set_max_per_run(5);

        $schedule = (object) array(
            'id' => 10,
            'template_id' => 20,
            'topic' => '',
            'frequency' => 'daily',
            'next_run' => '2023-01-01 00:00:00',
            'is_active' => 1,
        );

        $template = (object) array(
            'id' => 20,
            'name' => 'Template',
            'post_quantity' => 3,
            'is_active' => 1,
        );

        $this->build_scheduler_with_mocks($schedule, $template, 3, 77);

        $result = $this->scheduler->run_schedule_now(10);

        $this->assertIsArray($result);
        $this->assertCount(3, $result, 'All 3 posts should be generated immediately when within the limit.');

        $staggered = $this->get_staggered_events();
        $this->assertCount(0, $staggered, 'No Cron events should be scheduled when quantity <= max_per_run.');
    }

    /**
     * When a quantity_override of 3 is provided but max_per_run is 2, only 2 posts
     * are generated immediately and 1 is staggered via WP Cron.
     */
    public function test_quantity_override_with_stagger() {
        $this->set_max_per_run(2);
        $this->set_stagger_interval(10);

        $schedule = (object) array(
            'id' => 123,
            'template_id' => 456,
            'topic' => 'Override Topic',
            'frequency' => 'daily',
            'next_run' => '2023-01-01 00:00:00',
            'is_active' => 1,
        );

        $template = (object) array(
            'id' => 456,
            'name' => 'Override Test Template',
            'post_quantity' => 5, // template says 5; override should win
            'is_active' => 1,
        );

        // Override to 3; max_per_run=2 → 2 immediate, 1 staggered.
        $this->build_scheduler_with_mocks($schedule, $template, 2, 123);

        $result = $this->scheduler->run_schedule_now(123, 3);

        $this->assertIsArray($result);
        $this->assertCount(2, $result, 'Only 2 posts should be generated immediately (max_per_run).');

        $staggered = $this->get_staggered_events();
        $this->assertCount(1, $staggered, 'Exactly 1 staggered Cron event should be scheduled.');
    }

    // -------------------------------------------------------------------------
    // Success / failure loop tests
    // -------------------------------------------------------------------------

    /**
     * When every generate_post() call returns WP_Error, run_schedule_now() must
     * return a WP_Error.
     */
    public function test_all_fail_returns_wp_error() {
        $this->set_max_per_run(3);

        $schedule = (object) array(
            'id' => 50,
            'template_id' => 60,
            'topic' => '',
            'frequency' => 'daily',
            'next_run' => '2023-01-01 00:00:00',
            'is_active' => 1,
        );

        $template = (object) array(
            'id' => 60,
            'name' => 'Fail Template',
            'post_quantity' => 2, // 2 <= max_per_run(3), so both run immediately
            'is_active' => 1,
        );

        $error = new WP_Error('generation_failed', 'AI error');
        $this->build_scheduler_with_mocks($schedule, $template, 2, $error);

        $result = $this->scheduler->run_schedule_now(50);

        $this->assertWPError($result, 'All-fail should return a WP_Error.');
    }

    /**
     * When some generate_post() calls succeed and others fail (partial success),
     * run_schedule_now() must return a non-empty array of the successful post IDs.
     */
    public function test_partial_success_returns_nonempty_array() {
        $this->set_max_per_run(3);

        $schedule = (object) array(
            'id' => 70,
            'template_id' => 80,
            'topic' => '',
            'frequency' => 'daily',
            'next_run' => '2023-01-01 00:00:00',
            'is_active' => 1,
        );

        $template = (object) array(
            'id' => 80,
            'name' => 'Partial Template',
            'post_quantity' => 3, // 3 <= max_per_run(3), all run immediately
            'is_active' => 1,
        );

        $error = new WP_Error('generation_failed', 'AI error');
        // 1st call returns post 99, 2nd fails, 3rd returns post 101.
        $this->build_scheduler_with_mocks($schedule, $template, 3, array(99, $error, 101));

        $result = $this->scheduler->run_schedule_now(70);

        $this->assertIsArray($result, 'Partial success should return an array, not WP_Error.');
        $this->assertCount(2, $result, 'Only the 2 successful post IDs should be returned.');
        $this->assertEquals(array(99, 101), $result);
    }

    // -------------------------------------------------------------------------
    // Error path
    // -------------------------------------------------------------------------

    /**
     * Verifies that a schedule_not_found error is returned when the schedule does not exist.
     */
    public function test_run_schedule_now_not_found() {
        $mock_schedule_repo = $this->getMockBuilder('AIPS_Schedule_Repository')
            ->onlyMethods(array('get_by_id'))
            ->getMock();

        $mock_schedule_repo->expects($this->once())
            ->method('get_by_id')
            ->willReturn(null);

        $this->scheduler->set_repository($mock_schedule_repo);

        $result = $this->scheduler->run_schedule_now(9999);
        $this->assertWPError($result);
        $this->assertEquals('schedule_not_found', $result->get_error_code());
    }
}
