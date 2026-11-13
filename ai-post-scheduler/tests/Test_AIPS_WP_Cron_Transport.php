<?php
/**
 * Tests for AIPS_WP_Cron_Transport.
 *
 * @package AI_Post_Scheduler
 */

class Test_AIPS_WP_Cron_Transport extends WP_UnitTestCase {

	/**
	 * @var AIPS_WP_Cron_Transport
	 */
	private $transport;

	public function setUp(): void {
		parent::setUp();
		$this->transport = new AIPS_WP_Cron_Transport();
	}

	private function make_job(string $hook, array $args, int $fire_at): AIPS_Job_Definition {
		return new AIPS_Job_Definition('test', $hook, $args, $fire_at, array(), '', AIPS_Job_Groups::EMBEDDINGS);
	}

	public function test_is_available_and_name() {
		$this->assertTrue($this->transport->is_available());
		$this->assertSame('wp_cron', $this->transport->get_name());
	}

	public function test_schedule_and_next_scheduled_round_trip() {
		$hook = 'aips_test_wpcron_hook';
		$args = array(array('author_id' => 7, 'batch_size' => 20));
		$fire_at = time() + 120;

		$job = $this->make_job($hook, $args, $fire_at);

		$this->assertTrue($this->transport->schedule($job));
		$this->assertSame($fire_at, $this->transport->next_scheduled($job));
	}

	public function test_scheduled_args_match_job_args_exactly() {
		$hook = 'aips_test_wpcron_args_hook';
		$args = array(array('last_post_id' => 42, 'batch_size' => 10));
		$fire_at = time() + 300;

		$job = $this->make_job($hook, $args, $fire_at);
		$this->transport->schedule($job);

		// WordPress delivers the stored args positionally to the callback, so
		// wp_next_scheduled() only returns a timestamp when the exact args match.
		$this->assertSame($fire_at, wp_next_scheduled($hook, $args));
	}

	public function test_unschedule_removes_event() {
		$hook = 'aips_test_wpcron_unschedule_hook';
		$args = array(array('author_id' => 3));
		$fire_at = time() + 200;

		$job = $this->make_job($hook, $args, $fire_at);
		$this->transport->schedule($job);
		$this->assertNotFalse($this->transport->next_scheduled($job));

		$this->assertTrue($this->transport->unschedule($job));
		$this->assertFalse($this->transport->next_scheduled($job));
	}

	public function test_unschedule_is_idempotent_when_nothing_scheduled() {
		$job = $this->make_job('aips_test_wpcron_never_scheduled', array('x'), time() + 60);
		$this->assertTrue($this->transport->unschedule($job));
	}
}
