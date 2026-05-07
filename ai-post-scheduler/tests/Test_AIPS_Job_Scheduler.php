<?php
/**
 * Tests for AIPS_Job_Scheduler
 *
 * @package AI_Post_Scheduler
 */

class Test_AIPS_Job_Scheduler extends WP_UnitTestCase {

	private $scheduler;
	private $dispatcher;
	private $slicer;

	public function setUp(): void {
		parent::setUp();

		$this->dispatcher = $this->getMockBuilder('AIPS_Job_Dispatcher')
			->disableOriginalConstructor()
			->getMock();

		$this->slicer = new AIPS_Batch_Slicer();

		$this->scheduler = new AIPS_Job_Scheduler($this->dispatcher, $this->slicer);
	}

	public function test_schedule_simple_dispatches_single_job() {
		$hook = 'test_simple_hook';
		$timestamp = time() + 60;
		$args = array('arg1', 'arg2');

		$this->dispatcher->expects($this->once())
			->method('dispatch')
			->with($this->callback(function($job) use ($hook, $args) {
				return $job->get_hook() === $hook &&
				       $job->get_args() === $args;
			}))
			->willReturn(true);

		$result = $this->scheduler->schedule_simple($hook, $timestamp, $args);
		$this->assertTrue($result);
	}

	public function test_schedule_simple_with_job_type() {
		$this->dispatcher->expects($this->once())
			->method('dispatch')
			->with($this->callback(function($job) {
				return $job->get_job_type() === 'test_type';
			}))
			->willReturn(true);

		$result = $this->scheduler->schedule_simple(
			'test_hook',
			time() + 60,
			array(),
			array('job_type' => 'test_type')
		);
		$this->assertTrue($result);
	}

	public function test_schedule_staggered_dispatches_multiple_jobs() {
		$items = array('item1', 'item2', 'item3');

		// Expect 3 dispatch calls (one per item)
		$this->dispatcher->expects($this->exactly(3))
			->method('dispatch')
			->willReturn(true);

		$summary = $this->scheduler->schedule_staggered('test_hook', $items);

		$this->assertInstanceOf('AIPS_Dispatch_Summary', $summary);
		$this->assertEquals(3, $summary->get_scheduled_count());
		$this->assertEquals(0, $summary->get_failed_count());
		$this->assertTrue($summary->is_success());
	}

	public function test_schedule_staggered_with_custom_args_builder() {
		$items = array(
			(object) array('id' => 1, 'name' => 'Item 1'),
			(object) array('id' => 2, 'name' => 'Item 2'),
		);

		$dispatch_count = 0;
		$this->dispatcher->expects($this->exactly(2))
			->method('dispatch')
			->willReturnCallback(function($job) use (&$dispatch_count) {
				$dispatch_count++;
				// Check that args were built correctly
				$args = $job->get_args();
				$this->assertIsArray($args);
				$this->assertCount(2, $args); // id and name
				return true;
			});

		$summary = $this->scheduler->schedule_staggered('test_hook', $items, array(
			'args_builder' => function($item) {
				return array($item->id, $item->name);
			},
		));

		$this->assertEquals(2, $summary->get_scheduled_count());
	}

	public function test_schedule_staggered_with_custom_stagger() {
		$items = array('item1', 'item2');
		$base_time = time();

		$timestamps = array();
		$this->dispatcher->expects($this->exactly(2))
			->method('dispatch')
			->willReturnCallback(function($job) use (&$timestamps) {
				$timestamps[] = $job->get_fire_at();
				return true;
			});

		$this->scheduler->schedule_staggered('test_hook', $items, array(
			'stagger_seconds' => 30,
		));

		// Second item should be 30 seconds after first
		$this->assertEquals(30, $timestamps[1] - $timestamps[0]);
	}

	public function test_schedule_staggered_handles_failures() {
		$items = array('item1', 'item2', 'item3');

		// Fail the second dispatch
		$dispatch_count = 0;
		$this->dispatcher->expects($this->exactly(3))
			->method('dispatch')
			->willReturnCallback(function() use (&$dispatch_count) {
				$dispatch_count++;
				return $dispatch_count !== 2; // Fail second one
			});

		$summary = $this->scheduler->schedule_staggered('test_hook', $items);

		$this->assertEquals(2, $summary->get_scheduled_count());
		$this->assertEquals(1, $summary->get_failed_count());
		$this->assertTrue($summary->is_partial());
	}

	public function test_schedule_batched_creates_batch_jobs() {
		$item_count = 20;

		// With default settings, should create ~10 slices (aim for 2 per slice, max 10)
		$this->dispatcher->expects($this->exactly(10))
			->method('dispatch')
			->willReturn(true);

		$summary = $this->scheduler->schedule_batched('test_batch_hook', $item_count);

		$this->assertInstanceOf('AIPS_Dispatch_Summary', $summary);
		$this->assertEquals(10, $summary->get_scheduled_count());
		$this->assertEquals(0, $summary->get_failed_count());
	}

	public function test_schedule_batched_with_prefix_args() {
		$prefix_args = array('schedule_id' => 123);

		$this->dispatcher->expects($this->atLeastOnce())
			->method('dispatch')
			->with($this->callback(function($job) use ($prefix_args) {
				$args = $job->get_args();
				// First args should be prefix_args
				$this->assertEquals(123, $args[0]['schedule_id']);
				return true;
			}))
			->willReturn(true);

		$this->scheduler->schedule_batched('test_hook', 10, array(
			'prefix_args' => $prefix_args,
		));
	}

	public function test_schedule_batched_respects_context() {
		// Add filter for custom context
		add_filter('aips_batch_max_slices_custom', function() {
			return 3;
		});

		// 20 items with max 3 slices = 3 dispatches
		$this->dispatcher->expects($this->exactly(3))
			->method('dispatch')
			->willReturn(true);

		$summary = $this->scheduler->schedule_batched('test_hook', 20, array(
			'context' => 'custom',
		));

		$this->assertEquals(3, $summary->get_scheduled_count());

		remove_all_filters('aips_batch_max_slices_custom');
	}

	public function test_get_slicer_returns_instance() {
		$slicer = $this->scheduler->get_slicer();
		$this->assertInstanceOf('AIPS_Batch_Slicer', $slicer);
	}
}
