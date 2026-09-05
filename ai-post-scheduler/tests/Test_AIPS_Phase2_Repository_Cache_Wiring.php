<?php
/**
 * Structural regression tests for the Phase 2 (#1944) repository cache migration.
 *
 * Verifies that the Sources feature repositories adopted both the
 * AIPS_Cacheable_Repository and AIPS_Repository_Tables traits, declare the
 * expected cache group, and register cache policies (each carrying the broad
 * feature tag that guarantees invalidation on writes). These assertions need only
 * $wpdb->prefix, so they run without database tables.
 *
 * @package AI_Post_Scheduler
 */

class Test_AIPS_Phase2_Repository_Cache_Wiring extends WP_UnitTestCase {

	/**
	 * @dataProvider migrated_repository_provider
	 */
	public function test_repository_uses_shared_traits($class) {
		$traits = class_uses($class);

		$this->assertContains('AIPS_Cacheable_Repository', $traits, "$class should use AIPS_Cacheable_Repository.");
		$this->assertContains('AIPS_Repository_Tables', $traits, "$class should use AIPS_Repository_Tables.");
	}

	/**
	 * @dataProvider migrated_repository_provider
	 */
	public function test_repository_declares_group_and_broad_tagged_policies($class, $expected_group, $broad_tag, array $expected_ops) {
		$repo = new $class();

		$this->assertSame($expected_group, $this->invoke_protected($repo, 'repository_cache_group'));

		$policies = $this->invoke_protected($repo, 'repository_cache_policies');
		$this->assertIsArray($policies);

		foreach ($expected_ops as $op) {
			$this->assertArrayHasKey($op, $policies, "$class should declare a policy for $op.");
			$this->assertArrayHasKey('tags', $policies[$op], "$op policy should declare tags.");
			$this->assertContains(
				$broad_tag,
				$policies[$op]['tags'],
				"$op policy must carry the broad '$broad_tag' tag so writes invalidate it."
			);
		}
	}

	public function migrated_repository_provider() {
		return array(
			'sources' => array(
				'AIPS_Sources_Repository',
				'aips_sources',
				'sources',
				array(
					'sources.get_all',
					'sources.get_by_id',
					'sources.get_active_urls',
					'sources.get_source_term_ids',
					'sources.get_term_ids_for_sources',
					'sources.get_urls_by_group_term_ids',
					'sources.get_by_group_term_ids',
				),
			),
			'sources_data' => array(
				'AIPS_Sources_Data_Repository',
				'aips_sources_data',
				'sources_data',
				array(
					'sources_data.get_count_by_source_id',
					'sources_data.get_counts_by_source_ids',
				),
			),
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
