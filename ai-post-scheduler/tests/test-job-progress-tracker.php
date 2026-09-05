<?php
/**
 * PHPUnit tests for AIPS_Job_Progress_Tracker
 *
 * These are lightweight unit tests that mock the schedule repository to
 * validate integration points with the canonical aips_schedule_batch_runs
 * helpers without touching the database.
 */

use PHPUnit\Framework\TestCase;

// Allow tests to be executed via the plugin's test bootstrap which should
// provide autoloading. Avoid manually requiring plugin files here so CI can
// control bootstrap.

class JobProgressTrackerTest extends TestCase {

    public function test_save_progress_updates_batch_run_when_present() {
        $mockRepo = $this->createMock(AIPS_Schedule_Repository_Interface::class);

        $run = new stdClass();
        $run->id = 123;
        $run->status = 'pending';

        $mockRepo->method('get_batch_runs_for_schedule')
            ->with($this->equalTo(1))
            ->willReturn(array($run));

        $mockRepo->expects($this->once())
            ->method('update_batch_run_progress')
            ->with($this->equalTo(123), $this->equalTo(2), $this->equalTo(1))
            ->willReturn(true);

        $mockRepo->expects($this->once())
            ->method('append_post_ids_to_batch_run')
            ->with($this->equalTo(123), $this->equalTo(array('10','11')))
            ->willReturn(true);

        $tracker = new AIPS_Job_Progress_Tracker($mockRepo);

        $result = $tracker->save_progress('schedule_1', array(
            'completed' => 2,
            'total' => 5,
            'last_index' => 1,
            'post_ids' => array('10','11'),
        ));

        $this->assertTrue($result);
    }

    public function test_load_progress_prefers_batch_run_when_present() {
        $mockRepo = $this->createMock(AIPS_Schedule_Repository_Interface::class);

        $run = new stdClass();
        $run->id = 321;
        $run->status = 'running';
        $run->completed = 3;
        $run->total = 10;
        $run->resume_index = 2;
        $run->post_ids = json_encode(array(100,101,102));

        $mockRepo->method('get_batch_runs_for_schedule')
            ->with($this->equalTo(2))
            ->willReturn(array($run));

        $tracker = new AIPS_Job_Progress_Tracker($mockRepo);

        $loaded = $tracker->load_progress('schedule_2');

        $this->assertIsArray($loaded);
        $this->assertArrayHasKey('completed', $loaded);
        $this->assertEquals(3, $loaded['completed']);
        $this->assertEquals(10, $loaded['total']);
        $this->assertEquals(2, $loaded['last_index']);
        $this->assertEquals(array(100,101,102), $loaded['post_ids']);
    }
}
