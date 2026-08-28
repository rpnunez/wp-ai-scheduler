<?php
/**
 * Tests for AIPS_Job_Transport_Resolver.
 *
 * @package AI_Post_Scheduler
 */

/**
 * Action Scheduler transport double whose availability can be forced.
 */
class AIPS_Fake_AS_Transport extends AIPS_Action_Scheduler_Transport {
	private $available;

	public function __construct(bool $available) {
		$this->available = $available;
	}

	public function is_available(): bool {
		return $this->available;
	}
}

class Test_AIPS_Job_Transport_Resolver extends WP_UnitTestCase {

	public function tearDown(): void {
		remove_all_filters('aips_prefer_action_scheduler');
		parent::tearDown();
	}

	public function test_resolves_to_wp_cron_when_action_scheduler_unavailable() {
		$resolver = new AIPS_Job_Transport_Resolver(
			new AIPS_Fake_AS_Transport(false),
			new AIPS_WP_Cron_Transport()
		);

		$this->assertInstanceOf('AIPS_WP_Cron_Transport', $resolver->resolve());
		$this->assertSame('wp_cron', $resolver->resolve()->get_name());
	}

	public function test_resolves_to_action_scheduler_when_available() {
		$resolver = new AIPS_Job_Transport_Resolver(
			new AIPS_Fake_AS_Transport(true),
			new AIPS_WP_Cron_Transport()
		);

		$this->assertInstanceOf('AIPS_Action_Scheduler_Transport', $resolver->resolve());
		$this->assertSame('action_scheduler', $resolver->resolve()->get_name());
	}

	public function test_filter_can_force_wp_cron_fallback() {
		add_filter('aips_prefer_action_scheduler', '__return_false');

		$resolver = new AIPS_Job_Transport_Resolver(
			new AIPS_Fake_AS_Transport(true),
			new AIPS_WP_Cron_Transport()
		);

		$this->assertSame('wp_cron', $resolver->resolve()->get_name());
	}

	public function test_resolution_is_cached_until_reset() {
		$resolver = new AIPS_Job_Transport_Resolver(
			new AIPS_Fake_AS_Transport(false),
			new AIPS_WP_Cron_Transport()
		);

		$first = $resolver->resolve();
		$this->assertSame($first, $resolver->resolve());

		$resolver->reset();
		$this->assertInstanceOf('AIPS_WP_Cron_Transport', $resolver->resolve());
	}
}
