<?php
/**
 * Structural regression tests for the Phase 6 + 7 (#1944) repository cache migration.
 *
 * Phase 6 (ai-assistance) and Phase 7 (cache-monitor, data-management) all adopt
 * the AIPS_Cacheable_Repository and AIPS_Repository_Tables traits and declare a
 * cache group. ai-assistance caches its suggestion-history reads under the broad
 * `ai_assistance` tag; cache-monitor and data-management intentionally declare no
 * cached policies (self-referential cache observability / destructive live-data
 * operations), so their migration is trait + table() standardization only. These
 * assertions need only $wpdb->prefix.
 *
 * @package AI_Post_Scheduler
 */

class Test_AIPS_Phase6_7_Repository_Cache_Wiring extends WP_UnitTestCase {

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
		$repo = new $class();
		$this->assertSame($expected_group, $this->invoke_protected($repo, 'repository_cache_group'));
	}

	/**
	 * ai-assistance caches its suggestion-history reads under the broad tag.
	 */
	public function test_ai_assistance_declares_broad_tagged_policies() {
		$repo     = new AIPS_AI_Assistance_Repository();
		$policies = $this->invoke_protected($repo, 'repository_cache_policies');

		foreach (array('ai_assistance.get_by_session_and_field', 'ai_assistance.get_by_field') as $op) {
			$this->assertArrayHasKey($op, $policies, "AI assistance should declare a policy for $op.");
			$this->assertContains('ai_assistance', $policies[$op]['tags'], "$op must carry the broad 'ai_assistance' tag.");
		}
	}

	/**
	 * cache-monitor and data-management intentionally cache nothing.
	 *
	 * @dataProvider uncached_repositories_provider
	 */
	public function test_uncached_repositories_declare_no_policies($class) {
		$repo = new $class();
		$this->assertSame(array(), $this->invoke_protected($repo, 'repository_cache_policies'));
	}

	public function all_repositories_provider() {
		return array(
			'ai_assistance'   => array('AIPS_AI_Assistance_Repository', 'aips_ai_assistance'),
			'cache_monitor'   => array('AIPS_Cache_Monitor_Repository', 'aips_cache_monitor'),
			'data_management' => array('AIPS_Data_Management_Repository', 'aips_data_management'),
		);
	}

	public function uncached_repositories_provider() {
		return array(
			'cache_monitor'   => array('AIPS_Cache_Monitor_Repository'),
			'data_management' => array('AIPS_Data_Management_Repository'),
		);
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
