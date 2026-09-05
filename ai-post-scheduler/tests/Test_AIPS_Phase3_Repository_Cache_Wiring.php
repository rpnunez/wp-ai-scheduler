<?php
/**
 * Structural regression tests for the Phase 3 (#1944) repository cache migration.
 *
 * Verifies that the Content Enrichment feature repositories (embeddings, internal
 * links, affiliate links, taxonomy) adopted both the AIPS_Cacheable_Repository and
 * AIPS_Repository_Tables traits, declare the expected cache group, and register
 * cache policies (each carrying the broad feature tag that guarantees invalidation
 * on writes). These assertions need only $wpdb->prefix, so they run without
 * database tables.
 *
 * @package AI_Post_Scheduler
 */

class Test_AIPS_Phase3_Repository_Cache_Wiring extends WP_UnitTestCase {

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
			'internal_links' => array(
				'AIPS_Internal_Links_Repository',
				'aips_internal_links',
				'internal_links',
				array(
					'internal_links.get_by_source_post',
					'internal_links.get_status_counts',
				),
			),
			'affiliate_links' => array(
				'AIPS_Affiliate_Links_Repository',
				'aips_affiliate_links',
				'affiliate_links',
				array(
					'affiliate_links.get_by_id',
					'affiliate_links.get_all',
					'affiliate_links.get_enabled_by_tags',
					'affiliate_links.get_paginated',
					'affiliate_links.get_paginated_count',
				),
			),
			'taxonomy' => array(
				'AIPS_Taxonomy_Repository',
				'aips_taxonomy',
				'taxonomy',
				array(
					'taxonomy.get_by_type',
					'taxonomy.get_by_status_and_type',
					'taxonomy.get_by_id',
					'taxonomy.get_status_counts',
					'taxonomy.search',
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
