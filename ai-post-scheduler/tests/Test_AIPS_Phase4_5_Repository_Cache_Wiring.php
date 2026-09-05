<?php
/**
 * Structural regression tests for the Phase 4 + 5 (#1944) repository cache migration.
 *
 * Phase 4 (post-review) and Phase 5 (notifications, telemetry) all adopt the
 * AIPS_Cacheable_Repository and AIPS_Repository_Tables traits and declare a cache
 * group. Notifications caches its bell-badge reads under the broad `notifications`
 * tag; post-review and telemetry intentionally declare no cached policies (external
 * post-state dependency / hot per-request insert path), so the migration for those
 * is trait + table() standardization only. These assertions need only $wpdb->prefix.
 *
 * @package AI_Post_Scheduler
 */

class Test_AIPS_Phase4_5_Repository_Cache_Wiring extends WP_UnitTestCase {

	/**
	 * @dataProvider all_repositories_provider
	 */
	public function test_repository_uses_shared_traits($class, $expected_group) {
		$traits = class_uses($class);

		$this->assertContains('AIPS_Cacheable_Repository', $traits, "$class should use AIPS_Cacheable_Repository.");
		$this->assertContains('AIPS_Repository_Tables', $traits, "$class should use AIPS_Repository_Tables.");
	}

	/**
	 * @dataProvider all_repositories_provider
	 */
	public function test_repository_declares_group($class, $expected_group) {
		$repo = $this->instantiate($class);
		$this->assertSame($expected_group, $this->invoke_protected($repo, 'repository_cache_group'));
	}

	/**
	 * Notifications caches its bell reads under the broad `notifications` tag.
	 */
	public function test_notifications_declares_broad_tagged_policies() {
		$repo     = $this->instantiate('AIPS_Notifications_Repository');
		$policies = $this->invoke_protected($repo, 'repository_cache_policies');

		foreach (array('notifications.get_unread', 'notifications.count_unread') as $op) {
			$this->assertArrayHasKey($op, $policies, "Notifications should declare a policy for $op.");
			$this->assertContains('notifications', $policies[$op]['tags'], "$op must carry the broad 'notifications' tag.");
		}
	}

	/**
	 * Post-review and telemetry intentionally cache nothing (documented decision).
	 *
	 * @dataProvider uncached_repositories_provider
	 */
	public function test_uncached_repositories_declare_no_policies($class) {
		$repo = $this->instantiate($class);
		$this->assertSame(array(), $this->invoke_protected($repo, 'repository_cache_policies'));
	}

	public function all_repositories_provider() {
		return array(
			'post_review'   => array('AIPS_Post_Review_Repository', 'aips_post_review'),
			'notifications' => array('AIPS_Notifications_Repository', 'aips_notifications'),
			'telemetry'     => array('AIPS_Telemetry_Repository', 'aips_telemetry'),
		);
	}

	public function uncached_repositories_provider() {
		return array(
			'post_review' => array('AIPS_Post_Review_Repository'),
			'telemetry'   => array('AIPS_Telemetry_Repository'),
		);
	}

	/**
	 * Instantiate a repository, preferring its ::instance() singleton when present.
	 *
	 * @param string $class Repository class name.
	 * @return object
	 */
	private function instantiate($class) {
		if (method_exists($class, 'instance')) {
			return $class::instance();
		}
		return new $class();
	}

	/**
	 * Invoke a protected/private method for assertion purposes.
	 *
	 * @param object $object Target instance.
	 * @param string $method Method name.
	 * @return mixed
	 */
	private function invoke_protected($object, $method) {
		$ref = new ReflectionMethod($object, $method);
		$ref->setAccessible(true);
		return $ref->invoke($object);
	}
}
