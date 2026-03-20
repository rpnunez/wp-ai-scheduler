<?php
/**
 * Tests for Action Scheduler integration:
 *  - AIPS_Scheduler::schedule_generation_action() adapter
 *  - AIPS_AS_Worker handler
 *
 * @package AI_Post_Scheduler
 */

class Test_AIPS_AS_Integration extends WP_UnitTestCase {

    public function setUp(): void {
        parent::setUp();
        // Reset the shared AS schedule log before each test.
        $GLOBALS['aips_test_as_scheduled'] = array();
    }

    public function tearDown(): void {
        $GLOBALS['aips_test_as_scheduled'] = array();
        delete_option('aips_migrated_to_action_scheduler');
        parent::tearDown();
    }

    // -----------------------------------------------------------------------
    // AIPS_Scheduler::schedule_generation_action adapter
    // -----------------------------------------------------------------------

    /**
     * schedule_generation_action() should call as_schedule_single_action() when
     * Action Scheduler is available and $recurring is false (default).
     */
    public function test_schedule_generation_action_uses_as_when_available() {
        // The bootstrap stubs as_schedule_single_action() so AIPS_AS_AVAILABLE
        // resolves to true in the test environment.
        if (!function_exists('as_schedule_single_action')) {
            $this->markTestSkipped('as_schedule_single_action stub not available.');
        }

        $scheduler = new AIPS_Scheduler();
        $timestamp = time() + 3600;
        $args      = array('template_id' => 42);

        $result = $scheduler->schedule_generation_action($timestamp, $args);

        $this->assertNotFalse($result, 'schedule_generation_action() should not return false on success.');

        $scheduled = $GLOBALS['aips_test_as_scheduled'];
        $this->assertNotEmpty($scheduled, 'An AS action should have been recorded.');

        $last = end($scheduled);
        $this->assertSame('aips_action_generate_post', $last['hook']);
        $this->assertSame($args, $last['args']);
        $this->assertSame('aips', $last['group']);
        $this->assertFalse($last['recurring']);
    }

    /**
     * schedule_generation_action() with $recurring = true should call
     * as_schedule_recurring_action().
     */
    public function test_schedule_generation_action_recurring_uses_as() {
        if (!function_exists('as_schedule_recurring_action')) {
            $this->markTestSkipped('as_schedule_recurring_action stub not available.');
        }

        $scheduler = new AIPS_Scheduler();
        $timestamp = time() + 60;
        $args      = array('template_id' => 7);
        $interval  = 3600;

        $result = $scheduler->schedule_generation_action($timestamp, $args, true, $interval);

        $this->assertNotFalse($result);

        $scheduled = $GLOBALS['aips_test_as_scheduled'];
        $this->assertNotEmpty($scheduled);

        $last = end($scheduled);
        $this->assertSame('aips_action_generate_post', $last['hook']);
        $this->assertTrue($last['recurring']);
        $this->assertSame($interval, $last['interval']);
    }

    // -----------------------------------------------------------------------
    // AIPS_AS_Worker handler
    // -----------------------------------------------------------------------

    /**
     * AIPS_AS_Worker::handle() should throw an Exception when args is not array.
     */
    public function test_as_worker_throws_on_invalid_args() {
        $this->expectException(Exception::class);

        $worker = $this->make_worker_with_mocks(1, 42);
        $worker->handle('not-an-array');
    }

    /**
     * AIPS_AS_Worker::handle() should throw an Exception when template_id is missing.
     */
    public function test_as_worker_throws_on_missing_template_id() {
        $this->expectException(Exception::class);

        $worker = $this->make_worker_with_mocks(null, 42);
        $worker->handle(array());
    }

    /**
     * AIPS_AS_Worker::handle() should throw an Exception when template is not found.
     */
    public function test_as_worker_throws_when_template_not_found() {
        $this->expectException(Exception::class);

        $logger = $this->make_logger();

        $template_repository = new class {
            public function get_by_id($id) { return null; }
        };

        $generator = new class {
            public function generate_post($template, $voice = null, $topic = null) { return 1; }
        };

        $worker = new AIPS_AS_Worker($generator, $template_repository, $logger);
        $worker->handle(array('template_id' => 999));
    }

    /**
     * AIPS_AS_Worker::handle() should throw when generator returns WP_Error.
     */
    public function test_as_worker_throws_on_wp_error_result() {
        $this->expectException(Exception::class);

        $logger = $this->make_logger();

        $fake_template = (object) array('id' => 5, 'prompt_template' => 'test');

        $template_repository = new class($fake_template) {
            private $tpl;
            public function __construct($tpl) { $this->tpl = $tpl; }
            public function get_by_id($id) { return $this->tpl; }
        };

        $generator = new class {
            public function generate_post($template, $voice = null, $topic = null) {
                return new WP_Error('gen_failed', 'Something went wrong');
            }
        };

        $worker = new AIPS_AS_Worker($generator, $template_repository, $logger);
        $worker->handle(array('template_id' => 5));
    }

    /**
     * AIPS_AS_Worker::handle() should succeed and not throw when generation is successful.
     */
    public function test_as_worker_succeeds_on_valid_generation() {
        $logger = $this->make_logger();

        $fake_template = (object) array('id' => 5, 'prompt_template' => 'test');

        $template_repository = new class($fake_template) {
            private $tpl;
            public function __construct($tpl) { $this->tpl = $tpl; }
            public function get_by_id($id) { return $this->tpl; }
        };

        $generator = new class {
            public function generate_post($template, $voice = null, $topic = null) {
                return 123; // fake post ID
            }
        };

        $worker = new AIPS_AS_Worker($generator, $template_repository, $logger);

        // Should not throw.
        $exception = null;
        try {
            $worker->handle(array('template_id' => 5));
        } catch (\Exception $e) {
            $exception = $e;
        }

        $this->assertNull($exception, 'handle() should not throw on successful generation.');
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    /**
     * Build a worker with simple anonymous-class mocks.
     *
     * @param int|null $template_id   template_id to include in args (or null to omit).
     * @param int      $fake_post_id  Post ID the generator stub will return.
     * @return AIPS_AS_Worker
     */
    private function make_worker_with_mocks($template_id, $fake_post_id) {
        $logger = $this->make_logger();

        $fake_template = (object) array('id' => $template_id ?? 1);

        $template_repository = new class($fake_template, $template_id) {
            private $tpl;
            private $valid_id;
            public function __construct($tpl, $valid_id) {
                $this->tpl      = $tpl;
                $this->valid_id = $valid_id;
            }
            public function get_by_id($id) {
                return ($this->valid_id && $id === $this->valid_id) ? $this->tpl : null;
            }
        };

        $generator = new class($fake_post_id) {
            private $post_id;
            public function __construct($post_id) { $this->post_id = $post_id; }
            public function generate_post($template, $voice = null, $topic = null) {
                return $this->post_id;
            }
        };

        return new AIPS_AS_Worker($generator, $template_repository, $logger);
    }

    /**
     * Create a simple logger stub.
     *
     * @return object
     */
    private function make_logger() {
        return new class {
            public function log($message, $level = 'info') {}
        };
    }
}
